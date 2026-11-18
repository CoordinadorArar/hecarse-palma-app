<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<?php if ($tablas_existen): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@geoman-io/leaflet-geoman-free@2.17.0/dist/leaflet-geoman.css" integrity="sha384-nfYqa/3Xh7kCl9/bO8fCcmqGuhuVWAle+CQgdE/LdgaQiO6fSkKh8C0fLdCVrrQJ" crossorigin="anonymous">
<?php endif; ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Lotes / Mapeo de palmas</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Lotes / Mapeo de palmas</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (! $tablas_existen): ?>
            <div class="gp-nota-alerta d-flex gap-2" role="alert" id="alerta_tablas">
                <i class="bi bi-exclamation-triangle"></i>
                <div><?= esc($mensaje_tablas) ?></div>
            </div>
        <?php else: ?>

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="empresa">Empresa</label>
                        <select class="form-select" id="empresa">
                            <?php foreach ($empresas as $e): ?>
                                <option value="<?= (int) $e['id'] ?>" <?= (int) $e['id'] === (int) $empresa_sel ? 'selected' : '' ?>><?= esc(trim($e['razonSocial'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="finca">Finca</label>
                        <select class="form-select" id="finca">
                            <option value="">Seleccione&hellip;</option>
                            <?php foreach ($fincas as $f): ?>
                                <option value="<?= esc($f['codigo'], 'attr') ?>" <?= count($fincas) === 1 ? 'selected' : '' ?>><?= esc($f['codigo'] . ' — ' . $f['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="lote">Lote</label>
                        <div class="input-group gp-select-buscador">
                            <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                            <select class="form-select" id="lote">
                                <option value=""></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="buscar_palma">Buscar palma</label>
                        <form class="input-group has-validation" id="form_buscar" novalidate>
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control font-monospace" id="buscar_palma" inputmode="decimal" placeholder="Ej. 12.34" autocomplete="off" disabled>
                            <button type="submit" class="btn btn-outline-success" id="btn_buscar" aria-label="Buscar palma" title="Buscar palma" disabled><i class="bi bi-arrow-right"></i></button>
                            <div class="invalid-feedback" id="buscar_error"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3 d-none" id="kpis"></div>

        <div class="card mb-0 gp-mapa-card">
            <div class="card-header bg-white py-2 d-flex flex-wrap align-items-center gap-2 gp-mapa-barra">
                <div class="btn-group" role="group" aria-label="Modo de trabajo">
                    <button type="button" class="btn btn-success btn-sm" data-modo="seleccionar" aria-pressed="true" aria-label="Seleccionar" title="Seleccionar"><i class="bi bi-cursor"></i><span class="d-none d-sm-inline ms-1">Seleccionar</span></button>
                    <button type="button" class="btn btn-outline-success btn-sm" data-modo="agregar" aria-pressed="false" aria-label="Agregar palmas" title="Agregar palmas" disabled><i class="bi bi-plus-circle"></i><span class="d-none d-sm-inline ms-1">Agregar palmas</span></button>
                    <button type="button" class="btn btn-outline-success btn-sm" data-modo="mover" aria-pressed="false" aria-label="Mover" title="Mover" disabled><i class="bi bi-arrows-move"></i><span class="d-none d-sm-inline ms-1">Mover</span></button>
                    <button type="button" class="btn btn-outline-success btn-sm" data-modo="poligono" aria-pressed="false" aria-label="Polígono" title="Polígono" disabled><i class="bi bi-pentagon"></i><span class="d-none d-sm-inline ms-1" id="texto_poligono">Polígono</span></button>
                </div>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0 small">
                        <input class="form-check-input" type="checkbox" id="ver_erradicadas" checked>
                        <label class="form-check-label" for="ver_erradicadas">Erradicadas</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_encuadrar" aria-label="Encuadrar lote" title="Encuadrar lote" disabled><i class="bi bi-arrows-angle-contract"></i><span class="d-none d-sm-inline ms-1">Encuadrar lote</span></button>
                </div>
            </div>
            <div class="card-body p-0 d-flex gp-mapa-cuerpo">
                <div class="gp-mapa-lienzo">
                    <div id="mapa_lote" role="region" tabindex="0" aria-label="Mapa satelital de la finca"></div>
                    <div class="gp-mapa-modo d-none" id="banner_modo" role="status"></div>
                    <div class="gp-mapa-cargando d-none" id="mapa_cargando">
                        <div class="text-center">
                            <div class="spinner-border text-success" role="status"></div>
                            <div class="small text-muted mt-2" id="mapa_cargando_texto">Cargando…</div>
                        </div>
                    </div>
                    <div class="gp-mapa-cargando gp-mapa-error d-none" id="mapa_error">
                        <div class="text-center px-3">
                            <div class="gp-medallon chico gp-ppto-error d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi bi-exclamation-triangle"></i></div>
                            <h6 class="mb-1">No se pudo cargar el lote</h6>
                            <p class="small text-muted mb-3" id="mapa_error_texto"></p>
                            <button type="button" class="btn btn-outline-success btn-sm" data-accion="reintentar"><i class="bi bi-arrow-clockwise me-1"></i>Reintentar</button>
                        </div>
                    </div>
                </div>
                <aside class="gp-mapa-panel" id="panel_mapa" aria-label="Información del lote">
                    <button type="button" class="gp-mapa-asa" id="asa_panel" aria-label="Mostrar u ocultar el panel" aria-expanded="true"><span></span></button>
                    <div id="panel_contenido"></div>
                </aside>
            </div>
        </div>

        <div class="visually-hidden" aria-live="polite" id="mapa_anuncio"></div>

        <?php endif; ?>
    </section>

</main>

<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const TABLAS_EXISTEN = <?= $tablas_existen ? 'true' : 'false' ?>;
</script>
<?php if ($tablas_existen): ?>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@geoman-io/leaflet-geoman-free@2.17.0/dist/leaflet-geoman.js" integrity="sha384-7CTDkMmRSjHvjl8ftuwzLq/ogrEnsuNoi3825eH06KuMNWWwcfaNIHol5+I3EAzO" crossorigin="anonymous"></script>
<script src="<?= asset_url('public/assets/js/comun/palmas_mapa.js') ?>"></script>
<script src="<?= asset_url('public/assets/js/gestionpalma/lotes_mapa.js') ?>"></script>
<script src="<?= asset_url('public/assets/js/tutoriales/modulos/gestionpalma/lotes_mapa.js') ?>"></script>
<?php endif; ?>
<?php echo $this->endSection(); ?>
