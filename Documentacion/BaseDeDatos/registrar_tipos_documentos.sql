/* ============================================================================
   Registro del submódulo "Tipos de documento" en el menú
   (Maestros > Parámetros administración > Tipos de documento)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Resuelve por NOMBRE el Id del módulo contenedor "Parámetros
        administración" dentro de la loseta "Maestros" (IdLoseta = 23). Hoy es
        el Id = 51, pero no se clava: se busca igual que en
        registrar_parametros_administracion.sql.
     2) Inserta el submódulo "Tipos de documento" colgando de ese contenedor, con
        Ruta = 'maestros/tipos-documentos' e Icono = 'bi bi-person-vcard'.
     3) Le crea el permiso en ModulosRoles para el rol 1 (Desarrollador). Sin esa
        fila el sidebar NO muestra la opción, aunque el módulo exista.

   La pantalla es el CRUD de dbo.gTipoDocumento (tipos de documento de
   identificación del legado: TI, CC, CE, NIT), el catálogo que consume
   cTercero.tipoDocumento. Este script NO toca esa tabla: solo registra la
   opción de menú.

   El contenedor ya debe existir: lo crea
   Documentacion/BaseDeDatos/registrar_parametros_administracion.sql. Si no
   está, el script aborta sin tocar nada.

   Los dos INSERT van en UNA transacción con TRY/CATCH: un módulo sin su fila en
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
   "sqlcmd -f 65001 -i registrar_tipos_documentos.sql". El sqlcmd.exe clásico no
   detecta el BOM y sin -f 65001 lee el archivo con la code page ANSI: el
   literal "Parámetros administración" que resuelve el contenedor entraría
   deformado y la búsqueda por nombre no casaría, abortando el registro (y, si
   algún literal acentuado llegara a insertarse, el menú quedaría con mojibake).
   En SSMS no ocurre.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario    INT = 1;       -- <-- Ajustar al Id real del usuario administrador
DECLARE @IdLoseta     INT = 23;      -- Loseta "Maestros"
DECLARE @IdRol        INT = 1;       -- Rol "Desarrollador"
DECLARE @NombrePadre  NVARCHAR(100) = N'Parámetros administración';
DECLARE @Nombre       NVARCHAR(100) = N'Tipos de documento';
DECLARE @Ruta         NVARCHAR(200) = N'maestros/tipos-documentos';
DECLARE @Icono        NVARCHAR(100) = N'bi bi-person-vcard';
DECLARE @Fecha        DATETIME = GETDATE();
DECLARE @IdContenedor INT;
DECLARE @IdModulo     INT;

SELECT TOP 1 @IdContenedor = Id
FROM Modulos
WHERE Nombre = @NombrePadre
  AND IdLoseta = @IdLoseta
  AND IdModulo IS NULL
  AND FechaFinalizacion IS NULL
ORDER BY Id;

IF @IdContenedor IS NULL
BEGIN
    RAISERROR('No existe el contenedor "Parametros administracion" en la loseta 23; ejecute primero registrar_parametros_administracion.sql', 16, 1);
    RETURN;
END

BEGIN TRY

    BEGIN TRAN;

    -- 1) Submódulo "Tipos de documento"
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
   Descomentar y ejecutar para quitar del menú "Tipos de documento".

   Los DELETE de esta reversión son FÍSICOS, no lógicos como en el resto de la
   app (no hay ninguna FK que involucre a Modulos ni a ModulosRoles).
   El DELETE de ModulosRoles borra el permiso del submódulo para TODOS los roles,
   no solo para el rol 1: es lo correcto para una reversión total, pero tenerlo
   presente si se añadieron otros roles a mano.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdModuloRollback INT;

SELECT TOP 1 @IdModuloRollback = m.Id
FROM Modulos m
INNER JOIN Modulos p
        ON p.Id = m.IdModulo
       AND p.Nombre = N'Parámetros administración'
       AND p.FechaFinalizacion IS NULL
WHERE m.Nombre = N'Tipos de documento'
  AND m.IdLoseta = 23
  AND m.FechaFinalizacion IS NULL
ORDER BY m.Id;

IF @IdModuloRollback IS NULL
BEGIN
    RAISERROR('No se encontro el submodulo "Tipos de documento"; se aborta la reversion', 16, 1);
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
