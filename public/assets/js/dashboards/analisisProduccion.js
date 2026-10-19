const URL_TB = BASE_URL + 'dashboards/';
const MESES = TABLERO.meses;
const COLOR = { real: '#2f7a4b', meta: '#b6c0cb', anterior: '#8fc9a3', tonHa: '#1f5f8b', ppr: '#41a867' };
const COLOR_SEMAFORO = { verde: '#2f7a4b', ambar: '#e0a800', rojo: '#dc3545' };
const CLASE_KPI = { verde: 'ok', ambar: 'alerta', rojo: 'critico' };
const CLIC = 'Clic en una finca para ver detalle';
const PALETA_DONUT = ['#2f7a4b', '#41a867', '#8fc9a3', '#1f5f8b', '#6fa8d6', '#e0a800'];

const $id = (id) => document.getElementById(id);
const texto = (valor) => String(valor ?? '').trim();
const mostrar = (elemento, visible) => elemento?.classList.toggle('d-none', !visible);
const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const FORMATOS = {
    ton: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    tonSigno: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1, signDisplay: 'exceptZero' }),
    pct: new Intl.NumberFormat('es-CO', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    pctSigno: new Intl.NumberFormat('es-CO', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1, signDisplay: 'exceptZero' }),
    ppr: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    tonHa: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    compacto: new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 })
};

const nulo = (valor) => valor == null || valor === '' || Number.isNaN(Number(valor));
const f = (clave, valor, unidad = '') => (nulo(valor) ? '—' : FORMATOS[clave].format(Number(valor)) + unidad);
const NULO = '<span class="text-muted">—</span>';
const semaforo = (valor) => (valor >= 1 ? 'verde' : (valor >= 0.9 ? 'ambar' : 'rojo'));
const semaforoAnterior = (valor) => (valor >= 0 ? 'verde' : (valor >= -0.1 ? 'ambar' : 'rojo'));
const movil = () => window.innerWidth < 768;
const alto = () => (movil() ? 260 : 320);

const estado = { turno: 0, respuesta: null, filtros: null, textos: null, graficas: {} };

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

const valores = () => ({
    empresa: $id('filtro_empresa').value,
    anio: $id('filtro_anio').value,
    mes: $id('filtro_mes').value,
    modo: 'acumulado',
    finca: $id('filtro_finca').value,
    siembra: $id('filtro_siembra').value
});

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

const tarjetaEsqueleto = (col) => `<div class="${col}"><div class="card mb-0 h-100"><div class="card-body pt-3"><span class="placeholder col-5 mb-3"></span><span class="placeholder w-100 rounded ig-esqueleto"></span></div></div></div>`;

const skeleton = () => `<div class="placeholder-glow" aria-hidden="true">
    <div class="row g-3 mb-3">${'<div class="col-12 col-sm-6 col-xl-3"><div class="inf-kpi d-block"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-8 placeholder-lg"></span><span class="placeholder col-10 mt-2"></span></div></div>'.repeat(4)}</div>
    <div class="row g-3 mb-3">${tarjetaEsqueleto('col-12 col-xl-8')}${tarjetaEsqueleto('col-12 col-xl-4')}</div>
    <div class="row g-3 mb-3">${tarjetaEsqueleto('col-12 col-lg-6 col-xl-4').repeat(3)}</div>
</div>`;

const destruirGraficas = () => {
    Object.values(estado.graficas).forEach((grafica) => (grafica?.dispose ? grafica.dispose() : grafica?.destroy()));
    estado.graficas = {};
};

const variacion = (valor, etiqueta, color) => {
    if (nulo(valor)) return `<span class="tb-variacion text-muted"><i class="bi bi-dash" aria-hidden="true"></i>— ${etiqueta}</span>`;

    const numero = Number(valor);
    const flecha = numero > 0 ? 'bi-arrow-up-right' : (numero < 0 ? 'bi-arrow-down-right' : 'bi-dash');
    const lector = numero > 0 ? 'sube' : (numero < 0 ? 'baja' : 'sin cambio');

    return `<span class="tb-variacion ig-texto-${color}"><i class="bi ${flecha}" aria-hidden="true"></i><span class="visually-hidden">${lector}</span>${f('pctSigno', numero)} ${etiqueta}</span>`;
};

const progreso = (valor, color) => `<div class="progress ig-progreso mt-1" role="progressbar" aria-valuenow="${Math.round(Number(valor) * 100)}" aria-valuemin="0" aria-valuemax="100" aria-label="Cumplimiento"><div class="progress-bar ig-barra-${color}" style="width:${Math.min(Math.max(Number(valor), 0) * 100, 100)}%"></div></div>`;

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

const pintarKpis = (kpis, corte) => {
    const conMeta = !nulo(kpis.meta) && Number(kpis.meta) > 0;
    const cumple = conMeta && !nulo(kpis.cumplimiento);
    const color = cumple ? semaforo(Number(kpis.cumplimiento)) : null;
    const colorAnt = nulo(kpis.vsAnterior) ? null : semaforoAnterior(Number(kpis.vsAnterior));
    const flechaMeta = Number(kpis.difMeta) > 0 ? 'bi-arrow-up-right' : (Number(kpis.difMeta) < 0 ? 'bi-arrow-down-right' : 'bi-dash');
    const periodo = escaparHtml(`Ene – ${corte}`);

    return `<div class="row g-3 mb-3">
        ${kpi('bi-basket', 'Producción real', escaparHtml(f('ton', kpis.real, ' t')), periodo, cumple ? variacion(Number(kpis.cumplimiento) - 1, 'vs meta', color) : '')}
        ${kpi('bi-flag', 'Meta', conMeta ? escaparHtml(f('ton', kpis.meta, ' t')) : '—', conMeta ? 'Presupuesto al corte' : 'Sin meta cargada')}
        ${cumple
            ? kpi('bi-bullseye', 'Real vs Meta', `<span class="ig-texto-${color}">${escaparHtml(f('tonSigno', kpis.difMeta, ' t'))}</span>`, '',
                `<span class="tb-variacion ig-texto-${color}"><i class="bi ${flechaMeta}" aria-hidden="true"></i><span class="visually-hidden">${Number(kpis.difMeta) < 0 ? 'baja' : 'sube'}</span>${f('pct', kpis.cumplimiento)} cumplimiento</span>${progreso(kpis.cumplimiento, color)}<div class="tb-sparkline" id="spark_meta" role="img" aria-label="Producción real mensual"></div>`,
                `ig-kpi-${CLASE_KPI[color]}`)
            : kpi('bi-bullseye', 'Real vs Meta', '—', 'Sin meta cargada', '<div class="tb-sparkline" id="spark_meta" role="img" aria-label="Producción real mensual"></div>')}
        ${kpi('bi-calendar2-range', 'Real vs Año anterior', `<span class="${colorAnt ? `ig-texto-${colorAnt}` : ''}">${escaparHtml(f('tonSigno', kpis.difAnterior, ' t'))}</span>`, nulo(kpis.vsAnterior) ? 'Sin producción el año anterior' : escaparHtml(`Año anterior: ${f('ton', kpis.anterior, ' t')}`),
            `${variacion(kpis.vsAnterior, 'vs año anterior', colorAnt || 'verde')}<div class="tb-sparkline" id="spark_anterior" role="img" aria-label="Producción real y del año anterior por mes"></div>`,
            colorAnt ? `ig-kpi-${CLASE_KPI[colorAnt]}` : '')}
    </div>`;
};

const opcionesBase = (altura) => ({
    chart: { height: altura, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { speed: 300 }, parentHeightOffset: 0 },
    dataLabels: { enabled: false },
    grid: { borderColor: '#eef1f5' },
    legend: { position: movil() ? 'bottom' : 'top', horizontalAlign: 'left' },
    tooltip: { shared: true, intersect: false },
    plotOptions: { bar: { columnWidth: '58%', borderRadius: 3, borderRadiusApplication: 'end' } },
    xaxis: { labels: { style: { colors: '#5c6b7a' } } },
    yaxis: { min: 0, labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => FORMATOS.compacto.format(valor) } }
});

const anotacion = (x, etiqueta) => ({ x, borderColor: '#8795a4', strokeDashArray: 4, label: { text: etiqueta, borderColor: '#8795a4', orientation: 'horizontal', style: { color: '#fff', background: '#8795a4', fontSize: '11px' } } });

const serie = (lista, campo) => lista.map((fila) => (nulo(fila[campo]) ? null : Number(fila[campo])));

const tarjeta = (col, icono, titulo, unidad, cuerpo) => `<div class="${col}">
    <div class="card mb-0 h-100">
        <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
            <h6 class="gp-seccion mb-0 me-auto"><i class="bi ${icono}" aria-hidden="true"></i>${titulo}</h6>
            ${unidad ? `<span class="gp-subtexto">${unidad}</span>` : ''}
        </div>
        <div class="card-body pt-3">${cuerpo}</div>
    </div>
</div>`;

const lienzo = (id, etiqueta) => `<div class="inf-grafica" id="${id}" role="img" aria-label="${escaparHtml(etiqueta)}"></div>`;
const vacioGrafica = (mensaje = 'Sin datos') => `<div class="tb-grafica-vacia"><i class="bi bi-bar-chart" aria-hidden="true"></i>${escaparHtml(mensaje)}</div>`;

const opcionesSpark = (series, colores) => ({
    chart: { type: 'line', height: 40, sparkline: { enabled: true }, animations: { enabled: false } },
    series,
    colors: colores,
    stroke: { width: 2, curve: 'straight' },
    xaxis: { categories: MESES },
    tooltip: { fixed: { enabled: false }, x: { show: true }, marker: { show: false }, y: { formatter: (valor) => f('ton', valor, ' t') } }
});

const opcionesMensual = (r, porFinca) => {
    const base = opcionesBase(alto());
    const corte = Number(r.filtros?.mes);
    const series = [
        { name: 'Ton ejecutadas', type: 'column', data: serie(r.meses, 'real') },
        { name: 'Presupuesto', type: 'column', data: serie(r.meses, 'meta') }
    ];

    if (porFinca) series.push({ name: 'Ton/Ha', type: 'line', data: serie(r.meses, 'tonHa') });

    return {
        ...base,
        chart: { ...base.chart, type: 'line' },
        series,
        colors: [COLOR.real, COLOR.meta, COLOR.tonHa],
        stroke: { width: [0, 0, 3], curve: 'straight' },
        markers: { size: [0, 0, 4], strokeColors: '#fff', strokeWidth: 2 },
        xaxis: { ...base.xaxis, categories: MESES },
        yaxis: porFinca
            ? [
                { ...base.yaxis, seriesName: 'Ton ejecutadas' },
                { seriesName: 'Ton ejecutadas', show: false },
                { seriesName: 'Ton/Ha', opposite: true, min: 0, labels: { style: { colors: COLOR.tonHa }, formatter: (valor) => f('tonHa', valor) } }
            ]
            : base.yaxis,
        annotations: { xaxis: corte >= 1 && corte <= 12 ? [anotacion(MESES[corte - 1], 'Corte')] : [] },
        tooltip: { shared: true, intersect: false, y: { formatter: (valor, { seriesIndex }) => (seriesIndex === 2 ? f('tonHa', valor, ' t/ha') : f('ton', valor, ' t')) } }
    };
};

const opcionesFincas = (fincas, nombres, campo, colores) => {
    const base = opcionesBase(alto());

    return {
        ...base,
        chart: { ...base.chart, type: 'bar', events: { dataPointSelection: (evento, contexto, { dataPointIndex }) => drill(fincas[dataPointIndex]?.codigo) } },
        series: [{ name: 'Real', data: serie(fincas, 'real') }, { name: nombres, data: serie(fincas, campo) }],
        colors: colores,
        states: { active: { filter: { type: 'none' } } },
        xaxis: { ...base.xaxis, categories: fincas.map((finca) => texto(finca.nombre) || texto(finca.codigo)), labels: { ...base.xaxis.labels, trim: true, hideOverlappingLabels: false } },
        tooltip: { shared: true, intersect: false, x: { formatter: (valor) => escaparHtml(valor) }, y: { formatter: (valor) => f('ton', valor, ' t') } }
    };
};

const opcionesDonut = (fincas, total) => ({
    chart: { type: 'donut', height: alto(), fontFamily: 'inherit', toolbar: { show: false }, parentHeightOffset: 0, events: { dataPointSelection: (evento, contexto, { dataPointIndex }) => drill(fincas[dataPointIndex]?.codigo) } },
    series: serie(fincas, 'real'),
    labels: fincas.map((finca) => texto(finca.nombre) || texto(finca.codigo)),
    colors: fincas.map((_, indice) => PALETA_DONUT[indice % PALETA_DONUT.length]),
    dataLabels: { enabled: true, formatter: (valor) => FORMATOS.pct.format(valor / 100), dropShadow: { enabled: false } },
    legend: { position: 'bottom', horizontalAlign: 'left', formatter: (nombre) => escaparHtml(nombre) },
    stroke: { colors: ['#fff'], width: 2 },
    states: { active: { filter: { type: 'none' } } },
    plotOptions: { pie: { donut: { size: '62%', labels: { show: true, value: { formatter: (valor) => f('ton', valor, ' t') }, total: { show: true, label: 'Total', color: '#5c6b7a', formatter: () => f('ton', total, ' t') } } } } },
    tooltip: { y: { formatter: (valor) => f('ton', valor, ' t'), title: { formatter: (nombre) => escaparHtml(nombre) } } }
});

const opcionesPpr = (r) => {
    const base = opcionesBase(alto());

    return {
        ...base,
        chart: { ...base.chart, type: 'bar' },
        series: [{ name: 'PPR', data: serie(r.meses, 'ppr') }],
        colors: [COLOR.ppr],
        legend: { show: false },
        xaxis: { ...base.xaxis, categories: MESES },
        yaxis: { ...base.yaxis, labels: { ...base.yaxis.labels, formatter: (valor) => f('ppr', valor) } },
        tooltip: { shared: true, intersect: false, y: { formatter: (valor) => f('ppr', valor, ' kg/racimo') } }
    };
};

const opcionesGauge = (cumplimiento) => {
    const pct = Number(cumplimiento) * 100;

    return {
        series: [{
            type: 'gauge',
            min: 0,
            max: 120,
            startAngle: 200,
            endAngle: -20,
            splitNumber: 6,
            radius: '92%',
            center: ['50%', '58%'],
            axisLine: { lineStyle: { width: 14, color: [[90 / 120, '#dc3545'], [100 / 120, '#e0a800'], [1, '#2f7a4b']] } },
            pointer: { length: '62%', width: 5, itemStyle: { color: '#2f3b48' } },
            anchor: { show: true, size: 12, itemStyle: { color: '#2f3b48' } },
            axisTick: { distance: -14, length: 5, lineStyle: { color: '#fff', width: 1 } },
            splitLine: { distance: -14, length: 14, lineStyle: { color: '#fff', width: 2 } },
            axisLabel: { distance: 20, color: '#5c6b7a', fontSize: 11 },
            title: { show: false },
            detail: { valueAnimation: false, offsetCenter: [0, '38%'], fontSize: 25.6, fontWeight: 700, color: '#2f3b48', formatter: () => FORMATOS.pct.format(pct / 100) },
            data: [{ value: Math.min(Math.max(pct, 0), 120) }]
        }]
    };
};

const pintarContexto = () => {
    const r = estado.respuesta;
    const ultimo = Number(r.filtros?.ultimoMes);
    const chips = [`Corte: Ene – ${estado.textos.mes} ${texto(r.filtros?.anio)}`];

    if ($id('filtro_empresa').options.length > 1) chips.push(`Empresa: ${estado.textos.empresa}`);

    const html = chips.map((chip) => `<span class="gp-chip-fila inf-chip-filtro">${escaparHtml(chip)}</span>`);

    html.push(estado.filtros.finca
        ? `<span class="gp-chip-fila verde">Finca: ${escaparHtml(estado.textos.finca)}<button type="button" class="tb-chip-quitar" data-accion="todas" aria-label="Quitar filtro de finca y ver todas"><span aria-hidden="true">×</span></button></span>`
        : '<span class="gp-chip-fila inf-chip-filtro">Finca: Todas las fincas</span>');

    if (estado.filtros.siembra) html.push(`<span class="gp-chip-fila inf-chip-filtro">Siembra: ${escaparHtml(estado.filtros.siembra)}</span>`);
    if (ultimo >= 1 && ultimo <= 12) html.push(`<span class="gp-chip-fila ${Number(r.filtros?.mes) > ultimo ? 'ambar' : 'inf-chip-filtro'}">Último mes facturado: ${escaparHtml(MESES[ultimo - 1])}</span>`);

    $id('chips_contexto').innerHTML = html.join('');
};

const pintarSiembras = (siembras) => {
    const select = $id('filtro_siembra');
    const actual = select.value;
    const lista = (siembras || []).map(String);

    if (actual && !lista.includes(actual)) lista.unshift(actual);
    select.innerHTML = '<option value="">Todos</option>' + lista.map((anio) => `<option value="${escaparHtml(anio)}">${escaparHtml(anio)}</option>`).join('');
    select.value = actual;
    $(select).trigger('change.select2');
};

const celdaCumplimiento = (valor) => (nulo(valor) ? NULO : `<span class="ig-chip ig-chip-pct ${semaforo(Number(valor))}">${f('pct', valor)}</span>`);
const celdaVariacion = (valor) => (nulo(valor) ? NULO : `<span class="${Number(valor) < 0 ? 'ig-texto-rojo fw-semibold' : ''}">${f('tonSigno', valor)}</span>`);

const pintarTabla = (r) => {
    const lotes = [...(r.lotes || [])].sort((a, b) => texto(a.codigo).localeCompare(texto(b.codigo), 'es', { numeric: true }));

    if (!lotes.length) return `<div class="row g-3 mb-3">${tarjeta('col-12', 'bi-table', 'Producción por lote', '', vacioGrafica())}</div>`;

    const suma = (campo) => lotes.reduce((total, lote) => total + (nulo(lote[campo]) ? 0 : Number(lote[campo])), 0);
    const conMeta = lotes.some((lote) => !nulo(lote.meta));
    const real = suma('real');
    const meta = conMeta ? suma('meta') : null;
    const filas = lotes.map((lote) => `<tr>
        <td><span class="fw-semibold">${escaparHtml(lote.codigo)}</span>${texto(lote.nombre) ? `<span class="gp-subtexto">${escaparHtml(lote.nombre)}</span>` : ''}</td>
        <td class="text-center">${Number(lote.siembra) > 0 ? escaparHtml(lote.siembra) : NULO}</td>
        <td class="inf-num">${f('ton', lote.real)}</td>
        <td class="inf-num">${nulo(lote.meta) ? NULO : f('ton', lote.meta)}</td>
        <td class="text-center">${celdaCumplimiento(lote.cumplimiento)}</td>
        <td class="inf-num">${celdaVariacion(lote.variacion)}</td>
    </tr>`).join('');

    return `<div class="row g-3 mb-3">${tarjeta('col-12', 'bi-table', 'Producción por lote', `${lotes.length} lotes · t`, `<div class="table-responsive ig-ciclo-scroll tb-tabla-lotes">
        <table class="table table-sm align-middle mb-0 gp-tabla inf-tabla inf-tabla-fija">
            <thead class="gp-thead">
                <tr><th scope="col">Lote</th><th scope="col" class="text-center">Año siembra</th><th scope="col" class="text-end">Real (t)</th><th scope="col" class="text-end">Meta (t)</th><th scope="col" class="text-center">% Cumplimiento</th><th scope="col" class="text-end">Variación (t)</th></tr>
            </thead>
            <tbody>${filas}</tbody>
            <tfoot>
                <tr><th scope="row">Total</th><th></th><th class="inf-num">${f('ton', real)}</th><th class="inf-num">${f('ton', meta)}</th><th class="text-center">${meta > 0 ? celdaCumplimiento(real / meta) : NULO}</th><th class="inf-num">${conMeta ? celdaVariacion(real - meta) : NULO}</th></tr>
            </tfoot>
        </table>
    </div>`)}</div>`;
};

const avisosHtml = (avisos) => (avisos || []).map((aviso) => `<div class="alert alert-warning py-2 small mb-3 d-flex gap-2" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>${escaparHtml(aviso)}</span></div>`).join('');

const crear = (clave, id, opciones) => {
    estado.graficas[clave] = new ApexCharts($id(id), opciones);
    estado.graficas[clave].render();
};

const pintar = () => {
    const r = estado.respuesta;
    const kpis = r.kpis || {};
    const porFinca = Boolean(estado.filtros.finca);
    const fincas = (r.fincas || []).filter((finca) => !nulo(finca.real) || !nulo(finca.meta) || !nulo(finca.anterior));
    const hay = [kpis.real, kpis.meta, kpis.anterior].some((valor) => Number(valor) > 0);
    const conMeta = !nulo(kpis.meta) && Number(kpis.meta) > 0 && !nulo(kpis.cumplimiento);
    const corte = MESES[Number(r.filtros?.mes) - 1] || estado.textos.mes;

    destruirGraficas();
    pintarContexto();
    pintarSiembras(r.opciones?.siembras);

    if (!hay) {
        $id('resultado_cuerpo').innerHTML = avisosHtml(r.avisos) + `<div class="card mb-3">${estadoVacio('bi-bar-chart', 'Sin datos para los filtros seleccionados', '<p class="text-muted mb-0">Ajuste el año, el mes de corte, la finca o el año de siembra.</p>')}</div>`;
        return;
    }

    const conMeses = (r.meses || []).some((mes) => Number(mes.real) > 0 || Number(mes.meta) > 0);
    const conPpr = (r.meses || []).some((mes) => !nulo(mes.ppr));
    const conAnterior = fincas.some((finca) => Number(finca.real) > 0 || Number(finca.anterior) > 0);
    const conMetaFincas = fincas.some((finca) => Number(finca.real) > 0 || Number(finca.meta) > 0);
    const participan = fincas.filter((finca) => Number(finca.real) > 0);
    const resumen = `Real ${f('ton', kpis.real, ' t')} de Meta ${f('ton', kpis.meta, ' t')}`;

    let html = avisosHtml(r.avisos) + pintarKpis(kpis, corte) + `<div class="row g-3 mb-3">
        ${tarjeta('col-12 col-xl-8', 'bi-bar-chart-line', 'Producción mensual vs presupuesto', porFinca ? 't · t/ha' : 't', conMeses ? lienzo('grafica_mensual', `Producción mensual: ${resumen} al corte de ${corte}`) : vacioGrafica())}
        ${tarjeta('col-12 col-xl-4', 'bi-speedometer2', 'Cumplimiento al corte', '%', conMeta
            ? `<div class="tb-gauge" id="grafica_gauge" role="img" aria-label="${escaparHtml(`Cumplimiento ${f('pct', kpis.cumplimiento)}. ${resumen}`)}"></div><div class="gp-subtexto tb-gauge-texto">${escaparHtml(resumen)}</div>`
            : vacioGrafica('Sin meta para calcular cumplimiento'))}
    </div>`;

    html += porFinca
        ? `<div class="row g-3 mb-3">${tarjeta('col-12', 'bi-boxes', 'Peso promedio de racimo mensual', 'kg/racimo', conPpr ? lienzo('grafica_ppr', `Peso promedio de racimo mensual. Acumulado ${f('ppr', kpis.ppr, ' kg/racimo')}`) : vacioGrafica())}</div>${pintarTabla(r)}`
        : `<div class="row g-3 mb-3">
            ${tarjeta('col-12 col-lg-6 col-xl-4', 'bi-bar-chart', 'Real vs año anterior por finca', `t</span><span class="gp-subtexto w-100">${CLIC}`, conAnterior ? lienzo('grafica_anterior', 'Producción real y del año anterior por finca. Seleccione una finca para ver su detalle.') : vacioGrafica())}
            ${tarjeta('col-12 col-lg-6 col-xl-4', 'bi-bar-chart-steps', 'Real vs meta por finca', `t</span><span class="gp-subtexto w-100">${CLIC}`, conMetaFincas ? lienzo('grafica_meta', 'Producción real y meta por finca. Seleccione una finca para ver su detalle.') : vacioGrafica())}
            ${tarjeta('col-12 col-lg-6 col-xl-4', 'bi-pie-chart', 'Participación por finca', `%</span><span class="gp-subtexto w-100">${CLIC}`, participan.length ? lienzo('grafica_participacion', `Participación por finca: ${participan.map((finca) => `${texto(finca.nombre)} ${f('pct', finca.participacion)}`).join(', ')}`) : vacioGrafica())}
        </div>`;

    $id('resultado_cuerpo').innerHTML = html;

    crear('sparkMeta', 'spark_meta', opcionesSpark([{ name: 'Real', data: serie(r.meses || [], 'real') }], [COLOR.real]));
    crear('sparkAnterior', 'spark_anterior', opcionesSpark([{ name: 'Real', data: serie(r.meses || [], 'real') }, { name: 'Año anterior', data: serie(r.meses || [], 'anterior') }], [COLOR.real, COLOR.anterior]));
    if (conMeses) crear('mensual', 'grafica_mensual', opcionesMensual(r, porFinca));
    if (conMeta) {
        estado.graficas.gauge = echarts.init($id('grafica_gauge'));
        estado.graficas.gauge.setOption(opcionesGauge(kpis.cumplimiento));
    }
    if (porFinca && conPpr) crear('ppr', 'grafica_ppr', opcionesPpr(r));
    if (!porFinca && conAnterior) crear('anterior', 'grafica_anterior', opcionesFincas(fincas, 'Año anterior', 'anterior', [COLOR.real, COLOR.anterior]));
    if (!porFinca && conMetaFincas) crear('meta', 'grafica_meta', opcionesFincas(fincas, 'Meta', 'meta', [COLOR.real, COLOR.meta]));
    if (!porFinca && participan.length) crear('participacion', 'grafica_participacion', opcionesDonut(participan, kpis.real));
};

const consultar = async () => {
    const turno = ++estado.turno;
    const filtros = valores();

    destruirGraficas();
    estado.filtros = filtros;
    estado.textos = { empresa: textoSeleccion('filtro_empresa'), mes: textoSeleccion('filtro_mes'), finca: filtros.finca ? textoSeleccion('filtro_finca') : 'Todas las fincas' };
    ocupado(true);
    revisarVigencia();
    $id('chips_contexto').innerHTML = '';
    $id('resultado_cuerpo').innerHTML = skeleton();

    let respuesta;

    try {
        respuesta = await pedir('produccion/datos', filtros);
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
    pintar();
};

const drill = (codigo) => {
    if (codigo === undefined || codigo === null || (codigo === '' && !estado.filtros?.finca)) return;

    $('#filtro_finca').val(String(codigo)).trigger('change');
    consultar();
};

const cargarEmpresa = async () => {
    try {
        const resultado = await pedir('tableros/fincas', { tablero: TABLERO.tablero, empresa: $id('filtro_empresa').value });
        const anios = (resultado.anios || []).length ? resultado.anios : [resultado.anio_sel];

        $id('filtro_anio').innerHTML = anios.map((anio) => `<option value="${escaparHtml(anio)}">${escaparHtml(anio)}</option>`).join('');
        $id('filtro_anio').value = String(resultado.anio_sel);
        $id('filtro_mes').value = String(resultado.mes_sel);
        $id('filtro_finca').innerHTML = '<option value="">Todas las fincas</option>' + (resultado.fincas || []).map((finca) => `<option value="${escaparHtml(finca.codigo)}">${escaparHtml(`${texto(finca.codigo)} — ${texto(finca.nombre)}`)}</option>`).join('');
        $id('filtro_siembra').innerHTML = '<option value="">Todos</option>';
        $('#filtro_finca, #filtro_siembra').trigger('change.select2');
    } catch (error) {
        Swal.fire({ icon: [401, 403].includes(error.status) ? 'warning' : 'error', title: [401, 403].includes(error.status) ? 'Acción no permitida' : 'No se pudieron cargar las fincas', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }

    revisarVigencia();
};

$('#filtro_finca, #filtro_siembra').select2({
    width: '100%',
    language: { noResults: () => 'No se encontraron resultados' }
}).on('change', revisarVigencia);

['filtro_anio', 'filtro_mes'].forEach((id) => $id(id).addEventListener('change', revisarVigencia));
$id('filtro_empresa').addEventListener('change', cargarEmpresa);

$id('form_filtros').addEventListener('submit', (evento) => {
    evento.preventDefault();
    consultar();
});

$id('chips_contexto').addEventListener('click', (evento) => {
    if (evento.target.closest('[data-accion="todas"]')) drill('');
});

$id('resultado_cuerpo').addEventListener('click', (evento) => {
    if (evento.target.closest('[data-accion="reintentar"]')) consultar();
});

window.addEventListener('resize', () => estado.graficas.gauge?.resize());

consultar();
