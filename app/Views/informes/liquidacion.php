<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/transacciones.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle d-flex justify-content-between align-items-start">
        <h1>Liquidación de nómina</h1>

        <a href="<?= base_url() ?>public/documents/app/ManualLiquidacion.pdf?v-1.2" target="_blank"
            class="btn btn-link" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Revisar Manual">
            <i class="bi bi-question-circle-fill fs-4"></i>
        </a>
    </div>

    <div class="card mb-3 gp-filtros">
        <div class="card-body py-3 px-4">
            <form id="form_filtros" class="row g-2 align-items-end" novalidate>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_desde">Fecha inicio</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                        <input type="date" class="form-control" id="filtro_desde" min="2000-01-01" max="2100-12-31" aria-describedby="error_rango">
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_hasta">Fecha fin</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                        <input type="date" class="form-control" id="filtro_hasta" min="2000-01-01" max="2100-12-31" aria-describedby="error_rango">
                    </div>
                </div>

                <div class="col-12 col-md-auto">
                    <span class="form-label mb-1 small fw-semibold text-muted d-block">Periodo</span>
                    <div class="btn-group btn-group-sm w-100" role="group" aria-label="Accesos rápidos de periodo">
                        <button type="button" class="btn btn-outline-success text-nowrap" data-rango="mes" aria-pressed="false">Este mes</button>
                        <button type="button" class="btn btn-outline-success text-nowrap" data-rango="anterior" aria-pressed="false">Mes anterior</button>
                    </div>
                </div>

                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100" id="consultar_liquidacion">
                        <i class="bi bi-search me-1"></i> Consultar
                    </button>
                </div>

                <div class="col-12 col-md-auto ms-md-auto">
                    <ul class="nav nav-pills nav-fill flex-nowrap tx-pestanas" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link active" id="tab_temporales" data-bs-toggle="pill" data-bs-target="#panel_temporales" role="tab" aria-controls="panel_temporales" aria-selected="true">
                                <i class="bi bi-people" aria-hidden="true"></i>Temporales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" id="tab_siesa" data-bs-toggle="pill" data-bs-target="#panel_siesa" role="tab" aria-controls="panel_siesa" aria-selected="false">
                                <i class="bi bi-bank" aria-hidden="true"></i>Nómina Siesa
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="col-12 d-none" id="fila_error_rango">
                    <div class="invalid-feedback d-block small mt-0" id="error_rango" role="alert"></div>
                </div>
            </form>
        </div>
    </div>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="panel_temporales" role="tabpanel" aria-labelledby="tab_temporales">

    <div class="tab-content" id="moduleManagementTabs">
        <div class="tab-pane fade show active" id="moduleList" role="tabpanel" aria-labelledby="moduleList-tab">
            <div class="card mb-4">
                <div class="card-body pt-4">

                    <!-- <div class="d-flex align-items-start gap-3 mb-4 pt-4">
                        <i class="bi bi-info-circle fs-4 text-primary"></i>
                        <div>
                            <h6 class="mb-1 fw-semibold">Recomendación</h6>
                            <p class="mb-2 text-muted">
                                Antes de consultar se recomienda actualizar los contratos para mejorar el reporte.
                            </p>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="actualizar_contratos">
                                <i class="bi bi-arrow-repeat me-1"></i> Actualizar contratos
                            </button>
                        </div>
                    </div> -->

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Tipo de contrato</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="tipoContrato">
                                        <option disabled selected>Seleccione el tipo de contrato</option>
                                        <option value="Directo"> DIRECTO </option>
                                        <option value="Temporal"> TEMPORAL </option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Temporal</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-diagram-3"></i></span>
                                    <select class="form-select" id="temporal">
                                        <option disabled selected>Seleccione la temporal</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 d-flex align-items-end gap-4">
                                <button class="btn btn-outline-primary" id="filtrar_reporte">
                                    <i class="bi bi-funnel me-1"></i> Filtrar
                                </button>
                                <button class="btn btn-outline-secondary" id="limpiar_filtros">
                                    <i class="bi bi-x-circle me-1"></i> Limpiar Filtros
                                </button>
                            </div>
                        </div>

                        <div id="aviso_cruces"></div>

                    <!-- Modal empleados sin temporal -->
                    <div class="modal fade" id="modalSinTemporal" tabindex="-1">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Lista de empleados sin temporal asignada</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <table id="tabla-sin-temporal" class="table table-striped table-bordered w-100">
                                        <thead>
                                            <tr>
                                                <th>Documento</th>
                                                <th>Empleado</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                                <div class="modal-footer">
                                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <button type="button" class="btn btn-primary" id="btnAbrirModalTemporales">
                            <i class="bi bi-upload"></i> Subida Valores Temporal
                        </button>
                    </div>

                    <div class="modal fade" id="modalActualizarTemporales" tabindex="-1"
                        aria-labelledby="modalActualizarTemporalesLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalActualizarTemporalesLabel">
                                        <i class="bi bi-upload me-2"></i>Subida de valores pagados por la temporal
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="gp-referencia d-flex justify-content-between align-items-center gap-2 mb-3">
                                        <div>
                                            <span class="gp-etiqueta-mini d-block">Rango de aplicación</span>
                                            <span class="fw-semibold" id="modal_rango"></span>
                                        </div>
                                        <span class="gp-subtexto text-nowrap" id="modal_rango_dias"></span>
                                    </div>

                                    <div class="alert alert-info d-flex align-items-start" role="alert">
                                        <i class="bi bi-info-circle fs-5 me-2"></i>
                                        <div>
                                            <strong>Instrucciones:</strong>
                                            <ol class="mb-0 mt-2">
                                                <li>Descarga la plantilla de Excel</li>
                                                <li>Completa los datos según el formato requerido. Las columnas Año y Mes de la plantilla no se tienen en cuenta.</li>
                                                <li>Sube el archivo completado</li>
                                            </ol>
                                        </div>
                                    </div>

                                    <form id="formExcel" enctype="multipart/form-data">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-file-earmark-excel me-1"></i>Archivo Excel
                                                </label>
                                                <input type="file" name="archivo" class="form-control"
                                                    accept=".xlsx,.xls" required>
                                                <small class="text-muted">
                                                    Formatos permitidos: .xlsx, .xls
                                                </small>
                                            </div>

                                            <div class="col-12">
                                                <div class="d-grid gap-2">
                                                    <a href="<?= base_url() ?>public/documents/app/Plantilla_SubTotal_Temporales.xlsx"
                                                        download class="btn btn-outline-primary">
                                                        <i class="bi bi-download me-1"></i> Descargar plantilla
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="bi bi-x-circle me-1"></i>Cancelar
                                    </button>
                                    <button type="submit" form="formExcel" class="btn btn-success">
                                        <i class="bi bi-upload me-1"></i>Subir y actualizar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>


    <div class="gp-nota d-none align-items-start gap-2 mb-3" id="nota_rango">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>Cambió el rango de fechas. Pulse Consultar para actualizar.</span>
    </div>

    <div class="card" id="tarjeta_reporte">
        <div class="card-body p-0">

            <div class="gp-estado-vacio text-center py-4" id="reporte_vacio">
                <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi bi-calendar-range" aria-hidden="true"></i></div>
                <h6 class="mb-0">Seleccione el rango de fechas y pulse Consultar</h6>
            </div>

            <ul class="nav nav-tabs nav-tabs-bordered px-3 pt-3 d-none" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#reporteTotal">
                        Reporte Total
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#reporteDetalladoLabor">
                        Detallado por Labor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#reporteDetalladoTotal">
                        Detallado Total
                    </a>
                </li>
            </ul>

            <div class="tab-content p-4 d-none">

                <div class="tab-pane fade show active" id="reporteTotal">
                    <table id="tabla-total" class="table table-striped w-100"></table>

                    <div class="text-end mt-3">
                        <button class="btn btn-success btn-sm d-none" id="btnExportar">
                            <i class="bi bi-file-earmark-excel"></i> Exportar
                        </button>

                        <button class="btn btn-primary d-none" id="btnProcesar">
                            <i class="fas fa-save"></i> Procesar y Guardar
                        </button>
                    </div>
                </div>

                <div class="tab-pane fade" id="reporteDetalladoLabor">
                    <table id="tabla-labor" class="table table-striped w-100"></table>
                </div>

                <div class="tab-pane fade" id="reporteDetalladoTotal">
                    <table id="tabla-detallado" class="table table-striped w-100"></table>
                </div>

            </div>
        </div>
    </div>

    </div>

    <div class="tab-pane fade" id="panel_siesa" role="tabpanel" aria-labelledby="tab_siesa">

        <div class="gp-nota d-flex align-items-start gap-2 mb-3">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>El cruce se realiza por cédula y por el rango de fechas del documento de nómina (NNM) de Siesa, solo con transacciones no anuladas. El valor Siesa corresponde a los conceptos de labores 070 a 078. Siesa solo se consulta; no se modifica.</span>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="gp-seccion mb-0"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>Documento de nómina</h6>
                    <span class="tx-chip-cont verde d-none" id="siesa_num_docs"></span>
                </div>
                <div class="input-group d-none" id="siesa_selector" style="max-width:420px">
                    <span class="input-group-text"><i class="bi bi-files" aria-hidden="true"></i></span>
                    <select class="form-select" id="siesa_documento" aria-label="Documento de nómina"></select>
                </div>
            </div>
            <div class="card-body pt-3" id="siesa_cuerpo">
                <div class="gp-estado-vacio text-center py-4">
                    <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi bi-calendar-range" aria-hidden="true"></i></div>
                    <h6 class="mb-0">Seleccione el rango de fechas y pulse Consultar</h6>
                </div>
            </div>
            <?php if (! empty($puedeMarcar)): ?>
            <div class="card-footer bg-white d-none flex-wrap justify-content-between align-items-center gap-2 py-3" id="siesa_pie">
                <span class="gp-subtexto" id="siesa_motivo" aria-live="polite"></span>
                <div class="d-flex gap-2 flex-fill flex-md-grow-0">
                    <button type="button" class="btn btn-outline-danger flex-fill" id="btn_siesa_revertir" disabled>
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Revertir marca
                    </button>
                    <button type="button" class="btn btn-primary flex-fill" id="btn_siesa_marcar" disabled>
                        <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Marcar como liquidado
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="d-none" id="siesa_resultados">
            <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-3" id="siesa_kpis"></div>

            <div class="card">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <span class="gp-contador" id="siesa_contador"></span>
                    <span class="gp-subtexto">Diferencia = valor Siesa − valor AppPalma</span>
                </div>
                <div class="card-body p-0">
                    <table id="tabla-siesa" class="table table-hover align-middle mb-0 gp-tabla inf-tabla w-100">
                        <thead class="gp-thead">
                            <tr>
                                <th>Cédula</th>
                                <th>Empleado</th>
                                <th class="text-end">Valor Siesa</th>
                                <th class="text-end">Valor AppPalma</th>
                                <th class="text-end">Diferencia</th>
                                <th class="text-center">Pendientes</th>
                                <th class="text-center">Marcadas</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    </div>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>
<script>
    const CFG_SIESA = {
        urls: {
            consultar: "<?= base_url('informes/liquidacion-siesa/consultar') ?>",
            marcar: "<?= base_url('informes/liquidacion-siesa/marcar') ?>",
            revertir: "<?= base_url('informes/liquidacion-siesa/revertir') ?>"
        },
        puedeMarcar: <?= ! empty($puedeMarcar) ? 'true' : 'false' ?>
    };
</script>
<script src="<?= asset_url('public/assets/js/informes/liquidacion_siesa.js') ?>"></script>
<script>

    let InconsistenciasTemporal = false;
    let reporteProcesado = false; // Nueva variable
    let rangoConsulta = '';
    let rangoTemporales = '';
    let turnoTemporales = 0;
    let rangoModal = null;

    document.addEventListener('DOMContentLoaded', () => {
        const [desde, hasta] = LIQ_RANGOS.anterior(new Date());

        $('#filtro_desde').val(desde);
        $('#filtro_hasta').val(hasta);
        validar();

        $('#filtro_desde, #filtro_hasta').on('change', () => {
            $('#aviso_cruces').empty();
            revisarRango();
            validar();
        });
        $('[data-rango]').on('click', function () {
            const [desde, hasta] = LIQ_RANGOS[this.dataset.rango](new Date());

            $('#filtro_desde').val(desde);
            $('#filtro_hasta').val(hasta).trigger('change');
        });
        $('#form_filtros').on('submit', e => {
            e.preventDefault();

            if (!validar()) return;

            $('#tab_siesa').hasClass('active') ? consultarSiesa() : consultarReporte();
        });
        $('#tipoContrato, #temporal').on('change', () => $('#aviso_cruces').empty());
        $('#actualizar_contratos').on('click', actualizarContratos);
        $('#limpiar_filtros').on('click', limpiarFiltros);

        $('#btnAbrirModalTemporales').on('click', function () {
            const actual = rango();

            if (!validar()) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccione un rango de fechas válido antes de subir los valores.', returnFocus: false })
                    .then(() => $('#filtro_desde').trigger('focus'));
                return;
            }

            rangoModal = actual;
            $('#modal_rango').html(rangoTexto(actual.desde, actual.hasta));
            $('#modal_rango_dias').text(`${siesaNum(actual.dias)} ${actual.dias === 1 ? 'día' : 'días'}`);
            $('#modalActualizarTemporales').modal('show');
        });
    });

    function revisarRango() {
        const cambio = !!rangoConsulta && rangoConsulta !== rango().clave;

        $('#tarjeta_reporte').toggleClass('inf-desactualizado', cambio);
        $('#nota_rango').toggleClass('d-none', !cambio).toggleClass('d-flex', cambio);
        if (cambio) $('#btnProcesar, #btnExportar').hide();

        return cambio;
    }

    function textoCruces(temporal, cruces) {
        return `Ya existe un rango procesado para <strong>${escaparHtml(temporal)}</strong> que se cruza con el seleccionado: ${cruces.map(c => `<strong class="text-nowrap">${rangoTexto(c.desde, c.hasta)}</strong>`).join(', ')}. Ajuste las fechas para que no se superpongan.`;
    }

    function pintarCruces(contenido) {
        $('#aviso_cruces').html(`<div class="gp-nota-alerta d-flex align-items-start gap-2 mb-3"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>${contenido}</span></div>`);
    }

    function mostrarErrorRespuesta(response, temporal, generico, codigo) {
        const cruces = Array.isArray(response?.cruces) ? response.cruces : [];

        if (cruces.length || codigo === 409) {
            const contenido = cruces.length ? textoCruces(temporal, cruces) : escaparHtml(response?.message || generico);

            reporteProcesado = false;
            $('#btnProcesar').hide();
            pintarCruces(contenido);
            Swal.fire({ icon: 'warning', title: 'El rango se cruza con uno ya procesado', html: `<p class="mb-0">${contenido}</p>`, width: 560 });
            return;
        }

        Swal.fire('Error', response?.message || generico, 'error');
    }

    function limpiarFiltros() {
        $('#aviso_cruces').empty();
        // Resetear los selectores a sus valores por defecto
        $('#tipoContrato').val($('#tipoContrato option:first').val());
        $('#temporal').val($('#temporal option:first').val());

        // Recargar las tablas con todos los datos
        if (dataGlobal.length > 0) {
            cargarReporteTotal();
            cargarReporteLabor();
            cargarReporteDetallado();

            // Ocultar botón de procesar si estaba visible
            $('#btnProcesar').hide();

            // Notificación opcional
            Swal.fire({
                icon: 'info',
                title: 'Filtros limpiados',
                text: 'Se muestran todos los datos',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
        }
    }


    function actualizarContratos() {

        bloquearUI(true);

        $.post("<?= base_url('actualizar-contratos') ?>", {}, response => {

            if (response.status === 'success') {
                Swal.fire('Éxito', response.message, 'success');
            } else {
                Swal.fire('Error', response.message || 'Ocurrió un error', 'error');
            }

            bloquearUI(false);
        }).fail(() => {
            bloquearUI(false);
            Swal.fire('Error', 'No fue posible actualizar los contratos', 'error');
        });
    }


    function cargarTemporales() {
        const { desde, hasta, valido, clave } = rango();
        const select = $('#temporal');
        const turno = ++turnoTemporales;

        if (!valido) return;

        rangoTemporales = clave;
        InconsistenciasTemporal = false;
        reporteProcesado = false;
        $('#btnProcesar').hide();

        // Resetear filtros
        $('#tipoContrato').val($('#tipoContrato option:first').val());
        $('#temporal').html('<option disabled selected>Seleccione la temporal</option>');
        select.prop('disabled', true);

        fetch("<?= base_url('informes/temporales') ?>", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `desde=${desde}&hasta=${hasta}`
        })
            .then(res => res.json().catch(() => null).then(data => {
                if (!res.ok || !Array.isArray(data)) throw new Error(data?.message || 'No fue posible cargar las temporales');
                return data;
            }))
            .then(data => {
                if (turno !== turnoTemporales) return;

                select.html('<option disabled selected>Seleccione la temporal</option>');

                const temporalesSet = new Set();
                InconsistenciasTemporal = false;

                data.forEach(item => {

                    // SOLO contratos temporales
                    if (item.TipoContrato !== 'Temporal') {
                        return;
                    }

                    // Detectar inconsistencias reales
                    if (!item.RazonTemporal || item.RazonTemporal.trim() === '') {
                        InconsistenciasTemporal = true;
                        return;
                    }

                    // Evitar duplicados
                    temporalesSet.add(item.RazonTemporal.trim());
                });

                // Llenar select
                temporalesSet.forEach(razon => {
                    select.append(`
            <option value="${escaparHtml(razon)}">${escaparHtml(razon)}</option>
        `);
                });

                select.prop('disabled', false);

                if (InconsistenciasTemporal) {
                    mostrarAlertaInconsistencias();
                }
            })
            .catch(error => {
                if (turno !== turnoTemporales) return;
                select.prop('disabled', false);
                rangoTemporales = '';
                Swal.fire('Error', error.message, 'error');
            });
    }


    function mostrarAlertaInconsistencias() {
        Swal.fire({
            icon: 'error',
            title: 'Inconsistencias detectadas',
            html: `
            <p>Existen empleados <b>sin temporal asignada</b>.</p>
            <p>No es posible descargar el Excel hasta corregir estas inconsistencias.</p>
        `,
            confirmButtonText: 'Ver empleados'
        }).then(result => {
            if (result.isConfirmed) {
                cargarEmpleadosSinTemporal();
            }
        });
    }

    let dataGlobal = [];

    function consultarReporte() {
        const { desde, hasta, clave } = rango();

        if (!validar()) return;

        bloquearUI(true);
        reporteProcesado = false;
        $('#btnProcesar').hide();
        $('#aviso_cruces').empty();

        $.post("<?= base_url('informes/consultar-reporte') ?>", {
            desde, hasta
        }, response => {

            dataGlobal = response;
            rangoConsulta = clave;
            $('#reporte_vacio').addClass('d-none');
            $('#tarjeta_reporte .nav-tabs, #tarjeta_reporte .tab-content').removeClass('d-none');
            if (rangoTemporales !== clave) cargarTemporales();

            cargarReporteTotal();
            cargarReporteLabor();
            cargarReporteDetallado();

            bloquearUI(false);
            revisarRango();
        }).fail(xhr => {
            bloquearUI(false);
            Swal.fire('Error', xhr.responseJSON?.message || 'No fue posible consultar el reporte', 'error');
        });
    }

    function agruparPorEmpleado(data) {
        const agrupado = {};

        data.forEach(item => {
            const key = item.nombreTercero;

            if (!agrupado[key]) {
                agrupado[key] = {
                    cedula: item.cedula,
                    nombreTercero: item.nombreTercero,
                    TipoContrato: item.TipoContrato,
                    RazonTemporal: item.RazonTemporal,
                    valorTotalTercero: 0,
                };
            }

            agrupado[key].valorTotalTercero += parseFloat(item.valorTotalTercero);
        });

        return Object.values(agrupado);
    }


    function agruparPorLabor(data) {
        const agrupado = {};

        data.forEach(item => {
            const key = item.nombreGrupoLabor + '_' + item.cedula;

            if (!agrupado[key]) {
                agrupado[key] = {
                    labor: item.nombreGrupoLabor,
                    cedula: item.cedula,
                    nombreTercero: item.nombreTercero,
                    TipoContrato: item.TipoContrato,
                    RazonTemporal: item.RazonTemporal,
                    valorTotalTercero: 0
                };
            }

            agrupado[key].valorTotalTercero += parseFloat(item.valorTotalTercero);
        });

        return Object.values(agrupado);
    }

    /* Filtrar Tablas */
    function filtrarDataGlobal() {
        const tipoContrato = $('#tipoContrato').val();
        const temporal = $('#temporal').val();

        return dataGlobal.filter(item => {

            let cumple = true;

            if (tipoContrato && tipoContrato !== 'Seleccione el tipo de contrato') {
                cumple = cumple && item.TipoContrato == tipoContrato;
            }

            if (temporal && temporal !== 'Seleccione la temporal') {
                cumple = cumple && item.RazonTemporal == temporal;
            }

            return cumple;
        });
    }

    function cargarReporteTotal() {
        const dataFiltrada = filtrarDataGlobal();
        const dataAgrupada = agruparPorEmpleado(dataFiltrada);

        if ($.fn.DataTable.isDataTable('#tabla-total')) {
            $('#tabla-total').DataTable().destroy();
        }

        $('#tabla-total').DataTable({
            data: dataAgrupada,
            columns: [
                { title: 'Cédula', data: 'cedula' },
                { title: 'Empleado', data: 'nombreTercero' },
                { title: 'Tipo Contrato', data: 'TipoContrato' },
                { title: 'Temporal', data: 'RazonTemporal' },
                {
                    title: 'Total Pagado',
                    data: 'valorTotalTercero',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$')
                },
            ],
            pageLength: 10
        });
    }


    function cargarReporteLabor() {
        const dataFiltrada = filtrarDataGlobal();
        const dataLabor = agruparPorLabor(dataFiltrada);

        if ($.fn.DataTable.isDataTable('#tabla-labor')) {
            $('#tabla-labor').DataTable().destroy();
        }

        $('#tabla-labor').DataTable({
            data: dataLabor,
            columns: [
                { title: 'Grupo Labor', data: 'labor' },
                { title: 'Cédula', data: 'cedula' },
                { title: 'Empleado', data: 'nombreTercero' },
                { title: 'Temporal', data: 'RazonTemporal' },
                {
                    title: 'Total Pagado',
                    data: 'valorTotalTercero',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$')
                },
            ],
            pageLength: 10
        });
    }

    function prepararReporteDetallado(data) {
        const agrupado = {};

        data.forEach(item => {
            const key = [
                item.nombreTercero,
                item.nombreGrupoLabor,
                item.ccosto
            ].join('|');

            if (!agrupado[key]) {
                agrupado[key] = {
                    nombreTercero: item.nombreTercero,
                    labor: item.nombreGrupoLabor,
                    uNegocio: item.uNegocio,
                    centroOperacion: item.centroOperacion,
                    ccosto: item.ccosto ?? '(vacío)',
                    valor: 0
                };
            }

            agrupado[key].valor += Number(item.valorTotalTercero);
        });

        return Object.values(agrupado);
    }

    let tablaDetallado = null;

    function cargarReporteDetallado() {
        const dataFiltrada = filtrarDataGlobal();
        const dataPreparada = prepararReporteDetallado(dataFiltrada);

        const empleadosUnicos = [...new Set(
            dataPreparada.map(d => d.nombreTercero)
        )];

        colapsados = {};
        dataPreparada.forEach(r => {
            colapsados[r.nombreTercero] = empleadosUnicos.length > 1;
        });

        if (tablaDetallado) {
            tablaDetallado.destroy();
            $('#tabla-detallado tbody').off('click');
        }

        tablaDetallado = $('#tabla-detallado').DataTable({
            data: dataPreparada,
            paging: false,
            dom: "<'tx-dt-barra'if><'table-responsive't>",
            language: { info: '_TOTAL_ registros', infoEmpty: '0 registros', infoFiltered: '(de _MAX_)' },

            columns: [
                { data: 'nombreTercero', visible: false },
                { data: 'labor', title: 'Labor' },
                { data: 'uNegocio', title: 'Unidad Negocio' },
                { data: 'centroOperacion', title: 'Centro Operación' },
                { data: 'ccosto', title: 'Centro Costo' },
                {
                    data: 'valor',
                    title: 'Valor',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$')
                }
            ],

            order: [[0, 'asc'], [1, 'asc']],

            rowGroup: {
                dataSrc: 'nombreTercero',
                startRender: function (rows, group) {
                    const total = rows
                        .data()
                        .pluck('valor')
                        .reduce((a, b) => a + Number(b), 0);

                    return `
                    <tr class="grupo-empleado" data-group="${group}">
                        <td colspan="6" style="cursor:pointer;font-weight:600">
                            ${colapsados[group] ? '▶ ' : '▼ '}
                            ${group}
                            <span class="text-muted ms-2">
                                Total: $${total.toLocaleString('es-CO')}
                            </span>
                        </td>
                    </tr>
                `;
                }
            },

            drawCallback: function () {
                const api = this.api();
                api.rows().every(function () {
                    const empleado = this.data().nombreTercero;
                    $(this.node()).toggle(!colapsados[empleado]);
                });
            }
        });

        $('#tabla-detallado tbody').on('click', 'tr.grupo-empleado', function () {
            const group = $(this).data('group');
            colapsados[group] = !colapsados[group];
            tablaDetallado.draw(false);
        });
    }

    function verificarReporteProcesado() {
        const { desde, hasta, valido } = rango();
        const temporal = $('#temporal').val();
        const tipoContrato = $('#tipoContrato').val();

        $('#aviso_cruces').empty();

        if (revisarRango() || !valido || !temporal || !tipoContrato ||
            temporal === 'Seleccione la temporal' ||
            tipoContrato === 'Seleccione el tipo de contrato') {
            $('#btnProcesar').hide();
            $('#btnExportar').hide();
            return;
        }

        $.post("<?= base_url('informes/verificar-procesado') ?>", {
            desde, hasta, temporal, tipoContrato
        }, response => {
            const cruces = Array.isArray(response.cruces) ? response.cruces : [];

            if (response.status === 'error' && !cruces.length) {
                $('#btnProcesar').hide();
                Swal.fire('Error', response.message || 'No fue posible verificar el reporte', 'error');
            } else if (response.procesado) {
                reporteProcesado = true;
                $('#btnProcesar').hide();
                $('#btnExportar').removeClass('d-none').show();
                Swal.fire({
                    icon: 'info',
                    title: 'Reporte ya procesado',
                    text: `Este rango ya fue procesado para ${temporal} (${rangoTexto(desde, hasta)}).`,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } else if (cruces.length) {
                reporteProcesado = false;
                $('#btnProcesar').hide();
                $('#btnExportar').removeClass('d-none').show();
                pintarCruces(textoCruces(temporal, cruces));
            } else {
                reporteProcesado = false;
                const dataFiltrada = filtrarDataGlobal();

                const hayInconsistencias = dataFiltrada.some(item =>
                    !item.RazonTemporal || item.RazonTemporal.trim() === ''
                );

                if (!hayInconsistencias) {
                    $('#btnProcesar').removeClass('d-none').show();
                    $('#btnExportar').removeClass('d-none').show();
                } else {
                    $('#btnProcesar').hide();
                    $('#btnExportar').hide();
                }
            }
        }).fail(xhr => {
            $('#btnProcesar').hide();
            mostrarErrorRespuesta(xhr.responseJSON, temporal, 'No fue posible verificar el reporte', xhr.status);
        });
    }

    $('#filtrar_reporte').on('click', function () {
        cargarReporteTotal();
        cargarReporteLabor();
        cargarReporteDetallado();
        verificarReporteProcesado();
    });


    function crearTabla(id, columnas) {
        if ($.fn.DataTable.isDataTable(id)) {
            $(id).DataTable().destroy();
        }

        $(id).DataTable({
            data: dataGlobal,
            columns: columnas,
            pageLength: 10
        });
    }

    function cargarEmpleadosSinTemporal() {
        $('#modalSinTemporal').modal('show');

        const empleadosSinTemporal = dataGlobal.filter(item =>
            item.TipoContrato === 'Temporal' &&
            (!item.RazonTemporal || item.RazonTemporal.trim() === '')
        );

        if ($.fn.DataTable.isDataTable('#tabla-sin-temporal')) {
            $('#tabla-sin-temporal').DataTable().destroy();
        }

        $('#tabla-sin-temporal').DataTable({
            data: empleadosSinTemporal,
            columns: [
                { data: 'cedula', title: 'Documento' },
                { data: 'nombreTercero', title: 'Empleado' }
            ]
        });
    }


    function bloquearUI(estado) {
        $('#filtro_desde, #filtro_hasta, [data-rango]').prop('disabled', estado);

        if (estado) {
            $('#consultar_liquidacion').prop('disabled', true);
            Swal.fire({ title: 'Consultando...', allowOutsideClick: false });
            Swal.showLoading();
        } else {
            validar();
            Swal.close();
        }
    }

    function formatearFechaYYYYMMDD(fecha) {
        if (!fecha) return '';

        const str = fecha.toString();
        const anio = str.substring(0, 4);
        const mes = str.substring(4, 6);
        const dia = str.substring(6, 8);

        return `${dia}/${mes}/${anio}`;
    }


    $('#btnProcesar').on('click', function () {

        const tabla = $('#tabla-total').DataTable();

        if (tabla.data().count() === 0) {
            Swal.fire('Atención', 'No hay datos para procesar', 'warning');
            return;
        }

        const actual = rango();
        const temporal = $('#temporal').val();
        const tipoContrato = $('#tipoContrato').val();

        if (!temporal || temporal === 'Seleccione la temporal') {
            Swal.fire('Atención', 'Debe seleccionar una temporal', 'warning');
            return;
        }

        if (!tipoContrato || tipoContrato === 'Seleccione el tipo de contrato') {
            Swal.fire('Atención', 'Debe seleccionar un tipo de contrato', 'warning');
            return;
        }

        // Confirmar antes de procesar
        Swal.fire({
            title: '¿Procesar reporte?',
            html: `
                <div class="gp-referencia text-start mb-3">
                    <div class="tx-ficha">
                        ${siesaDato('Rango', rangoFicha(actual.desde, actual.hasta))}
                        ${siesaDato('Días', siesaNum(actual.dias))}
                        ${siesaDato('Tipo de contrato', escaparHtml(tipoContrato))}
                        ${siesaDato('Temporal', escaparHtml(temporal))}
                    </div>
                </div>
                <div class="gp-nota text-start">El rango quedará registrado como procesado para esta temporal. No podrá procesarse otro rango que se cruce con él.</div>
            `,
            icon: 'question',
            width: 560,
            focusCancel: true,
            showCancelButton: true,
            confirmButtonText: 'Sí, procesar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                procesarYGuardar();
            }
        });
    });

    function obtenerDatosFiltradosDataTable() {
        const table = $('#tabla-total').DataTable();

        // Obtiene TODAS las filas filtradas (no solo la página actual)
        return table.rows({ search: 'applied' }).data().toArray();
    }

    function procesarYGuardar() {
        const { desde, hasta } = rango();
        const temporal = $('#temporal').val();
        const tipoContrato = $('#tipoContrato').val();

        Swal.fire({
            title: 'Procesando...',
            html: 'Guardando datos en la base de datos',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const datosFiltrados = obtenerDatosFiltradosDataTable();

        if (!datosFiltrados || datosFiltrados.length === 0) {
            Swal.fire('Atención', 'No hay datos para procesar', 'warning');
            return;
        }

        const dataAgrupada = agruparPorEmpleado(datosFiltrados);

        $.ajax({
            url: "<?= base_url('informes/procesar-reporte') ?>",
            type: "POST",
            dataType: "json",
            data: {
                desde,
                hasta,
                temporal,
                tipoContrato,
                datos: JSON.stringify(dataAgrupada)
            },
            success: function (response) {

                if (response.status === 'success') {

                    Swal.fire({
                        icon: 'success',
                        title: 'Procesado con éxito',
                        html: `
                        <p>${response.message}</p>
                        <p><strong>Registros guardados:</strong> ${response.registros_guardados}</p>
                    `
                    });

                    reporteProcesado = true;
                    $('#btnProcesar').hide();

                } else {
                    mostrarErrorRespuesta(response, temporal, 'Error al procesar');
                }
            },
            error: function (xhr) {
                mostrarErrorRespuesta(xhr.responseJSON, temporal, 'No se pudo guardar el reporte', xhr.status);
            }
        });
    }

    $('#btnExportar').on('click', function () {
        const tabla = $('#tabla-total').DataTable();

        if (tabla.data().count() === 0) {
            Swal.fire('Atención', 'No hay datos para exportar', 'warning');
            return;
        }

        exportarExcelDataTable('REPORTE');
    })


    function exportarExcelDataTable(nombreArchivo) {
        const table = $('#tabla-total').DataTable();
        const { desde, hasta } = rango();
        const temporal = $('#temporal').val();

        let csv = 'Fecha inicio,Fecha fin,Cedula,Empleado,Tipo Contrato,Temporal,Total Pagado\n';

        table.rows({ search: 'applied' }).data().each(row => {
            csv += `"${desde}","${hasta}","${row.cedula}","${row.nombreTercero}","${row.TipoContrato}","${row.RazonTemporal}","${row.valorTotalTercero}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');

        link.href = URL.createObjectURL(blob);
        link.download = `${nombreArchivo}-${temporal}-${desde}_${hasta}.csv`;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $.fn.dataTable
            .tables({ visible: true, api: true })
            .columns.adjust();
    });


    $('#formExcel').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        formData.append('desde', rangoModal.desde);
        formData.append('hasta', rangoModal.hasta);

        $.ajax({
            url: "<?= base_url('informes/actualizar-valores-temporal') ?>",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: res => {

                if (res.tipo === 'duplicados') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Duplicados detectados',
                        width: 600,
                        html: `
                            <p class="text-danger fw-bold">Se encontraron cédulas duplicadas en el archivo Excel.</p>
                            <p>Debe corregir el archivo antes de continuar.</p>
                            <div style="max-height:300px; overflow:auto">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">Cédula</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${(res.duplicados || []).map(cedula => `
                                            <tr>
                                                <td class="text-center font-monospace">${escaparHtml(cedula)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `
                    });
                    return;
                }

                if (res.status !== 'ok') {
                    Swal.fire('Error', res.message, 'error');
                    return;
                }

                let html = `
                        <p><b>Rango:</b> ${escaparHtml(res.periodo)}</p>
                        <p><b>Actualizados:</b> ${res.actualizados}</p>
                        <p><b>Inválidos:</b> ${res.no_existen?.length ?? 0}</p>
                    `;

                if (res.no_existen.length > 0) {
                    html += `
                        <hr>
                        <p class="text-danger fw-bold">Empleados que NO existen en el sistema</p>
                        <div style="max-height:200px; overflow:auto">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Cédula</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${res.no_existen.map(cedula => `
                                        <tr>
                                            <td class="text-center">${escaparHtml(cedula)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                // CERRAR EL MODAL después de éxito
                $('#modalActualizarTemporales').modal('hide');

                // RESETEAR EL FORMULARIO
                $('#formExcel')[0].reset();

                Swal.fire({
                    title: 'Proceso finalizado',
                    html: html,
                    icon: res.no_existen.length ? 'warning' : 'success',
                    width: 600
                });
            },
            error: function (xhr) {
                const res = xhr.responseJSON || {};

                if (res.tipo === 'cruces' && Array.isArray(res.cruces)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'El rango se cruza con liquidaciones existentes',
                        width: 600,
                        html: `
                            <p>${escaparHtml(res.message)}</p>
                            <div style="max-height:240px; overflow:auto">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">Cédula</th>
                                            <th class="text-center">Rango</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${res.cruces.map(c => `
                                            <tr>
                                                <td class="text-center font-monospace">${escaparHtml(c.cedula)}</td>
                                                <td class="text-center text-nowrap">${rangoTexto(c.desde, c.hasta)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `
                    });
                    return;
                }

                Swal.fire('Error', res.message || 'No se pudo procesar el archivo', 'error');
            }
        });
    });
</script>
<?php echo $this->endSection(); ?>