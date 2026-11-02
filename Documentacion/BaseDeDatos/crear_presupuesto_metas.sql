/* ============================================================================
   Metas anuales y curva de estacionalidad del presupuesto de producción
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Crea la tabla dbo.aPresupuestoMeta si no existe:
          empresa, anio, finca ('' = meta de la empresa), kilosAnual,
          metaTonHa, usuario, fechaRegistro, usuarioModificacion,
          fechaModificacion.
          PK (empresa, anio, finca).
     2) Crea la tabla dbo.aPresupuestoCurva si no existe:
          empresa, anio, finca, mes (1..12), porcentaje (>= 0), usuario,
          fechaRegistro.
          PK (empresa, anio, finca, mes).
          Los 12 meses de una finca deben sumar 100 (lo valida la aplicación).

   Con la meta anual y la curva, el módulo "Presupuesto de producción" genera
   el presupuesto mensual por lote en dbo.aPresupuestoProduccion
   (kilos del mes = kilosAnual x porcentaje / 100, repartido por hNetas).

   REQUISITO: haber ejecutado crear_presupuesto_produccion.sql.
   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_presupuesto_metas.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID('dbo.aPresupuestoMeta', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.aPresupuestoMeta (
        empresa             INT            NOT NULL,
        anio                INT            NOT NULL,
        finca               VARCHAR(50)    NOT NULL CONSTRAINT DF_aPresupuestoMeta_finca DEFAULT (''),
        kilosAnual          DECIMAL(18, 2) NULL,
        metaTonHa           DECIMAL(9, 2)  NULL,
        usuario             VARCHAR(50)    NULL,
        fechaRegistro       DATETIME       NOT NULL CONSTRAINT DF_aPresupuestoMeta_fechaRegistro DEFAULT (GETDATE()),
        usuarioModificacion VARCHAR(50)    NULL,
        fechaModificacion   DATETIME       NULL,
        CONSTRAINT PK_aPresupuestoMeta PRIMARY KEY (empresa, anio, finca),
        CONSTRAINT CK_aPresupuestoMeta_kilosAnual CHECK (kilosAnual IS NULL OR kilosAnual >= 0),
        CONSTRAINT CK_aPresupuestoMeta_metaTonHa CHECK (metaTonHa IS NULL OR metaTonHa >= 0)
    );

    PRINT N'Tabla dbo.aPresupuestoMeta creada.';
END;

IF OBJECT_ID('dbo.aPresupuestoCurva', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.aPresupuestoCurva (
        empresa       INT           NOT NULL,
        anio          INT           NOT NULL,
        finca         VARCHAR(50)   NOT NULL,
        mes           TINYINT       NOT NULL,
        porcentaje    DECIMAL(9, 4) NOT NULL,
        usuario       VARCHAR(50)   NULL,
        fechaRegistro DATETIME      NOT NULL CONSTRAINT DF_aPresupuestoCurva_fechaRegistro DEFAULT (GETDATE()),
        CONSTRAINT PK_aPresupuestoCurva PRIMARY KEY (empresa, anio, finca, mes),
        CONSTRAINT CK_aPresupuestoCurva_mes CHECK (mes BETWEEN 1 AND 12),
        CONSTRAINT CK_aPresupuestoCurva_porcentaje CHECK (porcentaje >= 0)
    );

    PRINT N'Tabla dbo.aPresupuestoCurva creada.';
END;

SELECT OBJECT_ID('dbo.aPresupuestoMeta', 'U') AS aPresupuestoMeta,
       OBJECT_ID('dbo.aPresupuestoCurva', 'U') AS aPresupuestoCurva;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.aPresupuestoCurva', 'U') IS NOT NULL
    DROP TABLE dbo.aPresupuestoCurva;
IF OBJECT_ID('dbo.aPresupuestoMeta', 'U') IS NOT NULL
    DROP TABLE dbo.aPresupuestoMeta;
---------------------------------------------------------------------------- */
