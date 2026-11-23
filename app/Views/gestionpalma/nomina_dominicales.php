<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/transacciones.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/informes.css') ?>">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Dominicales</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Dominicales</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <?php if (empty($dominicales_disponible)): ?>
        <div class="card">
            <div class="card-body gp-estado-vacio text-center py-5">
                <div class="gp-medallon gp-ppto-error d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                    <i class="bi bi-database-exclamation" aria-hidden="true"></i>
                </div>
                <h6 class="mb-1">Dominicales no disponibles</h6>
                <p class="text-muted mb-0"><?= esc($mensaje_disponible ?: 'El cálculo de dominicales no está disponible.') ?></p>
            </div>
        </div>
        <?php else: ?>

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <form id="form_dominicales" class="row g-2 align-items-end" novalidate>

                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-building" aria-hidden="true"></i></span>
                            <select class="form-select" id="filtro_empresa">
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (int) $empresa['id'] === (int) $empresa_sel ? 'selected' : '' ?>><?= esc($empresa['razonSocial']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-3 col-xxl-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_domingo">Domingo</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                            <input type="date" class="form-control" id="filtro_domingo" step="7" min="2000-01-02" max="2100-12-26" value="<?= esc($domingo_sel, 'attr') ?>" aria-describedby="semana_texto" required>
                        </div>
                    </div>

                    <div class="col-12 col-md-8 offset-md-4 order-md-last">
                        <span class="gp-subtexto" id="semana_texto" aria-live="polite"></span>
                    </div>

                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-primary w-100" id="btn_calcular">
                            <i class="bi bi-calculator me-1" aria-hidden="true"></i>Calcular
                        </button>
                    </div>

                    <div class="col-12 col-md-auto ms-md-auto">
                        <?php if (! empty($puede_generar)): ?>
                        <span class="d-block" id="envoltura_generar">
                            <button type="button" class="btn btn-success w-100" id="btn_generar" disabled>
                                <i class="bi bi-lightning-charge me-1" aria-hidden="true"></i>Generar dominicales
                            </button>
                        </span>
                        <?php else: ?>
                        <span class="gp-subtexto"><i class="bi bi-eye me-1" aria-hidden="true"></i>Solo consulta: requiere permiso para registrar labores (TLA).</span>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <div id="aviso_generado"></div>

        <div class="gp-nota d-none align-items-start gap-2 mb-3" id="nota_cambio">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>Cambió la empresa o el domingo. Pulse Calcular para actualizar.</span>
        </div>

        <div class="card" id="dom_estado">
            <div class="card-body p-0" id="dom_estado_cuerpo">
                <div class="gp-estado-vacio text-center py-5">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-calendar-week" aria-hidden="true"></i>
                    </div>
                    <h6 class="mb-0">Seleccione la empresa y el domingo y pulse Calcular</h6>
                </div>
            </div>
        </div>

        <div class="d-none" id="dom_resultados">
            <div class="row row-cols-2 row-cols-md-4 g-3 mb-3" id="dom_kpis"></div>

            <div class="card">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <span class="gp-contador" id="dom_contador" aria-live="polite"></span>
                    <div class="input-group input-group-sm" style="max-width:280px">
                        <span class="input-group-text bg-light"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="dom_buscar" placeholder="Buscar por cédula o nombre" aria-label="Buscar por cédula o nombre" autocomplete="off">
                    </div>
                </div>
                <div class="card-body p-0">
                    <table id="tabla_dominicales" class="table table-hover align-middle mb-0 gp-tabla inf-tabla w-100">
                        <thead class="gp-thead">
                            <tr>
                                <th style="width:40px"><span class="visually-hidden">Reparto</span></th>
                                <th style="width:120px">Cédula</th>
                                <th>Trabajador</th>
                                <th class="text-center d-none d-md-table-cell">Días con labor</th>
                                <th>Ausencias</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th></th>
                                <th>Total</th>
                                <th></th>
                                <th class="d-none d-md-table-cell"></th>
                                <th></th>
                                <th></th>
                                <th class="inf-num" id="dom_total"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </section>

</main>
<?php echo $this->endSection(); ?>

<?php if (! empty($dominicales_disponible)): ?>
<?php echo $this->section('scripts'); ?>
<script> const BASE_URL = "<?= base_url() ?>"; </script>
<script src="<?= asset_url('public/assets/js/gestionpalma/nomina_dominicales.js') ?>"></script>
<?php echo $this->endSection(); ?>
<?php endif; ?>
