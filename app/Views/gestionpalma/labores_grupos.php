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
        <h1>Grupos de labor</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Grupos de labor</li>
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
                <span id="contador_grupos" class="gp-contador">
                    <i class="bi bi-collection me-1"></i><strong><?= esc($total_grupos) ?></strong> <?= (int) $total_grupos === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_grupos" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:100px">Código</th>
                                <th>Descripción</th>
                                <th style="width:220px">Centro de costo</th>
                                <th class="text-center" style="width:100px" title="Labores que usan el grupo">Labores</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:130px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_grupos">
                            <?= $tabla_grupos ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-collection"></i>
                    </div>
                    <h6>No se encontraron grupos de labor</h6>
                    <p class="text-muted">Ajuste el filtro de estado o registre un nuevo grupo.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo grupo
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Grupo de labor -->
    <div class="modal fade" id="modalGrupo" tabindex="-1" aria-labelledby="titulo_modal_grupo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_grupo">
                        <i class="bi bi-collection me-1"></i>Nuevo grupo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_grupo">
                    <input type="hidden" id="grupo_modo" value="crear">
                    <input type="hidden" id="grupo_empresa_pk">
                    <input type="hidden" id="grupo_codigo_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="grupo_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="grupo_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="grupo_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control font-monospace" id="grupo_codigo" maxlength="2" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="grupo_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="grupo_descripcion" maxlength="50" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="grupo_ccosto">Centro de costo</label>
                                <div class="input-group gp-select-buscador">
                                    <span class="input-group-text bg-light"><i class="bi bi-diagram-3"></i></span>
                                    <select class="form-select" id="grupo_ccosto">
                                        <option value=""></option>
                                        <?php foreach ($centros_costo as $ccosto): ?>
                                            <option value="<?= esc($ccosto['codigo'], 'attr') ?>"><?= esc($ccosto['codigo']) ?> &mdash; <?= esc($ccosto['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="grupo_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>La empresa y el código no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="grupo_activo" checked>
                                    <label class="form-check-label" for="grupo_activo">Activo</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="grupo_maneja_ccosto_siigo">
                                    <label class="form-check-label" for="grupo_maneja_ccosto_siigo">Maneja centro de costo Siigo</label>
                                </div>
                                <div class="gp-subtexto">Campo heredado del sistema anterior; no se usa en reportes ni integraciones actuales.</div>
                            </div>

                            <div class="col-md-6 d-none" id="grupo_col_ccosto_siigo">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="grupo_ccosto_siigo">Centro de costo Siigo</label>
                                <div class="input-group gp-select-buscador">
                                    <select class="form-select" id="grupo_ccosto_siigo">
                                        <option value=""></option>
                                        <?php foreach ($centros_costo as $ccosto): ?>
                                            <option value="<?= esc($ccosto['codigo'], 'attr') ?>"><?= esc($ccosto['codigo']) ?> &mdash; <?= esc($ccosto['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_grupo">
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
<script src="<?= base_url('public/assets/js/gestionpalma/labores_grupos.js') ?>"></script>
<?php echo $this->endSection(); ?>
