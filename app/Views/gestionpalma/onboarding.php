<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1><?= esc($nombre_loseta ?? 'Gestión Palma') ?></h1>
        <p class="text-muted mb-0">Administración de la información maestra del cultivo: fincas, lotes, labores, precios y parámetros contables y de nómina.</p>
    </div>

    <?php $rutas_en_construccion = ['gestion-palma/precios/reliquidar']; ?>

    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-info-circle"></i>
        <span>Esta loseta se está habilitando por etapas. Los módulos marcados como <em>En construcción</em> aún no tienen pantalla funcional.</span>
    </div>

    <section class="section">
        <div class="row g-3">
            <?php foreach ($sidebar as $item): ?>
                <?php
                $hijos = array_values(array_filter($submodulos, function ($sub) use ($item) {
                    return $sub['IdModuloPadre'] === $item['IdModulo'];
                }));

                $ruta = preg_replace('#/\d+$#', '', trim((string) $item['Ruta'], '/'));
                if ($ruta === '' && !empty($hijos)) {
                    $ruta = preg_replace('#/\d+$#', '', trim((string) $hijos[0]['Ruta'], '/'));
                }

                $rutas = array_filter(array_map(fn ($r) => preg_replace('#/\d+$#', '', trim((string) $r, '/')), array_merge([$item['Ruta']], array_column($hijos, 'Ruta'))));
                $enConstruccion = $rutas === [] || array_diff($rutas, $rutas_en_construccion) === [];

                $etiqueta = $ruta !== '' ? 'a' : 'div';
                ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <<?= $etiqueta ?> class="card gp-card h-100 text-decoration-none"<?= $ruta !== '' ? ' href="' . esc(base_url($ruta . '/' . $id_loseta), 'attr') . '"' : '' ?>>
                        <div class="card-body d-flex gap-3 align-items-start">
                            <div class="gp-icono d-flex align-items-center justify-content-center rounded-3">
                                <i class="<?= esc($item['Icono'], 'attr') ?>"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-dark fw-semibold"><?= esc($item['Nombre']) ?></h6>
                                <?php if (!empty($hijos)): ?>
                                    <small class="text-muted d-block mb-2"><?= esc(implode(' · ', array_column($hijos, 'Nombre'))) ?></small>
                                <?php endif; ?>
                                <?php if ($enConstruccion): ?>
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">En construcción</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </<?= $etiqueta ?>>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->
