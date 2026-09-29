<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/roles.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">
    <!-- Sección de Tabs -->
    <ul class="nav nav-tabs" id="rolManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="rolList-tab" data-bs-toggle="tab" href="#rolList" role="tab" aria-controls="rolList" aria-selected="true">
                Lista de Roles
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="createRol-tab" data-bs-toggle="tab" href="#createRol" role="tab" aria-controls="createRol" aria-selected="false">
                Crear Rol
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="assignModules-tab" data-bs-toggle="tab" href="#assignModules" role="tab" aria-controls="assignModules" aria-selected="false">
                Asignar Módulos
            </a>
        </li>
    </ul>
    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="rolManagementTabsContent">
        <!-- Tab 1: Lista de Roles -->
        <div class="tab-pane fade show active" id="rolList" role="tabpanel" aria-labelledby="rolList-tab">
            <div class="card">
                <div class="card-body">
                    <!-- Agrega un contenedor table-responsive para evitar el desbordamiento -->
                    <div class="table-responsive" id="contenedor_table">
                        <?php echo $tabla_roles; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Tab 2: Crear Rol -->
        <div class="tab-pane fade" id="createRol" role="tabpanel" aria-labelledby="createRol-tab">
            <div class="card">
                <div class="card-body p-4">
                    <form id="formulario_creacion">
                        <div class="row mb-4">
                            <!-- Nombre -->
                            <div class="col-6">
                                <label for="nombre_crear" class="form-label">Nombre:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="nombre_crear" placeholder="Digite el nombre del rol" onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Crear Rol -->
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-primary w-50 shadow-sm" id="btn_crear_rol">Crear Rol</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Tab 3: Asignar módulos y/o permisos -->
         <div class="tab-pane fade" id="assignModules" role="tabpanel" aria-labelledby="assignModules-tab">
            <div class="card">
                <div class="card-body mt-3">
                    <!-- Sección de Consulta de Rol -->
                    <div class="row mb-4">
                        <div class="col-md-6 d-flex flex-column">
                            <label for="searchUser" class="form-label">Consultar Rol por Nombre</label>
                            <select id="select_rol" class="form-control">

                            </select>
                        </div>
                    </div>

                    <!-- Sección de Modulos por Rol -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6><b>Módulos del Rol</b></h6>
                            <ul class="list-group" id="rolModulesList">
                                Seleccione el rol para ver los módulos asignados.
                            </ul>
                        </div>

                        <div class="col-md-6">
                            <h6><b>Módulos no asignados</b></h6>
                            <ul class="list-group" id="availableRolModulesList">
                                Seleccione el rol para ver los módulos que no tiene asignados.
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- MODAL DE EDICION DE ROL -->
    <div class="modal fade" id="editRolModal" tabindex="-1" aria-labelledby="editRolModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRolModalLabel">Modificar Rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Custom Styled Validation -->
                    <form class="row g-3" id="formulario_editar_rol">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                <input type="text" class="form-control" id="nombre" value="" onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                            </div>
                        </div>
                        <div class="col-1"></div>
                        <div class="col-4">
                            <label class="form-label" for="invalidCheck">Activar/Inactivar</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="invalidCheck">
                            </div>
                        </div>

                        <input type="hidden" id="IdRol" value="">

                        <div class="col-12 text-center">
                            <button class="btn btn-primary" type="submit" id="btn_actualizar_rol">Guardar</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </form><!-- End Custom Styled Validation -->
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
     * Propiedad para almacenar el módulo del rol seleccionado
     * para gestionar sus módulos.
     */
    let rolId;

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

    /**
     * Metodo para guardar la informacion actualizada del rol.
     */
    const updateRol = async () => {
        $('#btn_actualizar_rol').prop('disabled', true);

        const formData = new FormData();
        formData.append('Id', $('#IdRol').val());
        formData.append('Nombre', $('#nombre').val());
        formData.append('FechaFinalizacion', $('#invalidCheck').prop('checked') ? 1 : 0);

        try {
            const response = await fetch(`<?= base_url() ?>admin/roles/actualizarRol`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            $('#editRolModal').modal('hide');

            Toast.fire({
                icon: 'success',
                title: 'Rol actualizado correctamente.'
            });

            $('#contenedor_table').html(result);
            $('#btn_actualizar_rol').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * Metodo para obtener la informacion de un rol en especifico
     * cuando se abre el modal de edicion.
     */
    const obtenerRol = async (id) => {
        document.querySelector('#formulario_editar_rol').reset();

        try {
            const response = await fetch(`<?= base_url() ?>admin/roles/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                $('#IdRol').val(result.rol.Id);
                $('#nombre').val(result.rol.Nombre);
                $('#invalidCheck').prop('checked', result.rol.FechaFinalizacion === null);
            }

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const renderizarTabla = () => {
        if ($.fn.DataTable.isDataTable('#tabla_roles')) {
            $('#tabla_roles').DataTable().destroy();
        }

        $('#tabla_roles').DataTable({
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

    /**
     * Metodo para crear un nuevo rol.
     */
    const crearRol = async () => {
        $('#btn_crear_rol').prop('disabled', true);

        const formData = new FormData();
        formData.append('Nombre', $('#nombre_crear').val());

        try {
            const response = await fetch(`<?= base_url() ?>admin/roles/crearRol`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            Toast.fire({
                icon: 'success',
                title: 'Rol creado correctamente.'
            });

            $('#formulario_creacion').trigger('reset');
            $('#contenedor_table').html(result);
            $('#btn_crear_rol').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * Metodo para renderizar el listado de módulos que un rol tiene asignados.
     * Tambien se renderizan los módulos que no tiene asignados.
     * 
     * @param {object} modulos Listado de módulos asignados y no asignados.
     */
    const renderizarModulosRol = (modulos) => {
        document.querySelector('#rolModulesList').innerHTML =
            modulos.asignados == '' ? 'No tiene ningún módulo asignado.' : modulos.asignados;

        document.querySelector('#availableRolModulesList').innerHTML =
            modulos.todos == '' ? 'Tiene todos los módulos asignados.' : modulos.todos;
    }

    /**
     * Metodo para obtener los módulos de un rol en especifico.
     * 
     * @param {number} rolId Identificador del rol.
     */
    const obtenerModulosRol = async (rolId) => {
        try {

            const formData = new FormData();
            formData.append('rolId', rolId);

            const response = await fetch(`<?= base_url() ?>admin/roles/obtenerModulosRol`, {
                method: 'POST',
                body: formData
            });

            const modulos = await response.json();

            renderizarModulosRol(modulos);

        } catch (error) {
            console.error('Error al obtener los módulos del rol:', error);
        }
    };

    /**
     * Metodo para asignar el módulo especifico al rol.
     * 
     * @param int Id Identificador del módulo.
     */
    asginarModulo = async (id) => {
        $(`#asignar_${id}`).prop('disabled', true);

        const formData = new FormData();
        formData.append('rolId', rolId);
        formData.append('moduloId', id);

        try {

            const response = await fetch('<?= base_url() ?>admin/roles/asignarModuloRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result || result.success === false || typeof result.asignados === 'undefined') {
                throw new Error((result && result.message) || 'No se pudo asignar el módulo.');
            }

            renderizarModulosRol(result);

            Toast.fire({
                icon: 'success',
                title: result.message || 'Módulo asignado correctamente.'
            });

        } catch (error) {
            console.error('Error al asignar el módulo al rol:', error);

            $(`#asignar_${id}`).prop('disabled', false);

            Toast.fire({
                icon: 'error',
                title: error.message || 'No se pudo asignar el módulo.'
            });
        }
    }

    /**
     * Metodo para quitar un módulo especifico al rol.
     * 
     * @param int Id Identificador del módulo.
     */
    quitarModulo = async (id) => {
        $(`#quitar_${id}`).prop('disabled', true);

        try {
            const formData = new FormData();
            formData.append('rolId', rolId);
            formData.append('moduloId', id);

            const response = await fetch('<?= base_url() ?>admin/roles/quitarModuloRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result || result.success === false || typeof result.asignados === 'undefined') {
                throw new Error((result && result.message) || 'No se pudo quitar el módulo.');
            }

            renderizarModulosRol(result);

            Toast.fire({
                icon: 'success',
                title: result.message || 'Módulo quitado correctamente.'
            });

        } catch (error) {
            console.error('Error al quitar el módulo del rol:', error);

            $(`#quitar_${id}`).prop('disabled', false);

            Toast.fire({
                icon: 'error',
                title: error.message || 'No se pudo quitar el módulo.'
            });
        }
    }

    /**
     * 
     */
    document.addEventListener('DOMContentLoaded', function() {
        renderizarTabla();
    });

    document.querySelector('#formulario_editar_rol').addEventListener('submit', (e) => {
        e.preventDefault();
        updateRol();

        $('#select_rol').select2();
    });

    /**
     * 
     */
    document.querySelector('#formulario_creacion').addEventListener('submit', (e) => {
        e.preventDefault();
        crearRol();
    });

    /**
     * 
     */
    $('#select_rol').select2({
        ajax: {
            url: '<?= base_url() ?>admin/roles/buscarRol',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: 'Buscar rol por nombre',
        allowClear: true
    });

    /**
     * 
     */
    $('#select_rol').on('select2:select', function(e) {
        rolId = e.params.data.id;
        obtenerModulosRol(rolId);
    });

    /**
     * 
     */
    $('#select_rol').on('select2:unselect', function(e) {
        document.querySelector('#rolModulesList').innerHTML = 'Seleccione el rol para ver los módulos asignados.';
        document.querySelector('#availableRolModulesList').innerHTML = 'Seleccione el rol para ver los módulos que no tiene asignados.';
    });
</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->