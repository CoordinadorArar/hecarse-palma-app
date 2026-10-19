/* ============================================================================
   Planeación automática sugerida (Transacciones > Planeación de labores)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Crea, si no existe, dbo.pPlaneacionSugerencia: decisiones del usuario
     sobre las labores sugeridas por lote (vencidas o próximas según el último
     registro de labores o el plan de fertilización PFA vigente).

       base    Fecha del último pase de la labor en el lote ('AAAA-MM-DD') o
               'PFA:<numero>' para las sugerencias de fertilización.
       origen  CATALOGO (ciclo de aNovedad), HISTORIAL (frecuencia estimada)
               o PLAN (plan de fertilización PFA).
       estado  APROBADA (se creó la actividad "linea" en la planeación) o
               RECHAZADA (motivo obligatorio).

   Una sugerencia decidida no se vuelve a sugerir mientras no cambie su base
   (un registro nuevo de la labor en el lote genera una base distinta).
   Si la actividad aprobada se elimina del plan, "linea" queda en NULL.

   Requiere las tablas de crear_planeacion_labores.sql.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_planeacion_sugerencias.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY

    IF OBJECT_ID('dbo.pPlaneacionLinea', 'U') IS NULL
        THROW 50000, N'No existe dbo.pPlaneacionLinea: ejecute primero crear_planeacion_labores.sql.', 1;

    BEGIN TRAN;

    IF OBJECT_ID('dbo.pPlaneacionSugerencia', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.pPlaneacionSugerencia (
            id           INT IDENTITY(1, 1) NOT NULL,
            empresa      INT                NOT NULL,
            finca        VARCHAR(50)        NOT NULL,
            lote         VARCHAR(50)        NOT NULL,
            novedad      VARCHAR(50)        NOT NULL,
            base         VARCHAR(60)        NOT NULL,
            origen       VARCHAR(15)        NOT NULL,
            ciclo        INT                NULL,
            fechaProxima DATE               NULL,
            estado       VARCHAR(15)        NOT NULL,
            motivo       NVARCHAR(250)      NULL,
            linea        INT                NULL,
            usuario      VARCHAR(50)        NOT NULL,
            fecha        DATETIME           NOT NULL CONSTRAINT DF_pPlaneacionSugerencia_fecha DEFAULT (GETDATE()),
            CONSTRAINT PK_pPlaneacionSugerencia PRIMARY KEY (id),
            CONSTRAINT UQ_pPlaneacionSugerencia UNIQUE (empresa, finca, lote, novedad, base),
            CONSTRAINT FK_pPlaneacionSugerencia_linea FOREIGN KEY (linea) REFERENCES dbo.pPlaneacionLinea (id) ON DELETE SET NULL,
            CONSTRAINT CK_pPlaneacionSugerencia_estado CHECK (estado IN ('APROBADA', 'RECHAZADA')),
            CONSTRAINT CK_pPlaneacionSugerencia_origen CHECK (origen IN ('CATALOGO', 'HISTORIAL', 'PLAN')),
            CONSTRAINT CK_pPlaneacionSugerencia_motivo CHECK (estado <> 'RECHAZADA' OR LEN(motivo) >= 5)
        );

        CREATE INDEX IX_pPlaneacionSugerencia_fecha ON dbo.pPlaneacionSugerencia (empresa, fecha);
        CREATE INDEX IX_pPlaneacionSugerencia_linea ON dbo.pPlaneacionSugerencia (linea);

        PRINT N'Tabla dbo.pPlaneacionSugerencia creada.';
    END;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT t.name AS tabla, COUNT(c.column_id) AS columnas
FROM sys.tables t
JOIN sys.columns c ON c.object_id = t.object_id
WHERE t.name = 'pPlaneacionSugerencia'
GROUP BY t.name;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (borra las decisiones registradas). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.pPlaneacionSugerencia', 'U') IS NOT NULL
    DROP TABLE dbo.pPlaneacionSugerencia;
---------------------------------------------------------------------------- */
