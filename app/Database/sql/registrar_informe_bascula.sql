/* ============================================================================
   Registro del módulo "Informe báscula" (loseta Informes)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Ubica la loseta "Informes" por Ruta = 'informes/onboarding' e
        IdLoseta IS NULL (debe existir).
     2) Toma como hermano el módulo 'dashboards/produccion' (Tablero de
        producción). Si está en la loseta Informes, usa su mismo IdModulo
        padre y queda en el Orden siguiente; si no, cuelga de la loseta
        (IdModulo = NULL) al final del Orden.
     3) Crea, si no existe:
          Informe báscula  dashboards/bascula  bi bi-truck
     4) Da permiso en ModulosRoles para los MISMOS roles que hoy tienen
        permiso activo sobre 'dashboards/produccion' (o sobre la loseta si
        el hermano no existe).

   IDEMPOTENTE: se puede ejecutar varias veces.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resultado y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con
     "sqlcmd -f 65001 -i registrar_informe_bascula.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar    BIT = 0;
DECLARE @IdUsuario  INT = 1;
DECLARE @Fecha      DATETIME = GETDATE();
DECLARE @IdLoseta   INT;
DECLARE @IdHermano  INT;
DECLARE @IdModulo   INT;
DECLARE @IdNuevo    INT;
DECLARE @Nombre     NVARCHAR(100) = N'Informe báscula';
DECLARE @Ruta       NVARCHAR(200) = N'dashboards/bascula';
DECLARE @Icono      NVARCHAR(100) = N'bi bi-truck';

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

    SELECT TOP 1 @IdHermano = Id
    FROM Modulos
    WHERE Ruta = N'dashboards/produccion'
      AND FechaFinalizacion IS NULL
    ORDER BY CASE WHEN IdLoseta = @IdLoseta THEN 0 ELSE 1 END, Id;

    SELECT @IdModulo = IdModulo
    FROM Modulos
    WHERE Id = @IdHermano
      AND IdLoseta = @IdLoseta;

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    SELECT @Nombre, @Ruta, @Icono, @IdLoseta, @IdModulo, @IdUsuario, @Fecha
     WHERE NOT EXISTS (SELECT 1 FROM Modulos m
                        WHERE m.Ruta = @Ruta AND m.IdLoseta = @IdLoseta AND m.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Módulos creados: ', @@ROWCOUNT);

    SELECT TOP 1 @IdNuevo = Id
    FROM Modulos
    WHERE Ruta = @Ruta
      AND IdLoseta = @IdLoseta
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF COL_LENGTH('Modulos', 'Orden') IS NOT NULL
        EXEC sp_executesql
            N'IF EXISTS (SELECT 1 FROM Modulos WHERE Id = @IdNuevo AND Orden IS NULL)
              BEGIN
                  DECLARE @Base INT = (SELECT Orden FROM Modulos WHERE Id = @IdHermano AND IdLoseta = @IdLoseta);

                  IF @Base IS NULL
                  BEGIN
                      SET @Base = (SELECT ISNULL(MAX(Orden), 0) FROM Modulos
                                    WHERE IdLoseta = @IdLoseta
                                      AND ((IdModulo IS NULL AND @IdModulo IS NULL) OR IdModulo = @IdModulo)
                                      AND FechaFinalizacion IS NULL);
                  END
                  ELSE
                  BEGIN
                      UPDATE Modulos
                         SET Orden = Orden + 1
                       WHERE IdLoseta = @IdLoseta
                         AND ((IdModulo IS NULL AND @IdModulo IS NULL) OR IdModulo = @IdModulo)
                         AND FechaFinalizacion IS NULL
                         AND Orden > @Base
                         AND Id <> @IdNuevo;
                  END

                  UPDATE Modulos SET Orden = @Base + 1 WHERE Id = @IdNuevo;
              END',
            N'@IdNuevo INT, @IdHermano INT, @IdLoseta INT, @IdModulo INT',
            @IdNuevo = @IdNuevo, @IdHermano = @IdHermano, @IdLoseta = @IdLoseta, @IdModulo = @IdModulo;

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT DISTINCT @IdNuevo, hr.IdRol, @Fecha
      FROM ModulosRoles hr
     WHERE hr.IdModulo = ISNULL(@IdHermano, @IdLoseta)
       AND hr.FechaFinalizacion IS NULL
       AND NOT EXISTS (SELECT 1 FROM ModulosRoles mr
                        WHERE mr.IdModulo = @IdNuevo AND mr.IdRol = hr.IdRol AND mr.FechaFinalizacion IS NULL);

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
   Solo quita el módulo creado por este script y sus permisos.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @ids TABLE (Id INT);

INSERT INTO @ids (Id)
SELECT Id FROM Modulos WHERE Ruta = N'dashboards/bascula';

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
