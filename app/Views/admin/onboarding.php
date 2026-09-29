<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/reportesPowerBI.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">
    
    <section class="section dashboard">
        <div class="row">

            <!--Sección reportes power bi-->
            <?php if(!empty($reportes_usuario)): ?>
                <?php foreach($reportes_usuario as $reporte): ?>
            <!-- <div class="col-xl-6 col-lg-6 col-md-6">    
                <div class="card info-card sales-card" id="card-tab-<?= $reporte['Id'] ?>">
                    <div class="card-body">
                        <div class="text-center">
                            <button class="btn btn-warning btn-sm float-end mt-3" onclick="pantallaCompleta(<?= $reporte['Id'] ?>)"><i class="bi bi-arrows-angle-expand"></i></button>
                            <h5 class="card-title"><?php echo $reporte['Nombre'];?></h5>
                        </div>
                        <iframe class="iframe-reporte" src="<?= $reporte['Enlace'] ?>" frameborder="0"></iframe>
                    </div>
                </div>
            </div> -->
                <?php endforeach;?>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>
<script>
    const pantallaCompleta = (id) => {
        let elemento = document.getElementById('card-tab-'+id);

		if(!document.requestFullscreenElement){
			elemento.requestFullscreen();
		}else{
			document.exitFullscreen();
		}
    }
</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->