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
$seccionesPorFinca = [];
foreach ($secciones as $s) {
    $seccionesPorFinca[trim((string) $s['finca'])][] = [
        'codigo'      => trim((string) $s['codigo']),
        'descripcion' => $s['descripcion'],
    ];
}
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Lotes / Registro</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Lotes / Registro</li>
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
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_estado">Estado</label>
                        <select class="form-select" id="filtro_estado">
                            <option value="" selected>Todos</option>
                            <option value="1">Activos</option>
                            <option value="0">Inactivos</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_descuadre">Descuadre</label>
                        <select class="form-select" id="filtro_descuadre">
                            <option value="" selected>Todos</option>
                            <option value="1">Con descuadre</option>
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
                <span id="contador_lotes" class="gp-contador">
                    <i class="bi bi-hash me-1"></i><strong><?= esc($total_lotes) ?></strong> <?= (int) $total_lotes === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_lotes" class="table table-striped table-hover align-middle mb-0 gp-tabla gp-tabla-ancha" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th style="width:130px">Código</th>
                                <th>Descripción</th>
                                <th style="width:190px">Finca / Sección</th>
                                <th class="text-center" style="width:120px">Variedad</th>
                                <th class="text-end" style="width:150px">Palmas</th>
                                <th class="text-end" style="width:90px">Líneas</th>
                                <th class="text-end" style="width:150px">Hectáreas</th>
                                <th style="width:160px">Densidad / Distancia</th>
                                <th class="text-center" style="width:130px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:170px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_lotes">
                            <?= $tabla_lotes ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-hash"></i>
                    </div>
                    <h6>No se encontraron lotes</h6>
                    <p class="text-muted">Ajuste los filtros de finca, estado o descuadre, o registre un nuevo lote.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo lote
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Lote -->
    <div class="modal fade" id="modalLote" tabindex="-1" aria-labelledby="titulo_modal_lote" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_lote">
                        <i class="bi bi-hash me-1"></i>Nuevo lote
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_lote">
                    <input type="hidden" id="lote_modo" value="crear">
                    <input type="hidden" id="lote_empresa_pk">
                    <input type="hidden" id="lote_finca_pk">
                    <input type="hidden" id="lote_codigo_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-hash"></i>Identificación</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="lote_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_finca">Finca <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-tree"></i></span>
                                    <select class="form-select" id="lote_finca" required>
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($fincas as $finca): ?>
                                            <option value="<?= esc($finca['codigo'], 'attr') ?>"><?= esc($finca['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control font-monospace" id="lote_codigo" maxlength="50" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_numero">Número</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">N.°</span>
                                    <input type="number" class="form-control" id="lote_numero" min="0">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_letra">Letra</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-alphabet"></i></span>
                                    <input type="text" class="form-control" id="lote_letra" maxlength="50" autocomplete="off">
                                </div>
                            </div>

                            <div class="col-12 d-none" id="lote_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>La empresa, el código y la finca no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-card-text"></i>Descripción y clasificación</h6>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="lote_descripcion" maxlength="550" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_variedad">Variedad <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-flower1"></i></span>
                                    <select class="form-select" id="lote_variedad" required>
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($variedades as $variedad): ?>
                                            <option value="<?= esc($variedad['codigo'], 'attr') ?>"><?= esc($variedad['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_ccosto">Centro de costo <span class="text-danger">*</span></label>
                                <div class="input-group gp-select-buscador">
                                    <span class="input-group-text bg-light"><i class="bi bi-diagram-3"></i></span>
                                    <select class="form-select" id="lote_ccosto" required>
                                        <option value=""></option>
                                        <?php foreach ($centros_costo as $ccosto): ?>
                                            <option value="<?= esc($ccosto['codigo'], 'attr') ?>"><?= esc($ccosto['codigo']) ?> &mdash; <?= esc($ccosto['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_anio_siembra">Año de siembra <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar"></i></span>
                                    <input type="number" class="form-control" id="lote_anio_siembra" min="1900" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="lote_maneja_seccion">
                                    <label class="form-check-label" for="lote_maneja_seccion">Maneja sección</label>
                                </div>
                            </div>

                            <div class="col-md-6 d-none" id="lote_col_seccion">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_seccion">Sección <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-grid-1x2"></i></span>
                                    <select class="form-select" id="lote_seccion">
                                        <option value="">Seleccione&hellip;</option>
                                    </select>
                                </div>
                                <div class="form-text d-none" id="lote_seccion_vacio">
                                    <i class="bi bi-info-circle me-1"></i>Esta finca aún no tiene secciones registradas. Puede crearlas en Gestión Palma &rsaquo; Secciones.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-diagram-3"></i>Palmas y líneas</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_palmas_brutas">Palmas brutas <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-flower1"></i></span>
                                    <input type="number" class="form-control text-end" id="lote_palmas_brutas" min="0" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_palmas_produccion">Palmas producción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-flower1"></i></span>
                                    <input type="number" class="form-control text-end" id="lote_palmas_produccion" min="0" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_nolineas">No. de líneas <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-list-ol"></i></span>
                                    <input type="number" class="form-control text-end" id="lote_nolineas" min="0" required>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="lote_alerta_descuadre">
                                <div class="gp-nota-alerta"></div>
                            </div>

                            <div class="col-12 text-end">
                                <button type="button" class="btn btn-outline-success btn-sm" id="btn_administrar_lineas" disabled title="Guarde el lote primero">
                                    <i class="bi bi-list-ol me-1"></i>Administrar líneas
                                </button>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-rulers"></i>Medidas y densidad</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_hbrutas">Hectáreas brutas</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-rulers"></i></span>
                                    <input type="number" class="form-control text-end" id="lote_hbrutas" step="0.01" min="0">
                                    <span class="input-group-text bg-light">ha</span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_hnetas">Hectáreas netas</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-rulers"></i></span>
                                    <input type="number" class="form-control text-end" id="lote_hnetas" step="0.01" min="0">
                                    <span class="input-group-text bg-light">ha</span>
                                    <span class="input-group-text bg-light d-none" id="lote_hnetas_calculo" style="color:#2f7a4b" title="Calculado a partir de la distancia de siembra. Puede corregirlo.">
                                        <i class="bi bi-magic"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_dsiembra">Distancia de siembra</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-arrows-angle-expand"></i></span>
                                    <input type="number" class="form-control text-end" id="lote_dsiembra" step="0.01" min="0">
                                    <span class="input-group-text bg-light">m</span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="lote_densidad">Densidad</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-grid-3x3"></i></span>
                                    <input type="number" class="form-control text-end" id="lote_densidad" step="1" min="0">
                                    <span class="input-group-text bg-light">palma/ha</span>
                                    <span class="input-group-text bg-light d-none" id="lote_densidad_calculo" style="color:#2f7a4b" title="Calculado a partir de la distancia de siembra. Puede corregirlo.">
                                        <i class="bi bi-magic"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-droplet"></i>Canales de agua</h6>
                            </div>

                            <div class="col-12">
                                <div class="gp-panel-opcion mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <span class="gp-subtexto mb-0">Canales de riego o drenaje asociados al lote.</span>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btn_agregar_canal">
                                        <i class="bi bi-plus-lg me-1"></i>Agregar canal
                                    </button>
                                </div>
                                <div style="max-height:180px;overflow-y:auto">
                                    <table class="table table-sm gp-tabla mb-0">
                                        <thead class="gp-thead">
                                            <tr>
                                                <th>Tipo de canal</th>
                                                <th class="text-end" style="width:140px">Metros</th>
                                                <th style="width:50px"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="lote_canales_cuerpo"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-toggle-on"></i>Estado</h6>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="lote_activo" checked>
                                        <label class="form-check-label" for="lote_activo">Activo</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="lote_desarrollo">
                                        <label class="form-check-label" for="lote_desarrollo">En desarrollo</label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_lote">
                            <i class="bi bi-floppy me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Líneas del lote -->
    <div class="modal fade" id="modalLoteLineas" tabindex="-1" aria-labelledby="titulo_modal_lineas" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_lineas">
                        <i class="bi bi-list-ol me-1"></i>Líneas del lote
                        <span class="gp-subtexto" id="lineas_lote_subtitulo"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">

                    <form id="formulario_linea" class="row g-2 align-items-end mb-3">
                        <input type="hidden" id="lineas_modo" value="crear">
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="lineas_linea">Línea <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="lineas_linea" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="lineas_palmas">No. palmas <span class="text-danger">*</span></label>
                            <input type="number" class="form-control text-end" id="lineas_palmas" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="lineas_erradicada">
                                <label class="form-check-label" for="lineas_erradicada">Palma erradicada</label>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill" id="btn_guardar_linea">
                                <i class="bi bi-plus-lg me-1"></i>Agregar
                            </button>
                            <button type="button" class="btn btn-outline-secondary d-none" id="btn_cancelar_linea" title="Cancelar edición">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </form>

                    <div id="lineas_estado_vacio" class="gp-estado-vacio text-center py-4 d-none">
                        <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width:64px;height:64px">
                            <i class="bi bi-list-ol" style="font-size:28px"></i>
                        </div>
                        <p class="text-muted mb-2">Este lote no tiene líneas registradas.</p>
                        <button type="button" class="btn btn-success btn-sm" id="btn_lineas_vacio_agregar">
                            <i class="bi bi-plus-lg me-1"></i>Agregar línea
                        </button>
                    </div>

                    <div class="table-responsive" id="lineas_contenedor_tabla">
                        <table id="tabla_lote_lineas" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                            <thead class="gp-thead">
                                <tr>
                                    <th class="text-end" style="width:100px">Línea</th>
                                    <th class="text-end" style="width:120px">Palmas</th>
                                    <th class="text-center" style="width:130px">Erradicada</th>
                                    <th class="text-center" style="width:110px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_tabla_lineas"></tbody>
                        </table>
                    </div>

                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const TIPOS_CANAL = <?= json_encode($tipos_canal) ?>;
    const SECCIONES_POR_FINCA = <?= json_encode($seccionesPorFinca) ?>;
</script>
<script src="<?= base_url('public/assets/js/gestionpalma/lotes_registro.js') ?>"></script>
<?php echo $this->endSection(); ?>
