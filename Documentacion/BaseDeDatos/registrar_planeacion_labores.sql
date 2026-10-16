/* ============================================================================
   Registro del módulo "Planeación de labores" (Transacciones > Planeación)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Ubica la loseta del submódulo "Labores" (Ruta 'transacciones/labores').
        Debe existir: ver registrar_transacciones_labores.sql.
     2) Crea el submódulo "Planeación de labores" con
        Ruta = 'transacciones/planeacion' e Icono = 'bi bi-calendar-check'.
     3) Crea las acciones registrar-planeacion, consultar-planeacion,
        editar-planeacion y eliminar-planeacion en ModulosAcciones.
     4) Permisos (ModulosRoles + ModulosPermisosAcciones):
          Rol 1 (administrador)  registrar, consultar, editar, eliminar
          AGR (si existe)        registrar, consultar, editar
          GER y AUX (si existen) consultar

   Requiere las tablas de crear_planeacion_labores.sql.
   El controlador resuelve el Id del módulo por Modulos.Ruta.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i registrar_planeacion_labores.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario     INT = 1;
DECLARE @Nombre        NVARCHAR(100) = N'Planeación de labores';
DECLARE @Ruta          NVARCHAR(200) = N'transacciones/planeacion';
DECLARE @RutaLabores   NVARCHAR(200) = N'transacciones/labores';
DECLARE @Icono         NVARCHAR(100) = N'bi bi-calendar-check';
DECLARE @Orden         INT = 4;
DECLARE @Fecha         DATETIME = GETDATE();
DECLARE @IdLoseta      INT;
DECLARE @IdModulo      INT;

DECLARE @permisos TABLE (IdRol INT, Nombre VARCHAR(100));

BEGIN TRY

    BEGIN TRAN;

    SELECT TOP 1 @IdLoseta = IdLoseta
    FROM Modulos
    WHERE Ruta = @RutaLabores
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdLoseta IS NULL
        THROW 50001, N'No existe el submódulo "Labores". Ejecute primero registrar_transacciones_labores.sql.', 1;

    SELECT TOP 1 @IdModulo = Id
    FROM Modulos
    WHERE Ruta = @Ruta
      AND IdLoseta = @IdLoseta
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdModulo IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio, Orden)
        VALUES (@Nombre, @Ruta, @Icono, @IdLoseta, NULL, @IdUsuario, @Fecha, @Orden);

        SET @IdModulo = SCOPE_IDENTITY();

        PRINT CONCAT(N'Submodulo creado con Id = ', @IdModulo);
    END
    ELSE
        PRINT CONCAT(N'El submodulo ya existia con Id = ', @IdModulo, N'. No se inserta de nuevo.');

    INSERT INTO @permisos (IdRol, Nombre)
    SELECT 1, v.Nombre
      FROM (VALUES ('registrar-planeacion'), ('consultar-planeacion'), ('editar-planeacion'), ('eliminar-planeacion')) v(Nombre)
    UNION ALL
    SELECT r.Id, v.Nombre
      FROM Roles r
      JOIN (VALUES ('AGR', 'registrar-planeacion'), ('AGR', 'consultar-planeacion'), ('AGR', 'editar-planeacion'),
                   ('GER', 'consultar-planeacion'), ('AUX', 'consultar-planeacion')) v(Abreviatura, Nombre)
        ON v.Abreviatura = r.Abreviatura
     WHERE r.FechaFinalizacion IS NULL;

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT DISTINCT @IdModulo, p.IdRol, @Fecha
      FROM @permisos p
     WHERE NOT EXISTS (SELECT 1 FROM ModulosRoles mr
                        WHERE mr.IdModulo = @IdModulo AND mr.IdRol = p.IdRol AND mr.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Permisos de submodulo creados en ModulosRoles: ', @@ROWCOUNT);

    INSERT INTO ModulosAcciones (IdModulo, Nombre, Icono, IdUsuario, FechaInicio)
    SELECT DISTINCT @IdModulo, p.Nombre, NULL, @IdUsuario, @Fecha
      FROM @permisos p
     WHERE NOT EXISTS (SELECT 1 FROM ModulosAcciones ma
                        WHERE ma.IdModulo = @IdModulo AND ma.Nombre = p.Nombre AND ma.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Acciones creadas: ', @@ROWCOUNT);

    INSERT INTO ModulosPermisosAcciones (IdRol, IdAccion, IdUsuario, FechaInicio)
    SELECT p.IdRol, ma.Id, @IdUsuario, @Fecha
      FROM @permisos p
      JOIN ModulosAcciones ma ON ma.Nombre = p.Nombre AND ma.IdModulo = @IdModulo AND ma.FechaFinalizacion IS NULL
     WHERE NOT EXISTS (SELECT 1 FROM ModulosPermisosAcciones mpa
                        WHERE mpa.IdRol = p.IdRol AND mpa.IdAccion = ma.Id AND mpa.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Acciones asignadas a roles: ', @@ROWCOUNT);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, ma.Nombre AS Accion, r.Id AS IdRol, r.Abreviatura
FROM Modulos m
JOIN ModulosAcciones ma ON ma.IdModulo = m.Id AND ma.FechaFinalizacion IS NULL
LEFT JOIN ModulosPermisosAcciones mpa ON mpa.IdAccion = ma.Id AND mpa.FechaFinalizacion IS NULL
LEFT JOIN Roles r ON r.Id = mpa.IdRol
WHERE m.Id = @IdModulo
ORDER BY ma.Nombre, r.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (DELETE físicos). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdModuloRollback INT;

SELECT TOP 1 @IdModuloRollback = Id
FROM Modulos
WHERE Ruta = N'transacciones/planeacion'
  AND FechaFinalizacion IS NULL
ORDER BY Id;

IF @IdModuloRollback IS NULL
BEGIN
    RAISERROR('No se encontro el modulo "Planeación de labores"; se aborta la reversion', 16, 1);
    RETURN;
END

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosPermisosAcciones
    WHERE IdAccion IN (SELECT Id FROM ModulosAcciones WHERE IdModulo = @IdModuloRollback);

    DELETE FROM ModulosAcciones
    WHERE IdModulo = @IdModuloRollback;

    DELETE FROM ModulosRoles
    WHERE IdModulo = @IdModuloRollback;

    DELETE FROM Modulos
    WHERE Id = @IdModuloRollback;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
