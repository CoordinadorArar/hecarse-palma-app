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
        <h1>Secciones / Bloques</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Secciones / Bloques</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">

                    <div class="col-md-3">
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
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_finca">Finca</label>
                        <select class="form-select" id="filtro_finca">
                            <option value="" selected>Todas</option>
                            <?php foreach ($fincas as $finca): ?>
                                <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['descripcion']) ?></option>
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
                <div>
                    <span id="contador_secciones" class="gp-contador d-block">
                        <i class="bi bi-grid-1x2 me-1"></i><strong><?= esc($total_secciones) ?></strong> <?= (int) $total_secciones === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                    </span>
                    <span id="contador_hectareas" class="gp-subtexto <?= $ha_asignadas === null ? 'd-none' : '' ?>"></span>
                </div>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_secciones" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:100px">Código</th>
                                <th>Finca</th>
                                <th>Descripción</th>
                                <th class="text-end" style="width:130px">Hectáreas brutas</th>
                                <th class="text-center" style="width:90px" title="Registros asociados a la sección">Uso</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:130px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_secciones">
                            <?= $tabla_secciones ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-grid-1x2"></i>
                    </div>
                    <h6 id="gp_estado_vacio_titulo">No se encontraron secciones</h6>
                    <p class="text-muted" id="gp_estado_vacio_texto">Ajuste los filtros de finca o estado, o registre una nueva sección.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nueva sección
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Sección -->
    <div class="modal fade" id="modalSeccion" tabindex="-1" aria-labelledby="titulo_modal_seccion" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_seccion">
                        <i class="bi bi-grid-1x2 me-1"></i>Nueva sección
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_seccion">
                    <input type="hidden" id="seccion_modo" value="crear">
                    <input type="hidden" id="seccion_empresa_pk">
                    <input type="hidden" id="seccion_finca_pk">
                    <input type="hidden" id="seccion_codigo_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-hash"></i>Identificación</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="seccion_finca">Finca <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-tree"></i></span>
                                    <select class="form-select" id="seccion_finca" required>
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($fincas as $finca): ?>
                                            <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="seccion_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control font-monospace" id="seccion_codigo" maxlength="50" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="seccion_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>La finca y el código no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-card-text"></i>Descripción y extensión</h6>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="seccion_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="seccion_descripcion" maxlength="550" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="seccion_hbrutas">Hectáreas brutas</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-rulers"></i></span>
                                    <input type="number" class="form-control text-end" id="seccion_hbrutas" step="0.01" min="0">
                                    <span class="input-group-text bg-light">ha</span>
                                </div>
                                <div class="form-text">Opcional. Déjelo vacío si aún no se ha medido.</div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="seccion_activo" checked>
                                    <label class="form-check-label" for="seccion_activo">Activo</label>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_seccion">
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
<script src="<?= base_url('public/assets/js/gestionpalma/secciones.js') ?>"></script>
<?php echo $this->endSection(); ?>
