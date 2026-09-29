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
$empresaNombre = '';
foreach ($empresas as $e) {
    if ((int) $e['id'] === (int) $empresa_sel) {
        $empresaNombre = $e['razonSocial'];
    }
}
$urlPorLabor = base_url('gestion-palma/precios/por-labor/' . $id_loseta);
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Lista de precios / Precios labor por lote</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item">Lista de precios</li>
                <li class="breadcrumb-item active">Precios labor por lote</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div class="gp-nota mb-3">
            <i class="bi bi-info-circle me-1"></i>Aquí se registran precios de labor acotados a una finca o a un lote. Cuando existe uno, la liquidación lo usa en lugar del precio general del año. La búsqueda va del detalle a lo general: primero lote, luego sección, luego finca; si no encuentra ninguno, usa la lista general.
            <a class="gp-enlace-general ms-1" href="<?= esc($urlPorLabor, 'attr') ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir Precios por labor</a>
        </div>

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
                            <option value="" selected>Todos</option>
                            <?php foreach ($anios as $anio): ?>
                                <option value="<?= esc($anio, 'attr') ?>"><?= esc($anio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_finca">Finca</label>
                        <select class="form-select" id="filtro_finca">
                            <option value="" selected>Todas</option>
                            <?php foreach ($fincas as $finca): ?>
                                <option value="<?= esc($finca['codigo'], 'attr') ?>" data-descripcion="<?= esc($finca['descripcion'], 'attr') ?>"><?= esc($finca['codigo']) ?> &mdash; <?= esc($finca['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="filtro_busqueda"
                                placeholder="Buscar por labor o por lote" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-12 col-lg-auto ms-lg-auto">
                        <button type="button" class="btn btn-primary w-100" id="btn_buscar">
                            <i class="bi bi-funnel me-1"></i>Buscar
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <div class="row g-3">

            <div class="col-lg-5">
                <div class="card gp-panel-registro mb-0">

                    <div class="card-header bg-white py-3">
                        <div id="registro_encabezado_crear">
                            <h5 class="mb-0"><i class="bi bi-geo-alt me-1"></i>Registrar precio por ámbito</h5>
                            <span class="gp-subtexto">Empresa: <span id="registro_empresa"><?= esc($empresaNombre) ?></span></span>
                            <span class="gp-chip-fila verde mt-2 d-none" id="chip_copiado"></span>
                        </div>
                        <div class="d-none" id="registro_encabezado_editar">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h5 class="mb-1"><i class="bi bi-pencil me-1"></i>Editar precio</h5>
                                    <span class="gp-chip-ambito" id="chip_ambito_editar"></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn_duplicar">
                                        <i class="bi bi-files me-1"></i>Duplicar
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_cancelar_edicion">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar edición
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form id="formulario_precio" novalidate>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-12">
                                    <h6 class="gp-seccion" id="titulo_ambito"><i class="bi bi-geo-alt" aria-hidden="true"></i>Ámbito del precio</h6>
                                </div>

                                <div class="col-12" id="bloque_llave">
                                    <div class="row g-3">

                                        <div class="col-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="form_anio">Año <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-calendar"></i></span>
                                                <select class="form-select" id="form_anio">
                                                    <option value="">Seleccione&hellip;</option>
                                                    <?php foreach ($anios as $anio): ?>
                                                        <option value="<?= esc($anio, 'attr') ?>"><?= esc($anio) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-text<?= empty($anios) ? ' d-none' : '' ?>" id="ayuda_anio">Se listan los años que existen en la lista general de precios.</div>
                                            <div class="form-text<?= empty($anios) ? '' : ' d-none' ?>" id="nota_anio">
                                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Aún no hay años en la lista general de precios.
                                                <a class="gp-enlace-general" href="<?= esc($urlPorLabor, 'attr') ?>">Abrir Precios por labor</a>
                                            </div>
                                            <div class="form-text text-danger d-none" id="error_anio">Seleccione un año.</div>
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="form_finca">Finca <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-tree"></i></span>
                                                <select class="form-select" id="form_finca">
                                                    <option value="">Seleccione&hellip;</option>
                                                    <?php foreach ($fincas as $finca): ?>
                                                        <option value="<?= esc($finca['codigo'], 'attr') ?>" data-descripcion="<?= esc($finca['descripcion'], 'attr') ?>"><?= esc($finca['codigo']) ?> &mdash; <?= esc($finca['descripcion']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-text text-danger d-none" id="error_finca">Seleccione una finca.</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="gp-panel-opcion inhabilitado" id="panel_seccion" title="No hay secciones registradas">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="form_switch_seccion" disabled>
                                                    <label class="form-check-label" for="form_switch_seccion">Especificar sección</label>
                                                </div>
                                                <div class="form-text" id="nota_seccion">
                                                    <i class="bi bi-info-circle me-1"></i><span id="nota_seccion_texto">Esta empresa aún no tiene secciones registradas. El precio quedará a nivel de finca o de lote. Puede crearlas en Gestión Palma &rsaquo; Secciones y esta opción se habilitará automáticamente.</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 d-none" id="col_seccion">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="form_seccion">Sección</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-grid-1x2"></i></span>
                                                <select class="form-select" id="form_seccion">
                                                    <option value="">Seleccione&hellip;</option>
                                                </select>
                                            </div>
                                            <div class="form-text text-danger d-none" id="error_seccion">Seleccione una sección o desactive &laquo;Especificar sección&raquo;.</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="form_switch_lote">
                                                <label class="form-check-label" for="form_switch_lote">Especificar lote</label>
                                            </div>
                                        </div>

                                        <div class="col-12 d-none" id="col_lote">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="form_lote">Lote</label>
                                            <div class="input-group gp-select-buscador">
                                                <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                                <select class="form-select" id="form_lote" disabled>
                                                    <option value="">Seleccione&hellip;</option>
                                                </select>
                                            </div>
                                            <div class="form-text" id="ayuda_lote">Seleccione primero la finca.</div>
                                            <div class="form-text text-danger d-none" id="error_lote">Seleccione un lote o desactive &laquo;Especificar lote&raquo;.</div>
                                        </div>

                                    </div>
                                </div>

                                <div class="col-12 d-none" id="llave_fija">
                                    <div class="gp-llave-fija">
                                        <i class="bi bi-lock-fill"></i>
                                        <div>
                                            <div class="gp-llave-titulo" id="llave_fija_titulo"></div>
                                            <div class="gp-ruta-ambito" id="llave_fija_ruta"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="gp-ambito vacio" id="gp_ambito" aria-live="polite">
                                        <span class="gp-chip-ambito d-none" id="ambito_chip"></span>
                                        <div class="gp-ambito-titulo" id="ambito_titulo">Seleccione una finca para definir el ámbito del precio.</div>
                                        <span class="gp-subtexto" id="ambito_consecuencia"></span>
                                        <div class="mt-2">
                                            <span class="gp-etiqueta-mini">Orden de búsqueda en la liquidación</span>
                                            <div class="gp-cascada mt-1" id="ambito_cascada"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 d-none" id="nota_llave">
                                    <div class="gp-nota">
                                        <i class="bi bi-info-circle me-1"></i>El año, la labor y el ámbito (finca, sección y lote) identifican el registro y no se pueden modificar. Si necesita cambiarlos, use Duplicar para crear el registro correcto y luego elimine este.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <h6 class="gp-seccion" id="titulo_labor"><i class="bi bi-briefcase" aria-hidden="true"></i>Labor</h6>
                                </div>

                                <div class="col-12" id="col_labor">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="form_labor">Labor <span class="text-danger">*</span></label>
                                    <div class="input-group gp-select-buscador">
                                        <span class="input-group-text bg-light"><i class="bi bi-briefcase"></i></span>
                                        <select class="form-select" id="form_labor">
                                            <option value="">Seleccione&hellip;</option>
                                            <?php foreach ($labores as $labor): ?>
                                                <option value="<?= esc($labor['codigo'], 'attr') ?>" data-descripcion="<?= esc($labor['descripcion'], 'attr') ?>"><?= esc($labor['codigo']) ?> &mdash; <?= esc($labor['descripcion']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-text text-danger d-none" id="error_labor">Seleccione una labor.</div>
                                </div>

                                <div class="col-12 d-none" id="panel_referencia">
                                    <div class="gp-referencia">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <span class="gp-referencia-titulo" id="referencia_titulo"></span>
                                                <span class="gp-chip-fila verde d-none" id="referencia_base"><i class="bi bi-check-lg me-1"></i>Base sueldo</span>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_copiar_general">
                                                <i class="bi bi-clipboard-check me-1"></i>Copiar del general
                                            </button>
                                        </div>
                                        <div class="row g-2 mt-1" id="referencia_valores">
                                            <div class="col-3">
                                                <span class="gp-etiqueta-mini">Destajo</span>
                                                <div class="gp-referencia-valor" id="referencia_precioDestajo">0</div>
                                            </div>
                                            <div class="col-3">
                                                <span class="gp-etiqueta-mini">Contratistas</span>
                                                <div class="gp-referencia-valor" id="referencia_precioContratistas">0</div>
                                            </div>
                                            <div class="col-3">
                                                <span class="gp-etiqueta-mini">Otros</span>
                                                <div class="gp-referencia-valor" id="referencia_precioOtros">0</div>
                                            </div>
                                            <div class="col-3">
                                                <span class="gp-etiqueta-mini">Porcentaje</span>
                                                <div class="gp-referencia-valor" id="referencia_porcentaje">0</div>
                                            </div>
                                        </div>
                                        <div class="placeholder-glow mt-1 d-none" id="referencia_cargando">
                                            <span class="placeholder col-12"></span>
                                        </div>
                                    </div>
                                    <div class="gp-nota-alerta mt-2 d-none" id="referencia_aviso" role="alert">
                                        <i class="bi bi-exclamation-triangle me-1"></i><span id="referencia_aviso_texto"></span>
                                        <a class="gp-enlace-general ms-1 d-none" id="referencia_aviso_enlace" href="<?= esc($urlPorLabor, 'attr') ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir Precios por labor</a>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <h6 class="gp-seccion"><i class="bi bi-cash-coin"></i>Valores</h6>
                                </div>

                                <div class="col-6">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="valor_precioDestajo">Precio destajo ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="text" class="form-control gp-input-precio gp-cero" id="valor_precioDestajo" data-campo="precioDestajo" inputmode="decimal" autocomplete="off" value="0">
                                    </div>
                                    <div class="form-text d-none" id="ayuda_precioDestajo"><span data-general></span><span class="gp-delta d-none" data-delta></span></div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="valor_precioContratistas">Precio contratistas ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="text" class="form-control gp-input-precio gp-cero" id="valor_precioContratistas" data-campo="precioContratistas" inputmode="decimal" autocomplete="off" value="0">
                                    </div>
                                    <div class="form-text d-none" id="ayuda_precioContratistas"><span data-general></span><span class="gp-delta d-none" data-delta></span></div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="valor_precioOtros">Otros precios ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="text" class="form-control gp-input-precio gp-cero" id="valor_precioOtros" data-campo="precioOtros" inputmode="decimal" autocomplete="off" value="0">
                                    </div>
                                    <div class="form-text d-none" id="ayuda_precioOtros"><span data-general></span><span class="gp-delta d-none" data-delta></span></div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label mb-1 small fw-semibold text-muted" for="valor_porcentaje">Porcentaje (%)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control gp-input-precio gp-cero" id="valor_porcentaje" data-campo="porcentaje" inputmode="decimal" autocomplete="off" value="0">
                                        <span class="input-group-text bg-light">%</span>
                                    </div>
                                    <div class="form-text d-none" id="ayuda_porcentaje"><span data-general></span><span class="gp-delta d-none" data-delta></span></div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="valor_baseSueldo">
                                        <label class="form-check-label" for="valor_baseSueldo">Base al sueldo</label>
                                    </div>
                                    <div class="gp-nota mt-2">
                                        <i class="bi bi-info-circle me-1"></i>Base sueldo activo: la liquidación ignora el precio de destajo y usa el salario mínimo diario. Si el porcentaje es mayor que 0, usa ese porcentaje del salario.
                                    </div>
                                </div>

                                <div class="col-12 d-none" id="aviso_colision">
                                    <div class="gp-nota-alerta d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
                                        <span><i class="bi bi-exclamation-triangle me-1"></i>Ya existe un precio para esta labor en este ámbito y año. Edite el registro existente o cambie el ámbito.</span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_ir_existente">
                                            <i class="bi bi-arrow-right-circle me-1"></i>Ir al registro existente
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="card-footer bg-white py-2 d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" id="btn_limpiar">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary" id="btn_guardar">
                                <i class="bi bi-floppy me-1"></i>Guardar precio
                            </button>
                        </div>
                    </form>

                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-0">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                        <span id="contador_precios" class="gp-contador" aria-live="polite">
                            <i class="bi bi-geo-alt me-1"></i><strong><?= esc($total_precios) ?></strong> <?= (int) $total_precios === 1 ? 'precio registrado' : 'precios registrados' ?>
                        </span>
                        <button type="button" class="btn btn-success btn-sm" id="btn_nuevo_precio">
                            <i class="bi bi-plus-lg me-1"></i>Nuevo precio
                        </button>
                    </div>
                    <div class="card-body p-0">

                        <div class="table-responsive" id="gp_contenedor_tabla">
                            <table id="tabla_precios" class="table table-striped table-hover align-middle mb-0 gp-tabla gp-tabla-ancha" style="width:100%">
                                <thead class="gp-thead">
                                    <tr>
                                        <th class="text-center" style="width:80px">Año</th>
                                        <th style="min-width:240px">Labor</th>
                                        <th style="width:220px">Ámbito</th>
                                        <th class="text-end" style="width:100px">Destajo ($)</th>
                                        <th class="text-end" style="width:100px">Contratistas ($)</th>
                                        <th class="text-end" style="width:110px">Otros ($)</th>
                                        <th class="text-end" style="width:100px">Porcentaje (%)</th>
                                        <th class="text-center" style="width:110px" title="Si está activo, la liquidación ignora el precio de destajo y usa el salario mínimo diario; si además hay un porcentaje, usa ese porcentaje del salario.">Base sueldo <i class="bi bi-info-circle ms-1" aria-hidden="true"></i></th>
                                        <th class="text-center gp-col-sticky" style="width:110px">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpo_tabla_precios">
                                    <?= $tabla_precios ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                            <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <h6>Aún no hay precios por ámbito</h6>
                            <p class="text-muted">Los precios registrados aquí sobrescriben la lista general del año para una finca o un lote. Mientras no exista ninguno, todas las labores se liquidan con Precios por labor.</p>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <button type="button" class="btn btn-success" id="btn_registrar_primero">
                                    <i class="bi bi-plus-lg me-1"></i>Registrar el primero
                                </button>
                                <a class="btn btn-outline-secondary" href="<?= esc($urlPorLabor, 'attr') ?>">
                                    <i class="bi bi-tags me-1"></i>Ver Precios por labor
                                </a>
                            </div>
                        </div>

                        <div id="gp_estado_filtro" class="gp-estado-vacio text-center py-5 d-none">
                            <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                                <i class="bi bi-search" aria-hidden="true"></i>
                            </div>
                            <h6>No hay precios que coincidan con el filtro</h6>
                            <p class="text-muted">Ajuste el año, la finca o la búsqueda.</p>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_limpiar_filtros">
                                <i class="bi bi-x-circle me-1"></i>Limpiar filtros
                            </button>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </section>

</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const ID_LOSETA = <?= (int) $id_loseta ?>;
    const ANIOS_GENERALES = <?= json_encode(array_map('strval', $anios)) ?>;
    const TOTAL_INICIAL = <?= (int) $total_precios ?>;
    const NIVELES_INICIALES = <?= json_encode($niveles_precios) ?>;
</script>
<script src="<?= base_url('public/assets/js/gestionpalma/precios_por_lote.js') ?>"></script>
<?php echo $this->endSection(); ?>
