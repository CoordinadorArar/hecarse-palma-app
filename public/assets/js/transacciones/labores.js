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

const estado = {
    tipo: false,
    lineas: [],
    secuencia: 1,
    periodos: [],
    edicion: null,
    foto: null,
    guardando: false,
    insumosLote: {}
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
    if (!CFG.referencia) $id('ayuda_fecha_labor').textContent = todas ? 'Editable por línea, dentro del periodo.' : 'Igual a la fecha de la transacción';

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

    return `<tr>
        <td><span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span><span class="gp-subtexto font-monospace">${escaparHtml(trabajador.tercero)}</span></td>
        <td class="text-end tx-num">${cant(trabajador.cantidad)}</td>
${CFG.jornales ? `        <td class="text-end tx-num${trabajador.jornales === 0 ? ' tx-cero' : ''}">${cant(trabajador.jornales)}</td>` : ''}
        <td class="text-end tx-num">${celdaPrecio(trabajador)}</td>
        <td class="text-end tx-num fw-semibold">${trabajador.precio === null ? '<span class="text-muted">—</span>' : fmt(valorTrabajador(trabajador))}</td>
        <td class="text-center gp-col-sticky">
            <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-trabajador="${escaparHtml(trabajador.tercero)}" data-linea="${linea.id}" title="${titulo}" aria-label="${titulo}">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </td>
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
                    <input type="text" class="form-control form-control-sm tx-input-num" id="ins_dosis_${id}" data-dosis="${id}" inputmode="decimal" placeholder="0,00" autocomplete="off" maxlength="10"${bloqueo}>
                    <div class="invalid-feedback">Dosis mayor a 0, máx. 2 decimales.</div>
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

const tarjetaLinea = (linea, indice) => {
    const pendiente = linea.trabajadores.some((trabajador) => trabajador.precio === null);
    const faltaTarifa = linea.trabajadores.some(sinTarifa);
    const labor = `${escaparHtml(linea.novedad)} — ${escaparHtml(linea.novedadNombre)}`;
    const valor = pendiente
        ? CHIP_PENDIENTE
        : `${fmt(valorLinea(linea))}${faltaTarifa ? ' <i class="bi bi-exclamation-triangle tx-texto-ambar" title="Hay trabajadores sin tarifa" aria-label="Hay trabajadores sin tarifa"></i>' : ''}`;

    return `<div class="tx-tarjeta${faltaTarifa ? ' tx-tarjeta--sintarifa' : ''}${linea.marca ? ` ${linea.marca}` : ''}" data-linea="${linea.id}"${linea.cargando ? ' aria-busy="true"' : ''}>
        <div class="tx-tarjeta-cab tx-tarjeta-cab--fija flex-wrap">
            <span class="gp-chip-fila tx-chip-neutro font-monospace">#${indice + 1}</span>
            <span><span class="font-monospace fw-semibold">${escaparHtml(linea.novedad)}</span> — <span class="gp-truncar d-inline-block align-bottom" title="${labor}">${escaparHtml(linea.novedadNombre)}</span></span>
            <span class="gp-chip-fila tx-chip-neutro">${escaparHtml(linea.uMedida)}</span>
            <span class="tx-sep">&middot;</span>
            <span class="text-nowrap">${fechaCorta(linea.fecha)}</span>
            <span class="tx-sep">&middot;</span>
            <span class="font-monospace">${CFG.referencia ? '' : `${escaparHtml(linea.finca)} / `}${linea.seccion ? escaparHtml(linea.seccion) : '<span class="text-muted">—</span>'} / ${escaparHtml(linea.lote)}</span>
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
                        <th class="text-center gp-col-sticky" style="width:52px"><span class="visually-hidden">Quitar</span></th>
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
    $id('btn_liquidar').disabled = !hay;

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

const cargarPlanes = async (finca, valor = '') => {
    const selector = $id('referencia');

    reiniciarSelect('referencia', finca ? 'Cargando planes…' : 'Seleccione una finca primero');
    selector.dataset.previo = '';
    mostrar($id('ayuda_sin_planes'), false);

    if (!finca) return;

    let filas = [];

    try {
        filas = (await pedir('planes', { empresa: empresaId(), finca })).filas || [];
    } catch (error) {
        avisar(error);
    }

    if ($id('cap_finca').value !== finca) return;

    selector.innerHTML = `<option value="">${filas.length ? 'Seleccione la referencia…' : 'Sin planes de fertilización'}</option>` + filas.map((fila) => {
        const numero = escaparHtml(texto(fila.numero));
        const rango = `${fechaCorta(fila.fecha)} – ${fechaCorta(fila.fechaFinal)}${texto(fila.observacion) ? ` · ${texto(fila.observacion)}` : ''}`;

        return `<option value="${numero}" data-rango="${escaparHtml(rango)}">${numero} · ${escaparHtml(rango)}</option>`;
    }).join('');

    if (valor && !filas.some((fila) => texto(fila.numero) === valor)) selector.add(new Option(valor, valor));

    selector.disabled = selector.options.length <= 1;
    selector.value = valor;
    selector.dataset.previo = selector.value;
    mostrar($id('ayuda_sin_planes'), filas.length === 0);
    $('#referencia').trigger('change.select2');
};

const confirmarCambio = async (id, titulo, accion) => {
    const selector = $id(id);

    if (selector.value === (selector.dataset.previo || '')) return;

    if (estado.lineas.length > 0) {
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

const errorCampo = (id, visible) => mostrar($id(id), visible);

const cargarLineas = () => {
    const labor = $id('cap_labor');
    const um = $id('cap_um').value;
    const fecha = CFG.referencia || $id('todas_fechas').checked ? $id('cap_fecha').value : $id('fecha').value;
    const finca = $id('cap_finca').value;
    const lote = $id('cap_lote').value;
    const terceros = $('#cap_terceros').select2('data').filter((opcion) => opcion.id);
    const cantidad = aNumero($id('cap_cantidad').value);
    const jornales = aNumero($id('cap_jornales').value);
    const errores = {
        error_labor: labor.value === '',
        error_um: um === '',
        error_fecha_labor: !fechaEnPeriodo(fecha),
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

    if (Object.values(errores).some(Boolean)) {
        Toast.fire({ icon: 'warning', title: 'Revise los datos de la línea' });

        return;
    }

    if (estado.lineas.length >= MAX_LINEAS || terceros.length > MAX_LINEAS) {
        Toast.fire({ icon: 'warning', title: `Máximo ${MAX_LINEAS} líneas y ${MAX_LINEAS} trabajadores por línea` });

        return;
    }

    const linea = {
        id: estado.secuencia++,
        novedad: labor.value,
        novedadNombre: labor.selectedOptions[0].dataset.nombre || '',
        uMedida: um,
        fecha,
        finca,
        seccion: $id('cap_seccion').value,
        lote,
        cantidad,
        jornales,
        marca: 'tx-flash',
        cargando: true,
        insumos: CFG.referencia ? (estado.insumosLote[lote] || []).filter((insumo) => insumo.dosis > 0).filter((insumo, indice, lista) => lista.findIndex((otro) => otro.item === insumo.item) === indice).map((insumo) => ({ ...insumo })) : [],
        trabajadores: terceros.map((tercero) => ({ tercero: texto(tercero.id), terceroNombre: texto(tercero.text), cantidad: 0, jornales: 0, precio: null, existe: 0 }))
    };

    repartirLinea(linea);
    estado.lineas.push(linea);
    $('#cap_terceros').val(null).trigger('change');
    $id('cap_cantidad').value = '';
    $id('cap_jornales').value = '0';
    pintarLineas();
    Toast.fire({ icon: 'success', title: `Línea cargada · ${terceros.length} ${terceros.length === 1 ? 'trabajador' : 'trabajadores'}` });
    precioLinea(linea);
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
    if (estado.lineas.length === 0 || !exigirPeriodo()) return;

    const boton = $id('btn_liquidar');
    const original = boton.innerHTML;
    const lineas = [...estado.lineas];

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

    boton.innerHTML = original;
    pintarLineas();
};

const quitarTrabajador = (idLinea, tercero) => {
    const linea = estado.lineas.find((item) => item.id === idLinea);

    if (!linea) return;

    linea.trabajadores = linea.trabajadores.filter((trabajador) => trabajador.tercero !== tercero);

    if (linea.trabajadores.length === 0) {
        estado.lineas = estado.lineas.filter((item) => item !== linea);
    } else {
        repartirLinea(linea);
        linea.marca = 'tx-recalculo';
    }

    pintarLineas();
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
    guardar.title = numero ? 'Guardar los cambios de la transacción' : 'Guardar la transacción';
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

    if (CFG.referencia) {
        cargarPlanes('');
        $id('cap_codigo').value = '';
        $id('cap_codigo').classList.remove('is-invalid');
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

    if (Number(fila.bloqueada) === 1) {
        Swal.fire({ icon: 'warning', title: 'Transacción liquidada', html: 'Ya fue liquidada en nómina; no admite cambios.', confirmButtonText: 'Entendido' });

        return true;
    }

    return false;
};

const cargarEdicion = async (numero) => {
    if (!PERMISOS.editar || !$id('zona_formulario')) return;

    if (estado.edicion === numero) {
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
        resultado = await pedir('detalle', { empresa: empresaId(), numero });
    } catch (error) {
        Swal.fire({ icon: 'warning', title: error.status === 403 ? 'Acción no permitida' : 'No se puede editar la transacción', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });

        return;
    }

    const encabezado = resultado.encabezado || {};

    if (alertaBloqueo({ ...encabezado, numero }, 'editar')) return;

    limpiarTodo();
    ['pestanas', 'item_registro'].forEach((id) => $id(id)?.classList.remove('d-none'));
    bootstrap.Tab.getOrCreateInstance($id('tab_registro')).show();
    window.scrollTo({ top: 0, behavior: 'smooth' });

    if (EXT.editar && texto(encabezado.tipo) === 'PFA') {
        await EXT.editar(encabezado, resultado, numero);
        estado.foto = JSON.stringify(cuerpoBase());

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
            cantidad: Number(trabajador.cantidad || 0),
            jornales: Number(trabajador.jornales || 0),
            precio: Number(trabajador.precioLabor || 0),
            existe: Number(trabajador.precioLabor || 0) > 0 ? 1 : 0
        }))
    }));
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
        resultado = estado.edicion
            ? await pedir('actualizar', { ...cuerpoBase(), numero: estado.edicion })
            : await pedir('guardar', cuerpoBase());
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

        if (typeof volverConsulta === 'function' && $id('tab_consulta')) volverConsulta(numero, true);

        return;
    }

    const lineas = estado.lineas.length;
    const resumen = EXT.activo?.() ? EXT.resumen() : `${escaparHtml(fmt(lineas))} ${lineas === 1 ? 'línea' : 'líneas'}`;

    limpiarTodo();
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
    $('#cap_terceros').select2({
        width: '100%', placeholder: 'Busque y agregue uno o más terceros', minimumInputLength: 3, language: IDIOMA_SELECT2,
        ajax: {
            delay: 250,
            data: (params) => ({ termino: params.term }),
            transport: (params, exito, fallo) => {
                pedir('terceros', { empresa: empresaId(), termino: params.data.termino }).then(exito).catch(fallo);

                return { abort: () => { } };
            },
            processResults: (datos) => ({
                results: (datos.filas || []).map((fila) => ({ id: texto(fila.nit), text: texto(fila.razonSocial), nit: texto(fila.nit) }))
            })
        },
        templateResult: (opcion) => opcion.nit
            ? $(`<span><span class="font-monospace">${escaparHtml(opcion.nit)}</span> <span class="ms-1">${escaparHtml(opcion.text)}</span></span>`)
            : opcion.text,
        templateSelection: (opcion) => opcion.id
            ? $(`<span><span class="font-monospace">${escaparHtml(opcion.id)}</span> ${escaparHtml(texto(opcion.text).split(/\s+/).slice(0, 2).join(' '))}</span>`)
            : opcion.text
    });
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
    $('#referencia').on('change', () => confirmarCambio('referencia', '¿Cambiar la referencia?', () => {
        errorCampo('error_referencia', false);
        cargarLotes($id('cap_finca').value, $id('cap_seccion').disabled ? '' : $id('cap_seccion').value);
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
    $id('lista_lineas').addEventListener('click', (evento) => {
        const linea = evento.target.closest('[data-quitar-linea]');
        const trabajador = evento.target.closest('[data-quitar-trabajador]');

        if (linea) {
            estado.lineas = estado.lineas.filter((item) => item.id !== Number(linea.dataset.quitarLinea));
            pintarLineas();
        }

        if (trabajador) quitarTrabajador(Number(trabajador.dataset.linea), trabajador.dataset.quitarTrabajador);
    });

    $id('btn_guardar').addEventListener('click', () => {
        if (estado.edicion ? PERMISOS.editar : PERMISOS.registrar) guardar();
    });
    $id('btn_cancelar').addEventListener('click', cancelar);
});
