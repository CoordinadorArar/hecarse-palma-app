<?php echo $this->extend('template/layout'); ?>
<?php
$meses_cortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$anios        = $anios ?: [$anio_sel];
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/informes.css">
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/informesGerenciales.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main ig-root">

    <div class="pagetitle">
        <h1>Informes Gerenciales</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <?php if (! empty($id_loseta)): ?>
                    <li class="breadcrumb-item"><a href="<?= esc(base_url('dashboards/onboarding/' . $id_loseta), 'attr') ?>">Dashboards</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item">Dashboards</li>
                <?php endif; ?>
                <li class="breadcrumb-item active">Informes Gerenciales</li>
            </ol>
        </nav>
    </div>

    <div class="ig-informes d-flex gap-2 overflow-auto mb-3" role="tablist" aria-label="Informes gerenciales">
        <?php foreach ($informes as $indice => $informe): ?>
            <button type="button" class="ig-informe-tab d-flex align-items-center gap-2 text-start" role="tab" data-informe="<?= esc($informe['clave'], 'attr') ?>" aria-selected="<?= $indice === 0 ? 'true' : 'false' ?>">
                <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="<?= esc($informe['icono'] ?? 'bi bi-clipboard-data', 'attr') ?>" aria-hidden="true"></i></span>
                <span class="min-w-0">
                    <span class="d-block fw-semibold ig-informe-nombre"><?= esc($informe['nombre']) ?></span>
                    <span class="gp-subtexto text-truncate" title="<?= esc($informe['descripcion'] ?? '', 'attr') ?>"><?= esc($informe['descripcion'] ?? '') ?></span>
                </span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="card gp-filtros mb-3">
        <div class="card-body py-3 px-4">
            <form id="form_filtros" class="row g-2 align-items-end" novalidate>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (string) $empresa['id'] === (string) $empresa_sel ? 'selected' : '' ?>><?= esc($empresa['razonSocial'] ?? $empresa['nombre'] ?? $empresa['id']) ?></option>
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
                            <?php foreach ($meses_cortos as $indice => $mes): ?>
                                <option value="<?= $indice + 1 ?>" <?= (int) $mes_sel === $indice + 1 ? 'selected' : '' ?>><?= $mes ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_finca">Finca</label>
                    <div class="input-group flex-nowrap">
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
                <div class="col-12 col-md-3 col-lg-auto">
                    <span class="form-label mb-1 small fw-semibold text-muted d-block">Unidad</span>
                    <div class="btn-group w-100" role="group" aria-label="Unidad">
                        <input type="radio" class="btn-check" name="unidad" id="unidad_kg" value="kg" checked>
                        <label class="btn btn-outline-success btn-sm ig-unidad" for="unidad_kg">Kg</label>
                        <input type="radio" class="btn-check" name="unidad" id="unidad_ton" value="ton">
                        <label class="btn btn-outline-success btn-sm ig-unidad" for="unidad_ton">Ton</label>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg text-lg-end">
                    <button type="submit" class="btn btn-success ig-consultar" id="btn_consultar"><i class="bi bi-search me-1" aria-hidden="true"></i>Consultar</button>
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
    const INFORMES_GERENCIALES = <?= json_encode(['informes' => $informes], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= base_url('public/assets/js/dashboards/informesGerenciales.js') ?>"></script>
<?php echo $this->endSection(); ?>
