<!-- PLANTILLA BASE -->
<?php echo $this->extend('template/layout'); ?>

<!-- SECCION DE CSS -->
<?php echo $this->section('css'); ?>
<link rel="stylesheet" href="<?= asset_url('public/assets/css/gestionpalma.css') ?>">
<link rel="stylesheet" href="<?= asset_url('public/assets/css/roles.css') ?>">
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE CSS -->

<!-- SECCION PRINCIPAL -->
<?php echo $this->section('contenido'); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Roles</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= esc(base_url('losetas'), 'attr') ?>">Menú principal</a></li>
                <li class="breadcrumb-item"><a href="<?= esc(base_url('admin/onboarding/' . $id_loseta), 'attr') ?>">Administración</a></li>
                <li class="breadcrumb-item active">Roles</li>
            </ol>
        </nav>
    </div>
    <!-- Sección de Tabs -->
    <ul class="nav nav-tabs" id="rolManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="rolList-tab" data-bs-toggle="tab" href="#rolList" role="tab" aria-controls="rolList" aria-selected="true">
                Lista de Roles
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="createRol-tab" data-bs-toggle="tab" href="#createRol" role="tab" aria-controls="createRol" aria-selected="false">
                Crear Rol
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="assignModules-tab" data-bs-toggle="tab" href="#assignModules" role="tab" aria-controls="assignModules" aria-selected="false">
                Asignar Módulos
            </a>
        </li>
    </ul>
    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="rolManagementTabsContent">
        <!-- Tab 1: Lista de Roles -->
        <div class="tab-pane fade show active" id="rolList" role="tabpanel" aria-labelledby="rolList-tab">
            <div class="card">
                <div class="card-body">
                    <div id="contenedor_table">
                        <?php echo $tabla_roles; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Tab 2: Crear Rol -->
        <div class="tab-pane fade" id="createRol" role="tabpanel" aria-labelledby="createRol-tab">
            <div class="card">
                <div class="card-body p-4">
                    <form id="formulario_creacion">
                        <div class="row mb-4">
                            <!-- Nombre -->
                            <div class="col-6">
                                <label for="nombre_crear" class="form-label">Nombre:</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="nombre_crear" placeholder="Digite el nombre del rol" onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Crear Rol -->
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-primary w-50 shadow-sm" id="btn_crear_rol">Crear Rol</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Tab 3: Asignar módulos y/o permisos -->
         <div class="tab-pane fade" id="assignModules" role="tabpanel" aria-labelledby="assignModules-tab">
            <div class="card">
                <div class="card-body mt-3">
                    <!-- Sección de Consulta de Rol -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="select_rol" class="form-label fw-semibold">Rol</label>
                            <div class="input-group gp-select-buscador">
                                <span class="input-group-text bg-light"><i class="bi bi-person-badge"></i></span>
                                <select id="select_rol" class="form-control">

                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="select_inicio" class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                <span>Vista de inicio</span>
                                <span id="inicio_estado" class="rl-estado fw-normal" aria-live="polite"></span>
                            </label>
                            <div id="inicio_bloque">
                                <div class="input-group gp-select-buscador">
                                    <span class="input-group-text bg-light"><i class="bi bi-house-door"></i></span>
                                    <select id="select_inicio" class="form-control" disabled>
                                        <option value="">Seleccione un rol primero</option>
                                    </select>
                                </div>
                                <div id="inicio_aviso"></div>
                                <small class="gp-subtexto mt-1">Si un usuario tiene varios roles con vistas de inicio distintas, o alguno sin vista, entra al Menú principal.</small>
                            </div>
                            <div id="inicio_no_disponible" class="gp-nota-alerta d-none">
                                <i class="bi bi-exclamation-triangle me-1"></i>Falta ejecutar el script de base de datos de vista de inicio. Mientras tanto, todos los roles entran al Menú principal.
                            </div>
                            <div id="inicio_error" class="gp-ppto-metas-error mb-0 d-none">
                                <span><i class="bi bi-exclamation-circle me-1"></i>No se pudieron cargar las opciones.</span>
                                <button type="button" class="btn btn-link btn-sm p-0" onclick="cargarInicio()">Reintentar</button>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Modulos por Rol -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="gp-seccion mb-3"><i class="bi bi-check2-square"></i>Módulos asignados</h6>
                            <ul class="list-group rl-lista" id="rolModulesList">
                                <li class="list-group-item text-muted small">Seleccione el rol para ver los módulos asignados.</li>
                            </ul>
                        </div>

                        <div class="col-md-6">
                            <h6 class="gp-seccion mb-3"><i class="bi bi-plus-square"></i>Módulos disponibles</h6>
                            <ul class="list-group rl-lista" id="availableRolModulesList">
                                <li class="list-group-item text-muted small">Seleccione el rol para ver los módulos que no tiene asignados.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- MODAL DE EDICION DE ROL -->
    <div class="modal fade" id="editRolModal" tabindex="-1" aria-labelledby="editRolModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRolModalLabel">Modificar Rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Custom Styled Validation -->
                    <form class="row g-3" id="formulario_editar_rol">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                                <input type="text" class="form-control" id="nombre" value="" onkeypress="return noStrangeCharacters(event)" onpaste="return false">
                            </div>
                        </div>
                        <div class="col-1"></div>
                        <div class="col-4">
                            <label class="form-label" for="invalidCheck">Activar/Inactivar</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="invalidCheck">
                            </div>
                        </div>

                        <input type="hidden" id="IdRol" value="">

                        <div class="col-12 text-center">
                            <button class="btn btn-primary" type="submit" id="btn_actualizar_rol">Guardar</button>
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
<script>
    /**
     * Propiedad para almacenar el módulo del rol seleccionado
     * para gestionar sus módulos.
     */
    let rolId;

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

    /**
     * Metodo para guardar la informacion actualizada del rol.
     */
    const updateRol = async () => {
        $('#btn_actualizar_rol').prop('disabled', true);

        const formData = new FormData();
        formData.append('Id', $('#IdRol').val());
        formData.append('Nombre', $('#nombre').val());
        formData.append('FechaFinalizacion', $('#invalidCheck').prop('checked') ? 1 : 0);

        try {
            const response = await fetch(`<?= base_url() ?>admin/roles/actualizarRol`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            $('#editRolModal').modal('hide');

            Toast.fire({
                icon: 'success',
                title: 'Rol actualizado correctamente.'
            });

            $('#contenedor_table').html(result);
            $('#btn_actualizar_rol').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * Metodo para obtener la informacion de un rol en especifico
     * cuando se abre el modal de edicion.
     */
    const obtenerRol = async (id) => {
        document.querySelector('#formulario_editar_rol').reset();

        try {
            const response = await fetch(`<?= base_url() ?>admin/roles/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                $('#IdRol').val(result.rol.Id);
                $('#nombre').val(result.rol.Nombre);
                $('#invalidCheck').prop('checked', result.rol.FechaFinalizacion === null);
            }

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    const renderizarTabla = () => {
        if ($.fn.DataTable.isDataTable('#tabla_roles')) {
            $('#tabla_roles').DataTable().destroy();
        }

        $('#tabla_roles').DataTable();
    }

    /**
     * Metodo para crear un nuevo rol.
     */
    const crearRol = async () => {
        $('#btn_crear_rol').prop('disabled', true);

        const formData = new FormData();
        formData.append('Nombre', $('#nombre_crear').val());

        try {
            const response = await fetch(`<?= base_url() ?>admin/roles/crearRol`, {
                method: 'POST',
                body: formData
            });

            const result = await response.text();

            Toast.fire({
                icon: 'success',
                title: 'Rol creado correctamente.'
            });

            $('#formulario_creacion').trigger('reset');
            $('#contenedor_table').html(result);
            $('#btn_crear_rol').prop('disabled', false);
            renderizarTabla();

        } catch (error) {
            console.log('Se ha producido un error: ', error);
        }
    }

    /**
     * Metodo para renderizar el listado de módulos que un rol tiene asignados.
     * Tambien se renderizan los módulos que no tiene asignados.
     * 
     * @param {object} modulos Listado de módulos asignados y no asignados.
     */
    const renderizarModulosRol = (modulos) => {
        document.querySelector('#rolModulesList').innerHTML =
            modulos.asignados == '' ? '<li class="list-group-item text-muted small">No tiene ningún módulo asignado.</li>' : modulos.asignados;

        document.querySelector('#availableRolModulesList').innerHTML =
            modulos.todos == '' ? '<li class="list-group-item text-muted small">Tiene todos los módulos asignados.</li>' : modulos.todos;

        marcarInicio();
    }

    const inicio = { valor: '', guardado: '' };
    const $inicio = $('#select_inicio');
    let tGuardado;

    const escHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const verInicio = (vista) => {
        $('#inicio_bloque').toggleClass('d-none', vista !== 'bloque');
        $('#inicio_no_disponible').toggleClass('d-none', vista !== 'nodisp');
        $('#inicio_error').toggleClass('d-none', vista !== 'error');
    };

    const opcionesInicio = (html, deshabilitado) => {
        $inicio.html(html).prop('disabled', deshabilitado).trigger('change.select2');
    };

    const opcionInicio = (valor, texto, icono, ruta, tipo) =>
        `<option value="${valor}" data-icono="${escHtml(icono || 'bi bi-window')}" data-ruta="${escHtml(ruta)}" data-tipo="${tipo}">${escHtml(texto)}</option>`;

    const plantillaInicio = (seleccion) => (d) => {
        const el = d.element;
        if (!el || !el.dataset || !el.dataset.icono) return d.text;
        const chip = !seleccion && el.dataset.tipo === 'loseta' ? '<span class="gp-chip-fila gris ms-2">Loseta</span>' : '';
        return $(`<span class="rl-opcion"><i class="${escHtml(el.dataset.icono)} me-2 text-muted"></i>${escHtml(seleccion ? el.dataset.ruta : d.text)}${chip}</span>`);
    };

    const estadoGuardado = (estado) => {
        clearTimeout(tGuardado);
        $('#inicio_estado').html(
            estado === 'guardando' ? '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…</span>' :
            estado === 'ok' ? '<span class="text-success"><i class="bi bi-check2 me-1"></i>Guardado</span>' : ''
        );
        if (estado === 'ok') tGuardado = setTimeout(() => estadoGuardado(''), 2000);
    };

    const marcarInicio = () => {
        document.querySelectorAll('#rolModulesList .rl-chip-inicio').forEach((c) => c.remove());
        const li = inicio.valor !== '' && document.querySelector(`#rolModulesList li[id="${inicio.valor}"]`);
        if (!li) return;
        const btn = li.querySelector('button');
        let span = li.querySelector('.rl-nombre');
        if (!span) {
            span = document.createElement('span');
            span.className = 'rl-nombre';
            while (li.firstChild && li.firstChild !== btn) span.appendChild(li.firstChild);
            li.insertBefore(span, btn);
        }
        span.insertAdjacentHTML('beforeend', '<span class="gp-chip-fila verde ms-2 rl-chip-inicio"><i class="bi bi-house-door me-1"></i>Inicio</span>');
    };

    const resetInicio = () => {
        inicio.valor = '';
        inicio.guardado = '';
        estadoGuardado('');
        verInicio('bloque');
        $('#inicio_aviso').html('');
        opcionesInicio('<option value="">Seleccione un rol primero</option>', true);
    };

    const cargarInicio = async (notificar = false) => {
        const id = rolId;
        if (!id) return resetInicio();
        const previo = inicio.valor;
        verInicio('bloque');
        $('#inicio_aviso').html('');
        opcionesInicio('<option value="">Cargando opciones…</option>', true);

        try {
            const formData = new FormData();
            formData.append('id_rol', id);

            const response = await fetch('<?= base_url() ?>admin/roles/opcionesInicio', {
                method: 'POST',
                body: formData
            });
            const result = await response.json().catch(() => null);
            if (id !== rolId) return;
            if (!response.ok || !result || !result.success) throw new Error();

            if (!result.disponible) {
                inicio.valor = '';
                inicio.guardado = '';
                marcarInicio();
                return verInicio('nodisp');
            }

            const opciones = result.opciones || [];
            const valida = result.actual !== null && opciones.some((o) => o.id === result.actual);
            const grupos = new Map();
            opciones.forEach((o) => {
                if (!grupos.has(o.grupo)) grupos.set(o.grupo, []);
                grupos.get(o.grupo).push(o);
            });

            let html = opcionInicio('', 'Menú principal (por defecto)', 'bi bi-grid-3x3-gap', 'Menú principal', 'menu');
            grupos.forEach((items, grupo) => {
                html += `<optgroup label="${escHtml(grupo)}">` + items.map((o) => o.tipo === 'loseta'
                    ? opcionInicio(o.id, `Página de inicio de ${o.nombre}`, o.icono, o.nombre, 'loseta')
                    : opcionInicio(o.id, o.nombre, o.icono, `${o.grupo} › ${o.nombre}`, 'modulo')).join('') + '</optgroup>';
            });

            inicio.valor = valida ? String(result.actual) : '';
            inicio.guardado = result.actual === null ? '' : String(result.actual);
            opcionesInicio(html, opciones.length === 0);
            $inicio.val(inicio.valor).trigger('change.select2');

            if (!opciones.length) {
                $('#inicio_aviso').html('<small class="gp-subtexto mt-1">Asigne módulos al rol para elegir otra vista de inicio.</small>');
            } else if (result.actual !== null && !valida) {
                $('#inicio_aviso').html('<div class="gp-nota-alerta mt-2"><i class="bi bi-exclamation-triangle me-1"></i>La vista configurada ya no está asignada al rol; sus usuarios entran al Menú principal.</div>');
            }

            if (notificar && previo !== '' && inicio.valor === '') {
                Toast.fire({
                    icon: 'info',
                    title: 'La vista de inicio se restableció a Menú principal.'
                });
            }

            marcarInicio();
        } catch (error) {
            if (id !== rolId) return;
            console.error('Error al cargar las opciones de inicio:', error);
            verInicio('error');
        }
    };

    const guardarInicio = async (valor) => {
        const id = rolId;
        const anterior = inicio.valor;
        if (!id || valor === inicio.guardado) return;

        $inicio.prop('disabled', true);
        estadoGuardado('guardando');

        try {
            const formData = new FormData();
            formData.append('id_rol', id);
            formData.append('id_modulo', valor);

            const response = await fetch('<?= base_url() ?>admin/roles/actualizarInicio', {
                method: 'POST',
                body: formData
            });
            const result = await response.json().catch(() => null);
            if (result && result.success && result.tabla) {
                $('#contenedor_table').html(result.tabla);
                renderizarTabla();
            }
            if (id !== rolId) return;
            if (!response.ok || !result || !result.success) {
                throw new Error((result && result.message) || 'No se pudo actualizar la vista de inicio.');
            }

            inicio.valor = valor;
            inicio.guardado = valor;
            $('#inicio_aviso').html('');
            estadoGuardado('ok');
            marcarInicio();

            Toast.fire({
                icon: 'success',
                title: result.message || 'Vista de inicio actualizada.'
            });
        } catch (error) {
            if (id !== rolId) return;
            $inicio.val(anterior).trigger('change.select2');
            estadoGuardado('');

            Toast.fire({
                icon: 'error',
                title: error.message || 'No se pudo actualizar la vista de inicio.'
            });
        } finally {
            if (id === rolId) $inicio.prop('disabled', false);
        }
    };

    /**
     * Metodo para obtener los módulos de un rol en especifico.
     * 
     * @param {number} rolId Identificador del rol.
     */
    const obtenerModulosRol = async (rolId) => {
        try {

            const formData = new FormData();
            formData.append('rolId', rolId);

            const response = await fetch(`<?= base_url() ?>admin/roles/obtenerModulosRol`, {
                method: 'POST',
                body: formData
            });

            const modulos = await response.json();

            renderizarModulosRol(modulos);

        } catch (error) {
            console.error('Error al obtener los módulos del rol:', error);
        }
    };

    /**
     * Metodo para asignar el módulo especifico al rol.
     * 
     * @param int Id Identificador del módulo.
     */
    asginarModulo = async (id) => {
        $(`#asignar_${id}`).prop('disabled', true);

        const formData = new FormData();
        formData.append('rolId', rolId);
        formData.append('moduloId', id);

        try {

            const response = await fetch('<?= base_url() ?>admin/roles/asignarModuloRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result || result.success === false || typeof result.asignados === 'undefined') {
                throw new Error((result && result.message) || 'No se pudo asignar el módulo.');
            }

            renderizarModulosRol(result);
            cargarInicio(true);

            Toast.fire({
                icon: 'success',
                title: result.message || 'Módulo asignado correctamente.'
            });

        } catch (error) {
            console.error('Error al asignar el módulo al rol:', error);

            $(`#asignar_${id}`).prop('disabled', false);

            Toast.fire({
                icon: 'error',
                title: error.message || 'No se pudo asignar el módulo.'
            });
        }
    }

    /**
     * Metodo para quitar un módulo especifico al rol.
     * 
     * @param int Id Identificador del módulo.
     */
    quitarModulo = async (id) => {
        if (inicio.valor !== '' && String(id) === inicio.valor) {
            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: 'Este módulo es la vista de inicio del rol',
                text: 'Si lo quita, los usuarios de este rol entrarán al Menú principal.',
                showCancelButton: true,
                confirmButtonText: 'Quitar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                reverseButtons: true,
                focusCancel: true
            });
            if (!confirmacion.isConfirmed) return;
        }

        $(`#quitar_${id}`).prop('disabled', true);

        try {
            const formData = new FormData();
            formData.append('rolId', rolId);
            formData.append('moduloId', id);

            const response = await fetch('<?= base_url() ?>admin/roles/quitarModuloRol', {
                method: 'POST',
                body: formData
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result || result.success === false || typeof result.asignados === 'undefined') {
                throw new Error((result && result.message) || 'No se pudo quitar el módulo.');
            }

            renderizarModulosRol(result);
            cargarInicio(true);

            if (result.tabla) {
                $('#contenedor_table').html(result.tabla);
                renderizarTabla();
            }

            Toast.fire({
                icon: 'success',
                title: result.message || 'Módulo quitado correctamente.'
            });

        } catch (error) {
            console.error('Error al quitar el módulo del rol:', error);

            $(`#quitar_${id}`).prop('disabled', false);

            Toast.fire({
                icon: 'error',
                title: error.message || 'No se pudo quitar el módulo.'
            });
        }
    }

    /**
     * 
     */
    document.addEventListener('DOMContentLoaded', function() {
        renderizarTabla();
    });

    document.querySelector('#formulario_editar_rol').addEventListener('submit', (e) => {
        e.preventDefault();
        updateRol();

        $('#select_rol').select2();
    });

    /**
     * 
     */
    document.querySelector('#formulario_creacion').addEventListener('submit', (e) => {
        e.preventDefault();
        crearRol();
    });

    /**
     * 
     */
    $('#select_rol').select2({
        ajax: {
            url: '<?= base_url() ?>admin/roles/buscarRol',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: 'Buscar rol por nombre',
        allowClear: true,
        width: '100%'
    });

    $inicio.select2({
        width: '100%',
        minimumResultsForSearch: 8,
        templateResult: plantillaInicio(false),
        templateSelection: plantillaInicio(true)
    }).on('select2:select', (e) => guardarInicio(e.params.data.id));

    /**
     * 
     */
    $('#select_rol').on('select2:select', function(e) {
        rolId = e.params.data.id;
        inicio.valor = '';
        inicio.guardado = '';
        obtenerModulosRol(rolId);
        cargarInicio();
    });

    /**
     * 
     */
    $('#select_rol').on('select2:unselect', function(e) {
        rolId = null;
        resetInicio();
        document.querySelector('#rolModulesList').innerHTML = '<li class="list-group-item text-muted small">Seleccione el rol para ver los módulos asignados.</li>';
        document.querySelector('#availableRolModulesList').innerHTML = '<li class="list-group-item text-muted small">Seleccione el rol para ver los módulos que no tiene asignados.</li>';
    });
</script>
<?php echo $this->endSection(); ?>
<!-- FIN SECCION DE SCRIPTS -->