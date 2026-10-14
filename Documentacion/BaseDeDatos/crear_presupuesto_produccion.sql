/* ============================================================================
   Presupuesto de producción (informe gerencial "Presupuesto vs Ejecutado")
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Crea la tabla dbo.aPresupuestoProduccion si no existe:
          empresa, anio, mes (1..12), finca, lote ('' = presupuesto a nivel
          finca), kilos, usuario, fechaRegistro.
          PK (empresa, anio, mes, finca, lote).
     2) Inserta, si no existe, el presupuesto 2026 de la empresa 1 a nivel
        finca, tomado de Documentacion/PresupuestoEjecutadoCorteAgosto.xlsx
        (hoja "Cumplimiento al ppto", filas "Ppto 2026"):
          002 CRUCEIRO, 001 EL HATO, 003 CAÑAGUATE (12 meses cada una).

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_presupuesto_produccion.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID('dbo.aPresupuestoProduccion', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.aPresupuestoProduccion (
        empresa       INT            NOT NULL,
        anio          INT            NOT NULL,
        mes           TINYINT        NOT NULL,
        finca         VARCHAR(50)    NOT NULL,
        lote          VARCHAR(50)    NOT NULL CONSTRAINT DF_aPresupuestoProduccion_lote DEFAULT (''),
        kilos         DECIMAL(18, 2) NOT NULL,
        usuario       VARCHAR(50)    NULL,
        fechaRegistro DATETIME       NOT NULL CONSTRAINT DF_aPresupuestoProduccion_fechaRegistro DEFAULT (GETDATE()),
        CONSTRAINT PK_aPresupuestoProduccion PRIMARY KEY (empresa, anio, mes, finca, lote),
        CONSTRAINT CK_aPresupuestoProduccion_mes CHECK (mes BETWEEN 1 AND 12)
    );

    PRINT N'Tabla dbo.aPresupuestoProduccion creada.';
END;

DECLARE @presupuesto TABLE (empresa INT, anio INT, mes TINYINT, finca VARCHAR(50), lote VARCHAR(50), kilos DECIMAL(18, 2));

INSERT INTO @presupuesto (empresa, anio, mes, finca, lote, kilos)
VALUES (1, 2026,  1, '002', '', 826296.74),
       (1, 2026,  2, '002', '', 877940.28),
       (1, 2026,  3, '002', '', 826296.74),
       (1, 2026,  4, '002', '', 568079.01),
       (1, 2026,  5, '002', '', 413148.37),
       (1, 2026,  6, '002', '', 258217.73),
       (1, 2026,  7, '002', '', 206574.18),
       (1, 2026,  8, '002', '', 206574.18),
       (1, 2026,  9, '002', '', 206574.18),
       (1, 2026, 10, '002', '', 206574.18),
       (1, 2026, 11, '002', '', 258217.73),
       (1, 2026, 12, '002', '', 309861.28),
       (1, 2026,  1, '001', '', 172307.69),
       (1, 2026,  2, '001', '', 161586.79),
       (1, 2026,  3, '001', '', 163987.71),
       (1, 2026,  4, '001', '', 124671.03),
       (1, 2026,  5, '001', '', 85932.74),
       (1, 2026,  6, '001', '', 62023.15),
       (1, 2026,  7, '001', '', 66334.71),
       (1, 2026,  8, '001', '', 63733.79),
       (1, 2026,  9, '001', '', 76729.62),
       (1, 2026, 10, '001', '', 73846.15),
       (1, 2026, 11, '001', '', 86153.85),
       (1, 2026, 12, '001', '', 98461.54),
       (1, 2026,  1, '003', '', 83922.62),
       (1, 2026,  2, '003', '', 114439.93),
       (1, 2026,  3, '003', '', 114439.93),
       (1, 2026,  4, '003', '', 114439.93),
       (1, 2026,  5, '003', '', 114439.93),
       (1, 2026,  6, '003', '', 45775.97),
       (1, 2026,  7, '003', '', 38146.64),
       (1, 2026,  8, '003', '', 22887.99),
       (1, 2026,  9, '003', '', 22887.99),
       (1, 2026, 10, '003', '', 30517.31),
       (1, 2026, 11, '003', '', 30340.15),
       (1, 2026, 12, '003', '', 30413.92);

BEGIN TRY

    BEGIN TRAN;

    INSERT INTO dbo.aPresupuestoProduccion (empresa, anio, mes, finca, lote, kilos, usuario)
    SELECT p.empresa, p.anio, p.mes, p.finca, p.lote, p.kilos, 'script'
      FROM @presupuesto p
     WHERE NOT EXISTS (SELECT 1 FROM dbo.aPresupuestoProduccion x
                        WHERE x.empresa = p.empresa AND x.anio = p.anio AND x.mes = p.mes
                          AND x.finca = p.finca AND x.lote = p.lote);

    PRINT CONCAT(N'Filas de presupuesto insertadas: ', @@ROWCOUNT);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT finca, COUNT(*) AS meses, SUM(kilos) AS kilos
FROM dbo.aPresupuestoProduccion
WHERE empresa = 1 AND anio = 2026
GROUP BY finca
ORDER BY finca;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.aPresupuestoProduccion', 'U') IS NOT NULL
    DROP TABLE dbo.aPresupuestoProduccion;
---------------------------------------------------------------------------- */
