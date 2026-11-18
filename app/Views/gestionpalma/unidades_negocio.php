<?php echo $this->extend('template/layout'); ?>
<?php
$disponible = ! isset($disponible) || ! empty($disponible);
$fincas = $fincas ?? [];
$filas = $filas ?? '';
$total_unidades = (int) ($total ?? substr_count($filas, '<tr'));
$fincas_grilla = array_merge(
    array_filter($fincas, static fn ($f) => (int) ($f['activo'] ?? 1) === 1),
    array_filter($fincas, static fn ($f) => (int) ($f['activo'] ?? 1) !== 1)
);
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Unidades de negocio</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Unidades de negocio</li>
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
                <h6 class="mb-1">Unidades de negocio no disponibles</h6>
                <p class="text-muted mb-0"><?= esc($mensaje_disponible ?? 'Las tablas de unidades de negocio aún no existen en la base de datos. Solicite al administrador ejecutar el script de creación.') ?></p>
            </div>
        </div>
        <?php else: ?>

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
                <span id="contador_unidades" class="gp-contador" aria-live="polite">
                    <i class="bi bi-boxes me-1"></i><strong><?= esc($total_unidades) ?></strong> <?= $total_unidades === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div id="gp_contenedor_tabla">
                    <table id="tabla_unidades" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:100px">Código</th>
                                <th>Descripción</th>
                                <th style="min-width:280px">Fincas</th>
                                <th class="text-center" style="width:90px" title="Lotes que usan la unidad de negocio">Lotes</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:130px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_unidades">
                            <?= $filas ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <h6 id="gp_estado_vacio_titulo">No se encontraron unidades de negocio</h6>
                    <p class="text-muted" id="gp_estado_vacio_texto">Ajuste los filtros de finca o estado, o registre una nueva unidad de negocio.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nueva unidad de negocio
                    </button>
                </div>

            </div>
        </div>

        <?php endif; ?>

    </section>

    <?php if ($disponible): ?>
    <div class="modal fade" id="modalUnidadNegocio" tabindex="-1" aria-labelledby="titulo_modal_unidad" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_unidad">
                        <i class="bi bi-boxes me-1"></i>Nueva unidad de negocio
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_unidad">
                    <input type="hidden" id="unidad_modo" value="crear">
                    <input type="hidden" id="unidad_empresa_pk">
                    <input type="hidden" id="unidad_codigo_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-hash"></i>Identificación</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="unidad_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control font-monospace text-uppercase" id="unidad_codigo" maxlength="10" autocomplete="off" required>
                                </div>
                                <div class="form-text">Hasta 10 caracteres, sin espacios. Ej.: PAL.</div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="unidad_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="unidad_descripcion" maxlength="100" placeholder="Ej. Palma" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="unidad_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>El código no se puede modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-tree"></i>Fincas donde se maneja</h6>
                            </div>

                            <?php if ($fincas_grilla === []): ?>
                            <div class="col-12">
                                <div class="gp-nota-alerta">
                                    <i class="bi bi-exclamation-triangle me-1"></i>La empresa no tiene fincas registradas. Regístrelas en Gestión Palma &rsaquo; Fincas.
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span id="unidad_fincas_contador" class="gp-contador"><strong>0</strong> de <?= count($fincas_grilla) ?> fincas seleccionadas</span>
                                <div class="d-flex gap-3">
                                    <button type="button" class="btn btn-link btn-sm p-0" id="btn_unidad_marcar_todas"><i class="bi bi-check2-square me-1"></i>Marcar todas</button>
                                    <button type="button" class="btn btn-link btn-sm p-0" id="btn_unidad_quitar_todas"><i class="bi bi-square me-1"></i>Quitar todas</button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="col-12 d-none" id="unidad_aviso_bloqueo">
                                <div class="gp-nota">
                                    <i class="bi bi-lock me-1"></i>Las fincas con candado tienen lotes con esta unidad de negocio; para quitarlas, cambie primero la unidad de esos lotes en Lotes / Registro.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="row g-2" id="unidad_fincas">
                                    <?php foreach (array_values($fincas_grilla) as $i => $finca): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="gp-panel-opcion gp-opcion-finca d-flex align-items-start gap-2 h-100 mb-0" for="unidad_finca_<?= $i ?>">
                                            <input class="form-check-input mt-1 flex-shrink-0 unidad-finca" type="checkbox" id="unidad_finca_<?= $i ?>" value="<?= esc(trim((string) $finca['codigo']), 'attr') ?>">
                                            <span class="flex-grow-1 text-truncate">
                                                <span class="d-block small fw-semibold text-truncate"><?= esc($finca['descripcion']) ?></span>
                                                <span class="gp-subtexto unidad-finca-detalle">Cód. <?= esc(trim((string) $finca['codigo'])) ?></span>
                                            </span>
                                            <?php if ((int) ($finca['activo'] ?? 1) !== 1): ?>
                                            <span class="gp-chip-fila gris flex-shrink-0">Inactiva</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="unidad_activo" checked>
                                    <label class="form-check-label" for="unidad_activo">Activo</label>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_unidad">
                            <i class="bi bi-floppy me-1"></i>Guardar
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
<script src="<?= asset_url('public/assets/js/gestionpalma/unidades_negocio.js') ?>"></script>
<script src="<?= asset_url('public/assets/js/tutoriales/modulos/gestionpalma/unidades_negocio.js') ?>"></script>
<?php echo $this->endSection(); ?>
<?php endif; ?>
