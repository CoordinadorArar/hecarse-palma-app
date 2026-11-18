/* ============================================================================
   Unidades de negocio (Gestión Palma > Unidades de negocio)
   ----------------------------------------------------------------------------
   Jerarquía: Empresa -> Finca -> Unidad de negocio -> Lote.

   QUÉ HACE
   --------
     1) Crea, si no existen:
          dbo.aUnidadNegocio        Catálogo de unidades de negocio por empresa.
          dbo.aFincaUnidadNegocio   Unidades de negocio habilitadas en cada finca.
     2) Siembra para la empresa @Empresa: PAL PALMA, BUF BUFALOS,
        GTR GANADERIA TRADICIONAL, GCE GANADERIA CEBA.
     3) Agrega, si no existe, dbo.aLotes.unidadNegocio VARCHAR(10) NULL.
     4) Backfill (solo lotes con unidadNegocio NULL de @Empresa):
          CRLLC2-25 -> BUF, DGCR26-01 -> GTR, DGCR26-02 -> GCE,
          DGHT26-01 -> GTR (SUPUESTO A CONFIRMAR), resto -> PAL.
        Habilita en aFincaUnidadNegocio cada (finca, unidad) usada por algún
        lote, más PAL en todas las fincas activas.
     5) Crea FK_aLotes_aFincaUnidadNegocio (empresa, finca, unidadNegocio):
        la unidad del lote debe estar habilitada en su finca.
     6) Registra el módulo "Unidades de negocio" (gestion-palma/unidades-negocio,
        bi bi-boxes) en la loseta con Ruta gestion-palma/onboarding, al mismo
        nivel (IdModulo) que "Secciones / Bloques" (gestion-palma/secciones),
        Orden al final de sus hermanos, y copia en ModulosRoles los roles
        activos de ese módulo.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_unidades_negocio.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Empresa      INT = 1;
DECLARE @Usuario      VARCHAR(50) = 'MIGRACION';
DECLARE @IdUsuario    INT = 1;
DECLARE @Fecha        DATETIME = GETDATE();
DECLARE @Ruta         VARCHAR(200) = 'gestion-palma/unidades-negocio';
DECLARE @IdLoseta     INT;
DECLARE @IdContenedor INT;
DECLARE @IdHermano    INT;
DECLARE @IdNuevo      INT;

-- Asignación inicial de lotes no palma (el resto queda en PAL).
IF OBJECT_ID('tempdb..#mapa') IS NOT NULL
    DROP TABLE #mapa;

CREATE TABLE #mapa (lote VARCHAR(50) NOT NULL PRIMARY KEY, unidad VARCHAR(10) NOT NULL);

INSERT INTO #mapa (lote, unidad)
VALUES ('CRLLC2-25', 'BUF'),
       ('DGCR26-01', 'GTR'),
       ('DGCR26-02', 'GCE'),
       ('DGHT26-01', 'GTR');   -- GANADERIA HATO (finca 001): CONFIRMAR

BEGIN TRY

    BEGIN TRAN;

    IF NOT EXISTS (SELECT 1 FROM dbo.gEmpresa WHERE id = @Empresa)
        THROW 50001, N'No existe la empresa indicada en @Empresa.', 1;

    IF OBJECT_ID('dbo.aUnidadNegocio', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.aUnidadNegocio (
            empresa         INT          NOT NULL,
            codigo          VARCHAR(10)  NOT NULL,
            descripcion     VARCHAR(100) NOT NULL,
            activo          BIT          NOT NULL CONSTRAINT DF_aUnidadNegocio_activo DEFAULT (1),
            fechaRegistro   DATETIME     NOT NULL CONSTRAINT DF_aUnidadNegocio_fechaRegistro DEFAULT (GETDATE()),
            usuarioRegistro VARCHAR(50)  NULL,
            CONSTRAINT PK_aUnidadNegocio PRIMARY KEY (empresa, codigo),
            CONSTRAINT FK_aUnidadNegocio_gEmpresa FOREIGN KEY (empresa) REFERENCES dbo.gEmpresa (id)
        );

        PRINT N'Tabla dbo.aUnidadNegocio creada.';
    END;

    IF OBJECT_ID('dbo.aFincaUnidadNegocio', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.aFincaUnidadNegocio (
            empresa       INT         NOT NULL,
            finca         VARCHAR(50) NOT NULL,
            unidadNegocio VARCHAR(10) NOT NULL,
            CONSTRAINT PK_aFincaUnidadNegocio PRIMARY KEY (empresa, finca, unidadNegocio),
            CONSTRAINT FK_aFincaUnidadNegocio_aFinca FOREIGN KEY (empresa, finca) REFERENCES dbo.aFinca (empresa, codigo),
            CONSTRAINT FK_aFincaUnidadNegocio_aUnidadNegocio FOREIGN KEY (empresa, unidadNegocio) REFERENCES dbo.aUnidadNegocio (empresa, codigo)
        );

        CREATE INDEX IX_aFincaUnidadNegocio_unidad ON dbo.aFincaUnidadNegocio (empresa, unidadNegocio);

        PRINT N'Tabla dbo.aFincaUnidadNegocio creada.';
    END;

    INSERT INTO dbo.aUnidadNegocio (empresa, codigo, descripcion, activo, fechaRegistro, usuarioRegistro)
    SELECT @Empresa, s.codigo, s.descripcion, 1, @Fecha, @Usuario
      FROM (VALUES ('PAL', 'PALMA'),
                   ('BUF', 'BUFALOS'),
                   ('GTR', 'GANADERIA TRADICIONAL'),
                   ('GCE', 'GANADERIA CEBA')) s (codigo, descripcion)
     WHERE NOT EXISTS (SELECT 1 FROM dbo.aUnidadNegocio u WHERE u.empresa = @Empresa AND u.codigo = s.codigo);

    PRINT CONCAT(N'Unidades de negocio sembradas: ', @@ROWCOUNT);

    IF COL_LENGTH('dbo.aLotes', 'unidadNegocio') IS NULL
    BEGIN
        ALTER TABLE dbo.aLotes ADD unidadNegocio VARCHAR(10) NULL;

        PRINT N'Columna dbo.aLotes.unidadNegocio creada.';
    END;

    -- Habilitaciones finca-unidad (antes del backfill, para no violar la FK si ya existe).
    EXEC sp_executesql
        N'INSERT INTO dbo.aFincaUnidadNegocio (empresa, finca, unidadNegocio)
          SELECT DISTINCT x.empresa, x.finca, x.unidad
            FROM (SELECT l.empresa, RTRIM(l.finca) AS finca, ISNULL(l.unidadNegocio, ISNULL(m.unidad, ''PAL'')) AS unidad
                    FROM dbo.aLotes l
                    LEFT JOIN #mapa m ON m.lote = l.codigo
                   WHERE l.empresa = @Empresa
                  UNION
                  SELECT f.empresa, RTRIM(f.codigo), ''PAL''
                    FROM dbo.aFinca f
                   WHERE f.empresa = @Empresa AND f.activo = 1) x
           WHERE NOT EXISTS (SELECT 1 FROM dbo.aFincaUnidadNegocio fu
                              WHERE fu.empresa = x.empresa AND fu.finca = x.finca AND fu.unidadNegocio = x.unidad);

          PRINT CONCAT(N''Habilitaciones finca-unidad creadas: '', @@ROWCOUNT);

          UPDATE l
             SET l.unidadNegocio = ISNULL(m.unidad, ''PAL'')
            FROM dbo.aLotes l
            LEFT JOIN #mapa m ON m.lote = l.codigo
           WHERE l.empresa = @Empresa
             AND l.unidadNegocio IS NULL;

          PRINT CONCAT(N''Lotes con unidad de negocio asignada: '', @@ROWCOUNT);',
        N'@Empresa INT',
        @Empresa = @Empresa;

    IF OBJECT_ID('dbo.FK_aLotes_aFincaUnidadNegocio', 'F') IS NULL
    BEGIN
        EXEC sp_executesql
            N'ALTER TABLE dbo.aLotes WITH CHECK ADD CONSTRAINT FK_aLotes_aFincaUnidadNegocio
                FOREIGN KEY (empresa, finca, unidadNegocio) REFERENCES dbo.aFincaUnidadNegocio (empresa, finca, unidadNegocio)';

        PRINT N'FK_aLotes_aFincaUnidadNegocio creada.';
    END;

    SELECT TOP 1 @IdLoseta = Id
      FROM Modulos
     WHERE Ruta = 'gestion-palma/onboarding'
       AND IdLoseta IS NULL
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdLoseta IS NULL
        THROW 50002, N'No existe la loseta de Gestión Palma (Ruta gestion-palma/onboarding).', 1;

    SELECT TOP 1 @IdHermano = Id, @IdContenedor = IdModulo
      FROM Modulos
     WHERE Ruta = 'gestion-palma/secciones'
       AND IdLoseta = @IdLoseta
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdHermano IS NULL
        THROW 50003, N'No existe el módulo "Secciones / Bloques" (gestion-palma/secciones).', 1;

    SELECT TOP 1 @IdNuevo = Id
      FROM Modulos
     WHERE Ruta = @Ruta
       AND IdLoseta = @IdLoseta
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdNuevo IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (N'Unidades de negocio', @Ruta, 'bi bi-boxes', @IdLoseta, @IdContenedor, @IdUsuario, @Fecha);

        SET @IdNuevo = SCOPE_IDENTITY();

        IF COL_LENGTH('Modulos', 'Orden') IS NOT NULL
            EXEC sp_executesql
                N'UPDATE Modulos
                     SET Orden = (SELECT ISNULL(MAX(Orden), 0) + 1 FROM Modulos
                                   WHERE IdLoseta = @IdLoseta
                                     AND ISNULL(IdModulo, 0) = ISNULL(@IdContenedor, 0)
                                     AND Id <> @IdNuevo
                                     AND FechaFinalizacion IS NULL)
                   WHERE Id = @IdNuevo',
                N'@IdLoseta INT, @IdContenedor INT, @IdNuevo INT',
                @IdLoseta = @IdLoseta, @IdContenedor = @IdContenedor, @IdNuevo = @IdNuevo;

        PRINT CONCAT(N'Módulo creado con Id ', @IdNuevo);
    END
    ELSE
        PRINT CONCAT(N'El módulo ya existía con Id ', @IdNuevo);

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT DISTINCT @IdNuevo, pr.IdRol, @Fecha
      FROM ModulosRoles pr
     WHERE pr.IdModulo = @IdHermano
       AND pr.FechaFinalizacion IS NULL
       AND NOT EXISTS (SELECT 1 FROM ModulosRoles mr
                        WHERE mr.IdModulo = @IdNuevo AND mr.IdRol = pr.IdRol AND mr.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Permisos creados en ModulosRoles: ', @@ROWCOUNT);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

DROP TABLE #mapa;
GO

-- Verificación
SELECT u.empresa, u.codigo, u.descripcion, u.activo,
       (SELECT COUNT(*) FROM dbo.aFincaUnidadNegocio fu WHERE fu.empresa = u.empresa AND fu.unidadNegocio = u.codigo) AS fincas,
       (SELECT COUNT(*) FROM dbo.aLotes l WHERE l.empresa = u.empresa AND l.unidadNegocio = u.codigo) AS lotes
  FROM dbo.aUnidadNegocio u
 ORDER BY u.empresa, u.codigo;

SELECT fu.empresa, fu.finca, f.descripcion AS nombreFinca, f.activo AS fincaActiva, fu.unidadNegocio,
       (SELECT COUNT(*) FROM dbo.aLotes l
         WHERE l.empresa = fu.empresa AND l.finca = fu.finca AND l.unidadNegocio = fu.unidadNegocio) AS lotes
  FROM dbo.aFincaUnidadNegocio fu
  JOIN dbo.aFinca f ON f.empresa = fu.empresa AND f.codigo = fu.finca
 ORDER BY fu.empresa, fu.finca, fu.unidadNegocio;

SELECT empresa, finca, codigo, descripcion, unidadNegocio, ccosto
  FROM dbo.aLotes
 WHERE unidadNegocio IS NULL OR unidadNegocio <> 'PAL'
 ORDER BY empresa, finca, codigo;

SELECT m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo AS IdModuloPadre,
       COUNT(mr.IdRol) AS Roles
  FROM Modulos m
  LEFT JOIN ModulosRoles mr ON mr.IdModulo = m.Id AND mr.FechaFinalizacion IS NULL
 WHERE m.Ruta IN ('gestion-palma/unidades-negocio', 'gestion-palma/secciones')
   AND m.FechaFinalizacion IS NULL
 GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo
 ORDER BY m.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   Quita el módulo, la FK y la columna de aLotes y borra ambas tablas.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosRoles
     WHERE IdModulo IN (SELECT Id FROM Modulos WHERE Ruta = 'gestion-palma/unidades-negocio');

    DELETE FROM Modulos WHERE Ruta = 'gestion-palma/unidades-negocio';

    IF OBJECT_ID('dbo.FK_aLotes_aFincaUnidadNegocio', 'F') IS NOT NULL
        ALTER TABLE dbo.aLotes DROP CONSTRAINT FK_aLotes_aFincaUnidadNegocio;

    IF COL_LENGTH('dbo.aLotes', 'unidadNegocio') IS NOT NULL
        ALTER TABLE dbo.aLotes DROP COLUMN unidadNegocio;

    IF OBJECT_ID('dbo.aFincaUnidadNegocio', 'U') IS NOT NULL
        DROP TABLE dbo.aFincaUnidadNegocio;

    IF OBJECT_ID('dbo.aUnidadNegocio', 'U') IS NOT NULL
        DROP TABLE dbo.aUnidadNegocio;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
