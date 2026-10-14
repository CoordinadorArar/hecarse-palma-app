/* ============================================================================
   Registro del submódulo "Presupuesto de producción" (Gestión Palma > Contabilidad)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Ubica la loseta "Gestión Palma" por Ruta = 'gestion-palma/onboarding' e
        IdLoseta IS NULL (debe existir).
     2) Ubica el módulo "Contabilidad" como padre del submódulo con Ruta
        'gestion-palma/contabilidad/periodos' (debe existir).
     3) Crea, si no existe, el submódulo:
          Presupuesto de producción  gestion-palma/contabilidad/presupuesto  bi bi-bullseye
        con Orden = siguiente entre sus hermanos (si existe la columna Orden).
     4) Da permiso en ModulosRoles al submódulo para los MISMOS roles que hoy
        tienen permiso activo sobre 'gestion-palma/contabilidad/periodos'.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i registrar_modulo_presupuesto_produccion.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario  INT = 1;
DECLARE @Fecha      DATETIME = GETDATE();
DECLARE @Ruta       VARCHAR(200) = 'gestion-palma/contabilidad/presupuesto';
DECLARE @IdLoseta   INT;
DECLARE @IdPeriodos INT;
DECLARE @IdModulo   INT;
DECLARE @IdNuevo    INT;

BEGIN TRY

    BEGIN TRAN;

    SELECT TOP 1 @IdLoseta = Id
    FROM Modulos
    WHERE Ruta = 'gestion-palma/onboarding'
      AND IdLoseta IS NULL
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdLoseta IS NULL
        THROW 50001, N'No existe la loseta "Gestión Palma" (Ruta gestion-palma/onboarding).', 1;

    SELECT TOP 1 @IdPeriodos = Id, @IdModulo = IdModulo
    FROM Modulos
    WHERE Ruta = 'gestion-palma/contabilidad/periodos'
      AND IdLoseta = @IdLoseta
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdModulo IS NULL
        THROW 50002, N'No existe el módulo "Contabilidad" (padre de gestion-palma/contabilidad/periodos).', 1;

    SELECT TOP 1 @IdNuevo = Id
    FROM Modulos
    WHERE Ruta = @Ruta
      AND IdLoseta = @IdLoseta
      AND FechaFinalizacion IS NULL
    ORDER BY Id;

    IF @IdNuevo IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (N'Presupuesto de producción', @Ruta, 'bi bi-bullseye', @IdLoseta, @IdModulo, @IdUsuario, @Fecha);

        SET @IdNuevo = SCOPE_IDENTITY();

        IF COL_LENGTH('Modulos', 'Orden') IS NOT NULL
            EXEC sp_executesql
                N'UPDATE Modulos
                     SET Orden = (SELECT ISNULL(MAX(Orden), 0) + 1 FROM Modulos
                                   WHERE IdLoseta = @IdLoseta AND IdModulo = @IdModulo AND Id <> @IdNuevo)
                   WHERE Id = @IdNuevo',
                N'@IdLoseta INT, @IdModulo INT, @IdNuevo INT',
                @IdLoseta = @IdLoseta, @IdModulo = @IdModulo, @IdNuevo = @IdNuevo;

        PRINT CONCAT(N'Submódulo creado con Id ', @IdNuevo);
    END
    ELSE
        PRINT CONCAT(N'El submódulo ya existía con Id ', @IdNuevo);

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT DISTINCT @IdNuevo, pr.IdRol, @Fecha
      FROM ModulosRoles pr
     WHERE pr.IdModulo = @IdPeriodos
       AND pr.FechaFinalizacion IS NULL
       AND NOT EXISTS (SELECT 1 FROM ModulosRoles mr
                        WHERE mr.IdModulo = @IdNuevo AND mr.IdRol = pr.IdRol AND mr.FechaFinalizacion IS NULL);

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
WHERE m.IdModulo = @IdModulo
  AND m.FechaFinalizacion IS NULL
GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo
ORDER BY m.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (DELETE físicos). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

DECLARE @ids TABLE (Id INT);

INSERT INTO @ids (Id)
SELECT Id FROM Modulos WHERE Ruta = 'gestion-palma/contabilidad/presupuesto';

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
