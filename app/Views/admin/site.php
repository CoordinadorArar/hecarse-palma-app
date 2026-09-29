<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>


<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Configuración del Sitio</h1>
    </div>

    <section class="section">

        <form id="formulario_administracion_sitio" enctype="multipart/form-data">
            <div class="row">

                <!-- Columna izquierda: Identidad -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Identidad de la Marca</h5>

                            <!-- Nombre de la aplicación -->
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Nombre del Sitio</label>
                                <div class="col-sm-9">
                                    <input
                                        type="text"
                                        name="nombre_sitio"
                                        class="form-control"
                                        placeholder="Ej: MiEmpresa Admin"
                                        value="<?= esc($config['nombre_sitio'] ?? '') ?>"
                                        required>                                    
                                </div>
                            </div>

                            <!-- Logo -->
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Logo</label>
                                <div class="col-sm-9">
                                    <?php if (!empty($config['logo'])): ?>
                                        <div class="mb-2">
                                            <img src="<?= base_url('public/uploads/config/' . $config['logo']) ?>"
                                                alt="Logo actual"
                                                style="max-height: 60px; border: 1px solid #dee2e6; border-radius: 6px; padding: 4px; background: #fff;">
                                            <span class="ms-2 text-muted small">Logo actual</span>
                                        </div>
                                    <?php endif; ?>
                                    <input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                                    <div class="form-text">PNG, SVG o JPG. Recomendado: fondo transparente. Máx. 2MB.</div>
                                </div>
                            </div>

                            <!-- Favicon -->
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Favicon</label>
                                <div class="col-sm-9">
                                    <?php if (!empty($config['favicon'])): ?>
                                        <div class="mb-2">
                                            <img src="<?= base_url('public/uploads/config/' . $config['favicon']) ?>"
                                                alt="Favicon actual"
                                                style="width: 32px; height: 32px; border: 1px solid #dee2e6; border-radius: 4px; padding: 2px; background: #fff;">
                                            <span class="ms-2 text-muted small">Favicon actual</span>
                                        </div>
                                    <?php endif; ?>
                                    <input class="form-control" type="file" name="favicon" accept="image/png,image/x-icon,image/svg+xml">
                                    <div class="form-text">ICO, PNG o SVG. Recomendado: 32x32 px.</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Columna derecha: Colores -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Colores del Tema</h5>

                            <!-- Color primario -->
                            <div class="row mb-3">
                                <label class="col-sm-4 col-form-label">Color Primario</label>
                                <div class="col-sm-8">
                                    <div class="d-flex align-items-center gap-2">
                                        <input
                                            type="color"
                                            name="color_primary"
                                            id="colorPrimary"
                                            class="form-control form-control-color"
                                            value="<?= esc($config['color_primary'] ?? '#4154f1') ?>"
                                            title="Color principal de la aplicación">
                                        <input
                                            type="text"
                                            id="colorPrimaryHex"
                                            class="form-control font-monospace"
                                            placeholder="#4154f1"
                                            value="<?= esc($config['color_primary'] ?? '#4154f1') ?>"
                                            maxlength="7"
                                            style="max-width: 110px;">
                                    </div>
                                    <div class="form-text">Define los colores principales de la aplicación.</div>
                                </div>
                            </div>

                            <!-- Preview en tiempo real -->
                            <div class="row mb-4">
                                <label class="col-sm-4 col-form-label">Vista Previa</label>
                                <div class="col-sm-8">
                                    <div id="colorPreview" class="p-3 rounded" style="background-color: <?= esc($config['color_primary'] ?? '#4154f1') ?>1a; border: 1px solid <?= esc($config['color_primary'] ?? '#4154f1') ?>40;">
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <button type="button" class="btn btn-sm" id="prevBtnPrimary"
                                                style="background-color: <?= esc($config['color_primary'] ?? '#4154f1') ?>; color: #fff; border: none;">
                                                Botón Primary
                                            </button>
                                            <button type="button" class="btn btn-sm" id="prevBtnOutline"
                                                style="background: transparent; color: <?= esc($config['color_primary'] ?? '#4154f1') ?>; border: 1px solid <?= esc($config['color_primary'] ?? '#4154f1') ?>;">
                                                Outline
                                            </button>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <div id="prevSwatchDark" class="rounded" style="width:28px;height:28px;background-color:<?= esc($config['color_primary'] ?? '#4154f1') ?>;"></div>
                                            <div id="prevSwatchLight" class="rounded" style="width:28px;height:28px;"></div>
                                            <div id="prevSwatchLighter" class="rounded" style="width:28px;height:28px;"></div>
                                        </div>
                                        <div class="mt-2">
                                            <small id="prevColorLabel" class="text-muted font-monospace"></small>
                                        </div>
                                    </div>                                    
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- Botones de acción -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="p-2 d-flex justify-content-end gap-2">
                            <a href="<?= base_url('/admin/sitio/1') ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-floppy me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </form>

    </section>
    </div>
</main>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>

<script>
    (function() {
        const colorPicker = document.getElementById('colorPrimary');
        const colorHex = document.getElementById('colorPrimaryHex');

        // Helpers para manipular el color HEX
        function hexToRgb(hex) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return {
                r,
                g,
                b
            };
        }

        function lighten(hex, amount) {
            // amount: 0 = sin cambio, 1 = blanco
            const {
                r,
                g,
                b
            } = hexToRgb(hex);
            const nr = Math.round(r + (255 - r) * amount);
            const ng = Math.round(g + (255 - g) * amount);
            const nb = Math.round(b + (255 - b) * amount);
            return '#' + [nr, ng, nb].map(v => v.toString(16).padStart(2, '0')).join('');
        }

        function updatePreview(hex) {
            if (!/^#[0-9a-fA-F]{6}$/.test(hex)) return;

            const light = lighten(hex, 0.55);
            const lighter = lighten(hex, 0.90);

            document.getElementById('prevBtnPrimary').style.backgroundColor = hex;
            document.getElementById('prevBtnOutline').style.color = hex;
            document.getElementById('prevBtnOutline').style.borderColor = hex;
            document.getElementById('prevSwatchDark').style.backgroundColor = hex;
            document.getElementById('prevSwatchLight').style.backgroundColor = light;
            document.getElementById('prevSwatchLighter').style.backgroundColor = lighter;
            document.getElementById('prevColorLabel').textContent = hex + '  →  ' + light + '  →  ' + lighter;
            document.getElementById('colorPreview').style.backgroundColor = lighter;
            document.getElementById('colorPreview').style.borderColor = hex + '40';
        }

        // Sincronizar picker → texto
        colorPicker.addEventListener('input', function() {
            colorHex.value = this.value;
            updatePreview(this.value);
        });

        // Sincronizar texto → picker
        colorHex.addEventListener('input', function() {
            const val = this.value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                colorPicker.value = val;
                updatePreview(val);
            }
        });

        // Init preview con el valor cargado
        updatePreview(colorPicker.value);
    })();
</script>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->