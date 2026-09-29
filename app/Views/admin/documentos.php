<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<style>
    iframe{
        height: 100vh;
    }
</style>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main class="comntainer-fluid">
    <div class="row">
        <iframe id="iframe" src="<?php echo base_url('public/documents/app/').$archivo; ?>#toolbar=0"></iframe>
    </div>
</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>
<script>
    document.addEventListener("keydown", function (event) {
        if (event.ctrlKey && (event.key === "s" || event.key === "S")) {
            event.preventDefault();
        }
    });

    document.addEventListener("contextmenu", function (event) {
        event.preventDefault();
    });
</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->