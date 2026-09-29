<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="public/assets/css/login.css">
<?php echo $this->endSection(); ?>

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
                                <img src="public/assets/img/logo_hecarse.png" width="300" height="130" alt="Hecarse">
                            </a>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">

                                <div class="pt-4 pb-2">
                                    <h5 class="card-title text-center pb-0 fs-4"><?= $welcome_message ?></h5>
                                    <p class="text-center small"><?= $subtitle_recover ?></p>
                                </div>

                                <form id="formulario_recuperacion" class="row g-3 needs-validation" novalidate
                                    method="POST">

                                    <div class="col-12">
                                        <label for="email" class="form-label"><?= $email_label ?></label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text" id="inputGroupPrepend">
                                                <i class="bi bi-envelope-fill"></i>
                                            </span>
                                            <input type="email" name="email" class="form-control" id="email" required>
                                            <div class="invalid-feedback"><?= $validation_required ?></div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary w-100" type="submit"
                                            id="btn_recuperar"><?= $btn_recover ?></button>
                                    </div>
                                    <div class="col-12 text-center">
                                        <a class="small text-decoration-underline text-primary"
                                            href="<?= base_url('/') ?>"><?= $back_to_login ?></a>
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
     * Metodo para recuperar contraseña.
     */
    const recuperarClave = async () => {
        $('#btn_recuperar').prop('disabled', true);
        loader('show');

        const formData = new FormData();
        formData.append('Email', $('#email').val());

        try {
            const response = await fetch(`<?= base_url() ?>recover/envioEnlace`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                loader('hide');
                Toast.fire({
                    icon: 'success',
                    title: result.message
                });
            } else {
                loader('hide');
                Toast.fire({
                    icon: 'error',
                    title: result.message
                });
            }

            //Ocultar mensaje de carga y habilitar el botón
            $('#btn_recuperar').prop('disabled', false);

            //Limpiar formulario
            $('#formulario_recuperacion').trigger('reset');

        } catch (error) {
            console.log('Se ha producido un error: ', error);
            loader('hide');
            $('#btn_recuperar').prop('disabled', false);
        }
    }

    document.querySelector('#formulario_recuperacion').addEventListener('submit', (e) => {
        e.preventDefault();
        recuperarClave();
    });

</script>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->