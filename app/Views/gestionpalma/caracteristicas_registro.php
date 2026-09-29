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
        <h1>Características / Registro</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('gestion-palma/onboarding/' . $id_loseta), 'attr') ?>">Gestión Palma</a></li>
                <li class="breadcrumb-item">Características</li>
                <li class="breadcrumb-item active">Registro</li>
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

                    <div class="col-md-4">
                        <label class="form-label mb-1 small fw-semibold text-muted" for="filtro_busqueda">Búsqueda</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search" aria-hidden="true"></i></span>
                            <input type="text" class="form-control" id="filtro_busqueda"
                                placeholder="Buscar por código o descripción" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btn_limpiar_busqueda" title="Limpiar búsqueda">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-12 col-lg-auto ms-lg-auto">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary flex-fill" id="btn_buscar">
                                <i class="bi bi-funnel me-1" aria-hidden="true"></i>Buscar
                            </button>
                            <button type="button" class="btn btn-success flex-fill" id="btn_nuevo">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva característica
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span id="contador_caracteristicas" class="gp-contador">
                    <i class="bi bi-bug me-1" aria-hidden="true"></i><strong><?= esc($total_caracteristicas) ?></strong> <?= (int) $total_caracteristicas === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                </span>
                <span class="gp-subtexto">La misma característica puede repetirse en varios grupos: censarla y tratarla son registros distintos.</span>
            </div>
            <div class="card-body p-0">

                <div class="table-responsive" id="gp_contenedor_tabla">
                    <table id="tabla_caracteristicas" class="table table-striped table-hover align-middle mb-0 gp-tabla" style="width:100%">
                        <thead class="gp-thead">
                            <tr>
                                <th class="text-center" style="width:90px">Código</th>
                                <th style="min-width:280px">Descripción</th>
                                <th style="width:260px">Grupo</th>
                                <th class="text-center" style="width:100px" title="Registros de censo o tratamiento que usan la característica">Uso</th>
                                <th class="text-center" style="width:110px">Estado</th>
                                <th class="text-center gp-col-sticky" style="width:130px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo_tabla_caracteristicas">
                            <?= $tabla_caracteristicas ?>
                        </tbody>
                    </table>
                </div>

                <div id="gp_estado_vacio" class="gp-estado-vacio text-center py-5 d-none">
                    <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
                        <i class="bi bi-bug" aria-hidden="true"></i>
                    </div>
                    <h6>No se encontraron características</h6>
                    <p class="text-muted">Ajuste los filtros de grupo, estado o búsqueda, o registre una nueva característica.</p>
                    <button type="button" class="btn btn-success" id="btn_nuevo_vacio">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva característica
                    </button>
                </div>

            </div>
        </div>

    </section>

    <!-- Modal Característica -->
    <div class="modal fade" id="modalCaracteristica" tabindex="-1" aria-labelledby="titulo_modal_caracteristica" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal_caracteristica">
                        <i class="bi bi-bug me-1" aria-hidden="true"></i>Nueva característica
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formulario_caracteristica">
                    <input type="hidden" id="car_modo" value="crear">
                    <input type="hidden" id="car_empresa_pk">
                    <input type="hidden" id="car_codigo_pk">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">

                            <div class="col-md-8">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="car_empresa">Empresa <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-building" aria-hidden="true"></i></span>
                                    <select class="form-select" id="car_empresa" required>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= esc($empresa['id'], 'attr') ?>"><?= esc($empresa['razonSocial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="car_codigo">Código <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control font-monospace" id="car_codigo" inputmode="numeric" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-12" id="car_ayuda_codigo">
                                <div class="form-text mt-0">Consecutivo sugerido. Puede cambiarlo si necesita otro código.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-1 small fw-semibold text-muted" for="car_descripcion">Descripción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" id="car_descripcion" maxlength="500" autocomplete="off" required>
                                </div>
                                <div class="form-text">Nombre de la plaga, enfermedad o estado de la palma. Puede repetirse en grupos distintos.</div>
                            </div>

                            <div class="col-12">
                                <div class="gp-panel-opcion activo" id="car_panel_grupo">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="car_maneja_grupo" checked>
                                        <label class="form-check-label" for="car_maneja_grupo">Asignar grupo característico</label>
                                    </div>
                                    <span class="gp-subtexto">Indica en qué proceso de campo se usa la característica.</span>

                                    <div class="gp-revelar mt-3" id="car_bloque_grupo">
                                        <label class="form-label mb-1 small fw-semibold text-muted" for="car_grupo">Grupo característico <span class="text-danger">*</span></label>
                                        <div class="input-group gp-select-buscador">
                                            <span class="input-group-text bg-light"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                                            <select class="form-select" id="car_grupo">
                                                <option value=""></option>
                                                <?php foreach ($grupos as $grupo): ?>
                                                    <option value="<?= esc($grupo['codigo'], 'attr') ?>"><?= esc($grupo['descripcion']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="gp-nota-alerta mt-3 d-none" id="car_nota_sin_grupo">
                                        <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Sin grupo, la característica no se ofrece en los censos ni en los tratamientos de campo. Hoy todas las características registradas tienen grupo.
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="car_aviso_duplicado">
                                <div class="gp-nota-alerta" id="car_aviso_duplicado_texto"></div>
                            </div>

                            <div class="col-12 d-none" id="car_aviso_llave">
                                <div class="gp-nota">
                                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>La empresa y el código no se pueden modificar.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="car_activo" checked>
                                    <label class="form-check-label" for="car_activo">Activo</label>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_guardar_caracteristica">
                            <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar
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
    const SIGUIENTE_CODIGO = <?= json_encode((string) $siguiente_codigo) ?>;
    const GRUPOS = <?= json_encode(array_map(static fn($g) => ['codigo' => (string) $g['codigo'], 'descripcion' => (string) $g['descripcion']], $grupos)) ?>;
    const VARIANTES = <?= json_encode($variantes ?? []) ?>;
</script>
<script src="<?= base_url('public/assets/js/gestionpalma/caracteristicas_registro.js') ?>"></script>
<?php echo $this->endSection(); ?>
