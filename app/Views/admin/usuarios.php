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
            <a class="nav-link active" id="listUsers-tab" data-bs-toggle="tab" href="#listUsers" role="tab"
                aria-controls="listUsers" aria-selected="true">
                Lista de Usuarios
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="createUser-tab" data-bs-toggle="tab" href="#createUser" role="tab"
                aria-controls="createUser" aria-selected="false">
                Crear Usuario
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="assignRoles-tab" data-bs-toggle="tab" href="#assignRoles" role="tab"
                aria-controls="assignRoles" aria-selected="false">
                Asignar Roles
            </a>
        </li>
    </ul>

    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="userManagementTabsContent">
        <!-- Tab 1: Lista de Usuarios -->
        <div class="tab-pane fade show active" id="listUsers" role="tabpanel" aria-labelledby="listUsers-tab">
            <div class="card">
                <div class="card-body">
                    <!-- Agrega un contenedor table-responsive para evitar el desbordamiento -->
                    <div class="table-responsive" id="contenedor_table">
                        <?php echo $tabla_usuarios; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Crear Usuario -->
        <div class="tab-pane fade" id="createUser" role="tabpanel" aria-labelledby="createUser-tab">
            <div class="card">
                <div class="card-body p-4">
                    <form id="formulario_creacion">
                        <div class="row mb-4">
                            <!-- Nombre -->
                            <div class="col-4">
                                <label for="nombre_crear" class="form-label">Nombre:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="nombre_crear"
                                        placeholder="Digite el nombre del usuario"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                </div>
                            </div>

                            <!-- Apellido -->
                            <div class="col-4">
                                <label for="apellido_crear" class="form-label">Apellido:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person-fill"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="apellido_crear"
                                        placeholder="Digite el apellido del usuario"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                </div>
                            </div>

                            <!-- Usuario -->
                            <div class="col-4">
                                <label for="usuario_crear" class="form-label">Usuario:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person-fill"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="usuario_crear"
                                        placeholder="Digite el usuario de inicio de sesión"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <!-- Empresa -->
                            <div class="col-4">
                                <label for="empresa_crear" class="form-label">Empresa:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="empresa_crear"
                                        placeholder="Digite la empresa del usuario"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-4">
                                <label for="email_crear" class="form-label">Email:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control shadow-sm" id="email_crear"
                                        placeholder="Digite el correo electrónico"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                </div>
                            </div>

                            <!-- Teléfono -->
                            <div class="col-4">
                                <label for="telefono_crear" class="form-label">Teléfono:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="telefono_crear"
                                        placeholder="Digite el número de teléfono" onkeypress="soloNumeros(event)"
                                        onpaste="return false">
                                </div>
                            </div>

                        </div>

                        <div class="row mb-4">
                            <!-- Documento -->
                            <div class="col-4">
                                <label for="documento_crear" class="form-label">Documento:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="bi bi-file-earmark-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="documento_crear"
                                        placeholder="Digite el número de documento" onkeypress="soloNumeros(event)"
                                        onpaste="return false" required>
                                </div>
                            </div>

                            <!-- Contraseña -->
                            <div class="col-4">
                                <label for="password_crear" class="form-label">Contraseña:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control shadow-sm" id="password_crear"
                                        placeholder="Digite la contraseña (cédula)"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Crear Usuario -->
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-primary w-50 shadow-sm"
                                    id="btn_crear_usuario">Crear Usuario</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab 3: Asignar Roles -->
        <div class="tab-pane fade" id="assignRoles" role="tabpanel" aria-labelledby="assignRoles-tab">
            <div class="card">
                <div class="card-body mt-3">
                    <!-- Sección de Consulta de Usuario -->
                    <div class="row mb-4">
                        <div class="col-md-6 d-flex flex-column">
                            <label for="searchUser" class="form-label">Consultar Usuario por Nombre</label>
                            <select id="select_usuario" class="form-control">

                            </select>
                        </div>
                    </div>

                    <!-- Sección de Roles por Usuario -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6><b>Roles del Usuario</b></h6>
                            <ul class="list-group" id="userRolesList">
                                Seleccione el usuario para ver los roles asignados.
                            </ul>
                        </div>

                        <div class="col-md-6">
                            <h6><b>Roles no asignados</b></h6>
                            <ul class="list-group" id="availableUserRolesList">
                                Seleccione el usuario para ver los roles que no tiene asignados.
                            </ul>
                        </div>
                    </div>

                    <hr>

                    <!-- Sección de Asignación Masiva de Roles -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h6>Agregar Roles de Manera Masiva</h6>
                        </div>
                    </div>

                    <!-- Selección de Usuarios -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="selectMultipleUsers" class="form-label">Seleccione Usuarios</label>
                            <select class="form-select" id="selectMultipleUsers" multiple
                                aria-label="Seleccione varios usuarios">
                                <?= $lista_usuarios_activos ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="selectMassiveRole" class="form-label">Seleccione Rol</label>
                            <select class="form-select" id="selectMassiveRole">
                                <?= $lista_roles_activos ?>
                            </select>
                        </div>
                    </div>

                    <!-- Botón de Asignar Roles Masivos -->
                    <div class="row">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-primary w-100" id="btn_asignar_rol_masivo"
                                onclick="asignarRolMasivo()">Asignar Rol a Usuarios Seleccionados</button>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- MODAL DE EDICION DE USUARIO -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <ul class="nav nav-tabs" id="userEditTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="EditUser-tab" data-bs-toggle="tab" href="#editUser"
                                role="tab" aria-controls="editUser" aria-selected="true">
                                Editar Información
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="PassRecover-tab" data-bs-toggle="tab" href="#passRecover" role="tab"
                                aria-controls="passRecover" aria-selected="false">
                                Reestablecer Contraseña
                            </a>
                        </li>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="tab-content" id="userEditTabsContent">

                        <!-- TAB 1: Editar Usuario -->
                        <div class="tab-pane fade show active" id="editUser" role="tabpanel"
                            aria-labelledby="EditUser-tab">
                            <form class="row g-3" id="formulario_editar_usuario">
                                <div class="col-md-4">
                                    <label for="nombre" class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="nombre" value=""
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-md-4">
                                    <label for="apellido" class="form-label">Apellido</label>
                                    <input type="text" class="form-control" id="apellido" value=""
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-md-4">
                                    <label for="usuario" class="form-label">Usuario</label>
                                    <input type="text" class="form-control" id="usuario"
                                        aria-describedby="inputGroupPrepend"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-md-4">
                                    <label for="empresa" class="form-label">Empresa</label>
                                    <input type="text" class="form-control" id="empresa"
                                        aria-describedby="inputGroupPrepend"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-md-4">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="text" class="form-control" id="email_usuario"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-md-4">
                                    <label for="telefono" class="form-label">Télefono</label>
                                    <input type="number" class="form-control" id="telefono"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-md-4">
                                    <label for="documento" class="form-label">Documento</label>
                                    <input type="number" class="form-control" id="documento"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                                <div class="col-md-4">
                                    <label for="estado" class="form-label">Estado</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="invalidCheck">
                                        <label class="form-check-label" for="invalidCheck">
                                            Activar/Inactivar
                                        </label>
                                    </div>
                                </div>

                                <input type="hidden" id="Id" value="">

                                <div class="col-12 text-center">
                                    <button class="btn btn-primary" type="submit"
                                        id="btn_actualizar_usuario">Guardar</button>
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: Reestablecer Contraseña -->
                        <div class="tab-pane fade" id="passRecover" role="tabpanel" aria-labelledby="PassRecover-tab">
                            <form id="formulario_restablecer_contraseña" class="p-3">
                                <div class="mb-3">
                                    <label for="newPassword" class="form-label">Nueva Contraseña</label>
                                    <div class="input-group has-validation">
                                        <input type="password" class="form-control" id="newPassword"
                                            onkeypress="return noStrangeCharacters(event)" onpaste="return false"
                                            required>
                                        <span class="input-group-text togglePassword"
                                            onclick="togglePasswordVisibility('newPassword','togglePasswordIcon1')">
                                            <i class="bi bi-eye" id="togglePasswordIcon1"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirmPassword" class="form-label">Confirmar Contraseña</label>
                                    <div class="input-group has-validation">
                                        <input type="password" class="form-control" id="confirmPassword"
                                            onkeypress="return noStrangeCharacters(event)" onpaste="return false"
                                            required>
                                        <span class="input-group-text togglePassword"
                                            onclick="togglePasswordVisibility('confirmPassword','togglePasswordIcon2')">
                                            <i class="bi bi-eye" id="togglePasswordIcon2"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary"
                                        id="btn_restablecer_contraseña">Restablecer</button>
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
     * Propiedad para almacenar el rol del usuario seleccionado
     * para gestionar sus roles.
     */
    let usuarioId;

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
     * Metodo para guardar la informacion actualizada del usuario.
     */
    const updateUser = async () => {
        $('#btn_actualizar_usuario').prop('disabled', true);

        const formData = new FormData();
        formData.append('Id', $('#Id').val());
        formData.append('Nombre', $('#nombre').val());
        formData.append('Apellido', $('#apellido').val());
        formData.append('Usuario', $('#usuario').val());
        formData.append('Empresa', $('#empresa').val());
        formData.append('Email', $('#email_usuario').val());
        formData.append('Telefono', $('#telefono').val());
        formData.append('Documento', $('#documento').val());
        formData.append('FechaFinalizacion', $('#invalidCheck').prop('checked') ? 1 : 0);

        try {
            const response = await fetch(`<?= base_url() ?>admin/usuarios/actualizarUsuario`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            $('#editUserModal').modal('hide');

            Toast.fire({
                icon: 'success',
                title: 'Usuario actualizado correctamente.'
            });

            $('#contenedor_table').html(result);
            $('#btn_actualizar_usuario').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * Metodo para obtener la informacion de un usuario en especifico
     * cuando se abre el modal de edicion.
     */
    const obtenerUsuario = async (id) => {
        document.querySelector('#formulario_editar_usuario').reset();

        try {
            const response = await fetch(`<?= base_url() ?>admin/usuarios/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                $('#Id').val(result.usuario.Id);
                $('#nombre').val(result.usuario.Nombre);
                $('#apellido').val(result.usuario.Apellido);
                $('#usuario').val(result.usuario.Usuario);
                $('#empresa').val(result.usuario.Empresa);
                $('#email_usuario').val(result.usuario.Email);
                $('#telefono').val(result.usuario.Telefono);
                $('#documento').val(result.usuario.Documento);
                $('#invalidCheck').prop('checked', result.usuario.FechaFinalizacion === null);
            }

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const renderizarTabla = () => {

        if ($.fn.DataTable.isDataTable('#tabla_usuarios')) {
            $('#tabla_usuarios').DataTable().destroy();
        }

        $('#tabla_usuarios').DataTable({
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
     * Metodo para crear un nuevo usuario.
     */
    const crearUsuario = async () => {
        $('#btn_crear_usuario').prop('disabled', true);

        const nombre = $('#nombre_crear').val().trim();
        const apellido = $('#apellido_crear').val().trim();
        const usuario = $('#usuario_crear').val().trim();
        const empresa = $('#empresa_crear').val().trim();
        const email = $('#email_crear').val().trim();
        const telefono = $('#telefono_crear').val().trim();
        const documento = $('#documento_crear').val().trim();
        const password = $('#password_crear').val().trim();

        // Verificar si algún campo está vacío
        if (!nombre || !apellido || !usuario || !email || !documento || !password) {
            Toast.fire({
                icon: 'warning',
                title: 'Los campos de nombre, apellido, usuario, email, documento y contraseña son obligatorios.'
            });
            $('#btn_crear_usuario').prop('disabled', false);
            return;
        }

        const formData = new FormData();
        formData.append('Nombre', nombre);
        formData.append('Apellido', apellido);
        formData.append('Usuario', usuario);
        formData.append('Empresa', empresa);
        formData.append('Email', email);
        formData.append('Telefono', telefono);
        formData.append('Documento', documento);
        formData.append('Password', password);

        try {
            const response = await fetch(`<?= base_url() ?>admin/usuarios/crearUsuario`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success === false) {
                Toast.fire({
                    icon: 'warning',
                    title: result.message
                });
                $('#btn_crear_usuario').prop('disabled', false);
            }

            const text = await response.text();

            Toast.fire({
                icon: 'success',
                title: 'Usuario creado correctamente.'
            });

            $('#formulario_creacion').trigger('reset');
            $('#contenedor_table').html(text);
            $('#btn_crear_usuario').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }

    }

    /**
     * Metodo para renderizar el listado de roles que un usuario tiene asignados.
     * Tambien se renderizan los roles que no tiene asignados.
     * 
     * @param {object} roles Listado de roles asignados y no asignados.
     */
    const renderizarRolesUsuario = (roles) => {
        document.querySelector('#userRolesList').innerHTML =
            roles.asignados == '' ? 'No tiene ningún rol asignado.' : roles.asignados;

        document.querySelector('#availableUserRolesList').innerHTML =
            roles.todos == '' ? 'Tiene todos los roles asignado.' : roles.todos;
    }

    /**
     * Metodo para obtener los roles de un usuario en especifico.
     * 
     * @param {number} usuarioId Identificador del usuario.
     */
    const obtenerRolesUsuario = async (usuarioId) => {
        try {

            const formData = new FormData();
            formData.append('usuarioId', usuarioId);

            const response = await fetch(`<?= base_url() ?>admin/usuarios/obtenerRolesUsuario`, {
                method: 'POST',
                body: formData
            });

            const roles = await response.json();

            renderizarRolesUsuario(roles);

        } catch (error) {
            console.error('Error al obtener los roles del usuario:', error);
        }
    };

    /**
     * Metodo para asignar el rol especifico al usuario.
     * 
     * @param int Id Identificador del rol.
     */
    asginarRol = async (id) => {
        $(`#asignar_${id}`).prop('disabled', true);

        const formData = new FormData();
        formData.append('usuarioId', usuarioId);
        formData.append('rolId', id);

        try {

            const response = await fetch('<?= base_url() ?>admin/usuarios/asignarRolUsuario', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            renderizarRolesUsuario(result);
            $(`#asignar_${id}`).prop('disabled', true);

        } catch (error) {
            console.error('Error al asignar el rol al usuario:', error);
        }
    }

    /**
     * Metodo para quitar un rol especifico al usuario.
     * 
     * @param int Id Identificador del rol.
     */
    quitarRol = async (id) => {
        $(`#quitar_${id}`).prop('disabled', true);

        try {
            const formData = new FormData();
            formData.append('usuarioId', usuarioId);
            formData.append('rolId', id);

            const response = await fetch('<?= base_url() ?>admin/usuarios/quitarRolUsuario', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            renderizarRolesUsuario(result);

            $(`#quitar_${id}`).prop('disabled', true);
        } catch (error) {
            console.error('Error al quitar el rol del usuario:', error);
            document.getElementById('progressBarContainer').style.display = 'none';
        }
    }

    /**
     * Metodo para asignar el rol seleccionado a todos los usuarios
     * seleccionados en el select multiple.
     */
    const asignarRolMasivo = async () => {
        $('#btn_asignar_rol_masivo').prop('disabled', true);

        const selectMultiple = document.getElementById('selectMultipleUsers');
        const selectMassiveRole = document.getElementById('selectMassiveRole');

        const usuarios = Array.from(selectMultiple.selectedOptions).map(option => option.value);
        const rol = selectMassiveRole.value;

        const formData = new FormData();
        formData.append('usuarios', usuarios);
        formData.append('rol', rol);

        try {
            const response = await fetch('<?= base_url() ?>admin/usuarios/actualizarRolUsuarios', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            document.getElementById('selectMultipleUsers').innerHTML = result.usuarios;
            document.getElementById('selectMassiveRole').innerHTML = result.roles;

            Toast.fire({
                icon: 'success',
                title: result.message
            });

            $('#btn_asignar_rol_masivo').prop('disabled', false);

        } catch (error) {
            console.error('Error al asignar el rol al usuario:', error);
        }
    }

    /**
     * 
     */
    document.querySelector('#formulario_editar_usuario').addEventListener('submit', (e) => {
        e.preventDefault();
        updateUser();

        $('#select_usuario').select2();
    });

    /**
     * 
     */
    document.querySelector('#formulario_creacion').addEventListener('submit', (e) => {
        e.preventDefault();
        crearUsuario();
    });

    /**
     * 
     */
    $('#select_usuario').select2({
        ajax: {
            url: '<?= base_url() ?>admin/usuarios/buscarUsuario',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: 'Buscar usuario por nombre',
        allowClear: true
    });

    /**
     * 
     */
    $('#select_usuario').on('select2:select', function (e) {
        usuarioId = e.params.data.id;
        obtenerRolesUsuario(usuarioId);
    });

    /**
     * 
     */
    $('#select_usuario').on('select2:unselect', function (e) {
        document.querySelector('#userRolesList').innerHTML = 'Seleccione el usuario para ver los roles asignados.';
        document.querySelector('#availableUserRolesList').innerHTML = 'Seleccione el usuario para ver los roles que no tiene asignados.';
    });

    /**
     * Metodo para guardar la informacion actualizada del usuario.
     */
    const recoverPass = async () => {
        $('#btn_restablecer_contraseña').prop('disabled', true);
        loader('show');

        const nueva = $('#newPassword').val();
        const confirmar = $('#confirmPassword').val();
        const expresion = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

        if (!expresion.test(nueva)) {
            loader('hide');
            Toast.fire({
                icon: 'error',
                title: 'La contraseña debe tener mínimo 8 caracteres, incluir al menos una mayúscula, una minúscula y un número.'
            });
            $('#btn_restablecer_contraseña').prop('disabled', false);
            return;
        }

        if (nueva !== confirmar) {
            loader('hide');
            Toast.fire({
                icon: 'warning',
                title: 'Las contraseñas ingresadas no coinciden.'
            });
            $('#btn_restablecer_contraseña').prop('disabled', false);
            return;
        }

        const formData = new FormData();
        formData.append('Id', $('#Id').val());
        formData.append('Contraseña', $('#newPassword').val());

        try {
            const response = await fetch(`<?= base_url() ?>admin/usuarios/updatePass`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();
            loader('hide');
            $('#editUserModal').modal('hide');

            Toast.fire({
                icon: 'success',
                title: 'Contraseña reestablecida correctamente.'
            });

            $('#newPassword').val('');
            $('#confirmPassword').val('');
            $('#contenedor_table').html(result);
            $('#btn_restablecer_contraseña').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * 
     */
    document.querySelector('#formulario_restablecer_contraseña').addEventListener('submit', (e) => {
        e.preventDefault();
        recoverPass();

        $('#select_usuario').select2();
    });

    /**
     * Metodo para activar/desactivar la vista de la contraseña en
     * el formulario de login.
     */
    const togglePasswordVisibility = (inputId, iconId) => {
        const passwordInput = document.getElementById(inputId);
        const togglePasswordIcon = document.getElementById(iconId);

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            togglePasswordIcon.classList.remove('bi-eye');
            togglePasswordIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            togglePasswordIcon.classList.remove('bi-eye-slash');
            togglePasswordIcon.classList.add('bi-eye');
        }
    }

</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->