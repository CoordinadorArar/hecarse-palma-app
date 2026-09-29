const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

const Alerta = Swal.mixin({ confirmButtonColor: '#1dab4d' });

const URL_LOTES = BASE_URL + 'gestion-palma/lotes/registro/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroFinca = () => $id('filtro_finca').value;
const filtroEstado = () => $id('filtro_estado').value;
const filtroDescuadre = () => $id('filtro_descuadre').value;

const formatoNumero = (n) => Number(n || 0).toLocaleString('es-CO');

// ── Tabla de lotes ───────────────────────────────────────────────────────────

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_lotes')) {
        $('#tabla_lotes').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_lotes').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_lotes').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [9], orderable: false }],
        order: [[0, 'asc']],
        language: {
            lengthMenu: 'Mostrar _MENU_ registros por página',
            zeroRecords: 'No se encontraron resultados',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            emptyTable: 'No hay datos disponibles',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });
};

const actualizarEstado = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_lotes').innerHTML =
        `<i class="bi bi-hash me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_lotes').innerHTML = result.tabla || '';
    renderizarTabla();
    actualizarEstado(result.total ?? 0);
};

const avisar = (response, mensaje) => {
    const texto = mensaje || 'No se pudo completar la operación.';
    if (response.status === 401 || texto.length > 70) {
        Alerta.fire({ icon: 'warning', title: response.status === 401 ? 'Sesión expirada' : 'Atención', text: texto });
        return;
    }
    Toast.fire({ icon: 'warning', title: texto });
};

const filtrosActuales = () => ({
    filtro_empresa: filtroEmpresa(),
    filtro_finca: filtroFinca(),
    filtro_estado: filtroEstado(),
    filtro_descuadre: filtroDescuadre()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('finca', filtroFinca());
        body.append('estado', filtroEstado());
        body.append('descuadre', filtroDescuadre());

        const response = await fetch(URL_LOTES + 'listar', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
            return;
        }

        aplicarRespuesta(result);
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

const enviar = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));

    try {
        const response = await fetch(URL_LOTES + accion, { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
            return false;
        }

        aplicarRespuesta(result);
        Toast.fire({ icon: 'success', title: result.message });
        return true;
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
        return false;
    }
};

// ── Filtros ───────────────────────────────────────────────────────────────────

$id('filtro_empresa').addEventListener('change', listar);
$id('filtro_finca').addEventListener('change', listar);
$id('filtro_estado').addEventListener('change', listar);
$id('filtro_descuadre').addEventListener('change', listar);

// ── Sección (según finca) ────────────────────────────────────────────────────

const repoblarSecciones = (finca, seleccion = '') => {
    const lista = SECCIONES_POR_FINCA[finca] || [];
    const select = $id('lote_seccion');
    select.innerHTML = '<option value="">Seleccione&hellip;</option>' +
        lista.map((s) => `<option value="${s.codigo}">${s.descripcion}</option>`).join('');
    select.value = lista.some((s) => s.codigo === seleccion) ? seleccion : '';
    $id('lote_seccion_vacio').classList.toggle('d-none', lista.length > 0);
};

$id('lote_finca').addEventListener('change', () => repoblarSecciones($id('lote_finca').value));

const aplicarManejaSeccion = () => {
    const activo = $id('lote_maneja_seccion').checked;
    const col = $id('lote_col_seccion');
    col.classList.toggle('d-none', !activo);
    $id('lote_seccion').required = activo;
    if (activo) {
        col.classList.remove('gp-revelar');
        void col.offsetWidth;
        col.classList.add('gp-revelar');
    }
};

$id('lote_maneja_seccion').addEventListener('change', aplicarManejaSeccion);

// ── Densidad / hectáreas netas calculadas ────────────────────────────────────

const mostrarIconoCalculado = (id) => {
    const el = $id(id);
    el.classList.remove('d-none', 'gp-revelar');
    void el.offsetWidth;
    el.classList.add('gp-revelar');
};

const ocultarIconoCalculado = (id) => $id(id).classList.add('d-none');

$id('lote_dsiembra').addEventListener('blur', () => {
    const d = parseFloat($id('lote_dsiembra').value);
    if (!d) return;

    const densidad = Math.round(10000 / (d * d * 0.866));
    $id('lote_densidad').value = densidad;
    mostrarIconoCalculado('lote_densidad_calculo');

    const brutas = parseFloat($id('lote_palmas_brutas').value) || 0;
    $id('lote_hnetas').value = densidad ? (Math.round((brutas / densidad) * 100) / 100).toFixed(2) : '';
    mostrarIconoCalculado('lote_hnetas_calculo');
});

$id('lote_densidad').addEventListener('input', () => ocultarIconoCalculado('lote_densidad_calculo'));
$id('lote_hnetas').addEventListener('input', () => ocultarIconoCalculado('lote_hnetas_calculo'));

// ── Canales de agua ───────────────────────────────────────────────────────────

const opcionesTipoCanal = (seleccionado) => TIPOS_CANAL
    .map((t) => `<option value="${t.codigo}" ${t.codigo === seleccionado ? 'selected' : ''}>${t.codigo} — ${t.descripcion}</option>`)
    .join('');

const filaCanalVacia = () => '<tr id="lote_canal_vacio"><td colspan="3" class="text-muted small text-center py-2">Sin canales registrados.</td></tr>';

const agregarFilaCanal = (tipo = '', metros = '') => {
    const vacio = $id('lote_canal_vacio');
    if (vacio) vacio.remove();

    const fila = document.createElement('tr');
    fila.className = 'gp-revelar';
    fila.innerHTML = `
        <td><select class="form-select form-select-sm canal-tipo" required><option value="">Seleccione&hellip;</option>${opcionesTipoCanal(tipo)}</select></td>
        <td><input type="number" class="form-control form-control-sm text-end canal-metros" min="0" step="0.01" value="${metros}" required></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-quitar-canal"><i class="bi bi-trash"></i></button></td>`;
    $id('lote_canales_cuerpo').appendChild(fila);
};

const renderizarCanales = (canales) => {
    const cuerpo = $id('lote_canales_cuerpo');
    cuerpo.innerHTML = '';
    if (!canales.length) {
        cuerpo.innerHTML = filaCanalVacia();
        return;
    }
    canales.forEach((c) => agregarFilaCanal(c.tipoCanal, c.metros));
};

$id('btn_agregar_canal').addEventListener('click', () => agregarFilaCanal());

$id('lote_canales_cuerpo').addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-quitar-canal');
    if (!btn) return;
    btn.closest('tr').remove();
    if (!$id('lote_canales_cuerpo').querySelector('tr')) {
        $id('lote_canales_cuerpo').innerHTML = filaCanalVacia();
    }
});

const recolectarCanales = () => Array.from($id('lote_canales_cuerpo').querySelectorAll('tr'))
    .filter((fila) => fila.querySelector('.canal-tipo'))
    .map((fila) => ({
        tipoCanal: fila.querySelector('.canal-tipo').value,
        metros: fila.querySelector('.canal-metros').value
    }));

// ── Alerta de descuadre ───────────────────────────────────────────────────────

const mostrarAlertaDescuadre = (lote) => {
    const caja = $id('lote_alerta_descuadre');
    const hay = Number(lote.palmasBrutas) !== Number(lote.detallePalmas)
        || Number(lote.NoLineas) !== Number(lote.detalleLineas)
        || Number(lote.palmasProduccion) !== Number(lote.detalleProduccion);

    if (!hay) {
        caja.classList.add('d-none');
        return;
    }

    caja.querySelector('.gp-nota-alerta').innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>El maestro declara ${formatoNumero(lote.palmasBrutas)} palmas brutas, ${formatoNumero(lote.palmasProduccion)} de producción y ${formatoNumero(lote.NoLineas)} líneas. El detalle de líneas suma ${formatoNumero(lote.detallePalmas)} palmas en ${formatoNumero(lote.detalleLineas)} líneas. Puede guardar igual; verifique si es un error de digitación.`;
    caja.classList.remove('d-none');
};

// ── Modal Lote ────────────────────────────────────────────────────────────────

const modalLote = () => bootstrap.Modal.getOrCreateInstance($id('modalLote'));

$('#lote_ccosto').select2({
    width: '100%',
    dropdownParent: $('#modalLote'),
    placeholder: 'Seleccione una opción…',
    allowClear: true,
    language: {
        noResults: () => 'No se encontraron resultados',
        searching: () => 'Buscando…'
    }
});

const bloquearLlave = (bloquear) => {
    $id('lote_empresa').disabled = bloquear;
    $id('lote_finca').disabled = bloquear;
    $id('lote_codigo').disabled = bloquear;
    $id('lote_aviso_llave').classList.toggle('d-none', !bloquear);
};

const prepararFormulario = () => {
    $id('formulario_lote').reset();
    aplicarManejaSeccion();
    ocultarIconoCalculado('lote_densidad_calculo');
    ocultarIconoCalculado('lote_hnetas_calculo');
    $id('lote_alerta_descuadre').classList.add('d-none');
    $('#lote_ccosto').val('').trigger('change');
    renderizarCanales([]);
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('lote_modo').value = 'crear';
    $id('titulo_modal_lote').innerHTML = '<i class="bi bi-hash me-1"></i>Nuevo lote';
    $id('lote_empresa').value = filtroEmpresa();
    $id('lote_finca').value = filtroFinca();
    repoblarSecciones($id('lote_finca').value);
    $id('lote_activo').checked = true;
    $id('lote_desarrollo').checked = false;
    bloquearLlave(false);
    $id('btn_administrar_lineas').disabled = true;
    $id('btn_administrar_lineas').title = 'Guarde el lote primero';
    modalLote().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

window.editarLote = async (empresa, codigo, finca) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);
        body.append('finca', finca);

        const response = await fetch(URL_LOTES + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el lote.');
            return;
        }

        const lote = result.lote;
        prepararFormulario();
        $id('lote_modo').value = 'editar';
        $id('titulo_modal_lote').innerHTML = '<i class="bi bi-hash me-1"></i>Editar lote';
        $id('lote_empresa').value = lote.empresa;
        $id('lote_finca').value = lote.finca;
        $id('lote_codigo').value = lote.codigo;
        $id('lote_empresa_pk').value = lote.empresa;
        $id('lote_finca_pk').value = lote.finca;
        $id('lote_codigo_pk').value = lote.codigo;
        $id('lote_numero').value = lote.numero ?? '';
        $id('lote_letra').value = lote.letra || '';
        $id('lote_descripcion').value = lote.descripcion || '';
        $id('lote_variedad').value = lote.variedad || '';
        $('#lote_ccosto').val(lote.ccosto || '').trigger('change');
        repoblarSecciones(lote.finca, lote.seccion || '');
        $id('lote_maneja_seccion').checked = Number(lote.manejaSeccion) === 1;
        aplicarManejaSeccion();
        $id('lote_anio_siembra').value = lote.anioSiembra || '';
        $id('lote_palmas_brutas').value = lote.palmasBrutas ?? 0;
        $id('lote_palmas_produccion').value = lote.palmasProduccion ?? 0;
        $id('lote_nolineas').value = lote.NoLineas ?? 0;
        $id('lote_hbrutas').value = lote.hBrutas ?? '';
        $id('lote_hnetas').value = lote.hNetas ?? '';
        $id('lote_dsiembra').value = lote.dSiembra ?? '';
        $id('lote_densidad').value = lote.densidad ?? '';
        $id('lote_activo').checked = Number(lote.activo) === 1;
        $id('lote_desarrollo').checked = Number(lote.desarrollo) === 1;
        mostrarAlertaDescuadre(lote);
        renderizarCanales(result.canales || []);
        bloquearLlave(true);
        $id('btn_administrar_lineas').disabled = false;
        $id('btn_administrar_lineas').title = '';
        modalLote().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('btn_administrar_lineas').addEventListener('click', () => {
    lineasLote($id('lote_empresa_pk').value, $id('lote_codigo_pk').value, $id('lote_finca_pk').value);
});

$id('formulario_lote').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = $id('btn_guardar_lote');
    const edicion = $id('lote_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('lote_empresa_pk').value : $id('lote_empresa').value,
        finca: edicion ? $id('lote_finca_pk').value : $id('lote_finca').value,
        codigo: edicion ? $id('lote_codigo_pk').value : $id('lote_codigo').value,
        numero: $id('lote_numero').value,
        letra: $id('lote_letra').value,
        descripcion: $id('lote_descripcion').value,
        variedad: $id('lote_variedad').value,
        ccosto: $id('lote_ccosto').value,
        manejaSeccion: $id('lote_maneja_seccion').checked ? 1 : 0,
        seccion: $id('lote_maneja_seccion').checked ? $id('lote_seccion').value : '',
        anioSiembra: $id('lote_anio_siembra').value,
        palmasBrutas: $id('lote_palmas_brutas').value,
        palmasProduccion: $id('lote_palmas_produccion').value,
        NoLineas: $id('lote_nolineas').value,
        hBrutas: $id('lote_hbrutas').value,
        hNetas: $id('lote_hnetas').value,
        dSiembra: $id('lote_dsiembra').value,
        densidad: $id('lote_densidad').value,
        activo: $id('lote_activo').checked ? 1 : 0,
        desarrollo: $id('lote_desarrollo').checked ? 1 : 0,
        canales: JSON.stringify(recolectarCanales()),
        ...filtrosActuales()
    });

    if (ok) modalLote().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.cambiarEstadoLote = async (empresa, codigo, finca) => {
    const fila = document.querySelector(`#cuerpo_tabla_lotes tr[data-empresa="${CSS.escape(empresa)}"][data-codigo="${CSS.escape(codigo)}"][data-finca="${CSS.escape(finca)}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado del' : (activo ? 'inactivar el' : 'activar el');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} lote seleccionado.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, finca, ...filtrosActuales() });
    }
};

window.eliminarLote = async (empresa, codigo, finca, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar lote?',
        text: `Se eliminará «${descripcion}». Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, codigo, finca, ...filtrosActuales() });
    }
};

// ── Modal Líneas del lote ─────────────────────────────────────────────────────

const modalLineas = () => bootstrap.Modal.getOrCreateInstance($id('modalLoteLineas'));

let contextoLineas = { empresa: '', codigo: '', finca: '' };
let lineasActuales = [];

const destruirTablaLineas = () => {
    if ($.fn.DataTable.isDataTable('#tabla_lote_lineas')) {
        $('#tabla_lote_lineas').DataTable().destroy();
    }
};

const renderizarTablaLineas = () => {
    if (!$id('cuerpo_tabla_lineas').querySelectorAll('tr').length) return;
    $('#tabla_lote_lineas').DataTable({
        searching: true,
        columnDefs: [{ targets: [3], orderable: false }],
        order: [[0, 'asc']],
        language: {
            lengthMenu: 'Mostrar _MENU_ registros por página',
            zeroRecords: 'No se encontraron resultados',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            emptyTable: 'No hay datos disponibles',
            search: 'Buscar:',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });
};

const filaLinea = (l) => `<tr data-linea="${l.linea}">
    <td class="text-end font-monospace">${l.linea}</td>
    <td class="text-end font-monospace">${formatoNumero(l.noPalma)}</td>
    <td class="text-center">${l.palmaErradicada ? '<span class="gp-chip-rol activo">SÍ</span>' : '<span class="gp-chip-rol">NO</span>'}</td>
    <td class="text-center">
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" title="Editar" data-accion="editar" data-linea="${l.linea}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-outline-danger" title="Eliminar" data-accion="eliminar" data-linea="${l.linea}"><i class="bi bi-trash"></i></button>
        </div>
    </td>
</tr>`;

const aplicarRespuestaLineas = (lineas) => {
    lineasActuales = lineas;
    destruirTablaLineas();
    $id('cuerpo_tabla_lineas').innerHTML = lineas.map(filaLinea).join('');
    renderizarTablaLineas();
    const vacio = lineas.length === 0;
    $id('lineas_estado_vacio').classList.toggle('d-none', !vacio);
    $id('lineas_contenedor_tabla').classList.toggle('d-none', vacio);
};

const listarLineas = async () => {
    const body = new FormData();
    body.append('empresa', contextoLineas.empresa);
    body.append('codigo', contextoLineas.codigo);
    body.append('finca', contextoLineas.finca);

    const response = await fetch(URL_LOTES + 'lineas/listar', { method: 'POST', body });
    const result = await response.json();

    if (!response.ok || !result.success) {
        avisar(response, result.message);
        return;
    }

    aplicarRespuestaLineas(result.lineas || []);
};

const prepararFormularioLinea = () => {
    $id('formulario_linea').reset();
    $id('lineas_modo').value = 'crear';
    $id('btn_guardar_linea').innerHTML = '<i class="bi bi-plus-lg me-1"></i>Agregar';
    $id('btn_cancelar_linea').classList.add('d-none');
};

window.lineasLote = async (empresa, codigo, finca) => {
    contextoLineas = { empresa, codigo, finca };

    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);
        body.append('finca', finca);

        const response = await fetch(URL_LOTES + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el lote.');
            return;
        }

        $id('lineas_lote_subtitulo').textContent = `${result.lote.codigo} — ${result.lote.descripcion}`;
        prepararFormularioLinea();
        await listarLineas();
        modalLineas().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('btn_cancelar_linea').addEventListener('click', prepararFormularioLinea);
$id('btn_lineas_vacio_agregar').addEventListener('click', () => $id('lineas_linea').focus());

$id('cuerpo_tabla_lineas').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-accion]');
    if (!btn) return;
    const linea = Number(btn.dataset.linea);

    if (btn.dataset.accion === 'editar') {
        const datos = lineasActuales.find((l) => l.linea === linea);
        if (!datos) return;
        $id('lineas_modo').value = 'editar';
        $id('lineas_linea').value = datos.linea;
        $id('lineas_palmas').value = datos.noPalma;
        $id('lineas_erradicada').checked = !!datos.palmaErradicada;
        $id('btn_guardar_linea').innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
        $id('btn_cancelar_linea').classList.remove('d-none');
        $id('lineas_palmas').focus();
        return;
    }

    eliminarLineaLote(linea);
});

const eliminarLineaLote = async (linea) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar línea?',
        text: `Se eliminará la línea ${linea}. Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) return;

    const body = new FormData();
    body.append('empresa', contextoLineas.empresa);
    body.append('codigo', contextoLineas.codigo);
    body.append('finca', contextoLineas.finca);
    body.append('linea', linea);

    const response = await fetch(URL_LOTES + 'lineas/eliminar', { method: 'POST', body });
    const result = await response.json();

    if (!response.ok || !result.success) {
        avisar(response, result.message);
        return;
    }

    aplicarRespuestaLineas(result.lineas || []);
    Toast.fire({ icon: 'success', title: result.message });
};

$id('formulario_linea').addEventListener('submit', async (e) => {
    e.preventDefault();

    const edicion = $id('lineas_modo').value === 'editar';
    const btn = $id('btn_guardar_linea');
    btn.disabled = true;

    const body = new FormData();
    body.append('empresa', contextoLineas.empresa);
    body.append('codigo', contextoLineas.codigo);
    body.append('finca', contextoLineas.finca);
    body.append('linea', $id('lineas_linea').value);
    body.append('noPalma', $id('lineas_palmas').value);
    body.append('palmaErradicada', $id('lineas_erradicada').checked ? 1 : 0);

    try {
        const response = await fetch(URL_LOTES + (edicion ? 'lineas/actualizar' : 'lineas/crear'), { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
        } else {
            aplicarRespuestaLineas(result.lineas || []);
            Toast.fire({ icon: 'success', title: result.message });
            prepararFormularioLinea();
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }

    btn.disabled = false;
});

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_lotes').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_lotes').querySelector('strong').textContent);
});
