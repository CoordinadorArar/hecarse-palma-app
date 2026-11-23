/* ============================================================================
   Dominicales automáticos (Gestión Palma > Nómina > Dominicales)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Agrega, si no existe, dbo.pMotivoAusencia.remunerado
        BIT NOT NULL DEFAULT (0). Solo cuando la columna se crea en esta
        ejecución marca como remunerados (1) los motivos INC, CAL, VAC y LIC
        de todas las empresas (las ejecuciones posteriores no pisan lo que el
        usuario haya cambiado desde la pantalla).
     2) Cambia la descripción del motivo LIC a "Licencia remunerada" solo si
        aún conserva la descripción original "Licencia".
     3) Inserta, por cada empresa de gEmpresa, el motivo LNR
        "Licencia no remunerada" (remunerado = 0, activo = 1) si no existe.
     4) Registra el submódulo "Dominicales" bajo el mismo contenedor
        (IdModulo) y loseta del submódulo "Periodos de nómina"
        (gestion-palma/nomina/periodos), Ruta gestion-palma/nomina/dominicales,
        Orden al final de sus hermanos, y copia en ModulosRoles los roles
        activos de "Periodos de nómina".

   Requiere haber ejecutado antes planeacion_ajustes_noviembre.sql
   (tabla dbo.pMotivoAusencia).

   IDEMPOTENTE: se puede ejecutar varias veces.
   Archivo UTF-8: ejecutar desde SSMS o con
   "sqlcmd -f 65001 -i dominicales_automaticos.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario    INT = 1;
DECLARE @Fecha        DATETIME = GETDATE();
DECLARE @Ruta         VARCHAR(200) = 'gestion-palma/nomina/dominicales';
DECLARE @IdLoseta     INT;
DECLARE @IdContenedor INT;
DECLARE @IdHermano    INT;
DECLARE @IdNuevo      INT;
DECLARE @Creada       BIT = 0;

BEGIN TRY

    BEGIN TRAN;

    IF OBJECT_ID('dbo.pMotivoAusencia', 'U') IS NULL
        THROW 50001, N'Falta la tabla dbo.pMotivoAusencia: ejecute antes planeacion_ajustes_noviembre.sql.', 1;

    IF COL_LENGTH('dbo.pMotivoAusencia', 'remunerado') IS NULL
    BEGIN
        ALTER TABLE dbo.pMotivoAusencia
            ADD remunerado BIT NOT NULL CONSTRAINT DF_pMotivoAusencia_remunerado DEFAULT (0);

        SET @Creada = 1;

        PRINT N'Columna dbo.pMotivoAusencia.remunerado creada.';
    END;

    IF @Creada = 1
    BEGIN
        EXEC sp_executesql
            N'UPDATE dbo.pMotivoAusencia SET remunerado = 1 WHERE codigo IN (''INC'', ''CAL'', ''VAC'', ''LIC'')';

        PRINT CONCAT(N'Motivos marcados como remunerados: ', @@ROWCOUNT);
    END;

    UPDATE dbo.pMotivoAusencia
       SET descripcion = N'Licencia remunerada'
     WHERE codigo = 'LIC'
       AND LTRIM(RTRIM(descripcion)) = N'Licencia';

    PRINT CONCAT(N'Descripciones de LIC actualizadas: ', @@ROWCOUNT);

    EXEC sp_executesql
        N'INSERT INTO dbo.pMotivoAusencia (empresa, codigo, descripcion, activo, remunerado)
          SELECT e.id, ''LNR'', N''Licencia no remunerada'', 1, 0
            FROM dbo.gEmpresa e
           WHERE NOT EXISTS (SELECT 1 FROM dbo.pMotivoAusencia m WHERE m.empresa = e.id AND m.codigo = ''LNR'')';

    PRINT CONCAT(N'Motivos LNR insertados: ', @@ROWCOUNT);

    SELECT TOP 1 @IdHermano = Id, @IdContenedor = IdModulo, @IdLoseta = IdLoseta
      FROM Modulos
     WHERE Ruta = 'gestion-palma/nomina/periodos'
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdHermano IS NULL
        THROW 50002, N'No existe el submódulo "Periodos de nómina" (gestion-palma/nomina/periodos).', 1;

    IF @IdContenedor IS NULL OR @IdLoseta IS NULL
        THROW 50003, N'El submódulo "Periodos de nómina" no tiene contenedor (IdModulo) o loseta (IdLoseta).', 1;

    SELECT TOP 1 @IdNuevo = Id
      FROM Modulos
     WHERE Ruta = @Ruta
       AND IdLoseta = @IdLoseta
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdNuevo IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (N'Dominicales', @Ruta, 'bi bi-calendar-check', @IdLoseta, @IdContenedor, @IdUsuario, @Fecha);

        SET @IdNuevo = SCOPE_IDENTITY();

        IF COL_LENGTH('Modulos', 'Orden') IS NOT NULL
            EXEC sp_executesql
                N'UPDATE Modulos
                     SET Orden = (SELECT ISNULL(MAX(Orden), 0) + 1 FROM Modulos
                                   WHERE IdModulo = @IdContenedor AND Id <> @IdNuevo AND FechaFinalizacion IS NULL)
                   WHERE Id = @IdNuevo',
                N'@IdContenedor INT, @IdNuevo INT',
                @IdContenedor = @IdContenedor, @IdNuevo = @IdNuevo;

        PRINT CONCAT(N'Submódulo creado con Id ', @IdNuevo);
    END
    ELSE
        PRINT CONCAT(N'El submódulo ya existía con Id ', @IdNuevo);

    INSERT INTO ModulosRoles (IdModulo, IdRol, FechaInicio)
    SELECT DISTINCT @IdNuevo, pr.IdRol, @Fecha
      FROM ModulosRoles pr
     WHERE pr.IdModulo = @IdHermano
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

IF COL_LENGTH('dbo.pMotivoAusencia', 'remunerado') IS NOT NULL
    EXEC sp_executesql
        N'SELECT empresa, codigo, descripcion, activo, remunerado
            FROM dbo.pMotivoAusencia
           ORDER BY empresa, codigo';

SELECT m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo AS IdModuloPadre,
       COUNT(mr.IdRol) AS Roles
  FROM Modulos m
  LEFT JOIN ModulosRoles mr ON mr.IdModulo = m.Id AND mr.FechaFinalizacion IS NULL
 WHERE m.Ruta IN ('gestion-palma/nomina/dominicales', 'gestion-palma/nomina/periodos')
   AND m.FechaFinalizacion IS NULL
 GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo
 ORDER BY m.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   No borra las transacciones TLA de dominicales ya generadas.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosRoles
     WHERE IdModulo IN (SELECT Id FROM Modulos WHERE Ruta = 'gestion-palma/nomina/dominicales');

    DELETE FROM Modulos WHERE Ruta = 'gestion-palma/nomina/dominicales';

    DELETE FROM dbo.pMotivoAusencia
     WHERE codigo = 'LNR'
       AND NOT EXISTS (SELECT 1 FROM dbo.pPlaneacionAusencia a
                         JOIN dbo.pPlaneacion p ON p.id = a.planeacion
                        WHERE p.empresa = pMotivoAusencia.empresa AND a.codigoMotivo = pMotivoAusencia.codigo);

    UPDATE dbo.pMotivoAusencia SET descripcion = N'Licencia'
     WHERE codigo = 'LIC' AND descripcion = N'Licencia remunerada';

    IF OBJECT_ID('dbo.DF_pMotivoAusencia_remunerado', 'D') IS NOT NULL
        ALTER TABLE dbo.pMotivoAusencia DROP CONSTRAINT DF_pMotivoAusencia_remunerado;

    IF COL_LENGTH('dbo.pMotivoAusencia', 'remunerado') IS NOT NULL
        ALTER TABLE dbo.pMotivoAusencia DROP COLUMN remunerado;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
