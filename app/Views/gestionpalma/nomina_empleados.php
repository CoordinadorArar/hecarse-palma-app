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
        <h1>Empleados</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Empleados</li>
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
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_estado">Estado</label>
                        <select class="form-select" id="filtro_estado">
                            <option value="">Todos</option>
                            <option value="1" selected>Activos</option>
                            <option value="0">Inactivos</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_vinculo">Vínculo</label>
                        <select class="form-select" id="filtro_vinculo">
                            <option value="">Todos</option>
                            <option value="contratista">Contratistas</option>
                            <option value="conductor">Conductores</option>
                            <option value="otros">Ocasionales</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="filtro_busqueda"
                                placeholder="Buscar por código, tercero o nombre" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-12 col-lg-auto">
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
                <span id="contador_empleados" class="gp-contador">
                    <i class="bi bi-people me-1"></i><strong><?= esc($total_empleados) ?></strong> <?= (int) $total_empleados === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_empleados" class="table table-striped table-hover align-middle mb-0 gp-tabla gp-tabla-ancha" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-nowrap" style="width:120px">Código</th>
                                <th>Empleado</th>
                                <th class="text-center text-nowrap" style="width:115px">Ingreso</th>
                                <th class="text-end" style="width:140px">Salario</th>
                                <th style="width:190px">Proveedor</th>
                                <th class="text-center" style="width:120px">Vínculo</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:110px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_empleados">
                            <?= $tabla_empleados ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <h6>No se encontraron empleados</h6>
                    <p class="text-muted">Ajuste los filtros de búsqueda o registre un nuevo empleado.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo empleado
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Empleado -->
    <div class="modal fade" id="modalEmpleado" tabindex="-1" aria-labelledby="titulo_modal_empleado" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_empleado">
                        <i class="bi bi-person-badge me-1"></i>Nuevo empleado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_empleado">
                    <input type="hidden" id="empleado_modo" value="crear">
                    <input type="hidden" id="empleado_empresa_pk">
                    <input type="hidden" id="empleado_tercero_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-person-vcard"></i>Vinculación del tercero</h6>
                            </div>

                            <div class="col-12" id="empleado_panel_opcion">
                                <div class="gp-panel-opcion">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="empleado_crea_tercero">
                                        <label class="form-check-label" for="empleado_crea_tercero">Crear un tercero nuevo</label>
                                    </div>
                                    <span class="gp-subtexto" id="empleado_ayuda_opcion">Se vinculará un tercero ya existente. Escoja el tercero y la identificación y la descripción se completan solas.</span>
                                </div>
                            </div>

                            <div class="col-md-4" id="col_empleado_empresa">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="empleado_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-8 gp-campo-activo" id="col_empleado_tercero">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_tercero">Tercero <span class="text-danger">*</span></label>
                                <div class="input-group gp-select-buscador">
                                    <span class="input-group-text bg-light"><i class="bi bi-person-vcard"></i></span>
                                    <select class="form-select" id="empleado_tercero">
                                        <option value=""></option>
                                        <?php foreach (($terceros_disponibles[$empresa_sel] ?? []) as $tercero): ?>
                                            <option value="<?= esc($tercero['id'], 'attr') ?>" data-nit="<?= esc(trim((string) $tercero['nit']), 'attr') ?>">
                                                <?= esc($tercero['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-text <?= empty($terceros_disponibles[$empresa_sel]) ? '' : 'd-none' ?>" id="empleado_tercero_vacio">
                                    <i class="bi bi-info-circle me-1"></i>Esta empresa no tiene terceros registrados. Marque &laquo;Crear un tercero nuevo&raquo; para registrarlo.
                                </div>
                            </div>

                            <div class="col-md-8 align-self-end d-none" id="empleado_aviso_nuevo">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>Se creará un tercero nuevo en esta empresa; el consecutivo se asigna automáticamente al guardar.
                                </div>
                            </div>
                            <div class="col-md-4 gp-campo-condicionado" id="col_empleado_identificacion">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_identificacion">Identificación <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-credit-card-2-front"></i></span>
                                    <input type="text" class="form-control font-monospace bg-light" id="empleado_identificacion" inputmode="numeric" maxlength="25" readonly>
                                </div>
                            </div>

                            <div class="col-md-8 gp-campo-condicionado" id="col_empleado_descripcion">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-list"></i></span>
                                    <input type="text" class="form-control bg-light" id="empleado_descripcion" maxlength="950" readonly>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="empleado_bloque_tercero">
                                <div class="gp-bloque-tercero">
                                    <h6 class="gp-subseccion"><i class="bi bi-person-plus"></i>Información del tercero nuevo</h6>
                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_codigo">Código <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-credit-card-2-front"></i></span>
                                                <input type="text" class="form-control font-monospace" id="empleado_nt_codigo" inputmode="numeric" maxlength="25" autocomplete="off">
                                            </div>
                                            <div class="form-text">Se usa como número de documento y como código del tercero.</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_tipo_doc">Tipo de identificación <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-card-heading"></i></span>
                                                <select class="form-select" id="empleado_nt_tipo_doc">
                                                    <?php foreach ($tipos_documento as $tipo): ?>
                                                        <option value="<?= esc(trim((string) $tipo['codigo']), 'attr') ?>" <?= trim((string) $tipo['codigo']) === '13' ? 'selected' : '' ?>>
                                                            <?= esc(trim((string) $tipo['descripcionCorta'])) ?> &mdash; <?= esc(trim((string) $tipo['descripcion'])) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_apellido1">Primer apellido</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person-lines-fill"></i></span>
                                                <input type="text" class="form-control" id="empleado_nt_apellido1" maxlength="100" autocomplete="off">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_apellido2">Segundo apellido</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person-lines-fill"></i></span>
                                                <input type="text" class="form-control" id="empleado_nt_apellido2" maxlength="100" autocomplete="off">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_nombre1">Primer nombre</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                                <input type="text" class="form-control" id="empleado_nt_nombre1" maxlength="100" autocomplete="off">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_nombre2">Segundo nombre</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                                <input type="text" class="form-control" id="empleado_nt_nombre2" maxlength="100" autocomplete="off">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <span class="gp-subtexto" id="empleado_nt_nombre_completo"></span>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_descripcion">Descripción <span class="text-danger d-none" id="empleado_nt_descripcion_req">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-card-list"></i></span>
                                                <input type="text" class="form-control" id="empleado_nt_descripcion" maxlength="950" autocomplete="off">
                                            </div>
                                            <div class="form-text">Se completa sola con los apellidos y nombres. Escríbala solo si necesita un texto distinto.</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_telefono">Teléfono</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                                <input type="text" class="form-control" id="empleado_nt_telefono" maxlength="50" autocomplete="off">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_nt_direccion">Dirección</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-house-door"></i></span>
                                                <input type="text" class="form-control" id="empleado_nt_direccion" maxlength="200" autocomplete="off">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="empleado_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1"></i>La empresa y el tercero no se pueden modificar. La identificación y la descripción se administran desde la pantalla de Terceros.
                                    <a class="btn btn-link btn-sm p-0 ms-1" target="_blank" href="<?= esc(base_url('gestion-palma/contabilidad/terceros/' . $id_loseta), 'attr') ?>">Abrir Terceros</a>
                                </div>
                            </div>
                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-briefcase"></i>Condiciones laborales</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_proveedor">Proveedor</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-truck"></i></span>
                                    <select class="form-select" id="empleado_proveedor">
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach (($proveedores[$empresa_sel] ?? []) as $proveedor): ?>
                                            <option value="<?= esc(trim((string) $proveedor['nit']), 'attr') ?>">
                                                <?= esc($proveedor['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-text d-none" id="empleado_proveedor_legado">
                                    <i class="bi bi-info-circle me-1"></i>Este empleado conserva un código de proveedor del sistema anterior. Si escoge un proveedor de la lista, quedará corregido al guardar.
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_salario">Salario</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-cash-coin"></i></span>
                                    <input type="number" class="form-control text-end font-monospace" id="empleado_salario" min="0" step="1000" value="0">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="empleado_fecha_ingreso">Fecha de ingreso</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar-event"></i></span>
                                    <input type="date" class="form-control" id="empleado_fecha_ingreso">
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-toggles"></i>Clasificación</h6>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="empleado_activo" checked>
                                        <label class="form-check-label" for="empleado_activo">Activo con contrato</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="empleado_contratista">
                                        <label class="form-check-label" for="empleado_contratista">Contratista</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="empleado_conductor">
                                        <label class="form-check-label" for="empleado_conductor">Conductor interno</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="empleado_otros">
                                        <label class="form-check-label" for="empleado_otros">Ocasional</label>
                                    </div>
                                </div>
                                <span class="gp-subtexto mt-2">No son excluyentes; puede marcar varias.</span>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_empleado">
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
<script> const BASE_URL = "<?= base_url() ?>"; const TERCEROS_DISPONIBLES = <?= json_encode($terceros_disponibles) ?>; const PROVEEDORES = <?= json_encode($proveedores) ?>; </script>
<script src="<?= base_url('public/assets/js/gestionpalma/nomina_empleados.js') ?>"></script>
<?php echo $this->endSection(); ?>
