<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/maestros.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<?php
$relaciones = ['gTipoTransaccionProducto', 'iBodegaTipoTransaccion', 'iItemsBodega'];
$grupos     = [
    'Catálogos con código y descripción'        => [],
    'Relaciones (llave completa, no editables)' => [],
];

foreach ($entidades as $entidad) {
    $clave = in_array($entidad['nombre'], $relaciones, true)
        ? 'Relaciones (llave completa, no editables)'
        : 'Catálogos con código y descripción';

    $grupos[$clave][] = $entidad;
}
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Entidades Auxiliares</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('maestros/onboarding/' . $id_loseta), 'attr') ?>">Maestros</a></li>
                <li class="breadcrumb-item">Parámetros administración</li>
                <li class="breadcrumb-item active">Entidades Auxiliares</li>
            </ol>
        </nav>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3 px-4">
            <div class="row g-2 align-items-end">

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_empresa">Empresa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_empresa">
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= esc($empresa['id'], 'attr') ?>" <?= (int) $empresa['id'] === (int) $empresa_sel ? 'selected' : '' ?>>
                                    <?= esc($empresa['razonSocial']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-text">Cada entidad se guarda por empresa.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_entidad">Entidad</label>
                    <div class="input-group maestros-select-buscador">
                        <span class="input-group-text"><i class="bi bi-collection" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_entidad">
                            <option value=""></option>
                            <?php foreach ($grupos as $grupo => $lista): ?>
                                <optgroup label="<?= esc($grupo, 'attr') ?>">
                                    <?php foreach ($lista as $entidad): ?>
                                        <option value="<?= esc($entidad['nombre'], 'attr') ?>"><?= esc($entidad['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-text">Cada entidad es un catálogo independiente.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" id="filtro_busqueda" autocomplete="off"
                            placeholder="Elija primero una entidad" disabled>
                        <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda" disabled>
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-12 col-lg-auto ms-lg-auto">
                    <button type="button" class="btn btn-primary w-100" id="btn_buscar" disabled>
                        <i class="bi bi-funnel me-1" aria-hidden="true"></i>Buscar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <h6 class="mb-0 maestros-titulo-tarjeta"><i class="bi bi-collection me-1" aria-hidden="true"></i>Registros de la entidad</h6>
            <span id="contador_registros" class="maestros-contador" aria-live="polite"></span>
            <button type="button" class="btn btn-success btn-sm" id="btn_nuevo" disabled>
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo registro
            </button>
        </div>

        <div class="card-body p-0">

            <div class="maestros-nota m-3 mb-0 d-none" id="nota_llave_completa">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>En esta entidad las dos columnas forman la llave del registro, así que no se puede editar: para corregir una fila, elimínela y cree la correcta.</span>
            </div>

            <div class="maestros-nota-alerta m-3 mb-0 d-none flex-wrap justify-content-between gap-2" role="alert" id="nota_desactualizada">
                <span class="d-flex align-items-start gap-1">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <span id="mensaje_nota_desactualizada"></span>
                </span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reintentar_nota">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar
                </button>
            </div>

            <div class="table-responsive d-none" id="contenedor_tabla">
                <table class="table table-striped table-hover align-middle mb-0 maestros-tabla" style="width:100%">
                    <thead class="maestros-thead">
                        <tr>
                            <th id="th_col1" style="min-width:200px"></th>
                            <th id="th_col2" style="min-width:360px"></th>
                            <th class="text-center" style="width:110px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpo_tabla_entidades"></tbody>
                </table>
            </div>

            <div class="maestros-estado-vacio" id="estado_sin_entidad">
                <div class="maestros-medallon chico neutro"><i class="bi bi-collection" aria-hidden="true"></i></div>
                <h6 class="mb-1">Elija una entidad para ver sus registros</h6>
                <p class="mb-0">Cada entidad es un catálogo independiente. Selecciónela arriba y aquí aparecerán sus registros.</p>
            </div>

            <div class="maestros-estado-vacio d-none" id="estado_vacio">
                <div class="maestros-medallon"><i class="bi bi-inbox" aria-hidden="true"></i></div>
                <h6 class="mb-1" id="titulo_estado_vacio"></h6>
                <p class="mb-3">Varios de estos catálogos llegan vacíos del sistema anterior; puede crear el primero ahora.</p>
                <button type="button" class="btn btn-success" id="btn_crear_primero">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear el primero
                </button>
            </div>

            <div class="maestros-estado-vacio d-none" id="estado_filtro">
                <div class="maestros-medallon chico"><i class="bi bi-search" aria-hidden="true"></i></div>
                <h6 class="mb-1" id="titulo_estado_filtro"></h6>
                <p class="mb-3">Revise el texto o busque por el otro campo.</p>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_limpiar_filtro">
                    <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Limpiar búsqueda
                </button>
            </div>

            <div class="maestros-estado-vacio d-none" id="estado_error">
                <div class="maestros-medallon chico alerta"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
                <h6 class="mb-1">No se pudieron cargar los registros</h6>
                <p class="mb-3" id="mensaje_estado_error" aria-live="polite"></p>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reintentar">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar
                </button>
            </div>

        </div>

        <div class="card-footer bg-white py-2 d-none" id="pie_tabla">
            <div class="maestros-paginado">
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 text-muted" for="tam_pagina">Mostrar</label>
                    <select class="form-select form-select-sm w-auto" id="tam_pagina">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span id="info_paginado" aria-live="polite"></span>
                </div>
                <ul class="pagination pagination-sm mb-0" id="paginador"></ul>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEntidadAuxiliar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2">
                        <span id="titulo_modal"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo registro</span>
                        <span class="maestros-chip-entidad" id="chip_entidad"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_entidad" novalidate>
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="valor1">
                                    <span id="rotulo_valor1"></span> <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash" id="icono_valor1" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace" id="valor1" autocomplete="off">
                                    <span class="input-group-text bg-light d-none" id="candado_valor1"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text" id="ayuda_valor1"></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="valor2">
                                    <span id="rotulo_valor2"></span> <span class="text-danger d-none" id="obligatorio_valor2">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-text" id="icono_valor2" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="valor2" autocomplete="off">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text" id="ayuda_valor2"></div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar">
                            <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
    const BASE_URL = "<?= base_url() ?>";
    const POR_PAGINA = <?= (int) $por_pagina ?>;
</script>
<script src="<?= base_url('public/assets/js/maestros/entidades_auxiliares.js') ?>"></script>
<?php echo $this->endSection(); ?>
