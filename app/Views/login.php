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
                            <a href="#" class="logo d-flex align-items-center">
                                <img src="public/assets/img/logo_hecarse.png" width="300" height="130" alt="Hecarse">
                            </a>
                        </div>

                        <div class="card mb-3">

                            <div class="card-body">

                                <div class="pt-4 pb-2">
                                    <h5 class="card-title text-center pb-0 fs-4"><?= $welcome_message ?></h5>
                                    <p class="text-center small"><?= $subtitle_login ?></p>
                                </div>

                                <form id="loginForm" class="row g-3 needs-validation" novalidate>
                                    <div class="col-12">
                                        <label for="yourUsername" class="form-label"><?= $username ?></label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text" id="inputGroupPrepend">
                                                <i class="bi bi-person-fill"></i>
                                            </span>
                                            <input type="text" name="username" class="form-control" id="username"
                                                onkeypress="return noStrangeCharacters(event)" onpaste="return false"
                                                required>
                                            <div class="invalid-feedback"><?= $validation_required ?></div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="yourPassword" class="form-label"><?= $password ?></label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text">
                                                <i class="bi bi-lock-fill"></i>
                                            </span>
                                            <input type="password" name="password" class="form-control" id="password"
                                                onkeypress="return noStrangeCharacters(event)" onpaste="return false"
                                                required>
                                            <span class="input-group-text togglePassword"
                                                onclick="togglePasswordVisibility()">
                                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                            </span>
                                            <div class="invalid-feedback"><?= $validation_required ?></div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary w-100" type="submit"><?= $btn_login ?></button>
                                    </div>
                                    <div class="col-12 text-center">
                                        <a class="small text-decoration-underline text-primary"
                                            href="<?= base_url('recover') ?>"><?= $data_recover ?></a>
                                    </div>
                                    <!--- POLITICA Y PROTECCION DE DATOS --->
                                    <!-- <div class="col-12 text-center">
                                        <a class="small text-decoration-underline text-primary"
                                            href="#"><?= $data_protection ?></a>
                                    </div> -->
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

    <?php if (session()->getFlashdata('sesion_expirada')): ?>
        Toast.fire({
            icon: 'warning',
            title: '<?= session('sesion_expirada') ?>'
        });
    <?php endif; ?>

    /**
     * Función para enviar los datos del formulario de login.
     * 
     * @param {FormData} formData Datos del formulario.
     */
    const login = async (formData) => {
        try {
            const response = await fetch('verifyUser', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = 'losetas/';
            } else {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';

                Toast.fire({
                    icon: result.success ? 'success' : 'error',
                    title: result.message
                });

                if (result.requiereCambioPass) {
                    setTimeout(function () {
                        loader('hide');
                        window.location.href = 'http://localhost/distribuidora-rex/recover';
                    }, 8000);
                }
            }

        } catch (error) {
            console.error('Error en la solicitud:', error);
            Toast.fire({
                icon: result.success ? 'success' : 'error',
                title: result.message
            });
        }
    }

    /**
     * Metodo para activar/desactivar la vista de la contraseña en
     * el formulario de login.
     */
    const togglePasswordVisibility = () => {
        const passwordInput = document.getElementById('password');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

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
     * Evento para activar el posteo del formulario de login. 
     * Se llama la funcion para realizar la peticion al back.
     */
    document.getElementById('loginForm').addEventListener('submit', (event) => {
        event.preventDefault();

        const username = document.getElementById('username')?.value;
        const password = document.getElementById('password')?.value;

        if (!username || !password) return;

        const formData = new FormData();
        formData.append('username', username);
        formData.append('password', password);

        login(formData);
    });

</script>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->