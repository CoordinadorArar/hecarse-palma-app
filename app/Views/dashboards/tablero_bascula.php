<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informesGerenciales.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/tableros.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/basculaInforme.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main ig-root">

    <div class="pagetitle">
        <h1>Informe báscula</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <?php if (! empty($id_loseta)): ?>
                    <li class="breadcrumb-item"><a href="<?= esc(base_url('informes/onboarding/' . $id_loseta), 'attr') ?>">Informes</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item">Informes</li>
                <?php endif; ?>
                <li class="breadcrumb-item active">Informe báscula</li>
            </ol>
        </nav>
    </div>

    <div class="card gp-filtros mb-3">
        <div class="card-body py-3 px-4">
            <form id="form_filtros" class="row g-2 align-items-end" novalidate>
                <div class="col-12 col-md-6 col-lg-4<?= count($empresas) > 1 ? '' : ' d-none' ?>">
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
                <div class="col-12 col-md-6 col-lg-4 ba-fincas<?= count($fincas_disp ?? []) > 1 ? '' : ' d-none' ?>" id="col_fincas">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_fincas">Fincas</label>
                    <div class="input-group flex-nowrap inf-select2">
                        <span class="input-group-text"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                        <div class="flex-grow-1 inf-selector">
                            <select class="form-select" id="filtro_fincas" multiple>
                                <?php foreach ($fincas_disp ?? [] as $finca): ?>
                                    <option value="<?= esc($finca['codigo'], 'attr') ?>" data-nombre="<?= esc($finca['nombre'], 'attr') ?>"><?= esc($finca['codigo'] . ' — ' . $finca['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_desde">Fecha inicio</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                        <input type="date" class="form-control" id="filtro_desde" value="<?= esc($desde_sel ?? '', 'attr') ?>" min="2000-01-01" max="2100-12-31" aria-describedby="error_hasta">
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_hasta">Fecha fin</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                        <input type="date" class="form-control" id="filtro_hasta" value="<?= esc($hasta_sel ?? '', 'attr') ?>" min="2000-01-01" max="2100-12-31" aria-describedby="error_hasta">
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <span class="form-label mb-1 small fw-semibold text-muted d-block">Periodo</span>
                    <div class="btn-group btn-group-sm w-100" role="group" aria-label="Accesos rápidos de periodo">
                        <button type="button" class="btn btn-outline-success text-nowrap" data-rango="mes" aria-pressed="false">Este mes</button>
                        <button type="button" class="btn btn-outline-success text-nowrap" data-rango="anterior" aria-pressed="false">Mes anterior</button>
                        <button type="button" class="btn btn-outline-success text-nowrap" data-rango="treinta" aria-pressed="false">Últimos 30 días</button>
                    </div>
                </div>
                <div class="col-12 col-md">
                    <div class="d-flex gap-2 justify-content-md-end">
                        <button type="button" class="btn btn-outline-success ig-consultar" id="btn_excel" disabled><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Exportar a Excel</button>
                        <button type="submit" class="btn btn-outline-success ig-consultar" id="btn_consultar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar</button>
                    </div>
                </div>
                <div class="col-12 d-none" id="fila_error_hasta">
                    <div class="invalid-feedback d-block small mt-0" id="error_hasta" role="alert"></div>
                </div>
            </form>
        </div>
    </div>

    <div id="zona_resultados">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="chips_contexto"></div>
        <div id="resultado_cuerpo" aria-live="polite"></div>
    </div>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
</script>
<script src="<?= asset_url('public/assets/js/dashboards/informeBascula.js') ?>"></script>
<?php echo $this->endSection(); ?>
