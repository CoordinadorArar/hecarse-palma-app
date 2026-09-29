<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/header.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<?php echo $this->section('header'); ?>
<header id="header" class="header fixed-top d-flex align-items-center bg-primary">

    <div class="d-flex align-items-center justify-content-between">
        <i class="bi bi-list toggle-sidebar-btn text-white"></i>
        <a href="index.html" class="logo-header d-flex align-items-center">
            <img src="<?= base_url() ?>public/assets/img/logo_hecarse_blanco.png" alt="Hecarse">
        </a>
    </div><!-- End Logo -->

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">

            <!-- PANEL DE NOTIFICACIONES -->
            <!-- <li class="nav-item dropdown">
                <a class="nav-link nav-icon" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-bell text-white fs-5"></i> -->
            <!-- <span class="badge bg-danger badge-counter" id="contadorNotificaciones">3</span> -->
            <!-- </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow notifications">
                    <li class="dropdown-header">
                        Notificaciones
                        <a href="#" class="float-end small text-primary" onclick="marcarTodasLeidas()">Marcar todas como
                            leídas</a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li id="listaNotificaciones">
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <i class="bi bi-info-circle text-warning fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-dark">Nueva actualización disponible</h6>
                                <p class="small text-muted">Hace 2 min</p>
                            </div>
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li class="dropdown-footer">
                        <a href="#">Ver todas las notificaciones</a>
                    </li>
                </ul>
            </li> -->

            <!-- PANEL DE PERFIL USUARIO -->
            <li class="nav-item dropdown pe-3">
                <a class="nav-link nav-profile d-flex align-items-center gap-2 pe-0" href="#" data-bs-toggle="dropdown">
                    <!-- Foto de perfil -->
                    <img src="<?= $imagen_usuario ?>"
                         alt="Perfil"
                         class="rounded-circle"
                         style="width: 32px; height: 32px; object-fit: cover;">
                    <!-- Nombre -->
                    <span class="d-none d-md-block dropdown-toggle text-white"><?= $nombres ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li class="dropdown-header">
                        <h6><?= $nombres_apellidos ?></h6>
                        <span id="cargo"></span>                        
                        <span id="correo"><?= $correo ?></span>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <button class="dropdown-item d-flex align-items-center" data-bs-toggle="modal"
                            data-bs-target="#verticalycentered" id="btnMiPerfil">
                            <i class="bi bi-person"></i>
                            <span>Mi perfil</span>
                        </button>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <button class="dropdown-item d-flex align-items-center" id="cerrar_sesion"
                            onclick="cerrarSesion()">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Cerrar sesión</span>
                        </button>
                    </li>

                </ul>
            </li>
        </ul>
    </nav>
</header>

<!-- MODAL DEL PERFIL -->
<div class="modal fade" id="verticalycentered" tabindex="-1" aria-labelledby="perfilModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title" id="perfilModalLabel">
                    Mi Perfil
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4">

                <!-- SECCIÓN: Info + Datos -->
                <div class="row g-4 align-items-start">

                    <!-- Avatar + info básica -->
                    <div class="col-md-3 text-center">
                        <div class="position-relative d-inline-block mb-3">
                            <img id="fotoPerfil" src="<?= $imagen_usuario ?>"
                                 class="rounded-circle border border-3 border-primary"
                                 style="width:110px;height:110px;object-fit:cover;">
                        </div>
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <label for="inputFotoPerfil" class="btn btn-sm btn-outline-primary">
                                Cambiar
                            </label>
                            <label id="btnGuardarFoto" class="btn btn-sm btn-primary">
                                Guardar
                            </label>
                        </div>
                        <h6 class="mb-0 fw-bold" id="perfilUsuario"></h6>
                        <small class="text-muted d-block"><?= $nombres_apellidos ?></small>
                        <small class="text-muted d-block" id="fechaUsuario">Desde: </small>
                        <input type="file" id="inputFotoPerfil" accept="image/png" style="display:none;">
                    </div>

                    <!-- Datos del usuario -->
                    <div class="col-md-9">
                        <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.05em;">
                            Información de la cuenta
                        </h6>
                        <form class="row g-3" id="form_vista_perfil">
                            <div class="col-6">
                                <label class="form-label form-label-sm">Nombre</label>
                                <input type="text" class="form-control form-control-sm" id="perfilNombre" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-sm">Apellido</label>
                                <input type="text" class="form-control form-control-sm" id="perfilApellido" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-sm">Empresa</label>
                                <input type="text" class="form-control form-control-sm" id="perfilEmpresa" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-sm">Email</label>
                                <input type="text" class="form-control form-control-sm" id="perfilEmail" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-sm">Teléfono</label>
                                <input type="number" class="form-control form-control-sm" id="perfilTelefono" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-sm">Documento</label>
                                <input type="number" class="form-control form-control-sm" id="perfilDocumento" readonly>
                            </div>
                        </form>
                    </div>
                </div>

                <hr class="my-4">

                <!-- SECCIÓN: Cambiar contraseña -->
                <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.05em;">
                    Cambiar contraseña
                </h6>
                <form id="form_cambio_pass">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Contraseña nueva</label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="nuevaContrasena" class="form-control" id="nuevaContrasena"
                                    onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                <span class="input-group-text" style="cursor:pointer;"
                                    onclick="togglePasswordVisibility('nuevaContrasena','togglePasswordIcon1')">
                                    <i class="bi bi-eye" id="togglePasswordIcon1"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Confirmar contraseña</label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="confirmarContrasena" class="form-control" id="confirmarContrasena"
                                    onkeypress="return noStrangeCharacters(event)" onpaste="return false" required>
                                <span class="input-group-text" style="cursor:pointer;"
                                    onclick="togglePasswordVisibility('confirmarContrasena','togglePasswordIcon2')">
                                    <i class="bi bi-eye" id="togglePasswordIcon2"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button id="btn_cambio_contraseña" type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-shield-lock me-1"></i>Cambiar Contraseña
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

</header>
<?php echo $this->endSection(); ?>

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>

<script>

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

    /**
     * Metodo para cerrar sesion del usuario.
     */
    window.cerrarSesion = async () => {
        try {
            const response = await fetch('<?= base_url() . 'cerrar_sesion' ?>', {
                method: 'POST'
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = '<?= base_url() ?>';
            }

        } catch (error) {
            console.error(error);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {

        const mensaje = localStorage.getItem('mensajeToast');
        const id_usuario = <?= session('usu_id') ?>;

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


        /** MOSTRAR MENSAJE GUARDADO */
        if (mensaje) {
            const datos = JSON.parse(mensaje);
            Toast.fire({
                icon: datos.icon,
                title: datos.title
            });
            localStorage.removeItem('mensajeToast');
        }

        document.getElementById("btnMiPerfil").addEventListener("click", function () {
            fetch(`<?= base_url('obtener_usuario/') ?>${id_usuario}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const usuario = data.usuario;
                        const fecha = new Date(usuario.FechaInicio);
                        const fechaFormateada = fecha.toISOString().split('T')[0];
                        document.getElementById("perfilUsuario").textContent = usuario.Usuario;
                        document.getElementById("fechaUsuario").textContent = `Desde: ${fechaFormateada}`;
                        document.getElementById("perfilNombre").value = usuario.Nombre;
                        document.getElementById("perfilApellido").value = usuario.Apellido;
                        document.getElementById("perfilEmpresa").value = usuario.Empresa;
                        document.getElementById("perfilEmail").value = usuario.Email;
                        document.getElementById("perfilTelefono").value = usuario.Telefono;
                        document.getElementById("perfilDocumento").value = usuario.Documento;

                    }
                })
                .catch(error => console.error("Error al obtener el perfil:", error));
        });


        /**
         * Metodo para guardar la informacion actualizada del usuario.
         */
        const cambioPass = async () => {
            $('#btn_cambio_contraseña').prop('disabled', true);

            let nueva = $('#nuevaContrasena').val();
            let confirmar = $('#confirmarContrasena').val();
            let expresion = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

            if (!expresion.test(nueva)) {
                loader('hide');
                Toast.fire({
                    icon: 'error',
                    title: 'La contraseña debe tener mínimo 8 caracteres, incluir al menos una mayúscula, una minúscula y un número.'
                });
                $('#btn_cambio_contraseña').prop('disabled', false);
                return;
            }

            if (nueva !== confirmar) {
                Toast.fire({
                    icon: 'warning',
                    title: 'Las contraseñas ingresadas no coinciden.'
                });
                $('#btn_cambio_contraseña').prop('disabled', false);
                return;
            }

            const formData = new FormData();
            formData.append('Id', id_usuario);
            formData.append('Contraseña', $('#nuevaContrasena').val());

            try {
                const response = await fetch(`<?= base_url() ?>admin/usuarios/updatePass`, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.text();

                $('#editUserModal').modal('hide');

                Toast.fire({
                    icon: 'success',
                    title: 'Contraseña reestablecida correctamente.'
                });

                $('#nuevaContrasena').val('');
                $('#confirmarContrasena').val('');
                $('#btn_cambio_contraseña').prop('disabled', false);
                renderizarTabla();

            } catch (error) {
                console.log('Se ha producido un error: ', error);
            }
        }

        /**
         * 
         */
        document.querySelector('#form_cambio_pass').addEventListener('submit', (e) => {
            e.preventDefault();
            cambioPass();
        });


        /**
         * GUARDAR FOTO DE PERFIL
         */
        $('#btnGuardarFoto').on('click', async () => {
            $('#btnGuardarFoto').prop('disabled', true);
            const fileInput = document.getElementById('inputFotoPerfil');
            const file = fileInput.files[0];

            if (!file) {
                Toast.fire({
                    icon: 'warning',
                    title: 'Selecciona una imagen primero.'
                });
                $('#btnGuardarFoto').prop('disabled', false);
                return;
            }

            // Validación por tipo MIME
            if (file.type !== 'image/png') {
                Toast.fire({
                    icon: 'error',
                    title: 'El archivo debe ser una imagen PNG.'
                });
                $('#btnGuardarFoto').prop('disabled', false);
                return;
            }

            // Validación por extensión
            const extension = file.name.split('.').pop().toLowerCase();
            if (extension !== 'png') {
                Toast.fire({
                    icon: 'error',
                    title: 'La extensión del archivo debe ser .png.'
                });
                $('#btnGuardarFoto').prop('disabled', false);
                return;
            }

            //Validación por tamaño de la imagen
            if (file.size > 2 * 1024 * 1024) {
                Toast.fire({
                    icon: 'error',
                    title: 'La imagen no debe pesar más de 2MB.'
                });
                $('#btnGuardarFoto').prop('disabled', false);
                return;
            }

            const formData = new FormData();
            formData.append('imagenPerfil', file);
            formData.append('id_usuario', id_usuario);

            try {
                const response = await fetch('<?= base_url() ?>admin/usuarios/guardarFotoPerfil', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    localStorage.setItem('mensajeToast', JSON.stringify({
                        icon: 'success',
                        title: 'Foto actualizada correctamente.'
                    }));
                    $('#fotoPerfil').attr('src', result.rutaImagen + '?' + new Date().getTime());
                    $('#btnGuardarFoto').prop('disabled', false);
                    loader('show');
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: result.message
                    });
                    $('#btnGuardarFoto').prop('disabled', false);
                }
                location.reload(true);
            } catch (error) {
                console.error('Error al subir la imagen:', error);
                Toast.fire({
                    icon: 'error',
                    title: 'Error al subir la imagen.'
                });
            } finally {
                $('#btnGuardarFoto').prop('disabled', false);
            }
        });

    });

</script>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->