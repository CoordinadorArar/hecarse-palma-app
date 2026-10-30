<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/site_config.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<?php
$claves = ['logo', 'logo_claro', 'favicon'];
$valor = fn ($k) => (string) (($config[$k] ?? '') !== '' ? $config[$k] : $defectos[$k]);
$imgs = array_combine($claves, array_map(fn ($k) => sitio_img($k), $claves));
$imgsDefecto = array_combine($claves, array_map(fn ($k) => base_url($defectos[$k]), $claves));
$ayudaLogo = 'PNG o WEBP · fondo transparente · máx. 2 MB';
$acceptLogo = '.png,.webp,image/png,image/webp';
$logos = [
    'logo' => ['Logo', 'Inicio de sesión y recuperación', $ayudaLogo, $acceptLogo, ''],
    'logo_claro' => ['Logo claro', 'Barra superior y menú principal. Se muestra sobre el color primario. Formato horizontal', $ayudaLogo, $acceptLogo, 'cs-miniatura-primario'],
    'favicon' => ['Favicon', 'Pestaña del navegador', 'PNG o ICO · fondo transparente · máx. 2 MB', '.png,.ico,image/png,image/x-icon', 'cs-miniatura-favicon'],
];
$colores = [
    'primary' => ['Primario', 'Barra superior, botones, enlaces, pestañas y menú activo'],
    'secundario' => ['Secundario', 'Textos del menú lateral y títulos'],
];
$datos = [
    'url' => base_url('admin/sitio/guardar'),
    'nombre' => $valor('nombre_app'),
    'primary' => strtolower($valor('color_primary')),
    'secundario' => strtolower($valor('color_secundario')),
    'img' => $imgs,
    'defectos' => [
        'nombre' => (string) $defectos['nombre_app'],
        'primary' => strtolower((string) $defectos['color_primary']),
        'secundario' => strtolower((string) $defectos['color_secundario']),
        'img' => $imgsDefecto,
        'propia' => array_combine($claves, array_map(fn ($k) => $valor($k) !== $defectos[$k], $claves)),
    ],
];
?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Configuración del sitio</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('admin/onboarding/' . $id_loseta), 'attr') ?>">Administración</a></li>
                <li class="breadcrumb-item active">Configuración del sitio</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="gp-nota mb-3">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Los cambios se aplican a todo el sitio y a todos los usuarios al guardar.
        </div>

        <form id="cs_form" novalidate>
            <div class="row g-3">

                <div class="col-lg-7">

                    <div class="card mb-3">
                        <div class="card-header cs-encabezado"><h6 class="gp-seccion"><i class="bi bi-type" aria-hidden="true"></i>Identidad</h6></div>
                        <div class="card-body pt-3">
                            <label for="cs_nombre" class="form-label fw-semibold mb-1">Nombre del sitio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cs_nombre" maxlength="60" required autocomplete="off" value="<?= esc($datos['nombre'], 'attr') ?>" aria-describedby="cs_nombre_ayuda">
                            <div class="invalid-feedback" data-cs-error="nombre_app"></div>
                            <span class="gp-subtexto mt-1" id="cs_nombre_ayuda">Se muestra en la pestaña del navegador y como texto alternativo del logo</span>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header cs-encabezado"><h6 class="gp-seccion"><i class="bi bi-image" aria-hidden="true"></i>Logos</h6></div>
                        <div class="card-body pt-3">
                            <div class="row g-3">
                                <?php foreach ($logos as $clave => [$titulo, $uso, $ayuda, $accept, $modificador]): ?>
                                    <div class="col-md-4">
                                        <div class="cs-logo h-100" data-cs-logo="<?= $clave ?>">
                                            <span class="fw-semibold cs-logo-titulo"><?= $titulo ?></span>
                                            <span class="gp-subtexto mb-2 cs-logo-uso"><?= $uso ?></span>
                                            <div class="cs-miniatura <?= $modificador ?>" <?= $clave === 'logo_claro' ? 'data-cs-tema' : '' ?>>
                                                <?php if ($clave === 'favicon'): ?>
                                                    <img src="<?= esc($imgs[$clave], 'attr') ?>" data-cs-src="favicon" class="cs-fav-64" alt="Favicon a 64 px">
                                                    <img src="<?= esc($imgs[$clave], 'attr') ?>" data-cs-src="favicon" class="cs-fav-16" alt="Favicon a 16 px">
                                                <?php else: ?>
                                                    <img src="<?= esc($imgs[$clave], 'attr') ?>" data-cs-src="<?= $clave ?>" alt="<?= $titulo ?> actual">
                                                <?php endif; ?>
                                            </div>
                                            <input type="file" class="d-none" id="cs_archivo_<?= $clave ?>" accept="<?= $accept ?>" tabindex="-1" aria-hidden="true">
                                            <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" data-cs-elegir="<?= $clave ?>"><i class="bi bi-upload me-1" aria-hidden="true"></i>Cambiar imagen</button>
                                            <div class="cs-logo-estado d-none" data-cs-estado>
                                                <span class="gp-subtexto font-monospace text-truncate" data-cs-archivo></span>
                                                <span class="d-flex align-items-center gap-2 mt-1">
                                                    <span class="gp-chip-fila ambar">Sin guardar</span>
                                                    <button type="button" class="btn btn-link btn-sm p-0 cs-deshacer" data-cs-deshacer="<?= $clave ?>">Deshacer</button>
                                                </span>
                                            </div>
                                            <div class="invalid-feedback" data-cs-error="<?= $clave ?>"></div>
                                            <?php if ($clave === 'favicon'): ?>
                                                <div class="gp-nota-alerta mt-2 d-none" data-cs-cuadrado><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Se recomienda una imagen cuadrada de 64×64 px</div>
                                            <?php endif; ?>
                                            <span class="gp-subtexto mt-2"><?= $ayuda ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header cs-encabezado d-flex align-items-center gap-2">
                            <h6 class="gp-seccion flex-grow-1"><i class="bi bi-palette" aria-hidden="true"></i>Colores</h6>
                            <button type="button" class="btn btn-link btn-sm p-0" id="cs_colores_defecto">Restablecer colores ELAEIX</button>
                        </div>
                        <div class="card-body pt-3">
                            <?php foreach ($colores as $clave => [$titulo, $uso]): ?>
                                <div class="cs-color" data-cs-color="<?= $clave ?>">
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <div class="me-auto">
                                            <label class="form-label fw-semibold mb-0" for="cs_hex_<?= $clave ?>"><?= $titulo ?></label>
                                            <span class="gp-subtexto"><?= $uso ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="color" class="form-control form-control-color cs-picker" value="<?= esc($datos[$clave], 'attr') ?>" aria-label="Seleccionar color <?= strtolower($titulo) ?>">
                                            <input type="text" class="form-control font-monospace cs-hex" id="cs_hex_<?= $clave ?>" maxlength="7" autocomplete="off" spellcheck="false" value="<?= esc(strtoupper($datos[$clave]), 'attr') ?>">
                                            <span class="cs-chip" data-cs-contraste></span>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback text-end" data-cs-error="color_<?= $clave ?>"></div>
                                    <div class="gp-nota-alerta mt-2 d-none" data-cs-aviso><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= $clave === 'primary' ? 'El texto blanco sobre este color puede ser difícil de leer (mínimo 4.5:1)' : 'Este color sobre fondo blanco puede ser difícil de leer (mínimo 4.5:1)' ?></div>
                                    <?php if ($clave === 'primary'): ?>
                                        <div class="d-flex align-items-center gap-2 mt-2">
                                            <span class="gp-subtexto">Tonos derivados</span>
                                            <span class="cs-tira" id="cs_tira">
                                                <?php foreach (['active' => 'Activo', 'hover' => 'Hover', 'base' => 'Base', 'border' => 'Borde', 'subtle' => 'Fondo suave'] as $tono => $nombreTono): ?>
                                                    <span class="cs-muestra" data-cs-tono="<?= $tono ?>" data-cs-nombre="<?= $nombreTono ?>"></span>
                                                <?php endforeach; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <div class="col-lg-5">
                    <div class="card mb-3 cs-preview">
                        <div class="card-header cs-encabezado"><h6 class="gp-seccion"><i class="bi bi-eye" aria-hidden="true"></i>Vista previa</h6></div>
                        <div class="card-body pt-3" data-cs-tema aria-hidden="true">
                            <div class="cs-pv-app">
                                <div class="cs-pv-barra">
                                    <img src="<?= esc($imgs['logo_claro'], 'attr') ?>" data-cs-src="logo_claro" alt="">
                                    <span class="cs-pv-usuario"><span class="cs-pv-avatar"></span>Usuario</span>
                                </div>
                                <div class="cs-pv-cuerpo">
                                    <div class="cs-pv-menu">
                                        <span class="badge bg-primary mb-2">Módulo</span>
                                        <span class="cs-pv-item activo"><i class="bi bi-grid"></i>Inicio</span>
                                        <span class="cs-pv-item"><i class="bi bi-people"></i>Usuarios</span>
                                        <span class="cs-pv-item"><i class="bi bi-gear"></i>Ajustes</span>
                                    </div>
                                    <div class="cs-pv-contenido">
                                        <span class="cs-pv-titulo">Panel principal</span>
                                        <div class="cs-pv-card">
                                            <ul class="nav nav-tabs nav-tabs-bordered cs-pv-tabs">
                                                <li class="nav-item"><span class="nav-link active">General</span></li>
                                                <li class="nav-item"><span class="nav-link">Detalle</span></li>
                                            </ul>
                                            <input type="text" class="form-control form-control-sm cs-pv-foco" value="Campo activo" readonly tabindex="-1">
                                            <span class="d-flex flex-wrap gap-1">
                                                <span class="btn btn-primary btn-sm">Guardar</span>
                                                <span class="btn btn-outline-primary btn-sm">Cancelar</span>
                                            </span>
                                            <span class="d-flex align-items-center gap-2">
                                                <a class="cs-pv-enlace">Ver detalle</a>
                                                <span class="badge bg-primary">Nuevo</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mt-1">
                                <div class="col-6">
                                    <div class="cs-pv-mini">
                                        <span class="cs-pv-mini-titulo">Inicio de sesión</span>
                                        <img src="<?= esc($imgs['logo'], 'attr') ?>" data-cs-src="logo" class="cs-pv-logo" alt="">
                                        <span class="btn btn-primary btn-sm w-100">Ingresar</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="cs-pv-mini">
                                        <span class="cs-pv-mini-titulo">Pestaña del navegador</span>
                                        <span class="cs-pv-pestana">
                                            <img src="<?= esc($imgs['favicon'], 'attr') ?>" data-cs-src="favicon" alt="">
                                            <span class="text-truncate" id="cs_pv_nombre"><?= esc($datos['nombre']) ?></span>
                                            <i class="bi bi-x-lg"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="cs-acciones sticky-bottom p-3 d-flex flex-wrap align-items-center gap-2">
                <span class="cs-pendientes d-none" id="cs_pendientes" aria-live="polite"><i class="bi bi-circle-fill me-1" aria-hidden="true"></i><span></span></span>
                <div class="ms-auto d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="cs_defectos"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer valores por defecto</button>
                    <button type="button" class="btn btn-outline-secondary" id="cs_descartar" disabled>Descartar cambios</button>
                    <button type="submit" class="btn btn-primary" id="cs_guardar" disabled><i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar cambios</button>
                </div>
            </div>
        </form>

    </section>
</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    window.CS_CONFIG = <?= json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= asset_url('public/assets/js/admin/site.js') ?>"></script>
<?php echo $this->endSection(); ?>
