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
        <h1>Lista de precios / Precios por labor</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item">Lista de precios</li>
                <li class="breadcrumb-item active">Precios por labor</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <div id="panel_anios">

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

                        <div class="col-md-4">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="filtro_busqueda"
                                    placeholder="Buscar por año" autocomplete="off">
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
                                <button type="button" class="btn btn-success flex-fill" id="btn_nuevo_anio">
                                    <i class="bi bi-plus-lg me-1"></i>Nuevo año
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <span id="contador_anios" class="gp-contador">
                        <i class="bi bi-tags me-1"></i><strong><?= esc($total_anios) ?></strong> <?= (int) $total_anios === 1 ? 'año registrado' : 'años registrados' ?>
                    </span>
                </div>
                <div class="card-body p-0">

                    <div class="table-responsive" id="gp_contenedor_tabla">
                        <table id="tabla_anios" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                            <thead class="gp-thead">
                                <tr>
                                    <th class="text-center" style="width:110px">Año</th>
                                    <th class="text-end" style="width:120px">Labores</th>
                                    <th class="text-center" style="width:150px">Con precio</th>
                                    <th style="width:180px">Última actualización</th>
                                    <th>Usuario</th>
                                    <th class="text-center gp-col-sticky" style="width:150px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_tabla_anios">
                                <?= $tabla_anios ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                        <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                            <i class="bi bi-tags"></i>
                        </div>
                        <h6>No hay años de precios registrados</h6>
                        <p class="text-muted">Cree el primer año de la lista de precios de mano de obra.</p>
                        <button type="button" class="btn btn-success" id="btn_nuevo_anio_vacio">
                            <i class="bi bi-plus-lg me-1"></i>Nuevo año
                        </button>
                    </div>

                </div>
            </div>

        </div>

        <div id="panel_editor" class="d-none">
            <div class="card mb-0">

                <div class="card-header bg-white gp-barra-editor d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <div class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_volver">
                            <i class="bi bi-arrow-left me-1"></i>Volver
                        </button>
                        <div>
                            <h5 class="mb-0">Precios de <span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" id="editor_anio_titulo">&mdash;</span></h5>
                            <span class="gp-subtexto" id="editor_resumen"></span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="gp-contador-cambios d-none" id="contador_cambios" role="status" aria-live="polite"></span>
                        <button type="button" class="btn btn-outline-secondary d-none" id="btn_descartar">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Descartar
                        </button>
                        <button type="button" class="btn btn-primary" id="btn_guardar_cambios" disabled>
                            <i class="bi bi-floppy me-1"></i>Guardar cambios
                        </button>
                    </div>
                </div>

                <div class="card-body py-2 border-bottom">
                    <div class="row g-2 align-items-end">

                        <div class="col-md-2">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="editor_anio">Año</label>
                            <select class="form-select form-select-sm" id="editor_anio">
                                <?php foreach ($anios as $anio): ?>
                                    <option value="<?= esc($anio, 'attr') ?>"><?= esc($anio) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="editor_grupo">Grupo</label>
                            <select class="form-select form-select-sm" id="editor_grupo">
                                <option value="" selected>Todos los grupos</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="editor_busqueda">Búsqueda</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="editor_busqueda"
                                    placeholder="Buscar por código o descripción" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda_editor" title="Limpiar búsqueda">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-auto ms-md-auto">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="editor_mostrar">Mostrar</label>
                            <select class="form-select form-select-sm" id="editor_mostrar">
                                <option value="" selected>Todas las labores</option>
                                <option value="con">Solo con precio</option>
                                <option value="sin">Solo sin precio</option>
                                <option value="huerfanas">Solo no registradas</option>
                            </select>
                        </div>

                    </div>
                </div>

                <div class="px-3 pt-3">
                    <div class="gp-nota">
                        <i class="bi bi-info-circle me-1"></i>Base sueldo activo: la liquidación ignora el precio de destajo y usa el salario mínimo diario. Si el porcentaje es mayor que 0, usa ese porcentaje del salario.
                    </div>
                    <div class="gp-nota-alerta mt-2 d-none" id="editor_aviso_huerfanas">
                        <i class="bi bi-exclamation-triangle me-1"></i>Estas labores tienen precios registrados pero ya no existen en el maestro de labores. Se conservan porque tienen movimientos históricos; puede editarlas normalmente.
                    </div>
                </div>

                <div class="card-body p-0 pt-3">
                    <div class="table-responsive gp-scroll-grilla" id="gp_scroll_grilla">
                        <table class="table table-hover align-middle mb-0 gp-tabla gp-tabla-precios">
                            <thead class="gp-thead">
                                <tr>
                                    <th class="gp-col-marca"><span class="visually-hidden">Estado</span></th>
                                    <th class="text-center gp-col-sticky-izq" style="width:80px">Código</th>
                                    <th>Labor</th>
                                    <th style="width:120px">Grupo</th>
                                    <th class="text-end" style="width:120px">Destajo ($)</th>
                                    <th class="text-end" style="width:120px">Contratistas ($)</th>
                                    <th class="text-end" style="width:120px">Otros ($)</th>
                                    <th class="text-end" style="width:100px">Porcentaje (%)</th>
                                    <th class="text-center" style="width:110px" title="Si está activo, la liquidación ignora el precio de destajo y usa el salario mínimo diario; si además hay un porcentaje, usa ese porcentaje del salario.">Base sueldo <i class="bi bi-info-circle ms-1" aria-hidden="true"></i></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_grilla_precios"></tbody>
                        </table>

                        <div id="gp_editor_vacio" class="gp-estado-vacio text-center py-5 d-none">
                            <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:64px;height:64px">
                                <i class="bi bi-search" style="font-size:28px"></i>
                            </div>
                            <h6>No hay labores que coincidan con el filtro</h6>
                            <p class="text-muted">Ajuste la búsqueda, el grupo o el criterio de &laquo;Mostrar&raquo;.</p>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_limpiar_filtros_editor">
                                <i class="bi bi-x-circle me-1"></i>Limpiar filtros
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Nuevo año -->
    <div class="modal fade" id="modalAnio" tabindex="-1" aria-labelledby="titulo_modal_anio" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_anio">
                        <i class="bi bi-tags me-1"></i>Nuevo año de precios
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_anio">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="anio_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="anio_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="anio_valor">Año <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar"></i></span>
                                    <input type="number" class="form-control font-monospace" id="anio_valor" min="2000" max="2100" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="anio_aviso_existe">
                                <div class="gp-nota-alerta">
                                    <i class="bi bi-exclamation-triangle me-1"></i><span id="anio_aviso_texto"></span>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="gp-panel-opcion activo" id="panel_origen_replicar">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="radio" name="anio_origen_modo" id="anio_modo_replicar" value="replicar" checked>
                                        <label class="form-check-label fw-semibold" for="anio_modo_replicar">
                                            <i class="bi bi-files me-1"></i>Replicar desde un año existente
                                        </label>
                                    </div>
                                    <span class="gp-subtexto">Copia todos los precios del año que elija.</span>
                                    <div class="form-text d-none" id="anio_sin_origen">No hay años previos para replicar</div>

                                    <div class="mt-2" id="anio_bloque_origen">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="anio_origen">Año origen</label>
                                        <select class="form-select" id="anio_origen">
                                            <?php foreach ($anios as $anio): ?>
                                                <option value="<?= esc($anio, 'attr') ?>"><?= esc($anio) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text" id="anio_origen_ayuda"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="gp-panel-opcion" id="panel_origen_ceros">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="radio" name="anio_origen_modo" id="anio_modo_ceros" value="ceros">
                                        <label class="form-check-label fw-semibold" for="anio_modo_ceros">
                                            <i class="bi bi-file-earmark-plus me-1"></i>Iniciar en ceros
                                        </label>
                                    </div>
                                    <span class="gp-subtexto">Crea una fila por cada labor activa con todos los valores en cero.</span>
                                    <div class="form-text" id="anio_ceros_ayuda"></div>
                                    <div class="form-text d-none" id="anio_sin_labores">La empresa no tiene labores activas</div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_anio">
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
<script> const BASE_URL = "<?= base_url() ?>"; const LABORES_ACTIVAS = <?= (int) $labores_activas ?>; const ANIOS_INICIALES = <?= json_encode(array_map('intval', $anios)) ?>; </script>
<script src="<?= base_url('public/assets/js/gestionpalma/precios_por_labor.js') ?>"></script>
<?php echo $this->endSection(); ?>
