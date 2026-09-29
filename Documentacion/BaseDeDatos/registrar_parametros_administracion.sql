/* ============================================================================
   Creación del módulo contenedor "Parámetros administración" en la loseta
   "Maestros" (Id = 23) y reparentado del módulo "Empresas" (Id = 24)
   ----------------------------------------------------------------------------
   PROBLEMA QUE CORRIGE
   --------------------
   La opción "Empresas" quedó colgando directamente de la loseta "Maestros"
   (IdLoseta = 23, IdModulo = NULL), pero la ruta real del menú es
   "Maestros > Parámetros administración > Empresas". Falta el nivel intermedio.

   QUÉ HACE
   --------
     1) Inserta el módulo contenedor "Parámetros administración" en la loseta 23
        (Ruta = '' porque es solo un desplegable del sidebar, IdModulo = NULL).
     2) Le crea el permiso en ModulosRoles para el rol 1 (Desarrollador). Sin esa
        fila el sidebar NO muestra el contenedor y, por lo tanto, tampoco a
        "Empresas".
     3) Reparenta "Empresas" (Id = 24) poniéndole IdModulo = <Id del contenedor>.
        Conserva IdLoseta = 23: LosetasModel::getModuloByUsuarioRolAndLoseta()
        filtra "IdLoseta = ? AND IdModulo IS NULL" y
        LosetasModel::getSubModuloByUsuarioRolAndLoseta() filtra
        "IdLoseta = ? AND IdModulo = ?", así que basta asignar IdModulo para que
        "Empresas" pase de la lista de módulos a la de submódulos.

   La Ruta de "Empresas" ('maestros/empresas/23') NO se toca: el sidebar aplica
   preg_replace('#/\d+$#', ...) y reanexa el Id de la loseta, de modo que la ruta
   funciona igual en la rama de submódulo.

   Los tres pasos van en UNA transacción con TRY/CATCH: solo tienen sentido
   juntos. Si "Empresas" quedara reparentada sin el permiso del contenedor, la
   opción desaparecería del menú.

   IDEMPOTENTE: se puede ejecutar varias veces. Si el contenedor ya existe se
   resuelve su Id por nombre en lugar de insertarlo de nuevo, el permiso se
   inserta solo si no existe activo, y el UPDATE de "Empresas" solo actúa si su
   IdModulo aún no apunta al contenedor.

   Ejecutar contra la base de datos AppPalma (SQL Server).
   NOTA: Modulos.Id y ModulosRoles.Id son IDENTITY; el script no fija Ids, los
   captura con SCOPE_IDENTITY().

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8 CON BOM. Ejecutarlo SOLO desde SSMS o, por consola,
   con "sqlcmd -f 65001 -i registrar_parametros_administracion.sql". El
   sqlcmd.exe clásico no detecta el BOM y sin -f 65001 lee el archivo con la
   code page ANSI: "Parámetros administración" entraría a la base como
   "ParÃ¡metros administraciÃ³n" (menú con mojibake y, además, la idempotencia
   por nombre rota desde la corrida siguiente, porque esa forma no casa con el
   literal correcto). En SSMS no ocurre.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario   INT = 1; -- <-- Ajustar al Id real del usuario administrador
DECLARE @IdLoseta    INT = 23;      -- Loseta "Maestros"
DECLARE @IdEmpresas  INT = 24;      -- Módulo "Empresas"
DECLARE @IdRol       INT = 1;       -- Rol "Desarrollador"
DECLARE @Nombre      NVARCHAR(100) = N'Parámetros administración';
DECLARE @Fecha       DATETIME = GETDATE();
DECLARE @IdContenedor INT;

BEGIN TRY

    BEGIN TRAN;

    -- 1) Módulo contenedor "Parámetros administración"
    SELECT TOP 1 @IdContenedor = Id
    FROM Modulos
    WHERE Nombre = @Nombre
      AND IdLoseta = @IdLoseta
      AND IdModulo IS NULL
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdContenedor IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (@Nombre, N'', N'bi bi-sliders2', @IdLoseta, NULL, @IdUsuario, @Fecha);

        SET @IdContenedor = SCOPE_IDENTITY();

        PRINT CONCAT(N'Contenedor creado con Id = ', @IdContenedor);
    END
    ELSE
        PRINT CONCAT(N'Contenedor ya existía con Id = ', @IdContenedor, N'. No se inserta de nuevo.');

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
        PRINT N'El permiso del contenedor ya existía en ModulosRoles.';

    -- 3) Reparentado de "Empresas" bajo el contenedor
    UPDATE Modulos
    SET IdModulo = @IdContenedor,
        FechaModificacion = @Fecha
    WHERE Id = @IdEmpresas
      AND ISNULL(IdModulo, -1) <> @IdContenedor;

    IF @@ROWCOUNT > 0
        PRINT N'"Empresas" reparentada bajo el contenedor.';
    ELSE
        PRINT N'"Empresas" ya estaba colgando del contenedor.';

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
   REVERSIÓN MANUAL (deshacer los tres pasos)
   Descomentar y ejecutar para volver al estado anterior: "Empresas" colgando
   directo de la loseta "Maestros" y sin el módulo contenedor.

   Los DELETE de esta reversión son FÍSICOS, no lógicos como en el resto de la
   app (no hay ninguna FK que involucre a Modulos ni a ModulosRoles).
   El DELETE de ModulosRoles borra el permiso del contenedor para TODOS los
   roles, no solo para el rol 1: es lo correcto para una reversión total, pero
   tenerlo presente si se añadieron otros roles a mano.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdContenedorRollback INT;

SELECT @IdContenedorRollback = Id
FROM Modulos
WHERE Nombre = N'Parámetros administración'
  AND IdLoseta = 23
  AND IdModulo IS NULL
  AND FechaFinalizacion IS NULL;

IF @IdContenedorRollback IS NULL
BEGIN
    RAISERROR('No se encontro el contenedor; se aborta la reversion', 16, 1);
    RETURN;
END

BEGIN TRY

    BEGIN TRAN;

    -- 3) Devolver "Empresas" al nivel de módulo de la loseta
    UPDATE Modulos
    SET IdModulo = NULL,
        FechaModificacion = GETDATE()
    WHERE Id = 24;

    -- 2) Borrar el permiso del contenedor (todos los roles)
    DELETE FROM ModulosRoles
    WHERE IdModulo = @IdContenedorRollback;

    -- 1) Borrar el contenedor
    DELETE FROM Modulos
    WHERE Id = @IdContenedorRollback;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
