<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<?php
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Presupuesto de producción</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item">Contabilidad</li>
                <li class="breadcrumb-item active">Presupuesto de producción</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="gp-nota mb-3">
            <i class="bi bi-info-circle me-1"></i>Presupuesto de producción por lote. Alimenta el informe Presupuesto vs Ejecutado.
        </div>

        <div class="gp-nota-alerta mb-3 d-flex gap-2 <?= $tabla_existe ? 'd-none' : '' ?>" role="alert" id="alerta_tabla">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>La tabla de presupuesto no existe en la base de datos</strong>
                <div>Solicite al administrador ejecutar el script de creación. Mientras tanto la edición está deshabilitada.</div>
            </div>
        </div>

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (int) $empresa['id'] === (int) $empresa_sel ? 'selected' : '' ?>><?= esc($empresa['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_anio">Año</label>
                        <select class="form-select" id="filtro_anio">
                            <?php foreach ($anios as $anio): ?>
                                <option value="<?= esc($anio, 'attr') ?>" <?= (int) $anio === (int) $anio_sel ? 'selected' : '' ?>><?= esc($anio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_finca">Finca</label>
                        <select class="form-select" id="filtro_finca">
                            <option value="">Seleccione una finca</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto ms-md-auto d-grid d-md-block">
                        <button type="button" class="btn btn-primary" id="btn_consultar">
                            <i class="bi bi-funnel me-1"></i>Consultar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3 d-flex align-items-center gap-3">
                        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-basket"></i></span>
                        <div>
                            <div class="gp-etiqueta-mini" id="kpi_total_titulo">Total presupuestado <?= esc($anio_sel) ?></div>
                            <div class="gp-ppto-kpi" id="kpi_total">&ndash;</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3 d-flex align-items-center gap-3">
                        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-grid-3x3-gap"></i></span>
                        <div>
                            <div class="gp-etiqueta-mini">Lotes con presupuesto</div>
                            <div class="gp-ppto-kpi" id="kpi_lotes">&ndash;</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3 d-flex align-items-center gap-3">
                        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-graph-up-arrow"></i></span>
                        <div>
                            <div class="gp-etiqueta-mini">Vs. año anterior</div>
                            <div class="gp-ppto-kpi" id="kpi_anterior">&ndash;</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3 gp-ppto-metas position-relative" id="ppto_metas" aria-busy="false">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="gp-seccion m-0 p-0"><i class="bi bi-sliders"></i>Meta y estacionalidad <span id="metas_anio"><?= esc($anio_sel) ?></span></div>
                <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                    <span class="gp-subtexto" id="metas_resumen"></span>
                    <span class="gp-chip-fila d-none" id="metas_badge"></span>
                    <span class="gp-chip-fila ambar d-none" id="metas_sucio"><i class="bi bi-pencil me-1"></i>Cambios sin guardar</span>
                    <button type="button" class="btn btn-link btn-sm text-success gp-seccion-toggle gp-ppto-metas-toggle" data-bs-toggle="collapse" data-bs-target="#metas_cuerpo" aria-expanded="true" aria-controls="metas_cuerpo" aria-label="Mostrar u ocultar meta y estacionalidad">
                        <i class="bi bi-chevron-down gp-chevron"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="metas_cuerpo">
                <div class="card-body py-3 px-4">

                    <div class="gp-nota-alerta mb-3 d-none" role="alert" id="metas_sin_tablas">
                        <div class="d-flex gap-2">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>
                                <strong>Las tablas de metas y estacionalidad no existen en la base de datos</strong>
                                <div>Solicite al administrador ejecutar el script de creación: <code>Documentacion/BaseDeDatos/crear_presupuesto_metas.sql</code></div>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="metas_copiar_ruta"><i class="bi bi-clipboard me-1"></i>Copiar ruta</button>
                            </div>
                        </div>
                    </div>

                    <div class="gp-estado-error gp-ppto-metas-error d-none" id="metas_error">
                        <i class="bi bi-wifi-off"></i>
                        <span class="me-auto">No fue posible cargar las metas. <span id="metas_error_texto"></span></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="metas_reintentar"><i class="bi bi-arrow-clockwise me-1"></i>Reintentar</button>
                    </div>

                    <fieldset id="metas_emp_campos" disabled>
                        <div class="gp-subseccion"><i class="bi bi-building"></i>Empresa</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="meta_emp_tonha">Meta t/ha</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control text-end font-monospace" id="meta_emp_tonha" inputmode="decimal" autocomplete="off" placeholder="0,00">
                                    <span class="input-group-text bg-light">t/ha</span>
                                    <div class="invalid-feedback" id="meta_emp_tonha_error"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="gp-gramos-cab"><label class="form-label mb-1 small fw-semibold text-muted" for="meta_emp_kilos">Kilos anuales totales</label><span class="gp-gramos ms-auto d-none" id="meta_emp_kilos_g"></span></div>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control text-end font-monospace" id="meta_emp_kilos" inputmode="decimal" autocomplete="off" placeholder="0" data-gramos aria-describedby="meta_emp_kilos_g">
                                    <span class="input-group-text bg-light">kg</span>
                                    <div class="invalid-feedback" id="meta_emp_kilos_error"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md">
                                <span class="gp-subtexto font-monospace" id="meta_emp_lectura"></span>
                                <span class="gp-subtexto font-monospace" id="meta_emp_equiv"></span>
                                <span class="gp-chip-fila ambar d-none mt-1" id="meta_emp_difiere"><i class="bi bi-info-circle me-1"></i>No coincide con la meta t/ha</span>
                            </div>
                            <div class="col-12 col-md-auto d-grid d-md-block">
                                <button type="button" class="btn btn-outline-success btn-sm" id="meta_emp_guardar"><i class="bi bi-floppy me-1"></i>Guardar meta empresa</button>
                            </div>
                            <div class="col-12">
                                <span class="gp-subtexto font-monospace" id="meta_emp_fincas"></span>
                            </div>
                        </div>
                    </fieldset>

                    <hr class="my-3">

                    <div class="gp-subseccion"><i class="bi bi-geo-alt"></i>Finca <span class="fw-normal text-muted" id="meta_finca_nombre"></span></div>
                    <div class="gp-nota mb-2" id="meta_finca_nota"><i class="bi bi-info-circle me-1"></i>Seleccione una finca para definir su meta y curva</div>

                    <fieldset id="metas_finca_campos" disabled>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-3">
                                <div class="gp-gramos-cab"><label class="form-label mb-1 small fw-semibold text-muted" for="meta_finca_kilos">Meta anual</label><span class="gp-gramos ms-auto d-none" id="meta_finca_kilos_g"></span></div>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control text-end font-monospace" id="meta_finca_kilos" inputmode="decimal" autocomplete="off" placeholder="0" data-gramos aria-describedby="meta_finca_kilos_g">
                                    <span class="input-group-text bg-light">kg</span>
                                    <div class="invalid-feedback" id="meta_finca_kilos_error"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="meta_finca_tonha">Meta t/ha <span class="fw-normal">(opcional)</span></label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control text-end font-monospace" id="meta_finca_tonha" inputmode="decimal" autocomplete="off" placeholder="0,00">
                                    <span class="input-group-text bg-light">t/ha</span>
                                    <div class="invalid-feedback" id="meta_finca_tonha_error"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md">
                                <span class="gp-subtexto font-monospace" id="meta_finca_lectura"></span>
                                <button type="button" class="btn btn-link btn-sm p-0 d-none" id="meta_calcular"><i class="bi bi-calculator me-1"></i>Calcular kg desde t/ha</button>
                            </div>
                        </div>

                        <fieldset class="gp-ppto-curva mt-3">
                            <legend class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="small fw-semibold text-muted">Curva de estacionalidad (% del año)</span>
                                <span class="gp-chip-fila font-monospace" id="curva_total"></span>
                            </legend>
                            <div class="row row-cols-3 row-cols-sm-4 row-cols-lg-6 row-cols-xxl-12 g-2">
                                <?php foreach ($meses as $i => $mes): ?>
                                    <div class="col">
                                        <label class="form-label mb-1 small text-muted" for="curva_<?= $i + 1 ?>"><abbr title="<?= $mes ?>"><?= mb_substr($mes, 0, 3) ?></abbr></label>
                                        <div class="input-group input-group-sm has-validation">
                                            <input type="text" class="form-control text-end font-monospace gp-ppto-pct" id="curva_<?= $i + 1 ?>" data-mes="<?= $i + 1 ?>" inputmode="decimal" autocomplete="off" placeholder="0">
                                            <span class="input-group-text bg-light">%</span>
                                            <div class="invalid-feedback" id="curva_<?= $i + 1 ?>_error"></div>
                                        </div>
                                        <div class="form-text mt-1 text-end font-monospace gp-ppto-kg-mes" id="curva_kg_<?= $i + 1 ?>">&ndash;</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="gp-ppto-curva-grafica mt-2" id="curva_grafica" role="img" aria-label="Curva de estacionalidad"></div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="curva_copiar"><i class="bi bi-copy me-1"></i>Copiar curva de…</button>
                                    <ul class="dropdown-menu" id="curva_copiar_menu" aria-labelledby="curva_copiar"></ul>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="curva_uniforme"><i class="bi bi-distribute-horizontal me-1"></i>Repartir uniforme</button>
                            </div>
                        </fieldset>

                        <div class="d-grid d-md-flex justify-content-md-end gap-2 mt-3">
                            <button type="button" class="btn btn-primary" id="meta_finca_guardar"><i class="bi bi-floppy me-1"></i>Guardar meta y curva</button>
                            <button type="button" class="btn btn-success" id="meta_generar" aria-describedby="meta_motivo"><i class="bi bi-magic me-1"></i>Generar presupuesto por lotes</button>
                        </div>
                        <div class="form-text text-md-end" id="meta_motivo"></div>
                    </fieldset>

                </div>
            </div>
            <div class="gp-ppto-cargando d-none" id="metas_cargando">
                <div class="spinner-border text-success" role="status"><span class="visually-hidden">Cargando…</span></div>
            </div>
        </div>

        <div class="card mb-3 gp-ppto-form" id="ppto_form">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="gp-seccion m-0 p-0" id="form_titulo"><i class="bi bi-plus-circle"></i>Registrar presupuesto</div>
                <span class="gp-subtexto" id="form_contexto">Seleccione una finca para registrar</span>
            </div>
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="form_mes">Mes</label>
                        <select class="form-select" id="form_mes" disabled>
                            <?php foreach ($meses as $i => $mes): ?>
                                <option value="<?= $i + 1 ?>"><?= $mes ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback" id="form_mes_error"></div>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="form_lote">Lote</label>
                        <select class="form-select" id="form_lote" disabled>
                            <option value=""></option>
                        </select>
                        <div class="invalid-feedback" id="form_lote_error"></div>
                    </div>
                    <div class="col-12 col-md-2">
                        <div class="gp-gramos-cab"><label class="form-label mb-1 small fw-semibold text-muted" for="form_kilos">Kilos</label><span class="gp-gramos ms-auto d-none" id="form_kilos_g"></span></div>
                        <div class="input-group has-validation">
                            <input type="text" class="form-control text-end font-monospace" id="form_kilos" inputmode="decimal" autocomplete="off" placeholder="0" disabled data-gramos aria-describedby="form_kilos_g">
                            <span class="input-group-text bg-light">kg</span>
                            <div class="invalid-feedback" id="form_kilos_error"></div>
                        </div>
                    </div>
                    <div class="col-12 d-grid d-md-flex justify-content-md-end gap-2">
                        <button type="button" class="btn btn-outline-danger me-md-auto d-none" id="form_eliminar">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                        <button type="button" class="btn btn-primary" id="form_guardar" disabled>
                            <i class="bi bi-floppy me-1"></i>Guardar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="form_limpiar" disabled>Limpiar</button>
                    </div>
                    <div class="col-12 d-none" id="form_ayuda_col">
                        <div class="form-text mt-0" id="form_ayuda"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <div>
                    <span class="gp-contador" id="contador_ppto"><i class="bi bi-calendar3 me-1"></i><strong><?= esc($anio_sel) ?></strong></span>
                    <span class="gp-subtexto">Valores en kilogramos (kg)</span>
                    <span class="gp-subtexto d-none" id="sub_historico">Incluye metas de finca (histórico)</span>
                </div>
                <div class="d-flex flex-wrap gap-3 small text-muted">
                    <span><span class="gp-ppto-muestra vacio"></span>Sin presupuesto</span>
                    <span class="d-none" id="leyenda_bloqueada"><span class="gp-ppto-muestra bloqueada"></span>Bloqueado por meta de finca</span>
                </div>
            </div>
            <div class="card-body p-0 position-relative gp-ppto-zona" id="ppto_zona" aria-busy="false">

                <div class="gp-nota-alerta m-3 d-none" id="nota_historico"></div>

                <div class="gp-nota m-3 d-none" id="nota_sin_datos"></div>

                <div id="ppto_contenido" class="d-none">
                    <div class="table-responsive gp-scroll-grilla">
                        <table class="table table-hover align-middle mb-0 gp-tabla gp-ppto-matriz">
                            <caption class="visually-hidden" id="caption_ppto"></caption>
                            <thead class="gp-thead">
                                <tr>
                                    <th scope="col" class="gp-ppto-col-lote">Lote</th>
                                    <?php foreach ($meses as $i => $mes): ?>
                                        <th scope="col" class="text-end gp-ppto-col-mes" data-mes="<?= $i + 1 ?>"><abbr title="<?= $mes ?>"><?= mb_substr($mes, 0, 3) ?></abbr><i class="bi bi-lock-fill text-warning ms-1 d-none" aria-hidden="true"></i></th>
                                    <?php endforeach; ?>
                                    <th scope="col" class="gp-ppto-col-total">Total</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_ppto"></tbody>
                            <tfoot class="gp-ppto-pie" id="pie_ppto"></tfoot>
                        </table>
                    </div>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-geo-alt" id="vacio_icono"></i>
                    </div>
                    <h6 id="vacio_titulo">Seleccione una finca</h6>
                    <p class="text-muted mb-0" id="vacio_texto">Elija empresa, año y finca para ver y registrar el presupuesto por lote.</p>
                </div>

                <div id="gp_estado_error" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon chico gp-ppto-error d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-wifi-off"></i>
                    </div>
                    <h6>No fue posible cargar el presupuesto</h6>
                    <p class="text-muted" id="error_mensaje"></p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reintentar">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reintentar
                    </button>
                </div>

                <div class="gp-ppto-cargando d-none" id="ppto_cargando">
                    <div class="spinner-border text-success" role="status"><span class="visually-hidden">Cargando…</span></div>
                </div>

            </div>
        </div>

        <div class="visually-hidden" aria-live="polite" id="ppto_anuncio"></div>

        <div class="modal fade" id="modal_generar" tabindex="-1" aria-labelledby="generar_titulo" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="generar_titulo">Previsualización</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" id="generar_cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3" id="generar_kpis"></div>
                        <div id="generar_historico"></div>
                        <div class="table-responsive gp-ppto-previa">
                            <table class="table align-middle mb-0 gp-tabla gp-ppto-matriz">
                                <caption class="visually-hidden" id="generar_caption"></caption>
                                <thead class="gp-thead">
                                    <tr>
                                        <th scope="col" class="gp-ppto-col-lote">Lote</th>
                                        <?php foreach ($meses as $i => $mes): ?>
                                            <th scope="col" class="text-end gp-ppto-col-mes"><abbr title="<?= $mes ?>"><?= mb_substr($mes, 0, 3) ?></abbr></th>
                                        <?php endforeach; ?>
                                        <th scope="col" class="gp-ppto-col-total">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="generar_cuerpo"></tbody>
                                <tfoot class="gp-ppto-pie" id="generar_pie"></tfoot>
                            </table>
                        </div>
                        <div class="gp-subtexto mt-2"><i class="bi bi-info-circle me-1"></i>Valores redondeados; el residuo se asigna al lote de mayor área.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="generar_cancelar">Cancelar</button>
                        <button type="button" class="btn btn-success" id="generar_confirmar"><i class="bi bi-check2-circle me-1"></i>Confirmar y guardar</button>
                    </div>
                </div>
            </div>
        </div>

    </section>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const TABLA_EXISTE = <?= $tabla_existe ? 'true' : 'false' ?>;
</script>
<script src="<?= asset_url('public/assets/js/gestionpalma/presupuesto_produccion.js') ?>"></script>
<?php echo $this->endSection(); ?>
