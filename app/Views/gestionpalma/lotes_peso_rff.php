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

$lotesPorFinca = [];
foreach ($lotes as $l) {
    $lotesPorFinca[trim((string) $l['finca'])][] = [
        'codigo'      => trim((string) $l['codigo']),
        'descripcion' => $l['descripcion'],
    ];
}
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Lotes / Peso RFF</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Lotes / Peso RFF</li>
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
                            <option value="">Todos</option>
                            <?php foreach ($anios as $anio): ?>
                                <option value="<?= esc($anio['anio'], 'attr') ?>" <?= (int) $anio['anio'] === (int) $anio_sel ? 'selected' : '' ?>>
                                    <?= esc($anio['anio']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_mes">Mes</label>
                        <select class="form-select" id="filtro_mes">
                            <option value="">Todos</option>
                            <?php foreach ($meses as $numero => $nombre): ?>
                                <option value="<?= $numero ?>" <?= $numero === (int) $mes_sel ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                            <option value="13" <?= (int) $mes_sel === 13 ? 'selected' : '' ?>>Cierre del año</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_finca">Finca</label>
                        <select class="form-select" id="filtro_finca">
                            <option value="" selected>Todas</option>
                            <?php foreach ($fincas as $finca): ?>
                                <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_problemas">Problemas</label>
                        <select class="form-select" id="filtro_problemas">
                            <option value="" selected>Todos</option>
                            <option value="1">Solo con problemas</option>
                        </select>
                    </div>

                    <div class="col-md-12 col-lg-auto ms-lg-auto">
                        <button type="button" class="btn btn-success w-100" id="btn_nuevo">
                            <i class="bi bi-plus-lg me-1"></i>Nuevo
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span id="contador_pesos" class="gp-contador">
                    <i class="bi bi-speedometer2 me-1"></i><strong><?= esc($total_pesos) ?></strong> <?= (int) $total_pesos === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_pesos" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th style="width:170px">Periodo</th>
                                <th>Finca</th>
                                <th class="text-center" style="width:130px">Lote</th>
                                <th class="text-end" style="width:120px">Peso (Kg)</th>
                                <th class="text-center" style="width:130px">Origen</th>
                                <th style="width:200px">Vigencia</th>
                                <th class="text-center gp-col-sticky" style="width:120px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_pesos">
                            <?= $tabla_pesos ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <h6>Sin registros para este periodo</h6>
                    <p class="text-muted" id="gp_mensaje_vacio"></p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Registrar peso
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Peso RFF -->
    <div class="modal fade" id="modalPeso" tabindex="-1" aria-labelledby="titulo_modal_peso" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_peso">
                        <i class="bi bi-speedometer2 me-1"></i><span id="peso_titulo_texto">Nuevo peso RFF</span>
                        <span class="gp-subtexto" id="peso_subtitulo"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_peso">
                    <input type="hidden" id="peso_modo" value="crear">
                    <input type="hidden" id="peso_empresa">
                    <input type="hidden" id="peso_anio_pk">
                    <input type="hidden" id="peso_mes_pk">
                    <input type="hidden" id="peso_finca_pk">
                    <input type="hidden" id="peso_lote_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-calendar3"></i>Periodo y ubicación</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="peso_anio">Año <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="peso_anio" min="2000" max="2100" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="peso_mes">Mes <span class="text-danger">*</span></label>
                                <select class="form-select" id="peso_mes" required>
                                    <?php foreach ($meses as $numero => $nombre): ?>
                                        <option value="<?= $numero ?>"><?= $numero ?> &mdash; <?= $nombre ?></option>
                                    <?php endforeach; ?>
                                    <option value="13">13 &mdash; Cierre del año</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="peso_finca">Finca <span class="text-danger">*</span></label>
                                <select class="form-select" id="peso_finca" required>
                                    <option value="">Seleccione&hellip;</option>
                                    <?php foreach ($fincas as $finca): ?>
                                        <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['descripcion']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="peso_lote">Lote <span class="text-danger">*</span></label>
                                <select class="form-select" id="peso_lote" required>
                                    <option value="">Seleccione&hellip;</option>
                                </select>
                            </div>

                            <div class="col-12 d-none" id="peso_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>El año, el mes, la finca y el lote no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-speedometer2"></i>Peso y origen</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="peso_racimo">Peso (Kg) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-speedometer2"></i></span>
                                    <input type="number" class="form-control text-end" id="peso_racimo" step="0.001" min="0" required>
                                    <span class="input-group-text bg-light">Kg</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted d-block">Origen</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="peso_automatico">
                                    <label class="form-check-label" for="peso_automatico">Automático</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-calendar-range"></i>Vigencia</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="peso_fecha_inicial">Fecha inicial</label>
                                <input type="date" class="form-control" id="peso_fecha_inicial">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="peso_fecha_final">Fecha final</label>
                                <input type="date" class="form-control" id="peso_fecha_final">
                            </div>

                            <div class="col-12">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>Corresponden al rango de cosecha que sustenta este peso promedio. Se precargan con el primer y último día del periodo seleccionado; ajústelas solo si el rango real fue distinto.
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_peso">
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
<script>
    const BASE_URL = "<?= base_url() ?>";
    const LOTES_POR_FINCA = <?= json_encode($lotesPorFinca) ?>;
    const ANIO_SEL = <?= (int) $anio_sel ?>;
    const MES_SEL = <?= (int) $mes_sel ?>;
</script>
<script src="<?= base_url('public/assets/js/gestionpalma/lotes_peso_rff.js') ?>"></script>
<?php echo $this->endSection(); ?>
