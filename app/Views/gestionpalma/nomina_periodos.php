<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<?php
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Periodos de nómina</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Periodos de nómina</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (int) $empresa['id'] === (int) $empresa_sel ? 'selected' : '' ?>>
                                    <?= esc($empresa['razonSocial']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_anio">Año</label>
                        <select class="form-select" id="filtro_anio">
                            <option value="">Todos los años</option>
                            <?php foreach ($anios as $anio): ?>
                                <option value="<?= esc($anio, 'attr') ?>" <?= (int) $anio === (int) $anio_sel ? 'selected' : '' ?>>
                                    <?= esc($anio) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="filtro_busqueda"
                                placeholder="Buscar por mes, número de periodo o nombre" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary flex-fill" id="btn_buscar">
                                <i class="bi bi-funnel me-1"></i>Buscar
                            </button>
                            <button type="button" class="btn btn-success flex-fill" id="btn_nuevo">
                                <i class="bi bi-plus-lg me-1"></i>Nuevo
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span id="contador_periodos" class="gp-contador">
                    <i class="bi bi-list-ol me-1"></i><strong><?= esc($total_periodos) ?></strong> <?= (int) $total_periodos === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_periodos_nomina" class="table table-striped table-hover align-middle mb-0 gp-tabla gp-tabla-ancha" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:64px">Año</th>
                                <th class="text-nowrap" style="width:130px">Mes</th>
                                <th class="text-center" style="width:60px">No.</th>
                                <th class="text-center" style="width:110px">Tipo</th>
                                <th class="text-center" style="width:60px">Días</th>
                                <th style="width:180px">Vigencia</th>
                                <th style="width:150px">Corte / Pago</th>
                                <th>Periodo</th>
                                <th class="text-center" style="width:110px">Indicadores</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:120px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_periodos">
                            <?= $tabla_periodos ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-calendar2-week"></i>
                    </div>
                    <h6>No hay periodos de nómina para este filtro</h6>
                    <p class="text-muted">Ajuste los filtros o cree un nuevo periodo.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo periodo
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Periodo de nómina -->
    <div class="modal fade" id="modalPeriodoNomina" tabindex="-1" aria-labelledby="titulo_modal_periodo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_periodo">
                        <i class="bi bi-calendar-week me-1"></i>Nuevo periodo de nómina
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_periodo">
                    <input type="hidden" id="periodo_modo" value="crear">
                    <input type="hidden" id="periodo_empresa_pk">
                    <input type="hidden" id="periodo_anio_pk">
                    <input type="hidden" id="periodo_mes_pk">
                    <input type="hidden" id="periodo_no_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-calendar-week"></i>Identificación del periodo</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="periodo_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_anio">Año <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar"></i></span>
                                    <input type="number" class="form-control" id="periodo_anio" min="1900" max="2999" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_mes">Mes <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar-month"></i></span>
                                    <select class="form-select" id="periodo_mes" required>
                                        <?php foreach ($meses as $numero => $nombre): ?>
                                            <option value="<?= $numero ?>"><?= $numero ?> &mdash; <?= $nombre ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_no">No. periodo <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="number" class="form-control" id="periodo_no" min="1" required>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="periodo_aviso_llave">
                                <div class="form-text text-muted mt-0">
                                    <i class="bi bi-info-circle me-1"></i>La empresa, el año, el mes y el número del periodo no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-calendar-range"></i>Fechas y configuración</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_tipo">Tipo nómina <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-clock-history"></i></span>
                                    <select class="form-select" id="periodo_tipo" required>
                                        <?php foreach (($tipos_nomina[$empresa_sel] ?? []) as $tipo): ?>
                                            <option value="<?= esc(trim((string) $tipo['codigo']), 'attr') ?>">
                                                <?= esc(trim((string) $tipo['codigo'])) ?> &mdash; <?= esc(mb_convert_case(mb_strtolower(trim((string) $tipo['descripcion'])), MB_CASE_TITLE, 'UTF-8')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_dias">Días nómina</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-123"></i></span>
                                    <input type="number" class="form-control" id="periodo_dias" min="1" max="31">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_nombre">Nombre del periodo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="periodo_nombre" maxlength="250">
                                </div>
                                <div class="form-text">Opcional. Si se deja vacío se identifica por año, mes y número.</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_fecha_inicial">Fecha inicial</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar-event"></i></span>
                                    <input type="date" class="form-control" id="periodo_fecha_inicial">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_fecha_final">Fecha final</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar-check"></i></span>
                                    <input type="date" class="form-control" id="periodo_fecha_final">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_fecha_corte">Fecha de corte</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-scissors"></i></span>
                                    <input type="date" class="form-control" id="periodo_fecha_corte">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="periodo_fecha_pago">Fecha de pago</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-cash-coin"></i></span>
                                    <input type="date" class="form-control" id="periodo_fecha_pago">
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-toggles"></i>Indicadores</h6>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="periodo_agronomico">
                                        <label class="form-check-label" for="periodo_agronomico">Agronómico</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="periodo_ejecuta_labores">
                                        <label class="form-check-label" for="periodo_ejecuta_labores">Ejecuta labores</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="periodo_cerrado">
                                        <label class="form-check-label" for="periodo_cerrado">Periodo cerrado</label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_periodo">
                            <i class="bi bi-floppy me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<?php echo $this->section('scripts'); ?>
<script> const BASE_URL = "<?= base_url() ?>"; const TIPOS_NOMINA = <?= json_encode($tipos_nomina) ?>; </script>
<script src="<?= base_url('public/assets/js/gestionpalma/nomina_periodos.js') ?>"></script>
<?php echo $this->endSection(); ?>
