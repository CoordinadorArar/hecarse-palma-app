<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/financiero.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="tab-content" id="moduleManagementTabs">
        <div class="tab-pane fade show active" id="moduleList" role="tabpanel" aria-labelledby="moduleList-tab">
            <div class="card">
                <div class="card-body">
                    <br>
                    <h3> <img src="<?= base_url() ?>public/assets/img/powerbi.png" height="35px" width="35px">
                        Reportes Power Bi
                    </h3>
                    <br>
                    <p> Visualiza los indicadores claves de la compañia en tiempo real. </p>

                    <br>

                    <div class="row">

                        <?php if (!empty($reportes_usuario)): ?>
                            <?php foreach ($reportes_usuario as $reporte): ?>

                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 mb-4">
                                    <div class="card report-card h-90">
                                        <div class="card-body d-flex flex-column">

                                            <div class="d-flex align-items-center justify-content-between mb-3 pt-2">
                                                <h5 class="mb-0">
                                                    <?= esc($reporte['Nombre']) ?>
                                                </h5>
                                                <i class="<?= esc($reporte['Icono']) ?> fs-4 ms-3"></i>
                                            </div>

                                            <p class="text-muted">
                                                <?= esc($reporte['Descripcion']) ?>
                                            </p>
                                            <br>
                                            <div class="mt-auto d-flex justify-content-center">
                                                <a href="<?= esc($reporte['Enlace']) ?>" target="_blank"
                                                    class="btn btn-primary w-90">
                                                    Ver dashboard
                                                </a>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    No tienes reportes Power BI asignados.
                                </div>
                            </div>
                        <?php endif; ?>

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

</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->