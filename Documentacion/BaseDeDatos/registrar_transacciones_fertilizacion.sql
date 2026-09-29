/* ============================================================================
   Registro del módulo "Fertilización" (Transacciones > Fertilización, tipo RLF)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Ubica la loseta "Transacciones" (debe existir: ver
        registrar_transacciones_produccion.sql).
     2) Crea el submódulo "Fertilización" con Ruta = 'transacciones/fertilizacion' e
        Icono = 'bi bi-droplet'.
     3) Crea el permiso del submódulo en ModulosRoles para el rol 1.
     4) Crea las acciones registrar-fertilizacion, consultar-fertilizacion, editar-fertilizacion
        y eliminar-fertilizacion en ModulosAcciones.
     5) Asigna las cuatro acciones al rol 1 en ModulosPermisosAcciones.

   El controlador resuelve el Id del módulo por Modulos.Ruta, así que no hay
   que ajustar ningún Id en el código.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i registrar_transacciones_fertilizacion.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario     INT = 1;
DECLARE @IdRol         INT = 1;
DECLARE @NombreLoseta  NVARCHAR(100) = N'Transacciones';
DECLARE @Nombre        NVARCHAR(100) = N'Fertilización';
DECLARE @Ruta          NVARCHAR(200) = N'transacciones/fertilizacion';
DECLARE @Icono         NVARCHAR(100) = N'bi bi-droplet';
DECLARE @Fecha         DATETIME = GETDATE();
DECLARE @IdLoseta      INT;
DECLARE @IdModulo      INT;

DECLARE @acciones TABLE (Nombre VARCHAR(100));

INSERT INTO @acciones (Nombre)
VALUES ('registrar-fertilizacion'), ('consultar-fertilizacion'), ('editar-fertilizacion'), ('eliminar-fertilizacion');

BEGIN TRY

    BEGIN TRAN;

    SELECT TOP 1 @IdLoseta = Id
    FROM Modulos
    WHERE Nombre = @NombreLoseta
      AND IdLoseta IS NULL
      AND IdModulo IS NULL
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdLoseta IS NULL
        THROW 50001, N'No existe la loseta "Transacciones". Ejecute primero registrar_transacciones_produccion.sql.', 1;

    SELECT TOP 1 @IdModulo = Id
    FROM Modulos
    WHERE Ruta = @Ruta
      AND IdLoseta = @IdLoseta
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdModulo IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (@Nombre, @Ruta, @Icono, @IdLoseta, NULL, @IdUsuario, @Fecha);

        SET @IdModulo = SCOPE_IDENTITY();

        PRINT CONCAT(N'Submodulo creado con Id = ', @IdModulo);
    END
    ELSE
        PRINT CONCAT(N'El submodulo ya existia con Id = ', @IdModulo, N'. No se inserta de nuevo.');

    IF NOT EXISTS (
        SELECT 1
        FROM ModulosRoles
        WHERE IdModulo = @IdModulo
          AND IdRol = @IdRol
          AND FechaFinalizacion IS NULL
    )
    BEGIN
        INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
        VALUES (@IdModulo, @IdRol, @Fecha);

        PRINT N'Permiso del submodulo creado en ModulosRoles.';
    END
    ELSE
        PRINT N'El permiso del submodulo ya existia en ModulosRoles.';

    INSERT INTO ModulosAcciones (IdModulo, Nombre, Icono, IdUsuario, FechaInicio)
    SELECT @IdModulo, a.Nombre, NULL, @IdUsuario, @Fecha
      FROM @acciones a
     WHERE NOT EXISTS (SELECT 1 FROM ModulosAcciones ma
                        WHERE ma.IdModulo = @IdModulo AND ma.Nombre = a.Nombre AND ma.FechaFinalizacion IS NULL);

    INSERT INTO ModulosPermisosAcciones (IdRol, IdAccion, IdUsuario, FechaInicio)
    SELECT @IdRol, ma.Id, @IdUsuario, @Fecha
      FROM ModulosAcciones ma
      JOIN @acciones a ON a.Nombre = ma.Nombre
     WHERE ma.IdModulo = @IdModulo
       AND ma.FechaFinalizacion IS NULL
       AND NOT EXISTS (SELECT 1 FROM ModulosPermisosAcciones mpa
                        WHERE mpa.IdRol = @IdRol AND mpa.IdAccion = ma.Id AND mpa.FechaFinalizacion IS NULL);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, ma.Id AS IdAccion, ma.Nombre AS Accion
FROM Modulos m
LEFT JOIN ModulosAcciones ma ON ma.IdModulo = m.Id AND ma.FechaFinalizacion IS NULL
WHERE m.Id = @IdModulo;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (DELETE físicos). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdModuloRollback INT;

SELECT TOP 1 @IdModuloRollback = Id
FROM Modulos
WHERE Ruta = N'transacciones/fertilizacion'
  AND FechaFinalizacion IS NULL
ORDER BY Id;

IF @IdModuloRollback IS NULL
BEGIN
    RAISERROR('No se encontro el modulo "Fertilizacion"; se aborta la reversion', 16, 1);
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
