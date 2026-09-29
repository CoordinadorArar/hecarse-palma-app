/* ============================================================================
   Registro del menú "Tipo de Transacción > Registro"
   (Maestros > Tipo de Transacción > Registro)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Crea el módulo CONTENEDOR "Tipo de Transacción" en la loseta "Maestros"
        (IdLoseta = 23), con Ruta = '' e IdModulo = NULL. Es un contenedor NUEVO,
        hermano de "Parámetros administración" (Id = 51), no cuelga de él.
     2) Crea el submódulo "Registro" colgando de ese contenedor, con
        Ruta = 'maestros/tipos-transaccion/registro'.
     3) Crea el permiso en ModulosRoles para el rol 1 (Desarrollador) de LOS DOS:
        sin el permiso del contenedor el sidebar no muestra la rama, y sin el del
        submódulo no muestra la opción.

   La pantalla es el CRUD de dbo.gTipoTransaccion. Este script NO toca esa tabla:
   solo registra las opciones de menú.

   Los INSERT van en UNA transacción con TRY/CATCH: un módulo sin su fila en
   ModulosRoles no aparece en el menú y quedaría como basura invisible.

   IDEMPOTENTE: se puede ejecutar varias veces. Si el contenedor o el submódulo
   ya existen se resuelven por nombre/loseta/padre en lugar de insertarlos de
   nuevo, y cada permiso se inserta solo si no hay uno activo.

   Ejecutar contra la base de datos AppPalma (SQL Server).
   NOTA: Modulos.Id y ModulosRoles.Id son IDENTITY; el script no fija Ids, los
   captura con SCOPE_IDENTITY().

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8. Ejecutarlo SOLO desde SSMS o, por consola, con
   "sqlcmd -f 65001 -i registrar_tipos_transaccion.sql". El sqlcmd.exe clásico
   lee el archivo con la code page ANSI si no se le pasa -f 65001: el literal
   "Tipo de Transacción" entraría deformado al menú y, desde la corrida
   siguiente, la idempotencia por nombre dejaría de casar y se duplicarían las
   filas. En SSMS no ocurre.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario    INT = 1;       -- <-- Ajustar al Id real del usuario administrador
DECLARE @IdLoseta     INT = 23;      -- Loseta "Maestros"
DECLARE @IdRol        INT = 1;       -- Rol "Desarrollador"
DECLARE @NombrePadre  NVARCHAR(100) = N'Tipo de Transacción';
DECLARE @IconoPadre   NVARCHAR(100) = N'bi bi-arrow-left-right';
DECLARE @Nombre       NVARCHAR(100) = N'Registro';
DECLARE @Ruta         NVARCHAR(200) = N'maestros/tipos-transaccion/registro';
DECLARE @Icono        NVARCHAR(100) = N'bi bi-card-checklist';
DECLARE @Fecha        DATETIME = GETDATE();
DECLARE @IdContenedor INT;
DECLARE @IdModulo     INT;

BEGIN TRY

    BEGIN TRAN;

    -- 1) Módulo contenedor "Tipo de Transacción"
    SELECT TOP 1 @IdContenedor = Id
    FROM Modulos
    WHERE Nombre = @NombrePadre
      AND IdLoseta = @IdLoseta
      AND IdModulo IS NULL
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdContenedor IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (@NombrePadre, N'', @IconoPadre, @IdLoseta, NULL, @IdUsuario, @Fecha);

        SET @IdContenedor = SCOPE_IDENTITY();

        PRINT CONCAT(N'Contenedor creado con Id = ', @IdContenedor);
    END
    ELSE
        PRINT CONCAT(N'El contenedor ya existia con Id = ', @IdContenedor, N'. No se inserta de nuevo.');

    -- 2) Permiso del contenedor para el rol 1
    IF NOT EXISTS (
        SELECT 1
        FROM ModulosRoles
        WHERE IdModulo = @IdContenedor
          AND IdRol = @IdRol
          AND FechaFinalizacion IS NULL
    )
    BEGIN
        INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
        VALUES (@IdContenedor, @IdRol, @Fecha);

        PRINT N'Permiso del contenedor creado en ModulosRoles.';
    END
    ELSE
        PRINT N'El permiso del contenedor ya existia en ModulosRoles.';

    -- 3) Submódulo "Registro"
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

    -- 4) Permiso del submódulo para el rol 1
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
   REVERSIÓN MANUAL (deshacer los cuatro pasos)
   Descomentar y ejecutar para quitar del menú "Tipo de Transacción > Registro".

   Los DELETE de esta reversión son FÍSICOS, no lógicos como en el resto de la
   app (no hay ninguna FK que involucre a Modulos ni a ModulosRoles).
   Los DELETE de ModulosRoles borran los permisos para TODOS los roles, no solo
   para el rol 1: es lo correcto para una reversión total, pero tenerlo presente
   si se añadieron otros roles a mano.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdContenedorRollback INT;
DECLARE @IdModuloRollback     INT;

SELECT TOP 1 @IdContenedorRollback = Id
FROM Modulos
WHERE Nombre = N'Tipo de Transacción'
  AND IdLoseta = 23
  AND IdModulo IS NULL
  AND FechaFinalizacion IS NULL
ORDER BY Id;

IF @IdContenedorRollback IS NULL
BEGIN
    RAISERROR('No se encontro el contenedor "Tipo de Transaccion"; se aborta la reversion', 16, 1);
    RETURN;
END

SELECT TOP 1 @IdModuloRollback = Id
FROM Modulos
WHERE Nombre = N'Registro'
  AND IdLoseta = 23
  AND IdModulo = @IdContenedorRollback
  AND FechaFinalizacion IS NULL
ORDER BY Id;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosRoles
    WHERE IdModulo IN (@IdContenedorRollback, @IdModuloRollback);

    DELETE FROM Modulos
    WHERE Id IN (@IdContenedorRollback, @IdModuloRollback);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
