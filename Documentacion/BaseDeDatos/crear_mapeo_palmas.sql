/* ============================================================================
   Mapeo de palmas (Gestión Palma > Lotes > Mapeo de palmas e informe
   gerencial "Ciclos de cosecha / poda")
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Crea, si no existe, dbo.aPalma: una fila por palma georreferenciada
          (empresa, finca, lote, linea, palma) única, latitud/longitud WGS84,
          estado ACTIVA | ERRADICADA, fechaErradicacion, observacion y
          auditoría de registro/modificación.
          FK (empresa, lote, finca) -> aLotes (empresa, codigo, finca).
     2) Crea, si no existe, dbo.aLotesGeometria: polígono del lote en GeoJSON
          (Geometry Polygon/MultiPolygon, WGS84), área en ha y centroide.
          PK (empresa, finca, lote). FK -> aLotes.
     3) Crea, si no existe, dbo.aCensoPalma: histórico del censo sanitario
          por palma. FK palma -> aPalma(id) ON DELETE CASCADE;
          FK (empresa, caracteristica) -> aCaracteristica (empresa, codigo);
          FK (empresa, grupoCaracteristica) -> aGrupoCaracteristica (empresa, codigo).
     4) Crea los índices si no existen.

   NOTA: con estas FK, un lote con palmas o geometría registradas no se puede
   eliminar desde Registro de lotes hasta borrar antes su mapeo.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_mapeo_palmas.sql".
   Después: registrar_modulo_mapeo_palmas.sql y cargar_mapeo_palmas_cruceiro.sql.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    IF OBJECT_ID('dbo.aPalma', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.aPalma (
            id                  INT IDENTITY(1, 1) NOT NULL,
            empresa             INT            NOT NULL,
            finca               VARCHAR(50)    NOT NULL,
            lote                VARCHAR(50)    NOT NULL,
            linea               INT            NOT NULL,
            palma               INT            NOT NULL,
            latitud             DECIMAL(10, 7) NOT NULL,
            longitud            DECIMAL(10, 7) NOT NULL,
            estado              VARCHAR(15)    NOT NULL CONSTRAINT DF_aPalma_estado DEFAULT ('ACTIVA'),
            fechaErradicacion   DATE           NULL,
            observacion         VARCHAR(250)   NULL,
            usuarioRegistro     VARCHAR(50)    NOT NULL,
            fechaRegistro       DATETIME       NOT NULL CONSTRAINT DF_aPalma_fechaRegistro DEFAULT (GETDATE()),
            usuarioModificacion VARCHAR(50)    NULL,
            fechaModificacion   DATETIME       NULL,
            CONSTRAINT PK_aPalma PRIMARY KEY (id),
            CONSTRAINT UQ_aPalma_posicion UNIQUE (empresa, finca, lote, linea, palma),
            CONSTRAINT FK_aPalma_aLotes FOREIGN KEY (empresa, lote, finca) REFERENCES dbo.aLotes (empresa, codigo, finca),
            CONSTRAINT CK_aPalma_latitud CHECK (latitud BETWEEN -90 AND 90),
            CONSTRAINT CK_aPalma_longitud CHECK (longitud BETWEEN -180 AND 180),
            CONSTRAINT CK_aPalma_estado CHECK (estado IN ('ACTIVA', 'ERRADICADA')),
            CONSTRAINT CK_aPalma_numeracion CHECK (linea > 0 AND palma > 0)
        );

        PRINT N'Tabla dbo.aPalma creada.';
    END;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_aPalma_lote' AND object_id = OBJECT_ID('dbo.aPalma'))
    BEGIN
        CREATE INDEX IX_aPalma_lote ON dbo.aPalma (empresa, finca, lote) INCLUDE (linea, palma, latitud, longitud, estado);
        PRINT N'Índice IX_aPalma_lote creado.';
    END;

    IF OBJECT_ID('dbo.aLotesGeometria', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.aLotesGeometria (
            empresa             INT            NOT NULL,
            finca               VARCHAR(50)    NOT NULL,
            lote                VARCHAR(50)    NOT NULL,
            geojson             NVARCHAR(MAX)  NOT NULL,
            areaHa              DECIMAL(10, 2) NOT NULL,
            centroLat           DECIMAL(10, 7) NOT NULL,
            centroLon           DECIMAL(10, 7) NOT NULL,
            usuario             VARCHAR(50)    NOT NULL,
            fechaRegistro       DATETIME       NOT NULL CONSTRAINT DF_aLotesGeometria_fechaRegistro DEFAULT (GETDATE()),
            usuarioModificacion VARCHAR(50)    NULL,
            fechaModificacion   DATETIME       NULL,
            CONSTRAINT PK_aLotesGeometria PRIMARY KEY (empresa, finca, lote),
            CONSTRAINT FK_aLotesGeometria_aLotes FOREIGN KEY (empresa, lote, finca) REFERENCES dbo.aLotes (empresa, codigo, finca),
            CONSTRAINT CK_aLotesGeometria_centro CHECK (centroLat BETWEEN -90 AND 90 AND centroLon BETWEEN -180 AND 180),
            CONSTRAINT CK_aLotesGeometria_area CHECK (areaHa >= 0)
        );

        PRINT N'Tabla dbo.aLotesGeometria creada.';
    END;

    IF OBJECT_ID('dbo.aCensoPalma', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.aCensoPalma (
            id                  INT IDENTITY(1, 1) NOT NULL,
            empresa             INT          NOT NULL,
            palma               INT          NOT NULL,
            fecha               DATE         NOT NULL,
            grupoCaracteristica INT          NOT NULL,
            caracteristica      INT          NOT NULL,
            observacion         VARCHAR(250) NULL,
            usuarioRegistro     VARCHAR(50)  NOT NULL,
            fechaRegistro       DATETIME     NOT NULL CONSTRAINT DF_aCensoPalma_fechaRegistro DEFAULT (GETDATE()),
            CONSTRAINT PK_aCensoPalma PRIMARY KEY (id),
            CONSTRAINT FK_aCensoPalma_aPalma FOREIGN KEY (palma) REFERENCES dbo.aPalma (id) ON DELETE CASCADE,
            CONSTRAINT FK_aCensoPalma_aCaracteristica FOREIGN KEY (empresa, caracteristica) REFERENCES dbo.aCaracteristica (empresa, codigo),
            CONSTRAINT FK_aCensoPalma_aGrupoCaracteristica FOREIGN KEY (empresa, grupoCaracteristica) REFERENCES dbo.aGrupoCaracteristica (empresa, codigo)
        );

        PRINT N'Tabla dbo.aCensoPalma creada.';
    END;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_aCensoPalma_palma_fecha' AND object_id = OBJECT_ID('dbo.aCensoPalma'))
    BEGIN
        CREATE INDEX IX_aCensoPalma_palma_fecha ON dbo.aCensoPalma (palma, fecha DESC, id DESC) INCLUDE (caracteristica, grupoCaracteristica);
        PRINT N'Índice IX_aCensoPalma_palma_fecha creado.';
    END;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_aCensoPalma_caracteristica' AND object_id = OBJECT_ID('dbo.aCensoPalma'))
    BEGIN
        CREATE INDEX IX_aCensoPalma_caracteristica ON dbo.aCensoPalma (empresa, caracteristica);
        PRINT N'Índice IX_aCensoPalma_caracteristica creado.';
    END;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT t.name AS tabla, SUM(p.rows) AS filas
FROM sys.tables t
JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
WHERE t.name IN ('aPalma', 'aLotesGeometria', 'aCensoPalma')
GROUP BY t.name
ORDER BY t.name;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (borra las tablas y TODOS sus datos). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.aCensoPalma', 'U') IS NOT NULL
    DROP TABLE dbo.aCensoPalma;

IF OBJECT_ID('dbo.aPalma', 'U') IS NOT NULL
    DROP TABLE dbo.aPalma;

IF OBJECT_ID('dbo.aLotesGeometria', 'U') IS NOT NULL
    DROP TABLE dbo.aLotesGeometria;
---------------------------------------------------------------------------- */
