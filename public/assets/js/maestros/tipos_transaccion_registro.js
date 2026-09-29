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

const URL_TIPOS = BASE_URL + 'maestros/tipos-transaccion/registro/';

const NATURALEZAS = { 0: 'No aplica', 1: 'Resta', 2: 'Suma', 3: 'Suma y resta' };
const ANULACIONES = { A: 'Anular', E: 'Eliminar' };

const $id = (id) => document.getElementById(id);
const mostrar = (elemento, visible) => elemento.classList.toggle('d-none', !visible);
const numero = (valor) => Number(valor || 0).toLocaleString('es-CO');
const texto = (valor) => String(valor ?? '').trim();
const normalizar = (valor) => texto(valor).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const estado = {
    filas: [], total: 0, busqueda: '', activos: '',
    modo: 'crear', codigoOriginal: '', actualOriginal: '', moduloHuerfano: '', vistaDsMemoria: ''
};

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

const accionesFila = (usos) => {
    const editar = '<button type="button" class="btn btn-outline-secondary btn-sm" data-accion="editar" title="Editar tipo de transacción">'
        + '<i class="bi bi-pencil" aria-hidden="true"></i></button>';

    const eliminar = usos > 0
        ? `<span class="d-inline-block maestros-accion-bloqueada" tabindex="0" title="No se puede eliminar: ${numero(usos)} ${usos === 1 ? 'registro lo usa' : 'registros lo usan'}.">`
            + '<button type="button" class="btn btn-outline-danger btn-sm pe-none" disabled aria-disabled="true" tabindex="-1">'
            + '<i class="bi bi-trash" aria-hidden="true"></i></button></span>'
        : '<button type="button" class="btn btn-outline-danger btn-sm" data-accion="eliminar" title="Eliminar tipo de transacción">'
            + '<i class="bi bi-trash" aria-hidden="true"></i></button>';

    return `<div class="d-flex justify-content-center gap-1">${editar}${eliminar}</div>`;
};

const esHuerfano = (fila) => texto(fila.moduloDescripcion) === '';

const celdaModulo = (fila) => {
    const crudo = texto(fila.modulo);
    const descripcion = texto(fila.moduloDescripcion);

    if (esHuerfano(fila)) {
        return '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>'
            + '<span class="maestros-valor-huerfano font-monospace"'
            + ' title="Este módulo ya no está en el catálogo de módulos. Al editar el registro deberá elegir uno válido.">'
            + `${escaparHtml(crudo)}</span>`;
    }

    return `<span class="maestros-truncar" title="${escaparHtml(descripcion)}">${escaparHtml(descripcion)}</span>`;
};

const celdaNumeracion = (fila) => {
    const automatica = Number(fila.numeracion) === 1;
    const prefijo = texto(fila.prefijo);
    const longitud = Number(fila.longitud || 0);
    const detalle = [
        prefijo === '' ? '' : `Prefijo ${escaparHtml(prefijo)}`,
        longitud > 0 ? `${numero(longitud)} dígitos` : ''
    ].filter((parte) => parte !== '').join(' <span class="maestros-sep">·</span> ');

    const badge = automatica
        ? '<span class="badge rounded-pill px-3 py-2 maestros-badge-si"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Automática</span>'
        : '<span class="maestros-vacio">Manual</span>';

    return badge + (detalle === '' ? '' : `<span class="maestros-subtexto">${detalle}</span>`);
};

const celdaReferencia = (fila) => {
    if (Number(fila.referencia) !== 1) {
        return '<span class="maestros-vacio">No</span>';
    }

    const vista = texto(fila.vistaDs);

    return '<span class="badge rounded-pill px-3 py-2 maestros-badge-si">Sí</span>'
        + (vista === ''
            ? '<span class="maestros-subtexto">Sin vista</span>'
            : `<span class="maestros-subtexto font-monospace maestros-truncar" title="${escaparHtml(vista)}">${escaparHtml(vista)}</span>`);
};

const subtextoDescripcion = (fila) => {
    const naturaleza = Number(fila.naturaleza || 0);
    const anulacion = texto(fila.modoAnulacion) === 'E' ? 'E' : 'A';

    if (naturaleza === 0 && anulacion === 'A') {
        return '';
    }

    const partes = [];

    if (naturaleza !== 0) {
        partes.push(`<span class="maestros-etiqueta-mini">Naturaleza:</span>${escaparHtml(NATURALEZAS[naturaleza] || naturaleza)}`);
    }

    if (anulacion !== 'A') {
        partes.push(`<span class="maestros-etiqueta-mini">Anulación:</span>${escaparHtml(ANULACIONES[anulacion])}`);
    }

    return `<span class="maestros-subtexto">${partes.join(' <span class="maestros-sep">·</span> ')}</span>`;
};

const filaHtml = (fila) => {
    const codigo = texto(fila.codigo);
    const descripcion = texto(fila.descripcion);
    const activo = Number(fila.activo) === 1;
    const automatica = Number(fila.numeracion) === 1;
    const conReferencia = Number(fila.referencia) === 1;
    const usos = Number(fila.usos || 0);
    const actual = fila.actual === null || fila.actual === '' ? null : Number(fila.actual);
    const buscar = normalizar(`${codigo} ${descripcion} ${texto(fila.prefijo)} ${texto(fila.modulo)} ${texto(fila.moduloDescripcion)}`);

    return `<tr class="${activo ? '' : 'maestros-fila-inactiva'}" data-codigo="${escaparHtml(codigo)}"`
        + ` data-descripcion="${escaparHtml(descripcion)}" data-activo="${activo ? 1 : 0}" data-buscar="${escaparHtml(buscar)}">`
        + `<td class="text-center font-monospace fw-semibold" data-order="${escaparHtml(codigo.toUpperCase().padStart(3, '0'))}">${escaparHtml(codigo)}</td>`
        + `<td><span class="maestros-truncar maestros-truncar-dato" title="${escaparHtml(descripcion)}">${escaparHtml(descripcion)}</span>${subtextoDescripcion(fila)}</td>`
        + `<td>${celdaModulo(fila)}</td>`
        + `<td data-order="${automatica ? 1 : 0}">${celdaNumeracion(fila)}</td>`
        + `<td class="text-end font-monospace" data-order="${actual === null ? 0 : actual}">`
        + `${actual === null ? '<span class="maestros-vacio">Sin número</span>' : numero(actual)}</td>`
        + `<td data-order="${conReferencia ? 1 : 0}">${celdaReferencia(fila)}</td>`
        + `<td class="text-center" data-order="${activo ? 1 : 0}">`
        + `<span class="badge rounded-pill px-3 py-2 ${activo ? 'maestros-badge-activo' : 'maestros-badge-inactivo'}">${activo ? 'Activo' : 'Inactivo'}</span></td>`
        + `<td class="text-center maestros-col-sticky">${accionesFila(usos)}</td>`
        + '</tr>';
};

const pintarEsqueleto = () => {
    const anchos = ['50%', '85%', '60%', '70%', '45%', '65%', '55%', '60%'];

    tbody().innerHTML = Array.from({ length: 5 }, () => '<tr class="placeholder-glow">'
        + anchos.map((ancho) => `<td><span class="placeholder" style="width:${ancho}"></span></td>`).join('')
        + '</tr>').join('');
};

const esDataTable = () => $.fn.DataTable.isDataTable('#tabla_tipos_transaccion');
const instanciaTabla = () => $('#tabla_tipos_transaccion').DataTable();

$.fn.dataTable.ext.search.push((configuracion, datos, indice) => {
    if (configuracion.nTable.id !== 'tabla_tipos_transaccion') {
        return true;
    }

    const tr = configuracion.aoData[indice].nTr;

    if (estado.activos !== '' && (tr?.dataset.activo || '') !== estado.activos) {
        return false;
    }

    return estado.busqueda === '' || (tr?.dataset.buscar || '').includes(estado.busqueda);
});

const destruirTabla = () => {
    $id('pie_tabla').innerHTML = '';

    if (esDataTable()) {
        instanciaTabla().destroy();
    }
};

const iniciarTabla = () => {
    const tabla = $('#tabla_tipos_transaccion').DataTable({
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
    ['filtro_empresa', 'filtro_busqueda', 'btn_limpiar_busqueda', 'filtro_estado', 'btn_nuevo'].forEach((id) => {
        $id(id).disabled = activo;
    });
};

const pintarAvisoHuerfanos = (visible) => {
    const huerfanas = estado.filas.filter(esHuerfano);
    const codigos = [...new Set(huerfanas.map((fila) => texto(fila.modulo)))];

    mostrar($id('aviso_modulos_huerfanos'), visible && huerfanas.length > 0);

    if (!visible || huerfanas.length === 0) {
        return;
    }

    $id('texto_modulos_huerfanos').innerHTML = huerfanas.length === 1
        ? `<strong>1</strong> tipo de transacción usa un módulo que ya no está en el catálogo (${escaparHtml(codigos.join(', '))}).`
            + ' Al editarlo deberá elegir un módulo válido antes de guardar.'
        : `<strong>${numero(huerfanas.length)}</strong> tipos de transacción usan un módulo que ya no está en el catálogo`
            + ` (${escaparHtml(codigos.join(', '))}). Al editarlos deberá elegir un módulo válido antes de guardar.`;
};

const tituloFiltro = () => {
    const busqueda = texto($id('filtro_busqueda').value);

    if (busqueda === '' && estado.activos !== '') {
        return `Ningún tipo de transacción está ${estado.activos === '1' ? 'activo' : 'inactivo'}`;
    }

    return `Ningún tipo de transacción coincide con «${escaparHtml(busqueda)}»`;
};

const actualizarEstados = () => {
    const info = esDataTable() ? instanciaTabla().page.info() : null;
    const visibles = info ? info.recordsDisplay : 0;
    const vacia = estado.total === 0;
    const sinCoincidencias = !vacia && visibles === 0;
    const conFilas = !vacia && !sinCoincidencias;
    const filtrando = estado.busqueda !== '' || estado.activos !== '';

    mostrar($id('estado_error'), false);
    mostrar($id('estado_vacio'), vacia);
    mostrar($id('estado_filtro'), sinCoincidencias);
    mostrar($id('contenedor_tabla'), conFilas);
    mostrar($id('pie_tabla'), conFilas && visibles > info.length);

    pintarAvisoHuerfanos(conFilas);
    mostrar($id('aviso_sin_formato'), conFilas && estado.filas.every((fila) => texto(fila.formato) === ''));

    if (sinCoincidencias) {
        $id('titulo_estado_filtro').innerHTML = tituloFiltro();
    }

    const plural = (filtrando ? visibles : estado.total) === 1 ? 'registro encontrado' : 'registros encontrados';

    $id('contador_registros').innerHTML = conFilas
        ? `<i class="bi bi-arrow-left-right me-1"></i><strong>${numero(visibles)}</strong>`
            + `${filtrando ? ` de ${numero(estado.total)}` : ''} ${plural}`
        : '';
};

const mostrarEstadoError = (mensaje) => {
    mostrar($id('estado_vacio'), false);
    mostrar($id('estado_filtro'), false);
    mostrar($id('contenedor_tabla'), false);
    mostrar($id('pie_tabla'), false);
    mostrar($id('aviso_modulos_huerfanos'), false);
    mostrar($id('aviso_sin_formato'), false);
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
    mostrar($id('aviso_modulos_huerfanos'), false);
    mostrar($id('aviso_sin_formato'), false);
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

const filtrar = () => {
    estado.busqueda = normalizar($id('filtro_busqueda').value);
    estado.activos = $id('filtro_estado').value;

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

const limpiarFiltros = () => {
    clearTimeout(temporizador);
    $id('filtro_busqueda').value = '';
    $id('filtro_estado').value = '';
    filtrar();
    $id('filtro_busqueda').focus();
};

const limpiarBusqueda = () => {
    clearTimeout(temporizador);
    $id('filtro_busqueda').value = '';
    filtrar();
    $id('filtro_busqueda').focus();
};

$id('filtro_estado').addEventListener('change', filtrar);
$id('btn_limpiar_busqueda').addEventListener('click', limpiarBusqueda);
$id('btn_limpiar_filtro').addEventListener('click', limpiarFiltros);
$id('btn_reintentar').addEventListener('click', cargar);
$id('filtro_empresa').addEventListener('change', cargar);

const modal = () => bootstrap.Modal.getOrCreateInstance($id('modalTipoTransaccion'));

const limpiarInvalidos = () => {
    $id('formulario_tipo').querySelectorAll('.is-invalid').forEach((campo) => campo.classList.remove('is-invalid'));
};

const marcarInvalido = (elemento, mensaje) => {
    const feedback = (elemento.closest('.input-group') || elemento.parentElement).querySelector('.invalid-feedback');
    const cerrado = elemento.closest('.maestros-colapso:not(.abierto)');

    if (cerrado) {
        cerrado.inert = false;
        cerrado.classList.add('abierto');
    }

    elemento.classList.add('is-invalid');
    if (feedback) feedback.textContent = mensaje;

    Toast.fire({ icon: 'warning', title: escaparHtml(mensaje) });
    elemento.focus();
    return false;
};

const colapso = (id, abierto) => $id(id).classList.toggle('abierto', abierto);

const pintarNumeracion = () => {
    const activa = $id('numeracion').checked;

    $id('panel_numeracion').classList.toggle('activo', activa);
    colapso('bloque_numeracion', activa);
    $id('bloque_numeracion').inert = !activa;

    if (!activa) {
        ['prefijo', 'longitud', 'actual'].forEach((id) => $id(id).classList.remove('is-invalid'));
    }
};

const pintarReferencia = () => {
    const activa = $id('referencia').checked;
    const campo = $id('vistaDs');

    $id('panel_referencia').classList.toggle('activo', activa);
    colapso('bloque_referencia', activa);

    campo.disabled = !activa;
    campo.value = activa ? estado.vistaDsMemoria : '';

    if (!activa) {
        campo.classList.remove('is-invalid');
    }
};

const consecutivoCambio = () => {
    const actual = texto($id('actual').value);

    if (estado.modo !== 'editar') {
        return false;
    }

    if (actual === '' || estado.actualOriginal === '') {
        return actual !== estado.actualOriginal;
    }

    return Number(actual) !== Number(estado.actualOriginal);
};

const pintarAvisoConsecutivo = () => {
    const cambio = consecutivoCambio() && $id('numeracion').checked;

    mostrar($id('aviso_consecutivo'), cambio);

    if (cambio) {
        $id('texto_aviso_consecutivo').innerHTML = 'Está cambiando el consecutivo vivo de esta transacción'
            + (estado.actualOriginal === ''
                ? ' (hoy sin número).'
                : ` (guardado: <strong>${numero(estado.actualOriginal)}</strong>).`)
            + ' Si lo baja, el sistema puede volver a generar números de documento que ya existen.';
    }
};

const quitarHuerfano = () => {
    $id('modulo').querySelector('optgroup[data-huerfano="1"]')?.remove();
    mostrar($id('chip_huerfano'), false);
    mostrar($id('nota_modulo_huerfano'), false);
    estado.moduloHuerfano = '';
};

const marcarHuerfano = (crudo) => {
    const select = $id('modulo');
    const grupo = document.createElement('optgroup');
    const opcion = document.createElement('option');

    estado.moduloHuerfano = crudo;
    grupo.label = 'Valor actual — no está en el catálogo';
    grupo.dataset.huerfano = '1';
    opcion.className = 'maestros-opcion-huerfana';
    opcion.dataset.huerfano = '1';
    opcion.value = '';
    opcion.textContent = `${crudo} — no está en el catálogo de módulos`;
    grupo.appendChild(opcion);
    select.insertBefore(grupo, select.firstChild);
    opcion.selected = true;

    mostrar($id('chip_huerfano'), true);
    $id('nota_modulo_huerfano').innerHTML = '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>'
        + `<span>El módulo guardado en este registro («${escaparHtml(crudo)}») ya no está en el catálogo de módulos.`
        + ' Elija el módulo correcto antes de guardar: el valor actual no se puede conservar.</span>';
    mostrar($id('nota_modulo_huerfano'), true);
};

const pintarCandado = (edicion) => {
    const grupo = $id('grupo_codigo');
    const campo = $id('codigo');

    grupo.querySelector('#candado_codigo')?.remove();
    campo.readOnly = edicion;
    campo.classList.toggle('bg-light', edicion);
    campo.classList.toggle('rounded-end', !edicion);

    if (!edicion) {
        campo.removeAttribute('aria-readonly');
        return;
    }

    const candado = document.createElement('span');

    campo.setAttribute('aria-readonly', 'true');
    candado.className = 'input-group-text bg-light';
    candado.id = 'candado_codigo';
    candado.innerHTML = '<i class="bi bi-lock-fill" aria-hidden="true"></i>';
    grupo.insertBefore(candado, grupo.querySelector('.invalid-feedback'));
};

$id('modulo').addEventListener('change', () => {
    if ($id('modulo').value !== '') {
        quitarHuerfano();
    }
});

$id('numeracion').addEventListener('change', () => {
    pintarNumeracion();
    pintarAvisoConsecutivo();
});
$id('referencia').addEventListener('change', () => {
    if (!$id('referencia').checked) {
        estado.vistaDsMemoria = texto($id('vistaDs').value);
    }

    pintarReferencia();
});
$id('activo').addEventListener('change', () => $id('panel_activo').classList.toggle('activo', $id('activo').checked));
$id('actual').addEventListener('input', pintarAvisoConsecutivo);
$id('prefijo').addEventListener('input', (e) => { e.target.value = e.target.value.toUpperCase(); });
$id('formulario_tipo').addEventListener('input', (e) => e.target.classList.remove('is-invalid'));
$id('modalTipoTransaccion').addEventListener('hidden.bs.modal', () => { estado.vistaDsMemoria = ''; });

const abrirModal = (modo, fila) => {
    const edicion = modo === 'editar';
    const datos = edicion ? fila : {};

    estado.modo = modo;
    estado.codigoOriginal = edicion ? texto(datos.codigo) : '';
    estado.actualOriginal = edicion && texto(datos.actual) !== '' ? String(Number(datos.actual)) : '';
    estado.vistaDsMemoria = edicion ? texto(datos.vistaDs) : '';
    limpiarInvalidos();
    quitarHuerfano();

    $id('titulo_modal').innerHTML = edicion
        ? '<i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar tipo de transacción'
        : '<i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo tipo de transacción';
    $id('chip_empresa').textContent = razonSocialActual();

    $id('codigo').value = edicion ? texto(datos.codigo) : '';
    $id('descripcion').value = edicion ? texto(datos.descripcion) : '';
    $id('activo').checked = !edicion || Number(datos.activo) === 1;
    $id('modulo').value = edicion ? texto(datos.modulo) : '';
    $id('naturaleza').value = edicion ? String(Number(datos.naturaleza || 0)) : '0';
    $id('modoAnulacion').value = edicion && texto(datos.modoAnulacion) === 'E' ? 'E' : 'A';
    $id('numeracion').checked = edicion ? Number(datos.numeracion) === 1 : true;
    $id('prefijo').value = edicion ? texto(datos.prefijo) : '';
    $id('longitud').value = edicion && texto(datos.longitud) !== '' ? String(Number(datos.longitud)) : '';
    $id('actual').value = estado.actualOriginal;
    $id('referencia').checked = edicion && Number(datos.referencia) === 1;
    $id('vistaDs').value = '';
    $id('formato').value = edicion ? texto(datos.formato) : '';

    if (edicion && esHuerfano(datos)) {
        marcarHuerfano(texto(datos.modulo));
    }

    $id('panel_activo').classList.toggle('activo', $id('activo').checked);
    pintarNumeracion();
    pintarReferencia();
    pintarAvisoConsecutivo();
    pintarCandado(edicion);

    $id('ayuda_codigo').textContent = edicion
        ? 'El código identifica el registro y no se puede modificar. Si está mal, cree un tipo nuevo con el código correcto.'
        : 'Máximo 50 caracteres. Identifica la transacción en todos los documentos.';

    modal().show();
    setTimeout(() => $id(edicion ? 'descripcion' : 'codigo').focus(), 300);
};

const validar = () => {
    limpiarInvalidos();

    const codigo = texto($id('codigo').value);
    const repetido = estado.filas.some((fila) => texto(fila.codigo).toLowerCase() === codigo.toLowerCase());
    const longitud = texto($id('longitud').value);
    const actual = texto($id('actual').value);

    if (codigo === '') return marcarInvalido($id('codigo'), 'Escriba el código del tipo de transacción.');
    if (codigo.length > 50) return marcarInvalido($id('codigo'), 'El código admite máximo 50 caracteres.');
    if (estado.modo === 'crear' && repetido) return marcarInvalido($id('codigo'), 'El código ya existe en esta empresa.');
    if (texto($id('descripcion').value) === '') return marcarInvalido($id('descripcion'), 'Escriba la descripción.');

    if ($id('modulo').value === '') {
        return marcarInvalido($id('modulo'), estado.moduloHuerfano === ''
            ? 'Elija el módulo.'
            : `Elija un módulo del catálogo: el módulo guardado («${estado.moduloHuerfano}») ya no existe.`);
    }

    if ($id('naturaleza').value === '') return marcarInvalido($id('naturaleza'), 'Elija la naturaleza.');
    if ($id('modoAnulacion').value === '') return marcarInvalido($id('modoAnulacion'), 'Elija el método de anulación.');
    if (longitud !== '' && !/^\d+$/.test(longitud)) return marcarInvalido($id('longitud'), 'La longitud debe ser un número entero.');
    if (actual !== '' && !/^\d+$/.test(actual)) return marcarInvalido($id('actual'), 'El número actual debe ser un número entero mayor o igual a cero.');
    if (actual !== '' && Number(actual) > 2147483647) return marcarInvalido($id('actual'), 'El número actual no puede ser mayor que 2.147.483.647.');

    if ($id('referencia').checked && texto($id('vistaDs').value) === '') {
        return marcarInvalido($id('vistaDs'), 'Escriba la vista o procedimiento de referencia, o desactive «Requiere documento de referencia».');
    }

    return true;
};

const textoConsecutivo = (codigo) => {
    const actual = texto($id('actual').value);
    const encabezado = `El consecutivo de «${escaparHtml(codigo)}»`;

    if (estado.actualOriginal === '') {
        return `${encabezado}, <strong>hoy sin número</strong>, quedará en <strong>${numero(actual)}</strong>.`;
    }

    if (actual === '') {
        return `${encabezado}, hoy en <strong>${numero(estado.actualOriginal)}</strong>, quedará <strong>sin número</strong>.`;
    }

    return `${encabezado} pasará de <strong>${numero(estado.actualOriginal)}</strong> a <strong>${numero(actual)}</strong>.`;
};

const confirmarConsecutivo = async (codigo) => {
    if (!consecutivoCambio()) {
        return true;
    }

    const actual = texto($id('actual').value);
    const menor = actual === '' || Number(actual) < Number(estado.actualOriginal || 0);

    let consejo = 'Si el nuevo número es menor, el sistema puede generar documentos con números ya usados.';

    if (actual === '') {
        consejo = 'Al quedar sin número, la numeración puede reiniciarse y repetir documentos ya registrados.';
    } else if (estado.actualOriginal === '') {
        consejo = 'Verifique el número antes de guardar: a partir de él se numerarán los documentos nuevos.';
    }

    const confirmacion = await Swal.fire({
        title: '¿Cambiar el número actual?',
        html: textoConsecutivo(codigo) + '<br>' + consejo,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiarlo',
        confirmButtonColor: menor ? '#dc3545' : undefined,
        cancelButtonText: 'Cancelar',
        focusCancel: true
    });

    return confirmacion.isConfirmed;
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
    const cierres = [...$id('modalTipoTransaccion').querySelectorAll('[data-bs-dismiss="modal"]')];

    const datos = {
        empresa: empresaActual(),
        codigoOriginal: estado.codigoOriginal,
        codigo: texto($id('codigo').value),
        descripcion: texto($id('descripcion').value),
        numeracion: $id('numeracion').checked ? 1 : 0,
        actual: texto($id('actual').value),
        prefijo: texto($id('prefijo').value),
        longitud: texto($id('longitud').value),
        naturaleza: $id('naturaleza').value,
        modulo: $id('modulo').value,
        modoAnulacion: $id('modoAnulacion').value,
        referencia: $id('referencia').checked ? 1 : 0,
        vistaDs: $id('referencia').checked ? texto($id('vistaDs').value) : '',
        activo: $id('activo').checked ? 1 : 0,
        formato: texto($id('formato').value)
    };

    if (!await confirmarConsecutivo(datos.codigo)) {
        return;
    }

    boton.disabled = true;
    boton.innerHTML = '<i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Guardando...';
    cierres.forEach((cierre) => { cierre.disabled = true; });

    try {
        const selector = $id('modulo');
        const nuevo = normalizar(`${datos.codigo} ${datos.descripcion} ${datos.prefijo} ${datos.modulo} `
            + texto(selector.options[selector.selectedIndex]?.text));

        await pedir(edicion ? 'actualizar' : 'crear', datos);

        if (estado.busqueda !== '' && !nuevo.includes(estado.busqueda)) {
            clearTimeout(temporizador);
            $id('filtro_busqueda').value = '';
            estado.busqueda = '';
        }

        if (estado.activos !== '' && estado.activos !== String(datos.activo)) {
            $id('filtro_estado').value = '';
            estado.activos = '';
        }

        modal().hide();
        Toast.fire({ icon: 'success', title: edicion ? 'Tipo de transacción actualizado.' : 'Tipo de transacción creado.' });
        await cargar();
    } catch (error) {
        avisar(error);
    } finally {
        boton.disabled = false;
        boton.innerHTML = original;
        cierres.forEach((cierre) => { cierre.disabled = false; });
    }
});

const abrirEdicion = async (codigo) => {
    try {
        const resultado = await pedir('obtener', { empresa: empresaActual(), codigo });

        abrirModal('editar', resultado.fila);
    } catch (error) {
        avisar(error);
    }
};

const avisarDependencias = (datos, codigo) => {
    const dependencias = [...(datos.dependencias || [])].sort((a, b) => Number(b.filas) - Number(a.filas));
    const detalle = dependencias.slice(0, 6)
        .map((dependencia) => `<li><strong>${escaparHtml(dependencia.tabla)}</strong> · ${numero(dependencia.filas)} `
            + `${Number(dependencia.filas) === 1 ? 'registro' : 'registros'}</li>`)
        .join('');
    const restantes = dependencias.length - 6;

    return Swal.fire({
        icon: 'error',
        title: `«${escaparHtml(codigo)}» está en uso`,
        html: 'No se puede eliminar: hay registros que usan este tipo de transacción.'
            + `<ul class="text-start small mb-2">${detalle}`
            + `${restantes > 0 ? `<li class="maestros-vacio">y ${numero(restantes)} ${restantes === 1 ? 'tabla más' : 'tablas más'}</li>` : ''}</ul>`
            + 'Si ya no debe usarse, desactívelo con el interruptor «Activo» en lugar de eliminarlo.',
        confirmButtonText: 'Entendido'
    });
};

const eliminarFila = async (fila, codigo, descripcion) => {
    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar tipo de transacción?',
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

        Toast.fire({ icon: 'success', title: 'Tipo de transacción eliminado.' });
        await cargar();
    } catch (error) {
        if ((error.datos?.dependencias || []).length > 0) {
            await avisarDependencias(error.datos, codigo);
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

document.addEventListener('DOMContentLoaded', cargar);
