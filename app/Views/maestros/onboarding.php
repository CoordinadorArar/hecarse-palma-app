<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <section class="section dashboard">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-diagram-3 text-primary" style="font-size: 4rem;"></i>
                        <h4 class="mt-3 fw-semibold">Maestros</h4>
                        <p class="text-muted">
                            Selecciona un módulo en el menú lateral para administrar la información maestra del sistema.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->
