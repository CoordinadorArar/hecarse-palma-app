/* ============================================================================
   Reorganización de fincas y unidades de negocio
   ----------------------------------------------------------------------------
   Resultado: solo quedan las fincas 001 EL HATO, 002 CRUCEIRO y 003 CAÑAGUATE.
   La finca 004 GANADERIA CRUCEIRO deja de usarse: sus lotes pasan a 002
   conservando todo su historial. Las unidades de negocio toman los códigos de
   Siesa:
     PAL -> 001 PALMA
     GTR -> 002 GANADERIA TRADICIONAL
     GCE -> 003 GANADERIA CEBA
     BUF -> 004 GANADERIA BUFALOS
   Habilitaciones finales: 001 -> {001, 002}; 002 -> {001, 002, 003, 004};
   003 -> {001}; 004 -> ninguna.

   REQUIERE haber ejecutado antes crear_unidades_negocio.sql.
   DESPUÉS DEL COMMIT ejecutar contabilizar_unidad_negocio_lote.sql (la vista
   de contabilización toma la unidad de negocio del lote).

   QUÉ HACE
   --------
     SECCIÓN 1 (vista previa, solo lectura). Para revisar sin modificar nada,
     seleccionar desde el inicio del script hasta el final de la sección 1 y
     ejecutar; después ejecutar SET NOEXEC OFF; (la guarda inicial pudo
     dejarlo activo y no hay GO final que lo apague).
       - Fincas, unidades de negocio con su código nuevo, habilitaciones,
         lotes no palma y conteo de filas con finca = origen en TODAS las
         tablas dbo que tienen columna "finca".
       - Choques de llave: filas de origen cuya llave sin finca ya existe en
         destino (aLotePesosPeriodo, aLotesDetalle, aLotesGeometria y aPalma
         por UQ_aPalma_posicion). Deben ser 0; si no, la sección 2 se detiene.
     SECCIÓN 2 (cambios, dentro de BEGIN TRAN con XACT_ABORT ON):
       1) Recodifica las unidades de negocio (conserva activo): crea las
          unidades nuevas, copia sus habilitaciones, actualiza
          aLotes.unidadNegocio y borra las habilitaciones y unidades viejas
          (en ese orden por las FK, todas NO ACTION).
       2) Habilita en la finca destino las unidades 001, 002, 003 y 004.
       3) Lotes: como la PK de aLotes es (empresa, codigo, finca), inserta una
          copia de cada lote en la finca destino (conserva su unidad de negocio;
          si su sección no existe en destino queda sin sección), mueve las filas
          hijas/transaccionales y borra el lote original:
            aLotesDetalle, aPalma, aLotesGeometria, aLotePesosPeriodo,
            aTransaccion, aTransaccionNovedad, aTransaccionTercero, h_novedades,
            pPlaneacionLinea.
       4) Borra las habilitaciones finca-unidad de la finca origen y la marca
          activo = 0 en aFinca (NO se borra).
       5) Centro de costo del lote DGCR26-02 (GANADERIA CEBA) = 400101.
       6) Registra el cambio en Auditoria (solo si hubo cambios).
       7) Verificación: vuelve a contar finca = origen en todas las tablas y
          busca unidades viejas. Si queda alguna fila hace ROLLBACK; si no, deja
          la transacción ABIERTA para que se revise y se ejecute COMMIT o
          ROLLBACK (ver final).

   INFOS: dejó de usarse el 2026-11-04; no se requiere replicar el cambio allí.

   IDEMPOTENTE: si ya se aplicó, no encuentra unidades viejas ni filas de la
   finca origen y no registra auditoría.
   Ejecutar SOLO desde SSMS, completo y en una sola pestaña (la transacción
   queda abierta en esa sesión). No usar sqlcmd -i: al terminar cierra la
   conexión y revierte la transacción abierta.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- RAISERROR (no THROW): con XACT_ABORT ON un THROW revertiría la transacción pendiente de la corrida anterior.
IF @@TRANCOUNT > 0
BEGIN
    RAISERROR (N'Hay una transacción abierta en la sesión: ejecute COMMIT TRAN o ROLLBACK TRAN antes de volver a correr el script.', 16, 1);
    SET NOEXEC ON;
END
ELSE IF COL_LENGTH('dbo.aLotes', 'unidadNegocio') IS NULL OR OBJECT_ID('dbo.aFincaUnidadNegocio', 'U') IS NULL
BEGIN
    RAISERROR (N'Falta el modelo de unidades de negocio: ejecute antes crear_unidades_negocio.sql.', 16, 1);
    SET NOEXEC ON;
END;
GO

DECLARE @Empresa        INT          = 1;
DECLARE @FincaOrigen    VARCHAR(50)  = '004';               -- GANADERIA CRUCEIRO (se desactiva)
DECLARE @FincaDestino   VARCHAR(50)  = '002';               -- CRUCEIRO
DECLARE @Usuario        VARCHAR(50)  = 'MIGRACION';
DECLARE @IdUsuario      INT          = 1;
DECLARE @Script         VARCHAR(100) = 'reorganizar_fincas_unidades_negocio.sql';
DECLARE @LoteCeba       VARCHAR(50)  = 'DGCR26-02';         -- GANADERIA CEBA
DECLARE @CcostoCeba     VARCHAR(50)  = '400101';
DECLARE @nUnidades      INT = 0;
DECLARE @nLotesUnidad   INT = 0;
DECLARE @nHabilitadas   INT = 0;
DECLARE @nLotes         INT = 0;
DECLARE @nCcosto        INT = 0;
DECLARE @nFinca         INT = 0;
DECLARE @sqlConteo      NVARCHAR(MAX) = N'';

IF OBJECT_ID('tempdb..#unidades') IS NOT NULL
    DROP TABLE #unidades;

CREATE TABLE #unidades (anterior VARCHAR(10) NOT NULL PRIMARY KEY, nuevo VARCHAR(10) NOT NULL UNIQUE, descripcion VARCHAR(100) NOT NULL);

INSERT INTO #unidades (anterior, nuevo, descripcion)
VALUES ('PAL', '001', 'PALMA'),
       ('GTR', '002', 'GANADERIA TRADICIONAL'),
       ('GCE', '003', 'GANADERIA CEBA'),
       ('BUF', '004', 'GANADERIA BUFALOS');

IF OBJECT_ID('tempdb..#conteo') IS NOT NULL
    DROP TABLE #conteo;

CREATE TABLE #conteo (tabla SYSNAME NOT NULL, filas INT NOT NULL);

SELECT @sqlConteo += N'INSERT INTO #conteo (tabla, filas) SELECT N' + QUOTENAME(t.name, '''') + N', COUNT(*) FROM dbo.' + QUOTENAME(t.name)
                   + N' WHERE finca = @Finca'
                   + CASE WHEN COL_LENGTH('dbo.' + t.name, 'empresa') IS NOT NULL THEN N' AND empresa = @Empresa' ELSE N'' END
                   + N'; '
  FROM sys.tables t
  JOIN sys.columns c ON c.object_id = t.object_id AND c.name = 'finca'
 WHERE t.schema_id = SCHEMA_ID('dbo')
   AND TYPE_NAME(c.user_type_id) IN ('char', 'varchar', 'nchar', 'nvarchar');

-- Choques de PK: filas de la finca origen cuya llave (sin finca) ya existe en la finca destino.
DECLARE @sqlChoques NVARCHAR(MAX) = N'
    INSERT INTO #choques (tabla, filas)
    SELECT N''aLotePesosPeriodo'', COUNT(*)
      FROM dbo.aLotePesosPeriodo o
     WHERE o.empresa = @Empresa AND o.finca = @Origen
       AND EXISTS (SELECT 1 FROM dbo.aLotePesosPeriodo d
                    WHERE d.empresa = o.empresa AND d.[año] = o.[año] AND d.mes = o.mes AND d.lote = o.lote AND d.finca = @Destino)
    UNION ALL
    SELECT N''aLotesDetalle'', COUNT(*)
      FROM dbo.aLotesDetalle o
     WHERE o.empresa = @Empresa AND o.finca = @Origen
       AND EXISTS (SELECT 1 FROM dbo.aLotesDetalle d
                    WHERE d.empresa = o.empresa AND d.lote = o.lote AND d.linea = o.linea AND d.finca = @Destino)
    UNION ALL
    SELECT N''aLotesGeometria'', COUNT(*)
      FROM dbo.aLotesGeometria o
     WHERE o.empresa = @Empresa AND o.finca = @Origen
       AND EXISTS (SELECT 1 FROM dbo.aLotesGeometria d
                    WHERE d.empresa = o.empresa AND d.lote = o.lote AND d.finca = @Destino)
    UNION ALL
    SELECT N''aPalma'', COUNT(*)
      FROM dbo.aPalma o
     WHERE o.empresa = @Empresa AND o.finca = @Origen
       AND EXISTS (SELECT 1 FROM dbo.aPalma d
                    WHERE d.empresa = o.empresa AND d.lote = o.lote AND d.linea = o.linea AND d.palma = o.palma AND d.finca = @Destino);';

IF OBJECT_ID('tempdb..#choques') IS NOT NULL
    DROP TABLE #choques;

CREATE TABLE #choques (tabla SYSNAME NOT NULL, filas INT NOT NULL);

/* ============================================================================
   SECCIÓN 1. VISTA PREVIA (solo lectura)
   ============================================================================ */

SELECT empresa, codigo, descripcion, activo
  FROM dbo.aFinca
 WHERE empresa = @Empresa
 ORDER BY codigo;

SELECT u.codigo, u.descripcion, u.activo,
       COALESCE(m.nuevo, n.nuevo) AS codigoNuevo,
       COALESCE(m.descripcion, n.descripcion) AS descripcionNueva,
       CASE WHEN m.anterior IS NOT NULL THEN 'SE RECODIFICA' WHEN n.nuevo IS NOT NULL THEN 'YA RECODIFICADA' ELSE 'SIN CAMBIO' END AS estado,
       (SELECT COUNT(*) FROM dbo.aLotes l WHERE l.empresa = u.empresa AND l.unidadNegocio = u.codigo) AS lotes
  FROM dbo.aUnidadNegocio u
  LEFT JOIN #unidades m ON m.anterior = u.codigo
  LEFT JOIN #unidades n ON n.nuevo = u.codigo
 WHERE u.empresa = @Empresa
 ORDER BY u.codigo;

SELECT fu.finca, fu.unidadNegocio, ISNULL(m.nuevo, fu.unidadNegocio) AS unidadNueva,
       CASE WHEN fu.finca = @FincaOrigen THEN 'SE BORRA' ELSE 'SE CONSERVA' END AS estado
  FROM dbo.aFincaUnidadNegocio fu
  LEFT JOIN #unidades m ON m.anterior = fu.unidadNegocio
 WHERE fu.empresa = @Empresa
 ORDER BY fu.finca, fu.unidadNegocio;

SELECT l.finca, l.codigo, l.descripcion, l.unidadNegocio, ISNULL(m.nuevo, l.unidadNegocio) AS unidadNueva, l.seccion,
       CASE WHEN l.finca <> @FincaOrigen THEN '' WHEN l.seccion IS NULL OR s.codigo IS NOT NULL THEN 'OK' ELSE 'QUEDA SIN SECCIÓN' END AS seccionEnDestino,
       CASE WHEN l.finca <> @FincaOrigen THEN '' WHEN d.codigo IS NULL THEN 'OK' ELSE 'YA EXISTE EN DESTINO' END AS codigoEnDestino,
       l.ccosto, l.activo
  FROM dbo.aLotes l
  LEFT JOIN #unidades m ON m.anterior = l.unidadNegocio
  LEFT JOIN dbo.aSecciones s ON s.empresa = l.empresa AND s.finca = @FincaDestino AND s.codigo = l.seccion
  LEFT JOIN dbo.aLotes d ON d.empresa = l.empresa AND d.codigo = l.codigo AND d.finca = @FincaDestino
 WHERE l.empresa = @Empresa
   AND (l.unidadNegocio IS NULL OR l.unidadNegocio NOT IN ('PAL', '001') OR l.finca = @FincaOrigen)
 ORDER BY l.finca, l.codigo;

EXEC sp_executesql @sqlConteo, N'@Finca VARCHAR(50), @Empresa INT', @Finca = @FincaOrigen, @Empresa = @Empresa;

SELECT tabla, filas AS filasFincaOrigen FROM #conteo WHERE filas > 0 ORDER BY tabla;

EXEC sp_executesql @sqlChoques, N'@Empresa INT, @Origen VARCHAR(50), @Destino VARCHAR(50)',
     @Empresa = @Empresa, @Origen = @FincaOrigen, @Destino = @FincaDestino;

SELECT tabla, filas AS choquesLlaveEnDestino FROM #choques ORDER BY tabla;

/* ============================================================================
   SECCIÓN 2. CAMBIOS
   ============================================================================ */

IF OBJECT_ID('tempdb..#lotes') IS NOT NULL
    DROP TABLE #lotes;

CREATE TABLE #lotes (codigo VARCHAR(50) NOT NULL PRIMARY KEY, seccion VARCHAR(50) NULL);

BEGIN TRAN;

IF NOT EXISTS (SELECT 1 FROM dbo.aFinca WHERE empresa = @Empresa AND codigo = @FincaDestino AND activo = 1)
    THROW 50001, N'La finca destino no existe o está inactiva.', 1;

IF EXISTS (SELECT 1 FROM dbo.aLotes o
             JOIN dbo.aLotes d ON d.empresa = o.empresa AND d.codigo = o.codigo AND d.finca = @FincaDestino
            WHERE o.empresa = @Empresa AND o.finca = @FincaOrigen)
    THROW 50002, N'Algún lote de la finca origen ya existe en la finca destino.', 1;

TRUNCATE TABLE #choques;

EXEC sp_executesql @sqlChoques, N'@Empresa INT, @Origen VARCHAR(50), @Destino VARCHAR(50)',
     @Empresa = @Empresa, @Origen = @FincaOrigen, @Destino = @FincaDestino;

IF EXISTS (SELECT 1 FROM #choques WHERE filas > 0)
    THROW 50005, N'Hay filas de la finca origen cuya llave ya existe en la finca destino (aLotePesosPeriodo, aLotesDetalle, aLotesGeometria o aPalma): ver vista previa.', 1;

IF EXISTS (SELECT 1 FROM dbo.aLotes WHERE empresa = @Empresa AND finca = @FincaOrigen AND unidadNegocio IS NULL)
    THROW 50003, N'Hay lotes de la finca origen sin unidad de negocio: ejecute antes crear_unidades_negocio.sql.', 1;

-- 1) Recodificación de unidades de negocio
INSERT INTO dbo.aUnidadNegocio (empresa, codigo, descripcion, activo, fechaRegistro, usuarioRegistro)
SELECT @Empresa, m.nuevo, m.descripcion, ISNULL(u.activo, 1), GETDATE(), @Usuario
  FROM #unidades m
  LEFT JOIN dbo.aUnidadNegocio u ON u.empresa = @Empresa AND u.codigo = m.anterior
 WHERE NOT EXISTS (SELECT 1 FROM dbo.aUnidadNegocio x WHERE x.empresa = @Empresa AND x.codigo = m.nuevo);

SET @nUnidades = @@ROWCOUNT;
PRINT CONCAT(N'Unidades de negocio creadas con código nuevo: ', @nUnidades);

INSERT INTO dbo.aFincaUnidadNegocio (empresa, finca, unidadNegocio)
SELECT DISTINCT fu.empresa, fu.finca, m.nuevo
  FROM dbo.aFincaUnidadNegocio fu
  JOIN #unidades m ON m.anterior = fu.unidadNegocio
 WHERE fu.empresa = @Empresa
   AND NOT EXISTS (SELECT 1 FROM dbo.aFincaUnidadNegocio x
                    WHERE x.empresa = fu.empresa AND x.finca = fu.finca AND x.unidadNegocio = m.nuevo);

PRINT CONCAT(N'Habilitaciones copiadas al código nuevo: ', @@ROWCOUNT);

UPDATE l
   SET l.unidadNegocio = m.nuevo
  FROM dbo.aLotes l
  JOIN #unidades m ON m.anterior = l.unidadNegocio
 WHERE l.empresa = @Empresa;

SET @nLotesUnidad = @@ROWCOUNT;
PRINT CONCAT(N'Lotes con unidad de negocio recodificada: ', @nLotesUnidad);

DELETE fu
  FROM dbo.aFincaUnidadNegocio fu
  JOIN #unidades m ON m.anterior = fu.unidadNegocio
 WHERE fu.empresa = @Empresa;

PRINT CONCAT(N'Habilitaciones con código viejo borradas: ', @@ROWCOUNT);

DELETE u
  FROM dbo.aUnidadNegocio u
  JOIN #unidades m ON m.anterior = u.codigo
 WHERE u.empresa = @Empresa;

PRINT CONCAT(N'Unidades de negocio con código viejo borradas: ', @@ROWCOUNT);

-- 2) Unidades de negocio habilitadas en destino
INSERT INTO dbo.aFincaUnidadNegocio (empresa, finca, unidadNegocio)
SELECT @Empresa, @FincaDestino, m.nuevo
  FROM #unidades m
 WHERE NOT EXISTS (SELECT 1 FROM dbo.aFincaUnidadNegocio fu
                    WHERE fu.empresa = @Empresa AND fu.finca = @FincaDestino AND fu.unidadNegocio = m.nuevo);

SET @nHabilitadas = @@ROWCOUNT;
PRINT CONCAT(N'Habilitaciones creadas en la finca destino: ', @nHabilitadas);

-- 3) Lotes: copia en destino, traslado de hijas y borrado del original
INSERT INTO #lotes (codigo, seccion)
SELECT l.codigo, s.codigo
  FROM dbo.aLotes l
  LEFT JOIN dbo.aSecciones s ON s.empresa = l.empresa AND s.finca = @FincaDestino AND s.codigo = l.seccion
 WHERE l.empresa = @Empresa AND l.finca = @FincaOrigen;

INSERT INTO dbo.aLotes (empresa, codigo, manejaSeccion, seccion, finca, descripcion, [añoSiembra], mesSiembra,
                        palmasBrutas, palmasProduccion, hBrutas, hNetas, dSiembra, variedad, densidad, NoLineas,
                        fechaRegistro, usuario, foto, activo, desarrollo, numero, letra, ccosto, unidadNegocio)
SELECT l.empresa, l.codigo, CASE WHEN m.seccion IS NULL THEN 0 ELSE l.manejaSeccion END, m.seccion, @FincaDestino,
       l.descripcion, l.[añoSiembra], l.mesSiembra, l.palmasBrutas, l.palmasProduccion, l.hBrutas, l.hNetas,
       l.dSiembra, l.variedad, l.densidad, l.NoLineas, l.fechaRegistro, l.usuario, l.foto, l.activo, l.desarrollo,
       l.numero, l.letra, l.ccosto, l.unidadNegocio
  FROM dbo.aLotes l
  JOIN #lotes m ON m.codigo = l.codigo
 WHERE l.empresa = @Empresa AND l.finca = @FincaOrigen;

SET @nLotes = @@ROWCOUNT;
PRINT CONCAT(N'Lotes copiados a la finca destino: ', @nLotes);

UPDATE dbo.aLotesDetalle SET finca = @FincaDestino WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'aLotesDetalle: ', @@ROWCOUNT);

UPDATE dbo.aPalma SET finca = @FincaDestino WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'aPalma: ', @@ROWCOUNT);

UPDATE dbo.aLotesGeometria SET finca = @FincaDestino WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'aLotesGeometria: ', @@ROWCOUNT);

UPDATE p
   SET p.finca = @FincaDestino, p.seccion = CASE WHEN m.codigo IS NULL THEN p.seccion ELSE m.seccion END
  FROM dbo.aLotePesosPeriodo p
  LEFT JOIN #lotes m ON m.codigo = p.lote
 WHERE p.empresa = @Empresa AND p.finca = @FincaOrigen;
PRINT CONCAT(N'aLotePesosPeriodo: ', @@ROWCOUNT);

UPDATE dbo.aTransaccion SET finca = @FincaDestino WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'aTransaccion: ', @@ROWCOUNT);

UPDATE t
   SET t.finca = @FincaDestino, t.seccion = CASE WHEN m.codigo IS NULL THEN t.seccion ELSE m.seccion END
  FROM dbo.aTransaccionNovedad t
  LEFT JOIN #lotes m ON m.codigo = t.lote
 WHERE t.empresa = @Empresa AND t.finca = @FincaOrigen;
PRINT CONCAT(N'aTransaccionNovedad: ', @@ROWCOUNT);

UPDATE t
   SET t.finca = @FincaDestino, t.seccion = CASE WHEN m.codigo IS NULL THEN t.seccion ELSE m.seccion END
  FROM dbo.aTransaccionTercero t
  LEFT JOIN #lotes m ON m.codigo = t.lote
 WHERE t.empresa = @Empresa AND t.finca = @FincaOrigen;
PRINT CONCAT(N'aTransaccionTercero: ', @@ROWCOUNT);

UPDATE dbo.h_novedades SET finca = @FincaDestino WHERE finca = @FincaOrigen;
PRINT CONCAT(N'h_novedades: ', @@ROWCOUNT);

UPDATE pl SET pl.finca = @FincaDestino FROM dbo.pPlaneacionLinea pl JOIN dbo.pPlaneacion p ON p.id = pl.planeacion WHERE p.empresa = @Empresa AND pl.finca = @FincaOrigen;
PRINT CONCAT(N'pPlaneacionLinea: ', @@ROWCOUNT);

DELETE FROM dbo.aLotes WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'Lotes originales borrados: ', @@ROWCOUNT);

-- 4) Finca origen: sin habilitaciones e inactiva
DELETE FROM dbo.aFincaUnidadNegocio WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'Habilitaciones borradas en la finca origen: ', @@ROWCOUNT);

UPDATE dbo.aFinca SET activo = 0 WHERE empresa = @Empresa AND codigo = @FincaOrigen AND activo = 1;

SET @nFinca = @@ROWCOUNT;
PRINT CONCAT(N'Finca origen desactivada: ', @nFinca);

-- 5) Centro de costo de GANADERIA CEBA
UPDATE dbo.aLotes
   SET ccosto = @CcostoCeba
 WHERE empresa = @Empresa AND finca = @FincaDestino AND codigo = @LoteCeba
   AND (ccosto IS NULL OR ccosto <> @CcostoCeba);

SET @nCcosto = @@ROWCOUNT;
PRINT CONCAT(N'Centro de costo actualizado en ', @LoteCeba, N': ', @nCcosto);

-- 6) Auditoría
IF @nUnidades + @nLotesUnidad + @nHabilitadas + @nLotes + @nFinca + @nCcosto > 0
    INSERT INTO Auditoria (TablaAfectada, Accion, CambiosRealizados, IdUsuario, FechaRegistro)
    VALUES ('aUnidadNegocio', 'UPDATE',
            CONCAT('{"script":"', @Script, '","recodificacion":{"PAL":"001","GTR":"002","GCE":"003","BUF":"004"}',
                   ',"unidadesCreadas":', @nUnidades, ',"lotesRecodificados":', @nLotesUnidad, '}'),
            @IdUsuario, GETDATE()),
           ('aLotes', 'UPDATE',
            CONCAT('{"script":"', @Script, '","fincaOrigen":"', @FincaOrigen, '","fincaDestino":"', @FincaDestino,
                   '","lotesTrasladados":', @nLotes, ',"habilitacionesDestino":', @nHabilitadas,
                   ',"fincaDesactivada":', @nFinca, ',"ccosto":{"lote":"', @LoteCeba, '","valor":"', @CcostoCeba,
                   '","filas":', @nCcosto, '}}'),
            @IdUsuario, GETDATE());

-- 7) Verificación
TRUNCATE TABLE #conteo;

EXEC sp_executesql @sqlConteo, N'@Finca VARCHAR(50), @Empresa INT', @Finca = @FincaOrigen, @Empresa = @Empresa;

INSERT INTO #conteo (tabla, filas)
SELECT N'aUnidadNegocio (códigos viejos)', COUNT(*)
  FROM dbo.aUnidadNegocio u
  JOIN #unidades m ON m.anterior = u.codigo
 WHERE u.empresa = @Empresa;

SELECT tabla, filas AS filasPendientes FROM #conteo WHERE filas > 0 ORDER BY tabla;

SELECT u.codigo, u.descripcion, u.activo,
       (SELECT COUNT(*) FROM dbo.aLotes l WHERE l.empresa = u.empresa AND l.unidadNegocio = u.codigo) AS lotes
  FROM dbo.aUnidadNegocio u
 WHERE u.empresa = @Empresa
 ORDER BY u.codigo;

SELECT fu.finca, f.descripcion AS nombreFinca, f.activo AS fincaActiva, fu.unidadNegocio, u.descripcion AS nombreUnidad,
       (SELECT COUNT(*) FROM dbo.aLotes l
         WHERE l.empresa = fu.empresa AND l.finca = fu.finca AND l.unidadNegocio = fu.unidadNegocio) AS lotes
  FROM dbo.aFincaUnidadNegocio fu
  JOIN dbo.aFinca f ON f.empresa = fu.empresa AND f.codigo = fu.finca
  JOIN dbo.aUnidadNegocio u ON u.empresa = fu.empresa AND u.codigo = fu.unidadNegocio
 WHERE fu.empresa = @Empresa
 ORDER BY fu.finca, fu.unidadNegocio;

SELECT l.finca, l.codigo, l.descripcion, l.unidadNegocio, l.seccion, l.ccosto, l.activo,
       (SELECT COUNT(*) FROM dbo.aLotesDetalle d WHERE d.empresa = l.empresa AND d.lote = l.codigo AND d.finca = l.finca) AS lineas
  FROM dbo.aLotes l
 WHERE l.empresa = @Empresa AND (l.unidadNegocio IS NULL OR l.unidadNegocio <> '001')
 ORDER BY l.finca, l.codigo;

SELECT empresa, codigo, descripcion, activo
  FROM dbo.aFinca
 WHERE empresa = @Empresa
 ORDER BY codigo;

IF EXISTS (SELECT 1 FROM #conteo WHERE filas > 0)
BEGIN
    ROLLBACK TRAN;
    RAISERROR (N'Quedan filas con la finca origen o unidades con código viejo (ver primer resultado de la verificación). Se hizo ROLLBACK.', 16, 1);
END
ELSE
    PRINT N'Verificación correcta: 0 filas con la finca origen y 0 unidades con código viejo. La transacción sigue ABIERTA: revise y ejecute COMMIT TRAN o ROLLBACK TRAN.';
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   DECISIÓN FINAL (ejecutar SOLO UNA de las dos líneas, en la misma pestaña):

   SET NOEXEC OFF;       -- primero: si quedó activo, el COMMIT no se ejecutaría (sin aviso)
   SELECT @@TRANCOUNT;   -- debe ser 1; si es 0 la transacción ya se revirtió
   COMMIT TRAN;          -- confirma la reorganización
   ROLLBACK TRAN;        -- descarta todos los cambios

   - XACT_ABORT ON sigue activo en la sesión: cualquier error en una consulta
     manual hecha antes del COMMIT revierte toda la transacción.
   - No hay reversión después del COMMIT: el lote original, las unidades con
     código viejo y las habilitaciones de la finca origen se borran.
   - Después del COMMIT ejecutar contabilizar_unidad_negocio_lote.sql.
   ---------------------------------------------------------------------------- */
