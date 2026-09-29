<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Fincas</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Fincas</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">

                    <div class="col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (int) $empresa['id'] === (int) $empresa_sel ? 'selected' : '' ?>>
                                    <?= esc($empresa['razonSocial']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_estado">Estado</label>
                        <select class="form-select" id="filtro_estado">
                            <option value="" selected>Todos</option>
                            <option value="1">Activos</option>
                            <option value="0">Inactivos</option>
                        </select>
                    </div>

                    <div class="col-md-12 col-lg-auto ms-lg-auto">
                        <button type="button" class="btn btn-success w-100" id="btn_nuevo">
                            <i class="bi bi-plus-lg me-1"></i>Nuevo
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span id="contador_fincas" class="gp-contador">
                    <i class="bi bi-tree me-1"></i><strong><?= esc($total_fincas) ?></strong> <?= (int) $total_fincas === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_fincas" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:110px">Código</th>
                                <th>Finca</th>
                                <th style="width:220px">Propietario</th>
                                <th style="width:200px">Ubicación</th>
                                <th class="text-end" style="width:110px">Hectáreas</th>
                                <th class="text-center" style="width:80px">CO</th>
                                <th class="text-center" style="width:90px" title="Registros asociados a la finca">Uso</th>
                                <th class="text-center" style="width:130px">Marcas</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:130px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_fincas">
                            <?= $tabla_fincas ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-tree"></i>
                    </div>
                    <h6>No se encontraron fincas</h6>
                    <p class="text-muted">Ajuste el filtro de estado o registre una nueva finca.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nueva finca
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Finca -->
    <div class="modal fade" id="modalFinca" tabindex="-1" aria-labelledby="titulo_modal_finca" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_finca">
                        <i class="bi bi-tree me-1"></i>Nueva finca
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_finca">
                    <input type="hidden" id="finca_modo" value="crear">
                    <input type="hidden" id="finca_empresa_pk">
                    <input type="hidden" id="finca_codigo_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-hash"></i>Identificación</h6>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="finca_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control font-monospace" id="finca_codigo" maxlength="50" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_codigo_eq">Código equivalencia</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-arrow-left-right"></i></span>
                                    <input type="text" class="form-control font-monospace" id="finca_codigo_eq" maxlength="20" autocomplete="off">
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="finca_descripcion" maxlength="950" required>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="finca_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>La empresa y el código no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-person-vcard"></i>Propiedad y extensión</h6>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_propietario">Propietario</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person-vcard"></i></span>
                                    <select class="form-select" id="finca_propietario">
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($propietarios as $propietario): ?>
                                            <option value="<?= esc($propietario['id'], 'attr') ?>"><?= esc($propietario['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_hectareas">Hectáreas <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-rulers"></i></span>
                                    <input type="number" class="form-control text-end" id="finca_hectareas" step="0.01" min="0" required>
                                    <span class="input-group-text bg-light">ha</span>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-geo-alt"></i>Ubicación y operación</h6>
                            </div>

                            <div class="col-md-6 position-relative">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_ciudad_buscar">Ciudad</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control" id="finca_ciudad_buscar" autocomplete="off"
                                        placeholder="Escriba al menos 2 letras del municipio">
                                    <button type="button" class="btn btn-outline-secondary d-none" id="finca_ciudad_limpiar" title="Limpiar ciudad">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="finca_ciudad">
                                <div class="form-text text-success d-none" id="finca_ciudad_ok">
                                    <i class="bi bi-check-circle me-1"></i>Ciudad seleccionada.
                                </div>
                                <div class="form-text text-danger d-none" id="finca_ciudad_error">Seleccione una ciudad de la lista</div>
                                <div id="finca_ciudad_resultados" class="list-group position-absolute w-100 shadow-sm gp-typeahead" style="z-index:1050;"></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_zona">Zona geográfica</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-map"></i></span>
                                    <input type="text" class="form-control" id="finca_zona" maxlength="100">
                                </div>
                                <div class="form-text">Texto libre. Ej.: Zona Centro.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="finca_centro_operacion">Centro operación</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-diagram-3"></i></span>
                                    <input type="text" class="form-control font-monospace" id="finca_centro_operacion" maxlength="10" autocomplete="off">
                                </div>
                                <div class="form-text">Texto libre. Ej.: 002, 003</div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-tags"></i>Clasificación</h6>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="finca_activo" checked>
                                        <label class="form-check-label" for="finca_activo">Activo</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="finca_interna">
                                        <label class="form-check-label" for="finca_interna">Interna</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="finca_socio">
                                        <label class="form-check-label" for="finca_socio">Socio</label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_finca">
                            <i class="bi bi-floppy me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<?php echo $this->section('scripts'); ?>
<script> const BASE_URL = "<?= base_url() ?>"; </script>
<script src="<?= base_url('public/assets/js/gestionpalma/fincas.js') ?>"></script>
<?php echo $this->endSection(); ?>
