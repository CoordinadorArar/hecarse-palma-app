/* ============================================================================
   Histórico de producción (Tablero de producción)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Crea, si no existe, la tabla dbo.aProduccionHistorica con la producción
     mensual por lote de años anteriores a la báscula del aplicativo:
       empresa, anio, mes (1..12), finca (aFinca.codigo), lote (aLotes.codigo),
       palmas, hectareas, siembra, variedad, kilos, origen, usuario, fechaRegistro.
       PK (empresa, anio, mes, finca, lote). Índice (empresa, finca, anio).

   USO EN EL APLICATIVO
   --------------------
     Para cada empresa/finca/año/mes, si hay producción de báscula (TLC) > 0
     se usa la báscula; si no, se usa este histórico.

   La carga de datos se hace con cargar_produccion_historica.sql.
   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_produccion_historica.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID('dbo.aProduccionHistorica', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.aProduccionHistorica (
        empresa       INT            NOT NULL,
        anio          INT            NOT NULL,
        mes           TINYINT        NOT NULL,
        finca         VARCHAR(50)    NOT NULL,
        lote          VARCHAR(50)    NOT NULL,
        palmas        INT            NULL,
        hectareas     DECIMAL(18, 4) NULL,
        siembra       INT            NULL,
        variedad      VARCHAR(100)   NULL,
        kilos         DECIMAL(18, 2) NOT NULL,
        origen        VARCHAR(50)    NOT NULL,
        usuario       VARCHAR(50)    NULL,
        fechaRegistro DATETIME       NOT NULL CONSTRAINT DF_aProduccionHistorica_fechaRegistro DEFAULT (GETDATE()),
        CONSTRAINT PK_aProduccionHistorica PRIMARY KEY (empresa, anio, mes, finca, lote),
        CONSTRAINT CK_aProduccionHistorica_mes CHECK (mes BETWEEN 1 AND 12)
    );

    PRINT N'Tabla dbo.aProduccionHistorica creada.';
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.aProduccionHistorica') AND name = 'IX_aProduccionHistorica_empresa_finca_anio')
BEGIN
    CREATE INDEX IX_aProduccionHistorica_empresa_finca_anio ON dbo.aProduccionHistorica (empresa, finca, anio) INCLUDE (mes, lote, kilos, hectareas);

    PRINT N'Índice IX_aProduccionHistorica_empresa_finca_anio creado.';
END;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.aProduccionHistorica', 'U') IS NOT NULL
    DROP TABLE dbo.aProduccionHistorica;
---------------------------------------------------------------------------- */
