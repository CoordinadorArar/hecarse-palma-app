<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= empty($title) || $title === sitio('nombre_app') ? '' : $title . ' · ' ?><?= esc(sitio('nombre_app')) ?></title>

    <!-- Favicons -->
    <link href="<?= esc(sitio_img('favicon'), 'attr') ?>" rel="icon">
    <link href="<?= base_url('public/assets/img/elaeix_apple_touch_180.png') ?>" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link
        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
        rel="stylesheet">

    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.6/css/dataTables.bootstrap5.css"> -->

    <!-- <link
        href="https://cdn.datatables.net/v/bs5/jq-3.7.0/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-colvis-3.1.2/b-html5-3.1.2/b-print-3.1.2/datatables.min.css"
        rel="stylesheet"> -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.4.1/css/rowGroup.bootstrap5.min.css">
    <!-- Vendor CSS Files -->
    <link href="<?= base_url() ?>public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <?php $temaSitio = array_map(fn ($v) => preg_replace('/[^#0-9a-fA-F,]/', '', (string) $v), sitio_colores()); ?>
    <style>:root{--app-primary:<?= $temaSitio['primary'] ?>;--app-primary-rgb:<?= $temaSitio['primary_rgb'] ?>;--app-primary-hover:<?= $temaSitio['hover'] ?>;--app-primary-active:<?= $temaSitio['active'] ?>;--app-primary-subtle:<?= $temaSitio['subtle'] ?>;--app-primary-border:<?= $temaSitio['border'] ?>;--app-primary-emphasis:<?= $temaSitio['emphasis'] ?>;--app-secundario:<?= $temaSitio['secundario'] ?>;--app-secundario-rgb:<?= $temaSitio['secundario_rgb'] ?>}</style>
    <link href="<?= base_url() ?>public/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= base_url() ?>public/assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="<?= base_url() ?>public/assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="<?= base_url() ?>public/assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="<?= base_url() ?>public/assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="<?= asset_url('public/assets/css/preloader.css') ?>" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- <link href="<?= base_url() ?>public/assets/vendor/simple-datatables/style.css" rel="stylesheet"> -->

    <!-- Template Main CSS File -->
    <link href="<?= asset_url('public/assets/css/style.css') ?>" rel="stylesheet">
    <link href="<?= asset_url('public/assets/css/sidebar.css') ?>" rel="stylesheet">
    <link href="<?= asset_url('public/assets/css/tema.css') ?>" rel="stylesheet">
    <link href="<?= asset_url('public/assets/css/tutoriales.css') ?>" rel="stylesheet">
    <?php if (alerta_labores_permitida()): ?>
    <link href="<?= asset_url('public/assets/css/alerta_labores.css') ?>" rel="stylesheet">
    <?php endif; ?>

    <!-- RENDERIZAR CUSTOM CSS, SI EXISTEN -->
    <?php echo $this->renderSection("css"); ?>
</head>

<body>
    <div class="loader d-none">
        <div class="justify-content-center jimu-primary-loading"></div>
    </div>
    <!-- SIDEBAR -->
    <?php

    $uri = service('uri');

    $conHeader = (
        $uri->getSegment(1) !== ''
        && $uri->getSegment(1) !== 'losetas'
        && $uri->getSegment(1) !== 'recover'
        && $uri->getSegment(1) != 'login'
        && $uri->getSegment(1) != 'resetPass'
        && $uri->getSegment(2) !== 'documentos'
    );

    if ($conHeader) {
        $this->include('template/sidebar');
        $this->include('template/header');

        echo $this->renderSection('header');
        echo $this->renderSection('sidebar');
    }
    ?>

    <!-- RENDERIZAR CONTENIDO PRINCIPAL -->
    <?php echo $this->renderSection("contenido"); ?>

    <?php if (alerta_labores_permitida()) echo $this->include('template/alerta_labores'); ?>

    <!-- Vendor JS Files -->
    <script src="<?= base_url() ?>public/assets/vendor/apexcharts/apexcharts.min.js"></script>
    <script src="<?= base_url() ?>public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url() ?>public/assets/vendor/chart.js/chart.umd.js"></script>
    <script src="<?= base_url() ?>public/assets/vendor/echarts/echarts.min.js"></script>
    <script src="<?= base_url() ?>public/assets/vendor/quill/quill.js"></script>
    <script src="<?= base_url() ?>public/assets/vendor/tinymce/tinymce.min.js"></script>
    <script src="<?= base_url() ?>public/assets/vendor/php-email-form/validate.js"></script>
    <script src="<?= base_url() ?>public/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
    <!-- <script src="https://cdn.datatables.net/2.1.6/js/dataTables.min.js"></script> -->
    <!-- <script src="https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.min.js"></script> -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <!-- <script src="https://cdn.datatables.net/v/bs5/jq-3.7.0/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-colvis-3.1.2/b-html5-3.1.2/b-print-3.1.2/datatables.min.js"></script> -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= asset_url('public/assets/js/datatables-defaults.js') ?>"></script>

    <script src="https://cdn.datatables.net/rowgroup/1.4.1/js/dataTables.rowGroup.min.js"></script>

    <!-- // todo: sirven -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.6/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.6/js/dataTables.bootstrap5.js"></script> -->

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Sweetalert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script> const logoutUrl = "<?= site_url('logout') ?>"; </script>

    <!-- Template Main JS File -->
    <script src="<?= asset_url('public/assets/js/main.js') ?>"></script>

    <!-- RENDERIZAR CUSTOM SCRIPTS, SI EXISTEN -->
    <?php echo $this->renderSection("scripts"); ?>

    <?php if ($conHeader): ?>
    <script src="<?= asset_url('public/assets/js/tutoriales/tutorial-manager.js') ?>"></script>
    <?php endif; ?>

    <?php if (alerta_labores_permitida()): ?>
    <script src="<?= asset_url('public/assets/js/alerta_labores.js') ?>"></script>
    <?php endif; ?>

</body>

</html>