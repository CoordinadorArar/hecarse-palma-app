/* ============================================================================
   Acción "marcar-liquidacion" del módulo Informes › Liquidación
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Registra en ModulosAcciones la acción marcar-liquidacion en el módulo cuya
     Ruta es 'informes/liquidacion' (o 'informes/liquidacion/<n>'), con el mismo
     criterio que usa la aplicación (LiquidacionSiesaModel::idModulo(): primer
     Id activo). Si el módulo no existe se aborta con THROW.

     La acción se exige para marcar y para revertir la marca de liquidación de
     nómina a partir de los documentos NNM de Siesa (aTransaccionTercero.ejecutado).
     La vista previa no la requiere.

     NO la asigna a ningún rol: se asigna desde Administración (al final hay un
     ejemplo comentado). Mientras no exista o no esté asignada, los botones no
     aparecen y los endpoints marcar/revertir responden 403.

   IDEMPOTENTE: se puede ejecutar varias veces.

   Ejecutar contra la base de datos AppPalma (SQL Server), desde SSMS o con
   "sqlcmd -f 65001 -i registrar_accion_marcar_liquidacion.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario INT = 1;
DECLARE @Fecha     DATETIME = GETDATE();
DECLARE @Accion    VARCHAR(100) = 'marcar-liquidacion';
DECLARE @IdModulo  INT;

SELECT TOP 1 @IdModulo = Id
FROM Modulos
WHERE (Ruta = N'informes/liquidacion' OR Ruta LIKE N'informes/liquidacion/%')
  AND FechaFinalizacion IS NULL
ORDER BY Id;

BEGIN TRY

    BEGIN TRAN;

    IF @IdModulo IS NULL
        THROW 50001, N'No existe el módulo Informes › Liquidación (Ruta informes/liquidacion).', 1;

    IF NOT EXISTS (SELECT 1 FROM ModulosAcciones
                    WHERE IdModulo = @IdModulo AND Nombre = @Accion AND FechaFinalizacion IS NULL)
    BEGIN
        INSERT INTO ModulosAcciones (IdModulo, Nombre, Icono, IdUsuario, FechaInicio)
        VALUES (@IdModulo, @Accion, NULL, @IdUsuario, @Fecha);

        PRINT N'Acción marcar-liquidacion creada.';
    END
    ELSE
        PRINT N'La acción marcar-liquidacion ya existia.';

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT m.Id AS IdModulo, m.Nombre AS Modulo, m.Ruta, ma.Id AS IdAccion, ma.Nombre AS Accion
FROM Modulos m
JOIN ModulosAcciones ma ON ma.IdModulo = m.Id AND ma.FechaFinalizacion IS NULL
WHERE m.FechaFinalizacion IS NULL
  AND ma.Nombre = 'marcar-liquidacion'
ORDER BY m.Id;
GO

/* ----------------------------------------------------------------------------
   EJEMPLO (opcional): asignar la acción a un rol. Lo normal es hacerlo desde
   Administración. Ajustar @IdRol, descomentar y ejecutar.
   ----------------------------------------------------------------------------
DECLARE @IdRol     INT = 1;
DECLARE @IdUsuario INT = 1;

INSERT INTO ModulosPermisosAcciones (IdRol, IdAccion, IdUsuario, FechaInicio)
SELECT @IdRol, ma.Id, @IdUsuario, GETDATE()
  FROM ModulosAcciones ma
  JOIN Modulos m ON m.Id = ma.IdModulo AND m.FechaFinalizacion IS NULL
 WHERE ma.Nombre = 'marcar-liquidacion'
   AND ma.FechaFinalizacion IS NULL
   AND NOT EXISTS (SELECT 1 FROM ModulosPermisosAcciones mpa
                    WHERE mpa.IdRol = @IdRol AND mpa.IdAccion = ma.Id AND mpa.FechaFinalizacion IS NULL);
---------------------------------------------------------------------------- */

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (DELETE físicos). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
DELETE FROM ModulosPermisosAcciones
WHERE IdAccion IN (SELECT Id FROM ModulosAcciones WHERE Nombre = 'marcar-liquidacion');

DELETE FROM ModulosAcciones
WHERE Nombre = 'marcar-liquidacion';
---------------------------------------------------------------------------- */
