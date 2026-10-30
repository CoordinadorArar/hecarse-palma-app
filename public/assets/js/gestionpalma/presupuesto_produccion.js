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

const redondear = (numero) => Number(Number(numero).toFixed(2));

const formatear = (numero) => {
    const v = redondear(numero);
    const d = Number.isInteger(v) ? 0 : 2;
    return new Intl.NumberFormat('es-CO', { minimumFractionDigits: d, maximumFractionDigits: d }).format(v);
};

const canonico = (numero) => String(redondear(numero));

const parsear = (texto, dec = 2) => {
    const t = String(texto ?? '').replace(/\s/g, '');
    if (t === '') return '';
    let limpio = null;
    if (t.includes(',')) {
        const sinMiles = t.replace(/\./g, '');
        if (new RegExp(`^(\\d+|\\d{1,3}(\\.\\d{3})+)(,\\d{1,${dec}})?$`).test(t)) limpio = sinMiles.replace(',', '.');
    } else if (new RegExp(`^\\d+(\\.\\d{1,${dec}})?$`).test(t)) {
        limpio = t;
    } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
        limpio = t.replace(/\./g, '');
    }
    return limpio === null ? null : Number(limpio);
};

const estado = {
    anio: Number($id('filtro_anio').value),
    tablaExiste: TABLA_EXISTE,
    fincas: [],
    finca: null,
    anterior: null,
    cargado: false
};

const empresa = () => $id('filtro_empresa').value;

const nombreFinca = (f) => f.fincaNombre || f.finca;

const suma = (meses) => redondear(RANGO.reduce((s, m) => s + Number(meses[m] ?? 0), 0));

const activos = () => (estado.finca ? estado.finca.lotes.filter((l) => l.activo) : []);

const loteDe = (codigo) => estado.finca?.lotes.find((l) => l.lote === codigo);

const valorDe = (codigo, mes) => loteDe(codigo)?.meses[mes] ?? null;

const bloqueado = (mes) => estado.finca?.historico[mes] != null;

const mesesHistorico = () => RANGO.filter(bloqueado);

const editable = () => !!estado.finca && estado.tablaExiste;

const etiquetaLote = (l) => (l.loteNombre ? `${l.lote} — ${l.loteNombre}` : l.lote);

const delta = (actual, anterior) => {
    const pct = anterior ? ((actual - anterior) / anterior) * 100 : 0;
    const tipo = Math.abs(pct) < 0.005 ? 'igual' : (pct > 0 ? 'sube' : 'baja');
    const icono = tipo === 'igual' ? 'bi-dash' : `bi-arrow-${tipo === 'sube' ? 'up' : 'down'}-short`;
    return `<span class="gp-delta ${tipo}"><i class="bi ${icono}"></i>${formatear(Math.abs(pct))} %</span>`;
};

const avisar = (status, mensaje) => {
    const titulos = { 401: 'Sesión expirada', 403: 'Acceso denegado' };
    Alerta.fire({ icon: [401, 403, 409, 422].includes(status) ? 'warning' : 'error', title: titulos[status] || 'No se pudo completar la operación', text: mensaje || '' });
};

const enviar = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));
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
    return envio.ok ? envio.result : null;
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
        if (parsear(input.value) === null) $id(input.id + '_error').textContent = MSG_INVALIDO;
    });
};

const flash = (el) => {
    if (!el) return;
    el.classList.remove('gp-ppto-flash');
    void el.offsetWidth;
    el.classList.add('gp-ppto-flash');
    setTimeout(() => el.classList.remove('gp-ppto-flash'), 1300);
};

const anunciar = (texto) => {
    $id('ppto_anuncio').textContent = '';
    setTimeout(() => { $id('ppto_anuncio').textContent = texto; }, 50);
};

const escCss = (texto) => (window.CSS && CSS.escape ? CSS.escape(texto) : texto);

const celdaEn = (lote, mes) => document.querySelector(`#cuerpo_ppto td[data-lote="${escCss(lote)}"][data-mes="${mes}"]`);

const recalcular = () => {
    const f = estado.finca;
    f.lotes.forEach((l) => { l.total = suma(l.meses); });
    RANGO.forEach((m) => {
        const valores = [f.historico[m], ...f.lotes.map((l) => l.meses[m])].filter((v) => v != null);
        f.meses[m] = valores.length ? redondear(valores.reduce((s, v) => s + Number(v), 0)) : null;
    });
    f.totalHistorico = suma(f.historico);
    f.totalLotes = redondear(f.lotes.reduce((s, l) => s + l.total, 0));
    f.total = suma(f.meses);
};

const btnEliminar = (tipo, lote, mes, etiqueta) => `<button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2 gp-ppto-borrar" data-eliminar="${tipo}" data-lote="${escapar(lote)}" data-mes="${mes}" aria-label="${escapar(etiqueta)}" title="${escapar(etiqueta)}"><i class="bi bi-trash"></i></button>`;

const celdaSolo = (v, boton) => `<td class="gp-ppto-celda solo ${v == null ? 'vacio' : ''}">${v == null ? '–' : `<span class="d-inline-flex align-items-center">${formatear(v)}${boton}</span>`}</td>`;

const celdaLote = (l, m) => {
    const v = l.meses[m];
    if (!l.activo || !estado.tablaExiste) {
        const boton = l.activo || !estado.tablaExiste ? '' : btnEliminar('lote', l.lote, m, `Eliminar presupuesto lote ${l.lote}, ${MESES[m - 1]} ${estado.anio}`);
        return celdaSolo(v, boton).replace('<td class="', `<td data-lote="${escapar(l.lote)}" data-mes="${m}" class="`);
    }
    const bloq = bloqueado(m);
    const aria = bloq
        ? `Bloqueado: existe meta de finca en ${MESES[m - 1]}`
        : (v == null ? `Registrar presupuesto lote ${l.lote}, ${MESES[m - 1]}` : `Editar presupuesto lote ${l.lote}, ${MESES[m - 1]}: ${formatear(v)} kg`);
    return `<td class="gp-ppto-celda ${v == null ? 'vacio' : ''} ${bloq ? 'bloqueada' : ''}" data-lote="${escapar(l.lote)}" data-mes="${m}">
        <button type="button" class="gp-ppto-celda-btn" data-lote="${escapar(l.lote)}" data-mes="${m}" aria-label="${escapar(aria)}" ${bloq ? `disabled aria-disabled="true" title="${escapar(aria)}"` : ''}>${bloq ? '' : '<i class="bi bi-pencil gp-ppto-lapiz" aria-hidden="true"></i>'}${v == null ? '–' : formatear(v)}</button></td>`;
};

const filaLote = (l) => `
    <tr class="${l.activo ? '' : 'gp-ppto-inactivo'}">
        <th scope="row" class="gp-ppto-col-lote">
            <div class="font-monospace fw-semibold">${escapar(l.lote)}${l.activo ? '' : '<span class="gp-chip-fila gris ms-1">Inactivo</span>'}</div>
            <span class="gp-subtexto gp-truncar gp-ppto-nombre" title="${escapar(l.loteNombre)}">${escapar(l.loteNombre) || '–'}</span>
        </th>
        ${RANGO.map((m) => celdaLote(l, m)).join('')}
        <td class="gp-ppto-col-total">${l.total > 0 ? formatear(l.total) : '–'}</td>
    </tr>`;

const filaHistorico = (f) => `
    <tr class="gp-ppto-historico">
        <th scope="row" class="gp-ppto-col-lote">
            <div class="fw-semibold"><i class="bi bi-clock-history me-1"></i>Meta de finca</div>
            <span class="gp-chip-fila ambar">Histórico</span>
        </th>
        ${RANGO.map((m) => celdaSolo(f.historico[m], estado.tablaExiste ? btnEliminar('finca', '', m, `Eliminar meta de finca, ${MESES[m - 1]} ${estado.anio}`) : '')).join('')}
        <td class="gp-ppto-col-total">${formatear(f.totalHistorico)}</td>
    </tr>`;

const pintarKpis = () => {
    const f = estado.finca;
    $id('kpi_total_titulo').textContent = `Total presupuestado ${estado.anio}`;
    if (!f || !estado.tablaExiste) {
        ['kpi_total', 'kpi_lotes', 'kpi_anterior'].forEach((id) => { $id(id).innerHTML = '–'; });
        return;
    }
    const lista = activos();
    const ant = estado.anterior;
    const meta = metaFinca();
    const objetivo = meta && meta.finca === f.finca && metas.anio === estado.anio ? Number(meta.kilosAnual) : 0;
    const pct = objetivo > 0 ? (f.total / objetivo) * 100 : null;
    $id('kpi_total').innerHTML = `${formatear(f.total)} <small>kg</small>${pct === null ? '' : `<small class="d-block gp-ppto-kpi-meta ${pct >= 99.5 && pct <= 100.5 ? 'ok' : 'fuera'}">de meta ${nf(objetivo, 0)} kg · ${nf(pct, 1)} %</small>`}`;
    $id('kpi_lotes').innerHTML = `${lista.filter((l) => l.total > 0).length} <small>/ ${lista.length}</small>`;
    $id('kpi_anterior').innerHTML = ant && ant.total > 0 ? `${formatear(ant.total)} <small>kg</small>${delta(f.total, ant.total)}` : '<span class="text-muted fs-6">Sin datos</span>';
};

const mostrarVacio = (icono, titulo, texto) => {
    $id('vacio_icono').className = `bi ${icono}`;
    $id('vacio_titulo').textContent = titulo;
    $id('vacio_texto').textContent = texto;
};

const pintar = () => {
    const f = estado.finca;
    const anio = estado.anio;
    const hoy = new Date();
    const historicos = f ? mesesHistorico() : [];
    const conMatriz = !!f && (f.lotes.length > 0 || historicos.length > 0);

    $id('alerta_tabla').classList.toggle('d-none', estado.tablaExiste);
    $id('gp_estado_error').classList.add('d-none');
    $id('ppto_contenido').classList.toggle('d-none', !conMatriz);
    $id('gp_estado_vacio').classList.toggle('d-none', conMatriz);
    if (!f) mostrarVacio('bi-geo-alt', 'Seleccione una finca', 'Elija empresa, año y finca para ver y registrar el presupuesto por lote.');
    else mostrarVacio('bi-grid-3x3', 'La finca no tiene lotes registrados', 'Registre lotes en Gestión Palma › Lotes para poder presupuestar.');

    $id('contador_ppto').innerHTML = `<i class="bi bi-calendar3 me-1"></i><strong>${anio}</strong>${f ? ` · ${f.lotes.length} ${f.lotes.length === 1 ? 'lote' : 'lotes'}` : ''}`;
    $id('sub_historico').classList.toggle('d-none', !historicos.length);
    $id('leyenda_bloqueada').classList.toggle('d-none', !historicos.length);
    $id('nota_historico').classList.toggle('d-none', !conMatriz || !historicos.length);
    $id('nota_historico').innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i><strong>Esta finca tiene metas registradas a nivel finca (histórico)</strong> en: ${historicos.map((m) => MESES[m - 1]).join(', ')}. Mientras existan, no se puede cargar presupuesto por lote en esos meses. Elimínelas para habilitarlos.`;
    $id('nota_sin_datos').classList.toggle('d-none', !conMatriz || f.total > 0);
    $id('nota_sin_datos').innerHTML = `<i class="bi bi-info-circle me-1"></i>Aún no hay presupuesto para ${anio}. Use el formulario o pulse una celda para registrarlo.`;

    document.querySelectorAll('.gp-ppto-matriz th[data-mes]').forEach((th) => {
        const m = Number(th.dataset.mes);
        th.classList.toggle('gp-ppto-mes-actual', anio === hoy.getFullYear() && m === hoy.getMonth() + 1);
        th.querySelector('.bi-lock-fill').classList.toggle('d-none', !historicos.includes(m));
    });

    if (conMatriz) {
        $id('caption_ppto').textContent = `Presupuesto de producción por lote, ${nombreFinca(f)}, ${anio}`;
        $id('cuerpo_ppto').innerHTML = (historicos.length ? filaHistorico(f) : '') + f.lotes.map(filaLote).join('');
        $id('pie_ppto').innerHTML = `<tr>
            <th scope="row" class="gp-ppto-col-lote">Total mes</th>
            ${RANGO.map((m) => `<td>${f.meses[m] == null ? '–' : formatear(f.meses[m])}</td>`).join('')}
            <td class="gp-ppto-col-total">${formatear(f.total)}</td>
        </tr>`;
    } else {
        $id('cuerpo_ppto').innerHTML = '';
        $id('pie_ppto').innerHTML = '';
    }

    pintarKpis();
    pintarForm();
};

const form = {
    card: $id('ppto_form'),
    mes: $id('form_mes'),
    lote: $id('form_lote'),
    kilos: $id('form_kilos'),
    guardar: $id('form_guardar'),
    limpiar: $id('form_limpiar'),
    eliminar: $id('form_eliminar')
};

const $lote = $(form.lote);

let edicion = null;
let guardando = false;

$lote.select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Buscar lote…', language: { noResults: () => 'Sin resultados' } });

const mesDefecto = () => {
    const base = estado.anio === new Date().getFullYear() ? new Date().getMonth() + 1 : 1;
    return bloqueado(base) ? (RANGO.find((m) => !bloqueado(m)) ?? base) : base;
};

const marcar = (campo, mensaje) => {
    form[campo].classList.toggle('is-invalid', !!mensaje);
    $id(`form_${campo}_error`).textContent = mensaje || '';
    if (campo === 'lote') $lote.next('.select2-container').find('.select2-selection').toggleClass('is-invalid', !!mensaje);
};

const limpiarMarcas = () => ['mes', 'lote', 'kilos'].forEach((c) => marcar(c, ''));

const refrescarForm = (precargar) => {
    const mes = Number(form.mes.value);
    const lote = form.lote.value;
    const bloq = !!estado.finca && bloqueado(mes);
    marcar('mes', bloq ? `${MESES[mes - 1]} tiene meta de finca (histórico). Elimínela antes de cargar por lote.` : '');
    const actual = lote ? valorDe(lote, mes) : null;
    if (precargar && !edicion && actual != null) {
        form.kilos.value = formatear(actual);
        marcar('kilos', '');
    }
    const v = parsear(form.kilos.value);
    const ayuda = v === 0 && actual != null ? 'Guardar 0 elimina el presupuesto del lote en ese mes.' : (!edicion && actual != null ? `Ya registrado: ${formatear(actual)} kg. Se reemplazará.` : '');
    $id('form_ayuda').textContent = ayuda;
    $id('form_ayuda_col').classList.toggle('d-none', !ayuda);
    form.guardar.disabled = !editable() || bloq || guardando;
};

const modoForm = () => {
    const editando = !!edicion;
    form.card.classList.toggle('gp-ppto-form-editando', editando);
    $id('form_titulo').innerHTML = editando
        ? `<i class="bi bi-pencil-square"></i>Editando: ${escapar(edicion.lote)} · ${MESES[edicion.mes - 1]}`
        : '<i class="bi bi-plus-circle"></i>Registrar presupuesto';
    if (!guardando) form.guardar.innerHTML = `<i class="bi bi-floppy me-1"></i>${editando ? 'Actualizar' : 'Guardar'}`;
    form.limpiar.textContent = editando ? 'Cancelar edición' : 'Limpiar';
    form.eliminar.classList.toggle('d-none', !editando);
    refrescarForm(false);
};

const pintarForm = () => {
    const f = estado.finca;
    const habil = editable();
    const mesPrevio = form.mes.value;
    const lotePrevio = form.lote.value;
    $id('form_contexto').textContent = !f ? 'Seleccione una finca para registrar' : (estado.tablaExiste ? `Finca ${nombreFinca(f)} · ${estado.anio}` : 'Edición deshabilitada: la tabla no existe');
    RANGO.forEach((m) => {
        const opcion = form.mes.options[m - 1];
        opcion.disabled = !!f && bloqueado(m);
        opcion.textContent = MESES[m - 1] + (opcion.disabled ? ' (meta de finca)' : '');
    });
    form.mes.value = mesPrevio;
    form.lote.innerHTML = '<option value=""></option>' + activos().map((l) => `<option value="${escapar(l.lote)}">${escapar(etiquetaLote(l))}</option>`).join('');
    $lote.val(loteDe(lotePrevio)?.activo ? lotePrevio : '').trigger('change.select2');
    if (edicion && !loteDe(edicion.lote)?.activo) edicion = null;
    [form.mes, form.lote, form.kilos, form.limpiar, form.eliminar].forEach((el) => { el.disabled = !habil || guardando; });
    modoForm();
};

const reiniciarForm = () => {
    edicion = null;
    form.mes.value = String(mesDefecto());
    $lote.val('').trigger('change.select2');
    form.kilos.value = '';
    limpiarMarcas();
    modoForm();
};

const ocupado = (activo) => {
    guardando = activo;
    [form.mes, form.lote, form.kilos, form.limpiar, form.eliminar].forEach((el) => { el.disabled = activo || !editable(); });
    form.guardar.innerHTML = activo ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…' : '';
    modoForm();
};

const enfocarForm = () => {
    form.card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => form.kilos.focus({ preventScroll: true }), 300);
};

const seleccionarCelda = (lote, mes) => {
    const v = valorDe(lote, mes);
    edicion = v == null ? null : { lote, mes };
    form.mes.value = String(mes);
    $lote.val(lote).trigger('change.select2');
    form.kilos.value = v == null ? '' : formatear(v);
    limpiarMarcas();
    modoForm();
    enfocarForm();
};

const validarForm = () => {
    const mes = Number(form.mes.value);
    const v = parsear(form.kilos.value);
    limpiarMarcas();
    let ok = true;
    if (!mes) { marcar('mes', 'Seleccione el mes'); ok = false; }
    else if (bloqueado(mes)) { marcar('mes', `${MESES[mes - 1]} tiene meta de finca (histórico). Elimínela antes de cargar por lote.`); ok = false; }
    if (!form.lote.value) { marcar('lote', 'Seleccione el lote'); ok = false; }
    if (v === '') { marcar('kilos', 'Ingrese los kilos'); ok = false; }
    else if (v === null) { marcar('kilos', MSG_INVALIDO); ok = false; }
    else if (v === 0 && ok && valorDe(form.lote.value, mes) == null) { marcar('kilos', 'No hay presupuesto que eliminar en ese mes.'); ok = false; }
    return ok ? { mes, lote: form.lote.value, v } : null;
};

const textoLote = (codigo) => {
    const l = loteDe(codigo);
    return l?.loteNombre ? `${codigo} — ${l.loteNombre}` : codigo;
};

const confirmarLote = (lote, mes) => confirmarBorrado(
    '¿Eliminar presupuesto del lote?',
    `Se eliminará el presupuesto de «${textoLote(lote)}» en ${MESES[mes - 1]} ${estado.anio} (${formatear(valorDe(lote, mes) ?? 0)} kg). Esta acción no se puede deshacer.`,
    'Sí, eliminar'
);

const tras = (mensaje, lote, mes) => {
    Toast.fire({ icon: 'success', title: mensaje });
    anunciar(mensaje);
    recalcular();
    pintar();
    flash(lote ? celdaEn(lote, mes) : null);
};

const guardarForm = async () => {
    if (!editable() || guardando) return;
    const datos = validarForm();
    if (!datos) return;
    const { mes, lote, v } = datos;
    if (v === 0 && !(await confirmarLote(lote, mes))) return;
    const finca = estado.finca;
    ocupado(true);
    const result = await pedir('guardar', { empresa: empresa(), anio: estado.anio, finca: finca.finca, mes, lote, kilos: canonico(v) });
    ocupado(false);
    if (!result || estado.finca !== finca) return;
    loteDe(lote).meses[mes] = v > 0 ? v : null;
    edicion = null;
    form.kilos.value = '';
    tras(result.message || 'Presupuesto guardado correctamente.', lote, mes);
};

const eliminarLote = async (lote, mes) => {
    if (!(await confirmarLote(lote, mes))) return;
    const finca = estado.finca;
    const result = await pedir('eliminar', { empresa: empresa(), anio: estado.anio, finca: finca.finca, mes, lote });
    if (!result || estado.finca !== finca) return;
    loteDe(lote).meses[mes] = null;
    if (edicion && edicion.lote === lote && edicion.mes === mes) {
        edicion = null;
        form.kilos.value = '';
    }
    tras(result.message || 'Presupuesto del lote eliminado correctamente.', lote, mes);
};

const eliminarMeta = async (mes) => {
    const finca = estado.finca;
    const ok = await confirmarBorrado(
        '¿Eliminar meta de finca?',
        `Se eliminará la meta histórica de «${nombreFinca(finca)}» en ${MESES[mes - 1]} ${estado.anio} (${formatear(finca.historico[mes])} kg). Al eliminarla podrá cargar presupuesto por lote en ese mes.`,
        'Sí, eliminar'
    );
    if (!ok) return;
    const result = await pedir('eliminar', { empresa: empresa(), anio: estado.anio, finca: finca.finca, mes, lote: '' });
    if (!result || estado.finca !== finca) return;
    finca.historico[mes] = null;
    tras(result.message || 'Meta de finca eliminada correctamente.', null, mes);
};

let peticion = 0;

const cargando = (activo) => {
    $id('ppto_zona').setAttribute('aria-busy', activo ? 'true' : 'false');
    $id('ppto_cargando').classList.toggle('d-none', !activo);
    ['filtro_empresa', 'filtro_anio', 'filtro_finca', 'btn_consultar'].forEach((id) => { $id(id).disabled = activo; });
};

const mostrarError = (envio) => {
    if (envio.status === 401 || envio.status === 403) avisar(envio.status, envio.result.message);
    ['ppto_contenido', 'gp_estado_vacio', 'nota_sin_datos', 'nota_historico'].forEach((id) => $id(id).classList.add('d-none'));
    $id('gp_estado_error').classList.remove('d-none');
    $id('error_mensaje').textContent = envio.result.message || '';
};

const consultar = async (finca) => {
    const id = ++peticion;
    const anio = Number($id('filtro_anio').value);
    cargando(true);
    const envio = await enviar('listar', { empresa: empresa(), anio, finca });
    if (id !== peticion) return null;
    cargando(false);
    if (!envio.ok) {
        mostrarError(envio);
        return null;
    }
    estado.anio = anio;
    estado.tablaExiste = envio.result.tablaExiste !== false;
    return envio.result;
};

const cargar = async () => {
    const codigo = $id('filtro_finca').value;
    previos = leerFiltros();
    cargarMetas();
    if (!codigo) {
        estado.anio = Number($id('filtro_anio').value);
        estado.finca = null;
        estado.anterior = null;
        pintar();
        reiniciarForm();
        return;
    }
    const result = await consultar(codigo);
    if (!result) return;
    estado.finca = (result.data || [])[0] || null;
    estado.anterior = (result.anterior || [])[0] || null;
    estado.cargado = true;
    pintar();
    reiniciarForm();
};

const cargarFincas = async () => {
    const result = await consultar('');
    if (!result) return;
    estado.fincas = result.data || [];
    estado.cargado = true;
    const select = $id('filtro_finca');
    const previa = select.value;
    select.innerHTML = '<option value="">Seleccione una finca</option>' + estado.fincas.map((f) => `<option value="${escapar(f.finca)}">${escapar(f.fincaNombre ? `${f.finca} — ${f.fincaNombre}` : f.finca)}</option>`).join('');
    select.value = estado.fincas.some((f) => f.finca === previa) ? previa : '';
    cargar();
};

const nf = (numero, d) => new Intl.NumberFormat('es-CO', { minimumFractionDigits: d, maximumFractionDigits: d }).format(numero);

const metas = { datos: null, empresa: '', anio: 0, finca: '', clave: '', curva: Array(13).fill(''), cache: {}, sucioEmp: false, sucioFinca: false, ocupado: false, peticion: 0, grafica: null, kilos: 0, generacion: null, guardandoGen: false };

let previos = null;

const leerFiltros = () => ({ empresa: $id('filtro_empresa').value, anio: $id('filtro_anio').value, finca: $id('filtro_finca').value });

const metaFinca = () => (metas.finca ? metas.datos?.fincas?.find((x) => x.finca === metas.finca) : undefined);

const colapso = bootstrap.Collapse.getOrCreateInstance($id('metas_cuerpo'), { toggle: false });

const modalGenerar = bootstrap.Modal.getOrCreateInstance($id('modal_generar'));

const CAMPOS_FINCA = ['meta_finca_kilos', 'meta_finca_tonha', ...RANGO.map((m) => `curva_${m}`)];

const CAMPOS_EMP = ['meta_emp_tonha', 'meta_emp_kilos'];

const numeroDe = (id) => {
    const v = parsear($id(id).value);
    return typeof v === 'number' ? v : null;
};

const enviarNum = (id) => {
    const v = numeroDe(id);
    return v === null ? '' : canonico(v);
};

const valorInput = (v) => (v == null ? '' : formatear(v));

const marcarCampo = (id, mensaje) => {
    $id(id).classList.toggle('is-invalid', !!mensaje);
    $id(`${id}_error`).textContent = mensaje || '';
};

const errorCampo = (id) => {
    const pct = id.startsWith('curva_');
    const v = parsear($id(id).value, pct ? 4 : 2);
    if (v === null) return pct ? 'Entre 0 y 100, máx. 4 decimales' : MSG_INVALIDO;
    if (pct && v > 100) return 'Entre 0 y 100';
    if (id.includes('tonha') && v > 60) return 'Valor fuera de rango (0–60 t/ha)';
    return '';
};

const revisar = (id) => {
    const mensaje = errorCampo(id);
    marcarCampo(id, mensaje);
    return !mensaje;
};

const sumaCurva = () => Number(RANGO.reduce((s, m) => s + (typeof metas.curva[m] === 'number' ? metas.curva[m] : 0), 0).toFixed(4));

const curvaVacia = () => RANGO.every((m) => metas.curva[m] === '');

const curvaOk = () => Math.abs(sumaCurva() - 100) <= 0.01;

const cargandoBoton = (boton, activo, texto) => {
    if (activo) {
        boton.dataset.html = boton.innerHTML;
        boton.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${texto}`;
    } else if (boton.dataset.html) {
        boton.innerHTML = boton.dataset.html;
    }
    boton.disabled = activo;
};

let tGrafica = null;

const grafica = () => {
    clearTimeout(tGrafica);
    tGrafica = setTimeout(() => {
        const data = RANGO.map((m) => (typeof metas.curva[m] === 'number' ? redondear(metas.curva[m]) : 0));
        $id('curva_grafica').setAttribute('aria-label', `Curva de estacionalidad: ${RANGO.map((m) => `${MESES[m - 1].slice(0, 3)} ${nf(data[m - 1], 1)} %`).join(', ')}`);
        if (metas.grafica) {
            metas.grafica.updateSeries([{ name: 'Curva', data }]);
            return;
        }
        metas.grafica = new ApexCharts($id('curva_grafica'), {
            chart: { type: 'bar', height: 90, toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false }, parentHeightOffset: 0, fontFamily: 'inherit' },
            series: [{ name: 'Curva', data }],
            colors: ['#41a867'],
            plotOptions: { bar: { columnWidth: '62%', borderRadius: 2 } },
            dataLabels: { enabled: false },
            grid: { show: false, padding: { top: -16, bottom: -4, left: 4, right: 4 } },
            xaxis: { categories: MESES.map((m) => m[0]), labels: { style: { colors: '#5c6b7a', fontSize: '10px' } }, axisTicks: { show: false }, axisBorder: { show: false }, tooltip: { enabled: false } },
            yaxis: { show: false, min: 0 },
            legend: { show: false },
            states: { hover: { filter: { type: 'darken', value: 0.85 } } },
            tooltip: {
                custom: ({ series, dataPointIndex }) => {
                    const p = series[0][dataPointIndex];
                    return `<div class="px-2 py-1 small">${MESES[dataPointIndex].slice(0, 3)} · ${nf(p, 1)} %${metas.kilos > 0 ? ` · ${nf((metas.kilos * p) / 100, 0)} kg` : ''}</div>`;
                }
            }
        });
        metas.grafica.render();
    }, 150);
};

const pintarResumen = () => {
    const d = metas.datos;
    const f = metaFinca();
    const badge = $id('metas_badge');
    const kg = (v) => (Number(v) > 0 ? `${nf(Number(v), 0)} kg` : 'sin definir');
    $id('metas_anio').textContent = metas.anio || $id('filtro_anio').value;
    $id('metas_resumen').textContent = !d?.tablasMetas ? '' : (f ? `Meta finca ${kg(f.kilosAnual)} · Curva ${nf(Number(f.suma || 0), 1)} %` : `Meta empresa ${kg(d.empresa?.kilosAnual)}`);
    if (f) {
        badge.className = `gp-chip-fila ${f.curvaCompleta ? 'verde' : 'ambar'}`;
        badge.textContent = f.curvaCompleta ? 'Curva completa' : (f.curva ? `Curva ${nf(Number(f.suma || 0), 1)} %` : 'Sin curva');
    }
    badge.classList.toggle('d-none', !f || !d?.tablasMetas);
    $id('metas_sucio').classList.toggle('d-none', !(metas.sucioEmp || metas.sucioFinca));
};

const vivo = () => {
    const d = metas.datos;
    const f = metaFinca();
    const haEmp = Number(d?.empresa?.hectareas || 0);
    const kEmp = numeroDe('meta_emp_kilos');
    const tEmp = numeroDe('meta_emp_tonha');
    const equiv = kEmp > 0 && haEmp > 0 ? kEmp / 1000 / haEmp : null;
    const sumaFincas = (d?.fincas || []).reduce((s, x) => s + Number(x.kilosAnual || 0), 0);
    $id('meta_emp_lectura').textContent = d ? `${kEmp > 0 ? `≈ ${nf(kEmp / 1000, 1)} t · ` : ''}${nf(haEmp, 2)} ha netas (activas)` : '';
    $id('meta_emp_equiv').textContent = equiv ? `Equivale a ${nf(equiv, 2)} t/ha` : '';
    $id('meta_emp_difiere').classList.toggle('d-none', !(equiv && tEmp > 0 && Math.abs(equiv - tEmp) / tEmp > 0.05));
    $id('meta_emp_fincas').textContent = kEmp > 0 && sumaFincas > 0 ? `Fincas: ${nf(sumaFincas, 0)} kg de ${nf(kEmp, 0)} kg · ${nf((sumaFincas / kEmp) * 100, 1)} %` : '';
    $id('meta_emp_guardar').disabled = metas.ocupado || !CAMPOS_EMP.every((id) => !errorCampo(id));

    const kF = numeroDe('meta_finca_kilos');
    const tF = numeroDe('meta_finca_tonha');
    const ha = Number(f?.hectareas || 0);
    metas.kilos = kF || 0;
    $id('meta_finca_lectura').textContent = f ? [kF > 0 ? `≈ ${nf(kF / 1000, 1)} t` : '', `${nf(ha, 2)} ha netas`, kF > 0 && ha > 0 ? `${nf(kF / 1000 / ha, 2)} t/ha` : ''].filter(Boolean).join(' · ') : '';
    $id('meta_calcular').classList.toggle('d-none', !(f && tF > 0 && !(kF > 0) && ha > 0));
    if (!errorCampo('meta_finca_kilos')) marcarCampo('meta_finca_kilos', f && !curvaVacia() && !(kF > 0) ? 'Ingrese la meta anual en kg' : '');

    const total = sumaCurva();
    const ok = curvaOk();
    const dif = Math.abs(100 - total);
    $id('curva_total').className = `gp-chip-fila gp-ppto-total font-monospace ${ok ? 'verde' : (total < 100 ? 'ambar' : 'rojo')}`;
    $id('curva_total').textContent = ok ? 'Total 100,0 %' : `${total < 100 ? 'Faltan' : 'Sobra'} ${nf(dif, dif < 0.05 ? 2 : 1)} %`;
    RANGO.forEach((m) => {
        const p = metas.curva[m];
        $id(`curva_kg_${m}`).textContent = kF > 0 && typeof p === 'number' ? `${nf((kF * p) / 100, 0)} kg` : '–';
    });
    grafica();

    const errorCurva = !curvaVacia() && !ok;
    const motivo = !f ? '' : (!d.tablaPresupuesto ? 'La tabla de presupuesto no existe en la base de datos'
        : (errorCurva ? 'La curva debe sumar 100 %'
            : (metas.sucioFinca ? 'Guarde la meta y la curva antes de generar'
                : (!(Number(f.kilosAnual) > 0) ? 'Ingrese la meta anual en kg'
                    : (!f.curvaCompleta ? 'La curva debe sumar 100 %' : '')))));
    $id('meta_finca_guardar').disabled = !f || metas.ocupado || errorCurva || CAMPOS_FINCA.some((id) => errorCampo(id));
    $id('meta_generar').disabled = !f || metas.ocupado || !!motivo;
    $id('meta_motivo').textContent = motivo;
    pintarResumen();
};

const menuCopiar = () => {
    const f = metaFinca();
    const otras = (metas.datos?.fincas || []).filter((x) => x.finca !== f?.finca && x.curva);
    const item = (clave, texto) => `<li><button type="button" class="dropdown-item" data-copiar="${escapar(clave)}">${escapar(texto)}</button></li>`;
    $id('curva_copiar_menu').innerHTML = (f ? item('', `${nombreFinca(f)} – ${metas.anio - 1}`) : '')
        + (otras.length ? `<li><hr class="dropdown-divider"></li><li><h6 class="dropdown-header">Otras fincas ${metas.anio}</h6></li>${otras.map((x) => item(x.finca, `${nombreFinca(x)} – ${metas.anio}`)).join('')}` : '');
};

const ponerCurva = (curva) => {
    RANGO.forEach((m) => {
        const p = curva?.[m - 1];
        metas.curva[m] = p == null ? '' : Number(p);
        $id(`curva_${m}`).value = valorInput(p);
        marcarCampo(`curva_${m}`, '');
    });
};

const pintarMetas = () => {
    const d = metas.datos;
    const f = metaFinca();
    const e = d?.empresa || {};
    const habil = !!d?.tablasMetas;
    $id('metas_sin_tablas').classList.toggle('d-none', !d || habil);
    $id('metas_error').classList.add('d-none');
    $id('metas_emp_campos').disabled = !habil;
    $id('metas_finca_campos').disabled = !habil || !f;
    $id('meta_finca_nota').classList.toggle('d-none', !!f);
    $id('meta_finca_nombre').textContent = f ? `· ${nombreFinca(f)}` : '';
    $id('meta_emp_tonha').value = e.metaTonHa == null ? '' : nf(e.metaTonHa, 2);
    $id('meta_emp_kilos').value = valorInput(e.kilosAnual);
    $id('meta_finca_kilos').value = valorInput(f?.kilosAnual);
    $id('meta_finca_tonha').value = f?.metaTonHa == null ? '' : nf(f.metaTonHa, 2);
    [...CAMPOS_EMP, 'meta_finca_kilos', 'meta_finca_tonha'].forEach((id) => marcarCampo(id, ''));
    ponerCurva(f?.curva);
    metas.sucioEmp = false;
    metas.sucioFinca = false;
    menuCopiar();
    vivo();
    pintarKpis();
};

const metasCargando = (activo) => {
    $id('ppto_metas').setAttribute('aria-busy', activo ? 'true' : 'false');
    $id('metas_cargando').classList.toggle('d-none', !activo);
};

const cargarMetas = async () => {
    const id = ++metas.peticion;
    const datos = { empresa: empresa(), anio: Number($id('filtro_anio').value) };
    const finca = $id('filtro_finca').value;
    metasCargando(true);
    const envio = await enviar('metas', datos);
    if (id !== metas.peticion) return;
    metasCargando(false);
    Object.assign(metas, datos, { finca });
    if (!envio.ok) {
        if (envio.status === 401 || envio.status === 403) avisar(envio.status, envio.result.message);
        metas.datos = null;
        pintarMetas();
        $id('metas_error').classList.remove('d-none');
        $id('metas_error_texto').textContent = envio.result.message || '';
        return;
    }
    metas.datos = envio.result;
    metas.cache[`${datos.empresa}|${datos.anio}`] = envio.result;
    pintarMetas();
    const clave = `${datos.empresa}|${datos.anio}|${finca}`;
    if (clave !== metas.clave) {
        metas.clave = clave;
        const f = metaFinca();
        if (f && Number(f.kilosAnual) > 0 && f.curvaCompleta) colapso.hide();
        else colapso.show();
    }
};

const ensuciar = (bandera) => {
    metas[bandera] = true;
    vivo();
};

const guardia = (accion) => async () => {
    if (metas.sucioEmp || metas.sucioFinca) {
        const ok = await confirmar({ icon: 'warning', title: '¿Descartar cambios sin guardar?', text: 'La meta o la curva de estacionalidad tienen cambios sin guardar. Si continúa se perderán.', confirmButtonText: 'Descartar y continuar' });
        if (!ok) {
            if (previos) Object.entries(previos).forEach(([clave, valor]) => { $id(`filtro_${clave}`).value = valor; });
            return;
        }
        metas.sucioEmp = false;
        metas.sucioFinca = false;
    }
    accion();
};

const guardarEmpresa = async () => {
    if (metas.ocupado || !CAMPOS_EMP.map(revisar).every(Boolean)) return;
    const boton = $id('meta_emp_guardar');
    const datos = metas.datos;
    metas.ocupado = true;
    cargandoBoton(boton, true, 'Guardando…');
    const result = await pedir('guardarMeta', { empresa: metas.empresa, anio: metas.anio, finca: '', kilosAnual: enviarNum('meta_emp_kilos'), metaTonHa: enviarNum('meta_emp_tonha') });
    metas.ocupado = false;
    cargandoBoton(boton, false);
    if (result && metas.datos === datos) {
        Object.assign(datos.empresa, { kilosAnual: result.meta.kilosAnual, metaTonHa: result.meta.metaTonHa });
        $id('meta_emp_tonha').value = result.meta.metaTonHa == null ? '' : nf(result.meta.metaTonHa, 2);
        $id('meta_emp_kilos').value = valorInput(result.meta.kilosAnual);
        metas.sucioEmp = false;
        Toast.fire({ icon: 'success', title: result.message || 'Meta de la empresa guardada correctamente.' });
        anunciar(result.message || 'Meta de la empresa guardada correctamente.');
    }
    vivo();
};

const guardarFinca = async () => {
    const f = metaFinca();
    if (!f || metas.ocupado || !CAMPOS_FINCA.map(revisar).every(Boolean) || (!curvaVacia() && !curvaOk())) return vivo();
    const boton = $id('meta_finca_guardar');
    const curva = curvaVacia() ? '' : JSON.stringify(RANGO.map((m) => (typeof metas.curva[m] === 'number' ? metas.curva[m] : 0)));
    metas.ocupado = true;
    cargandoBoton(boton, true, 'Guardando…');
    const result = await pedir('guardarMeta', { empresa: metas.empresa, anio: metas.anio, finca: f.finca, kilosAnual: enviarNum('meta_finca_kilos'), metaTonHa: enviarNum('meta_finca_tonha'), curva });
    metas.ocupado = false;
    cargandoBoton(boton, false);
    if (result && metaFinca() === f) {
        const c = result.meta.curva;
        Object.assign(f, { kilosAnual: result.meta.kilosAnual, metaTonHa: result.meta.metaTonHa, curva: c ? RANGO.map((m) => c[m] ?? null) : null, suma: result.meta.suma, curvaCompleta: result.meta.completa });
        $id('meta_finca_kilos').value = valorInput(f.kilosAnual);
        $id('meta_finca_tonha').value = f.metaTonHa == null ? '' : nf(f.metaTonHa, 2);
        ponerCurva(f.curva);
        metas.sucioFinca = false;
        menuCopiar();
        pintarKpis();
        Toast.fire({ icon: 'success', title: result.message || 'Meta de la finca guardada correctamente.' });
        anunciar(result.message || 'Meta de la finca guardada correctamente.');
    }
    vivo();
};

const actualDe = (matriz, lote, m) => (matriz ? (matriz.lotes.find((l) => l.lote === lote)?.meses[m] ?? null) : null);

const celdaPrevia = (actual, nuevo) => {
    const cambia = actual != null && Math.abs(actual - (nuevo ?? 0)) >= 0.005;
    const titulo = cambia ? `Actual: ${nf(actual, 0)} kg → Nuevo: ${nuevo == null ? '–' : `${nf(nuevo, 0)} kg`}` : '';
    return `<td class="gp-ppto-celda solo ${cambia ? 'gp-ppto-cambia' : ''} ${nuevo == null && !cambia ? 'vacio' : ''}" ${cambia ? `title="${escapar(titulo)}"` : ''}>${nuevo == null ? '–' : nf(nuevo, 0)}${cambia ? `<span class="visually-hidden"> (${escapar(titulo)})</span>` : ''}</td>`;
};

const confirmarHabil = () => {
    const g = metas.generacion;
    const marca = $id('generar_entiendo');
    $id('generar_confirmar').disabled = !g || metas.guardandoGen || (!!marca && !marca.checked);
};

const pintarPrevia = (r, f) => {
    const meses = (r.meses || []).map(Number);
    const hist = (r.mesesHistorico || []).map(Number);
    const matriz = estado.finca?.finca === f.finca && estado.anio === metas.anio ? estado.finca : null;
    const enReparto = new Set(r.lotes.map((l) => l.lote));
    const fuera = (matriz?.lotes || []).filter((l) => !enReparto.has(l.lote) && meses.some((m) => l.meses[m] != null));
    const filas = [
        ...r.lotes.map((l) => ({ lote: l.lote, nombre: l.loteNombre, ha: l.hNetas, valor: (m) => (meses.includes(m) ? (l.meses?.[m] ?? null) : actualDe(matriz, l.lote, m)) })),
        ...fuera.map((l) => ({ lote: l.lote, nombre: l.loteNombre, ha: null, valor: (m) => (meses.includes(m) ? null : (l.meses[m] ?? null)) }))
    ];
    const totales = RANGO.map((m) => filas.reduce((s, x) => s + Number(x.valor(m) ?? 0), 0));
    const sobre = Number(r.celdasSobrescritas || 0);
    const mini = (etiqueta, valor, sub, extra = '') => `<div class="col-12 col-md-4"><div class="gp-ppto-mini h-100 ${extra}"><div class="gp-etiqueta-mini">${etiqueta}</div><div class="gp-ppto-kpi">${valor}</div>${sub ? `<span class="gp-subtexto">${sub}</span>` : ''}</div></div>`;

    $id('generar_titulo').textContent = `Previsualización · ${nombreFinca(f)} · ${metas.anio}`;
    $id('generar_caption').textContent = `Presupuesto por lote a generar, ${nombreFinca(f)}, ${metas.anio}`;
    $id('generar_kpis').innerHTML = mini('Total a generar', `${nf(Number(r.total || 0), 0)} <small>kg</small>`, `${meses.length} ${meses.length === 1 ? 'mes' : 'meses'} · meta ${nf(Number(r.kilosAnual || 0), 0)} kg`)
        + mini('Lotes', `${r.lotes.length} <small>· ${nf(Number(r.hectareas || 0), 2)} ha netas</small>`, fuera.length ? (fuera.length === 1 ? '1 lote fuera del reparto perderá su presupuesto' : `${fuera.length} lotes fuera del reparto perderán su presupuesto`) : '')
        + mini('Celdas a sobrescribir', `${sobre} <small>de ${Number(r.celdasExistentes || 0)}</small>`, sobre > 0 ? 'Resaltadas en la tabla' : 'Sin cambios sobre valores existentes', sobre > 0 ? 'ambar' : '');
    $id('generar_historico').innerHTML = hist.length ? `<div class="gp-nota-alerta mb-3"><div class="d-flex gap-2"><i class="bi bi-exclamation-triangle"></i><div>
        <div>Hay metas históricas de finca en ${hist.map((m) => MESES[m - 1].slice(0, 3)).join(', ')}. Al generar se reemplazarán por el detalle por lote.</div>
        <div class="form-check mt-2 mb-0"><input class="form-check-input" type="checkbox" id="generar_entiendo"><label class="form-check-label fw-semibold" for="generar_entiendo">Entiendo que se reemplazarán las metas históricas</label></div>
    </div></div></div>` : '';
    $id('generar_cuerpo').innerHTML = filas.map((x) => `<tr class="${x.ha == null ? 'gp-ppto-fuera' : ''}">
        <th scope="row" class="gp-ppto-col-lote">
            <div class="font-monospace fw-semibold">${escapar(x.lote)}</div>
            <span class="gp-subtexto gp-truncar gp-ppto-nombre" title="${escapar(x.nombre)}">${escapar(x.nombre) || '–'}</span>
            ${x.ha == null ? '<span class="gp-chip-fila ambar">Fuera del reparto</span>' : `<span class="gp-subtexto font-monospace">${nf(x.ha, 2)} ha netas</span>`}
        </th>
        ${RANGO.map((m) => celdaPrevia(meses.includes(m) ? actualDe(matriz, x.lote, m) : null, x.valor(m))).join('')}
        <td class="gp-ppto-col-total">${nf(RANGO.reduce((s, m) => s + Number(x.valor(m) ?? 0), 0), 0)}</td>
    </tr>`).join('');
    $id('generar_pie').innerHTML = `<tr><th scope="row" class="gp-ppto-col-lote">Total mes</th>${totales.map((t) => `<td>${nf(t, 0)}</td>`).join('')}<td class="gp-ppto-col-total">${nf(totales.reduce((s, t) => s + t, 0), 0)}</td></tr>`;
    confirmarHabil();
};

const abrirGenerar = async () => {
    const f = metaFinca();
    if (!f || metas.ocupado) return;
    if (!Number(f.lotesConArea)) {
        Alerta.fire({ icon: 'warning', title: 'La finca no tiene lotes activos con hectáreas netas' });
        return;
    }
    const boton = $id('meta_generar');
    metas.ocupado = true;
    cargandoBoton(boton, true, 'Calculando…');
    const result = await pedir('generar', { empresa: metas.empresa, anio: metas.anio, finca: f.finca, simular: 1 });
    metas.ocupado = false;
    cargandoBoton(boton, false);
    vivo();
    if (!result || metaFinca() !== f) return;
    metas.generacion = { ...result, finca: f.finca, empresa: metas.empresa, anio: metas.anio };
    pintarPrevia(result, f);
    modalGenerar.show();
};

const confirmarGenerar = async () => {
    const g = metas.generacion;
    if (!g || metas.guardandoGen) return;
    const boton = $id('generar_confirmar');
    const cierres = [$id('generar_cancelar'), $id('generar_cerrar')];
    metas.guardandoGen = true;
    cierres.forEach((b) => { b.disabled = true; });
    cargandoBoton(boton, true, 'Guardando…');
    const envio = await enviar('generar', { empresa: g.empresa, anio: g.anio, finca: g.finca, reemplazarHistorico: (g.mesesHistorico || []).length ? 1 : 0 });
    metas.guardandoGen = false;
    cierres.forEach((b) => { b.disabled = false; });
    cargandoBoton(boton, false);
    confirmarHabil();
    if (!envio.ok) {
        Alerta.fire({ icon: 'error', title: 'No se pudo generar el presupuesto', text: envio.result.message || '' });
        return;
    }
    const r = envio.result;
    const mensaje = `Presupuesto generado: ${r.meses.length} meses × ${r.lotes.length} lotes (${r.meses.length * r.lotes.length} celdas)`;
    metas.generacion = null;
    modalGenerar.hide();
    Toast.fire({ icon: 'success', title: mensaje });
    if ($id('filtro_finca').value === g.finca && Number($id('filtro_anio').value) === g.anio && empresa() === g.empresa) {
        await cargar();
        r.lotes.forEach((l) => r.meses.forEach((m) => flash(celdaEn(l.lote, m))));
        $id('ppto_zona').scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
    anunciar(mensaje);
};

CAMPOS_EMP.forEach((id) => {
    prepararInput($id(id));
    $id(id).addEventListener('input', () => {
        revisar(id);
        ensuciar('sucioEmp');
    });
});

['meta_finca_kilos', 'meta_finca_tonha'].forEach((id) => {
    prepararInput($id(id));
    $id(id).addEventListener('input', () => {
        revisar(id);
        ensuciar('sucioFinca');
    });
});

let tAnuncio = null;

const anunciarTotal = () => {
    clearTimeout(tAnuncio);
    anunciar(`Curva de estacionalidad: ${$id('curva_total').textContent}`);
};

$id('metas_copiar_ruta').addEventListener('click', async () => {
    const ruta = 'Documentacion/BaseDeDatos/crear_presupuesto_metas.sql';
    let ok = false;
    try {
        await navigator.clipboard.writeText(ruta);
        ok = true;
    } catch (error) {
        const area = document.createElement('textarea');
        area.value = ruta;
        area.readOnly = true;
        area.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
        document.body.appendChild(area);
        area.select();
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        area.remove();
    }
    Toast.fire(ok ? { icon: 'success', title: 'Ruta copiada' } : { icon: 'error', title: 'No se pudo copiar la ruta' });
});

RANGO.forEach((m) => {
    const input = $id(`curva_${m}`);
    input.addEventListener('focus', () => {
        if (typeof metas.curva[m] === 'number') input.value = String(Number(metas.curva[m].toFixed(4))).replace('.', ',');
        input.select();
    });
    input.addEventListener('blur', () => {
        if (typeof metas.curva[m] === 'number') input.value = formatear(metas.curva[m]);
    });
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^\d.,]/g, '');
        metas.curva[m] = parsear(input.value, 4);
        revisar(input.id);
        ensuciar('sucioFinca');
        clearTimeout(tAnuncio);
        tAnuncio = setTimeout(anunciarTotal, 600);
    });
    input.addEventListener('blur', anunciarTotal);
    input.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        (m < 12 ? $id(`curva_${m + 1}`) : $id('meta_finca_guardar')).focus();
    });
});

$id('meta_calcular').addEventListener('click', () => {
    const kilos = redondear(numeroDe('meta_finca_tonha') * Number(metaFinca()?.hectareas || 0) * 1000);
    $id('meta_finca_kilos').value = formatear(kilos);
    marcarCampo('meta_finca_kilos', '');
    ensuciar('sucioFinca');
    $id('meta_finca_kilos').focus();
});

$id('curva_uniforme').addEventListener('click', () => {
    ponerCurva(RANGO.map((m) => (m < 12 ? 8.33 : redondear(100 - 8.33 * 11))));
    ensuciar('sucioFinca');
});

$id('curva_copiar_menu').addEventListener('click', async (e) => {
    const boton = e.target.closest('[data-copiar]');
    const f = metaFinca();
    if (!boton || !f) return;
    let curva = null;
    if (boton.dataset.copiar === '') {
        const clave = `${metas.empresa}|${metas.anio - 1}`;
        if (!metas.cache[clave]) {
            const result = await pedir('metas', { empresa: metas.empresa, anio: metas.anio - 1 });
            if (!result) return;
            metas.cache[clave] = result;
        }
        curva = metas.cache[clave].fincas?.find((x) => x.finca === f.finca)?.curva;
    } else {
        curva = metas.datos.fincas.find((x) => x.finca === boton.dataset.copiar)?.curva;
    }
    if (metaFinca() !== f) return;
    if (!curva) {
        Toast.fire({ icon: 'info', title: 'No hay curva para copiar' });
        return;
    }
    ponerCurva(curva);
    ensuciar('sucioFinca');
    Toast.fire({ icon: 'success', title: 'Curva copiada, revise y guarde' });
});

$id('meta_emp_guardar').addEventListener('click', guardarEmpresa);
$id('meta_finca_guardar').addEventListener('click', guardarFinca);
$id('meta_generar').addEventListener('click', abrirGenerar);
$id('metas_reintentar').addEventListener('click', cargarMetas);
$id('generar_confirmar').addEventListener('click', confirmarGenerar);
$id('generar_historico').addEventListener('change', confirmarHabil);
$id('modal_generar').addEventListener('hide.bs.modal', (e) => {
    if (metas.guardandoGen) e.preventDefault();
});
$id('metas_cuerpo').addEventListener('shown.bs.collapse', () => window.dispatchEvent(new Event('resize')));

$id('btn_consultar').addEventListener('click', guardia(cargar));
$id('filtro_empresa').addEventListener('change', guardia(() => {
    $id('filtro_finca').value = '';
    cargarFincas();
}));
$id('filtro_anio').addEventListener('change', guardia(cargar));
$id('filtro_finca').addEventListener('change', guardia(cargar));
$id('btn_reintentar').addEventListener('click', () => (estado.cargado ? cargar() : cargarFincas()));

prepararInput(form.kilos);

const cambioSeleccion = () => {
    if (edicion && (edicion.lote !== form.lote.value || edicion.mes !== Number(form.mes.value))) edicion = null;
    marcar('lote', '');
    modoForm();
    refrescarForm(true);
};

form.mes.addEventListener('change', cambioSeleccion);
$lote.on('change', cambioSeleccion);
form.kilos.addEventListener('input', () => {
    if (parsear(form.kilos.value) !== null) marcar('kilos', '');
    refrescarForm(false);
});
form.kilos.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        form.kilos.blur();
        guardarForm();
    }
});
form.guardar.addEventListener('click', guardarForm);
form.limpiar.addEventListener('click', reiniciarForm);
form.eliminar.addEventListener('click', () => {
    if (edicion) eliminarLote(edicion.lote, edicion.mes);
});

$id('ppto_zona').addEventListener('click', (e) => {
    if (!editable() || guardando) return;
    const borrar = e.target.closest('[data-eliminar]');
    if (borrar) {
        const mes = Number(borrar.dataset.mes);
        if (borrar.dataset.eliminar === 'finca') eliminarMeta(mes);
        else eliminarLote(borrar.dataset.lote, mes);
        return;
    }
    const celda = e.target.closest('.gp-ppto-celda-btn');
    if (celda && !celda.disabled) seleccionarCelda(celda.dataset.lote, Number(celda.dataset.mes));
});

document.addEventListener('DOMContentLoaded', cargarFincas);
