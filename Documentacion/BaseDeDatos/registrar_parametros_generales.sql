/* ============================================================================
   Registro del submódulo "Parámetros Generales" en el menú
   (Maestros > Parámetros administración > Parámetros Generales)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Inserta el submódulo "Parámetros Generales" en la loseta "Maestros"
        (IdLoseta = 23) colgando del módulo contenedor "Parámetros
        administración" (IdModulo = 51), con Ruta = 'maestros/parametros-generales'.
     2) Le crea el permiso en ModulosRoles para el rol 1 (Desarrollador). Sin esa
        fila el sidebar NO muestra la opción, aunque el módulo exista.

   El contenedor (Id = 51) ya debe existir: lo crea
   Documentacion/BaseDeDatos/registrar_parametros_administracion.sql. Si no está,
   el script aborta sin tocar nada.

   Los dos pasos van en UNA transacción con TRY/CATCH: un módulo sin su fila en
   ModulosRoles no aparece en el menú y quedaría como basura invisible.

   IDEMPOTENTE: se puede ejecutar varias veces. Si el submódulo ya existe se
   resuelve su Id por nombre/loseta/padre en lugar de insertarlo de nuevo, y el
   permiso se inserta solo si no hay uno activo.

   Ejecutar contra la base de datos AppPalma (SQL Server).
   NOTA: Modulos.Id y ModulosRoles.Id son IDENTITY; el script no fija Ids, los
   captura con SCOPE_IDENTITY().

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8 CON BOM. Ejecutarlo SOLO desde SSMS o, por consola, con
   "sqlcmd -f 65001 -i registrar_parametros_generales.sql". El sqlcmd.exe clásico
   no detecta el BOM y sin -f 65001 lee el archivo con la code page ANSI:
   "Parámetros Generales" entraría a la base como "ParÃ¡metros Generales" (menú
   con mojibake y, además, la idempotencia por nombre rota desde la corrida
   siguiente, porque esa forma no casa con el literal correcto). En SSMS no ocurre.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario    INT = 1;       -- <-- Ajustar al Id real del usuario administrador
DECLARE @IdLoseta     INT = 23;      -- Loseta "Maestros"
DECLARE @IdContenedor INT = 51;      -- Módulo contenedor "Parámetros administración"
DECLARE @IdRol        INT = 1;       -- Rol "Desarrollador"
DECLARE @Nombre       NVARCHAR(100) = N'Parámetros Generales';
DECLARE @Ruta         NVARCHAR(200) = N'maestros/parametros-generales';
DECLARE @Icono        NVARCHAR(100) = N'bi bi-sliders';
DECLARE @Fecha        DATETIME = GETDATE();
DECLARE @IdModulo     INT;

IF NOT EXISTS (
    SELECT 1
    FROM Modulos
    WHERE Id = @IdContenedor
      AND IdLoseta = @IdLoseta
      AND FechaFinalizacion IS NULL
)
BEGIN
    RAISERROR('No existe el contenedor "Parametros administracion" (Id = 51) en la loseta 23; ejecute primero registrar_parametros_administracion.sql', 16, 1);
    RETURN;
END

BEGIN TRY

    BEGIN TRAN;

    -- 1) Submódulo "Parámetros Generales"
    SELECT TOP 1 @IdModulo = Id
    FROM Modulos
    WHERE Nombre = @Nombre
      AND IdLoseta = @IdLoseta
      AND IdModulo = @IdContenedor
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdModulo IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (@Nombre, @Ruta, @Icono, @IdLoseta, @IdContenedor, @IdUsuario, @Fecha);

        SET @IdModulo = SCOPE_IDENTITY();

        PRINT CONCAT(N'Submodulo creado con Id = ', @IdModulo);
    END
    ELSE
        PRINT CONCAT(N'El submodulo ya existia con Id = ', @IdModulo, N'. No se inserta de nuevo.');

    -- 2) Permiso del submódulo para el rol 1
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

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

/* Verificación: árbol resultante de la loseta "Maestros" (Id = 23) */
SELECT
    m.Id,
    m.Nombre,
    m.Ruta,
    m.Icono,
    m.IdLoseta,
    m.IdModulo AS IdModuloPadre,
    p.Nombre   AS ModuloPadre,
    COUNT(mr.IdRol) AS Roles
FROM Modulos m
LEFT JOIN Modulos p
       ON p.Id = m.IdModulo
LEFT JOIN ModulosRoles mr
       ON mr.IdModulo = m.Id
      AND mr.FechaFinalizacion IS NULL
WHERE m.IdLoseta = @IdLoseta
  AND m.FechaFinalizacion IS NULL
GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo, p.Nombre
ORDER BY ISNULL(m.IdModulo, m.Id), m.IdModulo, m.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (deshacer los dos pasos)
   Descomentar y ejecutar para quitar del menú "Parámetros Generales".

   Los DELETE de esta reversión son FÍSICOS, no lógicos como en el resto de la
   app (no hay ninguna FK que involucre a Modulos ni a ModulosRoles).
   El DELETE de ModulosRoles borra el permiso del submódulo para TODOS los roles,
   no solo para el rol 1: es lo correcto para una reversión total, pero tenerlo
   presente si se añadieron otros roles a mano.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdModuloRollback INT;

SELECT TOP 1 @IdModuloRollback = Id
FROM Modulos
WHERE Nombre = N'Parámetros Generales'
  AND IdLoseta = 23
  AND IdModulo = 51
  AND FechaFinalizacion IS NULL
ORDER BY Id;

IF @IdModuloRollback IS NULL
BEGIN
    RAISERROR('No se encontro el submodulo "Parametros Generales"; se aborta la reversion', 16, 1);
    RETURN;
END

BEGIN TRY

    BEGIN TRAN;

    -- 2) Borrar el permiso del submódulo (todos los roles)
    DELETE FROM ModulosRoles
    WHERE IdModulo = @IdModuloRollback;

    -- 1) Borrar el submódulo
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
