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
        <h1>Terceros</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item active">Terceros</li>
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
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_tipo">Tipo</label>
                        <select class="form-select" id="filtro_tipo">
                            <option value="">Todos los tipos</option>
                            <option value="1">1 &mdash; Natural</option>
                            <option value="2">2 &mdash; Jurídica</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_rol">Rol</label>
                        <select class="form-select" id="filtro_rol">
                            <option value="">Todos los roles</option>
                            <option value="cliente">Clientes</option>
                            <option value="proveedor">Proveedores</option>
                            <option value="empleado">Empleados</option>
                            <option value="accionista">Accionistas</option>
                            <option value="contratista">Contratistas</option>
                            <option value="extractora">Extractoras</option>
                            <option value="comercializadora">Comercializadoras</option>
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

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="filtro_busqueda"
                                placeholder="Buscar por nit, código, nombre o razón social" autocomplete="off">
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
                <span id="contador_terceros" class="gp-contador">
                    <i class="bi bi-list-ol me-1"></i><strong><?= esc($total_terceros) ?></strong> <?= (int) $total_terceros === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_terceros" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th>Código</th>
                                <th>Documento</th>
                                <th>Descripción</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Roles</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_terceros">
                            <?= $tabla_terceros ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-person-x"></i>
                    </div>
                    <h6>No se encontraron terceros</h6>
                    <p class="text-muted">Ajuste los filtros de búsqueda o cree un nuevo tercero.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo tercero
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Tercero -->
    <div class="modal fade" id="modalTercero" tabindex="-1" aria-labelledby="titulo_modal_tercero" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_tercero">
                        <i class="bi bi-person-vcard me-1"></i>Nuevo tercero
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_tercero">
                    <input type="hidden" id="tercero_modo" value="crear">
                    <input type="hidden" id="tercero_empresa_pk">
                    <input type="hidden" id="tercero_id_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-person-badge"></i>Identificación</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                    <select class="form-select" id="tercero_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_id">Consecutivo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="number" class="form-control bg-light" id="tercero_id" readonly>
                                </div>
                                <div class="form-text">Consecutivo asignado automáticamente.</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_tipo">Tipo de persona <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                    <select class="form-select" id="tercero_tipo" required>
                                        <option value="1">1 &mdash; Persona natural</option>
                                        <option value="2">2 &mdash; Persona jurídica</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_tipo_documento">Tipo de documento <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-heading"></i></span>
                                    <select class="form-select" id="tercero_tipo_documento" required>
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($tipos_documento as $tipo): ?>
                                            <option value="<?= esc(trim((string) $tipo['codigo']), 'attr') ?>">
                                                <?= esc(trim((string) $tipo['descripcionCorta'])) ?> &mdash; <?= esc(trim((string) $tipo['descripcion'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_nit">Nit / Documento <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-credit-card-2-front"></i></span>
                                    <input type="text" class="form-control font-monospace" id="tercero_nit" inputmode="numeric" maxlength="20" required>
                                </div>
                            </div>

                            <div class="col-md-2 d-none" id="col_tercero_dv">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_dv">DV</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-shield-check"></i></span>
                                    <input type="text" class="form-control font-monospace bg-light" id="tercero_dv" readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_codigo">Código</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" class="form-control" id="tercero_codigo" maxlength="20">
                                </div>
                                <div class="form-text">Se autocompleta con el Nit. Puede cambiarlo si el tercero maneja otro código.</div>
                            </div>

                            <div class="col-12 d-none" id="tercero_aviso_llave">
                                <div class="form-text text-muted mt-0">
                                    <i class="bi bi-info-circle me-1"></i>La empresa y el consecutivo del tercero no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-card-text"></i>Nombres</h6>
                            </div>

                            <div class="col-12" id="bloque_natural">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_apellido1">Primer apellido</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person-lines-fill"></i></span>
                                            <input type="text" class="form-control" id="tercero_apellido1" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_apellido2">Segundo apellido</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person-lines-fill"></i></span>
                                            <input type="text" class="form-control" id="tercero_apellido2" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_nombre1">Primer nombre</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                            <input type="text" class="form-control" id="tercero_nombre1" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_nombre2">Segundo nombre</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                            <input type="text" class="form-control" id="tercero_nombre2" maxlength="100">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="bloque_juridica">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_razon_social">Razón social <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-buildings"></i></span>
                                    <input type="text" class="form-control" id="tercero_razon_social" maxlength="550">
                                </div>
                            </div>

                            <div class="col-12">
                                <span class="gp-subtexto" id="tercero_nombre_completo"></span>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-list"></i></span>
                                    <input type="text" class="form-control" id="tercero_descripcion" maxlength="950" required>
                                </div>
                                <div class="form-text">Se autocompleta con el nombre o la razón social. Si no diligencia nombres, escríbala manualmente.</div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-tags"></i>Roles y estado</h6>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_activo" checked>
                                        <label class="form-check-label" for="tercero_activo">Activo</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_cliente">
                                        <label class="form-check-label" for="tercero_cliente">Cliente</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_proveedor">
                                        <label class="form-check-label" for="tercero_proveedor">Proveedor</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_empleado">
                                        <label class="form-check-label" for="tercero_empleado">Empleado</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_accionista">
                                        <label class="form-check-label" for="tercero_accionista">Accionista</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_contratista">
                                        <label class="form-check-label" for="tercero_contratista">Contratista</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_extractora">
                                        <label class="form-check-label" for="tercero_extractora">Extractora</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tercero_comercializadora">
                                        <label class="form-check-label" for="tercero_comercializadora">Comercializadora</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-geo-alt"></i>Ubicación</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_departamento">Departamento</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-map"></i></span>
                                    <select class="form-select" id="tercero_departamento">
                                        <option value="">Seleccione&hellip;</option>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <option value="<?= esc(trim((string) $departamento['codigo']), 'attr') ?>">
                                                <?= esc(trim((string) $departamento['descripcion'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 position-relative">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_ciudad_buscar">Ciudad</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control" id="tercero_ciudad_buscar" autocomplete="off"
                                        placeholder="Escriba al menos 2 letras">
                                </div>
                                <input type="hidden" id="tercero_ciudad">
                                <div class="form-text text-success d-none" id="tercero_ciudad_ok">
                                    <i class="bi bi-check-circle me-1"></i>Ciudad seleccionada.
                                </div>
                                <div id="tercero_ciudad_resultados" class="list-group position-absolute w-100 shadow-sm" style="z-index:1050;"></div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_barrio">Barrio</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-signpost"></i></span>
                                    <input type="text" class="form-control" id="tercero_barrio" maxlength="100">
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_direccion">Dirección</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-house-door"></i></span>
                                    <input type="text" class="form-control" id="tercero_direccion" maxlength="200">
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="gp-seccion"><i class="bi bi-telephone"></i>Contacto</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_telefono">Teléfono</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                    <input type="text" class="form-control" id="tercero_telefono" maxlength="50">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_fax">Fax</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-printer"></i></span>
                                    <input type="text" class="form-control" id="tercero_fax" maxlength="50">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_email">Correo electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="tercero_email" maxlength="150">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_contacto">Contacto</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person-rolodex"></i></span>
                                    <input type="text" class="form-control" id="tercero_contacto" maxlength="150">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="tercero_codigo_equivalencia">Código equivalencia</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-arrow-left-right"></i></span>
                                    <input type="text" class="form-control" id="tercero_codigo_equivalencia" maxlength="20">
                                </div>
                            </div>

                        </div>
                        <small class="text-muted d-block mt-3 d-none" id="tercero_fecha_registro"></small>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_tercero">
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
<script src="<?= base_url('public/assets/js/gestionpalma/terceros.js') ?>"></script>
<?php echo $this->endSection(); ?>
