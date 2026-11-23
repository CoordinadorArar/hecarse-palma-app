/* ============================================================================
   Liquidación preliminar a la temporal (Informes > Liquidación)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Agrega, si no existen, las columnas de dbo.Liquidacion:
          FechaCorte        DATE          NULL  (no nulo = liquidación preliminar)
          ValorReal         DECIMAL(18,2) NULL  (valor real del rango o hasta el corte)
          ValorEstimado     DECIMAL(18,2) NULL  (estimado después del corte)
          ValorAjuste       DECIMAL(18,2) NULL  (ajuste de preliminares anteriores)
          AjustadoEnInicio  DATE          NULL  (en la fila preliminar: rango de la
          AjustadoEnFin     DATE          NULL   liquidación que ya aplicó su ajuste)

     Los registros existentes quedan con NULL: la aplicación los interpreta
     como liquidaciones definitivas sin ajuste.

   Requiere haber ejecutado antes liquidacion_rango_fechas.sql
   (columnas FechaInicio y FechaFin).

   IDEMPOTENTE: se puede ejecutar varias veces.
   Archivo UTF-8: ejecutar desde SSMS o con
   "sqlcmd -f 65001 -i liquidacion_preliminar.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    IF COL_LENGTH('dbo.Liquidacion', 'FechaInicio') IS NULL OR COL_LENGTH('dbo.Liquidacion', 'FechaFin') IS NULL
        THROW 50001, N'Faltan las columnas FechaInicio/FechaFin: ejecute antes liquidacion_rango_fechas.sql.', 1;

    IF COL_LENGTH('dbo.Liquidacion', 'FechaCorte') IS NULL
        ALTER TABLE dbo.Liquidacion ADD FechaCorte DATE NULL;

    IF COL_LENGTH('dbo.Liquidacion', 'ValorReal') IS NULL
        ALTER TABLE dbo.Liquidacion ADD ValorReal DECIMAL(18, 2) NULL;

    IF COL_LENGTH('dbo.Liquidacion', 'ValorEstimado') IS NULL
        ALTER TABLE dbo.Liquidacion ADD ValorEstimado DECIMAL(18, 2) NULL;

    IF COL_LENGTH('dbo.Liquidacion', 'ValorAjuste') IS NULL
        ALTER TABLE dbo.Liquidacion ADD ValorAjuste DECIMAL(18, 2) NULL;

    IF COL_LENGTH('dbo.Liquidacion', 'AjustadoEnInicio') IS NULL
        ALTER TABLE dbo.Liquidacion ADD AjustadoEnInicio DATE NULL;

    IF COL_LENGTH('dbo.Liquidacion', 'AjustadoEnFin') IS NULL
        ALTER TABLE dbo.Liquidacion ADD AjustadoEnFin DATE NULL;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT COL_LENGTH('dbo.Liquidacion', 'FechaCorte')       AS FechaCorte,
       COL_LENGTH('dbo.Liquidacion', 'ValorReal')        AS ValorReal,
       COL_LENGTH('dbo.Liquidacion', 'ValorEstimado')    AS ValorEstimado,
       COL_LENGTH('dbo.Liquidacion', 'ValorAjuste')      AS ValorAjuste,
       COL_LENGTH('dbo.Liquidacion', 'AjustadoEnInicio') AS AjustadoEnInicio,
       COL_LENGTH('dbo.Liquidacion', 'AjustadoEnFin')    AS AjustadoEnFin;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   Se pierden las marcas de liquidación preliminar y los ajustes guardados.
   ----------------------------------------------------------------------------
IF COL_LENGTH('dbo.Liquidacion', 'AjustadoEnFin') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN AjustadoEnFin;

IF COL_LENGTH('dbo.Liquidacion', 'AjustadoEnInicio') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN AjustadoEnInicio;

IF COL_LENGTH('dbo.Liquidacion', 'ValorAjuste') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN ValorAjuste;

IF COL_LENGTH('dbo.Liquidacion', 'ValorEstimado') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN ValorEstimado;

IF COL_LENGTH('dbo.Liquidacion', 'ValorReal') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN ValorReal;

IF COL_LENGTH('dbo.Liquidacion', 'FechaCorte') IS NOT NULL
    ALTER TABLE dbo.Liquidacion DROP COLUMN FechaCorte;
---------------------------------------------------------------------------- */
