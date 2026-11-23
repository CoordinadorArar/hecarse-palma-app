const URL_DATOS = BASE_URL + 'dashboards/bascula/datos';
const URL_EXCEL = BASE_URL + 'dashboards/bascula/excel';
const CLAVE_COLAPSADAS = 'informeBascula.colapsadas';
const HTML_EXCEL = '<i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Exportar a Excel';
const COL_FACT = 'col-6 col-lg-3';
const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
const MAX_DIAS = 366;
const MAX_DIARIO = 92;
const FECHA_MIN = '2000-01-01';
const PALETA = ['#2f7a4b', '#41a867', '#7cc49a', '#4a5b6c', '#f0a500', '#5b8def'];
const ESTADOS = {
    cumple: { nombre: 'Cumple', corto: 'Cumple', clase: 'ig-e-normal', icono: 'bi-check-circle' },
    fuera_regla: { nombre: 'Fuera de regla', corto: 'Fuera de regla', clase: 'ig-e-vencido', icono: 'bi-exclamation-octagon' },
    sin_tlc: { nombre: 'Sin TLC', corto: 'Sin TLC', clase: 'ig-e-seguimiento', icono: 'bi-link-45deg' },
    incompleto: { nombre: 'Pesaje incompleto', corto: 'Incompleto', clase: 'ig-e-alerta', icono: 'bi-hourglass-split' }
};
const ORDEN_FILTRO = ['fuera_regla', 'cumple', 'sin_tlc', 'incompleto'];
const TABS = [['panel_viajes', 'bi-arrow-left-right', 'Báscula vs extractora'], ['panel_placas', 'bi-truck-front', 'Placas por día'], ['panel_conductores', 'bi-person-badge', 'Conductores'], ['panel_facturado', 'bi-receipt-cutoff', 'Báscula vs facturado']];
const ZERO_RECORDS = 'Ningún registro coincide con la búsqueda';
const SIN_EXTRACTORA = { 'SIN DESTINO': '#c5ccd3', 'MOVIMIENTO INTERNO': '#9aa5b1' };
const DOM_TABLA = "<'gp-dt-barra'lf><'table-responsive't><'gp-dt-barra gp-dt-pie'ip>";

const $id = (id) => document.getElementById(id);
const texto = (valor) => String(valor ?? '').trim();
const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const FORMATOS = {
    kg: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0, useGrouping: 'always' }),
    kgSigno: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0, useGrouping: 'always', signDisplay: 'exceptZero' }),
    pct: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 4 }),
    pctEje: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    entero: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0, useGrouping: 'always' }),
    compacto: new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 }),
    cop: new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
};

const nulo = (valor) => valor == null || valor === '' || Number.isNaN(Number(valor));
const f = (clave, valor, unidad = '') => (nulo(valor) ? '—' : FORMATOS[clave].format(Number(valor)) + unidad);
const NULO = '<span class="text-muted">—</span>';
const movil = () => window.innerWidth < 768;
const alto = () => (movil() ? 260 : 320);
const pad = (numero) => String(numero).padStart(2, '0');
const partes = (iso) => texto(iso).substring(0, 10).split('-').map(Number);
const fecha = (iso) => {
    const [anio, mes, dia] = partes(iso);
    return anio && mes && dia ? `${pad(dia)}/${pad(mes)}/${anio}` : '—';
};
const hora = (valor) => texto(valor).substring(11, 16);
const diaSemana = (iso) => {
    const [anio, mes, dia] = partes(iso);
    return DIAS[new Date(anio, mes - 1, dia).getDay()] || '';
};
const mesTexto = (valor) => {
    const [anio, mes] = partes(valor);
    return anio && mes ? `${MESES[mes - 1]} ${anio}` : texto(valor);
};
const isoDe = (dia) => `${dia.getFullYear()}-${pad(dia.getMonth() + 1)}-${pad(dia.getDate())}`;
const sumarDias = (iso, dias) => {
    const [anio, mes, dia] = partes(iso);
    return isoDe(new Date(anio, mes - 1, dia + dias));
};
const diasEntre = (desde, hasta) => {
    const [a1, m1, d1] = partes(desde);
    const [a2, m2, d2] = partes(hasta);
    return Math.round((Date.UTC(a2, m2 - 1, d2) - Date.UTC(a1, m1 - 1, d1)) / 86400000) + 1;
};
const rangoTexto = (desde, hasta) => (desde === hasta ? `el ${fecha(desde)}` : `entre ${fecha(desde)} y ${fecha(hasta)}`);
const RANGOS = {
    mes: (hoy) => [isoDe(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), isoDe(hoy)],
    anterior: (hoy) => [isoDe(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)), isoDe(new Date(hoy.getFullYear(), hoy.getMonth(), 0))],
    treinta: (hoy) => [isoDe(new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() - 29)), isoDe(hoy)]
};
const truncar = (valor, largo = 22) => (valor.length > largo ? valor.slice(0, largo - 1) + '…' : valor);
const nombreConductor = (fila) => texto(fila.conductor) || 'Sin nombre';
const nombrePropio = (valor) => texto(valor).toLocaleLowerCase('es').replace(/(^|\s)(\S)/g, (_, espacio, letra) => espacio + letra.toLocaleUpperCase('es'));
const fincasViaje = (fila) => (Array.isArray(fila.fincas) ? fila.fincas : []).map(String);
const nombresFincas = (codigos) => codigos.map((codigo) => `${codigo} ${estado.fincas[codigo] || ''}`.trim()).join(' · ');

const estado = { turno: 0, respuesta: null, graficas: {}, tablas: [], pendientes: {}, tab: 'panel_viajes', filtro: null, regla: 100, tablaViajes: null, fincas: {}, firmaFincas: '', esperaFincas: null, colapsadas: [], empresa: '', ocupado: false, exportando: false };

const leerColapsadas = () => {
    try {
        const lista = JSON.parse(localStorage.getItem(CLAVE_COLAPSADAS) || '[]');

        return Array.isArray(lista) ? lista.map(String) : [];
    } catch (error) {
        return [];
    }
};

const guardarColapsada = (clave, colapsada) => {
    const lista = leerColapsadas().filter((item) => item !== clave);

    try {
        localStorage.setItem(CLAVE_COLAPSADAS, JSON.stringify(colapsada ? [...lista, clave] : lista));
    } catch (error) {
        return;
    }
};

const estadoVacio = (icono, titulo, cuerpo, extra = '') => `
    <div class="gp-estado-vacio text-center py-5">
        <div class="gp-medallon ${extra} d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi ${icono}" aria-hidden="true"></i></div>
        <h6 class="mb-1">${titulo}</h6>
        ${cuerpo}
    </div>`;

const enviar = async (url, datos) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => (Array.isArray(valor) ? valor.forEach((item) => cuerpo.append(`${clave}[]`, item)) : cuerpo.append(clave, valor ?? '')));

    try {
        return await fetch(url, { method: 'POST', body: cuerpo, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    } catch (error) {
        throw Object.assign(new Error('Error de conexión.'), { status: 0 });
    }
};

const pedir = async (datos) => {
    const respuesta = await enviar(URL_DATOS, datos);
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

const ocupado = (activo) => {
    const boton = $id('btn_consultar');
    const cuerpo = $id('resultado_cuerpo');

    boton.disabled = activo || !rango().valido;
    boton.innerHTML = activo
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Actualizando…'
        : '<i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar';
    cuerpo.setAttribute('aria-busy', activo ? 'true' : 'false');
    cuerpo.classList.toggle('inf-desactualizado', activo && Boolean(estado.respuesta));
    estado.ocupado = activo;
    habilitarExcel();
};

const habilitarExcel = () => {
    if (!estado.exportando) $id('btn_excel').disabled = estado.ocupado || !estado.respuesta || !rango().valido;
};

const kpiEsqueleto = (col) => `<div class="${col}"><div class="inf-kpi d-block"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-8 placeholder-lg"></span><span class="placeholder col-10 mt-2"></span></div></div>`;

const skeleton = () => `<div class="placeholder-glow" aria-hidden="true">
    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-4"><div class="inf-kpi d-block"><span class="placeholder col-7 mb-2"></span><span class="placeholder col-5 placeholder-lg mb-2"></span><span class="placeholder col-10 mb-2"></span><span class="placeholder w-100"></span></div></div>
        <div class="col-12 col-xl-8"><div class="row g-3 h-100">${kpiEsqueleto('col-6 col-md-3 col-xl-6').repeat(4)}</div></div>
    </div>
    <div class="row g-3 mb-3">${kpiEsqueleto('col-6 col-md-4 col-xl').repeat(5)}</div>
    <div class="card mb-3"><div class="card-body pt-3"><span class="placeholder col-4 mb-3"></span><span class="placeholder w-100 rounded ig-esqueleto"></span></div></div>
</div>`;

const destruir = () => {
    estado.tablas.forEach((tabla) => tabla.destroy());
    Object.values(estado.graficas).forEach((grafica) => grafica.destroy());
    estado.tablas = [];
    estado.graficas = {};
    estado.pendientes = {};
    estado.tablaViajes = null;
};

const badgeEstado = (clave) => {
    const e = ESTADOS[clave];

    return e ? `<span class="ig-chip ${e.clase}"><i class="bi ${e.icono} me-1" aria-hidden="true"></i>${e.corto}</span>` : NULO;
};

const celdaDif = (valor, fila) => (nulo(valor) ? NULO : `<span${fila?.estado === 'fuera_regla' ? ' class="ig-texto-rojo fw-semibold"' : (Number(valor) < 0 ? ' class="text-muted"' : '')}>${f('kgSigno', valor)}</span>`);
const celdaPct = (valor) => (nulo(valor) ? NULO : `<span class="text-muted">${f('pct', valor, ' %')}</span>`);
const chipPct = (valor) => (nulo(valor) ? NULO : `<span class="text-muted">${f('pct', valor, ' %')}</span>`);
const celdaKgCero = (valor) => (Number(valor) ? f('kg', valor) : `<span class="text-muted">${f('kg', 0)}</span>`);
const placa = (valor) => (texto(valor) ? `<span class="badge gp-badge-doc font-monospace">${escaparHtml(valor)}</span>` : NULO);

const conteos = (k) => ({ cumple: Number(k.cumplen) || 0, fuera_regla: Number(k.fueraRegla) || 0, sin_tlc: Number(k.sinTlc) || 0, incompleto: Number(k.incompletos) || 0 });

const kpiRegla = (k, n) => {
    const regla = estado.regla;
    const cruzados = Number(k.cruzados) || 0;
    const sinPesajes = !(Number(k.kgBascula) > 0);
    const sin = sinPesajes || cruzados === 0;
    const cumple = typeof k.cumpleMes === 'boolean' ? k.cumpleMes : n.fuera_regla === 0;
    const chip = sin
        ? `<span class="ig-chip ig-e-sin-dato">${sinPesajes ? 'Sin pesajes' : 'Sin cruce TLC'}</span>`
        : (cumple ? '<span class="ig-chip verde"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Cumple</span>' : '<span class="ig-chip rojo"><i class="bi bi-x-octagon me-1" aria-hidden="true"></i>No cumple</span>');
    const claves = Object.keys(ESTADOS);
    const segmentos = claves.filter((clave) => n[clave] > 0).map((clave) => `<span class="${ESTADOS[clave].clase}" style="flex:${n[clave]} 1 0" title="${ESTADOS[clave].nombre}: ${f('entero', n[clave])}"></span>`).join('');
    const leyenda = claves.map((clave) => `<span class="d-inline-flex align-items-center gap-1"><span class="ig-leyenda-punto ${ESTADOS[clave].clase}" aria-hidden="true"></span>${ESTADOS[clave].corto} ${f('entero', n[clave])}</span>`).join('');
    const resumen = claves.map((clave) => `${ESTADOS[clave].nombre}: ${f('entero', n[clave])}`).join(', ');

    return `<div class="col-12 col-xl-4">
        <div class="inf-kpi ba-regla${sin ? '' : (cumple ? ' ig-kpi-ok' : ' ig-kpi-critico')}">
            <div class="d-flex align-items-center justify-content-between gap-2"><span class="gp-etiqueta-mini" title="${escaparHtml(`Un viaje queda fuera de regla si los kg de báscula superan a los de extractora en más de ${f('kg', regla, ' kg')}. Los kg de más en extractora no compensan.`)}">Regla de kg no llegados · máx. ${f('kg', regla, ' kg')} por viaje</span>${chip}</div>
            <div class="d-flex flex-wrap align-items-baseline gap-2"><span class="ba-regla-valor${!sin && n.fuera_regla > 0 ? ' ig-texto-rojo' : ''}">${sin ? '—' : f('entero', n.fuera_regla)}</span>${sin ? '' : `<span class="tb-variacion text-muted">de ${f('entero', cruzados)} ${cruzados === 1 ? 'viaje cruzado' : 'viajes cruzados'} fuera de regla</span>`}</div>
            <span class="gp-subtexto">${cruzados ? `% no llegados del periodo: ${f('pct', k.pctNoLlegados, ' %')} · referencia` : 'Sin viajes cruzados con TLC en el periodo'}</span>
            <div class="ba-distribucion" role="img" aria-label="${escaparHtml(`Distribución de viajes. ${resumen}`)}">${segmentos}</div>
            <div class="ig-leyenda">${leyenda}</div>
        </div>
    </div>`;
};

const kpi = (icono, etiqueta, valor, sub, claseIcono = '', ayuda = sub, columna = 'col-6 col-md-3 col-xl-6', claseKpi = '') => `<div class="${columna} min-w-0">
    <div class="inf-kpi${claseKpi ? ` ${claseKpi}` : ''}">
        <span class="gp-icono${claseIcono ? ` ${claseIcono}` : ''} rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi ${icono}" aria-hidden="true"></i></span>
        <div class="min-w-0 flex-grow-1">
            <span class="gp-etiqueta-mini d-block">${etiqueta}</span>
            <div class="inf-kpi-valor" title="${valor}">${valor}</div>
            <div class="gp-subtexto text-truncate" title="${ayuda}">${sub}</div>
        </div>
    </div>
</div>`;

const kpiEstado = (clave, cantidad, kg) => {
    const e = ESTADOS[clave];
    const sub = clave === 'incompleto' ? 'Sin segunda pesada' : f('kg', kg, ' kg');

    return `<div class="col-6 col-md-4 col-xl">
        <button type="button" class="inf-kpi ig-ciclo-kpi ${e.clase}" data-estado="${clave}" aria-pressed="${estado.filtro === clave}"${cantidad ? '' : ' disabled'}>
            <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi ${e.icono}" aria-hidden="true"></i></span>
            <span class="min-w-0 flex-grow-1">
                <span class="gp-etiqueta-mini d-block">${e.nombre}</span>
                <span class="inf-kpi-valor d-block">${f('entero', cantidad)}</span>
                <span class="gp-subtexto text-truncate">${sub}</span>
            </span>
        </button>
    </div>`;
};

const pintarKpis = (k, n, viajes) => {
    const kgEstado = viajes.reduce((total, viaje) => ({ ...total, [viaje.estado]: (total[viaje.estado] || 0) + (Number(viaje.kgBascula) || 0) }), {});
    const tlc = Number(k.tlcSinPesaje) || 0;
    const plural = (cantidad) => `${f('entero', cantidad)} ${cantidad === 1 ? 'viaje' : 'viajes'}`;
    const sinTlc = Number(k.sinTlc) || 0;
    const conFaltante = Number(k.viajesFaltante) || 0;
    const sobrante = Number(k.kgSobrante) || 0;
    const subNoLlegados = [conFaltante ? `${plural(conFaltante)} con faltante` : '', sinTlc ? `${f('entero', sinTlc)} sin TLC` : ''].filter(Boolean).join(' · ') || 'Sin faltantes';
    const ayudaNoLlegados = `Faltantes de viajes cruzados: ${f('kg', (Number(k.kgNoLlegados) || 0) - (Number(k.kgSinTlc) || 0), ' kg')} en ${plural(conFaltante)} + peso neto de viajes sin TLC: ${f('kg', k.kgSinTlc || 0, ' kg')} en ${plural(sinTlc)}. Los kg de más en extractora no compensan.`;
    const subExtractora = sobrante > 0 ? `+${f('kg', sobrante, ' kg')} de más en ${plural(Number(k.viajesSobrante) || 0)}` : 'Solo viajes cruzados';

    return `<div class="row g-3 mb-3">
        ${kpiRegla(k, n)}
        <div class="col-12 col-xl-8"><div class="row g-3 h-100">
            ${kpi('bi-truck', 'Viajes en báscula', f('entero', k.viajes), `${f('entero', k.cruzados)} cruzados · ${f('entero', k.sinTlc)} sin TLC`)}
            ${kpi('bi-box-seam', 'Kg báscula', f('kg', k.kgBascula, ' kg'), 'Peso neto finca')}
            ${kpi('bi-buildings', 'Kg extractora', f('kg', k.kgExtractora, ' kg'), subExtractora, '', sobrante > 0 ? 'Kg que la extractora registró por encima de la báscula. No se restan de los kg no llegados.' : subExtractora)}
            ${kpi('bi-graph-down-arrow', 'Kg no llegados', f('kg', k.kgNoLlegados, ' kg'), subNoLlegados, Number(k.kgNoLlegados) > 0 ? 'ba-icono-faltante' : '', ayudaNoLlegados)}
        </div></div>
    </div>
    <div class="row g-3 mb-3">
        ${Object.keys(ESTADOS).map((clave) => kpiEstado(clave, n[clave], kgEstado[clave])).join('')}
        <div class="col-6 col-md-4 col-xl">
            <div class="inf-kpi${tlc ? ' ig-kpi-alerta' : ''}" title="Tiquetes de extractora sin viaje en báscula">
                <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <span class="gp-etiqueta-mini d-block">TLC sin pesaje</span>
                    <div class="inf-kpi-valor">${f('entero', tlc)}</div>
                    <div class="gp-subtexto text-truncate">${f('kg', k.kgTlcSinPesaje || 0, ' kg')}</div>
                </div>
            </div>
        </div>
    </div>`;
};

const opcionesBase = (altura) => ({
    chart: { height: altura, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { speed: 300 }, parentHeightOffset: 0 },
    dataLabels: { enabled: false },
    grid: { borderColor: '#eef1f5' },
    legend: { position: movil() ? 'bottom' : 'top', horizontalAlign: 'left', formatter: (nombre) => escaparHtml(nombre) },
    tooltip: { shared: true, intersect: false },
    plotOptions: { bar: { columnWidth: '58%', borderRadius: 3, borderRadiusApplication: 'end' } },
    xaxis: { labels: { style: { colors: '#5c6b7a' } } },
    yaxis: { min: 0, labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => FORMATOS.compacto.format(valor) } }
});

const construirEjes = (desde, hasta) => {
    const total = Math.max(diasEntre(desde, hasta), 1);
    const dias = Array.from({ length: total }, (_, indice) => sumarDias(desde, indice));

    if (total > MAX_DIARIO) {
        const meses = [...new Set(dias.map((dia) => dia.substring(0, 7)))];
        const etiquetas = meses.map((mes) => `${MESES_CORTOS[Number(mes.substring(5, 7)) - 1]} ${mes.substring(0, 4)}`);

        return { mensual: true, largo: 7, claves: meses, etiquetas, titulos: etiquetas, visibles: new Set(etiquetas) };
    }

    const unMes = desde.substring(0, 7) === hasta.substring(0, 7);
    const paso = Math.ceil(total / (movil() ? 8 : 16));
    const etiquetas = dias.map((dia) => (unMes ? dia.substring(8, 10) : fecha(dia).substring(0, 5)));

    return { mensual: false, largo: 10, claves: dias, etiquetas, titulos: dias.map((dia) => `${diaSemana(dia)} ${fecha(dia)}`), visibles: new Set(etiquetas.filter((_, indice) => indice % paso === 0)) };
};

const agrupar = (lista, ejes) => (lista || []).reduce((grupos, fila) => {
    const clave = texto(fila.fecha).substring(0, ejes.largo);

    (grupos[clave] ||= []).push(fila);
    return grupos;
}, {});

const ejeX = (base, ejes) => ({ ...base.xaxis, categories: ejes.etiquetas, labels: { ...base.xaxis.labels, rotate: 0, formatter: (valor) => (ejes.visibles.has(valor) ? valor : '') }, tooltip: { enabled: false } });

const opcionesDias = (dias, ejes) => {
    const base = opcionesBase(alto());
    const grupos = agrupar(dias, ejes);
    const suma = (filas, campo) => filas.reduce((total, fila) => total + (Number(fila[campo]) || 0), 0);
    const puntos = ejes.claves.map((clave) => {
        const filas = grupos[clave];

        if (!filas) return null;

        const kgBascula = suma(filas, 'kgBasculaTotal');
        const noLlegados = suma(filas, 'kgNoLlegados');
        const pct = ejes.mensual ? (kgBascula > 0 ? (noLlegados / kgBascula) * 100 : null) : (nulo(filas[0].pctNoLlegados) ? null : Number(filas[0].pctNoLlegados));

        return { kgBascula, kgExtractora: suma(filas, 'kgExtractora'), pct, noLlegados };
    });
    const valores = (campo) => puntos.map((punto) => (punto ? punto[campo] : null));
    const pcts = valores('pct');
    const conDato = pcts.filter((valor) => valor !== null);
    const minimo = Math.min(0, ...conDato);
    const maximo = Math.max(0.01, ...conDato);
    const crudo = (maximo - minimo) / 5;
    const magnitud = 10 ** Math.floor(Math.log10(crudo));
    const paso = Math.max(0.01, [1, 2, 5, 10].map((factor) => factor * magnitud).find((valor) => valor >= crudo));
    const ejePct = { min: Math.floor(minimo / paso) * paso, max: Math.ceil(maximo / paso) * paso };
    const titulo = (indice) => ejes.titulos[indice] + (puntos[indice] ? ` · No llegados ${f('kg', puntos[indice].noLlegados, ' kg')}` : '');

    return {
        ...base,
        chart: { ...base.chart, type: 'line' },
        series: [
            { name: 'Kg báscula', type: 'column', data: valores('kgBascula') },
            { name: 'Kg extractora', type: 'column', data: valores('kgExtractora') },
            { name: '% no llegados (ref.)', type: 'line', data: pcts }
        ],
        colors: ['#2f7a4b', '#4a5b6c', '#f0a500'],
        stroke: { width: [0, 0, 2], curve: 'straight' },
        markers: { size: [0, 0, 3], strokeColors: '#fff', strokeWidth: 2 },
        xaxis: ejeX(base, ejes),
        yaxis: [
            { ...base.yaxis, seriesName: ['Kg báscula', 'Kg extractora'] },
            { seriesName: '% no llegados (ref.)', opposite: true, ...ejePct, tickAmount: Math.max(1, Math.round((ejePct.max - ejePct.min) / paso)), labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => f('pctEje', valor, ' %') } }
        ],
        tooltip: {
            shared: true,
            intersect: false,
            x: { formatter: (valor, { dataPointIndex }) => titulo(dataPointIndex) },
            y: { formatter: (valor, { seriesIndex }) => (seriesIndex === 2 ? f('pct', valor, ' %') : f('kg', valor, ' kg')) }
        }
    };
};

const opcionesPlacas = (placas, ejes) => {
    const base = opcionesBase(alto());
    const grupos = agrupar(placas.dias, ejes);
    const gris = (nombre) => SIN_EXTRACTORA[texto(nombre).toUpperCase()];
    const extractoras = [...(placas.extractoras || []).filter((nombre) => !gris(nombre)), ...(placas.extractoras || []).filter(gris)];
    const unidad = ejes.mensual ? ['placa-día', 'placas-día'] : ['placa', 'placas'];
    const contar = (clave, extractora) => (grupos[clave] || []).reduce((total, dia) => (dia.porExtractora?.[extractora] ? (total ?? 0) + (Number(dia.porExtractora[extractora].placas) || 0) : total), null);
    const totalClave = (clave) => (grupos[clave] || []).reduce((total, dia) => total + (Number(dia.total) || 0), 0);

    return {
        ...base,
        chart: { ...base.chart, type: 'bar', stacked: true },
        series: extractoras.map((extractora) => ({ name: extractora, data: ejes.claves.map((clave) => contar(clave, extractora)) })),
        colors: extractoras.map((nombre, indice) => gris(nombre) || PALETA[indice] || '#9aa5b1'),
        xaxis: ejeX(base, ejes),
        yaxis: { ...base.yaxis, decimalsInFloat: 0, forceNiceScale: true, labels: { ...base.yaxis.labels, formatter: (valor) => f('entero', valor) } },
        tooltip: {
            shared: true,
            intersect: false,
            x: { formatter: (valor, { dataPointIndex }) => `${ejes.titulos[dataPointIndex]} · ${f('entero', totalClave(ejes.claves[dataPointIndex]))} ${unidad[1]}` },
            y: { formatter: (valor) => (nulo(valor) ? '—' : `${f('entero', valor)} ${Number(valor) === 1 ? unidad[0] : unidad[1]}`), title: { formatter: (nombre) => `${escaparHtml(nombre)}: ` } }
        }
    };
};

const opcionesConductores = (lista) => {
    const base = opcionesBase(Math.max(140, lista.length * 34 + 60));
    const crudo = (Math.max(0, ...lista.map((conductor) => Number(conductor.kgBascula) || 0)) * 1.15) / 6;
    const magnitud = crudo > 0 ? 10 ** Math.floor(Math.log10(crudo)) : 0;
    const paso = [1, 2, 2.5, 5, 10].map((factor) => factor * magnitud).find((valor) => valor >= crudo);
    const fila = (etiqueta, valor) => `<div class="d-flex justify-content-between gap-3"><span class="text-muted">${etiqueta}</span><span class="fw-semibold">${valor}</span></div>`;

    return {
        ...base,
        chart: { ...base.chart, type: 'bar' },
        series: [{ name: 'Kg báscula', data: lista.map((conductor) => Number(conductor.kgBascula) || 0) }],
        colors: ['#2f7a4b'],
        plotOptions: { bar: { horizontal: true, barHeight: lista.length <= 4 ? '45%' : '70%', borderRadius: 3, borderRadiusApplication: 'end', dataLabels: { position: 'top' } } },
        dataLabels: { enabled: true, textAnchor: 'start', offsetX: 28, formatter: (valor) => FORMATOS.compacto.format(valor), style: { fontSize: '11px', fontWeight: 600, colors: ['#5c6b7a'] } },
        legend: { show: false },
        grid: { ...base.grid, padding: { right: 32 } },
        xaxis: { categories: lista.map(nombreConductor), min: 0, max: paso ? paso * 6 : undefined, tickAmount: 6, labels: { style: { colors: '#5c6b7a' }, formatter: (valor) => FORMATOS.compacto.format(valor) } },
        yaxis: { labels: { maxWidth: 200, style: { colors: '#5c6b7a' }, formatter: (valor) => truncar(String(valor ?? '')) } },
        tooltip: {
            shared: false,
            intersect: false,
            custom: ({ dataPointIndex }) => {
                const conductor = lista[dataPointIndex] || {};

                return `<div class="inf-tooltip"><div class="fw-semibold mb-1">${escaparHtml(nombreConductor(conductor))}</div>${fila('Kg báscula', f('kg', conductor.kgBascula, ' kg'))}${fila('Kg extractora', f('kg', conductor.kgExtractora, ' kg'))}${fila('Kg no llegados', f('kg', conductor.kgNoLlegados, ' kg'))}${fila('Kg de más', f('kg', conductor.kgSobrante || 0, ' kg'))}${fila('% no llegados (ref.)', f('pct', conductor.pctNoLlegados, ' %'))}</div>`;
            }
        }
    };
};

const lienzo = (id, etiqueta) => `<div class="inf-grafica" id="${id}" role="img" aria-label="${escaparHtml(etiqueta)}"></div>`;
const vacioGrafica = (mensaje = 'Sin pesajes en el periodo', icono = 'bi-bar-chart') => `<div class="tb-grafica-vacia"><i class="bi ${icono}" aria-hidden="true"></i>${escaparHtml(mensaje)}</div>`;

const seccion = (clave, icono, titulo, unidad, cuerpo) => {
    const abierta = !estado.colapsadas.includes(clave);

    return `<section>
    <div class="px-3 pt-3 pb-2 d-flex flex-wrap align-items-center gap-2">
        <h6 class="gp-seccion mb-0 me-auto border-0 pb-0"><i class="bi ${icono}" aria-hidden="true"></i>${titulo}</h6>
        ${unidad ? `<span class="gp-subtexto">${unidad}</span>` : ''}
        <button type="button" class="btn btn-link btn-sm text-success p-0 gp-seccion-toggle ba-colapsar${abierta ? '' : ' collapsed'}" data-bs-toggle="collapse" data-bs-target="#sec_${clave}" aria-expanded="${abierta}" aria-controls="sec_${clave}" aria-label="${escaparHtml(`Mostrar u ocultar ${titulo}`)}" title="Mostrar u ocultar"><i class="bi bi-chevron-down gp-chevron" aria-hidden="true"></i></button>
    </div>
    <div class="collapse${abierta ? ' show' : ''}" id="sec_${clave}"><div class="px-3 pb-3">${cuerpo}</div></div>
</section>`;
};

const tablaEstatica = (columnas, filas, pie) => {
    const celdas = (lista, etiqueta) => lista.map((celda, indice) => `<${etiqueta}${indice === 0 && etiqueta === 'th' ? ' scope="row"' : ''}${columnas[indice][1] ? ' class="inf-num"' : ''}>${celda}</${etiqueta}>`).join('');

    return `<div class="table-responsive ig-ciclo-scroll">
        <table class="table table-sm align-middle mb-0 gp-tabla inf-tabla">
            <thead class="gp-thead"><tr>${columnas.map(([titulo, clase, ayuda]) => `<th scope="col"${clase ? ` class="${clase}"` : ''}${ayuda ? ` title="${ayuda}"` : ''}>${titulo}</th>`).join('')}</tr></thead>
            <tbody>${filas.map(([clase, lista]) => `<tr${clase ? ` class="${clase}"` : ''}>${celdas(lista, 'td')}</tr>`).join('')}</tbody>
            ${pie ? `<tfoot><tr>${celdas(pie, 'th')}</tr></tfoot>` : ''}
        </table>
    </div>`;
};

const plural = (cantidad, uno, varios) => `${f('entero', cantidad)} ${cantidad === 1 ? uno : varios}`;
const noCuadra = (valor) => !nulo(valor) && Math.abs(Number(valor)) >= 1;
const celdaDifTlc = (valor) => (nulo(valor) ? NULO : (noCuadra(valor) ? `<span class="ig-texto-rojo fw-semibold">${f('kgSigno', valor)}</span>` : '<span class="text-muted">0</span><span class="gp-subtexto ig-texto-verde"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Cuadra</span>'));
const doc = (valor, tachado = false) => (texto(valor) ? `<span class="badge gp-badge-doc font-monospace${tachado ? ' text-decoration-line-through' : ''}">${escaparHtml(valor)}</span>` : NULO);
const celdaExtractora = (fila) => `<div class="gp-truncar ba-col-extractora" title="${escaparHtml(fila.extractora)}">${texto(fila.extractora) ? escaparHtml(fila.extractora) : NULO}</div>${texto(fila.nit) ? `<span class="gp-subtexto">NIT ${escaparHtml(fila.nit)}</span>` : ''}`;
const rangoHtml = (desde, hasta) => `<span class="gp-rango-fecha">${fecha(desde)} <i class="bi bi-arrow-right" aria-hidden="true"></i> ${fecha(hasta)}</span>`;
const detalleValor = (notas, ajuste) => {
    const piezas = [(Array.isArray(notas) ? notas : []).map(texto).filter(Boolean).join(', '), Number(ajuste) ? `Ajuste ${f('cop', ajuste)}` : ''].filter(Boolean);

    return piezas.length ? `<span class="gp-subtexto">${escaparHtml(piezas.join(' · '))}</span>` : '';
};

const sinPesaje = (valor, clase = '') => (Number(valor) > 0 ? `<span class="gp-subtexto ig-texto-ambar${clase}" title="TLC de la extractora en el periodo de la factura sin pesaje en báscula"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>${f('entero', valor)} TLC sin pesaje</span>` : '');

const panelFacturado = (r, periodo) => {
    const fact = r.facturacion || {};

    if (!fact.disponible) {
        const reintentar = fact.reintentar === true;

        return estadoVacio('bi-cloud-slash', 'Facturación de Siesa no disponible', `<p class="text-muted ${reintentar ? 'mb-3' : 'mb-0'}">${escaparHtml(texto(fact.mensaje) || 'No se recibió información de facturación.')}</p>${reintentar ? '<button type="button" class="btn btn-outline-success btn-sm" data-accion="reintentar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>' : ''}`, 'inf-medallon-error');
    }

    const facturas = [...(fact.facturas || [])].sort((a, b) => texto(a.inicio).localeCompare(texto(b.inicio)) || texto(a.extractora).localeCompare(texto(b.extractora)) || texto(a.documento).localeCompare(texto(b.documento)));
    const sinFacturar = fact.sinFacturar || [];
    const notas = [...(fact.anuladas || []).map((fila) => ({ ...fila, anulada: true })), ...(fact.ajustesSinFactura || [])].sort((a, b) => texto(a.fecha).localeCompare(texto(b.fecha)));

    if (!facturas.length && !sinFacturar.length && !notas.length) {
        return estadoVacio('bi-receipt', `Sin facturas de venta ${periodo}`, '<p class="text-muted mb-0">No hay facturas FVE de las extractoras en el periodo consultado.</p>');
    }

    const t = fact.totales || {};
    const total = (campo) => (nulo(t[campo]) ? sumar(facturas, campo) : Number(t[campo]));
    const kgSin = nulo(t.kgSinFacturar) ? sumar(sinFacturar, 'kg') : Number(t.kgSinFacturar);
    const tlcSin = sumar(sinFacturar, 'tlcs');
    const extractorasSin = new Set(sinFacturar.map((fila) => texto(fila.nit) || texto(fila.extractora))).size;
    const tlcFacturas = sumar(facturas, 'tlcs');
    const descuadres = facturas.filter((fila) => noCuadra(fila.difTlc)).length;
    const ajuste = total('ajusteValor');
    const resumen = `${plural(facturas.length, 'factura', 'facturas')} · ${f('cop', total('valorFacturado'))}`;
    const subTlc = descuadres ? `${f('entero', descuadres)} de ${plural(facturas.length, 'factura no cuadra', 'facturas no cuadran')}` : `Cuadra con TLC · TLC ${f('kg', total('kgTlc'), ' kg')}`;
    const aviso = (r.filtros?.fincas || []).length > 0 && fact.porFinca === false
        ? '<div class="px-3 pt-3"><div class="alert alert-info py-2 small mb-0 d-flex gap-2" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i><span>La facturación de Siesa no se discrimina por finca: este comparativo muestra el total de la empresa y no aplica el filtro de fincas.</span></div></div>'
        : '';
    const kpis = `${aviso}<div class="p-3"><div class="row g-3">
        ${kpi('bi-receipt', 'Kg facturado', f('kg', total('kgFacturado'), ' kg'), resumen, '', ajuste ? `Incluye ajustes NCE por ${f('cop', ajuste)}` : resumen, COL_FACT)}
        ${kpi('bi-arrows-collapse', 'Dif. facturado vs TLC', f('kgSigno', total('difTlc'), ' kg'), facturas.length ? subTlc : 'Sin facturas', descuadres ? 'ba-icono-faltante' : '', 'Kg facturado − kg TLC de la extractora en el periodo de cada factura', COL_FACT, facturas.length ? (descuadres ? 'ig-kpi-critico' : 'ig-kpi-ok') : '')}
        ${kpi('bi-box-seam', 'Dif. facturado vs báscula', f('kgSigno', total('difBascula'), ' kg'), `Báscula ${f('kg', total('kgBascula'), ' kg')} · referencia`, '', 'Kg facturado − kg báscula de las fincas en el periodo de cada factura (referencia)', COL_FACT)}
        ${kpi('bi-hourglass-split', 'TLC sin facturar', f('kg', kgSin, ' kg'), `${f('entero', tlcSin)} TLC · ${plural(extractorasSin, 'extractora', 'extractoras')}`, '', undefined, COL_FACT, kgSin > 0 ? 'ig-kpi-alerta' : '')}
    </div></div>`;

    const tablaFacturas = tablaEstatica(
        [['Factura'], ['Extractora'], ['Periodo facturado'], ['Kg facturado', 'text-end'], ['Kg TLC', 'text-end'], ['Dif. vs TLC', 'text-end', 'Kg facturado − kg TLC'], ['Kg báscula (ref.)', 'text-end'], ['Valor facturado', 'text-end']],
        facturas.map((fila) => {
            const chips = [
                fila.parcial ? '<span class="gp-chip-fila ambar" title="El periodo de la factura se sale del rango consultado; los kg TLC y báscula se calculan sobre todo el periodo de la factura."><i class="bi bi-calendar-range me-1" aria-hidden="true"></i>Parcial</span>' : '',
                fila.periodoPorFecha ? '<span class="gp-chip-fila gris" title="No se pudo leer el periodo en la nota de la factura; se usó la fecha de la factura."><i class="bi bi-question-circle me-1" aria-hidden="true"></i>Periodo estimado</span>' : ''
            ].filter(Boolean).join('');

            return [noCuadra(fila.difTlc) ? 'inf-fila-diferencia' : '', [
                `${doc(fila.documento)}<span class="gp-subtexto">${fecha(fila.fecha)}${texto(fila.notas) ? `<i class="bi bi-chat-left-text text-muted ms-1" title="${escaparHtml(fila.notas)}" role="img" aria-label="Notas de la factura"></i>` : ''}</span>`,
                celdaExtractora(fila),
                rangoHtml(fila.inicio, fila.fin) + (chips ? `<div class="d-flex flex-wrap gap-1 mt-1">${chips}</div>` : ''),
                `<span class="fw-semibold">${f('kg', fila.kgFacturado)}</span>`,
                `${f('kg', fila.kgTlc)}<span class="gp-subtexto">${f('entero', fila.tlcs || 0)} TLC</span>`,
                celdaDifTlc(fila.difTlc),
                `${f('kg', fila.kgBascula)}<span class="gp-subtexto">Dif. ${f('kgSigno', fila.difBascula)}</span>${sinPesaje(fila.tlcsSinPesaje)}`,
                f('cop', fila.valorFacturado) + detalleValor(fila.notasCredito, fila.ajusteValor)
            ]];
        }),
        [
            `Total<span class="gp-subtexto fw-normal">${plural(facturas.length, 'factura', 'facturas')}</span>`, '', '',
            f('kg', total('kgFacturado')),
            `${f('kg', total('kgTlc'))}<span class="gp-subtexto fw-normal">${f('entero', tlcFacturas)} TLC</span>`,
            celdaDifTlc(total('difTlc')),
            `${f('kg', total('kgBascula'))}<span class="gp-subtexto fw-normal">Dif. ${f('kgSigno', total('difBascula'))}</span>${sinPesaje(sumar(facturas, 'tlcsSinPesaje'), ' fw-normal')}`,
            f('cop', total('valorFacturado')) + (ajuste ? `<span class="gp-subtexto fw-normal">Ajuste ${f('cop', ajuste)}</span>` : '')
        ]
    );

    const tablaSin = tablaEstatica(
        [['Extractora'], ['Rango'], ['Nº TLC', 'text-end'], ['Kg', 'text-end']],
        sinFacturar.map((fila) => ['', [celdaExtractora(fila), rangoHtml(fila.desde, fila.hasta), f('entero', fila.tlcs), f('kg', fila.kg)]]),
        [`Total<span class="gp-subtexto fw-normal">${plural(extractorasSin, 'extractora', 'extractoras')}</span>`, '', f('entero', tlcSin), f('kg', kgSin)]
    );

    const tablaNotas = `<p class="gp-subtexto mb-2">No suman en el comparativo; los ajustes solo afectan el valor ($).</p>` + tablaEstatica(
        [['Documento'], ['Fecha'], ['Extractora'], ['Tipo'], ['Kg', 'text-end'], ['Valor', 'text-end'], ['Relacionado']],
        notas.map((fila) => ['', [
            doc(fila.documento, fila.anulada),
            fecha(fila.fecha),
            celdaExtractora(fila),
            fila.anulada ? '<span class="ig-chip rojo">Anulada · refacturada</span>' : '<span class="ig-chip ambar">Ajuste de valor</span>',
            fila.anulada ? f('kg', fila.kg) : NULO,
            f('cop', fila.valor),
            doc(fila.anulada ? fila.anuladaPor : fila.referencia)
        ]])
    );

    return [
        kpis,
        seccion('fact_facturas', 'bi-receipt-cutoff', 'Facturas de venta', `${plural(facturas.length, 'factura', 'facturas')} · ${f('kg', total('kgFacturado'), ' kg')} · ${f('cop', total('valorFacturado'))}`, facturas.length ? tablaFacturas : vacioGrafica('Sin facturas de venta en el periodo', 'bi-receipt')),
        sinFacturar.length ? seccion('fact_sin', 'bi-hourglass-split', 'TLC sin facturar', `${f('entero', tlcSin)} TLC · ${f('kg', kgSin, ' kg')}`, tablaSin) : '',
        notas.length ? seccion('fact_notas', 'bi-journal-x', 'Anulaciones y ajustes sin factura', `${plural((fact.anuladas || []).length, 'anulada', 'anuladas')} · ${plural((fact.ajustesSinFactura || []).length, 'ajuste', 'ajustes')}`, tablaNotas) : ''
    ].filter(Boolean).join('<hr class="m-0">');
};

const contadorFacturado = (r) => {
    const fact = r.facturacion || {};
    const cantidad = fact.disponible ? (fact.facturas || []).filter((fila) => noCuadra(fila.difTlc)).length : 0;

    return cantidad ? `<span class="ig-chip rojo ms-1" title="Facturas que no cuadran con TLC">${f('entero', cantidad)}</span>` : '';
};

const tablaHtml = (id, columnas, pie = null) => `<table class="table table-sm align-middle mb-0 gp-tabla inf-tabla inf-tabla-fija w-100" id="${id}">
    <thead class="gp-thead"><tr>${columnas.map(([titulo, clase = '']) => `<th scope="col"${clase ? ` class="${clase}"` : ''}>${titulo}</th>`).join('')}</tr></thead>
    <tfoot><tr>${columnas.map(([, clase = ''], indice) => `<th${clase ? ` class="${clase === 'text-end' ? 'inf-num' : clase}"` : ''}>${pie ? pie[indice] : ''}</th>`).join('')}</tr></tfoot>
</table>`;

const filtroEstados = (n, total) => `<div class="btn-group btn-group-sm ba-estados mb-2" role="group" aria-label="Filtrar viajes por estado">
    ${[['', 'Todos', total], ...ORDEN_FILTRO.map((clave) => [clave, ESTADOS[clave].corto, n[clave]])].map(([clave, nombre, cantidad]) => {
        const id = `fe_${clave || 'todos'}`;

        return `<input type="radio" class="btn-check" name="filtro_estado" id="${id}" value="${clave}" autocomplete="off"${(estado.filtro || '') === clave ? ' checked' : ''}${clave && !cantidad ? ' disabled' : ''}><label class="btn btn-outline-success" for="${id}">${nombre} (${f('entero', cantidad)})</label>`;
    }).join('')}
</div>`;

const tablaSinPesaje = (lista) => {
    const filas = [...lista].sort((a, b) => texto(a.fecha).localeCompare(texto(b.fecha)) || Number(a.tlc) - Number(b.tlc)).map((fila) => `<tr>
        <td class="font-monospace">${nulo(fila.tlc) ? NULO : escaparHtml(fila.tlc)}</td>
        <td>${fecha(fila.fecha)}</td>
        <td>${placa(fila.placa)}</td>
        <td><div class="gp-truncar" title="${escaparHtml(fila.conductor)}">${texto(fila.conductor) ? escaparHtml(fila.conductor) : NULO}</div></td>
        <td>${texto(fila.tiquete) ? escaparHtml(fila.tiquete) : NULO}</td>
        <td><div class="gp-truncar" title="${escaparHtml(fila.extractora)}">${escaparHtml(fila.extractora)}</div></td>
        <td class="inf-num">${f('kg', fila.kgExtractora)}</td>
    </tr>`).join('');
    const total = lista.reduce((suma, fila) => suma + (Number(fila.kgExtractora) || 0), 0);

    return `<div class="table-responsive ig-ciclo-scroll">
        <table class="table table-sm align-middle mb-0 gp-tabla inf-tabla">
            <thead class="gp-thead"><tr><th scope="col">TLC</th><th scope="col">Fecha</th><th scope="col">Placa</th><th scope="col">Conductor</th><th scope="col">Tiquete</th><th scope="col">Extractora</th><th scope="col" class="text-end">Kg extractora</th></tr></thead>
            <tbody>${filas}</tbody>
            <tfoot><tr><th scope="row">Total<span class="gp-subtexto fw-normal">${f('entero', lista.length)} tiquetes</span></th><th></th><th></th><th></th><th></th><th></th><th class="inf-num">${f('kg', total)}</th></tr></tfoot>
        </table>
    </div>`;
};

const numerica = (display) => ({ className: 'inf-num', type: 'num', render: (valor, tipo, fila) => (tipo === 'display' ? display(valor, fila) : (nulo(valor) ? null : Number(valor))) });
const listaTlc = (fila) => (Array.isArray(fila.tlcs) && fila.tlcs.length ? fila.tlcs : [fila.tlc]).filter((valor) => !nulo(valor));
const sumar = (filas, campo, condicion = () => true) => filas.filter(condicion).reduce((suma, fila) => suma + (nulo(fila[campo]) ? 0 : Number(fila[campo])), 0);

const crearTablaViajes = (viajes) => {
    const tabla = $('#tabla_viajes').DataTable({
        data: viajes,
        dom: DOM_TABLA,
        order: [[0, 'asc']],
        pageLength: 25,
        columns: [
            { data: 'entrada', render: (valor, tipo, fila) => (tipo === 'display' ? `<span class="fw-semibold">${fecha(fila.fecha)}</span>${hora(valor) ? `<span class="gp-subtexto">${hora(valor)}</span>` : ''}` : (tipo === 'filter' ? fecha(fila.fecha) : texto(valor || fila.fecha))) },
            { data: 'placa', render: (valor, tipo, fila) => {
                const codigos = fincasViaje(fila);

                if (tipo === 'filter') return `${texto(valor)} ${nombresFincas(codigos)}`;
                if (tipo !== 'display') return texto(valor);

                return placa(valor) + (codigos.length ? `<span class="gp-subtexto" title="${escaparHtml(nombresFincas(codigos))}">${escaparHtml(codigos.join(' · '))}</span>` : '<span class="gp-subtexto">Sin finca</span>');
            } },
            { data: 'conductor', render: (valor, tipo, fila) => (tipo === 'display' ? `<div class="gp-truncar" title="${escaparHtml(valor)}">${texto(valor) ? escaparHtml(valor) : NULO}</div>${texto(fila.cedula) ? `<span class="gp-subtexto">C.C. ${escaparHtml(fila.cedula)}</span>` : ''}` : `${texto(valor)} ${texto(fila.cedula)}`) },
            { data: 'extractora', render: (valor, tipo) => (tipo === 'display' ? `<div class="gp-truncar ba-col-extractora" title="${escaparHtml(valor)}">${texto(valor) ? escaparHtml(valor) : NULO}</div>` : texto(valor)) },
            { data: (fila) => (fila.estado === 'incompleto' || !Number(fila.pesada2) ? null : fila.pesada2), ...numerica((valor, fila) => {
                const celda = `<span class="text-muted small me-1">2.ª</span>${nulo(valor) ? NULO : `<span class="fw-semibold">${f('kg', valor)}</span>`}<span class="gp-subtexto">1.ª ${f('kg', fila.pesada1)}</span>`;

                return fila.parcial ? `<div title="Pesos totales del vehículo (sin prorrateo)">${celda}</div>` : celda;
            }) },
            { data: 'kgBascula', ...numerica((valor, fila) => (fila.estado === 'incompleto' ? `${NULO}<span class="gp-subtexto">Solo 1.ª pesada</span>` : f('kg', valor)) + (fila.parcial ? `<span class="gp-subtexto" title="${escaparHtml(`Carga mixta (${fincasViaje(fila).join(' · ')}): kg báscula, kg extractora y diferencia prorrateados a las fincas seleccionadas.`)}"><i class="bi bi-pie-chart me-1" aria-hidden="true"></i>Prorrateado</span>` : '')) },
            { data: 'tlc', type: 'num', render: (valor, tipo, fila) => {
                const lista = listaTlc(fila);

                if (tipo === 'display') return lista.length ? `<span class="font-monospace">${lista.map(escaparHtml).join(' · ')}</span>${texto(fila.tiquete) ? `<span class="gp-subtexto">${escaparHtml(fila.tiquete)}</span>` : ''}` : NULO;
                if (tipo === 'filter') return `${lista.join(' ')} ${texto(fila.tiquete)}`;

                return lista.length ? Number(lista[0]) : null;
            } },
            { data: 'kgExtractora', ...numerica((valor) => (nulo(valor) ? NULO : f('kg', valor))) },
            { data: 'perdidaKg', ...numerica(celdaDif) },
            { data: 'perdidaPct', ...numerica(celdaPct) },
            { data: 'estado', type: 'num', render: (valor, tipo) => (tipo === 'display' ? badgeEstado(valor) : (tipo === 'filter' ? valor : Object.keys(ESTADOS).indexOf(valor))) }
        ],
        footerCallback: function () {
            const api = this.api();
            const filas = api.rows({ search: 'applied' }).data().toArray();
            const kgExtractora = sumar(filas, 'kgExtractora');
            const dif = sumar(filas, 'perdidaKg');
            const completos = sumar(filas, 'kgBascula', (fila) => fila.estado !== 'incompleto');
            const noLlegados = sumar(filas, 'perdidaKg', (fila) => Number(fila.perdidaKg) > 0) + sumar(filas, 'kgBascula', (fila) => fila.estado === 'sin_tlc');
            const prorrateados = filas.filter((fila) => fila.parcial).length;
            const incompletos = filas.filter((fila) => fila.estado === 'incompleto').length;
            const pie = [
                `Total<span class="gp-subtexto fw-normal">${f('entero', filas.length)} viajes${prorrateados ? ` · ${f('entero', prorrateados)} prorrateados` : ''}</span>`, '', '', '',
                incompletos ? `<span class="gp-subtexto fw-normal">${f('entero', incompletos)} sin 2.ª pesada</span>` : '',
                f('kg', completos), '',
                f('kg', kgExtractora), `${f('kgSigno', dif)}<span class="gp-subtexto fw-normal">De más ${f('kg', Math.abs(sumar(filas, 'perdidaKg', (fila) => Number(fila.perdidaKg) < 0)))}</span>`,
                `${completos > 0 ? celdaPct((noLlegados / completos) * 100) : NULO}<span class="gp-subtexto fw-normal">No llegados ${f('kg', noLlegados)}</span>`, ''
            ];

            api.columns().every(function (indice) {
                $(this.footer()).html(pie[indice]);
            });
        }
    });

    estado.tablas.push(tabla);
    estado.tablaViajes = tabla;
};

const crearTablaPlacas = (placas) => {
    const totales = Object.fromEntries((placas.dias || []).map((dia) => [texto(dia.fecha).substring(0, 10), Number(dia.total) || 0]));
    const filas = (placas.dias || []).flatMap((dia) => Object.entries(dia.porExtractora || {}).map(([extractora, valor]) => ({ fecha: texto(dia.fecha).substring(0, 10), extractora, placas: Number(valor.placas) || 0, lista: valor.lista || [] })));

    estado.tablas.push($('#tabla_placas').DataTable({
        data: filas,
        paging: false,
        info: false,
        dom: "<'gp-dt-barra'f><'ig-ciclo-scroll't>",
        orderFixed: [[0, 'asc']],
        order: [[1, 'asc']],
        rowGroup: { dataSrc: 'fecha', startRender: (filasGrupo, grupo) => `<span class="fw-semibold">${diaSemana(grupo)} ${fecha(grupo)}</span> · ${f('entero', totales[grupo] || 0)} placas` },
        columns: [
            { data: 'fecha', visible: false },
            { data: 'extractora', render: (valor, tipo) => (tipo === 'display' ? `<span class="fw-semibold">${escaparHtml(valor)}</span>` : texto(valor)) },
            { data: 'placas', className: 'inf-num', type: 'num', render: (valor, tipo) => (tipo === 'display' ? f('entero', valor) : valor) },
            { data: 'lista', orderable: false, render: (valor, tipo) => (tipo === 'display' ? `<div class="inf-etiquetas">${valor.map(placa).join('')}</div>` : valor.join(' ')) }
        ]
    }));
};

const crearTablaConductores = (conductores) => {
    estado.tablas.push($('#tabla_conductores').DataTable({
        data: conductores,
        dom: DOM_TABLA,
        order: [[2, 'desc']],
        pageLength: 25,
        columns: [
            { data: 'conductor', className: 'inf-celda-fija', render: (valor, tipo, fila) => (tipo === 'display' ? `<div class="fw-semibold gp-truncar" title="${escaparHtml(nombreConductor(fila))}">${escaparHtml(nombreConductor(fila))}</div><span class="gp-subtexto">${texto(fila.cedula) ? `C.C. ${escaparHtml(fila.cedula)}` : 'Sin cédula'}</span>` : `${texto(valor)} ${texto(fila.cedula)}`) },
            { data: 'viajes', ...numerica((valor) => f('entero', valor)) },
            { data: 'kgBascula', ...numerica((valor) => f('kg', valor)) },
            { data: 'kgExtractora', ...numerica((valor) => f('kg', valor)) },
            { data: 'kgNoLlegados', ...numerica(celdaKgCero) },
            { data: 'kgSobrante', ...numerica(celdaKgCero) },
            { data: 'pctNoLlegados', ...numerica(chipPct), className: 'inf-num text-end' },
            { data: 'fueraRegla', ...numerica((valor, fila) => barraFuera(valor, fila.viajes)) }
        ],
        footerCallback: function () {
            const api = this.api();
            const filas = api.rows({ search: 'applied' }).data().toArray();
            const kgBascula = sumar(filas, 'kgBascula');
            const noLlegados = sumar(filas, 'kgNoLlegados');
            const viajes = sumar(filas, 'viajes');
            const pie = [
                `Total<span class="gp-subtexto fw-normal">${f('entero', filas.length)} conductores</span>`,
                f('entero', viajes), f('kg', kgBascula), f('kg', sumar(filas, 'kgExtractora')), celdaKgCero(noLlegados), celdaKgCero(sumar(filas, 'kgSobrante')),
                kgBascula > 0 ? chipPct((noLlegados / kgBascula) * 100) : NULO,
                barraFuera(sumar(filas, 'fueraRegla'), viajes)
            ];

            api.columns().every(function (indice) {
                $(this.footer()).html(pie[indice]);
            });
        }
    }));
};

const barraFuera = (valor, viajes) => {
    const cantidad = Number(valor) || 0;
    const total = Number(viajes) || 0;
    const pct = total ? Math.min((cantidad / total) * 100, 100) : 0;

    return `<div class="ms-auto" style="min-width:96px">
        <span class="${cantidad ? 'ig-texto-rojo fw-semibold' : 'text-muted'}">${f('entero', cantidad)} / ${f('entero', total)}</span>
        <div class="progress ig-progreso mt-1" role="progressbar" aria-label="Viajes fuera de regla" aria-valuenow="${Math.round(pct)}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar ig-barra-rojo" style="width:${pct}%"></div></div>
    </div>`;
};

const pestanas = (paneles, extras = []) => `<div class="card mb-3">
    <div class="card-body p-0">
        <ul class="nav nav-tabs nav-tabs-bordered px-3 pt-3" role="tablist">
            ${TABS.map(([id, icono, titulo], indice) => `<li class="nav-item" role="presentation"><button type="button" class="nav-link${estado.tab === id ? ' active' : ''}" id="tab_${id}" data-bs-toggle="tab" data-bs-target="#${id}" role="tab" aria-controls="${id}" aria-selected="${estado.tab === id}"><i class="bi ${icono} me-1" aria-hidden="true"></i>${titulo}${extras[indice] || ''}</button></li>`).join('')}
        </ul>
        <div class="tab-content">
            ${TABS.map(([id], indice) => `<div class="tab-pane fade${estado.tab === id ? ' show active' : ''}" id="${id}" role="tabpanel" aria-labelledby="tab_${id}" tabindex="0">${paneles[indice]}</div>`).join('')}
        </div>
    </div>
</div>`;

const activarGraficas = (panel) => {
    (estado.pendientes[panel] || []).forEach(([clave, id, opciones]) => {
        if (estado.graficas[clave] || !$id(id) || $id(id).closest('.collapse:not(.show)')) return;

        estado.graficas[clave] = new ApexCharts($id(id), opciones());
        estado.graficas[clave].render();
    });
};

const aplicarFiltro = () => {
    document.querySelectorAll('.ig-ciclo-kpi[data-estado]').forEach((boton) => boton.setAttribute('aria-pressed', String(boton.dataset.estado === estado.filtro)));

    const radio = $id(`fe_${estado.filtro || 'todos'}`);

    if (radio) radio.checked = true;
    if (!estado.tablaViajes) return;

    estado.tablaViajes.settings()[0].oLanguage.sZeroRecords = estado.filtro ? `Ningún viaje con estado «${ESTADOS[estado.filtro].nombre}» en el periodo` : ZERO_RECORDS;
    estado.tablaViajes.column(10).search(estado.filtro ? `^${estado.filtro}$` : '', true, false).draw();
};

const irATabla = () => {
    const cuerpo = $id('sec_viajes');

    if (cuerpo && !cuerpo.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(cuerpo, { toggle: false }).show();
    $id('tabla_viajes')?.closest('section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const filtrar = (clave, desdeKpi) => {
    estado.filtro = desdeKpi && clave === estado.filtro ? null : (clave || null);
    aplicarFiltro();

    if (!desdeKpi || !estado.filtro) return;

    const tab = $id('tab_panel_viajes');

    if (estado.tab === 'panel_viajes') {
        irATabla();
    } else if (tab) {
        tab.addEventListener('shown.bs.tab', irATabla, { once: true });
        bootstrap.Tab.getOrCreateInstance(tab).show();
    }
};

const pintarContexto = (r) => {
    const desde = texto(r.filtros?.desde);
    const hasta = texto(r.filtros?.hasta);
    const dias = diasEntre(desde, hasta);
    const ultimo = texto(r.filtros?.ultimoPesaje);
    const chips = [`<span class="gp-chip-fila inf-chip-filtro">Periodo: ${desde === hasta ? fecha(desde) : `${fecha(desde)} – ${fecha(hasta)}`} · ${f('entero', dias)} ${dias === 1 ? 'día' : 'días'}</span>`];

    const nombres = (r.filtros?.fincas || []).map((codigo) => nombrePropio(estado.fincas[String(codigo)] || codigo));
    const prorrateados = nombres.length ? (r.viajes || []).filter((viaje) => viaje.parcial).length : 0;

    chips.push(`<span class="gp-chip-fila inf-chip-filtro"${nombres.length > 2 ? ` title="${escaparHtml(nombres.join(', '))}"` : ''}>Fincas: ${escaparHtml(nombres.length ? nombres.slice(0, 2).join(', ') + (nombres.length > 2 ? ` +${nombres.length - 2}` : '') : 'todas')}</span>`);
    if (prorrateados) chips.push(`<span class="gp-chip-fila inf-chip-filtro" title="Viajes con carga de varias fincas: se incluyen solo los kg proporcionales a las fincas seleccionadas."><i class="bi bi-pie-chart me-1" aria-hidden="true"></i>${f('entero', prorrateados)} ${prorrateados === 1 ? 'viaje prorrateado' : 'viajes prorrateados'}</span>`);
    if ($id('filtro_empresa').options.length > 1) chips.push(`<span class="gp-chip-fila inf-chip-filtro">Empresa: ${escaparHtml($id('filtro_empresa').selectedOptions[0]?.text || '')}</span>`);
    if (ultimo) chips.push(`<span class="gp-chip-fila ${hasta > ultimo.substring(0, 10) ? 'ambar' : 'inf-chip-filtro'}"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Último pesaje: ${fecha(ultimo)}${hora(ultimo) ? ` ${hora(ultimo)}` : ''}</span>`);

    $id('chips_contexto').innerHTML = chips.join('');
};

const avisosHtml = (avisos) => (avisos || []).map((aviso) => `<div class="alert alert-warning py-2 small mb-3 d-flex gap-2" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>${escaparHtml(aviso)}</span></div>`).join('');

const actualizarFincas = (r) => {
    const lista = r.fincasDisponibles || [];
    const firma = lista.map((finca) => finca.codigo).join('|');

    estado.fincas = Object.fromEntries(lista.map((finca) => [String(finca.codigo), texto(finca.nombre)]));

    if (firma !== estado.firmaFincas) {
        estado.firmaFincas = firma;
        $id('filtro_fincas').innerHTML = lista.map((finca) => `<option value="${escaparHtml(finca.codigo)}" data-nombre="${escaparHtml(finca.nombre)}">${escaparHtml(`${finca.codigo} — ${texto(finca.nombre)}`)}</option>`).join('');
    }

    if (!estado.esperaFincas) $('#filtro_fincas').val((r.filtros?.fincas || []).map(String)).trigger('change.select2');
    $id('col_fincas').classList.toggle('d-none', lista.length <= 1);
};

const pintar = () => {
    const r = estado.respuesta;
    const k = r.kpis || {};
    const desde = texto(r.filtros?.desde);
    const hasta = texto(r.filtros?.hasta);
    const periodo = rangoTexto(desde, hasta);
    const viajes = r.viajes || [];
    const placas = { extractoras: r.placas?.extractoras || [], dias: r.placas?.dias || [] };
    const conductores = [...(r.conductores || [])].sort((a, b) => (Number(b.kgBascula) || 0) - (Number(a.kgBascula) || 0));
    const sinPesaje = r.tlcSinPesaje || [];
    const n = conteos(k);

    const conFincas = (r.filtros?.fincas || []).length > 0;
    const unidadKg = viajes.some((viaje) => viaje.parcial) ? 'kg · incluye prorrateo' : 'kg';

    estado.regla = nulo(r.regla?.maxPerdidaKg) ? 100 : Number(r.regla.maxPerdidaKg);
    destruir();
    actualizarFincas(r);
    pintarContexto(r);

    if (desde) $id('filtro_desde').value = desde;
    if (hasta) $id('filtro_hasta').value = hasta;
    validar();
    estado.colapsadas = leerColapsadas();

    if (!viajes.length) {
        const ultimo = texto(r.filtros?.ultimoPesaje).substring(0, 10);
        const boton = ultimo && (ultimo < desde || ultimo > hasta);

        const botones = (boton ? `<button type="button" class="btn btn-outline-success btn-sm" data-accion="ultimo" data-desde="${escaparHtml(ultimo.substring(0, 8))}01" data-hasta="${escaparHtml(ultimo)}"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Ver mes del último pesaje (${escaparHtml(mesTexto(ultimo))})</button>` : '')
            + (conFincas ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-accion="todas-fincas"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Ver todas las fincas</button>' : '');

        $id('resultado_cuerpo').innerHTML = avisosHtml(r.avisos) + `<div class="card mb-3">${estadoVacio('bi-truck', `Sin pesajes ${periodo}${conFincas ? ' para las fincas seleccionadas' : ''}`,
            `<p class="text-muted ${botones ? 'mb-3' : 'mb-0'}">No hay viajes registrados en la báscula para el periodo seleccionado.</p>${botones ? `<div class="d-flex flex-wrap justify-content-center gap-2">${botones}</div>` : ''}`)}</div>`;

        const fact = r.facturacion || {};

        if (fact.disponible && ['facturas', 'sinFacturar', 'anuladas', 'ajustesSinFactura'].some((clave) => (fact[clave] || []).length)) {
            $id('resultado_cuerpo').insertAdjacentHTML('beforeend', `<div class="card mb-3"><div class="card-body p-0"><div class="px-3 pt-3"><h6 class="gp-seccion mb-0 border-0 pb-0"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i>Báscula vs facturado${contadorFacturado(r)}</h6></div>${panelFacturado(r, periodo)}</div></div>`);
        }
        return;
    }

    if (estado.filtro && !n[estado.filtro]) estado.filtro = null;

    const ejes = construirEjes(desde, hasta);
    const por = ejes.mensual ? 'por mes' : 'por día';
    const unicas = new Set(placas.dias.flatMap((dia) => Object.values(dia.porExtractora || {}).flatMap((valor) => valor.lista || []))).size;
    const totalPlacas = placas.dias.reduce((suma, dia) => suma + (Number(dia.total) || 0), 0);
    const separar = (lista) => lista.filter(Boolean).join('<hr class="m-0">');

    const paneles = [
        separar([
            seccion('dias', 'bi-bar-chart-line', `Báscula vs extractora ${por}`, 'kg · % no llegados como referencia', (r.dias || []).length ? lienzo('grafica_dias', `Kg báscula, kg extractora y porcentaje de kg no llegados ${por} ${periodo}. Kg no llegados del periodo ${f('pct', k.pctNoLlegados, ' %')}`) : vacioGrafica()),
            seccion('viajes', 'bi-list-ul', 'Viajes del periodo', `${f('entero', viajes.length)} viajes · kg`, filtroEstados(n, viajes.length) + tablaHtml('tabla_viajes', [['Fecha'], ['Placa / finca'], ['Conductor'], ['Extractora'], ['Pesajes', 'text-end'], ['Kg báscula', 'text-end'], ['TLC / tiquete'], ['Kg extractora', 'text-end'], ['Dif. kg', 'text-end'], ['% pérdida (ref.)', 'text-end'], ['Estado']])),
            sinPesaje.length ? seccion('sin_pesaje', 'bi-receipt', 'TLC sin pesaje en báscula', `${f('entero', sinPesaje.length)} tiquetes · ${f('kg', k.kgTlcSinPesaje, ' kg')}`, tablaSinPesaje(sinPesaje)) : ''
        ]),
        separar([
            seccion('placas_grafica', 'bi-truck-front', `Placas ${por}`, ejes.mensual ? 'placas-día por extractora' : 'placas por extractora', placas.dias.length ? lienzo('grafica_placas', `Placas ${por} y extractora ${periodo}`) : vacioGrafica()),
            seccion('placas_tabla', 'bi-table', 'Detalle de placas', `${f('entero', totalPlacas)} placas · ${f('entero', unicas)} únicas`, tablaHtml('tabla_placas', [['Fecha'], ['Extractora'], ['Nº placas', 'text-end'], ['Placas']], ['', `Total del periodo<span class="gp-subtexto fw-normal">Placas por día sumadas</span>`, f('entero', totalPlacas), `${f('entero', unicas)} placas únicas`]))
        ]),
        separar([
            seccion('conductores_grafica', 'bi-person-badge', 'Kg báscula por conductor', `${f('entero', conductores.length)} conductores · ${unidadKg}`, conductores.length ? `<div class="inf-grafica-scroll">${lienzo('grafica_conductores', `Kg báscula por conductor ${periodo}`)}</div>` : vacioGrafica()),
            seccion('conductores_tabla', 'bi-table', 'Detalle por conductor', unidadKg, tablaHtml('tabla_conductores', [['Conductor'], ['Viajes', 'text-end'], ['Kg báscula', 'text-end'], ['Kg extractora', 'text-end'], ['No llegados kg', 'text-end'], ['Kg de más', 'text-end'], ['% no llegados (ref.)', 'text-end'], ['Fuera de regla', 'text-end']]))
        ]),
        panelFacturado(r, periodo)
    ];

    $id('resultado_cuerpo').innerHTML = avisosHtml(r.avisos) + pintarKpis(k, n, viajes) + pestanas(paneles, ['', '', '', contadorFacturado(r)]);

    estado.pendientes = {
        panel_viajes: [['dias', 'grafica_dias', () => opcionesDias(r.dias, ejes)]],
        panel_placas: [['placas', 'grafica_placas', () => opcionesPlacas(placas, ejes)]],
        panel_conductores: [['conductores', 'grafica_conductores', () => opcionesConductores(conductores)]]
    };

    crearTablaViajes(viajes);
    crearTablaPlacas(placas);
    crearTablaConductores(conductores);
    aplicarFiltro();
    activarGraficas(estado.tab);
};

const consultar = async () => {
    const turno = ++estado.turno;

    ocupado(true);

    if (!estado.respuesta) {
        destruir();
        $id('resultado_cuerpo').innerHTML = skeleton();
    }

    const empresa = $id('filtro_empresa').value;
    let respuesta;

    try {
        respuesta = await pedir({ empresa, fincas: $('#filtro_fincas').val() || [], desde: $id('filtro_desde').value, hasta: $id('filtro_hasta').value });
    } catch (error) {
        if (turno !== estado.turno) return;

        estado.respuesta = null;
        ocupado(false);
        destruir();
        $id('resultado_cuerpo').innerHTML = `<div class="card mb-3">${estadoVacio('bi-exclamation-triangle', 'No se pudo cargar el informe',
            `<p class="text-muted mb-3">${escaparHtml(error.message)}</p><button type="button" class="btn btn-outline-success btn-sm" data-accion="reintentar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>`, 'inf-medallon-error')}</div>`;
        return;
    }

    if (turno !== estado.turno) return;

    estado.respuesta = respuesta;
    estado.empresa = empresa;
    ocupado(false);
    pintar();
};

const exportar = async () => {
    const r = estado.respuesta;
    const boton = $id('btn_excel');

    if (!r || boton.disabled) return;

    const desde = texto(r.filtros?.desde);
    const hasta = texto(r.filtros?.hasta);

    estado.exportando = true;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Exportando…';

    try {
        const respuesta = await enviar(URL_EXCEL, { empresa: estado.empresa, fincas: (r.filtros?.fincas || []).map(String), desde, hasta });

        if (!respuesta.ok || (respuesta.headers.get('Content-Type') || '').includes('json')) {
            const resultado = await respuesta.json().catch(() => null);

            throw Object.assign(new Error(texto(resultado?.message) || 'No se pudo generar el archivo.'), { status: respuesta.status });
        }

        const nombre = (respuesta.headers.get('Content-Disposition') || '').match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)?.[1];
        const enlace = document.createElement('a');

        enlace.href = URL.createObjectURL(await respuesta.blob());
        enlace.download = nombre ? decodeURIComponent(nombre) : `informe_bascula_${desde}_${hasta}.xlsx`;
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(enlace.href), 1000);
    } catch (error) {
        Swal.fire({ icon: error.status === 403 ? 'warning' : 'error', title: error.status === 403 ? 'Acción no permitida' : 'No se pudo exportar', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }

    estado.exportando = false;
    boton.innerHTML = HTML_EXCEL;
    habilitarExcel();
};

const rango = () => {
    const desde = $id('filtro_desde').value;
    const hasta = $id('filtro_hasta').value;
    const llenos = Boolean(desde && hasta);
    const dias = llenos ? diasEntre(desde, hasta) : 0;
    const error = !llenos ? (desde || hasta ? 'Indique la fecha inicio y la fecha fin.' : '') : (hasta < desde ?'La fecha fin debe ser igual o posterior a la fecha inicio.' : (dias > MAX_DIAS ? `El rango máximo es de ${MAX_DIAS} días (seleccionaste ${f('entero', dias)}).` : ''));

    return { desde, hasta, error, valido: llenos && !error };
};

const validar = () => {
    const actual = rango();
    const hoy = new Date();

    $id('filtro_hasta').min = actual.desde || FECHA_MIN;
    $id('filtro_desde').classList.toggle('is-invalid', Boolean(actual.error) && !actual.desde);
    $id('filtro_hasta').classList.toggle('is-invalid', Boolean(actual.error) && Boolean(actual.desde));
    $id('error_hasta').textContent = actual.error;
    $id('fila_error_hasta').classList.toggle('d-none', !actual.error);
    $id('btn_consultar').disabled = !actual.valido;
    habilitarExcel();
    document.querySelectorAll('[data-rango]').forEach((boton) => {
        const [desde, hasta] = RANGOS[boton.dataset.rango](hoy);
        const activo = desde === actual.desde && hasta === actual.hasta;

        boton.classList.toggle('active', activo);
        boton.setAttribute('aria-pressed', String(activo));
    });

    return actual.valido;
};

const recargar = () => {
    if (validar()) {
        consultar();
        return;
    }

    estado.turno++;
    ocupado(false);
    validar();
};

const fijarRango = (desde, hasta) => {
    $id('filtro_desde').value = desde;
    $id('filtro_hasta').value = hasta;
    recargar();
};

$id('form_filtros').addEventListener('submit', (evento) => {
    evento.preventDefault();
    recargar();
});

$id('form_filtros').addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-rango]');

    if (boton) fijarRango(...RANGOS[boton.dataset.rango](new Date()));
});

['filtro_desde', 'filtro_hasta'].forEach((id) => $id(id).addEventListener('change', recargar));

const limpiarFincas = () => {
    clearTimeout(estado.esperaFincas);
    estado.esperaFincas = null;
    $('#filtro_fincas').val([]).trigger('change.select2');
};

$id('filtro_empresa').addEventListener('change', () => {
    limpiarFincas();
    recargar();
});

estado.firmaFincas = [...$id('filtro_fincas').options].map((opcion) => opcion.value).join('|');

$('#filtro_fincas').select2({
    width: '100%',
    placeholder: 'Todas las fincas',
    allowClear: true,
    closeOnSelect: false,
    language: { noResults: () => 'No se encontraron fincas' },
    templateSelection: (item) => nombrePropio(item.element?.dataset.nombre || item.text)
}).on('change', () => {
    clearTimeout(estado.esperaFincas);
    estado.esperaFincas = null;

    if (!($('#filtro_fincas').val() || []).length) {
        recargar();
        return;
    }

    estado.esperaFincas = setTimeout(() => {
        estado.esperaFincas = null;
        recargar();
    }, 500);
});

$id('resultado_cuerpo').addEventListener('click', (evento) => {
    const kpiEstado = evento.target.closest('.ig-ciclo-kpi[data-estado]');
    const accion = evento.target.closest('[data-accion]');

    if (kpiEstado) {
        filtrar(kpiEstado.dataset.estado, true);
    } else if (accion?.dataset.accion === 'reintentar') {
        consultar();
    } else if (accion?.dataset.accion === 'ultimo') {
        fijarRango(accion.dataset.desde, accion.dataset.hasta);
    } else if (accion?.dataset.accion === 'todas-fincas') {
        limpiarFincas();
        recargar();
    }
});

$id('resultado_cuerpo').addEventListener('change', (evento) => {
    if (evento.target.name === 'filtro_estado') filtrar(evento.target.value, false);
});

$id('resultado_cuerpo').addEventListener('shown.bs.tab', (evento) => {
    if (!evento.target.matches('[data-bs-toggle="tab"]')) return;

    estado.tab = texto(evento.target.dataset.bsTarget).replace('#', '');
    activarGraficas(estado.tab);
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
});

['shown.bs.collapse', 'hidden.bs.collapse'].forEach((tipo) => $id('resultado_cuerpo').addEventListener(tipo, (evento) => {
    if (!evento.target.matches('section > .collapse[id^="sec_"]')) return;

    const abierta = tipo === 'shown.bs.collapse';

    guardarColapsada(evento.target.id.slice(4), !abierta);

    if (!abierta) return;

    activarGraficas(estado.tab);
    window.dispatchEvent(new Event('resize'));
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
}));

$id('btn_excel').addEventListener('click', exportar);

validar();
consultar();
