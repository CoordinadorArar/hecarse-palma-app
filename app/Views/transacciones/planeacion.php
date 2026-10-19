<?php echo $this->extend('template/layout'); ?>
<?php
$permisos   = $permisos ?? ['registrar' => false, 'consultar' => false, 'editar' => false, 'eliminar' => false];
$disponible = ! empty($tablas_disponibles);
$destinos   = $registrar_destino ?? ['labores' => false, 'fertilizacion' => false, 'produccion' => false];
$verSugerencias = ! empty($permisos['consultar']) || ! empty($permisos['registrar']);
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/transacciones.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/planeacion.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main pl-root">

    <div class="pagetitle">
        <h1>Planeación de labores</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('transacciones/onboarding/' . $id_loseta), 'attr') ?>">Transacciones</a></li>
                <li class="breadcrumb-item active">Planeación de labores</li>
            </ol>
        </nav>
    </div>

    <?php if (! $disponible): ?>
    <div class="card">
        <div class="card-body gp-estado-vacio text-center py-5">
            <div class="gp-medallon gp-ppto-error d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                <i class="bi bi-database-exclamation" aria-hidden="true"></i>
            </div>
            <h6 class="mb-1">Planeación no disponible</h6>
            <p class="text-muted mb-0"><?= esc($mensaje_tablas ?? 'La planeación de labores no está disponible.') ?></p>
        </div>
    </div>
    <?php else: ?>

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
                <div class="col-12 col-md-6 col-lg-5">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="fecha">Fecha del plan</label>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group pl-fecha-grupo">
                            <button type="button" class="btn btn-outline-secondary" id="btn_dia_anterior" title="Día anterior" aria-label="Día anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                            <input type="date" class="form-control text-center" id="fecha" value="<?= esc($fecha_defecto ?? '', 'attr') ?>">
                            <button type="button" class="btn btn-outline-secondary" id="btn_dia_siguiente" title="Día siguiente" aria-label="Día siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                        </div>
                        <span class="small text-muted text-nowrap" id="fecha_legible" aria-live="polite"></span>
                    </div>
                </div>
                <div class="col-12 col-md-auto ms-md-auto">
                    <ul class="nav nav-pills nav-fill flex-nowrap tx-pestanas" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link active" data-vista="lote" role="tab" aria-selected="true"><i class="bi bi-geo-alt" aria-hidden="true"></i>Por lote</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" data-vista="trabajador" role="tab" aria-selected="false"><i class="bi bi-people" aria-hidden="true"></i>Por trabajador</button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="empresa" value="<?= esc($empresa_sel, 'attr') ?>">

    <div class="tx-relativo" id="zona_plan">

        <div class="gp-barra-editor tx-barra d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-none" id="barra_plan">
            <div class="d-flex flex-wrap align-items-center gap-2 min-w-0">
                <span class="badge" id="badge_plan"></span>
                <span class="fw-semibold" id="barra_fecha"></span>
                <span class="gp-chip-fila ambar d-none" id="chip_filtro"></span>
                <button type="button" class="btn btn-link btn-sm p-0 d-none" id="btn_ver_todas">Ver todas</button>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <?php if ($verSugerencias): ?>
                <button type="button" class="btn btn-outline-secondary d-none d-md-inline-flex align-items-center gap-1" data-accion="sugerencias">
                    <i class="bi bi-lightbulb" aria-hidden="true"></i>Sugerencias <span class="tx-chip-cont" data-cont="sugerencias">0</span>
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary d-none d-md-inline-flex align-items-center gap-1" data-accion="pendientes">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>Pendientes <span class="tx-chip-cont" data-cont="pendientes">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary d-none d-md-inline-flex align-items-center gap-1" data-accion="ausentes">
                    <i class="bi bi-person-x" aria-hidden="true"></i>Ausentes <span class="tx-chip-cont" data-cont="ausentes">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary d-none d-md-inline-flex align-items-center gap-1" data-accion="imprimir">
                    <i class="bi bi-printer" aria-hidden="true"></i>Imprimir
                </button>
                <div class="dropdown d-md-none">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Más opciones"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($verSugerencias): ?>
                        <li><button type="button" class="dropdown-item" data-accion="sugerencias"><i class="bi bi-lightbulb me-2" aria-hidden="true"></i>Sugerencias <span class="tx-chip-cont" data-cont="sugerencias">0</span></button></li>
                        <?php endif; ?>
                        <li><button type="button" class="dropdown-item" data-accion="pendientes"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Pendientes <span class="tx-chip-cont" data-cont="pendientes">0</span></button></li>
                        <li><button type="button" class="dropdown-item" data-accion="ausentes"><i class="bi bi-person-x me-2" aria-hidden="true"></i>Ausentes <span class="tx-chip-cont" data-cont="ausentes">0</span></button></li>
                        <li><button type="button" class="dropdown-item" data-accion="imprimir"><i class="bi bi-printer me-2" aria-hidden="true"></i>Imprimir</button></li>
                    </ul>
                </div>
                <button type="button" class="btn btn-outline-success d-none" data-accion="planilla" aria-label="Planear finca">
                    <i class="bi bi-table me-sm-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Planear finca</span>
                </button>
                <button type="button" class="btn btn-success d-none" data-accion="agregar">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar actividad
                </button>
            </div>
        </div>

        <div class="row g-2 mb-3" id="kpis"></div>

        <div class="pl-ausentes mb-3 d-none" id="franja_ausentes"></div>

        <div class="gp-nota-alerta d-flex flex-wrap align-items-center gap-2 mb-3 d-none" id="aviso_pendientes" role="status">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span id="aviso_pendientes_texto"></span>
            <button type="button" class="btn btn-link btn-sm p-0" data-accion="pendientes">Revisar</button>
        </div>

        <div id="estado_plan"></div>

        <div id="vista_lote"></div>

        <div class="d-none" id="vista_trabajador">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2 py-3">
                    <div class="input-group pl-buscador">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="buscar_trabajador" placeholder="Código o nombre" aria-label="Buscar trabajador" autocomplete="off">
                    </div>
                    <span class="gp-contador ms-auto" id="contador_trabajadores" aria-live="polite"></span>
                </div>
                <div class="card-body p-0" id="lista_trabajadores"></div>
            </div>
        </div>

        <div class="gp-ppto-cargando d-none" id="cargando_plan" aria-hidden="true">
            <div class="spinner-border text-success" role="status"><span class="visually-hidden">Cargando…</span></div>
        </div>

        <div class="tx-acciones flex-wrap d-none" id="barra_inferior"></div>

    </div>

    <div class="pl-impresion d-none" id="impresion"></div>

    <div class="modal fade" id="modalActividad" tabindex="-1" aria-labelledby="titulo_actividad" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="titulo_actividad">
                        <span><i class="bi bi-plus-square me-1" aria-hidden="true" id="icono_actividad"></i><span id="texto_actividad">Agregar actividad</span></span>
                        <span class="gp-chip-fila verde" id="chip_fecha_actividad"></span>
                        <span class="gp-chip-fila tx-chip-neutro d-none" id="chip_duplicada"></span>
                        <span class="d-none" id="chip_sug_actividad"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_actividad" novalidate>
                    <div class="modal-body px-4 py-3">
                        <div class="gp-referencia mb-3 d-none" id="act_sugerencia"></div>
                        <h6 class="gp-seccion mb-3"><i class="bi bi-geo-alt" aria-hidden="true"></i>Ubicación y labor</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-4 d-none" id="col_act_fecha">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_fecha">Fecha destino <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                                    <input type="date" class="form-control" id="act_fecha">
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_act_fecha">Seleccione la fecha destino.</div>
                                <div class="form-text d-none" id="act_fecha_ayuda"></div>
                            </div>
                            <div class="col-12 col-md-6" id="col_act_finca">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_finca">Finca <span class="text-danger">*</span></label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="act_finca">
                                        <option value="">Seleccione una opción…</option>
                                        <?php foreach ($fincas ?? [] as $finca): ?>
                                            <option value="<?= esc(trim((string) $finca['codigo']), 'attr') ?>" data-nombre="<?= esc(trim((string) $finca['descripcion']), 'attr') ?>"><?= esc(trim((string) $finca['codigo'])) ?> — <?= esc(trim((string) $finca['descripcion'])) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_act_finca">Seleccione la finca.</div>
                            </div>
                            <div class="col-12 col-md-6" id="col_act_lote">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_lote">Lote <span class="text-danger">*</span></label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="act_lote" disabled>
                                        <option value="">Seleccione una finca primero</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_act_lote">Seleccione el lote.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_labor">Labor <span class="text-danger">*</span></label>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control font-monospace text-uppercase tx-labor-codigo" id="act_codigo" placeholder="Código" aria-label="Código de labor" autocomplete="off" maxlength="20">
                                    <div class="tx-select-ancho">
                                        <select class="form-select" id="act_labor">
                                            <option value="">Seleccione una opción…</option>
                                            <?php foreach ($labores ?? [] as $labor): ?>
                                                <option value="<?= esc(trim((string) $labor['codigo']), 'attr') ?>" data-um="<?= esc(trim((string) $labor['uMedida']), 'attr') ?>" data-grupo="<?= esc(trim((string) $labor['grupo']), 'attr') ?>" data-nombre="<?= esc(trim((string) $labor['descripcion']), 'attr') ?>"><?= esc(trim((string) $labor['codigo'])) ?> — <?= esc(trim((string) $labor['descripcion'])) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_act_labor">Seleccione la labor.</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_um">Unidad de medida <span class="text-danger">*</span></label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="act_um">
                                        <option value=""></option>
                                        <?php foreach ($unidades ?? [] as $unidad): ?>
                                            <option value="<?= esc(trim((string) $unidad['codigo']), 'attr') ?>"><?= esc(trim((string) $unidad['codigo'])) ?> — <?= esc(trim((string) $unidad['descripcion'])) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_act_um">Seleccione la unidad de medida.</div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_hora_inicio">Hora inicio</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock" aria-hidden="true"></i></span>
                                    <input type="time" class="form-control" id="act_hora_inicio">
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_hora_fin">Hora fin</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                    <input type="time" class="form-control" id="act_hora_fin">
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_act_horario">La hora fin debe ser posterior a la de inicio.</div>
                            </div>
                            <div class="col-12 col-md-4 d-none" id="col_pfa">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_pfa">Referencia PFA <span class="fw-normal">(opcional)</span></label>
                                <select class="form-select" id="act_pfa">
                                    <option value="">Sin referencia</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="act_observacion">Observación</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-signpost" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="act_observacion" maxlength="250" placeholder="Ej. LN 1 A LA 54" autocomplete="off">
                                </div>
                                <div class="form-text d-flex justify-content-end"><span class="tx-num" id="contador_act_observacion">0 / 250</span></div>
                            </div>
                        </div>

                        <h6 class="gp-seccion mb-3"><i class="bi bi-people" aria-hidden="true"></i>Trabajadores <span class="tx-chip-cont verde ms-1" id="act_contador">0</span></h6>
                        <div class="gp-nota-alerta mb-2 d-none" id="act_aviso_ausentes" role="status"></div>
                        <div class="tx-select-ancho">
                            <select class="form-select" id="act_terceros" multiple></select>
                        </div>
                        <div class="invalid-feedback d-block d-none" id="error_act_terceros">Seleccione al menos un trabajador.</div>
                        <div class="form-text mb-3">Código (ej. CR150), identificación o nombre. Mínimo 3 caracteres.</div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <span class="form-text m-0">Cantidad por trabajador opcional; el total es informativo.</span>
                            <div class="tx-reparto tx-reparto--inactivo pl-indicador" id="act_indicador"></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 gp-tabla tx-grilla pl-reparto">
                                <thead class="gp-thead">
                                    <tr>
                                        <th>Trabajador</th>
                                        <th class="text-end">Cantidad <span class="fw-normal text-muted">(opcional)</span></th>
                                        <th class="text-center"><span class="visually-hidden">Quitar</span></th>
                                    </tr>
                                </thead>
                                <tbody id="act_filas"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer py-2 justify-content-between">
                        <small class="text-muted d-none d-sm-inline"><span class="text-danger">*</span> Campos obligatorios</small>
                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar</button>
                            <button type="button" class="btn btn-outline-primary" id="btn_guardar_otra"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Guardar y agregar otra</button>
                            <button type="submit" class="btn btn-primary" id="btn_guardar_actividad" title="Ctrl+S"><i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade pl-planilla" id="modalPlanilla" tabindex="-1" aria-labelledby="titulo_planilla" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content p-2">
                <div class="modal-header py-2">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="titulo_planilla">
                        <span><i class="bi bi-table me-1" aria-hidden="true"></i>Planilla de la finca</span>
                        <span class="gp-chip-fila verde" id="pla_chip_fecha"></span>
                    </h5>
                    <button type="button" class="btn-close" data-pla="cancelar" aria-label="Cerrar"></button>
                </div>
                <div class="pl-planilla-cab">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-5 col-lg-4">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="pla_finca"><i class="bi bi-lock me-1 d-none" id="pla_candado" aria-hidden="true"></i>Finca <span class="text-danger">*</span></label>
                            <div class="tx-select-ancho">
                                <select class="form-select" id="pla_finca"></select>
                            </div>
                            <span class="gp-subtexto d-none" id="pla_fijada">Finca fijada desde el plan</span>
                        </div>
                        <div class="col-auto"><span class="gp-contador" id="pla_contador" aria-live="polite"></span></div>
                        <div class="col ms-md-auto">
                            <div class="d-flex flex-wrap gap-1 justify-content-md-end" id="pla_totales"></div>
                        </div>
                    </div>
                    <div class="gp-nota-alerta pl-planilla-resumen d-none" id="pla_resumen_errores" role="alert"></div>
                </div>
                <div class="modal-body p-0">
                    <div class="pl-planilla-scroll">
                        <div id="pla_vacio"></div>
                        <div class="d-none" id="pla_contenido">
                            <table class="table table-sm align-middle mb-0 gp-tabla tx-grilla pl-planilla-tabla">
                                <thead class="gp-thead pl-planilla-thead">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Lote <span class="text-danger">*</span></th>
                                        <th>Labor <span class="text-danger">*</span></th>
                                        <th>UM <span class="text-danger">*</span></th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                        <th>Trabajadores <span class="text-danger">*</span></th>
                                        <th>Observación</th>
                                        <th><span class="visually-hidden">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody id="pla_filas"></tbody>
                            </table>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-outline-success btn-sm m-3" id="pla_agregar_fila"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar fila</button>
                                <small class="text-muted d-none d-lg-inline">Ctrl+Enter agrega una fila</small>
                            </div>
                            <div id="pla_cerradas"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <small class="text-muted d-none d-sm-inline"><span class="text-danger">*</span> Campos obligatorios</small>
                    <div class="d-flex flex-wrap gap-2 ms-auto pl-acciones-fin">
                        <button type="button" class="btn btn-primary order-sm-2" id="pla_guardar" title="Ctrl+S" disabled></button>
                        <button type="button" class="btn btn-secondary order-sm-1" data-pla="cancelar"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditarTrabajador" tabindex="-1" aria-labelledby="titulo_editar_trabajador" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="titulo_editar_trabajador">
                        <span><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar trabajador</span>
                        <span class="gp-chip-fila tx-chip-neutro font-monospace" id="chip_editar_trabajador"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_editar_trabajador" novalidate>
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="edit_tercero">Tercero <span class="text-danger">*</span></label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="edit_tercero"></select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_edit_tercero"></div>
                                <div class="form-text">Código (ej. CR150), identificación o nombre. Mínimo 3 caracteres.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="edit_cantidad">Cantidad <span class="text-muted fw-normal" id="edit_um"></span></label>
                                <input type="text" class="form-control font-monospace text-end tx-num" id="edit_cantidad" placeholder="Opcional" inputmode="decimal" maxlength="14" autocomplete="off">
                                <div class="invalid-feedback d-block d-none" id="error_edit_cantidad">Use números mayores o iguales a cero con máximo 2 decimales.</div>
                                <span class="gp-subtexto tx-num mt-1" id="edit_resumen" aria-live="polite"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2 justify-content-between">
                        <small class="text-muted"><span class="text-danger">*</span> Campos obligatorios</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAusencia" tabindex="-1" aria-labelledby="titulo_ausencia" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="titulo_ausencia">
                        <span id="aus_titulo"></span>
                        <span class="pl-chip pl-e-ausente"><i class="bi bi-person-x" aria-hidden="true"></i>Ausente</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div class="gp-cascada" id="aus_pasos"></div>
                        <span class="gp-subtexto" id="aus_contador"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="aus_motivo">Motivo <span class="fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control" id="aus_motivo" maxlength="250" autocomplete="off" placeholder="Ej. Incapacidad, permiso">
                    </div>
                    <div id="aus_cuerpo"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary me-auto" id="aus_atras"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Atrás</button>
                    <button type="button" class="btn btn-primary" id="aus_siguiente"></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCierre" tabindex="-1" aria-labelledby="titulo_cierre" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="titulo_cierre">
                        <span>Cerrar actividad</span><span id="cierre_chip"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_cierre" novalidate>
                    <div class="modal-body px-4 py-3">
                        <div class="gp-referencia mb-3" id="cierre_referencia"></div>
                        <div class="mb-3" id="cierre_col_cantidad">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="cierre_cantidad">Cantidad ejecutada <span class="text-danger" id="cierre_marca">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace text-end tx-num" id="cierre_cantidad" inputmode="decimal" maxlength="14" autocomplete="off">
                                <span class="input-group-text" id="cierre_um"></span>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="error_cierre_cantidad"></div>
                            <span class="gp-subtexto tx-num mt-1" id="cierre_saldo" aria-live="polite"></span>
                        </div>
                        <div>
                            <label class="form-label mb-1 small fw-semibold text-muted" for="cierre_observacion">Observación <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="cierre_observacion" rows="3" maxlength="250"></textarea>
                            <div class="d-flex justify-content-between">
                                <div class="invalid-feedback d-block d-none" id="error_cierre_observacion">Indique el motivo del cierre.</div>
                                <span class="form-text tx-num ms-auto" id="contador_cierre">0 / 250</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar</button>
                        <button type="submit" class="btn" id="cierre_confirmar"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end pl-offcanvas" tabindex="-1" id="panelPendientes" aria-labelledby="titulo_pendientes">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title d-flex align-items-center gap-2" id="titulo_pendientes">
                <i class="bi bi-clock-history" aria-hidden="true"></i>Pendientes de días anteriores <span class="tx-chip-cont" data-cont="pendientes">0</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body" id="lista_pendientes"></div>
    </div>

    <?php if ($verSugerencias): ?>
    <div class="offcanvas offcanvas-end pl-offcanvas pl-offcanvas--ancho" tabindex="-1" id="panelSugerencias" aria-labelledby="titulo_sugerencias">
        <div class="offcanvas-header border-bottom">
            <div class="min-w-0">
                <h5 class="offcanvas-title d-flex align-items-center gap-2" id="titulo_sugerencias">
                    <i class="bi bi-lightbulb" aria-hidden="true"></i>Sugerencias de planeación <span class="tx-chip-cont" data-sug-cont="total" aria-live="polite">0</span>
                </h5>
                <span class="gp-subtexto" id="sug_subtitulo"></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav nav-pills tx-pestanas mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link active" id="sug_tab_lista" data-sug-tab="lista" role="tab" aria-selected="true" aria-controls="sug_panel_lista"><i class="bi bi-lightbulb" aria-hidden="true"></i>Sugerencias <span class="tx-chip-cont" data-sug-cont="total">0</span></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" id="sug_tab_historial" data-sug-tab="historial" role="tab" aria-selected="false" aria-controls="sug_panel_historial"><i class="bi bi-journal-check" aria-hidden="true"></i>Historial</button>
                </li>
            </ul>

            <div id="sug_panel_lista" role="tabpanel" aria-labelledby="sug_tab_lista">
                <div class="gp-nota-alerta mb-3 d-none" id="sug_aviso" role="status"></div>
                <?php if (empty($permisos['registrar'])): ?>
                <div class="gp-nota-alerta mb-3"><i class="bi bi-eye me-1" aria-hidden="true"></i>Solo lectura: no tiene permiso para planear.</div>
                <?php endif; ?>
                <div class="row g-2 align-items-end mb-3" id="sug_filtros">
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="sug_dias">Horizonte</label>
                        <div class="input-group input-group-sm has-validation">
                            <input type="number" class="form-control" id="sug_dias" min="1" max="90" value="15" inputmode="numeric">
                            <span class="input-group-text">días</span>
                            <div class="invalid-feedback">Ingrese entre 1 y 90 días.</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="sug_finca">Finca</label>
                        <select class="form-select form-select-sm" id="sug_finca"><option value="">Todas las fincas</option></select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="sug_grupo">Grupo</label>
                        <select class="form-select form-select-sm" id="sug_grupo"><option value="">Todos los grupos</option></select>
                    </div>
                    <div class="col-12 col-md-4">
                        <span class="form-label d-block mb-1 small fw-semibold text-muted" id="sug_estado_titulo">Estado</span>
                        <div class="btn-group btn-group-sm w-100" role="group" aria-labelledby="sug_estado_titulo">
                            <input type="radio" class="btn-check" name="sug_estado" id="sug_e_todas" value="todas" checked>
                            <label class="btn btn-outline-secondary" for="sug_e_todas">Todas</label>
                            <input type="radio" class="btn-check" name="sug_estado" id="sug_e_vencidas" value="vencidas">
                            <label class="btn btn-outline-secondary" for="sug_e_vencidas">Vencidas <span class="tx-chip-cont" data-sug-cont="vencidas">0</span></label>
                            <input type="radio" class="btn-check" name="sug_estado" id="sug_e_proximas" value="proximas">
                            <label class="btn btn-outline-secondary" for="sug_e_proximas">Próximas <span class="tx-chip-cont" data-sug-cont="proximas">0</span></label>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="gp-contador" id="sug_resumen" aria-live="polite"></span>
                    <button type="button" class="btn btn-link btn-sm p-0 ms-auto d-none" id="sug_limpiar" data-accion="sug-limpiar">Limpiar filtros</button>
                </div>
                <div id="sug_lista" aria-busy="false"></div>
            </div>

            <div class="d-none" id="sug_panel_historial" role="tabpanel" aria-labelledby="sug_tab_historial">
                <div class="row g-2 align-items-end mb-3" id="his_filtros">
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="his_decision">Decisión</label>
                        <select class="form-select form-select-sm" id="his_decision">
                            <option value="">Todas</option>
                            <option value="APROBADA">Aprobadas</option>
                            <option value="RECHAZADA">Rechazadas</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="his_finca">Finca</label>
                        <select class="form-select form-select-sm" id="his_finca"><option value="">Todas las fincas</option></select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="his_desde">Desde</label>
                        <input type="date" class="form-control form-control-sm" id="his_desde">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="his_hasta">Hasta</label>
                        <input type="date" class="form-control form-control-sm" id="his_hasta">
                    </div>
                </div>
                <div class="mb-2"><span class="gp-contador" id="his_resumen" aria-live="polite"></span></div>
                <div id="his_lista" aria-busy="false"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</main>
<?php echo $this->endSection(); ?>

<?php if ($disponible): ?>
<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const FECHA_DEFECTO = <?= json_encode($fecha_defecto ?? '') ?>;
    const PERMISOS = <?= json_encode([
        'registrar' => ! empty($permisos['registrar']),
        'consultar' => ! empty($permisos['consultar']),
        'editar'    => ! empty($permisos['editar']),
        'eliminar'  => ! empty($permisos['eliminar']),
    ]) ?>;
    const DESTINOS = <?= json_encode([
        'labores'       => ['url' => base_url('transacciones/labores/' . $id_loseta), 'puede' => ! empty($destinos['labores'])],
        'fertilizacion' => ['url' => base_url('transacciones/fertilizacion/' . $id_loseta), 'puede' => ! empty($destinos['fertilizacion'])],
        'produccion'    => ['url' => base_url('transacciones/produccion/' . $id_loseta), 'puede' => ! empty($destinos['produccion'])],
    ]) ?>;
</script>
<script src="<?= asset_url('public/assets/js/transacciones/planeacion.js') ?>"></script>
<?php echo $this->endSection(); ?>
<?php endif; ?>
