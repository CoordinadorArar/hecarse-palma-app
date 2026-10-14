<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= base_url() ?>public/assets/css/modulos.css">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">
    <!-- Sección de Tabs -->
    <ul class="nav nav-tabs" id="moduleManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="moduleList-tab" data-bs-toggle="tab" href="#moduleList" role="tab" aria-controls="moduleList" aria-selected="true">
                Lista de Módulos
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="createModule-tab" data-bs-toggle="tab" href="#createModule" role="tab" aria-controls="createModule" aria-selected="false">
                Crear Módulo
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="organizeMenu-tab" data-bs-toggle="tab" href="#organizeMenu" role="tab" aria-controls="organizeMenu" aria-selected="false">
                <i class="bi bi-arrows-move me-1"></i>Organizar menú
            </a>
        </li>
    </ul>
    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="moduleManagementTabs">
        <!-- Tab 1: Lista de Módulos -->
        <div class="tab-pane fade show active" id="moduleList" role="tabpanel" aria-labelledby="moduleList-tab">
            <div class="card">
                <div class="card-body">
                    <!-- Agrega un contenedor table-responsive para evitar el desbordamiento -->
                    <div class="table-responsive" id="contenedor_table">
                        <?php echo $tabla_modulos; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Tab 2: Crear Rol -->
        <div class="tab-pane fade" id="createModule" role="tabpanel" aria-labelledby="createModule-tab">
            <div class="card">
                <div class="card-body p-4">
                    <div class="alert alert-light border py-2 small">
                        <strong>Tres niveles.</strong> <strong>Loseta</strong> = tarjeta del menú principal (sin loseta padre). <strong>Módulo</strong> = opción del menú lateral (con loseta padre). <strong>Submódulo</strong> = opción desplegable dentro de un módulo (con loseta y módulo padre).
                    </div>
                    <form id="formulario_creacion">
                        <div class="row mb-4">
                            <!-- Nombre -->
                            <div class="col-6">
                                <label for="nombre_crear" class="form-label">Nombre: (*)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="nombre_crear" placeholder="Digite el nombre del módulo" onkeypress="return noStrangeCharacters(event)">
                                </div>
                            </div>
                            <!-- Ruta -->
                            <div class="col-6">
                                <label for="ruta_crear" class="form-label">Ruta:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="ruta_crear" placeholder="Digite el ruta del módulo">
                                </div>
                                <div class="form-text">Déjala vacía si el módulo solo agrupa submódulos. Si el módulo tiene submódulos, su ruta no se usará: el clic solo desplegará el submenú.</div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <!-- Icono -->
                             <div class="col-6">
                                <label for="icono_crear" class="form-label">Icono: (*)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="icono_crear" placeholder="Digite el icono del módulo, ej: bi bi-gear" onkeypress="return noStrangeCharacters(event)">
                                </div>
                                <div class="form-text">Clase completa de Bootstrap Icons, incluyendo el prefijo. Ej.: <code>bi bi-tree</code>.</div>
                            </div>
                            <div class="col-md-6 d-flex flex-column">
                                <label for="select_loseta_padre" class="form-label">Loseta padre</label>
                                <select class="form-select" id="select_loseta_padre">
                                    <option value="">Seleccionar loseta...</option>
                                    <?php foreach ($losetas as $loseta) :?>
                                        <option value="<?php echo $loseta['Id'];?>"><?php echo $loseta['Nombre'];?></option>
                                    <?php endforeach;?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6 d-flex flex-column">
                                <label for="select_modulo_padre" class="form-label">Módulo padre <span class="text-muted">(opcional)</span></label>
                                <select class="form-select" id="select_modulo_padre" disabled>
                                    <option value="" selected>— Ninguno: será un módulo de primer nivel —</option>
                                </select>
                                <div class="form-text text-muted" id="ayuda_modulo_padre_crear">Un registro sin loseta es una loseta; las losetas no tienen módulo padre.</div>
                            </div>
                            <div class="col-12">
                                <div class="form-text fw-semibold" id="nivel_crear"></div>
                            </div>
                        </div>

                        <!-- Botón de Crear Módulo -->
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-primary w-50 shadow-sm" id="btn_crear_modulo">Crear Módulo</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="organizeMenu" role="tabpanel" aria-labelledby="organizeMenu-tab">
            <?php
            $ordenLosetas = [];
            $ordenModulos = [];
            $ordenSubmodulos = [];
            foreach ($modulos_orden ?? [] as $m) {
                if (empty($m['IdLoseta'])) {
                    $ordenLosetas[] = $m;
                } elseif (empty($m['IdModulo'])) {
                    $ordenModulos[$m['IdLoseta']][] = $m;
                } else {
                    $ordenSubmodulos[$m['IdModulo']][] = $m;
                }
            }
            $filaOrden = function ($m, $nivel, $extra = '') {
                $badges = ['loseta' => ['bg-primary', 'Loseta'], 'modulo' => ['bg-info text-dark', 'Módulo'], 'submodulo' => ['bg-light text-dark border', 'Submódulo']];
                $icono = trim((string) $m['Icono']) !== '' ? esc($m['Icono'], 'attr') : 'bi bi-square text-muted';
                return '<div class="orden-fila d-flex align-items-center gap-2">'
                    . '<span class="orden-handle" title="Arrastrar para reordenar"><i class="bi bi-grip-vertical"></i></span>'
                    . '<i class="' . $icono . ' orden-icono"></i>'
                    . '<span class="orden-nombre text-truncate' . ($nivel === 'loseta' ? ' fw-semibold' : '') . '">' . esc($m['Nombre']) . '</span>'
                    . '<span class="badge ' . $badges[$nivel][0] . ' d-none d-sm-inline-block">' . $badges[$nivel][1] . '</span>'
                    . ($m['FechaFinalizacion'] !== null ? '<span class="badge bg-secondary-subtle text-secondary-emphasis border">Inactivo</span>' : '')
                    . $extra . '</div>';
            };
            $claseItem = fn ($m, $nivel) => 'orden-item orden-item-' . $nivel . ($m['FechaFinalizacion'] !== null ? ' inactivo' : '');
            ?>
            <div class="card">
                <div class="card-body p-4">
                    <div class="alert alert-light border py-2 small mb-3">
                        <i class="bi bi-info-circle text-primary me-1"></i><strong>Arrastra</strong> desde el icono <i class="bi bi-grip-vertical"></i> para cambiar el orden. Las losetas y los módulos se reordenan dentro de su mismo grupo. Los <strong>submódulos</strong> también pueden moverse a otro módulo, incluso de otra loseta, siempre que ese módulo sea contenedor (sin ruta o con submódulos); al arrastrar verás resaltadas las zonas donde puedes soltar. Los cambios se guardan automáticamente.
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <span id="orden_estado" class="small text-muted"></span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_expandir_todo"><i class="bi bi-arrows-expand me-1"></i>Expandir todo</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_contraer_todo"><i class="bi bi-arrows-collapse me-1"></i>Contraer todo</button>
                        </div>
                    </div>
                    <div id="orden_arbol">
                        <ul class="orden-lista orden-losetas">
                            <?php foreach ($ordenLosetas as $i => $loseta) : ?>
                                <?php $hijos = $ordenModulos[$loseta['Id']] ?? []; ?>
                                <li class="<?= $claseItem($loseta, 'loseta') ?>" data-id="<?= esc($loseta['Id'], 'attr') ?>">
                                    <?= $filaOrden($loseta, 'loseta', $hijos ? '<span class="text-muted small ms-auto d-none d-sm-inline">' . count($hijos) . ' módulo(s)</span><button type="button" class="btn btn-sm btn-link text-secondary orden-toggle" data-bs-toggle="collapse" data-bs-target="#orden_modulos_' . esc($loseta['Id'], 'attr') . '" aria-controls="orden_modulos_' . esc($loseta['Id'], 'attr') . '" aria-expanded="' . ($i === 0 ? 'true' : 'false') . '"><i class="bi bi-chevron-down"></i></button>' : '') ?>
                                    <?php if ($hijos) : ?>
                                        <ul class="orden-lista orden-modulos collapse<?= $i === 0 ? ' show' : '' ?>" id="orden_modulos_<?= esc($loseta['Id'], 'attr') ?>">
                                            <?php foreach ($hijos as $modulo) : ?>
                                                <?php $subs = $ordenSubmodulos[$modulo['Id']] ?? []; ?>
                                                <?php $contenedor = $subs || trim((string) $modulo['Ruta']) === ''; ?>
                                                <li class="<?= $claseItem($modulo, 'modulo') ?>" data-id="<?= esc($modulo['Id'], 'attr') ?>"<?= $contenedor ? ' data-contenedor="1"' : '' ?>>
                                                    <?= $filaOrden($modulo, 'modulo') ?>
                                                    <?php if ($contenedor) : ?>
                                                        <ul class="orden-lista orden-submodulos<?= $subs ? '' : ' orden-vacia' ?>">
                                                            <?php foreach ($subs as $submodulo) : ?>
                                                                <li class="<?= $claseItem($submodulo, 'submodulo') ?>" data-id="<?= esc($submodulo['Id'], 'attr') ?>">
                                                                    <?= $filaOrden($submodulo, 'submodulo') ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <p class="orden-sin-modulos text-muted small fst-italic mb-0">Sin módulos</p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- MODAL DE EDICION DE MODULO -->
    <div class="modal fade" id="editModuloModal" tabindex="-1" aria-labelledby="editModuloModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModuloModalLabel">Modificar Módulo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border py-2 small">
                        <strong>Tres niveles.</strong> <strong>Loseta</strong> = tarjeta del menú principal (sin loseta padre). <strong>Módulo</strong> = opción del menú lateral (con loseta padre). <strong>Submódulo</strong> = opción desplegable dentro de un módulo (con loseta y módulo padre).
                    </div>
                    <!-- Custom Styled Validation -->
                    <form class="row g-3" id="formulario_editar_modulo">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                <input type="text" class="form-control" id="nombre" value="" onkeypress="return noStrangeCharacters(event)" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="ruta" class="form-label">Ruta</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-link"></i></span>
                                <input type="text" class="form-control" id="ruta" value="">
                            </div>
                            <div class="form-text">Déjala vacía si el módulo solo agrupa submódulos. Si el módulo tiene submódulos, su ruta no se usará: el clic solo desplegará el submenú.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="icono" class="form-label">Icono</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                <input type="text" class="form-control" id="icono" value="" onkeyup="printIcon(this)" onkeypress="return noStrangeCharacters(event)" required>
                                <span class="input-group-text" id="IconoImage">-</span>
                            </div>
                            <div class="form-text">Clase completa de Bootstrap Icons, incluyendo el prefijo. Ej.: <code>bi bi-tree</code>.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="losetaPadre" class="form-label">Loseta padre</label>
                            <select class="form-select" id="losetaPadre">
                                <option value="">Seleccionar loseta...</option>
                                <?php foreach ($losetas as $loseta) :?>
                                    <option value="<?php echo $loseta['Id'];?>"><?php echo $loseta['Nombre'];?></option>
                                <?php endforeach;?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="moduloPadre" class="form-label">Módulo padre <span class="text-muted">(opcional)</span></label>
                            <select class="form-select" id="moduloPadre" disabled>
                                <option value="" selected>— Ninguno: será un módulo de primer nivel —</option>
                            </select>
                            <div class="form-text text-muted" id="ayuda_modulo_padre">Un registro sin loseta es una loseta; las losetas no tienen módulo padre.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-text fw-semibold" id="nivel_editar"></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="" id="invalidCheck">
                                <label class="form-check-label" for="invalidCheck">Activo</label>
                            </div>
                        </div>

                        <input type="hidden" id="IdModulo" value="">

                        <div class="col-12 text-center">
                            <button class="btn btn-primary" type="submit" id="btn_actualizar_modulo">Guardar</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </form><!-- End Custom Styled Validation -->
                </div>
            </div>
        </div>
    </div>
</main>

<?php echo $this->endSection(); ?>
<!-- FIN SECCION PRINCIPAL -->

<!-- SECCION DE SCRIPTS -->
<?php echo $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    /**
     * Configuración de Toast de Sweetalert.
     */
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });

    const OPCION_NINGUNO = `<option value="" selected>— Ninguno: será un módulo de primer nivel —</option>`;

    const AYUDA_SIN_LOSETA  = 'Un registro sin loseta es una loseta; las losetas no tienen módulo padre.';
    const AYUDA_SIN_MODULOS = 'Esta loseta aún no tiene módulos; el registro quedará como módulo de primer nivel.';
    const AYUDA_MODULO      = 'Vacío = módulo de primer nivel. Si eliges un módulo, este registro será un submódulo y aparecerá desplegable dentro de él.';

    let arbolDesactualizado = false;

    const cfgCrear = {
        loseta: '#select_loseta_padre',
        modulo: '#select_modulo_padre',
        ayuda: '#ayuda_modulo_padre_crear',
        nivel: '#nivel_crear',
        nombre: '#nombre_crear'
    };

    const cfgEditar = {
        loseta: '#losetaPadre',
        modulo: '#moduloPadre',
        ayuda: '#ayuda_modulo_padre',
        nivel: '#nivel_editar',
        nombre: '#nombre',
        excluir: () => $('#IdModulo').val()
    };

    const renderizarTabla = () => {
        if ($.fn.DataTable.isDataTable('#tabla_modulos')) {
            $('#tabla_modulos').DataTable().destroy();
        }

        const tabla = $('#tabla_modulos').DataTable({
            responsive: true,
            columnDefs: [
                { targets: 0, visible: false, searchable: false },
                { targets: 3, visible: false },
                { targets: 7, orderable: false }
            ],
            order: [[0, 'asc']],
            orderFixed: [[3, 'asc']],
            rowGroup: {
                dataSrc: 3,
                startRender: function (rows, group) {
                    return $('<tr class="grupo-loseta"><td colspan="6" style="font-weight:600"></td></tr>')
                        .find('td')
                        .text(group)
                        .append($('<span class="text-muted ms-2"></span>').text(`${rows.count()} registro(s)`))
                        .end();
                }
            },
            language: {
                "lengthMenu": "Mostrar _MENU_ registros por página",
                "zeroRecords": "No se encontraron resultados",
                "info": "Mostrando página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay registros disponibles",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "search": "Buscar:",
                "loadingRecords": "Cargando...",
                "processing": "Procesando...",
                "emptyTable": "No hay datos disponibles en la tabla",
                "thousands": ",",
                "decimal": ".",
                "infoPostFix": "",
                "aria": {
                    "sortAscending": ": activar para ordenar la columna ascendente",
                    "sortDescending": ": activar para ordenar la columna descendente"
                }
            }
        });

        $('#tabla_modulos_filter').append(` <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="btn_orden_jerarquico">Restablecer orden jerárquico</button>`);
        $('#btn_orden_jerarquico').on('click', () => tabla.order([[0, 'asc']]).draw());
    }

    /**
     *
     */
    document.addEventListener('DOMContentLoaded', function() {
        renderizarTabla();
        actualizarNivel(cfgCrear);
        inicializarOrden();

        if (location.hash === '#organizeMenu') {
            bootstrap.Tab.getOrCreateInstance(document.querySelector('#organizeMenu-tab')).show();
            history.replaceState(null, '', location.pathname + location.search);
        }
    });

    /**Método para imprimir el icono en la vista */
    const printIcon = (input) => {
        let icono = input.value.trim();
        $('#IconoImage').html(icono !== '' ? `<i class="${icono}"></i>` : '-');
    }

    /**
     * Metodo para recargar los modulos de una loseta dentro de un select.
     */
    const cargarModulosLoseta = async (idLoseta, selector, excluirId) => {
        const $select = $(selector);
        $select.html(OPCION_NINGUNO).prop('disabled', true);

        if (idLoseta === null || idLoseta === undefined || idLoseta === '') {
            return [];
        }

        const formData = new FormData();
        formData.append('IdLoseta', idLoseta);

        try {
            const response = await fetch(`<?= base_url() ?>admin/modulos/obtenerModulosLoseta`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            let modulos = [];

            if (result.success) {
                modulos = result.modulos.filter(item => !excluirId || String(item.Id) !== String(excluirId));

                let html = OPCION_NINGUNO;
                modulos.forEach(item => {
                    html += `<option value="${item.Id}">${item.Nombre}</option>`;
                });

                $select.html(html);
            }

            $select.prop('disabled', false);
            return modulos;

        } catch (error) {
            console.log('Se ha producido un error: ', error);
            $select.prop('disabled', false);
            return [];
        }
    }

    /**
     * Metodo que muestra el nivel resultante segun los padres seleccionados.
     */
    const actualizarNivel = (cfg) => {
        const $loseta = $(cfg.loseta);
        const $modulo = $(cfg.modulo);
        const nombre = ($(cfg.nombre).val() || '').trim() || 'Nuevo';

        let texto;

        if (!$loseta.val()) {
            texto = '[Loseta] Este registro será una tarjeta del menú principal.';
        } else if (!$modulo.val()) {
            texto = `[Módulo] ${$loseta.find('option:selected').text().trim()} › ${nombre}`;
        } else {
            texto = `[Submódulo] ${$loseta.find('option:selected').text().trim()} › ${$modulo.find('option:selected').text().trim()} › ${nombre}`;
        }

        $(cfg.nivel).text(texto);
    }

    /**
     * Metodo que sincroniza el select de modulo padre con la loseta elegida.
     */
    const sincronizarPadres = async (cfg) => {
        const idLoseta = $(cfg.loseta).val();
        const modulos = await cargarModulosLoseta(idLoseta, cfg.modulo, cfg.excluir ? cfg.excluir() : null);

        let ayuda = AYUDA_MODULO;

        if (!idLoseta) {
            ayuda = AYUDA_SIN_LOSETA;
        } else if (modulos.length === 0) {
            ayuda = AYUDA_SIN_MODULOS;
        }

        $(cfg.ayuda).text(ayuda);
        actualizarNivel(cfg);

        return modulos;
    }

    /**
     * Metodo para obtener la informacion de un modulo en especifico
     * cuando se abre el modal de edicion.
     */
    const obtenerModulo = async (id) => {
        document.querySelector('#formulario_editar_modulo').reset();
        $('#moduloPadre').html(OPCION_NINGUNO).prop('disabled', true);

        try {
            const response = await fetch(`<?= base_url() ?>admin/modulos/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                $('#IdModulo').val(result.modulo.Id);
                $('#nombre').val(result.modulo.Nombre);
                $('#ruta').val(result.modulo.Ruta);
                $('#icono').val(result.modulo.Icono);
                printIcon(document.querySelector('#icono'));

                $('#losetaPadre').val(result.modulo.IdLoseta != null ? result.modulo.IdLoseta : '');

                await sincronizarPadres(cfgEditar);

                if (result.modulo.IdModulo) {
                    $('#moduloPadre').val(result.modulo.IdModulo);
                    actualizarNivel(cfgEditar);
                }

                $('#invalidCheck').prop('checked', result.modulo.FechaFinalizacion === null);
            }

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * Metodo para guardar la informacion actualizada del módulo.
     */
    const updateModule = async () => {
        $('#btn_actualizar_modulo').prop('disabled', true);

        let formData = new FormData();
        formData.append('Id', $('#IdModulo').val());
        formData.append('Nombre', $('#nombre').val());
        formData.append('Ruta', $('#ruta').val());
        formData.append('Icono', $('#icono').val());
        formData.append('IdLoseta', $('#losetaPadre').val());
        formData.append('IdModulo', $('#moduloPadre').val());
        formData.append('FechaFinalizacion', $('#invalidCheck').prop('checked') ? 1 : 0);

        try {
            let response = await fetch(`<?= base_url() ?>admin/modulos/actualizarModulo`, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const error = await response.json();
                Toast.fire({ icon: 'error', title: error.message });
                $('#btn_actualizar_modulo').prop('disabled', false);
                return;
            }

            let result = await response.text();

            $('#editModuloModal').modal('hide');

            Toast.fire({
                icon: 'success',
                title: 'Módulo actualizado correctamente.'
            });

            $('#contenedor_table').html(result);
            $('#btn_actualizar_modulo').prop('disabled', false);
            renderizarTabla();
            arbolDesactualizado = true;

        } catch (error) {
            console.log('Se ha producido un error: ', error);
            $('#btn_actualizar_modulo').prop('disabled', false);
            Toast.fire({ icon: 'error', title: 'No se pudo actualizar el módulo.' });
        }
    }

    /**
     * Metodo para crear un nuevo modulo.
     */
    const crearModulo = async () => {
        $('#btn_crear_modulo').prop('disabled', true);

        const formData = new FormData();
        formData.append('Nombre', $('#nombre_crear').val());
        formData.append('Ruta', $('#ruta_crear').val());
        formData.append('Icono', $('#icono_crear').val());
        formData.append('IdLosetaPadre', $('#select_loseta_padre').val());
        formData.append('IdModuloPadre', $('#select_modulo_padre').val());

        try {
            const response = await fetch(`<?= base_url() ?>admin/modulos/crearModulo`, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const error = await response.json();
                Toast.fire({ icon: 'error', title: error.message });
                $('#btn_crear_modulo').prop('disabled', false);
                return;
            }

            const result = await response.text();

            Toast.fire({
                icon: 'success',
                title: 'Módulo creado correctamente.'
            });

            $('#formulario_creacion').trigger('reset');
            $('#contenedor_table').html(result);
            $('#btn_crear_modulo').prop('disabled', false);
            renderizarTabla();
            sincronizarPadres(cfgCrear);
            arbolDesactualizado = true;

        } catch (error) {
            console.log('Se ha producido un error: ', error);
            $('#btn_crear_modulo').prop('disabled', false);
            Toast.fire({ icon: 'error', title: 'No se pudo crear el módulo.' });
        }
    }

    /**
     * Metodo para guardar el orden de un grupo de hermanos.
     */
    const guardarOrden = async (sortable, lista, previo) => {
        const $estado = $('#orden_estado');
        $estado.attr('class', 'small text-muted').html('<span class="spinner-border spinner-border-sm text-primary me-1"></span> Guardando…');
        sortable.option('disabled', true);
        lista.classList.add('orden-guardando');

        const formData = new FormData();
        sortable.toArray().forEach(id => formData.append('Ids[]', id));

        try {
            const response = await fetch(`<?= base_url() ?>admin/modulos/actualizarOrden`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message);
            }

            $estado.attr('class', 'small text-success').html('<i class="bi bi-check-circle me-1"></i>Orden guardado');
            Toast.fire({ icon: 'success', title: 'Orden guardado.' });
            $('#contenedor_table').html(result.tabla);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
            sortable.sort(previo, true);
            const mensaje = error.message || 'No se pudo guardar el orden.';
            $estado.attr('class', 'small text-danger').html('<i class="bi bi-exclamation-circle me-1"></i>').append(document.createTextNode(mensaje));
            Toast.fire({ icon: 'error', title: mensaje });
        }

        sortable.option('disabled', false);
        lista.classList.remove('orden-guardando');
    }

    /**
     * Metodo para mover un submodulo a otro modulo contenedor.
     */
    const moverSubmodulo = async (evt, marcarVacias) => {
        const $estado = $('#orden_estado');
        const listas = [evt.from, evt.to];
        const destino = evt.to.closest('.orden-item-modulo').querySelector(':scope > .orden-fila .orden-nombre').textContent;
        $estado.attr('class', 'small text-muted').html('<span class="spinner-border spinner-border-sm text-primary me-1"></span> Guardando…');
        listas.forEach(l => l.classList.add('orden-guardando'));

        const formData = new FormData();
        formData.append('Id', evt.item.dataset.id);
        formData.append('IdModulo', evt.to.closest('.orden-item-modulo').dataset.id);
        Sortable.get(evt.to).toArray().forEach(id => formData.append('IdsDestino[]', id));
        Sortable.get(evt.from).toArray().forEach(id => formData.append('IdsOrigen[]', id));

        try {
            const response = await fetch(`<?= base_url() ?>admin/modulos/moverSubmodulo`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message);
            }

            $estado.attr('class', 'small text-success').html('<i class="bi bi-check-circle me-1"></i>').append(document.createTextNode(`Submódulo movido a «${destino}»`));
            Toast.fire({ icon: 'success', title: 'Submódulo movido.' });
            $('#contenedor_table').html(result.tabla);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
            marcarVacias();
            const mensaje = error.message || 'No se pudo mover el submódulo.';
            $estado.attr('class', 'small text-danger').html('<i class="bi bi-exclamation-circle me-1"></i>').append(document.createTextNode(mensaje));
            Toast.fire({ icon: 'error', title: mensaje });
        }

        listas.forEach(l => l.classList.remove('orden-guardando'));
    }

    /**
     * Metodo que inicializa el arbol de ordenamiento del menu.
     */
    const inicializarOrden = () => {
        const arbol = document.querySelector('#orden_arbol');
        const subListas = () => arbol.querySelectorAll('.orden-submodulos');
        const marcarVacias = () => subListas().forEach(ul => ul.classList.toggle('orden-vacia', !ul.children.length));

        arbol.querySelectorAll('.orden-lista').forEach((lista, i) => {
            let previo = [];
            const esSub = lista.classList.contains('orden-submodulos');
            const sortable = Sortable.create(lista, {
                group: esSub ? { name: 'orden-sub', pull: true, put: true } : { name: `orden-${i}`, pull: false, put: false },
                handle: '.orden-handle',
                dataIdAttr: 'data-id',
                animation: 150,
                ghostClass: 'orden-ghost',
                chosenClass: 'orden-chosen',
                dragClass: 'orden-drag',
                forceFallback: true,
                fallbackOnBody: true,
                fallbackClass: 'orden-drag',
                delay: 150,
                delayOnTouchOnly: true,
                fallbackTolerance: 3,
                onStart: () => {
                    previo = sortable.toArray();
                    arbol.classList.add('orden-arrastrando');
                    if (esSub) {
                        arbol.classList.add('orden-arrastrando-sub');
                        subListas().forEach(ul => ul.classList.add('orden-destino'));
                    }
                },
                onMove: (evt) => {
                    if (!esSub) return;
                    arbol.querySelectorAll('.orden-destino-activo').forEach(ul => ul !== evt.to && ul.classList.remove('orden-destino-activo'));
                    evt.to.classList.add('orden-destino-activo');
                },
                onAdd: marcarVacias,
                onRemove: marcarVacias,
                onEnd: (evt) => {
                    arbol.classList.remove('orden-arrastrando', 'orden-arrastrando-sub');
                    subListas().forEach(ul => ul.classList.remove('orden-destino', 'orden-destino-activo'));
                    marcarVacias();
                    if (evt.from !== evt.to) {
                        moverSubmodulo(evt, marcarVacias);
                    } else if (evt.oldIndex !== evt.newIndex) {
                        guardarOrden(sortable, lista, previo);
                    }
                }
            });
        });

        const alternarTodo = (mostrar) => arbol.querySelectorAll('.orden-modulos').forEach(el => {
            const collapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            mostrar ? collapse.show() : collapse.hide();
        });

        $('#btn_expandir_todo').on('click', () => alternarTodo(true));
        $('#btn_contraer_todo').on('click', () => alternarTodo(false));
    }

    /**
     *
     */
    $('#select_loseta_padre').on('change', () => sincronizarPadres(cfgCrear));
    $('#select_modulo_padre').on('change', () => actualizarNivel(cfgCrear));
    $('#nombre_crear').on('keyup', () => actualizarNivel(cfgCrear));

    $('#losetaPadre').on('change', () => sincronizarPadres(cfgEditar));
    $('#moduloPadre').on('change', () => actualizarNivel(cfgEditar));
    $('#nombre').on('keyup', () => actualizarNivel(cfgEditar));

    $('#organizeMenu-tab').on('show.bs.tab', () => {
        if (arbolDesactualizado) {
            location.hash = '#organizeMenu';
            location.reload();
        }
    });

    document.querySelector('#formulario_creacion').addEventListener('submit', (e) => {
        e.preventDefault();
        crearModulo();
    });
    document.querySelector('#formulario_editar_modulo').addEventListener('submit', (e) => {
        e.preventDefault();
        updateModule();
    });
</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->
