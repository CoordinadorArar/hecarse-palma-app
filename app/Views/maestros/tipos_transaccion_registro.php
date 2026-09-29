<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/maestros.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Tipos de transacción</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('maestros/onboarding/' . $id_loseta), 'attr') ?>">Maestros</a></li>
                <li class="breadcrumb-item">Tipo de Transacción</li>
                <li class="breadcrumb-item active">Registro</li>
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
                    <div class="form-text">Los tipos de transacción se guardan por empresa.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" id="filtro_busqueda" autocomplete="off"
                            placeholder="Código, descripción, prefijo o módulo">
                        <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="form-text">Filtra la lista mientras escribe.</div>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_estado">Estado</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-toggle-on" aria-hidden="true"></i></span>
                        <select class="form-select" id="filtro_estado">
                            <option value="">Todos</option>
                            <option value="1">Solo activos</option>
                            <option value="0">Solo inactivos</option>
                        </select>
                    </div>
                    <div class="form-text">Filtra por transacciones activas o inactivas.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <h6 class="mb-0 maestros-titulo-tarjeta"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Tipos de transacción de la empresa</h6>
            <span id="contador_registros" class="maestros-contador" aria-live="polite"></span>
            <button type="button" class="btn btn-success btn-sm" id="btn_nuevo">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo tipo
            </button>
        </div>

        <div class="card-body p-0">

            <div class="maestros-nota m-3 mb-0">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>El código identifica el registro: no se puede modificar después de crearlo.</span>
            </div>

            <div class="maestros-nota-alerta m-3 mb-0 d-none" role="alert" id="aviso_modulos_huerfanos">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <span id="texto_modulos_huerfanos"></span>
            </div>

            <div class="maestros-nota m-3 mb-0 d-none" id="aviso_sin_formato">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>Ningún tipo de transacción de esta empresa tiene formato de impresión definido. Es un campo opcional.</span>
            </div>

            <div class="maestros-grilla-relativa" id="zona_grilla">

                <div class="table-responsive d-none" id="contenedor_tabla">
                    <table id="tabla_tipos_transaccion" class="table table-striped table-hover align-middle mb-0 maestros-tabla maestros-tabla-ancha" style="width:100%">
                        <thead class="maestros-thead">
                            <tr>
                                <th class="text-center" style="width:110px">Código</th>
                                <th style="min-width:260px">Descripción</th>
                                <th style="width:160px">Módulo</th>
                                <th style="width:180px">Numeración</th>
                                <th class="text-end" style="width:120px">Nro. actual</th>
                                <th style="width:180px">Referencia</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center maestros-col-sticky" style="width:110px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_tipos"></tbody>
                    </table>
                </div>

                <div class="maestros-estado-vacio d-none" id="estado_vacio">
                    <div class="maestros-medallon"><i class="bi bi-inbox" aria-hidden="true"></i></div>
                    <h6 class="mb-1">Esta empresa no tiene tipos de transacción</h6>
                    <p class="mb-3">Cree el primero para poder registrar documentos en esta empresa.</p>
                    <button type="button" class="btn btn-success" id="btn_crear_primero">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear el primero
                    </button>
                </div>

                <div class="maestros-estado-vacio d-none" id="estado_filtro">
                    <div class="maestros-medallon chico"><i class="bi bi-search" aria-hidden="true"></i></div>
                    <h6 class="mb-1" id="titulo_estado_filtro"></h6>
                    <p class="mb-3">Revise el texto, el estado seleccionado o limpie los filtros.</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_limpiar_filtro">
                        <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Limpiar filtros
                    </button>
                </div>

                <div class="maestros-estado-vacio d-none" id="estado_error">
                    <div class="maestros-medallon chico alerta"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
                    <h6 class="mb-1">No se pudieron cargar los tipos de transacción</h6>
                    <p class="mb-3" id="mensaje_estado_error" aria-live="polite"></p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reintentar">
                        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar
                    </button>
                </div>

            </div>

        </div>

        <div class="card-footer bg-white py-2 d-none" id="pie_tabla"></div>
    </div>

    <div class="modal fade" id="modalTipoTransaccion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2">
                        <span id="titulo_modal"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo tipo de transacción</span>
                        <span class="maestros-chip-entidad maestros-chip-texto" id="chip_empresa"></span>
                        <span class="maestros-chip-huerfano d-none" id="chip_huerfano">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Módulo no encontrado
                        </span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_tipo" novalidate>
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-card-text" aria-hidden="true"></i>Identificación</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group has-validation" id="grupo_codigo">
                                    <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace rounded-end" id="codigo" maxlength="50" autocomplete="off" placeholder="RLF">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text" id="ayuda_codigo">Máximo 50 caracteres. Identifica la transacción en todos los documentos.</div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-card-text" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="descripcion" maxlength="50" autocomplete="off"
                                        placeholder="TRANSACCIÓN DE LABORES DE CAMPO CON BÁSCULA">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Máximo 50 caracteres. Es el texto que ve el usuario al elegir la transacción.</div>
                            </div>

                            <div class="col-12">
                                <div class="maestros-panel-opcion" id="panel_activo">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="activo">
                                        <label class="form-check-label fw-semibold" for="activo">Activo</label>
                                    </div>
                                    <div class="form-text mb-0">Solo las transacciones activas se pueden usar al crear documentos nuevos.</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-diagram-3" aria-hidden="true"></i>Clasificación</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="modulo">Módulo <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                                    <select class="form-select" id="modulo">
                                        <option value="">Seleccione…</option>
                                        <?php foreach ($modulos as $modulo): ?>
                                            <option value="<?= esc($modulo['codigo'], 'attr') ?>"><?= esc($modulo['descripcion']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="maestros-nota-alerta mt-2 d-none" role="alert" id="nota_modulo_huerfano"></div>
                                <div class="form-text">Módulo del sistema donde se usa la transacción.</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="naturaleza">Naturaleza <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-plus-slash-minus" aria-hidden="true"></i></span>
                                    <select class="form-select" id="naturaleza">
                                        <option value="0">No aplica</option>
                                        <option value="1">Resta</option>
                                        <option value="2">Suma</option>
                                        <option value="3">Suma y resta</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Define cómo afecta la transacción a los saldos. Hoy todas usan «No aplica».</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="modoAnulacion">Método de anulación <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-x-octagon" aria-hidden="true"></i></span>
                                    <select class="form-select" id="modoAnulacion">
                                        <option value="A">Anular</option>
                                        <option value="E">Eliminar</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">«Anular» conserva el documento marcado como anulado; «Eliminar» lo borra.</div>
                            </div>

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-123" aria-hidden="true"></i>Numeración</h6>
                            </div>

                            <div class="col-12">
                                <div class="maestros-panel-opcion" id="panel_numeracion">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="numeracion">
                                        <label class="form-check-label fw-semibold" for="numeracion">Numeración automática</label>
                                    </div>
                                    <div class="form-text mb-0">Actívela para que el sistema asigne el número del documento.</div>

                                    <div class="maestros-colapso" id="bloque_numeracion">
                                        <div class="row g-3 pt-3">

                                            <div class="col-md-4">
                                                <label class="form-label mb-1 small fw-semibold text-muted" for="prefijo">Prefijo</label>
                                                <div class="input-group has-validation">
                                                    <span class="input-group-text"><i class="bi bi-type" aria-hidden="true"></i></span>
                                                    <input type="text" class="form-control font-monospace text-uppercase" id="prefijo" maxlength="50" autocomplete="off" placeholder="RLF">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="form-text">Se antepone al número. Normalmente es igual al código.</div>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label mb-1 small fw-semibold text-muted" for="longitud">Longitud</label>
                                                <div class="input-group has-validation">
                                                    <span class="input-group-text"><i class="bi bi-rulers" aria-hidden="true"></i></span>
                                                    <input type="text" class="form-control maestros-campo-mini" id="longitud" maxlength="2" inputmode="numeric" autocomplete="off">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="form-text">Cantidad total de dígitos del número. Valores usados hoy: 10, 12 y 20.</div>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label mb-1 small fw-semibold text-muted" for="actual">Nro. actual</label>
                                                <div class="input-group has-validation">
                                                    <span class="input-group-text"><i class="bi bi-123" aria-hidden="true"></i></span>
                                                    <input type="text" class="form-control font-monospace" id="actual" maxlength="10" inputmode="numeric" autocomplete="off">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="form-text">Último número asignado. El siguiente documento tomará el número siguiente.</div>
                                            </div>

                                            <div class="col-12">
                                                <div class="maestros-nota-alerta d-none" role="alert" id="aviso_consecutivo">
                                                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                                    <span id="texto_aviso_consecutivo"></span>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="maestros-seccion"><i class="bi bi-link-45deg" aria-hidden="true"></i>Referencia e impresión</h6>
                            </div>

                            <div class="col-12">
                                <div class="maestros-panel-opcion" id="panel_referencia">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="referencia">
                                        <label class="form-check-label fw-semibold" for="referencia">Requiere documento de referencia</label>
                                    </div>
                                    <div class="form-text mb-0">Actívelo cuando la transacción se alimenta de documentos ya registrados.</div>

                                    <div class="maestros-colapso" id="bloque_referencia">
                                        <div class="row g-3 pt-3">
                                            <div class="col-12">
                                                <label class="form-label mb-1 small fw-semibold text-muted" for="vistaDs">Vista o procedimiento que lista los documentos <span class="text-danger">*</span></label>
                                                <div class="input-group has-validation">
                                                    <span class="input-group-text"><i class="bi bi-table" aria-hidden="true"></i></span>
                                                    <input type="text" class="form-control font-monospace" id="vistaDs" maxlength="250" autocomplete="off"
                                                        placeholder="spSeleccionaProSinEjecucion">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="form-text">Nombre exacto del procedimiento o vista de SQL Server que devuelve los documentos disponibles.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="formato">Formato de impresión</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="bi bi-printer" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="formato" maxlength="50" autocomplete="off">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-text">Opcional. Nombre del formato usado al imprimir. Hoy ninguna transacción tiene formato definido.</div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2 justify-content-between">
                        <small class="text-muted"><span class="text-danger">*</span> Campos obligatorios.</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" id="btn_guardar">
                                <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar
                            </button>
                        </div>
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
</script>
<script src="<?= base_url('public/assets/js/maestros/tipos_transaccion_registro.js') ?>"></script>
<?php echo $this->endSection(); ?>
