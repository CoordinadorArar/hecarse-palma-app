<?php echo $this->extend('template/layout'); ?>
<?php $anios = $anios ?: [$anio_sel]; ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informesGerenciales.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/tableros.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main ig-root">

    <div class="pagetitle">
        <h1>Análisis de Producción</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <?php if (! empty($id_loseta)): ?>
                    <li class="breadcrumb-item"><a href="<?= esc(base_url('dashboards/onboarding/' . $id_loseta), 'attr') ?>">Dashboards</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item">Dashboards</li>
                <?php endif; ?>
                <li class="breadcrumb-item active">Análisis de Producción</li>
            </ol>
        </nav>
    </div>

    <div class="card gp-filtros mb-3">
        <div class="card-body py-3 px-4">
            <form id="form_filtros" class="row g-2 align-items-end" novalidate>
                <div class="col-12 col-md-auto">
                    <span class="form-label mb-1 small fw-semibold text-muted d-block">Vista</span>
                    <div class="btn-group btn-group-sm w-100 tb-vista" role="group" aria-label="Vista del tablero">
                        <input type="radio" class="btn-check" name="vista" id="vista_actual" value="actual" checked>
                        <label class="btn btn-outline-success text-nowrap" for="vista_actual"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Año actual</label>
                        <input type="radio" class="btn-check" name="vista" id="vista_historico" value="historico">
                        <label class="btn btn-outline-success text-nowrap" for="vista_historico"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Histórico</label>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3<?= count($empresas) > 1 ? '' : ' d-none' ?>">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (string) $empresa['id'] === (string) $empresa_sel ? 'selected' : '' ?>><?= esc($empresa['razonSocial'] ?? $empresa['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_anio">Año</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_anio">
                            <?php foreach ($anios as $anio): ?>
                                <option value="<?= esc($anio, 'attr') ?>" <?= (string) $anio === (string) $anio_sel ? 'selected' : '' ?>><?= esc($anio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_mes">Mes de corte</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_mes">
                            <?php foreach ($meses as $numero => $mes): ?>
                                <option value="<?= (int) $numero ?>" <?= (int) $mes_sel === (int) $numero ? 'selected' : '' ?>><?= esc($mes) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_finca">Finca</label>
                    <div class="input-group flex-nowrap inf-select2">
                        <span class="input-group-text"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                        <div class="flex-grow-1 inf-selector">
                            <select class="form-select" id="filtro_finca">
                                <option value="">Todas las fincas</option>
                                <?php foreach ($fincas as $finca): ?>
                                    <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['codigo'] . ' — ' . $finca['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_siembra">Año de siembra</label>
                    <div class="input-group flex-nowrap inf-select2">
                        <span class="input-group-text"><i class="bi bi-tree" aria-hidden="true"></i></span>
                        <div class="flex-grow-1 inf-selector">
                            <select class="form-select" id="filtro_siembra">
                                <option value="">Todos</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end">
                    <button type="submit" class="btn btn-success ig-consultar" id="btn_consultar"><i class="bi bi-search me-1" aria-hidden="true"></i>Consultar</button>
                </div>
                <div class="col-12 d-none" id="nota_vista">
                    <span class="gp-subtexto"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Año y mes de corte no aplican al histórico</span>
                </div>
            </form>
        </div>
    </div>

    <div id="zona_resultados">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="d-flex flex-wrap gap-2" id="chips_contexto"></span>
            <span class="gp-chip-fila ambar d-none" id="chip_desactualizado"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Filtros modificados · consulte de nuevo</span>
        </div>
        <div id="resultado_cuerpo" aria-live="polite"></div>
    </div>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const TABLERO = <?= json_encode(['tablero' => $tablero, 'meses' => array_values($meses)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= asset_url('public/assets/js/dashboards/analisisProduccion.js') ?>"></script>
<script src="<?= asset_url('public/assets/js/tutoriales/modulos/tableroProduccion.js') ?>"></script>
<?php echo $this->endSection(); ?>
