/* ============================================================================
   Migración Infos -> AppPalma: diagnóstico de diferencias (SOLO LECTURA)
   ----------------------------------------------------------------------------
   Compara todas las tablas con llave primaria que existen en ambas bases:
     soloInfos  = filas de Infos cuya llave no está en AppPalma (faltan).
     soloApp    = filas de AppPalma cuya llave no está en Infos (se eliminarán).
     distintos  = filas con la misma llave pero contenido diferente.
   Solo lista las tablas con alguna diferencia. Usa SELECT y variables de
   tabla: no modifica ninguna base.

   Uso:
     - Antes de migrar: confirmar que las diferencias están solo en las tablas
       que cubre 02_sincronizar_infos_a_apppalma.sql y revisar "soloApp".
     - Después de migrar: debe devolver CERO filas en la primera consulta.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i 01_diagnostico_diferencias.sql".
   ============================================================================ */
SET NOCOUNT ON;
DECLARE @r TABLE (tabla sysname, pk nvarchar(400), soloInfos int, soloApp int, distintos int, err nvarchar(400));
DECLARE @t sysname, @oid int, @pk nvarchar(max), @on nvarchar(max), @cols nvarchar(max), @sql nvarchar(max), @a int, @b int, @c int;
DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
  SELECT t.name, t.object_id FROM Infos.sys.tables t
  WHERE EXISTS (SELECT 1 FROM AppPalma.sys.tables x WHERE x.name = t.name)
    AND EXISTS (SELECT 1 FROM Infos.sys.indexes i WHERE i.object_id=t.object_id AND i.is_primary_key=1)
  ORDER BY t.name;
OPEN cur; FETCH NEXT FROM cur INTO @t, @oid;
WHILE @@FETCH_STATUS = 0
BEGIN
  SELECT @pk = STUFF((SELECT ',' + QUOTENAME(c.name) FROM Infos.sys.indexes i
      JOIN Infos.sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id
      JOIN Infos.sys.columns c ON c.object_id=i.object_id AND c.column_id=ic.column_id
      WHERE i.object_id=@oid AND i.is_primary_key=1 ORDER BY ic.key_ordinal FOR XML PATH('')),1,1,'');
  SELECT @on = STUFF((SELECT ' AND i.' + QUOTENAME(c.name) + '=a.' + QUOTENAME(c.name) FROM Infos.sys.indexes ix
      JOIN Infos.sys.index_columns ic ON ic.object_id=ix.object_id AND ic.index_id=ix.index_id
      JOIN Infos.sys.columns c ON c.object_id=ix.object_id AND c.column_id=ic.column_id
      WHERE ix.object_id=@oid AND ix.is_primary_key=1 ORDER BY ic.key_ordinal FOR XML PATH('')),1,5,'');
  SELECT @cols = STUFF((SELECT ',' + QUOTENAME(c.name) FROM Infos.sys.columns c
      JOIN Infos.sys.types ty ON ty.user_type_id=c.user_type_id
      WHERE c.object_id=@oid AND ty.name NOT IN ('text','ntext','image','xml','timestamp','geography','geometry','hierarchyid','sql_variant')
        AND EXISTS (SELECT 1 FROM AppPalma.sys.columns ac JOIN AppPalma.sys.tables at ON at.object_id=ac.object_id WHERE at.name=@t AND ac.name=c.name)
      ORDER BY c.column_id FOR XML PATH('')),1,1,'');
  BEGIN TRY
    SET @sql = N'SELECT @a=COUNT(*) FROM (SELECT ' + @pk + ' FROM Infos.dbo.' + QUOTENAME(@t) + ' EXCEPT SELECT ' + @pk + ' FROM AppPalma.dbo.' + QUOTENAME(@t) + ') x;'
             + N'SELECT @b=COUNT(*) FROM (SELECT ' + @pk + ' FROM AppPalma.dbo.' + QUOTENAME(@t) + ' EXCEPT SELECT ' + @pk + ' FROM Infos.dbo.' + QUOTENAME(@t) + ') x;'
             + N'SELECT @c=COUNT(*) FROM (SELECT ' + @cols + ' FROM Infos.dbo.' + QUOTENAME(@t) + ' EXCEPT SELECT ' + @cols + ' FROM AppPalma.dbo.' + QUOTENAME(@t) + ') x;';
    EXEC sp_executesql @sql, N'@a int OUTPUT,@b int OUTPUT,@c int OUTPUT', @a OUTPUT, @b OUTPUT, @c OUTPUT;
    INSERT @r VALUES (@t, @pk, @a, @b, @c - @a, NULL);
  END TRY
  BEGIN CATCH
    INSERT @r VALUES (@t, @pk, NULL, NULL, NULL, LEFT(ERROR_MESSAGE(),400));
  END CATCH
  FETCH NEXT FROM cur INTO @t, @oid;
END
CLOSE cur; DEALLOCATE cur;
SELECT tabla, pk, soloInfos, soloApp, distintos, err FROM @r
WHERE ISNULL(soloInfos,1) <> 0 OR ISNULL(soloApp,1) <> 0 OR ISNULL(distintos,1) <> 0 ORDER BY tabla;
SELECT COUNT(*) AS tablasComparadas FROM @r;

SELECT N'sUsuarios -> sUsuariosNO' AS tabla,
       (SELECT COUNT(*) FROM (SELECT usuario, descripcion, clave, activo, fechaRegistro, email FROM Infos.dbo.sUsuarios EXCEPT SELECT usuario, descripcion, clave, activo, fechaRegistro, email FROM AppPalma.dbo.sUsuariosNO) x) AS distintosInfos,
       (SELECT COUNT(*) FROM (SELECT usuario, descripcion, clave, activo, fechaRegistro, email FROM AppPalma.dbo.sUsuariosNO EXCEPT SELECT usuario, descripcion, clave, activo, fechaRegistro, email FROM Infos.dbo.sUsuarios) x) AS distintosApp;

SELECT a.tipo, a.numero, a.fecha, a.usuarioRegistro, a.fechaRegistro
FROM AppPalma.dbo.aTransaccion a
WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.aTransaccion i WHERE i.empresa = a.empresa AND i.tipo = a.tipo AND i.numero = a.numero)
   OR a.usuarioRegistro NOT IN (SELECT usuario FROM Infos.dbo.sUsuarios)
ORDER BY a.fechaRegistro;
