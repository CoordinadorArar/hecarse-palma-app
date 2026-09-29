<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/informes.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="tab-content" id="moduleManagementTabs">
        <div class="tab-pane fade show active" id="moduleList" role="tabpanel" aria-labelledby="moduleList-tab">
            <div class="card mb-4">
                <div class="card-body">
                    &nbsp;
                    <div class="d-flex justify-content-between align-items-start">
                        <h4> Liquidación de Nómina </h4>

                        <a href="<?= base_url() ?>public/documents/app/ManualLiquidacion.pdf?v-1.2" target="_blank"
                            class="btn btn-link" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Revisar Manual">
                            <i class="bi bi-question-circle-fill fs-4"></i>
                        </a>
                    </div>

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

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Año</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                <select class="form-select" id="anio">
                                    <option disabled selected>Seleccione el año</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Mes</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                <select class="form-select" id="mes">
                                    <option disabled selected>Seleccione el mes</option>
                                    <option value="01"> Enero </option>
                                    <option value="02"> Febrero </option>
                                    <option value="03"> Marzo </option>
                                    <option value="04"> Abril </option>
                                    <option value="05"> Mayo </option>
                                    <option value="06"> Junio </option>
                                    <option value="07"> Julio </option>
                                    <option value="08"> Agosto </option>
                                    <option value="09"> Septiembre </option>
                                    <option value="10"> Octubre </option>
                                    <option value="11"> Noviembre </option>
                                    <option value="12"> Diciembre </option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-outline-primary" id="consultar_liquidacion">
                                <i class="bi bi-search me-1"></i> Consultar
                            </button>
                        </div>

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
                    </div>

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
                                    <div class="alert alert-info d-flex align-items-start" role="alert">
                                        <i class="bi bi-info-circle fs-5 me-2"></i>
                                        <div>
                                            <strong>Instrucciones:</strong>
                                            <ol class="mb-0 mt-2">
                                                <li>Descarga la plantilla de Excel</li>
                                                <li>Completa los datos según el formato requerido</li>
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


    <div class="card">
        <div class="card-body p-0">

            <ul class="nav nav-tabs nav-tabs-bordered px-3 pt-3" role="tablist">
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

            <div class="tab-content p-4">

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

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>
<script>

    let InconsistenciasTemporal = false;
    let reporteProcesado = false; // Nueva variable

    document.addEventListener('DOMContentLoaded', () => {
        cargarAnios();

        $('#anio, #mes').on('change', cargarTemporales);
        $('#consultar_liquidacion').on('click', consultarReporte);
        $('#actualizar_contratos').on('click', actualizarContratos);
        $('#limpiar_filtros').on('click', limpiarFiltros);

        $('#btnAbrirModalTemporales').on('click', function () {
            $('#modalActualizarTemporales').modal('show');
        });
    });

    function limpiarFiltros() {
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


    function cargarAnios() {
        const anioActual = new Date().getFullYear();
        let html = '<option disabled selected>Seleccione el año</option>';

        for (let i = 0; i <= 2; i++) {
            html += `<option value="${anioActual - i}">${anioActual - i}</option>`;
        }

        $('#anio').html(html);
    }

    function cargarTemporales() {
        const anio = $('#anio').val();
        const mes = $('#mes').val();
        const select = $('#temporal');

        if (!anio || !mes) return;

        InconsistenciasTemporal = false;
        reporteProcesado = false;
        $('#btnProcesar').hide();

        // Resetear filtros
        $('#tipoContrato').val($('#tipoContrato option:first').val());
        $('#temporal').html('<option disabled selected>Seleccione la temporal</option>');

        fetch("<?= base_url('informes/temporales') ?>", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `anio=${anio}&mes=${mes}`
        })
            .then(res => res.json())
            .then(data => {

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
            <option value="${razon}">
                ${razon}
            </option>
        `);
                });

                select.prop('disabled', false);

                if (InconsistenciasTemporal) {
                    mostrarAlertaInconsistencias();
                }
            })
            .catch(() => select.prop('disabled', false));
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
        const anio = $('#anio').val();
        const mes = $('#mes').val();

        if (!anio || !mes) {
            Swal.fire('Atención', 'Seleccione el año y mes a consultar', 'warning');
            return;
        }

        bloquearUI(true);
        reporteProcesado = false;
        $('#btnProcesar').hide();

        $.post("<?= base_url('informes/consultar-reporte') ?>", {
            anio, mes
        }, response => {

            dataGlobal = response;

            cargarReporteTotal();
            cargarReporteLabor();
            cargarReporteDetallado();

            bloquearUI(false);
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
            responsive: true,
            pageLength: 10,
            language: idiomaDataTable
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
            responsive: true,
            pageLength: 10,
            language: idiomaDataTable
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
            info: false,

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
            language: idiomaDataTable,

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
        const anio = $('#anio').val();
        const mes = $('#mes').val();
        const temporal = $('#temporal').val();
        const tipoContrato = $('#tipoContrato').val();

        if (!anio || !mes || !temporal || !tipoContrato ||
            temporal === 'Seleccione la temporal' ||
            tipoContrato === 'Seleccione el tipo de contrato') {
            $('#btnProcesar').hide();
            $('#btnExportar').hide();
            return;
        }

        $.post("<?= base_url('informes/verificar-procesado') ?>", {
            anio, mes, temporal, tipoContrato
        }, response => {
            if (response.procesado) {
                reporteProcesado = true;
                $('#btnProcesar').hide();
                $('#btnExportar').removeClass('d-none').show();
                Swal.fire({
                    icon: 'info',
                    title: 'Reporte ya procesado',
                    text: 'Este reporte ya fue procesado anteriormente',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
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
        });
    }

    $('#filtrar_reporte').on('click', function () {
        cargarReporteTotal();
        cargarReporteLabor();
        cargarReporteDetallado();
        verificarReporteProcesado();
    });


    const idiomaDataTable = {
        lengthMenu: "Mostrar _MENU_ registros",
        zeroRecords: "No se encontraron registros",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        infoEmpty: "No hay registros disponibles",
        infoFiltered: "(filtrado de _MAX_ registros totales)",
        search: "Buscar:",
        loadingRecords: "Cargando...",
        processing: "Procesando...",
        emptyTable: "No hay datos disponibles",
        paginate: {
            first: "Primero",
            last: "Último",
            next: "›",
            previous: "‹"
        }
    };

    function crearTabla(id, columnas) {
        if ($.fn.DataTable.isDataTable(id)) {
            $(id).DataTable().destroy();
        }

        $(id).DataTable({
            data: dataGlobal,
            columns: columnas,
            responsive: true,
            pageLength: 10,
            language: idiomaDataTable
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
            ],
            responsive: true,
            language: idiomaDataTable
        });
    }


    function bloquearUI(estado) {
        $('#consultar_liquidacion').prop('disabled', estado);
        $('#anio, #mes').prop('disabled', estado);

        if (estado) {
            Swal.fire({ title: 'Consultando...', allowOutsideClick: false });
            Swal.showLoading();
        } else {
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

        const anio = $('#anio').val();
        const mes = $('#mes').val();
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
                <p>Se procesará el reporte con los siguientes datos:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li><strong>Año:</strong> ${anio}</li>
                    <li><strong>Mes:</strong> ${mes}</li>
                    <li><strong>Tipo Contrato:</strong> ${tipoContrato}</li>
                    <li><strong>Temporal:</strong> ${temporal}</li>
                </ul>
            `,
            icon: 'question',
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
        const anio = $('#anio').val();
        const mes = $('#mes').val();
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
                anio,
                mes,
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
                    Swal.fire('Error', response.message || 'Error al procesar', 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'No se pudo guardar el reporte', 'error');
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
        const anio = $('#anio').val();
        const mes = $('#mes').val();
        const temporal = $('#temporal').val();

        let csv = 'Año,Mes,Cedula,Empleado,Tipo Contrato,Temporal,Total Pagado\n';

        table.rows({ search: 'applied' }).data().each(row => {
            csv += `"${anio}","${mes}","${row.cedula}","${row.nombreTercero}","${row.TipoContrato}","${row.RazonTemporal}","${row.valorTotalTercero}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');

        link.href = URL.createObjectURL(blob);
        link.download = `${nombreArchivo}-${temporal}-${anio}-${mes}.csv`;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $.fn.dataTable
            .tables({ visible: true, api: true })
            .columns.adjust()
            .responsive.recalc();
    });


    $('#formExcel').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        $.ajax({
            url: "<?= base_url('informes/actualizar-valores-temporal') ?>",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: res => {

                if (res.status !== 'ok') {
                    Swal.fire('Error', res.message, 'error');
                    return;
                }

                let html = `
                        <p><b>Periodo:</b> ${res.periodo}</p>
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
                                            <td class="text-center">${cedula}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                if (res.tipo === 'duplicados') {

                    let html = `
                        <p class="text-danger fw-bold">
                            Se encontraron cédulas duplicadas en el archivo Excel.
                        </p>
                        <p>Debe corregir el archivo antes de continuar.</p>

                        <div style="max-height:300px; overflow:auto">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">Cédula</th>
                                        <th>Empleado(s)</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    res.duplicados.forEach(item => {
                        html += `
                            <tr>
                                <td class="text-center fw-bold">${item.cedula}</td>
                                <td>${item.nombres.join('<br>')}</td>
                            </tr>
                        `;
                    });

                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;

                    Swal.fire({
                        icon: 'warning',
                        title: 'Duplicados detectados',
                        html: html,
                        width: '700px'
                    });

                    return;
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
            error: function () {
                Swal.fire('Error', 'No se pudo procesar el archivo', 'error');
            }
        });
    });
</script>
<?php echo $this->endSection(); ?>