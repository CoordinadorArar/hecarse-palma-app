/* ============================================================================
   Planeación de labores (Transacciones > Planeación de labores)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Crea, si no existen, las tablas del plan diario de labores:
       1) dbo.pPlaneacion            Encabezado: un plan por empresa y fecha.
       2) dbo.pPlaneacionLinea       Actividades del plan (labor + finca/lote,
                                     meta, estado de cierre, traslado de
                                     pendientes y transacción vinculada).
       3) dbo.pPlaneacionTrabajador  Trabajadores asignados a cada actividad.
       4) dbo.pPlaneacionAusencia    Trabajadores ausentes en el día.

   La restricción "un trabajador no repite la misma labor en el mismo lote el
   mismo día" se valida en el backend (involucra tres tablas); dentro de una
   actividad el trabajador es único por UQ_pPlaneacionTrabajador.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_planeacion_labores.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    IF OBJECT_ID('dbo.pPlaneacion', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.pPlaneacion (
            id                  INT IDENTITY(1, 1) NOT NULL,
            empresa             INT                NOT NULL,
            fecha               DATE               NOT NULL,
            estado              VARCHAR(15)        NOT NULL CONSTRAINT DF_pPlaneacion_estado DEFAULT ('ABIERTA'),
            observacion         VARCHAR(500)       NULL,
            usuarioRegistro     VARCHAR(50)        NOT NULL,
            fechaRegistro       DATETIME           NOT NULL CONSTRAINT DF_pPlaneacion_fechaRegistro DEFAULT (GETDATE()),
            usuarioModificacion VARCHAR(50)        NULL,
            fechaModificacion   DATETIME           NULL,
            CONSTRAINT PK_pPlaneacion PRIMARY KEY (id),
            CONSTRAINT UQ_pPlaneacion_empresa_fecha UNIQUE (empresa, fecha),
            CONSTRAINT CK_pPlaneacion_estado CHECK (estado IN ('ABIERTA', 'CERRADA'))
        );

        PRINT N'Tabla dbo.pPlaneacion creada.';
    END;

    IF OBJECT_ID('dbo.pPlaneacionLinea', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.pPlaneacionLinea (
            id                INT IDENTITY(1, 1) NOT NULL,
            planeacion        INT                NOT NULL,
            orden             INT                NOT NULL CONSTRAINT DF_pPlaneacionLinea_orden DEFAULT (0),
            finca             VARCHAR(50)        NOT NULL,
            lote              VARCHAR(50)        NOT NULL,
            novedad           VARCHAR(50)        NOT NULL,
            uMedida           VARCHAR(50)        NOT NULL,
            cantidad          DECIMAL(18, 2)     NOT NULL CONSTRAINT DF_pPlaneacionLinea_cantidad DEFAULT (0),
            horaInicio        TIME(0)            NULL,
            horaFin           TIME(0)            NULL,
            observacion       VARCHAR(250)       NULL,
            referencia        VARCHAR(50)        NULL,
            estado            VARCHAR(15)        NOT NULL CONSTRAINT DF_pPlaneacionLinea_estado DEFAULT ('PENDIENTE'),
            cantidadEjecutada DECIMAL(18, 2)     NULL,
            observacionCierre VARCHAR(250)       NULL,
            usuarioCierre     VARCHAR(50)        NULL,
            fechaCierre       DATETIME           NULL,
            origen            INT                NULL,
            tipoTransaccion   VARCHAR(50)        NULL,
            numeroTransaccion VARCHAR(50)        NULL,
            CONSTRAINT PK_pPlaneacionLinea PRIMARY KEY (id),
            CONSTRAINT FK_pPlaneacionLinea_pPlaneacion FOREIGN KEY (planeacion) REFERENCES dbo.pPlaneacion (id) ON DELETE CASCADE,
            CONSTRAINT FK_pPlaneacionLinea_origen FOREIGN KEY (origen) REFERENCES dbo.pPlaneacionLinea (id),
            CONSTRAINT CK_pPlaneacionLinea_estado CHECK (estado IN ('PENDIENTE', 'COMPLETADA', 'PARCIAL', 'NO_EJECUTADA')),
            CONSTRAINT CK_pPlaneacionLinea_cantidad CHECK (cantidad >= 0 AND (cantidadEjecutada IS NULL OR cantidadEjecutada >= 0)),
            CONSTRAINT CK_pPlaneacionLinea_horas CHECK ((horaInicio IS NULL AND horaFin IS NULL)
                OR (horaInicio IS NOT NULL AND horaFin IS NOT NULL AND horaFin > horaInicio))
        );

        CREATE INDEX IX_pPlaneacionLinea_planeacion ON dbo.pPlaneacionLinea (planeacion, orden);
        CREATE INDEX IX_pPlaneacionLinea_origen ON dbo.pPlaneacionLinea (origen);
        CREATE INDEX IX_pPlaneacionLinea_transaccion ON dbo.pPlaneacionLinea (tipoTransaccion, numeroTransaccion);

        PRINT N'Tabla dbo.pPlaneacionLinea creada.';
    END;

    IF OBJECT_ID('dbo.pPlaneacionTrabajador', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.pPlaneacionTrabajador (
            id           INT IDENTITY(1, 1) NOT NULL,
            linea        INT                NOT NULL,
            tercero      VARCHAR(50)        NOT NULL,
            cantidad     DECIMAL(18, 2)     NOT NULL CONSTRAINT DF_pPlaneacionTrabajador_cantidad DEFAULT (0),
            reasignadoDe VARCHAR(50)        NULL,
            orden        INT                NOT NULL CONSTRAINT DF_pPlaneacionTrabajador_orden DEFAULT (0),
            CONSTRAINT PK_pPlaneacionTrabajador PRIMARY KEY (id),
            CONSTRAINT FK_pPlaneacionTrabajador_linea FOREIGN KEY (linea) REFERENCES dbo.pPlaneacionLinea (id) ON DELETE CASCADE,
            CONSTRAINT UQ_pPlaneacionTrabajador_linea_tercero UNIQUE (linea, tercero),
            CONSTRAINT CK_pPlaneacionTrabajador_cantidad CHECK (cantidad >= 0)
        );

        CREATE INDEX IX_pPlaneacionTrabajador_tercero ON dbo.pPlaneacionTrabajador (tercero);

        PRINT N'Tabla dbo.pPlaneacionTrabajador creada.';
    END;

    IF OBJECT_ID('dbo.pPlaneacionAusencia', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.pPlaneacionAusencia (
            planeacion INT          NOT NULL,
            tercero    VARCHAR(50)  NOT NULL,
            motivo     VARCHAR(250) NULL,
            usuario    VARCHAR(50)  NOT NULL,
            fecha      DATETIME     NOT NULL CONSTRAINT DF_pPlaneacionAusencia_fecha DEFAULT (GETDATE()),
            CONSTRAINT PK_pPlaneacionAusencia PRIMARY KEY (planeacion, tercero),
            CONSTRAINT FK_pPlaneacionAusencia_pPlaneacion FOREIGN KEY (planeacion) REFERENCES dbo.pPlaneacion (id) ON DELETE CASCADE
        );

        PRINT N'Tabla dbo.pPlaneacionAusencia creada.';
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
WHERE t.name IN ('pPlaneacion', 'pPlaneacionLinea', 'pPlaneacionTrabajador', 'pPlaneacionAusencia')
GROUP BY t.name
ORDER BY t.name;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (borra los planes registrados). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.pPlaneacionAusencia', 'U') IS NOT NULL
    DROP TABLE dbo.pPlaneacionAusencia;

IF OBJECT_ID('dbo.pPlaneacionTrabajador', 'U') IS NOT NULL
    DROP TABLE dbo.pPlaneacionTrabajador;

IF OBJECT_ID('dbo.pPlaneacionLinea', 'U') IS NOT NULL
    DROP TABLE dbo.pPlaneacionLinea;

IF OBJECT_ID('dbo.pPlaneacion', 'U') IS NOT NULL
    DROP TABLE dbo.pPlaneacion;
---------------------------------------------------------------------------- */
