/* ============================================================================
   Desactivar jobs de sincronización Infos -> AppPalma (SQL Server Agent)
   ----------------------------------------------------------------------------
   *** NO SE HA EJECUTADO. Lo ejecuta William (sysadmin) manualmente. ***

   QUÉ HACE
   --------
     Infos dejó de usarse el 2026-11-04.
     SECCIÓN 1 (solo lectura):
       - Jobs del SQL Agent cuyo nombre, paso, base del paso o comando menciona
         Infos, AppPalma o 'sincroniz': job, habilitado, criterio, paso, base,
         comando (primeros 400 caracteres) y próxima ejecución.
       - Bases publicadas/suscritas en replicación (sys.databases) y, si existe
         la base distribution, sus publicaciones y suscripciones.
       - Linked servers del servidor.
     SECCIÓN 2 (cambios): con @Aplicar = 1 DESHABILITA (sp_update_job
       @enabled = 0; NO borra) los jobs habilitados encontrados con criterio
       'Infos/sincroniz' e imprime cuáles. Los de criterio 'solo AppPalma'
       (p. ej. respaldos de AppPalma) se listan pero solo se deshabilitan con
       @IncluirSoloAppPalma = 1. Con @Aplicar = 0 (por defecto) solo imprime
       cuáles deshabilitaría.

   IDEMPOTENTE
   -----------
     Solo toma jobs habilitados; volver a ejecutarlo no cambia nada. Si no hay
     jobs, replicación ni linked servers, devuelve resultados vacíos sin error.

   CÓMO EJECUTAR
   -------------
     En SSMS, conectado al servidor con un usuario sysadmin: ejecutar el
     archivo completo con @Aplicar = 0, revisar resultados y mensajes; luego
     poner @Aplicar = 1 y volver a ejecutarlo completo.
     Reactivar un job: EXEC msdb.dbo.sp_update_job @job_name = N'<job>', @enabled = 1;
   ============================================================================ */

SET NOCOUNT ON;

/* ---------- SECCIÓN 1: solo lectura ---------- */

DECLARE @jobs TABLE (job_id UNIQUEIDENTIFIER PRIMARY KEY, nombre SYSNAME, enabled TINYINT, infos BIT);

INSERT @jobs
SELECT j.job_id, j.name, j.enabled,
       MAX(CASE WHEN t.texto LIKE N'%infos%' OR t.texto LIKE N'%sincroniz%' THEN 1 ELSE 0 END)
FROM msdb.dbo.sysjobs j
LEFT JOIN msdb.dbo.sysjobsteps s ON s.job_id = j.job_id
CROSS APPLY (SELECT CONCAT(j.name, N' ', s.step_name, N' ', s.database_name, N' ', s.command) COLLATE Latin1_General_CI_AI AS texto) t
WHERE t.texto LIKE N'%infos%' OR t.texto LIKE N'%apppalma%' OR t.texto LIKE N'%sincroniz%'
GROUP BY j.job_id, j.name, j.enabled;

SELECT j.nombre AS job, j.enabled AS habilitado,
       CASE WHEN j.infos = 1 THEN N'Infos/sincroniz' ELSE N'solo AppPalma' END AS criterio,
       s.step_id, s.step_name, s.database_name, LEFT(s.command, 400) AS comando, n.proxima_ejecucion
FROM @jobs j
LEFT JOIN msdb.dbo.sysjobsteps s ON s.job_id = j.job_id
OUTER APPLY (SELECT MIN(msdb.dbo.agent_datetime(js.next_run_date, js.next_run_time)) AS proxima_ejecucion
             FROM msdb.dbo.sysjobschedules js
             WHERE js.job_id = j.job_id AND js.next_run_date > 0) n
ORDER BY j.nombre, s.step_id;

SELECT name AS base, is_published, is_subscribed, is_merge_published, is_distributor
FROM sys.databases
WHERE is_published = 1 OR is_subscribed = 1 OR is_merge_published = 1 OR is_distributor = 1
ORDER BY name;

IF DB_ID(N'distribution') IS NOT NULL
    EXEC (N'SELECT DISTINCT p.publisher_db, p.publication, p.publication_type, s.subscriber_db, s.status
            FROM distribution.dbo.MSpublications p
            LEFT JOIN distribution.dbo.MSsubscriptions s ON s.publication_id = p.publication_id
            ORDER BY p.publisher_db, p.publication;');
ELSE
    PRINT N'No existe la base distribution: el servidor no es distribuidor de replicación.';

SELECT name AS linked_server, product, provider, data_source, catalog, is_data_access_enabled, modify_date
FROM sys.servers
WHERE is_linked = 1
ORDER BY name;

/* ---------- SECCIÓN 2: deshabilitar jobs ---------- */

DECLARE @Aplicar BIT = 0;
DECLARE @IncluirSoloAppPalma BIT = 0;
DECLARE @job_id UNIQUEIDENTIFIER, @nombre SYSNAME, @n INT = 0;

DECLARE c CURSOR LOCAL FAST_FORWARD FOR
    SELECT job_id, nombre FROM @jobs
    WHERE enabled = 1 AND (infos = 1 OR @IncluirSoloAppPalma = 1)
    ORDER BY nombre;

OPEN c;
FETCH NEXT FROM c INTO @job_id, @nombre;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF @Aplicar = 1
    BEGIN
        EXEC msdb.dbo.sp_update_job @job_id = @job_id, @enabled = 0;
        PRINT N'Deshabilitado: ' + @nombre;
    END
    ELSE
        PRINT N'Se deshabilitaría: ' + @nombre;
    SET @n += 1;
    FETCH NEXT FROM c INTO @job_id, @nombre;
END
CLOSE c;
DEALLOCATE c;

IF @n = 0
    PRINT N'No hay jobs habilitados para deshabilitar.';
ELSE IF @Aplicar = 0
    PRINT N'@Aplicar = 0: no se cambió nada. Poner @Aplicar = 1 para deshabilitarlos.';
