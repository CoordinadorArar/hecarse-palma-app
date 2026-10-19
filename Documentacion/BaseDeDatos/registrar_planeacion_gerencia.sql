/* ============================================================================
   Planeación de labores para el rol Gerencia (GER)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1. Ubica el submódulo "Planeación de labores" (Modulos.Ruta =
        'transacciones/planeacion'). Debe existir: ver
        registrar_planeacion_labores.sql.
     2. Asigna el submódulo al rol GER en ModulosRoles (si no lo tiene).
     3. Asigna al rol GER las acciones consultar-planeacion,
        registrar-planeacion y editar-planeacion en ModulosPermisosAcciones.
        No asigna eliminar-planeacion y no elimina permisos existentes.
     Permite planear actividades desde Informes Gerenciales (modal).
     Es idempotente: solo inserta lo que no exista activo.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resultado y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i ...".
   ============================================================================ */

USE AppPalma;
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar       BIT = 0;
DECLARE @IdUsuarioCrea INT = 6;
DECLARE @Ahora         DATETIME = GETDATE();
DECLARE @Ruta          NVARCHAR(200) = N'transacciones/planeacion';
DECLARE @Abreviatura   VARCHAR(10) = 'GER';
DECLARE @IdModulo      INT;
DECLARE @IdRol         INT;

DECLARE @acciones TABLE (Nombre VARCHAR(100) PRIMARY KEY);
INSERT @acciones VALUES
    ('consultar-planeacion'),
    ('registrar-planeacion'),
    ('editar-planeacion');

DECLARE @log TABLE (orden INT IDENTITY(1,1), paso NVARCHAR(60), filas INT);

BEGIN TRY

    BEGIN TRAN;

    SELECT TOP 1 @IdModulo = Id
    FROM dbo.Modulos
    WHERE Ruta = @Ruta
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdModulo IS NULL
        THROW 50001, N'No existe el submódulo "Planeación de labores". Ejecute primero registrar_planeacion_labores.sql.', 1;

    SELECT TOP 1 @IdRol = Id
    FROM dbo.Roles
    WHERE Abreviatura = @Abreviatura
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdRol IS NULL
        THROW 50002, N'No existe el rol Gerencia (GER). Ejecute primero registrar_roles_operacion.sql.', 1;

    IF EXISTS (SELECT 1 FROM @acciones a
                WHERE NOT EXISTS (SELECT 1 FROM dbo.ModulosAcciones ma
                                   WHERE ma.IdModulo = @IdModulo AND ma.Nombre = a.Nombre AND ma.FechaFinalizacion IS NULL))
        THROW 50003, N'Faltan acciones del submódulo "Planeación de labores". Ejecute primero registrar_planeacion_labores.sql.', 1;

    INSERT INTO dbo.ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT @IdModulo, @IdRol, @Ahora
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ModulosRoles mr WHERE mr.IdModulo = @IdModulo AND mr.IdRol = @IdRol AND mr.FechaFinalizacion IS NULL);
    INSERT @log VALUES (N'Módulo asignado', @@ROWCOUNT);

    INSERT INTO dbo.ModulosPermisosAcciones (IdRol, IdAccion, IdUsuario, FechaInicio)
    SELECT @IdRol, ma.Id, @IdUsuarioCrea, @Ahora
    FROM @acciones a
    JOIN dbo.ModulosAcciones ma ON ma.IdModulo = @IdModulo AND ma.Nombre = a.Nombre AND ma.FechaFinalizacion IS NULL
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ModulosPermisosAcciones p WHERE p.IdRol = @IdRol AND p.IdAccion = ma.Id AND p.FechaFinalizacion IS NULL);
    INSERT @log VALUES (N'Acciones asignadas', @@ROWCOUNT);

    SELECT paso, filas FROM @log ORDER BY orden;

    SELECT m.Id AS IdModulo, m.Nombre AS Modulo, ma.Nombre AS Accion, r.Abreviatura,
           CASE WHEN mpa.Id IS NULL THEN 'Sin permiso' ELSE 'OK' END AS Estado
    FROM dbo.Modulos m
    JOIN dbo.ModulosAcciones ma ON ma.IdModulo = m.Id AND ma.FechaFinalizacion IS NULL
    JOIN dbo.Roles r ON r.Id = @IdRol
    LEFT JOIN dbo.ModulosPermisosAcciones mpa ON mpa.IdAccion = ma.Id AND mpa.IdRol = r.Id AND mpa.FechaFinalizacion IS NULL
    WHERE m.Id = @IdModulo
    ORDER BY ma.Nombre;

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
