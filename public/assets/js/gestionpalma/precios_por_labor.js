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

const URL_PRECIOS = BASE_URL + 'gestion-palma/precios/por-labor/';

const $id = (id) => document.getElementById(id);

const avisar = (response, mensaje) => {
    const texto = mensaje || 'No se pudo completar la operación.';
    if (response.status === 401 || texto.length > 70) {
        Alerta.fire({ icon: 'warning', title: response.status === 401 ? 'Sesión expirada' : 'Atención', text: texto });
        return;
    }
    Toast.fire({ icon: 'warning', title: texto });
};

const pedir = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));

    try {
        const response = await fetch(URL_PRECIOS + accion, { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
            return null;
        }

        return result;
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
        return null;
    }
};

const escapar = (texto) => String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// ── Panel A: años ─────────────────────────────────────────────────────────────

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroBusqueda = () => $id('filtro_busqueda').value.trim();

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_anios')) {
        $('#tabla_anios').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_anios').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_anios').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [5], orderable: false }],
        order: [[0, 'desc']],
        language: {
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay datos disponibles'
        }
    });
};

const actualizarEstadoAnios = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_anios').innerHTML =
        `<i class="bi bi-tags me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'año registrado' : 'años registrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const datosAnio = (anio) => {
    const fila = document.querySelector(`#cuerpo_tabla_anios tr[data-anio="${CSS.escape(String(anio))}"]`);
    return {
        labores: fila ? Number(fila.dataset.labores || 0) : 0,
        conPrecio: fila ? Number(fila.dataset.conPrecio || 0) : 0
    };
};

let laboresActivas = LABORES_ACTIVAS;
let aniosEmpresa = ANIOS_INICIALES.map(String);
let modalAnios = [];
let modalLabores = 0;

const listar = async () => {
    const result = await pedir('listar', { empresa: filtroEmpresa(), busqueda: filtroBusqueda() });
    if (!result) return;
    destruirTabla();
    $id('cuerpo_tabla_anios').innerHTML = result.tabla || '';
    renderizarTabla();
    actualizarEstadoAnios(result.total ?? 0);
    laboresActivas = Number(result.laboresActivas ?? laboresActivas);
    aniosEmpresa = (result.anios || []).map(String).sort((a, b) => b - a);
    $id('editor_anio').innerHTML = aniosEmpresa.map((a) => `<option value="${a}">${a}</option>`).join('');
};

$id('filtro_empresa').addEventListener('change', listar);
$id('btn_buscar').addEventListener('click', listar);
$id('btn_limpiar_busqueda').addEventListener('click', () => {
    $id('filtro_busqueda').value = '';
    listar();
});
$id('filtro_busqueda').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        listar();
    }
});

// ── Editor: estado ────────────────────────────────────────────────────────────

const estado = { anio: null, filas: [], grupos: [] };

const CAMPOS = ['precioDestajo', 'precioContratistas', 'precioOtros', 'porcentaje'];

const aNumero = (texto) => {
    const limpio = String(texto ?? '').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    const numero = parseFloat(limpio);
    return isNaN(numero) ? 0 : numero;
};

const decimales = (campo) => (campo === 'porcentaje' ? 3 : 4);

const canonico = (numero, dec) => String(Number(Number(numero).toFixed(dec)));

const formatear = (numero, dec) => new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: dec }).format(numero);

const pintarInput = (input) => {
    const numero = aNumero(input.value);
    input.value = formatear(numero, decimales(input.dataset.campo));
    input.classList.toggle('gp-cero', numero === 0);
};

const valorActual = (el) => (el.type === 'checkbox'
    ? (el.checked ? '1' : '0')
    : canonico(aNumero(el.value), decimales(el.dataset.campo)));

const aplicarAtenuacion = (tr) => {
    const base = tr.querySelector('[data-campo="baseSueldo"]').checked;
    const destajo = tr.querySelector('[data-campo="precioDestajo"]');
    const porcentaje = tr.querySelector('[data-campo="porcentaje"]');
    destajo.classList.toggle('gp-valor-ignorado', base);
    destajo.title = base ? 'Este valor no se usa mientras Base sueldo esté activo.' : '';
    porcentaje.classList.toggle('gp-valor-ignorado', !base);
    porcentaje.classList.toggle('gp-valor-vigente', base && aNumero(porcentaje.value) > 0);
};

const marcarFila = (tr) => {
    let cambiada = false;
    tr.querySelectorAll('[data-campo]').forEach((el) => {
        const distinto = valorActual(el) !== el.dataset.orig;
        if (el.type !== 'checkbox') el.classList.toggle('gp-editado', distinto);
        if (distinto) cambiada = true;
    });
    tr.classList.toggle('gp-fila-modificada', cambiada);
    tr.querySelector('.gp-col-marca').innerHTML = cambiada
        ? '<span class="gp-punto-cambio" title="Cambio sin guardar"></span>'
        : '';
};

const filasModificadas = () => [...document.querySelectorAll('#cuerpo_grilla_precios tr.gp-fila-modificada')];

const actualizarCambios = () => {
    const modificadas = filasModificadas();
    const total = modificadas.length;
    const contador = $id('contador_cambios');
    contador.classList.toggle('d-none', total === 0);
    contador.innerHTML = `<i class="bi bi-pencil-fill me-1"></i>${total} ${total === 1 ? 'cambio sin guardar' : 'cambios sin guardar'}`;
    $id('btn_descartar').classList.toggle('d-none', total === 0);
    $id('btn_guardar_cambios').disabled = total === 0;

    document.querySelectorAll('#cuerpo_grilla_precios tr.gp-fila-grupo').forEach((grupo) => {
        const cuantas = modificadas.filter((f) => f.dataset.grupo === grupo.dataset.grupo).length;
        const marca = grupo.querySelector('.gp-grupo-cambios');
        marca.classList.toggle('d-none', cuantas === 0);
        marca.textContent = `${cuantas} con cambios`;
    });
};

const actualizarResumen = () => {
    const filas = [...document.querySelectorAll('#cuerpo_grilla_precios tr[data-novedad]')];
    const total = filas.length;
    const conPrecio = filas.filter((f) => f.dataset.existe === '1').length;
    const huerfanas = filas.filter((f) => f.dataset.huerfana === '1').length;
    $id('editor_resumen').textContent =
        `${total} ${total === 1 ? 'labor' : 'labores'} · ${conPrecio} con precio · ${huerfanas} no ${huerfanas === 1 ? 'registrada' : 'registradas'}`;
    $id('editor_aviso_huerfanas').classList.toggle('d-none', huerfanas === 0);
};

// ── Editor: render ────────────────────────────────────────────────────────────

const inputHTML = (fila, campo, etiqueta) => {
    const dec = decimales(campo);
    const valor = Number(Number(fila[campo] ?? 0).toFixed(dec));
    const nombre = fila.huerfana ? 'no registrada' : escapar(fila.descripcion);
    return `<input type="text" class="form-control form-control-sm gp-input-precio${valor === 0 ? ' gp-cero' : ''}" `
        + `inputmode="decimal" autocomplete="off" data-campo="${campo}" data-orig="${canonico(valor, dec)}" `
        + `value="${formatear(valor, dec)}" aria-label="${etiqueta} de la labor ${escapar(fila.novedad)} ${nombre}">`;
};

const filaHTML = (fila) => {
    const huerfana = Number(fila.huerfana) === 1;
    const sinPrecio = !huerfana && Number(fila.existe) === 0;
    const base = Number(fila.baseSueldo) === 1;
    const grupo = huerfana ? '__huerfanas' : String(fila.grupo ?? '');
    const codigo = escapar(fila.novedad);

    let clases = '';
    let chip = '';
    if (sinPrecio) {
        clases = 'gp-fila-sinprecio';
        chip = '<span class="gp-chip-fila verde mt-1 d-inline-block" title="Esta labor aún no tiene precio en este año. Se creará al guardar.">'
            + '<i class="bi bi-plus-circle-dotted me-1"></i>Sin precio</span>';
    } else if (huerfana) {
        clases = 'gp-fila-huerfana';
        chip = `<span class="gp-chip-fila ambar mt-1 d-inline-block" title="El código ${codigo} tiene precios en este año, pero ya no existe en el maestro de labores. Los datos se conservan.">`
            + '<i class="bi bi-exclamation-triangle me-1"></i>Labor no registrada</span>';
    }

    const descripcion = huerfana
        ? '<span class="text-muted">&mdash;</span>'
        : `<div class="gp-truncar" style="max-width:320px" title="${escapar(fila.descripcion)}">${escapar(fila.descripcion)}</div>`;

    const etiquetaGrupo = huerfana
        ? '<span class="text-muted">&mdash;</span>'
        : `<span class="badge gp-badge-doc rounded-pill px-2 py-1">${escapar(fila.grupoDescripcion || fila.grupo)}</span>`;

    const nombre = huerfana ? 'no registrada' : escapar(fila.descripcion);

    return `<tr class="${clases}" data-novedad="${codigo}" data-grupo="${escapar(grupo)}" `
        + `data-existe="${huerfana ? 1 : Number(fila.existe)}" data-huerfana="${huerfana ? 1 : 0}" `
        + `data-texto="${escapar((fila.novedad + ' ' + (fila.descripcion || '')).toLowerCase())}">`
        + '<td class="gp-col-marca"></td>'
        + `<td class="text-center gp-col-sticky-izq"><span class="badge gp-badge-doc font-monospace">${codigo}</span></td>`
        + `<td>${descripcion}${chip}</td>`
        + `<td>${etiquetaGrupo}</td>`
        + `<td class="text-end">${inputHTML(fila, 'precioDestajo', 'Destajo')}</td>`
        + `<td class="text-end">${inputHTML(fila, 'precioContratistas', 'Contratistas')}</td>`
        + `<td class="text-end">${inputHTML(fila, 'precioOtros', 'Otros')}</td>`
        + `<td class="text-end">${inputHTML(fila, 'porcentaje', 'Porcentaje')}</td>`
        + '<td class="text-center"><div class="form-check form-switch d-flex justify-content-center m-0">'
        + `<input class="form-check-input m-0" type="checkbox" data-campo="baseSueldo" data-orig="${base ? 1 : 0}"${base ? ' checked' : ''} `
        + `aria-label="Base sueldo de la labor ${codigo} ${nombre}"></div></td>`
        + '</tr>';
};

const grupoHTML = (clave, etiqueta, cuantas) =>
    `<tr class="gp-fila-grupo" data-grupo="${escapar(clave)}" data-etiqueta="${escapar(etiqueta)}"><td colspan="9">`
    + '<div class="d-flex justify-content-between align-items-center">'
    + `<span class="gp-grupo-titulo">${escapar(etiqueta)} &middot; ${cuantas} ${cuantas === 1 ? 'labor' : 'labores'}</span>`
    + '<span class="gp-grupo-cambios d-none"></span></div></td></tr>';

const pintarGrilla = () => {
    const bloques = new Map();
    estado.filas.forEach((fila) => {
        const huerfana = Number(fila.huerfana) === 1;
        const clave = huerfana ? '__huerfanas' : String(fila.grupo ?? '');
        const etiqueta = huerfana ? 'Labores no registradas' : String(fila.grupoDescripcion || fila.grupo || 'Sin grupo');
        if (!bloques.has(clave)) bloques.set(clave, { etiqueta, filas: [] });
        bloques.get(clave).filas.push(fila);
    });

    const claves = [...bloques.keys()]
        .filter((c) => c !== '__huerfanas')
        .sort((a, b) => bloques.get(a).etiqueta.localeCompare(bloques.get(b).etiqueta, 'es'));
    if (bloques.has('__huerfanas')) claves.push('__huerfanas');

    let html = '';
    claves.forEach((clave) => {
        const bloque = bloques.get(clave);
        bloque.filas.sort((a, b) => String(a.novedad).localeCompare(String(b.novedad), 'es'));
        html += grupoHTML(clave, bloque.etiqueta, bloque.filas.length);
        bloque.filas.forEach((fila) => { html += filaHTML(fila); });
    });

    $id('cuerpo_grilla_precios').innerHTML = html;
    document.querySelectorAll('#cuerpo_grilla_precios tr[data-novedad]').forEach(aplicarAtenuacion);
    actualizarResumen();
    actualizarCambios();
    filtrarGrilla();
};

// ── Editor: filtros en cliente ────────────────────────────────────────────────

const filtrarGrilla = () => {
    const grupo = $id('editor_grupo').value;
    const texto = $id('editor_busqueda').value.trim().toLowerCase();
    const mostrar = $id('editor_mostrar').value;
    const conSeparadores = grupo === '' && texto === '';
    const visiblesPorGrupo = {};
    let visibles = 0;

    document.querySelectorAll('#cuerpo_grilla_precios tr[data-novedad]').forEach((tr) => {
        let ok = true;
        if (grupo !== '' && tr.dataset.grupo !== grupo) ok = false;
        if (ok && texto !== '' && !tr.dataset.texto.includes(texto)) ok = false;
        if (ok && mostrar === 'con' && tr.dataset.existe !== '1') ok = false;
        if (ok && mostrar === 'sin' && tr.dataset.existe !== '0') ok = false;
        if (ok && mostrar === 'huerfanas' && tr.dataset.huerfana !== '1') ok = false;
        tr.classList.toggle('d-none', !ok);
        if (ok) {
            visibles++;
            visiblesPorGrupo[tr.dataset.grupo] = (visiblesPorGrupo[tr.dataset.grupo] || 0) + 1;
        }
    });

    document.querySelectorAll('#cuerpo_grilla_precios tr.gp-fila-grupo').forEach((tr) => {
        const cuantas = visiblesPorGrupo[tr.dataset.grupo] || 0;
        tr.classList.toggle('d-none', !conSeparadores || cuantas === 0);
        tr.querySelector('.gp-grupo-titulo').innerHTML =
            `${escapar(tr.dataset.etiqueta)} &middot; ${cuantas} ${cuantas === 1 ? 'labor' : 'labores'}`;
    });

    $id('gp_editor_vacio').classList.toggle('d-none', visibles > 0);
    $id('cuerpo_grilla_precios').closest('table').classList.toggle('d-none', visibles === 0);
};

['editor_grupo', 'editor_mostrar'].forEach((id) => $id(id).addEventListener('change', filtrarGrilla));
$id('editor_busqueda').addEventListener('input', filtrarGrilla);
$id('editor_busqueda').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') e.preventDefault();
});
$id('btn_limpiar_busqueda_editor').addEventListener('click', () => {
    $id('editor_busqueda').value = '';
    filtrarGrilla();
});
$id('btn_limpiar_filtros_editor').addEventListener('click', () => {
    $id('editor_grupo').value = '';
    $id('editor_busqueda').value = '';
    $id('editor_mostrar').value = '';
    filtrarGrilla();
});

// ── Editor: edición ───────────────────────────────────────────────────────────

const grilla = $id('cuerpo_grilla_precios');

grilla.addEventListener('focusin', (e) => {
    if (e.target.classList.contains('gp-input-precio')) {
        e.target.value = String(aNumero(e.target.value)).replace('.', ',');
        e.target.select();
    }
});

grilla.addEventListener('focusout', (e) => {
    if (e.target.classList.contains('gp-input-precio')) {
        pintarInput(e.target);
        const tr = e.target.closest('tr');
        aplicarAtenuacion(tr);
        marcarFila(tr);
        actualizarCambios();
    }
});

grilla.addEventListener('input', (e) => {
    if (e.target.classList.contains('gp-input-precio')) {
        const tr = e.target.closest('tr');
        marcarFila(tr);
        actualizarCambios();
    }
});

grilla.addEventListener('change', (e) => {
    if (e.target.dataset.campo === 'baseSueldo') {
        const tr = e.target.closest('tr');
        aplicarAtenuacion(tr);
        marcarFila(tr);
        actualizarCambios();
    }
});

grilla.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || !e.target.classList.contains('gp-input-precio')) return;
    e.preventDefault();
    const campo = e.target.dataset.campo;
    let fila = e.target.closest('tr').nextElementSibling;
    while (fila && (!fila.dataset.novedad || fila.classList.contains('d-none'))) {
        fila = fila.nextElementSibling;
    }
    if (fila) fila.querySelector(`[data-campo="${campo}"]`).focus();
});

// ── Editor: apertura y salida ─────────────────────────────────────────────────

const hayCambios = () => filasModificadas().length > 0;

const confirmarSalida = async (titulo, confirmar) => {
    const total = filasModificadas().length;
    const respuesta = await Alerta.fire({
        icon: 'warning',
        title: titulo,
        text: `Hay ${total} ${total === 1 ? 'cambio' : 'cambios'} sin guardar en el año ${estado.anio}. Si continúa, se perderán.`,
        showCancelButton: true,
        confirmButtonText: confirmar,
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Seguir editando'
    });
    return respuesta.isConfirmed;
};

const cargarDetalle = async (anio) => {
    const result = await pedir('detalle', { empresa: filtroEmpresa(), anio });
    if (!result) return false;

    estado.anio = Number(result.anio ?? anio);
    estado.filas = result.filas || [];
    estado.grupos = result.grupos || [];

    $id('editor_anio_titulo').textContent = estado.anio;
    $id('editor_grupo').innerHTML = '<option value="">Todos los grupos</option>'
        + estado.grupos.map((g) => `<option value="${escapar(g.codigo)}">${escapar(g.descripcion || g.codigo)}</option>`).join('');
    $id('editor_busqueda').value = '';
    $id('editor_mostrar').value = '';
    if (![...$id('editor_anio').options].some((o) => o.value === String(estado.anio))) {
        $id('editor_anio').insertAdjacentHTML('afterbegin', `<option value="${estado.anio}">${estado.anio}</option>`);
    }
    $id('editor_anio').value = String(estado.anio);
    pintarGrilla();
    return true;
};

const abrirEditor = async (anio) => {
    if (!(await cargarDetalle(anio))) return;
    $id('panel_anios').classList.add('d-none');
    const panel = $id('panel_editor');
    panel.classList.remove('d-none', 'gp-revelar');
    void panel.offsetWidth;
    panel.classList.add('gp-revelar');
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cerrarEditor = () => {
    $id('panel_editor').classList.add('d-none');
    $id('panel_anios').classList.remove('d-none');
    estado.anio = null;
    estado.filas = [];
    $id('cuerpo_grilla_precios').innerHTML = '';
    actualizarCambios();
};

$id('btn_volver').addEventListener('click', async () => {
    if (hayCambios() && !(await confirmarSalida('¿Salir sin guardar?', 'Salir sin guardar'))) return;
    cerrarEditor();
    listar();
});

$id('btn_descartar').addEventListener('click', async () => {
    if (!(await confirmarSalida('¿Descartar los cambios?', 'Sí, descartar'))) return;
    pintarGrilla();
});

$id('editor_anio').addEventListener('change', async (e) => {
    const destino = e.target.value;
    if (hayCambios() && !(await confirmarSalida('¿Salir sin guardar?', 'Salir sin guardar'))) {
        e.target.value = String(estado.anio);
        return;
    }
    await cargarDetalle(destino);
});

window.addEventListener('beforeunload', (e) => {
    if (!$id('panel_editor').classList.contains('d-none') && hayCambios()) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ── Editor: guardar ───────────────────────────────────────────────────────────

$id('btn_guardar_cambios').addEventListener('click', async () => {
    const modificadas = filasModificadas();
    if (modificadas.length === 0) return;

    const btn = $id('btn_guardar_cambios');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const filas = modificadas.map((tr) => {
        const dato = { novedad: tr.dataset.novedad };
        CAMPOS.forEach((campo) => { dato[campo] = aNumero(tr.querySelector(`[data-campo="${campo}"]`).value); });
        dato.baseSueldo = tr.querySelector('[data-campo="baseSueldo"]').checked ? 1 : 0;
        return dato;
    });

    const result = await pedir('guardar', {
        empresa: filtroEmpresa(),
        anio: estado.anio,
        filas: JSON.stringify(filas)
    });

    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar cambios';

    if (!result) {
        btn.disabled = false;
        return;
    }

    modificadas.forEach((tr) => {
        tr.querySelectorAll('[data-campo]').forEach((el) => {
            el.dataset.orig = String(valorActual(el));
            el.classList.remove('gp-editado');
        });
        tr.dataset.existe = '1';
        tr.classList.remove('gp-fila-modificada', 'gp-fila-sinprecio', 'gp-flash-guardado');
        tr.querySelector('.gp-chip-fila.verde')?.remove();
        tr.querySelector('.gp-col-marca').innerHTML = '';
        void tr.offsetWidth;
        tr.classList.add('gp-flash-guardado');
        setTimeout(() => tr.classList.remove('gp-flash-guardado'), 1300);
    });

    const guardadas = new Map(filas.map((f) => [String(f.novedad), f]));
    estado.filas = estado.filas.map((f) => {
        const nueva = guardadas.get(String(f.novedad));
        return nueva ? { ...f, ...nueva, novedad: f.novedad, existe: 1 } : f;
    });

    const total = Number(result.creados ?? 0) + Number(result.actualizados ?? 0);
    Toast.fire({ icon: 'success', title: total === 1 ? 'Se guardó 1 precio.' : `Se guardaron ${total} precios.` });
    actualizarResumen();
    actualizarCambios();
});

// ── Acciones de la lista de años ──────────────────────────────────────────────

const modalAnio = () => bootstrap.Modal.getOrCreateInstance($id('modalAnio'));

const validarAnioNuevo = () => {
    const anio = $id('anio_valor').value.trim();
    const existe = anio !== '' && modalAnios.includes(anio);
    $id('anio_aviso_existe').classList.toggle('d-none', !existe);
    if (existe) {
        $id('anio_aviso_texto').textContent = `El año ${anio} ya tiene lista de precios. Elija otro año o edite el existente.`;
    }
    $id('btn_guardar_anio').disabled = existe || ($id('anio_modo_replicar').disabled && $id('anio_modo_ceros').disabled);
};

const actualizarAyudaOrigen = () => {
    const origen = $id('anio_origen').value;
    const totales = String($id('anio_empresa').value) === String(filtroEmpresa()) ? datosAnio(origen).labores : 0;
    $id('anio_origen_ayuda').textContent = totales
        ? `Se copiarán los ${totales} precios de ${origen}.`
        : `Se copiarán los precios de ${origen}.`;
};

const marcarOpcion = () => {
    const replicar = $id('anio_modo_replicar').checked;
    $id('panel_origen_replicar').classList.toggle('activo', replicar);
    $id('panel_origen_ceros').classList.toggle('activo', $id('anio_modo_ceros').checked);
    const bloque = $id('anio_bloque_origen');
    bloque.classList.toggle('d-none', !replicar);
    if (!replicar) {
        $id('anio_origen_ayuda').textContent = '';
        return;
    }
    bloque.classList.remove('gp-revelar');
    void bloque.offsetWidth;
    bloque.classList.add('gp-revelar');
    actualizarAyudaOrigen();
};

const configurarModal = (origen) => {
    const hayAnios = modalAnios.length > 0;
    const hayLabores = modalLabores > 0;
    $id('anio_origen').innerHTML = modalAnios.map((a) => `<option value="${a}">${a}</option>`).join('');
    if (origen && modalAnios.includes(String(origen))) $id('anio_origen').value = String(origen);
    $id('anio_modo_replicar').disabled = !hayAnios;
    $id('anio_modo_ceros').disabled = !hayLabores;
    $id('anio_modo_replicar').checked = hayAnios;
    $id('anio_modo_ceros').checked = !hayAnios && hayLabores;
    $id('panel_origen_replicar').classList.toggle('inhabilitado', !hayAnios);
    $id('panel_origen_ceros').classList.toggle('inhabilitado', !hayLabores);
    $id('anio_sin_origen').classList.toggle('d-none', hayAnios);
    $id('anio_ceros_ayuda').classList.toggle('d-none', !hayLabores);
    $id('anio_ceros_ayuda').textContent = `Se crearán ${modalLabores} filas, una por cada labor activa.`;
    $id('anio_sin_labores').classList.toggle('d-none', hayLabores);
    marcarOpcion();
    validarAnioNuevo();
};

const cargarOpcionesModal = async (empresa, origen) => {
    if (String(empresa) === String(filtroEmpresa())) {
        modalAnios = aniosEmpresa.slice();
        modalLabores = laboresActivas;
    } else {
        const result = await pedir('listar', { empresa, busqueda: '' });
        if (!result) return;
        modalAnios = (result.anios || []).map(String).sort((a, b) => b - a);
        modalLabores = Number(result.laboresActivas ?? 0);
    }
    configurarModal(origen);
};

const abrirModalAnio = (origen) => {
    $id('formulario_anio').reset();
    $id('anio_empresa').value = filtroEmpresa();
    $id('anio_valor').value = '';
    modalAnio().show();
    cargarOpcionesModal(filtroEmpresa(), origen);
};

$id('btn_nuevo_anio').addEventListener('click', () => abrirModalAnio(null));
$id('btn_nuevo_anio_vacio').addEventListener('click', () => abrirModalAnio(null));
$id('anio_valor').addEventListener('input', validarAnioNuevo);
$id('anio_origen').addEventListener('change', actualizarAyudaOrigen);
$id('anio_empresa').addEventListener('change', () => cargarOpcionesModal($id('anio_empresa').value, null));
['anio_modo_replicar', 'anio_modo_ceros'].forEach((id) => $id(id).addEventListener('change', marcarOpcion));

$id('formulario_anio').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $id('btn_guardar_anio');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const result = await pedir('crear-anio', {
        empresa: $id('anio_empresa').value,
        anio: $id('anio_valor').value,
        origen: $id('anio_modo_replicar').checked ? $id('anio_origen').value : ''
    });

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';

    if (!result) return;

    modalAnio().hide();
    Toast.fire({ icon: 'success', title: `Año ${result.anio} creado con ${result.insertados} precios.` });
    await listar();
    abrirEditor(result.anio);
});

const eliminarAnio = async (anio) => {
    const datos = datosAnio(anio);
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: `¿Eliminar el año ${anio}?`,
        text: `Se eliminarán los ${datos.labores} precios registrados en ${anio}. Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    const result = await pedir('eliminar-anio', { empresa: filtroEmpresa(), anio });
    if (!result) return;

    Toast.fire({ icon: 'success', title: `Año ${anio} eliminado.` });
    await listar();
};

$id('cuerpo_tabla_anios').addEventListener('click', (e) => {
    const boton = e.target.closest('[data-accion]');
    if (!boton) return;
    const anio = boton.dataset.anio || boton.closest('tr')?.dataset.anio;
    if (boton.dataset.accion === 'editar') abrirEditor(anio);
    if (boton.dataset.accion === 'duplicar') abrirModalAnio(anio);
    if (boton.dataset.accion === 'eliminar') eliminarAnio(anio);
});

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstadoAnios($id('cuerpo_tabla_anios').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_anios').querySelector('strong').textContent);
});
