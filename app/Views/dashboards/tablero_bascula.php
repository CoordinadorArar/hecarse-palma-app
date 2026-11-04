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
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_mes">Mes</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-month" aria-hidden="true"></i></span>
                        <input type="month" class="form-control" id="filtro_mes" value="<?= esc($mes_sel ?? '', 'attr') ?>">
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end">
                    <button type="submit" class="btn btn-outline-success ig-consultar" id="btn_consultar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar</button>
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
