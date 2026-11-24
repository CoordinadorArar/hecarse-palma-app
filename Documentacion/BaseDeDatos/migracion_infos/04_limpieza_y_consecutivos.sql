/* ============================================================================
   OBSOLETO desde 2026-11-04: Infos ya no se usa
   Migración Infos -> AppPalma: limpieza de documentos de prueba y consecutivos
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1. Borra de dbo.Documentos los documentos de prueba
        (Codigo = 'AP-TP-1' AND Nombre = 'Documento de prueba'; hoy Id 1 y 2)
        y antes sus filas hijas en DocumentosCargos y DocumentosUsuarios
        (se relacionan por Codigo; no hay llaves foráneas). Si alguna de las
        tres tablas queda vacía y ya había usado su IDENTITY, lo reinicia con
        DBCC CHECKIDENT (RESEED, 0) para que el próximo Id sea 1.
     2. Alinea el IDENTITY de toda tabla que exista en AppPalma e Infos con
        columna identity (hoy: gFoto, h_novedades, h_TipoNoveda,
        logaTransaccionTercero, sLogCorreos, sReplicacion, sysdiagrams,
        [Zeus®ImportarArchivo]): si last_value difiere y el de Infos no es
        NULL, hace DBCC CHECKIDENT (RESEED) al valor de Infos. Idempotente:
        si ya coinciden no hace nada.
     3. Muestra la verificación: identity AppPalma vs Infos por tabla común y
        las filas de gTipoTransaccion cuyo consecutivo (actual) difiere de
        Infos (debe dar 0 filas si ya se ejecutó el 02).

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta todo dentro de una transacción,
     muestra lo que haría y hace ROLLBACK. No queda ningún cambio; los
     DBCC CHECKIDENT ejecutados dentro de la transacción también se revierten.
     @Aplicar = 1: lo mismo pero hace COMMIT.
     Cualquier error revierte TODO.

   REQUISITOS
   ----------
     - Ejecutar DESPUÉS de 02_sincronizar_infos_a_apppalma.sql.
     - Ejecutar conectado a AppPalma con un usuario que lea Infos y tenga
       permiso para DBCC CHECKIDENT (dueño de la tabla, db_owner o
       db_ddladmin).

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8 y contiene el nombre [Zeus®ImportarArchivo].
   Ejecutarlo SOLO desde SSMS o con
   "sqlcmd -f 65001 -i 04_limpieza_y_consecutivos.sql".
   ============================================================================ */

RAISERROR (N'OBSOLETO: Infos dejó de usarse el 2026-11-04. Este script sincroniza Infos -> AppPalma y borraría datos actuales de AppPalma. No ejecutar.', 16, 1);
SET NOEXEC ON;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT = 0;
DECLARE @log TABLE (orden INT IDENTITY(1,1), tabla SYSNAME, accion NVARCHAR(60), filas BIGINT);
DECLARE @tabla NVARCHAR(300), @nombre SYSNAME, @valor BIGINT, @actual BIGINT, @inc BIGINT, @reseed BIGINT, @sql NVARCHAR(MAX);

BEGIN TRY

    BEGIN TRAN;

    /* ---------- 1: documentos de prueba ---------- */

    DECLARE @docs TABLE (Codigo VARCHAR(100));
    INSERT @docs SELECT DISTINCT Codigo FROM AppPalma.dbo.Documentos WHERE Codigo = 'AP-TP-1' AND Nombre = 'Documento de prueba';

    DELETE AppPalma.dbo.Documentos WHERE Codigo = 'AP-TP-1' AND Nombre = 'Documento de prueba';
    INSERT @log VALUES (N'Documentos', N'eliminadas', @@ROWCOUNT);

    DELETE c FROM AppPalma.dbo.DocumentosCargos c
    WHERE c.Codigo IN (SELECT Codigo FROM @docs) AND NOT EXISTS (SELECT 1 FROM AppPalma.dbo.Documentos d WHERE d.Codigo = c.Codigo);
    INSERT @log VALUES (N'DocumentosCargos', N'eliminadas', @@ROWCOUNT);

    DELETE u FROM AppPalma.dbo.DocumentosUsuarios u
    WHERE u.Codigo IN (SELECT Codigo FROM @docs) AND NOT EXISTS (SELECT 1 FROM AppPalma.dbo.Documentos d WHERE d.Codigo = u.Codigo);
    INSERT @log VALUES (N'DocumentosUsuarios', N'eliminadas', @@ROWCOUNT);

    IF NOT EXISTS (SELECT 1 FROM AppPalma.dbo.Documentos) AND (SELECT last_value FROM AppPalma.sys.identity_columns WHERE object_id = OBJECT_ID(N'AppPalma.dbo.Documentos')) IS NOT NULL
    BEGIN
        DBCC CHECKIDENT (N'AppPalma.dbo.Documentos', RESEED, 0) WITH NO_INFOMSGS;
        INSERT @log VALUES (N'Documentos', N'identity reiniciado (próximo Id = 1)', 0);
    END

    IF NOT EXISTS (SELECT 1 FROM AppPalma.dbo.DocumentosCargos) AND (SELECT last_value FROM AppPalma.sys.identity_columns WHERE object_id = OBJECT_ID(N'AppPalma.dbo.DocumentosCargos')) IS NOT NULL
    BEGIN
        DBCC CHECKIDENT (N'AppPalma.dbo.DocumentosCargos', RESEED, 0) WITH NO_INFOMSGS;
        INSERT @log VALUES (N'DocumentosCargos', N'identity reiniciado (próximo Id = 1)', 0);
    END

    IF NOT EXISTS (SELECT 1 FROM AppPalma.dbo.DocumentosUsuarios) AND (SELECT last_value FROM AppPalma.sys.identity_columns WHERE object_id = OBJECT_ID(N'AppPalma.dbo.DocumentosUsuarios')) IS NOT NULL
    BEGIN
        DBCC CHECKIDENT (N'AppPalma.dbo.DocumentosUsuarios', RESEED, 0) WITH NO_INFOMSGS;
        INSERT @log VALUES (N'DocumentosUsuarios', N'identity reiniciado (próximo Id = 1)', 0);
    END

    /* ---------- 2: alinear IDENTITY con Infos ---------- */

    DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
        SELECT QUOTENAME(sa.name) + N'.' + QUOTENAME(ta.name), ta.name, CAST(ii.last_value AS BIGINT), CAST(ia.last_value AS BIGINT), CAST(ia.increment_value AS BIGINT)
        FROM AppPalma.sys.identity_columns ia
        JOIN AppPalma.sys.tables ta ON ta.object_id = ia.object_id
        JOIN AppPalma.sys.schemas sa ON sa.schema_id = ta.schema_id
        JOIN Infos.sys.schemas si ON si.name = sa.name
        JOIN Infos.sys.tables ti ON ti.name = ta.name AND ti.schema_id = si.schema_id
        JOIN Infos.sys.identity_columns ii ON ii.object_id = ti.object_id
        WHERE ii.last_value IS NOT NULL
          AND (ia.last_value IS NULL OR CAST(ia.last_value AS BIGINT) <> CAST(ii.last_value AS BIGINT));

    OPEN cur;
    FETCH NEXT FROM cur INTO @tabla, @nombre, @valor, @actual, @inc;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        SET @reseed = @valor + CASE WHEN @actual IS NULL THEN @inc ELSE 0 END;
        SET @sql = N'DBCC CHECKIDENT (N''' + REPLACE(@tabla, N'''', N'''''') + N''', RESEED, ' + CAST(@reseed AS NVARCHAR(30)) + N') WITH NO_INFOMSGS;';
        EXEC AppPalma.sys.sp_executesql @sql;
        INSERT @log VALUES (@nombre, N'identity ' + ISNULL(CAST(@actual AS NVARCHAR(30)), N'NULL') + N' -> ' + CAST(@valor AS NVARCHAR(30)), @valor);
        FETCH NEXT FROM cur INTO @tabla, @nombre, @valor, @actual, @inc;
    END
    CLOSE cur;
    DEALLOCATE cur;

    SELECT tabla, accion, filas FROM @log WHERE filas > 0 OR accion LIKE N'identity%' ORDER BY orden;

    /* ---------- 3: verificación ---------- */

    SELECT ta.name AS tabla,
           CAST(ia.last_value AS BIGINT) AS identity_AppPalma,
           CAST(ii.last_value AS BIGINT) AS identity_Infos,
           CASE WHEN ISNULL(CAST(ia.last_value AS BIGINT), -1) = ISNULL(CAST(ii.last_value AS BIGINT), -1) THEN N'OK' ELSE N'DIFIERE' END AS estado
    FROM AppPalma.sys.identity_columns ia
    JOIN AppPalma.sys.tables ta ON ta.object_id = ia.object_id
    JOIN AppPalma.sys.schemas sa ON sa.schema_id = ta.schema_id
    JOIN Infos.sys.schemas si ON si.name = sa.name
    JOIN Infos.sys.tables ti ON ti.name = ta.name AND ti.schema_id = si.schema_id
    JOIN Infos.sys.identity_columns ii ON ii.object_id = ti.object_id
    ORDER BY ta.name;

    SELECT d.empresa, d.codigo, d.actual AS actual_AppPalma, s.actual AS actual_Infos
    FROM AppPalma.dbo.gTipoTransaccion d
    JOIN Infos.dbo.gTipoTransaccion s ON s.empresa = d.empresa AND s.codigo = d.codigo
    WHERE EXISTS (SELECT d.actual EXCEPT SELECT s.actual);

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: cambios confirmados.';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: no se guardó ningún cambio. Revise los resultados y ejecute con @Aplicar = 1.';
    END

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK;
    SELECT ERROR_NUMBER() AS numero, ERROR_MESSAGE() AS mensaje, ERROR_LINE() AS linea;
    PRINT N'ERROR: se revirtió todo, no quedó ningún cambio.';
END CATCH;
