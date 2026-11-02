<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/losetas.css') ?>">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main class="losetas-page">
    <!-- BARRA SUPERIOR -->
    <header class="losetas-topbar">
        <div class="losetas-topbar-brand">
            <img src="<?= esc(sitio_img('logo_claro'), 'attr') ?>" alt="<?= esc(sitio('nombre_app'), 'attr') ?>">
        </div>
        <div class="losetas-topbar-user">
            <?php if (!empty($nombre_usuario)): ?>
                <div class="losetas-user-info d-none d-sm-flex">
                    <span class="losetas-user-avatar"><?= esc(mb_strtoupper(mb_substr($nombre_usuario, 0, 1))) ?></span>
                    <span class="losetas-user-text">
                        <strong><?= esc($nombre_usuario) ?></strong>
                        <?php if (!empty($ultimo_acceso)): ?>
                            <small>Último acceso: <?= esc($ultimo_acceso) ?></small>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (alerta_labores_permitida()): ?>
                <button type="button" class="btn lpl-trigger lpl-trigger-topbar" aria-label="Próximas labores agrícolas" aria-haspopup="dialog" aria-controls="modalProximasLabores" data-bs-toggle="tooltip" data-bs-title="Próximas labores" data-bs-placement="bottom">
                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-logout" data-bs-toggle="tooltip" data-bs-title="Cerrar sesión">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-md-inline">Cerrar sesión</span>
            </button>
        </div>
    </header>

    <!-- ENCABEZADO DE BIENVENIDA -->
    <div class="losetas-hero text-center">
        <h1 class="losetas-hero-title"><?= esc($empresa) ?></h1>
        <p class="losetas-hero-subtitle"><?= esc($subtitle) ?></p>
    </div>

    <!-- GRID DE MODULOS -->
    <?php if (!empty($losetas)): ?>
        <nav class="losetas-grid" id="losetas" aria-label="Módulos disponibles">
            <?php foreach ($losetas as $loseta): ?>
                <?php $ruta_loseta = preg_replace('#/\d+$#', '', trim((string) $loseta['Ruta'], '/')) . '/' . (int) $loseta['Id']; ?>
                <a class="loseta-card" href="<?= esc(base_url($ruta_loseta), 'attr') ?>">
                    <span class="loseta-card-icon" aria-hidden="true"><i class="<?= esc($loseta['Icono'], 'attr') ?>"></i></span>
                    <h2 class="loseta-card-title"><?= esc($loseta['Nombre']) ?></h2>
                    <span class="loseta-card-pie" aria-hidden="true">
                        <span class="loseta-card-acceder">Acceder</span>
                        <span class="loseta-card-chevron"><i class="bi bi-chevron-right"></i></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php else: ?>
        <div class="losetas-empty text-center">
            <i class="bi bi-inboxes"></i>
            <p>No tiene módulos asignados. Contacte a un administrador si cree que esto es un error.</p>
        </div>
    <?php endif; ?>

    <footer class="losetas-footer text-center">
        <small class="d-block">&copy; <?= date('Y') ?> <strong>ELAEIX</strong> · Desarrollado por <strong>Datapro Analítica S.A.S.</strong></small>
        <small class="losetas-footer-uso d-block">Licenciado para Agroinversiones Hecarse S.A.S.</small>
    </footer>
</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>
<script>
    /**
     * Lógica de cierre de sesión en la vista de losetas
     */
    document.querySelector('.btn-logout').addEventListener('click', () => {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Estás a punto de cerrar tu sesión',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--app-primary').trim(),
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, cerrar sesión'
        }).then(async (result) => {
            if (result.isConfirmed) {
                const response = await fetch('<?= base_url() . 'cerrar_sesion' ?>', { method: 'POST' });

                const result = await response.json();

                if (result.success) {
                    window.location.href = '<?= base_url() ?>';
                }
            }
        });
    });
</script>
<?php if (!empty($alerta_labores)): ?>
    <script>window.LPL_AUTO = true;</script>
<?php endif; ?>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->
