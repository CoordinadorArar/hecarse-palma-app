<?php

$raiz    = 'E:/xampp/htdocs/AppPalma/Documentacion/';
$archivo = $argv[1] ?? $raiz . 'DATOS PRODUCCION 2018 - 2026.xlsx';
$salida  = $argv[2] ?? $raiz . 'BaseDeDatos/cargar_produccion_historica.sql';
$empresa = 1;
$origen  = 'EXCEL 2018-2026';
$fincas  = ['CRUCEIRO' => '002', 'HATO' => '001', 'CAÑAGUATE' => '003'];
$prefijo = ['002' => 'CRL', '001' => 'HTL', '003' => 'CGL'];
$excepc  = ['002|14B' => 'CRL 14B-21'];

if (! is_file($archivo)) {
    fwrite(STDERR, "No existe $archivo\n");
    exit(1);
}

$zip = new ZipArchive();

if ($zip->open($archivo, ZipArchive::RDONLY) !== true) {
    fwrite(STDERR, "No se pudo abrir $archivo\n");
    exit(1);
}

$compartidos = [];

if (($x = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
    $sx = simplexml_load_string($x);
    $sx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    foreach ($sx->xpath('//m:si') as $si) {
        $si->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $compartidos[] = implode('', array_map('strval', $si->xpath('.//m:t')));
    }
}

function hoja(ZipArchive $zip, array $compartidos, string $nombre): array
{
    $libro = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $rels  = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $rutas = [];
    foreach ($rels->Relationship as $r) {
        $rutas[(string) $r['Id']] = ltrim((string) $r['Target'], '/');
    }
    foreach ($libro->sheets->sheet as $s) {
        if ((string) $s['name'] !== $nombre) continue;
        $ruta  = $rutas[(string) $s->attributes('r', true)['id']];
        $ruta  = str_starts_with($ruta, 'xl/') ? $ruta : 'xl/' . $ruta;
        $filas = [];
        foreach (simplexml_load_string($zip->getFromName($ruta))->sheetData->row as $fila) {
            foreach ($fila->c as $c) {
                $v = (string) $c->v;
                if ((string) $c['t'] === 's') $v = $compartidos[(int) $v];
                elseif ((string) $c['t'] === 'inlineStr') $v = (string) $c->is->t;
                preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m);
                $filas[(int) $m[2]][$m[1]] = $v;
            }
        }
        return $filas;
    }
    fwrite(STDERR, "No existe la hoja \"$nombre\"\n");
    exit(1);
}

$base   = hoja($zip, $compartidos, 'DATOS COSECHA 2018 A 2024');
$pivote = hoja($zip, $compartidos, 'Total Hecarse');
$histo  = hoja($zip, $compartidos, 'Historico de produccion');
$zip->close();

$enc = array_map(fn ($v) => mb_strtoupper(trim((string) $v)), $base[1] ?? []);
$col = [];
foreach (['AÑO', 'MES', 'FINCA', 'LOTE', 'PALMAS', 'HECTAREA', 'SIEMBRA', 'VARIEDAD', 'PRODUCCION KG'] as $n) {
    $c = array_search($n, $enc, true);
    if ($c === false) {
        fwrite(STDERR, "No se encontró la columna $n en la hoja base\n");
        exit(1);
    }
    $col[$n] = $c;
}
unset($base[1]);

$excluir = array_combine(range(4734, 4765), range(4793, 4824));

foreach ($excluir as $f => $marzo) {
    $x = $base[$f] ?? [];
    $y = $base[$marzo] ?? [];
    $t = fn (array $c, string $n) => trim((string) ($c[$col[$n]] ?? ''));
    $iguales = $t($x, 'AÑO') === '2024' && $t($x, 'MES') === '2' && mb_strtoupper($t($x, 'FINCA')) === 'HATO' && $t($y, 'AÑO') === '2024' && $t($y, 'MES') === '3';
    foreach (['FINCA', 'LOTE', 'PALMAS', 'HECTAREA', 'SIEMBRA', 'VARIEDAD', 'PRODUCCION KG'] as $n) {
        $iguales = $iguales && $t($x, $n) === $t($y, $n);
    }
    if (! $iguales) {
        fwrite(STDERR, "La fila $f ya no es la copia de marzo 2024 (fila $marzo): revise la lista de exclusión de HATO 2024-02\n");
        exit(1);
    }
}

$grupos    = [];
$vacias    = 0;
$siembras  = [];
$excluidas = ['filas' => 0, 'kilos' => 0.0];

foreach ($base as $f => $c) {
    $v     = fn (string $n) => trim((string) ($c[$col[$n]] ?? ''));
    if (isset($excluir[$f])) {
        $excluidas['filas']++;
        $excluidas['kilos'] += (float) $v('PRODUCCION KG');
        $excluidas['mes'][$fincas[mb_strtoupper($v('FINCA'))]][(int) $v('AÑO')][(int) $v('MES')] = ($excluidas['mes'][$fincas[mb_strtoupper($v('FINCA'))]][(int) $v('AÑO')][(int) $v('MES')] ?? 0) + (float) $v('PRODUCCION KG');
        continue;
    }
    $nomF  = mb_strtoupper($v('FINCA'));
    $kilos = $v('PRODUCCION KG');
    if ($nomF === '' && $kilos === '') continue;
    if (! isset($fincas[$nomF])) {
        fwrite(STDERR, "Finca sin mapeo: $nomF (fila $f)\n");
        exit(1);
    }
    $anio = (int) $v('AÑO');
    $mes  = (int) $v('MES');
    $lote = mb_strtoupper($v('LOTE'));
    if ($anio < 2000 || $mes < 1 || $mes > 12 || $lote === '') {
        fwrite(STDERR, "Fila $f con año/mes/lote inválido\n");
        exit(1);
    }
    if (! is_numeric($kilos)) {
        $vacias++;
        continue;
    }
    $cod = $fincas[$nomF];
    if ($v('SIEMBRA') !== '') $siembras["$cod|$lote"][(int) $v('SIEMBRA')] = true;
    $fila = [
        'fila' => $f, 'anio' => $anio, 'mes' => $mes, 'finca' => $cod, 'lote' => $lote, 'kilos' => (float) $kilos,
        'palmas' => is_numeric($v('PALMAS')) ? (int) round((float) $v('PALMAS')) : null,
        'hectareas' => is_numeric($v('HECTAREA')) ? (float) $v('HECTAREA') : null,
        'siembra' => is_numeric($v('SIEMBRA')) ? (int) $v('SIEMBRA') : null,
        'variedad' => $v('VARIEDAD') === '' ? null : mb_substr(preg_replace('/\s+/', ' ', $v('VARIEDAD')), 0, 100),
    ];
    $k = "$anio|$mes|$cod|$lote";
    if (! isset($grupos[$k])) {
        $grupos[$k] = $fila + ['n' => 1, 'max' => $fila['kilos']];
        continue;
    }
    $g = $grupos[$k];
    $grupos[$k] = $fila['kilos'] > $g['max'] ? $fila : $g;
    $grupos[$k]['n']     = $g['n'] + 1;
    $grupos[$k]['max']   = max($g['max'], $fila['kilos']);
    $grupos[$k]['kilos'] = $g['kilos'] + $fila['kilos'];
    $grupos[$k]['fila']  = $g['fila'];
    $grupos[$k]['filas'] = ($g['filas'] ?? [$g['fila']]) + [$g['n'] => $f];
}

$duplicados = array_filter($grupos, fn ($g) => $g['n'] > 1);
$mapa       = [];

foreach ($grupos as $g) {
    $k = $g['finca'] . '|' . $g['lote'];
    if (isset($mapa[$k])) continue;
    $s = array_keys($siembras[$k] ?? []);
    if (count($s) !== 1 && ! isset($excepc[$k])) {
        fwrite(STDERR, "El lote {$g['lote']} de la finca {$g['finca']} no tiene un único año de siembra en el Excel\n");
        exit(1);
    }
    $mapa[$k] = $excepc[$k] ?? $prefijo[$g['finca']] . $g['lote'] . '-' . substr((string) $s[0], -2);
}

$q  = fn (?string $s) => $s === null ? 'NULL' : "N'" . str_replace("'", "''", $s) . "'";
$nn = fn ($v, int $d = 0) => $v === null ? 'NULL' : number_format((float) $v, $d, '.', '');

$filasMapa = [];
foreach ($mapa as $k => $l) {
    [$cod, $lote] = explode('|', $k);
    $filasMapa[] = "    ('$cod', " . $q($lote) . ', ' . $q($l) . ')';
}

uasort($grupos, fn ($a, $b) => [$a['finca'], $a['anio'], $a['mes'], $a['lote']] <=> [$b['finca'], $b['anio'], $b['mes'], $b['lote']]);

$datos   = [];
$resumen = [];
foreach ($grupos as $g) {
    $datos[] = "({$g['anio']},{$g['mes']},'{$g['finca']}'," . $q($g['lote']) . ',' . $nn($g['palmas']) . ',' . $nn($g['hectareas'], 4) . ','
        . $nn($g['siembra']) . ',' . $q($g['variedad']) . ',' . $nn($g['kilos'], 2) . ')';
    $resumen[$g['finca']][$g['anio']]['filas'] = ($resumen[$g['finca']][$g['anio']]['filas'] ?? 0) + 1;
    $resumen[$g['finca']][$g['anio']]['kilos'] = ($resumen[$g['finca']][$g['anio']]['kilos'] ?? 0) + round($g['kilos'], 2);
    $resumen[$g['finca']][$g['anio']]['meses'][$g['mes']] = ($resumen[$g['finca']][$g['anio']]['meses'][$g['mes']] ?? 0) + round($g['kilos'], 2);
}

$esperado = [];
$actual   = null;
foreach ($pivote as $c) {
    $a = trim((string) ($c['A'] ?? ''));
    if ($a === 'FINCA') {
        $b = mb_strtoupper(trim((string) ($c['B'] ?? '')));
        $actual = $b === '(TODAS)' ? '' : ($fincas[$b] ?? null);
        continue;
    }
    if ($actual === null || preg_match('/^\d{4}$/', $a) !== 1) continue;
    $meses = [];
    foreach (range('B', 'M') as $i => $l) {
        if (is_numeric($c[$l] ?? '')) $meses[$i + 1] = (float) $c[$l];
    }
    $esperado[$actual][(int) $a] = ['total' => (float) ($c['N'] ?? 0), 'meses' => $meses];
}

foreach ($excluidas['mes'] ?? [] as $cod => $anios) {
    foreach ($anios as $anio => $meses) {
        foreach ($meses as $m => $kg) {
            foreach ([$cod, ''] as $k) {
                if (! isset($esperado[$k][$anio])) continue;
                $esperado[$k][$anio]['total'] -= $kg;
                if (isset($esperado[$k][$anio]['meses'][$m])) $esperado[$k][$anio]['meses'][$m] -= $kg;
            }
        }
    }
}

$f2   = fn (float $v) => number_format($v, 2, '.', '');
$f2m  = fn (float $v) => number_format($v, 2, ',', '.');
$nomF = array_flip($fincas);
$lineas = [];
$difs   = [];
$tot    = [];
$filasEsp = [];

foreach ($resumen as $cod => $anios) {
    ksort($anios);
    foreach ($anios as $anio => $r) {
        $e = $esperado[$cod][$anio] ?? null;
        $tot[$anio] = ($tot[$anio] ?? 0) + $r['kilos'];
        $lineas[] = sprintf('     %s %-9s %d: %5d filas %16s kg   Total Hecarse aj. %16s', $cod, $nomF[$cod], $anio, $r['filas'], $f2m($r['kilos']), $e === null ? 'sin dato' : $f2m($e['total']));
        if ($e === null || abs($e['total'] - $r['kilos']) > 0.01) $difs[] = "$cod $anio total: carga " . $f2($r['kilos']) . ' vs pivote ' . ($e === null ? 'sin dato' : $f2($e['total']));
        foreach ($e['meses'] ?? [] as $m => $kg) {
            if (abs($kg - ($r['meses'][$m] ?? 0)) > 0.01) $difs[] = "$cod $anio mes $m: carga " . $f2($r['meses'][$m] ?? 0) . ' vs pivote ' . $f2($kg);
        }
        if ($e !== null) $filasEsp[] = "    ('$cod', $anio, " . $f2($e['total']) . ')';
    }
}

ksort($tot);
foreach ($tot as $anio => $kg) {
    $e = $esperado[''][$anio]['total'] ?? null;
    $lineas[] = sprintf('     EMPRESA       %d: %22s kg   Total Hecarse aj. %16s', $anio, $f2m($kg), $e === null ? 'sin dato' : $f2m($e));
    if ($e === null || abs($e - $kg) > 0.01) $difs[] = "Empresa $anio: carga " . $f2($kg) . ' vs pivote ' . ($e === null ? 'sin dato' : $f2($e));
}

$difHist = [];
foreach ($histo as $c) {
    $a = trim((string) ($c['A'] ?? ''));
    if (preg_match('/^\d{4}$/', $a) !== 1 || ! isset($tot[(int) $a])) continue;
    $s = 0.0;
    foreach (range('B', 'M') as $l) $s += (float) ($c[$l] ?? 0);
    if (abs($s - $tot[(int) $a]) > 0.01) $difHist[] = "     $a: hoja \"Historico de produccion\" " . $f2m($s) . ' kg vs hoja base ' . $f2m($tot[(int) $a]) . ' kg';
}

$detDup = [];
foreach ($duplicados as $g) {
    $detDup[] = "     {$g['finca']} {$g['anio']}-{$g['mes']} lote {$g['lote']}: {$g['n']} filas sumadas (filas de la hoja " . implode(', ', $g['filas']) . ") = " . $f2m($g['kilos']) . ' kg';
}

$totalFilas = count($datos);
$totalDup   = count($duplicados);
$totalKg    = $f2m(array_sum($tot));
$fecha      = date('Y-m-d H:i');
$txtRes     = implode("\n", $lineas);
$txtExc     = "{$excluidas['filas']} filas de la hoja base (" . min(array_keys($excluir)) . "-" . max(array_keys($excluir)) . "), " . $f2m($excluidas['kilos']) . " kg, HATO 2024-02.
     Motivo: son la segunda fila de cada lote en HATO febrero 2024 y copian exactamente
     las filas de marzo 2024 (" . min($excluir) . "-" . max($excluir) . "); lo corrobora la hoja \"Historico de produccion\",
     febrero 2024 = 1.040.960 kg (Total Hecarse sin la copia). La herramienta aborta si esas
     filas dejan de coincidir con marzo (por ejemplo, si se corrige el Excel).";
$txtDup     = $detDup === [] ? '     (ninguno)' : implode("\n", $detDup);
$txtDif     = $difs === [] ? '     Sin diferencias: la carga cuadra con la tabla dinámica "Total Hecarse" menos las filas excluidas (total y mes a mes).' : '     ' . implode("\n     ", $difs);
$txtHist    = $difHist === [] ? '     (sin diferencias)' : implode("\n", $difHist);
$sqlMapa    = implode(",\n", $filasMapa);
$sqlEsp     = implode(",\n", $filasEsp);
$sqlDatos   = '';
foreach (array_chunk($datos, 1000) as $trozo) {
    $sqlDatos .= "INSERT INTO #datos (anio, mes, finca, loteExcel, palmas, hectareas, siembra, variedad, kilos) VALUES\n" . implode(",\n", $trozo) . ";\n";
}

$sql = <<<SQL
/* ============================================================================
   Carga del histórico de producción (dbo.aProduccionHistorica)
   ----------------------------------------------------------------------------
   GENERADO AUTOMÁTICAMENTE el {$fecha} con
   Documentacion/BaseDeDatos/herramientas/generar_carga_produccion_historica.php
   a partir de Documentacion/DATOS PRODUCCION 2018 - 2026.xlsx,
   hoja "DATOS COSECHA 2018 A 2024" (columnas AÑO, MES, FINCA, LOTE, PALMAS,
   HECTAREA, SIEMBRA, VARIEDAD, PRODUCCION KG).

   REQUISITO: haber ejecutado crear_produccion_historica.sql.

   CONTENIDO
     Filas a cargar: {$totalFilas} (empresa {$empresa}, origen '{$origen}'), {$totalKg} kg.
     Filas de la hoja sin PRODUCCION KG (omitidas): {$vacias}.
     Grupos duplicados empresa/año/mes/finca/lote: {$totalDup} (se suman los kilos y
     palmas/hectáreas/siembra/variedad se toman de la fila con más kilos):
{$txtDup}

   FILAS EXCLUIDAS: {$txtExc}

   RESUMEN POR FINCA Y AÑO ("Total Hecarse aj." = tabla dinámica "Total Hecarse" menos las filas excluidas)
{$txtRes}

   VERIFICACIÓN CONTRA LA TABLA DINÁMICA "Total Hecarse"
{$txtDif}

   DIFERENCIAS DE LA HOJA "Historico de produccion" (valores digitados) vs base
{$txtHist}

   MAPEO DE FINCAS: CRUCEIRO = 002, HATO = 001, CAÑAGUATE = 003.
   MAPEO DE LOTES (@mapa, editable): mismo criterio de cargar_mapeo_palmas_cruceiro.sql
     prefijo de finca (CRL / HTL / CGL) + lote del Excel + '-' + 2 dígitos del año
     de siembra; excepción CRUCEIRO 14B = 'CRL 14B-21'. El script valida que todos
     los lotes de @mapa existan en aLotes para la finca y aborta listando los faltantes.

   QUÉ HACE
   --------
     1) Valida tabla, fincas y lotes del mapeo.
     2) Borra las filas de dbo.aProduccionHistorica de la empresa con origen
        '{$origen}' e inserta de nuevo todas las filas (idempotente).
     3) Muestra el resumen por finca/año (filas y kg) y la diferencia contra
        el total de la tabla dinámica "Total Hecarse".

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resumen y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i cargar_produccion_historica.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT         = 0;
DECLARE @Empresa INT         = {$empresa};
DECLARE @Origen  VARCHAR(50) = '{$origen}';
DECLARE @Usuario VARCHAR(50) = 'MIGRACION';

IF OBJECT_ID('dbo.aProduccionHistorica', 'U') IS NULL
    THROW 50001, N'Ejecute primero crear_produccion_historica.sql.', 1;

DECLARE @mapa TABLE (finca VARCHAR(50) NOT NULL, loteExcel NVARCHAR(50) NOT NULL, loteSistema VARCHAR(50) NOT NULL, PRIMARY KEY (finca, loteExcel));

INSERT INTO @mapa (finca, loteExcel, loteSistema) VALUES
{$sqlMapa};

DECLARE @esperado TABLE (finca VARCHAR(50) NOT NULL, anio INT NOT NULL, kilos DECIMAL(18, 2) NOT NULL, PRIMARY KEY (finca, anio));

INSERT INTO @esperado (finca, anio, kilos) VALUES
{$sqlEsp};

IF EXISTS (SELECT 1 FROM (SELECT DISTINCT finca FROM @mapa) m WHERE NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = @Empresa AND LTRIM(RTRIM(f.codigo)) = m.finca))
BEGIN
    SELECT DISTINCT m.finca AS fincaNoEncontrada FROM @mapa m
     WHERE NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = @Empresa AND LTRIM(RTRIM(f.codigo)) = m.finca);
    THROW 50002, N'Hay fincas de la carga que no existen en aFinca para la empresa.', 1;
END;

IF EXISTS (SELECT 1 FROM @mapa m WHERE NOT EXISTS (SELECT 1 FROM aLotes l WHERE l.empresa = @Empresa AND LTRIM(RTRIM(l.finca)) = m.finca AND LTRIM(RTRIM(l.codigo)) = m.loteSistema))
BEGIN
    SELECT m.finca, m.loteExcel, m.loteSistema AS loteNoEncontrado FROM @mapa m
     WHERE NOT EXISTS (SELECT 1 FROM aLotes l WHERE l.empresa = @Empresa AND LTRIM(RTRIM(l.finca)) = m.finca AND LTRIM(RTRIM(l.codigo)) = m.loteSistema)
     ORDER BY m.finca, m.loteSistema;
    THROW 50003, N'Hay lotes del mapeo que no existen en aLotes para la empresa/finca indicada. Corrija @mapa.', 1;
END;

IF EXISTS (SELECT finca, loteSistema FROM @mapa GROUP BY finca, loteSistema HAVING COUNT(*) > 1)
    THROW 50004, N'Hay dos lotes del Excel mapeados al mismo lote del sistema en @mapa.', 1;

IF OBJECT_ID('tempdb..#datos') IS NOT NULL DROP TABLE #datos;

CREATE TABLE #datos (
    anio INT NOT NULL, mes TINYINT NOT NULL, finca VARCHAR(50) NOT NULL, loteExcel NVARCHAR(50) NOT NULL,
    palmas INT NULL, hectareas DECIMAL(18, 4) NULL, siembra INT NULL, variedad VARCHAR(100) NULL, kilos DECIMAL(18, 2) NOT NULL
);

{$sqlDatos}
IF EXISTS (SELECT 1 FROM #datos d WHERE NOT EXISTS (SELECT 1 FROM @mapa m WHERE m.finca = d.finca AND m.loteExcel = d.loteExcel))
    THROW 50005, N'Hay filas con un lote del Excel sin mapeo en @mapa.', 1;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM dbo.aProduccionHistorica WHERE empresa = @Empresa AND origen = @Origen;

    PRINT CONCAT(N'Filas anteriores eliminadas: ', @@ROWCOUNT);

    INSERT INTO dbo.aProduccionHistorica (empresa, anio, mes, finca, lote, palmas, hectareas, siembra, variedad, kilos, origen, usuario)
    SELECT @Empresa, d.anio, d.mes, d.finca, m.loteSistema, d.palmas, d.hectareas, d.siembra, d.variedad, d.kilos, @Origen, @Usuario
      FROM #datos d
      JOIN @mapa m ON m.finca = d.finca AND m.loteExcel = d.loteExcel;

    PRINT CONCAT(N'Filas insertadas: ', @@ROWCOUNT);

    SELECT h.finca, h.anio, COUNT(*) AS filas, SUM(h.kilos) AS kilos, e.kilos AS totalHecarse, SUM(h.kilos) - e.kilos AS diferencia
      FROM dbo.aProduccionHistorica h
      LEFT JOIN @esperado e ON e.finca = h.finca AND e.anio = h.anio
     WHERE h.empresa = @Empresa AND h.origen = @Origen
     GROUP BY h.finca, h.anio, e.kilos
     ORDER BY h.finca, h.anio;

    SELECT h.anio, COUNT(*) AS filas, SUM(h.kilos) AS kilos
      FROM dbo.aProduccionHistorica h
     WHERE h.empresa = @Empresa AND h.origen = @Origen
     GROUP BY h.anio
     ORDER BY h.anio;

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

DROP TABLE #datos;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL de esta carga. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
DELETE FROM dbo.aProduccionHistorica WHERE empresa = {$empresa} AND origen = '{$origen}';
---------------------------------------------------------------------------- */

SQL;

file_put_contents($salida, "\xEF\xBB\xBF" . str_replace("\n", "\r\n", $sql));
echo "Archivo: $salida\n";
echo "Filas: $totalFilas, omitidas sin kilos: $vacias, grupos duplicados: $totalDup\n";
echo $txtRes . "\n";
echo "Verificación Total Hecarse:\n" . $txtDif . "\n";
echo "Historico de produccion vs base:\n" . $txtHist . "\n";
echo "Mapa de lotes:\n" . $sqlMapa . "\n";
