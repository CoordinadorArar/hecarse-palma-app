<?php echo $this->extend('template/layout'); ?>
<?php
$k      = $resumen['kpis'] ?? [];
$fallos = $resumen['fallos'] ?? [];
if (in_array('empresas', $fallos, true)) {
    $fallos = array_merge($fallos, ['transacciones', 'mesAnterior', 'produccion', 'labores', 'planeacion', 'porFinca', 'ultimas']);
    $k = [];
}
$falla      = fn ($clave) => in_array($clave, $fallos, true);
$anio       = (int) ($resumen['anio'] ?? date('Y'));
$mes        = (int) ($resumen['mes'] ?? date('n'));
$meses      = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$cortos     = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$empresas   = $empresas ?? [];
$empresaSel = (int) ($empresa_sel ?? 0);
$permitidas = array_map(fn ($r) => preg_replace('#/\d+$#', '', trim((string) ($r['Ruta'] ?? ''), '/')), array_merge($sidebar ?? [], $submodulos ?? []));
$ok         = fn ($ruta) => in_array($ruta, $permitidas, true);
$url        = fn ($ruta) => esc(base_url($ruta . '/' . $id_loseta), 'attr');
$n          = fn ($v, $d = 0) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
$pct        = fn ($v) => number_format($v, $v < 10 ? 1 : 0, ',', '.');
$plural     = fn ($v, $s, $p) => $n($v) . ' ' . ((int) $v === 1 ? $s : $p);
$cmp        = fn ($v) => '$' . ($v >= 1e9 ? $n($v / 1e9, 1) . ' mil M' : ($v >= 1e6 ? $n($v / 1e6, 1) . ' M' : $n($v)));
$enlace     = fn ($ruta, $texto) => '<a class="small fw-semibold text-decoration-none" href="' . $url($ruta) . '">' . esc($texto) . ' <i class="bi bi-arrow-right" aria-hidden="true"></i></a>';
$vacio      = fn ($texto, $icono = 'bi-bar-chart', $ruta = null, $txt = '') => '<div class="gp-estado-vacio text-center text-muted py-5"><i class="bi ' . $icono . ' d-block mb-2 ao-icono-estado" aria-hidden="true"></i><h6 class="mb-1">Sin datos para mostrar</h6><span class="gp-subtexto">' . esc($texto) . '</span>' . ($ruta && $ok($ruta) ? '<div class="mt-2">' . $enlace($ruta, $txt) . '</div>' : '') . '</div>';
$noDisp     = fn ($py = 'py-5') => '<div class="gp-estado-vacio text-center text-muted ' . $py . '"><i class="bi bi-exclamation-circle d-block mb-2 ao-icono-estado" aria-hidden="true"></i><h6 class="mb-1">No disponible</h6><span class="gp-subtexto">No se pudo cargar esta información</span></div>';
$pie        = fn (...$links) => ($links = array_values(array_filter($links, fn ($l) => $ok($l[0])))) ? '<div class="card-footer bg-white text-end d-flex flex-wrap justify-content-end gap-3">' . implode('', array_map(fn ($l) => $enlace($l[0], $l[1]), $links)) . '</div>' : '';

$mods = [
    'produccion'    => ['nombre' => 'Producción', 'color' => '#2f7a4b', 'icono' => 'bi-basket'],
    'labores'       => ['nombre' => 'Labores', 'color' => '#1f5f8b', 'icono' => 'bi-tools'],
    'fertilizacion' => ['nombre' => 'Fertilización', 'color' => '#6fa8d6', 'icono' => 'bi-droplet'],
    'planeacion'    => ['nombre' => 'Planeación', 'color' => '#8795a4', 'icono' => 'bi-calendar-check'],
    'otros'         => ['nombre' => 'Otras', 'color' => '#b8c2cc', 'icono' => 'bi-three-dots'],
];

$dFin     = (int) date('j', strtotime($resumen['hasta'] ?? date('Y-m-d')));
$rango    = ($dFin > 1 ? '1–' . $dFin : '1') . ' ' . $cortos[$mes - 1];
$prev     = strtotime(sprintf('%04d-%02d-01 -1 month', $anio, $mes));
$dPrev    = min($dFin, (int) date('t', $prev));
$rangoAnt = ($dPrev > 1 ? '1–' . $dPrev : '1') . ' ' . $cortos[(int) date('n', $prev) - 1];
$mesTxt   = mb_strtolower($meses[$mes - 1]);

$tot = $falla('transacciones') ? null : ($k['total'] ?? null);
$anu = $tot === null ? null : ($k['anuladas'] ?? null);
$ant = $falla('mesAnterior') ? null : ($k['totalMesAnterior'] ?? null);
$pr  = $k['produccion'] ?? [];
$lb  = $k['labores'] ?? [];
$fe  = $k['fertilizacion'] ?? [];
$pl  = $falla('planeacion') ? [] : ($k['planeacion'] ?? []);
$p   = $tot !== null && (int) $ant > 0 ? ($tot - $ant) * 100 / $ant : 0;
$var = match (true) {
    $ant === null       => 'Comparativo no disponible',
    (int) $ant === 0    => 'Sin registros en ' . $rangoAnt,
    (int) $tot === (int) $ant => '<i class="bi bi-dash" aria-hidden="true"></i> Igual que ' . $rangoAnt,
    default             => '<span class="text-' . ($p > 0 ? 'success' : 'danger') . '"><i class="bi bi-arrow-' . ($p > 0 ? 'up' : 'down') . '-right" aria-hidden="true"></i> ' . ($p > 0 ? '+' : '−') . $pct(abs($p)) . ' %</span> vs ' . $rangoAnt,
};
$jor  = $lb['jornales'] ?? null;
$jTxt = (float) $jor > 0 ? $n($jor, fmod((float) $jor, 1) ? 1 : 0) . ' jornales · ' : '';
$val  = $lb['valor'] ?? null;
$act  = $pl['actividades'] ?? null;
$com  = (int) ($pl['completadas'] ?? 0);
$pp   = (int) $act > 0 ? (int) round($com * 100 / $act) : 0;

$kpis = [
    ['Transacciones del mes', 'bi-arrow-left-right', $n($tot), $var, null, 'ao-kpi-neutro', null, null, $tot],
    ['Producción', $mods['produccion']['icono'], $n($pr['transacciones'] ?? null), ($pr['racimos'] ?? null) === null ? 'No disponible' : $n($pr['racimos']) . ' racimos · ' . $n($pr['kg']) . ' kg', null, '', $mods['produccion']['color'], 'transacciones/produccion', $tot === null ? null : ($pr['transacciones'] ?? null)],
    ['Labores', $mods['labores']['icono'], $n($lb['transacciones'] ?? null), $val === null ? 'No disponible' : $jTxt . 'Valor ' . $cmp($val), $val === null ? null : $jTxt . 'Valor $' . $n($val), '', $mods['labores']['color'], 'transacciones/labores', $tot === null ? null : ($lb['transacciones'] ?? null)],
    ['Fertilización', $mods['fertilizacion']['icono'], $n($fe['registros'] ?? null), $plural($fe['planes'] ?? null, 'plan de fertilización', 'planes de fertilización'), null, '', $mods['fertilizacion']['color'], 'transacciones/fertilizacion', $tot === null ? null : ($fe['registros'] ?? null)],
    ['Planeación', $mods['planeacion']['icono'], $plural($act, 'actividad', 'actividades'), (int) $act === 0 ? 'Sin actividades planeadas' : '<div class="d-flex align-items-center gap-2"><div class="ao-barra flex-grow-1" role="progressbar" aria-valuenow="' . $pp . '" aria-valuemin="0" aria-valuemax="100" aria-label="Actividades completadas"><div style="width:' . $pp . '%"></div></div><span class="text-nowrap">' . $com . '/' . (int) $act . ' completadas</span></div>', (int) $act > 0 ? $com . ' completadas · ' . (int) ($pl['pendientes'] ?? 0) . ' pendientes' : null, '', $mods['planeacion']['color'], 'transacciones/planeacion', $act],
    ['Anuladas', 'bi-x-circle', $n($anu), (int) $anu === 0 ? 'Ninguna este mes' : $pct($anu * 100 / ($tot + $anu)) . ' % del mes', null, (int) $anu > 0 ? 'ao-kpi-alerta' : '', null, null, $anu],
];

$porDia    = $tot === null ? [] : ($resumen['porDia'] ?? []);
$hayDia    = array_sum(array_map(fn ($d) => $d['produccion'] + $d['labores'] + $d['fertilizacion'], $porDia)) > 0;
$porModulo = $tot === null ? [] : array_values(array_filter($resumen['porModulo'] ?? [], fn ($m) => (int) $m['transacciones'] > 0));
$porFinca  = $resumen['porFinca'] ?? [];
$ultimas   = $resumen['ultimas'] ?? [];
$sinMes    = 'No hay transacciones registradas en ' . $mesTxt . '.';
$chart     = fn ($html) => '<div class="ao-chart d-flex align-items-center justify-content-center">' . $html . '</div>';
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/admin_onboarding.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main ao-root">

    <div class="pagetitle row g-2 align-items-end">
        <div class="col-md">
            <h1><?= esc(($nombre_loseta ?? '') ?: 'Transacciones') ?></h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                    <li class="breadcrumb-item active">Transacciones</li>
                </ol>
            </nav>
            <p class="text-muted mb-0 small">Resumen de las transacciones registradas en el mes en curso.</p>
        </div>
        <div class="col-md-auto d-flex flex-wrap align-items-end gap-2">
            <span class="ao-chip"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= esc($meses[$mes - 1] . ' ' . $anio . ' · ' . $rango) ?></span>
            <?php if (count($empresas) > 1): ?>
                <div>
                    <label for="tx_empresa" class="small fw-semibold text-muted d-block mb-1">Empresa</label>
                    <select id="tx_empresa" class="form-select form-select-sm" style="min-width:220px">
                        <?php foreach ($empresas as $e): ?>
                            <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === $empresaSel ? ' selected' : '' ?>><?= esc($e['razonSocial']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($empresas): ?>
                <span class="ao-chip"><?= esc($empresas[0]['razonSocial']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach ($resumen['avisos'] ?? [] as $aviso): ?>
        <div class="alert alert-warning d-flex gap-2 align-items-center">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span><?= esc($aviso) ?></span>
        </div>
    <?php endforeach; ?>

    <section class="section">
        <div class="row g-3 mb-3">
            <?php foreach ($kpis as [$etiqueta, $icono, $valor, $subtexto, $titulo, $clase, $color, $ruta, $crudo]): ?>
                <?php if ($crudo === null) { $valor = '—'; $subtexto = 'No disponible'; $titulo = null; $clase = ''; } ?>
                <?php $link = $ruta && $ok($ruta); ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <<?= $link ? 'a href="' . $url($ruta) . '"' : 'div' ?> class="inf-kpi <?= $clase ?><?= $color ? ' ao-kpi-mod' : '' ?><?= $link ? ' text-decoration-none' : '' ?>"<?= $color ? ' style="--ao-mod:' . $color . '"' : '' ?> title="<?= esc($etiqueta . ': ' . ($titulo ?? trim(strip_tags($subtexto))), 'attr') ?>">
                        <div class="gp-icono d-flex align-items-center justify-content-center rounded-3"><i class="bi <?= $icono ?>" aria-hidden="true"></i></div>
                        <div class="min-w-0 flex-grow-1">
                            <span class="gp-etiqueta-mini text-truncate"><?= esc($etiqueta) ?></span>
                            <div class="inf-kpi-valor"><?= esc($valor) ?></div>
                            <div class="gp-subtexto<?= str_contains($subtexto, 'ao-barra') ? '' : ' text-truncate' ?>"><?= $subtexto ?></div>
                        </div>
                    </<?= $link ? 'a' : 'div' ?>>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-bar-chart" aria-hidden="true"></i>Transacciones por día</h6></div>
                    <div class="card-body pt-3">
                        <?php if ($hayDia): ?>
                            <div class="ao-chart" id="tx_chart_dia" role="img" aria-label="Transacciones por día de <?= esc($mesTxt . ' ' . $anio, 'attr') ?> por módulo"></div>
                        <?php else: ?>
                            <?= $chart($tot === null ? $noDisp() : $vacio($sinMes, 'bi-bar-chart', 'transacciones/produccion', 'Registrar producción')) ?>
                        <?php endif; ?>
                    </div>
                    <?= $pie(['transacciones/produccion', 'Producción'], ['transacciones/labores', 'Labores'], ['transacciones/fertilizacion', 'Fertilización']) ?>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-pie-chart" aria-hidden="true"></i>Por módulo</h6></div>
                    <div class="card-body pt-3">
                        <?php if ($porModulo): ?>
                            <div class="ao-chart" id="tx_chart_modulo" role="img" aria-label="Distribución de las transacciones del mes por módulo"></div>
                        <?php else: ?>
                            <?= $chart($tot === null ? $noDisp() : $vacio($sinMes, 'bi-pie-chart')) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-clock-history" aria-hidden="true"></i>Últimas transacciones</h6></div>
                    <div class="card-body py-2">
                        <?php if ($falla('ultimas')): ?>
                            <?= $noDisp() ?>
                        <?php elseif (!$ultimas): ?>
                            <?= $vacio('Aún no hay transacciones este mes.', 'bi-clock-history') ?>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm ao-tabla mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Módulo</th>
                                            <th scope="col">Documento</th>
                                            <th scope="col">Fecha</th>
                                            <th scope="col" class="d-none d-md-table-cell">Finca</th>
                                            <th scope="col" class="d-none d-lg-table-cell">Usuario</th>
                                            <th scope="col" class="text-end">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimas as $u): ?>
                                            <?php $m = $mods[$u['modulo']] ?? $mods['otros']; ?>
                                            <tr>
                                                <td>
                                                    <span class="ao-chip gris" title="<?= esc($m['nombre'], 'attr') ?>"><span class="ao-punto" style="background-color:<?= $m['color'] ?>" aria-hidden="true"></span><span class="d-none d-sm-inline"><?= esc($m['nombre']) ?></span><span class="d-sm-none" aria-hidden="true"><?= esc($u['tipo']) ?></span><span class="visually-hidden d-sm-none"><?= esc($m['nombre']) ?></span></span>
                                                </td>
                                                <td class="font-monospace fw-semibold text-nowrap<?= $u['anulado'] ? ' text-decoration-line-through text-muted' : '' ?>"><?= esc($u['numero']) ?></td>
                                                <td class="text-nowrap"><?= (int) substr($u['fecha'], 8, 2) . ' ' . $cortos[(int) substr($u['fecha'], 5, 2) - 1] ?></td>
                                                <td class="d-none d-md-table-cell"><?= esc($u['finca'] ?: '—') ?></td>
                                                <td class="d-none d-lg-table-cell text-muted"><?= esc($u['usuario'] ?: '—') ?></td>
                                                <td class="text-end"><?= $u['anulado'] ? '<span class="ao-chip rojo">Anulada</span>' : '' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?= $pie(['transacciones/produccion', 'Ver producción'], ['transacciones/labores', 'Ver labores'], ['transacciones/fertilizacion', 'Ver fertilización']) ?>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-geo-alt" aria-hidden="true"></i>Transacciones por finca</h6></div>
                    <div class="card-body pt-3">
                        <?php if ($falla('porFinca')): ?>
                            <?= $chart($noDisp()) ?>
                        <?php elseif ($porFinca): ?>
                            <div class="ao-chart" id="tx_chart_finca" role="img" aria-label="Transacciones del mes por finca"></div>
                        <?php else: ?>
                            <?= $chart($vacio((int) $tot > 0 ? 'Las transacciones del mes no tienen finca asociada.' : $sinMes, 'bi-geo-alt')) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const TX_RESUMEN = <?= json_encode(['mes' => $mes, 'modulos' => $mods, 'porDia' => $hayDia ? $porDia : [], 'porModulo' => $porModulo, 'porFinca' => $porFinca], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= asset_url('public/assets/js/transacciones/onboarding.js') ?>"></script>
<?php echo $this->endSection(); ?>
