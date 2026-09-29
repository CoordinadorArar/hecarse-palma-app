/* ============================================================================
   Registro del módulo "Maestros" en el árbol de permisos (tabla Modulos)
   ----------------------------------------------------------------------------
   Crea:
     1) La Loseta de nivel superior "Maestros" (IdLoseta = NULL, IdModulo = NULL)
     2) El módulo "Empresas" dentro de esa loseta (IdLoseta = <Id Maestros>)

   Ejecutar UNA sola vez contra la base de datos AppPalma (SQL Server).
   Después de ejecutarlo, asigna el módulo "Empresas" a los roles que deban
   verlo desde la pantalla existente "Administración > Roles" (no requiere
   tocar esta base de datos manualmente).

   IMPORTANTE: reemplaza @IdUsuario por el Id del usuario administrador que
   quede registrado como creador del módulo (columna IdUsuario en Modulos).
   ============================================================================ */

SET NOCOUNT ON;

DECLARE @IdUsuario   INT = 1; -- <-- Ajustar al Id real del usuario administrador
DECLARE @Fecha       DATETIME = GETDATE();
DECLARE @IdLoseta    INT;
DECLARE @IdModulo    INT;

BEGIN TRAN;

    -- 1) Loseta "Maestros"
    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES ('Maestros', '', 'bi bi-diagram-3', NULL, NULL, @IdUsuario, @Fecha);

    SET @IdLoseta = SCOPE_IDENTITY();

    UPDATE Modulos
    SET Ruta = CONCAT('maestros/onboarding/', @IdLoseta)
    WHERE Id = @IdLoseta;

    -- 2) Módulo "Empresas" dentro de la loseta "Maestros"
    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES ('Empresas', '', 'bi bi-buildings', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdModulo = SCOPE_IDENTITY();

    UPDATE Modulos
    SET Ruta = CONCAT('maestros/empresas/', @IdLoseta)
    WHERE Id = @IdModulo;

COMMIT TRAN;

SELECT Id, Nombre, Ruta, Icono, IdLoseta, IdModulo
FROM Modulos
WHERE Id IN (@IdLoseta, @IdModulo);
