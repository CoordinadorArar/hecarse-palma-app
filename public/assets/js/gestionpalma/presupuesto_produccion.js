const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
});

const Alerta = Swal.mixin({ confirmButtonColor: '#1dab4d' });

const URL_PPTO = BASE_URL + 'gestion-palma/contabilidad/presupuesto/';

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
    'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const RANGO = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

const MSG_INVALIDO = 'Ingrese un valor mayor o igual a 0 con máximo 2 decimales';

const $id = (id) => document.getElementById(id);

const escapar = (texto) => String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const formatear = (numero) => new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(numero);

const canonico = (numero) => String(Number(Number(numero).toFixed(2)));

const parsear = (texto) => {
    const t = String(texto ?? '').replace(/\s/g, '');
    if (t === '') return '';
    let limpio = null;
    if (t.includes(',')) {
        const sinMiles = t.replace(/\./g, '');
        if (/^\d+(,\d{1,2})?$/.test(sinMiles)) limpio = sinMiles.replace(',', '.');
    } else if (/^\d+(\.\d{1,2})?$/.test(t)) {
        limpio = t;
    } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
        limpio = t.replace(/\./g, '');
    }
    return limpio === null ? null : Number(limpio);
};

const estado = {
    anio: 0,
    tablaExiste: TABLA_EXISTE,
    data: [],
    anterior: {},
    cargado: false
};

const empresa = () => $id('filtro_empresa').value;

const filaDe = (finca) => estado.data.find((f) => f.finca === finca);

const tieneDatos = (fila) => !!fila && RANGO.some((m) => fila.meses[m] != null);

const totalDe = (fila) => RANGO.reduce((s, m) => s + Number(fila.meses[m] ?? 0), 0);

const nombreDe = (fila) => fila.fincaNombre || fila.finca;

const delta = (actual, anterior) => {
    const pct = anterior ? ((actual - anterior) / anterior) * 100 : 0;
    const tipo = Math.abs(pct) < 0.005 ? 'igual' : (pct > 0 ? 'sube' : 'baja');
    const icono = tipo === 'igual' ? 'bi-dash' : `bi-arrow-${tipo === 'sube' ? 'up' : 'down'}-short`;
    return `<span class="gp-delta ${tipo}"><i class="bi ${icono}"></i>${formatear(Math.abs(pct))} %</span>`;
};

const avisar = (status, mensaje) => {
    const titulos = { 401: 'Sesión expirada', 403: 'Acceso denegado' };
    Alerta.fire({ icon: titulos[status] ? 'warning' : 'error', title: titulos[status] || 'No se pudo completar la operación', text: mensaje || '' });
};

const enviar = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, typeof valor === 'object' ? JSON.stringify(valor) : valor));
    try {
        const response = await fetch(URL_PPTO + accion, { method: 'POST', body });
        const result = await response.json();
        return { ok: response.ok && result.success === true, status: response.status, result };
    } catch (error) {
        return { ok: false, status: 0, result: { message: 'Error de conexión.' } };
    }
};

const pedir = async (accion, datos) => {
    const envio = await enviar(accion, datos);
    if (!envio.ok) avisar(envio.status, envio.result.message);
    return envio.ok;
};

const confirmar = async (opciones) => (await Alerta.fire({ showCancelButton: true, cancelButtonText: 'Cancelar', ...opciones })).isConfirmed;

const confirmarBorrado = (title, text, confirmButtonText) => confirmar({ icon: 'warning', title, text, confirmButtonText, confirmButtonColor: '#dc3545' });

const prepararInput = (input) => {
    input.addEventListener('keydown', (e) => {
        if (e.key === '-') e.preventDefault();
    });
    input.addEventListener('focus', () => {
        const v = parsear(input.value);
        if (typeof v === 'number') input.value = canonico(v).replace('.', ',');
        input.select();
    });
    input.addEventListener('blur', () => {
        const v = parsear(input.value);
        if (typeof v === 'number') input.value = formatear(v);
    });
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^\d.,]/g, '');
        input.classList.toggle('is-invalid', parsear(input.value) === null);
    });
};

const flash = (el) => {
    if (!el) return;
    el.classList.remove('gp-ppto-flash');
    void el.offsetWidth;
    el.classList.add('gp-ppto-flash');
    setTimeout(() => el.classList.remove('gp-ppto-flash'), 1300);
};

const escCss = (texto) => (window.CSS && CSS.escape ? CSS.escape(texto) : texto);

const celdaEn = (finca, mes) => document.querySelector(`${window.innerWidth >= 768 ? '#cuerpo_ppto' : '#lista_ppto'} .gp-ppto-celda[data-finca="${escCss(finca)}"]${mes ? `[data-mes="${mes}"]` : ''}`);


const claseValor = (v) => (v == null ? 'vacio' : (Number(v) === 0 ? 'cero' : ''));

const tituloCelda = (v, mes) => (!estado.tablaExiste ? 'Edición deshabilitada: tabla no existe' : v == null ? 'Sin presupuesto. Clic para registrar' : `${MESES[mes - 1]} ${estado.anio}: ${formatear(v)} kg`);

const celdaTabla = (fila, mes) => {
    const v = fila.meses[mes];
    return `<td class="gp-ppto-celda ${claseValor(v)}" data-finca="${escapar(fila.finca)}" data-mes="${mes}" title="${tituloCelda(v, mes)}"><i class="bi bi-pencil gp-ppto-lapiz"></i>${v == null ? '–' : formatear(v)}</td>`;
};

const celdaMini = (fila, mes) => {
    const v = fila.meses[mes];
    return `<div class="col-4"><div class="gp-ppto-celda gp-ppto-mini ${claseValor(v)}" data-finca="${escapar(fila.finca)}" data-mes="${mes}" title="${tituloCelda(v, mes)}"><span class="gp-etiqueta-mini">${MESES[mes - 1].slice(0, 3)}</span><span class="d-block">${v == null ? '–' : formatear(v)}</span></div></div>`;
};

const atributosAccion = (fila, accion) => {
    if (!estado.tablaExiste) return 'disabled title="Edición deshabilitada: tabla no existe"';
    if (accion === 'eliminar' && !tieneDatos(fila)) return 'disabled title="Eliminar año"';
    return `title="${accion === 'editar' ? 'Editar año' : 'Eliminar año'}"`;
};

const botones = (fila, ancho) => `
    <button type="button" class="btn btn-sm btn-outline-primary ${ancho}" data-accion="editar" data-finca="${escapar(fila.finca)}" ${atributosAccion(fila, 'editar')}><i class="bi bi-pencil-square"></i>${ancho ? '<span class="ms-1">Editar año</span>' : ''}</button>
    <button type="button" class="btn btn-sm btn-outline-danger ${ancho}" data-accion="eliminar" data-finca="${escapar(fila.finca)}" ${atributosAccion(fila, 'eliminar')}><i class="bi bi-trash"></i>${ancho ? '<span class="ms-1">Eliminar</span>' : ''}</button>`;

const encabezadoFinca = (fila) => {
    const sin = !tieneDatos(fila);
    return `<div class="gp-truncar gp-ppto-nombre fw-semibold ${sin ? 'text-muted' : ''}" title="${escapar(nombreDe(fila))}">${escapar(nombreDe(fila))}</div>
        <span class="gp-subtexto font-monospace">${escapar(fila.finca)}${sin ? ' <span class="gp-chip-fila ambar ms-1">Sin presupuesto</span>' : ''}</span>`;
};

const totalesMes = () => RANGO.map((m) => {
    const valores = estado.data.map((f) => f.meses[m]).filter((v) => v != null);
    return valores.length ? valores.reduce((s, v) => s + Number(v), 0) : null;
});

const pintarKpis = () => {
    $id('kpi_total_titulo').textContent = `Total presupuestado ${estado.anio}`;
    if (!estado.tablaExiste) {
        ['kpi_total', 'kpi_fincas', 'kpi_anterior'].forEach((id) => { $id(id).innerHTML = '–'; });
        return;
    }
    const total = estado.data.reduce((s, f) => s + totalDe(f), 0);
    const anteriores = Object.values(estado.anterior);
    const totalAnt = anteriores.reduce((s, f) => s + totalDe(f), 0);
    $id('kpi_total').innerHTML = `${formatear(total)} <small>kg</small>`;
    $id('kpi_fincas').innerHTML = `${estado.data.filter(tieneDatos).length} <small>/ ${estado.data.length}</small>`;
    $id('kpi_anterior').innerHTML = anteriores.some(tieneDatos)
        ? `${formatear(totalAnt)} <small>kg</small>${delta(total, totalAnt)}`
        : '<span class="text-muted fs-6">Sin datos</span>';
};

const pintar = () => {
    const anio = estado.anio;
    const hoy = new Date();
    const vacio = estado.data.length === 0;
    const totales = totalesMes();
    const totalGeneral = totales.reduce((s, v) => s + Number(v ?? 0), 0);

    $id('contador_ppto').innerHTML = `<i class="bi bi-calendar3 me-1"></i><strong>${anio}</strong> · ${estado.data.length} ${estado.data.length === 1 ? 'finca' : 'fincas'}`;
    $id('ppto_zona').classList.toggle('gp-ppto-bloqueada', !estado.tablaExiste);
    $id('alerta_tabla').classList.toggle('d-none', estado.tablaExiste);
    $id('ppto_contenido').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
    $id('gp_estado_error').classList.add('d-none');

    const sinDatos = !vacio && !estado.data.some(tieneDatos);
    $id('nota_sin_datos').classList.toggle('d-none', !sinDatos);
    $id('nota_sin_datos').innerHTML = `<i class="bi bi-info-circle me-1"></i>Aún no hay presupuesto para ${anio}. Haga clic en una celda o use el lápiz de una finca para registrarlo.`;

    document.querySelectorAll('.gp-ppto-matriz th[data-mes]').forEach((th) => {
        th.classList.toggle('gp-ppto-mes-actual', anio === hoy.getFullYear() && Number(th.dataset.mes) === hoy.getMonth() + 1);
    });

    $id('cuerpo_ppto').innerHTML = estado.data.map((fila) => `
        <tr data-finca="${escapar(fila.finca)}">
            <td class="gp-ppto-col-finca">${encabezadoFinca(fila)}</td>
            ${RANGO.map((m) => celdaTabla(fila, m)).join('')}
            <td class="gp-ppto-col-total">${tieneDatos(fila) ? formatear(totalDe(fila)) : '–'}</td>
            <td class="text-center gp-col-sticky"><div class="d-inline-flex gap-1">${botones(fila, '')}</div></td>
        </tr>`).join('');

    $id('pie_ppto').innerHTML = vacio ? '' : `<tr>
        <td class="gp-ppto-col-finca">Total mes</td>
        ${totales.map((v) => `<td>${v == null ? '–' : formatear(v)}</td>`).join('')}
        <td class="gp-ppto-col-total">${formatear(totalGeneral)}</td>
        <td class="gp-col-sticky"></td>
    </tr>`;

    $id('lista_ppto').innerHTML = estado.data.map((fila) => `
        <div class="card mb-2 gp-ppto-tarjeta" data-finca="${escapar(fila.finca)}">
            <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2 py-2">
                <div class="text-truncate">${encabezadoFinca(fila)}</div>
                <span class="gp-ppto-total-valor fw-semibold">${tieneDatos(fila) ? formatear(totalDe(fila)) : '–'}</span>
            </div>
            <div class="card-body p-2"><div class="row g-1">${RANGO.map((m) => celdaMini(fila, m)).join('')}</div></div>
            <div class="card-footer bg-white d-flex gap-2 p-2">${botones(fila, 'w-50')}</div>
        </div>`).join('') + (vacio ? '' : `
        <div class="card mb-0 gp-ppto-pie-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold">Total mes</span>
                <span class="gp-ppto-total-valor fw-semibold">${formatear(totalGeneral)}</span>
            </div>
            <div class="card-body p-2"><div class="row g-1">${totales.map((v, i) => `<div class="col-4"><div class="gp-ppto-mini"><span class="gp-etiqueta-mini">${MESES[i].slice(0, 3)}</span><span class="d-block">${v == null ? '–' : formatear(v)}</span></div></div>`).join('')}</div></div>
        </div>`);

    pintarKpis();
};


const cargando = (activo) => {
    $id('ppto_cargando').classList.toggle('d-none', !activo || !estado.cargado);
    ['filtro_empresa', 'filtro_anio', 'btn_consultar'].forEach((id) => { $id(id).disabled = activo; });
};

const cargar = async () => {
    cerrarEditor();
    cargando(true);
    const anio = Number($id('filtro_anio').value);
    const envio = await enviar('listar', { empresa: empresa(), anio });
    cargando(false);

    if (!envio.ok) {
        if (envio.status === 401 || envio.status === 403) avisar(envio.status, envio.result.message);
        $id('ppto_contenido').classList.add('d-none');
        $id('gp_estado_vacio').classList.add('d-none');
        $id('nota_sin_datos').classList.add('d-none');
        $id('gp_estado_error').classList.remove('d-none');
        $id('error_mensaje').textContent = envio.result.message || '';
        return;
    }

    estado.anio = anio;
    estado.tablaExiste = envio.result.tablaExiste !== false;
    estado.data = envio.result.data || [];
    estado.anterior = Object.fromEntries((envio.result.anterior || []).map((f) => [f.finca, f]));
    estado.cargado = true;
    pintar();
};

$id('btn_consultar').addEventListener('click', cargar);
$id('filtro_empresa').addEventListener('change', cargar);
$id('filtro_anio').addEventListener('change', cargar);
$id('btn_reintentar').addEventListener('click', cargar);


const guardarMeses = async (fila, meses) => {
    if (!(await pedir('guardar', { empresa: empresa(), anio: estado.anio, finca: fila.finca, meses }))) return false;
    Object.entries(meses).forEach(([m, v]) => { fila.meses[m] = v === '' ? null : Number(v); });
    pintar();
    return true;
};

const eliminarAnio = async (fila) => {
    const ok = await confirmarBorrado(
        '¿Eliminar presupuesto del año?',
        `Se eliminarán los 12 meses de ${estado.anio} para «${nombreDe(fila)}» (${formatear(totalDe(fila))} kg). Esta acción no se puede deshacer.`,
        'Sí, eliminar'
    );
    if (!ok || !(await pedir('eliminar', { empresa: empresa(), anio: estado.anio, finca: fila.finca }))) return false;
    RANGO.forEach((m) => { fila.meses[m] = null; });
    pintar();
    Toast.fire({ icon: 'success', title: 'Presupuesto del año eliminado.' });
    return true;
};

const confirmarQuitarMes = (fila, mes) => confirmarBorrado(
    '¿Quitar presupuesto del mes?',
    `Se eliminará el presupuesto de «${MESES[mes - 1]} ${estado.anio}» para «${nombreDe(fila)}». Esta acción no se puede deshacer.`,
    'Sí, quitar'
);


let editor = null;

const modalMes = () => bootstrap.Modal.getOrCreateInstance($id('modalPresupuestoMes'));

const cerrarEditor = () => {
    if (!editor) return;
    if (editor.modal) modalMes().hide();
    else editor.contenedor.remove();
    editor = null;
};

const editorHtml = (fila, mes) => {
    const v = fila.meses[mes];
    const ant = estado.anterior[fila.finca]?.meses[mes];
    return `<div class="gp-subseccion">${escapar(nombreDe(fila))} · ${MESES[mes - 1]} ${estado.anio}</div>
        <div class="input-group input-group-sm has-validation">
            <input type="text" class="form-control gp-input-precio" inputmode="decimal" autocomplete="off" placeholder="Sin presupuesto" value="${v == null ? '' : formatear(v)}">
            <span class="input-group-text bg-light">kg</span>
            <div class="invalid-feedback">${MSG_INVALIDO}</div>
        </div>
        ${ant != null ? `<div class="form-text">Año anterior: ${formatear(ant)} kg</div>` : ''}
        <div class="d-flex gap-2 justify-content-end mt-2">
            ${v != null ? '<button type="button" class="btn btn-sm btn-outline-danger" data-editor="quitar" title="Quitar mes"><i class="bi bi-trash"></i></button>' : ''}
            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor="cancelar">Cancelar</button>
            <button type="button" class="btn btn-sm btn-primary" data-editor="guardar"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        </div>`;
};

const posicionar = (pop, celda) => {
    const r = celda.getBoundingClientRect();
    const left = Math.min(Math.max(r.left + r.width / 2 - 120, 8), window.innerWidth - 248);
    const arriba = r.bottom + pop.offsetHeight + 8 > window.innerHeight;
    pop.style.left = `${left + window.scrollX}px`;
    pop.style.top = `${(arriba ? r.top - pop.offsetHeight - 4 : r.bottom + 4) + window.scrollY}px`;
};

const ocupado = (activo) => {
    const btn = editor.contenedor.querySelector('[data-editor="guardar"]');
    editor.input.readOnly = activo;
    editor.contenedor.querySelectorAll('button').forEach((b) => { b.disabled = activo; });
    btn.innerHTML = activo ? '<span class="spinner-border spinner-border-sm me-1"></span>Guardar' : '<i class="bi bi-check-lg me-1"></i>Guardar';
};

const guardarEditor = async (siguiente) => {
    const { fila, mes, input } = editor;
    const v = parsear(input.value);
    if (v === null) {
        input.classList.add('is-invalid');
        return;
    }
    const nuevo = v === '' ? '' : canonico(v);
    const original = fila.meses[mes] == null ? '' : canonico(fila.meses[mes]);
    const abrirSiguiente = () => {
        cerrarEditor();
        if (siguiente && mes < 12) abrirEditor(fila.finca, mes + 1);
    };

    if (nuevo === original) {
        abrirSiguiente();
        return;
    }
    if (nuevo === '' && !(await confirmarQuitarMes(fila, mes))) return;

    const actual = editor;
    ocupado(true);
    const ok = await guardarMeses(fila, { [mes]: nuevo });
    if (editor !== actual) return;
    if (!ok) {
        ocupado(false);
        return;
    }
    Toast.fire({ icon: 'success', title: 'Presupuesto guardado.' });
    abrirSiguiente();
    flash(celdaEn(fila.finca, mes));
};

const quitarEditor = async () => {
    const { fila, mes } = editor;
    if (!(await confirmarQuitarMes(fila, mes))) return;
    const actual = editor;
    ocupado(true);
    const ok = await pedir('eliminar', { empresa: empresa(), anio: estado.anio, finca: fila.finca, mes });
    if (editor !== actual) return;
    if (!ok) {
        ocupado(false);
        return;
    }
    fila.meses[mes] = null;
    pintar();
    cerrarEditor();
    Toast.fire({ icon: 'success', title: 'Presupuesto del mes eliminado.' });
};

const abrirEditor = (finca, mes) => {
    const fila = filaDe(finca);
    const celda = celdaEn(finca, mes);
    if (!fila || !celda) return;
    cerrarEditor();

    const movil = window.innerWidth < 576;
    const contenedor = movil ? $id('editor_mes_modal') : document.createElement('div');
    contenedor.innerHTML = editorHtml(fila, mes);
    const input = contenedor.querySelector('input');
    editor = { fila, mes, input, contenedor, modal: movil };

    prepararInput(input);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            guardarEditor(false);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cerrarEditor();
        } else if (e.key === 'Tab' && !e.shiftKey) {
            e.preventDefault();
            guardarEditor(true);
        }
    });
    contenedor.querySelector('[data-editor="guardar"]').addEventListener('click', () => guardarEditor(false));
    contenedor.querySelector('[data-editor="cancelar"]').addEventListener('click', cerrarEditor);
    contenedor.querySelector('[data-editor="quitar"]')?.addEventListener('click', quitarEditor);

    if (movil) {
        modalMes().show();
        return;
    }
    contenedor.className = 'gp-ppto-popover gp-revelar';
    document.body.appendChild(contenedor);
    posicionar(contenedor, celda);
    input.focus();
};

$id('modalPresupuestoMes').addEventListener('shown.bs.modal', () => editor?.input.focus());
$id('modalPresupuestoMes').addEventListener('hidden.bs.modal', () => {
    if (editor?.modal) editor = null;
});

document.addEventListener('mousedown', (e) => {
    if (editor && !editor.modal && !e.target.closest('.gp-ppto-popover, .gp-ppto-celda, .swal2-container')) cerrarEditor();
});
window.addEventListener('resize', () => {
    if (editor && !editor.modal) cerrarEditor();
});
document.querySelector('.gp-ppto-matriz').closest('.table-responsive').addEventListener('scroll', () => {
    if (editor && !editor.modal) cerrarEditor();
});


let anual = null;
let cerrarForzado = false;

const modalAnual = () => bootstrap.Modal.getOrCreateInstance($id('modalPresupuestoAnual'));

const inputMes = (m) => $id('anual_mes_' + m);

const actualizarAnual = () => {
    let total = 0;
    let n = 0;
    let invalido = false;
    anual.cambios = false;
    RANGO.forEach((m) => {
        const input = inputMes(m);
        const v = parsear(input.value);
        if (v === null) invalido = true;
        if (typeof v === 'number') {
            total += v;
            n += 1;
        }
        const editado = v !== null && (v === '' ? '' : canonico(v)) !== anual.originales[m];
        input.classList.toggle('gp-editado', editado);
        if (editado || v === null) anual.cambios = true;
    });
    const ant = estado.anterior[anual.fila.finca];
    $id('anual_total').textContent = formatear(total);
    $id('anual_conteo').textContent = `${n} de 12 meses con valor`;
    $id('anual_delta').innerHTML = tieneDatos(ant) ? delta(total, totalDe(ant)) : '';
    $id('btn_guardar_anual').disabled = invalido || !anual.cambios;
};

const ponerValoresAnual = (valores) => {
    RANGO.forEach((m) => {
        const v = valores[m];
        inputMes(m).value = v == null || v === '' ? '' : formatear(v);
        inputMes(m).classList.remove('is-invalid');
    });
    actualizarAnual();
};

const hayDigitados = () => RANGO.some((m) => inputMes(m).value.trim() !== '');

const reemplazarSiHace = async (valores) => {
    if (hayDigitados() && !(await confirmar({ icon: 'question', title: '¿Reemplazar los valores actuales?', confirmButtonText: 'Sí, reemplazar' }))) return;
    ponerValoresAnual(valores);
};

const abrirAnual = (finca) => {
    const fila = filaDe(finca);
    if (!fila || !estado.tablaExiste) return;
    cerrarEditor();
    const ant = estado.anterior[finca];
    const conAnt = tieneDatos(ant);
    anual = { fila, cambios: false, originales: Object.fromEntries(RANGO.map((m) => [m, fila.meses[m] == null ? '' : canonico(fila.meses[m])])) };

    $id('titulo_modal_anual').innerHTML = `<i class="bi bi-calendar3-range me-1"></i>Presupuesto ${estado.anio}`;
    $id('subtitulo_modal_anual').textContent = `${fila.finca} · ${nombreDe(fila)}`;
    $id('anual_distribuir').value = '';
    $id('anual_distribuir').classList.remove('is-invalid');
    $id('btn_copiar_anterior').disabled = !conAnt;
    $id('anual_total_anterior').textContent = conAnt ? `Total ${estado.anio - 1}: ${formatear(totalDe(ant))} kg` : 'Sin datos del año anterior';
    $id('btn_eliminar_anual').classList.toggle('d-none', !tieneDatos(fila));
    RANGO.forEach((m) => {
        const v = ant?.meses[m];
        $id('anual_ant_' + m).textContent = v != null ? `Ant.: ${formatear(v)}` : '';
    });
    ponerValoresAnual(fila.meses);
    modalAnual().show();
};

const cerrarAnual = () => {
    cerrarForzado = true;
    modalAnual().hide();
};

RANGO.forEach((m) => {
    prepararInput(inputMes(m));
    inputMes(m).addEventListener('input', actualizarAnual);
});
prepararInput($id('anual_distribuir'));

$id('btn_distribuir').addEventListener('click', () => {
    const total = parsear($id('anual_distribuir').value);
    if (typeof total !== 'number') {
        $id('anual_distribuir').classList.add('is-invalid');
        return;
    }
    const centavos = Math.round(total * 100);
    const base = Math.floor(centavos / 12);
    reemplazarSiHace(Object.fromEntries(RANGO.map((m) => [m, (m === 12 ? centavos - base * 11 : base) / 100])));
});

$id('btn_copiar_anterior').addEventListener('click', () => {
    const ant = estado.anterior[anual.fila.finca];
    if (tieneDatos(ant)) reemplazarSiHace(ant.meses);
});

$id('btn_limpiar_anual').addEventListener('click', () => ponerValoresAnual({}));

$id('btn_guardar_anual').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const meses = Object.fromEntries(RANGO.map((m) => {
        const v = parsear(inputMes(m).value);
        return [m, v === '' || v === null ? '' : canonico(v)];
    }).filter(([m, v]) => v !== anual.originales[m]));
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    const ok = await guardarMeses(anual.fila, meses);
    btn.innerHTML = original;
    btn.disabled = false;
    if (!ok) return;
    Toast.fire({ icon: 'success', title: 'Presupuesto guardado.' });
    const finca = anual.fila.finca;
    cerrarAnual();
    flash(document.querySelector(window.innerWidth >= 768
        ? `#cuerpo_ppto tr[data-finca="${escCss(finca)}"]`
        : `#lista_ppto .gp-ppto-tarjeta[data-finca="${escCss(finca)}"]`));
});

$id('btn_eliminar_anual').addEventListener('click', async () => {
    if (await eliminarAnio(anual.fila)) cerrarAnual();
});

$id('modalPresupuestoAnual').addEventListener('hide.bs.modal', async (e) => {
    if (cerrarForzado || !anual?.cambios) {
        cerrarForzado = false;
        return;
    }
    e.preventDefault();
    if (await confirmar({ icon: 'question', title: '¿Descartar cambios?', text: 'Los valores modificados no se guardarán.', confirmButtonText: 'Sí, descartar' })) cerrarAnual();
});


$id('ppto_zona').addEventListener('click', (e) => {
    if (!estado.tablaExiste || !estado.cargado) return;
    const boton = e.target.closest('[data-accion]');
    if (boton) {
        const fila = filaDe(boton.dataset.finca);
        if (boton.dataset.accion === 'editar') abrirAnual(boton.dataset.finca);
        if (boton.dataset.accion === 'eliminar' && fila) eliminarAnio(fila);
        return;
    }
    const celda = e.target.closest('.gp-ppto-celda');
    if (celda) abrirEditor(celda.dataset.finca, Number(celda.dataset.mes));
});

document.addEventListener('DOMContentLoaded', cargar);
