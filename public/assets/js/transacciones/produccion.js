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

const URL_PROD = BASE_URL + 'transacciones/produccion/';

const BLOQUES = [
    { clave: 'cosecha', rotulo: 'Cosecha', rotuloPie: 'Cosecha', letra: 'C', vacio: 'Sin trabajadores en cosecha' },
    { clave: 'carga', rotulo: 'Carga', rotuloPie: 'Cargue', letra: 'G', vacio: 'Sin trabajadores en carga' },
    { clave: 'transporte', rotulo: 'Transporte', rotuloPie: 'Transporte', letra: 'T', vacio: 'Sin trabajadores en transporte' }
];

const SIN_LABORES = 'No hay labores definidas en el maestro de labores.';

const sinLabores = () => Array.isArray(estado.labores) && estado.labores.length === 0;

const $id = (id) => document.getElementById(id);
const mostrar = (elemento, visible) => elemento.classList.toggle('d-none', !visible);
const texto = (valor) => String(valor ?? '').trim();

const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const fmt = (valor, decimales = 0) => Number(valor || 0).toLocaleString('es-CO', {
    minimumFractionDigits: decimales, maximumFractionDigits: decimales
});

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

const aNumero = (valor) => {
    const crudo = texto(valor).replace(/\s/g, '');

    if (crudo === '') return 0;

    const limpio = crudo.replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.');
    const numero = Number(limpio);

    return isNaN(numero) ? 0 : numero;
};

kgGramos.parsear = aNumero;

const estado = {
    tiqueteCargado: false,
    encontrado: false,
    externo: false,
    lineas: [],
    secuencia: 1,
    secuenciaTrabajador: 1,
    fechaLineaTocada: false,
    guardando: false,
    fallaPrevio: false,
    sellado: false,
    lotes: [],
    labores: null,
    periodos: [],
    edicion: null,
    foto: null,
    modal: { linea: null, bloque: 'cosecha', trabajador: null, precios: {}, manual: false }
};

let turnoPeriodos = 0;
let turnoPrecio = 0;
let turnoPrevio = 0;

const empresaId = () => $id('empresa').value;

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;
    let resultado = null;

    try {
        respuesta = await fetch(URL_PROD + ruta, { method: 'POST', body: cuerpo });
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

const ocupar = (boton, ocupado, etiqueta) => {
    boton.disabled = ocupado;
    boton.innerHTML = ocupado
        ? `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${escaparHtml(etiqueta)}`
        : boton.dataset.original;
};

const guardarEtiquetas = () => ['btn_buscar_tiquete', 'btn_guardar', 'btn_cancelar'].forEach((id) => {
    $id(id).dataset.original = $id(id).innerHTML;
    $id(id).dataset.base = $id(id).innerHTML;
});

const periodoActual = () => estado.periodos.find((periodo) => String(Number(periodo.mes)) === $id('periodo').value) || null;
const nombrePeriodo = (periodo) => texto(periodo.descripcion) || (Number(periodo.mes) > 12 ? 'Periodo adicional' : MESES[Number(periodo.mes) - 1]);
const sinFechas = (periodo) => !texto(periodo.fechaInicial) || !texto(periodo.fechaFinal);
const periodoBloqueado = (periodo) => periodo !== null && (Number(periodo.cerrado) === 1 || (Number(periodo.mes) > 12 && sinFechas(periodo)));

const revisarPeriodo = () => {
    const periodo = periodoActual();
    const fecha = $id('fecha').value;
    const inicio = periodo ? texto(periodo.fechaInicial).substring(0, 10) : '';
    const fin = periodo ? texto(periodo.fechaFinal).substring(0, 10) : '';
    const conRango = inicio !== '' && fin !== '';
    const mesAnio = periodo ? `${$id('anio').value}-${String(Number(periodo.mes)).padStart(2, '0')}` : '';
    const fuera = periodo !== null && fecha !== '' && (conRango
        ? fecha < inicio || fecha > fin
        : Number(periodo.mes) <= 12 && fecha.substring(0, 7) !== mesAnio);
    const bloqueado = periodoBloqueado(periodo);

    $id('periodo_rango').textContent = periodo ? (conRango ? `Del ${fechaCorta(inicio)} al ${fechaCorta(fin)}` : 'Sin rango de fechas definido') : '';
    $id('nota_periodo_texto').textContent = bloqueado
        ? `El periodo ${nombrePeriodo(periodo)} ${Number(periodo.cerrado) === 1 ? 'está cerrado' : 'no tiene fechas definidas'}. Elija un periodo abierto para guardar.`
        : (fuera
            ? `La fecha del tiquete (${fechaCorta(fecha)}) no está dentro del periodo ${nombrePeriodo(periodo)}${conRango ? ` (${fechaCorta(inicio)} – ${fechaCorta(fin)})` : ''}.`
            : '');
    mostrar($id('nota_periodo'), bloqueado || fuera);
    $id('cap_fecha_linea').min = conRango ? inicio : '';
    $id('cap_fecha_linea').max = conRango ? fin : '';

    if (!estado.fechaLineaTocada) $id('cap_fecha_linea').value = fecha;
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

    const abiertos = filas.filter((periodo) => !periodoBloqueado(periodo));
    const propio = mes ? filas.find((periodo) => Number(periodo.mes) === Number(mes)) : null;

    if (abiertos.length === 0 && !propio) {
        selector.innerHTML = '<option value="">No hay periodos abiertos</option>';
        revisarPeriodo();

        return;
    }

    const hoy = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const sugerido = propio
        || abiertos.find((periodo) => hoy >= texto(periodo.fechaInicial).substring(0, 10) && hoy <= texto(periodo.fechaFinal).substring(0, 10))
        || abiertos.find((periodo) => Number(periodo.mes) === Number(selector.dataset.mes));

    estado.periodos = filas;
    selector.innerHTML = '<option value="">Seleccione el periodo…</option>' + filas.map((periodo) => {
        const cerrado = Number(periodo.cerrado) === 1;
        const sufijo = cerrado ? ' — Cerrado' : (periodoBloqueado(periodo) ? ' — Sin fechas' : '');

        return `<option value="${Number(periodo.mes)}"${sufijo ? ' disabled' : ''}>${String(Number(periodo.mes)).padStart(2, '0')} — ${escaparHtml(nombrePeriodo(periodo))}`
            + `${sinFechas(periodo) ? '' : ` (${fechaCorta(periodo.fechaInicial)} – ${fechaCorta(periodo.fechaFinal)})`}${sufijo}</option>`;
    }).join('');
    selector.value = sugerido ? String(Number(sugerido.mes)) : '';

    if (selector.selectedIndex < 0) selector.value = '';

    selector.disabled = false;
    revisarPeriodo();
    renderizar();
};

const racimosTiquete = () => Math.round(aNumero($id('racimos').value));
const racimosRepartidos = () => estado.lineas.reduce((suma, linea) => suma + linea.racimos, 0);
const faltantes = () => racimosTiquete() - racimosRepartidos();
const hayPesos = () => estado.lineas.some((linea) => linea.pesoRacimo > 0);

const estadoReparto = () => {
    if (!estado.tiqueteCargado) return 'inactivo';

    if (racimosTiquete() <= 0) return 'sinracimos';

    const diferencia = faltantes();

    if (diferencia > 0) return 'pendiente';

    return diferencia < 0 ? 'excedido' : 'cuadrado';
};

const pintarMedidor = () => {
    const medidor = $id('medidor');
    const clase = estadoReparto();
    const total = racimosTiquete();
    const repartidos = racimosRepartidos();
    const diferencia = faltantes();

    medidor.className = `tx-reparto tx-reparto--${clase}`;
    $id('med_tiquete').textContent = fmt(total);
    $id('med_repartidos').textContent = fmt(repartidos);

    const textos = {
        inactivo: 'Cargue el tiquete para conocer los racimos a repartir',
        sinracimos: 'Indique los racimos del tiquete para comenzar el reparto',
        pendiente: `<span class="fs-5 fw-bold tx-estado-num">Faltan ${fmt(diferencia)}</span> racimos`,
        cuadrado: '<i class="bi bi-check-circle-fill" aria-hidden="true"></i><span class="fs-5 fw-bold">Reparto cuadrado</span>',
        excedido: `<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span class="fs-5 fw-bold tx-estado-num">Sobran ${fmt(Math.abs(diferencia))}</span> racimos`
    };

    $id('med_estado').innerHTML = textos[clase];
    $id('med_barra').style.width = total > 0 ? `${Math.min(100, (repartidos / total) * 100)}%` : '0';

    mostrar($id('med_kg'), clase !== 'inactivo' && clase !== 'sinracimos');

    const neto = aNumero($id('pesoNeto').value);
    const asignados = estado.lineas.reduce((suma, linea) => suma + linea.kg, 0);

    $id('med_kg').classList.toggle('tx-texto-ambar', Boolean(estado.fallaPrevio));
    $id('med_kg_texto').textContent = estado.fallaPrevio
        ? `Kg asignados — ${estado.fallaPrevio === true ? 'no se pudo calcular' : estado.fallaPrevio}`
        : (hayPesos() || estado.lineas.length === 0
            ? `Kg asignados ${fmt(asignados)} de ${fmt(neto)} — los calcula el sistema`
            : `Kg asignados ${fmt(asignados)} de ${fmt(neto)} — reparto solo por racimos`);
};

const pintarTotales = () => {
    const totales = { cosecha: 0, carga: 0, transporte: 0, jornales: 0, sacos: 0 };

    estado.lineas.forEach((linea) => {
        totales.sacos += linea.sacos;
        linea.trabajadores.forEach((trabajador) => {
            totales[trabajador.bloque] += trabajador.cantidad * trabajador.precio;
            totales.jornales += trabajador.jornales;
        });
    });

    const clase = estadoReparto();

    $id('total_racimos').textContent = `${fmt(racimosRepartidos())} de ${fmt(racimosTiquete())}`;
    $id('total_racimos').classList.remove('tx-total--inactivo', 'tx-total--pendiente', 'tx-total--cuadrado', 'tx-total--excedido');
    $id('total_racimos').classList.add(`tx-total--${clase === 'sinracimos' ? 'inactivo' : clase}`);
    $id('total_sacos').textContent = fmt(totales.sacos);

    [['total_cosecha', totales.cosecha], ['total_cargue', totales.carga],
    ['total_transporte', totales.transporte], ['total_jornales', totales.jornales]].forEach(([id, valor]) => {
        $id(id).textContent = fmt(valor);
        $id(id).classList.toggle('tx-cero', valor === 0);
    });

    $id('total_sacos').classList.toggle('tx-cero', totales.sacos === 0);
};

const celdaTercero = (codigo, nombre, nit) => `<td>
            <div class="text-nowrap">${codigo ? `<span class="font-monospace fw-semibold">${escaparHtml(codigo)}</span> ` : ''}<span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(nombre)}">${escaparHtml(nombre)}</span></div>
            <span class="gp-subtexto font-monospace">${escaparHtml(nit)}</span>
        </td>`;

const chipFecha = (fecha, fuera) => {
    if (texto(fecha) === '') return '';

    return fuera
        ? `<span class="gp-chip-fila tx-chip-rojo font-monospace" title="La fecha de la línea está fuera del periodo seleccionado"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>${escaparHtml(fechaCorta(fecha))}</span>`
        : `<span class="gp-chip-fila tx-chip-neutro font-monospace"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>${escaparHtml(fechaCorta(fecha))}</span>`;
};

const fechaFuera = (fecha) => {
    const periodo = periodoActual();

    if (!periodo || texto(fecha) === '') return false;

    if (!sinFechas(periodo)) return fecha < texto(periodo.fechaInicial).substring(0, 10) || fecha > texto(periodo.fechaFinal).substring(0, 10);

    return Number(periodo.mes) <= 12 && fecha.substring(0, 7) !== `${$id('anio').value}-${String(Number(periodo.mes)).padStart(2, '0')}`;
};

const filaTrabajador = (linea, trabajador) => `
    <tr data-trabajador="${trabajador.id}">
        ${celdaTercero(trabajador.terceroCodigo, trabajador.terceroNombre, trabajador.tercero)}
        <td><span class="font-monospace">${escaparHtml(trabajador.novedad)}</span><span class="gp-subtexto gp-truncar" title="${escaparHtml(trabajador.novedadNombre)}">${escaparHtml(trabajador.novedadNombre)}</span></td>
        <td class="font-monospace text-nowrap" title="${escaparHtml(fechaCorta(trabajador.fecha))}">${escaparHtml(fechaCorta(trabajador.fecha).substring(0, 5))}</td>
        <td class="tx-num"${trabajador.manual ? ' title="Cantidad ajustada manualmente. Los demás trabajadores de esta actividad absorben la diferencia."' : ''}>${trabajador.manual ? '<i class="bi bi-pencil-fill tx-texto-ambar small me-1" aria-hidden="true"></i><span class="visually-hidden">Ajustada manualmente</span>' : ''}${fmt(trabajador.cantidad, 2)}</td>
        <td class="tx-num">${fmt(trabajador.precio, 2)}</td>
        <td class="tx-num${trabajador.jornales === 0 ? ' tx-cero' : ''}">${fmt(trabajador.jornales, 2)}</td>
        <td class="tx-num fw-semibold">${fmt(trabajador.cantidad * trabajador.precio)}</td>
        <td class="text-center gp-col-sticky">
            <div class="d-flex justify-content-center gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-accion="editar-trabajador" data-linea="${linea.id}" data-trabajador="${trabajador.id}" title="Editar trabajador">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-accion="eliminar-trabajador" data-linea="${linea.id}" data-trabajador="${trabajador.id}" title="Eliminar trabajador">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                </button>
            </div>
        </td>
    </tr>`;

const chipCuadrilla = (linea) => {
    const grupo = estado.lineas.filter((item) => item.finca === linea.finca && item.lote === linea.lote && item.fecha === linea.fecha);

    return grupo.length > 1 ? `<span class="gp-chip-fila tx-chip-neutro text-nowrap" title="Cuadrilla ${fmt(grupo.indexOf(linea) + 1)} de ${fmt(grupo.length)} en este lote y fecha"><i class="bi bi-people me-1" aria-hidden="true"></i>Cuadrilla ${fmt(grupo.indexOf(linea) + 1)} de ${fmt(grupo.length)}</span>` : '';
};

const tarjeta = (linea, indice) => {
    const vacia = sinLabores();
    const posicion = fmt(indice + 1);
    const delBloque = (clave) => linea.trabajadores.filter((trabajador) => trabajador.bloque === clave);
    const faltan = faltantes();

    const filas = BLOQUES.map((bloque) => {
        const lista = delBloque(bloque.clave);

        return lista.length === 0 ? '' : `<tr class="tx-grupo"><td colspan="8">${escaparHtml(bloque.rotuloPie)} <span class="tx-chip-cont verde">${fmt(lista.length)}</span></td></tr>`
            + lista.map((trabajador) => filaTrabajador(linea, trabajador)).join('');
    }).join('');

    return `
    <div class="tx-tarjeta${linea.planeacion ? ' tx-tarjeta--planeada' : ''}${linea.pesoRacimo > 0 || !hayPesos() ? '' : ' tx-tarjeta--sinpeso'}" data-linea="${linea.id}">
        <div class="tx-tarjeta-cab tx-tarjeta-cab--fija border-bottom">
            <span class="gp-chip-fila tx-chip-neutro font-monospace">Registro ${posicion}</span>
            <span class="fw-semibold">${escaparHtml(linea.fincaNombre)}</span>
            <i class="bi bi-chevron-right gp-ruta-sep" aria-hidden="true"></i>
            <span class="font-monospace fw-semibold">${escaparHtml(linea.lote)}</span>
            ${chipFecha(linea.fecha, fechaFuera(linea.fecha))}
            ${chipCuadrilla(linea)}
            ${linea.planeacion ? chipPlaneacion(linea.planeacion) : ''}
            ${linea.pesoRacimo > 0 || !hayPesos() ? '' : '<span class="gp-chip-fila ambar">Sin peso</span>'}
            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-accion="eliminar-linea" data-linea="${linea.id}" title="Quitar el registro ${posicion}" aria-label="Quitar el registro ${posicion}">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
        </div>
        <div class="px-3 py-2 border-bottom tx-franja">
            <div class="row g-2 row-cols-2 row-cols-sm-4 align-items-end">
                <div class="col">
                    <span class="gp-etiqueta-mini d-block mb-1">Peso prom.</span>
                    <span class="tx-dato-valor">${linea.pesoRacimo > 0 ? fmt(linea.pesoRacimo, 2) + ' kg' : '—'}</span>
                </div>
                <div class="col">
                    <label class="gp-etiqueta-mini d-block mb-1" for="linea_racimos_${linea.id}">Racimos</label>
                    <input type="text" class="form-control form-control-sm tx-input-num" id="linea_racimos_${linea.id}" data-campo="racimos" data-linea="${linea.id}" inputmode="numeric" maxlength="8" autocomplete="off" value="${fmt(linea.racimos)}">
                </div>
                <div class="col">
                    <label class="gp-etiqueta-mini d-block mb-1" for="linea_sacos_${linea.id}">Sacos</label>
                    <input type="text" class="form-control form-control-sm tx-input-num" id="linea_sacos_${linea.id}" data-campo="sacos" data-linea="${linea.id}" inputmode="numeric" maxlength="8" autocomplete="off" value="${fmt(linea.sacos)}">
                </div>
                <div class="col text-end">
                    <span class="gp-etiqueta-mini d-block mb-1"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Kg asignados</span>
                    <span class="fw-bold tx-kg-asignados" data-kg="${linea.id}" title="${escaparHtml(tooltipKg(linea))}">${textoKg(linea)}</span>
                    <span data-delta="${linea.id}"></span>
                </div>
                <div class="col-12${faltan > 0 ? '' : ' d-none'}" data-faltantes="${linea.id}">
                    <button type="button" class="btn btn-sm btn-outline-success" data-accion="asignar-faltantes" data-linea="${linea.id}" title="Suma a esta línea los ${fmt(faltan)} racimos que faltan para cuadrar con el tiquete.">
                        <i class="bi bi-check2-square me-1" aria-hidden="true"></i>Asignar faltantes (+${fmt(faltan)})
                    </button>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2">
            <span class="gp-etiqueta-mini">Trabajadores</span>
            <span class="tx-chip-cont${linea.trabajadores.length > 0 ? ' verde' : ''}">${fmt(linea.trabajadores.length)}</span>
            <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Agregar trabajadores al registro ${posicion}">
                ${BLOQUES.map((bloque) => `<button type="button" class="btn btn-outline-success" data-accion="nuevo-trabajador" data-linea="${linea.id}" data-bloque="${bloque.clave}"${vacia ? ' disabled' : ''} title="${escaparHtml(vacia ? SIN_LABORES : `Agregar trabajador de ${bloque.rotuloPie.toLowerCase()}`)}">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>${escaparHtml(bloque.rotuloPie)}
                </button>`).join('')}
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 gp-tabla tx-grilla tx-tabla-trab">
                <thead class="gp-thead">
                    <tr>
                        <th style="min-width:150px">Tercero</th>
                        <th style="min-width:120px">Actividad</th>
                        <th style="width:70px">Fecha</th>
                        <th class="text-end" style="width:90px">Cant. (kg)</th>
                        <th class="text-end" style="width:90px">Precio</th>
                        <th class="text-end" style="width:60px">Jor.</th>
                        <th class="text-end" style="width:100px">Valor</th>
                        <th class="text-center gp-col-sticky" style="width:80px">Acciones</th>
                    </tr>
                </thead>
                <tbody>${filas || `<tr><td colspan="8" class="text-center text-muted small py-3"><i class="bi ${vacia ? 'bi-slash-circle' : 'bi-people'} me-1" aria-hidden="true"></i>${escaparHtml(vacia ? SIN_LABORES : 'Sin trabajadores. Use + Cosecha, + Cargue o + Transporte.')}</td></tr>`}</tbody>
            </table>
        </div>
        <div class="tx-pie-tarjeta px-3 py-2 border-top d-flex flex-wrap justify-content-end gap-3">${pieTarjeta(linea)}</div>
    </div>`;
};

const pieTarjeta = (linea) => BLOQUES.map((bloque) => {
    const lista = linea.trabajadores.filter((trabajador) => trabajador.bloque === bloque.clave);
    const valor = lista.reduce((suma, trabajador) => suma + trabajador.cantidad * trabajador.precio, 0);
    const jor = lista.reduce((suma, trabajador) => suma + trabajador.jornales, 0);

    return `<span><span class="${valor === 0 ? 'tx-cero' : ''}">${escaparHtml(bloque.rotuloPie)} $${fmt(valor)}</span>`
        + ` <span class="${jor === 0 ? 'tx-cero' : ''}">&middot; Jor. ${fmt(jor)}</span></span>`;
}).join('<span class="tx-cero">|</span>');

const repartir = (kg, pesos) => {
    const centavos = Math.round(kg * 100);
    let w = pesos.map((peso) => Math.max(0, Number(peso) || 0));
    let suma = w.reduce((total, peso) => total + peso, 0);

    if (suma <= 0) {
        w = w.map(() => 1);
        suma = w.length;
    }

    const exactos = w.map((peso) => (centavos > 0 ? centavos * peso / suma : 0));
    const base = exactos.map((exacto) => Math.floor(exacto));
    let sobrante = centavos - base.reduce((total, valor) => total + valor, 0);

    exactos.map((exacto, k) => k)
        .sort((a, b) => ((exactos[b] - base[b]) - (exactos[a] - base[a])) || a - b)
        .forEach((k) => {
            if (sobrante <= 0) return;

            base[k]++;
            sobrante--;
        });

    return base.map((valor) => valor / 100);
};

const grupoDe = (linea, novedad) => linea.trabajadores.filter((trabajador) => trabajador.novedad === novedad);

const igualar = (linea, novedad) => {
    const grupo = grupoDe(linea, novedad);

    repartir(linea.kg, grupo.map(() => 1)).forEach((cantidad, k) => Object.assign(grupo[k], { cantidad, manual: false }));
};

const ajustar = (linea, actual, valor) => {
    const otros = grupoDe(linea, actual.novedad).filter((item) => item !== actual);

    Object.assign(actual, { cantidad: valor, manual: true });
    repartir(linea.kg - valor, otros.map(() => 1)).forEach((cantidad, k) => Object.assign(otros[k], { cantidad, manual: false }));
};

const reescalar = (linea) => new Set(linea.trabajadores.map((trabajador) => trabajador.novedad)).forEach((novedad) => {
    const grupo = grupoDe(linea, novedad);
    const porRacimos = grupo.every((trabajador) => !trabajador.cantidad);

    repartir(linea.kg, grupo.map((trabajador) => (porRacimos ? trabajador.racimos || 0 : trabajador.cantidad))).forEach((cantidad, k) => { grupo[k].cantidad = cantidad; });
});

const fotoCantidades = () => Object.fromEntries(estado.lineas.flatMap((linea) => linea.trabajadores.map((trabajador) => [trabajador.id, trabajador.cantidad])));

const refrescarTrabajadores = (antes) => estado.lineas.forEach((linea) => {
    const cambiados = linea.trabajadores.filter((trabajador) => trabajador.id in antes && Math.round(antes[trabajador.id] * 100) !== Math.round(trabajador.cantidad * 100));
    const pie = document.querySelector(`.tx-tarjeta[data-linea="${linea.id}"] .tx-pie-tarjeta`);

    if (cambiados.length === 0 || !pie) return;

    pie.innerHTML = pieTarjeta(linea);
    cambiados.forEach((trabajador) => {
        const fila = document.querySelector(`tr[data-trabajador="${trabajador.id}"]`);

        if (!fila) return;

        fila.outerHTML = filaTrabajador(linea, trabajador);
        [3, 6].forEach((indice) => document.querySelector(`tr[data-trabajador="${trabajador.id}"]`).cells[indice].classList.add('tx-recalculo'));
    });
});

const sinKg = (linea) => linea.pesoRacimo <= 0 && hayPesos();

const textoKg = (linea) => sinKg(linea)
    ? '<span class="tx-sin-kg">Sin kg — el lote no tiene peso promedio</span>'
    : fmt(linea.kg);

const tooltipKg = (linea) => {
    if (linea.pesoRacimo <= 0) {
        return `${fmt(linea.racimos)} racimos sin peso promedio · reparto proporcional = ${fmt(linea.kg)} kg`;
    }

    const base = linea.racimos * linea.pesoRacimo;
    const ajuste = linea.kg - base;

    return `${fmt(linea.racimos)} racimos × ${fmt(linea.pesoRacimo, 2)} kg prom. = ${fmt(base)} kg`
        + ` · ajuste proporcional ${ajuste >= 0 ? '+' : '−'}${fmt(Math.abs(ajuste))} kg`;
};

const renderizar = () => {
    $id('lista_lineas').innerHTML = estado.lineas.map((linea, indice) => tarjeta(linea, indice)).join('');
    mostrar($id('estado_sin_lineas'), estado.tiqueteCargado && estado.lineas.length === 0);
    mostrar($id('lista_lineas'), estado.lineas.length > 0);
    mostrar($id('pie_estimado'), estado.lineas.length > 0);
    mostrar($id('nota_sin_pesos'), estado.lineas.length > 0 && !hayPesos());
    $id('texto_sin_lineas').textContent = `Agregue líneas para repartir los ${fmt(racimosTiquete())} racimos del tiquete.`;
    pintarMedidor();
    pintarTotales();
    pintarPlaneadas();
};

const refrescarDerivados = () => {
    estado.lineas.forEach((linea) => {
        const cifra = document.querySelector(`[data-kg="${linea.id}"]`);

        if (cifra) {
            cifra.innerHTML = textoKg(linea);
            cifra.title = tooltipKg(linea);
        }
    });

    document.querySelectorAll('#lista_lineas [data-faltantes]').forEach((columna) => {
        const boton = columna.querySelector('[data-accion="asignar-faltantes"]');

        boton.title = `Suma a esta línea los ${fmt(faltantes())} racimos que faltan para cuadrar con el tiquete.`;
        boton.innerHTML = `<i class="bi bi-check2-square me-1" aria-hidden="true"></i>Asignar faltantes (+${fmt(faltantes())})`;
        mostrar(columna, faltantes() > 0);
    });

    pintarMedidor();
    pintarTotales();
};

const marcarFlash = (cambios) => {
    cambios.forEach(({ id, delta }) => {
        const objetivo = document.querySelector(`[data-kg="${id}"]`);

        if (!objetivo) return;

        objetivo.classList.remove('tx-recalculo');
        void objetivo.offsetWidth;
        objetivo.classList.add('tx-recalculo');

        const ranura = document.querySelector(`[data-delta="${id}"]`);

        if (!ranura || delta === 0) return;

        ranura.innerHTML = `<span class="gp-delta ${delta > 0 ? 'sube' : 'baja'}">${delta > 0 ? '+' : '−'}${fmt(Math.abs(delta))}</span>`;
        setTimeout(() => { ranura.innerHTML = ''; }, 3000);
    });
};

let temporizador = null;

const cuerpoBase = () => ({
    empresa: empresaId(),
    anio: $id('anio').value,
    mes: $id('periodo').value,
    fecha: $id('fecha').value,
    observacion: $id('observacion').value,
    tiquete: texto($id('tiquete').value),
    planta: '',
    pesoBruto: aNumero($id('pesoBruto').value),
    pesoTara: aNumero($id('pesoTara').value),
    sacos: Math.round(aNumero($id('sacos').value)),
    racimos: racimosTiquete(),
    vehiculo: texto($id('vehiculo').value),
    remolque: texto($id('remolque').value),
    codigoConductor: texto($id('codigoConductor').value),
    nombreConductor: texto($id('nombreConductor').value),
    externo: estado.externo ? 1 : 0,
    extractora: $id('extractora').value,
    confirmaFaltante: 0,
    confirmaLineasSinTrabajadores: 0,
    lineas: JSON.stringify(estado.lineas.map((linea) => ({
        finca: linea.finca,
        lote: linea.lote,
        fecha: linea.fecha,
        racimos: linea.racimos,
        sacos: linea.sacos,
        trabajadores: linea.trabajadores.map((trabajador) => ({
            tercero: trabajador.tercero,
            novedad: trabajador.novedad,
            cantidad: trabajador.cantidad,
            jornales: trabajador.jornales,
            precioLabor: trabajador.precio,
            racimos: trabajador.racimos ?? linea.racimos,
            contratista: trabajador.contratista || 0,
            fechaNovedad: trabajador.fecha
        }))
    })))
});

const previsualizar = () => {
    clearTimeout(temporizador);

    temporizador = setTimeout(async () => {
        const turno = ++turnoPrevio;

        if (estado.lineas.length === 0 || !estado.tiqueteCargado) {
            estado.fallaPrevio = false;
            estado.lineas.forEach((linea) => { linea.kg = 0; });
            refrescarDerivados();

            return;
        }

        let resultado;

        try {
            resultado = await pedir('previsualizar', estado.edicion ? { ...cuerpoBase(), numero: estado.edicion } : cuerpoBase());
        } catch (error) {
            if (turno !== turnoPrevio) return;

            estado.fallaPrevio = texto(error.datos?.message) || true;
            pintarMedidor();

            return;
        }

        if (turno !== turnoPrevio) return;

        estado.fallaPrevio = false;

        const cambios = [];
        const antes = fotoCantidades();

        (resultado.lineas || []).forEach((fila, indice) => {
            const linea = estado.lineas[indice];

            if (!linea) return;

            const nuevo = Number(fila.cantidad || 0);

            if (Math.round(nuevo) !== Math.round(linea.kg)) {
                cambios.push({ id: linea.id, delta: Math.round(nuevo - linea.kg) });
            }

            linea.kg = nuevo;
        });

        estado.lineas.forEach(reescalar);
        refrescarDerivados();
        refrescarTrabajadores(antes);
        marcarFlash(cambios);
    }, 400);
};

const camposTiquete = () => ['extractora', 'codigoConductor', 'nombreConductor', 'vehiculo', 'remolque',
    'fecha', 'racimos', 'sacos', 'pesoBruto', 'pesoTara'];

const habilitarTiquete = (habilitado) => {
    camposTiquete().forEach((id) => { $id(id).disabled = !habilitado; });
    ['bloque_capturable', 'col_extractora'].forEach((id) => $id(id).classList.toggle('tx-solo-lectura', !habilitado));
    ['col_racimos', 'col_sacos', 'col_tara'].forEach((id) => $id(id).classList.remove('tx-editable'));
    $('#extractora').prop('disabled', !habilitado).trigger('change.select2');
};

const habilitarCaptura = () => {
    ['racimos', 'sacos', 'pesoTara'].forEach((id) => { $id(id).disabled = false; });
    ['col_racimos', 'col_sacos', 'col_tara'].forEach((id) => $id(id).classList.add('tx-editable'));
};

const calcularNeto = () => {
    const bruto = aNumero($id('pesoBruto').value);
    const tara = aNumero($id('pesoTara').value);
    const invalido = tara > bruto;

    $id('pesoTara').classList.toggle('is-invalid', invalido);
    mostrar($id('error_tara'), invalido);
    $id('pesoBruto').classList.remove('is-invalid');
    mostrar($id('error_bruto'), false);
    $id('pesoNeto').classList.remove('is-invalid');
    mostrar($id('error_neto'), false);
    $id('pesoNeto').value = invalido ? '' : fmt(Math.max(0, bruto - tara), 2);

    pintarMedidor();
    previsualizar();
};

const activarDetalle = () => {
    estado.tiqueteCargado = estado.encontrado || estado.externo;
    mostrar($id('estado_sin_tiquete'), !estado.tiqueteCargado);
    $id('detalle_cuerpo').classList.toggle('tx-inactivo', !estado.tiqueteCargado);
    $id('detalle_cuerpo').toggleAttribute('inert', !estado.tiqueteCargado);
    actualizarAgregar();
    renderizar();
};

const actualizarAgregar = () => {
    const boton = $id('btn_agregar_linea');
    const sinLotes = $id('cap_lote').dataset.sinLotes === '1';

    boton.disabled = !estado.tiqueteCargado || racimosTiquete() <= 0 || sinLotes;
    boton.title = !estado.tiqueteCargado
        ? 'Primero cargue o capture el tiquete'
        : (racimosTiquete() <= 0
            ? 'Indique primero los racimos del tiquete'
            : (sinLotes ? 'Esta finca no tiene lotes' : 'Agregar la línea al detalle'));
};

const notaTiquete = (mensaje, conBoton) => {
    $id('nota_tiquete_texto').innerHTML = mensaje;
    mostrar($id('nota_tiquete'), mensaje !== '');
    mostrar($id('nota_tiquete_acciones'), conBoton === true);
};

const limpiarCaptura = () => {
    const selector = $id('cap_lote');

    estado.lotes = [];
    $('#cap_finca').val('').trigger('change.select2');
    selector.innerHTML = '<option value="">Seleccione una finca primero</option>';
    selector.disabled = true;
    selector.dataset.sinLotes = '0';
    $('#cap_lote').trigger('change.select2');
    $id('cap_racimos').value = '';
    $id('cap_sacos').value = '0';
    estado.fechaLineaTocada = false;
    $id('cap_fecha_linea').classList.remove('is-invalid');
    errorCaptura('error_fecha_linea', false);
    $id('ayuda_peso').className = 'gp-subtexto';
    $id('ayuda_peso').textContent = '';
};

const limpiarTiquete = () => {
    camposTiquete().forEach((id) => {
        if (id === 'extractora') {
            $('#extractora').val('').trigger('change.select2');

            return;
        }

        $id(id).value = '';
    });
    $id('pesoNeto').value = '';
    revisarPeriodo();
};

const buscarTiquete = async () => {
    const numero = texto($id('tiquete').value);
    const entrada = $id('tiquete');
    const error = $id('grupo_tiquete').querySelector('.invalid-feedback');

    entrada.classList.remove('is-invalid');
    notaTiquete('', false);

    if (numero === '') {
        entrada.classList.add('is-invalid');
        error.textContent = 'Indique el número del tiquete.';
        entrada.focus();

        return;
    }

    const boton = $id('btn_buscar_tiquete');

    ocupar(boton, true, 'Buscando…');

    let resultado;

    try {
        resultado = await pedir('buscarTiquete', { empresa: empresaId(), tiquete: numero });
    } catch (fallo) {
        avisar(fallo);
        ocupar(boton, false);

        return;
    }

    ocupar(boton, false);

    const usos = resultado.usos || [];

    if (!resultado.prellenado) {
        entrada.classList.add('is-invalid');
        error.textContent = `No se encontró el tiquete ${numero}.`;
        notaTiquete(`No se encontró el tiquete <span class="font-monospace">${escaparHtml(numero)}</span>.`, true);
        entrada.focus();

        return;
    }

    const datos = resultado.prellenado;

    estado.externo = false;
    estado.encontrado = true;
    $id('tiquete_externo').checked = false;
    habilitarTiquete(false);
    mostrar($id('nota_externo'), false);

    $id('fecha').value = texto(datos.fecha).substring(0, 10);
    revisarPeriodo();
    $id('pesoBruto').value = fmt(Number(datos.pesoBruto || 0), 2);
    $id('pesoTara').value = '';
    $id('pesoNeto').value = '';
    $id('codigoConductor').value = texto(datos.codigoConductor);
    $id('nombreConductor').value = texto(datos.nombreConductor);
    $id('vehiculo').value = texto(datos.vehiculo);
    $id('remolque').value = texto(datos.remolque);
    $('#extractora').val(texto(datos.extractora)).trigger('change.select2');

    habilitarCaptura();

    $id('bloque_pesos').classList.remove('tx-flash');
    void $id('bloque_pesos').offsetWidth;
    $id('bloque_pesos').classList.add('tx-flash');

    Toast.fire({ icon: 'success', title: 'Tiquete cargado.' });

    if (usos.length > 0) {
        const consecutivos = usos.slice(0, 5).map((fila) => `<span class="font-monospace">${escaparHtml(texto(fila.numero))}</span>`).join(', ');
        const resto = usos.length > 5 ? ` y ${fmt(usos.length - 5)} más` : '';

        notaTiquete(`Este tiquete ya está registrado en ${fmt(usos.length)} transacción(es): ${consecutivos}${resto}. Puede continuar si va a repartir el resto del viaje.`, false);
    }

    calcularNeto();
    activarDetalle();
    $id('racimos').focus();
};

const tieneDatosTiquete = () => camposTiquete().some((id) => texto($id(id).value) !== '');

const alternarExterno = async (encendido) => {
    if (!encendido && tieneDatosTiquete()) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: '¿Descartar los datos del tiquete?',
            showCancelButton: true,
            confirmButtonText: 'Sí, descartar',
            cancelButtonText: 'Cancelar'
        });

        if (!respuesta.isConfirmed) {
            $id('tiquete_externo').checked = true;

            return;
        }

        limpiarTiquete();
        estado.encontrado = false;
    }

    estado.externo = encendido;
    habilitarTiquete(encendido);
    mostrar($id('nota_externo'), encendido);
    notaTiquete('', false);
    $id('tiquete').classList.remove('is-invalid');

    if (encendido) {
        $id('card_tiquete').classList.add('gp-revelar');
        setTimeout(() => $id('card_tiquete').classList.remove('gp-revelar'), 300);
        $('#extractora').select2('open');
    }

    calcularNeto();
    activarDetalle();
};

const cargarLotes = async (finca) => {
    const selector = $id('cap_lote');

    estado.lotes = [];
    selector.dataset.sinLotes = '0';
    selector.innerHTML = '<option value="">Cargando lotes…</option>';
    selector.disabled = true;
    $('#cap_lote').trigger('change.select2');
    $id('ayuda_peso').textContent = '';
    actualizarAgregar();

    if (finca === '') {
        selector.innerHTML = '<option value="">Seleccione una finca primero</option>';
        $('#cap_lote').trigger('change.select2');
        actualizarAgregar();

        return;
    }

    let resultado;

    try {
        resultado = await pedir('lotes', { empresa: empresaId(), finca });
    } catch (error) {
        avisar(error);
        selector.innerHTML = '<option value="">Seleccione una finca primero</option>';
        $('#cap_lote').trigger('change.select2');
        actualizarAgregar();

        return;
    }

    estado.lotes = resultado.filas || [];

    if (estado.lotes.length === 0) {
        selector.innerHTML = '<option value="">Esta finca no tiene lotes</option>';
        selector.dataset.sinLotes = '1';
        $('#cap_lote').trigger('change.select2');
        actualizarAgregar();

        return;
    }

    selector.innerHTML = '<option value="">Seleccione una opción…</option>'
        + estado.lotes.map((lote) => `<option value="${escaparHtml(lote.codigo)}">${escaparHtml(lote.codigo)}</option>`).join('');
    selector.disabled = false;
    $('#cap_lote').trigger('change.select2');
    actualizarAgregar();
    $('#cap_lote').select2('open');
};

const consultarPeso = async (finca, lote) => {
    const ayuda = $id('ayuda_peso');

    ayuda.className = 'gp-subtexto';
    ayuda.textContent = '';

    if (finca === '' || lote === '') return null;

    let resultado;

    try {
        resultado = await pedir('pesoPromedio', {
            empresa: empresaId(), fecha: $id('fecha').value, finca, lote,
            ...($id('periodo').value ? { anio: $id('anio').value, mes: $id('periodo').value } : {})
        });
    } catch (error) {
        avisar(error);

        return null;
    }

    if (Number(resultado.existe) === 1 && Number(resultado.pesoRacimo) > 0) {
        ayuda.textContent = `Peso prom.: ${fmt(resultado.pesoRacimo, 2)} kg`;

        return Number(resultado.pesoRacimo);
    }

    ayuda.className = 'gp-subtexto tx-texto-ambar';
    ayuda.innerHTML = '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Este lote no tiene peso promedio en el periodo del tiquete: aportará racimos, pero no kg.';

    return 0;
};

const destacar = (id) => {
    const nodo = document.querySelector(`.tx-tarjeta[data-linea="${id}"]`);

    if (!nodo) return;

    nodo.classList.remove('tx-tarjeta--destacada');
    void nodo.offsetWidth;
    nodo.classList.add('tx-tarjeta--destacada');
    nodo.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => nodo.classList.remove('tx-tarjeta--destacada'), 1600);
};

const errorCaptura = (id, visible) => mostrar($id(id), visible);

const agregarLinea = async () => {
    ['error_finca', 'error_lote', 'error_fecha_linea', 'error_racimos'].forEach((id) => errorCaptura(id, false));
    $id('cap_fecha_linea').classList.remove('is-invalid');

    const finca = $id('cap_finca').value;
    const lote = $id('cap_lote').value;
    const fecha = $id('cap_fecha_linea').value;
    const racimos = Math.round(aNumero($id('cap_racimos').value));

    if (finca === '') {
        errorCaptura('error_finca', true);
        $('#cap_finca').select2('open');

        return;
    }

    if (lote === '') {
        errorCaptura('error_lote', true);
        $('#cap_lote').select2('open');

        return;
    }

    if (fecha === '' || fechaFuera(fecha)) {
        $id('error_fecha_linea').textContent = fecha === '' ? 'Indique la fecha.' : 'La fecha debe estar dentro del periodo.';
        errorCaptura('error_fecha_linea', true);
        $id('cap_fecha_linea').classList.add('is-invalid');
        $id('cap_fecha_linea').focus();

        return;
    }

    if (racimos <= 0) {
        errorCaptura('error_racimos', true);
        $id('cap_racimos').focus();

        return;
    }

    const peso = await consultarPeso(finca, lote);

    if (peso === null) return;

    if (peso === 0) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: 'El lote no tiene peso promedio',
            html: `El lote <b>${escaparHtml(lote)}</b> no tiene peso promedio registrado para el periodo del tiquete.`
                + ' Sus racimos sí cuentan en el reparto, pero el sistema no le asignará kg: el peso neto se repartirá entre las demás líneas.'
                + ' ¿Desea agregarla de todos modos?',
            showCancelButton: true,
            confirmButtonText: 'Sí, agregarla',
            cancelButtonText: 'Cancelar'
        });

        if (!respuesta.isConfirmed) return;
    }

    const opcion = $id('cap_finca').selectedOptions[0];

    estado.lineas.push({
        id: estado.secuencia++,
        finca,
        fincaNombre: texto(opcion?.text).split('—').slice(1).join('—').trim() || finca,
        lote,
        fecha,
        racimos,
        sacos: Math.round(aNumero($id('cap_sacos').value)),
        pesoRacimo: peso,
        kg: 0,
        trabajadores: []
    });

    $id('cap_racimos').value = '';
    $id('cap_sacos').value = '0';

    renderizar();

    const nodo = document.querySelector(`.tx-tarjeta[data-linea="${estado.lineas[estado.lineas.length - 1].id}"]`);

    if (nodo) nodo.classList.add('gp-revelar');

    $('#cap_lote').val('').trigger('change.select2');
    $('#cap_lote').select2('open');
    previsualizar();
};

const eliminarLinea = async (id) => {
    const linea = estado.lineas.find((item) => item.id === id);

    if (!linea) return;

    const posicion = estado.lineas.indexOf(linea) + 1;

    const respuesta = await Swal.fire({
        icon: 'warning',
        title: linea.planeacion ? '¿Quitar la actividad planeada?' : `¿Eliminar la línea ${escaparHtml(String(posicion))}?`,
        html: linea.planeacion ? 'Volverá a estar disponible en Planeaciones completadas.' : `Se eliminarán también los ${escaparHtml(String(linea.trabajadores.length))} trabajadores registrados en ella.`,
        showCancelButton: true,
        confirmButtonText: linea.planeacion ? 'Sí, quitar' : 'Sí, eliminar',
        confirmButtonColor: '#d6293e',
        cancelButtonText: 'Cancelar',
        focusCancel: true
    });

    if (!respuesta.isConfirmed) return;

    estado.lineas = estado.lineas.filter((item) => item.id !== id);
    renderizar();
    previsualizar();
};

const cargarLabores = async () => {
    if (estado.labores) return estado.labores;

    const resultado = await pedir('labores', { empresa: empresaId() });

    estado.labores = resultado.filas || [];

    return estado.labores;
};

const tercerosModal = () => $('#trab_tercero').select2('data')
    .filter((opcion) => texto(opcion.id) !== '')
    .map((opcion) => ({ tercero: opcion.id, nombre: texto(opcion.text), codigo: texto(opcion.codigo ?? opcion.element?.dataset.codigo) }));

const precioDe = (tercero) => {
    const sugerido = estado.modal.precios[tercero];

    return estado.modal.manual || tercerosModal().length <= 1 || sugerido === null || sugerido === undefined
        ? aNumero($id('trab_precio').value)
        : sugerido;
};

const sinPrecioModal = () => (estado.modal.manual ? [] : tercerosModal().filter((item) => estado.modal.precios[item.tercero] === null));

const preciosDistintos = () => new Set(tercerosModal().map((item) => precioDe(item.tercero))).size > 1;

const totalModal = () => {
    const seleccion = tercerosModal();
    const cantidad = aNumero($id('trab_cantidad').value);
    const total = seleccion.length > 1
        ? seleccion.reduce((suma, item) => suma + cantidad * precioDe(item.tercero), 0)
        : cantidad * aNumero($id('trab_precio').value);

    $id('trab_total').value = fmt(total, 2);
    $id('ayuda_total').textContent = seleccion.length > 1
        ? (preciosDistintos() ? 'Suma de cantidad × precio de cada tercero' : `Cantidad × precio × ${seleccion.length} terceros`)
        : 'Cantidad × precio.';
};

const pintarPrecio = () => {
    const seleccion = tercerosModal();
    const ayuda = $id('ayuda_precio');

    if (seleccion.length > 0 && $id('trab_fecha').value === '') {
        ayuda.textContent = 'Indique la fecha para consultar el precio';
    } else if (seleccion.length <= 1) {
        const sugerido = seleccion.length === 1 ? estado.modal.precios[seleccion[0].tercero] : undefined;

        ayuda.textContent = sugerido === undefined ? '' : (sugerido === null ? 'Sin precio en la lista' : 'Precio sugerido del lote');
    } else {
        ayuda.innerHTML = estado.modal.manual
            ? '<span class="gp-chip-fila ambar">Precio manual: aplica a todos</span> <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" id="btn_usar_sugeridos">Usar sugeridos</button>'
            : `Se usará el precio sugerido de cada tercero.${preciosDistintos() ? ' <span class="gp-chip-fila tx-chip-neutro">Precios distintos</span>' : ''}`
                + (sinPrecioModal().length > 0 ? ` <span class="gp-chip-fila ambar" title="${escaparHtml(sinPrecioModal().map((item) => item.nombre).join(', '))}">${sinPrecioModal().length} sin precio sugerido: usarán el precio escrito</span>` : '');
    }

    totalModal();
};

const pintarSeleccion = () => {
    const total = tercerosModal().length;
    const contador = $id('trab_contador');

    contador.textContent = `${total} ${total === 1 ? 'seleccionado' : 'seleccionados'}`;
    mostrar(contador, total > 0 && $id('trab_tercero').multiple);
    $id('btn_guardar_trabajador').innerHTML = total > 1
        ? `<i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar ${total} trabajadores`
        : '<i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar';
    pintarCantidad();
    pintarPrecio();
};

const pintarCantidad = () => {
    const linea = estado.lineas.find((item) => item.id === estado.modal.linea);

    if (!linea) return;

    const campo = $id('trab_cantidad');
    const ayuda = $id('ayuda_cantidad');
    const novedad = texto($id('trab_novedad').value);
    const actual = linea.trabajadores.find((item) => item.id === estado.modal.trabajador) || null;
    const otros = grupoDe(linea, novedad).filter((item) => item !== actual);
    const seleccion = tercerosModal().length;
    const editable = actual !== null && novedad === actual.novedad && otros.length > 0 && Math.round(linea.kg * 100) > 0;

    if (editable && !estado.modal.editable) {
        campo.value = fmt(actual.cantidad, 2);
        estado.modal.igualar = false;
    }

    estado.modal.editable = editable;
    campo.readOnly = !editable;
    campo.classList.toggle('tx-neto', !editable);
    campo.placeholder = editable ? '' : 'Automática';
    document.querySelector('label[for="trab_cantidad"]').innerHTML = editable ? 'Cantidad (kg) <span class="text-danger">*</span>' : 'Cantidad c/u (kg)';

    if (editable) {
        const cada = fmt(repartir(Math.max(0, linea.kg - aNumero(campo.value)), otros.map(() => 1))[0], 2);

        ayuda.innerHTML = `<span class="d-block">Máx. ${fmt(linea.kg, 2)} kg (total de la línea).</span>`
            + (otros.length === 1 ? `El otro quedaría con ${cada} kg.` : `Los otros ${otros.length} quedarían con ${cada} kg c/u.`)
            + (actual.manual && !estado.modal.igualar ? '<button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" id="btn_repartir_iguales">Repartir en partes iguales</button>' : '');

        return;
    }

    campo.classList.remove('is-invalid');
    mostrar($id('error_trab_cantidad'), false);

    if (novedad === '' || seleccion === 0) {
        campo.value = '';
        ayuda.textContent = 'Se reparte entre los trabajadores de la misma actividad.';

        return;
    }

    if (sinKg(linea) || Math.round(linea.kg * 100) <= 0) {
        campo.value = fmt(0, 2);
        ayuda.innerHTML = '<span class="tx-texto-ambar">La línea no tiene kg asignados; la cantidad se calculará cuando los tenga.</span>';

        return;
    }

    const total = otros.length + seleccion;

    campo.value = fmt(repartir(linea.kg, Array(total).fill(1))[0], 2);
    ayuda.innerHTML = (total === 1
        ? 'Recibe todos los kg de la línea.'
        : `Reparto igual entre ${total} trabajadores${otros.length > 0 ? ` (incluye ${otros.length} ya ${otros.length === 1 ? 'registrado' : 'registrados'})` : ''}.`)
        + (otros.some((item) => item.manual) ? '<span class="tx-texto-ambar d-block">Los ajustes manuales de esta actividad se reemplazan por el reparto igual.</span>' : '');
};

const repartirIguales = () => {
    const linea = estado.lineas.find((item) => item.id === estado.modal.linea);
    const actual = linea?.trabajadores.find((item) => item.id === estado.modal.trabajador);

    if (!actual) return;

    const grupo = grupoDe(linea, actual.novedad);

    estado.modal.igualar = true;
    $id('trab_cantidad').value = fmt(repartir(linea.kg, grupo.map(() => 1))[grupo.indexOf(actual)], 2);
    $id('trab_cantidad').classList.remove('is-invalid');
    mostrar($id('error_trab_cantidad'), false);
    pintarCantidad();
    totalModal();
};

const usarSugeridos = () => {
    const primero = tercerosModal()[0];
    const sugerido = primero ? estado.modal.precios[primero.tercero] : null;

    estado.modal.manual = false;

    if (sugerido !== null && sugerido !== undefined) $id('trab_precio').value = fmt(sugerido, 2);

    pintarPrecio();
};

const sugerirPrecio = async () => {
    const linea = estado.lineas.find((item) => item.id === estado.modal.linea);
    const seleccion = tercerosModal();
    const novedad = $id('trab_novedad').value;
    const turno = ++turnoPrecio;

    estado.modal.precios = {};
    pintarSeleccion();

    if (!linea || seleccion.length === 0 || novedad === '' || $id('trab_fecha').value === '') return;

    const precios = await Promise.all(seleccion.map((item) => pedir('precioLabor', {
        empresa: empresaId(), novedad, fecha: $id('trab_fecha').value,
        finca: linea.finca, lote: linea.lote, tercero: item.tercero
    }).then((resultado) => (Number(resultado.existe) === 1 ? Number(resultado.precio || 0) : null)).catch(() => null)));

    if (turno !== turnoPrecio) return;

    seleccion.forEach((item, indice) => {
        if (precios[indice] !== undefined) estado.modal.precios[item.tercero] = precios[indice];
    });

    const primero = estado.modal.precios[seleccion[0].tercero];

    if (primero !== null && primero !== undefined && (seleccion.length === 1 || !estado.modal.manual)) {
        $id('trab_precio').value = fmt(primero, 2);
    }

    pintarPrecio();
};

const iniciarTercero = (multiple) => {
    if ($('#trab_tercero').hasClass('select2-hidden-accessible')) $('#trab_tercero').select2('destroy');

    $id('trab_tercero').multiple = multiple;

    $('#trab_tercero').select2({
        width: '100%', dropdownParent: $('#modalTrabajador'),
        placeholder: multiple ? 'Busque por código, identificación o nombre' : 'Código, identificación o nombre…',
        allowClear: true, minimumInputLength: 3, language: IDIOMA_SELECT2,
        ajax: {
            delay: 250,
            data: (params) => ({ termino: params.term }),
            transport: (params, exito, fallo) => {
                pedir('terceros', { empresa: empresaId(), termino: params.data.termino }).then(exito).catch(fallo);

                return { abort: () => { } };
            },
            processResults: (datos) => ({
                results: (datos.filas || []).map((fila) => ({ id: fila.nit, text: fila.razonSocial, nit: fila.nit, codigo: texto(fila.codigo) }))
            })
        },
        templateResult: (opcion) => opcion.nit
            ? $(`<span class="d-flex align-items-baseline gap-2">${opcion.codigo ? `<span class="gp-chip-fila verde font-monospace fw-semibold">${escaparHtml(opcion.codigo)}</span>` : ''}`
                + `<span class="text-truncate">${escaparHtml(opcion.text)}</span><span class="gp-subtexto font-monospace ms-auto">${escaparHtml(opcion.nit)}</span></span>`)
            : opcion.text,
        templateSelection: (opcion) => {
            const codigo = texto(opcion.codigo ?? opcion.element?.dataset.codigo);

            if (!opcion.id) return opcion.text;

            return multiple
                ? $(`<span><span class="font-monospace">${escaparHtml(codigo || opcion.id)}</span> ${escaparHtml(texto(opcion.text).split(/\s+/).slice(0, 2).join(' '))}</span>`)
                : (codigo ? $(`<span><span class="font-monospace fw-semibold">${escaparHtml(codigo)}</span> — ${escaparHtml(opcion.text)}</span>`) : opcion.text);
        }
    });
};

const abrirModalTrabajador = async (idLinea, bloqueClave, idTrabajador) => {
    const linea = estado.lineas.find((item) => item.id === idLinea);

    if (!linea) return;

    const bloque = BLOQUES.find((item) => item.clave === bloqueClave) || BLOQUES[0];
    const trabajador = idTrabajador
        ? linea.trabajadores.find((item) => item.id === idTrabajador)
        : null;

    estado.modal = { linea: idLinea, bloque: bloque.clave, trabajador: idTrabajador || null, precios: {}, manual: false, editable: false, igualar: false };

    $id('titulo_modal_trabajador').innerHTML = `<i class="bi bi-person-plus me-1" aria-hidden="true"></i>`
        + `${trabajador ? 'Editar trabajador' : 'Agregar trabajador'} &middot; ${escaparHtml(bloque.rotulo)}`;
    $id('chip_modal_linea').textContent = `Registro ${estado.lineas.indexOf(linea) + 1} — ${linea.lote} · ${fechaCorta(linea.fecha)}`;

    const periodo = periodoActual();

    $id('trab_fecha').min = periodo && !sinFechas(periodo) ? texto(periodo.fechaInicial).substring(0, 10) : '';
    $id('trab_fecha').max = periodo && !sinFechas(periodo) ? texto(periodo.fechaFinal).substring(0, 10) : '';
    $id('trab_fecha').classList.remove('is-invalid');
    $id('trab_fecha').value = trabajador ? trabajador.fecha : (linea.fecha || $id('fecha').value);
    $id('trab_cantidad').classList.remove('is-invalid');
    $id('trab_precio').value = trabajador ? fmt(trabajador.precio, 2) : '';
    $id('trab_jornales').value = trabajador ? fmt(trabajador.jornales, 2) : '0';
    $id('ayuda_precio').textContent = '';
    $id('trab_precio').classList.remove('is-invalid');
    ['error_trab_tercero', 'error_trab_novedad', 'error_trab_cantidad'].forEach((id) => mostrar($id(id), false));

    const opcionesTercero = trabajador
        ? `<option value="${escaparHtml(trabajador.tercero)}" data-codigo="${escaparHtml(trabajador.terceroCodigo)}" selected>${escaparHtml(trabajador.terceroNombre)}</option>`
        : '';

    iniciarTercero(!trabajador);
    $id('trab_tercero').innerHTML = opcionesTercero;
    $('#trab_tercero').trigger('change.select2');

    let labores = [];

    try {
        labores = await cargarLabores();
    } catch (error) {
        avisar(error);
    }

    if (labores.length === 0) {
        Toast.fire({ icon: 'warning', title: escaparHtml(SIN_LABORES) });
        renderizar();

        return;
    }

    $id('trab_novedad').innerHTML = '<option value="">Seleccione una opción…</option>'
        + labores.map((labor) => `<option value="${escaparHtml(labor.codigo)}">${escaparHtml(labor.codigo)} — ${escaparHtml(labor.descripcion)}</option>`).join('');

    if (trabajador) $id('trab_novedad').value = trabajador.novedad;

    $('#trab_novedad').trigger('change.select2');
    pintarSeleccion();

    bootstrap.Modal.getOrCreateInstance($id('modalTrabajador')).show();
};

const guardarTrabajador = async () => {
    const linea = estado.lineas.find((item) => item.id === estado.modal.linea);

    if (!linea) return;

    const seleccion = tercerosModal();
    const novedad = texto($id('trab_novedad').value);
    const crudo = texto($id('trab_cantidad').value);
    const cantidad = Math.round(aNumero(crudo) * 100) / 100;
    const errorCantidad = !estado.modal.editable ? ''
        : (crudo !== '' && !/^-?(\d+|\d{1,3}(\.\d{3})+)([.,]\d+)?$/.test(crudo.replace(/\s/g, '')) ? 'Indique una cantidad válida.'
            : (cantidad <= 0 ? 'Indique una cantidad mayor que cero.'
                : (Math.round(cantidad * 100) > Math.round(linea.kg * 100) ? `La cantidad no puede superar los ${fmt(linea.kg, 2)} kg de la línea.` : '')));

    mostrar($id('error_trab_tercero'), seleccion.length === 0);
    $id('error_trab_tercero').textContent = 'Seleccione al menos un tercero';
    mostrar($id('error_trab_novedad'), novedad === '');
    $id('error_trab_novedad').textContent = 'Seleccione la actividad.';
    $id('trab_cantidad').classList.toggle('is-invalid', errorCantidad !== '');
    mostrar($id('error_trab_cantidad'), errorCantidad !== '');
    $id('error_trab_cantidad').textContent = errorCantidad;

    const faltaPrecio = aNumero($id('trab_precio').value) <= 0 && (seleccion.length === 1 || (seleccion.length > 1 && sinPrecioModal().length > 0));

    $id('trab_precio').classList.toggle('is-invalid', faltaPrecio);
    $id('trab_precio').parentElement.querySelector('.invalid-feedback').textContent = seleccion.length === 1
        ? 'Indique el precio (mayor que cero).'
        : 'Escriba el precio para los terceros sin precio sugerido.';

    const campoFecha = $id('trab_fecha');
    const fechaInvalida = campoFecha.value === '' || (campoFecha.min !== '' && campoFecha.value < campoFecha.min) || (campoFecha.max !== '' && campoFecha.value > campoFecha.max);

    campoFecha.classList.toggle('is-invalid', fechaInvalida);
    campoFecha.parentElement.querySelector('.invalid-feedback').textContent = campoFecha.value === '' ? 'Indique la fecha.' : 'La fecha debe estar dentro del periodo';

    if (seleccion.length === 0 || novedad === '' || errorCantidad !== '' || faltaPrecio || fechaInvalida) return;

    const datos = {
        novedad,
        novedadNombre: texto($id('trab_novedad').selectedOptions[0]?.text).split('—').slice(1).join('—').trim(),
        jornales: aNumero($id('trab_jornales').value),
        fecha: $id('trab_fecha').value,
        bloque: estado.modal.bloque
    };

    const repetidos = seleccion.filter((item) => linea.trabajadores.some((trabajador) => trabajador.id !== estado.modal.trabajador
        && trabajador.tercero === item.tercero && trabajador.novedad === novedad && trabajador.fecha === datos.fecha));

    if (repetidos.length > 0) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: 'Terceros ya registrados',
            html: `Con la misma labor y fecha en esta línea ya ${repetidos.length === 1 ? 'está registrado' : 'están registrados'}: <b>${repetidos.map((item) => escaparHtml(item.nombre)).join(', ')}</b>. ¿Desea continuar?`,
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        });

        if (!respuesta.isConfirmed) return;
    }

    const ids = [];
    const antes = fotoCantidades();

    if (estado.modal.trabajador) {
        const actual = linea.trabajadores.find((item) => item.id === estado.modal.trabajador);
        const origen = actual.novedad;
        const ajustada = estado.modal.editable && !estado.modal.igualar && Math.round(cantidad * 100) !== Math.round(actual.cantidad * 100);

        Object.assign(actual, datos, { tercero: seleccion[0].tercero, terceroNombre: seleccion[0].nombre, terceroCodigo: seleccion[0].codigo, precio: aNumero($id('trab_precio').value) });
        ids.push(actual.id);

        if (ajustada) {
            ajustar(linea, actual, cantidad);
        } else if (!estado.modal.editable || estado.modal.igualar) {
            igualar(linea, novedad);
            igualar(linea, origen);
        }
    } else {
        seleccion.forEach((item) => {
            const id = estado.secuenciaTrabajador++;

            ids.push(id);
            linea.trabajadores.push({ id, ...datos, cantidad: 0, tercero: item.tercero, terceroNombre: item.nombre, terceroCodigo: item.codigo, precio: precioDe(item.tercero) });
        });
        igualar(linea, novedad);
    }

    bootstrap.Modal.getOrCreateInstance($id('modalTrabajador')).hide();
    renderizar();
    ids.forEach((id) => delete antes[id]);
    refrescarTrabajadores(antes);

    ids.forEach((id) => document.querySelector(`tr[data-trabajador="${id}"]`)?.classList.add('gp-flash-guardado'));
};

const eliminarTrabajador = async (idLinea, idTrabajador) => {
    const linea = estado.lineas.find((item) => item.id === idLinea);

    if (!linea) return;

    const respuesta = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar el trabajador?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (!respuesta.isConfirmed) return;

    const quitado = linea.trabajadores.find((item) => item.id === idTrabajador);
    const antes = fotoCantidades();

    linea.trabajadores = linea.trabajadores.filter((item) => item.id !== idTrabajador);

    if (quitado) igualar(linea, quitado.novedad);

    renderizar();
    refrescarTrabajadores(antes);
};

const congelar = (activo) => ['card_tiquete', 'card_observacion', 'card_detalle']
    .forEach((id) => $id(id).toggleAttribute('inert', activo));

const sellar = (numero) => {
    const chip = $id('chip_estado');

    chip.className = 'gp-chip-fila verde';
    chip.innerHTML = `<i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>Registrada ${escaparHtml(numero)}`;
    $id('btn_guardar').disabled = true;
    $id('btn_cancelar').innerHTML = '<i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nueva transacción';
    $id('btn_cancelar').title = 'Comenzar una transacción nueva';
    estado.sellado = true;
    $id('zona_formulario').insertAdjacentHTML('beforeend', '<div class="tx-velo tx-velo-fijo" id="velo_registrado"></div>');
    congelar(true);
    $id('btn_cancelar').focus();
};

const limpiarTodo = () => {
    const chip = $id('chip_estado');

    modoEdicion(null);
    chip.className = 'gp-chip-fila tx-chip-neutro';
    chip.textContent = 'Sin guardar — el número se asigna al guardar';
    $id('btn_guardar').disabled = false;
    $id('btn_cancelar').innerHTML = $id('btn_cancelar').dataset.original;
    $id('btn_cancelar').title = 'Cancelar la transacción';
    $id('velo_registrado')?.remove();
    congelar(false);
    estado.sellado = false;
    estado.lineas = [];
    quitarPlaneacion();
    estado.externo = false;
    estado.encontrado = false;
    estado.tiqueteCargado = false;
    $id('tiquete_externo').checked = false;
    $id('tiquete').value = '';
    $id('tiquete').classList.remove('is-invalid');
    $id('observacion').value = '';
    limpiarCaptura();
    limpiarTiquete();
    habilitarTiquete(false);
    notaTiquete('', false);
    mostrar($id('nota_externo'), false);
    $id('anio').value = $id('periodo').dataset.anio;
    cargarPeriodos($id('anio').value);
    calcularNeto();
    activarDetalle();
    $id('tiquete').focus();
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

    if (texto(datos.destino) !== 'produccion') {
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

    const fecha = texto(datos.fecha).substring(0, 10);
    const anio = fecha.substring(0, 4);
    const anios = Array.from($id('anio').options);

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

    revisarPeriodo();
    await agregarPlaneaciones([{ ...datos, linea: Number(linea) }]);
};

const ESTADOS_PLA = { COMPLETADA: 'Completada', PARCIAL: 'Parcial' };
const pla = { filas: [], cargando: false, error: '', seleccion: new Set(), turno: 0, agregando: false };

const planeadas = () => estado.lineas.filter((linea) => linea.planeacion);
const plaAgregada = (id) => estado.lineas.some((linea) => linea.planeacion?.id === id);
const cantPla = (valor, um) => Number(valor || 0).toLocaleString('es-CO', { maximumFractionDigits: /RAC/i.test(um) ? 0 : 3 });
const sinTildes = (valor) => texto(valor).normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
const pluralPla = (cantidad, uno, varios) => `${fmt(cantidad)} ${cantidad === 1 ? uno : varios}`;

const chipPlaneacion = (planeacion) => `<span class="gp-chip-ambito seccion text-nowrap" title="Planeación del ${fechaCorta(planeacion.fecha)}${ESTADOS_PLA[planeacion.estado] ? ` · ${ESTADOS_PLA[planeacion.estado]}` : ''}"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Planeación</span>`;

const lineaPlaneada = async (dato) => {
    const fecha = texto(dato.fecha).substring(0, 10);
    const finca = texto(dato.finca);
    const lote = texto(dato.lote);
    const novedad = texto(dato.novedad);
    const novedadNombre = texto(dato.novedadNombre);
    const porRacimos = /RAC/i.test(texto(dato.uMedida));
    const [peso, trabajadores] = await Promise.all([consultarPeso(finca, lote), Promise.all((dato.trabajadores || []).map((trabajador) => pedir('precioLabor', {
        empresa: empresaId(), novedad, fecha, finca, lote, tercero: texto(trabajador.tercero)
    }).then((resultado) => (Number(resultado.existe) === 1 ? Number(resultado.precio || 0) : 0)).catch(() => 0).then((precio) => ({
        id: estado.secuenciaTrabajador++,
        tercero: texto(trabajador.tercero), terceroNombre: texto(trabajador.nombre), terceroCodigo: texto(trabajador.codigo),
        novedad, novedadNombre, cantidad: porRacimos ? 0 : Number(trabajador.cantidad || 0), precio, jornales: 0, fecha, contratista: 0, bloque: 'cosecha',
        ...(porRacimos ? { racimos: Math.round(Number(trabajador.cantidad || 0)) } : {})
    }))))]);
    const opcion = Array.from($id('cap_finca').options).find((item) => texto(item.value) === finca);

    return {
        id: estado.secuencia++,
        finca,
        fincaNombre: texto(dato.fincaNombre) || texto(opcion?.text).split('—').slice(1).join('—').trim() || finca,
        lote,
        fecha,
        racimos: porRacimos ? Math.round(Number(dato.cantidad || 0)) : 0,
        sacos: 0,
        pesoRacimo: peso || 0,
        kg: 0,
        planeacion: { id: Number(dato.linea), fecha, estado: texto(dato.estado).toUpperCase() },
        trabajadores
    };
};

const agregarPlaneaciones = async (lista) => {
    const nuevas = await Promise.all(lista.map(lineaPlaneada));

    $id('ayuda_peso').className = 'gp-subtexto';
    $id('ayuda_peso').textContent = '';
    estado.lineas.push(...nuevas);
    renderizar();
    nuevas.forEach((linea) => document.querySelector(`.tx-tarjeta[data-linea="${linea.id}"]`)?.classList.add('tx-flash'));
    previsualizar();

    return nuevas;
};

const pintarPlaneadas = () => {
    const total = planeadas().length;
    const boton = $id('btn_planeaciones');

    $id('nota_planeacion_texto').textContent = `${pluralPla(total, 'actividad', 'actividades')} de la planeación en el detalle. Revise los valores y guarde.`;
    mostrar($id('nota_planeacion'), total > 0);

    if (!boton) return;

    const disponibles = pla.filas.filter((fila) => !plaAgregada(Number(fila.linea))).length;

    $id('cont_planeaciones').textContent = fmt(disponibles);
    $id('cont_planeaciones').classList.toggle('verde', disponibles > 0);
    boton.disabled = !estado.tiqueteCargado || pla.agregando;
    boton.title = estado.tiqueteCargado ? 'Planeaciones completadas' : 'Cargue el tiquete primero';
};

const motivoPla = (fila) => (fechaFuera(texto(fila.fecha).substring(0, 10)) ? 'Fuera del periodo' : '');

const defectoPla = () => ({ pla_desde: $id('fecha').value, pla_hasta: $id('fecha').value, pla_finca: '', pla_buscar: '' });

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
    $id('pla_agregar').disabled = total === 0 || pla.agregando;
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

    $id('pla_rango').textContent = desde && hasta && desde !== hasta ? `${fechaCorta(desde)} – ${fechaCorta(hasta)}` : (desde || hasta ? fechaCorta(desde || hasta) : 'Todas las fechas');
    mostrar($id('pla_limpiar_col'), !pla.cargando && filtrosAplicados());
    ['pla_desde', 'pla_hasta', 'pla_finca', 'pla_buscar'].forEach((id) => { $id(id).disabled = pla.cargando; });
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

const agregarPla = async () => {
    const lista = pla.filas.filter((fila) => pla.seleccion.has(Number(fila.linea)) && seleccionablePla(fila));
    const boton = $id('pla_agregar');
    const original = boton.innerHTML;

    if (lista.length === 0 || pla.agregando) return;

    if (planeadas().length + lista.length > 50) {
        Toast.fire({ icon: 'warning', title: 'Máximo 50 actividades planeadas por transacción' });

        return;
    }

    pla.agregando = true;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Agregando…';

    try {
        await agregarPlaneaciones(lista);
        pla.seleccion = new Set();
        bootstrap.Modal.getOrCreateInstance($id('modalPlaneaciones')).hide();
        Toast.fire({ icon: 'success', title: `${pluralPla(lista.length, 'actividad agregada', 'actividades agregadas')} al detalle` });
    } catch (error) {
        avisar(error);
    }

    pla.agregando = false;
    boton.innerHTML = original;
    seleccionPla();
    pintarPlaneadas();
};

const modoEdicion = (numero) => {
    const guardar = $id('btn_guardar');
    const cancelar = $id('btn_cancelar');

    estado.edicion = numero;
    guardar.dataset.original = numero ? '<i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar cambios' : guardar.dataset.base;
    cancelar.dataset.original = numero ? '<i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Cancelar edición' : cancelar.dataset.base;
    guardar.innerHTML = guardar.dataset.original;
    cancelar.innerHTML = cancelar.dataset.original;
    guardar.title = numero ? 'Guardar los cambios de la transacción (Ctrl+S)' : 'Guardar la transacción (Ctrl+S)';
    cancelar.title = numero ? 'Cancelar la edición' : 'Cancelar la transacción';
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

const hayCambios = () => Boolean($id('zona_formulario')) && (estado.edicion
    ? JSON.stringify(cuerpoBase()) !== estado.foto
    : !estado.sellado && (texto($id('tiquete').value) !== '' || estado.lineas.length > 0));

const cargarEdicion = async (numero) => {
    if (!PERMISOS.editar || !$id('zona_formulario')) return;

    if (estado.edicion === numero) {
        bootstrap.Tab.getOrCreateInstance($id('tab_registro')).show();

        return;
    }

    if (hayCambios()) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: `¿Editar la transacción ${escaparHtml(numero)}?`,
            html: estado.edicion ? 'Se perderán los cambios de la edición.' : 'Se perderán los datos sin guardar.',
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

    if (Number(consulta.filas.find((item) => texto(item.numero) === numero)?.bloqueada) === 1 && Number(resultado.encabezado?.habilitada) !== 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Transacción liquidada',
            html: `La transacción <b class="font-monospace">${escaparHtml(numero)}</b> ya fue liquidada en nómina y no se puede editar.<br>${PERMISOS.habilitar ? 'Puede habilitarla con el botón <i class="bi bi-unlock"></i> de la consulta.' : 'Solicite a su superior que la habilite para edición.'}`,
            confirmButtonText: 'Entendido'
        });

        return;
    }

    limpiarTodo();
    ['pestanas', 'item_registro'].forEach((id) => $id(id)?.classList.remove('d-none'));
    bootstrap.Tab.getOrCreateInstance($id('tab_registro')).show();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    $id('zona_formulario').insertAdjacentHTML('beforeend', '<div class="tx-velo" id="velo_cargando"></div>');
    congelar(true);
    ['btn_guardar', 'btn_cancelar'].forEach((id) => { $id(id).disabled = true; });

    const encabezado = resultado.encabezado || {};
    const bascula = resultado.bascula || {};
    const externo = !resultado.bascula || Number(bascula.externo) === 1;

    estado.externo = externo;
    estado.encontrado = !externo;
    $id('tiquete_externo').checked = externo;
    $id('tiquete').value = texto(bascula.tiquete);
    habilitarTiquete(externo);
    mostrar($id('nota_externo'), externo);
    $id('fecha').value = texto(encabezado.fecha || bascula.fecha).substring(0, 10);
    $id('pesoBruto').value = fmt(Number(bascula.pesoBruto || 0), 2);
    $id('pesoTara').value = fmt(Number(bascula.pesoTara || 0), 2);
    $id('racimos').value = fmt(bascula.racimos);
    $id('sacos').value = fmt(bascula.sacos);
    ['codigoConductor', 'nombreConductor', 'vehiculo', 'remolque'].forEach((id) => { $id(id).value = texto(bascula[id]); });
    $('#extractora').val(texto(bascula.extractora)).trigger('change.select2');

    if (!externo) habilitarCaptura();

    $id('observacion').value = texto(encabezado.observacion);

    estado.lineas = (resultado.lineas || []).map((linea) => ({
        id: estado.secuencia++,
        finca: texto(linea.finca),
        fincaNombre: texto(linea.fincaNombre) || texto(linea.finca),
        lote: texto(linea.lote),
        fecha: texto(linea.fecha || encabezado.fecha).substring(0, 10),
        racimos: Number(linea.racimos || 0),
        sacos: Number(linea.sacos || 0),
        pesoRacimo: Number(linea.pesoRacimo || 0),
        kg: Number(linea.cantidad || 0),
        trabajadores: (linea.trabajadores || []).map((trabajador) => ({
            id: estado.secuenciaTrabajador++,
            tercero: texto(trabajador.tercero),
            terceroNombre: texto(trabajador.terceroNombre),
            terceroCodigo: texto(trabajador.terceroCodigo),
            novedad: texto(trabajador.novedad),
            novedadNombre: texto(trabajador.novedadNombre),
            cantidad: Number(trabajador.cantidad || 0),
            precio: Number(trabajador.precioLabor || 0),
            jornales: Number(trabajador.jornales || 0),
            fecha: texto(trabajador.fechaNovedad).substring(0, 10),
            contratista: Number(trabajador.contratista || 0) === 1 ? 1 : 0,
            bloque: 'cosecha'
        }))
    }));

    modoEdicion(numero);
    calcularNeto();
    activarDetalle();
    const anio = texto(encabezado.anio);
    const anios = Array.from($id('anio').options);

    if (anio !== '' && !anios.some((opcion) => opcion.value === anio)) {
        $id('anio').add(new Option(anio, anio), anios.find((opcion) => Number(opcion.value) < Number(anio)) || null);
    }

    $id('anio').value = anio;
    await cargarPeriodos(encabezado.anio, encabezado.mes);

    $id('velo_cargando')?.remove();
    congelar(false);
    ['btn_guardar', 'btn_cancelar'].forEach((id) => { $id(id).disabled = false; });
    estado.foto = JSON.stringify(cuerpoBase());

    if (Number(encabezado.habilitada) === 1) {
        Toast.fire({ icon: 'info', title: 'Edición habilitada · un solo uso', text: `Al guardar, la transacción quedará bloqueada de nuevo. Vence el ${fechaCorta(encabezado.habilitadaVence)} a las ${texto(encabezado.habilitadaVence).substring(11, 16)}.`, timer: 8000 });
    }
};

const salirEdicion = async () => {
    const numero = estado.edicion;

    if (hayCambios()) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: '¿Descartar los cambios?',
            showCancelButton: true,
            confirmButtonText: 'Descartar',
            cancelButtonText: 'Seguir editando',
            focusCancel: true
        });

        if (!respuesta.isConfirmed) return;
    }

    limpiarTodo();
    volverConsulta(numero, false);
};

const enviarGuardar = async (confirmaciones) => {
    if ($id('periodo').value === '' || periodoBloqueado(periodoActual())) {
        $id('error_periodo').textContent = $id('periodo').value === '' ? 'Seleccione el periodo' : 'Elija un periodo abierto';
        $id('periodo').classList.add('is-invalid');
        $id('periodo').focus();

        return;
    }

    const sinBruto = aNumero($id('pesoBruto').value) <= 0;
    const sinNeto = aNumero($id('pesoNeto').value) <= 0;

    $id('pesoBruto').classList.toggle('is-invalid', sinBruto);
    mostrar($id('error_bruto'), sinBruto);
    $id('pesoNeto').classList.toggle('is-invalid', sinNeto && !sinBruto);
    mostrar($id('error_neto'), sinNeto && !sinBruto);

    if (sinBruto || sinNeto) {
        (sinBruto ? $id('pesoBruto') : $id('pesoTara')).focus();

        return;
    }

    if (estado.lineas.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'La transacción no tiene líneas',
            html: 'Agregue al menos una línea de detalle antes de guardar.',
            confirmButtonText: 'Entendido'
        });

        return;
    }

    if (faltantes() < 0) {
        Swal.fire({
            icon: 'error',
            title: 'El reparto supera los racimos del tiquete',
            html: `Las líneas suman <b>${escaparHtml(fmt(Math.abs(faltantes())))}</b> racimos más que los <b>${escaparHtml(fmt(racimosTiquete()))}</b> del tiquete. Ajuste los racimos de alguna línea.`,
            confirmButtonText: 'Entendido'
        });

        return;
    }

    const boton = $id('btn_guardar');

    estado.guardando = true;
    ocupar(boton, true, 'Guardando…');
    $id('btn_cancelar').disabled = true;
    $id('zona_formulario').insertAdjacentHTML('beforeend', '<div class="tx-velo" id="velo_guardando"></div>');
    congelar(true);

    let resultado;

    try {
        const vinculos = planeadas().length ? { planeacionLineas: JSON.stringify(planeadas().map((linea) => linea.planeacion.id)) } : {};

        resultado = estado.edicion
            ? await pedir('actualizar', { ...cuerpoBase(), ...confirmaciones, ...vinculos, numero: estado.edicion })
            : await pedir('guardar', { ...cuerpoBase(), ...confirmaciones, ...vinculos });
    } catch (error) {
        estado.guardando = false;
        ocupar(boton, false);
        $id('btn_cancelar').disabled = false;
        $id('velo_guardando')?.remove();
        congelar(false);

        if (/actividad planeada/i.test(error.message)) cargarPla();

        await manejarErrorGuardar(error, confirmaciones);

        return;
    }

    estado.guardando = false;
    ocupar(boton, false);
    $id('btn_cancelar').disabled = false;
    $id('velo_guardando')?.remove();
    congelar(false);
    cargarPla();

    if (estado.edicion) {
        const numero = estado.edicion;

        Toast.fire({ icon: 'success', title: `Transacción ${escaparHtml(numero)} actualizada` });
        limpiarTodo();
        volverConsulta(numero, true);

        return;
    }

    sellar(texto(resultado.numero));

    const kilos = (resultado.lineas || []).reduce((suma, fila) => suma + Number(fila.cantidad || 0), 0);

    const respuesta = await Swal.fire({
        icon: 'success',
        title: 'Transacción registrada',
        html: `Se creó la transacción <b>${escaparHtml(texto(resultado.numero))}</b> con ${escaparHtml(fmt(estado.lineas.length))} líneas,`
            + ` <b>${escaparHtml(fmt(racimosRepartidos()))}</b> racimos y <b>${escaparHtml(fmt(kilos))} kg</b> repartidos.`,
        showCancelButton: true,
        confirmButtonText: 'Registrar otra',
        cancelButtonText: 'Cerrar'
    });

    if (respuesta.isConfirmed) limpiarTodo();
};

const manejarErrorGuardar = async (error, confirmaciones) => {
    const datos = error.datos || {};

    if (error.status === 403) {
        await Swal.fire({ icon: 'warning', title: 'Acción no permitida', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });

        return;
    }

    if (datos.requiereConfirmacion === true && Array.isArray(datos.lineasSinTrabajadores) && datos.lineasSinTrabajadores.length > 0) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: 'Hay líneas sin trabajadores',
            html: `Las líneas <b>${escaparHtml(datos.lineasSinTrabajadores.join(', '))}</b> no tienen trabajadores registrados.`,
            showCancelButton: true,
            confirmButtonText: 'Guardar de todos modos',
            cancelButtonText: 'Seguir editando',
            focusCancel: true
        });

        if (respuesta.isConfirmed) {
            await enviarGuardar({ ...confirmaciones, confirmaLineasSinTrabajadores: 1 });

            return;
        }

        const posicion = Number(datos.lineasSinTrabajadores[0]) - 1;
        const linea = estado.lineas[posicion];

        if (linea) {
            destacar(linea.id);
        }

        return;
    }

    if (datos.requiereConfirmacion === true && Number(datos.faltante) > 0) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: 'Faltan racimos por repartir',
            html: `Repartió <b>${escaparHtml(fmt(racimosRepartidos()))}</b> de los <b>${escaparHtml(fmt(racimosTiquete()))}</b> racimos del tiquete.`
                + ` Faltan <b>${escaparHtml(fmt(Number(datos.faltante)))}</b>.`,
            showCancelButton: true,
            confirmButtonText: 'Guardar de todos modos',
            cancelButtonText: 'Seguir repartiendo',
            focusCancel: true
        });

        if (respuesta.isConfirmed) await enviarGuardar({ ...confirmaciones, confirmaFaltante: 1 });

        return;
    }

    await Swal.fire({
        icon: 'error',
        title: 'No se pudo guardar la transacción',
        html: escaparHtml(error.message),
        confirmButtonText: 'Entendido'
    });

    if (Array.isArray(datos.lineasSinTrabajadores) && datos.lineasSinTrabajadores.length > 0) {
        const linea = estado.lineas[Number(datos.lineasSinTrabajadores[0]) - 1];

        if (linea) {
            destacar(linea.id);
        }
    }
};

const cancelar = async () => {
    if (estado.edicion) {
        salirEdicion();

        return;
    }

    if (estado.sellado) {
        limpiarTodo();

        return;
    }

    if (estado.lineas.length === 0 && texto($id('tiquete').value) === '') return;

    const respuesta = await Swal.fire({
        icon: 'warning',
        title: '¿Descartar la transacción?',
        html: 'Se perderá la transacción que está armando.',
        showCancelButton: true,
        confirmButtonText: 'Sí, descartar',
        cancelButtonText: 'Seguir editando',
        focusCancel: true
    });

    if (respuesta.isConfirmed) limpiarTodo();
};

const iniciarSelect2 = () => {
    $('#extractora, #cap_finca, #cap_lote').select2({
        width: '100%', placeholder: 'Seleccione una opción…', allowClear: true, language: IDIOMA_SELECT2
    });

    $('#trab_novedad').select2({
        width: '100%', dropdownParent: $('#modalTrabajador'),
        placeholder: 'Seleccione una opción…', allowClear: true, language: IDIOMA_SELECT2
    });

    iniciarTercero(false);
};

document.addEventListener('DOMContentLoaded', () => {
    if (!$id('zona_formulario')) return;

    guardarEtiquetas();
    iniciarSelect2();
    habilitarTiquete(false);
    activarDetalle();

    cargarLabores().catch(() => []).then(renderizar);
    cargarPeriodos($id('anio').value);

    $id('anio').addEventListener('change', () => cargarPeriodos($id('anio').value));
    $id('periodo').addEventListener('change', () => {
        $id('periodo').classList.remove('is-invalid');
        revisarPeriodo();
        renderizar();
        previsualizar();
    });
    ['input', 'change'].forEach((evento) => $id('fecha').addEventListener(evento, () => { revisarPeriodo(); renderizar(); }));

    $id('btn_buscar_tiquete').addEventListener('click', buscarTiquete);
    $id('btn_capturar_mano').addEventListener('click', () => {
        $id('tiquete_externo').checked = true;
        alternarExterno(true);
    });
    $id('tiquete_externo').addEventListener('change', (evento) => alternarExterno(evento.target.checked));
    $id('tiquete').addEventListener('input', () => {
        if (!estado.encontrado) return;

        estado.encontrado = false;
        activarDetalle();
    });
    $id('tiquete').addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            buscarTiquete();
        }
    });

    ['pesoBruto', 'pesoTara'].forEach((id) => $id(id).addEventListener('input', calcularNeto));
    $id('racimos').addEventListener('input', () => { activarDetalle(); previsualizar(); });
    $id('sacos').addEventListener('input', previsualizar);

    $('#cap_finca').on('change', () => cargarLotes($id('cap_finca').value));
    $('#cap_lote').on('change', () => consultarPeso($id('cap_finca').value, $id('cap_lote').value));

    $id('btn_agregar_linea').addEventListener('click', agregarLinea);
    ['cap_fecha_linea', 'cap_racimos', 'cap_sacos'].forEach((id) => $id(id).addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            agregarLinea();
        }
    }));

    $id('lista_lineas').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-accion]');

        if (!boton) return;

        const accion = boton.dataset.accion;
        const idLinea = Number(boton.dataset.linea);

        if (accion === 'eliminar-linea') {
            evento.stopPropagation();
            evento.preventDefault();
            eliminarLinea(idLinea);
        }

        if (accion === 'asignar-faltantes') {
            const linea = estado.lineas.find((item) => item.id === idLinea);
            const entrada = $id(`linea_racimos_${idLinea}`);

            linea.racimos += faltantes();
            entrada.value = fmt(linea.racimos);
            refrescarDerivados();
            previsualizar();
        }

        if (accion === 'nuevo-trabajador') abrirModalTrabajador(idLinea, boton.dataset.bloque, null);
        if (accion === 'editar-trabajador') {
            const linea = estado.lineas.find((item) => item.id === idLinea);
            const trabajador = linea.trabajadores.find((item) => item.id === Number(boton.dataset.trabajador));

            abrirModalTrabajador(idLinea, trabajador.bloque, trabajador.id);
        }
        if (accion === 'eliminar-trabajador') eliminarTrabajador(idLinea, Number(boton.dataset.trabajador));
    });

    $id('lista_lineas').addEventListener('input', (evento) => {
        const entrada = evento.target.closest('[data-campo]');

        if (!entrada) return;

        const linea = estado.lineas.find((item) => item.id === Number(entrada.dataset.linea));

        if (!linea) return;

        linea[entrada.dataset.campo] = Math.round(aNumero(entrada.value));
        refrescarDerivados();
        previsualizar();
    });

    $id('cap_fecha_linea').addEventListener('input', () => {
        estado.fechaLineaTocada = true;
        $id('cap_fecha_linea').classList.remove('is-invalid');
        errorCaptura('error_fecha_linea', false);
    });

    ['trab_cantidad', 'trab_precio'].forEach((id) => $id(id).addEventListener('input', totalModal));
    $id('trab_cantidad').addEventListener('input', () => {
        estado.modal.igualar = false;
        $id('trab_cantidad').classList.remove('is-invalid');
        mostrar($id('error_trab_cantidad'), false);
        pintarCantidad();
    });
    $id('ayuda_cantidad').addEventListener('click', (evento) => {
        if (evento.target.closest('#btn_repartir_iguales')) repartirIguales();
    });
    $('#trab_tercero, #trab_novedad').on('change', sugerirPrecio);
    $id('trab_precio').addEventListener('input', () => {
        $id('trab_precio').classList.remove('is-invalid');
        estado.modal.manual = true;
        pintarPrecio();
    });
    $id('ayuda_precio').addEventListener('click', (evento) => {
        if (evento.target.closest('#btn_usar_sugeridos')) usarSugeridos();
    });
    $id('trab_fecha').addEventListener('change', () => {
        $id('trab_fecha').classList.remove('is-invalid');
        sugerirPrecio();
    });

    $id('formulario_trabajador').addEventListener('submit', (evento) => {
        evento.preventDefault();
        guardarTrabajador();
    });

    $id('btn_guardar').addEventListener('click', () => {
        if (!estado.guardando && (estado.edicion ? PERMISOS.editar : PERMISOS.registrar)) enviarGuardar({});
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

    $id('tiquete').focus();

    if (PERMISOS.registrar) precargarPlaneacion();
});
