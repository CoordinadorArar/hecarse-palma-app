const URL_TB = BASE_URL + 'dashboards/';
const MESES = TABLERO.meses;
const COLOR = { aplicado: '#2f7a4b', meta: '#b6c0cb', falta: '#dc3545', exceso: '#e0a800', ok: '#2f7a4b' };
const CLASE_KPI = { verde: 'ok', ambar: 'alerta', rojo: 'critico' };

const $id = (id) => document.getElementById(id);
const texto = (valor) => String(valor ?? '').trim();
const mostrar = (elemento, visible) => elemento?.classList.toggle('d-none', !visible);
const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const FORMATOS = {
    kg: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }),
    kgPalma: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    kgPalmaSigno: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2, signDisplay: 'exceptZero' }),
    bultos: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    ha: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    pct: new Intl.NumberFormat('es-CO', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 })
};

const nulo = (valor) => valor == null || valor === '' || Number.isNaN(Number(valor));
const f = (clave, valor, unidad = '') => (nulo(valor) ? '—' : FORMATOS[clave].format(Number(valor)) + unidad);
const semaforoDosis = (valor) => {
    const desvio = Math.abs(Number(valor) - 1);

    return desvio <= 0.05 ? 'verde' : (desvio <= 0.15 ? 'ambar' : 'rojo');
};

const estado = { turno: 0, respuesta: null, filtros: null, textos: null, graficas: {}, lotes: new Map(), secciones: new Map() };

const estadoVacio = (icono, titulo, cuerpo, extra = '') => `
    <div class="gp-estado-vacio text-center py-5">
        <div class="gp-medallon ${extra} d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi ${icono}" aria-hidden="true"></i></div>
        <h6 class="mb-1">${titulo}</h6>
        ${cuerpo}
    </div>`;

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;

    try {
        respuesta = await fetch(URL_TB + ruta, { method: 'POST', body: cuerpo, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    } catch (error) {
        throw Object.assign(new Error('Error de conexión.'), { status: 0 });
    }

    let resultado = null;

    try {
        resultado = await respuesta.json();
    } catch (error) {
        resultado = null;
    }

    if (!respuesta.ok || resultado?.success !== true) {
        throw Object.assign(new Error(texto(resultado?.message) || 'No se pudo completar la operación.'), { status: respuesta.status });
    }

    return resultado;
};

const ubicacion = () => {
    const [finca = '', seccion = ''] = $id('filtro_finca').value.split('|');

    return { finca, seccion };
};

const valores = () => {
    const mes = $id('filtro_mes').value;

    return {
        empresa: $id('filtro_empresa').value,
        anio: $id('filtro_anio').value,
        mes: mes || '12',
        modo: mes ? 'mes' : 'acumulado',
        ...ubicacion(),
        lote: $id('filtro_lote').value,
        producto: $id('filtro_producto').value
    };
};

const textoSeleccion = (id) => $id(id).selectedOptions[0]?.text || '';

const revisarVigencia = () => {
    const cambiado = Boolean(estado.filtros) && JSON.stringify(valores()) !== JSON.stringify(estado.filtros);

    mostrar($id('chip_desactualizado'), cambiado);
    $id('resultado_cuerpo').classList.toggle('inf-desactualizado', cambiado);
};

const ocupado = (activo) => {
    const boton = $id('btn_consultar');

    boton.disabled = activo;
    boton.innerHTML = activo
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Consultando…'
        : '<i class="bi bi-search me-1" aria-hidden="true"></i>Consultar';
    $id('resultado_cuerpo').setAttribute('aria-busy', activo ? 'true' : 'false');
};

const skeleton = () => `<div class="placeholder-glow" aria-hidden="true">
    <div class="row g-3 mb-3">${'<div class="col-12 col-sm-6 col-xl-3"><div class="inf-kpi d-block"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-8 placeholder-lg"></span><span class="placeholder col-10 mt-2"></span></div></div>'.repeat(4)}</div>
    <div class="row g-3 mb-3">${'<div class="col-12 col-xl-6"><div class="card mb-0 h-100"><div class="card-body pt-3"><span class="placeholder col-5 mb-3"></span><span class="placeholder w-100 rounded ig-esqueleto"></span></div></div></div>'.repeat(2)}</div>
</div>`;

const destruirGraficas = () => {
    Object.values(estado.graficas).forEach((grafica) => grafica?.destroy());
    estado.graficas = {};
};

const kpi = (icono, etiqueta, valor, sub, extra = '', clase = '') => `<div class="col-12 col-sm-6 col-xl-3">
    <div class="inf-kpi ${clase}">
        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi ${icono}" aria-hidden="true"></i></span>
        <div class="min-w-0 flex-grow-1">
            <span class="gp-etiqueta-mini d-block">${etiqueta}</span>
            <div class="inf-kpi-valor" title="${escaparHtml(valor.replace(/<[^>]+>/g, ''))}">${valor}</div>
            ${extra}
            ${sub ? `<div class="gp-subtexto">${sub}</div>` : ''}
        </div>
    </div>
</div>`;

const pintarKpis = (kpis, neutro) => {
    const conPlan = !nulo(kpis.cumplimiento);
    const color = conPlan ? semaforoDosis(kpis.cumplimiento) : null;
    const barra = neutro ? 'bg-secondary' : `ig-barra-${color}`;
    const pendiente = Number(kpis.pendienteKgPalma);

    return `<div class="row g-3 mb-3">
        ${conPlan
            ? kpi('bi-speedometer2', 'Cumplimiento de dosis', `<span class="${neutro ? '' : `ig-texto-${color}`}">${f('pct', kpis.cumplimiento)}</span>`, escaparHtml(`${f('kg', kpis.aplicadoKg, ' kg')} de ${f('kg', kpis.planificadoKg, ' kg')} plan`),
                `<div class="progress ig-progreso mt-1" role="progressbar" aria-valuenow="${Math.round(Number(kpis.cumplimiento) * 100)}" aria-valuemin="0" aria-valuemax="100" aria-label="Cumplimiento de dosis"><div class="progress-bar ${barra}" style="width:${Math.min(Math.max(Number(kpis.cumplimiento), 0) * 100, 100)}%"></div></div>`,
                neutro ? '' : `ig-kpi-${CLASE_KPI[color]}`)
            : kpi('bi-speedometer2', 'Cumplimiento de dosis', '—', 'Sin plan de fertilización')}
        ${kpi('bi-rulers', 'Kg por hectárea', escaparHtml(f('kg', kpis.kgHa, ' kg/ha')), escaparHtml(`${f('kg', kpis.aplicadoKg, ' kg')} en ${f('ha', kpis.hectareas, ' ha')}`))}
        ${kpi('bi-box-seam', 'Bultos por hectárea', escaparHtml(f('bultos', kpis.bultosHa, ' bultos/ha')), escaparHtml(`${f('bultos', kpis.bultosAplicados)} de ${f('bultos', kpis.bultosPlan)} bultos plan`))}
        ${nulo(kpis.pendienteKgPalma)
            ? kpi('bi-hourglass-split', 'Kg/palma pendiente', '—', 'Sin plan de fertilización')
            : (pendiente > 0
                ? kpi('bi-hourglass-split', 'Kg/palma pendiente', `<span class="ig-texto-ambar">${escaparHtml(f('kgPalma', pendiente, ' kg'))}</span>`, escaparHtml(`de ${f('kgPalma', kpis.metaKgPalma, ' kg/palma')} meta`), '', 'ig-kpi-alerta')
                : kpi('bi-hourglass-split', 'Kg/palma pendiente', escaparHtml(f('kgPalma', 0, ' kg')), escaparHtml(`Meta ${f('kgPalma', kpis.metaKgPalma, ' kg/palma')}`), '<span class="ig-chip verde mt-1"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Dosis completa</span>', 'ig-kpi-ok'))}
    </div>`;
};

const vacioGrafica = (mensaje = 'Sin datos') => `<div class="tb-grafica-vacia"><i class="bi bi-bar-chart" aria-hidden="true"></i>${escaparHtml(mensaje)}</div>`;

const muestras = (lista) => `<span class="gp-subtexto d-flex flex-wrap align-items-center gap-2 w-100" aria-hidden="true">${lista.map(([color, nombre]) => `<span class="d-inline-flex align-items-center gap-1 text-nowrap"><span class="tb-muestra" style="background-color:${color}"></span>${nombre}</span>`).join('')}<span class="text-nowrap">· kg/palma</span></span>`;

const tarjeta = (icono, titulo, leyenda, cuerpo) => `<div class="col-12 col-xl-6">
    <div class="card mb-0 h-100">
        <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
            <h6 class="gp-seccion mb-0 me-auto"><i class="bi ${icono}" aria-hidden="true"></i>${titulo}</h6>
            ${leyenda}
        </div>
        <div class="card-body pt-3">${cuerpo}</div>
    </div>
</div>`;

const altoLotes = (cantidad) => Math.max(cantidad * 28 + 60, 260);

const colorDesviacion = (lote) => {
    const meta = Number(lote.metaKgPalma);
    const desvio = Number(lote.desviacionKgPalma);

    if (nulo(lote.desviacionKgPalma) || nulo(lote.metaKgPalma)) return COLOR.meta;
    if (meta > 0 ? Math.abs(desvio) <= meta * 0.05 : desvio === 0) return COLOR.ok;

    return desvio < 0 ? COLOR.falta : COLOR.exceso;
};

const opcionesLotes = (lotes, series, colores, extra = {}) => ({
    chart: { type: 'bar', height: altoLotes(lotes.length), toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { speed: 300 }, parentHeightOffset: 0 },
    series,
    colors: colores,
    dataLabels: { enabled: false },
    grid: { borderColor: '#eef1f5' },
    legend: { show: false },
    plotOptions: { bar: { horizontal: true, barHeight: '70%', borderRadius: 3, borderRadiusApplication: 'end' } },
    xaxis: { categories: lotes.map((lote) => texto(lote.codigo)), labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => f('kgPalma', valor) } },
    yaxis: { labels: { maxWidth: 120, style: { colors: '#5c6b7a' } } },
    tooltip: {
        shared: true,
        intersect: false,
        x: { formatter: (valor, opciones) => {
            const lote = lotes[opciones?.dataPointIndex];

            return lote ? escaparHtml(texto(lote.nombre) ? `${texto(lote.codigo)} — ${texto(lote.nombre)}` : texto(lote.codigo)) : escaparHtml(valor);
        } },
        y: { formatter: (valor, { seriesIndex }) => (seriesIndex === 1 ? f('kgPalmaSigno', valor, ' kg/palma') : f('kgPalma', valor, ' kg/palma')) }
    },
    ...extra
});

const contexto = () => {
    const r = estado.respuesta;
    const filtros = estado.filtros;
    const chips = [filtros.modo === 'mes' ? `Periodo: ${estado.textos.mes} ${texto(r.filtros?.anio)}` : `Periodo: Ene – Dic ${texto(r.filtros?.anio)}`];

    if ($id('filtro_empresa').options.length > 1) chips.push(`Empresa: ${estado.textos.empresa}`);
    if (!filtros.finca) chips.push('Finca: Todas las fincas');
    if (filtros.lote) chips.push(`Lote: ${estado.textos.lote}`);
    if (filtros.producto) chips.push(`Producto: ${estado.textos.producto}`);

    const html = chips.map((chip) => `<span class="gp-chip-fila inf-chip-filtro">${escaparHtml(chip)}</span>`);

    if (filtros.finca) html.splice(chips.length - (filtros.lote ? 1 : 0) - (filtros.producto ? 1 : 0), 0, `<span class="gp-chip-fila verde">Finca: ${escaparHtml(estado.textos.finca)}<button type="button" class="tb-chip-quitar" data-accion="todas" aria-label="Quitar filtro de finca y ver todas"><span aria-hidden="true">×</span></button></span>`);

    if (!filtros.producto) html.push('<span class="gp-chip-fila ambar"><i class="bi bi-layers me-1" aria-hidden="true"></i>Dosis sumada de todos los productos</span>');
    if (filtros.modo === 'mes') html.push('<span class="gp-chip-fila ambar"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Meta = plan completo vigente</span>');

    $id('chips_contexto').innerHTML = html.join('');
};

const opcion = (valor, etiqueta) => `<option value="${escaparHtml(valor)}">${escaparHtml(etiqueta)}</option>`;

const pintarUbicaciones = () => {
    const select = $id('filtro_finca');
    const actual = select.value;

    select.innerHTML = '<option value="">Todas las fincas</option>' + (estado.fincas || []).map((finca) => {
        const secciones = [...(estado.secciones.get(texto(finca.codigo)) || [])].sort((a, b) => a.localeCompare(b, 'es', { numeric: true }));

        return `<optgroup label="${escaparHtml(`${texto(finca.codigo)} — ${texto(finca.nombre)}`)}">${opcion(`${texto(finca.codigo)}|`, `${texto(finca.nombre)} · Toda la finca`)}${secciones.map((seccion) => opcion(`${texto(finca.codigo)}|${seccion}`, `${texto(finca.nombre)} · Sector ${seccion}`)).join('')}</optgroup>`;
    }).join('');

    if (actual && ![...select.options].some((item) => item.value === actual)) {
        const [finca, seccion] = actual.split('|');

        select.querySelector(`optgroup option[value="${CSS.escape(`${finca}|`)}"]`)?.parentElement.insertAdjacentHTML('beforeend', opcion(actual, `${finca} · Sector ${seccion}`));
    }

    select.value = actual;
    $(select).trigger('change.select2');
};

const pintarLotes = () => {
    const select = $id('filtro_lote');
    const actual = select.value;
    const { finca, seccion } = ubicacion();
    const lotes = [...estado.lotes.values()]
        .filter((lote) => finca && texto(lote.finca) === finca && (!seccion || texto(lote.seccion) === seccion))
        .sort((a, b) => texto(a.codigo).localeCompare(texto(b.codigo), 'es', { numeric: true }));

    select.innerHTML = '<option value="">Todos</option>' + lotes.map((lote) => opcion(lote.codigo, texto(lote.nombre) ? `${texto(lote.codigo)} — ${texto(lote.nombre)}` : texto(lote.codigo))).join('');
    select.value = lotes.some((lote) => texto(lote.codigo) === actual) ? actual : '';
    select.disabled = !finca;
    $(select).trigger('change.select2');
};

const pintarProductos = (productos) => {
    const select = $id('filtro_producto');
    const actual = select.value;
    const lista = [...(productos || [])];

    if (actual && !lista.some((producto) => texto(producto.codigo) === actual)) lista.unshift({ codigo: actual, nombre: textoSeleccion('filtro_producto') });
    select.innerHTML = '<option value="">Todos los productos</option>' + lista.map((producto) => opcion(producto.codigo, texto(producto.nombre) || texto(producto.codigo))).join('');
    select.value = actual;
    $(select).trigger('change.select2');
};

const registrarOpciones = (r) => {
    const opciones = r.opciones || {};
    const finca = texto(r.filtros?.finca);

    (opciones.lotes || []).forEach((lote) => {
        estado.lotes.set(texto(lote.codigo), lote);
        if (texto(lote.seccion)) {
            if (!estado.secciones.has(texto(lote.finca))) estado.secciones.set(texto(lote.finca), new Set());
            estado.secciones.get(texto(lote.finca)).add(texto(lote.seccion));
        }
    });

    if (finca) {
        if (!estado.secciones.has(finca)) estado.secciones.set(finca, new Set());
        (opciones.secciones || []).forEach((seccion) => estado.secciones.get(finca).add(texto(seccion)));
    }

    pintarUbicaciones();
    pintarLotes();
    pintarProductos(opciones.productos);
};

const pintar = () => {
    const r = estado.respuesta;
    const kpis = r.kpis || {};
    const avisos = (r.avisos || []).map((aviso) => `<div class="alert alert-warning py-2 small mb-3 d-flex gap-2" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>${escaparHtml(aviso)}</span></div>`).join('');
    const lotes = (r.lotes || [])
        .filter((lote) => Number(lote.aplicadoKg) > 0 || Number(lote.planificadoKg) > 0)
        .sort((a, b) => texto(a.codigo).localeCompare(texto(b.codigo), 'es', { numeric: true }));

    destruirGraficas();
    contexto();

    if (!lotes.length && !(Number(kpis.aplicadoKg) > 0 || Number(kpis.planificadoKg) > 0)) {
        $id('resultado_cuerpo').innerHTML = avisos + `<div class="card mb-3">${estadoVacio('bi-bar-chart', 'Sin datos para los filtros seleccionados', '<p class="text-muted mb-0">Ajuste el año, el mes, la finca, el lote o el producto.</p>')}</div>`;
        return;
    }

    const conAplicado = lotes.some((lote) => !nulo(lote.kgPalma));
    const conMeta = lotes.some((lote) => !nulo(lote.metaKgPalma));
    const resumen = `${lotes.length} lotes. Dosis total ${f('kgPalma', kpis.kgPalma, ' kg/palma')} de ${f('kgPalma', kpis.metaKgPalma, ' kg/palma')} meta`;

    $id('resultado_cuerpo').innerHTML = avisos + pintarKpis(kpis, estado.filtros.modo === 'mes') + `<div class="row g-3 mb-3">
        ${tarjeta('bi-bar-chart-steps', 'Dosis aplicada por lote', muestras([[COLOR.aplicado, 'Aplicado']]), conAplicado ? `<div class="inf-grafica" id="grafica_aplicado" role="img" aria-label="${escaparHtml(`Kg por palma aplicados por lote. ${resumen}`)}"></div>` : vacioGrafica())}
        ${tarjeta('bi-plus-slash-minus', 'Meta de dosis vs desviación', muestras([[COLOR.meta, 'Meta'], [COLOR.falta, 'Falta'], [COLOR.exceso, 'Exceso'], [COLOR.ok, 'En rango ±5 %']]), conMeta ? `<div class="inf-grafica" id="grafica_desviacion" role="img" aria-label="${escaparHtml(`Meta de dosis y desviación (aplicado menos meta) por lote. Rojo: falta, ámbar: exceso, verde: dentro de ±5 % de la meta. ${resumen}`)}"></div>` : vacioGrafica('Sin meta de dosis'))}
    </div>`;

    if (conAplicado) {
        estado.graficas.aplicado = new ApexCharts($id('grafica_aplicado'), opcionesLotes(lotes, [{ name: 'Kg/palma aplicados', data: lotes.map((lote) => (nulo(lote.kgPalma) ? null : Number(lote.kgPalma))) }], [COLOR.aplicado]));
        estado.graficas.aplicado.render();
    }

    if (conMeta) {
        estado.graficas.desviacion = new ApexCharts($id('grafica_desviacion'), opcionesLotes(lotes, [
            { name: 'Meta dosis', data: lotes.map((lote) => (nulo(lote.metaKgPalma) ? null : Number(lote.metaKgPalma))) },
            { name: 'Desviación (aplicado − meta)', data: lotes.map((lote) => (nulo(lote.desviacionKgPalma) ? null : Number(lote.desviacionKgPalma))) }
        ], [COLOR.meta, ({ dataPointIndex }) => colorDesviacion(lotes[dataPointIndex] || {})], {
            annotations: { xaxis: [{ x: 0, borderColor: '#8795a4' }] }
        }));
        estado.graficas.desviacion.render();
    }
};

const consultar = async () => {
    const turno = ++estado.turno;
    const filtros = valores();

    destruirGraficas();
    estado.filtros = filtros;
    estado.textos = { empresa: textoSeleccion('filtro_empresa'), mes: textoSeleccion('filtro_mes'), finca: textoSeleccion('filtro_finca'), lote: textoSeleccion('filtro_lote'), producto: textoSeleccion('filtro_producto') };
    ocupado(true);
    revisarVigencia();
    $id('chips_contexto').innerHTML = '';
    $id('resultado_cuerpo').innerHTML = skeleton();

    let respuesta;

    try {
        respuesta = await pedir('fertilizacion/datos', filtros);
    } catch (error) {
        if (turno !== estado.turno) return;

        const permiso = [401, 403].includes(error.status);

        ocupado(false);
        estado.respuesta = null;
        $id('resultado_cuerpo').innerHTML = `<div class="card mb-3">${estadoVacio(permiso ? 'bi-shield-lock' : 'bi-exclamation-octagon', permiso ? 'Acción no permitida' : 'No se pudo cargar el tablero',
            `<p class="text-muted mb-3">${escaparHtml(error.message)}</p>${permiso ? '' : '<button type="button" class="btn btn-outline-success btn-sm" data-accion="reintentar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>'}`, 'inf-medallon-error')}</div>`;
        return;
    }

    if (turno !== estado.turno) return;

    ocupado(false);
    estado.respuesta = respuesta;
    registrarOpciones(respuesta);
    pintar();
};

const cargarEmpresa = async () => {
    try {
        const resultado = await pedir('tableros/fincas', { tablero: TABLERO.tablero, empresa: $id('filtro_empresa').value });
        const anios = (resultado.anios || []).length ? resultado.anios : [resultado.anio_sel];

        $id('filtro_anio').innerHTML = anios.map((anio) => opcion(anio, anio)).join('');
        $id('filtro_anio').value = String(resultado.anio_sel);
        $id('filtro_mes').value = '';
        estado.fincas = resultado.fincas || [];
        estado.lotes = new Map();
        estado.secciones = new Map();
        $id('filtro_finca').value = '';
        $id('filtro_producto').innerHTML = '<option value="">Todos los productos</option>';
        pintarUbicaciones();
        pintarLotes();
        $('#filtro_producto').trigger('change.select2');
    } catch (error) {
        Swal.fire({ icon: [401, 403].includes(error.status) ? 'warning' : 'error', title: [401, 403].includes(error.status) ? 'Acción no permitida' : 'No se pudieron cargar las fincas', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }

    revisarVigencia();
};

estado.fincas = TABLERO.fincas || [];

$('#filtro_finca, #filtro_lote, #filtro_producto').select2({
    width: '100%',
    language: { noResults: () => 'No se encontraron resultados' }
});

$('#filtro_finca').on('change', () => {
    pintarLotes();
    revisarVigencia();
});
$('#filtro_lote, #filtro_producto').on('change', revisarVigencia);

['filtro_anio', 'filtro_mes'].forEach((id) => $id(id).addEventListener('change', revisarVigencia));
$id('filtro_empresa').addEventListener('change', cargarEmpresa);

$id('form_filtros').addEventListener('submit', (evento) => {
    evento.preventDefault();
    consultar();
});

$id('chips_contexto').addEventListener('click', (evento) => {
    if (!evento.target.closest('[data-accion="todas"]')) return;

    $('#filtro_finca').val('').trigger('change');
    consultar();
});

$id('resultado_cuerpo').addEventListener('click', (evento) => {
    if (evento.target.closest('[data-accion="reintentar"]')) consultar();
});

consultar();
