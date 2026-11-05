/* ============================================================================
   Rango de fechas en dbo.Liquidacion (Informes > Liquidación)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Agrega, si no existen, las columnas:
          FechaInicio  DATE NULL
          FechaFin     DATE NULL
     2) Los registros existentes quedan con NULL; la aplicación los interpreta
        como el mes completo de Año/Mes. El backfill opcional (al final,
        comentado) los llena explícitamente.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Archivo UTF-8: ejecutar desde SSMS o con
   "sqlcmd -f 65001 -i liquidacion_rango_fechas.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    IF COL_LENGTH('dbo.Liquidacion', 'FechaInicio') IS NULL
        ALTER TABLE dbo.Liquidacion ADD FechaInicio DATE NULL;

    IF COL_LENGTH('dbo.Liquidacion', 'FechaFin') IS NULL
        ALTER TABLE dbo.Liquidacion ADD FechaFin DATE NULL;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT COL_LENGTH('dbo.Liquidacion', 'FechaInicio') AS FechaInicio,
       COL_LENGTH('dbo.Liquidacion', 'FechaFin')    AS FechaFin;

/* ----------------------------------------------------------------------------
   BACKFILL OPCIONAL. Se ejecuta aparte, después de la primera parte: una vez
   creadas las columnas, descomentar, seleccionar solo este bloque y ejecutarlo.
   Llena FechaInicio/FechaFin de los registros heredados con el mes completo
   de Año/Mes. @Aplicar = 0 hace ROLLBACK; @Aplicar = 1 hace COMMIT.
   ----------------------------------------------------------------------------
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT = 0;

BEGIN TRY

    BEGIN TRAN;

    UPDATE dbo.Liquidacion
       SET FechaInicio = DATEFROMPARTS(TRY_CAST([Año] AS INT), TRY_CAST(Mes AS INT), 1),
           FechaFin    = EOMONTH(DATEFROMPARTS(TRY_CAST([Año] AS INT), TRY_CAST(Mes AS INT), 1))
     WHERE FechaInicio IS NULL
       AND FechaFin IS NULL
       AND TRY_CAST([Año] AS INT) BETWEEN 2000 AND 2100
       AND TRY_CAST(Mes AS INT) BETWEEN 1 AND 12;

    PRINT CONCAT(N'Registros actualizados: ', @@ROWCOUNT);

    SELECT [Año], Mes, FechaInicio, FechaFin, COUNT(*) AS Registros
      FROM dbo.Liquidacion
     GROUP BY [Año], Mes, FechaInicio, FechaFin
     ORDER BY [Año], Mes;

    IF @Aplicar = 1
    BEGIN
        COMMIT TRAN;
        PRINT N'APLICADO: cambios confirmados.';
    END
    ELSE
    BEGIN
        ROLLBACK TRAN;
        PRINT N'MODO PRUEBA: se revirtió todo. Cambie @Aplicar = 1 para confirmar.';
    END

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF COL_LENGTH('dbo.Liquidacion', 'FechaFin') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN FechaFin;

IF COL_LENGTH('dbo.Liquidacion', 'FechaInicio') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN FechaInicio;
---------------------------------------------------------------------------- */
