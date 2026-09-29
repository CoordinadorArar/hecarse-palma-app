<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/usuarios.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">
    <!-- Sección de Tabs -->
    <ul class="nav nav-tabs" id="userManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="listAcciones-tab" data-bs-toggle="tab" href="#listAcciones" role="tab" aria-controls="listAcciones" aria-selected="true">
                Lista de Acciones
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="createAccion-tab" data-bs-toggle="tab" href="#createAccion" role="tab" aria-controls="createAccion" aria-selected="false">
                Crear Acción
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="assignAccionesRoles-tab" data-bs-toggle="tab" href="#assignAccionesRoles" role="tab" aria-controls="assignAccionesRoles" aria-selected="false">
                Asignar Acciones a Rol
            </a>
        </li>
    </ul>

    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="userManagementTabsContent">

        <!-- Tab 1: Lista de Acciones -->
        <div class="tab-pane fade show active" id="listAcciones" role="tabpanel" aria-labelledby="listAcciones-tab">
            <div class="card">
                <div class="card-body">
                    <!-- Agrega un contenedor table-responsive para evitar el desbordamiento -->
                    <div class="table-responsive" id="contenedor_table">
                        <?php echo $tabla_acciones; ?>
                    </div>
                </div>
            </div>
        </div>

         <!-- Tab 2: Crear Acción -->
         <div class="tab-pane fade" id="createAccion" role="tabpanel" aria-labelledby="createAccion-tab">
            <div class="card">
                <div class="card-body p-4">
                    <form id="formulario_creacion">
                        <div class="row mb-4">
                            <!-- Modulo -->
                            <div class="col-6">
                                <label for="modulo_crear" class="form-label">Módulo:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <select class="form-select shadow-sm" id="modulo_crear" required>
                                        <option value="" disabled selected>Seleccione el módulo</option>
                                        <?php foreach ($modulos as $modulo) :?>
                                            <option value="<?php echo $modulo['Id'];?>"><?php echo $modulo['Nombre'];?></option>
                                        <?php endforeach;?>
                                    </select>
                                </div>
                            </div>
                            <!-- Nombre -->
                            <div class="col-6">
                                <label for="nombre_crear" class="form-label">Nombre:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="nombre_crear" placeholder="Digite el nombre de la acción" onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Crear Rol -->
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-primary w-50 shadow-sm" id="btn_crear_accion">Crear Acción</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab 3: Asignar acciones a rol -->
        <div class="tab-pane fade" id="assignAccionesRoles" role="tabpanel" aria-labelledby="assignAccionesRoles-tab">
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

                    <!-- Sección de Acciones por Rol -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6><b>Acciones Permitidas para el Rol</b></h6>
                            <ul class="list-group" id="rolAccionesList">
                                Seleccione el rol para ver las acciones asignadas.
                            </ul>
                        </div>

                        <div class="col-md-6">
                            <h6><b>Acciones no asignadas</b></h6>
                            <ul class="list-group" id="availableRolAccionesList">
                                Seleccione el rol para ver las acciones que no tiene asignadas.
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE EDICION DE USUARIO -->
    <div class="modal fade" id="editAccionModal" tabindex="-1" aria-labelledby="editAccionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="tab-content" id="accionEditTabsContent">

                        <!-- TAB 1: Editar Accion -->
                        <div class="tab-pane fade show active" id="editAccion" role="tabpanel" aria-labelledby="EditAccion-tab">
                            <form class="row g-3" id="formulario_editar_accion">
                                <div class="col-md-4">
                                    <label for="modulo_editar" class="form-label">Módulo:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                        <select class="form-select shadow-sm" id="modulo_editar">
                                            <option value="">Seleccione el módulo</option>
                                            <?php foreach ($modulos as $modulo) :?>
                                                <option value="<?php echo $modulo['Id'];?>"><?php echo $modulo['Nombre'];?></option>
                                            <?php endforeach;?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="nombre_editar" class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="nombre_editar" value=""onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="invalidCheck">Activar/Inactivar</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="invalidCheck">
                                    </div>
                                </div>
                        
                                <input type="hidden" id="IdAccion" value="">

                                <div class="col-12 text-center">
                                    <button class="btn btn-primary" type="submit" id="btn_actualizar_accion">Guardar</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </form>
                        </div>

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
    /**
     * Propiedad para almacenar el módulo del rol seleccionado
     * para gestionar sus módulos.
     */
    let accionId;

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
     * Metodo para obtener la informacion de un rol en especifico
     * cuando se abre el modal de edicion.
     */
    const obtenerAccion = async (id) => {
        document.querySelector('#formulario_editar_accion').reset();

        try {
            const response = await fetch(`<?= base_url() ?>admin/acciones/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                if(result.accion.IdModulo){
                    $('#modulo_editar option[value="'+result.accion.IdModulo+'"]').prop('selected', true);
                }
                $('#nombre_editar').val(result.accion.Nombre);
                $('#invalidCheck').prop('checked', result.accion.FechaFinalizacion === null);
                $('#IdAccion').val(result.accion.Id);
            }

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const renderizarTabla = () => {
        if ($.fn.DataTable.isDataTable('#tabla_acciones')) {
            $('#tabla_acciones').DataTable().destroy();
        }

        $('#tabla_acciones').DataTable({
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
    const crearAccion = async () => {
        $('#btn_crear_accion').prop('disabled', true);

        const formData = new FormData();
        formData.append('Modulo', $('#modulo_crear').val());
        formData.append('Nombre', $('#nombre_crear').val());

        try {
            const response = await fetch(`<?= base_url() ?>admin/acciones/crearAccion`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            Toast.fire({
                icon: 'success',
                title: 'Acción creada correctamente.'
            });

            $('#formulario_creacion').trigger('reset');
            $('#contenedor_table').html(result);
            $('#btn_crear_accion').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * 
     */
    document.querySelector('#formulario_creacion').addEventListener('submit', (e) => {
        e.preventDefault();
        crearAccion();
    });


    /**
     * Metodo para guardar la informacion actualizada del rol.
     */
    const updateAccion = async () => {
        $('#btn_actualizar_accion').prop('disabled', true);

        const formData = new FormData();
        formData.append('Id', $('#IdAccion').val());
        formData.append('IdModulo', $('#modulo_editar').val());
        formData.append('Nombre', $('#nombre_editar').val());
        formData.append('FechaFinalizacion', $('#invalidCheck').prop('checked') ? 1 : 0);

        try {
            const response = await fetch(`<?= base_url() ?>admin/acciones/actualizarAccion`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            $('#editAccionModal').modal('hide');

            Toast.fire({
                icon: 'success',
                title: 'Acción actualizada correctamente.'
            });

            $('#contenedor_table').html(result);
            $('#btn_actualizar_accion').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * 
     */
    document.querySelector('#formulario_editar_accion').addEventListener('submit', (e) => {
        e.preventDefault();
        updateAccion();
    });

    /**
     * 
     */
    document.addEventListener('DOMContentLoaded', function() {
        renderizarTabla();
    });


    /**
     * Metodo para renderizar el listado de acciones que un rol tiene asignados.
     * Tambien se renderizan los acciones que no tiene asignados.
     * 
     * @param {object} acciones Listado de acciones asignados y no asignados.
     */
    const renderizarAcccionesRol = (acciones) => {
        document.querySelector('#rolAccionesList').innerHTML =
            acciones.asignados == '' ? 'No tiene ningún acción asignada.' : acciones.asignados;

        document.querySelector('#availableRolAccionesList').innerHTML =
            acciones.todos == '' ? 'Tiene todos las acciones asignadas.' : acciones.todos;
    }

    /**
     * Metodo para obtener los módulos de un rol en especifico.
     * 
     * @param {number} rolId Identificador del rol.
     */
    const obtenerAccionesRol = async (rolId) => {
        try {

            const formData = new FormData();
            formData.append('rolId', rolId);

            const response = await fetch(`<?= base_url() ?>admin/acciones/obtenerAccionesRol`, {
                method: 'POST',
                body: formData
            });

            const acciones = await response.json();

            renderizarAcccionesRol(acciones);

        } catch (error) {
            console.error('Error al obtener las acciones del rol:', error);
        }
    };

    /**
     * Metodo para asignar la acción especifico al rol.
     * 
     * @param int Id Identificador del acción.
     */
    asginarAccion = async (id) => {
        $(`#asignar_${id}`).prop('disabled', true);

        const formData = new FormData();
        formData.append('rolId', rolId);
        formData.append('accionId', id);

        try {

            const response = await fetch('<?= base_url() ?>admin/acciones/asignarAccionRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            renderizarAcccionesRol(result);

            $(`#asignar_${id}`).prop('disabled', true);

        } catch (error) {
            console.error('Error al asignar la acción al rol:', error);
        }
    }

    /**
     * Metodo para quitar un módulo especifico al rol.
     * 
     * @param int Id Identificador del módulo.
     */
    quitarAccion = async (id) => {
        $(`#quitar_${id}`).prop('disabled', true);

        try {
            const formData = new FormData();
            formData.append('rolId', rolId);
            formData.append('accionId', id);

            const response = await fetch('<?= base_url() ?>admin/acciones/quitarAccionRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            renderizarAcccionesRol(result);

            $(`#quitar_${id}`).prop('disabled', true);
        } catch (error) {
            console.error('Error al quitar la acción del rol:', error);
            document.getElementById('progressBarContainer').style.display = 'none';
        }
    }

    /**
     * 
     */
    $('#select_rol').select2({
        ajax: {
            url: '<?= base_url() ?>admin/acciones/buscarRol',
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
        obtenerAccionesRol(rolId);
    });

    /**
     * 
     */
    $('#select_rol').on('select2:unselect', function(e) {
        document.querySelector('#rolAccionesList').innerHTML = 'Seleccione el rol para ver las acciones asignadas.';
        document.querySelector('#availableRolAccionesList').innerHTML = 'Seleccione el rol para ver las acciones que no tiene asignadas.';
    });


</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->