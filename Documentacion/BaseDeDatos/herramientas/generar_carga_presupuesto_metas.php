<?php

$raiz    = 'E:/xampp/htdocs/AppPalma/Documentacion/';
$archivo = $argv[1] ?? $raiz . 'DATOS PRODUCCION 2018 - 2026.xlsx';
$salida  = $argv[2] ?? $raiz . 'BaseDeDatos/cargar_presupuesto_metas_2026.sql';
$anio    = 2026;
$empresa = 1;
$fincas  = ['CRUCEIRO' => '002', 'HATO' => '001', 'CAÑAGUATE' => '003'];

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
        $celdas = [];
        foreach (simplexml_load_string($zip->getFromName($ruta))->sheetData->row as $fila) {
            foreach ($fila->c as $c) {
                $v = (string) $c->v;
                if ((string) $c['t'] === 's') $v = $compartidos[(int) $v];
                elseif ((string) $c['t'] === 'inlineStr') $v = (string) $c->is->t;
                $celdas[(string) $c['r']] = $v;
            }
        }
        return $celdas;
    }
    fwrite(STDERR, "No existe la hoja \"$nombre\"\n");
    exit(1);
}

$cumpl = hoja($zip, $compartidos, 'Cumplimiento al ppto');
$tonha = hoja($zip, $compartidos, ' tonha 12 ulti');
$zip->close();

$metaTonHa = $tonha['C52'] ?? '';

if (trim((string) ($tonha['A52'] ?? '')) !== 'Meta' || ! is_numeric($metaTonHa)) {
    fwrite(STDERR, "No se encontró la meta t/ha en ' tonha 12 ulti'!C52\n");
    exit(1);
}

$columnas = range('B', 'M');
$titulo   = '';
$datos    = [];

for ($f = 1; $f <= 200; $f++) {
    $a = trim((string) ($cumpl["A$f"] ?? ''));
    if (stripos($a, 'Cumplimiento al ppto') === 0) $titulo = mb_strtoupper(trim(mb_substr($a, 20)));
    if ($a !== "Ppto $anio" || $titulo === '') continue;
    $codigo = null;
    foreach ($fincas as $nombre => $cod) {
        if (str_contains($titulo, $nombre)) $codigo = $cod;
    }
    if ($codigo === null) {
        fwrite(STDERR, "Finca sin mapeo: $titulo (fila $f)\n");
        exit(1);
    }
    $meses = array_map(fn ($c) => (float) ($cumpl[$c . $f] ?? 0), $columnas);
    $total = array_sum($meses);
    $pct   = [];
    for ($m = 0; $m < 11; $m++) $pct[] = round($meses[$m] / $total * 100, 4);
    $pct[] = round(100 - array_sum($pct), 4);
    $datos[$codigo] = ['nombre' => $titulo, 'fila' => $f, 'meses' => $meses, 'anual' => round($total, 2), 'pct' => $pct];
}

if (count($datos) !== count($fincas)) {
    fwrite(STDERR, 'Se esperaban ' . count($fincas) . ' fincas con "Ppto ' . $anio . '" y se encontraron ' . count($datos) . "\n");
    exit(1);
}

$kilosEmpresa = round(array_sum(array_column($datos, 'anual')), 2);
$d2  = fn (float $v) => number_format($v, 2, '.', '');
$d4  = fn (float $v) => number_format($v, 4, '.', '');

$metas  = ["    ($empresa, $anio, '', " . $d2($kilosEmpresa) . ', ' . $d2((float) $metaTonHa) . ')'];
$curvas = [];
$resumen = ["     Empresa $empresa (finca ''): kilosAnual " . $d2($kilosEmpresa) . ', metaTonHa ' . $d2((float) $metaTonHa)];

foreach ($datos as $cod => $d) {
    $metas[] = "    ($empresa, $anio, '$cod', " . $d2($d['anual']) . ', NULL)';
    foreach ($d['pct'] as $i => $p) {
        $curvas[] = "    ($empresa, $anio, '$cod', " . str_pad((string) ($i + 1), 2, ' ', STR_PAD_LEFT) . ', ' . $d4($p) . ')';
    }
    $resumen[] = "     $cod {$d['nombre']} (fila {$d['fila']}): kilosAnual " . $d2($d['anual']) . ', suma curva ' . $d4(array_sum($d['pct']));
    $resumen[] = '         % ' . implode(' ', array_map($d4, $d['pct']));
}

$resumen  = implode("\n", $resumen);
$fecha    = date('Y-m-d H:i');
$filasMet = implode(",\n", $metas);
$filasCur = implode(",\n", $curvas);
$listaF   = "'" . implode("', '", array_keys($datos)) . "'";

$sql = <<<SQL
/* ============================================================================
   Carga inicial de metas y curva de estacionalidad del presupuesto {$anio}
   ----------------------------------------------------------------------------
   GENERADO AUTOMÁTICAMENTE el {$fecha} con
   Documentacion/BaseDeDatos/herramientas/generar_carga_presupuesto_metas.php
   a partir de Documentacion/DATOS PRODUCCION 2018 - 2026.xlsx:
     - hoja "Cumplimiento al ppto", filas "Ppto {$anio}" de cada finca:
         kilosAnual = suma de los 12 meses;
         porcentaje del mes = mes / anual x 100 (4 decimales, el último mes
         se ajusta para que la curva sume exactamente 100).
     - hoja " tonha 12 ulti", celda C52: meta t/ha de la empresa.
       kilosAnual de la empresa = suma de las fincas.

   REQUISITO: haber ejecutado crear_presupuesto_metas.sql.

   VALORES
{$resumen}

   QUÉ HACE
   --------
     1) Valida que las tablas existen y que las fincas existen en aFinca.
     2) MERGE en dbo.aPresupuestoMeta por (empresa, anio, finca): inserta o
        actualiza kilosAnual y metaTonHa.
     3) Reemplaza la curva de dbo.aPresupuestoCurva de esas fincas en el año
        (borra e inserta los 12 meses).
   NO modifica dbo.aPresupuestoProduccion (el presupuesto por lote se genera
   desde el módulo con la acción "Generar").
   IDEMPOTENTE: se puede ejecutar varias veces. Usuario de registro: MIGRACION.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resumen y hace ROLLBACK. @Aplicar = 1: hace COMMIT.
     Archivo UTF-8: ejecutar desde SSMS o con "sqlcmd -f 65001 -i cargar_presupuesto_metas_{$anio}.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT         = 0;
DECLARE @Empresa INT         = {$empresa};
DECLARE @Anio    INT         = {$anio};
DECLARE @Usuario VARCHAR(50) = 'MIGRACION';

IF OBJECT_ID('dbo.aPresupuestoMeta', 'U') IS NULL OR OBJECT_ID('dbo.aPresupuestoCurva', 'U') IS NULL
    THROW 50001, N'Ejecute primero crear_presupuesto_metas.sql.', 1;

DECLARE @metas TABLE (empresa INT, anio INT, finca VARCHAR(50), kilosAnual DECIMAL(18, 2), metaTonHa DECIMAL(9, 2));
DECLARE @curva TABLE (empresa INT, anio INT, finca VARCHAR(50), mes TINYINT, porcentaje DECIMAL(9, 4));

INSERT INTO @metas (empresa, anio, finca, kilosAnual, metaTonHa) VALUES
{$filasMet};

INSERT INTO @curva (empresa, anio, finca, mes, porcentaje) VALUES
{$filasCur};

IF EXISTS (SELECT 1 FROM @metas m WHERE m.finca <> '' AND NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = m.empresa AND LTRIM(RTRIM(f.codigo)) = m.finca))
BEGIN
    SELECT m.finca AS fincaNoEncontrada FROM @metas m
     WHERE m.finca <> '' AND NOT EXISTS (SELECT 1 FROM aFinca f WHERE f.empresa = m.empresa AND LTRIM(RTRIM(f.codigo)) = m.finca);
    THROW 50002, N'Hay fincas de la carga que no existen en aFinca para la empresa.', 1;
END;

IF EXISTS (SELECT finca FROM @curva GROUP BY finca HAVING COUNT(*) <> 12 OR SUM(porcentaje) <> 100)
    THROW 50003, N'La curva de alguna finca no tiene 12 meses o no suma 100.', 1;

BEGIN TRY

    BEGIN TRAN;

    MERGE dbo.aPresupuestoMeta AS t
    USING @metas AS s
       ON t.empresa = s.empresa AND t.anio = s.anio AND t.finca = s.finca
     WHEN MATCHED THEN
          UPDATE SET kilosAnual = s.kilosAnual, metaTonHa = s.metaTonHa,
                     usuarioModificacion = @Usuario, fechaModificacion = GETDATE()
     WHEN NOT MATCHED THEN
          INSERT (empresa, anio, finca, kilosAnual, metaTonHa, usuario)
          VALUES (s.empresa, s.anio, s.finca, s.kilosAnual, s.metaTonHa, @Usuario);

    PRINT CONCAT(N'Metas insertadas/actualizadas: ', @@ROWCOUNT);

    DELETE FROM dbo.aPresupuestoCurva
     WHERE empresa = @Empresa AND anio = @Anio AND finca IN ({$listaF});

    INSERT INTO dbo.aPresupuestoCurva (empresa, anio, finca, mes, porcentaje, usuario)
    SELECT empresa, anio, finca, mes, porcentaje, @Usuario FROM @curva;

    PRINT CONCAT(N'Meses de curva insertados: ', @@ROWCOUNT);

    SELECT m.finca, m.kilosAnual, m.metaTonHa,
           (SELECT COUNT(*) FROM dbo.aPresupuestoCurva c WHERE c.empresa = m.empresa AND c.anio = m.anio AND c.finca = m.finca) AS mesesCurva,
           (SELECT SUM(porcentaje) FROM dbo.aPresupuestoCurva c WHERE c.empresa = m.empresa AND c.anio = m.anio AND c.finca = m.finca) AS sumaCurva
      FROM dbo.aPresupuestoMeta m
     WHERE m.empresa = @Empresa AND m.anio = @Anio
     ORDER BY m.finca;

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
   REVERSIÓN MANUAL de esta carga. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;
BEGIN TRAN;
DELETE FROM dbo.aPresupuestoCurva WHERE empresa = {$empresa} AND anio = {$anio} AND finca IN ({$listaF});
DELETE FROM dbo.aPresupuestoMeta WHERE empresa = {$empresa} AND anio = {$anio} AND finca IN ('', {$listaF});
COMMIT;
---------------------------------------------------------------------------- */

SQL;

file_put_contents($salida, "\xEF\xBB\xBF" . str_replace("\n", "\r\n", $sql));
echo "Archivo: $salida\n";
echo $resumen . "\n";

foreach ($datos as $cod => $d) {
    $dif = 0.0;
    foreach ($d['pct'] as $i => $p) {
        $dif = max($dif, abs(round($d['anual'] * $p / 100, 2) - $d['meses'][$i]));
    }
    echo "$cod: diferencia máxima mes regenerado vs Excel = " . $d2($dif) . " kg\n";
}
