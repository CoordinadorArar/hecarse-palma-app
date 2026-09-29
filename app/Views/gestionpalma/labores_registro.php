<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/gestionpalma.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Labores / Registro</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Labores / Registro</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="card mb-3 gp-filtros">
            <div class="card-body py-3 px-4">
                <div class="row g-2 align-items-end">

                    <div class="col-md-2">
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
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_grupo">Grupo</label>
                        <select class="form-select" id="filtro_grupo">
                            <option value="" selected>Todos los grupos</option>
                            <?php foreach ($grupos as $grupo): ?>
                                <option value="<?= esc($grupo['codigo'], 'attr') ?>"><?= esc($grupo['descripcion']) ?></option>
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

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="filtro_busqueda"
                                placeholder="Buscar por código o descripción" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-12 col-lg-auto ms-lg-auto">
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
                <span id="contador_labores" class="gp-contador">
                    <i class="bi bi-clipboard-check me-1"></i><strong><?= esc($total_labores) ?></strong> <?= (int) $total_labores === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_labores" class="table table-striped table-hover align-middle mb-0 gp-tabla gp-tabla-ancha" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th style="width:110px">Código</th>
                                <th>Descripción</th>
                                <th style="width:140px">Grupo</th>
                                <th style="width:220px">Concepto</th>
                                <th class="text-center" style="width:90px">Unidad</th>
                                <th class="text-end" style="width:80px">Ciclos</th>
                                <th class="text-end" style="width:90px" title="Rendimiento">Tarea</th>
                                <th class="text-center" style="width:90px">Signo</th>
                                <th class="text-center" style="width:100px">Uso</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:150px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_labores">
                            <?= $tabla_labores ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <h6>No se encontraron labores</h6>
                    <p class="text-muted">Ajuste los filtros de grupo, estado o la búsqueda, o registre una nueva labor.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nueva labor
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Labor -->
    <div class="modal fade" id="modalLabor" tabindex="-1" aria-labelledby="titulo_modal_labor" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_labor">
                        <i class="bi bi-clipboard-check me-1"></i>Nueva labor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_labor">
                    <input type="hidden" id="labor_modo" value="crear">
                    <input type="hidden" id="labor_empresa_pk">
                    <input type="hidden" id="labor_codigo_pk">
                    <input type="hidden" id="labor_concepto_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-clipboard-check"></i>Identificación</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="labor_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control font-monospace" id="labor_codigo" maxlength="20" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_concepto">Concepto <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-cash-coin"></i></span>
                                    <select class="form-select" id="labor_concepto" required>
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($conceptos as $concepto): ?>
                                            <option value="<?= esc($concepto['codigo'], 'attr') ?>"><?= esc($concepto['codigo']) ?> &mdash; <?= esc($concepto['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="labor_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>La empresa, el código y el concepto de nómina no se pueden modificar. Si esta labor debe usar otro concepto, cree una labor nueva.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-card-text"></i>Descripción</h6>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="labor_descripcion" maxlength="200" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_des_corta">Descripción corta</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-heading"></i></span>
                                    <input type="text" class="form-control" id="labor_des_corta" maxlength="50" autocomplete="off">
                                </div>
                                <div class="form-text">Se autocompleta con la descripción (primeros 50 caracteres). Puede escribirla manualmente.</div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-tags"></i>Clasificación</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_grupo">Grupo <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-collection"></i></span>
                                    <select class="form-select" id="labor_grupo" required>
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($grupos as $grupo): ?>
                                            <option value="<?= esc($grupo['codigo'], 'attr') ?>"><?= esc($grupo['codigo']) ?> &mdash; <?= esc($grupo['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_umedida">Unidad de medida <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-rulers"></i></span>
                                    <select class="form-select" id="labor_umedida" required>
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($unidades_medida as $unidad): ?>
                                            <option value="<?= esc($unidad['codigo'], 'attr') ?>"><?= esc($unidad['desCorta']) ?> &mdash; <?= esc($unidad['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_clase">Clase de labor</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-diagram-3"></i></span>
                                    <select class="form-select" id="labor_clase">
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($clases_labor as $valor => $etiqueta): ?>
                                            <option value="<?= esc($valor, 'attr') ?>"><?= esc($etiqueta) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_equivalencia">Equivalencia</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-arrow-left-right"></i></span>
                                    <input type="text" class="form-control" id="labor_equivalencia" maxlength="20" autocomplete="off">
                                </div>
                                <div class="form-text">Código alterno de concepto de nómina. Opcional.</div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-calculator"></i>Rendimiento y cálculo</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_ciclos">Ciclos (días)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-arrow-repeat"></i></span>
                                    <input type="number" class="form-control text-end" id="labor_ciclos" min="0" step="1">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_tarea">Rendimiento</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-speedometer2"></i></span>
                                    <input type="number" class="form-control text-end" id="labor_tarea" min="0" step="0.01">
                                </div>
                                <div class="form-text">Se muestra como «Tarea» en la grilla.</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_naturaleza">Signo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-plus-slash-minus"></i></span>
                                    <select class="form-select" id="labor_naturaleza">
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($naturalezas as $valor => $etiqueta): ?>
                                            <option value="<?= esc($valor, 'attr') ?>"><?= esc($etiqueta) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_tipo_aplicacion">Tipo de aplicación</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-bullseye"></i></span>
                                    <select class="form-select" id="labor_tipo_aplicacion">
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($tipos_aplicacion as $valor => $etiqueta): ?>
                                            <option value="<?= esc($valor, 'attr') ?>"><?= esc($etiqueta) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-calendar-range"></i>Rango de siembra</h6>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="labor_maneja_rango">
                                    <label class="form-check-label" for="labor_maneja_rango">Maneja rango de siembra</label>
                                </div>
                            </div>

                            <div class="col-md-3 d-none" id="labor_col_anio_desde">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_anio_desde">Año desde</label>
                                <input type="number" class="form-control" id="labor_anio_desde" min="1900">
                            </div>

                            <div class="col-md-3 d-none" id="labor_col_anio_hasta">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_anio_hasta">Año hasta</label>
                                <input type="number" class="form-control" id="labor_anio_hasta" min="1900">
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-sliders"></i>Qué maneja esta labor</h6>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-subseccion"><i class="bi bi-rulers"></i>Dimensiones que registra</h6>
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_fecha">
                                        <label class="form-check-label" for="labor_maneja_fecha">Maneja Fecha</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_lote">
                                        <label class="form-check-label" for="labor_maneja_lote">Maneja Lote</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_linea">
                                        <label class="form-check-label" for="labor_maneja_linea">Maneja Línea</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_palma">
                                        <label class="form-check-label" for="labor_maneja_palma">Maneja Palma</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_racimo">
                                        <label class="form-check-label" for="labor_maneja_racimo">Maneja Racimos</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_caracteristica">
                                        <label class="form-check-label" for="labor_maneja_caracteristica">Maneja Característica</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-subseccion"><i class="bi bi-people"></i>Jornales y cálculo</h6>
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_jornal">
                                        <label class="form-check-label" for="labor_maneja_jornal">Maneja Jornales</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_calcula_jornal">
                                        <label class="form-check-label" for="labor_calcula_jornal">Calcula jornales</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_decimal">
                                        <label class="form-check-label" for="labor_maneja_decimal">Maneja Decimal</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-toggle-on"></i>Estado</h6>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_activo" checked>
                                        <label class="form-check-label" for="labor_activo">Activo</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_no_prestacional">
                                        <label class="form-check-label" for="labor_no_prestacional">No prestacional</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-archive"></i>Campos heredados sin uso actual</h6>
                            </div>

                            <div class="col-12">
                                <div class="gp-nota-alerta">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Estos campos existen en el sistema anterior; ninguna de las labores activas los usa hoy. Se incluyen por compatibilidad.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="labor_maneja_canal">
                                    <label class="form-check-label" for="labor_maneja_canal">Maneja Canal</label>
                                </div>
                            </div>

                            <div class="col-md-4 d-none" id="labor_col_tipo_canal">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_tipo_canal">Tipo de canal</label>
                                <select class="form-select" id="labor_tipo_canal">
                                    <option value="">Seleccione&hellip;</option>
                                    <?php foreach ($tipos_canal as $canal): ?>
                                        <option value="<?= esc($canal['codigo'], 'attr') ?>"><?= esc($canal['codigo']) ?> &mdash; <?= esc($canal['descripcion']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="labor_impuesto">
                                    <label class="form-check-label" for="labor_impuesto">Impuesto</label>
                                </div>
                            </div>

                            <div class="col-md-4 d-none" id="labor_col_grupo_ir">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="labor_grupo_ir">Grupo Impuesto</label>
                                <input type="text" class="form-control" id="labor_grupo_ir" maxlength="5">
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_saldo">
                                        <label class="form-check-label" for="labor_maneja_saldo">Maneja Saldo</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="labor_maneja_bascula">
                                        <label class="form-check-label" for="labor_maneja_bascula">Agregar Báscula</label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_labor">
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
<script> const BASE_URL = "<?= base_url() ?>"; </script>
<script src="<?= base_url('public/assets/js/gestionpalma/labores_registro.js') ?>"></script>
<?php echo $this->endSection(); ?>
