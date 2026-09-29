const URL_INF = BASE_URL + 'informes/reportes/';
const COLORES = ['#41a867', '#2f7a4b', '#7cc49a', '#4a5b6c', '#f0a500', '#8795a4', '#c9e6d6'];
const NUMERICOS = ['entero', 'decimal', 'moneda'];
const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

const $id = (id) => document.getElementById(id);
const texto = (valor) => String(valor ?? '').trim();
const mostrar = (elemento, visible) => elemento?.classList.toggle('d-none', !visible);
const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));
const sinAcentos = (valor) => texto(valor).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const FORMATOS = {
    entero: new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }),
    decimal: new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    moneda: new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
};

const formatear = (valor, formato = 'decimal') => (FORMATOS[formato] ? FORMATOS[formato].format(Number(valor || 0)) : texto(valor));
const partes = (valor) => texto(valor).split(/\s+[–—-]\s+/);
const codigoNombre = (valor) => {
    const [codigo, ...resto] = partes(valor);

    return resto.length
        ? `<span class="font-monospace fw-semibold">${escaparHtml(codigo)}</span> — <span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(resto.join(' – '))}">${escaparHtml(resto.join(' – '))}</span>`
        : escaparHtml(valor);
};

const ESPECIFICOS = {
    'lotes-por-fincas': {
        ocultas: ['finca', 'propietario', 'ciudad'],
        unidad: ['lote', 'lotes'],
        ayudas: { estado: 'Con «Todos» se incluyen fincas sin lotes.' },
        anchos: { lote: 'min-width:200px', siembra: 'width:90px', hBrutas: 'width:100px', hNetas: 'width:100px', palmasBrutas: 'width:110px', palmasProduccion: 'width:120px', palmasDetalle: 'width:120px', densidad: 'width:90px', estado: 'width:90px' },
        cuenta: (fila) => texto(fila.lote) !== '',
        vacia: (fila) => (texto(fila.lote) === '' ? 'Sin lotes registrados' : ''),
        subtitulo: (fila) => [fila.propietario, fila.ciudad, `Ha registradas: ${formatear(fila.haFinca, 'decimal')}`].map(texto).filter(Boolean).join(' · '),
        marca: { palmasDetalle: (fila) => Number(fila.palmasDetalle) !== Number(fila.palmasProduccion) },
        marcaTitulo: 'Difiere de palmas en producción',
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        titulo: 'Hectáreas netas por finca',
        detalle: { lotes: ['Lotes', 'entero'], palmasProduccion: ['Palmas producción', 'entero'], haFinca: ['Ha registradas (finca)', 'decimal'] },
        kpis: (kpis, respuesta, filtros) => (filtros.estado !== 'T' ? kpis : kpis.map((kpi) => (kpi.etiqueta === 'Lotes'
            ? { ...kpi, ayuda: `${formatear(respuesta.filas.filter((fila) => fila.estado === 'Activo').length, 'entero')} activos` }
            : kpi)))
    },
    'lotes-por-seccion-bloque': {
        ocultas: ['grupo', 'finca', 'seccion', 'haSeccion', 'sinSeccion'],
        unidad: ['lote', 'lotes'],
        alertaTitulo: 'Lotes sin sección asignada',
        ayudas: { seccion: 'Depende de la finca elegida; incluye «Sin sección».', estado: 'Por defecto solo lotes activos.' },
        anchos: { lote: 'min-width:200px', siembra: 'width:90px', variedad: 'min-width:150px', hBrutas: 'width:100px', hNetas: 'width:100px', palmasProduccion: 'width:120px', estado: 'width:90px' },
        cuenta: (fila) => texto(fila.lote) !== '',
        vacia: (fila) => (texto(fila.lote) === '' ? 'Sin lotes registrados' : ''),
        alerta: (fila) => Boolean(fila.sinSeccion),
        subtitulo: (fila, filas) => {
            if (fila.sinSeccion) return `${texto(fila.finca)} · Lotes sin sección asignada`;
            const brutas = filas.reduce((total, actual) => total + Number(actual.hBrutas || 0), 0);
            const dif = brutas - Number(fila.haSeccion || 0);
            return `${texto(fila.finca)} · Ha declaradas: ${formatear(fila.haSeccion, 'decimal')} · Ha brutas lotes: ${formatear(brutas, 'decimal')}${Math.abs(dif) >= 0.01 ? ` · Diferencia: ${dif > 0 ? '+' : '-'}${formatear(Math.abs(dif), 'decimal')}` : ''}`;
        },
        categoria: (valor) => partes(valor).length > 1 ? `${texto(valor).split(' · ')[0]} · ${partes(valor).slice(1).join(' – ')}` : texto(valor),
        titulo: 'Top 15 secciones por hectáreas netas',
        detalle: { lotes: ['Lotes', 'entero'], palmasProduccion: ['Palmas producción', 'entero'], haSeccion: ['Ha declaradas (sección)', 'decimal'] },
        kpis: (kpis, respuesta, filtros) => kpis.map((kpi) => {
            const total = respuesta.filas.length;
            if (kpi.etiqueta === 'Lotes sin sección') {
                const pct = total ? (Number(kpi.valor) * 100) / total : 0;
                return { ...kpi, ayuda: Number(kpi.valor) > 0 ? `${new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 }).format(pct)}% de los lotes` : 'Todos asignados' };
            }
            if (kpi.etiqueta === 'Lotes' && filtros.estado === 'T') {
                return { ...kpi, ayuda: `${formatear(respuesta.filas.filter((fila) => fila.estado === 'Activo').length, 'entero')} activos` };
            }
            return kpi;
        })
    },
    'lotes-por-variedad': {
        ocultas: ['grupo', 'procedencia', 'sinVariedad', 'pctHa'],
        unidad: ['lote', 'lotes'],
        alertaTitulo: 'Lotes sin variedad asignada',
        ayudas: { variedad: 'Incluye «Sin variedad».', estado: 'Por defecto solo lotes activos.' },
        anchos: { finca: 'min-width:170px', lote: 'min-width:200px', siembra: 'width:90px', hNetas: 'width:100px', palmasProduccion: 'width:120px', densidad: 'width:90px', estado: 'width:90px' },
        cuenta: (fila) => texto(fila.lote) !== '',
        vacia: (fila) => (texto(fila.lote) === '' ? 'Sin lotes registrados' : ''),
        alerta: (fila) => Boolean(fila.sinVariedad),
        subtitulo: (fila) => [fila.sinVariedad ? 'Variedad vacía o inexistente' : (texto(fila.procedencia) ? `Procedencia: ${texto(fila.procedencia)}` : ''), `${texto(fila.pctHa)} de las Ha netas`].filter(Boolean).join(' · '),
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        titulo: 'Hectáreas netas por variedad',
        detalle: { pctHa: ['% de Ha netas', 'texto'], lotes: ['Lotes', 'entero'], palmasProduccion: ['Palmas producción', 'entero'], procedencia: ['Procedencia', 'texto'] },
        etiquetaBarra: true,
        ordenServidor: true
    },
    'lotes-por-anio-siembra': {
        ocultas: ['grupo', 'edad', 'pctHa', 'sinAnio'],
        unidad: ['lote', 'lotes'],
        alertaTitulo: 'Lotes sin año de siembra',
        ayudas: { anioDesde: 'Con rango se excluyen lotes sin año.', variedad: 'Incluye «Sin variedad».', estado: 'Por defecto solo lotes activos.' },
        anchos: { finca: 'min-width:170px', lote: 'min-width:200px', mesSiembra: 'width:100px', variedad: 'min-width:150px', hNetas: 'width:100px', palmasProduccion: 'width:120px', estadoCultivo: 'width:110px', estado: 'width:90px' },
        cuenta: (fila) => texto(fila.lote) !== '',
        vacia: (fila) => (texto(fila.lote) === '' ? 'Sin lotes registrados' : ''),
        alerta: (fila) => Boolean(fila.sinAnio),
        subtitulo: (fila, filas) => {
            if (fila.sinAnio) return `Año de siembra vacío o en 0 · ${texto(fila.pctHa)} de las Ha netas`;
            const ha = (cultivo) => filas.filter((actual) => actual.estadoCultivo === cultivo).reduce((total, actual) => total + Number(actual.hNetas || 0), 0);
            return [`${texto(fila.pctHa)} de las Ha netas`, ...['Producción', 'Desarrollo'].filter((cultivo) => ha(cultivo) > 0).map((cultivo) => `${cultivo} ${formatear(ha(cultivo), 'decimal')} ha`)].join(' · ');
        },
        titulo: 'Hectáreas netas por año de siembra',
        detalle: { hNetas: ['Ha netas', 'decimal'], edad: ['Edad', 'texto'], lotes: ['Lotes', 'entero'], palmasProduccion: ['Palmas producción', 'entero'], pctHa: ['% de Ha netas', 'texto'] },
        ordenServidor: true
    }
};

const estado = { informe: null, respuesta: null, filtros: null, grafica: null, turno: 0, vigente: false };

const especifico = () => ESPECIFICOS[estado.informe?.clave] || {};
const empresaId = () => $id('empresa').value;
const informesOrdenados = () => [...INFORMES.informes].sort((a, b) => (a.estado === 'disponible' ? 0 : 1) - (b.estado === 'disponible' ? 0 : 1));
const buscarInforme = (clave) => INFORMES.informes.find((informe) => informe.clave === clave) || null;
const chipEstado = (informe) => (informe.estado === 'disponible'
    ? '<span class="gp-chip-fila verde">Disponible</span>'
    : '<span class="inf-chip-pronto">Próximamente</span>');

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
        respuesta = await fetch(URL_INF + ruta, { method: 'POST', body: cuerpo, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    } catch (error) {
        throw Object.assign(new Error('Error de conexión.'), { status: 0 });
    }

    if (ruta === 'exportar' && respuesta.ok && !(respuesta.headers.get('Content-Type') || '').includes('json')) return respuesta;

    let resultado = null;

    try {
        resultado = await respuesta.json();
    } catch (error) {
        resultado = null;
    }

    if (!respuesta.ok || resultado?.success !== true) {
        throw Object.assign(new Error(texto(resultado?.message) || (respuesta.status === 403 ? 'No tiene permiso para consultar este informe.' : 'No se pudo completar la operación.')), { status: respuesta.status });
    }

    return resultado;
};

const plantillaInforme = (informe, simple) => {
    if (!informe) return '';

    const pronto = informe.estado !== 'disponible' ? ' <span class="inf-chip-pronto">Próximamente</span>' : '';

    if (simple) return $(`<span><i class="${escaparHtml(informe.icono)} me-1" aria-hidden="true"></i>${escaparHtml(informe.nombre)}${pronto}</span>`);

    return $(`<div class="inf-opcion${informe.estado !== 'disponible' ? ' inf-opcion--pronto' : ''}">
        <span class="inf-opcion-nombre"><i class="${escaparHtml(informe.icono)}" aria-hidden="true"></i>${escaparHtml(informe.nombre)}${pronto}</span>
        <span class="inf-opcion-desc">${escaparHtml(informe.descripcion)}</span>
    </div>`);
};

const valorDefecto = (filtro) => texto(filtro.defecto);

const controlFiltro = (filtro) => {
    const clave = escaparHtml(filtro.clave);
    const espec = especifico();
    const obligatorio = filtro.obligatorio ? ' <span class="text-danger">*</span>' : '';
    const etiqueta = `<label class="form-label mb-1 small fw-semibold text-muted" for="f_${clave}">${escaparHtml(filtro.etiqueta)}${obligatorio}</label>`;
    const ayuda = espec.ayudas?.[filtro.clave] || filtro.ayuda;
    const pie = `<div class="invalid-feedback">Este filtro es obligatorio.</div>${ayuda ? `<div class="form-text">${escaparHtml(ayuda)}</div>` : ''}`;
    let opciones = filtro.opciones || [];

    if (filtro.tipo === 'rango') {
        return `<div class="col-12 col-lg-5">
            <label class="form-label mb-1 small fw-semibold text-muted" for="f_${clave}_desde">${escaparHtml(filtro.etiqueta)}${obligatorio}</label>
            <div class="input-group has-validation">
                <input type="date" class="form-control" id="f_${clave}_desde" data-filtro="${escaparHtml(filtro.desde || `${filtro.clave}Desde`)}" value="${escaparHtml(filtro.defecto?.desde || '')}">
                <span class="input-group-text">a</span>
                <input type="date" class="form-control" id="f_${clave}_hasta" data-filtro="${escaparHtml(filtro.hasta || `${filtro.clave}Hasta`)}" value="${escaparHtml(filtro.defecto?.hasta || '')}">
                ${pie}
            </div>
        </div>`;
    }

    if (filtro.tipo === 'mes' && !opciones.length) opciones = MESES.map((mes, indice) => ({ valor: indice + 1, texto: mes }));

    const control = ['select', 'select2', 'anio', 'mes'].includes(filtro.tipo)
        ? `<select class="form-select" id="f_${clave}" data-filtro="${clave}"${filtro.tipo === 'select2' ? ' data-buscador="1"' : ''}>
            ${filtro.obligatorio && valorDefecto(filtro) !== '' ? '' : `<option value="">${filtro.tipo !== 'select2' ? escaparHtml(filtro.placeholder || '') : ''}</option>`}
            ${opciones.map((opcion) => `<option value="${escaparHtml(opcion.valor)}"${texto(opcion.valor) === valorDefecto(filtro) ? ' selected' : ''}>${escaparHtml(opcion.texto)}</option>`).join('')}
        </select>`
        : `<input type="${filtro.tipo === 'fecha' ? 'date' : 'text'}" class="form-control" id="f_${clave}" data-filtro="${clave}" value="${escaparHtml(valorDefecto(filtro))}" autocomplete="off">`;

    return `<div class="col-12 col-sm-6 col-lg-3">${etiqueta}${filtro.tipo === 'select2' ? `<div class="inf-select2">${control}</div>` : control}${pie}</div>`;
};

const pintarFiltros = (informe) => {
    $id('informe_cuerpo').innerHTML = `<form id="form_filtros" novalidate>
        <div class="row g-2 align-items-start">
            ${(informe.filtros || []).map(controlFiltro).join('')}
            <div class="col-12 col-lg-auto ms-lg-auto">
                <span class="form-label mb-1 small d-none d-lg-block invisible" aria-hidden="true">Acciones</span>
                <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary flex-fill" id="btn_limpiar"><i class="bi bi-eraser me-1" aria-hidden="true"></i>Limpiar</button>
                <button type="submit" class="btn btn-primary flex-fill" id="btn_consultar"><i class="bi bi-search me-1" aria-hidden="true"></i>Consultar</button>
                </div>
            </div>
        </div>
    </form>`;

    $('#form_filtros [data-buscador]').each((_, elemento) => {
        const filtro = informe.filtros.find((item) => item.clave === elemento.dataset.filtro);

        $(elemento).select2({
            width: '100%', allowClear: true,
            placeholder: filtro.placeholder || 'Todos',
            language: { noResults: () => 'No se encontraron resultados' }
        });
    });
};

const valoresFiltros = () => Object.fromEntries(Array.from(document.querySelectorAll('#form_filtros [data-filtro]')).map((control) => [control.dataset.filtro, control.value]));

const textoFiltros = () => (estado.informe.filtros || []).map((filtro) => {
    const control = $id(`f_${filtro.clave}`);

    if (filtro.tipo === 'rango') {
        const desde = $id(`f_${filtro.clave}_desde`).value;
        const hasta = $id(`f_${filtro.clave}_hasta`).value;

        return desde || hasta ? `${filtro.etiqueta}: ${desde || '…'} a ${hasta || '…'}` : '';
    }

    if (!control) return '';

    const valor = control.tagName === 'SELECT' ? (control.value === '' ? (filtro.placeholder || 'Todos') : control.selectedOptions[0]?.text) : control.value;

    return valor ? `${filtro.etiqueta}: ${valor}` : '';
}).filter(Boolean);

const validarFiltros = () => {
    let valido = true;

    (estado.informe.filtros || []).filter((filtro) => filtro.obligatorio).forEach((filtro) => {
        (filtro.tipo === 'rango' ? [`f_${filtro.clave}_desde`, `f_${filtro.clave}_hasta`] : [`f_${filtro.clave}`]).forEach((id) => {
            const vacio = texto($id(id)?.value) === '';

            $id(id)?.classList.toggle('is-invalid', vacio);
            valido = valido && !vacio;
        });
    });

    return valido;
};

const destruirResultados = () => {
    estado.grafica?.destroy();
    estado.grafica = null;

    try {
        if ($.fn.DataTable.isDataTable('#tabla_informe')) $('#tabla_informe').DataTable().destroy();
    } catch (error) {
        $('#tabla_informe').empty();
    }

    $('#tabla_informe').empty();
};

const marcarDesactualizado = () => {
    if (!estado.respuesta) return;

    estado.vigente = false;
    $id('btn_exportar').disabled = true;
    mostrar($id('chip_desactualizado'), true);
    $id('resultado_cuerpo').classList.add('inf-desactualizado');
};

const pintarInicio = () => {
    const disponibles = informesOrdenados().filter((informe) => informe.estado === 'disponible').slice(0, 6);

    $id('estado_inicial').innerHTML = `<div class="card mb-3"><div class="card-body">${estadoVacio('bi-bar-chart-line', 'Elija un informe',
        `<p class="text-muted mb-3">Use el selector para ver los informes de ${escaparHtml(INFORMES.nombre)}.</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">${disponibles.map((informe) => `<button type="button" class="btn btn-outline-success btn-sm" data-informe="${escaparHtml(informe.clave)}"><i class="${escaparHtml(informe.icono)} me-1" aria-hidden="true"></i>${escaparHtml(informe.nombre)}</button>`).join('')}</div>`)}</div></div>`;
};

const elegirInforme = (clave) => {
    const informe = buscarInforme(clave);

    destruirResultados();
    estado.informe = informe;
    estado.respuesta = null;
    estado.vigente = false;
    mostrar($id('zona_resultados'), false);
    mostrar($id('card_informe'), Boolean(informe));
    mostrar($id('estado_inicial'), !informe);

    const url = new URL(window.location.href);

    if (informe) url.searchParams.set('informe', informe.clave);
    else url.searchParams.delete('informe');

    history.replaceState(null, '', url.toString());

    try {
        if (informe) localStorage.setItem(`informes:${INFORMES.modulo}`, informe.clave);
    } catch (error) {
        history.replaceState(null, '', url.toString());
    }

    if (!informe) {
        pintarInicio();

        return;
    }

    $id('informe_icono').className = informe.icono;
    $id('informe_nombre').textContent = informe.nombre;
    $id('informe_descripcion').textContent = informe.descripcion;
    $id('informe_chip').innerHTML = chipEstado(informe);

    if (informe.estado !== 'disponible') {
        $id('informe_cuerpo').innerHTML = estadoVacio('bi-hourglass-split', 'Este informe estará disponible próximamente',
            `<p class="text-muted mb-1">${escaparHtml(informe.descripcion)}</p>${informe.plan ? `<div class="gp-subtexto">Plan: ${escaparHtml(informe.plan)}</div>` : ''}`);

        return;
    }

    pintarFiltros(informe);
};

const skeleton = () => `<div class="placeholder-glow">
    <div class="row g-3 mb-3">${'<div class="col-6 col-lg-3"><div class="inf-kpi d-block"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-8 placeholder-lg"></span></div></div>'.repeat(4)}</div>
    <div class="card mb-3"><div class="card-body"><div class="inf-grafica d-flex"><span class="placeholder w-100 rounded"></span></div></div></div>
    <div class="card"><div class="card-body">${'<span class="placeholder col-12 mb-2"></span>'.repeat(6)}</div></div>
</div>`;

const ocupado = (activo) => {
    const boton = $id('btn_consultar');

    boton.disabled = activo;
    $id('btn_limpiar').disabled = activo;
    boton.innerHTML = activo
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Consultando…'
        : '<i class="bi bi-search me-1" aria-hidden="true"></i>Consultar';
    $id('zona_resultados').setAttribute('aria-busy', activo ? 'true' : 'false');
};

const columnaKpi = (total) => (total === 3 ? 'col-6 col-lg-4' : (total >= 5 ? 'col-6 col-md-4 col-xl' : 'col-6 col-lg-3'));

const pintarKpis = (kpis) => `<div class="row g-3 mb-3">${kpis.map((kpi) => `<div class="${columnaKpi(kpis.length)}">
    <div class="inf-kpi">
        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="${escaparHtml(kpi.icono || 'bi bi-hash')}" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <span class="gp-etiqueta-mini d-block">${escaparHtml(kpi.etiqueta)}</span>
            <div class="inf-kpi-valor" title="${escaparHtml(formatear(kpi.valor, kpi.formato))}">${escaparHtml(formatear(kpi.valor, kpi.formato))}</div>
            ${kpi.ayuda ? `<div class="gp-subtexto">${escaparHtml(kpi.ayuda)}</div>` : ''}
        </div>
    </div>
</div>`).join('')}</div>`;

const LOCALE_ES = {
    name: 'es',
    options: {
        months: MESES,
        shortMonths: MESES.map((mes) => mes.slice(0, 3)),
        days: DIAS,
        shortDays: DIAS.map((dia) => dia.slice(0, 3)),
        toolbar: { download: 'Descargar', selection: 'Selección', selectionZoom: 'Zoom', zoomIn: 'Acercar', zoomOut: 'Alejar', pan: 'Mover', reset: 'Restablecer' }
    }
};

const opcionesGrafica = (grafica) => {
    const espec = especifico();
    let orden = grafica.categorias.map((_, indice) => indice);

    if (grafica.horizontal && grafica.series.length === 1 && !espec.ordenServidor) orden = orden.sort((a, b) => Number(grafica.series[0].datos[b]) - Number(grafica.series[0].datos[a]));

    const categorias = orden.map((indice) => grafica.categorias[indice]);
    const detalle = grafica.detalle ? orden.map((indice) => grafica.detalle[indice]) : null;
    const series = grafica.series.map((serie) => ({ name: serie.nombre, type: serie.tipo, data: orden.map((indice) => Number(serie.datos[indice] || 0)) }));
    const etiqueta = espec.categoria || ((valor) => valor);
    const formato = grafica.formato || 'decimal';
    const ejes = { fontSize: '12px', colors: '#5c6b7a' };
    const estiloTitulo = { fontSize: '12px', color: '#5c6b7a', fontWeight: 600 };
    const pequena = window.innerWidth < 576;
    const alto = grafica.horizontal ? Math.min(520, Math.max(240, categorias.length * 44 + 60)) : (pequena ? 280 : 320);
    const recortar = (valor) => (texto(valor).length > 24 ? `${texto(valor).slice(0, 23)}…` : valor);
    const mayor = Math.max(0, ...(grafica.apilada ? categorias.map((_, indice) => series.reduce((total, serie) => total + serie.data[indice], 0)) : series.flatMap((serie) => serie.data)));
    const base = mayor / (espec.etiquetaBarra ? 0.65 : 0.8);
    const potencia = mayor > 0 ? 10 ** Math.floor(Math.log10(base)) : 1;
    const paso = base / potencia > 5 ? potencia * 2 : (base / potencia <= 2 ? potencia / 2 : potencia);
    const marcas = mayor > 0 ? Math.ceil(base / paso) : undefined;
    const tope = mayor > 0 ? Number((marcas * paso).toFixed(6)) : undefined;
    const escala = { min: 0, max: tope, tickAmount: marcas };
    const salto = !grafica.horizontal && pequena && categorias.length > 12 ? Math.ceil(categorias.length / 6) : 0;
    const yaxis = {
        labels: { maxWidth: 160, style: ejes, formatter: grafica.horizontal ? recortar : (valor) => formatear(valor, paso >= 1 ? 'entero' : formato) },
        ...(!grafica.horizontal ? escala : {}),
        ...(!grafica.horizontal && grafica.ejeY ? { title: { text: grafica.ejeY, style: estiloTitulo } } : {})
    };

    return {
        chart: {
            type: grafica.tipo === 'combo' ? 'line' : grafica.tipo, height: alto, stacked: Boolean(grafica.apilada),
            toolbar: { show: false }, zoom: { enabled: false }, animations: { speed: 350 }, parentHeightOffset: 0,
            fontFamily: getComputedStyle(document.body).fontFamily, locales: [LOCALE_ES], defaultLocale: 'es'
        },
        colors: grafica.colores || (series.length === 1 ? ['#41a867'] : COLORES),
        series,
        plotOptions: { bar: { horizontal: Boolean(grafica.horizontal), borderRadius: 4, borderRadiusApplication: 'end', barHeight: '60%', ...(espec.etiquetaBarra ? { dataLabels: { position: 'top' } } : {}) } },
        dataLabels: {
            enabled: Boolean(grafica.horizontal),
            formatter: espec.etiquetaBarra
                ? (valor, opciones) => [formatear(valor, formato), texto(detalle?.[opciones.dataPointIndex]?.pctHa)].filter(Boolean).join(' · ')
                : (valor) => formatear(valor, formato),
            ...(espec.etiquetaBarra ? { textAnchor: 'start', offsetX: 6 } : {}),
            style: { fontSize: '11px', fontWeight: 600, colors: [espec.etiquetaBarra ? '#2f3b47' : '#fff'] }
        },
        xaxis: {
            categories: categorias.map(etiqueta),
            title: { text: grafica.ejeX || undefined, style: estiloTitulo },
            labels: { style: ejes, formatter: grafica.horizontal ? (valor) => formatear(valor, paso >= 1 ? 'entero' : formato) : (salto ? (valor) => (categorias.map(etiqueta).indexOf(valor) % salto ? '' : valor) : undefined), ...(salto ? { rotate: 0 } : {}) },
            ...(grafica.horizontal && !grafica.apilada ? escala : {})
        },
        yaxis,
        grid: { borderColor: '#eef1f4', strokeDashArray: 3, padding: { left: 8, right: 16 } },
        legend: { show: series.length > 1, position: 'bottom' },
        tooltip: detalle
            ? {
                theme: 'light',
                custom: ({ dataPointIndex }) => {
                    const filas = [...series.map((serie) => [serie.name, formatear(serie.data[dataPointIndex], formato)]),
                        ...Object.entries(espec.detalle || {}).map(([clave, [titulo, tipo]]) => [titulo, formatear(detalle[dataPointIndex]?.[clave], tipo)])];

                    return `<div class="inf-tooltip"><div class="fw-semibold mb-1">${codigoNombre(categorias[dataPointIndex])}</div>
                        ${filas.map(([titulo, valor]) => `<div class="d-flex justify-content-between gap-3"><span>${escaparHtml(titulo)}:</span><span class="inf-num">${escaparHtml(valor)}</span></div>`).join('')}</div>`;
                }
            }
            : { theme: 'light', y: { formatter: (valor) => formatear(valor, formato) } },
        responsive: [{ breakpoint: 576, options: { dataLabels: { enabled: false }, yaxis: grafica.horizontal ? { labels: { maxWidth: 100 } } : { ...yaxis, labels: { ...yaxis.labels, maxWidth: 100 }, title: { text: undefined } } } }]
    };
};

const renderCelda = (columna) => (dato, tipo, fila) => {
    const espec = especifico();

    if (NUMERICOS.includes(columna.tipo)) {
        if (tipo !== 'display') return Number(dato || 0);

        const marca = espec.marca?.[columna.clave]?.(fila)
            ? `<span class="gp-punto-cambio me-1" title="${escaparHtml(espec.marcaTitulo || '')}"></span>`
            : '';

        return marca + formatear(dato, columna.tipo);
    }

    if (tipo !== 'display') return texto(dato);

    if (columna.tipo === 'estado') {
        if (texto(dato) === '') return '';

        return /^(activo|vigente|si|1|producci[oó]n)$/i.test(texto(dato))
            ? `<span class="gp-chip-fila verde">${escaparHtml(dato)}</span>`
            : `<span class="gp-chip-fila inf-chip-pronto">${escaparHtml(dato)}</span>`;
    }

    if (columna.tipo === 'fecha' || columna.alineacion === 'centro') return `<span class="font-monospace">${escaparHtml(dato)}</span>`;

    if (partes(dato).length > 1 && /^[\w.-]{1,20}$/.test(partes(dato)[0])) return codigoNombre(dato);

    return texto(dato).length > 40 ? `<span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(dato)}">${escaparHtml(dato)}</span>` : escaparHtml(dato);
};

const claseColumna = (columna) => (NUMERICOS.includes(columna.tipo) ? 'inf-num' : (columna.tipo === 'estado' || columna.tipo === 'fecha' || columna.alineacion === 'centro' ? 'text-center' : ''));

const pintarTabla = (respuesta) => {
    const espec = especifico();
    const columnas = respuesta.columnas;
    const ocultas = espec.ocultas || [];
    const visibles = columnas.filter((columna) => !ocultas.includes(columna.clave));
    const primeraNumerica = Math.max(1, visibles.findIndex((columna) => NUMERICOS.includes(columna.tipo)));
    const indiceGrupo = columnas.findIndex((columna) => columna.clave === respuesta.agrupar);
    const posicion = new Map();
    respuesta.filas.forEach((fila, indice) => posicion.has(fila[respuesta.agrupar]) || posicion.set(fila[respuesta.agrupar], String(indice).padStart(6, '0')));
    const suma = (filas, clave) => filas.reduce((total, fila) => total + Number(fila[clave] || 0), 0);
    const tabla = $id('tabla_informe');

    tabla.classList.toggle('gp-tabla-ancha', visibles.length > 10);
    tabla.style.minWidth = visibles.length > 10 ? '' : `${visibles.length * 110}px`;
    tabla.innerHTML = `<thead class="gp-thead"><tr>${columnas.map((columna) => `<th class="${claseColumna(columna)}" style="${espec.anchos?.[columna.clave] || ''}">${escaparHtml(columna.titulo)}</th>`).join('')}</tr></thead>
        <tbody></tbody>
        <tfoot class="inf-pie"><tr>${columnas.map(() => '<th></th>').join('')}</tr></tfoot>`;

    const cantidad = (filas) => {
        const total = filas.filter(espec.cuenta || (() => true)).length;
        const [uno, varios] = espec.unidad || ['registro', 'registros'];

        return `${formatear(total, 'entero')} ${total === 1 ? uno : varios}`;
    };

    $('#tabla_informe').DataTable({
        data: respuesta.filas,
        columns: columnas.map((columna) => ({ data: columna.clave, visible: !ocultas.includes(columna.clave), className: claseColumna(columna), render: espec.ordenServidor && columna.clave === respuesta.agrupar ? (dato, tipo, fila) => (tipo === 'sort' ? posicion.get(dato) : renderCelda(columna)(dato, tipo, fila)) : renderCelda(columna) })),
        order: indiceGrupo >= 0 ? [] : [[0, 'asc']],
        orderFixed: indiceGrupo >= 0 ? [[indiceGrupo, 'asc']] : undefined,
        rowGroup: indiceGrupo >= 0 ? {
            dataSrc: respuesta.agrupar,
            startRender: (filas, grupo) => {
                const datos = $('#tabla_informe').DataTable().rows({ search: 'applied' }).data().toArray().filter((fila) => fila[respuesta.agrupar] === grupo);
                const subtitulo = espec.subtitulo?.(datos[0], datos) || '';
                const alerta = espec.alerta?.(datos[0]);
                const fila = $('<tr/>');

                fila.append(`<td colspan="${primeraNumerica}">
                    <div class="d-flex flex-wrap align-items-center gap-2"><span class="fw-semibold">${codigoNombre(grupo)}</span><span class="gp-chip-fila ${alerta ? `ambar" title="${escaparHtml(espec.alertaTitulo || '')}` : 'verde'}">${cantidad(datos)}</span></div>
                    ${subtitulo ? `<div class="gp-subtexto">${escaparHtml(subtitulo)}</div>` : ''}
                </td>`);
                visibles.slice(primeraNumerica).forEach((columna) => fila.append(`<td class="${claseColumna(columna)}">${columna.total ? formatear(suma(datos, columna.clave), columna.tipo) : ''}</td>`));

                return fila;
            }
        } : undefined,
        createdRow: (fila, datos) => {
            const vacia = espec.vacia?.(datos);

            if (!vacia) return;

            Array.from(fila.cells).forEach((celda, indice) => {
                if (indice > 0) {
                    celda.classList.add('d-none');

                    return;
                }

                celda.colSpan = visibles.length;
                celda.className = 'text-muted fst-italic';
                celda.textContent = vacia;
            });
        },
        footerCallback: function () {
            const api = this.api();
            const datos = api.rows({ search: 'applied' }).data().toArray();

            columnas.forEach((columna, indice) => {
                const pie = api.column(indice).footer();

                pie.className = claseColumna(columna);
                pie.innerHTML = columna.total ? formatear(suma(datos, columna.clave), columna.tipo) : '';
            });

            api.column(columnas.indexOf(visibles[0])).footer().innerHTML = 'Total general';
        },
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Todos']],
        autoWidth: false,
        dom: "<'d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2'lf><'table-responsive't><'d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2'ip>",
        language: {
            lengthMenu: 'Mostrar _MENU_ registros',
            zeroRecords: 'Ningún registro coincide con la búsqueda',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            emptyTable: 'No hay datos disponibles',
            search: 'Buscar:',
            searchPlaceholder: 'Buscar en el detalle…',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        }
    });
};

const pintarResultado = (respuesta) => {
    const espec = especifico();
    const filas = respuesta.filas || [];
    const kpis = espec.kpis ? espec.kpis(respuesta.kpis || [], respuesta, estado.filtros) : (respuesta.kpis || []);
    const avisos = (respuesta.avisos || []).map((aviso) => `<div class="gp-nota-alerta mb-3" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>${escaparHtml(aviso)}</div>`).join('');
    $id('contador_registros').innerHTML = `<i class="bi bi-hash me-1" aria-hidden="true"></i><strong>${formatear(filas.length, 'entero')}</strong> ${filas.length === 1 ? 'registro' : 'registros'}`;

    if (filas.length === 0) {
        $id('resultado_cuerpo').innerHTML = avisos + `<div class="card">${estadoVacio('bi-inbox', 'No hay datos con estos filtros',
            '<p class="text-muted mb-3">Pruebe con otros filtros.</p><button type="button" class="btn btn-outline-secondary btn-sm" data-accion="limpiar">Limpiar filtros</button>')}</div>`;
        $id('btn_exportar').disabled = true;

        return;
    }

    $id('resultado_cuerpo').innerHTML = avisos + pintarKpis(kpis)
        + (respuesta.grafica ? `<div class="card mb-3"><div class="card-header bg-white py-3"><h6 class="gp-seccion mb-0"><i class="bi bi-bar-chart" aria-hidden="true"></i>${escaparHtml(espec.titulo || respuesta.grafica.titulo || respuesta.informe?.nombre || 'Gráfica')}</h6></div><div class="card-body"><div class="inf-grafica" id="grafica"></div></div></div>` : '')
        + `<div class="card"><div class="card-header bg-white py-3"><h6 class="gp-seccion mb-0"><i class="bi bi-table" aria-hidden="true"></i>Detalle</h6></div>
            <div class="card-body p-0"><table class="table table-hover align-middle mb-0 gp-tabla inf-tabla w-100" id="tabla_informe"></table></div></div>`;

    if (respuesta.grafica) {
        estado.grafica = new ApexCharts($id('grafica'), opcionesGrafica(respuesta.grafica));
        estado.grafica.render();
    }

    pintarTabla(respuesta);
    estado.vigente = true;
    $id('btn_exportar').disabled = false;
};

const parametros = (filtros = valoresFiltros()) => ({ empresa: empresaId(), modulo: INFORMES.modulo, informe: estado.informe.clave, filtros: JSON.stringify(filtros) });

const consultar = async () => {
    if (!estado.informe || !validarFiltros()) return;

    const turno = ++estado.turno;
    const datos = parametros();

    destruirResultados();
    estado.filtros = valoresFiltros();
    ocupado(true);
    $id('btn_exportar').disabled = true;
    mostrar($id('zona_resultados'), true);
    mostrar($id('chip_desactualizado'), false);
    $id('resultado_cuerpo').classList.remove('inf-desactualizado');
    $id('chips_filtros').innerHTML = textoFiltros().map((valor) => `<span class="gp-chip-fila inf-chip-filtro">${escaparHtml(valor)}</span>`).join('');
    $id('contador_registros').innerHTML = '';
    $id('resultado_cuerpo').innerHTML = skeleton();

    let respuesta;

    try {
        respuesta = await pedir('consultar', datos);
    } catch (error) {
        if (turno !== estado.turno) return;

        ocupado(false);
        estado.respuesta = null;
        $id('resultado_cuerpo').innerHTML = `<div class="card">${estadoVacio('bi-exclamation-octagon', 'No se pudo generar el informe',
            `<p class="text-muted mb-3">${escaparHtml(error.message)}</p><button type="button" class="btn btn-outline-primary btn-sm" data-accion="reintentar">Reintentar</button>`, 'inf-medallon-error')}</div>`;

        if (error.status === 403) Swal.fire({ icon: 'warning', title: 'Acción no permitida', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });

        return;
    }

    if (turno !== estado.turno) return;

    ocupado(false);
    estado.respuesta = respuesta;
    pintarResultado(respuesta);
};

const exportar = async () => {
    if (!estado.vigente) return;

    const boton = $id('btn_exportar');
    const original = boton.innerHTML;

    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Exportando…';

    try {
        const respuesta = await pedir('exportar', parametros(estado.filtros));
        const nombre = (respuesta.headers.get('Content-Disposition') || '').match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)?.[1];
        const enlace = document.createElement('a');

        enlace.href = URL.createObjectURL(await respuesta.blob());
        enlace.download = nombre ? decodeURIComponent(nombre) : `${estado.informe.clave}.xlsx`;
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(enlace.href), 1000);
    } catch (error) {
        Swal.fire({ icon: error.status === 403 ? 'warning' : 'error', title: error.status === 403 ? 'Acción no permitida' : 'No se pudo exportar', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }

    boton.innerHTML = original;
    boton.disabled = !estado.vigente;
};

const limpiarFiltros = () => {
    (estado.informe?.filtros || []).forEach((filtro) => {
        if (filtro.tipo === 'rango') {
            $id(`f_${filtro.clave}_desde`).value = filtro.defecto?.desde || '';
            $id(`f_${filtro.clave}_hasta`).value = filtro.defecto?.hasta || '';

            return;
        }

        $(`#f_${filtro.clave}`).val(valorDefecto(filtro)).trigger('change.select2');
    });
    document.querySelectorAll('#form_filtros .is-invalid').forEach((control) => control.classList.remove('is-invalid'));
    marcarDesactualizado();
};

const recargarDependientes = async (origen) => {
    const dependientes = (estado.informe?.filtros || []).filter((filtro) => [].concat(filtro.depende || []).includes(origen));

    for (const filtro of dependientes) {
        try {
            const resultado = await pedir('opciones', { empresa: empresaId(), modulo: INFORMES.modulo, informe: estado.informe.clave, filtro: filtro.clave, dependencias: JSON.stringify(valoresFiltros()) });
            const control = $id(`f_${filtro.clave}`);

            control.innerHTML = '<option value=""></option>' + (resultado.filas || []).map((opcion) => `<option value="${escaparHtml(opcion.valor)}">${escaparHtml(opcion.texto)}</option>`).join('');
            $(control).trigger('change.select2');
        } catch (error) {
            Swal.fire({ icon: 'warning', title: 'No se pudieron cargar las opciones', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const ordenados = informesOrdenados();
    const disponibles = ordenados.filter((informe) => informe.estado === 'disponible').length;

    $id('contador_informes').innerHTML = `<b>${disponibles}</b> de ${ordenados.length} informes disponibles`;
    $id('selector_informe').insertAdjacentHTML('beforeend', ordenados.map((informe) => `<option value="${escaparHtml(informe.clave)}">${escaparHtml(informe.nombre)}</option>`).join(''));

    $('#selector_informe').select2({
        width: '100%', placeholder: 'Buscar informe…', allowClear: true,
        language: { noResults: () => 'No se encontraron informes' },
        templateResult: (opcion) => (opcion.id ? plantillaInforme(buscarInforme(opcion.id), false) : opcion.text),
        templateSelection: (opcion) => (opcion.id ? plantillaInforme(buscarInforme(opcion.id), true) : opcion.text),
        matcher: (params, opcion) => {
            if (!texto(params.term)) return opcion;

            const informe = buscarInforme(opcion.id);

            return informe && sinAcentos(`${informe.nombre} ${informe.descripcion}`).includes(sinAcentos(params.term)) ? opcion : null;
        }
    }).on('change', () => elegirInforme($id('selector_informe').value));

    let inicial = new URL(window.location.href).searchParams.get('informe') || INFORMES.seleccionado || '';

    try {
        inicial = inicial || localStorage.getItem(`informes:${INFORMES.modulo}`) || '';
    } catch (error) {
        inicial = inicial || '';
    }

    $('#selector_informe').val(buscarInforme(inicial) ? inicial : '').trigger('change.select2');
    elegirInforme($id('selector_informe').value);

    $id('estado_inicial').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-informe]');

        if (boton) $('#selector_informe').val(boton.dataset.informe).trigger('change');
    });

    $id('informe_cuerpo').addEventListener('submit', (evento) => {
        evento.preventDefault();
        consultar();
    });
    $id('informe_cuerpo').addEventListener('click', (evento) => {
        if (evento.target.closest('#btn_limpiar')) limpiarFiltros();
    });
    $('#informe_cuerpo').on('change input', '[data-filtro]', (evento) => {
        evento.target.classList.remove('is-invalid');
        marcarDesactualizado();

        if (evento.type === 'change') recargarDependientes(evento.target.dataset.filtro);
    });

    $id('resultado_cuerpo').addEventListener('click', (evento) => {
        const accion = evento.target.closest('[data-accion]')?.dataset.accion;

        if (accion === 'reintentar') consultar();

        if (accion === 'limpiar') limpiarFiltros();
    });
    $id('btn_exportar').addEventListener('click', exportar);

    $id('filtro_empresa').addEventListener('change', () => {
        const url = new URL(window.location.href);

        url.searchParams.set('empresa', $id('filtro_empresa').value);
        window.location.href = url.toString();
    });
});
