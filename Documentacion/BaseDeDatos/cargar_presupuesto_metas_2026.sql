/* ============================================================================
   Carga inicial de metas y curva de estacionalidad del presupuesto 2026
   ----------------------------------------------------------------------------
   GENERADO AUTOMÁTICAMENTE el 2026-10-20 19:59 con
   Documentacion/BaseDeDatos/herramientas/generar_carga_presupuesto_metas.php
   a partir de Documentacion/DATOS PRODUCCION 2018 - 2026.xlsx:
     - hoja "Cumplimiento al ppto", filas "Ppto 2026" de cada finca:
         kilosAnual = suma de los 12 meses;
         porcentaje del mes = mes / anual x 100 (4 decimales, el último mes
         se ajusta para que la curva sume exactamente 100).
     - hoja " tonha 12 ulti", celda C52: meta t/ha de la empresa.
       kilosAnual de la empresa = suma de las fincas.

   REQUISITO: haber ejecutado crear_presupuesto_metas.sql.

   VALORES
     Empresa 1 (finca ''): kilosAnual 7162775.67, metaTonHa 24.00
     002 CRUCEIRO (fila 10): kilosAnual 5164354.60, suma curva 100.0000
         % 16.0000 17.0000 16.0000 11.0000 8.0000 5.0000 4.0000 4.0000 4.0000 4.0000 5.0000 6.0000
     001 HATO (fila 16): kilosAnual 1235768.77, suma curva 100.0000
         % 13.9434 13.0758 13.2701 10.0885 6.9538 5.0190 5.3679 5.1574 6.2091 5.9757 6.9717 7.9676
     003 CAÑAGUATE (fila 22): kilosAnual 762652.30, suma curva 100.0000
         % 11.0040 15.0055 15.0055 15.0055 15.0055 6.0022 5.0018 3.0011 3.0011 4.0015 3.9782 3.9881

   QUÉ HACE
   --------
     1) Valida que las tablas existen y que las fincas existen en aFinca.
     2) MERGE en dbo.aPresupuestoMeta por (empresa, anio, finca): inserta o
        actualiza kilosAnual y metaTonHa.
     3) Reemplaza la curva de dbo.aPresupuestoCurva de esas fincas en el año
        (borra e inserta los 12 meses).
   NO modifica dbo.aPresupuestoProduccion (el presupuesto por lote se genera
   desde el módulo con la acción "Generar").
   IDEMPOTENTE: se puede ejecutar varias veces. Usuario de registro: MIGRACION.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resumen y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i cargar_presupuesto_metas_2026.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT         = 0;
DECLARE @Empresa INT         = 1;
DECLARE @Anio    INT         = 2026;
DECLARE @Usuario VARCHAR(50) = 'MIGRACION';

IF OBJECT_ID('dbo.aPresupuestoMeta', 'U') IS NULL OR OBJECT_ID('dbo.aPresupuestoCurva', 'U') IS NULL
    THROW 50001, N'Ejecute primero crear_presupuesto_metas.sql.', 1;

DECLARE @metas TABLE (empresa INT, anio INT, finca VARCHAR(50), kilosAnual DECIMAL(18, 2), metaTonHa DECIMAL(9, 2));
DECLARE @curva TABLE (empresa INT, anio INT, finca VARCHAR(50), mes TINYINT, porcentaje DECIMAL(9, 4));

INSERT INTO @metas (empresa, anio, finca, kilosAnual, metaTonHa) VALUES
    (1, 2026, '', 7162775.67, 24.00),
    (1, 2026, '002', 5164354.60, NULL),
    (1, 2026, '001', 1235768.77, NULL),
    (1, 2026, '003', 762652.30, NULL);

INSERT INTO @curva (empresa, anio, finca, mes, porcentaje) VALUES
    (1, 2026, '002',  1, 16.0000),
    (1, 2026, '002',  2, 17.0000),
    (1, 2026, '002',  3, 16.0000),
    (1, 2026, '002',  4, 11.0000),
    (1, 2026, '002',  5, 8.0000),
    (1, 2026, '002',  6, 5.0000),
    (1, 2026, '002',  7, 4.0000),
    (1, 2026, '002',  8, 4.0000),
    (1, 2026, '002',  9, 4.0000),
    (1, 2026, '002', 10, 4.0000),
    (1, 2026, '002', 11, 5.0000),
    (1, 2026, '002', 12, 6.0000),
    (1, 2026, '001',  1, 13.9434),
    (1, 2026, '001',  2, 13.0758),
    (1, 2026, '001',  3, 13.2701),
    (1, 2026, '001',  4, 10.0885),
    (1, 2026, '001',  5, 6.9538),
    (1, 2026, '001',  6, 5.0190),
    (1, 2026, '001',  7, 5.3679),
    (1, 2026, '001',  8, 5.1574),
    (1, 2026, '001',  9, 6.2091),
    (1, 2026, '001', 10, 5.9757),
    (1, 2026, '001', 11, 6.9717),
    (1, 2026, '001', 12, 7.9676),
    (1, 2026, '003',  1, 11.0040),
    (1, 2026, '003',  2, 15.0055),
    (1, 2026, '003',  3, 15.0055),
    (1, 2026, '003',  4, 15.0055),
    (1, 2026, '003',  5, 15.0055),
    (1, 2026, '003',  6, 6.0022),
    (1, 2026, '003',  7, 5.0018),
    (1, 2026, '003',  8, 3.0011),
    (1, 2026, '003',  9, 3.0011),
    (1, 2026, '003', 10, 4.0015),
    (1, 2026, '003', 11, 3.9782),
    (1, 2026, '003', 12, 3.9881);

IF EXISTS (SELECT 1 FROM @metas m WHERE m.finca <> '' AND NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = m.empresa AND LTRIM(RTRIM(f.codigo)) = m.finca))
BEGIN
    SELECT m.finca AS fincaNoEncontrada FROM @metas m
     WHERE m.finca <> '' AND NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = m.empresa AND LTRIM(RTRIM(f.codigo)) = m.finca);
    THROW 50002, N'Hay fincas de la carga que no existen en aFinca para la empresa.', 1;
END;

IF EXISTS (SELECT finca FROM @curva GROUP BY finca HAVING COUNT(*) <> 12 OR SUM(porcentaje) <> 100)
    THROW 50003, N'La curva de alguna finca no tiene 12 meses o no suma 100.', 1;

BEGIN TRY

    BEGIN TRAN;

    MERGE dbo.aPresupuestoMeta AS t
    USING @metas AS s
       ON t.empresa = s.empresa AND t.anio = s.anio AND t.finca = s.finca
     WHEN MATCHED THEN
          UPDATE SET kilosAnual = s.kilosAnual, metaTonHa = s.metaTonHa,
                     usuarioModificacion = @Usuario, fechaModificacion = GETDATE()
     WHEN NOT MATCHED THEN
          INSERT (empresa, anio, finca, kilosAnual, metaTonHa, usuario)
          VALUES (s.empresa, s.anio, s.finca, s.kilosAnual, s.metaTonHa, @Usuario);

    PRINT CONCAT(N'Metas insertadas/actualizadas: ', @@ROWCOUNT);

    DELETE FROM dbo.aPresupuestoCurva
     WHERE empresa = @Empresa AND anio = @Anio AND finca IN ('002', '001', '003');

    INSERT INTO dbo.aPresupuestoCurva (empresa, anio, finca, mes, porcentaje, usuario)
    SELECT empresa, anio, finca, mes, porcentaje, @Usuario FROM @curva;

    PRINT CONCAT(N'Meses de curva insertados: ', @@ROWCOUNT);

    SELECT m.finca, m.kilosAnual, m.metaTonHa,
           (SELECT COUNT(*) FROM dbo.aPresupuestoCurva c WHERE c.empresa = m.empresa AND c.anio = m.anio AND c.finca = m.finca) AS mesesCurva,
           (SELECT SUM(porcentaje) FROM dbo.aPresupuestoCurva c WHERE c.empresa = m.empresa AND c.anio = m.anio AND c.finca = m.finca) AS sumaCurva
      FROM dbo.aPresupuestoMeta m
     WHERE m.empresa = @Empresa AND m.anio = @Anio
     ORDER BY m.finca;

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'Carga confirmada (COMMIT).';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: se revirtió todo. Cambie @Aplicar = 1 para confirmar.';
    END;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0 ROLLBACK;

    THROW;

END CATCH;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL de esta carga. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;
BEGIN TRAN;
DELETE FROM dbo.aPresupuestoCurva WHERE empresa = 1 AND anio = 2026 AND finca IN ('002', '001', '003');
DELETE FROM dbo.aPresupuestoMeta WHERE empresa = 1 AND anio = 2026 AND finca IN ('', '002', '001', '003');
COMMIT;
---------------------------------------------------------------------------- */
