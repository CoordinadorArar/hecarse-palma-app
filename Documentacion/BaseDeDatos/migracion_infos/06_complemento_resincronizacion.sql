/* ============================================================================
   Migración Infos -> AppPalma: complemento de la resincronización
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1. Limpia en AppPalma.dbo.aTransaccion las columnas fechaModificacion y
        usuarioModificacion (solo existen en AppPalma; Infos no las tiene)
        dejándolas en NULL donde alguna tenga valor. Si las columnas no
        existen, el paso se omite sin error.
     2. Borra de AppPalma.dbo.Auditoria los registros de
        TablaAfectada = 'aTransaccion' con Accion IN ('UPDATE','DELETE').
        Se conservan los INSERT y todo lo de las demás tablas.
     3. Muestra las filas afectadas por paso y la verificación: conteo de
        Auditoria de aTransaccion por Accion (no debe haber UPDATE ni DELETE)
        y cuántas aTransaccion siguen con fechaModificacion/usuarioModificacion
        (debe dar 0).

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta todo dentro de una transacción,
     muestra lo que haría y hace ROLLBACK. No queda ningún cambio.
     @Aplicar = 1: lo mismo pero hace COMMIT.
     Cualquier error revierte TODO.

   REQUISITOS
   ----------
     - Ejecutar DESPUÉS de 02_sincronizar_infos_a_apppalma.sql y de
       04_limpieza_y_consecutivos.sql.
     - Nadie registrando en AppPalma durante el proceso.
     - Ejecutar conectado a AppPalma.

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8. Ejecutarlo SOLO desde SSMS o con
   "sqlcmd -f 65001 -i 06_complemento_resincronizacion.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT = 0;
DECLARE @log TABLE (orden INT IDENTITY(1,1), tabla SYSNAME, accion NVARCHAR(60), filas INT);
DECLARE @filas INT, @sql NVARCHAR(MAX);
DECLARE @tieneColumnas BIT = CASE WHEN COL_LENGTH(N'AppPalma.dbo.aTransaccion', N'fechaModificacion') IS NOT NULL
                                   AND COL_LENGTH(N'AppPalma.dbo.aTransaccion', N'usuarioModificacion') IS NOT NULL THEN 1 ELSE 0 END;

BEGIN TRY

    BEGIN TRAN;

    /* ---------- 1: limpiar fechaModificacion / usuarioModificacion ---------- */

    IF @tieneColumnas = 1
    BEGIN
        SET @sql = N'UPDATE AppPalma.dbo.aTransaccion SET fechaModificacion = NULL, usuarioModificacion = NULL
                     WHERE fechaModificacion IS NOT NULL OR usuarioModificacion IS NOT NULL;
                     SET @filas = @@ROWCOUNT;';
        EXEC sp_executesql @sql, N'@filas INT OUTPUT', @filas = @filas OUTPUT;
        INSERT @log VALUES (N'aTransaccion', N'fechaModificacion/usuarioModificacion -> NULL', @filas);
    END
    ELSE
        INSERT @log VALUES (N'aTransaccion', N'columnas de modificación no existen (omitido)', 0);

    /* ---------- 2: auditoría de aTransaccion (UPDATE/DELETE) ---------- */

    DELETE FROM AppPalma.dbo.Auditoria WHERE TablaAfectada = 'aTransaccion' AND Accion IN ('UPDATE','DELETE');
    INSERT @log VALUES (N'Auditoria', N'eliminadas (aTransaccion UPDATE/DELETE)', @@ROWCOUNT);

    SELECT tabla, accion, filas FROM @log ORDER BY orden;

    /* ---------- 3: verificación ---------- */

    SELECT Accion, COUNT(*) AS registros
    FROM AppPalma.dbo.Auditoria
    WHERE TablaAfectada = 'aTransaccion'
    GROUP BY Accion
    ORDER BY Accion;

    IF @tieneColumnas = 1
    BEGIN
        SET @sql = N'SELECT COUNT(*) AS aTransaccion_con_modificacion FROM AppPalma.dbo.aTransaccion
                     WHERE fechaModificacion IS NOT NULL OR usuarioModificacion IS NOT NULL;';
        EXEC sp_executesql @sql;
    END

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
