<?php echo $this->extend('template/layout'); ?>
<?php
$permisos    = $permisos ?? ['registrar' => true, 'consultar' => true, 'editar' => true, 'eliminar' => true];
$pRegistrar  = ! empty($permisos['registrar']);
$pConsultar  = ! empty($permisos['consultar']);
$pEditar     = $pConsultar && ! empty($permisos['editar']);
$pEliminar   = $pConsultar && ! empty($permisos['eliminar']);
$conRegistro = $pRegistrar || $pEditar;
$conPestanas = $conRegistro && $pConsultar;
$anchoAcciones = [1 => 64, 2 => 98, 3 => 132][1 + (int) $pEditar + (int) $pEliminar];
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/transacciones.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Producción</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('transacciones/onboarding/' . $id_loseta), 'attr') ?>">Transacciones</a></li>
                <li class="breadcrumb-item active">Producción</li>
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
                <span class="gp-chip-fila tx-chip-neutro" id="chip_estado">Sin guardar &mdash; el número se asigna al guardar</span>
            </div>

            <div class="tx-reparto tx-reparto--inactivo" id="medidor">
                <div class="tx-reparto-control">
                    <div class="tx-dato">
                        <span class="gp-etiqueta-mini">Racimos del tiquete</span>
                        <span class="tx-dato-valor" id="med_tiquete">0</span>
                    </div>
                    <span class="tx-sep">&middot;</span>
                    <div class="tx-dato">
                        <span class="gp-etiqueta-mini">Repartidos</span>
                        <span class="tx-dato-valor" id="med_repartidos">0</span>
                    </div>
                    <span class="tx-sep">&middot;</span>
                    <span class="tx-estado" id="med_estado" aria-live="polite">Cargue el tiquete para conocer los racimos a repartir</span>
                </div>
                <div class="tx-reparto-barra"><span id="med_barra"></span></div>
                <div class="gp-subtexto tx-reparto-kg d-none" id="med_kg">
                    <i class="bi bi-calculator me-1" aria-hidden="true"></i><span id="med_kg_texto"></span>
                </div>
            </div>

        </div>

        <div class="card mb-3" id="card_tiquete">
            <div class="card-header bg-white py-3">
                <h6 class="gp-seccion mb-0"><i class="bi bi-receipt" aria-hidden="true"></i>Tiquete</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="tiquete">No. Tiquete <span class="text-danger">*</span></label>
                        <div class="input-group has-validation" id="grupo_tiquete">
                            <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                            <input type="text" class="form-control font-monospace" id="tiquete" maxlength="20" autocomplete="off" placeholder="000000">
                            <button type="button" class="btn btn-outline-secondary" id="btn_buscar_tiquete" title="Buscar el tiquete en báscula">
                                <i class="bi bi-search me-1" aria-hidden="true"></i>Buscar
                            </button>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-text">Número impreso en el tiquete de báscula.</div>
                    </div>

                    <div class="col-md-4">
                        <div class="gp-panel-opcion" id="panel_externo">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="tiquete_externo">
                                <label class="form-check-label fw-semibold" for="tiquete_externo">¿Tiquete externo?</label>
                            </div>
                            <div class="form-text mb-0">Actívelo para capturar los datos del tiquete a mano.</div>
                        </div>
                    </div>

                    <div class="col-md-5 tx-solo-lectura" id="col_extractora">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="extractora">Extractora</label>
                        <div class="tx-select-ancho">
                            <select class="form-select" id="extractora" disabled>
                                <option value="">Seleccione una opción&hellip;</option>
                                <?php foreach ($extractoras as $extractora): ?>
                                    <option value="<?= esc($extractora['nit'], 'attr') ?>"><?= esc($extractora['razonSocial']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-text">Planta que recibe el fruto.</div>
                    </div>

                    <div class="col-12">
                        <div class="gp-nota-alerta d-none" role="alert" id="nota_tiquete">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><span id="nota_tiquete_texto"></span>
                            <div class="mt-2 d-none" id="nota_tiquete_acciones">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_capturar_mano" title="Capturar el tiquete a mano">
                                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Capturarlo a mano
                                </button>
                            </div>
                        </div>
                        <div class="gp-nota-alerta mt-2 d-none" role="alert" id="nota_externo">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Está capturando el tiquete a mano. Verifique los pesos contra el tiquete físico.
                        </div>
                    </div>

                </div>

                <div class="row g-3 mt-0 tx-solo-lectura" id="bloque_capturable">

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="codigoConductor">Conductor</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                            <input type="text" class="form-control font-monospace" id="codigoConductor" maxlength="20" autocomplete="off" placeholder="Identificación" disabled onkeypress="soloNumeros(event)">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="nombreConductor">Nombre del conductor</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <input type="text" class="form-control" id="nombreConductor" maxlength="100" autocomplete="off" disabled>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="vehiculo">Vehículo</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-truck" aria-hidden="true"></i></span>
                            <input type="text" class="form-control font-monospace text-uppercase" id="vehiculo" maxlength="20" autocomplete="off" disabled>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="remolque">Remolque</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-truck-flatbed" aria-hidden="true"></i></span>
                            <input type="text" class="form-control font-monospace text-uppercase" id="remolque" maxlength="20" autocomplete="off" disabled>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="fecha">Fecha</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                            <input type="date" class="form-control" id="fecha" disabled>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="gp-referencia tx-pesos" id="bloque_pesos">
                            <div class="row g-3">

                                <div class="col-md-2 ms-md-auto" id="col_racimos">
                                    <label class="form-label mb-1 small fw-semibold text-muted text-end d-block" for="racimos">Racimos</label>
                                    <input type="text" class="form-control font-monospace text-end" id="racimos" inputmode="numeric" maxlength="10" autocomplete="off" disabled onkeypress="soloNumeros(event)">
                                </div>

                                <div class="col-md-2" id="col_sacos">
                                    <label class="form-label mb-1 small fw-semibold text-muted text-end d-block" for="sacos">Sacos</label>
                                    <input type="text" class="form-control font-monospace text-end" id="sacos" inputmode="numeric" maxlength="10" autocomplete="off" disabled onkeypress="soloNumeros(event)">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-semibold text-muted text-end d-block" for="pesoBruto">Peso bruto (kg)</label>
                                    <input type="text" class="form-control font-monospace text-end" id="pesoBruto" inputmode="numeric" maxlength="14" autocomplete="off" disabled onkeypress="soloNumeros(event)">
                                </div>

                                <div class="col-md-2" id="col_tara">
                                    <label class="form-label mb-1 small fw-semibold text-muted text-end d-block" for="pesoTara">Peso tara (kg)</label>
                                    <input type="text" class="form-control font-monospace text-end" id="pesoTara" inputmode="numeric" maxlength="14" autocomplete="off" disabled onkeypress="soloNumeros(event)">
                                    <div class="invalid-feedback d-block d-none" id="error_tara">La tara no puede superar el peso bruto.</div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-semibold text-muted text-end d-block" for="pesoNeto">Peso neto (kg)</label>
                                    <input type="text" class="form-control font-monospace text-end tx-neto" id="pesoNeto" readonly>
                                    <div class="form-text">Se calcula como bruto menos tara.</div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="card mb-3" id="card_observacion">
            <div class="card-header bg-white py-3">
                <h6 class="gp-seccion mb-0"><i class="bi bi-calendar3" aria-hidden="true"></i>Datos de la transacción</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="anio">Año <span class="text-danger">*</span></label>
                        <select class="form-select" id="anio">
                            <?php foreach ($anios ?? [] as $anio): ?>
                                <option value="<?= esc($anio, 'attr') ?>" <?= (int) $anio === (int) ($anio_actual ?? 0) ? 'selected' : '' ?>><?= esc($anio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="periodo">Periodo <span class="text-danger">*</span></label>
                        <select class="form-select" id="periodo" data-anio="<?= esc($anio_actual ?? '', 'attr') ?>" data-mes="<?= esc($mes_actual ?? '', 'attr') ?>" disabled>
                            <option value="">Cargando&hellip;</option>
                        </select>
                        <div class="invalid-feedback" id="error_periodo">Seleccione el periodo</div>
                        <div class="gp-subtexto mt-1" id="periodo_rango"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="observacion">Observación</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
                            <input type="text" class="form-control" id="observacion" maxlength="250" autocomplete="off">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-text">Opcional. Queda en el encabezado de la transacción.</div>
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
                <h6 class="gp-seccion mb-0"><i class="bi bi-diagram-3" aria-hidden="true"></i>Detalle</h6>
            </div>

            <div class="card-body">

                <div class="gp-estado-vacio text-center py-5" id="estado_sin_tiquete">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-truck" aria-hidden="true"></i>
                    </div>
                    <h6 class="mb-1">Comience por el tiquete</h6>
                    <p class="text-muted mb-0">Busque el número de tiquete o márquelo como externo para capturarlo a mano.</p>
                </div>

                <div class="tx-inactivo" id="detalle_cuerpo" inert>

                    <div class="gp-panel-opcion mb-3">
                        <div class="row g-2 align-items-end">

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_finca">Finca</label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="cap_finca">
                                        <option value="">Seleccione una opción&hellip;</option>
                                        <?php foreach ($fincas as $finca): ?>
                                            <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['codigo']) ?> &mdash; <?= esc($finca['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_finca">Seleccione la finca.</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_lote">Lote</label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="cap_lote" disabled>
                                        <option value="">Seleccione una finca primero</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_lote">Seleccione el lote.</div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_racimos">Racimos</label>
                                <input type="text" class="form-control font-monospace text-end" id="cap_racimos" inputmode="numeric" maxlength="8" autocomplete="off" onkeypress="soloNumeros(event)">
                                <div class="invalid-feedback d-block d-none" id="error_racimos">Indique los racimos de la línea (mayor que cero).</div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cap_sacos">Sacos</label>
                                <input type="text" class="form-control font-monospace text-end" id="cap_sacos" inputmode="numeric" maxlength="8" autocomplete="off" value="0" onkeypress="soloNumeros(event)">
                            </div>

                            <div class="col-md-2">
                                <button type="button" class="btn btn-success w-100" id="btn_agregar_linea" title="Agregar la línea al detalle">
                                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar
                                </button>
                            </div>

                            <div class="col-12">
                                <span class="gp-subtexto" id="ayuda_peso"></span>
                            </div>

                        </div>
                    </div>

                    <div class="gp-nota mb-3">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        <span>Usted reparte los racimos del tiquete. El peso neto lo reparte el sistema entre las líneas, según sus racimos y el peso promedio de cada lote: por eso los kg de todas las líneas cambian cada vez que agrega o quita una.</span>
                    </div>

                    <div class="gp-nota-alerta mb-3 d-none" role="alert" id="nota_sin_pesos">
                        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                        <span>Ninguno de los lotes agregados tiene peso promedio registrado. El sistema repartirá el peso neto solo en proporción a los racimos de cada línea.</span>
                    </div>

                    <div class="accordion" id="lista_lineas"></div>

                    <div class="gp-estado-vacio text-center py-5 d-none" id="estado_sin_lineas">
                        <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                            <i class="bi bi-list-ul" aria-hidden="true"></i>
                        </div>
                        <h6 class="mb-1">Todavía no hay líneas</h6>
                        <p class="text-muted mb-0" id="texto_sin_lineas">Elija finca, lote y racimos y pulse Agregar para repartir los racimos del tiquete.</p>
                    </div>

                    <div class="gp-subtexto mt-2" id="pie_estimado">Los kg que ve son un estimado. El valor definitivo se confirma al guardar.</div>

                </div>

            </div>

            <div class="card-footer bg-white py-3">
                <div class="row g-2 text-end">
                    <div class="col-md-4">
                        <span class="gp-etiqueta-mini">Total Racimos</span>
                        <div class="tx-total fs-5 fw-semibold tx-total--inactivo" id="total_racimos">0 de 0</div>
                    </div>
                    <div class="col-md-4">
                        <span class="gp-etiqueta-mini">Total Sacos</span>
                        <div class="tx-total fs-5 fw-semibold" id="total_sacos">0</div>
                    </div>
                    <div class="col-md-4">
                        <span class="gp-etiqueta-mini">Total Cosecha</span>
                        <div class="tx-total fs-5 fw-semibold" id="total_cosecha">0</div>
                    </div>
                    <div class="col-md-4">
                        <span class="gp-etiqueta-mini">Total Cargue</span>
                        <div class="tx-total-sec" id="total_cargue">0</div>
                    </div>
                    <div class="col-md-4">
                        <span class="gp-etiqueta-mini">Total Transporte</span>
                        <div class="tx-total-sec" id="total_transporte">0</div>
                    </div>
                    <div class="col-md-4">
                        <span class="gp-etiqueta-mini">Total Jornales</span>
                        <div class="tx-total-sec" id="total_jornales">0</div>
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
                <button type="button" class="btn btn-primary" id="btn_guardar" title="Guardar la transacción">
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
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_desde">Fecha desde</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                            <input type="date" class="form-control" id="con_desde">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_hasta">Fecha hasta</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                            <input type="date" class="form-control" id="con_hasta">
                            <div class="invalid-feedback">Debe ser posterior a la fecha desde</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_tiquete">No. Tiquete</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                            <input type="text" class="form-control font-monospace" id="con_tiquete" maxlength="20" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_numero">No. Transacción</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                            <input type="text" class="form-control font-monospace" id="con_numero" maxlength="20" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="con_estado">Estado</label>
                        <select class="form-select" id="con_estado">
                            <option value="">Todos</option>
                            <option value="0">Vigentes</option>
                            <option value="1">Anulados</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto ms-md-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="btn_limpiar_consulta">
                            <i class="bi bi-eraser me-1" aria-hidden="true"></i>Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary flex-fill" id="btn_consultar">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Consultar
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
                <span class="gp-rango-fecha" id="rango_consulta"></span>
            </div>
            <div class="gp-nota-alerta mx-3 mt-3 d-none" id="con_truncado">
                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Se muestran los primeros 500 resultados. Acote el rango de fechas o use otros filtros.
            </div>
            <div class="card-body p-0">
                <div id="con_aviso"></div>
                <div class="d-none" id="con_tabla">
                    <table class="table table-hover align-middle mb-0 gp-tabla tx-tabla-consulta">
                        <thead class="gp-thead">
                            <tr>
                                <th style="width:110px">Número</th>
                                <th style="width:110px">Fecha</th>
                                <th style="width:110px">Tiquete</th>
                                <th style="width:100px">Vehículo</th>
                                <th class="text-end" style="width:90px">Racimos</th>
                                <th class="text-end" style="width:130px">Peso neto (kg)</th>
                                <th class="text-end" style="width:80px">Líneas</th>
                                <th class="text-end" style="width:110px">Trabajadores</th>
                                <th>Observación</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:<?= $anchoAcciones ?>px"><span class="visually-hidden">Acciones</span></th>
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

    <?php if ($pConsultar): ?>
    <div class="modal fade" id="modalVerTransaccion" tabindex="-1" aria-labelledby="titulo_ver" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="titulo_ver">
                        <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>Transacción
                        <span class="font-monospace" id="ver_numero"></span>
                        <span id="ver_estado"></span>
                        <span class="gp-chip-fila tx-chip-neutro">TLC</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body px-4 py-3" id="ver_cuerpo"></div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btn_cerrar_ver">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <?php if ($conRegistro): ?>
    <div class="modal fade" id="modalTrabajador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2">
                        <span id="titulo_modal_trabajador"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Agregar trabajador</span>
                        <span class="gp-chip-fila tx-chip-neutro" id="chip_modal_linea"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_trabajador" novalidate>
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-person-workspace" aria-hidden="true"></i>Trabajo realizado</h6>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="trab_tercero">Tercero(s) <span class="text-danger">*</span></label>
                                <span class="tx-chip-cont verde ms-1 d-none" id="trab_contador" aria-live="polite"></span>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="trab_tercero"></select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_trab_tercero"></div>
                                <div class="form-text">Escriba al menos 3 caracteres del nombre o la identificación.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="trab_novedad">Actividad <span class="text-danger">*</span></label>
                                <div class="tx-select-ancho">
                                    <select class="form-select" id="trab_novedad"></select>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="error_trab_novedad"></div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="trab_fecha">Fecha <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                                    <input type="date" class="form-control" id="trab_fecha">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="trab_cantidad">Cantidad (kg) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace text-end" id="trab_cantidad" inputmode="decimal" maxlength="14" autocomplete="off">
                                <div class="invalid-feedback d-block d-none" id="error_trab_cantidad"></div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="trab_precio">Precio <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control font-monospace text-end" id="trab_precio" inputmode="decimal" maxlength="14" autocomplete="off">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="gp-subtexto" id="ayuda_precio"></div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="trab_jornales">Jornales</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace text-end" id="trab_jornales" inputmode="decimal" maxlength="10" autocomplete="off" value="0">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Hoy todas las transacciones se registran con jornales en cero.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="trab_total">Total</label>
                                <input type="text" class="form-control font-monospace text-end fs-5 fw-bold tx-neto" id="trab_total" readonly>
                                <div class="form-text" id="ayuda_total">Cantidad &times; precio.</div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2 justify-content-between">
                        <small class="text-muted"><span class="text-danger">*</span> Campos obligatorios</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" id="btn_guardar_trabajador">
                                <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const PERMISOS = <?= json_encode(['registrar' => $pRegistrar, 'consultar' => $pConsultar, 'editar' => $pEditar, 'eliminar' => $pEliminar]) ?>;
</script>
<script src="<?= base_url('public/assets/js/transacciones/produccion.js') ?>"></script>
<script src="<?= base_url('public/assets/js/transacciones/produccion_consulta.js') ?>"></script>
<?php echo $this->endSection(); ?>
