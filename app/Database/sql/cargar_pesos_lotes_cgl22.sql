/* ============================================================================
   Carga de pesos de racimo: lotes Cañaguate siembra 2022 (CGL10-22 a CGL14-22)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Inserta en dbo.aLotePesosPeriodo (empresa 1, finca '003', sin sección) las
     filas que faltan para los lotes CGL10-22, CGL11-22, CGL12-22, CGL13-22 y
     CGL14-22 (solo si no existen por llave primaria; es idempotente):
       - 2026-10: peso 2.600, automatico 0 (copia de la BD Infos).
       - 2026-11 a 2030-12: automatico 1, peso por lote igual al promedio
         ponderado kg/racimos de la cosecha jul-sep 2026:
           CGL10-22 3.026 | CGL11-22 3.086 | CGL12-22 2.751
           CGL13-22 2.595 | CGL14-22 2.463
     fechaInicial/fechaFinal = primer y último día de cada mes.
     Total esperado: 255 filas (51 meses x 5 lotes).

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): inserta dentro de una transacción, muestra el
     resultado y hace ROLLBACK. @Aplicar = 1: hace COMMIT.

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8 y contiene columnas [año]. Ejecutarlo SOLO desde SSMS
   o con "sqlcmd -f 65001 -i cargar_pesos_lotes_cgl22.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT = 0;

BEGIN TRY
    BEGIN TRAN;

    WITH m AS (
        SELECT CAST('20261001' AS date) AS f
        UNION ALL
        SELECT DATEADD(MONTH, 1, f) FROM m WHERE f < '20301201'
    )
    INSERT INTO dbo.aLotePesosPeriodo (empresa, [año], mes, finca, seccion, lote, pesoRacimo, automatico, fechaInicial, fechaFinal)
    SELECT 1, YEAR(m.f), MONTH(m.f), '003', NULL, v.lote,
           CASE WHEN m.f = '20261001' THEN 2.600 ELSE v.peso END,
           CASE WHEN m.f = '20261001' THEN 0 ELSE 1 END,
           m.f, DATEADD(DAY, -1, DATEADD(MONTH, 1, m.f))
    FROM m
    CROSS JOIN (VALUES ('CGL10-22', 3.026), ('CGL11-22', 3.086), ('CGL12-22', 2.751),
                       ('CGL13-22', 2.595), ('CGL14-22', 2.463)) v (lote, peso)
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.aLotePesosPeriodo p
        WHERE p.empresa = 1 AND p.[año] = YEAR(m.f) AND p.mes = MONTH(m.f)
          AND p.finca = '003' AND p.lote = v.lote
    );

    SELECT @@ROWCOUNT AS filasInsertadas;

    SELECT lote,
           MIN([año] * 100 + mes) AS periodoMinimo,
           MAX([año] * 100 + mes) AS periodoMaximo,
           COUNT(*) AS filas,
           MIN(pesoRacimo) AS pesoMinimo,
           MAX(pesoRacimo) AS pesoMaximo
    FROM dbo.aLotePesosPeriodo
    WHERE empresa = 1 AND finca = '003'
      AND lote IN ('CGL10-22', 'CGL11-22', 'CGL12-22', 'CGL13-22', 'CGL14-22')
      AND [año] * 100 + mes >= 202610
    GROUP BY lote
    ORDER BY lote;

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
