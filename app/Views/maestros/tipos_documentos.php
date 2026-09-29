<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/maestros.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Tipos de documento</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('maestros/onboarding/' . $id_loseta), 'attr') ?>">Maestros</a></li>
                <li class="breadcrumb-item">Parámetros administración</li>
                <li class="breadcrumb-item active">Tipos de documento</li>
            </ol>
        </nav>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3 px-4">
            <div class="row g-2 align-items-end">

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (int) $empresa['id'] === (int) $empresa_sel ? 'selected' : '' ?>>
                                    <?= esc($empresa['razonSocial']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-text">Los tipos de documento se guardan por empresa.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" id="filtro_busqueda" autocomplete="off"
                            placeholder="Código, descripción o abreviatura">
                        <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="form-text">Filtra la lista mientras escribe.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <h6 class="mb-0 maestros-titulo-tarjeta"><i class="bi bi-card-list me-1" aria-hidden="true"></i>Tipos de documento de la empresa</h6>
            <span id="contador_registros" class="maestros-contador" aria-live="polite"></span>
            <button type="button" class="btn btn-success btn-sm" id="btn_nuevo">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo tipo
            </button>
        </div>

        <div class="card-body p-0">

            <div class="maestros-nota m-3 mb-0">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>El código identifica el registro: no se puede modificar después de crearlo y admite máximo 3 caracteres, porque así lo guarda el maestro de Terceros.</span>
            </div>

            <div class="maestros-nota m-3 mb-0 d-none" id="aviso_sin_equivalencia">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>Ningún tipo de documento de esta empresa tiene equivalencia registrada. Es un campo opcional.</span>
            </div>

            <div class="maestros-grilla-relativa" id="zona_grilla">

                <div class="table-responsive d-none" id="contenedor_tabla">
                    <table id="tabla_tipos_documentos" class="table table-striped table-hover align-middle mb-0 maestros-tabla maestros-tabla-media" style="width:100%">
                        <thead class="maestros-thead">
                            <tr>
                                <th class="text-center" style="width:90px">Código</th>
                                <th style="min-width:280px">Descripción</th>
                                <th class="text-center" style="width:120px">Abreviatura</th>
                                <th class="text-center" style="width:110px">Código TD</th>
                                <th class="text-center" style="width:120px">Maneja NIT</th>
                                <th style="width:150px">Equivalencia</th>
                                <th class="text-center" style="width:110px">Terceros</th>
                                <th class="text-center" style="width:110px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_tipos"></tbody>
                    </table>
                </div>

                <div class="maestros-estado-vacio d-none" id="estado_vacio">
                    <div class="maestros-medallon"><i class="bi bi-inbox" aria-hidden="true"></i></div>
                    <h6 class="mb-1">Esta empresa no tiene tipos de documento</h6>
                    <p class="mb-3">Cree el primero para poder asignarlo en el maestro de Terceros.</p>
                    <button type="button" class="btn btn-success" id="btn_crear_primero">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear el primero
                    </button>
                </div>

                <div class="maestros-estado-vacio d-none" id="estado_filtro">
                    <div class="maestros-medallon chico"><i class="bi bi-search" aria-hidden="true"></i></div>
                    <h6 class="mb-1" id="titulo_estado_filtro"></h6>
                    <p class="mb-3">Revise el texto o limpie la búsqueda.</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_limpiar_filtro">
                        <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Limpiar búsqueda
                    </button>
                </div>

                <div class="maestros-estado-vacio d-none" id="estado_error">
                    <div class="maestros-medallon chico alerta"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
                    <h6 class="mb-1">No se pudieron cargar los tipos de documento</h6>
                    <p class="mb-3" id="mensaje_estado_error" aria-live="polite"></p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reintentar">
                        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar
                    </button>
                </div>

            </div>

        </div>

        <div class="card-footer bg-white py-2 d-none" id="pie_tabla"></div>
    </div>

    <div class="modal fade" id="modalTipoDocumento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2">
                        <span id="titulo_modal"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo tipo de documento</span>
                        <span class="maestros-chip-entidad maestros-chip-texto" id="chip_empresa"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_tipo" novalidate>
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-card-text" aria-hidden="true"></i>Identificación</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace" id="codigo" maxlength="3" autocomplete="off" placeholder="13">
                                    <span class="input-group-text bg-light d-none" id="candado_codigo"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text" id="ayuda_codigo">Máximo 3 caracteres. Es el valor que queda guardado en cada tercero.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="descripcionCorta">Abreviatura <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-tag" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control maestros-campo-corto" id="descripcionCorta" maxlength="50" autocomplete="off" placeholder="CC">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Texto corto que se muestra en listados y documentos.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-card-text" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="descripcion" maxlength="250" autocomplete="off" placeholder="Cédula de ciudadanía">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-sliders" aria-hidden="true"></i>Parámetros</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="codigoTD">Código TD <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-123" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control maestros-campo-mini" id="codigoTD" value="0" maxlength="10" inputmode="numeric" autocomplete="off">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Valor numérico heredado del sistema anterior. Si no aplica, déjelo en 0.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="equivalencia">Equivalencia</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace" id="equivalencia" maxlength="50" autocomplete="off">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Opcional. Código equivalente en otro sistema.</div>
                            </div>

                            <div class="col-12">
                                <div class="maestros-panel-opcion" id="panel_mnit">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="mNit">
                                        <label class="form-check-label" for="mNit">Maneja NIT</label>
                                    </div>
                                    <div class="form-text mb-0">Actívelo cuando el documento sea un NIT.</div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2 justify-content-between">
                        <small class="text-muted"><span class="text-danger">*</span> Campos obligatorios.</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" id="btn_guardar">
                                <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
</script>
<script src="<?= base_url('public/assets/js/maestros/tipos_documentos.js') ?>"></script>
<?php echo $this->endSection(); ?>
