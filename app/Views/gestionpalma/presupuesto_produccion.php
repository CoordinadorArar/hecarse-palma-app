<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css?v=<?= filemtime(FCPATH . 'public/assets/css/gestionpalma.css') ?>">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<?php
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$esqueleto = '<td><span class="placeholder col-8"></span></td>';
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
            <i class="bi bi-info-circle me-1"></i>Alimenta el informe Presupuesto vs Ejecutado.
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
                    <div class="col-12 col-md-4">
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
                        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-tree"></i></span>
                        <div>
                            <div class="gp-etiqueta-mini">Fincas con presupuesto</div>
                            <div class="gp-ppto-kpi" id="kpi_fincas">&ndash;</div>
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

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <div>
                    <span class="gp-contador" id="contador_ppto"><i class="bi bi-calendar3 me-1"></i><strong><?= esc($anio_sel) ?></strong></span>
                    <span class="gp-subtexto">Valores en kilogramos (kg)</span>
                </div>
                <div class="d-flex gap-3 small text-muted">
                    <span><span class="gp-ppto-muestra vacio"></span>Sin presupuesto</span>
                    <span><span class="gp-ppto-muestra cero"></span>Cero</span>
                </div>
            </div>
            <div class="card-body p-0 position-relative gp-ppto-zona <?= $tabla_existe ? '' : 'gp-ppto-bloqueada' ?>" id="ppto_zona">

                <div class="gp-nota m-3 d-none" id="nota_sin_datos"></div>

                <div id="ppto_contenido">
                    <div class="table-responsive gp-scroll-grilla d-none d-md-block">
                        <table class="table table-hover align-middle mb-0 gp-tabla gp-ppto-matriz">
                            <thead class="gp-thead">
                                <tr>
                                    <th class="gp-ppto-col-finca">Finca</th>
                                    <?php foreach ($meses as $i => $mes): ?>
                                        <th class="text-end gp-ppto-col-mes" data-mes="<?= $i + 1 ?>" title="<?= $mes ?>"><?= mb_substr($mes, 0, 3) ?></th>
                                    <?php endforeach; ?>
                                    <th class="gp-ppto-col-total">Total</th>
                                    <th class="text-center gp-col-sticky" style="width:96px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_ppto">
                                <?php for ($f = 0; $f < 4; $f++): ?>
                                    <tr class="placeholder-glow"><?= str_repeat($esqueleto, 15) ?></tr>
                                <?php endfor; ?>
                            </tbody>
                            <tfoot class="gp-ppto-pie" id="pie_ppto"></tfoot>
                        </table>
                    </div>
                    <div class="gp-ppto-lista d-md-none p-2" id="lista_ppto"></div>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-tree"></i>
                    </div>
                    <h6>La empresa no tiene fincas registradas</h6>
                    <p class="text-muted mb-0">Registre fincas en Gestión Palma › Fincas para poder presupuestar.</p>
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
                    <div class="spinner-border text-success" role="status"></div>
                </div>

            </div>
        </div>

    </section>

    <div class="modal fade gp-ppto-modal" id="modalPresupuestoMes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body" id="editor_mes_modal"></div>
            </div>
        </div>
    </div>

    <div class="modal fade gp-ppto-modal" id="modalPresupuestoAnual" tabindex="-1" aria-labelledby="titulo_modal_anual" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="titulo_modal_anual"></h5>
                        <span class="gp-subtexto" id="subtitulo_modal_anual"></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="gp-referencia mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="anual_distribuir">Distribuir total anual (kg)</label>
                                <div class="input-group input-group-sm has-validation">
                                    <input type="text" class="form-control gp-input-precio" id="anual_distribuir" inputmode="decimal" autocomplete="off">
                                    <button type="button" class="btn btn-outline-success" id="btn_distribuir">
                                        <i class="bi bi-distribute-horizontal me-1"></i>Distribuir
                                    </button>
                                    <div class="invalid-feedback">Ingrese un valor mayor o igual a 0 con máximo 2 decimales</div>
                                </div>
                                <div class="form-text">Reparte en partes iguales; el residuo de redondeo se suma a diciembre.</div>
                            </div>
                            <div class="col-12 col-md-6 text-md-end">
                                <div class="d-grid d-md-block">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_copiar_anterior">
                                        <i class="bi bi-clipboard-check me-1"></i>Copiar del año anterior
                                    </button>
                                </div>
                                <span class="gp-subtexto mt-1" id="anual_total_anterior"></span>
                            </div>
                        </div>
                    </div>

                    <div class="gp-seccion mb-3">
                        <i class="bi bi-calendar-month"></i>Kilos por mes
                        <button type="button" class="btn btn-link btn-sm p-0 text-danger ms-auto" id="btn_limpiar_anual">Limpiar todo</button>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($meses as $i => $mes): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="anual_mes_<?= $i + 1 ?>"><?= $mes ?></label>
                                <div class="input-group input-group-sm has-validation">
                                    <input type="text" class="form-control gp-input-precio" id="anual_mes_<?= $i + 1 ?>" data-mes="<?= $i + 1 ?>" inputmode="decimal" autocomplete="off" placeholder="Sin presupuesto">
                                    <span class="input-group-text bg-light">kg</span>
                                    <div class="invalid-feedback">Ingrese un valor mayor o igual a 0 con máximo 2 decimales</div>
                                </div>
                                <span class="gp-subtexto mt-1" id="anual_ant_<?= $i + 1 ?>"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="gp-ppto-total-vivo mt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Total anual</div>
                            <span class="gp-subtexto" id="anual_conteo"></span>
                        </div>
                        <div class="text-end">
                            <span class="gp-ppto-total-valor fs-5 fw-semibold" id="anual_total"></span> <span class="small text-muted">kg</span>
                            <span id="anual_delta"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-danger me-auto" id="btn_eliminar_anual">
                        <i class="bi bi-trash me-1"></i>Eliminar año
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btn_guardar_anual">
                        <i class="bi bi-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const TABLA_EXISTE = <?= $tabla_existe ? 'true' : 'false' ?>;
</script>
<script src="<?= base_url('public/assets/js/gestionpalma/presupuesto_produccion.js') ?>?v=<?= filemtime(FCPATH . 'public/assets/js/gestionpalma/presupuesto_produccion.js') ?>"></script>
<?php echo $this->endSection(); ?>
