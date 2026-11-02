<?php echo $this->extend('template/layout'); ?>
<?php
$k          = $resumen['kpis'] ?? [];
$fallos     = $resumen['fallos'] ?? [];
if (in_array('empresas', $fallos, true)) {
    $fallos = array_merge($fallos, ['presupuesto', 'fincasLotes', 'periodos', 'presupuestoMeses', 'precios', 'empleados']);
    $k = [];
}
$chipT      = fn ($cls, $corto, $largo) => '<span class="ao-chip ' . $cls . '" title="' . $largo . '"><span class="d-none d-sm-inline">' . $largo . '</span><span class="d-sm-none" aria-hidden="true">' . $corto . '</span><span class="visually-hidden d-sm-none">' . $largo . '</span></span>';
$falla      = fn ($clave) => in_array($clave, $fallos, true);
$anio       = (int) ($resumen['anio'] ?? date('Y'));
$mes        = (int) ($resumen['mes'] ?? date('n'));
$pptoOk     = !empty($resumen['presupuestoDisponible']) && !$falla('presupuesto');
$meses      = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$empresas   = $empresas ?? [];
$empresaSel = (int) ($empresa_sel ?? 0);
$url        = fn ($ruta) => esc(base_url($ruta . '/' . $id_loseta), 'attr');
$n          = fn ($v, $d = 0) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
$kv         = fn ($c) => $k[$c] ?? null;
$enlace     = fn ($ruta, $texto) => '<a class="small fw-semibold text-decoration-none" href="' . $url($ruta) . '">' . esc($texto) . ' <i class="bi bi-arrow-right" aria-hidden="true"></i></a>';
$vacio      = fn ($texto, $icono = 'bi-bar-chart', $ruta = null, $txt = '') => '<div class="gp-estado-vacio text-center text-muted py-5"><i class="bi ' . $icono . ' d-block mb-2 ao-icono-estado" aria-hidden="true"></i><h6 class="mb-1">Sin datos para mostrar</h6><span class="gp-subtexto">' . esc($texto) . '</span>' . ($ruta ? '<div class="mt-2">' . $enlace($ruta, $txt) . '</div>' : '') . '</div>';
$noDisp     = fn ($py = 'py-5') => '<div class="gp-estado-vacio text-center text-muted ' . $py . '"><i class="bi bi-exclamation-circle d-block mb-2 ao-icono-estado" aria-hidden="true"></i><h6 class="mb-1">No disponible</h6><span class="gp-subtexto">No se pudo cargar esta información</span></div>';
$pie        = fn (...$links) => '<div class="card-footer bg-white text-end d-flex flex-wrap justify-content-end gap-3">' . implode('', array_map(fn ($l) => $enlace($l[0], $l[1]), $links)) . '</div>';

$periodos  = $resumen['periodos'] ?? [];
$porMes    = array_column(array_filter($periodos['lista'] ?? [], fn ($p) => (int) $p['anio'] === $anio), 'estado', 'mes');
$actual    = $falla('periodos') ? null : ($porMes[$mes] ?? '');
$pptoMeses = ($pptoOk && !$falla('presupuestoMeses')) ? ($resumen['presupuestoMeses'] ?? []) : [];
$precios   = $resumen['precios'] ?? [];
$grupos    = $precios['porGrupo'] ?? [];
$fincas    = $resumen['fincasLotes'] ?? [];
usort($fincas, fn ($a, $b) => (int) !empty($a['tienePresupuesto']) <=> (int) !empty($b['tienePresupuesto']));
$todasPpto = $pptoOk && $fincas && !array_filter($fincas, fn ($f) => empty($f['tienePresupuesto']));

$ha      = $kv('hectareasNetas');
$sinPre  = $kv('laboresSinPrecio');
$conPre  = $kv('laboresConPrecio');
$kgMes   = $pptoOk ? $kv('presupuestoMesKg') : null;
$kpis = [
    ['Empleados activos', 'bi-people', $n($kv('empleadosActivos')), $n($kv('empleadosInactivos')) . ' inactivos', 'ao-kpi-neutro', 'gestion-palma/nomina/empleados', $kv('empleadosActivos')],
    ['Fincas', 'bi-house', $n($kv('fincasActivas')), $pptoOk && $kv('fincasConPresupuesto') !== null ? $n($kv('fincasConPresupuesto')) . ' con presupuesto este mes' : ((int) $kv('fincas') > (int) $kv('fincasActivas') ? $n($kv('fincas') - $kv('fincasActivas')) . ' inactivas' : ''), $pptoOk && (int) $kv('fincasSinPresupuesto') > 0 ? 'ao-kpi-alerta' : '', null, $kv('fincasActivas')],
    ['Lotes activos', 'bi-grid-3x3', $n($kv('lotesActivos')), $pptoOk && $kv('lotesSinPresupuesto') !== null ? $n($kv('lotesSinPresupuesto')) . ' sin presupuesto' : '', $pptoOk && (int) $kv('lotesSinPresupuesto') > 0 ? 'ao-kpi-alerta' : '', null, $kv('lotesActivos')],
    ['Hectáreas', 'bi-bounding-box', $n($ha, 1) . ' ha', 'en lotes activos', '', null, $ha],
    ['Palmas', 'bi-tree', $n($kv('palmas')), (float) $ha > 0 ? $n($kv('palmas') / $ha) . ' palmas/ha promedio' : '', '', null, $kv('palmas')],
    ['Presupuesto ' . $meses[$mes - 1], 'bi-clipboard-data', $n($pptoOk ? $kv('presupuestoMesTon') : null, 1) . ' t', $n($kgMes) . ' kg', $kgMes !== null && (float) $kgMes <= 0 ? 'ao-kpi-alerta' : '', null, $kgMes],
    ['Periodo actual', 'bi-calendar-check', ['ABIERTO' => 'Abierto', 'CERRADO' => 'Cerrado', '' => 'Sin crear'][$actual] ?? '—', $n($periodos['anioAbiertos'] ?? null) . ' abiertos en ' . $anio, $actual === '' ? 'ao-kpi-alerta' : '', null, $actual],
    ['Labores sin precio', 'bi-tag', $n($sinPre), $conPre !== null && $sinPre !== null ? 'de ' . $n($conPre + $sinPre) . ' labores' : '', (int) $sinPre > 0 ? 'ao-kpi-alerta' : '', null, $sinPre],
];
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
            <h1><?= esc(($nombre_loseta ?? '') ?: 'Maestros Palma') ?></h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                    <li class="breadcrumb-item active">Maestros Palma</li>
                </ol>
            </nav>
            <p class="text-muted mb-0 small">Información maestra del cultivo: fincas, lotes, presupuesto, periodos, labores y nómina.</p>
        </div>
        <?php if (count($empresas) > 1): ?>
            <div class="col-md-auto">
                <label for="gp_empresa" class="small fw-semibold text-muted d-block mb-1">Empresa</label>
                <select id="gp_empresa" class="form-select form-select-sm" style="min-width:220px">
                    <?php foreach ($empresas as $e): ?>
                        <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === $empresaSel ? ' selected' : '' ?>><?= esc($e['razonSocial']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($empresas): ?>
            <div class="col-md-auto"><span class="ao-chip"><?= esc($empresas[0]['razonSocial']) ?></span></div>
        <?php endif; ?>
    </div>

    <?php foreach ($resumen['avisos'] ?? [] as $aviso): ?>
        <div class="alert alert-warning d-flex gap-2 align-items-center">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span><?= esc($aviso) ?></span>
        </div>
    <?php endforeach; ?>

    <section class="section">
        <div class="row g-3 mb-3">
            <?php foreach ($kpis as [$etiqueta, $icono, $valor, $subtexto, $clase, $ruta, $crudo]): ?>
                <?php if ($crudo === null) { $valor = '—'; $subtexto = 'No disponible'; $clase = ''; } ?>
                <div class="col-6 col-lg-3">
                    <<?= $ruta ? 'a href="' . $url($ruta) . '"' : 'div' ?> class="inf-kpi <?= $clase ?><?= $ruta ? ' text-decoration-none' : '' ?>">
                        <div class="gp-icono d-flex align-items-center justify-content-center rounded-3"><i class="bi <?= $icono ?>" aria-hidden="true"></i></div>
                        <div class="min-w-0">
                            <span class="gp-etiqueta-mini text-truncate"><?= esc($etiqueta) ?></span>
                            <div class="inf-kpi-valor"><?= esc($valor) ?></div>
                            <?php if ($subtexto !== ''): ?>
                                <span class="gp-subtexto text-truncate"><?= esc($subtexto) ?></span>
                            <?php endif; ?>
                        </div>
                    </<?= $ruta ? 'a' : 'div' ?>>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-bar-chart" aria-hidden="true"></i>Presupuesto por mes <?= $anio ?></h6></div>
                    <div class="card-body pt-3">
                        <?php if (array_sum(array_map(fn ($m) => (float) $m['kilos'], $pptoMeses)) > 0): ?>
                            <div class="ao-chart" id="gp_chart_presupuesto" role="img" aria-label="Presupuesto de producción en toneladas por mes de <?= $anio ?>"></div>
                        <?php else: ?>
                            <div class="ao-chart d-flex align-items-center justify-content-center"><?= !$pptoOk || $falla('presupuestoMeses') ? $noDisp() : $vacio('No hay presupuesto cargado para ' . $anio . '.', 'bi-clipboard-data', 'gestion-palma/contabilidad/presupuesto', 'Cargar presupuesto') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-tag" aria-hidden="true"></i>Precios de labores</h6></div>
                    <div class="card-body pt-3">
                        <?php if (!$falla('precios') && (int) ($precios['total'] ?? 0) > 0): ?>
                            <div class="ao-chart" id="gp_chart_precios" role="img" aria-label="Labores con precio y sin precio asignado"></div>
                        <?php else: ?>
                            <div class="ao-chart d-flex align-items-center justify-content-center"><?= $falla('precios') ? $noDisp() : $vacio('No hay labores registradas.', 'bi-tag') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i>Precios por grupo de labor</h6></div>
                    <div class="card-body pt-3">
                        <?php if (!$falla('precios') && $grupos): ?>
                            <div class="<?= count($grupos) > 10 ? 'ao-scroll-x' : '' ?>">
                                <div class="ao-chart" id="gp_chart_grupos" role="img" aria-label="Labores con y sin precio por grupo de labor"<?= count($grupos) > 10 ? ' style="min-width:560px"' : '' ?>></div>
                            </div>
                        <?php else: ?>
                            <div class="ao-chart d-flex align-items-center justify-content-center"><?= $falla('precios') ? $noDisp() : $vacio('No hay labores registradas.', 'bi-tag') ?></div>
                        <?php endif; ?>
                    </div>
                    <?= $pie(['gestion-palma/precios/por-labor', 'Asignar precios']) ?>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                        <h6 class="gp-seccion mb-0"><i class="bi bi-map" aria-hidden="true"></i>Fincas y lotes</h6>
                        <?php if ($todasPpto): ?><span class="ao-chip ms-auto"><i class="bi bi-check-circle" aria-hidden="true"></i> Todas las fincas con presupuesto</span><?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if ($falla('fincasLotes')): ?>
                            <?= $noDisp() ?>
                        <?php elseif (!$fincas): ?>
                            <?= $vacio('La empresa no tiene fincas registradas.', 'bi-house', 'gestion-palma/fincas', 'Registrar finca') ?>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm ao-tabla mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Finca / Lote</th>
                                            <th scope="col" class="text-end d-none d-sm-table-cell">Ha</th>
                                            <th scope="col" class="text-end d-none d-sm-table-cell">Palmas</th>
                                            <th scope="col" class="text-end">Presupuesto mes</th>
                                            <th scope="col">Estado</th>
                                        </tr>
                                    </thead>
                                    <?php foreach ($fincas as $i => $f): ?>
                                        <?php
                                        $lotes = $f['lotesDetalle'] ?? [];
                                        $nl    = (int) ($f['lotes'] ?? 0);
                                        $con   = (int) ($f['lotesConPresupuesto'] ?? 0);
                                        $pct   = $nl > 0 ? round($con * 100 / $nl) : 0;
                                        $kgF   = (float) ($f['presupuestoFincaKg'] ?? 0);
                                        $plegar = count($lotes) > 5;
                                        ?>
                                        <tbody>
                                            <tr class="ao-finca">
                                                <th scope="row">
                                                    <?= esc($f['nombre'] ?: $f['codigo']) ?>
                                                    <span class="gp-subtexto"><?= $nl > 0 ? $n($nl) . ' lotes' : 'Sin lotes activos' ?></span>
                                                    <?php if ($plegar): ?>
                                                        <button type="button" class="btn btn-link btn-sm p-0 small fw-semibold text-decoration-none ao-toggle" data-bs-toggle="collapse" data-bs-target="#gp_lotes_<?= $i ?>" aria-expanded="false" aria-controls="gp_lotes_<?= $i ?>"><span class="ao-ver">Ver lotes (<?= count($lotes) ?>)</span><span class="ao-ocultar">Ocultar lotes</span></button>
                                                    <?php endif; ?>
                                                </th>
                                                <td class="text-end text-nowrap d-none d-sm-table-cell"><?= $n($f['hectareas'] ?? null, 1) ?></td>
                                                <td class="text-end text-nowrap d-none d-sm-table-cell"><?= $n($f['palmas'] ?? null) ?></td>
                                                <td class="text-end">
                                                    <span class="text-nowrap"><?= $pptoOk && isset($f['presupuestoMes']) ? $n($f['presupuestoMes']) . ' kg' : '—' ?></span>
                                                    <?php if ($kgF > 0): ?><span class="gp-subtexto">incluye <?= $n($kgF) ?> kg a nivel finca</span><?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$pptoOk): ?>
                                                        —
                                                    <?php elseif ($nl === 0): ?>
                                                        <span class="ao-chip gris">Sin lotes</span>
                                                    <?php elseif ($con === 0 && $kgF > 0): ?>
                                                        <span class="ao-chip gris" title="Meta de finca (histórico)"><span class="d-none d-sm-inline" aria-hidden="true">Meta de finca</span><span class="d-sm-none" aria-hidden="true">Finca</span><span class="visually-hidden">Meta de finca (histórico)</span></span>
                                                    <?php elseif ($con === 0): ?>
                                                        <?= $chipT('ambar', 'Sin', 'Sin presupuesto') ?>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="ao-barra flex-grow-1" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Lotes con presupuesto"><div style="width:<?= $pct ?>%"></div></div>
                                                            <span class="gp-subtexto text-nowrap"><?= $con ?>/<?= $nl ?> lotes</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <?php if ($lotes): ?>
                                            <tbody<?= $plegar ? ' class="collapse" id="gp_lotes_' . $i . '"' : '' ?>>
                                                <?php foreach ($lotes as $l): ?>
                                                    <tr>
                                                        <td class="ps-4"><span class="fw-semibold"><?= esc($l['codigo']) ?></span> <?= esc($l['nombre'] ?? '') ?></td>
                                                        <td class="text-end text-nowrap d-none d-sm-table-cell"><?= $n($l['hectareas'] ?? null, 1) ?></td>
                                                        <td class="text-end text-nowrap d-none d-sm-table-cell"><?= $n($l['palmas'] ?? null) ?></td>
                                                        <td class="text-end text-nowrap"><?= $pptoOk && isset($l['presupuestoMes']) ? $n($l['presupuestoMes']) . ' kg' : '—' ?></td>
                                                        <td><?= !$pptoOk ? '—' : (!empty($l['tienePresupuesto']) ? $chipT('', 'Con', 'Con presupuesto') : $chipT('ambar', 'Sin', 'Sin presupuesto')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?= $pie(['gestion-palma/fincas', 'Fincas'], ['gestion-palma/lotes/registro', 'Lotes'], ['gestion-palma/contabilidad/presupuesto', 'Cargar presupuesto']) ?>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-calendar3" aria-hidden="true"></i>Periodos contables <?= $anio ?></h6></div>
                    <div class="card-body py-3">
                        <?php if ($falla('periodos')): ?>
                            <?= $noDisp() ?>
                        <?php elseif (!$porMes): ?>
                            <?= $vacio('No hay periodos creados para ' . $anio . '.', 'bi-calendar3', 'gestion-palma/contabilidad/periodos', 'Crear periodos') ?>
                        <?php else: ?>
                            <div class="row row-cols-4 row-cols-sm-6 g-2 mb-3">
                                <?php foreach ($meses as $m => $nombreMes): ?>
                                    <?php
                                    $estado = $porMes[$m + 1] ?? '';
                                    [$cls, $ico, $txt] = ['ABIERTO' => ['', 'bi-unlock', 'abierto'], 'CERRADO' => ['gris', 'bi-lock', 'cerrado'], '' => ['ambar', '', 'sin crear']][$estado] ?? ['ambar', '', 'sin crear'];
                                    ?>
                                    <div class="col">
                                        <span class="ao-chip ao-mes w-100 <?= $cls ?><?= $m + 1 === $mes ? ' actual' : '' ?>" role="img" aria-label="<?= esc($nombreMes . ' ' . $anio . ': ' . $txt, 'attr') ?>" title="<?= esc(ucfirst($txt), 'attr') ?>">
                                            <span class="d-block" aria-hidden="true"><?= $ico ? '<i class="bi ' . $ico . '"></i>' : '–' ?></span>
                                            <span aria-hidden="true"><?= mb_substr($nombreMes, 0, 3) ?></span>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="ao-chip"><i class="bi bi-unlock" aria-hidden="true"></i> Abierto</span>
                                <span class="ao-chip gris"><i class="bi bi-lock" aria-hidden="true"></i> Cerrado</span>
                                <span class="ao-chip ambar">– Sin crear</span>
                            </div>
                            <?php $ab = count(array_filter($porMes, fn ($e) => $e === 'ABIERTO')); ?>
                            <span class="gp-subtexto"><?= $ab ?> abiertos · <?= count($porMes) - $ab ?> cerrados · <?= 12 - count($porMes) ?> sin crear</span>
                        <?php endif; ?>
                    </div>
                    <?= $pie(['gestion-palma/contabilidad/periodos', 'Gestionar periodos']) ?>
                </div>
            </div>
        </div>
    </section>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const GP_RESUMEN = <?= json_encode(['mes' => $mes, 'presupuesto' => $pptoMeses, 'precios' => ['con' => (int) ($precios['conPrecio'] ?? 0), 'sin' => (int) ($precios['sinPrecio'] ?? 0)], 'grupos' => $grupos], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= asset_url('public/assets/js/gestionpalma/onboarding.js') ?>"></script>
<?php echo $this->endSection(); ?>
