/* ============================================================================
   Finca 004 GANADERIA CRUCEIRO deja de usarse: sus lotes pasan a 002 CRUCEIRO
   y se crea el lote GANADERIA BUFALOS
   ----------------------------------------------------------------------------
   REQUIERE haber ejecutado antes crear_unidades_negocio.sql (los lotes de la
   finca origen deben tener unidadNegocio: DGCR26-01 -> GTR, DGCR26-02 -> GCE).

   QUÉ HACE
   --------
     SECCIÓN 1 (vista previa, solo lectura). Para revisar sin modificar nada,
     seleccionar desde el inicio del script hasta el final de la sección 1 y
     ejecutar; después ejecutar SET NOEXEC OFF; (la guarda inicial pudo
     dejarlo activo y no hay GO final que lo apague).
       - Fincas origen y destino, lotes que se trasladan, habilitaciones de
         unidad de negocio en destino y conteo de filas con finca = origen en
         TODAS las tablas dbo que tienen columna "finca".
       - Choques de llave: filas de origen cuya llave sin finca ya existe en
         destino (aLotePesosPeriodo, aLotesDetalle, aLotesGeometria y aPalma
         por UQ_aPalma_posicion). Deben
         ser 0; si no, la sección 2 se detiene.
     SECCIÓN 2 (cambios, dentro de BEGIN TRAN con XACT_ABORT ON):
       1) Habilita en la finca destino las unidades de negocio de los lotes que
          llegan y la del lote nuevo (BUF).
       2) Lotes: como la PK de aLotes es (empresa, codigo, finca), inserta una
          copia de cada lote en la finca destino (conserva su unidad de negocio;
          si su sección no existe en destino queda sin sección), mueve las filas
          hijas/transaccionales y borra el lote original:
            aLotesDetalle, aPalma, aLotesGeometria, aLotePesosPeriodo,
            aTransaccion, aTransaccionNovedad, aTransaccionTercero, h_novedades.
       3) Borra las habilitaciones finca-unidad de la finca origen y la marca
          activo = 0 en aFinca (NO se borra).
       4) Crea el lote @LoteNuevo (GANADERIA BUFALOS) en la finca destino con
          unidad BUF, copiando los demás campos y las líneas de @LotePlantilla.
       5) Verificación: vuelve a contar finca = origen en todas las tablas. Si
          queda alguna fila hace ROLLBACK; si no, deja la transacción ABIERTA
          para que se revise y se ejecute COMMIT o ROLLBACK (ver final).

   ADVERTENCIA INFOS
   -----------------
     migracion_infos/02_sincronizar_infos_a_apppalma.sql copia de Infos a
     AppPalma aLotePesosPeriodo, aTransaccion, aTransaccionNovedad y
     aTransaccionTercero (incluida la finca). Si Infos sigue sincronizando, el
     mismo traslado 004 -> 002 debe hacerse también en Infos; de lo contrario la
     siguiente sincronización volverá a dejar filas con finca 004. En
     aLotePesosPeriodo (llave con finca), con @ConservarPesosSoloAppPalma = 0
     la sincronización BORRA las filas movidas a 002 (no existen en Infos) y
     REINSERTA las de 004.

   IDEMPOTENTE: si ya se aplicó, no encuentra filas de la finca origen y no
   vuelve a crear el lote nuevo.
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

DECLARE @Empresa         INT          = 1;
DECLARE @FincaOrigen     VARCHAR(50)  = '004';               -- GANADERIA CRUCEIRO (se desactiva)
DECLARE @FincaDestino    VARCHAR(50)  = '002';               -- CRUCEIRO
DECLARE @Usuario         VARCHAR(50)  = 'MIGRACION';
DECLARE @LotePlantilla   VARCHAR(50)  = 'DGCR26-01';         -- GANADERIA CRUCEIRO
DECLARE @LoteNuevo       VARCHAR(50)  = 'DBCR26-01';         -- CONFIRMAR código
DECLARE @LoteNuevoDesc   VARCHAR(550) = 'GANADERIA BUFALOS';
DECLARE @LoteNuevoUnidad VARCHAR(10)  = 'BUF';
DECLARE @LoteNuevoCcosto VARCHAR(50)  = '400103';            -- CONFIRMAR (hoy es el de CRLLC2-25 LOTE 2 DE BUFALOS)
DECLARE @sqlConteo       NVARCHAR(MAX) = N'';

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
 WHERE empresa = @Empresa AND codigo IN (@FincaOrigen, @FincaDestino);

SELECT l.finca, l.codigo, l.descripcion, l.unidadNegocio, l.seccion,
       CASE WHEN l.seccion IS NULL OR s.codigo IS NOT NULL THEN 'OK' ELSE 'QUEDA SIN SECCIÓN' END AS seccionEnDestino,
       CASE WHEN d.codigo IS NULL THEN 'OK' ELSE 'YA EXISTE EN DESTINO' END AS codigoEnDestino,
       l.ccosto, l.activo
  FROM dbo.aLotes l
  LEFT JOIN dbo.aSecciones s ON s.empresa = l.empresa AND s.finca = @FincaDestino AND s.codigo = l.seccion
  LEFT JOIN dbo.aLotes d ON d.empresa = l.empresa AND d.codigo = l.codigo AND d.finca = @FincaDestino
 WHERE l.empresa = @Empresa AND l.finca = @FincaOrigen
 ORDER BY l.codigo;

SELECT empresa, finca, unidadNegocio
  FROM dbo.aFincaUnidadNegocio
 WHERE empresa = @Empresa AND finca IN (@FincaOrigen, @FincaDestino)
 ORDER BY finca, unidadNegocio;

SELECT @LoteNuevo AS loteNuevo,
       CASE WHEN EXISTS (SELECT 1 FROM dbo.aLotes WHERE empresa = @Empresa AND codigo = @LoteNuevo)
            THEN 'YA EXISTE (no se crea)' ELSE 'SE CREA' END AS estado;

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

-- 1) Unidades de negocio habilitadas en destino
INSERT INTO dbo.aFincaUnidadNegocio (empresa, finca, unidadNegocio)
SELECT DISTINCT @Empresa, @FincaDestino, x.unidad
  FROM (SELECT unidadNegocio AS unidad FROM dbo.aLotes WHERE empresa = @Empresa AND finca = @FincaOrigen
        UNION
        SELECT @LoteNuevoUnidad) x
 WHERE NOT EXISTS (SELECT 1 FROM dbo.aFincaUnidadNegocio fu
                    WHERE fu.empresa = @Empresa AND fu.finca = @FincaDestino AND fu.unidadNegocio = x.unidad);

PRINT CONCAT(N'Habilitaciones creadas en la finca destino: ', @@ROWCOUNT);

-- 2) Lotes: copia en destino, traslado de hijas y borrado del original
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

PRINT CONCAT(N'Lotes copiados a la finca destino: ', @@ROWCOUNT);

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

DELETE FROM dbo.aLotes WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'Lotes originales borrados: ', @@ROWCOUNT);

-- 3) Finca origen: sin habilitaciones e inactiva
DELETE FROM dbo.aFincaUnidadNegocio WHERE empresa = @Empresa AND finca = @FincaOrigen;
PRINT CONCAT(N'Habilitaciones borradas en la finca origen: ', @@ROWCOUNT);

UPDATE dbo.aFinca SET activo = 0 WHERE empresa = @Empresa AND codigo = @FincaOrigen AND activo = 1;
PRINT CONCAT(N'Finca origen desactivada: ', @@ROWCOUNT);

-- 4) Lote GANADERIA BUFALOS
IF NOT EXISTS (SELECT 1 FROM dbo.aLotes WHERE empresa = @Empresa AND codigo = @LoteNuevo)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM dbo.aLotes WHERE empresa = @Empresa AND codigo = @LotePlantilla AND finca = @FincaDestino)
        THROW 50004, N'No existe el lote plantilla en la finca destino.', 1;

    INSERT INTO dbo.aLotes (empresa, codigo, manejaSeccion, seccion, finca, descripcion, [añoSiembra], mesSiembra,
                            palmasBrutas, palmasProduccion, hBrutas, hNetas, dSiembra, variedad, densidad, NoLineas,
                            fechaRegistro, usuario, foto, activo, desarrollo, numero, letra, ccosto, unidadNegocio)
    SELECT empresa, @LoteNuevo, manejaSeccion, seccion, finca, @LoteNuevoDesc, [añoSiembra], mesSiembra,
           palmasBrutas, palmasProduccion, hBrutas, hNetas, dSiembra, variedad, densidad, NoLineas,
           GETDATE(), @Usuario, NULL, activo, desarrollo, numero, letra, @LoteNuevoCcosto, @LoteNuevoUnidad
      FROM dbo.aLotes
     WHERE empresa = @Empresa AND codigo = @LotePlantilla AND finca = @FincaDestino;

    INSERT INTO dbo.aLotesDetalle (empresa, lote, linea, finca, noPalma, izquierda, palmaErradicada)
    SELECT empresa, @LoteNuevo, linea, finca, noPalma, izquierda, palmaErradicada
      FROM dbo.aLotesDetalle
     WHERE empresa = @Empresa AND lote = @LotePlantilla AND finca = @FincaDestino;

    PRINT CONCAT(N'Lote ', @LoteNuevo, N' creado con ', @@ROWCOUNT, N' línea(s).');
END
ELSE
    PRINT CONCAT(N'El lote ', @LoteNuevo, N' ya existía: no se crea.');

-- 5) Verificación
TRUNCATE TABLE #conteo;

EXEC sp_executesql @sqlConteo, N'@Finca VARCHAR(50), @Empresa INT', @Finca = @FincaOrigen, @Empresa = @Empresa;

SELECT tabla, filas AS filasFincaOrigen FROM #conteo WHERE filas > 0 ORDER BY tabla;

SELECT l.finca, l.codigo, l.descripcion, l.unidadNegocio, l.seccion, l.ccosto, l.activo,
       (SELECT COUNT(*) FROM dbo.aLotesDetalle d WHERE d.empresa = l.empresa AND d.lote = l.codigo AND d.finca = l.finca) AS lineas
  FROM dbo.aLotes l
 WHERE l.empresa = @Empresa AND l.finca = @FincaDestino AND l.unidadNegocio <> 'PAL'
 ORDER BY l.codigo;

SELECT empresa, codigo, descripcion, activo
  FROM dbo.aFinca
 WHERE empresa = @Empresa AND codigo IN (@FincaOrigen, @FincaDestino);

IF EXISTS (SELECT 1 FROM #conteo WHERE filas > 0)
BEGIN
    ROLLBACK TRAN;
    RAISERROR (N'Quedan filas con la finca origen (ver primer resultado de la verificación). Se hizo ROLLBACK.', 16, 1);
END
ELSE
    PRINT N'Verificación correcta: 0 filas con la finca origen. La transacción sigue ABIERTA: revise y ejecute COMMIT TRAN o ROLLBACK TRAN.';
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   DECISIÓN FINAL (ejecutar SOLO UNA de las dos líneas, en la misma pestaña):

   SET NOEXEC OFF;       -- primero: si quedó activo, el COMMIT no se ejecutaría (sin aviso)
   SELECT @@TRANCOUNT;   -- debe ser 1; si es 0 la transacción ya se revirtió
   COMMIT TRAN;          -- confirma el traslado
   ROLLBACK TRAN;        -- descarta todos los cambios

   - XACT_ABORT ON sigue activo en la sesión: cualquier error en una consulta
     manual hecha antes del COMMIT revierte toda la transacción.
   - No hay reversión después del COMMIT: el lote original y las
     habilitaciones de la finca origen se borran.
   ---------------------------------------------------------------------------- */
