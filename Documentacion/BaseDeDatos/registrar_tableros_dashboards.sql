/* ============================================================================
   Registro de los tableros "Tablero de producción" y "Tablero de fertilización"
   (loseta Dashboards)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Ubica el módulo "Informes Gerenciales" por Ruta = 'dashboards/admin'
        (debe existir) y toma su IdLoseta (Dashboards) e IdModulo (padre).
     2) Crea, si no existen, como hermanos de Informes Gerenciales:
          Tablero de producción     dashboards/produccion     bi bi-bar-chart-line
          Tablero de fertilización  dashboards/fertilizacion  bi bi-droplet-half
        con Orden después de Informes Gerenciales (si existe la columna Orden).
     3) Da permiso en ModulosRoles a cada tablero para los MISMOS roles que hoy
        tienen permiso activo sobre 'dashboards/admin'.

   IDEMPOTENTE: se puede ejecutar varias veces.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resultado y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con
     "sqlcmd -f 65001 -i registrar_tableros_dashboards.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar    BIT = 0;
DECLARE @IdUsuario  INT = 1;
DECLARE @Fecha      DATETIME = GETDATE();
DECLARE @IdGerencia INT;
DECLARE @IdLoseta   INT;
DECLARE @IdModulo   INT;

DECLARE @tableros TABLE (Orden INT, Nombre NVARCHAR(100), Ruta NVARCHAR(200), Icono NVARCHAR(100));

INSERT INTO @tableros (Orden, Nombre, Ruta, Icono)
VALUES (1, N'Tablero de producción',    N'dashboards/produccion',    N'bi bi-bar-chart-line'),
       (2, N'Tablero de fertilización', N'dashboards/fertilizacion', N'bi bi-droplet-half');

BEGIN TRY

    BEGIN TRAN;

    SELECT TOP 1 @IdGerencia = Id, @IdLoseta = IdLoseta, @IdModulo = IdModulo
    FROM Modulos
    WHERE Ruta = 'dashboards/admin'
      AND IdLoseta IS NOT NULL
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdGerencia IS NULL
        THROW 50001, N'No existe el módulo "Informes Gerenciales" (Ruta dashboards/admin).', 1;

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    SELECT t.Nombre, t.Ruta, t.Icono, @IdLoseta, @IdModulo, @IdUsuario, @Fecha
      FROM @tableros t
     WHERE NOT EXISTS (SELECT 1 FROM Modulos m
                        WHERE m.Ruta = t.Ruta AND m.IdLoseta = @IdLoseta AND m.FechaFinalizacion IS NULL)
     ORDER BY t.Orden;

    PRINT CONCAT(N'Tableros creados: ', @@ROWCOUNT);

    IF COL_LENGTH('Modulos', 'Orden') IS NOT NULL
        EXEC sp_executesql
            N'DECLARE @Base INT = (SELECT ISNULL(Orden, 0) FROM Modulos WHERE Id = @IdGerencia);

              UPDATE m
                 SET Orden = m.Orden + 2
                FROM Modulos m
               WHERE m.IdLoseta = @IdLoseta
                 AND ((m.IdModulo IS NULL AND @IdModulo IS NULL) OR m.IdModulo = @IdModulo)
                 AND m.FechaFinalizacion IS NULL
                 AND m.Orden > @Base
                 AND m.Ruta NOT IN (N''dashboards/produccion'', N''dashboards/fertilizacion'')
                 AND EXISTS (SELECT 1 FROM Modulos n
                              WHERE n.Ruta IN (N''dashboards/produccion'', N''dashboards/fertilizacion'')
                                AND n.IdLoseta = @IdLoseta AND n.FechaFinalizacion IS NULL AND n.Orden IS NULL);

              UPDATE m
                 SET Orden = @Base + CASE m.Ruta WHEN N''dashboards/produccion'' THEN 1 ELSE 2 END
                FROM Modulos m
               WHERE m.Ruta IN (N''dashboards/produccion'', N''dashboards/fertilizacion'')
                 AND m.IdLoseta = @IdLoseta
                 AND m.FechaFinalizacion IS NULL
                 AND m.Orden IS NULL;',
            N'@IdGerencia INT, @IdLoseta INT, @IdModulo INT',
            @IdGerencia = @IdGerencia, @IdLoseta = @IdLoseta, @IdModulo = @IdModulo;

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT DISTINCT m.Id, gr.IdRol, @Fecha
      FROM Modulos m
      JOIN @tableros t ON t.Ruta = m.Ruta
      JOIN ModulosRoles gr ON gr.IdModulo = @IdGerencia AND gr.FechaFinalizacion IS NULL
     WHERE m.IdLoseta = @IdLoseta
       AND m.FechaFinalizacion IS NULL
       AND NOT EXISTS (SELECT 1 FROM ModulosRoles mr
                        WHERE mr.IdModulo = m.Id AND mr.IdRol = gr.IdRol AND mr.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Permisos creados en ModulosRoles: ', @@ROWCOUNT);

    SELECT m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo AS IdModuloPadre,
           COUNT(mr.IdRol) AS Roles
    FROM Modulos m
    LEFT JOIN ModulosRoles mr ON mr.IdModulo = m.Id AND mr.FechaFinalizacion IS NULL
    WHERE m.IdLoseta = @IdLoseta
      AND m.FechaFinalizacion IS NULL
    GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo
    ORDER BY m.Id;

    IF @Aplicar = 1
    BEGIN
        COMMIT TRAN;
        PRINT N'APLICADO: cambios confirmados.';
    END
    ELSE
    BEGIN
        ROLLBACK TRAN;
        PRINT N'MODO PRUEBA: se revirtió todo. Cambie @Aplicar = 1 para confirmar.';
    END

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (DELETE físicos). Descomentar y ejecutar.
   Solo quita los dos tableros creados por este script y sus permisos.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @ids TABLE (Id INT);

INSERT INTO @ids (Id)
SELECT Id FROM Modulos WHERE Ruta IN (N'dashboards/produccion', N'dashboards/fertilizacion');

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosRoles WHERE IdModulo IN (SELECT Id FROM @ids);
    DELETE FROM Modulos WHERE Id IN (SELECT Id FROM @ids);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
