/* ============================================================================
   Registro de los módulos de la loseta "Informes"
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Ubica la loseta "Informes" por Ruta = 'informes/onboarding' e
        IdLoseta IS NULL (debe existir).
     2) Crea, colgando directamente de la loseta (IdLoseta = loseta,
        IdModulo = NULL) y sin submódulos, los módulos:
          Administración    informes/administracion    bi bi-building-gear
          Revisión Labores  informes/revision-labores  bi bi-clipboard-check
          Transacciones     informes/transacciones     bi bi-arrow-left-right
          Indicadores       informes/indicadores       bi bi-graph-up-arrow
          Sanidad           informes/sanidad           bi bi-bug
          Fertilización     informes/fertilizacion     bi bi-droplet
     3) Da permiso en ModulosRoles a cada módulo para los MISMOS roles que hoy
        tienen permiso activo sobre la loseta.

   Los módulos se resuelven por Ruta, así que no hay Ids fijos.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i registrar_informes_modulos.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario INT = 1;
DECLARE @Fecha     DATETIME = GETDATE();
DECLARE @IdLoseta  INT;

DECLARE @modulos TABLE (Orden INT, Nombre NVARCHAR(100), Ruta NVARCHAR(200), Icono NVARCHAR(100));

INSERT INTO @modulos (Orden, Nombre, Ruta, Icono)
VALUES (1, N'Administración',   N'informes/administracion',   N'bi bi-building-gear'),
       (2, N'Revisión Labores', N'informes/revision-labores', N'bi bi-clipboard-check'),
       (3, N'Transacciones',    N'informes/transacciones',    N'bi bi-arrow-left-right'),
       (4, N'Indicadores',      N'informes/indicadores',      N'bi bi-graph-up-arrow'),
       (5, N'Sanidad',          N'informes/sanidad',          N'bi bi-bug'),
       (6, N'Fertilización',    N'informes/fertilizacion',    N'bi bi-droplet');

BEGIN TRY

    BEGIN TRAN;

    SELECT TOP 1 @IdLoseta = Id
    FROM Modulos
    WHERE Ruta = N'informes/onboarding'
      AND IdLoseta IS NULL
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdLoseta IS NULL
        THROW 50001, N'No existe la loseta "Informes" (Ruta informes/onboarding).', 1;

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    SELECT mo.Nombre, mo.Ruta, mo.Icono, @IdLoseta, NULL, @IdUsuario, @Fecha
      FROM @modulos mo
     WHERE NOT EXISTS (SELECT 1 FROM Modulos m
                        WHERE m.Ruta = mo.Ruta AND m.IdLoseta = @IdLoseta AND m.FechaFinalizacion IS NULL)
     ORDER BY mo.Orden;

    PRINT CONCAT(N'Modulos creados: ', @@ROWCOUNT);

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT DISTINCT m.Id, lr.IdRol, @Fecha
      FROM Modulos m
      JOIN @modulos mo ON mo.Ruta = m.Ruta
      JOIN ModulosRoles lr ON lr.IdModulo = @IdLoseta AND lr.FechaFinalizacion IS NULL
     WHERE m.IdLoseta = @IdLoseta
       AND m.FechaFinalizacion IS NULL
       AND NOT EXISTS (SELECT 1 FROM ModulosRoles mr
                        WHERE mr.IdModulo = m.Id AND mr.IdRol = lr.IdRol AND mr.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Permisos creados en ModulosRoles: ', @@ROWCOUNT);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo AS IdModuloPadre,
       COUNT(mr.IdRol) AS Roles
FROM Modulos m
LEFT JOIN ModulosRoles mr ON mr.IdModulo = m.Id AND mr.FechaFinalizacion IS NULL
WHERE (m.Id = @IdLoseta OR m.IdLoseta = @IdLoseta)
  AND m.FechaFinalizacion IS NULL
GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo
ORDER BY ISNULL(m.IdLoseta, m.Id), m.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (DELETE físicos). Descomentar y ejecutar.
   Solo quita los seis módulos creados por este script y sus permisos; la
   loseta y sus hijos previos (Liquidación, Onboarding, Contabilizar) no se tocan.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @IdLosetaRollback INT;

SELECT TOP 1 @IdLosetaRollback = Id
FROM Modulos
WHERE Ruta = N'informes/onboarding'
  AND IdLoseta IS NULL
  AND FechaFinalizacion IS NULL
ORDER BY Id;

IF @IdLosetaRollback IS NULL
BEGIN
    RAISERROR('No se encontro la loseta "Informes"; se aborta la reversion', 16, 1);
    RETURN;
END

DECLARE @ids TABLE (Id INT);

INSERT INTO @ids (Id)
SELECT Id
FROM Modulos
WHERE IdLoseta = @IdLosetaRollback
  AND Ruta IN (N'informes/administracion', N'informes/revision-labores', N'informes/transacciones',
               N'informes/indicadores', N'informes/sanidad', N'informes/fertilizacion');

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosRoles
    WHERE IdModulo IN (SELECT Id FROM @ids);

    DELETE FROM Modulos
    WHERE Id IN (SELECT Id FROM @ids);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
