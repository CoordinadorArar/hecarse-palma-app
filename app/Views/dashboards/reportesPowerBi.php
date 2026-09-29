<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/reportesPowerBI.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">
    <ul class="nav nav-tabs" id="reportesManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="list-reporte-tab" data-bs-toggle="tab" href="#reportesList" role="tab"
                aria-controls="reportesList" aria-selected="true">
                Lista de Reportes
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="create-reporte-tab" data-bs-toggle="tab" href="#createReporte" role="tab"
                aria-controls="createReporte" aria-selected="false">
                Crear Reporte
            </a>
        </li>
    </ul>
    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="reportManagementTabsContent">
        <!-- Tab 1: Lista de Reportes -->
        <div class="tab-pane fade show active" id="reportesList" role="tabpanel" aria-labelledby="reportesList-tab">
            <div class="card mt-3">
                <div class="card-body">
                    <div class="table-responsive" id="contenedor_table">
                        <?= $tabla_reportes ?>
                    </div>
                    <div class="modal fade" id="ModalEditarReporte" tabindex="-1" aria-labelledby="exampleModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-body">
                                    <ul class="nav nav-tabs" id="reporteModalTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link active" id="formEditarReporte-tab" data-bs-toggle="tab"
                                                href="#formEditarReporte" role="tab" aria-controls="formEditarReporte"
                                                aria-selected="true">
                                                Editar reporte
                                            </a>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link" id="asginarUsuariosReporte-tab" data-bs-toggle="tab"
                                                href="#asginarUsuariosReporte" role="tab"
                                                aria-controls="asginarUsuariosReporte" aria-selected="false">
                                                Asignar usuarios
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="reporteModalTabsContent">
                                        <!--Tab edición reporte -->
                                        <div class="tab-pane fade show active" id="formEditarReporte" role="tabpanel"
                                            aria-labelledby="formEditarReporte-tab">
                                            <form class="row mt-3 g-3" id="formulario_editar_reporte">
                                                <!-- Nombre -->
                                                <div class="col-6">
                                                    <label for="nombre" class="form-label">Nombre:</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light"><i
                                                                class="bi bi-card-text"></i></span>
                                                        <input type="text" class="form-control shadow-sm" id="nombre"
                                                            placeholder="Digite el nombre del reporte"
                                                            onkeypress="return noStrangeCharacters(event)"
                                                            onpaste="return false">
                                                    </div>
                                                </div>
                                                <!-- Enlace del Reporte -->
                                                <div class="col-6">
                                                    <label for="enlace" class="form-label">Enlace del Reporte:</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light"><i
                                                                class="bi bi-link-45deg"></i></span>
                                                        <input type="text" class="form-control shadow-sm" id="enlace"
                                                            placeholder="Digite el enlace del reporte"
                                                            onkeypress="return noStrangeCharacters(event)">
                                                    </div>
                                                </div>
                                                <!-- Descripción -->
                                                <div class="col-6">
                                                    <label for="descripcion" class="form-label">Descripción:</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light"><i
                                                                class="bi bi-card-text"></i></span>
                                                        <textarea class="form-control shadow-sm" id="descripcion"
                                                            rows="3" resize="false"
                                                            onkeypress="return noStrangeCharacters(event)"></textarea>
                                                    </div>
                                                </div>
                                                <!-- <div class="col-12">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="" id="invalidCheck">
                                                        <label class="form-check-label" for="invalidCheck">
                                                            Activar/Inactivar
                                                        </label>
                                                    </div>
                                                </div> -->

                                                <input type="hidden" id="IdReporte" value="">
                                                <div class="col-12 text-center">
                                                    <button class="btn btn-primary" type="submit"
                                                        id="btn_actualizar_reporte">Guardar</button>
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cerrar</button>
                                                </div>
                                            </form>
                                        </div>

                                        <!-- Tab Asignar Usuarios -->
                                        <div class="tab-pane fade" id="asginarUsuariosReporte" role="tabpanel"
                                            aria-labelledby="asginarUsuariosReporte-tab">
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm" id="tabla_usuarios_asignados">
                                                    <thead>
                                                        <tr>
                                                            <th>Nombre usuario</th>
                                                            <th>Centro Operación</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($usuarios_activos as $usuario): ?>
                                                            <tr>
                                                                <td>
                                                                    <?= $usuario['Nombre'] ?>
                                                                    <?= $usuario['Apellido'] ?>
                                                                </td>
                                                                <td>
                                                                    <?= $usuario['CentroOperacion'] ?>
                                                                </td>
                                                                <td>
                                                                    <input type="checkbox"
                                                                        class="form-check-input check-usuario"
                                                                        value="<?= $usuario['Id'] ?>"
                                                                        data-id="<?= $usuario['Documento'] ?>"
                                                                        onchange="asignacionUsuarios(this)">
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                <div class="text-center">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cerrar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Tab 2: Crear Reporte -->
        <div class="tab-pane fade" id="createReporte" role="tabpanel" aria-labelledby="createReporte-tab">
            <div class="card mt-3">
                <div class="card-body p-4">
                    <form id="formulario_creacion">
                        <div class="row mb-4">
                            <!-- Nombre -->
                            <div class="col-6">
                                <label for="nombre_crear" class="form-label">Nombre:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="nombre_crear"
                                        placeholder="Digite el nombre del reporte"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                </div>
                            </div>
                            <!-- Enlace del Reporte -->
                            <div class="col-6">
                                <label for="enlace_crear" class="form-label">Enlace del Reporte:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="enlace_crear"
                                        placeholder="Digite la enlace del reporte"
                                        onkeypress="return noStrangeCharacters(event)" required>
                                </div>
                            </div>
                            <!-- Descripción -->
                            <div class="col-6">
                                <label for="descripcion_crear" class="form-label">Descripción:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <textarea class="form-control shadow-sm" id="descripcion_crear" rows="3"
                                        resize="false" onkeypress="return noStrangeCharacters(event)"
                                        required></textarea>
                                </div>
                            </div>
                            <!-- Usuarios asignados -->
                            <div class="col-6">
                                <label for="usuarios_crear" class="form-label">Usuarios Asignados:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person-fill"></i></span>
                                    <select class="form-select shadow-sm" id="usuarios_crear" multiple>
                                        <option value="" disabled>Seleccione los usuarios</option>
                                        <?php foreach ($usuarios_activos as $usuario): ?>
                                            <option value="<?php echo $usuario['Id']; ?>">
                                                <?php echo $usuario['Apellido'] . ', ' . $usuario['Nombre']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!-- Botón de Crear Reporte -->
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-primary w-50 shadow-sm"
                                    id="btn_crear_reporte">Crear Reporte</button>
                            </div>
                        </div>
                    </form>
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
    /**
     * Configuración de Toast de Sweetalert.
     */
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });

    $('#usuarios_crear').select2();
    $('#usuarios').select2();

    /**
     * 
     */
    document.querySelector('#formulario_creacion').addEventListener('submit', (e) => {
        e.preventDefault();
        createReporte();
    });

    /**
     * 
     */
    document.querySelector('#formulario_editar_reporte').addEventListener('submit', (e) => {
        e.preventDefault();
        updateReporte();
    });

    /**
     * 
     */
    document.addEventListener('DOMContentLoaded', function () {
        renderizarTabla();
    });

    const renderizarTabla = () => {
        if ($.fn.DataTable.isDataTable('#tabla_reportes')) {
            $('#tabla_reportes').DataTable().destroy();
        }

        $('#tabla_reportes').DataTable({
            responsive: true,
            language: {
                "lengthMenu": "Mostrar _MENU_ registros por página",
                "zeroRecords": "No se encontraron resultados",
                "info": "Mostrando página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay registros disponibles",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "search": "Buscar:",
                "loadingRecords": "Cargando...",
                "processing": "Procesando...",
                "emptyTable": "No hay datos disponibles en la tabla",
                "thousands": ",",
                "decimal": ".",
                "infoPostFix": "",
                "aria": {
                    "sortAscending": ": activar para ordenar la columna ascendente",
                    "sortDescending": ": activar para ordenar la columna descendente"
                }
            }
        });
    }

    const renderizarTablaUsuarios = () => {
        if ($.fn.DataTable.isDataTable('#tabla_usuarios_asignados')) {
            $('#tabla_usuarios_asignados').DataTable().destroy();
        }

        $('#tabla_usuarios_asignados').DataTable({
            responsive: true,
            language: {
                "lengthMenu": "Mostrar _MENU_ registros por página",
                "zeroRecords": "No se encontraron resultados",
                "info": "Mostrando página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay registros disponibles",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "search": "Buscar:",
                "loadingRecords": "Cargando...",
                "processing": "Procesando...",
                "emptyTable": "No hay datos disponibles en la tabla",
                "thousands": ",",
                "decimal": ".",
                "infoPostFix": "",
                "aria": {
                    "sortAscending": ": activar para ordenar la columna ascendente",
                    "sortDescending": ": activar para ordenar la columna descendente"
                }
            }
        });
    }

    const createReporte = async () => {
        $('#btn_crear_reporte').prop('disabled', true);

        const formData = new FormData();
        formData.append('Nombre', $('#nombre_crear').val());
        formData.append('Enlace', $('#enlace_crear').val());
        formData.append('Descripcion', $('#descripcion_crear').val());
        formData.append('UsuariosAsignados', JSON.stringify($('#usuarios_crear').val()));

        try {
            const response = await fetch(`<?= base_url() ?>comercial/reportesPowerBi/crearReporte`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            Toast.fire({
                icon: 'success',
                title: 'Reporte creado correctamente.'
            });

            $('#formulario_creacion').trigger('reset');
            $('#usuarios_crear').val(null).trigger('change');
            $('#contenedor_table').html(result);
            $('#btn_crear_reporte').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const abrirReporte = async (enlace) => {
        window.open(enlace, '_blank');
    }

    const editarReporte = async (id) => {
        $('#ModalEditarReporte').modal('show');
        document.querySelector('#formulario_editar_reporte').reset();
        renderizarTablaUsuarios();

        try {
            const response = await fetch(`<?= base_url() ?>comercial/reportesPowerBi/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                $('#IdReporte').val(result.reporte.Id);
                $('#nombre').val(result.reporte.Nombre);
                $('#enlace').val(result.reporte.Enlace);
                $('#descripcion').val(result.reporte.Descripcion);
                //$('#invalidCheck').prop('checked', result.reporte.FechaFinalizacion === null);

                let usuarios = JSON.parse(result.reporte.Usuarios);
                let checks = document.getElementsByClassName('check-usuario');

                if (Array.isArray(usuarios)) {
                    usuarios.forEach(usuario => {
                        Array.from(checks).forEach(check => {
                            if (check.getAttribute('data-id') == usuario.id) {
                                check.checked = true;
                            }
                        });
                    });
                } else {
                    console.error('El JSON no contiene un array válido');
                }
            }
        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const updateReporte = async () => {
        $('#btn_actualizar_reporte').prop('disabled', true);

        const formData = new FormData();
        formData.append('IdReporte', $('#IdReporte').val());
        formData.append('Nombre', $('#nombre').val());
        formData.append('Enlace', $('#enlace').val());
        formData.append('Descripcion', $('#descripcion').val());
        //formData.append('FechaFinalizacion', $('#invalidCheck').prop('checked')? null : $('#fechaFinalizacion').val());

        try {
            const response = await fetch(`<?= base_url() ?>comercial/reportesPowerBi/actualizarReporte`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                Toast.fire({
                    icon: 'success',
                    title: 'Reporte editado correctamente.'
                });
                $('#formulario_editar_reporte').trigger('reset');
                $('#ModalEditarReporte').modal('hide');
                $('#contenedor_table').html(result.table);
                $('#btn_actualizar_reporte').prop('disabled', false);
                renderizarTabla();
            } else {
                Toast.fire({
                    icon: 'error',
                    title: result.text
                });
            }

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const asignacionUsuarios = async (elemento) => {
        let formData = new FormData();

        if (elemento.checked) {
            formData.append('accion', 'asignar');
        } else {
            formData.append('accion', 'remover');
        }

        formData.append('Id', elemento.value);
        formData.append('IdReporte', $('#IdReporte').val());

        try {
            const response = await fetch(`<?= base_url() ?>comercial/reportesPowerBi/asignarUsuarios`, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                Toast.fire({
                    icon: 'success',
                    title: result.text
                });
            } else {
                Toast.fire({
                    icon: 'error',
                    title: result.text
                });
            }
        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const eliminarReporte = async (id) => {
        Swal.fire({
            title: '¿Estás seguro de eliminar este reporte?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Si, eliminar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch(`<?= base_url() ?>comercial/reportesPowerBi/eliminarReporte/${id}`, {
                        method: 'POST'
                    });
                    const result = await response.json();

                    if (result.success) {
                        Toast.fire({
                            icon: 'success',
                            title: 'Reporte eliminado correctamente.'
                        });
                        $('#contenedor_table').html(result.table);
                        renderizarTabla();
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: result.text
                        });
                    }

                } catch (error) {
                    console.log('Se ha producido un error: ', error);
                }
            }
        });
    }

    const tabsPermitidos = <?= json_encode($tabs_permitidos) ?>

        console.log(tabsPermitidos);

    document.addEventListener('DOMContentLoaded', () => {
        const nombresTabsPermitidos = tabsPermitidos.map(tab => tab.Nombre);
        const todosLosTabs = document.querySelectorAll('[id$="-tab"]');
        todosLosTabs.forEach(tab => {
            if (!nombresTabsPermitidos.includes(tab.id)) {
                tab.style.display = 'none';
            }
        });
    });

</script>
<?php echo $this->endSection(); ?>