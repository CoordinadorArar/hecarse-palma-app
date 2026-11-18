const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

const CFG = { ruta: 'labores', jornales: true, referencia: false, ...(typeof MODULO_TX === 'undefined' ? {} : MODULO_TX) };
const EXT = {};
const URL_LAB = BASE_URL + `transacciones/${CFG.ruta}/`;
const MAX_LINEAS = 500;

const $id = (id) => document.getElementById(id);
const mostrar = (elemento, visible) => elemento?.classList.toggle('d-none', !visible);
const poner = (id, valor) => { if ($id(id)) $id(id).textContent = valor; };
const texto = (valor) => String(valor ?? '').trim();

const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const fmt = (valor, decimales = 0) => Number(valor || 0).toLocaleString('es-CO', {
    minimumFractionDigits: decimales, maximumFractionDigits: decimales
});

const cant = (valor) => fmt(valor, 2);

const IDIOMA_SELECT2 = {
    noResults: () => 'No se encontraron resultados',
    searching: () => 'Buscando…',
    inputTooShort: () => 'Escriba al menos 3 caracteres',
    errorLoading: () => 'No se pudo consultar'
};

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const fechaCorta = (valor) => {
    const partes = texto(valor).match(/^(\d{4})-(\d{2})-(\d{2})/);

    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : texto(valor);
};

const hoyIso = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const aNumero = (valor) => {
    const crudo = texto(valor).replace(/\s/g, '');

    if (crudo === '') return 0;

    return /^\d+([.,]\d{1,2})?$/.test(crudo) ? Number(crudo.replace(',', '.')) : NaN;
};

kgGramos.parsear = aNumero;

const estado = {
    tipo: false,
    lineas: [],
    secuencia: 1,
    periodos: [],
    edicion: null,
    foto: null,
    guardando: false,
    insumosLote: {},
    fechas: []
};

let turnoPeriodos = 0;
let turnoLotes = 0;

const empresaId = () => $id('empresa').value;

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;
    let resultado = null;

    try {
        respuesta = await fetch(URL_LAB + ruta, { method: 'POST', body: cuerpo });
    } catch (error) {
        throw new Error('Error de conexión.');
    }

    try {
        resultado = await respuesta.json();
    } catch (error) {
        resultado = null;
    }

    if (!respuesta.ok || resultado === null || resultado.success !== true) {
        const accion = { guardar: 'registrar', actualizar: 'editar', eliminar: 'eliminar', consultar: 'consultar', detalle: 'consultar' }[ruta] || 'realizar esta acción';
        const fallo = new Error(texto(resultado?.message)
            || (respuesta.status === 403 ? `No tiene permiso para ${accion}. Solicítelo al administrador.` : 'No se pudo completar la operación.'));

        fallo.datos = resultado || {};
        fallo.status = respuesta.status;
        throw fallo;
    }

    return resultado;
};

const avisar = (error) => Toast.fire({
    icon: error.message === 'Error de conexión.' ? 'error' : 'warning',
    title: escaparHtml(error.message)
});

const periodoActual = () => estado.periodos.find((periodo) => String(Number(periodo.mes)) === $id('periodo').value) || null;
const nombrePeriodo = (periodo) => texto(periodo.descripcion) || (Number(periodo.mes) > 12 ? 'Periodo adicional' : MESES[Number(periodo.mes) - 1]);
const rangoPeriodo = () => {
    const periodo = periodoActual();

    return periodo ? [texto(periodo.fechaInicial).substring(0, 10), texto(periodo.fechaFinal).substring(0, 10)] : ['', ''];
};

const fechaEnPeriodo = (fecha) => {
    const periodo = periodoActual();
    const [inicio, fin] = rangoPeriodo();

    if (!periodo || fecha === '') return false;

    if (inicio !== '' && fin !== '') return fecha >= inicio && fecha <= fin;

    return Number(periodo.mes) > 12 || fecha.substring(0, 7) === `${$id('anio').value}-${String(Number(periodo.mes)).padStart(2, '0')}`;
};

const revisarPeriodo = () => {
    const periodo = periodoActual();
    const fecha = $id('fecha').value;
    const [inicio, fin] = rangoPeriodo();
    const cerrado = periodo !== null && Number(periodo.cerrado) === 1;
    const fuera = periodo !== null && fecha !== '' && !fechaEnPeriodo(fecha);

    $id('periodo_rango').textContent = periodo ? (inicio && fin ? `${fechaCorta(inicio)} – ${fechaCorta(fin)}` : 'Sin rango de fechas definido') : '';
    mostrar($id('badge_cerrado'), cerrado);
    $id('nota_periodo_texto').textContent = cerrado
        ? `El periodo ${nombrePeriodo(periodo)} está cerrado. Elija un periodo abierto para guardar.`
        : (fuera ? `La fecha ${fechaCorta(fecha)} no está dentro del periodo ${nombrePeriodo(periodo)}${inicio && fin ? ` (${fechaCorta(inicio)} – ${fechaCorta(fin)})` : ''}.` : '');
    mostrar($id('nota_periodo'), cerrado || fuera);
    $id('fecha').classList.toggle('is-invalid', fuera);
    pintarFechas();
};

const pintarFechas = (destacar = '') => {
    if (CFG.referencia) return;

    const fechas = estado.fechas;
    const total = fechas.length;
    const todas = $id('todas_fechas').checked;

    mostrar($id('col_fechas'), total > 0);
    $id('col_fechas').innerHTML = total ? `<div class="d-flex flex-wrap align-items-center gap-2">
        <span class="gp-etiqueta-mini">Fechas a cargar</span><span class="tx-chip-cont verde">${fmt(total)}</span>
        ${fechas.map((fecha) => {
        const dentro = periodoActual() === null || fechaEnPeriodo(fecha);

        return `<span class="gp-chip-fila verde tx-chip-fecha${dentro ? '' : ' fuera'}${fecha === destacar ? ' tx-flash' : ''}"${dentro ? '' : ' title="Fuera del periodo"'}><span class="font-monospace">${fechaCorta(fecha).substring(0, 5)}</span><button type="button" class="tx-chip-quitar" data-quitar-fecha="${fecha}" aria-label="Quitar ${fechaCorta(fecha)}"><i class="bi bi-x" aria-hidden="true"></i></button></span>`;
    }).join('')}
        <button type="button" class="btn btn-link btn-sm p-0 ms-auto" data-limpiar-fechas>Limpiar</button>
        <span class="gp-subtexto w-100">Se crea una línea por fecha con la misma labor, lote, terceros y cantidad.</span>
    </div>` : '';
    $id('btn_cargar').innerHTML = `<i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Cargar${total ? ` · ${fmt(total)} ${total === 1 ? 'fecha' : 'fechas'}` : ''}`;
    $id('ayuda_fecha_labor').textContent = total
        ? ''
        : (todas ? 'Editable por línea, dentro del periodo.' : 'Igual a la fecha de la transacción');
};

const agregarFecha = () => {
    const fecha = $id('cap_fecha').value;
    const repetida = estado.fechas.includes(fecha);
    const fuera = !fechaEnPeriodo(fecha);

    $id('error_fecha_labor').textContent = repetida && !fuera ? 'La fecha ya está en la lista.' : 'La fecha debe estar dentro del periodo.';
    errorCampo('error_fecha_labor', fuera || repetida);

    if (!fuera && !repetida) estado.fechas = [...estado.fechas, fecha].sort();

    pintarFechas(fuera ? '' : fecha);
};

const cargarPeriodos = async (anio, mes) => {
    const selector = $id('periodo');
    const turno = ++turnoPeriodos;

    estado.periodos = [];
    selector.disabled = true;
    selector.classList.remove('is-invalid');
    selector.innerHTML = '<option value="">Cargando…</option>';
    revisarPeriodo();

    let filas = [];

    try {
        filas = (await pedir('periodos', { empresa: empresaId(), anio })).filas || [];
    } catch (error) {
        if (turno === turnoPeriodos) avisar(error);
    }

    if (turno !== turnoPeriodos) return;

    if (filas.length === 0) {
        selector.innerHTML = '<option value="">No hay periodos para el año</option>';
        revisarPeriodo();

        return;
    }

    const hoy = hoyIso();
    const abiertos = filas.filter((periodo) => Number(periodo.cerrado) !== 1);
    const sugerido = (mes ? filas.find((periodo) => Number(periodo.mes) === Number(mes)) : null)
        || abiertos.find((periodo) => hoy >= texto(periodo.fechaInicial).substring(0, 10) && hoy <= texto(periodo.fechaFinal).substring(0, 10))
        || abiertos.find((periodo) => Number(periodo.mes) === Number(selector.dataset.mes))
        || abiertos[0];

    estado.periodos = filas;
    selector.innerHTML = '<option value="">Seleccione el periodo…</option>' + filas.map((periodo) => {
        const cerrado = Number(periodo.cerrado) === 1;
        const propio = mes && Number(periodo.mes) === Number(mes);

        return `<option value="${Number(periodo.mes)}"${cerrado && !propio ? ' disabled' : ''}>${String(Number(periodo.mes)).padStart(2, '0')} — ${escaparHtml(nombrePeriodo(periodo))}${cerrado ? ' — Cerrado' : ''}</option>`;
    }).join('');
    selector.value = sugerido ? String(Number(sugerido.mes)) : '';

    if (selector.selectedIndex < 0) selector.value = '';

    selector.disabled = false;

    if (!mes && sugerido && !fechaEnPeriodo($id('fecha').value)) {
        const [inicio, fin] = rangoPeriodo();

        if (inicio && fin) $id('fecha').value = hoy >= inicio && hoy <= fin ? hoy : inicio;
        sincronizarFechas();
    }

    revisarPeriodo();
};

const sincronizarFechas = () => {
    const todas = CFG.referencia || $id('todas_fechas').checked;
    const captura = $id('cap_fecha');

    $id('panel_todas')?.classList.toggle('activo', todas);
    captura.disabled = !todas;
    captura.classList.toggle('tx-neto', !todas);

    if (!todas) estado.fechas = [];

    mostrar($id('btn_agregar_fecha'), todas && !CFG.referencia);
    pintarFechas();

    if (!todas || CFG.referencia || captura.value === '') captura.value = $id('fecha').value;

    if (!todas) {
        estado.lineas.forEach((linea) => {
            if (linea.fecha !== $id('fecha').value) {
                linea.fecha = $id('fecha').value;
                linea.trabajadores.forEach((trabajador) => { trabajador.precio = null; });
            }
        });
        pintarLineas();
    }
};

const trabajadoresDe = () => estado.lineas.flatMap((linea) => linea.trabajadores);
const pendientes = () => trabajadoresDe().filter((trabajador) => trabajador.precio === null).length;
const valorTrabajador = (trabajador) => (trabajador.precio === null ? 0 : Math.round(Math.round(trabajador.cantidad * trabajador.precio * 10000) / 10000));
const valorLinea = (linea) => linea.trabajadores.reduce((suma, trabajador) => suma + valorTrabajador(trabajador), 0);
const sinTarifa = (trabajador) => trabajador.precio !== null && (!trabajador.existe || trabajador.precio === 0);
const invalidar = () => trabajadoresDe().forEach((trabajador) => { trabajador.precio = null; });
const CHIP_PENDIENTE = '<span class="gp-chip-fila ambar"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Pendiente</span>';

const repartir = (total, partes) => {
    const centesimas = Math.round(total * 100);
    const base = Math.floor(centesimas / partes);
    const sobrante = centesimas - base * partes;

    return Array.from({ length: partes }, (_, indice) => (base + (indice < sobrante ? 1 : 0)) / 100);
};

const repartirLinea = (linea) => {
    const cantidades = repartir(linea.cantidad, linea.trabajadores.length);
    const jornales = repartir(linea.jornales, linea.trabajadores.length);

    linea.trabajadores.forEach((trabajador, indice) => {
        trabajador.cantidad = cantidades[indice];
        trabajador.jornales = jornales[indice];
    });
};

const celdaPrecio = (trabajador) => {
    if (trabajador.precio === null) return CHIP_PENDIENTE;

    if (sinTarifa(trabajador)) {
        return '<span class="tx-num tx-texto-ambar">0 <i class="bi bi-exclamation-triangle" title="Sin tarifa para esta labor/lote/tercero" aria-label="Sin tarifa para esta labor/lote/tercero"></i></span>';
    }

    return fmt(trabajador.precio, 2);
};

const filaTrabajadorLab = (linea, trabajador) => {
    const nombre = escaparHtml(trabajador.terceroNombre || trabajador.tercero);
    const titulo = linea.trabajadores.length === 1 ? 'Quitar trabajador (se quita la línea)' : `Quitar a ${nombre}`;
    const bloqueo = linea.cargando ? ' disabled' : '';
    const codigo = trabajador.terceroCodigo ? `<span class="font-monospace fw-semibold">${escaparHtml(trabajador.terceroCodigo)}</span> ` : '';

    return `<tr class="${trabajador.marca || ''}">
        <td>${codigo}<span class="${CFG.referencia ? 'text-break' : 'gp-truncar d-inline-block align-bottom'}" title="${nombre}">${nombre}</span><span class="gp-subtexto font-monospace">${escaparHtml(trabajador.tercero)}</span></td>
        <td class="text-end tx-num">${cant(trabajador.cantidad)}</td>
${CFG.jornales ? `        <td class="text-end tx-num${trabajador.jornales === 0 ? ' tx-cero' : ''}">${cant(trabajador.jornales)}</td>` : ''}
        <td class="text-end tx-num">${celdaPrecio(trabajador)}</td>
        <td class="text-end tx-num fw-semibold">${trabajador.precio === null ? '<span class="text-muted">—</span>' : fmt(valorTrabajador(trabajador))}</td>
        <td class="text-center gp-col-sticky"><div class="d-flex gap-1 justify-content-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-editar-trabajador="${escaparHtml(trabajador.tercero)}" data-linea="${linea.id}" title="Editar a ${nombre}" aria-label="Editar a ${nombre}"${bloqueo}>
                <i class="bi bi-pencil" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-trabajador="${escaparHtml(trabajador.tercero)}" data-linea="${linea.id}" title="${titulo}" aria-label="${titulo}"${bloqueo}>
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div></td>
    </tr>`;
};

const MAX_INSUMOS = 20;
const itemsCatalogo = () => CFG.items || [];
const nombreItem = (codigo) => texto(itemsCatalogo().find((item) => texto(item.codigo) === codigo)?.descripcion) || codigo;
const cantidadInsumo = (linea, insumo) => (insumo.cantidad ?? Math.round(Math.round(linea.cantidad * insumo.dosis * 1000000) / 10000) / 100);
const insumoDe = (dato) => ({
    item: texto(dato.item), itemNombre: texto(dato.itemNombre) || nombreItem(texto(dato.item)), uMedida: texto(dato.uMedida),
    dosis: Number(dato.dosis || 0), cantidad: dato.cantidad === undefined ? undefined : Number(dato.cantidad)
});

const bloqueInsumos = (linea) => {
    const insumos = linea.insumos;
    const id = linea.id;
    const bloqueo = linea.cargando ? ' disabled' : '';
    const opciones = itemsCatalogo().map((item) => `<option value="${escaparHtml(texto(item.codigo))}" data-um="${escaparHtml(texto(item.uMedida))}">${escaparHtml(texto(item.descripcion))}</option>`).join('');
    const filas = insumos.map((insumo) => {
        const nombre = escaparHtml(insumo.itemNombre);

        return `<tr class="${insumo.marca || ''}">
            <td><span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span><span class="gp-subtexto font-monospace">${escaparHtml(insumo.item)}</span></td>
            <td class="text-center"><span class="gp-chip-fila tx-chip-neutro">${escaparHtml(insumo.uMedida)}</span></td>
            <td class="text-end tx-num">${cant(insumo.dosis)}</td>
            <td class="text-end tx-num fw-semibold" title="${cant(linea.cantidad)} palmas × ${cant(insumo.dosis)}">${cant(cantidadInsumo(linea, insumo))}</td>
            <td class="text-center gp-col-sticky">
                <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-insumo="${escaparHtml(insumo.item)}" data-linea="${id}" title="Quitar ${nombre}" aria-label="Quitar ${nombre}"${bloqueo}>
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    return `<div class="tx-insumos">
        <div class="tx-insumos-cab">
            <i class="bi bi-droplet-half" aria-hidden="true"></i><span class="gp-etiqueta-mini">Insumos</span>
            ${insumos.length ? `<span class="tx-chip-cont verde">${fmt(insumos.length)}</span>` : '<span class="gp-chip-fila ambar"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Sin insumos</span>'}
            <span class="gp-subtexto ms-auto">Dosis por palma · ${cant(linea.cantidad)} palmas</span>
        </div>
        ${insumos.length ? `<div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 gp-tabla tx-grilla tx-tabla-trab">
                <thead class="gp-thead">
                    <tr>
                        <th style="min-width:240px">Item</th>
                        <th class="text-center" style="width:80px">U.M.</th>
                        <th class="text-end" style="width:110px">Dosis</th>
                        <th class="text-end" style="width:130px">Cantidad</th>
                        <th class="text-center gp-col-sticky" style="width:52px"><span class="visually-hidden">Quitar</span></th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        </div>` : ''}
        <div class="tx-insumos-captura">
            <div class="row g-2 align-items-start">
                <div class="col-12 col-md">
                    <label class="visually-hidden" for="ins_item_${id}">Insumo</label>
                    <div class="tx-select-ancho tx-select-sm">
                        <select class="form-select form-select-sm tx-insumo-item" id="ins_item_${id}" data-linea="${id}"${bloqueo}><option value=""></option>${opciones}</select>
                    </div>
                    <div class="invalid-feedback d-block d-none" id="ins_error_item_${id}"></div>
                </div>
                <div class="col-5 col-md-2">
                    <label class="visually-hidden" for="ins_um_${id}">U.M.</label>
                    <select class="form-select form-select-sm" id="ins_um_${id}"${bloqueo}>${$id('cap_um').innerHTML}</select>
                    <div class="invalid-feedback">Seleccione la unidad.</div>
                </div>
                <div class="col-5 col-md-2">
                    <label class="visually-hidden" for="ins_dosis_${id}">Dosis</label>
                    <input type="text" class="form-control form-control-sm tx-input-num" id="ins_dosis_${id}" data-dosis="${id}" inputmode="decimal" placeholder="0,00" autocomplete="off" maxlength="10" data-gramos-um="#ins_um_${id}" data-gramos-sufijo="/palma" aria-describedby="ins_dosis_${id}_g"${bloqueo}>
                    <div class="invalid-feedback">Dosis mayor a 0, máx. 2 decimales.</div>
                    <span class="gp-gramos d-block text-end mt-1 d-none" id="ins_dosis_${id}_g"></span>
                </div>
                <div class="col-2 col-md-auto">
                    <button type="button" class="btn btn-success btn-sm w-100" data-agregar-insumo="${id}" title="Agregar insumo" aria-label="Agregar insumo"${bloqueo}><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>
        ${linea.errorInsumo ? `<div class="invalid-feedback d-block px-3 pb-2">${escaparHtml(linea.errorInsumo)}</div>` : ''}
    </div>`;
};

const agregarInsumo = (id) => {
    const linea = estado.lineas.find((item) => item.id === id);

    if (!linea) return;

    const item = $id(`ins_item_${id}`).value;
    const um = $id(`ins_um_${id}`);
    const dosisCampo = $id(`ins_dosis_${id}`);
    const dosis = aNumero(dosisCampo.value);
    const errorItem = item === '' ? 'Seleccione un insumo.' : (linea.insumos.some((insumo) => insumo.item === item) ? 'El insumo ya está en esta línea.' : '');

    $id(`ins_item_${id}`).closest('.tx-select-ancho').classList.toggle('is-invalid', errorItem !== '');
    $id(`ins_error_item_${id}`).textContent = errorItem;
    mostrar($id(`ins_error_item_${id}`), errorItem !== '');
    um.classList.toggle('is-invalid', um.value === '');
    dosisCampo.classList.toggle('is-invalid', !(dosis > 0));

    if (errorItem || um.value === '' || !(dosis > 0)) return;

    if (linea.insumos.length >= MAX_INSUMOS) {
        Toast.fire({ icon: 'warning', title: `Máximo ${MAX_INSUMOS} insumos por línea` });

        return;
    }

    linea.insumos.push({ item, itemNombre: nombreItem(item), uMedida: um.value, dosis, marca: 'tx-flash' });
    [`ins_item_${id}`, `ins_um_${id}`, `ins_dosis_${id}`].forEach((campo) => { $id(campo).value = ''; });
    linea.errorInsumo = '';
    pintarLineas();
    $(`#ins_item_${id}`).select2('open');
};

const chipCuadrilla = (linea) => {
    const grupo = estado.lineas.filter((item) => item.novedad === linea.novedad && item.finca === linea.finca && item.lote === linea.lote && item.fecha === linea.fecha);

    return grupo.length > 1 ? `<span class="gp-chip-fila tx-chip-neutro text-nowrap" title="Cuadrilla ${fmt(grupo.indexOf(linea) + 1)} de ${fmt(grupo.length)} en esta labor, lote y fecha"><i class="bi bi-people me-1" aria-hidden="true"></i>Cuadrilla ${fmt(grupo.indexOf(linea) + 1)} de ${fmt(grupo.length)}</span>` : '';
};

const tarjetaLinea = (linea, indice) => {
    const pendiente = linea.trabajadores.some((trabajador) => trabajador.precio === null);
    const faltaTarifa = linea.trabajadores.some(sinTarifa);
    const labor = `${escaparHtml(linea.novedad)} — ${escaparHtml(linea.novedadNombre)}`;
    const valor = pendiente
        ? CHIP_PENDIENTE
        : `${fmt(valorLinea(linea))}${faltaTarifa ? ' <i class="bi bi-exclamation-triangle tx-texto-ambar" title="Hay trabajadores sin tarifa" aria-label="Hay trabajadores sin tarifa"></i>' : ''}`;

    return `<div class="tx-tarjeta${linea.planeacion ? ' tx-tarjeta--planeada' : ''}${faltaTarifa ? ' tx-tarjeta--sintarifa' : ''}${linea.marca ? ` ${linea.marca}` : ''}" data-linea="${linea.id}"${linea.cargando ? ' aria-busy="true"' : ''}>
        <div class="tx-tarjeta-cab tx-tarjeta-cab--fija flex-wrap">
            <span class="gp-chip-fila tx-chip-neutro font-monospace">#${indice + 1}</span>
            <span><span class="font-monospace fw-semibold">${escaparHtml(linea.novedad)}</span> — <span class="gp-truncar d-inline-block align-bottom" title="${labor}">${escaparHtml(linea.novedadNombre)}</span></span>
            <span class="gp-chip-fila tx-chip-neutro">${escaparHtml(linea.uMedida)}</span>
            ${linea.planeacion ? chipPlaneacion(linea.planeacion) : ''}
            <span class="tx-sep">&middot;</span>
            ${periodoActual() && !fechaEnPeriodo(linea.fecha)
        ? `<span class="text-nowrap text-danger" title="La fecha de la línea está fuera del periodo seleccionado"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>${fechaCorta(linea.fecha)}</span>`
        : `<span class="text-nowrap">${fechaCorta(linea.fecha)}</span>`}
            <span class="tx-sep">&middot;</span>
            <span class="font-monospace">${CFG.referencia ? '' : `${escaparHtml(linea.finca)} / `}${linea.seccion ? escaparHtml(linea.seccion) : '<span class="text-muted">—</span>'} / ${escaparHtml(linea.lote)}</span>
            ${chipCuadrilla(linea)}
            <span class="tx-chip-cont verde">${fmt(linea.trabajadores.length)} trab.</span>
            ${CFG.referencia ? `<span class="tx-chip-cont${linea.insumos.length ? ' verde' : ''}">${fmt(linea.insumos.length)} ins.</span>` : ''}
            <span class="ms-auto d-flex align-items-end gap-3">
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Cantidad</span><span class="tx-dato-valor">${cant(linea.cantidad)}</span></span>
${CFG.jornales ? `                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Jornales</span><span class="tx-dato-valor${linea.jornales === 0 ? ' tx-cero' : ''}">${cant(linea.jornales)}</span></span>` : ''}
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Valor</span><span class="tx-dato-valor fw-bold" style="color:#2f3b48">${valor}</span></span>
                <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-linea="${linea.id}" title="Quitar línea ${indice + 1}" aria-label="Quitar línea ${indice + 1}">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                </button>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 gp-tabla tx-grilla tx-tabla-trab">
                <thead class="gp-thead">
                    <tr>
                        <th style="min-width:240px">Tercero</th>
                        <th class="text-end" style="width:110px">Cantidad</th>
${CFG.jornales ? '                        <th class="text-end" style="width:100px">Jornales</th>' : ''}
                        <th class="text-end" style="width:120px">Precio</th>
                        <th class="text-end" style="width:130px">Valor</th>
                        <th class="text-center gp-col-sticky" style="width:88px"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>${linea.trabajadores.map((trabajador) => filaTrabajadorLab(linea, trabajador)).join('')}</tbody>
            </table>
        </div>
        ${CFG.referencia ? bloqueInsumos(linea) : ''}
    </div>`;
};

const pintarLineas = () => {
    const lineas = estado.lineas;
    const hay = lineas.length > 0;
    const sinLiquidar = pendientes();
    const valor = lineas.reduce((suma, linea) => suma + valorLinea(linea), 0);

    const foco = document.activeElement?.id || '';
    const capturas = Array.from(document.querySelectorAll('.tx-insumo-item')).map((selector) => {
        const id = selector.dataset.linea;
        const error = $id(`ins_error_item_${id}`);

        return {
            id,
            campos: ['ins_item_', 'ins_um_', 'ins_dosis_'].map((prefijo) => [prefijo + id, $id(prefijo + id).value, $id(prefijo + id).classList.contains('is-invalid')]),
            envoltura: selector.closest('.tx-select-ancho').classList.contains('is-invalid'),
            error: error.textContent,
            errorVisible: !error.classList.contains('d-none')
        };
    });

    $id('lista_lineas').innerHTML = lineas.map(tarjetaLinea).join('');
    lineas.forEach((linea) => {
        linea.marca = '';
        linea.insumos.forEach((insumo) => { insumo.marca = ''; });
        linea.trabajadores.forEach((trabajador) => { trabajador.marca = ''; });
    });

    if (CFG.referencia) {
        $('.tx-insumo-item').select2({ width: '100%', placeholder: 'Buscar insumo…', language: IDIOMA_SELECT2 });
        capturas.filter((captura) => $id(`ins_item_${captura.id}`)).forEach((captura) => {
            captura.campos.forEach(([campo, valor, invalido]) => {
                $(`#${campo}`).val(valor).trigger('change.select2');
                $id(campo).classList.toggle('is-invalid', invalido);
            });
            $id(`ins_item_${captura.id}`).closest('.tx-select-ancho').classList.toggle('is-invalid', captura.envoltura);
            $id(`ins_error_item_${captura.id}`).textContent = captura.error;
            mostrar($id(`ins_error_item_${captura.id}`), captura.errorVisible);
        });

        if (foco.startsWith('ins_')) $id(foco)?.focus();

        const unidades = {};

        lineas.forEach((linea) => linea.insumos.forEach((insumo) => {
            unidades[insumo.uMedida] = (unidades[insumo.uMedida] || 0) + cantidadInsumo(linea, insumo);
        }));
        poner('total_insumos', cant(unidades.KG || 0));
        $id('total_insumos').classList.toggle('tx-total--inactivo', !hay || !unidades.KG);
        poner('total_insumos_otras', Object.entries(unidades).filter(([um]) => um !== 'KG').map(([um, valor]) => `${cant(valor)} ${um}`).join(' · '));
        mostrar($id('total_insumos_otras'), Object.keys(unidades).some((um) => um !== 'KG'));
    }
    mostrar($id('contenedor_lineas'), hay);
    mostrar($id('estado_sin_lineas'), !hay);
    $id('btn_liquidar').disabled = !hay || estado.liquidando;

    $id('total_lineas').textContent = fmt(lineas.length);
    $id('total_trabajadores').textContent = fmt(trabajadoresDe().length);
    $id('total_cantidad').textContent = cant(lineas.reduce((suma, linea) => suma + linea.cantidad, 0));
    poner('total_jornales', cant(lineas.reduce((suma, linea) => suma + linea.jornales, 0)));
    $id('total_valor').textContent = fmt(valor);
    ['total_lineas', 'total_trabajadores', 'total_cantidad'].forEach((id) => $id(id).classList.toggle('tx-total--inactivo', !hay));

    const total = $id('total_valor');

    total.classList.remove('tx-total--inactivo', 'tx-total--pendiente', 'tx-total--cuadrado');
    total.classList.add(!hay ? 'tx-total--inactivo' : (sinLiquidar > 0 ? 'tx-total--pendiente' : 'tx-total--cuadrado'));
    mostrar($id('total_valor_nota'), hay && sinLiquidar > 0);

    $id('res_lineas').textContent = fmt(lineas.length);
    $id('res_valor').textContent = fmt(valor);
    pintarPlaneadas();
};

const habilitarTipo = (activo) => {
    const bloque = $id('bloque_transaccion');

    estado.tipo = activo;
    bloque.toggleAttribute('inert', !activo);
    bloque.classList.toggle('tx-inactivo', !activo);
    bloque.classList.toggle('gp-revelar', activo);
    mostrar($id('estado_sin_tipo'), !activo);
    $id('btn_guardar').disabled = !activo;

    if (!estado.edicion) {
        $id('chip_estado').className = 'gp-chip-fila tx-chip-neutro';
        $id('chip_estado').textContent = activo ? 'Sin guardar — el número se asigna al guardar' : 'Seleccione el tipo de transacción';
    }

    if (activo) setTimeout(() => $id('anio').focus(), 50);
};

const reiniciarSelect = (id, mensaje) => {
    $id(id).innerHTML = `<option value="">${mensaje}</option>`;
    $id(id).disabled = true;
    $(`#${id}`).trigger('change.select2');
};

const cargarSecciones = async (finca) => {
    reiniciarSelect('cap_seccion', finca ? 'Cargando…' : 'Seleccione una finca primero');

    if (!finca) return;

    try {
        const filas = (await pedir('secciones', { empresa: empresaId(), finca })).filas || [];

        if ($id('cap_finca').value !== finca) return;

        $id('cap_seccion').innerHTML = `<option value="">${filas.length ? 'Todas las secciones' : 'La finca no tiene secciones'}</option>`
            + filas.map((fila) => `<option value="${escaparHtml(texto(fila.codigo))}">${escaparHtml(texto(fila.codigo))} — ${escaparHtml(texto(fila.descripcion))}</option>`).join('');
        $id('cap_seccion').disabled = filas.length === 0;
        $('#cap_seccion').trigger('change.select2');
    } catch (error) {
        avisar(error);
        reiniciarSelect('cap_seccion', 'Seleccione una finca primero');
    }
};

const cargarLotes = async (finca, seccion) => {
    const turno = ++turnoLotes;
    const referencia = CFG.referencia ? $id('referencia').value : '';
    const falta = !finca ? 'Seleccione una finca primero' : (CFG.referencia && !referencia ? 'Seleccione una referencia primero' : '');

    reiniciarSelect('cap_lote', falta || 'Cargando…');
    revisarPalmas();

    if (falta) return;

    try {
        const filas = (await pedir('lotes', { empresa: empresaId(), finca, seccion, ...(CFG.referencia ? { referencia } : {}) })).filas || [];

        if (turno !== turnoLotes) return;

        $id('cap_lote').innerHTML = `<option value="">${filas.length ? 'Seleccione el lote…' : 'Sin lotes disponibles'}</option>`
            + filas.map((fila) => {
                estado.insumosLote[texto(fila.codigo)] = (fila.insumos || []).map(insumoDe);

                const nombre = `${escaparHtml(texto(fila.codigo))} — ${escaparHtml(texto(fila.descripcion))}`;
                const palmas = CFG.referencia ? Number(fila.palmas || 0) : null;

                return palmas === null
                    ? `<option value="${escaparHtml(texto(fila.codigo))}">${nombre}</option>`
                    : `<option value="${escaparHtml(texto(fila.codigo))}" data-palmas="${palmas}" data-nombre="${nombre}">${nombre} · ${fmt(palmas)} palmas</option>`;
            }).join('');
        $id('cap_lote').disabled = filas.length === 0;
        $('#cap_lote').trigger('change.select2');
    } catch (error) {
        if (turno === turnoLotes) {
            avisar(error);
            reiniciarSelect('cap_lote', 'Seleccione una finca primero');
        }
    }
};

const revisarPalmas = () => {
    const ayuda = $id('ayuda_cantidad');

    if (!ayuda) return;

    const palmas = $id('cap_lote').selectedOptions[0]?.dataset.palmas;
    const cantidad = aNumero($id('cap_cantidad').value);
    const excede = palmas !== undefined && cantidad > Number(palmas);

    mostrar(ayuda, palmas !== undefined);
    ayuda.classList.toggle('tx-texto-ambar', excede);
    ayuda.innerHTML = excede
        ? `<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Supera las palmas del plan (${fmt(palmas)})`
        : `Palmas del plan: <span class="tx-num fw-semibold">${fmt(palmas)}</span>`;
};

const cargarPlanes = async (finca, valor = '', estricto = false) => {
    const selector = $id('referencia');

    reiniciarSelect('referencia', finca ? 'Cargando planes…' : 'Seleccione una finca primero');
    selector.dataset.previo = '';
    mostrar($id('ayuda_sin_planes'), false);
    pintarPlaneadas();

    if (!finca) return;

    let filas = [];

    try {
        filas = (await pedir('planes', { empresa: empresaId(), finca, fecha: $id('fecha').value })).filas || [];
    } catch (error) {
        avisar(error);
    }

    if ($id('cap_finca').value !== finca) return;

    selector.innerHTML = `<option value="">${filas.length ? 'Seleccione la referencia…' : 'Sin planes de fertilización'}</option>` + filas.map((fila) => {
        const numero = escaparHtml(texto(fila.numero));
        const rango = `${fechaCorta(fila.fecha)} – ${fechaCorta(fila.fechaFinal)}${texto(fila.observacion) ? ` · ${texto(fila.observacion)}` : ''}`;

        return `<option value="${numero}" data-rango="${escaparHtml(rango)}">${numero} · ${escaparHtml(rango)}</option>`;
    }).join('');

    const vencida = Boolean(valor) && !filas.some((fila) => texto(fila.numero) === valor);

    if (vencida && !estricto) selector.add(new Option(valor, valor));

    selector.disabled = selector.options.length <= 1;
    selector.value = vencida && estricto ? '' : valor;
    selector.dataset.previo = selector.value;
    mostrar($id('ayuda_sin_planes'), filas.length === 0);
    $('#referencia').trigger('change.select2');
    pintarPlaneadas();

    if (vencida && estricto) {
        errorCampo('error_referencia', true);
        cargarLotes(finca, '');
    }
};

const recargarPlanes = () => {
    if (CFG.referencia && !EXT.activo?.() && $id('cap_finca').value) cargarPlanes($id('cap_finca').value, $id('referencia').value, true);
};

const confirmarCambio = async (id, titulo, accion) => {
    const selector = $id(id);

    if (selector.value === (selector.dataset.previo || '')) return;

    if (estado.lineas.length > 0 && (selector.dataset.previo || planeadas().length === 0)) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: titulo,
            html: `Se quitarán las ${fmt(estado.lineas.length)} líneas cargadas.`,
            showCancelButton: true,
            confirmButtonText: 'Cambiar y quitar líneas',
            confirmButtonColor: '#d6293e',
            cancelButtonText: 'Conservar',
            focusCancel: true
        });

        if (!respuesta.isConfirmed) {
            $(selector).val(selector.dataset.previo || '').trigger('change.select2');

            return;
        }

        estado.lineas = [];
        pintarLineas();
    }

    selector.dataset.previo = selector.value;
    accion();
};

const buscarCodigo = () => {
    const campo = $id('cap_codigo');
    const codigo = texto(campo.value).toUpperCase();

    if (codigo === '' || codigo === texto($id('cap_labor').value).toUpperCase()) return;

    const opcion = Array.from($id('cap_labor').options).find((item) => item.value !== '' && texto(item.value).toUpperCase() === codigo);

    campo.classList.toggle('is-invalid', !opcion);
    errorCampo('error_codigo', !opcion);

    if (opcion) $('#cap_labor').val(opcion.value).trigger('change');
};

const plantillaOpcion = (opcion) => {
    const datos = opcion.element?.dataset || {};

    if (datos.palmas !== undefined) {
        return $(`<span class="d-flex gap-2"><span>${escaparHtml(datos.nombre)}</span><span class="gp-subtexto ms-auto tx-num">${fmt(datos.palmas)} palmas</span></span>`);
    }

    if (datos.rango !== undefined) {
        return $(`<div><div class="font-monospace fw-semibold">${escaparHtml(opcion.id)}</div><div class="gp-subtexto">${escaparHtml(datos.rango)}</div></div>`);
    }

    return opcion.text;
};

const contarTerceros = () => {
    const total = $('#cap_terceros').select2('data').length;
    const chip = $id('cap_terceros_contador');

    chip.textContent = `${total} ${total === 1 ? 'seleccionado' : 'seleccionados'}`;
    mostrar(chip, total > 0);
};

const CONTROLES = {
    error_labor: 'cap_labor', error_um: 'cap_um', error_fecha_labor: 'cap_fecha', error_finca: 'cap_finca', error_referencia: 'referencia',
    error_lote: 'cap_lote', error_terceros: 'cap_terceros', error_cantidad: 'cap_cantidad', error_jornales: 'cap_jornales'
};

const marcarControl = (id, invalido) => {
    const control = $id(CONTROLES[id]);

    if (control) (control.closest('.tx-select-ancho') || control).classList.toggle('is-invalid', invalido);

    if (id === 'error_fecha_labor' && control?.disabled) {
        $id('fecha').classList.toggle('is-invalid', invalido || ($id('fecha').value !== '' && periodoActual() !== null && !fechaEnPeriodo($id('fecha').value)));
    }
};

const enfocarControl = (id) => {
    const control = $id(CONTROLES[id]);

    if (!control) return;

    if (control.closest('.tx-select-ancho')) $(control).select2('open');
    else (control.disabled && id === 'error_fecha_labor' ? $id('fecha') : control).focus();
};

const errorCampo = (id, visible) => {
    mostrar($id(id), visible);
    marcarControl(id, visible);
};

const insumosDeLote = (lote) => (CFG.referencia ? (estado.insumosLote[lote] || []).filter((insumo) => insumo.dosis > 0).filter((insumo, indice, lista) => lista.findIndex((otro) => otro.item === insumo.item) === indice).map((insumo) => ({ ...insumo })) : []);

const cargarLineas = () => {
    const labor = $id('cap_labor');
    const um = $id('cap_um').value;
    const fecha = CFG.referencia || $id('todas_fechas').checked ? $id('cap_fecha').value : $id('fecha').value;
    const finca = $id('cap_finca').value;
    const lote = $id('cap_lote').value;
    const terceros = $('#cap_terceros').select2('data').filter((opcion) => opcion.id);
    const cantidad = aNumero($id('cap_cantidad').value);
    const jornales = aNumero($id('cap_jornales').value);
    const fechas = estado.fechas.length ? [...estado.fechas] : [fecha];

    if (estado.fechas.length && periodoActual() !== null && estado.fechas.some((item) => !fechaEnPeriodo(item))) {
        Toast.fire({ icon: 'warning', title: 'Quite las fechas fuera del periodo' });

        return;
    }

    poner('error_fecha_labor', 'La fecha debe estar dentro del periodo.');

    const errores = {
        error_labor: labor.value === '',
        error_um: um === '',
        error_fecha_labor: !estado.fechas.length && !fechaEnPeriodo(fecha),
        error_finca: finca === '',
        error_referencia: CFG.referencia && $id('referencia').value === '',
        error_lote: lote === '',
        error_terceros: terceros.length === 0,
        error_cantidad: !(cantidad > 0),
        error_jornales: !(jornales >= 0)
    };

    $id('error_cantidad').textContent = isNaN(cantidad) ? 'Número inválido: sin signo y máximo 2 decimales.' : 'Indique una cantidad mayor que cero.';
    poner('error_jornales', isNaN(jornales) ? 'Número inválido: sin signo y máximo 2 decimales.' : 'Los jornales no pueden ser negativos.');

    if (!errores.error_cantidad && terceros.length > 0 && Math.round(cantidad * 100) < terceros.length) {
        errores.error_cantidad = true;
        $id('error_cantidad').textContent = `La cantidad no alcanza para repartir entre ${terceros.length} trabajadores`;
    }

    Object.entries(errores).forEach(([id, visible]) => errorCampo(id, visible));

    const primero = Object.keys(errores).find((id) => errores[id]);

    if (primero) {
        Toast.fire({ icon: 'warning', title: 'Revise los datos de la línea' });
        enfocarControl(primero);

        return;
    }

    if (estado.lineas.length + fechas.length > MAX_LINEAS || terceros.length > MAX_LINEAS) {
        Toast.fire({ icon: 'warning', title: `Máximo ${MAX_LINEAS} líneas y ${MAX_LINEAS} trabajadores por línea` });

        return;
    }

    const nuevas = fechas.map((fechaLinea) => ({
        id: estado.secuencia++,
        novedad: labor.value,
        novedadNombre: labor.selectedOptions[0].dataset.nombre || '',
        uMedida: um,
        fecha: fechaLinea,
        finca,
        seccion: $id('cap_seccion').value,
        lote,
        cantidad,
        jornales,
        marca: 'tx-flash',
        cargando: true,
        insumos: insumosDeLote(lote),
        trabajadores: terceros.map((tercero) => ({ tercero: texto(tercero.id), terceroNombre: texto(tercero.text), terceroCodigo: codigoTercero(tercero), cantidad: 0, jornales: 0, precio: null, existe: 0 }))
    }));
    const trabajadores = `${terceros.length} ${terceros.length === 1 ? 'trabajador' : 'trabajadores'}`;

    nuevas.forEach(repartirLinea);
    estado.lineas.push(...nuevas);
    $('#cap_terceros').val(null).trigger('change');
    $id('cap_cantidad').value = '';
    $id('cap_jornales').value = '0';
    pintarLineas();
    Toast.fire({ icon: 'success', title: nuevas.length === 1 ? `Línea cargada · ${trabajadores}` : `${nuevas.length} líneas cargadas · ${trabajadores} cada una` });
    $('#cap_terceros').select2('open');
    nuevas.forEach(precioLinea);
};

const envioLinea = (linea) => ({
    novedad: linea.novedad, uMedida: linea.uMedida, fecha: linea.fecha, finca: linea.finca, seccion: linea.seccion,
    lote: linea.lote, cantidad: linea.cantidad, jornales: linea.jornales,
    trabajadores: linea.trabajadores.map((trabajador) => ({ tercero: trabajador.tercero, cantidad: trabajador.cantidad, jornales: trabajador.jornales })),
    ...(CFG.referencia ? { insumos: linea.insumos.map((insumo) => ({ item: insumo.item, uMedida: insumo.uMedida, dosis: insumo.dosis })) } : {})
});

const lineasEnvio = () => JSON.stringify(estado.lineas.map(envioLinea));

const aplicarPrecios = (linea, fila) => (fila.trabajadores || []).forEach((dato) => {
    const trabajador = linea.trabajadores.find((item) => item.tercero === texto(dato.tercero));

    if (!trabajador) return;

    trabajador.precio = Number(dato.precioLabor || 0);
    trabajador.existe = Number(dato.existe) === 1 ? 1 : 0;
});

const pedirPrecios = (lineas) => pedir('liquidar', {
    empresa: empresaId(), anio: $id('anio').value, mes: $id('periodo').value, fecha: $id('fecha').value, lineas: JSON.stringify(lineas.map(envioLinea)),
    ...(CFG.referencia ? { finca: $id('cap_finca').value, referencia: $id('referencia').value } : {})
});

const precioLinea = async (linea) => {
    if ($id('periodo').value !== '') {
        try {
            const resultado = await pedirPrecios([linea]);

            if (estado.lineas.includes(linea)) {
                aplicarPrecios(linea, (resultado.lineas || [])[0] || {});
                linea.marca = 'tx-tarjeta--destacada';
            }
        } catch (error) {
            avisar(error);
        }
    }

    linea.cargando = false;
    pintarLineas();
};

const exigirPeriodo = () => {
    if ($id('periodo').value !== '' && Number(periodoActual()?.cerrado) !== 1) return true;

    $id('error_periodo').textContent = $id('periodo').value === '' ? 'Seleccione el periodo' : 'Elija un periodo abierto';
    $id('periodo').classList.add('is-invalid');
    $id('periodo').focus();

    return false;
};

const liquidar = async () => {
    if (estado.liquidando || estado.lineas.length === 0 || !exigirPeriodo()) return;

    const boton = $id('btn_liquidar');
    const original = boton.innerHTML;
    const lineas = [...estado.lineas];

    estado.liquidando = true;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Liquidando…';

    try {
        const resultado = await pedirPrecios(lineas);

        (resultado.lineas || []).forEach((fila, indice) => {
            const linea = lineas[(Number(fila.registro) || indice + 1) - 1];

            if (!linea || !estado.lineas.includes(linea)) return;

            aplicarPrecios(linea, fila);
            linea.marca = 'tx-recalculo';
        });
        Toast.fire({ icon: 'success', title: 'Líneas liquidadas' });
    } catch (error) {
        Swal.fire({ icon: 'warning', title: 'No se pudo liquidar', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }

    estado.liquidando = false;
    boton.innerHTML = original;
    pintarLineas();
};

const quitarTrabajador = async (idLinea, tercero) => {
    const linea = estado.lineas.find((item) => item.id === idLinea);
    const quitado = linea?.trabajadores.find((trabajador) => trabajador.tercero === tercero);

    if (!quitado) return;

    if (linea.trabajadores.length === 1) {
        estado.lineas = estado.lineas.filter((item) => item !== linea);
        pintarLineas();

        return;
    }

    const respuesta = await Swal.fire({
        icon: 'question',
        title: `¿Quitar a ${escaparHtml(quitado.terceroNombre || quitado.tercero)}?`,
        html: `Tiene registrados <b>${escaparHtml(cant(quitado.cantidad))} ${escaparHtml(linea.uMedida)}</b>.<br><small class="text-muted"><b>Restar del total</b> baja la cantidad de la línea · <b>Repartir entre los demás</b> mantiene el total y lo distribuye entre los restantes.</small>`,
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: 'Restar del total',
        confirmButtonColor: '#d6293e',
        denyButtonText: 'Repartir entre los demás',
        denyButtonColor: '#0d6efd',
        cancelButtonText: 'Cancelar',
        focusCancel: true
    });

    if ((!respuesta.isConfirmed && !respuesta.isDenied) || !estado.lineas.includes(linea) || !linea.trabajadores.includes(quitado) || linea.cargando) return;

    linea.trabajadores = linea.trabajadores.filter((trabajador) => trabajador !== quitado);

    if (respuesta.isConfirmed) {
        linea.cantidad = Math.round(linea.trabajadores.reduce((suma, item) => suma + item.cantidad, 0) * 100) / 100;
        linea.jornales = Math.round(linea.trabajadores.reduce((suma, item) => suma + item.jornales, 0) * 100) / 100;
        linea.insumos.forEach((insumo) => { insumo.cantidad = undefined; });
        linea.manual = true;
    } else if (linea.manual) {
        const cantidades = repartir(quitado.cantidad, linea.trabajadores.length);
        const jornales = repartir(quitado.jornales, linea.trabajadores.length);

        linea.trabajadores.forEach((trabajador, indice) => {
            trabajador.cantidad = Math.round((trabajador.cantidad + cantidades[indice]) * 100) / 100;
            trabajador.jornales = Math.round((trabajador.jornales + jornales[indice]) * 100) / 100;
        });
    } else {
        repartirLinea(linea);
    }

    linea.marca = 'tx-recalculo';
    pintarLineas();
    Toast.fire({ icon: 'success', title: respuesta.isConfirmed ? 'Trabajador quitado · total restado' : 'Trabajador quitado · cantidad repartida' });
};

const cuerpoBase = () => (EXT.activo?.() ? EXT.cuerpo() : {
    empresa: empresaId(),
    tipo: $id('tipo').value,
    anio: $id('anio').value,
    mes: $id('periodo').value,
    fecha: $id('fecha').value,
    ...(CFG.referencia
        ? { finca: $id('cap_finca').value, referencia: $id('referencia').value }
        : { todasFechas: $id('todas_fechas').checked ? 1 : 0, remision: texto($id('remision').value) }),
    observacion: texto($id('observacion').value),
    lineas: lineasEnvio()
});

const hayCambios = () => Boolean($id('zona_formulario')) && (estado.edicion
    ? JSON.stringify(cuerpoBase()) !== estado.foto
    : (EXT.activo?.() ? EXT.hayDetalle() : estado.lineas.length > 0) || texto($id('remision')?.value) !== '' || texto($id('observacion').value) !== '');

const contarObservacion = () => {
    $id('contador_observacion').textContent = `${fmt($id('observacion').value.length)} / 2550`;
};

const modoEdicion = (numero) => {
    const guardar = $id('btn_guardar');
    const cancelar = $id('btn_cancelar');
    const campoNumero = $id('numero');

    estado.edicion = numero;
    guardar.innerHTML = `<i class="bi bi-floppy me-1" aria-hidden="true"></i>${numero ? 'Guardar cambios' : 'Guardar'}`;
    guardar.title = numero ? 'Guardar los cambios de la transacción (Ctrl+S)' : 'Guardar la transacción (Ctrl+S)';
    cancelar.title = numero ? 'Cancelar la edición' : 'Cancelar la transacción';
    campoNumero.value = numero || 'Se asigna al guardar';
    campoNumero.classList.toggle('tx-neto', !numero);
    campoNumero.classList.toggle('fw-semibold', Boolean(numero));
    $('#tipo').prop('disabled', Boolean(numero)).trigger('change.select2');

    if ($id('tab_registro')) {
        $id('tab_registro').innerHTML = numero
            ? '<i class="bi bi-pencil" aria-hidden="true"></i>Edición'
            : '<i class="bi bi-pencil-square" aria-hidden="true"></i>Registro';
    }

    if (!PERMISOS.registrar && $id('pestanas')) {
        ['pestanas', 'item_registro'].forEach((id) => $id(id).classList.toggle('d-none', !numero));

        if (!numero) bootstrap.Tab.getOrCreateInstance($id('tab_consulta')).show();
    }

    if (!numero) {
        estado.foto = null;

        return;
    }

    $id('chip_estado').className = 'gp-chip-fila ambar';
    $id('chip_estado').innerHTML = `<i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editando <span class="font-monospace">${escaparHtml(numero)}</span>`;
};

const limpiarTodo = () => {
    modoEdicion(null);
    estado.lineas = [];
    $('#tipo').val('').trigger('change.select2');
    habilitarTipo(false);
    $id('fecha').value = hoyIso();
    if (!CFG.referencia) {
        $id('todas_fechas').checked = false;
        $id('remision').value = '';
    }

    $id('observacion').value = '';
    contarObservacion();
    $('#cap_labor, #cap_finca').val('').trigger('change.select2');
    $id('cap_um').value = '';
    reiniciarSelect('cap_seccion', 'Seleccione una finca primero');
    reiniciarSelect('cap_lote', 'Seleccione una finca primero');
    $id('cap_finca').dataset.previo = '';

    if ($id('cap_codigo')) {
        $id('cap_codigo').value = '';
        $id('cap_codigo').classList.remove('is-invalid');
    }

    if (CFG.referencia) {
        cargarPlanes('');
        revisarPalmas();
    }

    $('#cap_terceros').val(null).trigger('change');
    $id('cap_cantidad').value = '';
    $id('cap_jornales').value = '0';
    ['error_labor', 'error_um', 'error_fecha_labor', 'error_finca', 'error_lote', 'error_terceros', 'error_cantidad', 'error_jornales', 'error_referencia', 'error_codigo']
        .forEach((id) => errorCampo(id, false));
    sincronizarFechas();
    pintarLineas();
    $id('anio').value = $id('periodo').dataset.anio;
    cargarPeriodos($id('anio').value);
    EXT.limpiar?.();
    quitarPlaneacion();
};

const quitarPlaneacion = () => {
    const url = new URL(window.location.href);

    if (!url.searchParams.has('planeacion')) return;

    url.searchParams.delete('planeacion');
    history.replaceState(null, '', url.toString());
};

const precargarPlaneacion = async () => {
    const parametros = new URLSearchParams(window.location.search);
    const linea = parametros.get('planeacion');
    const fallar = (titulo, mensaje) => Swal.fire({ icon: 'warning', title: titulo, html: escaparHtml(mensaje), confirmButtonText: 'Entendido' });

    if (!/^\d+$/.test(texto(linea)) || Number(linea) <= 0) return;

    let datos;

    try {
        datos = await pedir('../planeacion/precarga', { linea });
    } catch (error) {
        fallar('No se pudo precargar la actividad', error.message);

        return;
    }

    if (texto(datos.destino) !== CFG.ruta) {
        fallar('Formulario incorrecto', `La actividad se registra en ${{ fertilizacion: 'Fertilización', produccion: 'Producción' }[texto(datos.destino)] || 'Registro de labores'}.`);

        return;
    }

    const empresa = texto(datos.empresa);

    if (empresa !== empresaId()) {
        if (parametros.get('empresa') === empresa) {
            fallar('Empresa no disponible', 'No tiene acceso a la empresa de la actividad planeada.');

            return;
        }

        const url = new URL(window.location.href);

        url.searchParams.set('empresa', empresa);
        estado.saliendo = true;
        window.location.href = url.toString();

        return;
    }

    const opcionTipo = Array.from($id('tipo').options).find((opcion) => opcion.value !== '' && texto(opcion.value) === texto(datos.tipo));

    if (!opcionTipo) {
        fallar('Tipo no disponible', `El tipo de transacción ${texto(datos.tipo)} no está habilitado para esta empresa.`);

        return;
    }

    const fecha = texto(datos.fecha).substring(0, 10);
    const anio = fecha.substring(0, 4);
    const anios = Array.from($id('anio').options);
    const finca = texto(datos.finca);

    $('#tipo').val(opcionTipo.value).trigger('change');

    if (!anios.some((opcion) => opcion.value === anio)) {
        $id('anio').add(new Option(anio, anio), anios.find((opcion) => Number(opcion.value) < Number(anio)) || null);
    }

    $id('anio').value = anio;
    $id('fecha').value = fecha;
    await cargarPeriodos(anio);

    const periodo = estado.periodos.find((item) => {
        const inicio = texto(item.fechaInicial).substring(0, 10);
        const fin = texto(item.fechaFinal).substring(0, 10);

        return Number(item.cerrado) !== 1 && (inicio && fin ? fecha >= inicio && fecha <= fin : Number(item.mes) === Number(fecha.substring(5, 7)));
    });

    if (periodo) $id('periodo').value = String(Number(periodo.mes));

    $id('fecha').value = fecha;
    sincronizarFechas();
    revisarPeriodo();

    if (CFG.referencia) {
        $('#cap_finca').val(finca).trigger('change.select2');
        $id('cap_finca').dataset.previo = finca;
        cargarSecciones(finca);
        await cargarPlanes(finca, texto(datos.referencia), true);

        if ($id('referencia').value) await cargarLotes(finca, '');
    }

    agregarPlaneaciones([{ ...datos, linea: Number(linea) }]);
};

const ESTADOS_PLA = { COMPLETADA: 'Completada', PARCIAL: 'Parcial' };
const pla = { filas: [], cargando: false, error: '', seleccion: new Set(), turno: 0 };

const planeadas = () => estado.lineas.filter((linea) => linea.planeacion);
const plaAgregada = (id) => estado.lineas.some((linea) => linea.planeacion?.id === id);
const cantPla = (valor, um) => Number(valor || 0).toLocaleString('es-CO', { maximumFractionDigits: /RAC/i.test(um) ? 0 : 3 });
const sinTildes = (valor) => texto(valor).normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
const pluralPla = (cantidad, uno, varios) => `${fmt(cantidad)} ${cantidad === 1 ? uno : varios}`;

const chipPlaneacion = (planeacion) => `<span class="gp-chip-ambito seccion text-nowrap" title="Planeación del ${fechaCorta(planeacion.fecha)}${ESTADOS_PLA[planeacion.estado] ? ` · ${ESTADOS_PLA[planeacion.estado]}` : ''}"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Planeación</span>`;

const lineaPlaneada = (dato) => {
    const lote = texto(dato.lote);
    const fecha = texto(dato.fecha).substring(0, 10);

    return {
        id: estado.secuencia++,
        novedad: texto(dato.novedad),
        novedadNombre: texto(dato.novedadNombre),
        uMedida: texto(dato.uMedida),
        fecha,
        finca: texto(dato.finca),
        seccion: texto(dato.seccion),
        lote,
        cantidad: Number(dato.cantidad || 0),
        jornales: 0,
        manual: true,
        marca: 'tx-flash',
        cargando: true,
        insumos: insumosDeLote(lote),
        planeacion: { id: Number(dato.linea), fecha, estado: texto(dato.estado).toUpperCase() },
        trabajadores: (dato.trabajadores || []).map((trabajador) => ({
            tercero: texto(trabajador.tercero), terceroNombre: texto(trabajador.nombre), terceroCodigo: texto(trabajador.codigo),
            cantidad: Number(trabajador.cantidad || 0), jornales: 0, precio: null, existe: 0
        }))
    };
};

const agregarPlaneaciones = (lista) => {
    const nuevas = lista.map(lineaPlaneada);

    estado.lineas.push(...nuevas);
    pintarLineas();
    nuevas.forEach(precioLinea);

    return nuevas;
};

const pintarPlaneadas = () => {
    const total = planeadas().length;
    const boton = $id('btn_planeaciones');

    poner('nota_planeacion_texto', `${pluralPla(total, 'actividad', 'actividades')} de la planeación en el detalle. Revise los valores y guarde.`);
    mostrar($id('nota_planeacion'), total > 0);

    if (!boton) return;

    const disponibles = pla.filas.filter((fila) => !plaAgregada(Number(fila.linea))).length;
    const bloqueado = CFG.referencia && (!$id('cap_finca').value || !$id('referencia').value);

    poner('cont_planeaciones', fmt(disponibles));
    $id('cont_planeaciones').classList.toggle('verde', disponibles > 0);
    boton.disabled = bloqueado;
    boton.title = bloqueado ? 'Seleccione la finca y la referencia primero' : 'Planeaciones completadas';
};

const motivoPla = (fila) => {
    const fecha = texto(fila.fecha).substring(0, 10);
    const referencia = texto(fila.referencia);

    if (!fechaEnPeriodo(fecha)) return 'Fuera del periodo';

    if (CFG.referencia && referencia && referencia !== $id('referencia').value) return `Otra referencia (${referencia})`;

    if (!CFG.referencia && !$id('todas_fechas').checked && fecha !== $id('fecha').value) return 'Fecha distinta; active Todas las fechas';

    return '';
};

const defectoPla = () => {
    const [inicio, fin] = rangoPeriodo();
    const fecha = $id('fecha').value;
    const periodo = (CFG.referencia || $id('todas_fechas').checked) && inicio && fin;

    return { pla_desde: periodo ? inicio : fecha, pla_hasta: periodo ? fin : fecha, pla_finca: CFG.referencia ? texto($id('cap_finca').value) : '', pla_buscar: '' };
};

const filtrosAplicados = () => Object.entries(defectoPla()).some(([id, valor]) => texto($id(id).value) !== valor);

const limpiarFiltrosPla = () => {
    Object.entries(defectoPla()).forEach(([id, valor]) => { $id(id).value = valor; });
    pintarPla();
};

const seleccionablePla = (fila) => !plaAgregada(Number(fila.linea)) && motivoPla(fila) === '';

const visiblesPla = () => {
    const desde = $id('pla_desde').value;
    const hasta = $id('pla_hasta').value;
    const finca = $id('pla_finca').value;
    const termino = sinTildes($id('pla_buscar').value);

    return pla.filas.filter((fila) => {
        const fecha = texto(fila.fecha).substring(0, 10);
        const busqueda = sinTildes([fila.lote, fila.loteNombre, fila.novedad, fila.novedadNombre, ...(fila.trabajadores || []).flatMap((trabajador) => [trabajador.codigo, trabajador.nombre])].join(' '));

        return (!desde || fecha >= desde) && (!hasta || fecha <= hasta) && (!finca || texto(fila.finca) === finca) && (!termino || busqueda.includes(termino));
    }).sort((a, b) => texto(b.fecha).localeCompare(texto(a.fecha)) || texto(a.finca).localeCompare(texto(b.finca)) || texto(a.lote).localeCompare(texto(b.lote)));
};

const filaPla = (fila) => {
    const id = Number(fila.linea);
    const agregada = plaAgregada(id);
    const motivo = agregada ? '' : motivoPla(fila);
    const libre = !agregada && motivo === '';
    const marcada = agregada || pla.seleccion.has(id);
    const trabajadores = fila.trabajadores || [];
    const codigos = escaparHtml(trabajadores.map((trabajador) => texto(trabajador.codigo) || texto(trabajador.tercero)).join(', '));
    const um = texto(fila.uMedida);
    const nombre = escaparHtml(texto(fila.novedadNombre));
    const completada = texto(fila.estado).toUpperCase() === 'COMPLETADA';

    return `<tr data-pla="${id}" class="${marcada ? 'table-active' : ''}${libre ? '' : ' text-muted'}"${libre ? ' style="cursor:pointer"' : ''}>
        <td class="text-center"><input class="form-check-input" type="checkbox" data-pla-check="${id}" aria-label="Seleccionar ${escaparHtml(texto(fila.novedad))} del lote ${escaparHtml(texto(fila.lote))} del ${fechaCorta(fila.fecha)}"${marcada ? ' checked' : ''}${libre ? '' : ' disabled'}></td>
        <td class="text-nowrap">${fechaCorta(fila.fecha)}</td>
        <td class="font-monospace text-nowrap">${escaparHtml(texto(fila.finca))} / ${escaparHtml(texto(fila.lote))}</td>
        <td><span class="font-monospace fw-semibold">${escaparHtml(texto(fila.novedad))}</span> <span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span></td>
        <td class="text-end tx-num text-nowrap">${cantPla(fila.cantidad, um)} ${escaparHtml(um)}</td>
        <td><div class="d-flex flex-wrap gap-1"><span class="gp-chip-fila ${completada ? 'verde' : 'ambar'}">${completada ? 'Completada' : 'Parcial'}</span>${agregada ? '<span class="gp-chip-fila tx-chip-neutro">Agregada</span>' : ''}</div>${motivo ? `<span class="gp-subtexto mt-1">${escaparHtml(motivo)}</span>` : ''}</td>
        <td><span class="tx-chip-cont${trabajadores.length ? ' verde' : ''}" title="${codigos}">${fmt(trabajadores.length)}</span><span class="gp-subtexto gp-truncar font-monospace mt-1" title="${codigos}">${codigos}</span></td>
    </tr>`;
};

const estadoPla = (icono, titulo, detalle, boton = '', chico = false) => `<div class="gp-estado-vacio text-center py-5">
    <div class="gp-medallon${chico ? ' chico' : ''} d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi ${icono}" aria-hidden="true"></i></div>
    <h6 class="mb-1">${titulo}</h6>${detalle ? `<p class="text-muted mb-0">${detalle}</p>` : ''}${boton ? `<div class="mt-3">${boton}</div>` : ''}
</div>`;

const seleccionPla = () => {
    const total = pla.seleccion.size;
    const todas = $id('pla_todas');
    const libres = Array.from(document.querySelectorAll('#pla_cuerpo [data-pla-check]:not(:disabled)'));

    $id('pla_seleccion').innerHTML = `<strong>${fmt(total)}</strong> ${total === 1 ? 'seleccionada' : 'seleccionadas'}`;
    $id('pla_agregar').disabled = total === 0;
    libres.forEach((check) => {
        check.checked = pla.seleccion.has(Number(check.dataset.plaCheck));
        check.closest('tr').classList.toggle('table-active', check.checked);
    });

    if (!todas) return;

    const marcadas = libres.filter((check) => check.checked).length;

    todas.disabled = libres.length === 0;
    todas.checked = libres.length > 0 && marcadas === libres.length;
    todas.indeterminate = marcadas > 0 && marcadas < libres.length;
};

const pintarPla = () => {
    const cuerpo = $id('pla_cuerpo');
    const visibles = visiblesPla();
    const desde = $id('pla_desde').value;
    const hasta = $id('pla_hasta').value;
    const limpiar = '<button type="button" class="btn btn-outline-secondary btn-sm" data-pla-limpiar><i class="bi bi-eraser me-1" aria-hidden="true"></i>Limpiar filtros</button>';

    poner('pla_rango', desde && hasta && desde !== hasta ? `${fechaCorta(desde)} – ${fechaCorta(hasta)}` : (desde || hasta ? fechaCorta(desde || hasta) : 'Todas las fechas'));
    mostrar($id('pla_limpiar_col'), !pla.cargando && filtrosAplicados());
    ['pla_desde', 'pla_hasta', 'pla_buscar'].forEach((id) => { $id(id).disabled = pla.cargando; });
    $id('pla_finca').disabled = pla.cargando || CFG.referencia;
    pla.seleccion = new Set([...pla.seleccion].filter((id) => visibles.some((fila) => Number(fila.linea) === id && seleccionablePla(fila))));
    cuerpo.setAttribute('aria-busy', String(pla.cargando));

    if (pla.cargando) {
        cuerpo.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Consultando planeaciones…</span></div></div>';
    } else if (pla.error) {
        cuerpo.innerHTML = estadoPla('bi-wifi-off', 'No se pudieron consultar las planeaciones', escaparHtml(pla.error), '<button type="button" class="btn btn-outline-secondary btn-sm" data-pla-reintentar><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>', true);
    } else if (pla.filas.length === 0) {
        cuerpo.innerHTML = estadoPla('bi-calendar-x', 'No hay planeaciones completadas por registrar', 'Las actividades cerradas como Completada o Parcial en la planeación aparecen aquí hasta que se registren.');
    } else if (visibles.length === 0) {
        cuerpo.innerHTML = estadoPla('bi-funnel', 'Ninguna planeación coincide con el filtro', '', limpiar);
    } else {
        cuerpo.innerHTML = `<div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 gp-tabla">
                <thead class="gp-thead">
                    <tr>
                        <th class="text-center" style="width:44px"><input class="form-check-input" type="checkbox" id="pla_todas" aria-label="Seleccionar todas"></th>
                        <th style="width:100px">Fecha</th>
                        <th style="width:130px">Finca / Lote</th>
                        <th style="min-width:220px">Labor</th>
                        <th class="text-end" style="width:120px">Ejecutado</th>
                        <th style="width:190px">Estado</th>
                        <th style="width:170px">Trabajadores</th>
                    </tr>
                </thead>
                <tbody>${visibles.map(filaPla).join('')}</tbody>
            </table>
        </div>`;
    }

    seleccionPla();
};

const cargarPla = async () => {
    if (!$id('btn_planeaciones')) return;

    const turno = ++pla.turno;

    pla.cargando = true;
    pla.error = '';
    pintarPla();

    try {
        const resultado = await pedir('planeaciones', { empresa: empresaId() });

        if (turno !== pla.turno) return;

        pla.filas = resultado.planeaciones || [];
    } catch (error) {
        if (turno !== pla.turno) return;

        pla.error = error.message;
    }

    pla.cargando = false;
    pintarPla();
    pintarPlaneadas();
};

const abrirPla = () => {
    if ($id('btn_planeaciones').disabled) return;

    pla.seleccion = new Set();
    Object.entries(defectoPla()).forEach(([id, valor]) => { $id(id).value = valor; });
    bootstrap.Modal.getOrCreateInstance($id('modalPlaneaciones')).show();
    cargarPla();
};

const agregarPla = () => {
    const lista = pla.filas.filter((fila) => pla.seleccion.has(Number(fila.linea)) && seleccionablePla(fila));

    if (lista.length === 0) return;

    if (estado.lineas.length + lista.length > MAX_LINEAS || planeadas().length + lista.length > 50) {
        Toast.fire({ icon: 'warning', title: planeadas().length + lista.length > 50 ? 'Máximo 50 actividades planeadas por transacción' : `Máximo ${MAX_LINEAS} líneas por transacción` });

        return;
    }

    pla.seleccion = new Set();
    bootstrap.Modal.getOrCreateInstance($id('modalPlaneaciones')).hide();
    agregarPlaneaciones(lista);
    Toast.fire({ icon: 'success', title: `${pluralPla(lista.length, 'actividad agregada', 'actividades agregadas')} al detalle` });
};

const alertaBloqueo = (fila, accion) => {
    const numero = escaparHtml(texto(fila.numero));

    if (Number(fila.anulado) === 1) {
        Swal.fire({ icon: 'info', title: 'Transacción anulada', html: 'No se puede modificar una transacción anulada.', confirmButtonText: 'Entendido' });

        return true;
    }

    if (Number(fila.cerrado) === 1) {
        const periodo = `${texto(fila.anio)}-${String(Number(fila.mes)).padStart(2, '0')}`;

        Swal.fire({
            icon: 'warning',
            title: 'Periodo cerrado',
            html: `El periodo <b class='font-monospace'>${escaparHtml(periodo)}</b> está cerrado. La transacción ${numero} no se puede ${accion}.`,
            confirmButtonText: 'Entendido'
        });

        return true;
    }

    if (accion === 'eliminar' && Number(fila.bloqueada) === 1) {
        Swal.fire({ icon: 'warning', title: 'Transacción liquidada', html: `Ya fue liquidada en nómina; no se puede eliminar.${Number(fila.habilitada) === 1 ? ' La habilitación solo permite editarla.' : ''}`, confirmButtonText: 'Entendido' });

        return true;
    }

    if (Number(fila.bloqueada) === 1 && Number(fila.habilitada) !== 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Transacción liquidada',
            html: `La transacción <b class="font-monospace">${numero}</b> ya fue liquidada en nómina y no se puede editar.<br>${PERMISOS.habilitar ? 'Puede habilitarla con el botón <i class="bi bi-unlock"></i> de la consulta.' : 'Solicite a su superior que la habilite para edición.'}`,
            confirmButtonText: 'Entendido'
        });

        return true;
    }

    return false;
};

const cargarEdicion = async (numero, tipo = '') => {
    if (!PERMISOS.editar || !$id('zona_formulario')) return;

    if (estado.edicion === numero && (!tipo || $id('tipo').value === tipo)) {
        bootstrap.Tab.getOrCreateInstance($id('tab_registro')).show();

        return;
    }

    if (hayCambios()) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: '¿Descartar los datos actuales?',
            html: `Se abrirá la transacción ${escaparHtml(numero)} y se perderán los datos sin guardar.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        });

        if (!respuesta.isConfirmed) return;
    }

    let resultado;

    try {
        resultado = await pedir('detalle', { empresa: empresaId(), numero, ...(CFG.referencia && tipo ? { tipo } : {}) });
    } catch (error) {
        Swal.fire({ icon: 'warning', title: error.status === 403 ? 'Acción no permitida' : 'No se puede editar la transacción', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });

        return;
    }

    const encabezado = resultado.encabezado || {};

    if (alertaBloqueo({ ...encabezado, numero }, 'editar')) return;

    const vence = texto(encabezado.habilitadaVence);
    const avisoLiquidada = () => Number(encabezado.habilitada) === 1 && Toast.fire({ icon: 'info', title: 'Edición habilitada · un solo uso', text: `Al guardar, la transacción quedará bloqueada de nuevo. Vence el ${fechaCorta(vence)} a las ${vence.substring(11, 16)}.`, timer: 8000 });

    limpiarTodo();
    ['pestanas', 'item_registro'].forEach((id) => $id(id)?.classList.remove('d-none'));
    bootstrap.Tab.getOrCreateInstance($id('tab_registro')).show();
    window.scrollTo({ top: 0, behavior: 'smooth' });

    if (EXT.editar && texto(encabezado.tipo) === 'PFA') {
        await EXT.editar(encabezado, resultado, numero);
        estado.foto = JSON.stringify(cuerpoBase());
        avisoLiquidada();

        return;
    }

    const fecha = texto(encabezado.fecha).substring(0, 10);

    $('#tipo').val(texto(encabezado.tipo) || 'TLA').trigger('change.select2');
    modoEdicion(texto(encabezado.numero) || numero);
    habilitarTipo(true);
    $id('fecha').value = fecha;
    $id('observacion').value = texto(encabezado.observacion);

    if (CFG.referencia) {
        const finca = texto(encabezado.finca);

        $('#cap_finca').val(finca).trigger('change.select2');
        $id('cap_finca').dataset.previo = finca;
        cargarSecciones(finca);
        await cargarPlanes(finca, texto(encabezado.referencia));
        cargarLotes(finca, '');
    } else {
        $id('remision').value = texto(encabezado.remision);
    }
    contarObservacion();

    estado.lineas = (resultado.lineas || []).map((linea) => ({
        id: estado.secuencia++,
        novedad: texto(linea.novedad),
        novedadNombre: texto(linea.novedadNombre),
        uMedida: texto(linea.uMedida),
        fecha: texto(linea.fecha).substring(0, 10),
        finca: texto(linea.finca),
        seccion: texto(linea.seccion),
        lote: texto(linea.lote),
        cantidad: Number(linea.cantidad || 0),
        jornales: Number(linea.jornales || 0),
        marca: '',
        insumos: (linea.insumos || []).map(insumoDe),
        trabajadores: (linea.trabajadores || []).map((trabajador) => ({
            tercero: texto(trabajador.tercero),
            terceroNombre: texto(trabajador.terceroNombre),
            terceroCodigo: texto(trabajador.terceroCodigo),
            cantidad: Number(trabajador.cantidad || 0),
            jornales: Number(trabajador.jornales || 0),
            precio: Number(trabajador.precioLabor || 0),
            existe: Number(trabajador.precioLabor || 0) > 0 ? 1 : 0
        }))
    }));
    estado.lineas.forEach((linea) => {
        const cantidades = repartir(linea.cantidad, linea.trabajadores.length);
        const jornales = repartir(linea.jornales, linea.trabajadores.length);

        if (linea.trabajadores.some((trabajador, indice) => trabajador.cantidad !== cantidades[indice] || trabajador.jornales !== jornales[indice])) linea.manual = true;
    });
    if (!CFG.referencia) $id('todas_fechas').checked = estado.lineas.some((linea) => linea.fecha !== fecha);
    sincronizarFechas();

    const anio = texto(encabezado.anio);
    const anios = Array.from($id('anio').options);

    if (anio !== '' && !anios.some((opcion) => opcion.value === anio)) {
        $id('anio').add(new Option(anio, anio), anios.find((opcion) => Number(opcion.value) < Number(anio)) || null);
    }

    $id('anio').value = anio;
    await cargarPeriodos(anio, encabezado.mes);
    sincronizarFechas();
    pintarLineas();
    estado.foto = JSON.stringify(cuerpoBase());
    avisoLiquidada();
};

const guardar = async () => {
    if (!estado.tipo || estado.guardando) return;

    if (!exigirPeriodo()) return;

    if (!fechaEnPeriodo($id('fecha').value)) {
        $id('fecha').classList.add('is-invalid');
        $id('fecha').focus();
        Toast.fire({ icon: 'warning', title: 'La fecha debe estar dentro del periodo' });

        return;
    }

    if (EXT.activo?.() && !EXT.validar()) return;

    if (!EXT.activo?.() && estado.lineas.length === 0) {
        Swal.fire({ icon: 'warning', title: 'La transacción no tiene líneas', html: 'Cargue al menos una línea de detalle antes de guardar.', confirmButtonText: 'Entendido' });

        return;
    }

    const sinPrecio = EXT.activo?.() ? 0 : pendientes();
    const sinTarifas = EXT.activo?.() ? 0 : trabajadoresDe().filter(sinTarifa).length;

    if (sinPrecio > 0 || sinTarifas > 0) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: 'Hay trabajadores sin precio',
            html: [
                sinPrecio > 0 ? `<b>${escaparHtml(fmt(sinPrecio))}</b> ${sinPrecio === 1 ? 'trabajador tiene' : 'trabajadores tienen'} el precio pendiente de liquidar.` : '',
                sinTarifas > 0 ? `<b>${escaparHtml(fmt(sinTarifas))}</b> ${sinTarifas === 1 ? 'trabajador no tiene' : 'trabajadores no tienen'} tarifa para su labor y lote.` : ''
            ].filter(Boolean).join('<br>') + '<br>¿Desea guardar de todos modos?',
            showCancelButton: true,
            confirmButtonText: 'Guardar de todos modos',
            cancelButtonText: 'Revisar',
            focusCancel: true
        });

        if (!respuesta.isConfirmed || estado.guardando) return;
    }

    const boton = $id('btn_guardar');
    const original = boton.innerHTML;
    const bloque = $id('bloque_transaccion');

    estado.guardando = true;
    boton.disabled = true;
    $id('btn_cancelar').disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…';
    bloque.setAttribute('inert', '');

    let resultado = null;
    let fallo = null;

    try {
        const vinculos = planeadas().length && !EXT.activo?.() ? { planeacionLineas: JSON.stringify(planeadas().map((linea) => linea.planeacion.id)) } : {};

        resultado = estado.edicion
            ? await pedir('actualizar', { ...cuerpoBase(), ...vinculos, numero: estado.edicion })
            : await pedir('guardar', { ...cuerpoBase(), ...vinculos });
    } catch (error) {
        fallo = error;
    }

    estado.guardando = false;
    boton.disabled = false;
    $id('btn_cancelar').disabled = false;
    boton.innerHTML = original;
    bloque.removeAttribute('inert');

    if (fallo) {
        EXT.error?.(fallo);

        if (/actividad planeada/i.test(fallo.message)) cargarPla();

        const numeroLinea = CFG.referencia && !EXT.activo?.() && /insumo/i.test(fallo.message) ? fallo.message.match(/l[ií]nea (\d+)/i) : null;
        const lineaError = numeroLinea ? estado.lineas[Number(numeroLinea[1]) - 1] : null;

        if (lineaError) {
            lineaError.errorInsumo = fallo.message;
            lineaError.marca = 'tx-tarjeta--destacada';
            pintarLineas();
        }

        Swal.fire({ icon: fallo.status === 403 ? 'warning' : 'error', title: fallo.status === 403 ? 'Acción no permitida' : 'No se pudo guardar la transacción', html: escaparHtml(fallo.message), confirmButtonText: 'Entendido' });

        return;
    }

    const numero = texto(resultado.numero);

    if (estado.edicion) {
        Toast.fire({ icon: 'success', title: `Transacción ${escaparHtml(numero)} actualizada` });
        limpiarTodo();
        cargarPla();

        if (typeof volverConsulta === 'function' && $id('tab_consulta')) volverConsulta(numero, true);

        return;
    }

    const lineas = estado.lineas.length;
    const resumen = EXT.activo?.() ? EXT.resumen() : `${escaparHtml(fmt(lineas))} ${lineas === 1 ? 'línea' : 'líneas'}`;

    limpiarTodo();
    cargarPla();
    Swal.fire({
        icon: 'success',
        title: 'Transacción registrada',
        html: `Se creó la transacción <b class="font-monospace">${escaparHtml(numero)}</b> con ${resumen}.`,
        confirmButtonText: 'Entendido'
    });
};

const cancelar = async () => {
    if (hayCambios()) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: '¿Cancelar la transacción?',
            html: 'Se perderán los datos sin guardar.',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'Seguir editando',
            focusCancel: true
        });

        if (!respuesta.isConfirmed) return;
    }

    const numero = estado.edicion;

    limpiarTodo();

    if (numero && typeof volverConsulta === 'function' && $id('tab_consulta')) volverConsulta(numero, false);
};

const iniciarSelect2 = () => {
    $('#tipo').select2({ width: '100%', placeholder: 'Seleccione el tipo…', language: IDIOMA_SELECT2 });
    $('#cap_labor, #cap_finca, #cap_seccion, #cap_lote').select2({
        width: '100%', placeholder: 'Seleccione una opción…', allowClear: true, language: IDIOMA_SELECT2, templateResult: plantillaOpcion
    });
    $('#referencia').select2({ width: '100%', language: IDIOMA_SELECT2, templateResult: plantillaOpcion });
    const terceros = {
        width: '100%', minimumInputLength: 3, language: IDIOMA_SELECT2, templateResult: plantillaTercero,
        ajax: {
            delay: 250,
            data: (params) => ({ termino: params.term }),
            transport: (params, exito, fallo) => {
                pedir('terceros', { empresa: empresaId(), termino: params.data.termino }).then(exito).catch(fallo);

                return { abort: () => { } };
            },
            processResults: (datos) => ({
                results: (datos.filas || []).map((fila) => ({ id: texto(fila.nit), text: texto(fila.razonSocial), nit: texto(fila.nit), codigo: texto(fila.codigo) }))
            })
        }
    };

    $('#cap_terceros').select2({
        ...terceros,
        placeholder: 'Busque por código, identificación o nombre',
        templateSelection: (opcion) => opcion.id
            ? $(`<span><span class="font-monospace">${escaparHtml(codigoTercero(opcion) || opcion.id)}</span> ${escaparHtml(texto(opcion.text).split(/\s+/).slice(0, 2).join(' '))}</span>`)
            : opcion.text
    });

    if ($id('edit_tercero')) {
        $('#edit_tercero').select2({
            ...terceros,
            placeholder: 'Código, identificación o nombre…',
            dropdownParent: $('#modalEditarTrabajador'),
            templateSelection: (opcion) => opcion.id
                ? $(`<span><span class="font-monospace fw-semibold">${escaparHtml(codigoTercero(opcion) || opcion.id)}</span> — ${escaparHtml(opcion.text)}</span>`)
                : opcion.text
        });
    }
};

const codigoTercero = (opcion) => texto(opcion.codigo ?? opcion.element?.dataset.codigo);

const plantillaTercero = (opcion) => (opcion.nit
    ? $(`<span class="d-flex align-items-baseline gap-2">${codigoTercero(opcion) ? `<span class="gp-chip-fila verde font-monospace fw-semibold">${escaparHtml(codigoTercero(opcion))}</span>` : ''}<span class="text-truncate">${escaparHtml(opcion.text)}</span><span class="gp-subtexto font-monospace ms-auto">${escaparHtml(opcion.nit)}</span></span>`)
    : opcion.text);

const edicionTrabajador = { linea: null, tercero: '' };

const trabajadorEditado = () => edicionTrabajador.linea?.trabajadores.find((trabajador) => trabajador.tercero === edicionTrabajador.tercero) || null;

const resumenEdicion = () => {
    const linea = edicionTrabajador.linea;
    const trabajador = trabajadorEditado();
    const cantidad = aNumero($id('edit_cantidad').value);

    if (!trabajador) return;

    poner('edit_resumen', `Cantidad de la línea: ${cant(linea.cantidad)} → ${cantidad > 0 ? cant(linea.cantidad - trabajador.cantidad + cantidad) : '—'}${CFG.jornales ? ` · Jornales: ${cant(trabajador.jornales)} (sin cambio)` : ''}`);
};

const abrirEdicionTrabajador = (idLinea, tercero) => {
    const linea = estado.lineas.find((item) => item.id === idLinea);
    const trabajador = linea?.trabajadores.find((item) => item.tercero === tercero);

    if (!trabajador || linea.cargando) return;

    const opcion = new Option(trabajador.terceroNombre || tercero, tercero, true, true);

    opcion.dataset.codigo = trabajador.terceroCodigo || '';
    edicionTrabajador.linea = linea;
    edicionTrabajador.tercero = tercero;
    poner('chip_editar_trabajador', `#${estado.lineas.indexOf(linea) + 1} · ${linea.lote} · ${fechaCorta(linea.fecha).substring(0, 5)}`);
    $('#edit_tercero').empty().append(opcion).trigger('change');
    if ($id('edit_um')) $id('edit_um').textContent = `(${Array.from($id('cap_um').options).find((item) => item.value === linea.uMedida)?.textContent.trim() || linea.uMedida})`;
    $id('edit_cantidad').dataset.gramos = linea.uMedida || '-';
    $id('edit_cantidad').value = String(trabajador.cantidad).replace('.', ',');
    ['error_edit_tercero', 'error_edit_cantidad'].forEach((id) => mostrar($id(id), false));
    $id('edit_tercero').closest('.tx-select-ancho').classList.remove('is-invalid');
    $id('edit_cantidad').classList.remove('is-invalid');
    resumenEdicion();
    bootstrap.Modal.getOrCreateInstance($id('modalEditarTrabajador')).show();
};

const guardarEdicionTrabajador = (evento) => {
    evento.preventDefault();

    const linea = edicionTrabajador.linea;
    const trabajador = trabajadorEditado();
    const modal = bootstrap.Modal.getOrCreateInstance($id('modalEditarTrabajador'));

    if (!trabajador || !estado.lineas.includes(linea) || linea.cargando) {
        modal.hide();

        return;
    }

    const seleccion = $('#edit_tercero').select2('data')[0] || {};
    const nuevo = texto(seleccion.id);
    const cantidad = aNumero($id('edit_cantidad').value);
    const errorTercero = nuevo === '' ? 'Seleccione el tercero.' : (nuevo !== trabajador.tercero && linea.trabajadores.some((item) => item.tercero === nuevo) ? 'Ese tercero ya está en esta línea.' : '');

    $id('error_edit_tercero').textContent = errorTercero;
    mostrar($id('error_edit_tercero'), errorTercero !== '');
    $id('edit_tercero').closest('.tx-select-ancho').classList.toggle('is-invalid', errorTercero !== '');
    mostrar($id('error_edit_cantidad'), !(cantidad > 0));
    $id('edit_cantidad').classList.toggle('is-invalid', !(cantidad > 0));

    if (errorTercero || !(cantidad > 0)) return;

    const cambio = nuevo !== trabajador.tercero;
    const numero = estado.lineas.indexOf(linea) + 1;

    trabajador.cantidad = cantidad;
    trabajador.marca = 'tx-flash';
    linea.manual = true;

    if (cambio) {
        Object.assign(trabajador, { tercero: nuevo, terceroNombre: texto(seleccion.text), terceroCodigo: codigoTercero(seleccion), precio: null, existe: 0 });
        linea.cargando = true;
    }

    linea.cantidad = Math.round(linea.trabajadores.reduce((suma, item) => suma + item.cantidad, 0) * 100) / 100;
    linea.jornales = Math.round(linea.trabajadores.reduce((suma, item) => suma + item.jornales, 0) * 100) / 100;
    linea.insumos.forEach((insumo) => { insumo.cantidad = undefined; });
    linea.marca = 'tx-recalculo';
    modal.hide();
    pintarLineas();
    Toast.fire({ icon: 'success', title: cambio ? `Trabajador actualizado · línea #${numero} en re-liquidación` : `Trabajador actualizado · línea #${numero}` });

    if (cambio) precioLinea(linea);
};

document.addEventListener('DOMContentLoaded', () => {
    if (!$id('zona_formulario')) return;

    iniciarSelect2();
    $id('fecha').value = hoyIso();
    sincronizarFechas();
    pintarLineas();
    cargarPeriodos($id('anio').value);

    $('#tipo').on('change', () => {
        if (!estado.edicion) habilitarTipo($id('tipo').value !== '');
    });

    $id('anio').addEventListener('change', () => {
        invalidar();
        pintarLineas();
        cargarPeriodos($id('anio').value);
    });
    $id('periodo').addEventListener('change', () => {
        $id('periodo').classList.remove('is-invalid');
        invalidar();

        const [inicio, fin] = rangoPeriodo();

        if (inicio && fin && !fechaEnPeriodo($id('fecha').value)) {
            $id('fecha').value = inicio;
            sincronizarFechas();
            recargarPlanes();
        }

        revisarPeriodo();
        pintarLineas();
    });
    ['input', 'change'].forEach((evento) => $id('fecha').addEventListener(evento, () => {
        invalidar();
        sincronizarFechas();
        revisarPeriodo();
        pintarLineas();
    }));
    $id('fecha').addEventListener('change', () => {
        if ($id('cap_fecha').disabled) errorCampo('error_fecha_labor', false);

        recargarPlanes();
    });
    $id('todas_fechas')?.addEventListener('change', sincronizarFechas);
    $id('observacion').addEventListener('input', contarObservacion);

    $('#cap_labor').on('change', () => {
        const opcion = $id('cap_labor').selectedOptions[0];
        const um = opcion ? texto(opcion.dataset.um) : '';

        errorCampo('error_labor', false);

        if ($id('cap_codigo')) {
            $id('cap_codigo').value = texto($id('cap_labor').value);
            $id('cap_codigo').classList.remove('is-invalid');
            errorCampo('error_codigo', false);
        }

        if (um && Array.from($id('cap_um').options).some((item) => item.value === um)) {
            $id('cap_um').value = um;
            errorCampo('error_um', false);
        }
    });
    $('#cap_finca').on('change', () => {
        if (EXT.activo?.()) {
            EXT.finca();

            return;
        }

        const cambiar = () => {
            const finca = $id('cap_finca').value;

            errorCampo('error_finca', false);
            cargarSecciones(finca);

            if (CFG.referencia) cargarPlanes(finca);

            cargarLotes(finca, '');
        };

        if (CFG.referencia) confirmarCambio('cap_finca', '¿Cambiar la finca?', cambiar);
        else cambiar();
    });
    $('#referencia').on('change', () => confirmarCambio('referencia', '¿Cambiar la referencia?', async () => {
        errorCampo('error_referencia', false);
        await cargarLotes($id('cap_finca').value, $id('cap_seccion').disabled ? '' : $id('cap_seccion').value);

        if (planeadas().length === 0) return;

        estado.lineas.filter((linea) => linea.insumos.length === 0).forEach((linea) => { linea.insumos = insumosDeLote(linea.lote); });
        liquidar();
    }));
    $id('cap_codigo')?.addEventListener('change', buscarCodigo);
    $id('cap_codigo')?.addEventListener('input', () => {
        $id('cap_codigo').classList.remove('is-invalid');
        errorCampo('error_codigo', false);
    });
    $id('cap_codigo')?.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            buscarCodigo();
        }
    });
    $('#cap_seccion').on('change', () => {
        if (!$id('cap_seccion').disabled) cargarLotes($id('cap_finca').value, $id('cap_seccion').value);
    });
    $('#cap_lote').on('change', () => {
        const palmas = $id('cap_lote').selectedOptions[0]?.dataset.palmas;

        errorCampo('error_lote', false);

        if (palmas !== undefined) $id('cap_cantidad').value = String(Number(palmas)).replace('.', ',');

        revisarPalmas();
    });
    $('#cap_terceros').on('change', () => {
        contarTerceros();
        errorCampo('error_terceros', false);
    });
    $id('cap_um').addEventListener('change', () => errorCampo('error_um', false));
    $id('cap_fecha').addEventListener('change', () => errorCampo('error_fecha_labor', false));
    $id('btn_agregar_fecha')?.addEventListener('click', agregarFecha);
    $id('cap_fecha').addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter' || !$id('btn_agregar_fecha') || $id('btn_agregar_fecha').classList.contains('d-none')) return;

        evento.preventDefault();
        agregarFecha();
    });
    $id('col_fechas')?.addEventListener('click', (evento) => {
        const quitar = evento.target.closest('[data-quitar-fecha]');

        if (!quitar && !evento.target.closest('[data-limpiar-fechas]')) return;

        estado.fechas = quitar ? estado.fechas.filter((fecha) => fecha !== quitar.dataset.quitarFecha) : [];
        errorCampo('error_fecha_labor', false);
        pintarFechas();
        $id('cap_fecha').focus();
    });
    $id('formulario_editar_trabajador')?.addEventListener('submit', guardarEdicionTrabajador);
    $id('edit_cantidad')?.addEventListener('input', () => {
        $id('edit_cantidad').classList.remove('is-invalid');
        mostrar($id('error_edit_cantidad'), false);
        resumenEdicion();
    });
    $('#edit_tercero').on('change', () => {
        $id('edit_tercero').closest('.tx-select-ancho').classList.remove('is-invalid');
        mostrar($id('error_edit_tercero'), false);
    });
    $id('modalEditarTrabajador')?.addEventListener('shown.bs.modal', () => $id('edit_cantidad').focus());
    $id('cap_cantidad').addEventListener('input', () => {
        errorCampo('error_cantidad', false);
        revisarPalmas();
    });
    $id('cap_jornales').addEventListener('input', () => errorCampo('error_jornales', false));
    ['cap_cantidad', 'cap_jornales'].forEach((id) => $id(id).addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            cargarLineas();
        }
    }));

    $id('btn_cargar').addEventListener('click', cargarLineas);
    $id('btn_liquidar').addEventListener('click', liquidar);
    $('#lista_lineas').on('change', '.tx-insumo-item', (evento) => {
        const id = evento.target.dataset.linea;
        const um = $id(`ins_um_${id}`);
        const sugerida = evento.target.selectedOptions[0]?.dataset.um || '';

        evento.target.closest('.tx-select-ancho').classList.remove('is-invalid');
        mostrar($id(`ins_error_item_${id}`), false);
        um.value = Array.from(um.options).some((opcion) => opcion.value === sugerida) ? sugerida : '';
        um.classList.remove('is-invalid');
    });
    $id('lista_lineas').addEventListener('input', (evento) => evento.target.classList.remove('is-invalid'));
    $id('lista_lineas').addEventListener('change', (evento) => {
        if (evento.target.id.startsWith('ins_um_')) evento.target.classList.remove('is-invalid');
    });
    $id('lista_lineas').addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter' && evento.target.dataset.dosis) {
            evento.preventDefault();
            agregarInsumo(Number(evento.target.dataset.dosis));
        }
    });
    $id('lista_lineas').addEventListener('click', (evento) => {
        const agregar = evento.target.closest('[data-agregar-insumo]');
        const quitarInsumo = evento.target.closest('[data-quitar-insumo]');

        if (agregar) agregarInsumo(Number(agregar.dataset.agregarInsumo));

        if (quitarInsumo) {
            const lineaInsumo = estado.lineas.find((item) => item.id === Number(quitarInsumo.dataset.linea));

            lineaInsumo.insumos = lineaInsumo.insumos.filter((insumo) => insumo.item !== quitarInsumo.dataset.quitarInsumo);
            lineaInsumo.errorInsumo = '';
            pintarLineas();
        }
    });
    $id('lista_lineas').addEventListener('click', async (evento) => {
        const linea = evento.target.closest('[data-quitar-linea]');
        const trabajador = evento.target.closest('[data-quitar-trabajador]');
        const editar = evento.target.closest('[data-editar-trabajador]');

        if (editar) abrirEdicionTrabajador(Number(editar.dataset.linea), editar.dataset.editarTrabajador);

        if (linea) {
            const id = Number(linea.dataset.quitarLinea);
            const actual = estado.lineas.find((item) => item.id === id);

            if (!actual) return;

            const respuesta = await Swal.fire({
                icon: 'warning',
                title: actual.planeacion ? '¿Quitar la actividad planeada?' : `¿Eliminar la línea ${escaparHtml(String(estado.lineas.indexOf(actual) + 1))}?`,
                html: actual.planeacion ? 'Volverá a estar disponible en Planeaciones completadas.' : `Se eliminarán también los ${escaparHtml(String(actual.trabajadores.length))} trabajadores registrados en ella.`,
                showCancelButton: true,
                confirmButtonText: actual.planeacion ? 'Sí, quitar' : 'Sí, eliminar',
                confirmButtonColor: '#d6293e',
                cancelButtonText: 'Cancelar',
                focusCancel: true
            });

            if (!respuesta.isConfirmed) return;

            estado.lineas = estado.lineas.filter((item) => item.id !== id);
            pintarLineas();
        }

        if (trabajador) quitarTrabajador(Number(trabajador.dataset.linea), trabajador.dataset.quitarTrabajador);
    });

    $id('btn_guardar').addEventListener('click', () => {
        if (estado.edicion ? PERMISOS.editar : PERMISOS.registrar) guardar();
    });
    $id('btn_cancelar').addEventListener('click', cancelar);

    document.addEventListener('keydown', (evento) => {
        if (!(evento.ctrlKey || evento.metaKey) || evento.key.toLowerCase() !== 's') return;

        evento.preventDefault();

        if ($id('btn_guardar') && !$id('btn_guardar').disabled && $id('btn_guardar').offsetParent !== null && !document.querySelector('.modal.show') && !(Swal.isVisible() && !Swal.getPopup()?.classList.contains('swal2-toast'))) $id('btn_guardar').click();
    });

    window.addEventListener('beforeunload', (e) => {
        if (!estado.guardando && !estado.saliendo && hayCambios()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    if ($id('btn_planeaciones')) {
        $id('btn_planeaciones').addEventListener('click', abrirPla);
        $('#cap_finca, #referencia').on('change', pintarPlaneadas);
        ['pla_desde', 'pla_hasta', 'pla_finca', 'pla_buscar'].forEach((id) => $id(id).addEventListener('input', pintarPla));
        $id('pla_agregar').addEventListener('click', agregarPla);
        $id('modalPlaneaciones').addEventListener('click', (evento) => {
            if (evento.target.closest('[data-pla-limpiar]')) limpiarFiltrosPla();

            if (evento.target.closest('[data-pla-reintentar]')) cargarPla();

            const fila = evento.target.closest('tr[data-pla]');
            const check = fila?.querySelector('[data-pla-check]');

            if (!check || check.disabled || evento.target === check) return;

            check.checked = !check.checked;
            check.dispatchEvent(new Event('change', { bubbles: true }));
        });
        $id('modalPlaneaciones').addEventListener('change', (evento) => {
            const check = evento.target.closest('[data-pla-check]');

            if (evento.target.id === 'pla_todas') {
                document.querySelectorAll('#pla_cuerpo [data-pla-check]:not(:disabled)').forEach((item) => {
                    if (evento.target.checked) pla.seleccion.add(Number(item.dataset.plaCheck));
                    else pla.seleccion.delete(Number(item.dataset.plaCheck));
                });
            }

            if (check) {
                if (check.checked) pla.seleccion.add(Number(check.dataset.plaCheck));
                else pla.seleccion.delete(Number(check.dataset.plaCheck));
            }

            if (check || evento.target.id === 'pla_todas') seleccionPla();
        });
        cargarPla();
    }

    if (PERMISOS.registrar) precargarPlaneacion();
});
