<?php echo $this->extend('template/layout'); ?>
<?php
$permisos    = $permisos ?? ['registrar' => true, 'consultar' => true, 'editar' => true, 'eliminar' => true];
$pRegistrar  = ! empty($permisos['registrar']);
$pConsultar  = ! empty($permisos['consultar']);
$pEditar     = $pConsultar && ! empty($permisos['editar']);
$pEliminar   = $pConsultar && ! empty($permisos['eliminar']);
$conRegistro = $pRegistrar || $pEditar;
$conPestanas = $conRegistro && $pConsultar;
$etiqueta    = static fn ($valor) => ucfirst(mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $valor))));
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/transacciones.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Fertilización</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('transacciones/onboarding/' . $id_loseta), 'attr') ?>">Transacciones</a></li>
                <li class="breadcrumb-item active">Fertilización</li>
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
                <?php if ($conPestanas): ?>
                <div class="col-12 col-md-auto ms-md-auto<?= $pRegistrar ? '' : ' d-none' ?>" id="pestanas">
                    <ul class="nav nav-pills nav-fill flex-nowrap tx-pestanas" role="tablist">
                        <li class="nav-item<?= $pRegistrar ? '' : ' d-none' ?>" role="presentation" id="item_registro">
                            <button type="button" class="nav-link<?= $pRegistrar ? ' active' : '' ?>" id="tab_registro" data-bs-toggle="pill" data-bs-target="#panel_registro" role="tab" aria-controls="panel_registro" aria-selected="<?= $pRegistrar ? 'true' : 'false' ?>">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>Registro
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link<?= $pRegistrar ? '' : ' active' ?>" id="tab_consulta" data-bs-toggle="pill" data-bs-target="#panel_consulta" role="tab" aria-controls="panel_consulta" aria-selected="<?= $pRegistrar ? 'false' : 'true' ?>">
                                <i class="bi bi-search" aria-hidden="true"></i>Consulta
                            </button>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <input type="hidden" id="empresa" value="<?= esc($empresa_sel, 'attr') ?>">

    <div class="tab-content">

    <?php if ($conRegistro): ?>
    <div class="tab-pane fade<?= $pRegistrar ? ' show active' : '' ?>" id="panel_registro" role="tabpanel" aria-labelledby="tab_registro">

    <div class="tx-relativo" id="zona_formulario">

        <div class="gp-barra-editor tx-barra d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="gp-chip-fila tx-chip-neutro" id="chip_estado">Seleccione el tipo de transacción</span>
            </div>
            <div class="d-flex align-items-center gap-3" data-solo="PFA">
                <div class="tx-dato">
                    <span class="gp-etiqueta-mini">Lotes</span>
                    <span class="tx-dato-valor" id="pfa_res_lotes">0</span>
                </div>
                <span class="tx-sep">&middot;</span>
                <div class="tx-dato">
                    <span class="gp-etiqueta-mini">Total kg</span>
                    <span class="tx-dato-valor" id="pfa_res_kg">0,00</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3" data-solo="RLF">
                <div class="tx-dato">
                    <span class="gp-etiqueta-mini">Líneas</span>
                    <span class="tx-dato-valor" id="res_lineas">0</span>
                </div>
                <span class="tx-sep">&middot;</span>
                <div class="tx-dato">
                    <span class="gp-etiqueta-mini">Valor liquidado</span>
                    <span class="tx-dato-valor" id="res_valor">0</span>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="card_tipo">
            <div class="card-header bg-white py-3">
                <h6 class="gp-seccion mb-0"><i class="bi bi-journal-text" aria-hidden="true"></i>Tipo de transacción</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-5">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="tipo">Tipo transacción <span class="text-danger">*</span></label>
                        <div class="tx-select-ancho">
                            <select class="form-select" id="tipo">
                                <option value="">Seleccione el tipo&hellip;</option>
                                <?php foreach ($tipos ?? [] as $tipo): ?>
                                    <option value="<?= esc($tipo['codigo'], 'attr') ?>"><?= esc(trim($tipo['codigo'])) ?> &mdash; <?= esc($etiqueta($tipo['descripcion'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="numero">Número</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                            <input type="text" class="form-control font-monospace tx-neto" id="numero" value="Se asigna al guardar" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="estado_sin_tipo">
            <div class="card-body gp-estado-vacio text-center py-4">
                <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-2">
                    <i class="bi bi-ui-checks" aria-hidden="true"></i>
                </div>
                <h6 class="mb-1">Seleccione el tipo de transacción</h6>
                <p class="text-muted mb-0">Al elegirlo se habilitan los datos y el detalle.</p>
            </div>
        </div>

        <div class="tx-inactivo" id="bloque_transaccion" inert>

            <div class="card mb-3" id="card_datos">
                <div class="card-header bg-white py-3">
                    <h6 class="gp-seccion mb-0"><i class="bi bi-card-heading" aria-hidden="true"></i>Encabezado de la transacción</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="anio">Año <span class="text-danger">*</span></label>
                            <select class="form-select" id="anio">
                                <?php foreach ($anios ?? [] as $anio): ?>
                                    <option value="<?= esc($anio, 'attr') ?>" <?= (int) $anio === (int) ($anio_actual ?? 0) ? 'selected' : '' ?>><?= esc($anio) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-5 col-lg-3">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="periodo">Periodo <span class="text-danger">*</span> <span class="badge gp-badge-cerrado ms-1 d-none" id="badge_cerrado">Cerrado</span></label>
                            <select class="form-select" id="periodo" data-anio="<?= esc($anio_actual ?? '', 'attr') ?>" data-mes="<?= esc($mes_actual ?? '', 'attr') ?>" disabled>
                                <option value="">Cargando&hellip;</option>
                            </select>
                            <div class="invalid-feedback" id="error_periodo">Seleccione el periodo</div>
                            <div class="gp-subtexto mt-1" id="periodo_rango"></div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="fecha">Fecha <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                                <input type="date" class="form-control" id="fecha">
                                <div class="invalid-feedback">Indique una fecha dentro del periodo</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2" data-solo="PFA">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_fecha_final">Fecha final <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                                <input type="date" class="form-control" id="pfa_fecha_final">
                                <div class="invalid-feedback">Debe ser igual o posterior a la fecha</div>
                            </div>
                            <div class="form-text">Por defecto, la fecha inicial.</div>
                        </div>
                        <div class="col-12 col-md-5 col-lg-5" id="col_finca">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="cap_finca">Finca <span class="text-danger">*</span></label>
                            <div class="tx-select-ancho">
                                <select class="form-select" id="cap_finca">
                                    <option value="">Seleccione una opción&hellip;</option>
                                    <?php foreach ($fincas ?? [] as $finca): ?>
                                        <option value="<?= esc(trim((string) $finca['codigo']), 'attr') ?>"><?= esc(trim((string) $finca['codigo'])) ?> &mdash; <?= esc(trim((string) $finca['descripcion'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="error_finca">Seleccione la finca.</div>
                        </div>
                        <div class="col-12 col-md-7 col-lg-8" data-solo="RLF">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="referencia">Referencia <span class="text-danger">*</span></label>
                            <div class="tx-select-ancho">
                                <select class="form-select" id="referencia" disabled>
                                    <option value="">Seleccione una finca primero</option>
                                </select>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="error_referencia">Seleccione la referencia.</div>
                            <div class="form-text">Plan de fertilización (PFA) de la finca.</div>
                            <div class="form-text tx-texto-ambar d-none" id="ayuda_sin_planes"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>La finca no tiene planes PFA vigentes.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="observacion">Observación / notas</label>
                            <textarea class="form-control" id="observacion" rows="2" maxlength="2550"></textarea>
                            <div class="form-text d-flex justify-content-between">
                                <span>Opcional.</span>
                                <span class="tx-num" id="contador_observacion">0 / 2550</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="gp-nota-alerta d-none" role="alert" id="nota_periodo">
                                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><span id="nota_periodo_texto"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3" id="card_detalle">
                <div class="card-header bg-white py-3">
                    <h6 class="gp-seccion mb-0" data-solo="RLF"><i class="bi bi-droplet" aria-hidden="true"></i>Detalle de la transacción</h6>
                    <h6 class="gp-seccion mb-0" data-solo="PFA"><i class="bi bi-clipboard-data" aria-hidden="true"></i>Detalle del plan</h6>
                </div>
                <div class="card-body">

                    <div data-solo="PFA">
                        <div class="gp-nota-alerta mb-3 d-none" role="alert" id="pfa_nota_uso">
                            <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Este plan está referenciado por registros de labores (RLF). Puede editarlo, pero no quitar lotes que ya tengan labores registradas.
                        </div>

                        <div class="gp-panel-opcion mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-8 col-lg-5">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_item">Item <span class="text-danger">*</span></label>
                                    <div class="tx-select-ancho">
                                        <select class="form-select" id="pfa_item"><option value=""></option></select>
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="pfa_error_item"></div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-2">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_um">U.M. <span class="text-danger">*</span></label>
                                    <select class="form-select" id="pfa_um"></select>
                                    <div class="invalid-feedback">Seleccione la unidad.</div>
                                </div>
                                <div class="col-6 col-md-12 col-lg-5">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_lote">Lote <span class="text-danger">*</span></label>
                                    <div class="tx-select-ancho">
                                        <select class="form-select" id="pfa_lote" disabled><option value="">Seleccione una finca primero</option></select>
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="pfa_error_lote">Seleccione el lote.</div>
                                </div>
                                <div class="col-6 col-md-3 col-lg-2">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_dosis">Dosis <span class="text-danger">*</span></label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control font-monospace text-end" id="pfa_dosis" inputmode="decimal" maxlength="10" autocomplete="off">
                                        <span class="input-group-text">kg/palma</span>
                                        <div class="invalid-feedback">Mayor a 0, máx. 2 decimales.</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 col-lg-2">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_pbulto">Peso bulto <span class="text-danger">*</span></label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control font-monospace text-end" id="pfa_pbulto" inputmode="decimal" maxlength="10" autocomplete="off" value="50">
                                        <span class="input-group-text">kg</span>
                                        <div class="invalid-feedback">Mayor a 0, máx. 2 decimales.</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 col-lg-2">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_palmas">No. palmas</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-tree" aria-hidden="true"></i></span>
                                        <input type="text" class="form-control font-monospace text-end tx-neto" id="pfa_palmas" readonly tabindex="-1">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 col-lg-2">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="pfa_bultos">No. bultos</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                                        <input type="text" class="form-control font-monospace text-end tx-neto fw-semibold" id="pfa_bultos" readonly tabindex="-1">
                                    </div>
                                    <div class="form-text">palmas × dosis ÷ peso bulto</div>
                                </div>
                                <div class="col-12 col-md-6 ms-md-auto col-lg-auto ms-lg-auto">
                                    <button type="button" class="btn btn-success w-100" id="pfa_cargar" title="Agregar el insumo al lote">
                                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Cargar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="gp-nota mb-3">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            <span>Cargar agrega el insumo al lote indicado. Las palmas se toman del lote y los bultos se calculan automáticamente. Lo cargado no se modifica: quite el insumo o el lote y vuelva a cargar.</span>
                        </div>

                        <div id="pfa_lista"></div>

                        <div class="gp-estado-vacio text-center py-5" id="pfa_vacio">
                            <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                                <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                            </div>
                            <h6 class="mb-1">Todavía no hay lotes</h6>
                            <p class="text-muted mb-0">Seleccione insumo, lote y dosis y pulse Cargar.</p>
                        </div>
                    </div>

                    <div class="gp-panel-opcion mb-3" data-solo="RLF">
                        <div class="row g-2 align-items-end">

                            <div class="col-12 col-md-8 col-lg-9">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_labor">Labor <span class="text-danger">*</span></label>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control font-monospace text-uppercase tx-labor-codigo" id="cap_codigo" placeholder="Código" aria-label="Código de labor" autocomplete="off" maxlength="20">
                                    <div class="tx-select-ancho">
                                        <select class="form-select" id="cap_labor">
                                            <option value="">Seleccione una opción&hellip;</option>
                                            <?php foreach ($labores ?? [] as $labor): ?>
                                                <option value="<?= esc(trim((string) $labor['codigo']), 'attr') ?>" data-um="<?= esc(trim((string) $labor['uMedida']), 'attr') ?>" data-nombre="<?= esc(trim((string) $labor['descripcion']), 'attr') ?>"><?= esc(trim((string) $labor['codigo'])) ?> &mdash; <?= esc(trim((string) $labor['descripcion'])) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_codigo">No existe una labor de fertilización con ese código.</div>
                                <div class="invalid-feedback d-block d-none" id="error_labor">Seleccione la labor.</div>
                            </div>

                            <div class="col-12 col-md-4 col-lg-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_um">U.M. <span class="text-danger">*</span></label>
                                <select class="form-select" id="cap_um">
                                    <option value="">Seleccione&hellip;</option>
                                    <?php foreach ($unidades ?? [] as $unidad): ?>
                                        <option value="<?= esc(trim((string) $unidad['codigo']), 'attr') ?>"><?= esc(trim((string) $unidad['codigo'])) ?> &mdash; <?= esc(trim((string) $unidad['descripcion'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback d-block d-none" id="error_um">Seleccione la unidad.</div>
                            </div>

                            <div class="col-6 col-md-3 col-lg-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_seccion">Sección <span class="fw-normal">(opcional)</span></label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="cap_seccion" disabled>
                                        <option value="">Seleccione una finca primero</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-6 col-md-6 col-lg-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_lote">Lote <span class="text-danger">*</span></label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="cap_lote" disabled>
                                        <option value="">Seleccione una referencia primero</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_lote">Seleccione el lote.</div>
                            </div>

                            <div class="col-12 col-md-3 col-lg-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_fecha">Fecha labor <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                                    <input type="date" class="form-control" id="cap_fecha">
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_fecha_labor">La fecha debe estar dentro del periodo.</div>
                                <div class="form-text" id="ayuda_fecha_labor">Por defecto, la fecha de la transacción.</div>
                            </div>

                            <div class="col-12 col-lg-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_terceros">Terceros <span class="text-danger">*</span></label>
                                <span class="tx-chip-cont verde ms-1 d-none" id="cap_terceros_contador" aria-live="polite"></span>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="cap_terceros" multiple></select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_terceros">Seleccione al menos un tercero.</div>
                                <div class="form-text">Escriba al menos 3 caracteres del nombre o la identificación.</div>
                            </div>

                            <div class="col-6 col-md-3 col-lg-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_cantidad">Cantidad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace text-end" id="cap_cantidad" inputmode="decimal" maxlength="14" autocomplete="off">
                                <div class="invalid-feedback d-block d-none" id="error_cantidad">Indique una cantidad mayor que cero.</div>
                                <div class="form-text d-none" id="ayuda_cantidad"></div>
                            </div>

                            <div class="col-6 col-md-3 col-lg-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_jornales">Jornales</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace text-end tx-neto" id="cap_jornales" value="0" tabindex="-1" disabled>
                                </div>
                                <div class="form-text">No aplica en fertilización.</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-auto ms-lg-auto d-flex gap-2">
                                <button type="button" class="btn btn-success flex-fill" id="btn_cargar" title="Crea una línea repartida entre los terceros seleccionados">
                                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Cargar
                                </button>
                                <button type="button" class="btn btn-outline-primary flex-fill" id="btn_liquidar" title="Recalcular el precio de todas las líneas" disabled>
                                    <i class="bi bi-calculator me-1" aria-hidden="true"></i>Liquidar
                                </button>
                            </div>

                        </div>
                    </div>

                    <div class="gp-nota mb-3" data-solo="RLF">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        <span>Cargar crea una línea con la cantidad indicada, repartida en partes iguales entre los terceros seleccionados. Una vez cargada no se modifica: quite el trabajador o la línea y vuelva a cargar.</span>
                    </div>

                    <div data-solo="RLF">
                    <div class="d-none" id="contenedor_lineas"><div id="lista_lineas"></div></div>

                    <div class="gp-estado-vacio text-center py-5" id="estado_sin_lineas">
                        <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                            <i class="bi bi-list-ul" aria-hidden="true"></i>
                        </div>
                        <h6 class="mb-1">Todavía no hay líneas</h6>
                        <p class="text-muted mb-0">Complete labor, lote, terceros y cantidad y pulse Cargar.</p>
                    </div>
                    </div>

                </div>

                <div class="card-footer bg-white py-3" data-solo="PFA">
                    <div class="row g-2 text-end">
                        <div class="col-6 col-md-3">
                            <span class="gp-etiqueta-mini">Lotes</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="pfa_total_lotes">0</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="gp-etiqueta-mini">Insumos</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="pfa_total_insumos">0</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="gp-etiqueta-mini">Total kg</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="pfa_total_kg">0,00</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="gp-etiqueta-mini">Total bultos</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="pfa_total_bultos">0,00</div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white py-3" data-solo="RLF">
                    <div class="row g-2 text-end">
                        <div class="col-6 col-md">
                            <span class="gp-etiqueta-mini">Líneas</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="total_lineas">0</div>
                        </div>
                        <div class="col-6 col-md">
                            <span class="gp-etiqueta-mini">Trabajadores</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="total_trabajadores">0</div>
                        </div>
                        <div class="col-6 col-md">
                            <span class="gp-etiqueta-mini">Cantidad</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="total_cantidad">0</div>
                        </div>
                        <div class="col-6 col-md">
                            <span class="gp-etiqueta-mini">Insumos (kg)</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="total_insumos">0,00</div>
                            <div class="gp-subtexto font-monospace d-none" id="total_insumos_otras"></div>
                        </div>
                        <div class="col-12 col-md">
                            <span class="gp-etiqueta-mini">Valor</span>
                            <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="total_valor">0</div>
                            <div class="gp-subtexto d-none" id="total_valor_nota">Hay líneas sin liquidar</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="tx-acciones" id="barra_acciones">
            <small class="text-muted d-none d-sm-inline"><span class="text-danger">*</span> Campos obligatorios</small>
            <div class="d-flex gap-2 ms-auto">
                <button type="button" class="btn btn-outline-secondary" id="btn_cancelar" title="Cancelar la transacción">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btn_guardar" title="Guardar la transacción" disabled>
                    <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar
                </button>
            </div>
        </div>

    </div>

    </div>
    <?php endif; ?>

    <?php if ($pConsultar): ?>
    <div class="tab-pane fade<?= $pRegistrar ? '' : ' show active' ?>" id="panel_consulta" role="tabpanel" aria-labelledby="tab_consulta">

        <form class="card mb-3 gp-filtros" id="formulario_consulta" novalidate>
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_campo">Campo</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-funnel" aria-hidden="true"></i></span>
                            <select class="form-select" id="con_campo">
                                <option value="fecha">Fecha</option>
                                <option value="finca">Finca</option>
                                <option value="numero">Número</option>
                                <option value="observacion">Observación</option>
                                <option value="referencia">Referencia</option>
                                <option value="tipo">Tipo</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_operador">Operador</label>
                        <select class="form-select" id="con_operador">
                            <option value="igual">Igual a</option>
                            <option value="diferente">Diferente de</option>
                            <option value="mayor">Mayor que</option>
                            <option value="menor">Menor que</option>
                            <option value="mayorIgual">Mayor o igual</option>
                            <option value="menorIgual">Menor o igual</option>
                            <option value="contiene">Contiene</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_valor">Valor</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true" id="con_valor_icono"></i></span>
                            <input type="date" class="form-control" id="con_valor" maxlength="100" autocomplete="off" placeholder="Vacío = todos">
                        </div>
                    </div>
                    <div class="col-12 col-md-auto ms-md-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="btn_limpiar_consulta">
                            <i class="bi bi-eraser me-1" aria-hidden="true"></i>Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary flex-fill" id="btn_consultar">
                            <i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span class="gp-contador" id="contador_consulta" aria-live="polite">
                    <i class="bi bi-hash me-1" aria-hidden="true"></i><strong>0</strong> registros encontrados
                </span>
            </div>
            <div class="gp-nota-alerta mx-3 mt-3 d-none" id="con_truncado">
                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Se muestran los primeros 500 resultados. Use un filtro más específico.
            </div>
            <div class="card-body p-0">
                <div id="con_aviso"></div>
                <div class="d-none" id="con_tabla">
                    <table class="table table-hover align-middle mb-0 gp-tabla tx-tabla-consulta">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:90px">Acciones</th>
                                <th style="width:70px">Tipo</th>
                                <th style="width:140px">Número</th>
                                <th style="width:110px">Fecha</th>
                                <th style="width:110px">Finca</th>
                                <th style="width:150px">Referencia</th>
                                <th>Observación</th>
                                <th class="text-center" style="width:110px">Anulado</th>
                            </tr>
                        </thead>
                        <tbody id="con_filas"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    </div>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const MODULO_TX = { ruta: 'fertilizacion', jornales: false, referencia: true, items: <?= json_encode($items ?? []) ?> };
    const PERMISOS = <?= json_encode(['registrar' => $pRegistrar, 'consultar' => $pConsultar, 'editar' => $pEditar, 'eliminar' => $pEliminar]) ?>;
</script>
<script src="<?= base_url('public/assets/js/transacciones/labores.js') ?>"></script>
<script src="<?= base_url('public/assets/js/transacciones/labores_consulta.js') ?>"></script>
<script src="<?= base_url('public/assets/js/transacciones/fertilizacion_pfa.js') ?>"></script>
<?php echo $this->endSection(); ?>
