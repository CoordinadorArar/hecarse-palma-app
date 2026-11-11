/* ============================================================================
   Log de peticiones de báscula (API api/receive-data-bascula)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Crea, si no existe, la tabla dbo.bBasculaLog con una fila por cada
     petición recibida en el endpoint POST api/receive-data-bascula, tanto
     exitosas (200) como fallidas (401, 400, 503, 500).
       ip, estadoHttp, ok, error, tabla, codigoFinca, lote, conteos del lote
       (recibidas, insertadas, existentes, omitidas, fechasInvalidas), rango
       de consecutivos (numeroMin, numeroMax), duracionMs y detalle (extracto
       del cuerpo en fallas o filas afectadas en recepciones exitosas).
       Índices por fecha y por (codigoFinca, fecha).

   USO EN EL APLICATIVO
   --------------------
     Si la tabla no existe el endpoint sigue funcionando y escribe el log en
     el archivo de logs de CodeIgniter.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_bascula_log.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID('dbo.bBasculaLog', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.bBasculaLog (
        id              INT           IDENTITY(1, 1) NOT NULL,
        fecha           DATETIME      NOT NULL CONSTRAINT DF_bBasculaLog_fecha DEFAULT (GETDATE()),
        ip              VARCHAR(45)   NULL,
        estadoHttp      SMALLINT      NOT NULL,
        ok              BIT           NOT NULL,
        error           VARCHAR(500)  NULL,
        tabla           VARCHAR(50)   NULL,
        codigoFinca     VARCHAR(10)   NULL,
        lote            INT           NULL,
        recibidas       INT           NULL,
        insertadas      INT           NULL,
        existentes      INT           NULL,
        omitidas        INT           NULL,
        fechasInvalidas INT           NULL,
        numeroMin       INT           NULL,
        numeroMax       INT           NULL,
        duracionMs      INT           NULL,
        detalle         NVARCHAR(MAX) NULL,
        CONSTRAINT PK_bBasculaLog PRIMARY KEY (id)
    );

    PRINT N'Tabla dbo.bBasculaLog creada.';
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_bBasculaLog_fecha' AND object_id = OBJECT_ID('dbo.bBasculaLog'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_bBasculaLog_fecha ON dbo.bBasculaLog (fecha);

    PRINT N'Índice IX_bBasculaLog_fecha creado.';
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_bBasculaLog_codigoFinca_fecha' AND object_id = OBJECT_ID('dbo.bBasculaLog'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_bBasculaLog_codigoFinca_fecha ON dbo.bBasculaLog (codigoFinca, fecha);

    PRINT N'Índice IX_bBasculaLog_codigoFinca_fecha creado.';
END;

/* ----------------------------------------------------------------------------
   CONSULTAS DE VALIDACIÓN. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
-- Últimas 50 peticiones de un equipo (codigoFinca)
SELECT TOP 50 fecha, ip, estadoHttp, ok, error, lote, recibidas, insertadas, existentes, omitidas, fechasInvalidas, numeroMin, numeroMax, duracionMs
FROM dbo.bBasculaLog
WHERE codigoFinca = '001'
ORDER BY fecha DESC;

-- Fallas de los últimos 7 días
SELECT fecha, ip, estadoHttp, error, tabla, codigoFinca, lote, recibidas, detalle
FROM dbo.bBasculaLog
WHERE ok = 0 AND fecha >= DATEADD(DAY, -7, GETDATE())
ORDER BY fecha DESC;

-- Última recepción exitosa por equipo
SELECT codigoFinca, MAX(fecha) AS ultimaRecepcion, COUNT(*) AS peticionesOk, SUM(insertadas) AS insertadas
FROM dbo.bBasculaLog
WHERE ok = 1
GROUP BY codigoFinca
ORDER BY ultimaRecepcion;

-- Huecos de consecutivo por equipo en dbo.bBasculaRegistro
SELECT coEquipo, numeroAnterior + 1 AS desde, numero - 1 AS hasta, numero - numeroAnterior - 1 AS faltantes
FROM (
    SELECT coEquipo, numero, LAG(numero) OVER (PARTITION BY coEquipo ORDER BY numero) AS numeroAnterior
    FROM dbo.bBasculaRegistro
) r
WHERE numero - numeroAnterior > 1
ORDER BY coEquipo, desde;
---------------------------------------------------------------------------- */

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.bBasculaLog', 'U') IS NOT NULL
    DROP TABLE dbo.bBasculaLog;
---------------------------------------------------------------------------- */
