<?php echo $this->extend('template/layout'); ?>
<?php $disponible = ! isset($tabla_disponible) || ! empty($tabla_disponible); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Motivos de ausencia</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Motivos de ausencia</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <?php if (! $disponible): ?>
        <div class="card">
            <div class="card-body gp-estado-vacio text-center py-5">
                <div class="gp-medallon gp-ppto-error d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                    <i class="bi bi-database-exclamation" aria-hidden="true"></i>
                </div>
                <h6 class="mb-1">Motivos de ausencia no disponibles</h6>
                <p class="text-muted mb-0"><?= esc($mensaje_disponible ?? 'El maestro de motivos de ausencia no está disponible.') ?></p>
            </div>
        </div>
        <?php else: ?>

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">

                    <div class="col-md-5 col-lg-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                            <select class="form-select" id="filtro_empresa">
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (int) $empresa['id'] === (int) $empresa_sel ? 'selected' : '' ?>><?= esc($empresa['razonSocial']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo motivo
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span id="contador_motivos" class="gp-contador" aria-live="polite">
                    <i class="bi bi-person-x me-1" aria-hidden="true"></i><strong><?= esc($total_motivos ?? 0) ?></strong> <?= (int) ($total_motivos ?? 0) === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div id="gp_contenedor_tabla">
                    <table id="tabla_motivos" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:110px">Código</th>
                                <th>Descripción</th>
                                <th class="text-center" style="width:110px" title="Ausencias que usan el motivo">Uso</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:130px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_motivos">
                            <?= $tabla_motivos ?? '' ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-person-x" aria-hidden="true"></i>
                    </div>
                    <h6>No se encontraron motivos de ausencia</h6>
                    <p class="text-muted">Ajuste el filtro de estado o registre un nuevo motivo.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo motivo
                    </button>
                </div>

            </div>
        </div>

        <?php endif; ?>

    </section>

    <?php if ($disponible): ?>
    <div class="modal fade" id="modalMotivo" tabindex="-1" aria-labelledby="titulo_modal_motivo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_motivo">
                        <i class="bi bi-person-x me-1" aria-hidden="true"></i>Nuevo motivo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_motivo" novalidate>
                    <input type="hidden" id="mo_modo" value="crear">
                    <input type="hidden" id="mo_empresa_pk">
                    <input type="hidden" id="mo_codigo_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="mo_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building" aria-hidden="true"></i></span>
                                    <select class="form-select" id="mo_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="mo_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace text-uppercase" id="mo_codigo" maxlength="10" autocomplete="off" required>
                                    <div class="invalid-feedback">Ingrese el código.</div>
                                </div>
                                <div class="form-text">Ej.: INC, PER, VAC.</div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="mo_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="mo_descripcion" maxlength="100" placeholder="Ej. Incapacidad médica" autocomplete="off" required>
                                    <div class="invalid-feedback">Ingrese la descripción.</div>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="mo_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>La empresa y el código no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="mo_activo" checked>
                                    <label class="form-check-label" for="mo_activo">Activo</label>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_motivo">
                            <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<?php echo $this->endSection(); ?>

<?php if ($disponible): ?>
<?php echo $this->section('scripts'); ?>
<script> const BASE_URL = "<?= base_url() ?>"; </script>
<script src="<?= asset_url('public/assets/js/gestionpalma/motivos_ausencia.js') ?>"></script>
<?php echo $this->endSection(); ?>
<?php endif; ?>
