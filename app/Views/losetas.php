<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="public/assets/css/losetas.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main class="losetas-page">
    <!-- BARRA SUPERIOR -->
    <header class="losetas-topbar">
        <div class="losetas-topbar-brand">
            <img src="public/assets/img/logo_hecarse_blanco.png" alt="Hecarse">
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
            <button type="button" class="btn btn-logout" data-bs-toggle="tooltip" data-bs-title="Cerrar sesión">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-md-inline">Cerrar sesión</span>
            </button>
        </div>
    </header>

    <!-- ENCABEZADO DE BIENVENIDA -->
    <div class="losetas-hero text-center">
        <h1 class="losetas-hero-title"><?= esc($title) ?></h1>
        <p class="losetas-hero-subtitle"><?= esc($subtitle) ?></p>
    </div>

    <!-- GRID DE MODULOS -->
    <?php if (!empty($losetas)): ?>
        <div class="losetas-grid" id="losetas">
            <?php foreach ($losetas as $loseta): ?>
                <?php $ruta_loseta = preg_replace('#/\d+$#', '', trim((string) $loseta['Ruta'], '/')) . '/' . (int) $loseta['Id']; ?>
                <div class="loseta-card"
                    role="button"
                    tabindex="0"
                    aria-label="Ingresar a <?= esc($loseta['Nombre']) ?>"
                    onclick="ingresarModulo('<?= esc($ruta_loseta) ?>')"
                    onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); ingresarModulo('<?= esc($ruta_loseta) ?>'); }">
                    <div class="loseta-card-icon">
                        <i class="<?= esc($loseta['Icono']) ?>"></i>
                    </div>
                    <h4 class="loseta-card-title"><?= esc($loseta['Nombre']) ?></h4>
                    <span class="loseta-card-link">Ingresar <i class="bi bi-arrow-right"></i></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="losetas-empty text-center">
            <i class="bi bi-inboxes"></i>
            <p>No tiene módulos asignados. Contacte a un administrador si cree que esto es un error.</p>
        </div>
    <?php endif; ?>

    <footer class="losetas-footer text-center">
        <small>&copy; <?= date('Y') ?> Hecarse. Todos los derechos reservados.</small>
    </footer>
</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>
<script>
    /**
     * Metodo para redireccionar a la ruta del modulo seleccionado.
     *
     * @param {string} ruta Ruta del modulo seleccionado.
     */
    const ingresarModulo = (ruta) => {
        window.location.href = ruta;
    }

    /**
     * Lógica de cierre de sesión en la vista de losetas
     */
    document.querySelector('.btn-logout').addEventListener('click', () => {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Estás a punto de cerrar tu sesión',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
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
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->
