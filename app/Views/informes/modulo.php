<?php echo $this->extend('template/layout'); ?>
<?php
$modulo_nombre = $modulo['nombre'];
$informes      = array_map(static function ($informe) use ($modulo) {
    $planes = array_values(array_filter(
        glob(ROOTPATH . 'Documentacion/Informes/*-' . $informe['clave'] . '.md') ?: [],
        static fn ($ruta) => str_contains(basename($ruta), $modulo['clave'])
    ));

    return $informe + ['plan' => $planes ? basename($planes[0]) : ''];
}, $informes);
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/informes.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1><?= esc($modulo_nombre) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('informes/onboarding/' . $id_loseta), 'attr') ?>">Informes</a></li>
                <li class="breadcrumb-item active"><?= esc($modulo_nombre) ?></li>
            </ol>
        </nav>
    </div>

    <div class="card mb-3 gp-filtros">
        <div class="card-body py-3 px-4">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (string) $empresa['id'] === (string) $empresa_sel ? 'selected' : '' ?>><?= esc($empresa['razonSocial']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-8 col-lg-6">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="selector_informe">Informe</label>
                    <div class="input-group flex-nowrap">
                        <span class="input-group-text"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
                        <div class="flex-grow-1 inf-selector">
                            <select class="form-select" id="selector_informe"><option value=""></option></select>
                        </div>
                    </div>
                </div>
                <div class="d-none d-lg-block col-lg-3 text-lg-end">
                    <span class="gp-contador" id="contador_informes"></span>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="empresa" value="<?= esc($empresa_sel, 'attr') ?>">

    <div id="estado_inicial"></div>

    <div class="card mb-3 d-none" id="card_informe">
        <div class="card-header bg-white py-3 d-flex align-items-start gap-3">
            <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i id="informe_icono" aria-hidden="true"></i></span>
            <div>
                <h5 class="mb-0 fw-semibold" id="informe_nombre"></h5>
                <div class="gp-subtexto" id="informe_descripcion"></div>
            </div>
            <span class="ms-auto" id="informe_chip"></span>
        </div>
        <div class="card-body" id="informe_cuerpo"></div>
    </div>

    <div class="d-none" id="zona_resultados" aria-live="polite">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="gp-contador" id="contador_registros"></span>
            <span class="d-flex flex-wrap gap-1" id="chips_filtros"></span>
            <span class="gp-chip-fila ambar d-none" id="chip_desactualizado"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Filtros modificados · consulte de nuevo</span>
            <button type="button" class="btn btn-outline-success ms-auto inf-exportar" id="btn_exportar" disabled>
                <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Exportar a Excel
            </button>
        </div>
        <div id="resultado_cuerpo"></div>
    </div>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const INFORMES = <?= json_encode(['modulo' => $modulo['clave'], 'nombre' => $modulo_nombre, 'seleccionado' => $informe_sel ?? '', 'informes' => $informes], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= base_url('public/assets/js/informes/modulo.js') ?>"></script>
<?php echo $this->endSection(); ?>
