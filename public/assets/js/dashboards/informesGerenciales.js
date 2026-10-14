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
    compacto: new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 })
};

const estado = { informe: INFORMES_GERENCIALES.informes[0]?.clave || '', unidad: 'kg', turno: 0, respuesta: null, filtros: null, textos: null, indice: new Map(), abiertos: new Set(), nodo: null, graficas: {}, g3Abierto: false };

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

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;

    try {
        respuesta = await fetch(URL_IG + ruta, { method: 'POST', body: cuerpo, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
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

const valores = () => ({ empresa: $id('filtro_empresa').value, anio: $id('filtro_anio').value, mes: $id('filtro_mes').value, finca: $id('filtro_finca').value });

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

const destruirGraficas = () => {
    Object.values(estado.graficas).forEach((grafica) => grafica?.destroy());
    estado.graficas = {};
};

const pintarContexto = () => {
    const corte = estado.respuesta?.corte || {};
    const chips = [
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

const pintar = () => {
    const respuesta = estado.respuesta;
    const avisos = (respuesta.avisos || []).length
        ? `<div class="gp-nota-alerta mb-3" role="alert"><div class="d-flex gap-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><ul class="mb-0 ps-3">${respuesta.avisos.map((aviso) => `<li>${escaparHtml(aviso)}</li>`).join('')}</ul></div></div>`
        : '';

    destruirGraficas();
    pintarContexto();

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
    estado.filtros = filtros;
    estado.textos = { empresa: textoSeleccion('filtro_empresa'), finca: filtros.finca ? textoSeleccion('filtro_finca') : 'Todas las fincas' };
    ocupado(true);
    revisarVigencia();
    $id('chips_contexto').innerHTML = '';
    $id('resultado_cuerpo').innerHTML = skeleton();

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
    estado.indice = new Map();
    indexar(respuesta.arbol || []);
    estado.abiertos = new Set((respuesta.arbol || []).filter((nodo) => nodo.nivel === 'empresa').map((nodo) => nodo._k));
    estado.nodo = { _k: '', nombre: 'Total general', meses: respuesta.meses || [] };
    pintar();
};

const cargarFincas = async () => {
    const select = $id('filtro_finca');

    select.innerHTML = '<option value="">Todas las fincas</option>';
    $(select).trigger('change');

    try {
        const resultado = await pedir('fincas', { empresa: $id('filtro_empresa').value });
        const fincas = Array.isArray(resultado) ? resultado : (resultado.fincas || []);

        select.innerHTML += fincas.map((finca) => `<option value="${escaparHtml(finca.codigo)}">${escaparHtml(`${texto(finca.codigo)} — ${texto(finca.nombre)}`)}</option>`).join('');
        $(select).trigger('change');
    } catch (error) {
        Swal.fire({ icon: error.status === 403 ? 'warning' : 'error', title: 'No se pudieron cargar las fincas', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }
};

$('#filtro_finca').select2({
    width: '100%',
    language: { noResults: () => 'No se encontraron resultados' }
}).on('change', revisarVigencia);

['filtro_anio', 'filtro_mes'].forEach((id) => $id(id).addEventListener('change', revisarVigencia));
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
    consultar();
}));

$id('resultado_cuerpo').addEventListener('click', (evento) => {
    const toggle = evento.target.closest('[data-toggle]');
    const nodo = evento.target.closest('[data-nodo]');
    const accion = evento.target.closest('[data-accion]')?.dataset.accion;

    if (toggle) {
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

    if (nodo && (evento.key === 'Enter' || evento.key === ' ')) {
        evento.preventDefault();
        seleccionarNodo(estado.indice.get(nodo.dataset.nodo));
    }
});

$id('resultado_cuerpo').innerHTML = `<div class="card mb-3">${estadoVacio('bi-funnel', 'Seleccione los filtros y presione Consultar', '<p class="text-muted mb-0">El informe se calcula con el mes de corte elegido.</p>')}</div>`;
consultar();
