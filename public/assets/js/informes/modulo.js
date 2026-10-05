const URL_INF = BASE_URL + 'informes/reportes/';
const COLORES = ['#41a867', '#2f7a4b', '#7cc49a', '#4a5b6c', '#f0a500', '#8795a4', '#c9e6d6'];
const NUMERICOS = ['entero', 'decimal', 'moneda', 'porcentaje', 'variacion'];
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
    moneda: new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }),
    porcentaje: new Intl.NumberFormat('es-CO', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    variacion: new Intl.NumberFormat('es-CO', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1, signDisplay: 'exceptZero' })
};

const formatear = (valor, formato = 'decimal') => (valor == null ? '—' : FORMATOS[formato] ? FORMATOS[formato].format(Number(valor || 0)).replace(/^-(?=[^1-9]*$)/, '') : texto(valor));
const partes = (valor) => texto(valor).split(/\s+[–—-]\s+/);
const codigoNombre = (valor) => {
    const [codigo, ...resto] = partes(valor);

    return resto.length
        ? `<span class="font-monospace fw-semibold">${escaparHtml(codigo)}</span> — <span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(resto.join(' – '))}">${escaparHtml(resto.join(' – '))}</span>`
        : escaparHtml(valor);
};

const desvioPeso = (fila) => fila.pesoCalculado != null && Math.abs(Number(fila.diferencia)) > 0.05 * Number(fila.pesoCalculado);
const variacionAlta = (fila) => fila.variacion != null && Math.abs(fila.variacion) >= 0.2;
const contar = (total, uno, varios) => (total ? `${formatear(total, 'entero')} ${total === 1 ? uno : varios}` : '');
const AUX_INDICADORES = ['kilosDif', 'kilosConRacAnt', 'racPesoAnt', 'kilosConRacAct', 'racPesoAct', 'tonConHaAnt', 'tonConHaAct', 'hNetasPeso', 'hNetasAnt', 'hNetasAct', 'proyConHa'];
const ANCHOS_INDICADORES = { racimosAnt: 'width:100px', racimosAct: 'width:100px', kilosAnt: 'width:120px', kilosAct: 'width:120px', variacion: 'width:90px', pesoAnt: 'width:90px', pesoAct: 'width:90px', tonHaAnt: 'width:90px', tonHaAct: 'width:90px', proyeccion: 'width:100px' };
const MARCA_CAIDA = { variacion: (fila) => fila.variacion != null && fila.variacion <= -0.2 };

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
    },
    labores: {
        ocultas: ['grupo', 'desCorta', 'sinGrupo'],
        unidad: ['labor', 'labores'],
        ordenServidor: true,
        alerta: (fila) => Boolean(fila.sinGrupo),
        alertaTitulo: 'Labores sin grupo válido',
        ayudas: { grupo: 'Incluye «Sin grupo».', estado: 'Por defecto solo labores activas.' },
        anchos: { codigo: 'width:80px', labor: 'min-width:200px', clase: 'width:120px', uMedida: 'width:80px', signo: 'width:70px', concepto: 'min-width:170px', ciclos: 'width:70px', tarea: 'width:80px', parametros: 'min-width:200px', estado: 'width:90px' },
        subtitulo: (fila, filas) => {
            const sinConcepto = filas.filter((actual) => texto(actual.concepto) === '').length;
            const inactivas = estado.filtros?.estado === 'T' ? filas.filter((actual) => actual.estado === 'Inactiva').length : 0;
            return [fila.sinGrupo ? 'Grupo vacío o inexistente en el catálogo' : '', sinConcepto ? `${formatear(sinConcepto, 'entero')} sin concepto de nómina` : '', inactivas ? `${formatear(inactivas, 'entero')} ${inactivas === 1 ? 'inactiva' : 'inactivas'}` : ''].filter(Boolean).join(' · ');
        },
        titulo: 'Labores por grupo',
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        detalle: { sinConcepto: ['Sin concepto de nómina', 'entero'], porLote: ['Registran por lote', 'entero'], pct: ['% del total', 'texto'] },
        destacar: ['Racimo', 'Báscula', 'Jornal'],
        subtexto: { labor: (fila) => fila.desCorta },
        vacioCelda: { concepto: 'Sin concepto' }
    },
    'peso-promedio-racimos-lote': {
        ocultas: ['grupo', 'pesoXRacimos', 'difXRacimos', 'kgConRacimos'],
        unidad: ['lote', 'lotes'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { finca: 'min-width:170px', seccion: 'width:80px', lote: 'min-width:200px', vigencia: 'width:190px', origen: 'width:100px', pesoRacimo: 'width:110px', pesoCalculado: 'width:120px', diferencia: 'width:110px', kg: 'width:120px', racimos: 'width:90px' },
        marca: { diferencia: desvioPeso },
        marcaTitulo: 'Difiere más de 5 % del peso calculado',
        subtitulo: (fila, filas) => [
            contar(filas.filter((actual) => actual.origen === 'Manual').length, 'manual', 'manuales'),
            contar(filas.filter((actual) => actual.pesoCalculado == null).length, 'sin cosecha registrada', 'sin cosecha registrada'),
            contar(filas.filter(desvioPeso).length, 'con desvío > 5 %', 'con desvío > 5 %')
        ].filter(Boolean).join(' · '),
        detalle: { pesoCalculado: ['Peso calculado', 'decimal'], kg: ['Kg cosechados', 'decimal'], racimos: ['Racimos', 'entero'], lotes: ['Lotes', 'entero'], origen: ['Origen', 'texto'] }
    },
    'lista-precio-novedades': {
        ocultas: ['grupo', 'destajoRegistrado', 'sinGrupo', 'sinPrecio'],
        unidad: ['labor', 'labores'],
        ordenServidor: true,
        pageLength: 100,
        alerta: (fila) => Boolean(fila.sinGrupo),
        alertaTitulo: 'Labores sin grupo válido',
        anchos: { codigo: 'width:80px', labor: 'min-width:200px', uMedida: 'width:80px', destajo: 'width:120px', contratistas: 'width:120px', otros: 'width:120px', porcentaje: 'width:100px', baseSueldo: 'width:100px', destajoAnterior: 'width:120px', variacion: 'width:110px' },
        subtexto: { labor: (fila) => (fila.sinPrecio ? 'Sin precio en el año' : '') },
        marca: { variacion: variacionAlta },
        marcaTitulo: 'Variación de 20 % o más frente al año anterior',
        subtitulo: (fila, filas) => [
            fila.sinGrupo ? 'Grupo vacío o inexistente en el catálogo' : '',
            contar(filas.filter((actual) => actual.sinPrecio).length, 'sin precio', 'sin precio'),
            contar(filas.filter((actual) => actual.baseSueldo === 'Sí').length, 'a base sueldo', 'a base sueldo'),
            contar(filas.filter(variacionAlta).length, 'con variación ≥ 20 %', 'con variación ≥ 20 %')
        ].filter(Boolean).join(' · '),
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        detalle: { actual: ['Destajo actual', 'moneda'], anterior: ['Destajo año anterior', 'moneda'], grupo: ['Grupo', 'texto'] }
    },
    'finca-lote-metros-canal': {
        ocultas: ['finca'],
        unidad: ['lote', 'lotes'],
        cuenta: (fila) => fila.haUnica != null,
        ayudas: { tipoCanal: 'Solo lotes con canal de este tipo.', estado: 'Por defecto solo lotes activos.' },
        anchos: { seccion: 'width:90px', lote: 'min-width:200px', anioSiembra: 'width:90px', tipoCanal: 'min-width:170px', hNetas: 'width:100px', metros: 'width:110px', mHa: 'width:100px' },
        marca: { hNetas: (fila) => Number(fila.hNetas) <= 0 },
        marcaTitulo: 'Lote sin Ha netas: m/Ha no calculable',
        subtitulo: (fila, filas) => {
            const conCanal = filas.filter((actual) => actual.haUnica != null);
            const ha = conCanal.reduce((total, actual) => total + Number(actual.haUnica || 0), 0);
            return [
                `${formatear(conCanal.length, 'entero')} de ${contar(Number(fila.lotesFinca), 'lote con canal', 'lotes con canal') || '0 lotes con canal'}`,
                `Ha netas: ${formatear(ha, 'decimal')}`,
                contar(conCanal.filter((actual) => Number(actual.hNetas) <= 0).length, 'sin Ha netas', 'sin Ha netas')
            ].filter(Boolean).join(' · ');
        },
        titulo: 'Metros de canal por finca',
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        detalle: { total: ['Total metros', 'entero'], lotes: ['Lotes con canal', 'entero'], mHa: ['m/Ha', 'decimal'] }
    },
    'lote-linea-palmas': {
        ocultas: ['finca'],
        unidad: ['lote', 'lotes'],
        pageLength: 100,
        anchos: { lote: 'min-width:200px', lineasDeclaradas: 'width:100px', lineasCenso: 'width:100px', palmasBrutas: 'width:110px', palmasCenso: 'width:110px', erradicadas: 'width:100px', palmasProduccion: 'width:120px', produccionCenso: 'width:120px', diferencia: 'width:100px', estado: 'width:90px', linea: 'width:90px', lado: 'width:100px', palmas: 'width:120px', produccion: 'width:130px' },
        marca: {
            lineasCenso: (fila) => fila.lineasCenso !== fila.lineasDeclaradas,
            palmasCenso: (fila) => fila.palmasCenso != null && fila.palmasCenso !== fila.palmasBrutas,
            diferencia: (fila) => Number(fila.diferencia) !== 0,
            palmas: (fila) => Boolean(fila.atipica),
            produccion: (fila) => Boolean(fila.negativa)
        },
        marcaTitulo: (fila, clave) => ({
            lineasCenso: fila.sinCenso ? 'Sin censo registrado' : 'Difiere de las líneas del lote',
            palmasCenso: 'Difiere de las palmas brutas del lote',
            diferencia: 'La producción del lote no cuadra con el censo',
            palmas: 'Línea atípica frente a la mediana del lote',
            produccion: 'Erradicadas superan las palmas de la línea'
        })[clave] || '',
        subtitulo: (fila, filas) => {
            const lineas = filas.reduce((total, actual) => total + Number(actual.lineasCenso || 0), 0);
            const conDiferencia = filas.filter((actual) => actual.conDiferencia).length;
            const sinCenso = filas.filter((actual) => actual.sinCenso).length;

            return [
                contar(lineas, 'línea en censo', 'líneas en censo') || '0 líneas en censo',
                contar(conDiferencia, 'lote con diferencia', 'lotes con diferencia'),
                contar(sinCenso, 'sin censo', 'sin censo'),
                conDiferencia || sinCenso ? '' : 'Censo cuadrado'
            ].filter(Boolean).join(' · ');
        },
        detalle: { palmas: ['Total palmas', 'entero'], lado: ['Lado', 'texto'], observacion: ['Observación', 'texto'] }
    },
    'labores-tercero-fecha': {
        ocultas: ['trabajador', 'codigo', 'cargo'],
        unidad: ['labor', 'labores'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { labor: 'min-width:240px', uMedida: 'width:90px', cantidad: 'width:110px', jornales: 'width:100px', dias: 'width:80px', valor: 'width:140px' },
        alerta: (fila) => Boolean(fila.conNegativo),
        alertaTitulo: 'Tiene labores con valor negativo por ajustes',
        marca: { valor: (fila) => Boolean(fila.negativo) },
        marcaTitulo: 'Valor negativo por ajuste de signo',
        subtitulo: (fila, filas) => [
            texto(fila.cargo),
            fila.contratista ? 'Contratista' : '',
            contar(Number(fila.diasTrabajador), 'día laborado', 'días laborados'),
            contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
        ].filter(Boolean).join(' · '),
        categoria: (valor) => {
            const nombre = partes(valor).slice(1).join(' – ');
            return nombre && nombre !== 'Sin nombre en terceros' ? nombre : valor;
        },
        titulo: 'Top 15 trabajadores por valor devengado',
        detalle: { jornales: ['Jornales', 'decimal'], dias: ['Días laborados', 'entero'], labores: ['Labores', 'entero'] }
    },
    'labores-tercero-detallada-fecha': {
        ocultas: ['trabajador', 'finca', 'jornales', 'tiquete', 'periodo'],
        unidad: ['registro', 'registros'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { fecha: 'width:110px', dia: 'width:70px', transaccion: 'width:120px', lote: 'min-width:180px', labor: 'min-width:220px', uMedida: 'width:90px', cantidad: 'width:110px', racimos: 'width:90px', precio: 'width:120px', valor: 'width:140px' },
        subtexto: { transaccion: (fila) => (texto(fila.tiquete) ? `Tiquete ${texto(fila.tiquete)}` : '') },
        marca: { valor: (fila) => Boolean(fila.negativo), cantidad: (fila) => Boolean(fila.negativo) },
        marcaTitulo: 'Registro negativo por ajuste de signo',
        alerta: (fila) => Boolean(fila.conNegativo),
        alertaTitulo: 'Tiene registros negativos por ajustes',
        subtitulo: (fila, filas) => {
            const fincas = fila.fincasTrabajador || [];
            const periodos = fila.periodosTrabajador || [];
            return [
                contar(Number(fila.diasTrabajador), 'día laborado', 'días laborados'),
                fincas.length > 1 ? `${fincas.length} fincas` : texto(fincas[0]),
                periodos.length > 3 ? `${periodos.length} periodos` : (periodos.length ? `${periodos.length > 1 ? 'Periodos' : 'Periodo'} ${periodos.join(', ')}` : ''),
                contar(filas.filter((actual) => !texto(actual.periodo)).length, 'sin periodo', 'sin periodo'),
                contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
            ].filter(Boolean).join(' · ');
        },
        categoria: (valor) => (/^\d{2}\/\d{2}\/\d{4}$/.test(texto(valor)) && texto(estado.filtros?.fechaInicial).slice(0, 4) === texto(estado.filtros?.fechaFinal).slice(0, 4) ? texto(valor).slice(0, 5) : valor),
        titulo: 'Valor devengado por día',
        detalle: { dia: ['Día', 'texto'], registros: ['Registros', 'entero'], trabajadores: ['Trabajadores', 'entero'], jornales: ['Jornales', 'decimal'] }
    },
    'labores-detallada-fecha-novedad': {
        ocultas: ['labor', 'grupo', 'contratista', 'uMedida', 'jornales'],
        unidad: ['registro', 'registros'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { fecha: 'width:110px', transaccion: 'width:120px', finca: 'min-width:150px', lote: 'min-width:180px', anioSiembra: 'width:90px', trabajador: 'min-width:220px', cantidad: 'width:110px', precio: 'width:120px', valor: 'width:140px' },
        marca: { cantidad: (fila) => Boolean(fila.negativo), valor: (fila) => Boolean(fila.negativo) },
        marcaTitulo: 'Registro negativo por ajuste de signo',
        alerta: (fila) => Boolean(fila.conNegativo),
        alertaTitulo: 'Tiene registros negativos por ajustes',
        subtitulo: (fila, filas) => {
            const distintos = (clave) => new Set(filas.map((actual) => texto(actual[clave])).filter(Boolean)).size;
            return [
                partes(fila.grupo).slice(1).join(' – ') || texto(fila.grupo),
                texto(fila.uMedida) ? `U. medida: ${texto(fila.uMedida)}` : '',
                contar(distintos('lote'), 'lote', 'lotes'),
                contar(distintos('codigo'), 'trabajador', 'trabajadores'),
                contar(distintos('fecha'), 'día', 'días'),
                Number(fila.tarea) > 0 ? `Tarea: ${formatear(fila.tarea, Number.isInteger(Number(fila.tarea)) ? 'entero' : 'decimal')} ${texto(fila.uMedida)}/jornal` : '',
                contar(filas.filter((actual) => !texto(actual.lote)).length, 'sin lote', 'sin lote'),
                contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
            ].filter(Boolean).join(' · ');
        },
        detalle: { rango: ['Semana', 'texto'], valor: ['Valor', 'moneda'], cantidad: ['Cantidad', 'decimal'], labores: ['Labores', 'entero'], trabajadores: ['Trabajadores', 'entero'], lotes: ['Lotes', 'entero'], registros: ['Registros', 'entero'] }
    },
    'labores-fecha': {
        ocultas: ['documento', 'finca', 'contratista', 'jornales'],
        unidad: ['registro', 'registros'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { fecha: 'width:110px', lote: 'min-width:180px', labor: 'min-width:220px', trabajador: 'min-width:220px', uMedida: 'width:90px', cantidad: 'width:110px', precio: 'width:120px', valor: 'width:140px' },
        marca: { cantidad: (fila) => Boolean(fila.negativo), valor: (fila) => Boolean(fila.negativo) },
        marcaTitulo: 'Registro negativo por ajuste de signo',
        subtexto: { lote: (fila) => (fila.faltaLote ? 'Falta lote requerido' : '') },
        alerta: (fila) => Boolean(fila.conNegativo),
        alertaTitulo: 'Tiene registros negativos por ajustes',
        subtitulo: (fila, filas) => {
            const fincas = fila.fincasDocumento || [];
            return [
                `Doc. ${texto(fila.fechaDocumento)}`,
                fincas.length > 1 ? `${fincas.length} fincas` : texto(fincas[0]),
                contar(Number(fila.lineasDocumento), 'línea', 'líneas'),
                contar(Number(fila.trabajadoresDocumento), 'trabajador', 'trabajadores'),
                texto(fila.usuario) ? `Registró: ${texto(fila.usuario)}` : '',
                texto(fila.referencia) ? `Ref: ${texto(fila.referencia)}` : '',
                contar(filas.filter((actual) => actual.faltaLote).length, 'sin lote requerido', 'sin lote requerido'),
                contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
            ].filter(Boolean).join(' · ');
        },
        detalle: { rango: ['Semana', 'texto'], documentos: ['Transacciones', 'entero'], trabajadores: ['Trabajadores', 'entero'], registros: ['Registros', 'entero'], jornales: ['Jornales', 'decimal'] }
    },
    'registro-tiquetes-fecha': {
        ocultas: ['extractora', 'fechaTiquete', 'remolque', 'interno', 'codigoConductor', 'sacos', 'bruto', 'tara', 'netoConRacimos'],
        unidad: ['tiquete', 'tiquetes'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { fecha: 'width:100px', transaccion: 'width:110px', tiquete: 'width:130px', finca: 'min-width:150px', vehiculo: 'width:130px', conductor: 'min-width:180px', neto: 'width:120px', racimos: 'width:90px', kgRacimo: 'width:100px' },
        subtexto: {
            tiquete: (fila) => (texto(fila.repetido) ? `Repetido en ${texto(fila.repetido)}` : texto(fila.bascula)),
            vehiculo: (fila) => (texto(fila.remolque) ? `Remolque ${texto(fila.remolque)}` : '')
        },
        marca: { neto: (fila) => Boolean(fila.descuadre), racimos: (fila) => Boolean(fila.sinRacimos) },
        marcaTitulo: (fila, clave) => ({
            neto: `Neto no cuadra con bruto − tara (B ${formatear(fila.bruto, 'entero')} − T ${formatear(fila.tara, 'entero')} = ${formatear(Number(fila.bruto) - Number(fila.tara), 'entero')})`,
            racimos: 'Tiquete sin racimos: no entra en el peso promedio'
        })[clave] || '',
        alerta: (fila) => Boolean(fila.sinExtractora),
        alertaTitulo: 'Extractora no encontrada en terceros',
        subtitulo: (fila, filas) => [
            contar(new Set(filas.map((actual) => texto(actual.fecha)).filter(Boolean)).size, 'día', 'días'),
            contar(filas.filter((actual) => actual.sinRacimos).length, 'sin racimos', 'sin racimos'),
            contar(filas.filter((actual) => actual.descuadre).length, 'con neto descuadrado', 'con neto descuadrado'),
            contar(new Set(filas.filter((actual) => texto(actual.repetido)).map((actual) => texto(actual.tiquete))).size, 'repetido', 'repetidos')
        ].filter(Boolean).join(' · '),
        titulo: 'Kilos netos por extractora',
        detalle: { rango: ['Semana', 'texto'], tiquetes: ['Tiquetes', 'entero'], racimos: ['Racimos', 'entero'], pesoPromedio: ['Kg/racimo', 'decimal'] }
    },
    'labores-trabajador-fechas': {
        ocultas: ['periodo', 'trabajador', 'finca', 'tiquete', 'racimos', 'jornales'],
        unidad: ['registro', 'registros'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { fecha: 'width:110px', dia: 'width:70px', transaccion: 'width:120px', lote: 'min-width:180px', labor: 'min-width:220px', uMedida: 'width:90px', cantidad: 'width:110px', precio: 'width:120px', valor: 'width:140px' },
        subtexto: {
            transaccion: (fila) => (texto(fila.tiquete) ? `Tiquete ${texto(fila.tiquete)}` : ''),
            lote: (fila) => texto(fila.fincaSubtexto)
        },
        marca: { cantidad: (fila) => Boolean(fila.negativo), valor: (fila) => Boolean(fila.negativo) },
        marcaTitulo: 'Registro negativo por ajuste de signo',
        alerta: (fila) => Boolean(fila.alertaGrupo),
        alertaTitulo: 'Tiene registros negativos por ajustes',
        subtitulo: (fila, filas) => [
            contar(Number(fila.diasGrupo), 'día laborado', 'días laborados'),
            contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
        ].filter(Boolean).join(' · '),
        detalle: { codigo: ['Código', 'texto'], registros: ['Registros', 'entero'], cantidad: ['Cantidad', 'texto'], dias: ['Días', 'entero'] }
    },
    'labores-lote-fecha': {
        ocultas: ['lote', 'finca', 'anioSiembra', 'palmas', 'palmasPeso', 'valorConPalmas', 'valorContratista', 'diasLote'],
        unidad: ['labor', 'labores'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { grupo: 'min-width:150px', labor: 'min-width:240px', uMedida: 'width:90px', ejecucion: 'width:110px', cantidad: 'width:110px', precio: 'width:120px', dias: 'width:80px', valor: 'width:140px', costoPalma: 'width:110px' },
        alerta: (fila) => Boolean(fila.sinCosto),
        alertaTitulo: 'Costo por palma no calculable (sin lote o sin palmas en producción)',
        marca: { valor: (fila) => Boolean(fila.negativo), precio: (fila) => Boolean(fila.variosPrecios) },
        marcaTitulo: (fila, clave) => ({
            valor: 'Valor negativo por ajuste de signo',
            precio: 'Varios precios en el rango; se muestra el promedio'
        })[clave] || '',
        subtitulo: (fila, filas) => [
            [...new Set(filas.flatMap((actual) => texto(actual.finca).split(' · ')).filter(Boolean))].join(' · '),
            texto(fila.anioSiembra) ? `Siembra ${texto(fila.anioSiembra)}` : '',
            texto(fila.lote) === 'Sin lote' ? 'No entra en costo por palma' : (Number(fila.palmas) > 0 ? contar(Number(fila.palmas), 'palma en producción', 'palmas en producción') : 'Sin palmas en producción'),
            contar(Number(fila.diasLote), 'día con labor', 'días con labor'),
            Number(fila.valorContratistaLote) > 0 ? `Contratistas ${formatear(fila.valorContratistaLote, 'moneda')}${Number(fila.valorLote) ? ` (${formatear(Number(fila.valorContratistaLote) / Number(fila.valorLote), 'porcentaje')})` : ''}` : '',
            contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
        ].filter(Boolean).join(' · '),
        titulo: 'Top 15 lotes por valor ejecutado',
        detalle: { finca: ['Finca', 'texto'], palmas: ['Palmas prod.', 'entero'], costoPalma: ['Costo/palma', 'moneda'], labores: ['Labores', 'entero'], dias: ['Días con labor', 'entero'] }
    },
    'produccion-lote-fecha': {
        ocultas: ['finca', 'racimosPeso', 'kilosConRacimos', 'tonConHa', 'hNetasPeso', 'kilosConPalmas', 'palmasPeso', 'toneladas', 'sinRacimos', 'diasFinca', 'sinHaFinca'],
        unidad: ['lote', 'lotes'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { lote: 'min-width:220px', variedad: 'min-width:140px', anioSiembra: 'width:90px', hNetas: 'width:90px', palmas: 'width:100px', racimos: 'width:100px', kilos: 'width:120px', pesoRacimo: 'width:110px', tonHa: 'width:90px', kgPalma: 'width:100px', dias: 'width:90px' },
        vacioCelda: { variedad: 'Sin variedad' },
        marca: { hNetas: (fila) => Number(fila.hNetas) <= 0, palmas: (fila) => Number(fila.palmas) <= 0, racimos: (fila) => Boolean(fila.sinRacimos) },
        marcaTitulo: (fila, clave) => ({
            hNetas: 'Lote sin Ha netas: Ton/ha no calculable',
            palmas: 'Lote sin palmas en producción: Kg/palma no calculable',
            racimos: 'Kilos sin racimos registrados: peso promedio no calculable'
        })[clave] || '',
        alerta: (fila) => Boolean(fila.sinHaFinca),
        alertaTitulo: 'Finca con lotes sin Ha netas',
        subtitulo: (fila, filas) => [
            `Ha netas: ${formatear(filas.reduce((total, actual) => total + Number(actual.hNetas || 0), 0), 'decimal')}`,
            contar(filas.reduce((total, actual) => total + Number(actual.palmas || 0), 0), 'palma en producción', 'palmas en producción'),
            contar(Number(fila.diasFinca), 'día con cosecha', 'días con cosecha'),
            contar(filas.filter((actual) => Number(actual.hNetas) <= 0).length, 'lote sin Ha netas', 'lotes sin Ha netas')
        ].filter(Boolean).join(' · '),
        categoria: (valor) => partes(valor)[0],
        detalle: { finca: ['Finca', 'texto'], racimos: ['Racimos', 'entero'], hNetas: ['Ha netas', 'decimal'], tonHa: ['Ton/ha', 'decimal'], kgPalma: ['Kg/palma', 'decimal'] }
    },
    'venta-fruta-extractora': {
        ocultas: ['grupo', 'kilosConRacimos', 'sinRacimos', 'atipico', 'partidos', 'sinExtractora', 'kgRacimoGrupo', 'tiquetesGrupo', 'fincasGrupo', 'mesesGrupo', 'promedioRango'],
        get unidad() { return estado.filtros?.agrupar === 'finca' ? ['finca', 'fincas'] : (estado.filtros?.agrupar === 'mes' ? ['mes', 'meses'] : ['extractora', 'extractoras']); },
        ordenServidor: true,
        pageLength: 100,
        anchos: { extractora: 'min-width:260px', mes: 'width:110px', finca: 'min-width:180px', tiquetes: 'width:90px', racimos: 'width:100px', kilos: 'width:130px', toneladas: 'width:110px', kgRacimo: 'width:100px', participacion: 'width:90px' },
        marca: { tiquetes: (fila) => Number(fila.partidos) > 0 || (Number(fila.tiquetes) === 0 && Number(fila.kilos) > 0), kgRacimo: (fila) => Boolean(fila.atipico), racimos: (fila) => Number(fila.sinRacimos) > 0 },
        marcaTitulo: (fila, clave) => ({
            tiquetes: Number(fila.tiquetes) === 0 ? 'Kilos de un tiquete partido; el tiquete se cuenta en otra fila del grupo' : (Number(fila.partidos) === 1 ? 'Incluye 1 tiquete registrado en 2 TLC (contado una vez)' : `Incluye ${formatear(fila.partidos, 'entero')} tiquetes registrados en 2 TLC (contados una vez)`),
            kgRacimo: `Difiere más de 20 % del promedio del rango (${formatear(fila.promedioRango, 'decimal')} kg/racimo)`,
            racimos: 'Kilos sin racimos registrados'
        })[clave] || '',
        alerta: (fila) => Boolean(fila.sinExtractora),
        alertaTitulo: 'Extractora no encontrada en terceros',
        subtitulo: (fila, filas) => [
            contar(Number(fila.tiquetesGrupo), 'tiquete', 'tiquetes'),
            fila.kgRacimoGrupo != null ? `Kg/racimo ${formatear(fila.kgRacimoGrupo, 'decimal')}` : '',
            estado.filtros?.agrupar === 'mes' ? contar(Number(fila.fincasGrupo), 'finca', 'fincas') : contar(Number(fila.mesesGrupo), 'mes con despacho', 'meses con despacho'),
            contar(filas.reduce((total, actual) => total + Number(actual.sinRacimos || 0), 0), 'sin racimos', 'sin racimos')
        ].filter(Boolean).join(' · '),
        detalle: { total: ['Total mes (t)', 'decimal'], participacion: ['% participación', 'porcentaje'], tiquetes: ['Tiquetes', 'entero'], racimos: ['Racimos', 'entero'], pesoPromedio: ['Kg/racimo', 'decimal'] }
    },
    'produccion-lote-anual': {
        ocultas: ['finca', 'variedad', 'baseDif', 'baseAnt', 'toneladas', 'hNetasPeso', 'tonConHa', 'tonConHaAnt', ...MESES.map((_, indice) => `tonConHa${indice + 1}`), 'proyConHa', 'sinCosecha', 'sinHaFinca', 'hNetasAct', 'hNetasAnt'],
        unidad: ['lote', 'lotes'],
        ordenServidor: true,
        pageLength: 100,
        columnaFija: true,
        anchos: { lote: 'min-width:200px', anioSiembra: 'width:80px', hNetas: 'width:80px', ...Object.fromEntries(MESES.map((_, indice) => [`m${indice + 1}`, 'width:84px'])), total: 'width:110px', anterior: 'width:110px', variacion: 'width:90px', proyeccion: 'width:100px' },
        subtexto: { lote: (fila) => texto(fila.variedad) || 'Sin variedad' },
        marca: {
            ...Object.fromEntries(MESES.map((_, indice) => [`m${indice + 1}`, (fila) => fila[`m${indice + 1}`] != null && Number(fila[`m${indice + 1}`]) === 0])),
            variacion: (fila) => fila.variacion != null && fila.variacion <= -0.2,
            hNetas: (fila) => Number(fila.hNetas) <= 0
        },
        marcaTitulo: (fila, clave) => ({
            variacion: 'Caída de 20 % o más vs mismo periodo del año anterior',
            hNetas: 'Lote sin Ha netas: Ton/ha y proyección no calculables'
        })[clave] || 'Sin cosecha en el mes',
        alerta: (fila) => Boolean(fila.sinHaFinca),
        alertaTitulo: 'Finca con lotes sin Ha netas',
        subtitulo: (fila, filas) => {
            const suma = (clave) => filas.reduce((total, actual) => total + Number(actual[clave] || 0), 0);

            return [
                `Ha netas: ${formatear(suma('hNetas'), 'decimal')}`,
                `Ton acum.: ${formatear(suma('toneladas'), 'decimal')}`,
                suma('hNetasAct') > 0 ? `Ton/ha: ${formatear(suma('tonConHa') / suma('hNetasAct'), 'decimal')}` : '',
                suma('baseAnt') > 0 ? `Var. vs ${Number(estado.filtros?.anio) - 1}: ${formatear(suma('baseDif') / suma('baseAnt'), 'variacion')}` : '',
                contar(filas.filter((actual) => Number(actual.hNetas) <= 0).length, 'lote sin Ha netas', 'lotes sin Ha netas')
            ].filter(Boolean).join(' · ');
        }
    },
    'labores-ccosto-fecha': {
        ocultas: ['ccosto', 'finca', 'valorContratista'],
        unidad: ['labor', 'labores'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { grupo: 'min-width:150px', labor: 'min-width:240px', uMedida: 'width:90px', ejecucion: 'width:110px', cantidad: 'width:110px', precio: 'width:120px', dias: 'width:80px', valor: 'width:140px' },
        marca: { valor: (fila) => Boolean(fila.negativo), precio: (fila) => Boolean(fila.variosPrecios) },
        marcaTitulo: (fila, clave) => ({
            valor: 'Valor negativo por ajuste de signo',
            precio: 'Varios precios en el rango; se muestra el promedio'
        })[clave] || '',
        subtitulo: (fila, filas) => {
            const fincas = fila.fincasCcosto || [];
            return [
                `${formatear(fila.pctCcosto, 'porcentaje')} del total`,
                contar(Number(fila.laboresCcosto), 'labor', 'labores'),
                contar(Number(fila.diasCcosto), 'día con labor', 'días con labor'),
                fincas.length > 3 ? `${fincas.length} fincas` : fincas.map(texto).filter(Boolean).join(' · '),
                Number(fila.valorContratistaCcosto) > 0 ? `Contratistas ${formatear(fila.valorContratistaCcosto, 'moneda')}${Number(fila.valorCcosto) ? ` (${formatear(Number(fila.valorContratistaCcosto) / Number(fila.valorCcosto), 'porcentaje')})` : ''}` : '',
                contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
            ].filter(Boolean).join(' · ');
        },
        titulo: 'Top 10 centros de costo por valor ejecutado',
        detalle: { pct: ['% del total', 'porcentaje'], labores: ['Labores', 'entero'], dias: ['Días con labor', 'entero'], fincas: ['Fincas', 'texto'], centros: ['Centros de costo', 'entero'], contratista: ['Contratistas', 'moneda'] }
    },
    'labores-ccosto-fecha-lote': {
        ocultas: ['ccosto', 'finca', 'grupo', 'ejecucion', 'dias', 'palmasPeso', 'valorConPalmas', 'valorContratista', 'valorLote', 'pctLote'],
        unidad: ['labor', 'labores'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { lote: 'min-width:200px', anioSiembra: 'width:90px', mes: 'width:90px', labor: 'min-width:240px', uMedida: 'width:90px', palmas: 'width:100px', cantidad: 'width:110px', precio: 'width:120px', valor: 'width:140px', costoPalma: 'width:110px' },
        alerta: (fila) => Boolean(fila.sinCcosto) || Number(fila.palmasCcosto) === 0,
        alertaTitulo: 'Centro sin costo por palma (sin centro de costo o sin palmas en producción)',
        marca: { valor: (fila) => Boolean(fila.negativo), precio: (fila) => Boolean(fila.variosPrecios) },
        marcaTitulo: (fila, clave) => ({
            valor: 'Valor negativo por ajuste de signo',
            precio: 'Varios precios en el rango; se muestra el promedio'
        })[clave] || '',
        subtitulo: (fila, filas) => [
            `${formatear(fila.pctCcosto, 'porcentaje')} del total`,
            ...(texto(fila.loteUnico) ? [`Lote ${texto(fila.loteUnico)}`, texto(fila.anioUnico) ? `Siembra ${texto(fila.anioUnico)}` : ''] : [contar(Number(fila.lotesCcosto), 'lote', 'lotes')]),
            Number(fila.palmasCcosto) > 0 ? contar(Number(fila.palmasCcosto), 'palma en producción', 'palmas en producción') : '',
            fila.costoPalmaCcosto != null ? `Costo/palma ${formatear(fila.costoPalmaCcosto, 'moneda')}` : 'Sin costo por palma',
            contar(Number(fila.diasCcosto), 'día con labor', 'días con labor'),
            Number(fila.valorContratistaCcosto) > 0 ? `Contratistas ${formatear(fila.valorContratistaCcosto, 'moneda')}${Number(fila.valorCcosto) ? ` (${formatear(Number(fila.valorContratistaCcosto) / Number(fila.valorCcosto), 'porcentaje')})` : ''}` : '',
            contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
        ].filter(Boolean).join(' · '),
        detalle: { centro: ['Centro de costo', 'texto'], pctCentro: ['% del centro', 'porcentaje'], pct: ['% del total', 'porcentaje'], lotes: ['Lotes', 'entero'], palmas: ['Palmas prod.', 'entero'], costoPalma: ['Costo/palma', 'moneda'], labores: ['Labores', 'entero'], centros: ['Centros de costo', 'entero'] }
    },
    'labores-detalle': {
        ocultas: ['documento', 'tipo', 'numero', 'fechaTransaccion', 'finca', 'grupo', 'concepto', 'contratista', 'racimos', 'tiquete', 'estado', 'usuario'],
        unidad: ['línea', 'líneas'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { fecha: 'width:110px', lote: 'min-width:180px', labor: 'min-width:220px', trabajador: 'min-width:220px', ccosto: 'min-width:160px', uMedida: 'width:90px', cantidad: 'width:110px', jornales: 'width:100px', precio: 'width:120px', valor: 'width:140px' },
        marca: { cantidad: (fila) => Boolean(fila.negativo), valor: (fila) => (Boolean(fila.anulado) && estado.filtros?.estado === 'todas') || Boolean(fila.negativo) },
        marcaTitulo: (fila, clave) => (clave === 'valor' && fila.anulado && estado.filtros?.estado === 'todas' ?'Línea de documento anulado' : 'Registro negativo por ajuste de signo'),
        subtexto: {
            lote: (fila) => (fila.faltaLote ? 'Falta lote requerido' : ''),
            trabajador: (fila) => (texto(fila.contratista) ? `Contratista: ${texto(fila.contratista)}` : '')
        },
        alerta: (fila) => Boolean(fila.anulado) || Boolean(fila.conNegativo),
        alertaTitulo: 'Documento anulado o con ajustes negativos',
        subtitulo: (fila, filas) => {
            const fincas = fila.fincasDocumento || [];
            return [
                fila.anulado ? 'Anulado' : '',
                `Doc. ${texto(fila.fechaDocumento)}`,
                fincas.length > 1 ? `${fincas.length} fincas` : texto(fincas[0]),
                texto(fila.tiqueteDocumento) ? `Tiquete ${texto(fila.tiqueteDocumento)}` : '',
                contar(Number(fila.lineasDocumento), 'línea', 'líneas'),
                contar(Number(fila.trabajadoresDocumento), 'trabajador', 'trabajadores'),
                texto(fila.usuario) ? `Registró: ${texto(fila.usuario)}` : '',
                texto(fila.referencia) ? `Ref: ${texto(fila.referencia)}` : '',
                contar(filas.filter((actual) => actual.faltaLote).length, 'sin lote requerido', 'sin lote requerido'),
                contar(filas.filter((actual) => actual.negativo).length, 'con ajuste negativo', 'con ajuste negativo')
            ].filter(Boolean).join(' · ');
        }
    },
    'liquidacion-contratistas-periodo': {
        ocultas: ['contratista', 'dia', 'finca', 'finTrabajador', 'jornalesTrabajador', 'valorTrabajador', 'lineasTrabajador', 'negativo', 'sinPrecio', 'precioDistinto', 'precioHabitual', 'conNegativo', 'sinProveedor', 'sinPrecioGrupo', 'pctGrupo', 'trabajadoresGrupo', 'diasGrupo', 'fincaUnica', 'nFincas', 'laboresGrupo', 'sinPrecioN', 'precioDistintoN', 'negativoN', 'fincasGrupo'],
        unidad: ['línea', 'líneas'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { trabajador: 'min-width:240px', fecha: 'width:110px', documento: 'width:120px', lote: 'min-width:180px', labor: 'min-width:220px', uMedida: 'width:90px', cantidad: 'width:110px', jornales: 'width:100px', precio: 'width:120px', valor: 'width:140px' },
        subtexto: {
            fecha: (fila) => texto(fila.dia),
            lote: (fila) => (texto(fila.lote) ? texto(fila.finca) : ['Sin lote', texto(fila.finca)].filter(Boolean).join(' · ')),
            trabajador: (fila) => (fila.finTrabajador ? `Subtotal ${formatear(fila.valorTrabajador, 'moneda')} · ${formatear(fila.jornalesTrabajador, 'decimal')} jornales · ${contar(Number(fila.lineasTrabajador), 'línea', 'líneas')}` : '')
        },
        marca: { cantidad: (fila) => Boolean(fila.negativo), valor: (fila) => Boolean(fila.negativo), precio: (fila) => Boolean(fila.sinPrecio || fila.precioDistinto) },
        marcaTitulo: (fila, clave) => (clave !== 'precio' ? 'Registro negativo por ajuste de signo' : (fila.sinPrecio ? 'Labor sin precio: no suma a la liquidación' : `Difiere del precio habitual de la labor en el periodo (${formatear(fila.precioHabitual, 'moneda')})`)),
        alerta: (fila) => Boolean(fila.sinProveedor || fila.conNegativo || fila.sinPrecioGrupo),
        alertaTitulo: 'Revisar antes de liquidar: proveedor no asociado, líneas sin precio o ajustes negativos',
        subtitulo: (fila) => [
            `${formatear(fila.pctGrupo, 'porcentaje')} del total`,
            contar(Number(fila.trabajadoresGrupo), 'trabajador', 'trabajadores'),
            contar(Number(fila.diasGrupo), 'día laborado', 'días laborados'),
            Number(fila.nFincas) > 1 ? contar(Number(fila.nFincas), 'finca', 'fincas') : texto(fila.fincaUnica),
            contar(Number(fila.laboresGrupo), 'labor', 'labores'),
            contar(Number(fila.sinPrecioN), 'sin precio', 'sin precio'),
            contar(Number(fila.precioDistintoN), 'con precio distinto al habitual', 'con precio distinto al habitual'),
            contar(Number(fila.negativoN), 'con ajuste negativo', 'con ajuste negativo')
        ].filter(Boolean).join(' · '),
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        titulo: 'Valor a liquidar por contratista',
        detalle: { nit: ['NIT', 'texto'], trabajadores: ['Trabajadores', 'entero'], jornales: ['Jornales', 'decimal'], lineas: ['Líneas', 'entero'], pct: ['% del total', 'porcentaje'] }
    },
    'resumen-contratistas-periodo': {
        ocultas: ['tipo', 'pctGrupoLabor', 'trabajadores', 'sinGrupo', 'pctTipo', 'contratistasTipo', 'trabajadoresTipo', 'mesesTipo', 'sinProveedorTipo'],
        unidad: ['fila', 'filas'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { etiqueta: 'min-width:220px', ...Object.fromEntries(MESES.map((_, indice) => [`m${indice + 1}`, 'width:120px'])), total: 'width:140px', pct: 'width:90px' },
        subtexto: {
            etiqueta: (fila) => (fila.sinGrupo ? 'Labores sin grupo en el maestro' : (fila.pctGrupoLabor != null ? `${formatear(fila.pctGrupoLabor, 'porcentaje')} del grupo` : (estado.filtros?.agrupar === 'contratista' && fila.tipo === 'Contratistas' && Number(fila.trabajadores) ? contar(Number(fila.trabajadores), 'trabajador', 'trabajadores') : '')))
        },
        alerta: (fila) => Boolean(fila.sinProveedorTipo),
        alertaTitulo: 'Incluye trabajadores sin proveedor asociado',
        subtitulo: (fila) => [
            `${formatear(fila.pctTipo, 'porcentaje')} del total de labores`,
            fila.tipo === 'Contratistas' ? contar(Number(fila.contratistasTipo), 'contratista', 'contratistas') : '',
            contar(Number(fila.trabajadoresTipo), 'trabajador', 'trabajadores'),
            fila.tipo === 'Contratistas' ? contar(Number(fila.mesesTipo), 'mes con labor', 'meses con labor') : ''
        ].filter(Boolean).join(' · '),
        detalle: { propio: ['Valor propio', 'moneda'], total: ['Total labores', 'moneda'], contratistas: ['Contratistas activos', 'entero'] }
    },
    'liquidacion-contratistas-periodo-lotes': {
        ocultas: ['contratista', 'seccion', 'finTrabajador', 'jornalesTrabajador', 'valorTrabajador', 'faltaLote', 'sinPrecio', 'variosPrecios', 'precioMin', 'precioMax', 'conAjuste', 'netoNegativo', 'sinProveedor', 'alertaGrupo', 'pctGrupo', 'trabajadoresGrupo', 'lotesGrupo', 'fincaUnica', 'nFincas', 'laboresGrupo', 'faltaLoteN', 'sinPrecioN', 'variosPreciosN', 'ajusteN'],
        unidad: ['fila', 'filas'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { trabajador: 'min-width:240px', finca: 'min-width:150px', lote: 'min-width:180px', labor: 'min-width:220px', uMedida: 'width:90px', cantidad: 'width:110px', jornales: 'width:100px', precio: 'width:120px', valor: 'width:140px' },
        subtexto: {
            trabajador: (fila) => (fila.finTrabajador ? `Subtotal ${formatear(fila.valorTrabajador, 'moneda')} · ${formatear(fila.jornalesTrabajador, 'decimal')} jornales` : ''),
            finca: (fila) => (texto(fila.seccion) ? `Sección ${texto(fila.seccion)}` : ''),
            lote: (fila) => (texto(fila.lote) ? '' : (fila.faltaLote ? 'Falta lote requerido' : 'No se abre por lote'))
        },
        marca: { cantidad: (fila) => Boolean(fila.conAjuste), valor: (fila) => Boolean(fila.conAjuste), precio: (fila) => Boolean(fila.sinPrecio || fila.variosPrecios) },
        marcaTitulo: (fila, clave) => (clave !== 'precio'
            ? (fila.netoNegativo ? 'Neto negativo por ajustes de signo' : 'Incluye ajustes negativos; se muestra el neto')
            : (fila.sinPrecio ? 'Labor sin precio: no suma a la liquidación' : `Varios precios en el rango (mín. ${formatear(fila.precioMin, 'moneda')} – máx. ${formatear(fila.precioMax, 'moneda')}); se muestra el promedio ponderado`)),
        alerta: (fila) => Boolean(fila.alertaGrupo),
        alertaTitulo: 'Revisar antes de liquidar: proveedor no asociado, filas sin lote requerido o sin precio',
        subtitulo: (fila) => [
            `${formatear(fila.pctGrupo, 'porcentaje')} del total`,
            contar(Number(fila.trabajadoresGrupo), 'trabajador', 'trabajadores'),
            contar(Number(fila.lotesGrupo), 'lote', 'lotes'),
            Number(fila.nFincas) > 1 ? contar(Number(fila.nFincas), 'finca', 'fincas') : texto(fila.fincaUnica),
            contar(Number(fila.laboresGrupo), 'labor', 'labores'),
            contar(Number(fila.faltaLoteN), 'sin lote requerido', 'sin lote requerido'),
            contar(Number(fila.sinPrecioN), 'sin precio', 'sin precio'),
            contar(Number(fila.variosPreciosN), 'con varios precios', 'con varios precios'),
            contar(Number(fila.ajusteN), 'con ajuste negativo', 'con ajuste negativo')
        ].filter(Boolean).join(' · '),
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        detalle: { total: ['Total contratista', 'moneda'], pct: ['% del total', 'porcentaje'], trabajadores: ['Trabajadores', 'entero'], lotes: ['Lotes', 'entero'], jornales: ['Jornales', 'decimal'] }
    },
    'hoja-vida-labores': {
        ocultas: ['grupo', 'negativo', 'negM', 'labores', 'unidades', 'sinGrupo', 'pctGrupo', 'laboresGrupo', 'trabajadoresGrupo', 'mesesGrupo', 'unidadesGrupo'],
        get unidad() { return estado.filtros?.nivel === 'labor' ? ['labor', 'labores'] : ['grupo de labor', 'grupos de labor']; },
        ordenServidor: true,
        pageLength: 100,
        get anchos() {
            const mes = estado.filtros?.metrica === 'valor' ? 'width:110px' : 'width:90px';
            return { etiqueta: 'min-width:240px', uMedida: 'width:80px', ...Object.fromEntries(MESES.map((_, indice) => [`m${indice + 1}`, mes])), total: 'width:130px', pct: 'width:80px', trabajadores: 'width:100px' };
        },
        subtexto: {
            etiqueta: (fila) => (fila.sinGrupo ? 'Labores sin grupo en el maestro' : (estado.filtros?.nivel === 'labor' ? '' : [contar(Number(fila.labores), 'labor', 'labores'), texto(fila.unidades) ? `Unidades: ${texto(fila.unidades)}` : ''].filter(Boolean).join(' · ')))
        },
        get marca() {
            return estado.filtros?.metrica === 'valor' ? {
                total: (fila) => Boolean(fila.negativo),
                ...Object.fromEntries(MESES.map((_, indice) => [`m${indice + 1}`, (fila) => texto(fila.negM).split(',').map(texto).includes(String(indice + 1))]))
            } : {};
        },
        marcaTitulo: () => 'Neto negativo por ajustes de signo',
        alerta: (fila) => Boolean(fila.sinGrupo),
        alertaTitulo: 'Labores sin grupo válido',
        subtitulo: (fila) => [
            `${formatear(fila.pctGrupo, 'porcentaje')} del valor del año`,
            contar(Number(fila.laboresGrupo), 'labor', 'labores'),
            contar(Number(fila.trabajadoresGrupo), 'trabajador en el año', 'trabajadores en el año'),
            contar(Number(fila.mesesGrupo), 'mes con labor', 'meses con labor'),
            texto(fila.unidadesGrupo) ? `Unidades: ${texto(fila.unidadesGrupo)}` : ''
        ].filter(Boolean).join(' · '),
        detalle: { total: ['Total mes', 'moneda'], trabajadores: ['Trabajadores distintos', 'entero'], labores: ['Labores con registro', 'entero'], pct: ['% del año', 'porcentaje'] }
    },
    'indicadores-anio': {
        ocultas: ['finca', 'variedad', 'palmas', 'sinRacimos', 'sinHaFinca', ...AUX_INDICADORES],
        unidad: ['lote', 'lotes'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { lote: 'min-width:200px', anioSiembra: 'width:80px', hNetas: 'width:80px', ...ANCHOS_INDICADORES },
        subtexto: { lote: (fila) => texto(fila.variedad) || 'Sin variedad' },
        marca: { ...MARCA_CAIDA, hNetas: (fila) => Number(fila.hNetas) <= 0, racimosAct: (fila) => Boolean(fila.sinRacimos) },
        marcaTitulo: (fila, clave) => ({
            variacion: 'Caída de 20 % o más vs mismo periodo del año anterior',
            hNetas: 'Lote sin Ha netas: t/ha y proyección no calculables',
            racimosAct: 'Kilos sin racimos registrados'
        })[clave] || '',
        alerta: (fila) => Boolean(fila.sinHaFinca),
        alertaTitulo: 'Finca con lotes sin Ha netas',
        subtitulo: (fila, filas) => {
            const suma = (clave) => filas.reduce((total, actual) => total + Number(actual[clave] || 0), 0);
            const anio = Number(estado.filtros?.anio);

            return [
                `Ha netas ${formatear(suma('hNetas'), 'decimal')}`,
                `Kilos ${anio} ${formatear(suma('kilosAct'), 'entero')}`,
                suma('kilosAnt') > 0 ? `Var. vs ${anio - 1} ${formatear(suma('kilosDif') / suma('kilosAnt'), 'variacion')}` : '',
                suma('hNetasAct') > 0 ? `T/ha ${formatear(suma('tonConHaAct') / suma('hNetasAct'), 'decimal')}` : '',
                contar(filas.filter((actual) => Number(actual.hNetas) <= 0).length, 'lote sin Ha netas', 'lotes sin Ha netas')
            ].filter(Boolean).join(' · ');
        },
        get detalle() {
            const anio = Number(estado.filtros?.anio);

            return { finca: ['Finca', 'texto'], kilosAct: [`Kilos ${anio}`, 'entero'], variacion: [`Var. kilos vs ${anio - 1}`, 'variacion'], pesoAct: [`Kg/racimo ${anio}`, 'decimal'] };
        }
    },
    'indicadores-anio-siembra': {
        ocultas: ['lotes', 'edad', ...AUX_INDICADORES],
        unidad: ['año de siembra', 'años de siembra'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { anioSiembra: 'width:110px', hNetas: 'width:100px', palmas: 'width:100px', ...ANCHOS_INDICADORES },
        subtexto: { anioSiembra: (fila) => [fila.edad != null ? `Edad ${formatear(fila.edad, 'entero')} ${Number(fila.edad) === 1 ? 'año' : 'años'}` : '', contar(Number(fila.lotes), 'lote', 'lotes')].filter(Boolean).join(' · ') },
        marca: MARCA_CAIDA,
        marcaTitulo: 'Caída de 20 % o más vs mismo periodo del año anterior',
        get detalle() {
            const anio = Number(estado.filtros?.anio);

            return { tonHaAnt: [`Ton/ha ${anio - 1}`, 'decimal'], variacion: [`Var. kilos vs ${anio - 1}`, 'variacion'], lotes: ['Lotes', 'entero'], hNetas: ['Ha netas', 'decimal'], edad: ['Edad (años)', 'entero'] };
        }
    },
    'ciclos-corte-racimos': {
        ocultas: ['finca', 'anioSiembra', 'variedad', 'ultimoCorte', 'corteAnterior', 'sumaIntervalos', 'nIntervalos', 'cicloLargo', 'umbral', 'lotesFinca', 'cosechadosFinca', 'racimosFinca', 'intPromFinca', 'largosFinca', 'fincaCicloLargo'],
        unidad: ['lote', 'lotes'],
        ordenServidor: true,
        pageLength: 150,
        columnaFija: true,
        anchos: { lote: 'min-width:180px', ...Object.fromEntries(Array.from({ length: 31 }, (_, indice) => [`d${indice + 1}`, 'width:44px'])), racimos: 'width:90px', pases: 'width:70px', intProm: 'width:80px', intMax: 'width:80px', diasSinCorte: 'width:90px' },
        subtexto: { lote: (fila) => [texto(fila.anioSiembra) ? `Siembra ${texto(fila.anioSiembra)}` : '', texto(fila.variedad)].filter(Boolean).join(' · ') },
        marca: {
            intMax: (fila) => fila.intMax != null && Number(fila.intMax) > Number(fila.umbral),
            diasSinCorte: (fila) => fila.diasSinCorte != null && Number(fila.diasSinCorte) > Number(fila.umbral)
        },
        marcaTitulo: (fila, clave) => ({
            intMax: `Ciclo largo: ${formatear(fila.intMax, 'entero')} días entre pases (umbral ${formatear(fila.umbral, 'entero')})`,
            diasSinCorte: `${formatear(fila.diasSinCorte, 'entero')} días sin corte`
        })[clave] || '',
        alerta: (fila) => Boolean(fila.fincaCicloLargo),
        get alertaTitulo() { return `Finca con lotes en ciclo largo (> ${texto(estado.filtros?.umbral)} días)`; },
        subtitulo: (fila) => [
            `${formatear(fila.cosechadosFinca, 'entero')}/${formatear(fila.lotesFinca, 'entero')} lotes cosechados`,
            `${formatear(fila.racimosFinca, 'entero')} racimos`,
            fila.intPromFinca != null ? `Int. prom. ${formatear(fila.intPromFinca, 'decimal')} días` : '',
            contar(Number(fila.largosFinca), 'con ciclo largo', 'con ciclo largo')
        ].filter(Boolean).join(' · ')
    },
    'ciclos-labores': {
        ocultas: ['labor', 'finca', 'anioSiembra', 'palmas', 'hNetas', 'uMedida', 'ciclos', 'pasesAnio', 'porMeses', 'atrasada', 'pasesMeta', 'progMeta', 'cumplimientoLabor', 'atrasadosLabor', 'lotesLabor'],
        unidad: ['lote', 'lotes'],
        ordenServidor: true,
        pageLength: 200,
        columnaFija: true,
        anchos: { lote: 'min-width:200px', ...Object.fromEntries(MESES.map((_, indice) => [`m${indice + 1}`, 'width:84px'])), total: 'width:110px', pases: 'width:90px', programados: 'width:90px', cumplimiento: 'width:90px' },
        subtexto: {
            lote: (fila) => [
                partes(fila.finca).slice(1).join(' – ') || texto(fila.finca),
                texto(fila.anioSiembra) ? `Siembra ${texto(fila.anioSiembra)}` : '',
                fila.uMedida === 'PAL' ? contar(Number(fila.palmas), 'palma', 'palmas') : (fila.uMedida === 'HA' && Number(fila.hNetas) > 0 ? `${formatear(fila.hNetas, 'decimal')} ha` : '')
            ].filter(Boolean).join(' · ')
        },
        marca: {
            cumplimiento: (fila) => fila.cumplimiento != null && Number(fila.cumplimiento) < 1,
            pases: (fila) => Boolean(fila.porMeses)
        },
        marcaTitulo: (fila, clave) => ({
            cumplimiento: `Atrasada: ${formatear(fila.pases, 'decimal')} de ${formatear(fila.programados, 'decimal')} pases al corte`,
            pases: 'Calculado por meses con labor'
        })[clave] || '',
        alerta: (fila) => Number(fila.atrasadosLabor) > 0,
        alertaTitulo: 'Labor con lotes atrasados',
        subtitulo: (fila) => (fila.ciclos != null ? [
            texto(fila.uMedida) ? `U. medida ${texto(fila.uMedida)}` : '',
            `Ciclo cada ${formatear(fila.ciclos, 'entero')} días (${Number(fila.pasesAnio).toLocaleString('es-CO')} pases/año)`,
            `Meta al corte ${formatear(fila.programados, 'decimal')} pases`,
            `Cumplimiento ${formatear(fila.cumplimientoLabor, 'porcentaje')}`,
            contar(Number(fila.atrasadosLabor), 'lote atrasado', 'lotes atrasados')
        ] : ['Sin meta de ciclos', contar(Number(fila.lotesLabor), 'lote con labor', 'lotes con labor')]).filter(Boolean).join(' · '),
        detalle: { pasesProm: ['Pases prom. por lote', 'decimal'], metaCorte: ['Meta al corte (pases)', 'decimal'], atrasados: ['Lotes atrasados', 'entero'], lotes: ['Lotes con la labor', 'entero'] }
    },
    'rendimiento-trabajador-mes': {
        ocultas: ['trabajador', 'documento', 'ccosto', 'numMes', 'diasLaborados', 'jornalesRegistrados', 'jornalesEstimados', 'jornalesTrabajador', 'valorTrabajador', 'salarioTrabajador', 'diferenciaTrabajador', 'mesesTrabajador', 'mesesRinde', 'mesesBajo', 'conMesesBajo'],
        unidad: ['mes', 'meses'],
        ordenServidor: true,
        pageLength: 100,
        anchos: { mes: 'width:110px', jornales: 'width:90px', valor: 'width:130px', valorSalario: 'width:130px', diferencia: 'width:130px', valorJornal: 'width:110px', rinde: 'width:80px' },
        marca: { diferencia: (fila) => fila.diferencia != null && Number(fila.diferencia) < 0 },
        marcaTitulo: 'Bajo el salario mínimo proporcional',
        subtituloAjustado: true,
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        alerta: (fila) => Boolean(fila.conMesesBajo),
        alertaTitulo: 'Trabajador con meses bajo el mínimo',
        subtitulo: (fila) => [
            /^Sin /.test(texto(fila.ccosto)) ? texto(fila.ccosto) : `CC ${texto(fila.ccosto)}`,
            `${formatear(fila.jornalesTrabajador, 'decimal')} jornales`,
            `Valor ${formatear(fila.valorTrabajador, 'moneda')}`,
            fila.diferenciaTrabajador != null ? `Diferencia ${formatear(fila.diferenciaTrabajador, 'moneda')}` : '',
            fila.mesesRinde != null ? `Rinde ${formatear(fila.mesesRinde, 'entero')} de ${formatear(fila.mesesTrabajador, 'entero')} ${Number(fila.mesesTrabajador) === 1 ? 'mes' : 'meses'}` : ''
        ].filter(Boolean).join(' · '),
        detalle: { ccosto: ['Centro de costo', 'texto'], jornales: ['Jornales', 'decimal'], valorJornal: ['$/jornal', 'moneda'], mesesRinde: ['Meses que rinde', 'texto'] }
    },
    'rendimiento-trabajador-cosecha': {
        get ocultas() { return ['documento', 'diaSemana', 'racimosExactos', 'bajoTarea', 'diasCumple', 'diasTrabajador', 'kilosTrabajador', 'kgDiaTrabajador', 'lotesPrincipales', 'fincasTrabajador', 'kilosTarea', 'diasTarea', 'tareaKilos', 'tareaDias', ...(estado.filtros?.vista === 'dia' ? ['trabajador'] : [])]; },
        get unidad() { return estado.filtros?.vista === 'dia' ? ['día', 'días'] : ['trabajador', 'trabajadores']; },
        ordenServidor: true,
        pageLength: 100,
        anchos: { trabajador: 'min-width:240px', fecha: 'width:110px', lotes: 'min-width:140px', dias: 'width:80px', racimos: 'width:90px', kilos: 'width:110px', jornales: 'width:90px', kgDia: 'width:100px', kgRacimo: 'width:90px', tarea: 'width:90px', cumplimiento: 'width:100px' },
        subtexto: {
            trabajador: (fila) => [texto(fila.fincasTrabajador), texto(fila.lotesPrincipales) ? `Lotes ${texto(fila.lotesPrincipales)}` : ''].filter(Boolean).join(' · '),
            fecha: (fila) => texto(fila.diaSemana)
        },
        marca: { cumplimiento: (fila) => Boolean(fila.bajoTarea) },
        marcaTitulo: (fila) => `Bajo la tarea de ${formatear(fila.tarea, 'entero')} kg`,
        categoria: (valor) => partes(valor).slice(1).join(' – ') || valor,
        subtitulo: (fila, filas) => {
            const conTarea = filas.filter((actual) => actual.tarea != null).length;

            return [
                contar(Number(fila.diasTrabajador), 'día', 'días'),
                `${formatear(fila.kilosTrabajador, 'entero')} kg`,
                fila.kgDiaTrabajador != null ? `${formatear(fila.kgDiaTrabajador, 'entero')} kg/día` : '',
                conTarea ? `Cumple ${formatear(fila.diasCumple, 'entero')} de ${formatear(conTarea, 'entero')} ${conTarea === 1 ? 'día' : 'días'}` : ''
            ].filter(Boolean).join(' · ');
        },
        detalle: { dias: ['Días', 'entero'], kilos: ['Kilos', 'entero'], racimos: ['Racimos', 'entero'], kgRacimo: ['Kg/racimo', 'decimal'], cumplimiento: ['% cumpl.', 'porcentaje'] }
    }
};

const estado = { informe: null, respuesta: null, filtros: null, graficas: [], turno: 0, vigente: false };

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
        : `<input type="${filtro.tipo === 'fecha' ? 'date' : 'text'}" class="form-control" id="f_${clave}" data-filtro="${clave}" value="${escaparHtml(valorDefecto(filtro))}" placeholder="${escaparHtml(filtro.placeholder || '')}" autocomplete="off">`;

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

    recargarIniciales(informe);
};

const recargarIniciales = (informe) => {
    const conDefecto = (informe.filtros || []).filter((filtro) => valorDefecto(filtro) !== '').map((filtro) => filtro.clave);
    const iniciales = (informe.filtros || []).filter((filtro) => [].concat(filtro.depende || []).some((clave) => conDefecto.includes(clave)));

    if (iniciales.length) recargarDependientes(null, iniciales);
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
    estado.graficas.forEach((grafica) => grafica.destroy());
    estado.graficas = [];

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

const opcionesMapa = (grafica) => {
    const pequena = window.innerWidth < 576;
    const formato = grafica.formato || 'decimal';
    const total = grafica.series.length;
    const centinelas = grafica.series.flatMap((serie) => serie.datos.map(Number));
    const valores = centinelas.filter((valor) => valor >= 0).sort((a, b) => a - b);
    const maximo = valores[valores.length - 1];
    const cortes = [...new Set([0.25, 0.5, 0.75].map((p) => valores[Math.round(p * (valores.length - 1))]))].filter((corte) => corte < maximo);
    const limites = valores.length ? [...cortes, maximo] : [];
    const verdes = ['#c9e6d6', '#7cc49a', '#41a867', '#2f7a4b'].slice(4 - limites.length);
    const anio = (texto(grafica.titulo).match(/\b(19|20)\d{2}\b/) || [])[0] || texto(estado.filtros?.anio);
    const especiales = grafica.centinelas || { '-1': { nombre: 'Sin cosecha', color: '#fbe3b0' }, '-2': { nombre: 'Mes no transcurrido', color: '#eef1f4' } };
    const denso = grafica.categorias.length > 20;
    const saltoX = pequena && denso ? Math.ceil(grafica.categorias.length / 8) : 0;
    const ranges = [
        ...limites.map((limite, indice) => ({
            from: indice ? limites[indice - 1] + 1e-9 : 0,
            to: limite,
            color: verdes[indice],
            name: limites.length === 1 ? formatear(limite, formato) : (indice === 0 ? `≤ ${formatear(limite, formato)}` : (indice === limites.length - 1 ? `> ${formatear(limites[indice - 1], formato)}` : `${formatear(limites[indice - 1], formato)}–${formatear(limite, formato)}`))
        })),
        ...[-2, -1].filter((clave) => centinelas.includes(clave) && especiales[clave]).map((clave) => ({ from: clave - 0.5, to: clave + 0.5, color: especiales[clave].color, name: especiales[clave].nombre }))
    ];
    const ejes = { fontSize: '12px', colors: '#5c6b7a' };

    return {
        chart: {
            type: 'heatmap', height: Math.max(260, total * (pequena ? 12 : 14) + 90),
            toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false }, parentHeightOffset: 0,
            fontFamily: getComputedStyle(document.body).fontFamily, locales: [LOCALE_ES], defaultLocale: 'es'
        },
        series: [...grafica.series].reverse().map((serie) => ({ name: serie.nombre, data: grafica.categorias.map((categoria, indice) => ({ x: categoria, y: serie.datos[indice] == null ? null : Number(serie.datos[indice]) })) })),
        plotOptions: { heatmap: { enableShades: false, radius: 0, colorScale: { ranges } } },
        stroke: { width: 1, colors: ['#fff'] },
        dataLabels: { enabled: false },
        legend: { show: true, position: 'top', horizontalAlign: 'left', markers: { fillColors: ranges.map((rango) => rango.color) } },
        xaxis: { labels: { style: denso ? { ...ejes, fontSize: '10px' } : ejes, rotate: pequena && !denso ? -45 : 0, ...(saltoX ? { formatter: (valor) => (grafica.categorias.indexOf(valor) % saltoX ? '' : valor) } : {}) }, title: { text: undefined }, tooltip: { enabled: false } },
        yaxis: { labels: { maxWidth: pequena ? 50 : 70, style: { ...ejes, fontSize: '11px', fontFamily: 'SFMono-Regular, Menlo, Consolas, monospace' } }, title: { text: undefined } },
        tooltip: {
            theme: 'light',
            custom: ({ seriesIndex, dataPointIndex }) => {
                const indice = total - 1 - seriesIndex;
                const detalle = grafica.detalle?.[indice] || {};
                const valor = Number(grafica.series[indice].datos[dataPointIndex]);
                const filas = [
                    ...[['Finca', texto(detalle.finca)], ['Siembra', texto(detalle.siembra)]].filter(([, dato]) => dato),
                    [grafica.ejeX || 'Mes', `${grafica.categorias[dataPointIndex]}${grafica.sufijoX ?? ` ${anio}`}`.trim()],
                    ...(valor < 0 ? [[(especiales[valor] || especiales[-1])?.nombre || '', '']] : [[grafica.medida, formatear(valor, formato)], ...(grafica.medida !== 'Ton/ha' && detalle.tonHa ? [['Ton/ha', formatear(detalle.tonHa[dataPointIndex], 'decimal')]] : [])]),
                    ...(detalle.notas?.[dataPointIndex] ? [[texto(detalle.notas[dataPointIndex]), '']] : [])
                ];

                return `<div class="inf-tooltip"><div class="fw-semibold mb-1">${codigoNombre(detalle.lote || grafica.series[indice].nombre)}</div>
                    ${filas.map(([titulo, dato]) => `<div class="d-flex justify-content-between gap-3"><span>${escaparHtml(titulo)}${dato ? ':' : ''}</span><span class="inf-num">${escaparHtml(dato)}</span></div>`).join('')}</div>`;
            }
        }
    };
};

const opcionesGrafica = (grafica) => {
    if (grafica.tipo === 'heatmap') return opcionesMapa(grafica);

    const espec = especifico();
    let orden = grafica.categorias.map((_, indice) => indice);

    if (grafica.horizontal && grafica.series.length === 1 && !espec.ordenServidor) orden = orden.sort((a, b) => Number(grafica.series[0].datos[b]) - Number(grafica.series[0].datos[a]));

    const categorias = orden.map((indice) => grafica.categorias[indice]);
    const detalle = grafica.detalle ? orden.map((indice) => grafica.detalle[indice]) : null;
    const series = grafica.series.map((serie) => ({ name: serie.nombre, type: serie.tipo, data: orden.map((indice) => (serie.datos[indice] == null ? null : Number(serie.datos[indice]))) }));
    const etiqueta = espec.categoria || ((valor) => valor);
    const formato = grafica.formato || 'decimal';
    const ejes = { fontSize: '12px', colors: '#5c6b7a' };
    const estiloTitulo = { fontSize: '12px', color: '#5c6b7a', fontWeight: 600 };
    const pequena = window.innerWidth < 576;
    const linea = grafica.tipo === 'line';
    const combo = grafica.tipo === 'combo';
    const colores = grafica.colores || (series.length === 1 ? ['#41a867'] : COLORES);
    const alto = grafica.horizontal ? Math.min(520, Math.max(240, categorias.length * 44 + 60)) : (combo ? (pequena ? 300 : 340) : (pequena ? 280 : 320));
    const recortar = (valor) => (texto(valor).length > 24 ? `${texto(valor).slice(0, 23)}…` : valor);
    const menor = grafica.tipo === 'bar' ? Math.min(0, ...series.flatMap((serie) => serie.data.filter((valor) => valor != null))) : 0;
    const porcentual = ['porcentaje', 'variacion'].includes(formato);
    const referencia = grafica.referencia;
    const mayor = Math.max(0, ...(grafica.apilada ? categorias.map((_, indice) => series.reduce((total, serie) => total + serie.data[indice], 0)) : series.filter((_, indice) => !combo || grafica.series[indice].eje !== 1).flatMap((serie) => serie.data)), ...(referencia ? [Number(referencia.valor)] : []));
    const base = mayor / (espec.etiquetaBarra ? 0.65 : 0.8);
    const potencia = mayor > 0 ? 10 ** Math.floor(Math.log10(base)) : 1;
    const paso = base / potencia > 5 ? potencia * 2 : (base / potencia <= 2 ? potencia / 2 : potencia);
    const marcas = mayor > 0 ? Math.ceil(base / paso) : undefined;
    const tope = mayor > 0 ? Number((marcas * paso).toFixed(6)) : undefined;
    const escala = menor < 0 ? { forceNiceScale: true } : { min: 0, max: tope, tickAmount: marcas };
    const salto = !grafica.horizontal && categorias.length > (pequena ? 12 : 40) ? Math.ceil(categorias.length / (pequena ? 6 : 20)) : 0;
    const pesos = series.filter((_, indice) => combo && grafica.series[indice].eje === 1).flatMap((serie) => serie.data).filter((valor) => valor != null);
    const sufijo = (indice) => (combo ? grafica.series[indice].sufijo ?? (/^%/.test(texto(grafica.series[indice].nombre)) ? ' %' : '') : '');
    const derecho = grafica.series.findIndex((serie) => serie.eje === 1);
    const pctDerecho = derecho >= 0 && sufijo(derecho) === ' %';
    const pasoPct = [0.5, 1, 2, 5, 10, 20].find((valor) => valor * 5 >= Math.max(...pesos, 0) * 1.1) || 20;
    const topePct = Math.min(100, Math.max(pasoPct, Math.ceil(Math.max(...pesos, 0) * 1.1 / pasoPct) * pasoPct));
    const escalaMovil = () => {
        const potencia = 10 ** Math.floor(Math.log10((mayor - menor) / 3));
        const pasoMovil = [1, 2, 2.5, 5, 10, 20].map((factor) => factor * potencia).find((valor) => Math.ceil(mayor / valor) - Math.floor(menor / valor) <= 3);

        return mayor > menor ? { min: Math.floor(menor / pasoMovil) * pasoMovil || 0, max: Math.ceil(mayor / pasoMovil) * pasoMovil, tickAmount: Math.ceil(mayor / pasoMovil) - Math.floor(menor / pasoMovil), forceNiceScale: false } : {};
    };
    const corto = (valor) => (valor >= 1e6 ? `$ ${(Math.round(valor / 1e5) / 10).toLocaleString('es-CO')} M` : (valor >= 1e3 ? `$ ${Math.round(valor / 1e3).toLocaleString('es-CO')} mil` : formatear(valor, 'moneda')));
    const yaxis = combo ? [
        { min: 0, max: tope, tickAmount: marcas, labels: { maxWidth: 160, style: { ...ejes, colors: colores[0] }, formatter: (valor) => formatear(valor, formato === 'moneda' ? formato : (paso >= 1 ? 'entero' : 'decimal')) }, title: { text: grafica.ejeY, style: { ...estiloTitulo, color: colores[0] } } },
        { opposite: true, ...(pctDerecho ? { min: 0, max: topePct, tickAmount: Math.round(topePct / pasoPct) } : { min: Math.max(0, Math.floor((pesos.length ? Math.min(...pesos) : 0)) - 1), max: Math.ceil(Math.max(...pesos, 0)) + 1, tickAmount: marcas }), labels: { maxWidth: 160, style: { ...ejes, colors: colores[1] }, formatter: (valor) => `${pctDerecho ? Number(Number(valor).toFixed(1)).toLocaleString('es-CO') : formatear(valor, 'decimal')}${sufijo(derecho)}` }, title: { text: grafica.ejeY2, style: { ...estiloTitulo, color: colores[1] } } }
    ] : {
        labels: { maxWidth: 160, style: ejes, formatter: grafica.horizontal ? recortar : (valor) => formatear(valor, paso >= 1 && !linea ? 'entero' : formato) },
        ...(linea ? { forceNiceScale: true } : (!grafica.horizontal ? escala : {})),
        ...(!grafica.horizontal && grafica.ejeY ? { title: { text: grafica.ejeY, style: estiloTitulo } } : {})
    };

    return {
        chart: {
            type: grafica.tipo === 'combo' ? 'line' : grafica.tipo, height: alto, stacked: Boolean(grafica.apilada),
            toolbar: { show: false }, zoom: { enabled: false }, animations: { speed: 350 }, parentHeightOffset: 0,
            fontFamily: getComputedStyle(document.body).fontFamily, locales: [LOCALE_ES], defaultLocale: 'es'
        },
        colors: colores,
        series,
        ...(linea ? { markers: { size: grafica.anchos ? [5, 3] : 4, strokeWidth: 2, strokeColors: '#fff', hover: { size: 6 } }, stroke: { width: grafica.anchos || 2.5, curve: 'straight', dashArray: grafica.guiones || 0 } } : {}),
        ...(combo ? { markers: { size: [0, 5], strokeWidth: 2, strokeColors: '#fff', hover: { size: 7 } }, stroke: { width: [0, 3], curve: 'straight' } } : {}),
        plotOptions: { bar: { horizontal: Boolean(grafica.horizontal), borderRadius: 4, borderRadiusApplication: 'end', barHeight: '60%', ...(combo ? { columnWidth: '55%' } : {}), ...(espec.etiquetaBarra ? { dataLabels: { position: 'top' } } : {}), ...(menor < 0 ? { colors: { ranges: [{ from: menor, to: -1e-9, color: '#4a5b6c' }] } } : {}) } },
        dataLabels: {
            enabled: Boolean(grafica.horizontal && !grafica.apilada && grafica.etiquetas !== false),
            formatter: espec.etiquetaBarra
                ? (valor, opciones) => [formatear(valor, formato), texto(detalle?.[opciones.dataPointIndex]?.pctHa)].filter(Boolean).join(' · ')
                : (valor) => formatear(valor, formato),
            ...(espec.etiquetaBarra ? { textAnchor: 'start', offsetX: 6 } : {}),
            style: { fontSize: '11px', fontWeight: 600, colors: [espec.etiquetaBarra ? '#2f3b47' : '#fff'] }
        },
        xaxis: {
            categories: categorias.map(etiqueta),
            title: { text: (!combo && grafica.ejeX) || undefined, style: estiloTitulo },
            labels: combo ? { style: ejes, rotate: -45, hideOverlappingLabels: true, trim: false, maxHeight: 90 } : { style: ejes, formatter: grafica.horizontal ? (valor) => formatear(valor, paso >= 1 && !porcentual ? 'entero' : formato) : (salto ? (valor) => (categorias.map(etiqueta).indexOf(valor) % salto ? '' : valor) : undefined), ...(salto ? { rotate: 0 } : {}) },
            ...(grafica.horizontal ? escala : {})
        },
        yaxis,
        ...(referencia ? { annotations: { [grafica.horizontal ? 'xaxis' : 'yaxis']: [{ [grafica.horizontal ? 'x' : 'y']: Number(referencia.valor), borderColor: referencia.color, strokeDashArray: 4, label: { text: referencia.etiqueta, orientation: 'horizontal', borderColor: referencia.color, style: { color: referencia.color, background: '#fff', fontSize: '11px', fontWeight: 600 } } }] } } : {}),
        grid: { borderColor: '#eef1f4', strokeDashArray: 3, padding: { left: 8, right: 16 } },
        legend: { show: series.length > 1, position: 'bottom' },
        tooltip: linea && grafica.anchos
            ? {
                theme: 'light', shared: true, intersect: false,
                custom: ({ dataPointIndex }) => {
                    const [actual, anterior] = series.map((serie) => serie.data[dataPointIndex]);
                    const filas = [...series.map((serie) => [serie.name, formatear(serie.data[dataPointIndex], formato)]), ...(series.length === 2 && actual != null && anterior ? [['Var.', formatear(actual / anterior - 1, 'variacion')]] : [])];

                    return `<div class="inf-tooltip"><div class="fw-semibold mb-1">${escaparHtml(categorias[dataPointIndex])}</div>
                        ${filas.map(([titulo, valor]) => `<div class="d-flex justify-content-between gap-3"><span>${escaparHtml(titulo)}:</span><span class="inf-num">${escaparHtml(valor)}</span></div>`).join('')}</div>`;
                }
            }
            : detalle
            ? {
                theme: 'light',
                ...(combo ? { shared: true, intersect: false } : {}),
                custom: ({ dataPointIndex }) => {
                    const filas = [...series.map((serie, indice) => [serie, (combo && grafica.series[indice].formato) || formato]).filter(([serie]) => linea || combo || serie.data[dataPointIndex] != null).map(([serie, tipo]) => [serie.name, `${formatear(serie.data[dataPointIndex], tipo)}${serie.data[dataPointIndex] == null ? '' : sufijo(series.indexOf(serie))}`]),
                        ...Object.entries(espec.detalle || {}).filter(([clave]) => detalle[dataPointIndex]?.[clave] !== undefined).map(([clave, [titulo, tipo]]) => [titulo, formatear(detalle[dataPointIndex]?.[clave], tipo)])];

                    return `<div class="inf-tooltip"><div class="fw-semibold mb-1">${codigoNombre(categorias[dataPointIndex])}</div>
                        ${filas.map(([titulo, valor]) => `<div class="d-flex justify-content-between gap-3"><span>${escaparHtml(titulo)}:</span><span class="inf-num">${escaparHtml(valor)}</span></div>`).join('')}</div>`;
                }
            }
            : { theme: 'light', y: { formatter: (valor) => formatear(valor, formato) } },
        responsive: [{ breakpoint: 576, options: combo ? { yaxis: yaxis.map((eje, indice) => ({ ...eje, labels: { ...eje.labels, maxWidth: 50, ...(indice === 0 && formato === 'moneda' ? { maxWidth: 70, formatter: corto } : {}) }, title: { text: undefined } })), xaxis: { labels: { rotate: -90 } }, markers: { size: [0, 4] } } : { dataLabels: { enabled: false }, ...(grafica.horizontal && formato === 'moneda' ? { xaxis: { ...escalaMovil(), labels: { formatter: (valor) => `${valor < 0 ? '-' : ''}${corto(Math.abs(valor))}` } } } : {}), yaxis: grafica.horizontal ? { labels: { maxWidth: 100 } } : { ...yaxis, labels: { ...yaxis.labels, maxWidth: 100 }, title: { text: undefined } } } }]
    };
};

const renderCelda = (columna) => (dato, tipo, fila) => {
    const espec = especifico();

    if (NUMERICOS.includes(columna.tipo)) {
        if (tipo !== 'display') return Number(dato || 0);

        if (dato == null) return '<span class="text-muted">—</span>';

        const marca = espec.marca?.[columna.clave]?.(fila)
            ? `<span class="gp-punto-cambio me-1" title="${escaparHtml((typeof espec.marcaTitulo === 'function' ? espec.marcaTitulo(fila, columna.clave) : espec.marcaTitulo) || '')}"></span>`
            : '';

        return marca + formatear(dato, columna.tipo);
    }

    if (tipo !== 'display') return texto(dato) || texto(espec.vacioCelda?.[columna.clave]);

    if (columna.tipo === 'estado') {
        if (texto(dato) === '') return '';

        return /^(activ[oa]|vigente|s[ií]|1|producci[oó]n|autom[aá]tico)$/i.test(texto(dato))
            ? `<span class="gp-chip-fila verde">${escaparHtml(dato)}</span>`
            : `<span class="gp-chip-fila inf-chip-pronto">${escaparHtml(dato)}</span>`;
    }

    if (columna.tipo === 'etiquetas') {
        const etiquetas = texto(dato).split(/\s*[·|]\s*/).filter(Boolean);

        return etiquetas.length
            ? `<div class="inf-etiquetas">${etiquetas.map((parte) => `<span class="gp-chip-fila ${(espec.destacar || []).some((inicio) => parte.startsWith(inicio)) ? 'verde' : 'inf-chip-filtro'}">${escaparHtml(parte)}</span>`).join('')}</div>`
            : '<span class="text-muted">—</span>';
    }

    if (espec.vacioCelda?.[columna.clave] && texto(dato) === '') return `<span class="gp-chip-fila ambar">${escaparHtml(espec.vacioCelda[columna.clave])}</span>`;

    const subtexto = espec.subtexto?.[columna.clave];

    if (columna.tipo === 'fecha' || columna.alineacion === 'centro') return `<span class="font-monospace">${escaparHtml(dato)}</span>${texto(subtexto?.(fila)) ? `<div class="gp-subtexto">${escaparHtml(subtexto(fila))}</div>` : ''}`;
    const contenido = partes(dato).length > 1 && /^[\w.-]{1,20}$/.test(partes(dato)[0])
        ? codigoNombre(dato)
        : (texto(dato).length > 40 ? `<span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(dato)}">${escaparHtml(dato)}</span>` : escaparHtml(dato));

    return texto(subtexto?.(fila)) ? `${contenido}<div class="gp-subtexto gp-truncar" title="${escaparHtml(subtexto(fila))}">${escaparHtml(subtexto(fila))}</div>` : contenido;
};

const claseColumna = (columna) => `${NUMERICOS.includes(columna.tipo) ? 'inf-num' : (columna.tipo === 'estado' || columna.tipo === 'fecha' || columna.alineacion === 'centro' ? 'text-center' : '')}${columna.futuro ? ' text-muted' : ''}`.trim();

const pintarTabla = (respuesta) => {
    const espec = especifico();
    const columnas = respuesta.columnas;
    const ocultas = espec.ocultas || [];
    const visibles = columnas.filter((columna) => !ocultas.includes(columna.clave));
    const conTotal = columnas.some((columna) => columna.total);
    const primeraNumerica = conTotal ? Math.max(1, visibles.findIndex((columna) => NUMERICOS.includes(columna.tipo))) : visibles.length;
    const indiceGrupo = columnas.findIndex((columna) => columna.clave === respuesta.agrupar);
    const posicion = new Map();
    respuesta.filas.forEach((fila, indice) => posicion.has(fila[respuesta.agrupar]) || posicion.set(fila[respuesta.agrupar], String(indice).padStart(6, '0')));
    const suma = (filas, clave) => filas.reduce((total, fila) => total + Number(fila[clave] || 0), 0);
    const agregado = (filas, columna) => {
        if (columna.total !== 'ponderado') return suma(filas, columna.clave);
        const denominador = suma(filas, columna.denominador);
        return denominador ? suma(filas, columna.numerador) / denominador : null;
    };
    const tabla = $id('tabla_informe');

    tabla.classList.toggle('gp-tabla-ancha', visibles.length > 10);
    tabla.classList.toggle('inf-tabla-fija', Boolean(espec.columnaFija));
    tabla.style.minWidth = visibles.length > 10 ? '' : `${visibles.length * 110}px`;
    tabla.innerHTML = `<thead class="gp-thead"><tr>${columnas.map((columna) => `<th class="${claseColumna(columna)}" style="${espec.anchos?.[columna.clave] || ''}" title="${escaparHtml(columna.ayuda || '')}">${escaparHtml(columna.titulo)}</th>`).join('')}</tr></thead>
        <tbody></tbody>
        ${conTotal ? `<tfoot class="inf-pie"><tr>${columnas.map(() => '<th></th>').join('')}</tr></tfoot>` : ''}`;

    const cantidad = (filas) => {
        const total = filas.filter(espec.cuenta || (() => true)).length;
        const [uno, varios] = espec.unidad || ['registro', 'registros'];

        return `${formatear(total, 'entero')} ${total === 1 ? uno : varios}`;
    };

    const largos = [...new Set([25, 50, 100, espec.pageLength || 25])].sort((a, b) => a - b);

    $('#tabla_informe').DataTable({
        data: respuesta.filas,
        columns: columnas.map((columna) => ({ data: columna.clave, visible: !ocultas.includes(columna.clave), className: claseColumna(columna), render: espec.ordenServidor && columna.clave === respuesta.agrupar ? (dato, tipo, fila) => (tipo === 'sort' ? posicion.get(dato) : renderCelda(columna)(dato, tipo, fila)) : (espec.columnaFija && columna === visibles[0] ? (dato, tipo, fila) => (tipo === 'display' ? `<div class="inf-celda-fija" title="${escaparHtml(dato)}">${renderCelda(columna)(dato, tipo, fila)}</div>` : renderCelda(columna)(dato, tipo, fila)) : renderCelda(columna)) })),
        order: indiceGrupo >= 0 || espec.ordenServidor ? [] : [[0, 'asc']],
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
                    ${subtitulo ? `<div class="gp-subtexto${espec.subtituloAjustado ? ' inf-subtitulo-ajustado' : ''}">${escaparHtml(subtitulo)}</div>` : ''}
                </td>`);
                visibles.slice(primeraNumerica).forEach((columna) => fila.append(`<td class="${claseColumna(columna)}">${columna.total ? formatear(agregado(datos, columna), columna.tipo) : ''}</td>`));

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
        footerCallback: conTotal ? function () {
            const api = this.api();
            const datos = api.rows({ search: 'applied' }).data().toArray();

            columnas.forEach((columna, indice) => {
                const pie = api.column(indice).footer();

                pie.className = claseColumna(columna);
                pie.title = columna.ayuda || '';
                pie.innerHTML = columna.total && columna.total !== 'grupo' && columna.pie !== false ? formatear(agregado(datos, columna), columna.tipo) : '';
            });

            api.column(columnas.indexOf(visibles[0])).footer().innerHTML = 'Total general';
        } : undefined,
        pageLength: espec.pageLength || 25,
        lengthMenu: [[...largos, -1], [...largos, 'Todos']],
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

    const multiples = Array.isArray(respuesta.graficas) && respuesta.graficas.length > 0;
    const graficas = multiples ? respuesta.graficas : (respuesta.grafica ? [respuesta.grafica] : []);

    $id('resultado_cuerpo').innerHTML = avisos + pintarKpis(kpis)
        + graficas.map((grafica, indice) => `<div class="card mb-3"><div class="card-header bg-white py-3"><h6 class="gp-seccion mb-0"><i class="${escaparHtml((multiples && grafica.icono) || 'bi bi-bar-chart')}" aria-hidden="true"></i>${escaparHtml(multiples ? grafica.titulo : (espec.titulo || grafica.titulo || respuesta.informe?.nombre || 'Gráfica'))}</h6></div><div class="card-body${grafica.tipo === 'heatmap' ? ' inf-grafica-scroll' : ''}"><div class="inf-grafica" id="grafica_${indice}"></div></div></div>`).join('')
        + `<div class="card"><div class="card-header bg-white py-3"><h6 class="gp-seccion mb-0"><i class="bi bi-table" aria-hidden="true"></i>Detalle</h6></div>
            <div class="card-body p-0"><table class="table table-hover align-middle mb-0 gp-tabla inf-tabla w-100" id="tabla_informe"></table></div></div>`;

    estado.graficas = graficas.map((grafica, indice) => new ApexCharts($id(`grafica_${indice}`), opcionesGrafica(grafica)));
    estado.graficas.forEach((grafica) => grafica.render());

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

    if (estado.informe) recargarIniciales(estado.informe);
};

const recargarDependientes = async (origen, lista = null) => {
    const informe = estado.informe;
    const dependientes = lista || (informe?.filtros || []).filter((filtro) => [].concat(filtro.depende || []).includes(origen));

    for (const filtro of dependientes) {
        try {
            const resultado = await pedir('opciones', { empresa: empresaId(), modulo: INFORMES.modulo, informe: informe.clave, filtro: filtro.clave, dependencias: JSON.stringify(valoresFiltros()) });

            if (estado.informe !== informe) return;

            const control = $id(`f_${filtro.clave}`);

            control.innerHTML = '<option value=""></option>' + (resultado.filas || []).map((opcion) => `<option value="${escaparHtml(opcion.valor)}">${escaparHtml(opcion.texto)}</option>`).join('');
            $(control).trigger('change.select2');
        } catch (error) {
            if (estado.informe !== informe) return;

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
