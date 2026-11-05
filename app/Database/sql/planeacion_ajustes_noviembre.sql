/* ============================================================================
   Ajustes de noviembre a la planeación de labores
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Pasa a DECIMAL(18, 3), conservando la nulabilidad, las columnas:
          dbo.pPlaneacionLinea.cantidad
          dbo.pPlaneacionLinea.cantidadEjecutada
          dbo.pPlaneacionTrabajador.cantidad
        Las restricciones CHECK e índices no agrupados que dependan de esas
        columnas se eliminan y se vuelven a crear con la misma definición
        (los DEFAULT no impiden el cambio de escala y se conservan).
     2) Crea, si no existe, la tabla dbo.pMotivoAusencia (empresa, codigo,
        descripcion, activo) con PK (empresa, codigo) y siembra, por cada
        empresa de gEmpresa, los motivos INC, PER, CAL, VAC, LIC, INJ, SUS y
        OTR que no existan.
     3) Agrega, si no existe, dbo.pPlaneacionAusencia.codigoMotivo VARCHAR(10) NULL.
     4) Registra el submódulo "Motivos de ausencia" bajo el mismo contenedor
        (IdModulo) del submódulo "Unidad de medida" en la loseta Maestros Palma
        (Ruta de loseta gestion-palma/onboarding; hoy "Configuración"),
        Ruta gestion-palma/parametros/motivo-ausencia,
        Orden al final de sus hermanos, y copia en ModulosRoles los roles
        activos del submódulo "Unidad de medida"
        (gestion-palma/parametros/unidad-medida).
        (Gestión Palma no usa acciones finas en ModulosAcciones: solo acceso.)

   Requiere haber ejecutado antes crear_planeacion_labores.sql.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i planeacion_ajustes_noviembre.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario    INT = 1;
DECLARE @Fecha        DATETIME = GETDATE();
DECLARE @Ruta         VARCHAR(200) = 'gestion-palma/parametros/motivo-ausencia';
DECLARE @IdLoseta     INT;
DECLARE @IdContenedor INT;
DECLARE @IdHermano    INT;
DECLARE @IdNuevo      INT;
DECLARE @sql          NVARCHAR(MAX) = N'';

DECLARE @columnas TABLE (tabla SYSNAME NOT NULL, columna SYSNAME NOT NULL);
DECLARE @pendientes TABLE (tabla SYSNAME NOT NULL, columna SYSNAME NOT NULL, nulo BIT NOT NULL);
DECLARE @checks TABLE (tabla SYSNAME NOT NULL, nombre SYSNAME NOT NULL, definicion NVARCHAR(MAX) NOT NULL);
DECLARE @indices TABLE (tabla SYSNAME NOT NULL, nombre SYSNAME NOT NULL, crear NVARCHAR(MAX) NOT NULL);

INSERT INTO @columnas (tabla, columna)
VALUES ('pPlaneacionLinea', 'cantidad'), ('pPlaneacionLinea', 'cantidadEjecutada'), ('pPlaneacionTrabajador', 'cantidad');

BEGIN TRY

    BEGIN TRAN;

    IF OBJECT_ID('dbo.pPlaneacionAusencia', 'U') IS NULL
       OR EXISTS (SELECT 1 FROM @columnas c WHERE COL_LENGTH('dbo.' + c.tabla, c.columna) IS NULL)
        THROW 50001, N'Faltan las tablas de planeación: ejecute antes crear_planeacion_labores.sql.', 1;

    INSERT INTO @pendientes (tabla, columna, nulo)
    SELECT c.tabla, c.columna, sc.is_nullable
      FROM @columnas c
      JOIN sys.columns sc ON sc.object_id = OBJECT_ID('dbo.' + c.tabla) AND sc.name = c.columna
     WHERE NOT (TYPE_NAME(sc.system_type_id) = 'decimal' AND sc.precision = 18 AND sc.scale = 3);

    IF EXISTS (
        SELECT 1
          FROM sys.indexes i
          JOIN sys.index_columns k ON k.object_id = i.object_id AND k.index_id = i.index_id
          JOIN sys.columns c ON c.object_id = k.object_id AND c.column_id = k.column_id
          JOIN @pendientes p ON i.object_id = OBJECT_ID('dbo.' + p.tabla) AND c.name = p.columna
         WHERE i.is_primary_key = 1 OR i.is_unique_constraint = 1 OR i.type <> 2
        UNION ALL
        SELECT 1
          FROM sys.stats s
          JOIN sys.stats_columns k ON k.object_id = s.object_id AND k.stats_id = s.stats_id
          JOIN sys.columns c ON c.object_id = k.object_id AND c.column_id = k.column_id
          JOIN @pendientes p ON s.object_id = OBJECT_ID('dbo.' + p.tabla) AND c.name = p.columna
         WHERE s.user_created = 1
    )
        THROW 50002, N'Una llave primaria, restricción UNIQUE, índice agrupado o estadística manual depende de las cantidades de planeación; revísela antes de continuar.', 1;

    INSERT INTO @checks (tabla, nombre, definicion)
    SELECT DISTINCT OBJECT_NAME(cc.parent_object_id), cc.name, cc.definition
      FROM sys.check_constraints cc
      JOIN @pendientes p ON cc.parent_object_id = OBJECT_ID('dbo.' + p.tabla)
     WHERE COL_NAME(cc.parent_object_id, cc.parent_column_id) = p.columna
        OR cc.definition LIKE N'%[[]' + p.columna + N']%';

    INSERT INTO @indices (tabla, nombre, crear)
    SELECT OBJECT_NAME(i.object_id), i.name,
           N'CREATE ' + CASE WHEN i.is_unique = 1 THEN N'UNIQUE ' ELSE N'' END + N'NONCLUSTERED INDEX ' + QUOTENAME(i.name)
           + N' ON dbo.' + QUOTENAME(OBJECT_NAME(i.object_id)) + N' ('
           + STUFF((SELECT N', ' + QUOTENAME(c.name) + CASE WHEN k.is_descending_key = 1 THEN N' DESC' ELSE N'' END
                      FROM sys.index_columns k
                      JOIN sys.columns c ON c.object_id = k.object_id AND c.column_id = k.column_id
                     WHERE k.object_id = i.object_id AND k.index_id = i.index_id AND k.is_included_column = 0
                     ORDER BY k.key_ordinal
                       FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, N'') + N')'
           + ISNULL(N' INCLUDE (' + STUFF((SELECT N', ' + QUOTENAME(c.name)
                      FROM sys.index_columns k
                      JOIN sys.columns c ON c.object_id = k.object_id AND c.column_id = k.column_id
                     WHERE k.object_id = i.object_id AND k.index_id = i.index_id AND k.is_included_column = 1
                     ORDER BY k.index_column_id
                       FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, N'') + N')', N'')
           + CASE WHEN i.has_filter = 1 THEN N' WHERE ' + i.filter_definition ELSE N'' END
      FROM sys.indexes i
     WHERE i.type = 2
       AND i.is_primary_key = 0
       AND i.is_unique_constraint = 0
       AND EXISTS (SELECT 1
                     FROM sys.index_columns k
                     JOIN sys.columns c ON c.object_id = k.object_id AND c.column_id = k.column_id
                     JOIN @pendientes p ON i.object_id = OBJECT_ID('dbo.' + p.tabla) AND c.name = p.columna
                    WHERE k.object_id = i.object_id AND k.index_id = i.index_id);

    SELECT @sql += N'ALTER TABLE dbo.' + QUOTENAME(tabla) + N' DROP CONSTRAINT ' + QUOTENAME(nombre) + N'; ' FROM @checks;
    SELECT @sql += N'DROP INDEX ' + QUOTENAME(nombre) + N' ON dbo.' + QUOTENAME(tabla) + N'; ' FROM @indices;
    SELECT @sql += N'ALTER TABLE dbo.' + QUOTENAME(tabla) + N' ALTER COLUMN ' + QUOTENAME(columna) + N' DECIMAL(18, 3) '
                 + CASE WHEN nulo = 1 THEN N'NULL' ELSE N'NOT NULL' END + N'; ' FROM @pendientes;
    SELECT @sql += crear + N'; ' FROM @indices;
    SELECT @sql += N'ALTER TABLE dbo.' + QUOTENAME(tabla) + N' WITH CHECK ADD CONSTRAINT ' + QUOTENAME(nombre) + N' CHECK ' + definicion + N'; ' FROM @checks;

    IF @sql <> N''
    BEGIN
        EXEC sp_executesql @sql;

        PRINT N'Cantidades de planeación pasadas a DECIMAL(18, 3).';
    END
    ELSE
        PRINT N'Las cantidades de planeación ya estaban en DECIMAL(18, 3).';

    IF OBJECT_ID('dbo.pMotivoAusencia', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.pMotivoAusencia (
            empresa     INT          NOT NULL,
            codigo      VARCHAR(10)  NOT NULL,
            descripcion VARCHAR(100) NOT NULL,
            activo      BIT          NOT NULL CONSTRAINT DF_pMotivoAusencia_activo DEFAULT (1),
            CONSTRAINT PK_pMotivoAusencia PRIMARY KEY (empresa, codigo)
        );

        PRINT N'Tabla dbo.pMotivoAusencia creada.';
    END;

    INSERT INTO dbo.pMotivoAusencia (empresa, codigo, descripcion, activo)
    SELECT e.id, s.codigo, s.descripcion, 1
      FROM dbo.gEmpresa e
     CROSS JOIN (VALUES ('INC', N'Incapacidad'),
                        ('PER', N'Permiso'),
                        ('CAL', N'Calamidad doméstica'),
                        ('VAC', N'Vacaciones'),
                        ('LIC', N'Licencia'),
                        ('INJ', N'Ausencia injustificada'),
                        ('SUS', N'Suspensión'),
                        ('OTR', N'Otro')) s (codigo, descripcion)
     WHERE NOT EXISTS (SELECT 1 FROM dbo.pMotivoAusencia m WHERE m.empresa = e.id AND m.codigo = s.codigo);

    PRINT CONCAT(N'Motivos de ausencia sembrados: ', @@ROWCOUNT);

    IF COL_LENGTH('dbo.pPlaneacionAusencia', 'codigoMotivo') IS NULL
    BEGIN
        ALTER TABLE dbo.pPlaneacionAusencia ADD codigoMotivo VARCHAR(10) NULL;

        PRINT N'Columna dbo.pPlaneacionAusencia.codigoMotivo creada.';
    END;

    SELECT TOP 1 @IdLoseta = Id
      FROM Modulos
     WHERE Ruta = 'gestion-palma/onboarding'
       AND IdLoseta IS NULL
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdLoseta IS NULL
        THROW 50003, N'No existe la loseta "Maestros Palma" (Ruta gestion-palma/onboarding).', 1;

    SELECT TOP 1 @IdHermano = Id, @IdContenedor = IdModulo
      FROM Modulos
     WHERE Ruta = 'gestion-palma/parametros/unidad-medida'
       AND IdLoseta = @IdLoseta
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdHermano IS NULL
        THROW 50004, N'No existe el submódulo "Unidad de medida" (gestion-palma/parametros/unidad-medida).', 1;

    IF @IdContenedor IS NULL
        THROW 50005, N'El submódulo "Unidad de medida" no tiene contenedor (IdModulo).', 1;

    SELECT TOP 1 @IdNuevo = Id
      FROM Modulos
     WHERE Ruta = @Ruta
       AND IdLoseta = @IdLoseta
       AND FechaFinalizacion IS NULL
     ORDER BY Id;

    IF @IdNuevo IS NULL
    BEGIN
        INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
        VALUES (N'Motivos de ausencia', @Ruta, 'bi bi-person-x', @IdLoseta, @IdContenedor, @IdUsuario, @Fecha);

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

SELECT OBJECT_NAME(c.object_id) AS tabla, c.name AS columna, TYPE_NAME(c.system_type_id) AS tipo,
       c.precision, c.scale, c.max_length, c.is_nullable
  FROM sys.columns c
 WHERE (c.object_id = OBJECT_ID('dbo.pPlaneacionLinea') AND c.name IN ('cantidad', 'cantidadEjecutada'))
    OR (c.object_id = OBJECT_ID('dbo.pPlaneacionTrabajador') AND c.name = 'cantidad')
    OR (c.object_id = OBJECT_ID('dbo.pPlaneacionAusencia') AND c.name = 'codigoMotivo')
 ORDER BY tabla, columna;

SELECT OBJECT_NAME(parent_object_id) AS tabla, name AS restriccion, definition AS definicion
  FROM sys.check_constraints
 WHERE parent_object_id IN (OBJECT_ID('dbo.pPlaneacionLinea'), OBJECT_ID('dbo.pPlaneacionTrabajador'))
 ORDER BY tabla, restriccion;

IF OBJECT_ID('dbo.pMotivoAusencia', 'U') IS NOT NULL
    EXEC sp_executesql
        N'SELECT empresa, COUNT(*) AS motivos, SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS activos
            FROM dbo.pMotivoAusencia
           GROUP BY empresa
           ORDER BY empresa';

SELECT m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo AS IdModuloPadre,
       COUNT(mr.IdRol) AS Roles
  FROM Modulos m
  LEFT JOIN ModulosRoles mr ON mr.IdModulo = m.Id AND mr.FechaFinalizacion IS NULL
 WHERE m.Ruta IN ('gestion-palma/parametros/motivo-ausencia', 'gestion-palma/parametros/unidad-medida')
   AND m.FechaFinalizacion IS NULL
 GROUP BY m.Id, m.Nombre, m.Ruta, m.Icono, m.IdLoseta, m.IdModulo
 ORDER BY m.Id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   Borra los motivos y el submódulo, quita codigoMotivo y devuelve las
   cantidades a DECIMAL(18, 2) (los valores con 3 decimales se redondean).
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM ModulosRoles
     WHERE IdModulo IN (SELECT Id FROM Modulos WHERE Ruta = 'gestion-palma/parametros/motivo-ausencia');

    DELETE FROM Modulos WHERE Ruta = 'gestion-palma/parametros/motivo-ausencia';

    IF COL_LENGTH('dbo.pPlaneacionAusencia', 'codigoMotivo') IS NOT NULL
        ALTER TABLE dbo.pPlaneacionAusencia DROP COLUMN codigoMotivo;

    IF OBJECT_ID('dbo.pMotivoAusencia', 'U') IS NOT NULL
        DROP TABLE dbo.pMotivoAusencia;

    IF OBJECT_ID('dbo.CK_pPlaneacionLinea_cantidad', 'C') IS NOT NULL
        ALTER TABLE dbo.pPlaneacionLinea DROP CONSTRAINT CK_pPlaneacionLinea_cantidad;

    IF OBJECT_ID('dbo.CK_pPlaneacionTrabajador_cantidad', 'C') IS NOT NULL
        ALTER TABLE dbo.pPlaneacionTrabajador DROP CONSTRAINT CK_pPlaneacionTrabajador_cantidad;

    ALTER TABLE dbo.pPlaneacionLinea ALTER COLUMN cantidad DECIMAL(18, 2) NOT NULL;
    ALTER TABLE dbo.pPlaneacionLinea ALTER COLUMN cantidadEjecutada DECIMAL(18, 2) NULL;
    ALTER TABLE dbo.pPlaneacionTrabajador ALTER COLUMN cantidad DECIMAL(18, 2) NOT NULL;

    ALTER TABLE dbo.pPlaneacionLinea WITH CHECK ADD CONSTRAINT CK_pPlaneacionLinea_cantidad
        CHECK (cantidad >= 0 AND (cantidadEjecutada IS NULL OR cantidadEjecutada >= 0));

    ALTER TABLE dbo.pPlaneacionTrabajador WITH CHECK ADD CONSTRAINT CK_pPlaneacionTrabajador_cantidad
        CHECK (cantidad >= 0);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
