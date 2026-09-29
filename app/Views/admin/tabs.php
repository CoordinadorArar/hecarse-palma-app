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
            <a class="nav-link active" id="listTabs-tab" data-bs-toggle="tab" href="#listTabs" role="tab" aria-controls="listTabs" aria-selected="true">
                Lista de Pestañas
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="createTab-tab" data-bs-toggle="tab" href="#createTab" role="tab" aria-controls="createTab" aria-selected="false">
                Crear Pestaña
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="assignTabsRoles-tab" data-bs-toggle="tab" href="#assignTabsRoles" role="tab" aria-controls="assignTabsRoles" aria-selected="false">
                Asignar Pestañas a Rol
            </a>
        </li>
    </ul>

    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="userManagementTabsContent">

        <!-- Tab 1: Lista de Tabs -->
        <div class="tab-pane fade show active" id="listTabs" role="tabpanel" aria-labelledby="listTabs-tab">
            <div class="card">
                <div class="card-body">
                    <!-- Agrega un contenedor table-responsive para evitar el desbordamiento -->
                    <div class="table-responsive" id="contenedor_table">
                        <?php echo $tabla_tabs; ?>
                    </div>
                </div>
            </div>
        </div>

         <!-- Tab 2: Crear Tab -->
         <div class="tab-pane fade" id="createTab" role="tabpanel" aria-labelledby="createTab-tab">
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
                                    <input type="text" class="form-control shadow-sm" id="nombre_crear" placeholder="Digite el nombre de la pestaña" onkeypress="return noStrangeCharacters(event)">
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Crear Rol -->
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-primary w-50 shadow-sm" id="btn_crear_accion">Crear Pestaña</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab 3: Asignar tabs a rol -->
        <div class="tab-pane fade" id="assignTabsRoles" role="tabpanel" aria-labelledby="assignTabsRoles-tab">
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

                    <!-- Sección de Pestañas por Rol -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6><b>Pestañas Permitidas para el Rol</b></h6>
                            <ul class="list-group" id="rolTabsList">
                                Seleccione el rol para ver las pestañas asignadas.
                            </ul>
                        </div>

                        <div class="col-md-6">
                            <h6><b>Pestañas no asignadas</b></h6>
                            <ul class="list-group" id="availableRolTabsList">
                                Seleccione el rol para ver las pestañas que no tiene asignadas.
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE EDICION DE PESTAÑAS -->
    <div class="modal fade" id="editTabModal" tabindex="-1" aria-labelledby="editAccionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="tab-content" id="tabEditTabsContent">

                        <!-- TAB 1: Editar Pestaña -->
                        <div class="tab-pane fade show active" id="editTab" role="tabpanel" aria-labelledby="EditTab-tab">
                            <form class="row g-3" id="formulario_editar_tab">
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
                                    <input type="text" class="form-control" id="nombre_editar" value="" onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="invalidCheck">Activar/Inactivar</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="invalidCheck">
                                    </div>
                                </div>
                        
                                <input type="hidden" id="IdTab" value="">

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
    let tabId;

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
    const obtenerTab = async (id) => {
        document.querySelector('#formulario_editar_tab').reset();

        try {
            const response = await fetch(`<?= base_url() ?>admin/tabs/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                if(result.tab.IdModulo){
                    $('#modulo_editar option[value="'+result.tab.IdModulo+'"]').prop('selected', true);
                }
                $('#nombre_editar').val(result.tab.Nombre);
                $('#invalidCheck').prop('checked', result.tab.FechaFinalizacion === null);
                $('#IdTab').val(result.tab.Id);
            }

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const renderizarTabla = () => {
        if ($.fn.DataTable.isDataTable('#tabla_tabs')) {
            $('#tabla_tabs').DataTable().destroy();
        }

        $('#tabla_tabs').DataTable({
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
    const crearTab = async () => {
        $('#btn_crear_tab').prop('disabled', true);

        const formData = new FormData();
        formData.append('Modulo', $('#modulo_crear').val());
        formData.append('Nombre', $('#nombre_crear').val());

        try {
            const response = await fetch(`<?= base_url() ?>admin/tabs/crearTab`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            Toast.fire({
                icon: 'success',
                title: 'Pestaña creada correctamente.'
            });

            $('#formulario_creacion').trigger('reset');
            $('#contenedor_table').html(result);
            $('#btn_crear_tab').prop('disabled', false);
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
        crearTab();
    });


    /**
     * Metodo para guardar la informacion actualizada del rol.
     */
    const updateTab = async () => {
        $('#btn_actualizar_tab').prop('disabled', true);

        const formData = new FormData();
        formData.append('Id', $('#IdTab').val());
        formData.append('IdModulo', $('#modulo_editar').val());
        formData.append('Nombre', $('#nombre_editar').val());
        formData.append('FechaFinalizacion', $('#invalidCheck').prop('checked') ? 1 : 0);

        try {
            const response = await fetch(`<?= base_url() ?>admin/tabs/actualizarTab`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            $('#editTabModal').modal('hide');

            Toast.fire({
                icon: 'success',
                title: 'Pestaña actualizada correctamente.'
            });

            $('#contenedor_table').html(result);
            $('#btn_actualizar_tab').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * 
     */
    document.querySelector('#formulario_editar_tab').addEventListener('submit', (e) => {
        e.preventDefault();
        updateTab();
    });

    /**
     * 
     */
    document.addEventListener('DOMContentLoaded', function() {
        renderizarTabla();
    });


    /**
     * Metodo para renderizar el listado de tabs que un rol tiene asignados.
     * Tambien se renderizan los tabs que no tiene asignados.
     * 
     * @param {object} tabs Listado de tabs asignados y no asignados.
     */
    const renderizarTabsRol = (tabs) => {
        document.querySelector('#rolTabsList').innerHTML =
            tabs.asignados == '' ? 'No tiene ningún pestaña asignada.' : tabs.asignados;

        document.querySelector('#availableRolTabsList').innerHTML =
            tabs.todos == '' ? 'Tiene todos las pestañas asignadas.' : tabs.todos;
    }

    /**
     * Metodo para obtener los módulos de un rol en especifico.
     * 
     * @param {number} rolId Identificador del rol.
     */
    const obtenerTabsRol = async (rolId) => {
        try {

            const formData = new FormData();
            formData.append('rolId', rolId);

            const response = await fetch(`<?= base_url() ?>admin/tabs/obtenerTabsRol`, {
                method: 'POST',
                body: formData
            });

            const tabs = await response.json();

            renderizarTabsRol(tabs);

        } catch (error) {
            console.error('Error al obtener las pestañas del rol:', error);
        }
    };

    /**
     * Metodo para asignar la acción especifico al rol.
     * 
     * @param int Id Identificador del acción.
     */
    asginarTab = async (id) => {
        $(`#asignar_${id}`).prop('disabled', true);

        const formData = new FormData();
        formData.append('rolId', rolId);
        formData.append('tabId', id);

        try {

            const response = await fetch('<?= base_url() ?>admin/tabs/asignarTabRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            renderizarTabsRol(result);

            $(`#asignar_${id}`).prop('disabled', true);

        } catch (error) {
            console.error('Error al asignar la pestaña al rol:', error);
        }
    }

    /**
     * Metodo para quitar un módulo especifico al rol.
     * 
     * @param int Id Identificador del módulo.
     */
    quitarTab = async (id) => {
        $(`#quitar_${id}`).prop('disabled', true);

        try {
            const formData = new FormData();
            formData.append('rolId', rolId);
            formData.append('tabId', id);

            const response = await fetch('<?= base_url() ?>admin/tabs/quitarTabRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            renderizarTabsRol(result);

            $(`#quitar_${id}`).prop('disabled', true);
        } catch (error) {
            console.error('Error al quitar la pestaña del rol:', error);
            document.getElementById('progressBarContainer').style.display = 'none';
        }
    }

    /**
     * 
     */
    $('#select_rol').select2({
        ajax: {
            url: '<?= base_url() ?>admin/tabs/buscarRol',
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
        obtenerTabsRol(rolId);
    });

    /**
     * 
     */
    $('#select_rol').on('select2:unselect', function(e) {
        document.querySelector('#rolTabsList').innerHTML = 'Seleccione el rol para ver las pestañas asignadas.';
        document.querySelector('#availableRolTabsList').innerHTML = 'Seleccione el rol para ver las pestañas que no tiene asignadas.';
    });


</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->