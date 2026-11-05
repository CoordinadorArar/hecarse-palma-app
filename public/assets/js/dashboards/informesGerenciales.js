const URL_IG = BASE_URL + 'dashboards/informes-gerenciales/';
const MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const SIN_PRESUPUESTO = 'Sin presupuesto definido para este nivel';
const COLOR_SEMAFORO = { verde: '#2f7a4b', ambar: '#e0a800', rojo: '#dc3545' };
const CLASE_KPI = { verde: 'ok', ambar: 'alerta', rojo: 'critico' };

const $id = (id) => document.getElementById(id);
const texto = (valor) => String(valor ?? '').trim();
const mostrar = (elemento, visible) => elemento?.classList.toggle('d-none', !visible);
const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const FORMATOS = {
    kg: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }),
    ton: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    kgSigno: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0, signDisplay: 'exceptZero' }),
    tonSigno: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1, signDisplay: 'exceptZero' }),
    porcentaje: new Intl.NumberFormat('es-CO', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    compacto: new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 }),
    ha: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    ha1: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    fecha: new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' })
};

const ESTADOS_CICLO = {
    seguimiento: { nombre: 'En seguimiento', rango: '< 10 días', relleno: '#2f6fb0', texto: '#fff' },
    normal: { nombre: 'Próximo a cosechar', rango: '10–12 días', relleno: '#24814a', texto: '#fff' },
    alerta: { nombre: 'Listo para cosechar', rango: '13–15 días', relleno: '#f0961e', texto: '#3b2600' },
    sobremaduracion: { nombre: 'Sobremaduración', rango: '> 15 días', relleno: '#c93c3c', texto: '#fff' },
    vencido: { nombre: 'Vencido', rango: 'Vencido', relleno: '#c93c3c', texto: '#fff' },
    proximo: { nombre: 'Próximo', rango: '0–45 días', relleno: '#f0961e', texto: '#3b2600' },
    intermedio: { nombre: 'Intermedio', rango: '46–90 días', relleno: '#f2cf3d', texto: '#3d3200' },
    'al-dia': { nombre: 'Al día', rango: '> 90 días', relleno: '#24814a', texto: '#fff' },
    'sin-dato': { nombre: 'Sin dato', rango: 'Sin registro', relleno: '#c5ccd3', texto: '#495057' }
};
const RATIO_MAPA = { cruceiro: [1000, 770], canaguate: [1000, 484] };
const ESTILO_SIN_LOTE = { areaColor: '#f1f3f5', decal: { symbol: 'rect', dashArrayX: [1, 0], dashArrayY: [2, 6], rotation: -Math.PI / 4, color: 'rgba(135,149,164,.18)' } };
const MAPAS_REGISTRADOS = {};

const estado = { informe: INFORMES_GERENCIALES.informes[0]?.clave || '', unidad: 'kg', turno: 0, respuesta: null, filtros: null, textos: null, indice: new Map(), abiertos: new Set(), nodo: null, graficas: {}, g3Abierto: false, filtroEstado: null, activo: null, hover: null, lotes: new Map(), regiones: new Set(), geo: null };

const esCiclo = () => estado.informe.startsWith('ciclos-');
const NOMBRES_CICLO = { 'ciclos-poda': 'Poda', 'ciclos-fertilizacion': 'Fertilización', 'ciclos-sanidad': 'Sanidad', 'ciclos-mantenimiento': 'Mantenimiento' };
const esIngreso = () => esCiclo() && estado.informe !== 'ciclos-cosecha';
const conLabores = () => ['ciclos-sanidad', 'ciclos-mantenimiento'].includes(estado.informe);
const nombreCiclo = () => NOMBRES_CICLO[estado.informe] || 'Cosecha';
const estadoCiclo = (clave) => ESTADOS_CICLO[clave] || ESTADOS_CICLO['sin-dato'];
const fecha = (valor) => {
    const [anio, mes, dia] = texto(valor).split('-').map(Number);

    return anio ? FORMATOS.fecha.format(new Date(anio, mes - 1, dia)) : '';
};
const NULO = '<span class="text-muted">—</span>';
const celdaFecha = (valor) => fecha(valor) || NULO;
const corto = (lote) => texto(lote.descripcion).match(/LOTE\s+(?:CA\S*\s+)?(?:N[oº°]\.?\s*)?(\d+[A-Z]?)\b/i)?.[1]?.toUpperCase()
    || texto(lote.codigo).match(/^[A-Z]+\s*-?\s*(\d+[A-Z]?)/i)?.[1]?.toUpperCase()
    || texto(lote.codigo);

const factor = () => (estado.unidad === 'ton' ? 1000 : 1);
const sufijo = () => (estado.unidad === 'ton' ? ' t' : ' kg');
const escalar = (valor) => (valor == null ? null : Number(valor) / factor());
const cifraEscalada = (valor, unidad = true) => (valor == null ? '—' : FORMATOS[estado.unidad].format(valor) + (unidad ? sufijo() : ''));
const cifra = (valor, unidad = false) => cifraEscalada(escalar(valor), unidad);
const diferencia = (valor, unidad = false) => (valor == null ? '—' : FORMATOS[`${estado.unidad}Signo`].format(Math.abs(valor) < 0.5 ? 0 : Number(valor) / factor()) + (unidad ? sufijo() : ''));
const porcentaje = (valor) => (valor == null ? '—' : FORMATOS.porcentaje.format(valor));
const semaforo = (valor) => (valor >= 1 ? 'verde' : (valor >= 0.9 ? 'ambar' : 'rojo'));
const conPresupuesto = (nodo) => nodo.presupuesto != null && Number(nodo.presupuesto) > 0;
const movil = () => window.innerWidth < 768;
const alto = () => (movil() ? 260 : 320);

const estadoVacio = (icono, titulo, cuerpo, extra = '') => `
    <div class="gp-estado-vacio text-center py-5">
        <div class="gp-medallon ${extra} d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi ${icono}" aria-hidden="true"></i></div>
        <h6 class="mb-1">${titulo}</h6>
        ${cuerpo}
    </div>`;

const pedir = async (ruta, datos, base = URL_IG) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;

    try {
        respuesta = await fetch(base + ruta, { method: 'POST', body: cuerpo, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    } catch (error) {
        throw Object.assign(new Error('Error de conexión.'), { status: 0 });
    }

    let resultado = null;

    try {
        resultado = await respuesta.json();
    } catch (error) {
        resultado = null;
    }

    if (respuesta.ok && Array.isArray(resultado)) return resultado;

    if (!respuesta.ok || resultado?.success !== true) {
        throw Object.assign(new Error(texto(resultado?.message) || (respuesta.status === 403 ? 'No tiene permiso para consultar este informe.' : 'No se pudo completar la operación.')), { status: respuesta.status });
    }

    return resultado;
};

const valores = () => (esCiclo()
    ? { empresa: $id('filtro_empresa').value, finca: $id('filtro_finca').value, corte: $id('filtro_fecha').value }
    : { empresa: $id('filtro_empresa').value, anio: $id('filtro_anio').value, mes: $id('filtro_mes').value, finca: $id('filtro_finca').value });

const fincaPreferida = (select) => Object.keys(INFORMES_GERENCIALES.mapas?.[$id('filtro_empresa').value] || {}).find((codigo) => [...select.options].some((opcion) => opcion.value === codigo)) || select.options[0]?.value || '';

const marcarFinca = (invalido) => $id('filtro_finca').classList.toggle('is-invalid', invalido);

const ajustarFiltros = () => {
    const ciclo = esCiclo();
    const select = $id('filtro_finca');
    const todas = select.querySelector('option[value=""]');

    document.querySelectorAll('.ig-filtro-ppto').forEach((columna) => mostrar(columna, !ciclo));
    document.querySelectorAll('.ig-filtro-ciclo').forEach((columna) => mostrar(columna, ciclo));

    if (ciclo && todas) {
        const vacia = !select.value;

        todas.remove();
        if (vacia) select.value = fincaPreferida(select);
    }
    if (!ciclo && !todas) {
        select.insertAdjacentHTML('afterbegin', '<option value="">Todas las fincas</option>');
        select.value = '';
    }

    if (!ciclo) marcarFinca(false);
    $(select).trigger('change.select2');
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

const skeleton = () => `<div class="placeholder-glow">
    <div class="row g-3 mb-3">${'<div class="col-12 col-sm-6 col-xl-4 col-xxl-2"><div class="inf-kpi d-block"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-8 placeholder-lg"></span></div></div>'.repeat(6)}</div>
    <div class="row g-3 mb-3">${'<div class="col-12 col-xl-8"><div class="card mb-0"><div class="card-body pt-3"><span class="placeholder w-100 rounded ig-esqueleto"></span></div></div></div><div class="col-12 col-xl-4"><div class="card mb-0"><div class="card-body pt-3"><span class="placeholder w-100 rounded ig-esqueleto"></span></div></div></div>'}</div>
</div>`;

const skeletonCiclo = () => `<div class="placeholder-glow">
    <div class="row g-3 mb-3">${'<div class="col-6 col-md-4 col-xxl-2"><div class="inf-kpi d-block"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-8 placeholder-lg"></span></div></div>'.repeat(6)}</div>
    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7"><div class="card mb-0"><div class="card-body pt-3"><span class="placeholder w-100 rounded ig-esqueleto-mapa"></span></div></div></div>
        <div class="col-12 col-xl-5"><div class="card mb-0"><div class="card-body pt-3">${'<span class="placeholder col-12 mb-2"></span>'.repeat(8)}</div></div></div>
    </div>
</div>`;

const vacioFinca = () => `<div class="card mb-3">${estadoVacio('bi-geo-alt', 'Seleccione una finca y presione Consultar', '<p class="text-muted mb-0">El ciclo se calcula a la fecha de corte.</p>')}</div>`;

const destruirGraficas = () => {
    Object.values(estado.graficas).forEach((grafica) => (grafica?.dispose ? grafica.dispose() : grafica?.destroy()));
    estado.graficas = {};
    const mapa = estado.geo?.mapa;

    if (mapa) requestAnimationFrame(() => mapa.off().remove());
    estado.geo = null;
};

const pintarContexto = () => {
    const corte = estado.respuesta?.corte || {};
    const chips = esCiclo() ? [
        `Corte: ${fecha(corte)}`,
        `Empresa: ${estado.textos.empresa}`,
        `Finca: ${estado.textos.finca}`,
        `Ciclo: ${nombreCiclo()}`
    ] : [
        `Corte: ${texto(corte.mesNombre)} ${texto(corte.anio)}`,
        `Empresa: ${estado.textos.empresa}`,
        `Finca: ${estado.textos.finca}`,
        `Unidad: ${estado.unidad === 'ton' ? 'Ton' : 'Kg'}`
    ];

    $id('chips_contexto').innerHTML = chips.map((chip) => `<span class="gp-chip-fila inf-chip-filtro">${escaparHtml(chip)}</span>`).join('');
};

const kpi = (icono, etiqueta, valor, sub, extra = '', clase = '') => `<div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
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
    const presupuesto = Number(kpis.presupuesto) > 0;
    const anual = Number(kpis.presupuestoAnual) > 0;
    const color = semaforo(Number(kpis.cumplimiento));
    const descuadre = Math.abs(Number(kpis.diferencia || 0)) >= 0.5;
    const falta = Number(kpis.faltante);

    return `<div class="row g-3 mb-3">
        ${kpi('bi-flag', 'Presupuesto al corte', escaparHtml(cifra(kpis.presupuesto, true)), escaparHtml(`Ene – ${texto(corte.mesNombre)}`))}
        ${kpi('bi-truck', 'Ejecutado facturado', escaparHtml(cifra(kpis.facturado, true)), 'Báscula')}
        ${kpi('bi-person-gear', 'Ejecutado labores', escaparHtml(cifra(kpis.labores, true)), 'Labores de cosecha')}
        ${presupuesto
            ? kpi('bi-speedometer2', 'Cumplimiento al corte', `<span class="ig-texto-${color}">${porcentaje(kpis.cumplimiento)}</span>`, '',
                `<div class="progress ig-progreso mt-1" role="progressbar" aria-valuenow="${Math.round(Number(kpis.cumplimiento) * 100)}" aria-valuemin="0" aria-valuemax="100" aria-label="Cumplimiento"><div class="progress-bar ig-barra-${color}" style="width:${Math.min(Number(kpis.cumplimiento) * 100, 100)}%"></div></div>`,
                `ig-kpi-${CLASE_KPI[color]}`)
            : kpi('bi-speedometer2', 'Cumplimiento al corte', '—', 'Sin presupuesto')}
        ${presupuesto && anual
            ? kpi('bi-hourglass-split', 'Faltante vs anual', escaparHtml(cifra(Math.max(falta, 0), true)), falta <= 0
                ? '<span class="ig-texto-verde fw-semibold">Meta anual cumplida</span>'
                : escaparHtml(`de ${cifra(kpis.presupuestoAnual, true)} presupuesto anual`))
            : kpi('bi-hourglass-split', 'Faltante vs anual', '—', 'Sin presupuesto')}
        ${kpi('bi-arrow-left-right', 'Diferencia fact − labores', escaparHtml(diferencia(kpis.diferencia, true)), descuadre
            ? '<span class="ig-texto-ambar fw-semibold">Revisar: báscula ≠ labores</span>'
            : '<span class="ig-chip verde">Cuadra</span>', '', descuadre ? 'ig-kpi-alerta' : '')}
    </div>`;
};

const opcionesBase = (altura) => ({
    chart: { height: altura, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { speed: 300 }, parentHeightOffset: 0 },
    dataLabels: { enabled: false },
    grid: { borderColor: '#eef1f5' },
    legend: { position: 'top', horizontalAlign: 'left' },
    tooltip: { shared: true, intersect: false },
    xaxis: { labels: { style: { colors: '#5c6b7a' } } }
});

const pctMensual = (mes) => (mes && mes.facturado != null && Number(mes.presupuesto) > 0 ? (Number(mes.facturado) / Number(mes.presupuesto)) * 100 : null);

const seriesMensuales = (nodo) => {
    const meses = nodo.meses || [];
    const campo = (clave) => MESES_CORTOS.map((_, indice) => escalar(meses[indice]?.[clave]));

    return [
        { name: 'Presupuesto', type: 'column', data: campo('presupuesto') },
        { name: 'Facturado', type: 'column', data: campo('facturado') },
        { name: 'Labores', type: 'column', data: campo('labores') },
        { name: '% Cumplimiento', type: 'line', data: MESES_CORTOS.map((_, indice) => pctMensual(meses[indice])) }
    ];
};

const seriesDiferencia = (nodo) => [{
    name: 'Diferencia',
    data: MESES_CORTOS.map((_, indice) => {
        const mes = nodo.meses?.[indice];

        return mes && mes.facturado != null && mes.labores != null ? escalar(Number(mes.facturado) - Number(mes.labores)) : null;
    })
}];

const anotacion = (x, etiqueta) => ({ x, borderColor: '#8795a4', strokeDashArray: 4, label: { text: etiqueta, borderColor: '#8795a4', orientation: 'horizontal', style: { color: '#fff', background: '#8795a4', fontSize: '11px' } } });

const opcionesG1 = () => {
    const base = opcionesBase(alto());
    const corte = Number(estado.respuesta.corte?.mes);
    const pct = (valor) => (valor == null ? '—' : FORMATOS.porcentaje.format(valor / 100));

    return {
        ...base,
        chart: { ...base.chart, type: 'line' },
        series: seriesMensuales(estado.nodo),
        colors: ['#b6c0cb', '#2f7a4b', '#41a867', '#1f5f8b'],
        stroke: { width: [0, 0, 0, 3], curve: 'straight' },
        markers: { size: [0, 0, 0, 4], strokeColors: '#fff', strokeWidth: 2 },
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 3, borderRadiusApplication: 'end' } },
        legend: { position: movil() ? 'bottom' : 'top', horizontalAlign: 'left' },
        xaxis: { ...base.xaxis, categories: MESES_CORTOS },
        yaxis: [
            { seriesName: 'Presupuesto', min: 0, labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => FORMATOS.compacto.format(valor) } },
            { seriesName: 'Presupuesto', show: false },
            { seriesName: 'Presupuesto', show: false },
            { seriesName: '% Cumplimiento', opposite: true, min: 0, labels: { style: { colors: '#1f5f8b' }, formatter: pct } }
        ],
        annotations: { xaxis: corte >= 1 && corte <= 12 ? [anotacion(MESES_CORTOS[corte - 1], 'Corte')] : [] },
        tooltip: { shared: true, intersect: false, y: { formatter: (valor, { seriesIndex }) => (seriesIndex === 3 ? pct(valor) : cifraEscalada(valor)) } }
    };
};

const fincasCumplimiento = () => [...estado.indice.values()]
    .filter((nodo) => nodo.nivel === 'finca' && conPresupuesto(nodo) && nodo.cumplimiento != null)
    .sort((a, b) => Number(a.cumplimiento) - Number(b.cumplimiento));

const opcionesG2 = (fincas) => {
    const base = opcionesBase(fincas.length > 12 ? fincas.length * 28 + 60 : alto());

    return {
        ...base,
        chart: { ...base.chart, type: 'bar', events: { dataPointSelection: (evento, contexto, { dataPointIndex }) => seleccionarFinca(fincas[dataPointIndex]) } },
        series: [{ name: '% Cumplimiento', data: fincas.map((nodo) => Math.round(Number(nodo.cumplimiento) * 1000) / 10) }],
        colors: fincas.map((nodo) => COLOR_SEMAFORO[semaforo(Number(nodo.cumplimiento))]),
        plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '70%', borderRadius: 3, borderRadiusApplication: 'end' } },
        legend: { show: false },
        states: { active: { filter: { type: 'none' } } },
        xaxis: { ...base.xaxis, categories: fincas.map((nodo) => texto(nodo.nombre) || texto(nodo.codigo)), min: 0, labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => `${FORMATOS.compacto.format(valor)} %` } },
        yaxis: { labels: { maxWidth: 140, style: { colors: '#5c6b7a' } } },
        annotations: { xaxis: [anotacion(100, 'Meta')] },
        tooltip: { shared: false, intersect: false, y: { title: { formatter: () => 'Cumplimiento' }, formatter: (valor) => FORMATOS.porcentaje.format(valor / 100) } }
    };
};

const opcionesG3 = () => {
    const base = opcionesBase(movil() ? 240 : 280);

    return {
        ...base,
        chart: { ...base.chart, type: 'bar' },
        series: seriesDiferencia(estado.nodo),
        colors: ['#2f7a4b'],
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 3, colors: { ranges: [{ from: -1e15, to: -1e-9, color: '#dc3545' }] } } },
        legend: { show: false },
        xaxis: { ...base.xaxis, categories: MESES_CORTOS },
        yaxis: { labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => FORMATOS.compacto.format(valor) } },
        annotations: { yaxis: [{ y: 0, borderColor: '#8795a4' }] },
        tooltip: { shared: true, intersect: false, y: { formatter: (valor) => (valor == null ? '—' : FORMATOS[`${estado.unidad}Signo`].format(valor) + sufijo()) } }
    };
};

const pintarGraficas = () => `<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100 mb-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
                <h6 class="gp-seccion mb-0 me-auto"><i class="bi bi-bar-chart-line" aria-hidden="true"></i>Presupuesto vs ejecutado mensual</h6>
                <span class="gp-chip-fila inf-chip-filtro ig-chip-nodo" id="chip_nodo"></span>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none d-none" id="btn_ver_total"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Ver total</button>
            </div>
            <div class="card-body pt-2"><div class="inf-grafica" id="grafica_mensual"></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header bg-white py-3"><h6 class="gp-seccion mb-0"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i>Cumplimiento acumulado por finca</h6></div>
            <div class="card-body pt-2${fincasCumplimiento().length > 12 ? ' inf-grafica-scroll' : ''}" id="contenedor_fincas"><div class="inf-grafica" id="grafica_fincas"></div></div>
        </div>
    </div>
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header bg-white py-3">
                <h6 class="gp-seccion gp-seccion-toggle mb-0${estado.g3Abierto ? '' : ' collapsed'}" role="button" data-bs-toggle="collapse" data-bs-target="#colapso_diferencia" aria-expanded="${estado.g3Abierto}" aria-controls="colapso_diferencia">
                    <i class="bi bi-plus-slash-minus" aria-hidden="true"></i>Diferencia facturado vs labores por mes<i class="bi bi-chevron-down gp-chevron ms-auto" aria-hidden="true"></i>
                </h6>
            </div>
            <div class="collapse${estado.g3Abierto ? ' show' : ''}" id="colapso_diferencia"><div class="card-body pt-2"><div class="inf-grafica" id="grafica_diferencia"></div></div></div>
        </div>
    </div>
</div>`;

const crearG3 = () => {
    if (estado.graficas.g3 || !$id('grafica_diferencia')) return;

    estado.graficas.g3 = new ApexCharts($id('grafica_diferencia'), opcionesG3());
    estado.graficas.g3.render();
};

const actualizarNodo = () => {
    const raiz = estado.nodo._k === '';

    $id('chip_nodo').textContent = `Mostrando: ${texto(estado.nodo.nombre)}`;
    mostrar($id('btn_ver_total'), !raiz);
    document.querySelectorAll('#tabla_arbol tr.ig-activa').forEach((fila) => fila.classList.remove('ig-activa'));
    if (!raiz) document.querySelector(`#tabla_arbol tr[data-k="${estado.nodo._k}"]`)?.classList.add('ig-activa');
};

const seleccionarNodo = (nodo) => {
    estado.nodo = nodo;
    actualizarNodo();
    estado.graficas.g1?.updateSeries(seriesMensuales(nodo));
    estado.graficas.g3?.updateSeries(seriesDiferencia(nodo));
};

const seleccionarFinca = (nodo) => {
    if (!nodo) return;

    let clave = nodo._padre;

    while (clave) {
        estado.abiertos.add(clave);
        clave = estado.indice.get(clave)?._padre;
    }

    if ((nodo.hijos || []).length) estado.abiertos.add(nodo._k);
    pintarFilas();
    seleccionarNodo(nodo);
    document.querySelector(`#tabla_arbol tr[data-k="${nodo._k}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const guion = () => `<span class="text-muted" title="${SIN_PRESUPUESTO}">—</span>`;

const celdaDiferencia = (valor) => {
    if (valor == null) return '<span class="text-muted">—</span>';
    if (Math.abs(Number(valor)) < 0.5) return '<span class="text-muted"><i class="bi bi-check2 text-success me-1" aria-hidden="true"></i>0</span>';

    return `<span class="ig-descuadre" title="Facturado y labores no coinciden"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>${diferencia(valor)}</span>`;
};

const celdaCumplimiento = (nodo) => {
    if (nodo.presupuesto == null) return guion();
    if (!conPresupuesto(nodo) || nodo.cumplimiento == null) return '<span class="text-muted">—</span>';

    return `<span class="ig-chip ig-chip-pct ${semaforo(Number(nodo.cumplimiento))}">${porcentaje(nodo.cumplimiento)}</span>`;
};

const celdaFaltante = (nodo) => {
    if (nodo.faltante == null) return guion();

    return Number(nodo.faltante) > 0 ? cifra(nodo.faltante) : '<span class="ig-chip verde">Cumplido</span>';
};

const fila = (nodo) => {
    const nombre = texto(nodo.nombre) || texto(nodo.codigo);
    const abierto = estado.abiertos.has(nodo._k);
    const seleccionable = ['empresa', 'finca'].includes(nodo.nivel);
    const titulo = nodo.nivel === 'lote' && texto(nodo.codigo) ? `${texto(nodo.codigo)} — ${nombre}` : nombre;
    const etiqueta = nodo.nivel === 'lote' && texto(nodo.codigo)
        ? `<span class="font-monospace small text-muted">${escaparHtml(nodo.codigo)}</span> — ${escaparHtml(nombre)}`
        : escaparHtml(nombre);
    const toggle = (nodo.hijos || []).length
        ? `<button type="button" class="btn btn-link btn-sm p-0 me-1 ig-toggle" data-toggle="${nodo._k}" aria-expanded="${abierto}" aria-label="${abierto ? 'Contraer' : 'Expandir'} ${escaparHtml(nombre)}"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>`
        : '<span class="ig-toggle-espacio me-1" aria-hidden="true"></span>';

    return `<tr class="ig-${nodo.nivel}${estado.nodo?._k === nodo._k ? ' ig-activa' : ''}" data-k="${nodo._k}">
        <td class="ig-col-nombre"><div class="d-flex align-items-center min-w-0">${toggle}<span class="ig-nombre text-truncate${seleccionable ? ' ig-seleccionable' : ''}" title="${escaparHtml(titulo)}"${seleccionable ? ` data-nodo="${nodo._k}" role="button" tabindex="0"` : ''}>${etiqueta}</span></div></td>
        <td class="inf-num">${nodo.presupuesto == null ? guion() : cifra(nodo.presupuesto)}</td>
        <td class="inf-num">${cifra(nodo.facturado)}</td>
        <td class="inf-num d-none d-sm-table-cell">${cifra(nodo.labores)}</td>
        <td class="inf-num">${celdaDiferencia(nodo.diferencia)}</td>
        <td class="text-center">${celdaCumplimiento(nodo)}</td>
        <td class="inf-num d-none d-sm-table-cell">${celdaFaltante(nodo)}</td>
    </tr>`;
};

const filas = (nodos) => nodos.map((nodo) => fila(nodo) + (estado.abiertos.has(nodo._k) ? filas(nodo.hijos || []) : '')).join('');

const pintarFilas = () => {
    $id('tabla_arbol').tBodies[0].innerHTML = filas(estado.respuesta.arbol);
};

const pintarTabla = (kpis) => {
    const unidad = estado.unidad === 'ton' ? 't' : 'kg';

    return `<div class="card mb-3">
        <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
            <h6 class="gp-seccion mb-0 me-auto"><i class="bi bi-diagram-3" aria-hidden="true"></i>Detalle por compañía, finca y lote</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-accion="expandir"><i class="bi bi-arrows-expand me-1" aria-hidden="true"></i>Expandir todo</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-accion="contraer"><i class="bi bi-arrows-collapse me-1" aria-hidden="true"></i>Contraer todo</button>
            ${botonPlanear('planear-cosecha', 'Planear cosecha')}
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 gp-tabla ig-arbol" id="tabla_arbol">
                    <thead class="gp-thead">
                        <tr>
                            <th class="ig-col-nombre">Nombre</th>
                            <th class="text-end">Presupuesto (${unidad})</th>
                            <th class="text-end">Ejecutado facturado (${unidad})</th>
                            <th class="text-end d-none d-sm-table-cell">Ejecutado labores (${unidad})</th>
                            <th class="text-end" title="Facturado − labores">Diferencia (${unidad})</th>
                            <th class="text-center">% Cumpl. al corte</th>
                            <th class="text-end d-none d-sm-table-cell">Faltante anual (${unidad})</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <th class="ig-col-nombre">Total general</th>
                            <th class="inf-num">${cifra(kpis.presupuesto)}</th>
                            <th class="inf-num">${cifra(kpis.facturado)}</th>
                            <th class="inf-num d-none d-sm-table-cell">${cifra(kpis.labores)}</th>
                            <th class="inf-num">${celdaDiferencia(kpis.diferencia)}</th>
                            <th class="text-center">${conPresupuesto(kpis) ? `<span class="ig-chip ig-chip-pct ${semaforo(Number(kpis.cumplimiento))}">${porcentaje(kpis.cumplimiento)}</span>` : '<span class="text-muted">—</span>'}</th>
                            <th class="inf-num d-none d-sm-table-cell">${celdaFaltante(kpis)}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>`;
};

const indexar = (nodos, padre = '') => nodos.forEach((nodo, indice) => {
    nodo._k = `${padre}${indice}.`;
    nodo._padre = padre;
    estado.indice.set(nodo._k, nodo);
    indexar(nodo.hijos || [], nodo._k);
});

const hectareas = (lotes) => lotes.reduce((suma, lote) => suma + Number(lote.hectareas || 0), 0);

const ordenarLotes = (lotes) => lotes.sort((a, b) => {
    const sinA = a.estado === 'sin-dato';
    const sinB = b.estado === 'sin-dato';

    if (sinA || sinB) return (sinA - sinB) || texto(a.codigo).localeCompare(texto(b.codigo));

    return (esIngreso() ? a.diasParaIngreso - b.diasParaIngreso : b.diasTranscurridos - a.diasTranscurridos) || texto(a.codigo).localeCompare(texto(b.codigo));
});

const rangoCiclo = (clave) => {
    const rango = (estado.respuesta?.rangos || []).find((item) => item.clave === clave);

    if (!esIngreso() || !rango || clave === 'vencido') return ESTADOS_CICLO[clave].rango;
    if (clave === 'sin-dato') return 'Sin registro';
    if (rango.desde != null && rango.hasta != null) return `${rango.desde}–${rango.hasta} días`;

    return rango.desde != null ? `> ${rango.desde - 1} días` : ESTADOS_CICLO[clave].rango;
};

const kpiCiclo = (clave, etiqueta, valor, sub, marca, inactivo = false) => `<div class="col-6 col-md-4 col-xxl-2">
    <button type="button" class="inf-kpi ig-ciclo-kpi ig-e-${clave}" ${clave === 'todos' ? '' : `aria-pressed="${estado.filtroEstado === clave}"`} data-estado="${clave}"${inactivo ? ' disabled' : ''}>
        ${marca}
        <span class="min-w-0 flex-grow-1">
            <span class="gp-etiqueta-mini d-block">${etiqueta}</span>
            <span class="inf-kpi-valor d-block">${valor}</span>
            <span class="gp-subtexto">${sub}</span>
        </span>
    </button>
</div>`;

const pintarKpisCiclo = (claves) => {
    const lotes = estado.respuesta.lotes;
    const kpis = estado.respuesta.kpis || {};

    return `<div class="row g-3 mb-3">
        ${kpiCiclo('todos', 'Lotes evaluados', FORMATOS.kg.format(lotes.length), `${FORMATOS.ha1.format(kpis.hectareas ?? hectareas(lotes))} ha`, '<span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></span>')}
        ${claves.map((clave) => {
            const grupo = lotes.filter((lote) => lote.estado === clave);

            return kpiCiclo(clave, ESTADOS_CICLO[clave].nombre, FORMATOS.kg.format(grupo.length), escaparHtml(`${rangoCiclo(clave) !== ESTADOS_CICLO[clave].nombre ? `${rangoCiclo(clave)} · ` : ''}${FORMATOS.ha1.format(hectareas(grupo))} ha`), '<span class="ig-leyenda-punto" aria-hidden="true"></span>', clave === 'sin-dato' && !grupo.length);
        }).join('')}
    </div>`;
};

const leyendaMapa = (claves, sinLote = 'sin-lote') => `<div class="ig-leyenda">
    ${claves.map((clave) => `<span class="d-inline-flex align-items-center gap-1 ig-e-${clave}"><span class="ig-leyenda-punto" aria-hidden="true"></span>${escaparHtml(ESTADOS_CICLO[clave].nombre)}${rangoCiclo(clave) !== ESTADOS_CICLO[clave].nombre ? ` <span class="text-muted">${escaparHtml(rangoCiclo(clave))}</span>` : ''}</span>`).join('')}
    <span class="d-inline-flex align-items-center gap-1"><span class="ig-leyenda-punto ${sinLote}" aria-hidden="true"></span>Sin lote en sistema</span>
</div>`;

const pintarMapaCiclo = (claves, columna, ratio) => `<div class="${columna}">
    <div class="card h-100 mb-0">
        <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
            <h6 class="gp-seccion mb-0 me-auto"><i class="bi bi-map" aria-hidden="true"></i>Mapa · ${escaparHtml(estado.respuesta.finca?.descripcion || estado.textos.finca)}</h6>
            ${botonPlanear('planear-lote', 'Planear en lote', false, ' id="btn_planear_lote" hidden')}
            <button type="button" class="btn btn-sm btn-outline-secondary d-none d-md-inline-block" data-accion="restablecer" id="btn_restablecer"><i class="bi bi-arrows-angle-contract me-1" aria-hidden="true"></i>Restablecer vista</button>
        </div>
        <div class="card-body pt-2" id="cuerpo_mapa"><div class="ig-mapa" id="mapa_ciclo" style="--ig-mapa-ratio: ${ratio[0]}/${ratio[1]}"></div></div>
        <div class="card-footer bg-white border-0 pt-0" id="pie_mapa">${leyendaMapa(claves)}</div>
    </div>
</div>`;

const pintarMapaGeo = (claves) => {
    const finca = estado.respuesta.finca?.descripcion || estado.textos.finca;

    return `<div class="col-12 col-xxl-7">
    <div class="card h-100 mb-0">
        <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
            <h6 class="gp-seccion mb-0 me-auto"><i class="bi bi-map" aria-hidden="true"></i>Mapa · ${escaparHtml(finca)}</h6>
            <span class="d-inline-flex align-items-center gap-1 small text-muted d-none" id="geo_cargando"><span class="spinner-border spinner-border-sm text-success" aria-hidden="true"></span>Cargando palmas…</span>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="form-check form-switch mb-0 small"><input class="form-check-input" type="checkbox" role="switch" id="ig_geo_palmas" checked><label class="form-check-label" for="ig_geo_palmas">Mostrar palmas</label></div>
                <span class="small text-muted d-none d-lg-inline">Palmas:</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Color de las palmas">
                    <input type="radio" class="btn-check" name="ig_modo_palmas" id="ig_modo_informe" value="informe"${estado.modoPalmas === 'sanitario' ? '' : ' checked'}>
                    <label class="btn btn-outline-success" for="ig_modo_informe"><span class="d-none d-sm-inline">Estado del informe</span><span class="d-sm-none">Informe</span></label>
                    <input type="radio" class="btn-check" name="ig_modo_palmas" id="ig_modo_sanitario" value="sanitario"${estado.modoPalmas === 'sanitario' ? ' checked' : ''}>
                    <label class="btn btn-outline-success" for="ig_modo_sanitario"><span class="d-none d-sm-inline">Estado sanitario</span><span class="d-sm-none">Sanitario</span></label>
                </div>
            </div>
            ${botonPlanear('planear-lote', 'Planear en lote', false, ' id="btn_planear_lote" hidden')}
            <button type="button" class="btn btn-sm btn-outline-secondary" data-accion="restablecer" id="btn_restablecer" title="Restablecer vista" aria-label="Restablecer vista"><i class="bi bi-arrows-angle-contract" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Restablecer vista</span></button>
        </div>
        <div class="card-body p-0 overflow-hidden" id="cuerpo_mapa"><div class="ig-geo-mapa" id="mapa_ciclo" role="region" tabindex="0" aria-label="${escaparHtml(`Mapa satelital de la finca ${finca}`)}"></div></div>
        <div class="card-footer bg-white border-0" id="pie_mapa">${leyendaMapa(claves, 'ig-geo-sin-lote')}<div id="geo_palmas"></div></div>
        <div class="visually-hidden" aria-live="polite" id="geo_anuncio"></div>
    </div>
</div>`;
};

const claseColumna = (normal) => `${normal}${estado.conGeo ? ' d-xxl-none' : ''}`;

const pintarTablaCiclo = () => `<div class="card h-100 mb-0">
    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
        <h6 class="gp-seccion mb-0 me-auto"><i class="bi bi-list-ul" aria-hidden="true"></i>Lotes · ${nombreCiclo()}</h6>
        <span class="gp-chip-fila inf-chip-filtro ig-chip-nodo d-none" id="chip_estado"></span>
        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none d-none" id="btn_ver_todos"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Ver todos</button>
        ${botonPlanear('planear', 'Planear actividad', true)}
    </div>
    <div class="card-body p-0">
        <div class="table-responsive ig-ciclo-scroll">
            <table class="table table-hover align-middle mb-0 gp-tabla ig-ciclo-tabla${estado.conGeo ? ' ig-geo-tabla' : ''}" id="tabla_ciclo">
                <thead class="gp-thead">
                    <tr>
                        <th>Lote</th>
                        <th class="text-center">Estado</th>
                        <th class="ig-col-destacada">${esIngreso() ? 'Días p/ ingreso' : 'Días desde corte'}</th>
                        <th class="${claseColumna('d-none d-md-table-cell')}">Inicio</th>
                        <th class="${claseColumna('')}">Fin</th>
                        <th class="${claseColumna('text-end d-none d-md-table-cell')}">Días labor</th>
                        <th class="${claseColumna('text-end d-none d-md-table-cell')}">Ciclo</th>
                        <th>Próximo ingreso</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot></tfoot>
            </table>
        </div>
    </div>
</div>`;

const filaCiclo = (lote, indice) => {
    const dias = esIngreso() ? lote.diasParaIngreso : lote.diasTranscurridos;
    const conDato = lote.estado !== 'sin-dato';

    return `<tr role="button" tabindex="0" data-i="${indice}"${estado.activo === indice ? ' class="ig-activa"' : ''}>
        <td><span class="fw-semibold d-block">${escaparHtml(lote.codigo)}</span><span class="gp-subtexto text-truncate ig-lote-desc" title="${escaparHtml(lote.descripcion)}">${escaparHtml(lote.descripcion)}</span></td>
        <td class="text-center"><span class="ig-chip ig-e-${escaparHtml(lote.estado)}">${escaparHtml(estadoCiclo(lote.estado).nombre)}</span>${conLabores() && conDato && lote.labor ? `<span class="gp-subtexto d-block text-truncate ig-labor-urgente mt-1" title="${escaparHtml(lote.labor)}">${escaparHtml(lote.labor)}${lote.labores > 1 ? ` <span class="text-muted" title="${FORMATOS.kg.format(lote.labores)} labores evaluadas">+${FORMATOS.kg.format(lote.labores - 1)}</span>` : ''}</span>` : ''}</td>
        <td class="ig-col-destacada${dias < 0 ? ' ig-texto-rojo' : ''}">${dias == null ? NULO : FORMATOS.kg.format(dias)}</td>
        <td class="${claseColumna('d-none d-md-table-cell')}">${celdaFecha(lote.inicio)}</td>
        <td class="${claseColumna('')}">${celdaFecha(lote.fin)}</td>
        <td class="${claseColumna('inf-num d-none d-md-table-cell')}">${conDato && lote.diasLabor != null ? FORMATOS.kg.format(lote.diasLabor) : NULO}</td>
        <td class="${claseColumna('inf-num d-none d-md-table-cell')}">${conDato && lote.ciclo != null ? `${FORMATOS.kg.format(lote.ciclo)} d` : NULO}</td>
        <td>${celdaFecha(lote.proximo)}</td>
    </tr>`;
};

const pintarFilasCiclo = () => {
    const tabla = $id('tabla_ciclo');
    const visibles = estado.respuesta.lotes.map((lote, indice) => [lote, indice]).filter(([lote]) => !estado.filtroEstado || lote.estado === estado.filtroEstado);

    if (!tabla) return;

    tabla.tBodies[0].innerHTML = visibles.map(([lote, indice]) => filaCiclo(lote, indice)).join('');
    tabla.tFoot.innerHTML = `<tr><th>Total · ${FORMATOS.kg.format(visibles.length)} lotes</th><th colspan="7" class="inf-num">${FORMATOS.ha.format(hectareas(visibles.map(([lote]) => lote)))} ha</th></tr>`;
};

const datosMapa = () => [
    ...estado.respuesta.lotes.map((lote) => {
        const tono = estadoCiclo(lote.estado);
        const tenue = Boolean(estado.filtroEstado) && lote.estado !== estado.filtroEstado;

        return {
            name: lote.codigo,
            itemStyle: { areaColor: tono.relleno, opacity: tenue ? 0.25 : 1 },
            label: { show: !tenue, color: tono.texto },
            emphasis: { itemStyle: { areaColor: tono.relleno, opacity: tenue ? 0.25 : 1 }, label: { show: !tenue, color: tono.texto } }
        };
    }),
    ...(estado.respuesta.sinLote || []).map((nombre) => ({ name: nombre, itemStyle: ESTILO_SIN_LOTE, label: { show: false }, emphasis: { disabled: true } }))
];

const parTip = (etiqueta, valor, fuerte = false) => `<div class="d-flex justify-content-between gap-3"><span class="text-muted">${etiqueta}</span><span class="text-end${fuerte ? ' fw-bold' : ''}" style="font-variant-numeric: tabular-nums">${valor}</span></div>`;

const tipCiclo = (parametros) => {
    const lote = estado.lotes.get(parametros.name);

    if (!lote) return `<b>${escaparHtml(parametros.name)}</b><div class="text-muted">Sin registro en el sistema</div>`;

    const para = Number(lote.diasParaIngreso);
    const urgente = conLabores() ? (lote.labor ? parTip('Labor más urgente', escaparHtml(lote.labor), true) : '') + (lote.labores != null ? parTip('Labores evaluadas', FORMATOS.kg.format(lote.labores)) : '') : '';
    const detalle = lote.estado === 'sin-dato' ? '' : urgente + (esIngreso()
        ? parTip('Próximo ingreso', escaparHtml(fecha(lote.proximo)), true)
            + `<div class="text-end mb-1"${para < 0 ? ' style="color: #a62c2c"' : ''}>${para < 0 ? `Vencido hace ${FORMATOS.kg.format(-para)} días` : `Vence en ${FORMATOS.kg.format(para)} días`}</div>`
        : parTip('Días desde último corte', FORMATOS.kg.format(lote.diasTranscurridos), true))
        + parTip('Último ciclo', escaparHtml(`${fecha(lote.inicio)} – ${fecha(lote.fin)}`))
        + parTip('Días de labor', FORMATOS.kg.format(lote.diasLabor))
        + parTip('Ciclo', `${FORMATOS.kg.format(lote.ciclo)} días`);

    return `<div class="mb-1"><b>${escaparHtml(corto(lote))}</b> · ${escaparHtml(lote.descripcion)}</div>
        <div class="mb-2"><span class="ig-chip ig-e-${escaparHtml(lote.estado)}">${escaparHtml(estadoCiclo(lote.estado).nombre)}</span></div>
        ${detalle}${parTip('Hectáreas', Number.isFinite(Number(lote.hectareas ?? NaN)) ? FORMATOS.ha.format(lote.hectareas) : '—')}${lote.labor && !conLabores() ? parTip('Labor', escaparHtml(lote.labor)) : ''}${puedePlanear() ? '<div class="text-muted small mt-1">Clic para seleccionar y planear</div>' : ''}`;
};

const opcionesMapa = () => ({
    tooltip: {
        trigger: 'item',
        confine: true,
        backgroundColor: '#fff',
        borderColor: '#e4e8ee',
        borderWidth: 1,
        padding: [8, 10],
        textStyle: { color: '#2f3b48', fontSize: 12 },
        extraCssText: 'border-radius:.5rem; box-shadow:0 4px 14px rgba(0,0,0,.08); max-width:260px; white-space:normal;',
        formatter: tipCiclo
    },
    series: [{
        type: 'map',
        map: 'ig-' + estado.respuesta.mapa,
        roam: !movil(),
        scaleLimit: { min: 1, max: 5 },
        selectedMode: false,
        aspectScale: 1,
        left: 8,
        right: 8,
        top: 8,
        bottom: 8,
        itemStyle: { borderColor: '#fff', borderWidth: 1.5 },
        emphasis: { itemStyle: { borderColor: '#1d2630', borderWidth: 3, shadowBlur: 10, shadowColor: 'rgba(0,0,0,.35)' } },
        label: { show: true, fontSize: movil() ? 9 : 11, fontWeight: 600, formatter: (parametros) => (estado.lotes.has(parametros.name) ? corto(estado.lotes.get(parametros.name)) : '') },
        data: datosMapa()
    }]
});

const registrarMapa = (mapa, url) => {
    MAPAS_REGISTRADOS[mapa] ||= fetch(url).then((respuesta) => {
        if (!respuesta.ok) throw new Error('Mapa no disponible');

        return respuesta.text();
    }).then((svg) => {
        echarts.registerMap('ig-' + mapa, { svg });

        return new Set([...svg.matchAll(/ name="([^"]+)"/g)].map((coincidencia) => coincidencia[1]));
    });

    return MAPAS_REGISTRADOS[mapa].catch((error) => {
        delete MAPAS_REGISTRADOS[mapa];
        throw error;
    });
};

const mapaNoDisponible = () => {
    const cuerpo = $id('cuerpo_mapa');

    cuerpo.parentElement.querySelectorAll('.card-header > :not(h6), #cuerpo_mapa ~ *').forEach((nodo) => nodo.remove());
    cuerpo.className = 'card-body pt-2';
    cuerpo.innerHTML = estadoVacio('bi-map', 'No se pudo cargar el mapa', '<p class="text-muted mb-0">La tabla de lotes sigue disponible.</p>', 'inf-medallon-error');
};

const crearMapa = async (turno) => {
    const respuesta = estado.respuesta;
    let regiones;

    try {
        regiones = await registrarMapa(respuesta.mapa, respuesta.mapaUrl);
    } catch (error) {
        if (turno !== estado.turno || !$id('cuerpo_mapa')) return;

        mapaNoDisponible();
        return;
    }

    if (turno !== estado.turno || estado.respuesta !== respuesta || !$id('mapa_ciclo')) return;

    estado.regiones = regiones;
    estado.graficas.mapa = echarts.init($id('mapa_ciclo'));
    estado.graficas.mapa.setOption(opcionesMapa());
    estado.graficas.mapa.on('click', (parametros) => {
        const indice = respuesta.lotes.findIndex((lote) => lote.codigo === parametros.name);

        if (indice >= 0) activarLote(indice, true);
    });
};

const animar = () => !matchMedia('(prefers-reduced-motion: reduce)').matches;
const loteTenue = (lote) => Boolean(estado.filtroEstado) && lote.estado !== estado.filtroEstado;
const anunciar = (mensaje) => $id('geo_anuncio') && ($id('geo_anuncio').textContent = mensaje);

const estiloLote = (codigo) => {
    const G = estado.geo;
    const lote = estado.lotes.get(codigo);

    if (!lote) return { color: 'rgba(255,255,255,.7)', weight: 1, opacity: 1, fill: false, dashArray: '4 4' };

    const relleno = estadoCiclo(lote.estado).relleno;
    const elegido = G.seleccion === codigo;

    if (loteTenue(lote)) return { color: '#ffffff', weight: 1, opacity: 0.4, fillColor: relleno, fillOpacity: 0.1 };
    const informe = estado.modoPalmas === 'informe' && G.grupos.has(codigo);

    if (G.hover === codigo && !elegido) return { color: '#ffffff', weight: 3, opacity: 1, fillColor: relleno, fillOpacity: informe ? 0.15 : 0.7 };

    return { color: '#ffffff', weight: elegido ? 4 : 1.5, opacity: 1, fillColor: relleno, fillOpacity: informe || (elegido && G.grupos.has(codigo)) ? 0.15 : 0.55 };
};

const hoverGeo = (codigo) => {
    const G = estado.geo;
    const previo = G.hover;

    G.hover = codigo ?? null;
    [previo, G.hover].forEach((clave) => G.capas.get(clave)?.setStyle(estiloLote(clave)));
    G.capas.get(G.hover && !loteTenue(estado.lotes.get(G.hover) || {}) ? G.hover : G.seleccion)?.bringToFront();
};

const restilarGeo = () => {
    const G = estado.geo;

    G.capas.forEach((capa, codigo) => capa.setStyle(estiloLote(codigo)));
    G.etiquetas.forEach((etiqueta, codigo) => etiqueta.setOpacity(loteTenue(estado.lotes.get(codigo)) ? 0.45 : 1));
    G.grupos.forEach((grupo) => grupo.remove());
    G.grupos.clear();
    G.mapa.closePopup();
    actualizarPalmas();
};

const seleccionarGeo = (codigo) => {
    const G = estado.geo;
    const previo = G.seleccion;
    const capa = G.capas.get(codigo);

    G.seleccion = codigo ?? null;
    G.halo?.remove();
    G.halo = null;
    G.capas.get(previo)?.setStyle(estiloLote(previo));

    if (capa) {
        G.halo = L.polygon(capa.getLatLngs(), { color: '#111827', weight: 8, opacity: 0.45, fill: false, interactive: false, pane: 'ig-halo', renderer: G.lienzoHalo }).addTo(G.mapa);
        capa.bringToFront();
        capa.setStyle(estiloLote(codigo));
        G.mapa.fitBounds(capa.getBounds(), { padding: [24, 24], maxZoom: 18, animate: animar() });
        anunciar(`Lote ${corto(estado.lotes.get(codigo))} seleccionado`);
    }

    actualizarPalmas();
};

const restablecerGeo = () => {
    const G = estado.geo;

    estado.activo = null;
    document.querySelectorAll('#tabla_ciclo tr.ig-activa').forEach((filaLote) => filaLote.classList.remove('ig-activa'));
    G.mapa.closePopup();
    seleccionarGeo(null);
    G.mapa.fitBounds(G.lotes.getBounds(), { padding: [16, 16], animate: animar() });
    botonMapa();
};

const oracion = (valor) => texto(valor).charAt(0).toUpperCase() + texto(valor).slice(1).toLowerCase();

const diaUtc = (valor) => {
    const [anio, mes, dia] = texto(valor).slice(0, 10).split('-').map(Number);

    return Date.UTC(anio, mes - 1, dia);
};

const tipPalma = (palma, lote) => {
    const informe = parTip('Estado del informe', `<span class="ig-chip ig-e-${escaparHtml(lote.estado)}">${escaparHtml(estadoCiclo(lote.estado).nombre)}</span>`);
    const sanitario = parTip('Estado sanitario', `<span class="d-inline-flex align-items-center gap-1">${PalmasMapa.muestra(palma.sanitario)}${escaparHtml(palma.descripcion ? oracion(palma.descripcion) : PalmasMapa.PALETA_SANITARIA[palma.sanitario].nombre)}</span>`);
    const dias = palma.fechaCenso ? Math.round((diaUtc(estado.respuesta.corte) - diaUtc(palma.fechaCenso)) / 864e5) : NaN;

    return `<div class="mb-1 fw-bold font-monospace">${escaparHtml(`Palma ${palma.linea}.${palma.palma}`)}</div>
    ${parTip('Lote', escaparHtml(corto(lote)))}${parTip('Línea', escaparHtml(palma.linea))}${parTip('Palma', escaparHtml(palma.palma))}
    ${estado.modoPalmas === 'sanitario' ? sanitario + informe : informe + sanitario}
    ${parTip('Último censo', palma.fechaCenso ? escaparHtml(fecha(texto(palma.fechaCenso).slice(0, 10))) : '<span class="text-muted">Sin censo</span>')}
    ${estado.informe === 'ciclos-sanidad' ? parTip('Días desde censo', Number.isFinite(dias) ? (dias < 0 ? '<span class="text-muted">Posterior al corte</span>' : `${FORMATOS.kg.format(dias)} días`) : '<span class="text-muted">Sin censo</span>') : ''}
    ${conLabores() && lote.labor ? parTip('Labor más urgente', escaparHtml(lote.labor)) : ''}
    ${palma.estado === 'ERRADICADA' ? parTip('Estado', 'Erradicada') : ''}`;
};

const marcarPalma = (marca) => {
    const G = estado.geo;
    const radio = PalmasMapa.radioPalma(G.mapa.getZoom()).radio;

    G.marca?.remove();
    G.marca = marca && L.layerGroup([
        L.circleMarker(marca.getLatLng(), { radius: radio + 6, color: '#111827', weight: 4, opacity: 0.55, fill: false, interactive: false, pane: 'ig-palmas', renderer: G.lienzo }),
        L.circleMarker(marca.getLatLng(), { radius: radio + 4, color: '#ffffff', weight: 2.5, opacity: 1, fill: false, interactive: false, pane: 'ig-palmas', renderer: G.lienzo })
    ]).addTo(G.mapa);
    if (G.marca) G.marca.palma = marca;
};

const palmaEn = (latlng) => {
    const G = estado.geo;
    const punto = G.mapa.latLngToContainerPoint(latlng);
    let elegida = null;
    let minimo = Infinity;

    G.grupos.forEach((grupo) => grupo.eachLayer((marca) => {
        const distancia = marca.options.interactive ? punto.distanceTo(G.mapa.latLngToContainerPoint(marca.getLatLng())) : Infinity;

        if (distancia < minimo) [elegida, minimo] = [marca, distancia];
    }));

    return elegida && minimo <= elegida.options.radius + (L.Browser.touch ? 10 : 4) ? elegida : null;
};

const abrirPalma = (marca) => {
    const G = estado.geo;

    L.popup({ className: 'ig-geo-tip', offset: [0, -4] }).setLatLng(marca.getLatLng()).setContent(tipPalma(marca.palma, estado.lotes.get(marca.lote))).openOn(G.mapa);
    marcarPalma(marca);
    anunciar(`Palma ${marca.palma.linea}.${marca.palma.palma} seleccionada`);
};

const palmasDe = (respuesta) => (respuesta.palmas || []).map(([id, linea, palma, lat, lon, estadoPalma, caracteristica, fechaCenso]) => {
    const descripcion = caracteristica == null ? '' : texto(respuesta.caracteristicas?.[caracteristica]?.descripcion ?? caracteristica);

    return { id, linea, palma, lat: Number(lat), lon: Number(lon), estado: estadoPalma, descripcion, fechaCenso, sanitario: PalmasMapa.claveSanitaria(descripcion, estadoPalma) };
}).filter((palma) => Number.isFinite(palma.lat) && Number.isFinite(palma.lon));

const estiloMarca = (palma, codigo, zoom) => {
    const lote = estado.lotes.get(codigo) || {};
    const tono = estadoCiclo(lote.estado);
    const { radio, weight } = PalmasMapa.radioPalma(zoom);
    const base = palma.sanitario === 'erradicada' || estado.modoPalmas === 'sanitario'
        ? PalmasMapa.estiloPalma({ clave: palma.sanitario }, zoom)
        : { radius: radio, weight, color: tono.texto === '#fff' ? '#ffffff' : '#1f2937', opacity: 1, fill: true, fillColor: tono.relleno, fillOpacity: 0.95, dashArray: null };

    return loteTenue(lote) ? { ...base, fillOpacity: Math.min(base.fillOpacity, 0.25), opacity: 0.35 } : base;
};

const grupoPalmas = (codigo, palmas) => {
    const zoom = estado.geo.mapa.getZoom();
    const activo = !loteTenue(estado.lotes.get(codigo));

    return L.layerGroup(palmas.map((palma) => {
        const marca = L.circleMarker([palma.lat, palma.lon], { ...estiloMarca(palma, codigo, zoom), interactive: activo, pane: 'ig-palmas', renderer: estado.geo.lienzo });

        return Object.assign(marca, { palma, lote: codigo });
    }));
};

const restilarPalmas = () => {
    const G = estado.geo;
    const zoom = G.mapa.getZoom();

    G.grupos.forEach((grupo) => grupo.eachLayer((marca) => {
        marca.options.interactive = !loteTenue(estado.lotes.get(marca.lote));
        marca.setStyle(estiloMarca(marca.palma, marca.lote, zoom));
    }));
    if (G.marca) marcarPalma(G.marca.palma);
};

const lotesConPalmas = () => {
    const G = estado.geo;
    const vista = G.mapa.getBounds();
    const cerca = G.mapa.getZoom() >= 17;

    if (!$id('ig_geo_palmas')?.checked) return [];

    return [...G.capas].filter(([codigo, capa]) => estado.lotes.has(codigo) && (codigo === G.seleccion || (cerca && vista.intersects(capa.getBounds())))).map(([codigo]) => codigo);
};

const pieGeo = () => {
    const G = estado.geo;
    const caja = $id('geo_palmas');
    const conteo = {};
    const codigos = [...G.grupos.keys()];
    const filaPie = (contenido) => `<div class="ig-geo-leyenda-palmas mt-2 pt-2">${contenido}</div>`;

    if (!caja) return;

    const informe = estado.modoPalmas !== 'sanitario';

    G.grupos.forEach((grupo, codigo) => grupo.eachLayer((marca) => {
        const clave = informe && marca.palma.sanitario !== 'erradicada' ? `e:${estado.lotes.get(codigo)?.estado || 'sin-dato'}` : marca.palma.sanitario;

        conteo[clave] = (conteo[clave] || 0) + 1;
    }));

    const item = (muestra, nombre, cantidad) => `<span class="d-inline-flex align-items-center gap-1">${muestra}${escaparHtml(nombre)} <span class="text-muted">${FORMATOS.kg.format(cantidad)}</span></span>`;
    const items = informe
        ? (estado.respuesta.rangos || []).filter((rango) => ESTADOS_CICLO[rango.clave] && conteo[`e:${rango.clave}`])
            .map((rango) => item(`<span class="gp-mapa-punto" style="--gp-sanitario:${ESTADOS_CICLO[rango.clave].relleno}" aria-hidden="true"></span>`, ESTADOS_CICLO[rango.clave].nombre, conteo[`e:${rango.clave}`])).join('')
            + (conteo.erradicada ? item(PalmasMapa.muestra('erradicada'), PalmasMapa.PALETA_SANITARIA.erradicada.nombre, conteo.erradicada) : '')
        : Object.keys(PalmasMapa.PALETA_SANITARIA).filter((clave) => conteo[clave])
            .map((clave) => item(PalmasMapa.muestra(clave), PalmasMapa.PALETA_SANITARIA[clave].nombre, conteo[clave])).join('');

    if (!$id('ig_geo_palmas').checked) caja.innerHTML = '';
    else if (G.error) caja.innerHTML = filaPie('<span class="text-danger small">No se pudieron cargar las palmas</span><button type="button" class="btn btn-link btn-sm p-0 ms-2 align-baseline" data-accion="reintentar-palmas">Reintentar</button>');
    else if (codigos.length) caja.innerHTML = filaPie(`<span class="gp-etiqueta-mini d-block mb-1">Palmas · ${informe ? 'Estado del informe' : 'Estado sanitario'} · ${escaparHtml(codigos.length === 1 ? `Lote ${corto(estado.lotes.get(codigos[0]))}` : `${codigos.length} lotes en vista`)}</span><div class="ig-leyenda">${items || '<span class="text-muted">Sin palmas mapeadas</span>'}</div>`);
    else caja.innerHTML = !G.seleccion && G.mapa.getZoom() < 17 ? '<div class="small text-muted mt-2"><i class="bi bi-zoom-in me-1" aria-hidden="true"></i>Acérquese o seleccione un lote para ver las palmas</div>' : '';
};

const actualizarPalmas = () => {
    const G = estado.geo;

    if (!G) return;

    const codigos = lotesConPalmas();
    const clave = codigos.join('\n');

    G.grupos.forEach((grupo, codigo) => {
        if (codigos.includes(codigo)) return;
        grupo.remove();
        G.grupos.delete(codigo);
    });
    if (G.marca && !G.grupos.has(G.marca.palma.lote)) {
        G.mapa.closePopup();
        marcarPalma(null);
    }
    codigos.filter((codigo) => !G.cache.has(codigo)).forEach((codigo) => G.cache.set(codigo, pedir('palmas', { empresa: estado.filtros.empresa, finca: estado.filtros.finca, lote: codigo }).then(palmasDe).catch((error) => {
        G.cache.delete(codigo);
        throw error;
    })));
    G.clave = clave;
    G.error = false;
    G.capas.forEach((capa, codigo) => capa.setStyle(estiloLote(codigo)));
    mostrar($id('geo_cargando'), codigos.some((codigo) => !G.grupos.has(codigo)));
    pieGeo();

    Promise.allSettled(codigos.map((codigo) => G.cache.get(codigo))).then((resultados) => {
        if (estado.geo !== G || G.clave !== clave) return;

        resultados.forEach((resultado, i) => {
            if (resultado.status === 'fulfilled' && !G.grupos.has(codigos[i])) G.grupos.set(codigos[i], grupoPalmas(codigos[i], resultado.value).addTo(G.mapa));
        });
        G.error = resultados.some((resultado) => resultado.status === 'rejected');
        G.capas.forEach((capa, codigo) => capa.setStyle(estiloLote(codigo)));
        mostrar($id('geo_cargando'), false);
        pieGeo();
    });
};

const crearGeo = () => {
    const contenedor = $id('mapa_ciclo');
    const fino = matchMedia('(pointer: fine)').matches;
    const base = PalmasMapa.capasBase();
    const G = { seleccion: null, hover: null, capas: new Map(), etiquetas: new Map(), grupos: new Map(), cache: new Map(), halo: null, marca: null, error: false, tiles: false };

    estado.geo = G;
    G.mapa = L.map(contenedor, { scrollWheelZoom: false, dragging: !L.Browser.mobile, maxZoom: 21 });
    G.mapa.createPane('ig-halo').style.zIndex = 395;
    G.mapa.createPane('ig-palmas').style.zIndex = 450;
    G.lienzoHalo = L.canvas({ pane: 'ig-halo' });
    G.lienzo = L.canvas({ pane: 'ig-palmas', padding: 0.5 });
    Object.values(base)[0].addTo(G.mapa);
    Object.values(base).forEach((capa) => capa.on('tileload', () => {
        G.tiles = true;
    }).on('load', () => setTimeout(() => {
        if (!G.tiles && estado.geo === G) {
            destruirGraficas();
            mapaNoDisponible();
        }
    })));
    L.control.layers(base, null, { position: 'topright', collapsed: movil() }).addTo(G.mapa);
    L.control.scale({ imperial: false }).addTo(G.mapa);
    PalmasMapa.pantallaCompleta(G.mapa, contenedor.closest('.card'));

    try {
        G.lotes = L.geoJSON(estado.respuesta.geo.lotes, {
            style: (feature) => estiloLote(texto(feature.properties?.codigo)),
            onEachFeature: (feature, capa) => {
                const codigo = texto(feature.properties?.codigo);
                const lote = estado.lotes.get(codigo);
                const centro = feature.properties?.centro;

                G.capas.set(codigo, capa);
                if (fino) {
                    capa.bindTooltip(() => tipCiclo({ name: codigo }), { sticky: true, className: 'ig-geo-tip', direction: 'top', offset: [0, -8] });
                    capa.on('mouseover', () => hoverGeo(codigo)).on('mouseout', () => hoverGeo(null));
                }
                if (!lote) return;

                G.etiquetas.set(codigo, L.tooltip({ permanent: true, direction: 'center', className: 'ig-geo-etiqueta', opacity: loteTenue(lote) ? 0.45 : 1 })
                    .setLatLng(Array.isArray(centro) && centro.length === 2 ? centro : capa.getBounds().getCenter()).setContent(escaparHtml(corto(lote))));
                capa.on('click', (evento) => {
                    if (palmaEn(evento.latlng)) return;
                    activarLote(estado.respuesta.lotes.indexOf(lote), true);
                    if (!fino) L.popup({ className: 'ig-geo-tip' }).setLatLng(evento.latlng).setContent(tipCiclo({ name: codigo })).openOn(G.mapa);
                });
            }
        }).addTo(G.mapa);
        G.mapa.fitBounds(G.lotes.getBounds(), { padding: [16, 16], animate: false });
        G.etiquetas.forEach((etiqueta) => etiqueta.addTo(G.mapa));
    } catch (error) {
        destruirGraficas();
        mapaNoDisponible();
        return;
    }

    G.mapa.on('zoomend', () => {
        contenedor.classList.toggle('ig-geo-sin-etiquetas', G.mapa.getZoom() < 14);
        restilarPalmas();
    });
    G.mapa.on('moveend', actualizarPalmas);
    G.mapa.on('popupclose', () => marcarPalma(null));
    G.mapa.on('click', (evento) => {
        const marca = palmaEn(evento.latlng);

        if (marca) abrirPalma(marca);
    });
    contenedor.classList.toggle('ig-geo-sin-etiquetas', G.mapa.getZoom() < 14);
    contenedor.addEventListener('focus', () => G.mapa.scrollWheelZoom.enable());
    contenedor.addEventListener('blur', () => G.mapa.scrollWheelZoom.disable());
    contenedor.addEventListener('touchstart', () => G.mapa.dragging.enable(), { once: true, passive: true });
    $id('ig_geo_palmas').addEventListener('change', () => {
        document.querySelectorAll('[name="ig_modo_palmas"]').forEach((radio) => {
            radio.disabled = !$id('ig_geo_palmas').checked;
        });
        actualizarPalmas();
    });
    document.querySelectorAll('[name="ig_modo_palmas"]').forEach((radio) => radio.addEventListener('change', () => {
        estado.modoPalmas = radio.value;
        restilarPalmas();
        G.capas.forEach((capa, codigo) => capa.setStyle(estiloLote(codigo)));
        pieGeo();
        if (G.marca) abrirPalma(G.marca.palma);
    }));
    actualizarPalmas();
};

const resaltarMapa = (indice) => {
    const grafica = estado.graficas.mapa;

    if (estado.geo) {
        hoverGeo(estado.respuesta.lotes[indice]?.codigo);
        return;
    }

    if (!grafica) return;

    grafica.dispatchAction({ type: 'downplay', seriesIndex: 0 });

    if (indice == null || !estado.regiones.has(estado.respuesta.lotes[indice]?.codigo)) {
        grafica.dispatchAction({ type: 'hideTip' });
        return;
    }

    grafica.dispatchAction({ type: 'highlight', seriesIndex: 0, dataIndex: indice });
    grafica.dispatchAction({ type: 'showTip', seriesIndex: 0, dataIndex: indice });
};

const activarLote = (indice, desdeMapa) => {
    estado.activo = indice;
    document.querySelectorAll('#tabla_ciclo tr.ig-activa').forEach((filaLote) => filaLote.classList.remove('ig-activa'));

    const filaLote = document.querySelector(`#tabla_ciclo tr[data-i="${indice}"]`);

    filaLote?.classList.add('ig-activa');

    if (desdeMapa) filaLote?.scrollIntoView({ block: 'nearest' });
    if (estado.geo) seleccionarGeo(estado.respuesta.lotes[indice]?.codigo);
    else if (!desdeMapa) resaltarMapa(indice);
    botonMapa();
};

const filtrarEstado = (clave) => {
    estado.filtroEstado = !clave || clave === 'todos' || clave === estado.filtroEstado ? null : clave;
    document.querySelectorAll('.ig-ciclo-kpi[aria-pressed]').forEach((boton) => boton.setAttribute('aria-pressed', String(boton.dataset.estado === estado.filtroEstado)));
    $id('chip_estado').textContent = estado.filtroEstado ? `Filtrando: ${estadoCiclo(estado.filtroEstado).nombre}` : '';
    mostrar($id('chip_estado'), Boolean(estado.filtroEstado));
    mostrar($id('btn_ver_todos'), Boolean(estado.filtroEstado));
    pintarFilasCiclo();
    estado.graficas.mapa?.setOption({ series: [{ data: datosMapa() }] });
    if (estado.geo) restilarGeo();
};

const pintarCiclos = (avisos) => {
    const respuesta = estado.respuesta;
    const claves = (respuesta.rangos || []).map((rango) => rango.clave).filter((clave) => ESTADOS_CICLO[clave]);
    const ratio = RATIO_MAPA[respuesta.mapa] || [1000, 700];
    const geo = Boolean(window.L && window.PalmasMapa && respuesta.geo?.lotes?.features?.length);
    const conMapa = geo || Boolean(respuesta.mapa && respuesta.mapaUrl);
    const lado = geo || (conMapa && ratio[1] / ratio[0] >= 0.6);

    estado.conGeo = geo;

    estado.lotes = new Map(respuesta.lotes.map((lote) => [lote.codigo, lote]));

    if (!respuesta.lotes.length) {
        $id('resultado_cuerpo').innerHTML = avisos + `<div class="card mb-3">${estadoVacio('bi-inbox', `Sin lotes con registros de ${nombreCiclo().toLowerCase()} para la finca`, '<p class="text-muted mb-0">Verifique la finca o la fecha de corte.</p>')}</div>`;
        return;
    }

    $id('resultado_cuerpo').innerHTML = avisos + pintarKpisCiclo(claves)
        + (conMapa ? '' : '<div class="gp-nota d-flex align-items-center gap-2 mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Esta finca no tiene mapa configurado; se muestra solo la tabla de lotes.</span></div>')
        + `<div class="row g-3 mb-3">
            ${geo ? pintarMapaGeo(claves) : (conMapa ? pintarMapaCiclo(claves, lado ? 'col-12 col-xl-7' : 'col-12', ratio) : '')}
            <div class="col-12${geo ? ' col-xxl-5' : (lado ? ' col-xl-5' : '')}">${pintarTablaCiclo()}</div>
        </div>`;
    pintarFilasCiclo();

    if (geo) crearGeo();
    else if (conMapa) crearMapa(estado.turno);
};

const pintar = () => {
    const respuesta = estado.respuesta;
    const avisos = (respuesta.avisos || []).length
        ? `<div class="gp-nota-alerta mb-3" role="alert"><div class="d-flex gap-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><ul class="mb-0 ps-3">${respuesta.avisos.map((aviso) => `<li>${escaparHtml(aviso)}</li>`).join('')}</ul></div></div>`
        : '';

    destruirGraficas();
    pintarContexto();

    if (esCiclo()) {
        pintarCiclos(avisos);
        return;
    }

    if (!(respuesta.arbol || []).length || (respuesta.arbol[0].nivel === 'empresa' && !(respuesta.arbol[0].hijos || []).length)) {
        $id('resultado_cuerpo').innerHTML = avisos + `<div class="card mb-3">${estadoVacio('bi-inbox', 'Sin producción ni presupuesto para el corte seleccionado', '<p class="text-muted mb-0">Ajuste el año, el mes de corte o la finca.</p>')}</div>`;
        return;
    }

    const fincas = fincasCumplimiento();

    $id('resultado_cuerpo').innerHTML = avisos + pintarKpis(respuesta.kpis || {}, respuesta.corte || {}) + pintarGraficas() + pintarTabla(respuesta.kpis || {});
    pintarFilas();
    actualizarNodo();

    estado.graficas.g1 = new ApexCharts($id('grafica_mensual'), opcionesG1());
    estado.graficas.g1.render();

    if (fincas.length) {
        estado.graficas.g2 = new ApexCharts($id('grafica_fincas'), opcionesG2(fincas));
        estado.graficas.g2.render();
    } else {
        $id('grafica_fincas').innerHTML = '<p class="text-muted small text-center py-5 mb-0">Sin fincas con presupuesto al corte.</p>';
    }

    if (estado.g3Abierto) crearG3();

    $id('colapso_diferencia').addEventListener('shown.bs.collapse', () => {
        estado.g3Abierto = true;
        crearG3();
    });
    $id('colapso_diferencia').addEventListener('hidden.bs.collapse', () => {
        estado.g3Abierto = false;
    });
};

const consultar = async () => {
    const turno = ++estado.turno;
    const filtros = valores();

    destruirGraficas();
    marcarFinca(esCiclo() && !filtros.finca);

    if (esCiclo() && !filtros.finca) {
        ocupado(false);
        estado.respuesta = null;
        estado.filtros = null;
        revisarVigencia();
        $id('chips_contexto').innerHTML = '';
        $id('resultado_cuerpo').innerHTML = vacioFinca();
        return;
    }

    estado.filtros = filtros;
    estado.textos = { empresa: textoSeleccion('filtro_empresa'), finca: filtros.finca ? textoSeleccion('filtro_finca') : 'Todas las fincas' };
    ocupado(true);
    revisarVigencia();
    $id('chips_contexto').innerHTML = '';
    $id('resultado_cuerpo').innerHTML = esCiclo() ? skeletonCiclo() : skeleton();

    let respuesta;

    try {
        respuesta = await pedir('consultar', { informe: estado.informe, ...filtros });
    } catch (error) {
        if (turno !== estado.turno) return;

        ocupado(false);
        estado.respuesta = null;
        $id('resultado_cuerpo').innerHTML = `<div class="card mb-3">${estadoVacio('bi-exclamation-octagon', 'No se pudo generar el informe',
            `<p class="text-muted mb-3">${escaparHtml(error.message)}</p><button type="button" class="btn btn-outline-success btn-sm" data-accion="reintentar">Reintentar</button>`, 'inf-medallon-error')}</div>`;

        if ([401, 403].includes(error.status)) Swal.fire({ icon: 'warning', title: 'Acción no permitida', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });

        return;
    }

    if (turno !== estado.turno) return;

    ocupado(false);
    estado.respuesta = respuesta;

    if (esCiclo()) {
        respuesta.lotes = ordenarLotes(respuesta.lotes || []);
        estado.filtroEstado = null;
        estado.modoPalmas = estado.informe === 'ciclos-sanidad' ? 'sanitario' : 'informe';
        estado.activo = null;
        estado.hover = null;
        pintar();
        return;
    }

    estado.indice = new Map();
    indexar(respuesta.arbol || []);
    estado.abiertos = new Set((respuesta.arbol || []).filter((nodo) => nodo.nivel === 'empresa').map((nodo) => nodo._k));
    estado.nodo = { _k: '', nombre: 'Total general', meses: respuesta.meses || [] };
    pintar();
};

const cargarFincas = async () => {
    const select = $id('filtro_finca');

    select.innerHTML = esCiclo() ? '' : '<option value="">Todas las fincas</option>';
    $(select).trigger('change');

    try {
        const empresa = $id('filtro_empresa').value;
        const resultado = await pedir('fincas', { empresa });
        const fincas = Array.isArray(resultado) ? resultado : (resultado.fincas || []);

        if (resultado.labores) act.catalogo[empresa] = { labores: resultado.labores, unidades: resultado.unidades || [], fincas: fincas.map((finca) => ({ codigo: finca.codigo, descripcion: finca.nombre, activo: 1 })) };

        select.innerHTML += fincas.map((finca) => `<option value="${escaparHtml(finca.codigo)}">${escaparHtml(`${texto(finca.codigo)} — ${texto(finca.nombre)}`)}</option>`).join('');
        if (esCiclo()) select.value = fincaPreferida(select);
        $(select).trigger('change');
    } catch (error) {
        Swal.fire({ icon: error.status === 403 ? 'warning' : 'error', title: 'No se pudieron cargar las fincas', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }
};

const URL_PL = BASE_URL + 'transacciones/planeacion/';
const PLAN = INFORMES_GERENCIALES.planeacion || {};
const TEMAS_PLAN = { 'presupuesto-ejecutado': 'Labores de cosecha', 'ciclos-cosecha': 'Labores de cosecha', 'ciclos-poda': 'Labores de poda', 'ciclos-fertilizacion': 'Fertilización (grupo 02)', 'ciclos-sanidad': 'Sanidad (grupo 03)', 'ciclos-mantenimiento': 'Mantenimiento (grupo 04)' };
const IDIOMA_PLAN = { noResults: () => 'No se encontraron resultados', searching: () => 'Buscando…', inputTooShort: () => 'Escriba al menos 3 caracteres', errorLoading: () => 'No se pudo consultar' };
const CANTIDAD = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 3 });
const act = { catalogo: {}, trabajadores: [], origen: null, original: '', forzar: false, guardando: false, lote: '', pfa: '', turnos: { lote: 0, pfa: 0 } };

if (PLAN.habilitada) act.catalogo[$id('filtro_empresa').value] = { labores: PLAN.labores || [], unidades: PLAN.unidades || [], fincas: PLAN.fincas || [] };

const catalogo = () => act.catalogo[estado.filtros?.empresa] || null;
const puedePlanear = () => Boolean($id('ig_act_modal') && catalogo()?.labores.length);
const hoyIso = () => {
    const hoy = new Date();

    return `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
};
const diaMes = (valor) => {
    const [anio, mes, dia] = texto(valor).split('-').map(Number);

    return anio ? `${String(dia).padStart(2, '0')} ${MESES_CORTOS[mes - 1].toLowerCase()}` : '';
};
const aNumero = (valor) => {
    const crudo = texto(valor).replace(/\s/g, '');
    const numero = /^\d+([.,]\d{1,3})?$/.test(crudo) ? Number(crudo.replace(',', '.')) : NaN;

    if (crudo === '') return 0;

    return texto(valorAct('um')).toUpperCase() === 'RAC' && !Number.isInteger(numero) ? NaN : numero;
};
const opcionPlan = (codigo, nombre) => `<option value="${escaparHtml(texto(codigo))}">${escaparHtml(`${texto(codigo)} — ${texto(nombre)}`)}</option>`;
const valorAct = (campo) => $id(`ig_act_${campo}`).value;
const laborAct = () => catalogo()?.labores.find((labor) => texto(labor.codigo) === valorAct('labor')) || null;
const grupoAct = () => texto(laborAct()?.grupo);
const pfaVisible = () => grupoAct() === '02' || (estado.informe === 'ciclos-fertilizacion' && !valorAct('labor'));
const loteInfo = () => (esCiclo() && act.lote ? estado.lotes.get(act.lote) : null);
const firmaAct = () => JSON.stringify([...['fecha', 'finca', 'labor', 'um', 'inicio', 'fin', 'obs'].map(valorAct), act.lote, act.trabajadores]);

const botonPlanear = (accion, etiqueta, compacto = false, extra = '') => (puedePlanear()
    ? `<button type="button" class="btn btn-sm btn-success" data-accion="${accion}" title="${escaparHtml(etiqueta)}" aria-label="${escaparHtml(etiqueta)}"${extra}><i class="bi bi-calendar-plus" aria-hidden="true"></i><span class="${compacto ? 'd-none d-sm-inline ' : ''}ms-1">${escaparHtml(etiqueta)}</span></button>`
    : '');

const botonMapa = () => {
    const boton = $id('btn_planear_lote');
    const lote = estado.respuesta?.lotes?.[estado.activo];

    if (!boton) return;

    boton.hidden = !lote;
    if (!lote) return;

    const etiqueta = `Planear en ${corto(lote)}`;

    boton.title = etiqueta;
    boton.setAttribute('aria-label', etiqueta);
    boton.lastElementChild.textContent = etiqueta;
};

const marcarAct = (campo, invalido, mensaje = '') => {
    const elemento = $id(`ig_act_${campo}`);

    (elemento.closest('.tx-select-ancho') || elemento).classList.toggle('is-invalid', invalido);
    if (mensaje) $id(`ig_act_error_${campo}`).textContent = mensaje;
    mostrar($id(`ig_act_error_${campo}`), invalido);
};

const asegurarAct = (campo, valor) => {
    const selector = $id(`ig_act_${campo}`);

    if (valor && ![...selector.options].some((opcion) => opcion.value === valor)) selector.add(new Option(valor, valor));
};

const pintarFechaAct = () => {
    const valor = valorAct('fecha');

    $id('ig_act_chip_fecha').textContent = valor ? diaMes(valor) : 'Sin fecha';
    $id('ig_act_fecha_ayuda').textContent = valor ? `Se agregará al plan del ${diaMes(valor)}; si no existe, se crea.` : 'Seleccione la fecha del plan.';
};

const pintarTotalAct = () => {
    const numeros = act.trabajadores.map((trabajador) => aNumero(trabajador.valor));
    const invalida = numeros.some(Number.isNaN);
    const suma = numeros.reduce((total, numero) => total + (numero || 0), 0);
    const indicador = $id('ig_act_indicador');

    indicador.className = `tx-reparto tx-reparto--${invalida ? 'pendiente' : 'inactivo'} pl-indicador`;
    indicador.innerHTML = `<span class="tx-estado">${invalida
        ? '<i class="bi bi-exclamation-octagon" aria-hidden="true"></i>Hay cantidades inválidas'
        : `<i class="bi bi-calculator" aria-hidden="true"></i>${suma > 0 ? `Total <span class="tx-estado-num">${CANTIDAD.format(suma)}</span> ${escaparHtml(valorAct('um'))}` : 'Sin cantidades asignadas'}`}</span>`;
};

const pintarRepartoAct = () => {
    $id('ig_act_contador').textContent = FORMATOS.kg.format(act.trabajadores.length);
    $id('ig_act_filas').innerHTML = act.trabajadores.length ? act.trabajadores.map((trabajador, indice) => {
        const nombre = escaparHtml(trabajador.nombre || trabajador.tercero);

        return `<tr>
            <td><span class="font-monospace fw-semibold me-1">${escaparHtml(trabajador.codigo)}</span><span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span></td>
            <td class="text-end"><input type="text" class="form-control form-control-sm tx-input-num font-monospace ms-auto" data-reparto="${indice}" value="${escaparHtml(trabajador.valor)}" placeholder="Opcional" inputmode="decimal" maxlength="14" autocomplete="off" aria-label="Cantidad de ${nombre}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-quitar="${escaparHtml(trabajador.tercero)}" title="Quitar a ${nombre}" aria-label="Quitar a ${nombre}"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
        </tr>`;
    }).join('') : '<tr><td colspan="3" class="text-center text-muted py-3">Seleccione los trabajadores de la actividad.</td></tr>';
    pintarTotalAct();
};

const pintarContextoAct = () => {
    const caja = $id('ig_act_contexto');
    const lote = loteInfo();

    mostrar(caja, Boolean(lote));
    if (!lote) return;

    const conDato = lote.estado !== 'sin-dato';
    const para = Number(lote.diasParaIngreso);
    const dato = (etiqueta, valor) => `<span><span class="text-muted">${etiqueta}:</span> <span class="fw-semibold">${valor}</span></span>`;

    caja.innerHTML = `<div class="d-flex flex-wrap gap-3 align-items-center small">
        <span class="min-w-0"><span class="fw-semibold font-monospace me-1">${escaparHtml(lote.codigo)}</span><span class="fw-semibold">${escaparHtml(lote.descripcion)}</span></span>
        <span class="ig-chip ig-e-${escaparHtml(lote.estado)} order-first order-sm-0">${escaparHtml(estadoCiclo(lote.estado).nombre)}</span>
        ${conDato && esIngreso() && lote.diasParaIngreso != null ? (para < 0 ? `<span class="ig-texto-rojo fw-semibold">Vencido hace ${FORMATOS.kg.format(-para)} días</span>` : dato('Días p/ ingreso', FORMATOS.kg.format(para))) : ''}
        ${conDato && !esIngreso() && lote.diasTranscurridos != null ? dato('Días desde corte', FORMATOS.kg.format(lote.diasTranscurridos)) : ''}
        ${conLabores() && conDato && lote.labor ? dato('Labor más urgente', escaparHtml(lote.labor)) : ''}
        ${fecha(lote.proximo) ? dato('Próximo ingreso', escaparHtml(fecha(lote.proximo))) : ''}
    </div>`;
};

const cargarSelectAct = async (campo, ruta, datos, opcion, valor, vacio) => {
    const selector = $id(`ig_act_${campo}`);
    const turno = ++act.turnos[campo];
    let correcto = true;

    selector.disabled = true;
    selector.innerHTML = '<option value="">Cargando…</option>';
    $(selector).trigger('change.select2');
    mostrar($id(`ig_act_fallo_${campo}`), false);

    try {
        const filas = (await pedir(ruta, datos, URL_PL)).filas || [];

        if (turno !== act.turnos[campo]) return false;
        selector.innerHTML = vacio + filas.map(opcion).join('');
        selector.value = [...selector.options].some((item) => item.value === valor) ? valor : '';
    } catch (error) {
        if (turno !== act.turnos[campo]) return false;
        selector.innerHTML = vacio;
        mostrar($id(`ig_act_fallo_${campo}`), true);
        correcto = false;
    }

    selector.disabled = false;
    $(selector).trigger('change.select2');

    return correcto;
};

const cargarLotesAct = async () => {
    const finca = valorAct('finca');

    if (!finca) {
        ++act.turnos.lote;
        $id('ig_act_lote').disabled = true;
        $id('ig_act_lote').innerHTML = '<option value="">Seleccione una finca primero</option>';
        $('#ig_act_lote').trigger('change.select2');
        mostrar($id('ig_act_fallo_lote'), false);
        return;
    }

    await cargarSelectAct('lote', 'lotes', { empresa: estado.filtros.empresa, finca }, (lote) => (Number(lote.activo ?? 1) !== 0 || texto(lote.codigo) === act.lote ? opcionPlan(lote.codigo, lote.descripcion) : ''), act.lote, '<option value="">Seleccione el lote…</option>');
};

const cargarPfaAct = async (forzar = false) => {
    const finca = valorAct('finca');
    const dia = valorAct('fecha');
    const clave = `${estado.filtros.empresa}|${finca}|${dia}`;

    mostrar($id('ig_act_col_pfa'), pfaVisible());
    if (!pfaVisible() || (!forzar && clave === act.pfa)) return;

    act.pfa = clave;
    if (!finca || !dia) {
        $id('ig_act_pfa').innerHTML = '<option value="">Sin referencia</option>';
        return;
    }

    if (!(await cargarSelectAct('pfa', 'planesFertilizacion', { empresa: estado.filtros.empresa, fecha: dia, finca }, (plan) => `<option value="${escaparHtml(texto(plan.numero))}">PFA-${escaparHtml(texto(plan.numero))}${texto(plan.observacion) ? ` — ${escaparHtml(texto(plan.observacion))}` : ''}</option>`, valorAct('pfa'), '<option value="">Sin referencia</option>'))) act.pfa = '';
};

const laborCambiada = () => {
    const labor = laborAct();
    const um = texto(labor?.uMedida);

    $id('ig_act_codigo').value = labor ? texto(labor.codigo) : '';
    $id('ig_act_codigo').classList.remove('is-invalid');
    marcarAct('labor', false);
    const cosecha = Number(labor?.claseLabor) === 2;

    if (um || cosecha) {
        asegurarAct('um', cosecha ? 'RAC' : um);
        $('#ig_act_um').val(cosecha ? 'RAC' : um).trigger('change.select2');
        marcarAct('um', false);
    }
    $id('ig_act_um').disabled = cosecha;
    cargarPfaAct();
    pintarTotalAct();
};

const pintarLabores = () => {
    const todas = $id('ig_act_todas').checked;
    const previa = valorAct('labor');
    const lista = catalogo().labores.filter((labor) => todas || (labor.temas || []).includes(estado.informe));
    const sigue = lista.some((labor) => texto(labor.codigo) === previa);
    const chip = $id('ig_act_tema');

    $id('ig_act_labor').innerHTML = '<option value=""></option>' + lista.map((labor) => opcionPlan(labor.codigo, labor.descripcion)).join('');
    $('#ig_act_labor').val(sigue ? previa : '').trigger('change.select2');
    chip.className = `gp-chip-fila ${todas ? 'tx-chip-neutro' : 'verde'}`;
    chip.innerHTML = `<i class="bi bi-funnel me-1" aria-hidden="true"></i><span class="visually-hidden">Mostrando </span>${escaparHtml(todas ? 'Todas las labores' : TEMAS_PLAN[estado.informe] || 'Labores del informe')}`;
    if (!sigue) laborCambiada();
};

const elegirCodigo = (codigo) => {
    const labor = catalogo().labores.find((item) => texto(item.codigo).toUpperCase() === texto(codigo).toUpperCase());

    if (!labor) return false;

    if (![...$id('ig_act_labor').options].some((opcion) => opcion.value === texto(labor.codigo))) {
        $id('ig_act_todas').checked = true;
        pintarLabores();
    }
    $('#ig_act_labor').val(texto(labor.codigo)).trigger('change.select2');
    laborCambiada();

    return true;
};

const laborUrgente = () => {
    const lote = loteInfo();

    if (conLabores() && lote?.labor && !valorAct('labor')) elegirCodigo(texto(lote.labor).split(' — ')[0]);
};

const destinoPlan = (accion) => {
    if (accion !== 'planear-cosecha') return { finca: texto(estado.filtros.finca), lote: texto(estado.respuesta?.lotes?.[estado.activo]?.codigo) };

    const nodo = estado.nodo || {};
    const padre = estado.indice.get(nodo._padre) || {};

    return {
        finca: texto(estado.filtros.finca) || (nodo.nivel === 'finca' ? texto(nodo.codigo) : (nodo.nivel === 'lote' ? texto(padre.codigo) : '')),
        lote: nodo.nivel === 'lote' ? texto(nodo.codigo) : ''
    };
};

const abrirPlan = (boton) => {
    const cat = catalogo();

    if (!cat || act.guardando) return;

    const { finca, lote } = destinoPlan(boton.dataset.accion);

    Object.assign(act, { origen: boton, trabajadores: [], forzar: false, lote, pfa: '' });
    $id('ig_act_finca').innerHTML = '<option value=""></option>' + cat.fincas.filter((item) => Number(item.activo ?? 1) !== 0 || texto(item.codigo) === finca).map((item) => opcionPlan(item.codigo, item.descripcion)).join('');
    asegurarAct('finca', finca);
    $id('ig_act_finca').disabled = Boolean(estado.filtros.finca);
    $('#ig_act_finca').val(finca).trigger('change.select2');
    $id('ig_act_um').innerHTML = '<option value=""></option>' + cat.unidades.map((unidad) => opcionPlan(unidad.codigo, unidad.descripcion)).join('');
    $('#ig_act_um').val('').trigger('change.select2');
    $id('ig_act_fecha').min = hoyIso();
    $id('ig_act_fecha').value = PLAN.fechaDefecto && PLAN.fechaDefecto >= hoyIso() ? PLAN.fechaDefecto : hoyIso();
    ['inicio', 'fin', 'obs', 'codigo'].forEach((campo) => {
        $id(`ig_act_${campo}`).value = '';
    });
    $id('ig_act_contador_obs').textContent = '0 / 250';
    $id('ig_act_pfa').innerHTML = '<option value="">Sin referencia</option>';
    $id('ig_act_labor').innerHTML = '';
    document.querySelectorAll('#ig_act_form .is-invalid').forEach((campo) => campo.classList.remove('is-invalid'));
    document.querySelectorAll('#ig_act_form [id^="ig_act_error_"], #ig_act_form [id^="ig_act_fallo_"]').forEach((aviso) => mostrar(aviso, false));
    $('#ig_act_terceros').empty().trigger('change.select2');
    $id('ig_act_todas').checked = !cat.labores.some((labor) => (labor.temas || []).includes(estado.informe));
    pintarLabores();
    laborUrgente();
    pintarFechaAct();
    pintarContextoAct();
    pintarRepartoAct();
    act.original = firmaAct();
    bootstrap.Modal.getOrCreateInstance($id('ig_act_modal')).show();
    cargarLotesAct();
    cargarPfaAct();
};

const validarAct = () => {
    const inicio = valorAct('inicio');
    const fin = valorAct('fin');
    const horaMal = Boolean(inicio) !== Boolean(fin) || Boolean(inicio && fin && fin <= inicio);
    const dia = valorAct('fecha');
    const malas = act.trabajadores.some((trabajador) => Number.isNaN(aNumero(trabajador.valor)));

    marcarAct('fecha', !dia || dia < hoyIso(), dia ? 'La fecha no puede ser anterior a hoy.' : 'Seleccione la fecha del plan.');
    ['finca', 'lote', 'um'].forEach((campo) => marcarAct(campo, !valorAct(campo)));
    if (!valorAct('labor')) marcarAct('labor', true, 'Seleccione la labor.');
    marcarAct('inicio', horaMal);
    $id('ig_act_fin').classList.toggle('is-invalid', horaMal);
    $id('ig_act_error_horario').textContent = inicio && fin ? 'La hora fin debe ser posterior a la de inicio.' : 'Indique la hora de inicio y la hora fin, o deje ambas vacías.';
    mostrar($id('ig_act_error_horario'), horaMal);
    marcarAct('terceros', !act.trabajadores.length);
    document.querySelectorAll('#ig_act_filas [data-reparto]').forEach((campo) => campo.classList.toggle('is-invalid', Number.isNaN(aNumero(campo.value))));
    $id('ig_act_error_cantidades').textContent = texto(valorAct('um')).toUpperCase() === 'RAC'
        ? 'Hay cantidades inválidas. Use números enteros para racimos.'
        : 'Hay cantidades inválidas. Use números mayores o iguales a cero con máximo 3 decimales.';
    mostrar($id('ig_act_error_cantidades'), malas);

    const primero = $id('ig_act_form').querySelector('.is-invalid');

    if (!primero) return true;

    primero.scrollIntoView({ block: 'center', behavior: animar() ? 'smooth' : 'auto' });
    (primero.querySelector('.select2-selection') || primero).focus();

    return false;
};

const guardandoAct = (activo) => {
    const boton = $id('ig_act_guardar');

    act.guardando = activo;
    boton.disabled = activo;
    boton.innerHTML = activo
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…'
        : '<i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar';
    $id('ig_act_cancelar').disabled = activo;
    $id('ig_act_cerrar').disabled = activo;
};

const guardarAct = async () => {
    if (act.guardando || !validarAct()) return;

    const dia = valorAct('fecha');
    const actividad = {
        finca: valorAct('finca'),
        lote: valorAct('lote'),
        novedad: valorAct('labor'),
        uMedida: valorAct('um'),
        referencia: grupoAct() === '02' ? valorAct('pfa') : '',
        observacion: texto(valorAct('obs')),
        horaInicio: valorAct('inicio'),
        horaFin: valorAct('fin'),
        trabajadores: act.trabajadores.map((trabajador) => ({ tercero: trabajador.tercero, cantidad: aNumero(trabajador.valor) }))
    };

    guardandoAct(true);

    try {
        const respuesta = await pedir('agregar', { empresa: estado.filtros.empresa, fecha: dia, actividad: JSON.stringify(actividad) }, URL_PL);

        guardandoAct(false);
        act.forzar = true;
        bootstrap.Modal.getOrCreateInstance($id('ig_act_modal')).hide();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Actividad planeada para el ${diaMes(dia)}`,
            html: escaparHtml(respuesta.message),
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
    } catch (error) {
        guardandoAct(false);

        const permiso = [401, 403].includes(error.status);

        Swal.fire({ icon: permiso || error.status === 422 ? 'warning' : 'error', title: permiso ? 'Acción no permitida' : 'No se pudo planear la actividad', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }
};

if ($id('ig_act_modal')) {
    const comunAct = { width: '100%', language: IDIOMA_PLAN, dropdownParent: $('#ig_act_modal') };

    $('#ig_act_finca, #ig_act_labor, #ig_act_um').select2({ ...comunAct, placeholder: 'Seleccione una opción…' });
    $('#ig_act_lote').select2(comunAct);
    $('#ig_act_terceros').select2({
        ...comunAct,
        minimumInputLength: 3,
        placeholder: 'Busque por código, identificación o nombre',
        templateResult: (opcion) => (opcion.nit
            ? $(`<span class="d-flex align-items-baseline gap-2">${opcion.codigo ? `<span class="gp-chip-fila verde font-monospace fw-semibold">${escaparHtml(opcion.codigo)}</span>` : ''}<span class="text-truncate">${escaparHtml(opcion.text)}</span><span class="gp-subtexto font-monospace ms-auto">${escaparHtml(opcion.nit)}</span></span>`)
            : opcion.text),
        templateSelection: (opcion) => (opcion.id
            ? $(`<span><span class="font-monospace">${escaparHtml(opcion.codigo || opcion.id)}</span> ${escaparHtml(texto(opcion.text).split(/\s+/).slice(0, 2).join(' '))}</span>`)
            : opcion.text),
        ajax: {
            delay: 250,
            data: (params) => ({ termino: params.term }),
            transport: (params, exito, fallo) => {
                pedir('terceros', { empresa: estado.filtros.empresa, termino: params.data.termino }, URL_PL).then(exito).catch(fallo);

                return { abort: () => { } };
            },
            processResults: (datos) => ({ results: (datos.filas || []).map((fila) => ({ id: texto(fila.nit), text: texto(fila.razonSocial), nit: texto(fila.nit), codigo: texto(fila.codigo) })) })
        }
    }).on('change', () => {
        act.trabajadores = $('#ig_act_terceros').select2('data').filter((opcion) => opcion.id).map((opcion) => act.trabajadores.find((trabajador) => trabajador.tercero === opcion.id)
            || { tercero: opcion.id, codigo: texto(opcion.codigo), nombre: texto(opcion.text), valor: '' });
        marcarAct('terceros', false);
        pintarRepartoAct();
    });

    $('#ig_act_finca').on('change', () => {
        act.lote = '';
        marcarAct('finca', false);
        pintarContextoAct();
        cargarLotesAct();
        cargarPfaAct();
    });
    $('#ig_act_lote').on('change', () => {
        act.lote = valorAct('lote');
        marcarAct('lote', false);
        pintarContextoAct();
        laborUrgente();
    });
    $('#ig_act_labor').on('change', laborCambiada);
    $('#ig_act_um').on('change', () => {
        marcarAct('um', false);
        pintarTotalAct();
    });
    $id('ig_act_todas').addEventListener('change', pintarLabores);
    $id('ig_act_codigo').addEventListener('change', () => {
        const codigo = texto(valorAct('codigo'));

        if (!codigo || elegirCodigo(codigo)) return;

        $id('ig_act_codigo').classList.add('is-invalid');
        $id('ig_act_error_labor').textContent = 'No existe una labor con ese código.';
        mostrar($id('ig_act_error_labor'), true);
    });
    $id('ig_act_fecha').addEventListener('change', () => {
        marcarAct('fecha', false);
        pintarFechaAct();
        cargarPfaAct();
    });
    ['ig_act_inicio', 'ig_act_fin'].forEach((id) => $id(id).addEventListener('change', () => {
        ['ig_act_inicio', 'ig_act_fin'].forEach((campo) => $id(campo).classList.remove('is-invalid'));
        mostrar($id('ig_act_error_horario'), false);
    }));
    $id('ig_act_obs').addEventListener('input', () => {
        $id('ig_act_contador_obs').textContent = `${valorAct('obs').length} / 250`;
    });
    $id('ig_act_filas').addEventListener('input', (evento) => {
        const campo = evento.target.closest('[data-reparto]');

        if (!campo) return;

        act.trabajadores[Number(campo.dataset.reparto)].valor = campo.value;
        campo.classList.toggle('is-invalid', Number.isNaN(aNumero(campo.value)));
        mostrar($id('ig_act_error_cantidades'), false);
        pintarTotalAct();
    });
    $id('ig_act_filas').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-quitar]');

        if (boton) $('#ig_act_terceros').val(($('#ig_act_terceros').val() || []).filter((nit) => nit !== boton.dataset.quitar)).trigger('change');
    });
    $id('ig_act_form').addEventListener('click', (evento) => {
        const reintentar = evento.target.closest('[data-reintentar]')?.dataset.reintentar;

        if (reintentar === 'lote') cargarLotesAct();
        if (reintentar === 'pfa') cargarPfaAct(true);
    });
    $id('ig_act_form').addEventListener('submit', (evento) => {
        evento.preventDefault();
        guardarAct();
    });
    $id('ig_act_modal').addEventListener('keydown', (evento) => {
        if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 's') {
            evento.preventDefault();
            guardarAct();
        }
    });
    $id('ig_act_modal').addEventListener('hide.bs.modal', async (evento) => {
        if (act.guardando) {
            evento.preventDefault();
            return;
        }
        if (act.forzar || firmaAct() === act.original) return;

        evento.preventDefault();

        const { isConfirmed } = await Swal.fire({ icon: 'question', title: '¿Descartar la actividad?', text: 'Se perderán los datos ingresados.', showCancelButton: true, confirmButtonText: 'Descartar', cancelButtonText: 'Seguir editando', focusCancel: true });

        if (!isConfirmed) return;

        act.forzar = true;
        bootstrap.Modal.getOrCreateInstance($id('ig_act_modal')).hide();
    });
    $id('ig_act_modal').addEventListener('hidden.bs.modal', () => {
        if (act.origen?.isConnected) act.origen.focus();
    });
}

$('#filtro_finca').select2({
    width: '100%',
    language: { noResults: () => 'No se encontraron resultados' }
}).on('change', () => {
    if ($id('filtro_finca').value) marcarFinca(false);
    revisarVigencia();
});

['filtro_anio', 'filtro_mes', 'filtro_fecha'].forEach((id) => $id(id).addEventListener('change', revisarVigencia));
$id('filtro_empresa').addEventListener('change', cargarFincas);

$id('form_filtros').addEventListener('submit', (evento) => {
    evento.preventDefault();
    consultar();
});

document.querySelectorAll('input[name="unidad"]').forEach((radio) => radio.addEventListener('change', () => {
    estado.unidad = radio.value;
    if (estado.respuesta) pintar();
}));

document.querySelectorAll('.ig-informe-tab').forEach((pestana) => pestana.addEventListener('click', () => {
    document.querySelectorAll('.ig-informe-tab').forEach((otra) => otra.setAttribute('aria-selected', String(otra === pestana)));
    estado.informe = pestana.dataset.informe;
    ajustarFiltros();
    consultar();
}));

window.addEventListener('resize', () => estado.graficas.mapa?.resize());

$id('resultado_cuerpo').addEventListener('click', (evento) => {
    const toggle = evento.target.closest('[data-toggle]');
    const nodo = evento.target.closest('[data-nodo]');
    const accion = evento.target.closest('[data-accion]')?.dataset.accion;
    const kpiEstado = evento.target.closest('.ig-ciclo-kpi');
    const filaLote = evento.target.closest('#tabla_ciclo tr[data-i]');

    if (accion?.startsWith('planear')) {
        abrirPlan(evento.target.closest('[data-accion]'));
    } else if (kpiEstado) {
        filtrarEstado(kpiEstado.dataset.estado);
    } else if (evento.target.closest('#btn_ver_todos')) {
        filtrarEstado(null);
    } else if (filaLote) {
        activarLote(Number(filaLote.dataset.i), false);
    } else if (accion === 'restablecer') {
        estado.graficas.mapa?.dispatchAction({ type: 'hideTip' });
        if (estado.geo) {
            restablecerGeo();
        } else {
            estado.graficas.mapa?.setOption(opcionesMapa(), true);
            estado.activo = null;
            document.querySelectorAll('#tabla_ciclo tr.ig-activa').forEach((filaLote) => filaLote.classList.remove('ig-activa'));
            botonMapa();
        }
    } else if (accion === 'reintentar-palmas') {
        actualizarPalmas();
    } else if (toggle) {
        const clave = toggle.dataset.toggle;

        estado.abiertos.has(clave) ? estado.abiertos.delete(clave) : estado.abiertos.add(clave);
        pintarFilas();
        document.querySelector(`#tabla_arbol [data-toggle="${clave}"]`)?.focus();
    } else if (nodo) {
        seleccionarNodo(estado.indice.get(nodo.dataset.nodo));
    } else if (evento.target.closest('#btn_ver_total')) {
        seleccionarNodo({ _k: '', nombre: 'Total general', meses: estado.respuesta.meses || [] });
    } else if (accion === 'expandir') {
        estado.abiertos = new Set([...estado.indice.values()].filter((item) => (item.hijos || []).length).map((item) => item._k));
        pintarFilas();
    } else if (accion === 'contraer') {
        estado.abiertos = new Set();
        pintarFilas();
    } else if (accion === 'reintentar') {
        consultar();
    }
});

$id('resultado_cuerpo').addEventListener('keydown', (evento) => {
    const nodo = evento.target.closest('[data-nodo]');
    const filaLote = evento.target.closest('#tabla_ciclo tr[data-i]');

    if ((nodo || filaLote) && (evento.key === 'Enter' || evento.key === ' ')) {
        evento.preventDefault();
        nodo ? seleccionarNodo(estado.indice.get(nodo.dataset.nodo)) : activarLote(Number(filaLote.dataset.i), false);
    }
});

$id('resultado_cuerpo').addEventListener('mouseover', (evento) => {
    const filaLote = evento.target.closest('#tabla_ciclo tr[data-i]');

    if (filaLote && filaLote.dataset.i !== estado.hover) {
        estado.hover = filaLote.dataset.i;
        resaltarMapa(Number(estado.hover));
    }
});

$id('resultado_cuerpo').addEventListener('mouseout', (evento) => {
    if (evento.target.closest('#tabla_ciclo tbody') && !evento.relatedTarget?.closest?.('#tabla_ciclo tbody')) {
        estado.hover = null;
        resaltarMapa(null);
    }
});

$id('resultado_cuerpo').innerHTML = `<div class="card mb-3">${estadoVacio('bi-funnel', 'Seleccione los filtros y presione Consultar', '<p class="text-muted mb-0">El informe se calcula con el mes de corte elegido.</p>')}</div>`;
consultar();
