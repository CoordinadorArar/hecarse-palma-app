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

const URL_TIPOS = BASE_URL + 'maestros/tipos-documentos/';

const $id = (id) => document.getElementById(id);
const mostrar = (elemento, visible) => elemento.classList.toggle('d-none', !visible);
const numero = (valor) => Number(valor || 0).toLocaleString('es-CO');
const texto = (valor) => String(valor ?? '').trim();
const normalizar = (valor) => texto(valor).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const estado = { filas: [], total: 0, busqueda: '', modo: 'crear', codigoOriginal: '' };

const tbody = () => $id('cuerpo_tabla_tipos');
const empresaActual = () => $id('filtro_empresa').value;
const razonSocialActual = () => {
    const selector = $id('filtro_empresa');

    return texto(selector.options[selector.selectedIndex]?.text);
};

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;
    let resultado = null;

    try {
        respuesta = await fetch(URL_TIPOS + ruta, { method: 'POST', body: cuerpo });
    } catch (error) {
        throw new Error('Error de conexión.');
    }

    try {
        resultado = await respuesta.json();
    } catch (error) {
        resultado = null;
    }

    if (!respuesta.ok || resultado === null || resultado.success !== true) {
        const fallo = new Error(texto(resultado?.message) || 'No se pudo completar la operación.');

        fallo.datos = resultado || {};
        throw fallo;
    }

    return resultado;
};

const avisar = (error) => Toast.fire({
    icon: error.message === 'Error de conexión.' ? 'error' : 'warning',
    title: escaparHtml(error.message)
});

// ── Grilla ────────────────────────────────────────────────────────────────────

const accionesFila = (usos) => {
    const editar = '<button type="button" class="btn btn-outline-secondary btn-sm" data-accion="editar" title="Editar tipo de documento">'
        + '<i class="bi bi-pencil" aria-hidden="true"></i></button>';

    const eliminar = usos > 0
        ? `<span class="d-inline-block maestros-accion-bloqueada" tabindex="0" title="No se puede eliminar: ${numero(usos)} ${usos === 1 ? 'tercero usa' : 'terceros usan'} este tipo de documento.">`
            + '<button type="button" class="btn btn-outline-danger btn-sm pe-none" disabled aria-disabled="true" tabindex="-1">'
            + '<i class="bi bi-trash" aria-hidden="true"></i></button></span>'
        : '<button type="button" class="btn btn-outline-danger btn-sm" data-accion="eliminar" title="Eliminar tipo de documento">'
            + '<i class="bi bi-trash" aria-hidden="true"></i></button>';

    return `<div class="d-flex justify-content-center gap-1">${editar}${eliminar}</div>`;
};

const filaHtml = (fila) => {
    const codigo = texto(fila.codigo);
    const descripcion = texto(fila.descripcion);
    const corta = texto(fila.descripcionCorta);
    const equivalencia = texto(fila.equivalencia);
    const crudoTD = Number(fila.codigoTD);
    const codigoTD = Number.isFinite(crudoTD) ? crudoTD : 0;
    const usos = Number(fila.usos || 0);
    const manejaNit = Number(fila.mNit) === 1;
    const orden = codigo.toUpperCase().padStart(3, '0');

    const nitHtml = manejaNit
        ? '<span class="badge rounded-pill px-3 py-2 maestros-badge-si"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Sí</span>'
        : '<span class="badge rounded-pill px-3 py-2 maestros-badge-inactivo">No</span>';

    return `<tr data-codigo="${escaparHtml(codigo)}" data-descripcion="${escaparHtml(descripcion)}"`
        + ` data-buscar="${escaparHtml(normalizar(`${codigo} ${descripcion} ${corta}`))}">`
        + `<td class="text-center font-monospace fw-semibold" data-order="${escaparHtml(orden)}">${escaparHtml(codigo)}</td>`
        + `<td><span class="maestros-truncar maestros-truncar-dato" title="${escaparHtml(descripcion)}">${escaparHtml(descripcion)}</span></td>`
        + `<td class="text-center"><span class="badge maestros-badge-doc font-monospace">${escaparHtml(corta)}</span></td>`
        + `<td class="text-center font-monospace" data-order="${codigoTD}">${codigoTD}</td>`
        + `<td class="text-center" data-order="${manejaNit ? 1 : 0}">${nitHtml}</td>`
        + `<td class="font-monospace">${equivalencia === ''
            ? '<span class="maestros-vacio">Sin equivalencia</span>'
            : `<span class="maestros-truncar" title="${escaparHtml(equivalencia)}">${escaparHtml(equivalencia)}</span>`}</td>`
        + `<td class="text-center" data-order="${usos}">${usos === 0 ? '<span class="maestros-vacio">Sin uso</span>' : `<span class="fw-semibold">${numero(usos)}</span>`}</td>`
        + `<td class="text-center">${accionesFila(usos)}</td>`
        + '</tr>';
};

const pintarEsqueleto = () => {
    const anchos = ['50%', '85%', '60%', '50%', '55%', '70%', '45%', '60%'];

    tbody().innerHTML = Array.from({ length: 5 }, () => '<tr class="placeholder-glow">'
        + anchos.map((ancho) => `<td><span class="placeholder" style="width:${ancho}"></span></td>`).join('')
        + '</tr>').join('');
};

const esDataTable = () => $.fn.DataTable.isDataTable('#tabla_tipos_documentos');
const instanciaTabla = () => $('#tabla_tipos_documentos').DataTable();

$.fn.dataTable.ext.search.push((configuracion, datos, indice) => {
    if (configuracion.nTable.id !== 'tabla_tipos_documentos' || estado.busqueda === '') {
        return true;
    }

    return (configuracion.aoData[indice].nTr?.dataset.buscar || '').includes(estado.busqueda);
});

const destruirTabla = () => {
    $id('pie_tabla').innerHTML = '';

    if (esDataTable()) {
        instanciaTabla().destroy();
    }
};

const iniciarTabla = () => {
    const tabla = $('#tabla_tipos_documentos').DataTable({
        dom: 'rt<"maestros-paginado"ip>',
        paging: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [{ targets: [7], orderable: false }],
        language: {
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

    $id('pie_tabla').appendChild(tabla.table().container().querySelector('.maestros-paginado'));
    tabla.on('draw', actualizarEstados);
};

const velo = (activo) => {
    const zona = $id('zona_grilla');

    zona.setAttribute('aria-busy', activo ? 'true' : 'false');
    zona.querySelector('.maestros-velo')?.remove();

    if (!activo) {
        return;
    }

    const capa = document.createElement('div');

    capa.className = 'maestros-velo';
    capa.innerHTML = '<div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Cargando…</span></div>';
    zona.appendChild(capa);
};

const bloquear = (activo) => {
    ['filtro_empresa', 'filtro_busqueda', 'btn_limpiar_busqueda', 'btn_nuevo'].forEach((id) => {
        $id(id).disabled = activo;
    });
};

const actualizarEstados = () => {
    const info = esDataTable() ? instanciaTabla().page.info() : null;
    const visibles = info ? info.recordsDisplay : 0;
    const vacia = estado.total === 0;
    const sinCoincidencias = !vacia && visibles === 0;
    const conFilas = !vacia && !sinCoincidencias;

    mostrar($id('estado_error'), false);
    mostrar($id('estado_vacio'), vacia);
    mostrar($id('estado_filtro'), sinCoincidencias);
    mostrar($id('contenedor_tabla'), conFilas);
    mostrar($id('pie_tabla'), conFilas && visibles > info.length);

    mostrar($id('aviso_sin_equivalencia'), conFilas && estado.filas.every((fila) => texto(fila.equivalencia) === ''));

    if (sinCoincidencias) {
        $id('titulo_estado_filtro').innerHTML = `Ningún tipo de documento coincide con «${escaparHtml(texto($id('filtro_busqueda').value))}»`;
    }

    const plural = (estado.busqueda === '' ? visibles : estado.total) === 1 ? 'registro encontrado' : 'registros encontrados';

    $id('contador_registros').innerHTML = conFilas
        ? `<i class="bi bi-card-list me-1"></i><strong>${numero(visibles)}</strong>`
            + `${estado.busqueda === '' ? '' : ` de ${numero(estado.total)}`} ${plural}`
        : '';
};

const mostrarEstadoError = (mensaje) => {
    mostrar($id('estado_vacio'), false);
    mostrar($id('estado_filtro'), false);
    mostrar($id('contenedor_tabla'), false);
    mostrar($id('pie_tabla'), false);
    mostrar($id('aviso_sin_equivalencia'), false);
    $id('contador_registros').innerHTML = '';
    $id('mensaje_estado_error').textContent = mensaje;
    mostrar($id('estado_error'), true);
};

let cargando = false;

const cargar = async () => {
    if (cargando) {
        return;
    }

    cargando = true;
    destruirTabla();
    pintarEsqueleto();
    mostrar($id('contenedor_tabla'), true);
    mostrar($id('estado_vacio'), false);
    mostrar($id('estado_filtro'), false);
    mostrar($id('estado_error'), false);
    mostrar($id('pie_tabla'), false);
    mostrar($id('aviso_sin_equivalencia'), false);
    $id('contador_registros').innerHTML = '';
    velo(true);
    bloquear(true);

    try {
        const resultado = await pedir('listar', { empresa: empresaActual() });

        estado.filas = resultado.filas || [];
        estado.total = estado.filas.length;

        destruirTabla();
        tbody().innerHTML = estado.filas.map(filaHtml).join('');

        if (estado.total > 0) {
            iniciarTabla();
        }

        actualizarEstados();
    } catch (error) {
        estado.filas = [];
        estado.total = 0;
        destruirTabla();
        tbody().innerHTML = '';
        avisar(error);
        mostrarEstadoError(error.message);
    } finally {
        cargando = false;
        velo(false);
        bloquear(false);
    }
};

// ── Filtros ───────────────────────────────────────────────────────────────────

const filtrar = () => {
    estado.busqueda = normalizar($id('filtro_busqueda').value);

    if (esDataTable()) {
        instanciaTabla().draw();
        return;
    }

    actualizarEstados();
};

let temporizador = null;

$id('filtro_busqueda').addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(filtrar, 250);
});

const limpiarBusqueda = () => {
    clearTimeout(temporizador);
    $id('filtro_busqueda').value = '';
    filtrar();
    $id('filtro_busqueda').focus();
};

$id('btn_limpiar_busqueda').addEventListener('click', limpiarBusqueda);
$id('btn_limpiar_filtro').addEventListener('click', limpiarBusqueda);
$id('btn_reintentar').addEventListener('click', cargar);
$id('filtro_empresa').addEventListener('change', cargar);

// ── Modal ─────────────────────────────────────────────────────────────────────

const modal = () => bootstrap.Modal.getOrCreateInstance($id('modalTipoDocumento'));

const limpiarInvalidos = () => {
    $id('formulario_tipo').querySelectorAll('.is-invalid').forEach((campo) => campo.classList.remove('is-invalid'));
};

const marcarInvalido = (elemento, mensaje) => {
    const feedback = (elemento.closest('.input-group') || elemento.parentElement).querySelector('.invalid-feedback');

    elemento.classList.add('is-invalid');
    if (feedback) feedback.textContent = mensaje;

    Toast.fire({ icon: 'warning', title: mensaje });
    elemento.focus();
    return false;
};

const pintarPanelNit = () => $id('panel_mnit').classList.toggle('activo', $id('mNit').checked);

$id('mNit').addEventListener('change', pintarPanelNit);

$id('formulario_tipo').addEventListener('input', (e) => e.target.classList.remove('is-invalid'));

const abrirModal = (modo, fila) => {
    const edicion = modo === 'editar';
    const campoCodigo = $id('codigo');

    estado.modo = modo;
    estado.codigoOriginal = edicion ? texto(fila.codigo) : '';
    limpiarInvalidos();

    $id('titulo_modal').innerHTML = edicion
        ? '<i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar tipo de documento'
        : '<i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo tipo de documento';
    $id('chip_empresa').textContent = razonSocialActual();

    campoCodigo.value = edicion ? texto(fila.codigo) : '';
    $id('descripcion').value = edicion ? texto(fila.descripcion) : '';
    $id('descripcionCorta').value = edicion ? texto(fila.descripcionCorta) : '';
    $id('codigoTD').value = edicion ? Number(fila.codigoTD || 0) : 0;
    $id('equivalencia').value = edicion ? texto(fila.equivalencia) : '';
    $id('mNit').checked = edicion && Number(fila.mNit) === 1;
    pintarPanelNit();

    campoCodigo.readOnly = edicion;
    campoCodigo.classList.toggle('bg-light', edicion);
    campoCodigo.classList.toggle('rounded-end', !edicion);
    mostrar($id('candado_codigo'), edicion);

    if (edicion) {
        campoCodigo.setAttribute('aria-readonly', 'true');
        $id('ayuda_codigo').textContent = 'El código identifica el registro y no se puede modificar.'
            + ' Si está mal, elimine el registro y cree el correcto.';
    } else {
        campoCodigo.removeAttribute('aria-readonly');
        $id('ayuda_codigo').textContent = 'Máximo 3 caracteres. Es el valor que queda guardado en cada tercero.';
    }

    modal().show();
    setTimeout(() => $id(edicion ? 'descripcion' : 'codigo').focus(), 300);
};

const validar = () => {
    limpiarInvalidos();

    const codigo = texto($id('codigo').value);
    const repetido = estado.filas.some((fila) => texto(fila.codigo).toLowerCase() === codigo.toLowerCase());

    if (codigo === '') return marcarInvalido($id('codigo'), 'Escriba el código del tipo de documento.');
    if (codigo.length > 3) return marcarInvalido($id('codigo'), 'El código admite máximo 3 caracteres.');
    if (estado.modo === 'crear' && repetido) return marcarInvalido($id('codigo'), 'El código ya existe en esta empresa.');
    if (texto($id('descripcion').value) === '') return marcarInvalido($id('descripcion'), 'Escriba la descripción.');
    if (texto($id('descripcionCorta').value) === '') return marcarInvalido($id('descripcionCorta'), 'Escriba la abreviatura.');
    if (!/^\d+$/.test(texto($id('codigoTD').value))) return marcarInvalido($id('codigoTD'), 'El código TD debe ser un número entero.');

    return true;
};

$id('btn_nuevo').addEventListener('click', () => abrirModal('crear'));
$id('btn_crear_primero').addEventListener('click', () => abrirModal('crear'));

$id('formulario_tipo').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validar()) {
        return;
    }

    const boton = $id('btn_guardar');
    const original = boton.innerHTML;
    const edicion = estado.modo === 'editar';
    const cierres = [...$id('modalTipoDocumento').querySelectorAll('[data-bs-dismiss="modal"]')];

    const datos = {
        empresa: empresaActual(),
        codigoOriginal: estado.codigoOriginal,
        codigo: texto($id('codigo').value),
        descripcion: texto($id('descripcion').value),
        descripcionCorta: texto($id('descripcionCorta').value),
        codigoTD: texto($id('codigoTD').value),
        mNit: $id('mNit').checked ? 1 : 0,
        equivalencia: texto($id('equivalencia').value)
    };

    boton.disabled = true;
    boton.innerHTML = '<i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Guardando...';
    cierres.forEach((cierre) => { cierre.disabled = true; });

    try {
        await pedir(edicion ? 'actualizar' : 'crear', datos);

        const nuevo = normalizar(`${datos.codigo} ${datos.descripcion} ${datos.descripcionCorta}`);

        if (estado.busqueda !== '' && !nuevo.includes(estado.busqueda)) {
            clearTimeout(temporizador);
            $id('filtro_busqueda').value = '';
            estado.busqueda = '';
        }

        modal().hide();
        Toast.fire({ icon: 'success', title: edicion ? 'Tipo de documento actualizado.' : 'Tipo de documento creado.' });
        await cargar();
    } catch (error) {
        avisar(error);
    } finally {
        boton.disabled = false;
        boton.innerHTML = original;
        cierres.forEach((cierre) => { cierre.disabled = false; });
    }
});

// ── Editar y eliminar ─────────────────────────────────────────────────────────

const abrirEdicion = async (codigo) => {
    try {
        const resultado = await pedir('obtener', { empresa: empresaActual(), codigo });

        abrirModal('editar', resultado.fila);
    } catch (error) {
        avisar(error);
    }
};

const avisarUso = (codigo, descripcion, usos) => Swal.fire({
    icon: 'error',
    title: 'El tipo de documento está en uso',
    html: `No se puede eliminar «${escaparHtml(codigo)} · ${escaparHtml(descripcion)}»: `
        + `<strong>${numero(usos)}</strong> ${usos === 1 ? 'tercero lo tiene' : 'terceros lo tienen'} asignado.<br>`
        + 'Cambie el tipo de documento de esos terceros antes de eliminarlo.',
    confirmButtonText: 'Entendido'
});

const eliminarFila = async (fila, codigo, descripcion) => {
    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar tipo de documento?',
        html: `Se eliminará «${escaparHtml(codigo)} · ${escaparHtml(descripcion)}».<br>Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar',
        focusCancel: true
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    const botones = [...fila.querySelectorAll('.btn')];
    const iconoBorrar = botones.find((boton) => boton.querySelector('.bi-trash'));

    botones.forEach((boton) => { boton.disabled = true; });
    if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i>';

    try {
        await pedir('eliminar', { empresa: empresaActual(), codigo });

        Toast.fire({ icon: 'success', title: 'Tipo de documento eliminado.' });
        await cargar();
    } catch (error) {
        const usos = Number(error.datos?.terceros || 0);

        if (usos > 0) {
            await avisarUso(codigo, descripcion, usos);
            await cargar();
            return;
        }

        avisar(error);
        botones.forEach((boton) => { boton.disabled = false; });
        if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
    }
};

tbody().addEventListener('click', (e) => {
    const boton = e.target.closest('[data-accion]');

    if (!boton) {
        return;
    }

    const fila = boton.closest('tr');

    if (boton.dataset.accion === 'editar') {
        abrirEdicion(fila.dataset.codigo ?? '');
        return;
    }

    eliminarFila(fila, fila.dataset.codigo ?? '', fila.dataset.descripcion ?? '');
});

// ── Arranque ──────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', cargar);
