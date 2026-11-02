/* ============================================================================
   Creación de lotes históricos inactivos: SIEM-21, SIEM-22 y CGL-22
   ----------------------------------------------------------------------------
   ORIGEN
   ------
     Lotes huérfanos de Infos: fueron borrados de dbo.aLotes al reemplazarlos,
     pero siguen referenciados en aTransaccionItem, aTransaccionNovedad,
     aTransaccionTercero y aLotePesosPeriodo (y por sincronización en AppPalma):
       - SIEM-21 (2022-11 a 2024-01) -> reemplazado por CRL14A-21 / CRL 14B-21.
       - SIEM-22 (2023-01 a 2025-08) y CGL-22 (2025-09 a 2025-10) ->
         reemplazados por CGL10-22 a CGL14-22.
     Palmas: noPalmas de las transacciones de fertilización (CGL-22 = suma de
     CGL10..14-22). Hectáreas = palmas / 143.

   QUÉ HACE
   --------
     Inserta en dbo.aLotes (empresa 1) los 3 lotes con activo = 0, para que los
     informes resuelvan esos movimientos. Ojo: los selectores de captura de
     Labores y Producción no filtran por activo, así que también aparecerán
     allí (igual que hoy SIEM-26 y SIEMC-24). Solo inserta si no existe
     (empresa, codigo, finca); es idempotente:
       SIEM-21 | finca 002 | 2021 | 5123 palmas | 35.83 ha | desarrollo 1
       SIEM-22 | finca 003 | 2022 | 4747 palmas | 33.20 ha | desarrollo 1
       CGL-22  | finca 003 | 2022 | 4602 palmas | 32.18 ha | desarrollo 0
     Total esperado: 3 filas.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): inserta dentro de una transacción, muestra el
     resultado y hace ROLLBACK. @Aplicar = 1: hace COMMIT.

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8 y contiene columnas [añoSiembra] y texto con Ñ.
   Ejecutarlo SOLO desde SSMS o con
   "sqlcmd -f 65001 -i crear_lotes_historicos_infos.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT = 0;

BEGIN TRY
    BEGIN TRAN;

    INSERT INTO dbo.aLotes (empresa, codigo, manejaSeccion, seccion, finca, descripcion, [añoSiembra], mesSiembra,
                            palmasBrutas, palmasProduccion, hBrutas, hNetas, dSiembra, variedad, densidad, NoLineas,
                            fechaRegistro, usuario, foto, activo, desarrollo, numero, letra, ccosto)
    SELECT 1, v.codigo, 0, NULL, v.finca, v.descripcion, v.anio, 1,
           v.palmas, v.palmas, v.ha, v.ha, v.ha, '001', 143, 1,
           GETDATE(), 'Admin', NULL, 0, v.desarrollo, NULL, NULL, NULL
    FROM (VALUES ('SIEM-21', '002', N'SIEMBRA 2021', 2021, 5123, 35.83, 1),
                 ('SIEM-22', '003', N'SIEMBRA 2022', 2022, 4747, 33.20, 1),
                 ('CGL-22', '003', N'LOTE CAÑAGUATE SIEMBRA 2022', 2022, 4602, 32.18, 0))
         v (codigo, finca, descripcion, anio, palmas, ha, desarrollo)
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.aLotes l
        WHERE l.empresa = 1 AND l.codigo = v.codigo AND l.finca = v.finca
    );

    SELECT @@ROWCOUNT AS filasInsertadas;

    SELECT empresa, codigo, finca, descripcion, [añoSiembra], palmasBrutas, palmasProduccion,
           hBrutas, hNetas, dSiembra, densidad, activo, desarrollo
    FROM dbo.aLotes
    WHERE empresa = 1
      AND ((codigo = 'SIEM-21' AND finca = '002')
        OR (codigo IN ('SIEM-22', 'CGL-22') AND finca = '003'))
    ORDER BY finca, codigo;

    IF @Aplicar = 1
        COMMIT;
    ELSE
        ROLLBACK;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK;
    THROW;
END CATCH;
