/* ============================================================================
   Roles de operación: Gerencia, Agrónomo y Auxiliar de registro
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1. Crea en Roles (si no existen por Abreviatura):
          GER  Gerencia
          AGR  Agrónomo
          AUX  Auxiliar de registro
     2. Les asigna módulos en ModulosRoles y acciones de Transacciones en
        ModulosPermisosAcciones según la matriz de abajo.
     3. Asigna el rol a los usuarios de @usuarios (UsuariosRoles).
     Es idempotente: solo inserta lo que no exista activo.

   MATRIZ DE PERMISOS  *** REVISAR ANTES DE APLICAR ***
   ----------------------------------------------------
                                              GER   AGR   AUX
     Dashboards (8, 9 Onboarding)              x
       Informes Gerenciales (10)               x
     Informes (11, 13 Onboarding)              x     x     x
       Administración, Revisión Labores,
       Transacciones, Indicadores, Sanidad,
       Fertilización (61-66)                   x     x     x
       Liquidación (12) y Contabilizar (14)    -     -     -   (procesan/escriben
                                                               datos: quedan para
                                                               Contador/Director)
     Transacciones (57) Producción, Labores,
       Fertilización (58, 59, 60)              x     x     x
         consultar                             x     x     x
         registrar / editar                          x     x
         eliminar                              -     -     -
     Maestros Palma, Administración,
     Gestión Documental                        -     -     -

   USUARIOS
   --------
     Se asigna el rol solo a usuarios activos que NO tengan ya un rol activo.
     Con @ReemplazarRolActual = 1 se finaliza el rol actual y se asigna el
     nuevo (por ejemplo "operativo", que hoy tiene Director Administrativo).

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resultado y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i ...".
   ============================================================================ */

USE AppPalma;
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar             BIT = 0;
DECLARE @ReemplazarRolActual BIT = 0;
DECLARE @IdUsuarioCrea       INT = 6;
DECLARE @Ahora               DATETIME = GETDATE();

DECLARE @roles TABLE (Abreviatura VARCHAR(10) PRIMARY KEY, Nombre VARCHAR(500));
INSERT @roles VALUES
    ('GER', 'Gerencia'),
    ('AGR', 'Agrónomo'),
    ('AUX', 'Auxiliar de registro');

DECLARE @modulos TABLE (Abreviatura VARCHAR(10), IdModulo INT);
INSERT @modulos
SELECT 'GER', v.Id FROM (VALUES (8),(9),(10),(11),(13),(61),(62),(63),(64),(65),(66),(57),(58),(59),(60)) v(Id)
UNION ALL
SELECT r.Abreviatura, v.Id FROM (VALUES ('AGR'),('AUX')) r(Abreviatura)
CROSS JOIN (VALUES (11),(13),(61),(62),(63),(64),(65),(66),(57),(58),(59),(60)) v(Id);

DECLARE @acciones TABLE (Abreviatura VARCHAR(10), Prefijo VARCHAR(20));
INSERT @acciones VALUES
    ('GER', 'consultar-'),
    ('AGR', 'consultar-'), ('AGR', 'registrar-'), ('AGR', 'editar-'),
    ('AUX', 'consultar-'), ('AUX', 'registrar-'), ('AUX', 'editar-');

DECLARE @usuarios TABLE (Usuario VARCHAR(100) PRIMARY KEY, Abreviatura VARCHAR(10));
INSERT @usuarios VALUES
    ('Gerente',     'GER'),
    ('agronomo',    'AGR'),
    ('operativo',   'AUX'),
    ('operativo01', 'AUX'),
    ('operativo02', 'AUX');

DECLARE @log TABLE (orden INT IDENTITY(1,1), paso NVARCHAR(60), filas INT);

BEGIN TRY

    BEGIN TRAN;

    INSERT INTO dbo.Roles (Nombre, Abreviatura, IdUsuario, FechaInicio)
    SELECT r.Nombre, r.Abreviatura, @IdUsuarioCrea, @Ahora
    FROM @roles r
    WHERE NOT EXISTS (SELECT 1 FROM dbo.Roles x WHERE x.Abreviatura = r.Abreviatura AND x.FechaFinalizacion IS NULL);
    INSERT @log VALUES (N'Roles creados', @@ROWCOUNT);

    INSERT INTO dbo.ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT m.IdModulo, ro.Id, @Ahora
    FROM @modulos m
    JOIN dbo.Roles ro ON ro.Abreviatura = m.Abreviatura AND ro.FechaFinalizacion IS NULL
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ModulosRoles mr WHERE mr.IdModulo = m.IdModulo AND mr.IdRol = ro.Id AND mr.FechaFinalizacion IS NULL);
    INSERT @log VALUES (N'Módulos asignados', @@ROWCOUNT);

    INSERT INTO dbo.ModulosPermisosAcciones (IdRol, IdAccion, IdUsuario, FechaInicio)
    SELECT ro.Id, ma.Id, @IdUsuarioCrea, @Ahora
    FROM @acciones a
    JOIN dbo.Roles ro ON ro.Abreviatura = a.Abreviatura AND ro.FechaFinalizacion IS NULL
    JOIN dbo.ModulosAcciones ma ON ma.IdModulo IN (58, 59, 60) AND ma.Nombre LIKE a.Prefijo + '%' AND ma.FechaFinalizacion IS NULL
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ModulosPermisosAcciones p WHERE p.IdRol = ro.Id AND p.IdAccion = ma.Id AND p.FechaFinalizacion IS NULL);
    INSERT @log VALUES (N'Acciones asignadas', @@ROWCOUNT);

    IF @ReemplazarRolActual = 1
    BEGIN
        UPDATE ur SET ur.FechaFinalizacion = @Ahora, ur.FechaModificacion = @Ahora
        FROM dbo.UsuariosRoles ur
        JOIN dbo.Usuarios u ON u.Id = ur.IdUsuario
        JOIN @usuarios x ON x.Usuario = u.Usuario
        JOIN dbo.Roles ro ON ro.Abreviatura = x.Abreviatura AND ro.FechaFinalizacion IS NULL
        WHERE ur.FechaFinalizacion IS NULL AND ur.IdRol <> ro.Id;
        INSERT @log VALUES (N'Roles anteriores finalizados', @@ROWCOUNT);
    END;

    INSERT INTO dbo.UsuariosRoles (IdUsuario, IdRol, FechaInicio)
    SELECT u.Id, ro.Id, @Ahora
    FROM @usuarios x
    JOIN dbo.Usuarios u ON u.Usuario = x.Usuario AND u.FechaFinalizacion IS NULL
    JOIN dbo.Roles ro ON ro.Abreviatura = x.Abreviatura AND ro.FechaFinalizacion IS NULL
    WHERE NOT EXISTS (SELECT 1 FROM dbo.UsuariosRoles ur WHERE ur.IdUsuario = u.Id AND ur.FechaFinalizacion IS NULL);
    INSERT @log VALUES (N'Usuarios asignados', @@ROWCOUNT);

    SELECT paso, filas FROM @log ORDER BY orden;

    SELECT ro.Abreviatura, ro.Nombre AS Rol,
           STRING_AGG(m.Nombre, ', ') AS Modulos
    FROM dbo.Roles ro
    JOIN dbo.ModulosRoles mr ON mr.IdRol = ro.Id AND mr.FechaFinalizacion IS NULL
    JOIN dbo.Modulos m ON m.Id = mr.IdModulo
    WHERE ro.Abreviatura IN (SELECT Abreviatura FROM @roles) AND ro.FechaFinalizacion IS NULL
    GROUP BY ro.Abreviatura, ro.Nombre;

    SELECT x.Usuario, x.Abreviatura AS RolEsperado, ro.Nombre AS RolActivo,
           CASE WHEN u.Id IS NULL THEN 'No existe o está inactivo'
                WHEN ro.Abreviatura = x.Abreviatura THEN 'OK'
                ELSE 'Conserva su rol actual (ver @ReemplazarRolActual)' END AS Estado
    FROM @usuarios x
    LEFT JOIN dbo.Usuarios u ON u.Usuario = x.Usuario AND u.FechaFinalizacion IS NULL
    LEFT JOIN dbo.UsuariosRoles ur ON ur.IdUsuario = u.Id AND ur.FechaFinalizacion IS NULL
    LEFT JOIN dbo.Roles ro ON ro.Id = ur.IdRol
    ORDER BY x.Abreviatura, x.Usuario;

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: cambios confirmados.';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: se revirtió todo. Cambie @Aplicar = 1 para confirmar.';
    END

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK;
    THROW;
END CATCH;
