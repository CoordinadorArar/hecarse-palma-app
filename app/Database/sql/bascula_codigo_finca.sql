/* ============================================================================
   Código de finca en registros de báscula (API api/receive-data-bascula)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Agrega a dbo.bBasculaRegistro las columnas:
       coEquipo VARCHAR(3): centro de operación del equipo que envía el lote
                            (campo codigoFinca del JSON). Forma parte de la PK.
       coFinca  VARCHAR(3): centro de operación de la finca dueña del peso.
     Backfill de las filas existentes (todas enviadas por el equipo de
     Cruceiro, CO 003):
       coEquipo = '003'.
       coFinca  = '004' si el origen es solo Cañaguate ('CAÑAGUATE',
                  'CANAGUATE' o 'CG'); '002' si es solo El Hato ('EL HATO'
                  o 'FINCA EL HATO'); el resto '003'.
     Deja coEquipo NOT NULL y cambia PK_bBasculaRegistro de (numero) a
     (coEquipo, numero), porque cada equipo tiene su propia numeración.

   USO EN EL APLICATIVO
   --------------------
     Mientras las columnas no existan, el endpoint responde 503 sin insertar.
     El informe de báscula usa coFinca para asignar la finca de los viajes
     sin TLC.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i bascula_codigo_finca.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY
    BEGIN TRANSACTION;

    IF OBJECT_ID('dbo.bBasculaRegistro', 'U') IS NULL
        THROW 50001, N'No existe dbo.bBasculaRegistro. Ejecutar primero crear_bascula_registros.sql.', 1;

    IF COL_LENGTH('dbo.bBasculaRegistro', 'coEquipo') IS NULL
    BEGIN
        ALTER TABLE dbo.bBasculaRegistro ADD coEquipo VARCHAR(3) NULL;
        PRINT N'Columna coEquipo agregada.';
    END;

    IF COL_LENGTH('dbo.bBasculaRegistro', 'coFinca') IS NULL
    BEGIN
        ALTER TABLE dbo.bBasculaRegistro ADD coFinca VARCHAR(3) NULL;
        PRINT N'Columna coFinca agregada.';
    END;

    EXEC sp_executesql N'
        UPDATE dbo.bBasculaRegistro SET coEquipo = ''003'' WHERE coEquipo IS NULL;
        PRINT CONCAT(N''coEquipo asignado: '', @@ROWCOUNT, N'' filas.'');

        UPDATE dbo.bBasculaRegistro
           SET coFinca = CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(origen, '''')))) IN (N''CA'' + NCHAR(209) + N''AGUATE'', N''CANAGUATE'', N''CG'') THEN ''004''
                              WHEN UPPER(LTRIM(RTRIM(ISNULL(origen, '''')))) IN (N''EL HATO'', N''FINCA EL HATO'') THEN ''002''
                              ELSE ''003'' END
         WHERE coFinca IS NULL;
        PRINT CONCAT(N''coFinca asignado: '', @@ROWCOUNT, N'' filas.'');';

    IF COLUMNPROPERTY(OBJECT_ID('dbo.bBasculaRegistro'), 'coEquipo', 'AllowsNull') = 1
    BEGIN
        EXEC sp_executesql N'ALTER TABLE dbo.bBasculaRegistro ALTER COLUMN coEquipo VARCHAR(3) NOT NULL;';
        PRINT N'Columna coEquipo NOT NULL.';
    END;

    IF NOT EXISTS (
        SELECT 1
          FROM sys.indexes i
          JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
          JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
         WHERE i.object_id = OBJECT_ID('dbo.bBasculaRegistro') AND i.is_primary_key = 1 AND c.name = 'coEquipo'
    )
    BEGIN
        IF OBJECT_ID('dbo.PK_bBasculaRegistro', 'PK') IS NOT NULL
            ALTER TABLE dbo.bBasculaRegistro DROP CONSTRAINT PK_bBasculaRegistro;

        EXEC sp_executesql N'ALTER TABLE dbo.bBasculaRegistro ADD CONSTRAINT PK_bBasculaRegistro PRIMARY KEY (coEquipo, numero);';
        PRINT N'PK_bBasculaRegistro (coEquipo, numero) creada.';
    END;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRANSACTION;

    THROW;
END CATCH;
GO

SELECT origen, coEquipo, coFinca, COUNT(*) AS filas
  FROM dbo.bBasculaRegistro
 GROUP BY origen, coEquipo, coFinca
 ORDER BY coEquipo, coFinca, origen;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   ATENCIÓN: elimina las filas enviadas por equipos distintos al 003, porque
   la PK (numero) no admite números repetidos entre equipos.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;
BEGIN TRANSACTION;
DELETE FROM dbo.bBasculaRegistro WHERE coEquipo <> '003';
ALTER TABLE dbo.bBasculaRegistro DROP CONSTRAINT PK_bBasculaRegistro;
ALTER TABLE dbo.bBasculaRegistro ADD CONSTRAINT PK_bBasculaRegistro PRIMARY KEY (numero);
ALTER TABLE dbo.bBasculaRegistro DROP COLUMN coEquipo, coFinca;
COMMIT TRANSACTION;
---------------------------------------------------------------------------- */
