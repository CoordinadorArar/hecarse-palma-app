<?php echo $this->extend('template/layout'); ?>

<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/maestros.css">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
<?php
$camposParametro = function (string $suf) use ($tipos_dato) {
    $edicion = $suf === '_editar';
?>
<div class="row g-3">

    <div class="col-12">
        <label class="form-label mb-1 small fw-semibold text-muted" for="nombre<?= $suf ?>">Nombre <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-hash" aria-hidden="true"></i></span>
            <input type="text" class="form-control font-monospace<?= $edicion ? ' bg-light' : '' ?>" id="nombre<?= $suf ?>"
                maxlength="150" autocomplete="off" placeholder="nombreTecnicoDelParametro"
                <?= $edicion ? 'readonly tabindex="-1" aria-readonly="true"' : '' ?>>
            <?php if ($edicion): ?>
                <span class="input-group-text bg-light"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
            <?php endif; ?>
            <div class="invalid-feedback"></div>
        </div>
        <div class="form-text"><?= $edicion
            ? 'El nombre identifica el parámetro y no se puede cambiar aquí. Para renombrarlo, cree uno nuevo con el nombre correcto y elimine este.'
            : 'Nombre técnico con el que los procesos leen el parámetro. Debe escribirse exactamente igual.' ?></div>
    </div>

    <div class="col-12">
        <div class="maestros-panel-opcion" id="panel_opcion<?= $suf ?>">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="maneja_ds<?= $suf ?>">
                <label class="form-check-label fw-semibold" for="maneja_ds<?= $suf ?>">Maneja origen de datos</label>
            </div>
            <div class="form-text mb-0" id="ayuda_maneja_ds<?= $suf ?>" aria-live="polite">El valor se escribe a mano.</div>
        </div>
    </div>

    <div class="col-12">
        <div class="maestros-colapso abierto" id="bloque_valor_fijo<?= $suf ?>">
            <label class="form-label mb-1 small fw-semibold text-muted d-block">Tipo de dato</label>
            <?php $i = 0; foreach ($tipos_dato as $clave => $etiqueta): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_dato<?= $suf ?>" id="tipo_dato_<?= $i ?><?= $suf ?>"
                        value="<?= esc($clave, 'attr') ?>" <?= $i === 0 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tipo_dato_<?= $i ?><?= $suf ?>"><?= esc($etiqueta) ?></label>
                </div>
            <?php $i++; endforeach; ?>
            <div class="form-text">Define cómo se valida y se guarda el valor.</div>
        </div>
    </div>

    <div class="col-12">
        <div class="maestros-colapso d-none" id="bloque_origen<?= $suf ?>">
            <div class="row g-3">

                <div class="col-12">
                    <div class="maestros-nota-alerta d-none" role="alert" id="nota_cambio_tabla<?= $suf ?>">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        <span>Cambió la tabla de origen: se limpiaron Campo valor, Campo etiqueta y Valor.</span>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="ds<?= $suf ?>">Tabla <span class="text-danger">*</span></label>
                    <div class="input-group maestros-select-buscador">
                        <span class="input-group-text"><i class="bi bi-table" aria-hidden="true"></i></span>
                        <select class="form-select" id="ds<?= $suf ?>"></select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="form-text" id="ayuda_ds<?= $suf ?>">Cargando tablas disponibles…</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="c_valor<?= $suf ?>">Campo valor <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key" aria-hidden="true"></i></span>
                        <select class="form-select" id="c_valor<?= $suf ?>" disabled></select>
                        <span class="input-group-text maestros-cargando d-none" id="cargando_c_valor<?= $suf ?>">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        </span>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="form-text" id="ayuda_c_valor<?= $suf ?>">Seleccione primero la tabla.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="c_etiqueta<?= $suf ?>">Campo etiqueta <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-tag" aria-hidden="true"></i></span>
                        <select class="form-select" id="c_etiqueta<?= $suf ?>" disabled></select>
                        <span class="input-group-text maestros-cargando d-none" id="cargando_c_etiqueta<?= $suf ?>">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        </span>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="form-text" id="ayuda_c_etiqueta<?= $suf ?>">Seleccione primero la tabla.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label mb-1 small fw-semibold text-muted" for="valor_texto<?= $suf ?>">Valor <span class="text-danger">*</span></label>
        <div class="input-group" id="grupo_valor_texto<?= $suf ?>">
            <span class="input-group-text"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
            <input type="text" class="form-control" id="valor_texto<?= $suf ?>" maxlength="500" autocomplete="off"
                placeholder="Valor que leerán los procesos">
            <div class="invalid-feedback"></div>
        </div>
        <div class="input-group maestros-select-buscador d-none" id="grupo_valor_select<?= $suf ?>">
            <span class="input-group-text"><i class="bi bi-list-check" aria-hidden="true"></i></span>
            <select class="form-select d-none" id="valor_select<?= $suf ?>" aria-label="Valor" disabled></select>
            <div class="invalid-feedback"></div>
        </div>
        <div class="maestros-nota-alerta mt-2 d-none" role="alert" id="nota_huerfano<?= $suf ?>"></div>
        <div class="form-text maestros-nota mt-2 d-none" id="nota_recortada<?= $suf ?>"></div>
        <div class="form-text" id="ayuda_valor<?= $suf ?>">Se guarda tal cual se escribe, según el tipo de dato elegido.</div>
    </div>

</div>
<?php
};
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Parámetros Generales</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('maestros/onboarding/' . $id_loseta), 'attr') ?>">Maestros</a></li>
                <li class="breadcrumb-item">Parámetros administración</li>
                <li class="breadcrumb-item active">Parámetros Generales</li>
            </ol>
        </nav>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3 px-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-5 col-lg-4">
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
                    <div class="form-text">Cada parámetro se guarda para una sola empresa.</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#listaParametros">Lista de parámetros</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#crearParametro">Crear parámetro</a>
        </li>
    </ul>

    <div class="tab-content pt-3">

        <!-- Tab 1: Lista -->
        <div class="tab-pane fade show active" id="listaParametros">
            <div class="card">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <h6 class="mb-0 maestros-titulo-tarjeta"><i class="bi bi-sliders2 me-1" aria-hidden="true"></i>Lista de parámetros</h6>
                    <span id="contador_parametros" class="maestros-contador"></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive" id="contenedor_tabla">
                        <table id="tabla_parametros" class="table table-striped table-hover align-middle mb-0 maestros-tabla maestros-tabla-media" style="width:100%">
                            <thead class="maestros-thead">
                                <tr>
                                    <th style="min-width:260px">Parámetro</th>
                                    <th class="text-center" style="width:130px"
                                        title="Indica si el valor se escribe a mano (Valor fijo) o se elige de una tabla de la base de datos (Tabla)">Origen<i class="bi bi-info-circle ms-1" aria-hidden="true"></i></th>
                                    <th class="text-center" style="width:120px">Tipo de dato</th>
                                    <th style="min-width:260px">Origen de datos</th>
                                    <th style="min-width:200px">Valor</th>
                                    <th class="text-center maestros-col-sticky" style="width:110px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_tabla_parametros">
                                <?php echo $tabla_parametros; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Crear -->
        <div class="tab-pane fade" id="crearParametro">
            <div class="card">
                <div class="card-body p-4">
                    <form id="formulario_creacion" novalidate>
                        <?php $camposParametro(''); ?>
                        <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3">
                            <small class="text-muted"><span class="text-danger">*</span> Campos obligatorios.</small>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" id="btn_limpiar_parametro">
                                    <i class="bi bi-eraser me-1"></i> Limpiar
                                </button>
                                <button type="submit" class="btn btn-primary" id="btn_crear_parametro">
                                    <i class="bi bi-plus-circle me-1"></i> Crear parámetro
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Editar -->
    <div class="modal fade" id="modalEditarParametro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2">
                        <span><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar parámetro</span>
                        <span class="maestros-chip-huerfano d-none" id="chip_huerfano">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Valor no encontrado
                        </span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formulario_editar_parametro" novalidate>
                    <div class="modal-body px-4 py-3">
                        <?php $camposParametro('_editar'); ?>
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
<script src="<?= base_url('public/assets/js/maestros/parametros_generales.js') ?>"></script>
<?php echo $this->endSection(); ?>
