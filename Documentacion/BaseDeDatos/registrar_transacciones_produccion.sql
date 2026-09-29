/* ============================================================================
   Registro de la loseta "Transacciones" y del módulo "Producción"
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Crea la LOSETA "Transacciones": una fila de Modulos con IdLoseta = NULL
        e IdModulo = NULL, Ruta = 'transacciones/onboarding'. Es la séptima
        loseta, hermana de Administración (1), Dashboards (8), Informes (11),
        Gestión Documental (16), Maestros (23) y Gestión Palma (25).
     2) Crea el submódulo "Producción" colgando de esa loseta, con
        IdLoseta = <Id de la loseta>, IdModulo = NULL y
        Ruta = 'transacciones/produccion'.
     3) Crea el permiso en ModulosRoles para el rol 1 (Desarrollador) de LOS DOS:
        sin el permiso de la loseta no se ve la portada, y sin el del submódulo
        no se ve la opción en el sidebar.

   La pantalla registra transacciones de producción tipo TLC en aTransaccion,
   aTransaccionBascula, aTransaccionNovedad y aTransaccionTercero. Este script NO
   toca esas tablas: solo registra las opciones de menú.

   Los INSERT van en UNA transacción con TRY/CATCH: un módulo sin su fila en
   ModulosRoles no aparece en el menú y quedaría como basura invisible.

   IDEMPOTENTE: se puede ejecutar varias veces. La loseta y el submódulo se
   resuelven por nombre antes de insertarlos, y cada permiso se inserta solo si
   no hay uno activo.

   Ejecutar contra la base de datos AppPalma (SQL Server).
   NOTA: Modulos.Id y ModulosRoles.Id son IDENTITY; el script no fija Ids, los
   captura con SCOPE_IDENTITY().

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8. Ejecutarlo SOLO desde SSMS o, por consola, con
   "sqlcmd -f 65001 -i registrar_transacciones_produccion.sql". El sqlcmd.exe
   clásico lee el archivo con la code page ANSI si no se le pasa -f 65001: el
   literal "Producción" entraría deformado al menú y, desde la corrida
   siguiente, la idempotencia por nombre dejaría de casar y se duplicarían las
   filas. En SSMS no ocurre.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario     INT = 1;       -- <-- Ajustar al Id real del usuario administrador
DECLARE @IdRol         INT = 1;       -- Rol "Desarrollador"
DECLARE @NombreLoseta  NVARCHAR(100) = N'Transacciones';
DECLARE @RutaLoseta    NVARCHAR(200) = N'transacciones/onboarding';
DECLARE @IconoLoseta   NVARCHAR(100) = N'bi bi-arrow-left-right';
DECLARE @Nombre        NVARCHAR(100) = N'Producción';
DECLARE @Ruta          NVARCHAR(200) = N'transacciones/produccion';
DECLARE @Icono         NVARCHAR(100) = N'bi bi-basket';
DECLARE @Fecha         DATETIME = GETDATE();
DECLARE @IdLoseta      INT;
DECLARE @IdModulo      INT;

BEGIN TRY

    BEGIN TRAN;

    -- 1) Loseta "Transacciones"
    SELECT TOP 1 @IdLoseta = Id
    FROM Modulos
    WHERE Nombre = @NombreLoseta
      AND IdLoseta IS NULL
      AND IdModulo IS NULL
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdLoseta IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (@NombreLoseta, @RutaLoseta, @IconoLoseta, NULL, NULL, @IdUsuario, @Fecha);

        SET @IdLoseta = SCOPE_IDENTITY();

        PRINT CONCAT(N'Loseta creada con Id = ', @IdLoseta);
    END
    ELSE
        PRINT CONCAT(N'La loseta ya existia con Id = ', @IdLoseta, N'. No se inserta de nuevo.');

    -- 2) Permiso de la loseta para el rol 1
    IF NOT EXISTS (
        SELECT 1
        FROM ModulosRoles
        WHERE IdModulo = @IdLoseta
          AND IdRol = @IdRol
          AND FechaFinalizacion IS NULL
    )
    BEGIN
        INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
        VALUES (@IdLoseta, @IdRol, @Fecha);

        PRINT N'Permiso de la loseta creado en ModulosRoles.';
    END
    ELSE
        PRINT N'El permiso de la loseta ya existia en ModulosRoles.';

    -- 3) Submódulo "Producción"
    SELECT TOP 1 @IdModulo = Id
    FROM Modulos
    WHERE Nombre = @Nombre
      AND IdLoseta = @IdLoseta
      AND IdModulo IS NULL
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

/* Verificación: la loseta nueva y su árbol */
SELECT
    m.Id,
    m.Nombre,
    m.Ruta,
    m.Icono,
    m.IdLoseta,
    m.IdModulo AS IdModuloPadre,
    COUNT(mr.IdRol) AS Roles
FROM Modulos m
LEFT JOIN ModulosRoles mr
       ON mr.IdModulo = m.Id
      AND mr.FechaFinalizacion IS NULL
WHERE (m.Id = @IdLoseta OR m.IdLoseta = @IdLoseta)
  AND m.FechaFinalizacion IS NULL
GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo
ORDER BY ISNULL(m.IdLoseta, m.Id), m.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (deshacer los cuatro pasos)
   Descomentar y ejecutar para quitar del menú la loseta "Transacciones" y el
   módulo "Producción".

   Los DELETE de esta reversión son FÍSICOS, no lógicos como en el resto de la
   app (no hay ninguna FK que involucre a Modulos ni a ModulosRoles).
   Los DELETE de ModulosRoles borran los permisos para TODOS los roles, no solo
   para el rol 1: es lo correcto para una reversión total, pero tenerlo presente
   si se añadieron otros roles a mano.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdLosetaRollback INT;
DECLARE @IdModuloRollback INT;

SELECT TOP 1 @IdLosetaRollback = Id
FROM Modulos
WHERE Nombre = N'Transacciones'
  AND IdLoseta IS NULL
  AND IdModulo IS NULL
  AND FechaFinalizacion IS NULL
ORDER BY Id;

IF @IdLosetaRollback IS NULL
BEGIN
    RAISERROR('No se encontro la loseta "Transacciones"; se aborta la reversion', 16, 1);
    RETURN;
END

SELECT TOP 1 @IdModuloRollback = Id
FROM Modulos
WHERE Nombre = N'Producción'
  AND IdLoseta = @IdLosetaRollback
  AND IdModulo IS NULL
  AND FechaFinalizacion IS NULL
ORDER BY Id;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosRoles
    WHERE IdModulo IN (@IdLosetaRollback, @IdModuloRollback);

    DELETE FROM Modulos
    WHERE Id IN (@IdLosetaRollback, @IdModuloRollback);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
