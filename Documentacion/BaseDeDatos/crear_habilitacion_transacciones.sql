/* ============================================================================
   Habilitación para edición de transacciones liquidadas en nómina
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Crea la tabla aTransaccionHabilitacion. Cada fila es una habilitación
        de UN SOLO USO que un superior concede, con motivo obligatorio, para
        editar una transacción ya liquidada o ejecutada en nómina (Labores TLA,
        Producción TLC, Fertilización RLF). Vence a las 24 horas (FechaVence);
        al guardar la edición la aplicación llena IdUsuarioUso y FechaUso y la
        transacción vuelve a quedar bloqueada.
          - empresa, tipo, numero: llave de la transacción (mismos tipos que
            aTransaccionEliminada; numero se guarda sin espacios).
          - Índice IX_aTransaccionHabilitacion_llave por (empresa, tipo, numero).
     2) Registra en ModulosAcciones las acciones habilitar-labores,
        habilitar-produccion y habilitar-fertilizacion en el módulo de cada
        pantalla, con el mismo criterio que usa la aplicación: Labores y
        Fertilización por Modulos.Ruta (primer Id activo, como idModulo()) y
        Producción por el Id 58 (ProduccionController::MODULO). Si alguno no
        existe se aborta con THROW. NO las asigna a ningún rol: se asignan
        desde Administración (al final hay un ejemplo comentado).

   Mientras este script no se ejecute la aplicación sigue funcionando: detecta
   la tabla con OBJECT_ID; las transacciones liquidadas quedan bloqueadas para
   editar y el endpoint "habilitar" responde que falta ejecutar este script.

   IDEMPOTENTE: se puede ejecutar varias veces.

   Ejecutar contra la base de datos AppPalma (SQL Server), desde SSMS o con
   "sqlcmd -f 65001 -i crear_habilitacion_transacciones.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID('dbo.aTransaccionHabilitacion', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.aTransaccionHabilitacion (
        Id                INT            IDENTITY(1, 1) NOT NULL,
        empresa           INT            NOT NULL,
        tipo              VARCHAR(50)    NOT NULL,
        numero            VARCHAR(50)    NOT NULL,
        motivo            NVARCHAR(250)  NOT NULL,
        IdUsuarioHabilita INT            NOT NULL,
        FechaHabilitacion DATETIME       NOT NULL CONSTRAINT DF_aTransaccionHabilitacion_fecha DEFAULT (GETDATE()),
        FechaVence        DATETIME       NOT NULL,
        IdUsuarioUso      INT            NULL,
        FechaUso          DATETIME       NULL,
        CONSTRAINT PK_aTransaccionHabilitacion PRIMARY KEY (Id)
    );

    PRINT N'Tabla aTransaccionHabilitacion creada.';
END
ELSE
    PRINT N'La tabla aTransaccionHabilitacion ya existia.';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_aTransaccionHabilitacion_llave'
                  AND object_id = OBJECT_ID('dbo.aTransaccionHabilitacion'))
    CREATE INDEX IX_aTransaccionHabilitacion_llave ON dbo.aTransaccionHabilitacion (empresa, tipo, numero);
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario INT = 1;
DECLARE @Fecha     DATETIME = GETDATE();

DECLARE @IdModuloProduccion INT = 58;

DECLARE @acciones TABLE (IdModulo INT NULL, Nombre VARCHAR(100));

INSERT INTO @acciones (IdModulo, Nombre)
VALUES ((SELECT TOP 1 Id FROM Modulos WHERE Ruta = N'transacciones/labores' AND FechaFinalizacion IS NULL ORDER BY Id), 'habilitar-labores'),
       ((SELECT Id FROM Modulos WHERE Id = @IdModuloProduccion AND FechaFinalizacion IS NULL), 'habilitar-produccion'),
       ((SELECT TOP 1 Id FROM Modulos WHERE Ruta = N'transacciones/fertilizacion' AND FechaFinalizacion IS NULL ORDER BY Id), 'habilitar-fertilizacion');

BEGIN TRY

    BEGIN TRAN;

    IF EXISTS (SELECT 1 FROM @acciones WHERE IdModulo IS NULL)
        THROW 50001, N'Falta alguno de los módulos: transacciones/labores, transacciones/fertilizacion (por Ruta) o Producción (Id 58, el MODULO fijo de ProduccionController).', 1;

    INSERT INTO ModulosAcciones (IdModulo, Nombre, Icono, IdUsuario, FechaInicio)
    SELECT a.IdModulo, a.Nombre, NULL, @IdUsuario, @Fecha
      FROM @acciones a
     WHERE NOT EXISTS (SELECT 1 FROM ModulosAcciones ma
                        WHERE ma.IdModulo = a.IdModulo AND ma.Nombre = a.Nombre AND ma.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Acciones creadas: ', @@ROWCOUNT);

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
  AND ma.Nombre IN ('habilitar-labores', 'habilitar-produccion', 'habilitar-fertilizacion')
ORDER BY m.Id;
GO

/* ----------------------------------------------------------------------------
   EJEMPLO (opcional): asignar las tres acciones a un rol. Lo normal es hacerlo
   desde Administración. Ajustar @IdRol, descomentar y ejecutar.
   ----------------------------------------------------------------------------
DECLARE @IdRol     INT = 1;
DECLARE @IdUsuario INT = 1;

INSERT INTO ModulosPermisosAcciones (IdRol, IdAccion, IdUsuario, FechaInicio)
SELECT @IdRol, ma.Id, @IdUsuario, GETDATE()
  FROM ModulosAcciones ma
  JOIN Modulos m ON m.Id = ma.IdModulo AND m.FechaFinalizacion IS NULL
 WHERE ma.Nombre IN ('habilitar-labores', 'habilitar-produccion', 'habilitar-fertilizacion')
   AND ma.FechaFinalizacion IS NULL
   AND NOT EXISTS (SELECT 1 FROM ModulosPermisosAcciones mpa
                    WHERE mpa.IdRol = @IdRol AND mpa.IdAccion = ma.Id AND mpa.FechaFinalizacion IS NULL);
---------------------------------------------------------------------------- */

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (DELETE físicos y DROP). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
DELETE FROM ModulosPermisosAcciones
WHERE IdAccion IN (SELECT Id FROM ModulosAcciones
                    WHERE Nombre IN ('habilitar-labores', 'habilitar-produccion', 'habilitar-fertilizacion'));

DELETE FROM ModulosAcciones
WHERE Nombre IN ('habilitar-labores', 'habilitar-produccion', 'habilitar-fertilizacion');

IF OBJECT_ID('dbo.aTransaccionHabilitacion', 'U') IS NOT NULL
    DROP TABLE dbo.aTransaccionHabilitacion;
---------------------------------------------------------------------------- */
