/*
    Asignación a roles de los módulos y submódulos de la loseta "Gestión Palma" (IdLoseta = 25).

    PROBLEMA QUE CORRIGE
    --------------------
    Los 15 submódulos (Ids 36-50) y el módulo "Parámetros agronómicos" (Id 29) fueron
    creados en "Modulos" pero nunca se asignaron en "ModulosRoles".

    Como LosetasModel::getSubModuloByUsuarioRolAndLoseta() hace JOIN contra ModulosRoles,
    devuelve un array vacío, el sidebar no encuentra hijos para Contabilidad / Nómina /
    Lotes / Labores / Lista de precios / Características y los pinta como opción simple.
    Al tener esos módulos padre Ruta = '', terminan renderizados como un <span> inerte
    con el título "Sin submódulos asignados", sin menú collapse.

    El template app/Views/template/sidebar.php YA implementa el collapse correctamente;
    no requiere cambios. Esto es exclusivamente una corrección de datos.

    ALCANCE
    -------
    Rol destino: IdRol = 1 (Desarrollador), que es el único rol que hoy tiene asignados
    los módulos padre de esta loseta. Si se requiere habilitar la loseta a otros roles,
    cambiar @IdRol y volver a ejecutar.

    Es idempotente: sólo inserta las asignaciones que aún no existan activas.

    REVERSIÓN
    ---------
    UPDATE ModulosRoles
       SET FechaFinalizacion = GETDATE()
     WHERE IdRol = 1
       AND IdModulo IN (29, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50)
       AND FechaFinalizacion IS NULL;
*/

USE AppPalma;
GO

SET NOCOUNT ON;

DECLARE @IdLoseta INT = 25;
DECLARE @IdRol    INT = 1;
DECLARE @Fecha    DATETIME = GETDATE();

BEGIN TRY

    BEGIN TRAN;

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT m.Id, @IdRol, @Fecha
    FROM Modulos m
    WHERE m.IdLoseta = @IdLoseta
      AND m.FechaFinalizacion IS NULL
      AND NOT EXISTS (
            SELECT 1
            FROM ModulosRoles mr
            WHERE mr.IdModulo = m.Id
              AND mr.IdRol = @IdRol
              AND mr.FechaFinalizacion IS NULL
      );

    PRINT CONCAT('Asignaciones creadas: ', @@ROWCOUNT);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

/* Verificación: todos los módulos y submódulos deben quedar con roles >= 1 */
SELECT
    m.Id,
    m.Nombre,
    m.IdModulo AS IdModuloPadre,
    m.Ruta,
    COUNT(mr.IdRol) AS Roles
FROM Modulos m
LEFT JOIN ModulosRoles mr
       ON mr.IdModulo = m.Id
      AND mr.FechaFinalizacion IS NULL
WHERE m.IdLoseta = @IdLoseta
  AND m.FechaFinalizacion IS NULL
GROUP BY m.Id, m.Nombre, m.IdModulo, m.Ruta
ORDER BY ISNULL(m.IdModulo, 0), m.Id;
