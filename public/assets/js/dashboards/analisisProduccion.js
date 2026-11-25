const URL_TB = BASE_URL + 'dashboards/';
const MESES = TABLERO.meses;
const COLOR = { real: '#2f7a4b', meta: '#b6c0cb', anterior: '#8fc9a3', tonHa: '#1f5f8b', metaTonHa: '#4f7fa6', ppr: '#41a867' };
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
    kg: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }),
    compacto: new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 })
};

const nulo = (valor) => valor == null || valor === '' || Number.isNaN(Number(valor));
const f = (clave, valor, unidad = '') => (nulo(valor) ? '—' : FORMATOS[clave].format(Number(valor)) + unidad);
const NULO = '<span class="text-muted">—</span>';
const semaforo = (valor) => (valor >= 1 ? 'verde' : (valor >= 0.9 ? 'ambar' : 'rojo'));
const semaforoAnterior = (valor) => (valor >= 0 ? 'verde' : (valor >= -0.1 ? 'ambar' : 'rojo'));
const movil = () => window.innerWidth < 768;
const alto = () => (movil() ? 260 : 320);

const partesMes = (periodo) => texto(periodo).split('-').map(Number);
const indiceMes = (periodo) => { const [anio, mes] = partesMes(periodo); return anio * 12 + mes - 1; };
const periodoDe = (indice) => `${Math.floor(indice / 12)}-${String((indice % 12) + 1).padStart(2, '0')}`;
const etiquetaMes = (periodo) => { const [anio, mes] = partesMes(periodo); return MESES[mes - 1] ? `${MESES[mes - 1]} ${anio}` : ''; };
const restarAnio = (periodo) => periodoDe(indiceMes(periodo) - 12);
const rango = (desde, hasta) => `${etiquetaMes(desde)} – ${etiquetaMes(hasta)}`;
const cuentaMeses = (desde, hasta) => indiceMes(hasta) - indiceMes(desde) + 1;
const periodoConsulta = (r) => {
    const desde = texto(r.filtros?.desde) || estado.filtros.desde;
    const hasta = texto(r.filtros?.hasta) || estado.filtros.hasta;

    const corte = texto(r.filtros?.corte);

    return {
        desde,
        hasta,
        meses: cuentaMeses(desde, hasta),
        conReal: (r.meses || []).filter((mes) => !nulo(mes.real)).length,
        corte: corte && indiceMes(hasta) > indiceMes(corte) ? corte : '',
        texto: rango(desde, hasta),
        comparado: rango(restarAnio(desde), restarAnio(hasta))
    };
};

const estado = { turno: 0, respuesta: null, filtros: null, textos: null, graficas: {}, meta: null };
const SCRIPT_HISTORICO = 'crear_produccion_historica.sql';
const FUENTE = { bascula: ['verde', 'Báscula', ''], historico: ['gris', 'Excel', ' · Histórico Excel'], mixto: ['ambar', 'Mixto', ' · Báscula/Excel'] };

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
    desde: $id('filtro_desde').value,
    hasta: $id('filtro_hasta').value,
    finca: $id('filtro_finca').value,
    siembra: $id('filtro_siembra').value,
    vista: document.querySelector('input[name="vista"]:checked').value
});

const textoSeleccion = (id) => $id(id).selectedOptions[0]?.text || '';

const errorRango = () => {
    if ($id('vista_historico').checked) return '';

    const meses = cuentaMeses($id('filtro_desde').value, $id('filtro_hasta').value);

    if (meses < 1) return '«Hasta» no puede ser anterior a «Desde».';

    return meses > 24 ? `El periodo máximo es de 24 meses; el seleccionado tiene ${meses}.` : '';
};

const revisarVigencia = () => {
    const error = errorRango();
    const cambiado = !error && Boolean(estado.filtros) && JSON.stringify(valores()) !== JSON.stringify(estado.filtros);

    ['filtro_desde', 'filtro_hasta'].forEach((id) => $id(id).classList.toggle('is-invalid', Boolean(error)));
    $id('error_rango').textContent = error;
    mostrar($id('fila_error_rango'), Boolean(error));
    $id('btn_consultar').disabled = Boolean(error) || $id('resultado_cuerpo').getAttribute('aria-busy') === 'true';
    mostrar($id('chip_desactualizado'), cambiado);
    $id('resultado_cuerpo').classList.toggle('inf-desactualizado', cambiado);
};

const ocupado = (activo) => {
    const boton = $id('btn_consultar');

    boton.disabled = activo || Boolean(errorRango());
    boton.innerHTML = activo
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Consultando…'
        : '<i class="bi bi-search me-1" aria-hidden="true"></i>Consultar';
    $id('resultado_cuerpo').setAttribute('aria-busy', activo ? 'true' : 'false');
};

const tarjetaEsqueleto = (col) => `<div class="${col}"><div class="card mb-0 h-100"><div class="card-body pt-3"><span class="placeholder col-5 mb-3"></span><span class="placeholder w-100 rounded ig-esqueleto"></span></div></div></div>`;

const skeleton = () => `<div class="placeholder-glow" aria-hidden="true">
    <div class="row g-3 mb-3">${'<div class="col-12 col-sm-6 col-lg-4 col-xxl min-w-0"><div class="inf-kpi d-block"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-8 placeholder-lg"></span><span class="placeholder col-10 mt-2"></span></div></div>'.repeat(5)}</div>
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

const kpi = (icono, etiqueta, valor, sub, extra = '', clase = '', titulo = '') => `<div class="col-12 col-sm-6 col-lg-4 col-xxl min-w-0">
    <div class="inf-kpi ${clase}">
        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi ${icono}" aria-hidden="true"></i></span>
        <div class="min-w-0 flex-grow-1">
            <span class="gp-etiqueta-mini d-block">${etiqueta}</span>
            <div class="inf-kpi-valor" title="${escaparHtml(valor.replace(/<[^>]+>/g, ''))}">${valor}</div>
            ${extra}
            ${sub ? `<div class="gp-subtexto text-truncate" title="${escaparHtml(titulo || sub.replace(/<[^>]+>/g, ''))}">${sub}</div>` : ''}
        </div>
    </div>
</div>`;

const metaTonHaPeriodo = (kpis, meses) => (nulo(kpis.metaTonHa) ? null : (Number(kpis.metaTonHa) * meses) / 12);

const kpiTonHa = (kpis, meses) => {
    const real = escaparHtml(f('tonHa', kpis.tonHa, ' t/ha'));
    const metaPeriodo = metaTonHaPeriodo(kpis, meses);

    if (metaPeriodo === null) return kpi('bi-speedometer2', 'Rendimiento t/ha', real, 'Sin meta t/ha cargada');

    const sub = escaparHtml(`${kpis.metaTonHaOrigen === 'finca' ? 'Meta finca' : 'Meta empresa'}: ${f('tonHa', kpis.metaTonHa)} t/ha anual · ${f('tonHa', metaPeriodo)} t/ha del periodo`);

    if (!(metaPeriodo > 0) || nulo(kpis.tonHa)) return kpi('bi-speedometer2', 'Rendimiento t/ha', real, sub);

    const cumple = Number(kpis.tonHa) / metaPeriodo;
    const color = semaforo(cumple);

    return kpi('bi-speedometer2', 'Rendimiento t/ha', real, sub, `${variacion(cumple - 1, 'vs meta del periodo', color)}${progreso(cumple, color)}`, `ig-kpi-${CLASE_KPI[color]}`);
};

const pintarKpis = (kpis, periodo) => {
    const conMeta = !nulo(kpis.meta) && Number(kpis.meta) > 0;
    const cumple = conMeta && !nulo(kpis.cumplimiento);
    const color = cumple ? semaforo(Number(kpis.cumplimiento)) : null;
    const colorAnt = nulo(kpis.vsAnterior) ? null : semaforoAnterior(Number(kpis.vsAnterior));
    const flechaMeta = Number(kpis.difMeta) > 0 ? 'bi-arrow-up-right' : (Number(kpis.difMeta) < 0 ? 'bi-arrow-down-right' : 'bi-dash');
    const anterior = f('ton', kpis.anterior, ' t');

    return `<div class="row g-3 mb-3">
        ${kpi('bi-basket', 'Producción real', escaparHtml(f('ton', kpis.real, ' t')), escaparHtml(periodo.texto), cumple ? variacion(Number(kpis.cumplimiento) - 1, 'vs meta', color) : '')}
        ${kpi('bi-flag', 'Meta', conMeta ? escaparHtml(f('ton', kpis.meta, ' t')) : '—', conMeta ? 'Presupuesto del periodo' : 'Sin meta cargada')}
        ${cumple
            ? kpi('bi-bullseye', 'Real vs Meta', `<span class="ig-texto-${color}">${escaparHtml(f('tonSigno', kpis.difMeta, ' t'))}</span>`, '',
                `<span class="tb-variacion ig-texto-${color}"><i class="bi ${flechaMeta}" aria-hidden="true"></i><span class="visually-hidden">${Number(kpis.difMeta) < 0 ? 'baja' : 'sube'}</span>${f('pct', kpis.cumplimiento)} cumplimiento</span>${progreso(kpis.cumplimiento, color)}<div class="tb-sparkline" id="spark_meta" role="img" aria-label="Producción real mensual"></div>`,
                `ig-kpi-${CLASE_KPI[color]}`)
            : kpi('bi-bullseye', 'Real vs Meta', '—', 'Sin meta cargada', '<div class="tb-sparkline" id="spark_meta" role="img" aria-label="Producción real mensual"></div>')}
        ${kpi('bi-calendar2-range', 'Real vs Año anterior', `<span class="${colorAnt ? `ig-texto-${colorAnt}` : ''}">${escaparHtml(f('tonSigno', kpis.difAnterior, ' t'))}</span>`, nulo(kpis.vsAnterior) ? 'Sin producción el año anterior' : escaparHtml(`Año anterior: ${anterior}`),
            `${variacion(kpis.vsAnterior, 'vs año anterior', colorAnt || 'verde')}<div class="tb-sparkline" id="spark_anterior" role="img" aria-label="Producción real y del año anterior por mes"></div>`,
            colorAnt ? `ig-kpi-${CLASE_KPI[colorAnt]}` : '', nulo(kpis.vsAnterior) ? '' : `${periodo.comparado}: ${anterior}`)}
        ${kpiTonHa(kpis, periodo.conReal)}
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

const ejeMeses = (base, meses) => ({
    ...base.xaxis,
    categories: meses.map((fila, indice) => { const [anio, mes] = partesMes(fila.periodo); return indice === 0 || mes === 1 ? [MESES[mes - 1], String(anio)] : MESES[mes - 1]; }),
    labels: { ...base.xaxis.labels, rotate: 0, hideOverlappingLabels: true, style: { ...base.xaxis.labels.style, fontSize: '11px' } }
});

const barrasMeses = (base, meses) => ({ ...base.plotOptions, bar: { ...base.plotOptions.bar, columnWidth: meses.length > 18 ? '70%' : base.plotOptions.bar.columnWidth } });

const tooltipMes = (meses) => ({ formatter: (valor, { dataPointIndex }) => etiquetaMes(meses[dataPointIndex]?.periodo) });

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

const opcionesSpark = (series, colores, meses) => ({
    chart: { type: 'line', height: 40, sparkline: { enabled: true }, animations: { enabled: false } },
    series,
    colors: colores,
    stroke: { width: 2, curve: 'straight' },
    xaxis: { categories: meses.map((fila) => etiquetaMes(fila.periodo)) },
    tooltip: {
        fixed: { enabled: false },
        x: { show: true },
        marker: { show: false },
        y: {
            formatter: (valor) => f('ton', valor, ' t'),
            title: { formatter: (nombre, { dataPointIndex }) => (nombre === 'Año anterior' ? `Año anterior (${etiquetaMes(restarAnio(meses[dataPointIndex]?.periodo))})` : nombre) }
        }
    }
});

const opcionesMensual = (r, porFinca, meses) => {
    const base = opcionesBase(alto());
    const series = [
        { name: 'Ton ejecutadas', type: 'column', data: serie(r.meses, 'real') },
        { name: 'Presupuesto', type: 'column', data: serie(r.meses, 'meta') }
    ];

    const kpis = r.kpis || {};
    const metaPeriodo = metaTonHaPeriodo(kpis, meses);
    const conMetaTonHa = porFinca && metaPeriodo > 0 && Number(kpis.meta) > 0 && r.meses.some((mes) => !nulo(mes.meta));

    if (porFinca) series.push({ name: 'Ton/Ha', type: 'line', data: serie(r.meses, 'tonHa') });
    if (conMetaTonHa) series.push({ name: 'Meta t/ha', type: 'line', data: r.meses.map((mes) => (nulo(mes.meta) ? null : (metaPeriodo * Number(mes.meta)) / Number(kpis.meta))) });

    return {
        ...base,
        chart: { ...base.chart, type: 'line' },
        series,
        colors: [COLOR.real, COLOR.meta, COLOR.tonHa, COLOR.metaTonHa],
        stroke: { width: [0, 0, 3, 2], curve: 'straight', dashArray: [0, 0, 0, 6] },
        markers: { size: [0, 0, 4, 0], strokeColors: '#fff', strokeWidth: 2 },
        legend: { ...base.legend, formatter: (nombre) => escaparHtml(nombre) },
        plotOptions: barrasMeses(base, r.meses),
        xaxis: ejeMeses(base, r.meses),
        yaxis: porFinca
            ? [
                { ...base.yaxis, seriesName: 'Ton ejecutadas' },
                { seriesName: 'Ton ejecutadas', show: false },
                { seriesName: 'Ton/Ha', opposite: true, min: 0, labels: { style: { colors: COLOR.tonHa }, formatter: (valor) => f('tonHa', valor) } },
                ...(conMetaTonHa ? [{ seriesName: 'Ton/Ha', show: false }] : [])
            ]
            : base.yaxis,
        tooltip: { shared: true, intersect: false, x: tooltipMes(r.meses), y:{ formatter: (valor, { seriesIndex }) => (seriesIndex >= 2 ? f('tonHa', valor, ' t/ha') : f('ton', valor, ' t')) } }
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
        plotOptions: barrasMeses(base, r.meses),
        xaxis: ejeMeses(base, r.meses),
        yaxis: { ...base.yaxis, labels: { ...base.yaxis.labels, formatter: (valor) => f('ppr', valor) } },
        tooltip: { shared: true, intersect: false, x: tooltipMes(r.meses), y:{ formatter: (valor) => f('ppr', valor, ' kg/racimo') } }
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
    const historico = estado.filtros.vista === 'historico';
    const periodo = historico ? null : periodoConsulta(r);
    const ultimo = historico ? '' : texto(r.filtros?.ultimoMes);
    const chips = historico ? [`Vista: Histórico ${texto(r.desde)}–${texto(r.hasta)}`] : [`Comparado con: ${periodo.comparado}`];
    const fuentes = historico ? [] : [r.kpis?.fuentes?.real, r.kpis?.fuentes?.anterior].filter(Boolean);
    const mixto = fuentes.includes('mixto') || (fuentes.includes('historico') && fuentes.includes('bascula'));

    if ($id('filtro_empresa').options.length > 1) chips.push(`Empresa: ${estado.textos.empresa}`);

    const html = chips.map((chip) => `<span class="gp-chip-fila inf-chip-filtro">${escaparHtml(chip)}</span>`);

    if (periodo) html.unshift(`<span class="gp-chip-fila inf-chip-filtro"><i class="bi bi-calendar-range me-1" aria-hidden="true"></i>${escaparHtml(`Periodo: ${periodo.texto} · ${periodo.meses} ${periodo.meses === 1 ? 'mes' : 'meses'}`)}</span>`);

    html.push(estado.filtros.finca
        ? `<span class="gp-chip-fila verde">Finca: ${escaparHtml(estado.textos.finca)}<button type="button" class="tb-chip-quitar" data-accion="todas" aria-label="Quitar filtro de finca y ver todas"><span aria-hidden="true">×</span></button></span>`
        : '<span class="gp-chip-fila inf-chip-filtro">Finca: Todas las fincas</span>');

    if (estado.filtros.siembra) html.push(`<span class="gp-chip-fila inf-chip-filtro">Siembra: ${escaparHtml(estado.filtros.siembra)}</span>`);
    if (etiquetaMes(ultimo)) html.push(`<span class="gp-chip-fila ${indiceMes(periodo.hasta) > indiceMes(ultimo) ? 'ambar' : 'inf-chip-filtro'}">Último mes facturado: ${escaparHtml(etiquetaMes(ultimo))}</span>`);
    if (mixto || fuentes.includes('historico')) html.push(`<span class="gp-chip-fila ${mixto ? 'ambar' : 'gris'}" title="Parte de los datos proviene del histórico cargado desde Excel"><i class="bi bi-database me-1" aria-hidden="true"></i>Datos: ${mixto ? 'Mixto' : 'Histórico Excel'}</span>`);

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
    const periodo = periodoConsulta(r);
    const meses = r.meses || [];
    const avisos = [...(r.avisos || []), ...(periodo.corte ? [`Los totales comparan hasta ${etiquetaMes(periodo.corte)}; los meses posteriores solo se muestran en la gráfica con su meta y año anterior.`] : [])];

    destruirGraficas();
    pintarContexto();
    pintarSiembras(r.opciones?.siembras);

    if (!hay) {
        $id('resultado_cuerpo').innerHTML = avisosHtml(avisos) + `<div class="card mb-3">${estadoVacio('bi-bar-chart', 'Sin datos para los filtros seleccionados', '<p class="text-muted mb-0">Ajuste el periodo, la finca o el año de siembra.</p>')}</div>`;
        return;
    }

    const conMeses = (r.meses || []).some((mes) => Number(mes.real) > 0 || Number(mes.meta) > 0);
    const conPpr = (r.meses || []).some((mes) => !nulo(mes.ppr));
    const conAnterior = fincas.some((finca) => Number(finca.real) > 0 || Number(finca.anterior) > 0);
    const conMetaFincas = fincas.some((finca) => Number(finca.real) > 0 || Number(finca.meta) > 0);
    const participan = fincas.filter((finca) => Number(finca.real) > 0);
    const resumen = `Real ${f('ton', kpis.real, ' t')} de Meta ${f('ton', kpis.meta, ' t')}`;

    let html = avisosHtml(avisos) + pintarKpis(kpis, periodo) + `<div class="row g-3 mb-3">
        ${tarjeta('col-12 col-xl-8', 'bi-bar-chart-line', 'Producción mensual vs presupuesto', porFinca ? 't · t/ha' : 't', conMeses ? lienzo('grafica_mensual', `Producción mensual ${periodo.texto}: ${resumen}`) : vacioGrafica())}
        ${tarjeta('col-12 col-xl-4', 'bi-speedometer2', 'Cumplimiento del periodo', '%', conMeta
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

    crear('sparkMeta', 'spark_meta', opcionesSpark([{ name: 'Real', data: serie(meses, 'real') }], [COLOR.real], meses));
    crear('sparkAnterior', 'spark_anterior', opcionesSpark([{ name: 'Real', data: serie(meses, 'real') }, { name: 'Año anterior', data: serie(meses, 'anterior') }], [COLOR.real, COLOR.anterior], meses));
    if (conMeses) crear('mensual', 'grafica_mensual', opcionesMensual(r, porFinca, periodo.conReal));
    if (conMeta) {
        estado.graficas.gauge = echarts.init($id('grafica_gauge'));
        estado.graficas.gauge.setOption(opcionesGauge(kpis.cumplimiento));
    }
    if (porFinca && conPpr) crear('ppr', 'grafica_ppr', opcionesPpr(r));
    if (!porFinca && conAnterior) crear('anterior', 'grafica_anterior', opcionesFincas(fincas, 'Año anterior', 'anterior', [COLOR.real, COLOR.anterior]));
    if (!porFinca && conMetaFincas) crear('meta', 'grafica_meta', opcionesFincas(fincas, 'Meta', 'meta', [COLOR.real, COLOR.meta]));
    if (!porFinca && participan.length) crear('participacion', 'grafica_participacion', opcionesDonut(participan, kpis.real));
};

const mezclar = (desde, hasta, t) => '#' + [1, 3, 5].map((i) => Math.round(parseInt(desde.substr(i, 2), 16) * (1 - t) + parseInt(hasta.substr(i, 2), 16) * t).toString(16).padStart(2, '0')).join('');

const leyendaFuentes = (anios) => [['verde', 'Báscula del sistema'], ['gris', 'Histórico Excel'], ...(anios.some((anio) => anio.fuente === 'mixto') ? [['ambar', 'Mixto']] : [])]
    .map(([clase, nombre]) => `<span class="gp-chip-fila ${clase} text-nowrap">${nombre}</span>`).join('');

const opcionesHistorico = (anios) => {
    const base = opcionesBase(alto());
    const grises = Math.max(anios.length - 2, 0);

    return {
        ...base,
        chart: { ...base.chart, type: 'line' },
        series: anios.map((anio) => ({ name: String(anio.anio), data: anio.meses.map((valor) => (valor === null ? null : valor / 1000)) })),
        colors: anios.map((_, i) => (i === 0 ? COLOR.real : (i === 1 ? COLOR.anterior : mezclar('#8795a4', '#d3dae1', grises > 1 ? (i - 2) / (grises - 1) : 0)))),
        stroke: { width: anios.map((_, i) => [3.5, 2.5][i] ?? 1.5), curve: 'straight' },
        markers: { size: anios.map((_, i) => (i === 0 ? 4 : 0)), strokeColors: '#fff', strokeWidth: 2 },
        legend: { ...base.legend, formatter: (nombre) => escaparHtml(nombre), onItemClick: { toggleDataSeries: true }, onItemHover: { highlightDataSeries: true } },
        xaxis: { ...base.xaxis, categories: MESES },
        tooltip: { shared: true, intersect: false, y: { formatter: (valor, { seriesIndex }) => f('ton', valor, ' t') + (FUENTE[anios[seriesIndex]?.fuente]?.[2] || '') } }
    };
};

const opcionesTonHaAnual = (anios, meta, parcial) => {
    const base = opcionesBase(alto());

    return {
        ...base,
        chart: { ...base.chart, type: 'bar' },
        series: [{ name: 't/ha', data: serie(anios, 'tonHa') }],
        colors: [({ dataPointIndex }) => (anios[dataPointIndex]?.anio === parcial ? 'rgba(31, 95, 139, .55)' : COLOR.tonHa)],
        plotOptions: { bar: { ...base.plotOptions.bar, dataLabels: { position: 'top' } } },
        dataLabels: { enabled: !movil(), offsetY: -18, formatter: (valor) => f('tonHa', valor), style: { fontSize: '11px', fontWeight: 600, colors: ['#3a4b5c'] } },
        legend: { show: false },
        xaxis: { ...base.xaxis, categories: anios.map((anio) => (anio.anio === parcial ? `${anio.anio}*` : String(anio.anio))), labels: { ...base.xaxis.labels, rotate: 0, hideOverlappingLabels: true } },
        yaxis: { ...base.yaxis, max: (maximo) => Math.max(maximo, meta || 0) * 1.15, labels: { ...base.yaxis.labels, formatter: (valor) => f('tonHa', valor) } },
        annotations: { yaxis: meta ? [{ y: meta, borderColor: COLOR.metaTonHa, strokeDashArray: 6, label: { text: `Meta ${f('tonHa', meta)} t/ha`, borderColor: COLOR.metaTonHa, style: { color: '#fff', background: COLOR.metaTonHa, fontSize: '11px' } } }] : [] },
        tooltip: { y: { formatter: (valor) => f('tonHa', valor, ' t/ha') } }
    };
};

const tablaHistorico = (anios, reciente, parcial, ultimoMes, periodo) => {
    const todos = anios.flatMap((anio) => anio.meses).filter((valor) => valor !== null).sort((a, b) => a - b);
    const umbrales = [0.2, 0.4, 0.6, 0.8].map((p) => todos[Math.floor(p * (todos.length - 1))]);
    const celda = (valor) => (valor === null ? `<td class="inf-num">${NULO}</td>` : `<td class="inf-num tb-calor-${1 + umbrales.filter((umbral) => valor > umbral).length}">${f('kg', valor)}</td>`);
    const filas = anios.map((anio) => {
        const actual = anio.anio === reciente;
        const esParcial = anio.anio === parcial;
        const fuente = FUENTE[anio.fuente];

        return `<tr>
            <th scope="row" class="tb-col-anio${actual ? ' tb-anio-actual fw-semibold' : ' fw-normal'}">${anio.anio}${esParcial ? `<span class="gp-subtexto d-block text-nowrap">Parcial a ${escaparHtml(MESES[ultimoMes])}</span>` : ''}${fuente ? `<span class="d-block mt-1"><span class="gp-chip-fila ${fuente[0]}">${fuente[1]}</span></span>` : ''}</th>
            ${anio.meses.map(celda).join('')}
            <td class="inf-num fw-semibold tb-col-total"${esParcial ? ' title="parcial"' : ''}>${nulo(anio.total) ? NULO : f('kg', anio.total)}</td>
        </tr>`;
    }).join('');

    return `<div class="row g-3 mb-3">${tarjeta('col-12', 'bi-table', 'Producción histórica mensual', `<span class="d-inline-flex flex-wrap align-items-center gap-1">${leyendaFuentes(anios)}<span class="ms-1">· kg</span></span>`, `<div class="table-responsive ig-ciclo-scroll tb-tabla-historico">
        <table class="table table-sm align-middle mb-0 gp-tabla inf-tabla">
            <caption class="visually-hidden">${escaparHtml(`Producción histórica mensual en kg por año, ${periodo}. Finca: ${estado.textos.finca}`)}</caption>
            <thead class="gp-thead">
                <tr><th scope="col" class="tb-col-anio">Año</th>${MESES.map((mes) => `<th scope="col" class="text-end">${escaparHtml(mes)}</th>`).join('')}<th scope="col" class="text-end tb-col-total">Total</th></tr>
            </thead>
            <tbody>${filas}</tbody>
        </table>
    </div>`)}</div>`;
};

const pintarHistorico = () => {
    const r = estado.respuesta;
    const avisos = r.avisos || [];
    const sinTabla = avisos.some((aviso) => texto(aviso).includes(SCRIPT_HISTORICO));
    const anios = (r.anios || []).map((anio) => ({ ...anio, anio: Number(anio.anio), meses: Array.from({ length: 12 }, (_, i) => (nulo(anio.meses?.[i]) ? null : Number(anio.meses[i]))) })).sort((a, b) => b.anio - a.anio);
    const conLineas = anios.filter((anio) => anio.meses.some((valor) => valor !== null));

    destruirGraficas();
    pintarContexto();

    if (!conLineas.length) {
        $id('resultado_cuerpo').innerHTML = sinTabla
            ? avisosHtml(avisos.filter((aviso) => !texto(aviso).includes(SCRIPT_HISTORICO))) + `<div class="card mb-3">${estadoVacio('bi-database-exclamation', 'Histórico no disponible', `<p class="text-muted mb-0">La tabla de producción histórica aún no está creada. Solicite al administrador ejecutar <code>Documentacion/BaseDeDatos/${SCRIPT_HISTORICO}</code> y <code>Documentacion/BaseDeDatos/cargar_produccion_historica.sql</code>.</p>`)}</div>`
            : avisosHtml(avisos) + `<div class="card mb-3">${estadoVacio('bi-bar-chart', 'Sin histórico para los filtros seleccionados', '<p class="text-muted mb-0">Ajuste la finca o el año de siembra.</p>')}</div>`;
        return;
    }

    const actual = conLineas[0];
    const ultimoMes = actual.meses.findLastIndex((valor) => valor !== null);
    const parcial = ultimoMes < 11 ? actual.anio : null;
    const ton = (anio) => (nulo(anio.total) ? anio.meses.reduce((suma, valor) => suma + (valor || 0), 0) : Number(anio.total)) / 1000;
    const completos = conLineas.filter((anio) => anio.anio !== parcial);
    const mejor = (completos.length ? completos : conLineas).reduce((a, b) => (ton(b) > ton(a) ? b : a));
    const meta = Number(r.metaTonHa) > 0 ? Number(r.metaTonHa) : null;
    const asc = [...anios].reverse();
    const conTonHa = asc.some((anio) => !nulo(anio.tonHa));
    const periodo = `${texto(r.desde)}–${texto(r.hasta)}`;

    $id('resultado_cuerpo').innerHTML = avisosHtml(avisos) + `<div class="row g-3 mb-3">
        ${tarjeta('col-12 col-xl-8', 'bi-graph-up', 'Producción mensual por año', 't', `${lienzo('grafica_historico', `Producción mensual ${periodo}. Año actual ${f('ton', ton(actual), ' t')} a ${MESES[ultimoMes]}; mejor año ${mejor.anio} con ${f('ton', ton(mejor), ' t')}`)}`)}
        ${tarjeta('col-12 col-xl-4', 'bi-bar-chart', 'Toneladas por hectárea anual', 't/ha', conTonHa
            ? lienzo('grafica_tonha_anual', `t/ha anual por año; meta ${meta ? f('tonHa', meta) : 'sin meta'}; año actual ${f('tonHa', actual.tonHa)}${parcial ? ' parcial' : ''}`) + (parcial ? `<div class="gp-subtexto mt-2">* Parcial a ${escaparHtml(MESES[ultimoMes])}</div>` : '')
            : vacioGrafica('t/ha histórico no disponible para el filtro seleccionado'))}
    </div>${tablaHistorico(anios, actual.anio, parcial, ultimoMes, periodo)}`;

    crear('historico', 'grafica_historico', opcionesHistorico(conLineas));
    if (conTonHa) crear('tonHaAnual', 'grafica_tonha_anual', opcionesTonHaAnual(asc, meta, parcial));
};

const claveMeta = (filtros) => [filtros.empresa, filtros.finca, filtros.siembra].join('|');

const guardarMeta = (r, filtros) => (estado.meta = { clave: claveMeta(filtros), valor: r?.kpis?.metaTonHa });

const metaHistorico = (filtros) => (estado.meta?.clave === claveMeta(filtros)
    ? Promise.resolve(estado.meta)
    : pedir('produccion/datos', { ...filtros, vista: '' }).then((r) => guardarMeta(r, filtros)).catch(() => null));

const consultar = async () => {
    if (errorRango()) return revisarVigencia();

    const turno = ++estado.turno;
    const filtros = valores();

    destruirGraficas();
    estado.filtros = filtros;
    estado.textos = { empresa: textoSeleccion('filtro_empresa'), finca: filtros.finca ? textoSeleccion('filtro_finca') : 'Todas las fincas' };
    ocupado(true);
    revisarVigencia();
    $id('chips_contexto').innerHTML = '';
    $id('resultado_cuerpo').innerHTML = skeleton();

    let respuesta;
    let meta = null;

    try {
        if (filtros.vista === 'historico') {
            [respuesta, meta] = await Promise.all([pedir('produccion/historico', { empresa: filtros.empresa, finca: filtros.finca, siembra: filtros.siembra }), metaHistorico(filtros)]);
        } else {
            respuesta = await pedir('produccion/datos', filtros);
        }
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

    if (filtros.vista === 'historico') {
        if (nulo(respuesta.metaTonHa)) respuesta.metaTonHa = meta?.valor;
        pintarHistorico();
        return;
    }

    guardarMeta(respuesta, filtros);
    pintar();
};

const drill = (codigo) => {
    if (codigo === undefined || codigo === null || (codigo === '' && !estado.filtros?.finca)) return;

    $('#filtro_finca').val(String(codigo)).trigger('change');
    consultar();
};

const opcionesPeriodo = (inicio, fin) => {
    let html = '';

    for (let indice = fin; indice >= inicio; indice--) {
        const periodo = periodoDe(indice);

        if (indice === fin || indice % 12 === 11) html += `${indice === fin ? '' : '</optgroup>'}<optgroup label="${periodo.slice(0, 4)}">`;
        html += `<option value="${periodo}">${escaparHtml(etiquetaMes(periodo))}</option>`;
    }

    return html ? `${html}</optgroup>` : '';
};

const pintarPeriodos = (minimo, maximo, desdeMaximo, desde, hasta) => {
    const inicio = indiceMes(minimo || desde);

    $id('filtro_desde').innerHTML = opcionesPeriodo(inicio, indiceMes(desdeMaximo || desde));
    $id('filtro_hasta').innerHTML = opcionesPeriodo(inicio, indiceMes(maximo || hasta));
    $id('filtro_desde').value = texto(desde);
    $id('filtro_hasta').value = texto(hasta);
};

const cargarEmpresa = async () => {
    try {
        const resultado = await pedir('tableros/fincas', { tablero: TABLERO.tablero, empresa: $id('filtro_empresa').value });

        pintarPeriodos(resultado.periodo_min, resultado.periodo_max, resultado.desde_max, resultado.desde_sel, resultado.hasta_sel);
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

['filtro_desde', 'filtro_hasta'].forEach((id) => $id(id).addEventListener('change', revisarVigencia));
$id('filtro_empresa').addEventListener('change', cargarEmpresa);

document.querySelectorAll('input[name="vista"]').forEach((radio) => radio.addEventListener('change', () => {
    const historico = radio.value === 'historico';

    ['filtro_desde', 'filtro_hasta'].forEach((id) => { $id(id).disabled = historico; });
    mostrar($id('nota_vista'), historico);
    consultar();
}));

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

pintarPeriodos(TABLERO.periodoMin, TABLERO.periodoMax, TABLERO.desdeMax, TABLERO.desde, TABLERO.hasta);
consultar();
