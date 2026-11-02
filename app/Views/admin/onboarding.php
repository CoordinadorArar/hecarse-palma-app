<?php echo $this->extend('template/layout'); ?>
<?php
$k          = $resumen['kpis'] ?? [];
$fallos     = $resumen['fallos'] ?? [];
$falla      = fn ($clave) => in_array($clave, $fallos, true);
$kpiFalla   = $falla('kpis') || $k === [];
$num        = fn ($clave) => $kpiFalla ? '—' : number_format((int) ($k[$clave] ?? 0), 0, ',', '.');
$activos    = (int) ($k['usuariosActivos'] ?? 0);
$sinRol     = (int) ($k['usuariosSinRol'] ?? 0);
$sesiones   = $resumen['sesionesPorDia'] ?? [];
$porRol     = $resumen['usuariosPorRol'] ?? [];
$porLoseta  = $resumen['modulosPorLoseta'] ?? [];
$accesos    = $resumen['ultimosAccesos'] ?? [];
$rolesSin   = $resumen['rolesSinUsuarios'] ?? [];
$modulosSin = $resumen['modulosSinRol'] ?? [];
$hayDatos   = fn ($filas, $campos) => array_sum(array_map(fn ($f) => array_sum(array_map(fn ($c) => (int) ($f[$c] ?? 0), $campos)), $filas)) > 0;
$kpis = [
    ['Usuarios activos', 'bi-people', 'usuariosActivos', $num('usuariosInactivos') . ' inactivos', 'ao-kpi-neutro'],
    ['Usuarios sin rol', 'bi-person-exclamation', 'usuariosSinRol', $sinRol > 0 ? 'Requieren asignación' : 'Todos con rol', $sinRol > 0 ? 'ao-kpi-alerta' : ''],
    ['Roles activos', 'bi-shield-lock', 'rolesActivos', '', ''],
    ['Losetas', 'bi-grid-3x3-gap', 'losetasActivas', $num('modulosActivos') . ' módulos', ''],
    ['Submódulos', 'bi-diagram-3', 'submodulosActivos', $num('tabsActivos') . ' tabs', ''],
    ['Acciones', 'bi-lightning', 'accionesActivas', '', ''],
    ['Sesiones 30 días', 'bi-box-arrow-in-right', 'sesiones30d', $num('usuariosSesion30d') . ' usuarios distintos', ''],
    ['Activos en 7 días', 'bi-activity', 'usuariosSesion7d', $activos > 0 ? number_format((int) ($k['usuariosSesion7d'] ?? 0) * 100 / $activos, 0, ',', '.') . '% de activos' : '', ''],
];
if ($kpiFalla) {
    $kpis = array_map(fn ($x) => [$x[0], $x[1], $x[2], 'No disponible', ''], $kpis);
}
$relativa = function ($fecha) {
    $seg = time() - strtotime($fecha);
    if ($seg < 60) {
        return 'hace un momento';
    }
    if ($seg < 3600) {
        return 'hace ' . intdiv($seg, 60) . ' min';
    }
    if (date('Y-m-d', strtotime($fecha)) === date('Y-m-d')) {
        return 'hace ' . intdiv($seg, 3600) . ' h';
    }
    if (date('Y-m-d', strtotime($fecha)) === date('Y-m-d', strtotime('-1 day'))) {
        return 'ayer';
    }
    return 'hace ' . max(2, (int) round($seg / 86400)) . ' días';
};
$iniciales = function ($nombre) {
    $partes = preg_split('/\s+/', trim((string) $nombre)) ?: [];
    return mb_strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice(array_filter($partes), 0, 2)))) ?: '?';
};
$vacio = fn ($texto, $icono = 'bi-bar-chart') => '<div class="gp-estado-vacio text-center text-muted py-5"><i class="bi ' . $icono . ' d-block mb-2 ao-icono-estado" aria-hidden="true"></i><h6 class="mb-1">Sin datos para mostrar</h6><span class="gp-subtexto">' . esc($texto) . '</span></div>';
$noDisp = fn ($py = 'py-5') => '<div class="gp-estado-vacio text-center text-muted ' . $py . '"><i class="bi bi-exclamation-circle d-block mb-2 ao-icono-estado" aria-hidden="true"></i><h6 class="mb-1">No disponible</h6><span class="gp-subtexto">No se pudo cargar esta información</span></div>';
$chips = function ($items) {
    $html = '';
    foreach (array_slice($items, 0, 12) as $item) {
        $html .= '<span class="ao-chip ambar">' . esc($item) . '</span>';
    }
    if (count($items) > 12) {
        $html .= '<span class="ao-chip">+' . (count($items) - 12) . ' más</span>';
    }
    return $html;
};
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/admin_onboarding.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main ao-root">

    <div class="pagetitle">
        <h1><?= esc($nombre_loseta ?: 'Administración') ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item active">Administración</li>
            </ol>
        </nav>
        <p class="text-muted mb-0 small">Usuarios, roles, módulos y configuración general del sitio.</p>
    </div>

    <?php foreach ($resumen['avisos'] ?? [] as $aviso): ?>
        <div class="alert alert-warning d-flex gap-2 align-items-center">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span><?= esc($aviso) ?></span>
        </div>
    <?php endforeach; ?>

    <section class="section">
        <div class="row g-3 mb-3">
            <?php foreach ($kpis as [$etiqueta, $icono, $clave, $subtexto, $clase]): ?>
                <div class="col-6 col-lg-3">
                    <div class="inf-kpi <?= $clase ?>">
                        <div class="gp-icono d-flex align-items-center justify-content-center rounded-3"><i class="bi <?= $icono ?>" aria-hidden="true"></i></div>
                        <div class="min-w-0">
                            <span class="gp-etiqueta-mini text-truncate"><?= esc($etiqueta) ?></span>
                            <div class="inf-kpi-valor"><?= $num($clave) ?></div>
                            <?php if ($subtexto !== ''): ?>
                                <span class="gp-subtexto text-truncate"><?= esc($subtexto) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-graph-up" aria-hidden="true"></i>Inicios de sesión, últimos 30 días</h6></div>
                    <div class="card-body pt-3">
                        <?php if ($hayDatos($sesiones, ['sesiones', 'usuarios'])): ?>
                            <div class="ao-chart" id="ao_chart_sesiones" role="img" aria-label="Inicios de sesión y usuarios distintos por día durante los últimos 30 días"></div>
                        <?php else: ?>
                            <div class="ao-chart d-flex align-items-center justify-content-center"><?= $falla('sesionesPorDia') ? $noDisp() : $vacio('No se registraron inicios de sesión en los últimos 30 días.') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-person-badge" aria-hidden="true"></i>Usuarios por rol</h6></div>
                    <div class="card-body pt-3">
                        <?php if ($hayDatos($porRol, ['usuarios'])): ?>
                            <div class="ao-chart" id="ao_chart_roles" role="img" aria-label="Cantidad de usuarios asignados a cada rol"></div>
                        <?php else: ?>
                            <div class="ao-chart d-flex align-items-center justify-content-center"><?= $falla('usuariosPorRol') ? $noDisp() : $vacio('Aún no hay usuarios asignados a roles.') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-bar-chart-line" aria-hidden="true"></i>Módulos por loseta</h6></div>
                    <div class="card-body pt-3">
                        <?php if ($hayDatos($porLoseta, ['modulos', 'submodulos', 'acciones'])): ?>
                            <div class="ao-scroll-x">
                                <div class="ao-chart" id="ao_chart_losetas" role="img" aria-label="Módulos, submódulos y acciones configurados en cada loseta"<?= count($porLoseta) > 8 ? ' style="min-width:' . (count($porLoseta) * 70) . 'px"' : '' ?>></div>
                            </div>
                        <?php else: ?>
                            <div class="ao-chart d-flex align-items-center justify-content-center"><?= $falla('modulosPorLoseta') ? $noDisp() : $vacio('No hay módulos configurados en las losetas.') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-clock-history" aria-hidden="true"></i>Últimos accesos</h6></div>
                    <div class="card-body py-2">
                        <?php if (empty($accesos)): ?>
                            <?= $falla('ultimosAccesos') ? $noDisp() : $vacio('Todavía no hay inicios de sesión registrados.', 'bi-clock-history') ?>
                        <?php endif; ?>
                        <?php foreach ($accesos as $acceso): ?>
                            <?php $ts = strtotime($acceso['fecha']); ?>
                            <div class="ao-lista d-flex align-items-center gap-2">
                                <span class="ao-avatar rounded-circle d-inline-flex align-items-center justify-content-center fw-semibold" aria-hidden="true"><?= esc($iniciales($acceso['nombre'] ?: $acceso['usuario'])) ?></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate"><?= esc($acceso['nombre'] ?: $acceso['usuario']) ?></div>
                                    <span class="gp-subtexto text-truncate">@<?= esc($acceso['usuario']) ?></span>
                                </div>
                                <time class="ms-auto text-end text-nowrap small" datetime="<?= esc(date('Y-m-d\TH:i', $ts), 'attr') ?>">
                                    <span class="d-block"><?= esc($relativa($acceso['fecha'])) ?></span>
                                    <span class="gp-subtexto"><?= esc(date('d/m/Y H:i', $ts)) ?></span>
                                </time>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer bg-white text-end">
                        <a class="small fw-semibold text-decoration-none" href="<?= esc(base_url('admin/usuarios/' . $id_loseta), 'attr') ?>">Ver usuarios <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-white"><h6 class="gp-seccion"><i class="bi bi-ui-checks" aria-hidden="true"></i>Configuración pendiente</h6></div>
                    <div class="card-body py-3">
                        <?php if (empty($rolesSin) && empty($modulosSin) && !$falla('rolesSinUsuarios') && !$falla('modulosSinRol')): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle d-block mb-2 ao-icono-estado ao-icono-ok" aria-hidden="true"></i>
                                <h6 class="mb-1">Todo está configurado</h6>
                                <span class="gp-subtexto">Todos los roles tienen usuarios y todos los módulos están asignados.</span>
                            </div>
                        <?php else: ?>
                            <?php if ($falla('rolesSinUsuarios')): ?>
                                <div class="mb-4"><span class="fw-semibold small">Roles sin usuarios</span><?= $noDisp('py-3') ?></div>
                            <?php elseif (!empty($rolesSin)): ?>
                                <div class="mb-4">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-semibold small">Roles sin usuarios</span>
                                        <span class="badge rounded-pill ao-chip ambar"><?= count($rolesSin) ?></span>
                                        <a class="ms-auto small text-decoration-none" href="<?= esc(base_url('admin/usuarios/' . $id_loseta), 'attr') ?>">Asignar usuarios</a>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1"><?= $chips($rolesSin) ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($falla('modulosSinRol')): ?>
                                <div><span class="fw-semibold small">Módulos sin rol</span><?= $noDisp('py-3') ?></div>
                            <?php elseif (!empty($modulosSin)): ?>
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <span class="fw-semibold small">Módulos sin rol</span>
                                        <span class="badge rounded-pill ao-chip ambar"><?= count($modulosSin) ?></span>
                                        <span class="ms-auto d-flex gap-3 small">
                                            <a class="text-decoration-none" href="<?= esc(base_url('admin/roles/' . $id_loseta), 'attr') ?>">Asignar a roles</a>
                                            <a class="text-decoration-none" href="<?= esc(base_url('admin/modulos/' . $id_loseta), 'attr') ?>">Revisar módulos</a>
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1"><?= $chips($modulosSin) ?></div>
                                </div>
                            <?php endif; ?>
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
    const ADMIN_RESUMEN = <?= json_encode(['sesiones' => $sesiones, 'roles' => $porRol, 'losetas' => $porLoseta], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= asset_url('public/assets/js/admin/onboarding.js') ?>"></script>
<?php echo $this->endSection(); ?>
