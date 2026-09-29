<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="public/assets/css/login.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main>
    <div class="container">

        <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">

                        <div class="d-flex justify-content-center py-4">
                            <a href="#" class="logo d-flex align-items-center w-auto">
                                <img src="<?= base_url('public/assets/img/logo_hecarse.png') ?>" width="30"
                                    alt="Hecarse">
                                <span class="d-none d-lg-block"><?= $title ?></span>
                            </a>
                        </div>

                        <div class="card mb-3">

                            <div class="card-body">

                                <div class="pt-4 pb-2">
                                    <h5 class="card-title text-center pb-0 fs-4"><?= $welcome_message ?></h5>
                                    <p class="text-center small"><?= $subtitle_login ?></p>
                                </div>

                                <form id="form_update_pass" class="row g-3 needs-validation" method="POST" novalidate>

                                    <input type="hidden" name="token_generado" class="form-control" id="token_generado"
                                        value="<?= esc($token) ?>">
                                    <input type="hidden" name="id_usuario" class="form-control" id="id_usuario"
                                        value="<?= esc($userId) ?>">

                                    <div class="col-12">
                                        <label for="yourPassword" class="form-label"><?= $password ?></label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text">
                                                <i class="bi bi-lock-fill"></i>
                                            </span>
                                            <input type="password" name="password" class="form-control" id="password"
                                                required>
                                            <span class="input-group-text togglePassword"
                                                onclick="togglePasswordVisibility('password','togglePasswordIcon')">
                                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                            </span>
                                            <div class="invalid-feedback"><?= $validation_required ?></div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="yourPassword" class="form-label"><?= $password_confirm ?></label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text">
                                                <i class="bi bi-lock-fill"></i>
                                            </span>
                                            <input type="password" name="password_confirm" class="form-control"
                                                id="password_confirm" required>
                                            <span class="input-group-text togglePassword"
                                                onclick="togglePasswordVisibility('password_confirm','togglePasswordIcon2')">
                                                <i class="bi bi-eye" id="togglePasswordIcon2"></i>
                                            </span>
                                            <div class="invalid-feedback"><?= $validation_required ?></div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary w-100" type="submit"
                                            id="btn_update_pass"><?= $btn_update_pass ?></button>
                                    </div>
                                    <div class="col-12 text-center">
                                        <a class="small text-decoration-underline text-primary"
                                            href="pages-register.html"><?= $data_protection ?></a>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </section>

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


    /**
     * Metodo para activar/desactivar la vista de la contraseña en
     * el formulario de login.
    */
    const togglePasswordVisibility = (inputId, iconId) => {
        const passwordInput = document.getElementById(inputId);  // Obtén el campo de la contraseña por su ID
        const togglePasswordIcon = document.getElementById(iconId);  // Obtén el icono de visibilidad por su ID

        // Verificar el tipo de campo y alternar entre 'password' y 'text'
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';  // Cambiar el tipo a 'text' para mostrar la contraseña
            togglePasswordIcon.classList.remove('bi-eye');  // Quitar el icono de 'ojo cerrado'
            togglePasswordIcon.classList.add('bi-eye-slash');  // Añadir el icono de 'ojo abierto'
        } else {
            passwordInput.type = 'password';  // Cambiar el tipo a 'password' para ocultar la contraseña
            togglePasswordIcon.classList.remove('bi-eye-slash');  // Quitar el icono de 'ojo abierto'
            togglePasswordIcon.classList.add('bi-eye');  // Añadir el icono de 'ojo cerrado'
        }
    }



    // Mostrar el mensaje de error y redirigir después de 3 segundos (si existe un mensaje de error)
    $(document).ready(function () {
        <?php if (isset($msg_caducado) && !empty($msg_caducado)): ?>
            //loader('show');
            Toast.fire({
                icon: 'error',
                title: '<?= $msg_caducado ?>'
            });
            setTimeout(function () {
                loader("hide");
                window.location.href = 'https://distrirex.com/app/'; // Redirige a la página de login
            }, 4000); // 4 segundos
        <?php endif; ?>
    })


    /**
     * Funcion para actualizar la contraseña en la base de datos.
    */
    const actualizarClave = async () => {
        $('#btn_update_pass').prop('disabled', true);
        loader('show');

        const formData = new FormData();
        formData.append('token_generado', $('#token_generado').val());
        formData.append('id_usuario', $('#id_usuario').val());
        formData.append('password', $('#password').val());  // Contraseña
        formData.append('password_confirm', $('#password_confirm').val()); // Confirmación de contraseña

        try {
            const response = await fetch(`<?= base_url() ?>resetPass/verifyPasswords`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            loader('hide');
            if (result.success) {
                Toast.fire({
                    icon: 'success',
                    title: result.message
                });

                // Redirigir después de 5 segundos (5000 milisegundos)
                setTimeout(function () {
                    loader('hide');
                    window.location.href = 'https://distrirex.com/app/'; // Redirige a la página de login
                }, 3000);
            } else {
                loader('hide');
                Toast.fire({
                    icon: 'error',
                    title: result.message
                });
            }

            $('#form_update_pass').trigger('reset');
            $('#btn_update_pass').prop('disabled', false);

        } catch (error) {
            console.log('Se ha producido un error: ', error);
            loader('hide');
        }
    }


    document.querySelector('#form_update_pass').addEventListener('submit', (e) => {
        e.preventDefault();
        actualizarClave();
    });

</script>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->