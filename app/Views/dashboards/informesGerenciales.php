<?php echo $this->extend('template/layout'); ?>
<?php
$meses_cortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$anios        = $anios ?: [$anio_sel];
$plan         = ! empty($planeacion['habilitada']) ? $planeacion : ['habilitada' => false];
?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/transacciones.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/planeacion.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informesGerenciales.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main ig-root">

    <div class="pagetitle">
        <h1>Informes Gerenciales</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <?php if (! empty($id_loseta)): ?>
                    <li class="breadcrumb-item"><a href="<?= esc(base_url('dashboards/onboarding/' . $id_loseta), 'attr') ?>">Dashboards</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item">Dashboards</li>
                <?php endif; ?>
                <li class="breadcrumb-item active">Informes Gerenciales</li>
            </ol>
        </nav>
    </div>

    <div class="ig-informes d-flex gap-2 overflow-auto mb-3" role="tablist" aria-label="Informes gerenciales">
        <?php foreach ($informes as $indice => $informe): ?>
            <button type="button" class="ig-informe-tab d-flex align-items-center gap-2 text-start" role="tab" data-informe="<?= esc($informe['clave'], 'attr') ?>" aria-selected="<?= $indice === 0 ? 'true' : 'false' ?>">
                <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="<?= esc($informe['icono'] ?? 'bi bi-clipboard-data', 'attr') ?>" aria-hidden="true"></i></span>
                <span class="min-w-0">
                    <span class="d-block fw-semibold ig-informe-nombre"><?= esc($informe['nombre']) ?></span>
                    <span class="gp-subtexto text-truncate" title="<?= esc($informe['descripcion'] ?? '', 'attr') ?>"><?= esc($informe['descripcion'] ?? '') ?></span>
                </span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="card gp-filtros mb-3">
        <div class="card-body py-3 px-4">
            <form id="form_filtros" class="row g-2 align-items-end" novalidate>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (string) $empresa['id'] === (string) $empresa_sel ? 'selected' : '' ?>><?= esc($empresa['razonSocial'] ?? $empresa['nombre'] ?? $empresa['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2 ig-filtro-ppto">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_anio">Año</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_anio">
                            <?php foreach ($anios as $anio): ?>
                                <option value="<?= esc($anio, 'attr') ?>" <?= (string) $anio === (string) $anio_sel ? 'selected' : '' ?>><?= esc($anio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2 ig-filtro-ppto">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_mes">Mes de corte</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_mes">
                            <?php foreach ($meses_cortos as $indice => $mes): ?>
                                <option value="<?= $indice + 1 ?>" <?= (int) $mes_sel === $indice + 1 ? 'selected' : '' ?>><?= $mes ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3 ig-filtro-ciclo d-none">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_fecha">Fecha de corte</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                        <input type="date" class="form-control" id="filtro_fecha" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_finca">Finca</label>
                    <div class="input-group flex-nowrap inf-select2">
                        <span class="input-group-text"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                        <div class="flex-grow-1 inf-selector">
                            <select class="form-select" id="filtro_finca">
                                <option value="">Todas las fincas</option>
                                <?php foreach ($fincas as $finca): ?>
                                    <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['codigo'] . ' — ' . $finca['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="invalid-feedback">Seleccione una finca</div>
                </div>
                <div class="col-12 col-md-3 col-lg-auto ig-filtro-ppto">
                    <span class="form-label mb-1 small fw-semibold text-muted d-block">Unidad</span>
                    <div class="btn-group w-100" role="group" aria-label="Unidad">
                        <input type="radio" class="btn-check" name="unidad" id="unidad_kg" value="kg" checked>
                        <label class="btn btn-outline-success btn-sm ig-unidad" for="unidad_kg">Kg</label>
                        <input type="radio" class="btn-check" name="unidad" id="unidad_ton" value="ton">
                        <label class="btn btn-outline-success btn-sm ig-unidad" for="unidad_ton">Ton</label>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg text-lg-end">
                    <button type="submit" class="btn btn-success ig-consultar" id="btn_consultar"><i class="bi bi-search me-1" aria-hidden="true"></i>Consultar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="zona_resultados">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="d-flex flex-wrap gap-2" id="chips_contexto"></span>
            <span class="gp-chip-fila ambar d-none" id="chip_desactualizado"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Filtros modificados · consulte de nuevo</span>
        </div>
        <div id="resultado_cuerpo" aria-live="polite"></div>
    </div>


    <?php if ($plan['habilitada']): ?>
        <div class="modal fade" id="ig_act_modal" tabindex="-1" aria-labelledby="ig_act_titulo" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="ig_act_titulo">
                            <span><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Planear actividad</span>
                            <span class="gp-chip-fila verde" id="ig_act_chip_fecha"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" id="ig_act_cerrar"></button>
                    </div>
                    <form id="ig_act_form" novalidate>
                        <div class="modal-body px-4 py-3">
                            <div class="gp-referencia mb-3 d-none" id="ig_act_contexto"></div>
                            <h6 class="gp-seccion mb-3"><i class="bi bi-geo-alt" aria-hidden="true"></i>Ubicación y labor</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-12 col-md-4">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_fecha">Fecha del plan <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                                        <input type="date" class="form-control" id="ig_act_fecha">
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_error_fecha">Seleccione la fecha del plan.</div>
                                    <div class="form-text" id="ig_act_fecha_ayuda"></div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_finca">Finca <span class="text-danger">*</span></label>
                                    <div class="tx-select-ancho">
                                        <select class="form-select" id="ig_act_finca"></select>
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_error_finca">Seleccione la finca.</div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_lote">Lote <span class="text-danger">*</span></label>
                                    <div class="tx-select-ancho">
                                        <select class="form-select" id="ig_act_lote" disabled></select>
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_error_lote">Seleccione el lote.</div>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_fallo_lote">No se pudo cargar. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-reintentar="lote">Reintentar</button></div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                        <label class="form-label mb-0 small fw-semibold text-muted" for="ig_act_labor">Labor <span class="text-danger">*</span></label>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="gp-chip-fila verde" id="ig_act_tema" role="status"></span>
                                            <div class="form-check form-switch mb-0 small">
                                                <input class="form-check-input" type="checkbox" role="switch" id="ig_act_todas">
                                                <label class="form-check-label" for="ig_act_todas">Ver todas</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <input type="text" class="form-control font-monospace text-uppercase tx-labor-codigo" id="ig_act_codigo" placeholder="Código" aria-label="Código de labor" autocomplete="off" maxlength="20">
                                        <div class="tx-select-ancho">
                                            <select class="form-select" id="ig_act_labor"></select>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_error_labor">Seleccione la labor.</div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_um">Unidad de medida <span class="text-danger">*</span></label>
                                    <div class="tx-select-ancho">
                                        <select class="form-select" id="ig_act_um"></select>
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_error_um">Seleccione la unidad de medida.</div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_inicio">Hora inicio <span class="fw-normal">(opcional)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-clock" aria-hidden="true"></i></span>
                                        <input type="time" class="form-control" id="ig_act_inicio">
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_fin">Hora fin <span class="fw-normal">(opcional)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                        <input type="time" class="form-control" id="ig_act_fin">
                                    </div>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_error_horario"></div>
                                </div>
                                <div class="col-12 col-md-4 d-none" id="ig_act_col_pfa">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_pfa">Referencia PFA <span class="fw-normal">(opcional)</span></label>
                                    <select class="form-select" id="ig_act_pfa">
                                        <option value="">Sin referencia</option>
                                    </select>
                                    <div class="invalid-feedback d-block d-none" id="ig_act_fallo_pfa">No se pudo cargar. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-reintentar="pfa">Reintentar</button></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="ig_act_obs">Observación</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-signpost" aria-hidden="true"></i></span>
                                        <input type="text" class="form-control" id="ig_act_obs" maxlength="250" placeholder="Ej. LN 1 A LA 54" autocomplete="off">
                                    </div>
                                    <div class="form-text d-flex justify-content-end"><span class="tx-num" id="ig_act_contador_obs">0 / 250</span></div>
                                </div>
                            </div>

                            <h6 class="gp-seccion mb-3"><i class="bi bi-people" aria-hidden="true"></i>Trabajadores <span class="tx-chip-cont verde ms-1" id="ig_act_contador" aria-live="polite">0</span></h6>
                            <div class="tx-select-ancho">
                                <select class="form-select" id="ig_act_terceros" multiple></select>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="ig_act_error_terceros">Seleccione al menos un trabajador.</div>
                            <div class="form-text mb-3">Código (ej. CR150), identificación o nombre. Mínimo 3 caracteres.</div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <span class="form-text m-0">Opcional: cantidad planeada por trabajador; el total es informativo.</span>
                                <div class="tx-reparto tx-reparto--inactivo pl-indicador" id="ig_act_indicador" aria-live="polite"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 gp-tabla tx-grilla pl-reparto ig-act-reparto">
                                    <thead class="gp-thead">
                                        <tr>
                                            <th>Trabajador</th>
                                            <th class="text-end">Cantidad <span class="fw-normal text-muted">(opcional)</span></th>
                                            <th class="text-center"><span class="visually-hidden">Quitar</span></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ig_act_filas"></tbody>
                                </table>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="ig_act_error_cantidades">Hay cantidades inválidas. Use números mayores o iguales a cero con máximo 3 decimales.</div>
                        </div>
                        <div class="modal-footer py-2 justify-content-between">
                            <small class="text-muted d-none d-sm-inline"><span class="text-danger">*</span> Campos obligatorios</small>
                            <div class="d-flex flex-wrap gap-2 ms-auto flex-grow-1 flex-sm-grow-0">
                                <button type="button" class="btn btn-secondary flex-grow-1 flex-sm-grow-0" data-bs-dismiss="modal" id="ig_act_cancelar"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar</button>
                                <button type="submit" class="btn btn-success flex-grow-1 flex-sm-grow-0" id="ig_act_guardar" title="Ctrl+S"><i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar</button>
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
    const INFORMES_GERENCIALES = <?= json_encode(['informes' => $informes, 'mapas' => $mapas ?? [], 'planeacion' => $plan], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<script src="<?= asset_url('public/assets/js/comun/palmas_mapa.js') ?>"></script>
<script src="<?= asset_url('public/assets/js/dashboards/informesGerenciales.js') ?>"></script>
<script src="<?= asset_url('public/assets/js/tutoriales/modulos/informesGerenciales.js') ?>"></script>
<?php echo $this->endSection(); ?>
