<?php

$raiz    = 'E:/xampp/htdocs/AppPalma/Documentacion/';
$archivo = $argv[1] ?? $raiz . 'DATOS PRODUCCION 2018 - 2026.xlsx';
$salida  = $argv[2] ?? $raiz . 'BaseDeDatos/cargar_presupuesto_lotes_2026.sql';
$anio    = 2026;
$empresa = 1;
$fincas  = ['CRUCEIRO' => '002', 'HATO' => '001', 'CAÑAGUATE' => '003'];
$ns      = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

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
    $sx->registerXPathNamespace('m', $ns);
    foreach ($sx->xpath('//m:si') as $si) {
        $si->registerXPathNamespace('m', $ns);
        $compartidos[] = implode('', array_map('strval', $si->xpath('.//m:t')));
    }
}

$libro = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
$rels  = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
$rutas = [];

foreach ($rels->Relationship as $r) {
    $rutas[(string) $r['Id']] = ltrim((string) $r['Target'], '/');
}

$cumpl = null;

foreach ($libro->sheets->sheet as $s) {
    if ((string) $s['name'] !== 'Cumplimiento al ppto') continue;
    $ruta  = $rutas[(string) $s->attributes('r', true)['id']];
    $ruta  = str_starts_with($ruta, 'xl/') ? $ruta : 'xl/' . $ruta;
    $cumpl = [];
    foreach (simplexml_load_string($zip->getFromName($ruta))->sheetData->row as $fila) {
        foreach ($fila->c as $c) {
            $v = (string) $c->v;
            if ((string) $c['t'] === 's') $v = $compartidos[(int) $v];
            $cumpl[(string) $c['r']] = $v;
        }
    }
}

$zip->close();

if ($cumpl === null) {
    fwrite(STDERR, "No existe la hoja \"Cumplimiento al ppto\"\n");
    exit(1);
}

$columnas = range('B', 'M');
$titulo   = '';
$datos    = [];

for ($f = 1; $f <= 200; $f++) {
    $a = trim((string) ($cumpl["A$f"] ?? ''));
    if (stripos($a, 'Cumplimiento al ppto') === 0) $titulo = mb_strtoupper(trim(mb_substr($a, 20)));
    if ($a !== "Ppto $anio") continue;
    $meses = array_map(fn ($c) => (float) ($cumpl[$c . $f] ?? 0), $columnas);
    if ($titulo === '') {
        $datos['empresa'] = ['fila' => $f, 'meses' => $meses];
        continue;
    }
    $codigo = null;
    foreach ($fincas as $nombre => $cod) {
        if (str_contains($titulo, $nombre)) $codigo = $cod;
    }
    if ($codigo === null) {
        fwrite(STDERR, "Finca sin mapeo: $titulo (fila $f)\n");
        exit(1);
    }
    $datos[$codigo] = ['nombre' => $titulo, 'fila' => $f, 'meses' => $meses, 'redondeo' => array_map(fn ($v) => round($v, 2), $meses)];
}

if (count($datos) !== count($fincas) + 1) {
    fwrite(STDERR, 'No se encontraron todas las filas "Ppto ' . $anio . "\" esperadas\n");
    exit(1);
}

$hoja    = $datos['empresa'];
unset($datos['empresa']);
$d2      = fn (float $v) => number_format($v, 2, '.', '');
$filas   = [];
$resumen = [];
$errores = [];

foreach ($datos as $cod => $d) {
    foreach ($d['redondeo'] as $i => $k) {
        $filas[] = "    ($empresa, $anio, '$cod', " . str_pad((string) ($i + 1), 2, ' ', STR_PAD_LEFT) . ', ' . $d2($k) . ')';
    }
    $resumen[] = "     $cod {$d['nombre']} (fila {$d['fila']}): anual " . $d2(array_sum($d['redondeo'])) . ' kg (columna N de la hoja: ' . $d2(round(array_sum($d['meses']), 2)) . ')';
    $resumen[] = '         ' . implode(' ', array_map($d2, $d['redondeo']));
}

for ($i = 0; $i < 12; $i++) {
    $suma = round(array_sum(array_map(fn ($d) => $d['redondeo'][$i], $datos)), 2);
    if (abs($suma - $hoja['meses'][$i]) >= 0.01) {
        $errores[] = 'Mes ' . ($i + 1) . ': fincas ' . $d2($suma) . ' vs total hoja ' . $d2($hoja['meses'][$i]);
    }
}

$totalEmpresa = round(array_sum(array_map(fn ($d) => array_sum($d['redondeo']), $datos)), 2);
$resumen[]    = "     Total empresa (fila {$hoja['fila']}): anual " . $d2($totalEmpresa) . ' kg, enero ' . $d2(round($hoja['meses'][0], 2)) . ' kg';
$resumen[]    = '     Nota: cada mes se redondea a 2 decimales; la suma anual puede diferir en centavos de la columna N.';
$resumen      = implode("\n", $resumen);
$fecha        = date('Y-m-d H:i');
$valores      = implode(",\n", $filas);
$listaF       = "'" . implode("', '", array_keys($datos)) . "'";

$sql = <<<SQL
/* ============================================================================
   Carga del presupuesto de producción {$anio} POR LOTE
   ----------------------------------------------------------------------------
   GENERADO AUTOMÁTICAMENTE el {$fecha} con
   Documentacion/BaseDeDatos/herramientas/generar_carga_presupuesto_lotes_2026.php
   a partir de Documentacion/DATOS PRODUCCION 2018 - 2026.xlsx, hoja
   "Cumplimiento al ppto", filas "Ppto {$anio}" de cada finca. Los valores
   mensuales son KILOS (aunque la hoja diga Ton), redondeados a 2 decimales.

   VALORES DE LA HOJA (kg)
{$resumen}

   QUÉ HACE
   --------
     1) Valida que las fincas existen en aFinca y que cada una tiene al menos
        un lote activo con hNetas > 0 (si no, THROW con la lista).
     2) Reparte los kilos de cada finca y mes entre los lotes ACTIVOS de la
        finca con hNetas > 0, proporcional a hNetas, ROUND a 2 decimales.
        El residuo se asigna al lote de mayor hNetas; si fuera negativo y
        dejara un lote por debajo de 0, se descuenta en orden de mayor área
        sin bajar de 0. La suma por finca y mes cuadra exacta con la hoja.
        (Misma lógica que PresupuestoProduccionModel::distribucion.)
     3) En una transacción BORRA todas las filas {$anio} de esas fincas en
        dbo.aPresupuestoProduccion (metas de finca con lote = '' y filas por
        lote) e inserta el reparto (solo kilos > 0). Usuario: MIGRACION.
     4) Muestra el resumen por finca y mes y el detalle por lote.
   IDEMPOTENTE: se puede ejecutar varias veces (borra y vuelve a insertar).
   Las hectáreas y los lotes se toman de aLotes al momento de ejecutar.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resumen y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i cargar_presupuesto_lotes_{$anio}.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT         = 0;
DECLARE @Empresa INT         = {$empresa};
DECLARE @Anio    INT         = {$anio};
DECLARE @Usuario VARCHAR(50) = 'MIGRACION';

IF OBJECT_ID('dbo.aPresupuestoProduccion', 'U') IS NULL
    THROW 50001, N'Ejecute primero crear_presupuesto_produccion.sql.', 1;

IF OBJECT_ID('tempdb..#lotes') IS NOT NULL DROP TABLE #lotes;
IF OBJECT_ID('tempdb..#reparto') IS NOT NULL DROP TABLE #reparto;

DECLARE @hoja TABLE (empresa INT, anio INT, finca VARCHAR(50), mes TINYINT, kilos DECIMAL(18, 2), PRIMARY KEY (finca, mes));

INSERT INTO @hoja (empresa, anio, finca, mes, kilos) VALUES
{$valores};

IF EXISTS (SELECT 1 FROM (SELECT DISTINCT empresa, finca FROM @hoja) h WHERE NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = h.empresa AND LTRIM(RTRIM(f.codigo)) = h.finca))
BEGIN
    SELECT DISTINCT h.finca AS fincaNoEncontrada FROM @hoja h
     WHERE NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = h.empresa AND LTRIM(RTRIM(f.codigo)) = h.finca);
    THROW 50002, N'Hay fincas de la carga que no existen en aFinca para la empresa.', 1;
END;

SELECT l.empresa, LTRIM(RTRIM(l.finca)) AS finca, LTRIM(RTRIM(l.codigo)) AS lote,
       LTRIM(RTRIM(ISNULL(l.descripcion, ''))) AS descripcion, CAST(l.hNetas AS FLOAT) AS hNetas,
       ROW_NUMBER() OVER (PARTITION BY LTRIM(RTRIM(l.finca)) ORDER BY l.hNetas DESC, LTRIM(RTRIM(l.codigo))) AS orden
  INTO #lotes
  FROM aLotes l
 WHERE l.empresa = @Empresa AND LTRIM(RTRIM(l.finca)) IN ({$listaF}) AND l.activo = 1 AND ISNULL(l.hNetas, 0) > 0;

IF EXISTS (SELECT 1 FROM (SELECT DISTINCT finca FROM @hoja) h WHERE NOT EXISTS (SELECT 1 FROM #lotes l WHERE l.finca = h.finca))
BEGIN
    SELECT DISTINCT h.finca AS fincaSinLotesConArea FROM @hoja h WHERE NOT EXISTS (SELECT 1 FROM #lotes l WHERE l.finca = h.finca);
    THROW 50003, N'Hay fincas sin lotes activos con hNetas > 0.', 1;
END;

IF EXISTS (SELECT finca, lote FROM #lotes GROUP BY finca, lote HAVING COUNT(*) > 1)
BEGIN
    SELECT finca, lote, COUNT(*) AS veces FROM #lotes GROUP BY finca, lote HAVING COUNT(*) > 1;
    THROW 50004, N'Hay códigos de lote repetidos en la misma finca.', 1;
END;

WITH base AS (
    SELECT h.finca, h.mes, h.kilos, l.lote, l.orden,
           CAST(ROUND(CAST(h.kilos AS FLOAT) * l.hNetas / SUM(l.hNetas) OVER (PARTITION BY h.finca, h.mes), 2) AS DECIMAL(18, 2)) AS kilosBase
      FROM @hoja h
      JOIN #lotes l ON l.finca = h.finca
), residuo AS (
    SELECT b.*,
           b.kilos - SUM(b.kilosBase) OVER (PARTITION BY b.finca, b.mes) AS resto,
           SUM(b.kilosBase) OVER (PARTITION BY b.finca, b.mes ORDER BY b.orden ROWS UNBOUNDED PRECEDING) - b.kilosBase AS previo
      FROM base b
)
SELECT finca, mes, lote,
       CAST(kilosBase + CASE
            WHEN resto >= 0 THEN CASE WHEN orden = 1 THEN resto ELSE 0 END
            WHEN -resto <= previo THEN 0
            WHEN -resto - previo >= kilosBase THEN -kilosBase
            ELSE resto + previo
       END AS DECIMAL(18, 2)) AS kilos
  INTO #reparto
  FROM residuo;

BEGIN TRY

    BEGIN TRAN;

    DELETE FROM dbo.aPresupuestoProduccion
     WHERE empresa = @Empresa AND anio = @Anio AND finca IN ({$listaF});

    PRINT CONCAT(N'Filas eliminadas (lote y metas de finca): ', @@ROWCOUNT);

    INSERT INTO dbo.aPresupuestoProduccion (empresa, anio, mes, finca, lote, kilos, usuario)
    SELECT @Empresa, @Anio, mes, finca, lote, kilos, @Usuario
      FROM #reparto
     WHERE kilos > 0;

    PRINT CONCAT(N'Filas por lote insertadas: ', @@ROWCOUNT);

    SELECT h.finca, h.mes, h.kilos AS kilosHoja,
           ISNULL(p.kilos, 0) AS kilosRepartidos,
           h.kilos - ISNULL(p.kilos, 0) AS diferencia,
           l.lotes, l.hectareas
      FROM @hoja h
      OUTER APPLY (SELECT SUM(x.kilos) AS kilos FROM dbo.aPresupuestoProduccion x
                    WHERE x.empresa = @Empresa AND x.anio = @Anio AND x.finca = h.finca AND x.mes = h.mes) p
      OUTER APPLY (SELECT COUNT(*) AS lotes, CAST(SUM(y.hNetas) AS DECIMAL(18, 2)) AS hectareas FROM #lotes y WHERE y.finca = h.finca) l
     ORDER BY h.finca, h.mes;

    SELECT l.finca, l.lote, l.descripcion, CAST(l.hNetas AS DECIMAL(18, 2)) AS hNetas,
           CAST(l.hNetas * 100 / SUM(l.hNetas) OVER (PARTITION BY l.finca) AS DECIMAL(9, 4)) AS participacion,
           SUM(CASE WHEN p.mes = 1 THEN p.kilos ELSE 0 END) AS ene,
           SUM(CASE WHEN p.mes = 2 THEN p.kilos ELSE 0 END) AS feb,
           SUM(CASE WHEN p.mes = 3 THEN p.kilos ELSE 0 END) AS mar,
           SUM(CASE WHEN p.mes = 4 THEN p.kilos ELSE 0 END) AS abr,
           SUM(CASE WHEN p.mes = 5 THEN p.kilos ELSE 0 END) AS may,
           SUM(CASE WHEN p.mes = 6 THEN p.kilos ELSE 0 END) AS jun,
           SUM(CASE WHEN p.mes = 7 THEN p.kilos ELSE 0 END) AS jul,
           SUM(CASE WHEN p.mes = 8 THEN p.kilos ELSE 0 END) AS ago,
           SUM(CASE WHEN p.mes = 9 THEN p.kilos ELSE 0 END) AS sep,
           SUM(CASE WHEN p.mes = 10 THEN p.kilos ELSE 0 END) AS oct,
           SUM(CASE WHEN p.mes = 11 THEN p.kilos ELSE 0 END) AS nov,
           SUM(CASE WHEN p.mes = 12 THEN p.kilos ELSE 0 END) AS dic,
           ISNULL(SUM(p.kilos), 0) AS total
      FROM #lotes l
      LEFT JOIN dbo.aPresupuestoProduccion p ON p.empresa = @Empresa AND p.anio = @Anio AND p.finca = l.finca AND p.lote = l.lote
     GROUP BY l.finca, l.lote, l.descripcion, l.hNetas, l.orden
     ORDER BY l.finca, l.orden;

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

SQL;

file_put_contents($salida, "\xEF\xBB\xBF" . str_replace("\n", "\r\n", $sql));
echo "Archivo: $salida\n";
echo $resumen . "\n";
echo $errores === [] ? "Total por mes de las fincas = fila total de la hoja (12/12)\n" : implode("\n", $errores) . "\n";
