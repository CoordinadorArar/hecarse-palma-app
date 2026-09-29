<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1><?= esc($modulo) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active"><?= esc($modulo) ?></li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card">
                    <div class="card-body text-center py-5">

                        <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-4">
                            <i class="<?= esc($icono, 'attr') ?>"></i>
                        </div>

                        <div>
                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 mb-3">
                                <i class="bi bi-cone-striped me-1"></i>Módulo en construcción
                            </span>
                        </div>

                        <h5 class="card-title p-0 mb-2"><?= esc($modulo) ?></h5>

                        <p class="text-muted mb-4 mx-auto" style="max-width:520px;">
                            <?php if (!empty($descripcion)): ?>
                                <?= esc($descripcion) ?>
                            <?php endif; ?>
                            Esta sección está en desarrollo. Se habilitará en una próxima entrega; mientras tanto puede seguir usando el resto de módulos de Gestión Palma.
                        </p>

                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <a class="btn btn-primary" href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Volver a Gestión Palma</a>
                            <a class="btn btn-outline-secondary" href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->
