/* ============================================================================
   Planeación de labores: horario en dos campos de hora (inicio y fin)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Sobre dbo.pPlaneacionLinea (creada por crear_planeacion_labores.sql):
       1) Agrega horaInicio TIME(0) NULL y horaFin TIME(0) NULL si no existen.
       2) Migra el campo de texto "horario" cuando coincide con las opciones
          sugeridas por la pantalla:
             '6 - 2 PM'  -> 06:00 a 14:00
             '6 - 12 M'  -> 06:00 a 12:00
             '7 - 3 PM'  -> 07:00 a 15:00
          Cualquier otro texto queda con ambas horas en NULL.
       3) Agrega CK_pPlaneacionLinea_horas: ambas horas nulas o ambas
          diligenciadas con horaFin > horaInicio.
       4) Elimina las dependencias (índices, estadísticas de usuario,
          restricciones) sobre "horario" y luego la columna "horario".

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i alterar_planeacion_horario.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY

    IF OBJECT_ID('dbo.pPlaneacionLinea', 'U') IS NULL
        THROW 50000, N'No existe dbo.pPlaneacionLinea: ejecute primero crear_planeacion_labores.sql.', 1;

    BEGIN TRAN;

    DECLARE @sql NVARCHAR(MAX);

    IF COL_LENGTH('dbo.pPlaneacionLinea', 'horaInicio') IS NULL
    BEGIN
        ALTER TABLE dbo.pPlaneacionLinea ADD horaInicio TIME(0) NULL;
        PRINT N'Columna horaInicio agregada.';
    END;

    IF COL_LENGTH('dbo.pPlaneacionLinea', 'horaFin') IS NULL
    BEGIN
        ALTER TABLE dbo.pPlaneacionLinea ADD horaFin TIME(0) NULL;
        PRINT N'Columna horaFin agregada.';
    END;

    IF COL_LENGTH('dbo.pPlaneacionLinea', 'horario') IS NOT NULL
    BEGIN
        EXEC sp_executesql N'
            UPDATE dbo.pPlaneacionLinea
               SET horaInicio = m.inicio, horaFin = m.fin
              FROM dbo.pPlaneacionLinea l
              JOIN (VALUES (''6 - 2 PM'', CAST(''06:00'' AS TIME(0)), CAST(''14:00'' AS TIME(0))),
                           (''6 - 12 M'', CAST(''06:00'' AS TIME(0)), CAST(''12:00'' AS TIME(0))),
                           (''7 - 3 PM'', CAST(''07:00'' AS TIME(0)), CAST(''15:00'' AS TIME(0)))) m (texto, inicio, fin)
                ON m.texto = LTRIM(RTRIM(l.horario))
             WHERE l.horaInicio IS NULL AND l.horaFin IS NULL;
            PRINT CONCAT(N''Filas migradas desde horario: '', @@ROWCOUNT);';
    END;

    IF OBJECT_ID('dbo.CK_pPlaneacionLinea_horas', 'C') IS NULL
    BEGIN
        EXEC sp_executesql N'
            ALTER TABLE dbo.pPlaneacionLinea WITH CHECK ADD CONSTRAINT CK_pPlaneacionLinea_horas
                CHECK ((horaInicio IS NULL AND horaFin IS NULL)
                    OR (horaInicio IS NOT NULL AND horaFin IS NOT NULL AND horaFin > horaInicio));';
        PRINT N'Restricción CK_pPlaneacionLinea_horas agregada.';
    END;

    IF COL_LENGTH('dbo.pPlaneacionLinea', 'horario') IS NOT NULL
    BEGIN
        SET @sql = N'';

        SELECT @sql += N'ALTER TABLE dbo.pPlaneacionLinea DROP CONSTRAINT ' + QUOTENAME(d.name) + N';'
          FROM sys.default_constraints d
         WHERE d.parent_object_id = OBJECT_ID('dbo.pPlaneacionLinea')
           AND d.parent_column_id = COLUMNPROPERTY(OBJECT_ID('dbo.pPlaneacionLinea'), 'horario', 'ColumnId');

        SELECT @sql += N'ALTER TABLE dbo.pPlaneacionLinea DROP CONSTRAINT ' + QUOTENAME(k.name) + N';'
          FROM sys.check_constraints k
         WHERE k.parent_object_id = OBJECT_ID('dbo.pPlaneacionLinea')
           AND (k.parent_column_id = COLUMNPROPERTY(OBJECT_ID('dbo.pPlaneacionLinea'), 'horario', 'ColumnId')
                OR k.definition LIKE N'%\[horario\]%' ESCAPE N'\');

        SELECT @sql += CASE WHEN i.is_primary_key = 1 OR i.is_unique_constraint = 1
                            THEN N'ALTER TABLE dbo.pPlaneacionLinea DROP CONSTRAINT ' + QUOTENAME(i.name) + N';'
                            ELSE N'DROP INDEX ' + QUOTENAME(i.name) + N' ON dbo.pPlaneacionLinea;' END
          FROM sys.indexes i
         WHERE i.object_id = OBJECT_ID('dbo.pPlaneacionLinea')
           AND EXISTS (SELECT 1 FROM sys.index_columns ic
                        WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                          AND ic.column_id = COLUMNPROPERTY(OBJECT_ID('dbo.pPlaneacionLinea'), 'horario', 'ColumnId'));

        SELECT @sql += N'DROP STATISTICS dbo.pPlaneacionLinea.' + QUOTENAME(s.name) + N';'
          FROM sys.stats s
         WHERE s.object_id = OBJECT_ID('dbo.pPlaneacionLinea')
           AND s.user_created = 1
           AND EXISTS (SELECT 1 FROM sys.stats_columns sc
                        WHERE sc.object_id = s.object_id AND sc.stats_id = s.stats_id
                          AND sc.column_id = COLUMNPROPERTY(OBJECT_ID('dbo.pPlaneacionLinea'), 'horario', 'ColumnId'));

        IF @sql <> N''
        BEGIN
            EXEC sp_executesql @sql;
            PRINT N'Dependencias de horario eliminadas.';
        END;

        ALTER TABLE dbo.pPlaneacionLinea DROP COLUMN horario;
        PRINT N'Columna horario eliminada.';
    END;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT c.name AS columna, t.name AS tipo, c.scale AS escala, c.is_nullable AS nulo
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.pPlaneacionLinea') AND c.name IN ('horario', 'horaInicio', 'horaFin')
ORDER BY c.column_id;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (las horas se convierten a texto 'HH:MM - HH:MM').
   Descomentar y ejecutar.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;
BEGIN TRAN;

IF COL_LENGTH('dbo.pPlaneacionLinea', 'horario') IS NULL
    ALTER TABLE dbo.pPlaneacionLinea ADD horario VARCHAR(30) NULL;

EXEC sp_executesql N'
    UPDATE dbo.pPlaneacionLinea
       SET horario = CONVERT(varchar(5), horaInicio, 108) + '' - '' + CONVERT(varchar(5), horaFin, 108)
     WHERE horaInicio IS NOT NULL AND horaFin IS NOT NULL;';

IF OBJECT_ID('dbo.CK_pPlaneacionLinea_horas', 'C') IS NOT NULL
    ALTER TABLE dbo.pPlaneacionLinea DROP CONSTRAINT CK_pPlaneacionLinea_horas;

IF COL_LENGTH('dbo.pPlaneacionLinea', 'horaFin') IS NOT NULL
    ALTER TABLE dbo.pPlaneacionLinea DROP COLUMN horaFin;

IF COL_LENGTH('dbo.pPlaneacionLinea', 'horaInicio') IS NOT NULL
    ALTER TABLE dbo.pPlaneacionLinea DROP COLUMN horaInicio;

COMMIT TRAN;
---------------------------------------------------------------------------- */
