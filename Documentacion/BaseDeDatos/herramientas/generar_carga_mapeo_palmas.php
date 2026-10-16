<?php

$raiz   = 'E:/xampp/htdocs/AppPalma/Documentacion/';
$dir    = rtrim($argv[1] ?? $raiz, '/\\') . '/';
$salida = $argv[2] ?? $raiz . 'BaseDeDatos/cargar_mapeo_palmas_cruceiro.sql';

function abrir(string $f): PDO
{
    if (! is_file($f)) {
        fwrite(STDERR, "No existe $f\n");
        exit(1);
    }
    $d = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
    $d->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $d;
}

function tmInversa(float $E, float $N): array
{
    $a = 6378137.0; $f = 1 / 298.257222101; $e2 = 2 * $f - $f * $f; $ep2 = $e2 / (1 - $e2);
    $lat0 = deg2rad(4.59620041666667); $lon0 = deg2rad(-74.0775079166667); $k0 = 1.0; $FE = 1000000; $FN = 1000000;
    $M = fn ($p) => $a * ((1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256) * $p - (3 * $e2 / 8 + 3 * $e2 ** 2 / 32 + 45 * $e2 ** 3 / 1024) * sin(2 * $p) + (15 * $e2 ** 2 / 256 + 45 * $e2 ** 3 / 1024) * sin(4 * $p) - (35 * $e2 ** 3 / 3072) * sin(6 * $p));
    $M1 = $M($lat0) + ($N - $FN) / $k0;
    $mu = $M1 / ($a * (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256));
    $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));
    $p1 = $mu + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu) + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu) + (151 * $e1 ** 3 / 96) * sin(6 * $mu) + (1097 * $e1 ** 4 / 512) * sin(8 * $mu);
    $C1 = $ep2 * cos($p1) ** 2; $T1 = tan($p1) ** 2; $N1 = $a / sqrt(1 - $e2 * sin($p1) ** 2); $R1 = $a * (1 - $e2) / (1 - $e2 * sin($p1) ** 2) ** 1.5;
    $D = ($E - $FE) / ($N1 * $k0);
    $lat = $p1 - ($N1 * tan($p1) / $R1) * ($D ** 2 / 2 - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 ** 2 - 9 * $ep2) * $D ** 4 / 24 + (61 + 90 * $T1 + 298 * $C1 + 45 * $T1 ** 2 - 252 * $ep2 - 3 * $C1 ** 2) * $D ** 6 / 720);
    $lon = $lon0 + ($D - (1 + 2 * $T1 + $C1) * $D ** 3 / 6 + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 ** 2 + 8 * $ep2 + 24 * $T1 ** 2) * $D ** 5 / 120) / cos($p1);
    return [round(rad2deg($lon), 7), round(rad2deg($lat), 7)];
}

function gpkgDecodificar(string $b): array
{
    if (substr($b, 0, 2) !== 'GP') {
        throw new Exception('Geometría GPKG inválida');
    }
    $flags = ord($b[3]);
    $pos = 8 + [0, 32, 48, 48, 64][($flags >> 1) & 7];
    return wkb($b, $pos);
}

function wkb(string $b, int &$pos): array
{
    $le = ord($b[$pos]); $pos++;
    $t = unpack($le ? 'V' : 'N', substr($b, $pos, 4))[1]; $pos += 4;
    $dim = 2;
    if ($t & 0x80000000) $dim++;
    if ($t & 0x40000000) $dim++;
    $t &= 0x0FFFFFFF;
    if ($t > 3000) { $dim = 4; $t -= 3000; } elseif ($t > 2000) { $dim = 3; $t -= 2000; } elseif ($t > 1000) { $dim = 3; $t -= 1000; }
    $u = function () use ($b, &$pos, $le) { $v = unpack($le ? 'V' : 'N', substr($b, $pos, 4))[1]; $pos += 4; return $v; };
    $pt = function () use ($b, &$pos, $le, $dim) { $v = array_values(unpack(($le ? 'e' : 'E') . $dim, substr($b, $pos, 8 * $dim))); $pos += 8 * $dim; return [$v[0], $v[1]]; };
    $ring = function () use ($u, $pt) { $n = $u(); $r = []; for ($i = 0; $i < $n; $i++) $r[] = $pt(); return $r; };
    switch ($t) {
        case 1: return ['Point', $pt()];
        case 3: $n = $u(); $r = []; for ($i = 0; $i < $n; $i++) $r[] = $ring(); return ['Polygon', $r];
        case 6: $n = $u(); $g = []; for ($i = 0; $i < $n; $i++) $g[] = wkb($b, $pos)[1]; return ['MultiPolygon', $g];
    }
    throw new Exception("Tipo WKB no soportado: $t");
}

function aWgs84(array $g): array
{
    $anillo = fn ($r) => array_map(fn ($p) => tmInversa($p[0], $p[1]), $r);
    if ($g[0] === 'Polygon') return ['type' => 'Polygon', 'coordinates' => array_map($anillo, $g[1])];
    return ['type' => 'MultiPolygon', 'coordinates' => array_map(fn ($pol) => array_map($anillo, $pol), $g[1])];
}

function areaAnillo(array $r, float $lat0): float
{
    $e2 = 0.00669437999014; $s2 = sin(deg2rad($lat0)) ** 2;
    $ky = deg2rad(1) * 6378137 * (1 - $e2) / (1 - $e2 * $s2) ** 1.5;
    $kx = deg2rad(1) * 6378137 / sqrt(1 - $e2 * $s2) * cos(deg2rad($lat0));
    $s = 0.0;
    for ($i = 0, $n = count($r); $i < $n - 1; $i++) {
        $s += $r[$i][0] * $r[$i + 1][1] - $r[$i + 1][0] * $r[$i][1];
    }
    return abs($s / 2) * $kx * $ky;
}

function centroAnillo(array $r): array
{
    $a = 0.0; $x = 0.0; $y = 0.0;
    for ($i = 0, $n = count($r); $i < $n - 1; $i++) {
        $c = $r[$i][0] * $r[$i + 1][1] - $r[$i + 1][0] * $r[$i][1];
        $a += $c; $x += ($r[$i][0] + $r[$i + 1][0]) * $c; $y += ($r[$i][1] + $r[$i + 1][1]) * $c;
    }
    return $a == 0.0 ? [$r[0][0], $r[0][1], 0.0] : [$x / (3 * $a), $y / (3 * $a), abs($a / 2)];
}

function medidas(array $geo): array
{
    $poligonos = $geo['type'] === 'Polygon' ? [$geo['coordinates']] : $geo['coordinates'];
    $lats = array_merge(...array_map(fn ($pol) => array_column($pol[0], 1), $poligonos));
    $lat0 = array_sum($lats) / count($lats);
    $area = 0.0; $sx = 0.0; $sy = 0.0; $sw = 0.0;
    foreach ($poligonos as $pol) {
        foreach ($pol as $k => $anillo) {
            $signo = $k === 0 ? 1 : -1;
            $area += $signo * areaAnillo($anillo, $lat0);
            [$cx, $cy, $w] = centroAnillo($anillo);
            $sx += $signo * $cx * $w; $sy += $signo * $cy * $w; $sw += $signo * $w;
        }
    }
    return [round($area / 10000, 2), round($sy / $sw, 7), round($sx / $sw, 7)];
}

function n7(float $v): string
{
    return number_format($v, 7, '.', '');
}

function q(string $s): string
{
    return "N'" . str_replace("'", "''", $s) . "'";
}

$L = abrir($dir . 'Lotes.gpkg');
$P = abrir($dir . 'Palmas_Maestra_migrado.gpkg');

$geos = [];
foreach ($L->query('SELECT id_lote, geometry FROM INCIDENCIAS ORDER BY fid') as $r) {
    $geo = aWgs84(gpkgDecodificar($r['geometry']));
    [$ha, $lat, $lon] = medidas($geo);
    $geos[] = '(' . q(trim($r['id_lote'])) . ', ' . q(json_encode($geo)) . ", $ha, " . n7($lat) . ', ' . n7($lon) . ')';
}

$palmas = [];
$porFid = [];
foreach ($P->query('SELECT fid, lote, linea, palma, geom FROM palmas ORDER BY lote, linea, palma') as $r) {
    [, $c] = gpkgDecodificar($r['geom']);
    [$lon, $lat] = tmInversa($c[0], $c[1]);
    $porFid[(int) $r['fid']] = [trim($r['lote']), (int) $r['linea'], (int) $r['palma']];
    $palmas[] = "('" . trim($r['lote']) . "'," . (int) $r['linea'] . ',' . (int) $r['palma'] . ',' . n7($lat) . ',' . n7($lon) . ')';
}

$obs = [];
foreach ($P->query("SELECT cod, evaluador, hallazgo, observaciones FROM Inspecciones WHERE IFNULL(hallazgo, '') <> '' OR IFNULL(observaciones, '') <> ''") as $r) {
    $k = explode('.', (string) $r['cod']);
    if (count($k) !== 3) continue;
    $texto = trim('Inspección QGIS: ' . trim((string) $r['hallazgo']) . (trim((string) $r['evaluador']) !== '' ? ' (' . trim($r['evaluador']) . ')' : '') . ' ' . trim((string) $r['observaciones']));
    $obs[] = '(' . q($k[0]) . ', ' . (int) $k[1] . ', ' . (int) $k[2] . ', ' . q(mb_substr($texto, 0, 250)) . ')';
}

$censos = [];
$estados = [];
$huerfanos = 0;
foreach ($P->query('SELECT id_palma, fecha_censo, estado FROM censos_palma ORDER BY id_palma, fecha_censo, id_censo') as $r) {
    $p = $porFid[(int) $r['id_palma']] ?? null;
    if ($p === null) { $huerfanos++; continue; }
    $estado = trim((string) $r['estado']);
    $estados[$estado] = ($estados[$estado] ?? 0) + 1;
    $censos[] = "('" . $p[0] . "'," . $p[1] . ',' . $p[2] . ",'" . substr($r['fecha_censo'], 0, 10) . "'," . q($estado) . ')';
}

$bloques = function (string $tabla, string $cols, array $filas, int $tam = 1000): string {
    $sql = '';
    foreach (array_chunk($filas, $tam) as $trozo) {
        $sql .= "INSERT INTO $tabla ($cols) VALUES\n" . implode(",\n", $trozo) . ";\n";
    }
    return $sql;
};

ksort($estados);
$resumenEstados = implode("\n", array_map(fn ($e, $n) => "     $e: $n", array_keys($estados), $estados));
$totalPalmas = count($palmas);
$totalCensos = count($censos);
$totalGeos = count($geos);
$fecha = date('Y-m-d H:i');

$sql = <<<SQL
/* ============================================================================
   Carga inicial del mapeo de palmas - Finca 002 CRUCEIRO (empresa 1)
   ----------------------------------------------------------------------------
   GENERADO AUTOMÁTICAMENTE el {$fecha} con
   Documentacion/BaseDeDatos/herramientas/generar_carga_mapeo_palmas.php
   a partir de Documentacion/Lotes.gpkg (capa INCIDENCIAS) y
   Documentacion/Palmas_Maestra_migrado.gpkg (capas palmas, censos_palma,
   Inspecciones). Coordenadas convertidas de EPSG:3116 (MAGNA-SIRGAS Bogotá)
   a WGS84 (lat/lon, 7 decimales).

   REQUISITO: haber ejecutado crear_mapeo_palmas.sql.

   QUÉ HACE
   --------
     1) Valida que los {$totalGeos} lotes de #mapLotes existen en aLotes (empresa/finca),
        que las características de #mapEstados existen en aCaracteristica y
        que todo estado del censo tiene mapeo. Si algo falla, aborta (THROW).
     2) Inserta en aLotesGeometria las {$totalGeos} geometrías que no existan.
     3) Inserta en aPalma las {$totalPalmas} palmas que no existan por
        (empresa, finca, lote, linea, palma), estado ACTIVA.
     4) Inserta en aCensoPalma los {$totalCensos} censos que no existan por
        (palma, fecha, caracteristica), con observación "QGIS: <estado>".
   Usuario de registro: MIGRACION. No modifica filas existentes (idempotente).

   ESTADOS DEL CENSO EN EL ORIGEN
{$resumenEstados}

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resumen y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i cargar_mapeo_palmas_cruceiro.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT         = 0;
DECLARE @Empresa INT         = 1;
DECLARE @Finca   VARCHAR(50) = '002';
DECLARE @Usuario VARCHAR(50) = 'MIGRACION';

IF OBJECT_ID('tempdb..#mapEstados') IS NOT NULL DROP TABLE #mapEstados;
IF OBJECT_ID('tempdb..#mapLotes') IS NOT NULL DROP TABLE #mapLotes;
IF OBJECT_ID('tempdb..#geo') IS NOT NULL DROP TABLE #geo;
IF OBJECT_ID('tempdb..#palmas') IS NOT NULL DROP TABLE #palmas;
IF OBJECT_ID('tempdb..#obs') IS NOT NULL DROP TABLE #obs;
IF OBJECT_ID('tempdb..#censos') IS NOT NULL DROP TABLE #censos;

/* ----------------------------------------------------------------------------
   MAPEO ESTADO DEL CENSO (QGIS) -> (grupoCaracteristica, caracteristica)
   *** REVISAR / EDITAR ANTES DE APLICAR ***
     DE ALTA             -> 0 / 92  DE ALTA
     EMISIÓN ENFERMA     -> 0 / 93  EMISIÓN ENFERMA
     EMISIÓN SANA        -> 0 / 94  EMISIÓN SANA
     GRADO 1/2/3         -> 0 / 95, 96, 97
     CRATER PC           -> 0 / 98  CRATER
     DOBLAMIENTO COGOLLO -> 3 / 25  COGOLLO DOBLADO (tratamiento)    POR CONFIRMAR
     RAYO                -> 3 / 56  AFECTADA POR RAYO (tratamiento)  POR CONFIRMAR
     ERRADICAR           -> 2 / 44  REINCIDENCIA (erradicación)      POR CONFIRMAR
   ---------------------------------------------------------------------------- */
CREATE TABLE #mapEstados (estado NVARCHAR(50) PRIMARY KEY, grupo INT NOT NULL, caracteristica INT NOT NULL);
INSERT INTO #mapEstados (estado, grupo, caracteristica) VALUES
    (N'DE ALTA',             0, 92),
    (N'EMISIÓN ENFERMA',     0, 93),
    (N'EMISIÓN SANA',        0, 94),
    (N'GRADO 1',             0, 95),
    (N'GRADO 2',             0, 96),
    (N'GRADO 3',             0, 97),
    (N'CRATER PC',           0, 98),
    (N'DOBLAMIENTO COGOLLO', 3, 25),
    (N'RAYO',                3, 56),
    (N'ERRADICAR',           2, 44);

/* MAPEO id_lote (QGIS) -> aLotes.codigo */
CREATE TABLE #mapLotes (loteGpkg NVARCHAR(10) PRIMARY KEY, lote VARCHAR(50) NOT NULL);
INSERT INTO #mapLotes (loteGpkg, lote) VALUES
    (N'1', 'CRL1-14'), (N'2', 'CRL2-14'), (N'3', 'CRL3-14'), (N'4', 'CRL4-14'), (N'5', 'CRL5-14'),
    (N'6A', 'CRL6A-14'), (N'6B', 'CRL6B-14'), (N'7', 'CRL7-14'), (N'8A', 'CRL8A-14'), (N'8B', 'CRL8B-14'),
    (N'9', 'CRL9-14'), (N'10', 'CRL10-14'), (N'11', 'CRL11-14'), (N'12', 'CRL12-14'), (N'13', 'CRL13-14'),
    (N'14A', 'CRL14A-21'), (N'14B', 'CRL 14B-21');

IF OBJECT_ID('dbo.aPalma', 'U') IS NULL OR OBJECT_ID('dbo.aCensoPalma', 'U') IS NULL OR OBJECT_ID('dbo.aLotesGeometria', 'U') IS NULL
    THROW 50001, N'Ejecute primero crear_mapeo_palmas.sql.', 1;

IF EXISTS (SELECT 1 FROM #mapLotes m WHERE NOT EXISTS (SELECT 1 FROM aLotes l WHERE l.empresa = @Empresa AND l.finca = @Finca AND l.codigo = m.lote))
BEGIN
    SELECT m.loteGpkg, m.lote AS loteNoEncontrado FROM #mapLotes m
     WHERE NOT EXISTS (SELECT 1 FROM aLotes l WHERE l.empresa = @Empresa AND l.finca = @Finca AND l.codigo = m.lote);
    THROW 50002, N'Hay lotes del mapeo que no existen en aLotes para la empresa/finca indicada.', 1;
END;

IF EXISTS (SELECT 1 FROM #mapEstados m WHERE NOT EXISTS (SELECT 1 FROM aCaracteristica c WHERE c.empresa = @Empresa AND c.codigo = m.caracteristica AND c.grupoCaracteristica = m.grupo))
BEGIN
    SELECT m.* FROM #mapEstados m
     WHERE NOT EXISTS (SELECT 1 FROM aCaracteristica c WHERE c.empresa = @Empresa AND c.codigo = m.caracteristica AND c.grupoCaracteristica = m.grupo);
    THROW 50003, N'Hay estados mapeados a una característica/grupo inexistente en aCaracteristica.', 1;
END;

CREATE TABLE #geo (loteGpkg NVARCHAR(10) NOT NULL, geojson NVARCHAR(MAX) NOT NULL, areaHa DECIMAL(10, 2) NOT NULL, centroLat DECIMAL(10, 7) NOT NULL, centroLon DECIMAL(10, 7) NOT NULL);
CREATE TABLE #palmas (loteGpkg NVARCHAR(10) NOT NULL, linea INT NOT NULL, palma INT NOT NULL, latitud DECIMAL(10, 7) NOT NULL, longitud DECIMAL(10, 7) NOT NULL);
CREATE TABLE #obs (loteGpkg NVARCHAR(10) NOT NULL, linea INT NOT NULL, palma INT NOT NULL, observacion NVARCHAR(250) NOT NULL);
CREATE TABLE #censos (loteGpkg NVARCHAR(10) NOT NULL, linea INT NOT NULL, palma INT NOT NULL, fecha DATE NOT NULL, estado NVARCHAR(50) NOT NULL);

/* ---- Geometrías de lotes ({$totalGeos}) ---- */

SQL;

$sql .= $bloques('#geo', 'loteGpkg, geojson, areaHa, centroLat, centroLon', $geos);
$sql .= "\n/* ---- Palmas ({$totalPalmas}) ---- */\n";
$sql .= $bloques('#palmas', 'loteGpkg, linea, palma, latitud, longitud', $palmas);
$sql .= "\n/* ---- Observaciones de inspección (" . count($obs) . ") ---- */\n";
$sql .= $obs === [] ? '' : $bloques('#obs', 'loteGpkg, linea, palma, observacion', $obs);
$sql .= "\n/* ---- Censos ({$totalCensos}) ---- */\n";
$sql .= $bloques('#censos', 'loteGpkg, linea, palma, fecha, estado', $censos);

$sql .= <<<'SQL'

IF EXISTS (SELECT 1 FROM #censos c WHERE NOT EXISTS (SELECT 1 FROM #mapEstados m WHERE m.estado = c.estado))
BEGIN
    SELECT DISTINCT c.estado AS estadoSinMapeo FROM #censos c WHERE NOT EXISTS (SELECT 1 FROM #mapEstados m WHERE m.estado = c.estado);
    THROW 50004, N'Hay estados del censo sin mapeo en #mapEstados.', 1;
END;

IF EXISTS (SELECT 1 FROM #palmas p WHERE NOT EXISTS (SELECT 1 FROM #mapLotes m WHERE m.loteGpkg = p.loteGpkg))
    THROW 50005, N'Hay palmas de un lote QGIS sin mapeo en #mapLotes.', 1;

BEGIN TRY

    BEGIN TRAN;

    INSERT INTO dbo.aLotesGeometria (empresa, finca, lote, geojson, areaHa, centroLat, centroLon, usuario)
    SELECT @Empresa, @Finca, m.lote, g.geojson, g.areaHa, g.centroLat, g.centroLon, @Usuario
      FROM #geo g
      JOIN #mapLotes m ON m.loteGpkg = g.loteGpkg
     WHERE NOT EXISTS (SELECT 1 FROM dbo.aLotesGeometria x WHERE x.empresa = @Empresa AND x.finca = @Finca AND x.lote = m.lote);

    PRINT CONCAT(N'Geometrías insertadas: ', @@ROWCOUNT);

    INSERT INTO dbo.aPalma (empresa, finca, lote, linea, palma, latitud, longitud, estado, observacion, usuarioRegistro)
    SELECT @Empresa, @Finca, m.lote, p.linea, p.palma, p.latitud, p.longitud, 'ACTIVA', o.observacion, @Usuario
      FROM #palmas p
      JOIN #mapLotes m ON m.loteGpkg = p.loteGpkg
      LEFT JOIN #obs o ON o.loteGpkg = p.loteGpkg AND o.linea = p.linea AND o.palma = p.palma
     WHERE NOT EXISTS (SELECT 1 FROM dbo.aPalma x
                        WHERE x.empresa = @Empresa AND x.finca = @Finca AND x.lote = m.lote AND x.linea = p.linea AND x.palma = p.palma);

    PRINT CONCAT(N'Palmas insertadas: ', @@ROWCOUNT);

    INSERT INTO dbo.aCensoPalma (empresa, palma, fecha, grupoCaracteristica, caracteristica, observacion, usuarioRegistro)
    SELECT DISTINCT @Empresa, a.id, c.fecha, e.grupo, e.caracteristica, LEFT(CONCAT(N'QGIS: ', c.estado), 250), @Usuario
      FROM #censos c
      JOIN #mapLotes m ON m.loteGpkg = c.loteGpkg
      JOIN #mapEstados e ON e.estado = c.estado
      JOIN dbo.aPalma a ON a.empresa = @Empresa AND a.finca = @Finca AND a.lote = m.lote AND a.linea = c.linea AND a.palma = c.palma
     WHERE NOT EXISTS (SELECT 1 FROM dbo.aCensoPalma x WHERE x.palma = a.id AND x.fecha = c.fecha AND x.caracteristica = e.caracteristica);

    PRINT CONCAT(N'Censos insertados: ', @@ROWCOUNT);

    SELECT l.codigo AS lote, l.palmasBrutas,
           (SELECT COUNT(*) FROM dbo.aPalma p WHERE p.empresa = l.empresa AND p.finca = l.finca AND p.lote = l.codigo) AS palmasMapeadas,
           (SELECT COUNT(*) FROM dbo.aCensoPalma c JOIN dbo.aPalma p ON p.id = c.palma
             WHERE p.empresa = l.empresa AND p.finca = l.finca AND p.lote = l.codigo) AS censos,
           g.areaHa, l.hNetas
      FROM aLotes l
      LEFT JOIN dbo.aLotesGeometria g ON g.empresa = l.empresa AND g.finca = l.finca AND g.lote = l.codigo
     WHERE l.empresa = @Empresa AND l.finca = @Finca AND l.codigo IN (SELECT lote FROM #mapLotes)
     ORDER BY l.codigo;

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'Carga confirmada (COMMIT).';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: se revirtió todo. Cambie @Aplicar = 1 para confirmar.';
    END;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0 ROLLBACK;

    THROW;

END CATCH;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL de esta carga (solo filas con usuario MIGRACION de la finca).
   Descomentar y ejecutar.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;
BEGIN TRAN;
DELETE c FROM dbo.aCensoPalma c JOIN dbo.aPalma p ON p.id = c.palma
 WHERE p.empresa = 1 AND p.finca = '002' AND c.usuarioRegistro = 'MIGRACION';
DELETE FROM dbo.aPalma WHERE empresa = 1 AND finca = '002' AND usuarioRegistro = 'MIGRACION';
DELETE FROM dbo.aLotesGeometria WHERE empresa = 1 AND finca = '002' AND usuario = 'MIGRACION';
COMMIT;
---------------------------------------------------------------------------- */

SQL;

file_put_contents($salida, "\xEF\xBB\xBF" . str_replace("\n", "\r\n", $sql));
echo "Archivo: $salida\n";
echo 'Tamaño: ' . round(filesize($salida) / 1024 / 1024, 2) . " MB\n";
echo "Geometrías: $totalGeos | Palmas: $totalPalmas | Observaciones: " . count($obs) . " | Censos: $totalCensos | Censos huérfanos: $huerfanos\n";
foreach ($estados as $e => $n) echo "  $e: $n\n";
