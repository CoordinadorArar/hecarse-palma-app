<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/maestros.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Empresas</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('maestros/onboarding/' . $id_loseta), 'attr') ?>">Maestros</a></li>
                <li class="breadcrumb-item">Parámetros administración</li>
                <li class="breadcrumb-item active">Empresas</li>
            </ol>
        </nav>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#listaEmpresas">Lista de Empresas</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#crearEmpresa">Crear Empresa</a>
        </li>
    </ul>

    <div class="tab-content pt-3">

        <!-- Tab 1: Lista -->
        <div class="tab-pane fade show active" id="listaEmpresas">
            <div class="card">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <h6 class="mb-0 maestros-titulo-tarjeta"><i class="bi bi-building me-1" aria-hidden="true"></i>Lista de empresas</h6>
                    <span id="contador_empresas" class="maestros-contador"></span>
                </div>
                <div class="card-body">
                    <div id="aviso_sin_representantes" class="maestros-nota mb-3 d-none">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Ninguna empresa tiene representante legal vinculado todavía. Se asocia desde el botón Editar, campo Tercero vinculado.
                    </div>
                    <div class="table-responsive" id="contenedor_tabla">
                        <table id="tabla_empresas" class="table table-striped table-hover align-middle mb-0 maestros-tabla maestros-tabla-ancha" style="width:100%">
                            <thead class="maestros-thead">
                                <tr>
                                    <th class="text-center" style="width:72px">Código</th>
                                    <th style="width:132px">Nit</th>
                                    <th style="min-width:220px">Razón social</th>
                                    <th style="width:200px">Representante legal</th>
                                    <th style="width:240px">Contacto</th>
                                    <th class="text-center" style="width:110px">Extractora</th>
                                    <th class="text-center" style="width:120px">Estado</th>
                                    <th style="width:130px">Fecha registro</th>
                                    <th class="text-center maestros-col-sticky" style="width:110px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_tabla_empresas">
                                <?php echo $tabla_empresas; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Crear -->
        <div class="tab-pane fade" id="crearEmpresa">
            <div class="card">
                <div class="card-body p-4">
                    <form id="formulario_creacion" novalidate>
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-building" aria-hidden="true"></i>Datos de la empresa</h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="codigo_crear">Código</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace bg-light text-center" id="codigo_crear"
                                        value="<?= esc($siguiente_codigo ?? '') ?>" readonly tabindex="-1" aria-readonly="true">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                                </div>
                                <div class="form-text">Consecutivo asignado por el sistema. No se puede modificar.</div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="nit_crear">Nit <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="nit_crear" placeholder="900123456"
                                        maxlength="15" inputmode="numeric" autocomplete="off"
                                        onkeypress="return soloNumeros(event)" onpaste="return sanitizePaste(event)">
                                    <span class="input-group-text bg-light px-2">–</span>
                                    <input type="text" class="form-control font-monospace text-center bg-light dv-box dv-vacio" id="dv_crear"
                                        maxlength="1" placeholder="DV" readonly tabindex="-1" aria-label="Dígito de verificación">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">El dígito de verificación se calcula solo a partir del Nit.</div>
                            </div>

                            <div class="col-md-4 d-none d-md-block"></div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="razonSocial_crear">Razón social <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="razonSocial_crear" placeholder="Nombre legal de la empresa"
                                        maxlength="550" autocomplete="off"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return sanitizePaste(event)">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-person-badge" aria-hidden="true"></i>Representante legal</h6>
                                <div class="form-text">Con estos datos se crea el tercero que queda vinculado a la empresa.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="cc_crear">CC representante <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="cc_crear" placeholder="1090123456"
                                        maxlength="25" inputmode="numeric" autocomplete="off"
                                        onkeypress="return soloNumeros(event)" onpaste="return sanitizePaste(event)">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Se usa como número de documento y como código del tercero.</div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="nombreRepresentante_crear">Nombre representante <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="nombreRepresentante_crear" placeholder="Nombres y apellidos"
                                        maxlength="550" autocomplete="off"
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return sanitizePaste(event)">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="telefono_crear">Teléfono</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="telefono_crear" placeholder="3001234567"
                                        maxlength="50" inputmode="tel" autocomplete="off" onpaste="return sanitizePaste(event)">
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="direccion_crear">Dirección</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-house-door" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="direccion_crear" placeholder="Calle 10 # 5-25"
                                        maxlength="550" autocomplete="off" onpaste="return sanitizePaste(event)">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="email_crear">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                                    <input type="email" class="form-control" id="email_crear" placeholder="representante@empresa.com"
                                        maxlength="250" inputmode="email" autocomplete="off">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-card-text" aria-hidden="true"></i>Información adicional</h6>
                            </div>

                            <div class="col-12 campo-pendiente">
                                <div class="aviso">
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                    Actividad económica y Notas y observaciones todavía no se guardan: la base de datos aún no tiene esas dos columnas. Se muestran aquí para dejar el formulario completo y quedarán habilitados cuando se ejecute el script de actualización de la base de datos.
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="actividadEconomica_crear">Actividad económica
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">Pendiente</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-briefcase" aria-hidden="true"></i></span>
                                            <input type="text" class="form-control" id="actividadEconomica_crear" placeholder="Actividad económica principal"
                                                maxlength="250" autocomplete="off" disabled
                                                title="Campo pendiente de habilitar. Todavía no se guarda.">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="notas_crear">Notas y observaciones
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">Pendiente</span>
                                        </label>
                                        <textarea class="form-control" id="notas_crear" rows="3" maxlength="1000"
                                            placeholder="Notas y observaciones..." disabled
                                            title="Campo pendiente de habilitar. Todavía no se guarda."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="extractora_crear">
                                        <label class="form-check-label" for="extractora_crear">Es extractora</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="activo_crear" checked>
                                        <label class="form-check-label" for="activo_crear">Activa</label>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-2">
                            <small class="text-muted"><span class="text-danger">*</span> Campos obligatorios.</small>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" id="btn_limpiar_empresa">
                                    <i class="bi bi-eraser me-1"></i> Limpiar
                                </button>
                                <button type="submit" class="btn btn-primary" id="btn_crear_empresa">
                                    <i class="bi bi-plus-circle me-1"></i> Crear empresa
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Editar -->
    <div class="modal fade" id="modalEditarEmpresa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square"></i> Editar Empresa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formulario_editar_empresa">
                    <input type="hidden" id="editar_id">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-md-4">
                                <label class="form-label">Nit <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control" id="editar_nit" maxlength="15" required
                                        onkeypress="return soloNumeros(event)" onpaste="return sanitizePaste(event)">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">DV</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-check2-circle"></i></span>
                                    <input type="text" class="form-control font-monospace" id="editar_dv" maxlength="1">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Razón Social <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <input type="text" class="form-control" id="editar_razonSocial" required
                                        onkeypress="return noStrangeCharacters(event)" onpaste="return sanitizePaste(event)">
                                </div>
                            </div>

                            <div class="col-md-6 position-relative">
                                <label class="form-label">Tercero vinculado</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" class="form-control" id="tercero_buscar_editar"
                                        placeholder="Buscar por nombre o Nit" autocomplete="off">
                                </div>
                                <input type="hidden" id="tercero_id_editar">
                                <div class="list-group position-absolute w-100 shadow-sm maestros-typeahead" id="tercero_resultados_editar"></div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label d-block">Extractora</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="editar_extractora">
                                    <label class="form-check-label" for="editar_extractora">Es extractora</label>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label d-block">Estado</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="editar_activo">
                                    <label class="form-check-label" for="editar_activo">Activa</label>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_edicion">
                            <i class="bi bi-floppy me-1"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</main>
<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script> const BASE_URL = "<?= base_url() ?>"; </script>
<script src="<?= base_url('public/assets/js/maestros/empresas.js') ?>"></script>
<?php echo $this->endSection(); ?>
