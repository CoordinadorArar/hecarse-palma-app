/* ============================================================================
   Carga del presupuesto de producción 2026 POR LOTE
   ----------------------------------------------------------------------------
   GENERADO AUTOMÁTICAMENTE el 2026-10-21 14:09 con
   Documentacion/BaseDeDatos/herramientas/generar_carga_presupuesto_lotes_2026.php
   a partir de Documentacion/DATOS PRODUCCION 2018 - 2026.xlsx, hoja
   "Cumplimiento al ppto", filas "Ppto 2026" de cada finca. Los valores
   mensuales son KILOS (aunque la hoja diga Ton), redondeados a 2 decimales.

   VALORES DE LA HOJA (kg)
     002 CRUCEIRO (fila 10): anual 5164354.60 kg (columna N de la hoja: 5164354.60)
         826296.74 877940.28 826296.74 568079.01 413148.37 258217.73 206574.18 206574.18 206574.18 206574.18 258217.73 309861.28
     001 HATO (fila 16): anual 1235768.77 kg (columna N de la hoja: 1235768.77)
         172307.69 161586.79 163987.71 124671.03 85932.74 62023.15 66334.71 63733.79 76729.62 73846.15 86153.85 98461.54
     003 CAÑAGUATE (fila 22): anual 762652.31 kg (columna N de la hoja: 762652.30)
         83922.62 114439.93 114439.93 114439.93 114439.93 45775.97 38146.64 22887.99 22887.99 30517.31 30340.15 30413.92
     Total empresa (fila 4): anual 7162775.68 kg, enero 1082527.04 kg
     Nota: cada mes se redondea a 2 decimales; la suma anual puede diferir en centavos de la columna N.

   QUÉ HACE
   --------
     1) Valida que las fincas existen en aFinca y que cada una tiene al menos
        un lote activo con hNetas > 0 (si no, THROW con la lista).
     2) Reparte los kilos de cada finca y mes entre los lotes ACTIVOS de la
        finca con hNetas > 0, proporcional a hNetas, ROUND a 2 decimales.
        El residuo se asigna al lote de mayor hNetas; si fuera negativo y
        dejara un lote por debajo de 0, se descuenta en orden de mayor área
        sin bajar de 0. La suma por finca y mes cuadra exacta con la hoja.
        (Misma lógica que PresupuestoProduccionModel::distribucion.)
     3) En una transacción BORRA todas las filas 2026 de esas fincas en
        dbo.aPresupuestoProduccion (metas de finca con lote = '' y filas por
        lote) e inserta el reparto (solo kilos > 0). Usuario: MIGRACION.
     4) Muestra el resumen por finca y mes y el detalle por lote.
   IDEMPOTENTE: se puede ejecutar varias veces (borra y vuelve a insertar).
   Las hectáreas y los lotes se toman de aLotes al momento de ejecutar.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resumen y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i cargar_presupuesto_lotes_2026.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT         = 0;
DECLARE @Empresa INT         = 1;
DECLARE @Anio    INT         = 2026;
DECLARE @Usuario VARCHAR(50) = 'MIGRACION';

IF OBJECT_ID('dbo.aPresupuestoProduccion', 'U') IS NULL
    THROW 50001, N'Ejecute primero crear_presupuesto_produccion.sql.', 1;

IF OBJECT_ID('tempdb..#lotes') IS NOT NULL DROP TABLE #lotes;
IF OBJECT_ID('tempdb..#reparto') IS NOT NULL DROP TABLE #reparto;

DECLARE @hoja TABLE (empresa INT, anio INT, finca VARCHAR(50), mes TINYINT, kilos DECIMAL(18, 2), PRIMARY KEY (finca, mes));

INSERT INTO @hoja (empresa, anio, finca, mes, kilos) VALUES
    (1, 2026, '002',  1, 826296.74),
    (1, 2026, '002',  2, 877940.28),
    (1, 2026, '002',  3, 826296.74),
    (1, 2026, '002',  4, 568079.01),
    (1, 2026, '002',  5, 413148.37),
    (1, 2026, '002',  6, 258217.73),
    (1, 2026, '002',  7, 206574.18),
    (1, 2026, '002',  8, 206574.18),
    (1, 2026, '002',  9, 206574.18),
    (1, 2026, '002', 10, 206574.18),
    (1, 2026, '002', 11, 258217.73),
    (1, 2026, '002', 12, 309861.28),
    (1, 2026, '001',  1, 172307.69),
    (1, 2026, '001',  2, 161586.79),
    (1, 2026, '001',  3, 163987.71),
    (1, 2026, '001',  4, 124671.03),
    (1, 2026, '001',  5, 85932.74),
    (1, 2026, '001',  6, 62023.15),
    (1, 2026, '001',  7, 66334.71),
    (1, 2026, '001',  8, 63733.79),
    (1, 2026, '001',  9, 76729.62),
    (1, 2026, '001', 10, 73846.15),
    (1, 2026, '001', 11, 86153.85),
    (1, 2026, '001', 12, 98461.54),
    (1, 2026, '003',  1, 83922.62),
    (1, 2026, '003',  2, 114439.93),
    (1, 2026, '003',  3, 114439.93),
    (1, 2026, '003',  4, 114439.93),
    (1, 2026, '003',  5, 114439.93),
    (1, 2026, '003',  6, 45775.97),
    (1, 2026, '003',  7, 38146.64),
    (1, 2026, '003',  8, 22887.99),
    (1, 2026, '003',  9, 22887.99),
    (1, 2026, '003', 10, 30517.31),
    (1, 2026, '003', 11, 30340.15),
    (1, 2026, '003', 12, 30413.92);

IF EXISTS (SELECT 1 FROM (SELECT DISTINCT empresa, finca FROM @hoja) h WHERE NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = h.empresa AND LTRIM(RTRIM(f.codigo)) = h.finca))
BEGIN
    SELECT DISTINCT h.finca AS fincaNoEncontrada FROM @hoja h
     WHERE NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = h.empresa AND LTRIM(RTRIM(f.codigo)) = h.finca);
    THROW 50002, N'Hay fincas de la carga que no existen en aFinca para la empresa.', 1;
END;

SELECT l.empresa, LTRIM(RTRIM(l.finca)) AS finca, LTRIM(RTRIM(l.codigo)) AS lote,
       LTRIM(RTRIM(ISNULL(l.descripcion, ''))) AS descripcion, CAST(l.hNetas AS FLOAT) AS hNetas,
       ROW_NUMBER() OVER (PARTITION BY LTRIM(RTRIM(l.finca)) ORDER BY l.hNetas DESC, LTRIM(RTRIM(l.codigo))) AS orden
  INTO #lotes
  FROM aLotes l
 WHERE l.empresa = @Empresa AND LTRIM(RTRIM(l.finca)) IN ('002', '001', '003') AND l.activo = 1 AND ISNULL(l.hNetas, 0) > 0;

IF EXISTS (SELECT 1 FROM (SELECT DISTINCT finca FROM @hoja) h WHERE NOT EXISTS (SELECT 1 FROM #lotes l WHERE l.finca = h.finca))
BEGIN
    SELECT DISTINCT h.finca AS fincaSinLotesConArea FROM @hoja h WHERE NOT EXISTS (SELECT 1 FROM #lotes l WHERE l.finca = h.finca);
    THROW 50003, N'Hay fincas sin lotes activos con hNetas > 0.', 1;
END;

IF EXISTS (SELECT finca, lote FROM #lotes GROUP BY finca, lote HAVING COUNT(*) > 1)
BEGIN
    SELECT finca, lote, COUNT(*) AS veces FROM #lotes GROUP BY finca, lote HAVING COUNT(*) > 1;
    THROW 50004, N'Hay códigos de lote repetidos en la misma finca.', 1;
END;

WITH base AS (
    SELECT h.finca, h.mes, h.kilos, l.lote, l.orden,
           CAST(ROUND(CAST(h.kilos AS FLOAT) * l.hNetas / SUM(l.hNetas) OVER (PARTITION BY h.finca, h.mes), 2) AS DECIMAL(18, 2)) AS kilosBase
      FROM @hoja h
      JOIN #lotes l ON l.finca = h.finca
), residuo AS (
    SELECT b.*,
           b.kilos - SUM(b.kilosBase) OVER (PARTITION BY b.finca, b.mes) AS resto,
           SUM(b.kilosBase) OVER (PARTITION BY b.finca, b.mes ORDER BY b.orden ROWS UNBOUNDED PRECEDING) - b.kilosBase AS previo
      FROM base b
)
SELECT finca, mes, lote,
       CAST(kilosBase + CASE
            WHEN resto >= 0 THEN CASE WHEN orden = 1 THEN resto ELSE 0 END
            WHEN -resto <= previo THEN 0
            WHEN -resto - previo >= kilosBase THEN -kilosBase
            ELSE resto + previo
       END AS DECIMAL(18, 2)) AS kilos
  INTO #reparto
  FROM residuo;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM dbo.aPresupuestoProduccion
     WHERE empresa = @Empresa AND anio = @Anio AND finca IN ('002', '001', '003');

    PRINT CONCAT(N'Filas eliminadas (lote y metas de finca): ', @@ROWCOUNT);

    INSERT INTO dbo.aPresupuestoProduccion (empresa, anio, mes, finca, lote, kilos, usuario)
    SELECT @Empresa, @Anio, mes, finca, lote, kilos, @Usuario
      FROM #reparto
     WHERE kilos > 0;

    PRINT CONCAT(N'Filas por lote insertadas: ', @@ROWCOUNT);

    SELECT h.finca, h.mes, h.kilos AS kilosHoja,
           ISNULL(p.kilos, 0) AS kilosRepartidos,
           h.kilos - ISNULL(p.kilos, 0) AS diferencia,
           l.lotes, l.hectareas
      FROM @hoja h
      OUTER APPLY (SELECT SUM(x.kilos) AS kilos FROM dbo.aPresupuestoProduccion x
                    WHERE x.empresa = @Empresa AND x.anio = @Anio AND x.finca = h.finca AND x.mes = h.mes) p
      OUTER APPLY (SELECT COUNT(*) AS lotes, CAST(SUM(y.hNetas) AS DECIMAL(18, 2)) AS hectareas FROM #lotes y WHERE y.finca = h.finca) l
     ORDER BY h.finca, h.mes;

    SELECT l.finca, l.lote, l.descripcion, CAST(l.hNetas AS DECIMAL(18, 2)) AS hNetas,
           CAST(l.hNetas * 100 / SUM(l.hNetas) OVER (PARTITION BY l.finca) AS DECIMAL(9, 4)) AS participacion,
           SUM(CASE WHEN p.mes = 1 THEN p.kilos ELSE 0 END) AS ene,
           SUM(CASE WHEN p.mes = 2 THEN p.kilos ELSE 0 END) AS feb,
           SUM(CASE WHEN p.mes = 3 THEN p.kilos ELSE 0 END) AS mar,
           SUM(CASE WHEN p.mes = 4 THEN p.kilos ELSE 0 END) AS abr,
           SUM(CASE WHEN p.mes = 5 THEN p.kilos ELSE 0 END) AS may,
           SUM(CASE WHEN p.mes = 6 THEN p.kilos ELSE 0 END) AS jun,
           SUM(CASE WHEN p.mes = 7 THEN p.kilos ELSE 0 END) AS jul,
           SUM(CASE WHEN p.mes = 8 THEN p.kilos ELSE 0 END) AS ago,
           SUM(CASE WHEN p.mes = 9 THEN p.kilos ELSE 0 END) AS sep,
           SUM(CASE WHEN p.mes = 10 THEN p.kilos ELSE 0 END) AS oct,
           SUM(CASE WHEN p.mes = 11 THEN p.kilos ELSE 0 END) AS nov,
           SUM(CASE WHEN p.mes = 12 THEN p.kilos ELSE 0 END) AS dic,
           ISNULL(SUM(p.kilos), 0) AS total
      FROM #lotes l
      LEFT JOIN dbo.aPresupuestoProduccion p ON p.empresa = @Empresa AND p.anio = @Anio AND p.finca = l.finca AND p.lote = l.lote
     GROUP BY l.finca, l.lote, l.descripcion, l.hNetas, l.orden
     ORDER BY l.finca, l.orden;

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
