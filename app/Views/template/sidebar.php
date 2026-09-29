<?php
$url_actual = trim(implode('/', service('uri')->getSegments()), '/');

$normalizarRuta = function ($ruta) {
    return preg_replace('#/\d+$#', '', trim((string) $ruta, '/'));
};

$esActivo = function ($ruta) use ($url_actual) {
    return $ruta !== '' && ($url_actual === $ruta || strpos($url_actual, $ruta . '/') === 0);
};
?>

<?php echo $this->section('sidebar'); ?>

<aside id="sidebar" class="sidebar">

    <div class="p-2">
        <!-- Título del módulo -->
        <div class="text-center mb-3">
            <span class="badge bg-primary text-white px-3 py-2 fs-6 rounded-pill w-100" id="nombreModulo">
                <?= $nombre_loseta ?>
            </span>
        </div>
        <!-- Botón menú principal -->
        <a href="<?= base_url('losetas') ?>" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>Menú Principal</span>
        </a>
    </div>

    <hr class="mx-3 my-2">

    <ul class="sidebar-nav" id="sidebar-nav">
        <?php foreach ($sidebar as $item): ?>
            <?php
            $submodulosRelacionados = array_filter($submodulos, function ($sub) use ($item) {
                return $sub['IdModuloPadre'] === $item['IdModulo'];
            });

            $rutaItem   = $normalizarRuta($item['Ruta']);
            $hrefItem   = base_url($rutaItem . '/' . $id_loseta);
            $activoItem = $esActivo($rutaItem);

            $hijoActivo = false;
            foreach ($submodulosRelacionados as $sub) {
                if ($esActivo($normalizarRuta($sub['Ruta']))) {
                    $hijoActivo = true;
                    break;
                }
            }
            ?>
            <li class="nav-item">
                <?php if (!empty($submodulosRelacionados)): ?>
                    <!-- Opción con submódulos -->
                    <a class="nav-link<?= ($hijoActivo || $activoItem) ? '' : ' collapsed' ?>" data-bs-toggle="collapse"
                        href="#submenu-<?= esc($item['IdModulo'], 'attr') ?>" role="button"
                        aria-expanded="<?= ($hijoActivo || $activoItem) ? 'true' : 'false' ?>"
                        aria-controls="submenu-<?= esc($item['IdModulo'], 'attr') ?>">
                        <i class="<?= esc($item['Icono'], 'attr') ?>"></i>
                        <span><?= esc($item['Nombre']) ?></span>
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <ul class="nav-content collapse<?= ($hijoActivo || $activoItem) ? ' show' : '' ?>"
                        id="submenu-<?= esc($item['IdModulo'], 'attr') ?>" data-bs-parent="#sidebar-nav">
                        <?php foreach ($submodulosRelacionados as $sub): ?>
                            <?php
                            $rutaSub   = $normalizarRuta($sub['Ruta']);
                            $activoSub = $esActivo($rutaSub);
                            ?>
                            <li>
                                <a class="<?= $activoSub ? 'active' : '' ?>"
                                    href="<?= esc(base_url($rutaSub . '/' . $id_loseta), 'attr') ?>">
                                    <i class="<?= esc($sub['Icono'], 'attr') ?>"></i>
                                    <?= esc($sub['Nombre']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <!-- Opción sin submódulos -->
                    <?php $etiquetaItem = $rutaItem !== '' ? 'a' : 'span'; ?>
                    <<?= $etiquetaItem ?> class="nav-link<?= $activoItem ? '' : ' collapsed' ?><?= $rutaItem === '' ? ' nav-link-pendiente' : '' ?>"<?= $rutaItem !== '' ? ' href="' . esc($hrefItem, 'attr') . '"' : ' title="Sin submódulos asignados"' ?>>
                        <i class="<?= esc($item['Icono'], 'attr') ?>"></i>
                        <span><?= esc($item['Nombre']) ?></span>
                    </<?= $etiquetaItem ?>>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="mt-4 text-center">
        <small class="text-muted" style="font-size: .7em;">&copy; 2026 Derechos reservados Hecarse.</small>
    </div>
</aside>

<?php echo $this->endSection(); ?>
