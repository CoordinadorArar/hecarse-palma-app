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
                    <br>
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="fw-semibold mb-3">Período:</h6>

                        <a href="<?= base_url() ?>public/documents/app/ManualContabilizar.pdf?v-1.2" target="_blank"
                            class="btn btn-link" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Revisar Manual">
                            <i class="bi bi-question-circle-fill fs-4"></i>
                        </a>
                    </div>

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
                            <button class="btn btn-outline-primary" id="consultar_consolidado">
                                <i class="bi bi-search me-1"></i> Consultar
                            </button>
                        </div>

                        <hr>

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

                            <button class="btn btn-outline-secondary" id="limpiar_filtro">
                                <i class="bi bi-x-circle me-1"></i> Limpiar Filtro
                            </button>
                        </div>

                        <!-- <h6> Valor Total: <span id="totalValor" class="fw-bold text-success">$0</span> </h6> -->

                        <hr>

                        <div class="mb-3">
                            <button class="btn btn-primary sm" onclick="generarTXT()">
                                <i class="bi bi-download"></i> Descargar TXT
                            </button>
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
                    <a class="nav-link active" data-bs-toggle="tab" href="#reporteLabor">
                        Por Grupo Labor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#reporteCentroCostos">
                        Por Centro Costos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#reporteDetallado">
                        Detallado
                    </a>
                </li>
            </ul>

            <div class="tab-content p-4">

                <div class="tab-pane fade show active" id="reporteLabor">
                    <table id="tabla-labor" class="table table-striped w-100"></table>
                </div>

                <div class="tab-pane fade" id="reporteCentroCostos">
                    <table id="tabla-centrocosto" class="table table-striped w-100"></table>
                </div>

                <div class="tab-pane fade" id="reporteDetallado">
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
    let datosGlobales = [];
    let datosDetallados = [];

    document.addEventListener('DOMContentLoaded', () => {
        cargarAnios();
        $('#consultar_consolidado').on('click', consultarReporte);
        $('#anio, #mes').on('change', cargarTemporales);
        $('#filtrar_reporte').on('click', filtrarPorTemporal);
        $('#limpiar_filtro').on('click', limpiarFiltro);
    });

    function cargarAnios() {
        const anioActual = new Date().getFullYear();
        let html = '<option disabled selected>Seleccione el año</option>';

        for (let i = 0; i <= 2; i++) {
            html += `<option value="${anioActual - i}">${anioActual - i}</option>`;
        }

        $('#anio').html(html);
    }

    function bloquearUI(estado) {
        $('#consultar_consolidado').prop('disabled', estado);
        $('#anio, #mes').prop('disabled', estado);

        if (estado) {
            Swal.fire({
                title: 'Consultando...',
                allowOutsideClick: false
            });
            Swal.showLoading();
        } else {
            Swal.close();
        }
    }

    // Limpiar Filtro de tablas
    function limpiarFiltro() {
        if (datosGlobales.length === 0) {
            return;
        }

        // Resetear select de temporal
        $('#temporal').val('').prop('selectedIndex', 0);

        // Reagrupar datos originales
        const datosAgrupadosLabor = agruparPorGrupoLabor(datosGlobales);
        const datosAgrupadosCcosto = agruparPorCentroCosto(datosGlobales);

        // Calcular total general
        const totalGeneral = datosAgrupadosLabor.reduce((sum, item) =>
            sum + parseFloat(item.ValorHecarse || 0), 0
        );

        // Actualizar total
        $('#totalValor').text(
            totalGeneral.toLocaleString('es-CO', {
                style: 'currency',
                currency: 'COP',
                minimumFractionDigits: 0
            })
        );

        // Recargar tablas con todos los datos
        cargarReporteLabor(datosAgrupadosLabor);
        cargarReporteCentroCosto(datosAgrupadosCcosto);
        cargarReporteDetallado(datosDetallados);
    }

    function cargarTemporales() {
        const anio = $('#anio').val();
        const mes = $('#mes').val();
        const select = $('#temporal');

        if (!anio || !mes) return;

        // Resetear filtros
        $('#temporal').html('<option disabled selected>Seleccione la temporal</option>');

        fetch("<?= base_url('informes/temporales') ?>", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `anio=${anio}&mes=${mes}`
            })
            .then(res => res.json())
            .then(data => {

                select.html('<option disabled selected>Seleccione la temporal</option>');

                const temporalesSet = new Set();

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
            })
            .catch(() => select.prop('disabled', false));
    }

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

    // function consultarReporte() {
    //     const anio = $('#anio').val();
    //     const mes = $('#mes').val();

    //     if (!anio || !mes) {
    //         Swal.fire('Atención', 'Seleccione el año y mes a consultar', 'warning');
    //         return;
    //     }

    //     bloquearUI(true);

    //     $.post("<?= base_url('informes/pagos-labor') ?>", { anio, mes }, response => {

    //         // Guardar datos globales
    //         datosGlobales = response.resumen;
    //         datosDetallados = response.detallado;

    //         // Agrupar datos por Grupo Labor
    //         const datosAgrupadosLabor = agruparPorGrupoLabor(response);

    //         // Agrupar datos por Centro de Costos
    //         const datosAgrupadosCcosto = agruparPorCentroCosto(response);

    //         // Calcular total general
    //         const totalGeneral = datosAgrupadosLabor.reduce((sum, item) => sum + item.ValorHecarse, 0);

    //         // Mostrar total arriba
    //         $('#totalValor').text(
    //             totalGeneral.toLocaleString('es-CO', {
    //                 style: 'currency',
    //                 currency: 'COP',
    //                 minimumFractionDigits: 0
    //             })
    //         );

    //         // Cargar ambas tablas
    //         cargarReporteLabor(datosAgrupadosLabor);
    //         cargarReporteCentroCosto(datosAgrupadosCcosto);
    //         cargarReporteDetallado(datosDetallados);

    //         bloquearUI(false);
    //     }).fail(() => {
    //         bloquearUI(false);
    //         Swal.fire('Error', 'No se pudo consultar el reporte', 'error');
    //     });
    // }

    function consultarReporte() {
        const anio = $('#anio').val();
        const mes = $('#mes').val();

        if (!anio || !mes) {
            Swal.fire('Atención', 'Seleccione el año y mes a consultar', 'warning');
            return;
        }

        bloquearUI(true);

        $.post("<?= base_url('informes/pagos-labor') ?>", {
            anio,
            mes
        }, response => {

            console.log('Response:', response); // DEBUG: Ver qué llega

            // Guardar AMBOS conjuntos de datos
            datosGlobales = response.resumen; // Datos agrupados
            datosDetallados = response.detallado; // Datos con RazonTemporal

            // Validar que los datos llegaron correctamente
            if (!Array.isArray(datosGlobales) || !Array.isArray(datosDetallados)) {
                bloquearUI(false);
                Swal.fire('Error', 'Formato de datos incorrecto', 'error');
                console.error('datosGlobales:', datosGlobales);
                console.error('datosDetallados:', datosDetallados);
                return;
            }

            // Agrupar datos por Grupo Labor (ya vienen del resumen, pero por si acaso)
            const datosAgrupadosLabor = agruparPorGrupoLabor(datosGlobales);

            // Agrupar datos por Centro de Costos
            const datosAgrupadosCcosto = agruparPorCentroCosto(datosGlobales);

            // Calcular total general desde los datos agrupados
            const totalGeneral = datosAgrupadosLabor.reduce((sum, item) =>
                sum + parseFloat(item.ValorHecarse || 0), 0
            );

            // Mostrar total arriba
            $('#totalValor').text(
                totalGeneral.toLocaleString('es-CO', {
                    style: 'currency',
                    currency: 'COP',
                    minimumFractionDigits: 0
                })
            );

            // Cargar todas las tablas
            cargarReporteLabor(datosAgrupadosLabor);
            cargarReporteCentroCosto(datosAgrupadosCcosto);
            cargarReporteDetallado(datosDetallados); // Usar detallados

            bloquearUI(false);

        }).fail((jqXHR, textStatus, errorThrown) => {
            bloquearUI(false);
            console.error('Error en la petición:', textStatus, errorThrown);
            console.error('Response:', jqXHR.responseText);
            Swal.fire('Error', 'No se pudo consultar el reporte', 'error');
        });
    }

    /**
     * Agrupa los datos por nombreGrupoLabor sumando los valores
     */
    function agruparPorGrupoLabor(data) {
        const agrupado = {};

        data.forEach(row => {
            const labor = row.nombreGrupoLabor;

            if (!agrupado[labor]) {
                agrupado[labor] = {
                    nombreGrupoLabor: labor,
                    ValorHecarse: 0,
                    ValorTemporal: 0,
                    Participacion: 0
                };
            }

            agrupado[labor].ValorHecarse += parseFloat(row.ValorHecarse || 0);
            agrupado[labor].ValorTemporal += parseFloat(row.ValorTemporal || 0);
            agrupado[labor].Participacion += parseFloat(row.Participacion || 0);
        });

        // Convertir objeto a array y ordenar por valor descendente
        return Object.values(agrupado).sort((a, b) => b.ValorHecarse - a.ValorHecarse);
    }

    /**
     * Agrupa los datos por Centro de Costo sumando los valores
     */
    function agruparPorCentroCosto(data) {
        const agrupado = {};

        data.forEach(row => {
            const ccosto = row.ccosto || 'SIN CENTRO DE COSTO';

            if (!agrupado[ccosto]) {
                agrupado[ccosto] = {
                    ccosto: ccosto,
                    ValorHecarse: 0,
                    ValorTemporal: 0,
                    Participacion: 0
                };
            }

            agrupado[ccosto].ValorHecarse += parseFloat(row.ValorHecarse || 0);
            agrupado[ccosto].ValorTemporal += parseFloat(row.ValorTemporal || 0);
            agrupado[ccosto].Participacion += parseFloat(row.Participacion || 0);
        });

        // Convertir objeto a array y ordenar por valor descendente
        return Object.values(agrupado).sort((a, b) => b.ValorHecarse - a.ValorHecarse);
    }

    function cargarReporteLabor(data) {

        if ($.fn.DataTable.isDataTable('#tabla-labor')) {
            $('#tabla-labor').DataTable().destroy();
        }

        console.log(data);

        $('#tabla-labor').DataTable({
            data: data,
            columns: [{
                    title: 'Grupo Labor',
                    data: 'nombreGrupoLabor',
                    className: 'fw-semibold'
                },
                {
                    title: 'Valor Hecarse',
                    data: 'ValorHecarse',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$'),
                    className: 'text-end'
                },
                {
                    title: '% Participación',
                    data: 'Participacion',
                    render: data => {
                        const porcentaje = (data * 100).toFixed(2);
                        return `<span class="badge bg-primary">${porcentaje}%</span>`;
                    },
                    className: 'text-center'
                },
                {
                    title: 'Valor Temporal',
                    data: 'ValorTemporal',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$'),
                    className: 'text-end'
                }
            ],
            responsive: true,
            pageLength: 10,
            language: idiomaDataTable,
            order: [
                [1, 'desc']
            ] // Ordenar por Valor Hecarse descendente
        });
    }

    function cargarReporteCentroCosto(data) {

        if ($.fn.DataTable.isDataTable('#tabla-centrocosto')) {
            $('#tabla-centrocosto').DataTable().destroy();
        }

        $('#tabla-centrocosto').DataTable({
            data: data,
            columns: [{
                    title: 'Centro de Costo',
                    data: 'ccosto',
                    className: 'fw-semibold',
                    render: function(data) {
                        return data === 'NULL' || data === null || data === '' ?
                            '<span class="text-muted fst-italic">SIN CENTRO DE COSTO</span>' :
                            data;
                    }
                },
                {
                    title: 'Valor Hecarse',
                    data: 'ValorHecarse',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$'),
                    className: 'text-end'
                },
                {
                    title: '% Participación',
                    data: 'Participacion',
                    render: data => {
                        const porcentaje = (data * 100).toFixed(2);
                        let badgeClass = 'bg-primary';

                        // Colorear según el porcentaje
                        if (porcentaje >= 20) badgeClass = 'bg-success';
                        else if (porcentaje >= 10) badgeClass = 'bg-info';
                        else if (porcentaje >= 5) badgeClass = 'bg-warning';
                        else badgeClass = 'bg-secondary';

                        return `<span class="badge ${badgeClass}">${porcentaje}%</span>`;
                    },
                    className: 'text-center'
                },
                {
                    title: 'Valor Temporal',
                    data: 'ValorTemporal',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$'),
                    className: 'text-end'
                }
            ],
            responsive: true,
            pageLength: 10,
            language: idiomaDataTable,
            order: [
                [1, 'desc']
            ] // Ordenar por Valor Hecarse descendente
        });
    }


    function cargarReporteDetallado(data) {

        if ($.fn.DataTable.isDataTable('#tabla-detallado')) {
            $('#tabla-detallado').DataTable().destroy();
        }

        $('#tabla-detallado').DataTable({
            data: data,
            columns: [{
                    title: 'Grupo Labor',
                    data: 'nombreGrupoLabor'
                },
                {
                    title: 'Centro Costo',
                    data: 'ccosto'
                },
                {
                    title: 'Temporal',
                    data: 'RazonTemporal'
                },
                {
                    title: 'Valor Hecarse',
                    data: 'ValorHecarse',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$')
                },
                {
                    title: '% Participación',
                    data: 'Participacion',
                    render: data => `${(data * 100).toFixed(2)} %`
                },
                {
                    title: 'Valor Temporal',
                    data: 'ValorTemporal',
                    render: $.fn.dataTable.render.number('.', ',', 0, '$')
                }
            ],
            responsive: true,
            pageLength: 10,
            language: idiomaDataTable
        });
    }

    // Ajustar tablas al cambiar de pestaña
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
        $.fn.dataTable
            .tables({
                visible: true,
                api: true
            })
            .columns.adjust();
    });

    function generarTXT() {
        const anio = $('#anio').val();
        const mes = $('#mes').val();
        const temporal = $('#temporal').val();

        if (!anio || !mes || !temporal) {
            Swal.fire('Atención', 'Seleccione año, mes y temporal', 'warning');
            return;
        }

        Swal.fire({
            title: 'Generando archivo...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(`${BASE_URL}informes/generar-txt?anio=${anio}&mes=${mes}&temporal=${temporal}`)
            .then(response => {
                if (!response.ok) throw new Error('Error al generar el archivo');
                const disposition = response.headers.get('Content-Disposition');
                const filename = disposition?.match(/filename="?([^"]+)"?/)?.[1] ?? 'archivo.txt';
                return response.blob().then(blob => ({
                    blob,
                    filename
                }));
            })
            .then(({
                blob,
                filename
            }) => {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                Swal.close();
            })
            .catch(err => {
                Swal.fire('Error', err.message, 'error');
            });
    }

    function filtrarPorTemporal() {
        const temporal = $('#temporal').val();

        if (!temporal) {
            Swal.fire('Atención', 'Seleccione una temporal para filtrar', 'warning');
            return;
        }

        if (datosDetallados.length === 0) {
            Swal.fire('Atención', 'Debe consultar primero el reporte', 'warning');
            return;
        }

        // Filtrar los datos globales por la temporal seleccionada
        const datosFiltrados = datosDetallados.filter(item => item.RazonTemporal === temporal);

        console.log(datosDetallados);

        if (datosFiltrados.length === 0) {
            Swal.fire('Información', 'No hay datos para la temporal seleccionada', 'info');
            return;
        }

        // Agrupar datos filtrados
        const datosAgrupadosLabor = agruparPorGrupoLabor(datosFiltrados);
        const datosAgrupadosCcosto = agruparPorCentroCosto(datosFiltrados);

        // Calcular total del filtro
        const totalFiltrado = datosAgrupadosLabor.reduce((sum, item) => sum + item.ValorHecarse, 0);

        // Actualizar total
        $('#totalValor').text(
            totalFiltrado.toLocaleString('es-CO', {
                style: 'currency',
                currency: 'COP',
                minimumFractionDigits: 0
            })
        );

        // Recargar tablas con datos filtrados
        cargarReporteLabor(datosAgrupadosLabor);
        cargarReporteCentroCosto(datosAgrupadosCcosto);
        cargarReporteDetallado(datosFiltrados);
    }
</script>
<?php echo $this->endSection(); ?>