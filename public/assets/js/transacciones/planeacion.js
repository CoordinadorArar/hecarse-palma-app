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

const URL_PL = BASE_URL + 'transacciones/planeacion/';

const $id = (id) => document.getElementById(id);
const mostrar = (elemento, visible) => elemento?.classList.toggle('d-none', !visible);
const texto = (valor) => String(valor ?? '').trim();
const formatoHora = (hora) => new Intl.DateTimeFormat('es-CO', { hour: 'numeric', minute: '2-digit' }).format(new Date(`2000-01-01T${texto(hora).slice(0, 5)}:00`));
const formatoHorario = (a) => (texto(a.horaInicio) && texto(a.horaFin) ? `${formatoHora(a.horaInicio)} – ${formatoHora(a.horaFin)}` : '—');

const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const fmt = (valor, decimales = 0) => Number(valor || 0).toLocaleString('es-CO', {
    minimumFractionDigits: decimales, maximumFractionDigits: decimales
});

const cant = (valor) => {
    const numero = Number(valor || 0);

    return fmt(numero, Number.isInteger(Math.round(numero * 100) / 100) ? 0 : 2);
};

const aNumero = (valor) => {
    const crudo = texto(valor).replace(/\s/g, '');

    if (crudo === '') return 0;

    return /^\d+([.,]\d{1,2})?$/.test(crudo) ? Number(crudo.replace(',', '.')) : NaN;
};

const valorInput = (numero) => (Number.isFinite(numero) ? String(numero).replace('.', ',') : '');
const centavos = (valor) => Math.round(Number(valor || 0) * 100);

const repartir = (total, partes) => {
    const centesimas = Math.round(total * 100);
    const base = Math.floor(centesimas / partes);
    const sobrante = centesimas - base * partes;

    return Array.from({ length: partes }, (_, indice) => (base + (indice < sobrante ? 1 : 0)) / 100);
};

const IDIOMA_SELECT2 = {
    noResults: () => 'No se encontraron resultados',
    searching: () => 'Buscando…',
    inputTooShort: () => 'Escriba al menos 3 caracteres',
    errorLoading: () => 'No se pudo consultar'
};

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

const aFecha = (iso) => {
    const [anio, mes, dia] = texto(iso).slice(0, 10).split('-').map(Number);

    return new Date(anio, (mes || 1) - 1, dia || 1);
};

const aIso = (fecha) => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;

const sumarDias = (iso, dias) => {
    const fecha = aFecha(iso);

    fecha.setDate(fecha.getDate() + dias);

    return aIso(fecha);
};

const fechaLegible = (iso) => {
    const fecha = aFecha(iso);

    return `${DIAS[fecha.getDay()].slice(0, 3)} ${fecha.getDate()} ${MESES[fecha.getMonth()].slice(0, 3)} ${fecha.getFullYear()}`;
};

const fechaCorta = (iso) => {
    const fecha = aFecha(iso);

    return `${DIAS[fecha.getDay()].slice(0, 3)} ${fecha.getDate()} ${MESES[fecha.getMonth()].slice(0, 3)}`;
};

const fechaLarga = (iso) => {
    const fecha = aFecha(iso);

    return `${DIAS[fecha.getDay()]} ${fecha.getDate()} de ${MESES[fecha.getMonth()]} de ${fecha.getFullYear()}`;
};

const fechaHora = (valor) => (texto(valor) ? `${fechaLegible(valor)} ${texto(valor).slice(11, 16)}` : '');

const relativo = (iso) => {
    const hoy = aIso(new Date());

    return iso === hoy ? 'Hoy' : (iso === sumarDias(hoy, 1) ? 'Mañana' : '');
};

const ESTADOS = {
    PENDIENTE: { clase: 'pl-e-pendiente', icono: 'bi-hourglass', nombre: 'Pendiente' },
    COMPLETADA: { clase: 'pl-e-completada', icono: 'bi-check-circle', nombre: 'Completada' },
    PARCIAL: { clase: 'pl-e-parcial', icono: 'bi-circle-half', nombre: 'Parcial' },
    NO_EJECUTADA: { clase: 'pl-e-noejecutada', icono: 'bi-x-circle', nombre: 'No ejecutada' }
};

const chipEstado = (clave) => {
    const e = ESTADOS[clave] || ESTADOS.PENDIENTE;

    return `<span class="pl-chip ${e.clase}"><i class="bi ${e.icono}" aria-hidden="true"></i>${e.nombre}</span>`;
};

const CHIP_AUSENTE = '<span class="pl-chip pl-e-ausente"><i class="bi bi-person-x" aria-hidden="true"></i>Ausente</span>';

const FILTROS = { ausentes: 'Con ausentes', pendientes: 'Pendientes de cierre', completadas: 'Completadas' };

const estado = {
    plan: null,
    cargado: false,
    error: '',
    vista: 'lote',
    filtro: null,
    pendientes: [],
    destacar: null,
    guardando: false
};

let turnoPlan = 0;
let turnoLotes = 0;

const empresaId = () => $id('empresa').value;
const fechaPlan = () => $id('fecha').value;
const reducido = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;
    let resultado = null;

    try {
        respuesta = await fetch(URL_PL + ruta, { method: 'POST', body: cuerpo });
    } catch (error) {
        throw new Error('Error de conexión.');
    }

    try {
        resultado = await respuesta.json();
    } catch (error) {
        resultado = null;
    }

    if (!respuesta.ok || resultado === null || resultado.success !== true) {
        throw Object.assign(new Error(texto(resultado?.message)
            || (respuesta.status === 403 ? 'No tiene permiso para realizar esta acción. Solicítelo al administrador.' : 'No se pudo completar la operación.')), { datos: resultado });
    }

    return resultado;
};

const avisar = (error) => Toast.fire({ icon: error.message === 'Error de conexión.' ? 'error' : 'warning', title: escaparHtml(error.message) });

const errorSwal = (error, titulo = 'No se pudo completar la operación') => Swal.fire({
    icon: 'warning', title: titulo, text: error.message, confirmButtonText: 'Entendido'
});

const confirmar = async (titulo, mensaje, boton, peligro = false) => (await Swal.fire({
    icon: peligro ? 'warning' : 'question',
    title: escaparHtml(titulo),
    text: mensaje,
    showCancelButton: true,
    confirmButtonText: boton,
    cancelButtonText: 'Cancelar',
    confirmButtonColor: peligro ? '#d6293e' : undefined,
    focusCancel: peligro
})).isConfirmed;

const actividades = () => estado.plan?.actividades || [];
const actividad = (id) => actividades().find((a) => a.id === Number(id)) || null;
const pendientesPlan = () => actividades().filter((a) => a.estado === 'PENDIENTE');
const ausente = (t) => Number(t.ausente) === 1;
const editable = () => (estado.plan ? estado.plan.estado === 'ABIERTA' && PERMISOS.editar : PERMISOS.registrar);
const nitsAusentes = () => new Set((estado.plan?.ausencias || []).map((a) => a.tercero));
const nombreCorto = (nombre) => texto(nombre).split(/\s+/).slice(0, 2).join(' ');

const codigoDe = (nit) => {
    const persona = (estado.plan?.ausencias || []).find((a) => a.tercero === nit)
        || actividades().flatMap((a) => a.trabajadores).find((t) => t.tercero === nit);

    return texto(persona?.codigo) || nit;
};

const personasPlan = () => {
    const mapa = new Map();
    const fuera = nitsAusentes();

    actividades().forEach((a) => a.trabajadores.forEach((t) => {
        if (!ausente(t) && !fuera.has(t.tercero) && !mapa.has(t.tercero)) mapa.set(t.tercero, { tercero: t.tercero, codigo: texto(t.codigo), nombre: texto(t.nombre) });
    }));

    return [...mapa.values()].sort((x, y) => (x.codigo || x.nombre).localeCompare(y.codigo || y.nombre, 'es', { numeric: true }));
};

const duplicado = (tercero, novedad, finca, lote, excluir = null) => actividades().find((a) => a.id !== excluir
    && a.novedad === novedad && a.finca === finca && a.lote === lote && a.trabajadores.some((t) => t.tercero === tercero)) || null;

const aEnvio = (a) => ({
    id: a.id,
    finca: a.finca,
    lote: a.lote,
    novedad: a.novedad,
    uMedida: a.uMedida,
    horaInicio: texto(a.horaInicio),
    horaFin: texto(a.horaFin),
    observacion: a.observacion,
    referencia: a.referencia,
    trabajadores: a.trabajadores.filter((t) => !ausente(t)).map((t) => ({ tercero: t.tercero, cantidad: t.cantidad, reasignadoDe: texto(t.reasignadoDe) }))
});

const pasaFiltro = (a) => {
    if (estado.filtro === 'pendientes') return a.estado === 'PENDIENTE';
    if (estado.filtro === 'completadas') return a.estado === 'COMPLETADA' || a.estado === 'PARCIAL';
    if (estado.filtro === 'ausentes') return a.trabajadores.some((t) => ausente(t) || texto(t.reasignadoDe) !== '');

    return true;
};

const cargar = async (silencioso = false) => {
    const turno = ++turnoPlan;
    const datos = { empresa: empresaId(), fecha: fechaPlan() };

    if (silencioso) {
        mostrar($id('cargando_plan'), true);
    } else {
        estado.cargado = false;
        pintar();
    }

    try {
        const [respuesta, pendientes] = await Promise.all([
            pedir('plan', datos),
            pedir('pendientes', datos).catch(() => ({ filas: [] }))
        ]);

        if (turno !== turnoPlan) return;

        estado.plan = respuesta.plan || null;
        estado.pendientes = pendientes.filas || [];
        estado.error = '';
    } catch (error) {
        if (turno !== turnoPlan) return;

        if (silencioso && estado.cargado && !estado.error) {
            avisar(error);
        } else {
            estado.error = error.message;
        }
    }

    estado.cargado = true;
    mostrar($id('cargando_plan'), false);
    pintar();
};

const aplicarPlan = (plan, destacar = null) => {
    if (!plan) {
        cargar(true);

        return;
    }

    estado.plan = plan;
    estado.destacar = destacar;
    pintar();
    estado.destacar = null;

    const tarjeta = destacar ? $id(`act_${destacar}`) : null;

    tarjeta?.scrollIntoView({ behavior: reducido() ? 'auto' : 'smooth', block: 'center' });
};

const pintarFecha = () => {
    const fecha = fechaPlan();
    const rel = relativo(fecha);

    $id('fecha_legible').innerHTML = fecha ? `${escaparHtml(fechaLegible(fecha))}${rel ? ` <span class="gp-chip-fila verde ms-1">${rel}</span>` : ''}` : '';
};

const pintarBarra = () => {
    const plan = estado.plan;
    const badge = $id('badge_plan');
    const ausentes = plan?.ausencias.length || 0;

    badge.className = `badge ${plan ? (plan.estado === 'CERRADA' ? 'gp-badge-cerrado' : 'gp-badge-abierto') : 'tx-chip-neutro'}`;
    badge.innerHTML = plan
        ? `<i class="bi ${plan.estado === 'CERRADA' ? 'bi-lock' : 'bi-unlock'} me-1" aria-hidden="true"></i>${plan.estado === 'CERRADA' ? 'Cerrado' : 'Abierto'}`
        : 'Sin plan';
    $id('barra_fecha').textContent = fechaLegible(fechaPlan());
    $id('chip_filtro').textContent = estado.filtro ? `Filtrando: ${FILTROS[estado.filtro]}` : '';
    mostrar($id('chip_filtro'), Boolean(estado.filtro));
    mostrar($id('btn_ver_todas'), Boolean(estado.filtro));

    document.querySelectorAll('[data-cont="pendientes"]').forEach((chip) => {
        chip.textContent = fmt(estado.pendientes.length);
        chip.classList.toggle('pl-ambar', estado.pendientes.length > 0);
    });
    document.querySelectorAll('[data-cont="ausentes"]').forEach((chip) => {
        chip.textContent = fmt(ausentes);
        chip.classList.toggle('pl-ambar', ausentes > 0);
    });
    document.querySelectorAll('#barra_plan [data-accion="ausentes"]').forEach((boton) => { boton.disabled = !plan; });
    document.querySelectorAll('#barra_plan [data-accion="imprimir"]').forEach((boton) => { boton.disabled = !actividades().length; });
    mostrar(document.querySelector('#barra_plan [data-accion="agregar"]'), editable());
    mostrar(document.querySelector('#barra_plan [data-accion="planilla"]'), editable());
};

const kpi = (clave, etiqueta, valor, sub, icono, clase = '', opciones = {}) => `<div class="${opciones.col || 'col-6 col-md-4 col-xl'}">
    <button type="button" class="inf-kpi pl-kpi ${clase}" data-kpi="${clave}"${opciones.filtro ? ` aria-pressed="${estado.filtro === clave}"` : ''}${opciones.inactivo ? ' disabled' : ''}>
        <span class="gp-icono rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi ${icono}" aria-hidden="true"></i></span>
        <span class="min-w-0 flex-grow-1">
            <span class="gp-etiqueta-mini d-block">${etiqueta}</span>
            <span class="inf-kpi-valor d-block">${valor}</span>
            ${sub}
        </span>
    </button>
</div>`;

const pintarKpis = () => {
    const plan = estado.plan;
    const r = plan.resumen;
    const asignados = new Set(pendientesPlan().flatMap((a) => a.trabajadores.filter((t) => !ausente(t)).map((t) => t.tercero))).size;
    const cerradas = r.completadas + r.parciales;
    const conAusentes = actividades().filter((a) => a.trabajadores.some((t) => ausente(t) || texto(t.reasignadoDe) !== '')).length;

    $id('kpis').innerHTML = [
        kpi('todas', 'Actividades', fmt(r.actividades), `<span class="gp-subtexto">${fmt(r.vinculadas)} registradas</span>`, 'bi-list-task'),
        kpi('trabajadores', 'Trabajadores asignados', fmt(asignados), `<span class="gp-subtexto">de ${fmt(personasPlan().length)} presentes</span>`, 'bi-people'),
        kpi('ausentes', 'Ausentes', fmt(r.ausentes), `<span class="gp-subtexto">${fmt(conAusentes)} actividades afectadas</span>`, 'bi-person-x', 'pl-e-ausente', { filtro: true, inactivo: r.ausentes === 0 }),
        kpi('pendientes', 'Pendientes de cierre', fmt(r.pendientes), `<span class="gp-subtexto">${fmt(r.noEjecutadas)} no ejecutadas</span>`, 'bi-hourglass', 'pl-e-pendiente', { filtro: true }),
        kpi('completadas', 'Completadas', `${fmt(cerradas)} / ${fmt(r.actividades)}`, `<span class="pl-progreso d-block" role="progressbar" aria-label="Avance" aria-valuemin="0" aria-valuemax="${r.actividades}" aria-valuenow="${cerradas}"><span></span></span>`, 'bi-check-circle', 'pl-e-completada', { filtro: true, col: 'col-12 col-md-4 col-xl' })
    ].join('');

    const barra = document.querySelector('#kpis .pl-progreso span');

    if (barra) barra.style.width = `${r.actividades ? Math.round(cerradas * 100 / r.actividades) : 0}%`;
};

const pintarAusentes = () => {
    const ausencias = estado.plan?.ausencias || [];
    const franja = $id('franja_ausentes');
    const puede = editable();

    mostrar(franja, ausencias.length > 0);
    franja.innerHTML = ausencias.length ? `<div class="d-flex flex-wrap align-items-center gap-2">
        <span class="pl-ausentes-titulo"><i class="bi bi-person-x me-1" aria-hidden="true"></i>Ausentes del día</span>
        ${ausencias.map((a) => `<span class="pl-chip pl-e-ausente" title="${escaparHtml(a.motivo || a.nombre)}">
            <span class="font-monospace">${escaparHtml(a.codigo || a.tercero)}</span>${escaparHtml(nombreCorto(a.nombre))}
            ${puede ? `<button type="button" class="tx-chip-quitar" data-accion="presente" data-tercero="${escaparHtml(a.tercero)}" title="Marcar presente"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Marcar presente</span></button>` : ''}
        </span>`).join('')}
    </div>` : '';
};

const pintarAviso = () => {
    const total = estado.pendientes.length;
    const visible = total > 0 && editable() && Boolean(estado.plan);

    mostrar($id('aviso_pendientes'), visible);
    $id('aviso_pendientes_texto').textContent = total === 1 ? 'Hay 1 actividad con saldo de días anteriores.' : `Hay ${fmt(total)} actividades con saldo de días anteriores.`;
};

const estadoVacio = (icono, titulo, mensaje, botones = '', clase = '') => `<div class="card mb-3"><div class="card-body gp-estado-vacio text-center py-5">
    <div class="gp-medallon ${clase} d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi ${icono}" aria-hidden="true"></i></div>
    <h6 class="mb-1">${titulo}</h6>
    ${mensaje ? `<p class="text-muted mb-3">${mensaje}</p>` : ''}
    ${botones ? `<div class="d-flex flex-wrap justify-content-center gap-2">${botones}</div>` : ''}
</div></div>`;

const botonPlanilla = () => '<button type="button" class="btn btn-outline-success" data-accion="planilla"><i class="bi bi-table me-1" aria-hidden="true"></i>Planear finca</button>';

const botonAgregar = (clase = '') => `<button type="button" class="btn btn-success ${clase}" data-accion="agregar"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar actividad</button>`;

const kebabActividad = (a) => {
    if (!editable()) return '';

    const pendiente = a.estado === 'PENDIENTE';
    const reabrir = !pendiente && Number(a.vinculada) !== 1 && Number(a.trasladada) !== 1;
    const items = [
        pendiente ? `<li><button type="button" class="dropdown-item" data-accion="editar" data-act="${a.id}"><i class="bi bi-pencil me-2" aria-hidden="true"></i>Editar</button></li>` : '',
        `<li><button type="button" class="dropdown-item" data-accion="duplicar" data-act="${a.id}"><i class="bi bi-copy me-2" aria-hidden="true"></i>Duplicar</button></li>`,
        reabrir ? `<li><button type="button" class="dropdown-item" data-accion="reabrir" data-act="${a.id}"><i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>Reabrir</button></li>` : '',
        pendiente ? `<li><hr class="dropdown-divider"></li><li><button type="button" class="dropdown-item text-danger" data-accion="eliminar" data-act="${a.id}"><i class="bi bi-trash me-2" aria-hidden="true"></i>Eliminar</button></li>` : ''
    ].join('');

    return `<div class="dropdown pl-kebab">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de la actividad"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">${items}</ul>
    </div>`;
};

const accionesTrabajador = (a, t, unico) => {
    const nombre = escaparHtml(t.nombre || t.tercero);
    const nit = escaparHtml(t.tercero);
    const quitar = unico ? 'La actividad debe tener al menos un trabajador' : `Quitar a ${nombre}`;
    const botones = [
        ['editar-trab', 'btn-outline-secondary', 'bi-pencil', `Editar a ${nombre}`, false],
        ['ausente-trab', 'btn-outline-warning', 'bi-person-x', `Marcar ausente a ${nombre}`, false],
        ['quitar-trab', 'btn-outline-danger', 'bi-x-lg', quitar, unico]
    ];

    return `<div class="d-none d-sm-flex gap-1 justify-content-center">${botones.map(([accion, clase, icono, titulo, inactivo]) => `<button type="button" class="btn btn-sm ${clase}" data-accion="${accion}" data-act="${a.id}" data-tercero="${nit}" title="${titulo}" aria-label="${titulo}"${inactivo ? ' disabled' : ''}><i class="bi ${icono}" aria-hidden="true"></i></button>`).join('')}</div>
    <div class="dropdown d-sm-none">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de ${nombre}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">${botones.map(([accion, , icono, titulo, inactivo]) => `<li><button type="button" class="dropdown-item${accion === 'quitar-trab' ? ' text-danger' : ''}" data-accion="${accion}" data-act="${a.id}" data-tercero="${nit}"${inactivo ? ' disabled' : ''}><i class="bi ${icono} me-2" aria-hidden="true"></i>${titulo}</button></li>`).join('')}</ul>
    </div>`;
};

const filaTrabajador = (a, t, acciones, unico) => {
    const nombre = escaparHtml(t.nombre || t.tercero);
    const fuera = ausente(t);
    const recibe = texto(t.reasignadoDe) ? `<span class="gp-chip-fila ambar ms-1" title="Reasignado por ausencia">recibe de ${escaparHtml(codigoDe(t.reasignadoDe))}</span>` : '';

    return `<tr class="${fuera ? 'pl-fila-ausente' : ''}">
        <td><span class="font-monospace fw-semibold me-1">${escaparHtml(t.codigo)}</span><span class="gp-truncar d-inline-block align-bottom pl-nombre" title="${nombre}">${nombre}</span>${fuera ? ` ${CHIP_AUSENTE}` : recibe}<span class="gp-subtexto font-monospace">${escaparHtml(t.tercero)}</span></td>
        <td class="text-end tx-num">${fuera && !Number(t.cantidad) ? '0<span class="gp-subtexto">reasignada</span>' : cant(t.cantidad)}</td>
        ${acciones ? `<td class="text-center gp-col-sticky">${fuera ? '' : accionesTrabajador(a, t, unico)}</td>` : ''}
    </tr>`;
};

const pieActividad = (a) => {
    const puede = editable();

    if (a.estado === 'PENDIENTE') {
        if (!puede) return '';

        return `<div class="pl-cierre"><div class="pl-botonera">
            <button type="button" class="btn btn-outline-success" data-accion="completar" data-act="${a.id}"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Completada</span><span class="d-sm-none">Completa</span></button>
            <button type="button" class="btn btn-outline-warning" data-accion="parcial" data-act="${a.id}"><i class="bi bi-circle-half me-1" aria-hidden="true"></i>Parcial</button>
            <button type="button" class="btn btn-outline-danger" data-accion="noejecutada" data-act="${a.id}"><i class="bi bi-x-lg me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">No ejecutada</span><span class="d-sm-none">No ejec.</span></button>
        </div></div>`;
    }

    const destino = DESTINOS[a.destino] || null;
    let integracion = '';

    if (Number(a.esCosecha) === 1) {
        integracion = '<span class="gp-chip-fila tx-chip-neutro"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Se registra en Producción</span>';
    } else if (Number(a.vinculada) === 1) {
        const numero = `${escaparHtml(a.tipoTransaccion)}-${escaparHtml(a.numeroTransaccion)}`;

        integracion = `<span class="pl-chip pl-e-registrada" title="Registrada en ${a.destino === 'fertilizacion' ? 'Fertilización' : 'Registro de labores'}"><i class="bi bi-journal-check" aria-hidden="true"></i>${numero}</span>`;
    } else if (Number(a.registrable) === 1 && destino?.puede) {
        integracion = `<button type="button" class="btn btn-sm btn-primary" data-accion="registrar" data-act="${a.id}"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Registrar</button>`;
    }

    const reabrir = puede && Number(a.vinculada) !== 1 && Number(a.trasladada) !== 1
        ? `<button type="button" class="btn btn-link btn-sm p-0" data-accion="reabrir" data-act="${a.id}">Reabrir</button>` : '';
    const trasladado = Number(a.trasladada) === 1 ? '<span class="gp-chip-fila tx-chip-neutro"><i class="bi bi-arrow-right me-1" aria-hidden="true"></i>Saldo trasladado</span>' : '';

    return `<div class="pl-cierre d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-3 min-w-0">
            ${chipEstado(a.estado)}
            ${a.estado === 'PARCIAL' ? `<span class="tx-dato"><span class="gp-etiqueta-mini">Ejecutado</span><span class="tx-dato-valor">${cant(a.cantidadEjecutada)} / ${cant(a.cantidad)} ${escaparHtml(a.uMedida)}</span></span>` : ''}
            ${a.estado !== 'COMPLETADA' && texto(a.observacionCierre) ? `<span class="gp-subtexto fst-italic">${escaparHtml(a.observacionCierre)}</span>` : ''}
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">${trasladado}${integracion}${reabrir}</div>
    </div>`;
};

const tarjetaActividad = (a) => {
    const presentes = a.trabajadores.filter((t) => !ausente(t));
    const acciones = a.estado === 'PENDIENTE' && editable();
    const labor = escaparHtml(a.novedadNombre);
    const um = escaparHtml(a.uMedida);

    return `<article class="tx-tarjeta pl-actividad ${ESTADOS[a.estado]?.clase || 'pl-e-pendiente'}${estado.destacar === a.id ? ' tx-tarjeta--destacada' : ''}" id="act_${a.id}">
        <div class="tx-tarjeta-cab tx-tarjeta-cab--fija pl-cab">
            <span class="pl-cab-labor min-w-0"><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> — <span class="gp-truncar d-inline-block align-bottom" title="${labor}">${labor}</span></span>
            <span class="pl-cab-chips d-flex flex-wrap align-items-center gap-1">
                <span class="gp-chip-fila tx-chip-neutro">${um}</span>
                ${chipEstado(a.estado)}
                ${texto(a.referencia) ? `<span class="gp-chip-ambito seccion font-monospace">PFA-${escaparHtml(a.referencia)}</span>` : ''}
            </span>
            <span class="pl-cab-datos ms-auto d-flex align-items-end gap-3">
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Meta</span><span class="tx-dato-valor">${cant(a.cantidad)} ${um}</span></span>
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Horario</span><span class="tx-dato-valor"><i class="bi bi-clock me-1" aria-hidden="true"></i>${escaparHtml(formatoHorario(a))}</span></span>
            </span>
            ${kebabActividad(a)}
        </div>
        <div class="tx-franja d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-signpost" aria-hidden="true"></i>
            <span class="min-w-0 flex-grow-1">${texto(a.observacion) ? escaparHtml(a.observacion) : '<span class="text-muted">—</span>'}</span>
            ${a.origen ? `<span class="gp-chip-fila tx-chip-neutro"><i class="bi bi-arrow-return-right me-1" aria-hidden="true"></i>Trasladada del ${escaparHtml(fechaCorta(a.origenFecha).slice(4))}</span>` : ''}
            <span class="tx-chip-cont verde">${fmt(presentes.length)} trab.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 gp-tabla tx-grilla pl-tabla-trab">
                <thead class="gp-thead">
                    <tr>
                        <th>Trabajador</th>
                        <th class="text-end">Cantidad</th>
                        ${acciones ? '<th class="text-center gp-col-sticky pl-col-acciones"><span class="visually-hidden">Acciones</span></th>' : ''}
                    </tr>
                </thead>
                <tbody>${a.trabajadores.map((t) => filaTrabajador(a, t, acciones, presentes.length === 1)).join('')}</tbody>
            </table>
        </div>
        ${pieActividad(a)}
    </article>`;
};

const agrupar = (lista, llave) => {
    const grupos = new Map();

    lista.forEach((item) => {
        const clave = llave(item);

        if (!grupos.has(clave)) grupos.set(clave, []);
        grupos.get(clave).push(item);
    });

    return [...grupos.entries()];
};

const sinResultados = () => estadoVacio('bi-funnel', 'Ninguna actividad coincide con el filtro', '', '<button type="button" class="btn btn-outline-secondary" data-accion="ver-todas">Ver todas</button>', 'chico');

const pintarVistaLote = () => {
    const visibles = actividades().filter(pasaFiltro);

    if (!visibles.length) {
        $id('vista_lote').innerHTML = sinResultados();

        return;
    }

    const ordenadas = [...visibles].sort((x, y) => x.finca.localeCompare(y.finca, 'es', { numeric: true }) || x.lote.localeCompare(y.lote, 'es', { numeric: true }) || x.orden - y.orden);

    $id('vista_lote').innerHTML = agrupar(ordenadas, (a) => a.finca).map(([finca, lista]) => {
        const personas = new Set(lista.flatMap((a) => a.trabajadores.filter((t) => !ausente(t)).map((t) => t.tercero))).size;

        return `<section class="pl-finca">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <span class="gp-grupo-titulo"><span class="font-monospace me-1">${escaparHtml(finca)}</span>${escaparHtml(lista[0].fincaNombre)}</span>
                <span class="d-flex flex-wrap align-items-center gap-2">
                    <span class="gp-subtexto">${fmt(lista.length)} ${lista.length === 1 ? 'actividad' : 'actividades'} · ${fmt(personas)} ${personas === 1 ? 'trabajador' : 'trabajadores'}</span>
                    ${editable() ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-accion="planilla" data-finca="${escaparHtml(finca)}"><i class="bi bi-table me-1" aria-hidden="true"></i>Editar planilla</button>` : ''}
                </span>
            </div>
            ${agrupar(lista, (a) => a.lote).map(([lote, items]) => `<div class="pl-lote">
                <div class="pl-lote-cab"><i class="bi bi-geo-alt" aria-hidden="true"></i><span class="font-monospace fw-semibold">${escaparHtml(lote)}</span><span>${escaparHtml(items[0].loteNombre)}</span>${texto(items[0].seccion) ? `<span>· Sección ${escaparHtml(items[0].seccion)}</span>` : ''}</div>
                <div class="tx-lineas">${items.map(tarjetaActividad).join('')}</div>
            </div>`).join('')}
        </section>`;
    }).join('');
};

const lineaActividad = (a, t) => `${escaparHtml(a.lote)} · ${escaparHtml(a.novedadNombre || a.novedad)} · ${cant(t.cantidad)} ${escaparHtml(a.uMedida)}${texto(a.horaInicio) ? ` · ${escaparHtml(formatoHorario(a))}` : ''}`;

const pintarVistaTrabajador = () => {
    const busca = texto($id('buscar_trabajador').value).toLowerCase();
    const coincide = (p) => !busca || `${p.codigo} ${p.nombre} ${p.tercero}`.toLowerCase().includes(busca);
    const mapa = new Map();

    actividades().filter(pasaFiltro).forEach((a) => a.trabajadores.filter((t) => !ausente(t)).forEach((t) => {
        if (!mapa.has(t.tercero)) mapa.set(t.tercero, { tercero: t.tercero, codigo: texto(t.codigo), nombre: texto(t.nombre), filas: [] });
        mapa.get(t.tercero).filas.push({ a, t });
    }));

    const personas = [...mapa.values()].filter(coincide).sort((x, y) => (x.codigo || x.nombre).localeCompare(y.codigo || y.nombre, 'es', { numeric: true }));
    const ausentes = (estado.plan?.ausencias || []).filter((p) => coincide({ ...p, codigo: texto(p.codigo), nombre: texto(p.nombre) }));
    const total = personas.length + ausentes.length;

    $id('contador_trabajadores').innerHTML = `<i class="bi bi-people me-1" aria-hidden="true"></i><strong>${fmt(total)}</strong> ${total === 1 ? 'trabajador' : 'trabajadores'}`;

    if (!total) {
        $id('lista_trabajadores').innerHTML = `<div class="gp-estado-vacio text-center py-4">
            <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi bi-person-slash" aria-hidden="true"></i></div>
            <h6 class="mb-0">${busca ? 'Ningún trabajador coincide con la búsqueda' : 'Ninguna actividad coincide con el filtro'}</h6>
        </div>`;

        return;
    }

    const filas = personas.map((p) => p.filas.map(({ a, t }, i) => `<tr${i === 0 ? ' class="pl-grupo-inicio"' : ''}>
        ${i === 0 ? `<td rowspan="${p.filas.length}" class="font-monospace fw-semibold">${escaparHtml(p.codigo || '—')}</td><td rowspan="${p.filas.length}">${escaparHtml(p.nombre)}<span class="gp-subtexto font-monospace">${escaparHtml(p.tercero)}</span></td>` : ''}
        <td class="font-monospace">${escaparHtml(a.finca)} / ${escaparHtml(a.lote)}</td>
        <td><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> <span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(a.novedadNombre)}">${escaparHtml(a.novedadNombre)}</span></td>
        <td class="text-end tx-num text-nowrap">${cant(t.cantidad)} <span class="gp-chip-fila tx-chip-neutro">${escaparHtml(a.uMedida)}</span></td>
        <td class="text-nowrap">${escaparHtml(formatoHorario(a))}</td>
        <td>${texto(a.observacion) ? escaparHtml(a.observacion) : '<span class="text-muted">—</span>'}</td>
        <td>${chipEstado(a.estado)}</td>
    </tr>`).join('')).join('');
    const filasAusentes = ausentes.length ? `<tr class="tx-grupo"><td colspan="8">Ausentes</td></tr>${ausentes.map((p) => `<tr class="pl-fila-ausente">
        <td class="font-monospace fw-semibold">${escaparHtml(p.codigo || '—')}</td>
        <td><span class="pl-nombre">${escaparHtml(p.nombre)}</span><span class="gp-subtexto font-monospace">${escaparHtml(p.tercero)}</span></td>
        <td colspan="5"><span class="gp-subtexto">${texto(p.motivo) ? escaparHtml(p.motivo) : 'Sin motivo registrado'}</span></td>
        <td>${CHIP_AUSENTE}</td>
    </tr>`).join('')}` : '';
    const tarjetas = personas.map((p) => `<div class="pl-trab">
        <div class="d-flex align-items-center gap-2 mb-1"><span class="font-monospace fw-semibold">${escaparHtml(p.codigo || '—')}</span><span class="gp-truncar">${escaparHtml(p.nombre)}</span></div>
        ${p.filas.map(({ a, t }) => `<div class="pl-trab-linea"><span class="min-w-0">${lineaActividad(a, t)}</span>${chipEstado(a.estado)}</div>`).join('')}
    </div>`).join('') + ausentes.map((p) => `<div class="pl-trab pl-fila-ausente">
        <div class="d-flex align-items-center gap-2"><span class="font-monospace fw-semibold">${escaparHtml(p.codigo || '—')}</span><span class="gp-truncar pl-nombre">${escaparHtml(p.nombre)}</span><span class="ms-auto">${CHIP_AUSENTE}</span></div>
    </div>`).join('');

    $id('lista_trabajadores').innerHTML = `<div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0 gp-tabla pl-tabla-personas">
            <thead class="gp-thead"><tr><th>Código</th><th>Trabajador</th><th>Finca / Lote</th><th>Labor</th><th class="text-end">Cantidad</th><th>Horario</th><th>Observación</th><th>Estado</th></tr></thead>
            <tbody>${filas}${filasAusentes}</tbody>
        </table>
    </div>
    <div class="d-md-none">${tarjetas}</div>`;
};

const pintarBarraInferior = () => {
    const plan = estado.plan;
    const barra = $id('barra_inferior');

    mostrar(barra, Boolean(plan));

    if (!plan) return;

    if (plan.estado === 'CERRADA') {
        barra.innerHTML = `<div class="gp-llave-fija flex-grow-1 align-items-center">
            <i class="bi bi-lock" aria-hidden="true"></i>
            <span class="gp-llave-titulo flex-grow-1">Plan cerrado el ${escaparHtml(fechaHora(plan.fechaModificacion) || fechaLegible(plan.fecha))}${texto(plan.usuarioModificacion) ? ` por ${escaparHtml(plan.usuarioModificacion)}` : ''}</span>
            ${PERMISOS.editar ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-accion="reabrir-plan"><i class="bi bi-unlock me-1" aria-hidden="true"></i>Reabrir plan</button>' : ''}
        </div>`;

        return;
    }

    const faltan = plan.resumen.pendientes;

    barra.innerHTML = `<span class="gp-subtexto pl-faltan">${!plan.resumen.actividades ? 'El plan no tiene actividades' : (faltan ? `Faltan <strong>${fmt(faltan)}</strong> ${faltan === 1 ? 'actividad' : 'actividades'} por cerrar` : '<i class="bi bi-check2-all me-1" aria-hidden="true"></i>Todas las actividades están cerradas')}</span>
        <div class="d-flex flex-wrap justify-content-end gap-2 ms-auto pl-acciones-fin">
            ${editable() ? botonAgregar('d-sm-none w-100') : ''}
            ${PERMISOS.editar ? `<button type="button" class="btn btn-primary" data-accion="cerrar-plan"${faltan || !plan.resumen.actividades ? ' disabled' : ''}><i class="bi bi-lock me-1" aria-hidden="true"></i>Cerrar plan</button>` : ''}
        </div>`;
};

const pintarContenido = () => {
    const conDatos = estado.cargado && !estado.error && actividades().length > 0;

    mostrar($id('vista_lote'), conDatos && estado.vista === 'lote');
    mostrar($id('vista_trabajador'), conDatos && estado.vista === 'trabajador');

    if (!conDatos) return;

    if (estado.vista === 'lote') pintarVistaLote();
    else pintarVistaTrabajador();
};

const pintar = () => {
    pintarFecha();

    const listo = estado.cargado && !estado.error;
    const conPlan = listo && actividades().length > 0;

    mostrar($id('barra_plan'), listo);
    mostrar($id('kpis'), !estado.cargado || conPlan);
    mostrar($id('barra_inferior'), false);

    if (!estado.cargado) {
        $id('kpis').innerHTML = Array(5).fill('<div class="col-6 col-md-4 col-xl"><div class="inf-kpi placeholder-glow"><span class="placeholder col-12 pl-ph-kpi"></span></div></div>').join('');
        $id('estado_plan').innerHTML = Array(3).fill('<div class="tx-tarjeta pl-ph-tarjeta placeholder-glow"><span class="placeholder col-7 mb-3"></span><span class="placeholder col-12 mb-2"></span><span class="placeholder col-9"></span></div>').join('');
        mostrar($id('franja_ausentes'), false);
        mostrar($id('aviso_pendientes'), false);
        pintarContenido();

        return;
    }

    if (estado.error) {
        $id('estado_plan').innerHTML = estadoVacio('bi-exclamation-octagon', 'No se pudo cargar el plan', escaparHtml(estado.error),
            '<button type="button" class="btn btn-outline-primary" data-accion="reintentar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>', 'inf-medallon-error');
        mostrar($id('franja_ausentes'), false);
        mostrar($id('aviso_pendientes'), false);
        pintarContenido();

        return;
    }

    pintarBarra();
    pintarAusentes();
    pintarAviso();
    pintarPendientes();
    $id('estado_plan').innerHTML = conPlan ? '' : estadoVacio('bi-calendar-plus', `No hay actividades planeadas para el ${escaparHtml(fechaLegible(fechaPlan()))}`,
        'Agregue actividades o traslade pendientes de días anteriores.',
        `${editable() ? botonPlanilla() + botonAgregar() : ''}<button type="button" class="btn btn-outline-secondary" data-accion="pendientes"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Ver pendientes (${fmt(estado.pendientes.length)})</button>`);

    if (conPlan) pintarKpis();

    pintarBarraInferior();
    pintarContenido();
};

const filtrar = (clave) => {
    estado.filtro = clave;
    pintar();
};

const cambiarVista = (vista) => {
    estado.vista = vista;
    document.querySelectorAll('[data-vista]').forEach((boton) => {
        boton.classList.toggle('active', boton.dataset.vista === vista);
        boton.setAttribute('aria-selected', String(boton.dataset.vista === vista));
    });
    pintarContenido();
};

const guardarLista = async (lista, mensaje, destacar = null, eliminadas = [], propagar = false) => {
    if (estado.guardando) return false;

    const previos = new Set(actividades().map((a) => a.id));

    estado.guardando = true;
    mostrar($id('cargando_plan'), true);

    try {
        const respuesta = await pedir('guardar', {
            empresa: empresaId(),
            fecha: fechaPlan(),
            observacion: estado.plan?.observacion || '',
            actividades: JSON.stringify(lista),
            eliminadas: JSON.stringify(eliminadas)
        });
        const nueva = (respuesta.plan?.actividades || []).find((a) => !previos.has(a.id));

        aplicarPlan(respuesta.plan, destacar || nueva?.id || null);
        Toast.fire({ icon: 'success', title: escaparHtml(mensaje) });

        return true;
    } catch (error) {
        if (propagar) throw error;

        errorSwal(error, 'No se pudo guardar el plan').then(() => { if (/recargue el plan/i.test(error.message)) cargar(true); });

        return false;
    } finally {
        estado.guardando = false;
        mostrar($id('cargando_plan'), false);
    }
};

const ejecutar = async (ruta, datos, mensaje, destacar = null, titulo = 'No se pudo completar la operación') => {
    if (estado.guardando) return false;

    estado.guardando = true;
    mostrar($id('cargando_plan'), true);

    try {
        const respuesta = await pedir(ruta, datos);

        aplicarPlan(respuesta.plan, destacar);
        Toast.fire({ icon: 'success', title: escaparHtml(mensaje) });

        return true;
    } catch (error) {
        errorSwal(error, titulo);

        return false;
    } finally {
        estado.guardando = false;
        mostrar($id('cargando_plan'), false);
    }
};

const eliminarActividad = async (id) => {
    const a = actividad(id);

    if (!a) return;

    const lista = pendientesPlan().filter((x) => x.id !== a.id).map(aEnvio);
    const ultima = lista.length === 0 && actividades().length === 1;

    if (ultima && !PERMISOS.eliminar) {
        Toast.fire({ icon: 'warning', title: 'Es la única actividad del plan y no tiene permiso para eliminar planes.' });

        return;
    }

    if (!await confirmar('¿Eliminar la actividad?', ultima
        ? `Es la única actividad: se eliminará el plan del ${fechaLegible(fechaPlan())}.`
        : `${a.novedad} — ${a.novedadNombre} en el lote ${a.lote}.`, 'Sí, eliminar', true)) return;

    if (!ultima) {
        guardarLista(lista, 'Actividad eliminada', null, [a.id]);

        return;
    }

    try {
        await pedir('eliminar', { planeacion: estado.plan.id });
        estado.plan = null;
        pintar();
        Toast.fire({ icon: 'success', title: 'Plan eliminado' });
    } catch (error) {
        errorSwal(error, 'No se pudo eliminar el plan');
    }
};

const quitarTrabajador = async (id, tercero) => {
    const a = actividad(id);
    const t = a?.trabajadores.find((x) => x.tercero === tercero);

    if (!t || !await confirmar(`¿Quitar a ${texto(t.codigo) || tercero}?`, `Se quitará de ${a.novedad} en el lote ${a.lote}; la meta baja ${cant(t.cantidad)} ${a.uMedida}.`, 'Sí, quitar', true)) return;

    const lista = pendientesPlan().map((x) => {
        const envio = aEnvio(x);

        if (x.id === a.id) envio.trabajadores = envio.trabajadores.filter((w) => w.tercero !== tercero);

        return envio;
    });

    guardarLista(lista, 'Trabajador quitado', a.id);
};

const registrar = (id) => {
    const a = actividad(id);
    const destino = DESTINOS[a?.destino];

    if (!a || !destino?.puede || Number(a.registrable) !== 1) return;

    window.open(`${destino.url}?planeacion=${encodeURIComponent(a.id)}`, '_blank');
};

const codigoTercero = (opcion) => texto(opcion.codigo ?? opcion.element?.dataset.codigo);

const plantillaTercero = (opcion) => (opcion.nit
    ? $(`<span class="d-flex align-items-baseline gap-2">${codigoTercero(opcion) ? `<span class="gp-chip-fila verde font-monospace fw-semibold">${escaparHtml(codigoTercero(opcion))}</span>` : ''}<span class="text-truncate">${escaparHtml(opcion.text)}</span><span class="gp-subtexto font-monospace ms-auto">${escaparHtml(opcion.nit)}</span></span>`)
    : opcion.text);

const opcionTercero = (t) => {
    const opcion = new Option(texto(t.nombre) || t.tercero, t.tercero, true, true);

    opcion.dataset.codigo = texto(t.codigo);

    return opcion;
};

const form = { id: null, trabajadores: [], manual: false };

const laborOpcion = () => $id('act_labor').selectedOptions[0] || null;
const umFormulario = () => $id('act_um').value;
const grupoFormulario = () => texto(laborOpcion()?.dataset.grupo);

const errorCampo = (id, visible, mensaje = null) => {
    if (mensaje !== null) $id(id).textContent = mensaje;
    mostrar($id(id), visible);
};

const pintarIndicador = (elemento, asignado, meta, um, motivo = '') => {
    const c = Number.isNaN(asignado) ? NaN : centavos(asignado);
    const m = centavos(meta);
    const clase = motivo || !(m > 0) || Number.isNaN(c) ? (motivo ? 'pendiente' : 'inactivo') : (c === m ? 'cuadrado' : (c > m ? 'excedido' : 'pendiente'));
    const icono = { cuadrado: 'bi-check-circle', excedido: 'bi-exclamation-octagon', pendiente: 'bi-hourglass-split', inactivo: 'bi-dash-circle' }[clase];

    elemento.className = `tx-reparto tx-reparto--${clase} pl-indicador`;
    elemento.innerHTML = `<span class="tx-estado"><i class="bi ${icono}" aria-hidden="true"></i>${motivo
        ? escaparHtml(motivo)
        : `Asignado <span class="tx-estado-num">${Number.isNaN(c) ? '—' : cant(asignado)} / ${cant(meta)}</span> ${escaparHtml(um)}`}</span><div class="tx-reparto-barra"><span></span></div>`;
    elemento.querySelector('.tx-reparto-barra span').style.width = `${m > 0 && !Number.isNaN(c) ? Math.min(100, Math.round(c * 100 / m)) : 0}%`;
};

const sumaFormulario = () => form.trabajadores.reduce((suma, t) => suma + t.cantidad, 0);

const indicadorActividad = () => pintarIndicador($id('act_indicador'), sumaFormulario(), aNumero($id('act_meta').value), umFormulario() || '');

const duplicadoFormulario = (t) => {
    const novedad = $id('act_labor').value;
    const finca = $id('act_finca').value;
    const lote = $id('act_lote').value;
    const otra = novedad && finca && lote ? duplicado(t.tercero, novedad, finca, lote, form.id) : null;

    return otra ? `${texto(t.codigo) || t.tercero} ya tiene esta labor en ${otra.lote}` : '';
};

const pintarReparto = () => {
    $id('act_contador').textContent = fmt(form.trabajadores.length);
    $id('act_filas').innerHTML = form.trabajadores.length ? form.trabajadores.map((t, i) => {
        const nombre = escaparHtml(t.nombre || t.tercero);
        const error = duplicadoFormulario(t);

        return `<tr class="${error ? 'pl-fila-dup' : ''}">
            <td><span class="font-monospace fw-semibold me-1">${escaparHtml(t.codigo)}</span><span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span>${t.reasignadoDe ? `<span class="gp-chip-fila ambar ms-1">recibe de ${escaparHtml(codigoDe(t.reasignadoDe))}</span>` : ''}${error ? `<div class="invalid-feedback d-block">${escaparHtml(error)}</div>` : ''}</td>
            <td class="text-end"><input type="text" class="form-control form-control-sm tx-input-num font-monospace ms-auto" data-reparto="${i}" value="${valorInput(t.cantidad)}" inputmode="decimal" maxlength="14" autocomplete="off" aria-label="Cantidad de ${nombre}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-quitar-reparto="${escaparHtml(t.tercero)}" title="Quitar a ${nombre}" aria-label="Quitar a ${nombre}"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
        </tr>`;
    }).join('') : '<tr><td colspan="3" class="text-center text-muted py-3">Seleccione los trabajadores de la actividad.</td></tr>';
    indicadorActividad();
};

const repartirFormulario = () => {
    const meta = aNumero($id('act_meta').value);

    if (!form.trabajadores.length || !(meta >= 0)) return;

    repartir(meta, form.trabajadores.length).forEach((valor, i) => { form.trabajadores[i].cantidad = valor; });
};

const sincronizarTrabajadores = () => {
    const datos = $('#act_terceros').select2('data').filter((opcion) => opcion.id);

    form.trabajadores = datos.map((opcion) => form.trabajadores.find((t) => t.tercero === opcion.id)
        || { tercero: opcion.id, codigo: codigoTercero(opcion), nombre: texto(opcion.text), cantidad: 0, reasignadoDe: '' });

    if (!form.manual) repartirFormulario();

    errorCampo('error_act_terceros', false);
    $id('act_terceros').closest('.tx-select-ancho').classList.remove('is-invalid');
    pintarReparto();
};

const actualizarLabor = () => {
    $id('act_codigo').value = $id('act_labor').value;
    $id('act_codigo').classList.remove('is-invalid');
    errorCampo('error_act_labor', false);
    $id('act_labor').closest('.tx-select-ancho').classList.remove('is-invalid');
    $id('act_meta_um').textContent = umFormulario() || '—';
    mostrar($id('col_pfa'), grupoFormulario() === '02');

    if (grupoFormulario() === '02') cargarPfa($id('act_pfa').dataset.previo || '');

    pintarReparto();
};

const asegurarOpcion = (selector, valor) => {
    if (valor && ![...selector.options].some((o) => o.value === valor)) selector.add(new Option(valor, valor));
};

const opcionLote = (l) => `<option value="${escaparHtml(texto(l.codigo))}" data-nombre="${escaparHtml(texto(l.descripcion))}" data-seccion="${escaparHtml(texto(l.seccion))}">${escaparHtml(texto(l.codigo))} — ${escaparHtml(texto(l.descripcion))}</option>`;

const opcionPfa = (p) => `<option value="${escaparHtml(texto(p.numero))}">PFA-${escaparHtml(texto(p.numero))}${texto(p.observacion) ? ` — ${escaparHtml(texto(p.observacion))}` : ''}</option>`;

const cargarLotes = async (finca, lote = '') => {
    const selector = $id('act_lote');
    const turno = ++turnoLotes;

    selector.disabled = true;
    selector.innerHTML = `<option value="">${finca ? 'Cargando…' : 'Seleccione una finca primero'}</option>`;
    $('#act_lote').trigger('change.select2');

    if (!finca) return;

    let filas = [];

    try {
        filas = (await pedir('lotes', { empresa: empresaId(), finca })).filas || [];
    } catch (error) {
        avisar(error);
    }

    if (turno !== turnoLotes) return;

    selector.innerHTML = '<option value=""></option>' + filas
        .filter((l) => Number(l.activo ?? 1) !== 0 || texto(l.codigo) === lote)
        .map(opcionLote)
        .join('');
    selector.disabled = false;
    $('#act_lote').val(lote).trigger('change.select2');
};

const cargarPfa = async (referencia = '') => {
    const selector = $id('act_pfa');
    const finca = $id('act_finca').value;

    selector.innerHTML = '<option value="">Sin referencia</option>';

    if (!finca) return;

    try {
        const filas = (await pedir('planesFertilizacion', { empresa: empresaId(), finca, fecha: fechaPlan() })).filas || [];

        selector.innerHTML += filas.map(opcionPfa).join('');
    } catch (error) {
        avisar(error);
    }

    selector.value = Array.from(selector.options).some((o) => o.value === referencia) ? referencia : '';
};

const contarObservacion = () => {
    $id('contador_act_observacion').textContent = `${$id('act_observacion').value.length} / 250`;
};

const abrirActividad = async (base = null, duplicar = false) => {
    const editar = Boolean(base) && !duplicar;

    form.id = editar ? base.id : null;
    form.manual = Boolean(base);
    form.trabajadores = base ? base.trabajadores.filter((t) => !ausente(t)).map((t) => ({
        tercero: t.tercero, codigo: texto(t.codigo), nombre: texto(t.nombre), cantidad: Number(t.cantidad), reasignadoDe: editar ? texto(t.reasignadoDe) : ''
    })) : [];

    $id('icono_actividad').className = `bi ${editar ? 'bi-pencil-square' : 'bi-plus-square'} me-1`;
    $id('texto_actividad').textContent = editar ? 'Editar actividad' : 'Agregar actividad';
    $id('chip_fecha_actividad').textContent = fechaLegible(fechaPlan());
    $id('chip_duplicada').textContent = duplicar ? `Duplicada de ${base.novedad} — ${base.novedadNombre}` : '';
    mostrar($id('chip_duplicada'), duplicar);
    mostrar($id('btn_guardar_otra'), !editar);

    document.querySelectorAll('#formulario_actividad .is-invalid').forEach((campo) => campo.classList.remove('is-invalid'));
    ['error_act_finca', 'error_act_lote', 'error_act_labor', 'error_act_um', 'error_act_meta', 'error_act_horario', 'error_act_terceros'].forEach((id) => errorCampo(id, false));

    $id('act_pfa').dataset.previo = editar ? texto(base.referencia) : '';
    $('#act_finca').val(base?.finca || '').trigger('change.select2');
    $('#act_labor').val(editar ? base.novedad : '').trigger('change.select2');
    asegurarOpcion($id('act_um'), texto(base?.uMedida));
    $('#act_um').val(base?.uMedida || '').trigger('change.select2');
    $id('act_meta').value = base ? valorInput(Number(base.cantidad)) : '';
    $id('act_hora_inicio').value = base ? texto(base.horaInicio) : '06:00';
    $id('act_hora_fin').value = base ? texto(base.horaFin) : '14:00';
    $id('act_observacion').value = base ? texto(base.observacion) : '';
    contarObservacion();
    $('#act_terceros').empty().append(form.trabajadores.map(opcionTercero)).trigger('change.select2');
    actualizarLabor();
    bootstrap.Modal.getOrCreateInstance($id('modalActividad')).show();
    await cargarLotes(base?.finca || '', base?.lote || '');
    pintarReparto();
};

const validarActividad = () => {
    const finca = $id('act_finca').value;
    const lote = $id('act_lote').value;
    const labor = $id('act_labor').value;
    const um = umFormulario();
    const meta = aNumero($id('act_meta').value);
    const inicio = $id('act_hora_inicio').value;
    const fin = $id('act_hora_fin').value;
    const horaMal = Boolean(inicio) !== Boolean(fin) || (inicio && fin && fin <= inicio);
    const invalidos = form.trabajadores.some((t) => Number.isNaN(t.cantidad) || t.cantidad < 0);
    const duplicados = form.trabajadores.map(duplicadoFormulario).filter(Boolean);
    const marcar = (id, error, invalido) => {
        errorCampo(error, invalido);
        $id(id).closest('.tx-select-ancho')?.classList.toggle('is-invalid', invalido);
    };

    marcar('act_finca', 'error_act_finca', !finca);
    marcar('act_lote', 'error_act_lote', !lote);
    marcar('act_labor', 'error_act_labor', !labor);
    marcar('act_um', 'error_act_um', !um);
    errorCampo('error_act_horario', horaMal, inicio && fin ? 'La hora fin debe ser posterior a la de inicio.' : 'Indique la hora de inicio y la hora fin, o deje ambas vacías.');
    ['act_hora_inicio', 'act_hora_fin'].forEach((id) => $id(id).classList.toggle('is-invalid', Boolean(horaMal)));
    errorCampo('error_act_meta', !(meta > 0));
    $id('act_meta').classList.toggle('is-invalid', !(meta > 0));
    marcar('act_terceros', 'error_act_terceros', !form.trabajadores.length);

    if (!finca || !lote || !labor || !um || !(meta > 0) || !form.trabajadores.length) return 'Complete los campos obligatorios.';
    if (horaMal) return $id('error_act_horario').textContent;
    if (invalidos) return 'Hay cantidades inválidas: use números mayores o iguales a cero con máximo 2 decimales.';
    if (centavos(sumaFormulario()) !== centavos(meta)) return `La suma asignada (${cant(sumaFormulario())}) debe ser igual a la meta (${cant(meta)} ${um}).`;
    if (duplicados.length) return duplicados[0];

    return '';
};

const guardarActividad = async (otra = false) => {
    if (estado.guardando) return;

    if (form.id && !pendientesPlan().some((a) => a.id === form.id)) {
        bootstrap.Modal.getOrCreateInstance($id('modalActividad')).hide();
        Toast.fire({ icon: 'warning', title: 'La actividad ya no está pendiente; se recargó el plan.' });
        cargar(true);

        return;
    }

    const error = validarActividad();

    if (error) {
        Toast.fire({ icon: 'warning', title: escaparHtml(error) });

        return;
    }

    const datos = {
        id: form.id,
        finca: $id('act_finca').value,
        lote: $id('act_lote').value,
        novedad: $id('act_labor').value,
        uMedida: umFormulario(),
        horaInicio: $id('act_hora_inicio').value,
        horaFin: $id('act_hora_fin').value,
        observacion: texto($id('act_observacion').value),
        referencia: grupoFormulario() === '02' ? $id('act_pfa').value : '',
        trabajadores: form.trabajadores.map((t) => ({ tercero: t.tercero, cantidad: t.cantidad, reasignadoDe: t.reasignadoDe }))
    };
    const lista = pendientesPlan().map((a) => (a.id === form.id ? datos : aEnvio(a)));

    if (!form.id) {
        delete datos.id;
        lista.push(datos);
    }

    const ok = await guardarLista(lista, form.id ? 'Actividad actualizada' : 'Actividad agregada', form.id);

    if (!ok) return;

    if (!otra) {
        bootstrap.Modal.getOrCreateInstance($id('modalActividad')).hide();

        return;
    }

    form.trabajadores = [];
    form.manual = false;
    $id('act_pfa').dataset.previo = '';
    $('#act_labor').val('').trigger('change.select2');
    $('#act_um').val('').trigger('change.select2');
    $('#act_terceros').empty().trigger('change.select2');
    $id('act_meta').value = '';
    $id('act_observacion').value = '';
    contarObservacion();
    $id('chip_duplicada').textContent = '';
    mostrar($id('chip_duplicada'), false);
    actualizarLabor();
    $id('act_codigo').focus();
};

const edicion = { id: null, tercero: '' };

const resumenEdicion = () => {
    const a = actividad(edicion.id);
    const t = a?.trabajadores.find((x) => x.tercero === edicion.tercero);
    const cantidad = aNumero($id('edit_cantidad').value);

    if (!t) return;

    $id('edit_resumen').textContent = `Meta de la actividad: ${cant(a.cantidad)} → ${cantidad >= 0 ? cant(a.cantidad - t.cantidad + cantidad) : '—'} ${a.uMedida}`;
};

const abrirEdicionTrabajador = (id, tercero) => {
    const a = actividad(id);
    const t = a?.trabajadores.find((x) => x.tercero === tercero);

    if (!t) return;

    edicion.id = a.id;
    edicion.tercero = tercero;
    $id('chip_editar_trabajador').textContent = `${a.lote} · ${a.novedad}`;
    $('#edit_tercero').empty().append(opcionTercero(t)).trigger('change');
    $id('edit_um').textContent = `(${a.uMedida})`;
    $id('edit_cantidad').value = valorInput(Number(t.cantidad));
    errorCampo('error_edit_tercero', false);
    errorCampo('error_edit_cantidad', false);
    $id('edit_tercero').closest('.tx-select-ancho').classList.remove('is-invalid');
    $id('edit_cantidad').classList.remove('is-invalid');
    resumenEdicion();
    bootstrap.Modal.getOrCreateInstance($id('modalEditarTrabajador')).show();
};

const guardarEdicionTrabajador = async (evento) => {
    evento.preventDefault();

    const a = actividad(edicion.id);

    if (!a) return;

    const seleccion = $('#edit_tercero').select2('data')[0] || {};
    const nuevo = texto(seleccion.id);
    const cantidad = aNumero($id('edit_cantidad').value);
    const otra = nuevo && nuevo !== edicion.tercero ? duplicado(nuevo, a.novedad, a.finca, a.lote, a.id) : null;
    const error = !nuevo ? 'Seleccione el tercero.'
        : (nuevo !== edicion.tercero && a.trabajadores.some((t) => t.tercero === nuevo) ? 'Ese trabajador ya está en esta actividad.'
            : (otra ? `${codigoTercero(seleccion) || nuevo} ya tiene esta labor en ${otra.lote}` : ''));

    errorCampo('error_edit_tercero', error !== '', error);
    $id('edit_tercero').closest('.tx-select-ancho').classList.toggle('is-invalid', error !== '');
    errorCampo('error_edit_cantidad', !(cantidad >= 0));
    $id('edit_cantidad').classList.toggle('is-invalid', !(cantidad >= 0));

    if (error || !(cantidad >= 0)) return;

    const lista = pendientesPlan().map((x) => {
        const envio = aEnvio(x);

        if (x.id === a.id) {
            envio.trabajadores = envio.trabajadores.map((w) => (w.tercero === edicion.tercero
                ? { tercero: nuevo, cantidad, reasignadoDe: nuevo === edicion.tercero ? w.reasignadoDe : '' } : w));
        }

        return envio;
    });

    if (await guardarLista(lista, 'Trabajador actualizado', a.id)) bootstrap.Modal.getOrCreateInstance($id('modalEditarTrabajador')).hide();
};

const cierre = { id: null, estado: '' };

const saldoCierre = () => {
    const a = actividad(cierre.id);
    const ejecutada = aNumero($id('cierre_cantidad').value);

    $id('cierre_saldo').textContent = a && ejecutada > 0 && ejecutada < a.cantidad ? `Saldo: ${cant(a.cantidad - ejecutada)} ${a.uMedida}` : '';
};

const abrirCierre = (id, clave) => {
    const a = actividad(id);

    if (!a) return;

    const parcial = clave === 'PARCIAL';

    cierre.id = a.id;
    cierre.estado = clave;
    $id('cierre_chip').innerHTML = chipEstado(clave);
    $id('cierre_referencia').innerHTML = `<div class="d-flex flex-wrap gap-3">
        <div><span class="gp-etiqueta-mini d-block">Lote</span><span class="font-monospace fw-semibold">${escaparHtml(a.lote)}</span></div>
        <div class="min-w-0 flex-grow-1"><span class="gp-etiqueta-mini d-block">Labor</span><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> ${escaparHtml(a.novedadNombre)}</div>
        <div class="text-end"><span class="gp-etiqueta-mini d-block">Meta</span><span class="tx-num fw-semibold">${cant(a.cantidad)} ${escaparHtml(a.uMedida)}</span></div>
    </div>`;
    mostrar($id('cierre_col_cantidad'), parcial);
    $id('cierre_um').textContent = a.uMedida;
    $id('cierre_cantidad').value = '';
    $id('cierre_observacion').value = '';
    $id('contador_cierre').textContent = '0 / 250';
    $id('cierre_saldo').textContent = '';
    ['cierre_cantidad', 'cierre_observacion'].forEach((campo) => $id(campo).classList.remove('is-invalid'));
    errorCampo('error_cierre_cantidad', false);
    errorCampo('error_cierre_observacion', false);
    $id('cierre_confirmar').className = `btn ${parcial ? 'btn-warning' : 'btn-danger'}`;
    $id('cierre_confirmar').innerHTML = parcial
        ? '<i class="bi bi-circle-half me-1" aria-hidden="true"></i>Cerrar como parcial'
        : '<i class="bi bi-x-lg me-1" aria-hidden="true"></i>Marcar no ejecutada';
    bootstrap.Modal.getOrCreateInstance($id('modalCierre')).show();
};

const guardarCierre = async (evento) => {
    evento.preventDefault();

    const a = actividad(cierre.id);

    if (!a) return;

    const parcial = cierre.estado === 'PARCIAL';
    const ejecutada = aNumero($id('cierre_cantidad').value);
    const observacion = texto($id('cierre_observacion').value);
    const errorCantidad = parcial && !(ejecutada > 0 && ejecutada < a.cantidad);

    errorCampo('error_cierre_cantidad', errorCantidad, `Indique una cantidad mayor que cero y menor que la meta (${cant(a.cantidad)}).`);
    $id('cierre_cantidad').classList.toggle('is-invalid', errorCantidad);
    errorCampo('error_cierre_observacion', observacion === '');
    $id('cierre_observacion').classList.toggle('is-invalid', observacion === '');

    if (errorCantidad || observacion === '') return;

    const ok = await ejecutar('cerrarActividad', {
        linea: a.id, estado: cierre.estado, cantidadEjecutada: parcial ? ejecutada : 0, observacionCierre: observacion
    }, parcial ? 'Actividad cerrada como parcial' : 'Actividad marcada como no ejecutada', a.id, 'No se pudo cerrar la actividad');

    if (ok) bootstrap.Modal.getOrCreateInstance($id('modalCierre')).hide();
};

const aus = { tercero: '', codigo: '', nombre: '', pasos: [], i: 0 };

const visiblesPaso = (paso) => paso.candidatos.filter((c) => !c.otro || paso.ampliar);
const marcadosPaso = (paso) => visiblesPaso(paso).filter((c) => c.marcado);

const repartirPaso = (paso) => {
    const marcados = marcadosPaso(paso);

    paso.candidatos.forEach((c) => { if (!marcados.includes(c)) c.cantidad = 0; });

    if (marcados.length) repartir(paso.cantidad, marcados.length).forEach((valor, i) => { marcados[i].cantidad = valor; });
};

const motivoPaso = (paso) => {
    const marcados = marcadosPaso(paso);

    if (marcados.some((c) => Number.isNaN(c.cantidad) || c.cantidad < 0)) return 'Hay cantidades inválidas';
    if (!marcados.length) return paso.cantidad === 0 && paso.a.trabajadores.length > 1 ? '' : 'Seleccione al menos un trabajador';

    return '';
};

const pasoValido = (paso) => !motivoPaso(paso)
    && centavos(marcadosPaso(paso).reduce((suma, c) => suma + c.cantidad, 0)) === centavos(paso.cantidad);

const actualizarPaso = () => {
    const paso = aus.pasos[aus.i];
    const ultimo = aus.i === aus.pasos.length - 1;

    pintarIndicador($id('aus_indicador'), marcadosPaso(paso).reduce((suma, c) => suma + c.cantidad, 0), paso.cantidad, paso.a.uMedida, motivoPaso(paso));
    $id('aus_siguiente').disabled = !pasoValido(paso);
    $id('aus_siguiente').innerHTML = ultimo ? '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>Confirmar ausencia' : 'Siguiente<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>';

    if (ultimo) {
        $id('aus_resumen').innerHTML = aus.pasos.map((p) => `<div><span class="font-monospace fw-semibold">${escaparHtml(p.a.lote)}</span> ${escaparHtml(p.a.novedadNombre || p.a.novedad)} → ${marcadosPaso(p).map((c) => `<span class="font-monospace">${escaparHtml(c.codigo || c.tercero)}</span> ${Number.isNaN(c.cantidad) ? '—' : cant(c.cantidad)}`).join(', ') || '<span class="text-muted">sin reparto</span>'}</div>`).join('');
    }
};

const candidatoHtml = (c, k) => `<div class="pl-candidato${c.marcado ? ' activo' : ''}">
    <input class="form-check-input" type="checkbox" id="aus_c_${k}" data-cand="${k}"${c.marcado ? ' checked' : ''}>
    <label class="form-check-label min-w-0" for="aus_c_${k}"><span class="font-monospace fw-semibold me-1">${escaparHtml(c.codigo || c.tercero)}</span><span class="gp-truncar d-inline-block align-bottom">${escaparHtml(c.nombre)}</span></label>
    <input type="text" class="form-control form-control-sm tx-input-num font-monospace" data-cand-cant="${k}" value="${c.marcado ? valorInput(c.cantidad) : ''}" inputmode="decimal" maxlength="14" autocomplete="off" aria-label="Cantidad para ${escaparHtml(c.nombre)}"${c.marcado ? '' : ' disabled'}>
</div>`;

const pintarAusencia = () => {
    const paso = aus.pasos[aus.i];
    const a = paso.a;
    const companeros = paso.candidatos.map((c, k) => [c, k]).filter(([c]) => !c.otro);
    const otros = paso.candidatos.map((c, k) => [c, k]).filter(([c]) => c.otro);

    $id('aus_pasos').innerHTML = aus.pasos.map((p, i) => `${i ? '<span class="gp-cascada-sep"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>' : ''}<span class="gp-cascada-paso${i === aus.i ? ' activo' : ''}${i < aus.i ? ' pl-paso-hecho' : ''}">${i < aus.i ? '<i class="bi bi-check me-1" aria-hidden="true"></i>' : ''}<span class="font-monospace me-1">${escaparHtml(p.a.lote)}</span>${escaparHtml(p.a.novedad)}</span>`).join('');
    $id('aus_contador').textContent = `Actividad ${aus.i + 1} de ${aus.pasos.length}`;
    mostrar($id('aus_atras'), aus.i > 0);
    $id('aus_cuerpo').innerHTML = `<div class="gp-referencia mb-3 d-flex flex-wrap align-items-center gap-3">
            <div><span class="gp-etiqueta-mini d-block">Lote</span><span class="font-monospace fw-semibold">${escaparHtml(a.lote)}</span></div>
            <div class="min-w-0 flex-grow-1"><span class="gp-etiqueta-mini d-block">Labor</span><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> ${escaparHtml(a.novedadNombre)}</div>
            <div><span class="gp-etiqueta-mini d-block">Meta</span><span class="tx-num">${cant(a.cantidad)} ${escaparHtml(a.uMedida)}</span></div>
            <div class="text-end"><span class="gp-etiqueta-mini d-block">A reasignar</span><span class="inf-kpi-valor">${cant(paso.cantidad)}</span></div>
        </div>
        ${companeros.length
            ? `<div class="gp-subseccion"><i class="bi bi-people" aria-hidden="true"></i>Compañeros en la actividad</div><div class="pl-candidatos">${companeros.map(([c, k]) => candidatoHtml(c, k)).join('')}</div>`
            : '<div class="gp-estado-vacio text-center py-3"><div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi bi-person-dash" aria-hidden="true"></i></div><h6 class="mb-0">No hay compañeros en esta actividad</h6></div>'}
        <div class="gp-panel-opcion my-3${paso.ampliar ? ' activo' : ''}">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="aus_ampliar"${paso.ampliar ? ' checked' : ''}${otros.length ? '' : ' disabled'}>
                <label class="form-check-label fw-semibold" for="aus_ampliar">Ampliar a todos los presentes</label>
            </div>
            ${otros.length ? '' : '<div class="form-text mb-0">No hay otros trabajadores presentes disponibles para esta labor.</div>'}
        </div>
        ${paso.ampliar && otros.length ? `<div class="gp-subseccion"><i class="bi bi-person-plus" aria-hidden="true"></i>Otros presentes del día</div><div class="pl-candidatos">${otros.map(([c, k]) => candidatoHtml(c, k)).join('')}</div>` : ''}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <button type="button" class="btn btn-link btn-sm p-0" id="aus_repartir"><i class="bi bi-distribute-horizontal me-1" aria-hidden="true"></i>Repartir en partes iguales</button>
            <div class="tx-reparto pl-indicador" id="aus_indicador"></div>
        </div>
        ${aus.i === aus.pasos.length - 1 ? '<div class="gp-nota mt-3"><div class="fw-semibold mb-1">Resumen del reparto</div><div id="aus_resumen"></div></div>' : ''}`;
    actualizarPaso();
};

const enviarAusencia = async (reparto) => {
    const ok = await ejecutar('ausencia', {
        planeacion: estado.plan.id,
        tercero: aus.tercero,
        motivo: texto($id('aus_motivo').value),
        reparto: JSON.stringify(reparto)
    }, `Ausencia registrada · ${reparto.length} ${reparto.length === 1 ? 'actividad reasignada' : 'actividades reasignadas'}`, null, 'No se pudo registrar la ausencia');

    if (ok) bootstrap.Modal.getOrCreateInstance($id('modalAusencia')).hide();
};

const abrirAusencia = async (tercero) => {
    const persona = personasPlan().find((p) => p.tercero === tercero);

    if (!persona || !estado.plan) return;

    Object.assign(aus, { tercero, codigo: persona.codigo, nombre: persona.nombre, i: 0 });

    const presentes = personasPlan().filter((p) => p.tercero !== tercero);

    aus.pasos = pendientesPlan().filter((a) => a.trabajadores.some((t) => t.tercero === tercero)).map((a) => {
        const companeros = a.trabajadores.filter((t) => t.tercero !== tercero && !ausente(t))
            .map((t) => ({ tercero: t.tercero, codigo: texto(t.codigo), nombre: texto(t.nombre), marcado: true, cantidad: 0, otro: false }));
        const otros = presentes.filter((p) => !a.trabajadores.some((t) => t.tercero === p.tercero) && !duplicado(p.tercero, a.novedad, a.finca, a.lote, a.id))
            .map((p) => ({ ...p, marcado: false, cantidad: 0, otro: true }));
        const paso = { a, cantidad: Number(a.trabajadores.find((t) => t.tercero === tercero).cantidad), candidatos: [...companeros, ...otros], ampliar: companeros.length === 0 };

        repartirPaso(paso);

        return paso;
    });

    $id('aus_titulo').textContent = `Reasignar a ${persona.codigo || tercero} · ${persona.nombre}`;
    $id('aus_motivo').value = '';

    if (!aus.pasos.length) {
        if (await confirmar(`¿Marcar ausente a ${persona.codigo || tercero}?`, 'No tiene actividades pendientes en el plan.', 'Sí, marcar ausente')) enviarAusencia([]);

        return;
    }

    pintarAusencia();
    bootstrap.Modal.getOrCreateInstance($id('modalAusencia')).show();
};

const elegirAusente = async () => {
    const personas = personasPlan();

    if (!estado.plan || !editable()) {
        if (estado.plan?.ausencias.length) document.querySelector('#franja_ausentes')?.scrollIntoView({ behavior: reducido() ? 'auto' : 'smooth', block: 'center' });

        return;
    }

    if (!personas.length) {
        Toast.fire({ icon: 'info', title: 'No hay trabajadores presentes en el plan.' });

        return;
    }

    const { value } = await Swal.fire({
        title: 'Marcar ausencia',
        input: 'select',
        inputOptions: Object.fromEntries(personas.map((p) => [p.tercero, `${escaparHtml(p.codigo || p.tercero)} · ${escaparHtml(p.nombre)}`])),
        inputPlaceholder: 'Seleccione el trabajador…',
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar',
        inputValidator: (valor) => (valor ? undefined : 'Seleccione el trabajador.')
    });

    if (value) abrirAusencia(value);
};

const marcarPresente = async (tercero) => {
    const persona = (estado.plan?.ausencias || []).find((a) => a.tercero === tercero);

    if (!persona || !await confirmar(`¿Marcar presente a ${persona.codigo || tercero}?`, 'Las cantidades reasignadas no se revierten.', 'Sí, marcar presente')) return;

    ejecutar('quitarAusencia', { planeacion: estado.plan.id, tercero }, 'Trabajador marcado como presente');
};

const pintarPendientes = () => {
    const filas = estado.pendientes;
    const puede = editable();
    const ausentes = nitsAusentes();

    if (!filas.length) {
        $id('lista_pendientes').innerHTML = `<div class="gp-estado-vacio text-center py-5">
            <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi bi-check2-all" aria-hidden="true"></i></div>
            <h6 class="mb-0">No hay saldos pendientes</h6>
        </div>`;

        return;
    }

    $id('lista_pendientes').innerHTML = agrupar([...filas].sort((x, y) => y.fecha.localeCompare(x.fecha)), (p) => p.fecha).map(([fecha, lista]) => `<div class="mb-3">
        <span class="gp-grupo-titulo mb-2">${escaparHtml(fechaCorta(fecha))}</span>
        ${lista.map((p) => {
        const fuera = p.trabajadores.filter((t) => ausentes.has(t.tercero));

        return `<div class="pl-pendiente ${ESTADOS[p.estado]?.clase || ''}" data-pendiente="${p.id}">
            <div class="d-flex flex-wrap align-items-baseline gap-2"><span class="font-monospace fw-semibold">${escaparHtml(p.finca)} / ${escaparHtml(p.lote)}</span><span class="min-w-0"><span class="font-monospace">${escaparHtml(p.novedad)}</span> ${escaparHtml(p.novedadNombre)}</span></div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                <span class="tx-dato"><span class="gp-etiqueta-mini">Saldo</span><span class="tx-dato-valor">${cant(p.saldo)} ${escaparHtml(p.uMedida)}</span></span>
                <span class="tx-dato"><span class="gp-etiqueta-mini">Horario</span><span class="tx-dato-valor"><i class="bi bi-clock me-1" aria-hidden="true"></i>${escaparHtml(formatoHorario(p))}</span></span>
                ${chipEstado(p.estado)}
            </div>
            <span class="gp-subtexto mt-1">${texto(p.observacion) ? `${escaparHtml(p.observacion)} · ` : ''}${p.trabajadores.map((t) => escaparHtml(t.codigo || t.tercero)).join(', ')}</span>
            ${fuera.length ? `<div class="gp-nota-alerta mt-2"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>${fuera.map((t) => escaparHtml(t.codigo || t.tercero)).join(', ')} ${fuera.length === 1 ? 'está ausente' : 'están ausentes'}: se asignará a los demás.</div>` : ''}
            ${puede ? `<button type="button" class="btn btn-success btn-sm mt-2 pl-trasladar" data-accion="trasladar" data-pendiente="${p.id}"><i class="bi bi-arrow-down-circle me-1" aria-hidden="true"></i>Trasladar</button>` : ''}
        </div>`;
    }).join('')}
    </div>`).join('');
};

const trasladar = async (id) => {
    if (estado.guardando) return;

    const item = document.querySelector(`.pl-pendiente[data-pendiente="${id}"]`);
    const origen = estado.pendientes.find((p) => p.id === Number(id));
    const previos = new Set(actividades().map((a) => a.id));

    estado.guardando = true;
    item?.classList.add('pl-ocupado');

    try {
        const respuesta = await pedir('trasladar', { empresa: empresaId(), fecha: fechaPlan(), lineas: JSON.stringify([Number(id)]) });
        const nueva = (respuesta.plan?.actividades || []).find((a) => !previos.has(a.id));

        item?.classList.add('pl-saliendo');
        await new Promise((fin) => setTimeout(fin, reducido() ? 0 : 250));
        estado.pendientes = estado.pendientes.filter((p) => p.id !== Number(id));
        estado.plan = respuesta.plan || estado.plan;
        estado.destacar = nueva?.id || null;
        pintar();
        estado.destacar = null;
        Toast.fire({
            icon: 'success', title: `Trasladada a ${escaparHtml(origen?.lote || '')}`, showConfirmButton: Boolean(nueva), confirmButtonText: 'Ver', timer: 5000
        }).then((r) => {
            if (!r.isConfirmed || !nueva) return;

            bootstrap.Offcanvas.getOrCreateInstance($id('panelPendientes')).hide();
            $id(`act_${nueva.id}`)?.scrollIntoView({ behavior: reducido() ? 'auto' : 'smooth', block: 'center' });
        });

        if (!respuesta.plan) cargar(true);
    } catch (error) {
        item?.classList.remove('pl-ocupado');
        errorSwal(error, 'No se pudo trasladar el pendiente');
    } finally {
        estado.guardando = false;
    }
};

const imprimir = () => {
    const plan = estado.plan;

    if (!plan || !actividades().length) {
        Toast.fire({ icon: 'info', title: 'No hay actividades para imprimir.' });

        return;
    }

    const ordenadas = [...actividades()].sort((x, y) => x.finca.localeCompare(y.finca, 'es', { numeric: true }) || x.lote.localeCompare(y.lote, 'es', { numeric: true }) || x.orden - y.orden);
    const personas = new Set();
    let numero = 0;

    const cuerpo = agrupar(ordenadas, (a) => a.finca).map(([finca, lista]) => `<tr class="pl-imp-grupo"><td colspan="10">${escaparHtml(finca)} — ${escaparHtml(lista[0].fincaNombre)}</td></tr>${lista.map((a) => a.trabajadores.filter((t) => !ausente(t)).map((t) => {
        numero++;
        personas.add(t.tercero);

        return `<tr>
            <td class="text-center">${numero}</td>
            <td class="font-monospace">${escaparHtml(t.codigo)}</td>
            <td>${escaparHtml(t.nombre)}</td>
            <td>${escaparHtml(a.lote)}</td>
            <td>${escaparHtml(a.novedad)} ${escaparHtml(a.novedadNombre)}</td>
            <td>${escaparHtml(a.uMedida)}</td>
            <td class="text-end">${cant(t.cantidad)}</td>
            <td class="text-nowrap">${escaparHtml(formatoHorario(a))}</td>
            <td>${escaparHtml(a.observacion)}</td>
            <td></td>
        </tr>`;
    }).join('')).join('')}`).join('');

    $id('impresion').innerHTML = `<div class="pl-imp-cab">
            <div><div class="pl-imp-empresa">${escaparHtml($id('filtro_empresa').selectedOptions[0]?.textContent.trim())}</div><div class="pl-imp-titulo">PROGRAMACIÓN DIARIA DE LABORES</div></div>
            <div class="text-end"><div class="pl-imp-fecha">${escaparHtml(fechaLarga(plan.fecha))}</div><div>Plan ${plan.estado === 'CERRADA' ? 'cerrado' : 'abierto'}</div><div>Impreso: ${escaparHtml(new Date().toLocaleString('es-CO'))}</div></div>
        </div>
        <table class="pl-imp-tabla">
            <thead><tr><th>N°</th><th>Código</th><th>Trabajador</th><th>Lote</th><th>Labor</th><th>UM</th><th>Cantidad</th><th>Horario</th><th>Observación</th><th class="pl-imp-firma">Firma</th></tr></thead>
            <tbody>${cuerpo}</tbody>
        </table>
        <div class="pl-imp-pie">
            <div>Total: ${fmt(personas.size)} trabajadores · ${fmt(ordenadas.filter((a) => a.trabajadores.some((t) => !ausente(t))).length)} actividades</div>
            <div class="pl-imp-firmas"><div>Elaboró (Agrónomo)</div><div>Vo. Bo.</div></div>
        </div>`;
    window.print();
};

const plantillaLote = (opcion) => (opcion.element?.dataset.nombre !== undefined
    ? $(`<div><span class="font-monospace fw-semibold">${escaparHtml(opcion.id)}</span> ${escaparHtml(opcion.element.dataset.nombre)}${opcion.element.dataset.seccion ? `<span class="gp-subtexto">Sección ${escaparHtml(opcion.element.dataset.seccion)}</span>` : ''}</div>`)
    : opcion.text);

const selectTerceros = (padre) => ({
    width: '100%',
    minimumInputLength: 3,
    language: IDIOMA_SELECT2,
    dropdownParent: $(padre),
    templateResult: plantillaTercero,
    ajax: {
        delay: 250,
        data: (params) => ({ termino: params.term }),
        transport: (params, exito, fallo) => {
            pedir('terceros', { empresa: empresaId(), termino: params.data.termino }).then(exito).catch(fallo);

            return { abort: () => { } };
        },
        processResults: (datos) => {
            const fuera = nitsAusentes();

            return {
                results: (datos.filas || []).filter((fila) => !fuera.has(texto(fila.nit)))
                    .map((fila) => ({ id: texto(fila.nit), text: texto(fila.razonSocial), nit: texto(fila.nit), codigo: texto(fila.codigo) }))
            };
        }
    }
});

const pla = { finca: '', filas: [], uid: 0, intento: false, abierta: null, lotes: '', pfa: null, mapa: [], turno: 0, visible: false, fijada: false };

const ETIQUETAS_PLA = { codigo: 'Código de labor', lote: 'Lote', labor: 'Labor', um: 'Unidad de medida', meta: 'Meta', inicio: 'Hora inicio', fin: 'Hora fin', trab: 'Trabajadores', obs: 'Observación', pfa: 'Referencia PFA' };

const MARCAS_PLA = {
    nueva: { icono: 'bi-plus-circle', titulo: 'Nueva', chip: 'pl-e-completada' },
    editada: { icono: 'bi-pencil', titulo: 'Modificada', chip: 'pl-e-parcial' },
    eliminada: { icono: 'bi-trash', titulo: 'Por eliminar', chip: 'pl-e-noejecutada' }
};

const campoPla = (f, campo) => f.tr.querySelector(`[data-campo="${campo}"]`);
const vivasPla = () => pla.filas.filter((f) => !f.eliminada);
const numeroPla = (f) => pla.filas.indexOf(f) + 1;
const sumaPla = (f) => f.trabajadores.reduce((suma, t) => suma + t.cantidad, 0);
const cerradasFinca = () => actividades().filter((a) => a.finca === pla.finca && a.estado !== 'PENDIENTE');

const filaDe = (elemento) => {
    const tr = elemento.closest('[data-fila]');

    return tr ? pla.filas.find((f) => f.uid === Number(tr.dataset.fila)) || null : null;
};

const datosPla = (f) => {
    const grupo = texto(campoPla(f, 'labor').selectedOptions[0]?.dataset.grupo);

    return {
        lote: campoPla(f, 'lote').value,
        novedad: campoPla(f, 'labor').value,
        uMedida: campoPla(f, 'um').value,
        meta: aNumero(campoPla(f, 'meta').value),
        horaInicio: campoPla(f, 'inicio').value,
        horaFin: campoPla(f, 'fin').value,
        observacion: texto(campoPla(f, 'obs').value),
        referencia: grupo === '02' ? f.referencia : ''
    };
};

const envioPla = (f) => {
    const d = datosPla(f);

    return {
        id: f.id || undefined,
        finca: pla.finca,
        lote: d.lote,
        novedad: d.novedad,
        uMedida: d.uMedida,
        horaInicio: d.horaInicio,
        horaFin: d.horaFin,
        observacion: d.observacion,
        referencia: d.referencia,
        trabajadores: f.trabajadores.map((t) => ({ tercero: t.tercero, cantidad: t.cantidad, reasignadoDe: t.reasignadoDe }))
    };
};

const firmaPla = (f) => JSON.stringify({ ...envioPla(f), meta: datosPla(f).meta });
const vaciaPla = (f) => !f.id && !campoPla(f, 'labor').value && !texto(campoPla(f, 'meta').value) && !f.trabajadores.length && !texto(campoPla(f, 'obs').value);
const tipoPla = (f) => (f.eliminada ? 'eliminada' : (!f.id ? 'nueva' : (firmaPla(f) !== f.original ? 'editada' : '')));
const suciaPla = () => pla.filas.some((f) => (f.id ? f.eliminada || firmaPla(f) !== f.original : !vaciaPla(f)));
const igualPla = (f, meta) => f.trabajadores.length > 0 && repartir(meta, f.trabajadores.length).every((valor, i) => centavos(valor) === centavos(f.trabajadores[i].cantidad));

const repartirPla = (f) => {
    const meta = aNumero(campoPla(f, 'meta').value);

    if (!f.trabajadores.length || !(meta >= 0)) return;

    repartir(meta, f.trabajadores.length).forEach((valor, i) => { f.trabajadores[i].cantidad = valor; });
};

const erroresPla = (f, claves) => {
    const d = datosPla(f);
    const suma = sumaPla(f);
    const errores = [];
    const agregar = (campo, corto, mensaje) => errores.push({ campo, corto, mensaje });

    f.dups = f.trabajadores.map((t) => {
        const codigo = texto(t.codigo) || t.tercero;
        const otra = (claves.get(`${t.tercero}|${d.novedad}|${d.lote}`) || []).find((x) => x !== f);
        const cerrada = !otra && d.novedad && d.lote ? cerradasFinca().find((a) => a.novedad === d.novedad && a.lote === d.lote && a.trabajadores.some((x) => x.tercero === t.tercero)) : null;

        return otra ? `${codigo} repetido en la fila ${numeroPla(otra)}` : (cerrada ? `${codigo} ya tiene esta labor en el lote ${cerrada.lote} (cerrada)` : '');
    });

    if (f.servidor) agregar(f.trabajadores.some((t) => t.tercero === f.terceroServidor) ? 'detalle' : '', f.servidor.length > 40 ? `${f.servidor.slice(0, 40).trim()}…` : f.servidor, f.servidor);
    if (!d.lote) agregar('lote', 'Lote', 'Seleccione el lote.');
    if (!d.novedad) agregar('labor', 'Labor', 'Seleccione la labor.');
    if (!d.uMedida) agregar('um', 'Unidad de medida', 'Seleccione la unidad de medida.');
    if (!(d.meta > 0)) agregar('meta', 'Meta', 'Indique una meta mayor que cero (máx. 2 decimales).');
    if (Boolean(d.horaInicio) !== Boolean(d.horaFin) || (d.horaInicio && d.horaFin && d.horaFin <= d.horaInicio)) {
        agregar('inicio', 'Horario', d.horaInicio && d.horaFin ? 'La hora fin debe ser posterior a la de inicio.' : 'Indique la hora de inicio y la hora fin, o deje ambas vacías.');
    }
    if (!f.trabajadores.length) agregar('trab', 'Trabajadores', 'Seleccione al menos un trabajador.');
    else if (f.trabajadores.some((t) => Number.isNaN(t.cantidad) || t.cantidad < 0)) agregar('detalle', 'Reparto', 'Hay cantidades inválidas: use números mayores o iguales a cero con máximo 2 decimales.');
    else if (d.meta > 0 && centavos(suma) !== centavos(d.meta)) agregar('detalle', 'Reparto', `La suma asignada (${cant(suma)}) debe ser igual a la meta (${cant(d.meta)} ${d.uMedida}).`);

    f.dups.forEach((mensaje, i) => { if (mensaje) agregar('trab', `${texto(f.trabajadores[i].codigo) || f.trabajadores[i].tercero} duplicado`, mensaje); });

    return errores;
};

const filaPlaHtml = (uid) => `<tr class="pl-planilla-fila" data-fila="${uid}">
        <td class="pl-planilla-cabeza"><span class="pl-planilla-num font-monospace text-muted"><span class="d-lg-none">Fila </span><span data-num></span></span><span class="pl-planilla-marca"></span></td>
        <td data-label="Lote *"><div class="tx-select-ancho tx-select-sm"><select class="form-select form-select-sm" data-campo="lote">${pla.lotes}</select></div></td>
        <td class="pl-planilla-ancha" data-label="Labor *">
            <div class="d-flex gap-1"><input type="text" class="form-control form-control-sm font-monospace text-uppercase tx-labor-codigo" data-campo="codigo" placeholder="Código" autocomplete="off" maxlength="20"><div class="tx-select-ancho tx-select-sm"><select class="form-select form-select-sm" data-campo="labor">${$id('act_labor').innerHTML}</select></div></div>
            <select class="form-select form-select-sm mt-1 d-none" data-campo="pfa"><option value="">PFA (opcional)</option></select>
        </td>
        <td data-label="UM *"><div class="tx-select-ancho tx-select-sm"><select class="form-select form-select-sm" data-campo="um">${$id('act_um').innerHTML}</select></div></td>
        <td data-label="Meta *"><input type="text" class="form-control form-control-sm tx-input-num font-monospace tx-num" data-campo="meta" inputmode="decimal" maxlength="14" autocomplete="off"></td>
        <td data-label="Inicio"><input type="time" class="form-control form-control-sm" data-campo="inicio"></td>
        <td data-label="Fin"><input type="time" class="form-control form-control-sm" data-campo="fin"></td>
        <td class="pl-planilla-ancha" data-label="Trabajadores *">
            <div class="tx-select-ancho tx-select-sm pl-planilla-trab"><select class="form-select form-select-sm" data-campo="trab" multiple></select><span class="pl-chip pl-planilla-mas d-none"></span></div>
            <div class="d-flex align-items-center gap-1 mt-1"><button type="button" class="btn btn-link btn-sm p-0 pl-planilla-reparto-btn d-none" data-pla="detalle" aria-expanded="false" aria-controls="pla_det_${uid}"></button></div>
        </td>
        <td class="pl-planilla-ancha" data-label="Observación"><input type="text" class="form-control form-control-sm" data-campo="obs" maxlength="250" placeholder="Ej. LN 1 A LA 54" autocomplete="off"></td>
        <td class="pl-planilla-acciones"><div class="d-flex gap-1 justify-content-end">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-pla="duplicar" title="Duplicar fila" aria-label="Duplicar fila"><i class="bi bi-copy" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-sm" data-pla="eliminar"></button>
        </div></td>
    </tr>
    <tr class="pl-planilla-error d-none" data-fila="${uid}"><td colspan="10"><ul class="list-unstyled mb-0 small text-danger"></ul></td></tr>
    <tr class="pl-planilla-detalle d-none" id="pla_det_${uid}" data-fila="${uid}"><td colspan="10"></td></tr>`;

const enfocarPla = (f, campo) => {
    const control = campoPla(f, campo);
    const s2 = control.classList.contains('select2-hidden-accessible') ? control.nextElementSibling : null;

    (s2 ? s2.querySelector('.select2-search__field') || s2.querySelector('.select2-selection') : control).focus();
};

const destellar = (elemento) => {
    elemento.classList.remove('tx-flash');
    void elemento.offsetWidth;
    elemento.classList.add('tx-flash');
};

const cargarPfaPla = async (f) => {
    const selector = campoPla(f, 'pfa');
    const ver = texto(campoPla(f, 'labor').selectedOptions[0]?.dataset.grupo) === '02';

    mostrar(selector, ver);

    if (!ver) return;

    pla.pfa = pla.pfa || pedir('planesFertilizacion', { empresa: empresaId(), finca: pla.finca, fecha: fechaPlan() })
        .then((r) => r.filas || []).catch((error) => { avisar(error); return []; });

    const filas = await pla.pfa;

    selector.innerHTML = '<option value="">PFA (opcional)</option>' + filas.map(opcionPfa).join('');
    selector.value = filas.some((p) => texto(p.numero) === f.referencia) ? f.referencia : '';
};

const crearFilaPla = (d = {}, despues = null) => {
    const plantilla = document.createElement('template');
    const f = { uid: ++pla.uid, id: d.id || null, trabajadores: (d.trabajadores || []).map((t) => ({ ...t })), manual: Boolean(d.manual), referencia: texto(d.referencia), servidor: '', errores: [], dups: [], umLabor: '' };
    const comun = { width: '100%', language: IDIOMA_SELECT2, dropdownParent: $('#modalPlanilla') };

    plantilla.innerHTML = filaPlaHtml(f.uid);
    [f.tr, f.err, f.det] = [...plantilla.content.children];
    $id('pla_filas').insertBefore(plantilla.content, despues ? despues.det.nextSibling : null);
    pla.filas.splice(despues ? pla.filas.indexOf(despues) + 1 : pla.filas.length, 0, f);

    $(campoPla(f, 'lote')).select2({
        ...comun,
        placeholder: 'Lote…',
        dropdownAutoWidth: true,
        templateResult: plantillaLote,
        templateSelection: (opcion) => (opcion.id && opcion.element?.dataset.nombre !== undefined
            ? $(`<span title="${escaparHtml(opcion.element.dataset.nombre)}"><span class="font-monospace fw-semibold">${escaparHtml(opcion.id)}</span> <span class="text-muted">${escaparHtml(opcion.element.dataset.nombre)}</span></span>`)
            : opcion.text)
    }).val(d.lote || '').trigger('change.select2');
    $(campoPla(f, 'labor')).select2({ ...comun, placeholder: 'Labor…' }).val(d.novedad || '').trigger('change.select2');
    asegurarOpcion(campoPla(f, 'um'), texto(d.uMedida));
    $(campoPla(f, 'um')).select2({ ...comun, placeholder: 'UM', dropdownAutoWidth: true, minimumResultsForSearch: Infinity, templateSelection: (opcion) => opcion.id || opcion.text })
        .val(d.uMedida || '').trigger('change.select2');
    $(campoPla(f, 'trab')).select2({
        ...selectTerceros('#modalPlanilla'),
        placeholder: 'Código o nombre',
        templateSelection: (opcion) => (opcion.id ? $(`<span class="font-monospace" title="${escaparHtml(opcion.text)}">${escaparHtml(codigoTercero(opcion) || opcion.id)}</span>`) : opcion.text)
    }).append(f.trabajadores.map(opcionTercero)).trigger('change.select2');

    campoPla(f, 'codigo').value = d.novedad || '';
    campoPla(f, 'meta').value = d.meta > 0 ? valorInput(Number(d.meta)) : '';
    campoPla(f, 'inicio').value = texto(d.horaInicio).slice(0, 5);
    campoPla(f, 'fin').value = texto(d.horaFin).slice(0, 5);
    campoPla(f, 'obs').value = texto(d.observacion);
    f.umLabor = texto(campoPla(f, 'labor').selectedOptions[0]?.dataset.um);
    f.original = f.id ? firmaPla(f) : null;
    cargarPfaPla(f);

    return f;
};

const pintarDetallePla = (f) => {
    f.det.firstElementChild.innerHTML = `<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <span class="gp-etiqueta-mini">Reparto de la meta</span>
            <div class="d-flex flex-wrap flex-lg-nowrap align-items-center gap-3 ms-auto">
                <button type="button" class="btn btn-link btn-sm p-0" data-pla="igual"><i class="bi bi-distribute-horizontal me-1" aria-hidden="true"></i>Repartir en partes iguales</button>
                <div class="tx-reparto pl-indicador" data-indicador></div>
            </div>
        </div>
        ${f.trabajadores.length ? `<div class="pl-planilla-reparto">${f.trabajadores.map((t, i) => {
        const nombre = escaparHtml(t.nombre || t.tercero);

        return `<div class="pl-planilla-item" data-item="${i}">
                <span class="font-monospace fw-semibold">${escaparHtml(t.codigo || t.tercero)}</span>
                <span class="gp-truncar flex-grow-1" title="${nombre}">${nombre}</span>
                ${t.reasignadoDe ? `<span class="gp-chip-fila ambar">recibe de ${escaparHtml(codigoDe(t.reasignadoDe))}</span>` : ''}
                <input type="text" class="form-control form-control-sm tx-input-num font-monospace" data-cant="${i}" value="${valorInput(t.cantidad)}" inputmode="decimal" maxlength="14" autocomplete="off" aria-label="Cantidad de ${nombre}">
                <div class="invalid-feedback d-block d-none w-100 mt-0" data-dup></div>
            </div>`;
    }).join('')}</div>` : '<span class="small text-muted">Seleccione los trabajadores de la fila.</span>'}`;
};

const pintarFilaPla = (f, numero) => {
    const tipo = tipoPla(f);
    const errores = pla.intento && !f.eliminada ? f.errores : [];
    const malos = new Set(errores.map((e) => e.campo));
    const d = datosPla(f);
    const suma = sumaPla(f);
    const cuadra = f.trabajadores.length > 0 && d.meta > 0 && !Number.isNaN(suma) && centavos(suma) === centavos(d.meta);
    const marca = MARCAS_PLA[tipo];
    const boton = f.tr.querySelector('[data-pla="detalle"]');
    const eliminar = f.tr.querySelector('[data-pla="eliminar"]');
    const caja = f.tr.querySelector('.pl-planilla-trab .select2-selection');
    const ocultos = caja ? [...caja.querySelectorAll('.select2-selection__choice')].filter((c) => c.getBoundingClientRect().bottom > caja.getBoundingClientRect().bottom + 1).length : 0;
    const mas = f.tr.querySelector('.pl-planilla-mas');

    ['nueva', 'editada', 'eliminada'].forEach((clase) => f.tr.classList.toggle(`pl-planilla-fila--${clase}`, tipo === clase));
    f.tr.classList.toggle('pl-planilla-fila--error', errores.length > 0);
    f.tr.querySelector('[data-num]').textContent = numero;
    f.tr.querySelector('.pl-planilla-marca').innerHTML = marca
        ? `<i class="bi ${marca.icono} d-none d-lg-block" title="${marca.titulo}" aria-hidden="true"></i><span class="visually-hidden">${marca.titulo}</span><span class="pl-chip ${marca.chip} d-lg-none" aria-hidden="true">${marca.titulo}</span>` : '';

    f.tr.querySelectorAll('[data-campo]').forEach((control) => {
        const campo = control.dataset.campo;
        const malo = malos.has(campo === 'fin' ? 'inicio' : campo);

        control.setAttribute('aria-label', `${ETIQUETAS_PLA[campo]} fila ${numero}`);
        control.setAttribute('aria-invalid', String(malo));
        (control.closest('.tx-select-ancho') || control).classList.toggle('is-invalid', malo || (campo === 'codigo' && control.dataset.desconocido === '1'));

        if (control.disabled !== f.eliminada) control.disabled = f.eliminada;
    });

    f.tr.querySelector('[data-pla="duplicar"]').disabled = f.eliminada || estado.guardando;
    eliminar.disabled = estado.guardando;
    eliminar.className = `btn btn-sm ${f.eliminada ? 'btn-outline-secondary' : 'btn-outline-danger'}`;
    eliminar.title = f.eliminada ? 'Deshacer' : 'Eliminar fila';
    eliminar.setAttribute('aria-label', eliminar.title);
    eliminar.innerHTML = `<i class="bi ${f.eliminada ? 'bi-arrow-counterclockwise' : 'bi-trash'}" aria-hidden="true"></i>`;

    mostrar(boton, f.trabajadores.length > 0 && !f.eliminada);
    boton.classList.toggle('pl-mal', !cuadra);
    boton.innerHTML = cuadra
        ? `<i class="bi bi-check-circle" aria-hidden="true"></i><span class="tx-chip-cont verde">${fmt(f.trabajadores.length)}</span>${igualPla(f, d.meta) ? 'Igual' : 'Manual'}`
        : `<i class="bi bi-exclamation-octagon" aria-hidden="true"></i>Asignado <span class="tx-num">${Number.isNaN(suma) ? '—' : cant(suma)} / ${Number.isNaN(d.meta) ? '—' : cant(d.meta)}</span>`;
    boton.setAttribute('aria-expanded', String(pla.abierta === f.uid));
    mas.textContent = `+${ocultos}`;
    mostrar(mas, ocultos > 0);

    mostrar(f.err, errores.length > 0);
    f.err.querySelector('ul').innerHTML = errores.map((e) => `<li><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>${escaparHtml(e.mensaje)}</li>`).join('');

    mostrar(f.det, pla.abierta === f.uid);

    if (pla.abierta !== f.uid) return;

    const indicador = f.det.querySelector('[data-indicador]');

    if (indicador) pintarIndicador(indicador, suma, d.meta, d.uMedida);

    f.det.querySelectorAll('[data-item]').forEach((item) => {
        const i = Number(item.dataset.item);
        const mensaje = f.dups[i] || (f.servidor && f.trabajadores[i]?.tercero === f.terceroServidor ? f.servidor : '');

        item.classList.toggle('pl-fila-dup', mensaje !== '');
        item.querySelector('[data-dup]').textContent = mensaje;
        mostrar(item.querySelector('[data-dup]'), mensaje !== '');
    });
};

const pintarResumenPla = () => {
    const vivas = vivasPla();
    const nuevas = vivas.filter((f) => !f.id && !vaciaPla(f)).length;
    const editadas = vivas.filter((f) => f.id && firmaPla(f) !== f.original).length;
    const eliminadas = pla.filas.length - vivas.length;
    const cambios = nuevas + editadas;
    const totales = new Map();
    const personas = new Set();
    const conError = pla.intento ? vivas.filter((f) => f.errores.length) : [];
    const resumen = $id('pla_resumen_errores');
    const guardar = $id('pla_guardar');

    vivas.forEach((f) => {
        const d = datosPla(f);

        if (d.uMedida && d.meta > 0) totales.set(d.uMedida, (totales.get(d.uMedida) || 0) + d.meta);
        f.trabajadores.forEach((t) => personas.add(t.tercero));
    });

    $id('pla_contador').innerHTML = pla.finca ? [
        `<strong>${fmt(vivas.length)}</strong> ${vivas.length === 1 ? 'fila' : 'filas'}`,
        nuevas ? `${fmt(nuevas)} ${nuevas === 1 ? 'nueva' : 'nuevas'}` : '',
        editadas ? `${fmt(editadas)} ${editadas === 1 ? 'editada' : 'editadas'}` : '',
        eliminadas ? `${fmt(eliminadas)} por eliminar` : ''
    ].filter(Boolean).join(' · ') : '';
    $id('pla_totales').innerHTML = [...totales.entries()].map(([um, total]) => `<span class="pl-chip"><span class="font-monospace">${escaparHtml(um)}</span>${cant(total)}</span>`).join('')
        + (personas.size ? `<span class="pl-chip"><i class="bi bi-people" aria-hidden="true"></i>${fmt(personas.size)} ${personas.size === 1 ? 'trabajador' : 'trabajadores'}</span>` : '');

    mostrar(resumen, conError.length > 0);
    resumen.innerHTML = conError.length ? `<div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Hay ${fmt(conError.length)} ${conError.length === 1 ? 'fila' : 'filas'} con errores</div>
        <div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1">${conError.slice(0, 6).map((f) => `<button type="button" class="btn btn-link btn-sm p-0" data-pla="ir" data-uid="${f.uid}">Fila ${numeroPla(f)}: ${escaparHtml([...new Set(f.errores.map((e) => e.corto))].join(', '))}</button>`).join('')}${conError.length > 6 ? `<span>y ${fmt(conError.length - 6)} más</span>` : ''}</div>` : '';

    guardar.disabled = estado.guardando || !pla.finca || !(cambios || eliminadas);
    $id('pla_agregar_fila').disabled = estado.guardando;

    if ($id('pla_finca').disabled !== (pla.fijada || estado.guardando)) $id('pla_finca').disabled = pla.fijada || estado.guardando;
    guardar.innerHTML = estado.guardando
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…'
        : `<i class="bi bi-floppy me-1" aria-hidden="true"></i>${cambios ? `Guardar ${fmt(cambios)} ${cambios === 1 ? 'actividad' : 'actividades'}` : 'Guardar cambios'}`;

    return cambios;
};

const refrescarPla = () => {
    const claves = new Map();

    vivasPla().forEach((f) => {
        const d = datosPla(f);

        if (d.lote && d.novedad) f.trabajadores.forEach((t) => claves.set(`${t.tercero}|${d.novedad}|${d.lote}`, [...(claves.get(`${t.tercero}|${d.novedad}|${d.lote}`) || []), f]));
    });
    pla.filas.forEach((f, i) => {
        f.errores = f.eliminada || vaciaPla(f) ? [] : erroresPla(f, claves);
        pintarFilaPla(f, i + 1);
    });

    return pintarResumenPla();
};

const quitarFilaPla = (f, reponer = true) => {
    $(f.tr).find('select.select2-hidden-accessible').select2('destroy');
    [f.tr, f.err, f.det].forEach((tr) => tr.remove());
    pla.filas.splice(pla.filas.indexOf(f), 1);

    if (pla.abierta === f.uid) pla.abierta = null;

    if (reponer && !pla.filas.length) nuevaFilaPla();
    else refrescarPla();
};

const nuevaFilaPla = () => {
    const previa = pla.filas[pla.filas.length - 1];
    const d = previa ? datosPla(previa) : null;
    const f = crearFilaPla({ lote: d?.lote || '', horaInicio: d ? d.horaInicio : '06:00', horaFin: d ? d.horaFin : '14:00' });

    refrescarPla();

    if (pla.visible) enfocarPla(f, d?.lote ? 'codigo' : 'lote');

    f.tr.scrollIntoView({ block: 'nearest' });

    return f;
};

const duplicarFilaPla = (f) => {
    const d = datosPla(f);
    const nueva = crearFilaPla({ ...d, novedad: '', referencia: '', manual: f.manual, trabajadores: f.trabajadores.map((t) => ({ ...t, reasignadoDe: '' })) }, f);

    refrescarPla();
    enfocarPla(nueva, 'codigo');
    destellar(nueva.tr);
};

const eliminarFilaPla = (f) => {
    if (f.id) {
        f.eliminada = !f.eliminada;

        if (pla.abierta === f.uid) pla.abierta = null;

        refrescarPla();

        return;
    }

    const conDatos = !vaciaPla(f);

    quitarFilaPla(f);

    if (conDatos) Toast.fire({ icon: 'info', title: 'Fila eliminada' });
};

const alternarDetallePla = (f) => {
    pla.abierta = pla.abierta === f.uid ? null : f.uid;

    if (pla.abierta) pintarDetallePla(f);

    refrescarPla();
};

const irAFilaPla = (f) => {
    if (!f) return;

    const campo = f.errores[0]?.campo;

    f.tr.scrollIntoView({ behavior: reducido() ? 'auto' : 'smooth', block: 'center' });

    if (campo === 'detalle') {
        if (pla.abierta !== f.uid) alternarDetallePla(f);
        f.det.querySelector('[data-cant]')?.focus();
    } else {
        enfocarPla(f, campo || 'codigo');
    }

    destellar(f.tr);
};

const pintarCerradasPla = () => {
    const cerradas = cerradasFinca();

    $id('pla_cerradas').innerHTML = cerradas.length ? `<details class="pl-planilla-cerradas mx-3 mb-3">
        <summary>Cerradas en esta finca (${fmt(cerradas.length)})</summary>
        <div class="table-responsive mt-2">
            <table class="table table-sm align-middle mb-0 gp-tabla">
                <thead class="gp-thead"><tr><th>Lote</th><th>Labor</th><th class="text-end">Meta</th><th>Trabajadores</th><th>Estado</th></tr></thead>
                <tbody>${cerradas.map((a) => `<tr>
                    <td class="font-monospace">${escaparHtml(a.lote)}</td>
                    <td><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> ${escaparHtml(a.novedadNombre)}</td>
                    <td class="text-end tx-num text-nowrap">${cant(a.cantidad)} ${escaparHtml(a.uMedida)}</td>
                    <td class="font-monospace">${a.trabajadores.map((t) => escaparHtml(t.codigo || t.tercero)).join(', ')}</td>
                    <td>${Number(a.vinculada) === 1 ? '<span class="pl-chip pl-e-registrada"><i class="bi bi-journal-check" aria-hidden="true"></i>Registrada</span>' : chipEstado(a.estado)}</td>
                </tr>`).join('')}</tbody>
            </table>
        </div>
    </details>` : '';
};

const limpiarPla = () => {
    $('#pla_filas select.select2-hidden-accessible').select2('destroy');
    $id('pla_filas').innerHTML = '';
    $id('pla_cerradas').innerHTML = '';
    Object.assign(pla, { filas: [], abierta: null, intento: false, pfa: null, mapa: [] });
};

const enfocarInicialPla = () => {
    if (!pla.visible) return;

    if (!pla.finca) {
        $id('pla_finca').nextElementSibling?.querySelector('.select2-selection')?.focus();

        return;
    }

    const f = pla.filas.find((x) => !x.id) || pla.filas[0];

    if (f) enfocarPla(f, !f.id && campoPla(f, 'lote').value ? 'codigo' : 'lote');
};

const cargarPlanilla = async (finca) => {
    const turno = ++pla.turno;

    limpiarPla();
    pla.finca = finca;
    mostrar($id('pla_contenido'), false);
    $id('pla_vacio').innerHTML = finca
        ? '<div class="text-center py-5"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Cargando…</span></div></div>'
        : '<div class="gp-estado-vacio text-center py-5"><div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi bi-table" aria-hidden="true"></i></div><h6 class="mb-0">Seleccione la finca para cargar su planilla</h6></div>';
    pintarResumenPla();

    if (!finca) {
        enfocarInicialPla();

        return;
    }

    const existentes = pendientesPlan().filter((a) => a.finca === finca)
        .sort((x, y) => x.lote.localeCompare(y.lote, 'es', { numeric: true }) || x.orden - y.orden);
    const usados = new Set(existentes.map((a) => a.lote));
    let lotes = [];

    try {
        lotes = (await pedir('lotes', { empresa: empresaId(), finca })).filas || [];
    } catch (error) {
        avisar(error);
    }

    if (turno !== pla.turno) return;

    const mapa = new Map(lotes.filter((l) => Number(l.activo ?? 1) !== 0 || usados.has(texto(l.codigo))).map((l) => [texto(l.codigo), l]));

    existentes.forEach((a) => { if (!mapa.has(a.lote)) mapa.set(a.lote, { codigo: a.lote, descripcion: a.loteNombre, seccion: a.seccion }); });
    pla.lotes = '<option value=""></option>' + [...mapa.values()].map(opcionLote).join('');
    $id('pla_vacio').innerHTML = '';
    mostrar($id('pla_contenido'), true);
    existentes.forEach((a) => {
        const trabajadores = a.trabajadores.filter((t) => !ausente(t)).map((t) => ({
            tercero: t.tercero, codigo: texto(t.codigo), nombre: texto(t.nombre), cantidad: Number(t.cantidad), reasignadoDe: texto(t.reasignadoDe)
        }));
        const f = crearFilaPla({ ...a, meta: Number(a.cantidad), trabajadores });

        f.manual = !igualPla(f, Number(a.cantidad));
    });
    pintarCerradasPla();

    if (!existentes.length) nuevaFilaPla();
    else refrescarPla();

    enfocarInicialPla();
};

const abrirPlanilla = (finca = '') => {
    const fijada = finca !== '';
    const fincas = [...new Set(actividades().map((a) => a.finca))];
    const opciones = [...$id('pla_finca').options].filter((o) => o.value);
    const elegida = finca || (fincas.length === 1 ? fincas[0] : (opciones.length === 1 ? opciones[0].value : ''));

    $id('pla_chip_fecha').textContent = fechaLegible(fechaPlan());
    pla.fijada = fijada;
    mostrar($id('pla_candado'), fijada);
    mostrar($id('pla_fijada'), fijada);
    $('#pla_finca').val(elegida).trigger('change.select2');
    bootstrap.Modal.getOrCreateInstance($id('modalPlanilla')).show();
    cargarPlanilla(elegida);
};

const descartarPla = async (titulo) => (await Swal.fire({
    icon: 'warning',
    title: escaparHtml(titulo),
    showCancelButton: true,
    confirmButtonText: 'Descartar',
    cancelButtonText: 'Seguir editando',
    confirmButtonColor: '#d6293e',
    focusCancel: true
})).isConfirmed;

const cerrarPlanilla = () => bootstrap.Modal.getOrCreateInstance($id('modalPlanilla')).hide();

const cancelarPlanilla = async () => {
    if (estado.guardando || (suciaPla() && !await descartarPla('¿Descartar los cambios de la planilla?'))) return;

    cerrarPlanilla();
};

const guardarPlanilla = async () => {
    if (estado.guardando || !pla.finca) return;

    pla.filas.filter(vaciaPla).forEach((f) => quitarFilaPla(f, false));
    pla.filas.forEach((f) => { f.servidor = ''; });
    pla.intento = true;

    const cambios = refrescarPla();
    const vivas = vivasPla();
    const conError = vivas.filter((f) => f.errores.length);
    const eliminadas = pla.filas.filter((f) => f.eliminada).map((f) => f.id);

    if (!pla.filas.length) nuevaFilaPla();

    if (conError.length) {
        Toast.fire({ icon: 'warning', title: `Revise ${fmt(conError.length)} ${conError.length === 1 ? 'fila' : 'filas'} con errores` });
        irAFilaPla(conError[0]);

        return;
    }

    if (!cambios && !eliminadas.length) return;

    const lista = [];
    const poner = () => vivas.forEach((f) => {
        lista.push(envioPla(f));
        pla.mapa.push(f);
    });

    pla.mapa = [];
    pendientesPlan().forEach((a) => {
        if (a.finca !== pla.finca) {
            lista.push(aEnvio(a));
            pla.mapa.push(null);
        } else if (!pla.mapa.includes(vivas[0]) && vivas.length) {
            poner();
        }
    });

    if (vivas.length && !pla.mapa.includes(vivas[0])) poner();

    const promesa = guardarLista(lista, cambios ? `${fmt(cambios)} ${cambios === 1 ? 'actividad guardada' : 'actividades guardadas'}` : 'Cambios guardados', null, eliminadas, true);

    refrescarPla();

    try {
        await promesa;
        cerrarPlanilla();
    } catch (error) {
        const f = pla.mapa[Number(error.datos?.actividad) - 1];

        if (/recargue el plan/i.test(error.message)) {
            cerrarPlanilla();
            errorSwal(error, 'No se pudo guardar el plan');
            cargar(true);
        } else if (f && pla.filas.includes(f)) {
            f.servidor = error.message;
            f.terceroServidor = texto(error.datos?.tercero);
            refrescarPla();
            irAFilaPla(f);
        } else {
            errorSwal(error, 'No se pudo guardar el plan');
        }
    } finally {
        refrescarPla();
    }
};

const cambiarCampoPla = (f, control) => {
    const campo = control.dataset.campo;

    if (campo === 'codigo') {
        const codigo = texto(control.value).toUpperCase();
        const opcion = [...campoPla(f, 'labor').options].find((o) => o.value && o.value.toUpperCase() === codigo);

        control.dataset.desconocido = codigo && !opcion ? '1' : '';

        if (opcion && opcion.value !== campoPla(f, 'labor').value) {
            $(campoPla(f, 'labor')).val(opcion.value).trigger('change');

            return;
        }
    } else if (campo === 'labor') {
        const um = campoPla(f, 'um');
        const umLabor = texto(control.selectedOptions[0]?.dataset.um);

        campoPla(f, 'codigo').value = control.value;
        campoPla(f, 'codigo').dataset.desconocido = '';

        if (umLabor && (!um.value || um.value === f.umLabor)) $(um).val(umLabor).trigger('change.select2');

        f.umLabor = umLabor;
        cargarPfaPla(f);
    } else if (campo === 'trab') {
        const datos = $(control).select2('data').filter((opcion) => opcion.id);

        f.trabajadores = datos.map((opcion) => f.trabajadores.find((t) => t.tercero === opcion.id)
            || { tercero: opcion.id, codigo: codigoTercero(opcion), nombre: texto(opcion.text), cantidad: 0, reasignadoDe: '' });

        if (!f.manual) repartirPla(f);
        if (pla.abierta === f.uid) pintarDetallePla(f);
    } else if (campo === 'pfa') {
        f.referencia = control.value;
    } else if (campo === 'meta') {
        const meta = aNumero(control.value);

        if (meta > 0) control.value = valorInput(meta);
    }

    f.servidor = '';
    refrescarPla();
};

const iniciarPlanilla = () => {
    const modal = $id('modalPlanilla');

    $('#pla_filas').on('change', '[data-campo]', (evento) => {
        const f = filaDe(evento.target);

        if (f) cambiarCampoPla(f, evento.target);
    });
    $id('pla_filas').addEventListener('input', (evento) => {
        const f = filaDe(evento.target);
        const cantidad = evento.target.closest('[data-cant]');

        if (!f || evento.target.classList.contains('select2-search__field')) return;

        if (cantidad) {
            const valor = aNumero(cantidad.value);

            f.manual = true;
            f.trabajadores[Number(cantidad.dataset.cant)].cantidad = valor;
            cantidad.classList.toggle('is-invalid', Number.isNaN(valor));
        } else if (evento.target.dataset.campo === 'meta' && !f.manual) {
            repartirPla(f);

            if (pla.abierta === f.uid) pintarDetallePla(f);
        }

        f.servidor = '';
        refrescarPla();
    });
    modal.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-pla]');
        const f = boton ? filaDe(boton) : null;

        if (!boton || boton.disabled) return;

        ({
            cancelar: cancelarPlanilla,
            detalle: () => alternarDetallePla(f),
            igual: () => {
                f.manual = false;
                repartirPla(f);
                pintarDetallePla(f);
                refrescarPla();
            },
            duplicar: () => duplicarFilaPla(f),
            eliminar: () => eliminarFilaPla(f),
            ir: () => irAFilaPla(pla.filas.find((x) => x.uid === Number(boton.dataset.uid)))
        })[boton.dataset.pla]?.();
    });
    modal.addEventListener('keydown', (evento) => {
        const control = evento.target;
        const atajo = evento.ctrlKey || evento.metaKey;

        if (atajo && evento.key.toLowerCase() === 's') {
            evento.preventDefault();
            guardarPlanilla();
        } else if (atajo && evento.key === 'Enter') {
            evento.preventDefault();

            if (pla.finca && !estado.guardando && !$id('pla_contenido').classList.contains('d-none')) nuevaFilaPla();
        } else if (evento.key === 'Escape' && !evento.defaultPrevented && !document.querySelector('.select2-container--open')) {
            evento.preventDefault();
            cancelarPlanilla();
        } else if (evento.key === 'Enter' && control.matches('#pla_filas input:not(.select2-search__field)')) {
            evento.preventDefault();

            if (control.dataset.campo === 'codigo') control.dispatchEvent(new Event('change', { bubbles: true }));
            if (control.dataset.campo === 'obs' && filaDe(control) === pla.filas[pla.filas.length - 1]) nuevaFilaPla();
        }
    });
    $id('pla_agregar_fila').addEventListener('click', nuevaFilaPla);
    $id('pla_guardar').addEventListener('click', guardarPlanilla);
    $('#pla_finca').on('change', async () => {
        const finca = $id('pla_finca').value;

        if (finca === pla.finca) return;

        if (suciaPla() && !await descartarPla('Se descartarán los cambios de la finca actual')) {
            $('#pla_finca').val(pla.finca).trigger('change.select2');

            return;
        }

        cargarPlanilla(finca);
    });
    modal.addEventListener('shown.bs.modal', () => {
        pla.visible = true;
        refrescarPla();
        enfocarInicialPla();
    });
    modal.addEventListener('hidden.bs.modal', () => {
        pla.visible = false;
        pla.turno++;
        limpiarPla();
        pla.finca = '';
    });
};

const iniciarSelect2 = () => {
    const comun = { width: '100%', language: IDIOMA_SELECT2, dropdownParent: $('#modalActividad') };

    $id('pla_finca').innerHTML = $id('act_finca').innerHTML;
    $('#pla_finca').select2({ ...comun, dropdownParent: $('#modalPlanilla'), placeholder: 'Seleccione la finca…' });
    $('#act_finca, #act_labor, #act_um').select2({ ...comun, placeholder: 'Seleccione una opción…', allowClear: true });
    $('#act_lote').select2({
        ...comun,
        placeholder: 'Seleccione el lote…',
        allowClear: true,
        templateResult: plantillaLote,
        templateSelection: (opcion) => (opcion.element?.dataset.nombre !== undefined && opcion.id
            ? `${opcion.id} — ${opcion.element.dataset.nombre}${opcion.element.dataset.seccion ? ` · Sección ${opcion.element.dataset.seccion}` : ''}`
            : opcion.text)
    });
    $('#act_terceros').select2({
        ...selectTerceros('#modalActividad'),
        placeholder: 'Busque por código, identificación o nombre',
        templateSelection: (opcion) => (opcion.id
            ? $(`<span><span class="font-monospace">${escaparHtml(codigoTercero(opcion) || opcion.id)}</span> ${escaparHtml(nombreCorto(opcion.text))}</span>`)
            : opcion.text)
    });
    $('#edit_tercero').select2({
        ...selectTerceros('#modalEditarTrabajador'),
        placeholder: 'Código, identificación o nombre…',
        templateSelection: (opcion) => (opcion.id
            ? $(`<span><span class="font-monospace fw-semibold">${escaparHtml(codigoTercero(opcion) || opcion.id)}</span> — ${escaparHtml(opcion.text)}</span>`)
            : opcion.text)
    });
};

const ACCIONES = {
    agregar: () => abrirActividad(),
    planilla: (d) => abrirPlanilla(d.finca || ''),
    pendientes: () => bootstrap.Offcanvas.getOrCreateInstance($id('panelPendientes')).show(),
    ausentes: elegirAusente,
    imprimir,
    'ver-todas': () => filtrar(null),
    reintentar: () => cargar(),
    editar: (d) => abrirActividad(actividad(d.act)),
    duplicar: (d) => abrirActividad(actividad(d.act), true),
    eliminar: (d) => eliminarActividad(d.act),
    reabrir: async (d) => {
        if (await confirmar('¿Reabrir la actividad?', 'La actividad vuelve a Pendiente.', 'Sí, reabrir')) ejecutar('reabrirActividad', { linea: d.act }, 'Actividad reabierta', Number(d.act));
    },
    completar: (d) => ejecutar('cerrarActividad', { linea: d.act, estado: 'COMPLETADA', cantidadEjecutada: '', observacionCierre: '' }, 'Actividad completada', Number(d.act), 'No se pudo cerrar la actividad'),
    parcial: (d) => abrirCierre(d.act, 'PARCIAL'),
    noejecutada: (d) => abrirCierre(d.act, 'NO_EJECUTADA'),
    registrar: (d) => registrar(d.act),
    'editar-trab': (d) => abrirEdicionTrabajador(d.act, d.tercero),
    'ausente-trab': (d) => abrirAusencia(d.tercero),
    'quitar-trab': (d) => quitarTrabajador(d.act, d.tercero),
    presente: (d) => marcarPresente(d.tercero),
    trasladar: (d) => trasladar(d.pendiente),
    'cerrar-plan': async () => {
        if (await confirmar('¿Cerrar el plan?', `Se cerrará el plan del ${fechaLegible(fechaPlan())}; no se podrá modificar sin reabrirlo.`, 'Sí, cerrar plan')) ejecutar('cerrarPlan', { planeacion: estado.plan.id }, 'Plan cerrado');
    },
    'reabrir-plan': async () => {
        if (await confirmar('¿Reabrir el plan?', 'El plan vuelve a quedar abierto para modificaciones.', 'Sí, reabrir')) ejecutar('reabrirPlan', { planeacion: estado.plan.id }, 'Plan reabierto');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (!FECHA_DEFECTO && !$id('fecha').value) $id('fecha').value = aIso(new Date());

    iniciarSelect2();
    iniciarPlanilla();

    document.addEventListener('click', (evento) => {
        const kpiBoton = evento.target.closest('.pl-kpi');
        const boton = evento.target.closest('[data-accion]');

        if (kpiBoton) {
            const clave = kpiBoton.dataset.kpi;

            if (clave === 'trabajadores') cambiarVista('trabajador');
            else filtrar(clave === 'todas' || clave === estado.filtro ? null : clave);

            return;
        }

        if (boton && !boton.disabled && ACCIONES[boton.dataset.accion]) ACCIONES[boton.dataset.accion](boton.dataset);
    });

    document.querySelectorAll('[data-vista]').forEach((boton) => boton.addEventListener('click', () => cambiarVista(boton.dataset.vista)));
    $id('btn_ver_todas').addEventListener('click', () => filtrar(null));
    $id('buscar_trabajador').addEventListener('input', pintarVistaTrabajador);

    $id('fecha').addEventListener('change', () => {
        if ($id('fecha').value) cargar();
    });
    $id('btn_dia_anterior').addEventListener('click', () => {
        $id('fecha').value = sumarDias(fechaPlan(), -1);
        cargar();
    });
    $id('btn_dia_siguiente').addEventListener('click', () => {
        $id('fecha').value = sumarDias(fechaPlan(), 1);
        cargar();
    });
    $id('filtro_empresa').addEventListener('change', () => {
        const url = new URL(window.location.href);

        url.searchParams.set('empresa', $id('filtro_empresa').value);
        window.location.href = url.toString();
    });

    $('#act_finca').on('change', () => {
        errorCampo('error_act_finca', false);
        $id('act_finca').closest('.tx-select-ancho').classList.remove('is-invalid');
        cargarLotes($id('act_finca').value);

        if (grupoFormulario() === '02') cargarPfa();

        pintarReparto();
    });
    $('#act_lote').on('change', () => {
        errorCampo('error_act_lote', false);
        $id('act_lote').closest('.tx-select-ancho').classList.remove('is-invalid');
        pintarReparto();
    });
    $('#act_labor').on('change', () => {
        const um = texto(laborOpcion()?.dataset.um);

        if (um) $('#act_um').val(um).trigger('change');
        actualizarLabor();
    });
    $('#act_terceros').on('change', sincronizarTrabajadores);
    $('#act_um').on('change', () => {
        errorCampo('error_act_um', false);
        $id('act_um').closest('.tx-select-ancho').classList.remove('is-invalid');
        $id('act_meta_um').textContent = umFormulario() || '—';
        indicadorActividad();
    });
    ['act_hora_inicio', 'act_hora_fin'].forEach((id) => $id(id).addEventListener('change', () => {
        errorCampo('error_act_horario', false);
        ['act_hora_inicio', 'act_hora_fin'].forEach((x) => $id(x).classList.remove('is-invalid'));
    }));
    $id('act_codigo').addEventListener('change', () => {
        const codigo = texto($id('act_codigo').value).toUpperCase();
        const existe = Array.from($id('act_labor').options).some((o) => o.value && o.value.toUpperCase() === codigo);

        if (existe) {
            $('#act_labor').val(Array.from($id('act_labor').options).find((o) => o.value.toUpperCase() === codigo).value).trigger('change');
        } else if (codigo) {
            $id('act_codigo').classList.add('is-invalid');
            errorCampo('error_act_labor', true, 'No existe una labor con ese código.');
        }
    });
    $id('act_meta').addEventListener('input', () => {
        $id('act_meta').classList.remove('is-invalid');
        errorCampo('error_act_meta', false);

        if (!form.manual) {
            repartirFormulario();
            pintarReparto();
        } else {
            indicadorActividad();
        }
    });
    $id('act_repartir').addEventListener('click', () => {
        form.manual = false;
        repartirFormulario();
        pintarReparto();
    });
    $id('act_observacion').addEventListener('input', contarObservacion);
    $id('act_filas').addEventListener('input', (evento) => {
        const campo = evento.target.closest('[data-reparto]');

        if (!campo) return;

        const valor = aNumero(campo.value);

        form.manual = true;
        form.trabajadores[Number(campo.dataset.reparto)].cantidad = valor;
        campo.classList.toggle('is-invalid', Number.isNaN(valor));
        indicadorActividad();
    });
    $id('act_filas').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-quitar-reparto]');

        if (!boton) return;

        $('#act_terceros').val(($('#act_terceros').val() || []).filter((nit) => nit !== boton.dataset.quitarReparto)).trigger('change');
    });
    $id('formulario_actividad').addEventListener('submit', (evento) => {
        evento.preventDefault();
        guardarActividad(false);
    });
    $id('btn_guardar_otra').addEventListener('click', () => guardarActividad(true));
    $id('modalActividad').addEventListener('keydown', (evento) => {
        if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 's') {
            evento.preventDefault();
            guardarActividad(false);
        }
    });
    $id('modalActividad').addEventListener('shown.bs.modal', () => {
        if ($id('chip_duplicada').textContent) $id('act_codigo').focus();
    });

    $id('formulario_editar_trabajador').addEventListener('submit', guardarEdicionTrabajador);
    $id('edit_cantidad').addEventListener('input', resumenEdicion);
    $id('modalEditarTrabajador').addEventListener('shown.bs.modal', () => $id('edit_cantidad').focus());

    $id('formulario_cierre').addEventListener('submit', guardarCierre);
    $id('cierre_cantidad').addEventListener('input', saldoCierre);
    $id('cierre_observacion').addEventListener('input', () => {
        $id('contador_cierre').textContent = `${$id('cierre_observacion').value.length} / 250`;
    });
    $id('modalCierre').addEventListener('shown.bs.modal', () => ($id('cierre_col_cantidad').classList.contains('d-none') ? $id('cierre_observacion') : $id('cierre_cantidad')).focus());

    $id('aus_cuerpo').addEventListener('change', (evento) => {
        const paso = aus.pasos[aus.i];
        const marca = evento.target.closest('[data-cand]');

        if (evento.target.id === 'aus_ampliar') {
            paso.ampliar = evento.target.checked;

            if (!paso.ampliar) paso.candidatos.forEach((c) => { if (c.otro) c.marcado = false; });
        } else if (marca) {
            paso.candidatos[Number(marca.dataset.cand)].marcado = marca.checked;
        } else {
            return;
        }

        if (!paso.manual) repartirPaso(paso);

        pintarAusencia();
    });
    $id('aus_cuerpo').addEventListener('input', (evento) => {
        const campo = evento.target.closest('[data-cand-cant]');

        if (!campo) return;

        const paso = aus.pasos[aus.i];
        const valor = aNumero(campo.value);

        paso.manual = true;
        paso.candidatos[Number(campo.dataset.candCant)].cantidad = valor;
        campo.classList.toggle('is-invalid', Number.isNaN(valor));
        actualizarPaso();
    });
    $id('aus_cuerpo').addEventListener('click', (evento) => {
        if (!evento.target.closest('#aus_repartir')) return;

        const paso = aus.pasos[aus.i];

        paso.manual = false;
        repartirPaso(paso);
        pintarAusencia();
    });
    $id('aus_atras').addEventListener('click', () => {
        aus.i = Math.max(0, aus.i - 1);
        pintarAusencia();
    });
    $id('aus_siguiente').addEventListener('click', () => {
        if (!pasoValido(aus.pasos[aus.i])) return;

        if (aus.i < aus.pasos.length - 1) {
            aus.i++;
            pintarAusencia();

            return;
        }

        enviarAusencia(aus.pasos.filter((p) => marcadosPaso(p).length).map((p) => ({
            linea: p.a.id,
            asignaciones: marcadosPaso(p).map((c) => ({ tercero: c.tercero, cantidad: c.cantidad }))
        })));
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && estado.cargado && !estado.error && !estado.guardando && !document.querySelector('.modal.show')) cargar(true);
    });

    cargar();
});
