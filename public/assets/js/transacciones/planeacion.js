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

const cant = (valor) => Number(valor || 0).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 3 });

const aNumero = (valor) => {
    const crudo = texto(valor).replace(/\s/g, '');

    if (crudo === '') return 0;

    return /^\d+([.,]\d{1,3})?$/.test(crudo) ? Number(crudo.replace(',', '.')) : NaN;
};

kgGramos.parsear = aNumero;

const valorInput = (numero) => (Number.isFinite(numero) ? String(numero).replace('.', ',') : '');
const milesimas = (valor) => Math.round(Number(valor || 0) * 1000);
const valorCant = (numero) => (numero > 0 ? valorInput(numero) : '');
const cantUm = (valor, um = '') => (Number(valor) > 0 ? `${cant(valor)}${um ? ` ${escaparHtml(um)}` : ''}` : '<span class="text-muted">—</span>');
const esRac = (um) => texto(um).toUpperCase() === 'RAC';
const esPal = (um) => texto(um).toUpperCase() === 'PAL';
const modoNum = (um) => (esRac(um) ? 'numeric' : 'decimal');
const malaCant = (valor, um) => Number.isNaN(valor) || valor < 0 || (esRac(um) && !Number.isInteger(valor));
const msgCant = (um) => (esRac(um) ? 'Use números enteros para racimos.' : 'Use números mayores o iguales a cero con máximo 3 decimales.');

const repartir = (total, partes, entero = false) => {
    const escala = entero ? 1 : 1000;
    const unidades = Math.round(total * escala);
    const base = Math.floor(unidades / partes);
    const sobrante = unidades - base * partes;

    return Array.from({ length: partes }, (_, indice) => (base + (indice < sobrante ? 1 : 0)) / escala);
};

const pesos = new Map();

const pesoDe = (finca, lote, fecha, alListo) => {
    const clave = `${$id('empresa').value}|${finca}|${lote}|${fecha}`;

    if (!pesos.has(clave)) {
        pesos.set(clave, { estado: 'cargando', peso: 0 });
        pedir('pesoPromedio', { empresa: $id('empresa').value, fecha, finca, lote })
            .then((r) => Number(r.pesoRacimo) || 0)
            .catch((error) => {
                avisar(error);

                return 0;
            })
            .then((peso) => {
                pesos.set(clave, { estado: peso > 0 ? 'ok' : 'sin', peso });
                alListo();
            });
    }

    return pesos.get(clave);
};

const notaKg = (suma, kg) => {
    if (!kg) return { sufijo: '', sub: '', pendiente: false };
    if (kg.estado === 'cargando') return { sufijo: ' · Calculando kg…', sub: '', pendiente: false };
    if (kg.estado === 'sin') return { sufijo: ' · Sin peso promedio del lote para esta fecha; no se estiman kg.', sub: '', pendiente: true };

    return {
        sufijo: suma > 0 ? ` · ≈ <span class="tx-estado-num">${fmt(suma * kg.peso)}</span> kg` : '',
        sub: `<span class="gp-subtexto tx-num">Peso promedio ${fmt(kg.peso, 2)} kg/rac</span>`,
        pendiente: false
    };
};

const textoKg = (suma, kg) => (kg?.estado === 'ok' && suma > 0 ? ` · ≈ ${fmt(suma * kg.peso)} kg` : (kg?.estado === 'sin' ? ' · sin peso prom.' : (kg?.estado === 'cargando' ? ' · calculando kg…' : '')));

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
    guardando: false,
    exportando: false
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
const variosLotes = () => estado.plan?.variosLotes === true;
const listaY = (items) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} y ${items[items.length - 1]}` : items.join(''));
const cantTrab = (t) => (t.cantidadEjecutada != null ? Number(t.cantidadEjecutada) : t.cantidad);

const bloque = (a) => {
    const madre = a.division ? actividad(a.division) || a : a;

    return [madre, ...actividades().filter((x) => x.division && Number(x.division) === madre.id)];
};

const reabrible = (a) => a.estado !== 'PENDIENTE' && bloque(a).every((x) => Number(x.vinculada) !== 1 && Number(x.trasladada) !== 1);
const ausente = (t) => Number(t.ausente) === 1;
const exportable = () => actividades().some((a) => a.trabajadores.some((t) => !ausente(t)));
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
    contarSug();

    if (sug.abierto && sug.fecha !== fechaPlan()) cargarSug();
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

    const tarjeta = destacar ? $id(`act_${[].concat(destacar)[0]}`) : null;

    tarjeta?.scrollIntoView({ behavior: reducido() ? 'auto' : 'smooth', block: 'center' });
};

const fechaRel = (fecha) => {
    const rel = relativo(fecha);

    return fecha ? `${escaparHtml(fechaLegible(fecha))}${rel ? ` <span class="gp-chip-fila verde ms-1">${rel}</span>` : ''}` : '';
};

const pintarFecha = () => {
    $id('fecha_legible').innerHTML = fechaRel(fechaPlan());
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
    document.querySelectorAll('#barra_plan [data-accion="excel"]').forEach((boton) => { boton.disabled = estado.exportando || !exportable(); });
    document.querySelectorAll('#barra_plan [data-accion="duplicar_plan"]').forEach((boton) => {
        boton.disabled = estado.guardando || !actividades().length;
        boton.title = actividades().length ? '' : 'El plan no tiene actividades para duplicar';
    });
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
    const reabrir = reabrible(a);
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
        <td class="text-end tx-num">${fuera && !Number(t.cantidad) ? '0<span class="gp-subtexto">reasignada</span>'
            : (!a.division && t.cantidadEjecutada != null ? `${cant(t.cantidadEjecutada)}<span class="gp-subtexto">de ${cant(t.cantidad)} plan.</span>` : cantUm(t.cantidad))}</td>
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
        </div>${variosLotes() && a.trabajadores.some((t) => !ausente(t)) ? `<div class="pl-cierre-extra d-flex justify-content-end mt-2"><button type="button" class="btn btn-link btn-sm p-0" data-accion="varios-lotes" data-act="${a.id}"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>Se ejecutó en varios lotes</button></div>` : ''}</div>`;
    }

    let integracion = '';

    if (Number(a.vinculada) === 1) {
        const numero = `${escaparHtml(a.tipoTransaccion)}-${escaparHtml(a.numeroTransaccion)}`;

        integracion = `<span class="pl-chip pl-e-registrada" title="Registrada en ${{ fertilizacion: 'Fertilización', produccion: 'Producción' }[a.destino] || 'Registro de labores'}"><i class="bi bi-journal-check" aria-hidden="true"></i>${numero}</span>`;
    } else if (Number(a.registrable) === 1) {
        integracion = '<span class="gp-chip-fila tx-chip-neutro" data-chip="por-registrar" title="Regístrela desde Labores, Fertilización o Producción › Planeaciones completadas"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Por registrar</span>';
    }

    const reabrir = puede && reabrible(a)
        ? `<button type="button" class="btn btn-link btn-sm p-0" data-accion="reabrir" data-act="${a.id}">Reabrir</button>` : '';
    const trasladado = Number(a.trasladada) === 1 ? '<span class="gp-chip-fila tx-chip-neutro"><i class="bi bi-arrow-right me-1" aria-hidden="true"></i>Saldo trasladado</span>' : '';

    return `<div class="pl-cierre d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-3 min-w-0">
            ${chipEstado(a.estado)}
            ${a.estado === 'PARCIAL' ? `<span class="tx-dato"><span class="gp-etiqueta-mini">Ejecutado</span><span class="tx-dato-valor">${Number(a.cantidad) > 0 ? `${cant(a.cantidadEjecutada)} / ${cant(a.cantidad)} ${escaparHtml(a.uMedida)}` : cantUm(a.cantidadEjecutada, a.uMedida)}</span></span>` : ''}
            ${a.estado !== 'COMPLETADA' && texto(a.observacionCierre) ? `<span class="gp-subtexto fst-italic">${escaparHtml(a.observacionCierre)}</span>` : ''}
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">${trasladado}${integracion}${reabrir}</div>
    </div>`;
};

const chipDivision = (a) => {
    const b = bloque(a);

    if (b.length < 2) return '';

    const madre = b[0];
    const titulo = a.division ? `Planeada en ${madre.lote} · ${madre.novedad}` : b.map((x) => x.lote).join(', ');

    return `<button type="button" class="gp-chip-fila tx-chip-neutro border-0" data-chip="division" data-accion="ver-division" data-act="${madre.id}" title="${escaparHtml(titulo)}"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>${a.division ? `División de ${escaparHtml(madre.lote)}` : `Dividida en ${fmt(b.length)} lotes`}</button>`;
};

const tarjetaActividad = (a) => {
    const presentes = a.trabajadores.filter((t) => !ausente(t));
    const acciones = a.estado === 'PENDIENTE' && editable();
    const labor = escaparHtml(a.novedadNombre);
    const um = escaparHtml(a.uMedida);

    return `<article class="tx-tarjeta pl-actividad ${ESTADOS[a.estado]?.clase || 'pl-e-pendiente'}${[].concat(estado.destacar).includes(a.id) ? ' tx-tarjeta--destacada' : ''}" id="act_${a.id}">
        <div class="tx-tarjeta-cab tx-tarjeta-cab--fija pl-cab">
            <span class="pl-cab-labor min-w-0"><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> — <span class="gp-truncar d-inline-block align-bottom" title="${labor}">${labor}</span></span>
            <span class="pl-cab-chips d-flex flex-wrap align-items-center gap-1">
                <span class="gp-chip-fila tx-chip-neutro">${um}</span>
                ${chipEstado(a.estado)}
                ${texto(a.referencia) ? `<span class="gp-chip-ambito seccion font-monospace">PFA-${escaparHtml(a.referencia)}</span>` : ''}
            </span>
            <span class="pl-cab-datos ms-auto d-flex align-items-end gap-3">
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Total</span><span class="tx-dato-valor">${cantUm(a.cantidad, a.uMedida)}</span></span>
                ${Number(a.claseLabor) === 2 && esRac(a.uMedida) ? `<span class="tx-dato text-end"><span class="gp-etiqueta-mini">Kg est.</span><span class="tx-dato-valor"${Number(a.pesoRacimo) > 0 ? '' : ' title="Sin peso promedio del lote"'}>${Number(a.kgEstimado) > 0 ? `≈ ${fmt(a.kgEstimado)} kg` : '<span class="text-muted">—</span>'}</span></span>` : ''}
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Horario</span><span class="tx-dato-valor"><i class="bi bi-clock me-1" aria-hidden="true"></i>${escaparHtml(formatoHorario(a))}</span></span>
            </span>
            ${kebabActividad(a)}
        </div>
        <div class="tx-franja d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-signpost" aria-hidden="true"></i>
            <span class="min-w-0 flex-grow-1">${texto(a.observacion) ? escaparHtml(a.observacion) : '<span class="text-muted">—</span>'}</span>
            ${a.origen ? `<span class="gp-chip-fila tx-chip-neutro"><i class="bi bi-arrow-return-right me-1" aria-hidden="true"></i>Trasladada del ${escaparHtml(fechaCorta(a.origenFecha).slice(4))}</span>` : ''}
            ${chipDivision(a)}
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

const lineaActividad = (a, t) => `${escaparHtml(a.lote)} · ${escaparHtml(a.novedadNombre || a.novedad)} · ${cantUm(cantTrab(t), a.uMedida)}${texto(a.horaInicio) ? ` · ${escaparHtml(formatoHorario(a))}` : ''}`;

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
        <td class="text-end tx-num text-nowrap">${Number(cantTrab(t)) > 0 ? `${cant(cantTrab(t))} <span class="gp-chip-fila tx-chip-neutro">${escaparHtml(a.uMedida)}</span>` : '<span class="text-muted">—</span>'}</td>
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
            ${PERMISOS.editar ? '<button type="button" class="btn btn-sm btn-outline-secondary" id="btn_reabrir_plan" data-accion="reabrir-plan"><i class="bi bi-unlock me-1" aria-hidden="true"></i>Reabrir plan</button>' : ''}
        </div>`;

        return;
    }

    const faltan = plan.resumen.pendientes;

    barra.innerHTML = `<span class="gp-subtexto pl-faltan">${!plan.resumen.actividades ? 'El plan no tiene actividades' : (faltan ? `Faltan <strong>${fmt(faltan)}</strong> ${faltan === 1 ? 'actividad' : 'actividades'} por cerrar` : '<i class="bi bi-check2-all me-1" aria-hidden="true"></i>Todas las actividades están cerradas')}</span>
        <div class="d-flex flex-wrap justify-content-end gap-2 ms-auto pl-acciones-fin">
            ${editable() ? botonAgregar('d-sm-none w-100') : ''}
            ${PERMISOS.editar ? `<button type="button" class="btn btn-primary" id="btn_cerrar_plan" data-accion="cerrar-plan"${faltan || !plan.resumen.actividades ? ' disabled' : ''}><i class="bi bi-lock me-1" aria-hidden="true"></i>Cerrar plan</button>` : ''}
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
        invalidarSug();

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

        aplicarPlan(respuesta.plan, typeof destacar === 'function' ? destacar(respuesta) : destacar);
        Toast.fire({ icon: 'success', title: escaparHtml(mensaje) });
        invalidarSug();

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
        invalidarSug();
    } catch (error) {
        errorSwal(error, 'No se pudo eliminar el plan');
    }
};

const quitarTrabajador = async (id, tercero) => {
    const a = actividad(id);
    const t = a?.trabajadores.find((x) => x.tercero === tercero);

    if (!t || !await confirmar(`¿Quitar a ${texto(t.codigo) || tercero}?`, `Se quitará de ${a.novedad} en el lote ${a.lote}.${Number(t.cantidad) > 0 ? ` El total de la actividad baja ${cant(t.cantidad)} ${a.uMedida}.` : ''}`, 'Sí, quitar', true)) return;

    const lista = pendientesPlan().map((x) => {
        const envio = aEnvio(x);

        if (x.id === a.id) envio.trabajadores = envio.trabajadores.filter((w) => w.tercero !== tercero);

        return envio;
    });

    guardarLista(lista, 'Trabajador quitado', a.id);
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

const form = { id: null, trabajadores: [], sug: null };

const fechaActividad = () => (form.sug ? $id('act_fecha').value || fechaPlan() : fechaPlan());

const laborOpcion = () => $id('act_labor').selectedOptions[0] || null;
const umFormulario = () => $id('act_um').value;
const grupoFormulario = () => texto(laborOpcion()?.dataset.grupo);
const esCosechaForm = () => laborOpcion()?.dataset.clase === '2';
const palmasForm = () => Number($id('act_lote').selectedOptions[0]?.dataset.palmas || 0);

const errorCampo = (id, visible, mensaje = null) => {
    if (mensaje !== null) $id(id).textContent = mensaje;
    mostrar($id(id), visible);
};

const pintarMensaje = (elemento, clase, icono, html, sub = '') => {
    elemento.className = `tx-reparto tx-reparto--${clase} pl-indicador`;
    elemento.innerHTML = `<span class="tx-estado text-wrap"><i class="bi ${icono}" aria-hidden="true"></i><span>${html}</span></span>${sub}`;
};

const pintarIndicador = (elemento, asignado, meta, um, motivo = '', kg = null) => {
    const c = Number.isNaN(asignado) ? NaN : milesimas(asignado);
    const m = milesimas(meta);
    const clase = motivo || !(m > 0) || Number.isNaN(c) ? (motivo ? 'pendiente' : 'inactivo') : (c === m ? 'cuadrado' : (c > m ? 'excedido' : 'pendiente'));
    const icono = { cuadrado: 'bi-check-circle', excedido: 'bi-exclamation-octagon', pendiente: 'bi-hourglass-split', inactivo: 'bi-dash-circle' }[clase];
    const nota = notaKg(motivo ? 0 : asignado, motivo ? null : kg);
    const base = clase === 'excedido' && um === 'palmas'
        ? `Excede en <span class="tx-estado-num">${cant(asignado - meta)}</span> palmas · <span class="tx-estado-num">${cant(asignado)} / ${cant(meta)}</span>`
        : `Asignado <span class="tx-estado-num">${Number.isNaN(c) ? '—' : cant(asignado)} / ${cant(meta)}</span> ${escaparHtml(um)}`;

    pintarMensaje(elemento, clase, icono, `${motivo ? escaparHtml(motivo) : base}${nota.sufijo}`, `${nota.sub}<div class="tx-reparto-barra"><span></span></div>`);
    elemento.querySelector('.tx-reparto-barra span').style.width = `${m > 0 && !Number.isNaN(c) ? Math.min(100, Math.round(c * 100 / m)) : 0}%`;
};

const pintarTotal = (elemento, lista, um, kg = null) => {
    const invalida = lista.some((t) => malaCant(t.cantidad, um));
    const suma = lista.reduce((total, t) => total + t.cantidad, 0);
    const nota = notaKg(invalida ? 0 : suma, invalida ? null : kg);

    pintarMensaje(elemento, invalida || nota.pendiente ? 'pendiente' : 'inactivo', invalida ? 'bi-exclamation-octagon' : 'bi-calculator', invalida
        ? 'Hay cantidades inválidas'
        : `${suma > 0 ? `Total <span class="tx-estado-num">${cant(suma)}</span> ${escaparHtml(um)}` : 'Sin cantidades asignadas'}${nota.sufijo}`, nota.sub);
};

const pintarCantidades = (elemento, lista, um, meta, palmas, lote, kg) => {
    const suma = lista.reduce((total, t) => total + t.cantidad, 0);
    const invalida = lista.some((t) => malaCant(t.cantidad, um));

    if (!invalida && esPal(um) && lote && palmas > 0) pintarIndicador(elemento, suma, palmas, 'palmas');
    else if (!invalida && meta > 0) pintarIndicador(elemento, suma, meta, um, '', kg);
    else if (!invalida && esPal(um) && lote) pintarMensaje(elemento, 'inactivo', 'bi-dash-circle', 'El lote no tiene palmas en producción registradas.');
    else pintarTotal(elemento, lista, um, kg);
};

const metaDe = (valor, um) => {
    const numero = aNumero(valor);

    return malaCant(numero, um) ? 0 : numero;
};

const excesoPalmas = (lista, um, palmas, lote) => {
    const suma = lista.reduce((total, t) => total + (Number.isNaN(t.cantidad) ? 0 : t.cantidad), 0);

    return esPal(um) && lote && palmas > 0 && milesimas(suma) > milesimas(palmas)
        ? `La suma de palmas (${cant(suma)}) supera las ${cant(palmas)} palmas en producción del lote ${lote}.` : '';
};

const kgForm = () => {
    const finca = $id('act_finca').value;
    const lote = $id('act_lote').value;
    const fecha = fechaActividad();

    return esCosechaForm() && esRac(umFormulario()) && finca && lote && fecha ? pesoDe(finca, lote, fecha, refrescarKg) : null;
};

const celdaKg = (t, kg) => (kg?.estado === 'ok' && t?.cantidad > 0 ? `≈ ${fmt(t.cantidad * kg.peso)}` : '—');

const indicadorActividad = () => {
    const um = umFormulario() || '';
    const lote = $id('act_lote').value;
    const campo = $id('act_total');
    const total = aNumero(campo.value);
    const totalMalo = texto(campo.value) !== '' && malaCant(total, um);
    const exceso = excesoPalmas(form.trabajadores, um, palmasForm(), lote);

    pintarCantidades($id('act_indicador'), form.trabajadores, um, metaDe(campo.value, um), palmasForm(), lote, kgForm());
    $id('act_total_um').textContent = um || '—';
    campo.setAttribute('inputmode', modoNum(um));
    campo.classList.toggle('is-invalid', totalMalo);
    errorCampo('error_act_total', totalMalo, msgCant(um));
    $id('act_repartir').disabled = !form.trabajadores.length || !(total > 0) || totalMalo;
    errorCampo('error_act_palmas', exceso !== '', exceso);
    $id('act_lote_palmas').textContent = `${fmt(palmasForm())} palmas en producción`;
    mostrar($id('act_lote_palmas'), esPal(um) && Boolean(lote) && palmasForm() > 0);
};

const refrescarKg = () => {
    const kg = kgForm();

    $id('act_filas').querySelectorAll('[data-kg]').forEach((celda) => { celda.textContent = celdaKg(form.trabajadores[Number(celda.dataset.kg)], kg); });
    indicadorActividad();
};

const repartirActividad = () => {
    if ($id('act_repartir').disabled) return;

    repartir(aNumero($id('act_total').value), form.trabajadores.length, esRac(umFormulario())).forEach((valor, i) => { form.trabajadores[i].cantidad = valor; });
    pintarReparto();
    $id('act_filas').querySelectorAll('[data-reparto]').forEach(destellar);
};

const duplicadoFormulario = (t) => {
    const novedad = $id('act_labor').value;
    const finca = $id('act_finca').value;
    const lote = $id('act_lote').value;
    const otra = novedad && finca && lote && fechaActividad() === fechaPlan() ? duplicado(t.tercero, novedad, finca, lote, form.id) : null;

    return otra ? `${texto(t.codigo) || t.tercero} ya tiene esta labor en ${otra.lote}` : '';
};

const pintarReparto = () => {
    const um = umFormulario();
    const cosecha = esCosechaForm() && esRac(um);
    const kg = kgForm();

    mostrar($id('act_th_kg'), cosecha);
    $id('act_contador').textContent = fmt(form.trabajadores.length);
    $id('act_filas').innerHTML = form.trabajadores.length ? form.trabajadores.map((t, i) => {
        const nombre = escaparHtml(t.nombre || t.tercero);
        const error = duplicadoFormulario(t);

        return `<tr class="${error ? 'pl-fila-dup' : ''}">
            <td><span class="font-monospace fw-semibold me-1">${escaparHtml(t.codigo)}</span><span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span>${t.reasignadoDe ? `<span class="gp-chip-fila ambar ms-1">recibe de ${escaparHtml(codigoDe(t.reasignadoDe))}</span>` : ''}${error ? `<div class="invalid-feedback d-block">${escaparHtml(error)}</div>` : ''}</td>
            <td class="text-end"><div class="d-flex align-items-center justify-content-end gap-2"><span class="gp-gramos d-none" id="g_rep_${i}"></span><input type="text" class="form-control form-control-sm tx-input-num font-monospace ms-auto${malaCant(t.cantidad, um) ? ' is-invalid' : ''}" data-reparto="${i}" value="${valorCant(t.cantidad)}" placeholder="Opcional" inputmode="${modoNum(um)}" maxlength="14" autocomplete="off" aria-label="Cantidad de ${nombre}" data-gramos-um="#act_um" aria-describedby="g_rep_${i}"></div></td>
            ${cosecha ? `<td class="text-end tx-num text-muted text-nowrap" data-kg="${i}">${celdaKg(t, kg)}</td>` : ''}
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-quitar-reparto="${escaparHtml(t.tercero)}" title="Quitar a ${nombre}" aria-label="Quitar a ${nombre}"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
        </tr>`;
    }).join('') : `<tr><td colspan="${cosecha ? 4 : 3}" class="text-center text-muted py-3">Seleccione los trabajadores de la actividad.</td></tr>`;
    indicadorActividad();

    const fuera = form.sug && fechaActividad() === fechaPlan() ? form.trabajadores.filter((t) => nitsAusentes().has(t.tercero)) : [];

    mostrar($id('act_aviso_ausentes'), fuera.length > 0);
    $id('act_aviso_ausentes').innerHTML = fuera.length ? `<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>${fuera.map((t) => escaparHtml(t.codigo || t.tercero)).join(', ')} ${fuera.length === 1 ? 'está ausente' : 'están ausentes'} en esa fecha; ${fuera.length === 1 ? 'quítelo o reemplácelo' : 'quítelos o reemplácelos'}.` : '';
};

const sincronizarTrabajadores = () => {
    const datos = $('#act_terceros').select2('data').filter((opcion) => opcion.id);

    form.trabajadores = datos.map((opcion) => form.trabajadores.find((t) => t.tercero === opcion.id)
        || { tercero: opcion.id, codigo: codigoTercero(opcion), nombre: texto(opcion.text), cantidad: 0, reasignadoDe: '' });

    errorCampo('error_act_terceros', false);
    $id('act_terceros').closest('.tx-select-ancho').classList.remove('is-invalid');
    pintarReparto();
};

const actualizarLabor = (forzar = true) => {
    $id('act_codigo').value = $id('act_labor').value;
    $id('act_codigo').classList.remove('is-invalid');
    errorCampo('error_act_labor', false);
    $id('act_labor').closest('.tx-select-ancho').classList.remove('is-invalid');
    mostrar($id('col_pfa'), grupoFormulario() === '02');

    if (grupoFormulario() === '02') cargarPfa($id('act_pfa').dataset.previo || '');

    const cosecha = esCosechaForm() && (forzar || esRac(umFormulario()));

    if (cosecha) {
        asegurarOpcion($id('act_um'), 'RAC');
        $('#act_um').val('RAC').trigger('change.select2');
        errorCampo('error_act_um', false);
        $id('act_um').closest('.tx-select-ancho').classList.remove('is-invalid');
    }

    $id('act_um').disabled = cosecha;
    mostrar($id('act_um_ayuda'), cosecha);
    pintarReparto();
};

const asegurarOpcion = (selector, valor) => {
    if (valor && ![...selector.options].some((o) => o.value === valor)) selector.add(new Option(valor, valor));
};

const opcionLote = (l) => `<option value="${escaparHtml(texto(l.codigo))}" data-nombre="${escaparHtml(texto(l.descripcion))}" data-seccion="${escaparHtml(texto(l.seccion))}" data-palmas="${Number(l.palmas) || 0}">${escaparHtml(texto(l.codigo))} — ${escaparHtml(texto(l.descripcion))}</option>`;

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
        const filas = (await pedir('planesFertilizacion', { empresa: empresaId(), finca, fecha: fechaActividad() })).filas || [];

        selector.innerHTML += filas.map(opcionPfa).join('');
    } catch (error) {
        avisar(error);
    }

    selector.value = Array.from(selector.options).some((o) => o.value === referencia) ? referencia : '';
};

const contarObservacion = () => {
    $id('contador_act_observacion').textContent = `${$id('act_observacion').value.length} / 250`;
};

const botonActividad = () => {
    const boton = $id('btn_guardar_actividad');

    boton.disabled = estado.guardando;
    boton.className = `btn ${form.sug ? 'btn-success' : 'btn-primary'}`;
    boton.innerHTML = form.sug
        ? (estado.guardando ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Aprobando…' : '<i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Aprobar y planear')
        : '<i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar';
};

const fechaDestino = () => {
    const fecha = $id('act_fecha').value;
    const otra = Boolean(fecha) && fecha !== fechaPlan();

    $id('chip_fecha_actividad').textContent = fecha ? fechaLegible(fecha) : 'Sin fecha';
    $id('act_fecha_ayuda').textContent = otra ? `Se creará en la planeación del ${fechaLegible(fecha)}.` : '';
    mostrar($id('act_fecha_ayuda'), otra);
};

const abrirActividad = async (base = null, duplicar = false, sugerida = null) => {
    const editar = Boolean(base) && !duplicar && !sugerida;

    form.id = editar ? base.id : null;
    form.sug = sugerida;
    form.trabajadores = base ? base.trabajadores.filter((t) => !ausente(t)).map((t) => ({
        tercero: t.tercero, codigo: texto(t.codigo), nombre: texto(t.nombre), cantidad: Number(t.cantidad || 0), reasignadoDe: editar ? texto(t.reasignadoDe) : ''
    })) : [];

    $id('icono_actividad').className = `bi ${sugerida ? 'bi-lightbulb' : (editar ? 'bi-pencil-square' : 'bi-plus-square')} me-1`;
    $id('texto_actividad').textContent = sugerida ? 'Revisar sugerencia' : (editar ? 'Editar actividad' : 'Agregar actividad');
    $id('chip_fecha_actividad').textContent = fechaLegible(fechaPlan());
    $id('chip_duplicada').textContent = duplicar ? `Duplicada de ${base.novedad} — ${base.novedadNombre}` : '';
    mostrar($id('chip_duplicada'), duplicar);
    mostrar($id('btn_guardar_otra'), !editar && !sugerida);
    $id('chip_sug_actividad').innerHTML = sugerida ? chipUrgencia(sugerida) : '';
    mostrar($id('chip_sug_actividad'), Boolean(sugerida));
    $id('act_sugerencia').innerHTML = sugerida ? referenciaSug(sugerida) : '';
    mostrar($id('act_sugerencia'), Boolean(sugerida));
    mostrar($id('col_act_fecha'), Boolean(sugerida));
    ['col_act_finca', 'col_act_lote'].forEach((id) => {
        $id(id).classList.toggle('col-md-4', Boolean(sugerida));
        $id(id).classList.toggle('col-md-6', !sugerida);
    });
    $id('act_fecha').value = fechaPlan();
    fechaDestino();
    botonActividad();

    document.querySelectorAll('#formulario_actividad .is-invalid').forEach((campo) => campo.classList.remove('is-invalid'));
    ['error_act_fecha', 'error_act_finca', 'error_act_lote', 'error_act_labor', 'error_act_um', 'error_act_horario', 'error_act_terceros'].forEach((id) => errorCampo(id, false));

    $id('act_pfa').dataset.previo = editar || sugerida ? texto(base.referencia) : '';
    $('#act_finca').val(base?.finca || '').trigger('change.select2');
    $('#act_labor').val(editar || sugerida ? base.novedad : '').trigger('change.select2');
    asegurarOpcion($id('act_um'), texto(base?.uMedida));
    $('#act_um').val(base?.uMedida || '').trigger('change.select2');
    $id('act_hora_inicio').value = base ? texto(base.horaInicio) : '06:00';
    $id('act_hora_fin').value = base ? texto(base.horaFin) : '14:00';
    $id('act_observacion').value = base ? texto(base.observacion) : '';
    $id('act_total').value = '';
    contarObservacion();
    $('#act_terceros').empty().append(form.trabajadores.map(opcionTercero)).trigger('change.select2');
    actualizarLabor(!editar);
    bootstrap.Modal.getOrCreateInstance($id('modalActividad')).show();
    await cargarLotes(base?.finca || '', base?.lote || '');
    pintarReparto();
};

const validarActividad = () => {
    const finca = $id('act_finca').value;
    const lote = $id('act_lote').value;
    const labor = $id('act_labor').value;
    const um = umFormulario();
    const inicio = $id('act_hora_inicio').value;
    const fin = $id('act_hora_fin').value;
    const horaMal = Boolean(inicio) !== Boolean(fin) || (inicio && fin && fin <= inicio);
    const invalidos = form.trabajadores.some((t) => malaCant(t.cantidad, um));
    const exceso = excesoPalmas(form.trabajadores, um, palmasForm(), lote);
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
    marcar('act_terceros', 'error_act_terceros', !form.trabajadores.length);

    const sinFecha = Boolean(form.sug) && !$id('act_fecha').value;

    errorCampo('error_act_fecha', sinFecha);
    $id('act_fecha').classList.toggle('is-invalid', sinFecha);

    if (sinFecha || !finca || !lote || !labor || !um || !form.trabajadores.length) return 'Complete los campos obligatorios.';
    if (horaMal) return $id('error_act_horario').textContent;
    if (invalidos) return `Hay cantidades inválidas. ${msgCant(um)}`;

    if (exceso) {
        $id('act_filas').querySelector('[data-reparto]')?.focus();

        return exceso;
    }

    if (duplicados.length) return duplicados[0];

    return '';
};

const datosActividad = () => ({
    finca: $id('act_finca').value,
    lote: $id('act_lote').value,
    novedad: $id('act_labor').value,
    uMedida: umFormulario(),
    horaInicio: $id('act_hora_inicio').value,
    horaFin: $id('act_hora_fin').value,
    observacion: texto($id('act_observacion').value),
    referencia: grupoFormulario() === '02' ? $id('act_pfa').value : '',
    trabajadores: form.trabajadores.map((t) => ({ tercero: t.tercero, cantidad: t.cantidad, reasignadoDe: t.reasignadoDe }))
});

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

    if (form.sug) {
        aprobarSug();

        return;
    }

    const datos = { id: form.id, ...datosActividad() };
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
    $id('act_pfa').dataset.previo = '';
    $('#act_labor').val('').trigger('change.select2');
    $('#act_um').val('').trigger('change.select2');
    $('#act_terceros').empty().trigger('change.select2');
    $id('act_observacion').value = '';
    $id('act_total').value = '';
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

    const final = cantidad >= 0 ? a.cantidad - t.cantidad + cantidad : NaN;

    $id('edit_resumen').textContent = !(Number(a.cantidad) > 0) && milesimas(final) === 0 ? 'Total de la actividad: sin cantidades'
        : `Total de la actividad: ${Number(a.cantidad) > 0 ? cant(a.cantidad) : '—'} → ${milesimas(final) > 0 ? `${cant(final)} ${a.uMedida}` : '—'}`;
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
    $id('edit_cantidad').value = valorCant(Number(t.cantidad));
    $id('edit_cantidad').setAttribute('inputmode', modoNum(a.uMedida));
    $id('edit_cantidad').dataset.gramos = a.uMedida || '-';
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
    const mala = malaCant(cantidad, a.uMedida);

    errorCampo('error_edit_cantidad', mala, msgCant(a.uMedida));
    $id('edit_cantidad').classList.toggle('is-invalid', mala);

    if (error || mala) return;

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

    $id('cierre_saldo').textContent = a && a.cantidad > 0 && ejecutada > 0 && ejecutada < a.cantidad ? `Saldo: ${cant(a.cantidad - ejecutada)} ${a.uMedida}` : '';
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
        <div class="text-end"><span class="gp-etiqueta-mini d-block">Total planeado</span><span class="tx-num fw-semibold">${cantUm(a.cantidad, a.uMedida)}</span></div>
    </div>`;
    mostrar($id('cierre_col_cantidad'), parcial);
    mostrar($id('cierre_varios'), parcial && variosLotes() && a.trabajadores.some((t) => !ausente(t)));
    $id('cierre_marca').className = Number(a.cantidad) > 0 ? 'text-danger' : 'fw-normal text-muted';
    $id('cierre_marca').textContent = Number(a.cantidad) > 0 ? '*' : '(opcional)';
    $id('cierre_um').textContent = a.uMedida;
    $id('cierre_cantidad').setAttribute('inputmode', modoNum(a.uMedida));
    $id('cierre_cantidad').dataset.gramos = a.uMedida || '-';
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
    const planeada = Number(a.cantidad) > 0;
    const noEntero = parcial && esRac(a.uMedida) && Number.isFinite(ejecutada) && !Number.isInteger(ejecutada);
    const errorCantidad = noEntero || (parcial && (planeada ? !(ejecutada > 0 && ejecutada < a.cantidad) : Number.isNaN(ejecutada)));

    errorCampo('error_cierre_cantidad', errorCantidad, noEntero ? msgCant(a.uMedida) : (planeada
        ? `Indique una cantidad mayor que cero y menor que el total planeado (${cant(a.cantidad)} ${a.uMedida}).`
        : (texto($id('cierre_cantidad').value).startsWith('-') ? 'La cantidad ejecutada no puede ser negativa.' : msgCant(a.uMedida))));
    $id('cierre_cantidad').classList.toggle('is-invalid', errorCantidad);
    errorCampo('error_cierre_observacion', observacion === '');
    $id('cierre_observacion').classList.toggle('is-invalid', observacion === '');

    if (errorCantidad || observacion === '') return;

    const ok = await ejecutar('cerrarActividad', {
        linea: a.id, estado: cierre.estado, cantidadEjecutada: parcial ? ejecutada : 0, observacionCierre: observacion
    }, parcial ? 'Actividad cerrada como parcial' : 'Actividad marcada como no ejecutada', a.id, 'No se pudo cerrar la actividad');

    if (ok) bootstrap.Modal.getOrCreateInstance($id('modalCierre')).hide();
};

const MAX_LOTES = 10;
const vl = { a: null, lotes: [], disponibles: [], raw: new Map(), sucio: false, cerrar: false, enviando: false, turno: 0 };

const pfaVl = () => texto(vl.a?.grupo) === '02' && texto(vl.a?.referencia) !== '';
const presentesVl = () => vl.a.trabajadores.filter((t) => !ausente(t));
const valorVl = (tercero, lote) => aNumero(vl.raw.get(`${tercero}|${lote}`) ?? '');
const numVl = (tercero, lote) => (Number.isFinite(valorVl(tercero, lote)) ? valorVl(tercero, lote) : 0);
const sumaLoteVl = (lote) => presentesVl().reduce((suma, t) => suma + numVl(t.tercero, lote), 0);
const conCantidadVl = () => vl.lotes.filter((l) => milesimas(sumaLoteVl(l.codigo)) > 0);
const camposVl = () => [...$id('vl_tabla').querySelectorAll('[data-vl-lote]')];

const estadoMadreVl = () => {
    const suma = milesimas(sumaLoteVl(vl.lotes[0].codigo));
    const meta = milesimas(vl.a.cantidad);

    return suma <= 0 ? '' : (meta > 0 && suma < meta ? 'PARCIAL' : 'COMPLETADA');
};

const botonVl = () => {
    const n = conCantidadVl().length;

    $id('vl_confirmar').disabled = vl.enviando;
    $id('vl_confirmar').innerHTML = vl.enviando
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Cerrando…'
        : `<i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>${n > 1 ? `Cerrar en ${fmt(n)} lotes` : 'Cerrar en varios lotes'}`;
};

const calcularVl = () => {
    const a = vl.a;
    const um = escaparHtml(a.uMedida);
    const meta = Number(a.cantidad) > 0;
    const tabla = $id('vl_tabla');
    const total = vl.lotes.reduce((suma, l) => suma + sumaLoteVl(l.codigo), 0);
    const planeado = vl.lotes[0];
    const madre = estadoMadreVl();
    const hijas = conCantidadVl().filter((l) => !l.planeado).map((l) => `<span class="font-monospace fw-semibold">${escaparHtml(l.codigo)}</span>`);
    const parcial = madre === 'PARCIAL';

    tabla.querySelectorAll('[data-vl-total-trab]').forEach((celda) => {
        const t = presentesVl().find((x) => x.tercero === celda.dataset.vlTotalTrab);
        const suma = vl.lotes.reduce((s, l) => s + numVl(t.tercero, l.codigo), 0);
        const dif = milesimas(suma) - milesimas(t.cantidad);

        celda.innerHTML = `${cant(suma)}${Number(t.cantidad) > 0 ? (dif ? `<span class="gp-subtexto tx-texto-ambar">(${dif > 0 ? '+' : '−'}${cant(Math.abs(dif) / 1000)})</span>` : ' <i class="bi bi-check2 text-success" aria-hidden="true"></i>') : ''}`;
    });
    tabla.querySelectorAll('[data-vl-total-lote]').forEach((celda) => {
        const l = vl.lotes.find((x) => x.codigo === celda.dataset.vlTotalLote);
        const suma = sumaLoteVl(l.codigo);
        const tope = esPal(a.uMedida) && l.palmas > 0;
        const exceso = tope && milesimas(suma) > milesimas(l.palmas);
        const propio = l.planeado && meta;

        celda.className = `text-end tx-num${exceso ? ' text-danger' : (propio ? (milesimas(suma) < milesimas(a.cantidad) ? ' tx-texto-ambar' : ' text-success') : '')}`;
        celda.innerHTML = `${propio ? `${cant(suma)} / ${cant(a.cantidad)}` : cant(suma)} ${um}${tope ? `<span class="gp-subtexto${exceso ? ' text-danger' : ''}">de ${fmt(l.palmas)} palmas</span>` : ''}`;
    });
    tabla.querySelector('[data-vl-total-general]').innerHTML = `${cant(total)} ${um}`;

    if (camposVl().some((c) => malaCant(aNumero(c.value), a.uMedida))) {
        pintarMensaje($id('vl_indicador'), 'pendiente', 'bi-exclamation-octagon', 'Hay cantidades inválidas');
    } else {
        const igual = meta && milesimas(total) === milesimas(a.cantidad);

        pintarMensaje($id('vl_indicador'), igual ? 'cuadrado' : 'inactivo', igual ? 'bi-check-circle' : 'bi-calculator', `Total ejecutado <span class="tx-estado-num">${cant(total)}</span> ${um}${meta ? ` · planeado ${cant(a.cantidad)}` : ''}`);
    }

    $id('vl_resultado').innerHTML = madre
        ? `<span class="font-monospace fw-semibold">${escaparHtml(planeado.codigo)}</span> quedará ${chipEstado(madre)} (${meta ? `${cant(sumaLoteVl(planeado.codigo))} / ${cant(a.cantidad)}` : cant(sumaLoteVl(planeado.codigo))} ${um})${hijas.length
            ? ` y se ${hijas.length === 1 ? 'creará 1 actividad' : `crearán ${fmt(hijas.length)} actividades`} ${chipEstado('COMPLETADA')} en ${listaY(hijas)}.`
            : '. Agregue los lotes donde también se ejecutó la labor.'}`
        : `<i class="bi bi-exclamation-triangle tx-texto-ambar me-1" aria-hidden="true"></i>Indique la cantidad ejecutada en el lote planeado ${escaparHtml(planeado.codigo)}.`;
    $id('vl_marca').className = parcial ? 'text-danger' : 'fw-normal text-muted';
    $id('vl_marca').textContent = parcial ? '*' : '(opcional)';

    if (!parcial) {
        errorCampo('error_vl_observacion', false);
        $id('vl_observacion').classList.remove('is-invalid');
    }

    botonVl();
};

const cabLoteVl = (l) => {
    const codigo = escaparHtml(l.codigo);
    const nombre = escaparHtml(l.nombre);
    const pfa = l.enPfa
        ? `<span class="gp-chip-ambito seccion font-monospace">PFA-${escaparHtml(vl.a.referencia)}</span>`
        : '<span class="gp-chip-fila tx-chip-neutro" title="La actividad de este lote se crea sin referencia PFA">Sin PFA</span>';

    return `<th class="text-end ${l.planeado ? 'pl-vl-planeado' : 'pl-vl-col-lote'}">
        <span class="d-flex align-items-center justify-content-end"><span class="font-monospace fw-semibold">${codigo}</span>${l.planeado
            ? '<span class="gp-chip-fila verde ms-1">Planeado</span>'
            : `<button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" data-vl-quitar="${codigo}" aria-label="Quitar lote ${codigo}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>`}</span>
        <span class="gp-subtexto gp-truncar fw-normal ms-auto" title="${nombre}">${nombre || '—'}</span>
        ${esPal(vl.a.uMedida) && l.palmas > 0 ?`<span class="gp-subtexto fw-normal">${fmt(l.palmas)} palmas</span>` : ''}
        ${!l.planeado && pfaVl() ? `<span class="d-block mt-1">${pfa}</span>` : ''}
    </th>`;
};

const pintarTablaVl = () => {
    const a = vl.a;
    const um = a.uMedida;
    const filas = presentesVl().map((t) => {
        const nombre = escaparHtml(t.nombre || t.tercero);

        return `<tr>
            <td class="gp-col-sticky pl-vl-trab"><span class="font-monospace fw-semibold me-1">${escaparHtml(t.codigo)}</span><span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${escaparHtml(nombreCorto(t.nombre || t.tercero))}</span>${texto(t.reasignadoDe) ? `<span class="gp-chip-fila ambar ms-1">recibe de ${escaparHtml(codigoDe(t.reasignadoDe))}</span>` : ''}</td>
            <td class="text-end tx-num text-muted">${cantUm(t.cantidad)}</td>
            ${vl.lotes.map((l) => `<td class="text-end${l.planeado ? ' pl-vl-planeado' : ''}"><input type="text" class="form-control form-control-sm tx-input-num font-monospace text-end ms-auto${malaCant(valorVl(t.tercero, l.codigo), um) ? ' is-invalid' : ''}" data-vl-tercero="${escaparHtml(t.tercero)}" data-vl-lote="${escaparHtml(l.codigo)}" value="${escaparHtml(vl.raw.get(`${t.tercero}|${l.codigo}`) ?? '')}" placeholder="0" inputmode="${modoNum(um)}" maxlength="14" autocomplete="off" aria-label="Cantidad de ${nombre} en ${escaparHtml(l.codigo)}"></td>`).join('')}
            <td class="text-end fw-semibold tx-num gp-col-sticky pl-vl-total" data-vl-total-trab="${escaparHtml(t.tercero)}"></td>
        </tr>`;
    }).join('');

    $id('vl_tabla').innerHTML = `<thead class="gp-thead"><tr>
            <th class="gp-col-sticky pl-vl-trab">Trabajador</th>
            <th class="text-end">Plan</th>
            ${vl.lotes.map(cabLoteVl).join('')}
            <th class="text-end gp-col-sticky pl-vl-total">Total</th>
        </tr></thead>
        <tbody>${filas}</tbody>
        <tfoot><tr class="pl-vl-pie fw-semibold">
            <td class="gp-col-sticky pl-vl-trab">Total por lote</td>
            <td class="text-end tx-num text-muted">${cantUm(a.cantidad)}</td>
            ${vl.lotes.map((l) => `<td data-vl-total-lote="${escaparHtml(l.codigo)}"></td>`).join('')}
            <td class="text-end tx-num gp-col-sticky pl-vl-total" data-vl-total-general></td>
        </tr></tfoot>`;
    calcularVl();
};

const pintarSelectorVl = (cargando = false) => {
    const selector = $id('vl_agregar_lote');
    const usados = new Set(vl.lotes.map((l) => l.codigo));
    const libres = vl.disponibles.filter((l) => !usados.has(texto(l.codigo)) && Number(l.activo ?? 1) !== 0);
    const lleno = vl.lotes.length >= MAX_LOTES;

    if ($(selector).hasClass('select2-hidden-accessible')) $(selector).select2('destroy');

    selector.innerHTML = '<option value=""></option>' + libres.map(opcionLote).join('');
    selector.disabled = cargando || lleno || !libres.length;
    $(selector).select2({
        width: '100%',
        language: IDIOMA_SELECT2,
        dropdownParent: $('#modalVariosLotes'),
        placeholder: cargando ? 'Cargando lotes…' : (lleno ? 'Puede distribuir la actividad en máximo 10 lotes.'
            : (libres.length ? `Agregar lote de la finca ${vl.a.finca} — ${vl.a.fincaNombre}…` : 'No hay más lotes activos en la finca.')),
        templateResult: (opcion) => (opcion.id && opcion.element
            ? $(`<div><span class="font-monospace fw-semibold">${escaparHtml(opcion.id)}</span> ${escaparHtml(opcion.element.dataset.nombre)}${esPal(vl.a.uMedida) ? `<span class="gp-subtexto">${fmt(opcion.element.dataset.palmas)} palmas</span>` : ''}</div>`)
            : opcion.text)
    });
};

const cargarLotesVl = async () => {
    const turno = ++vl.turno;
    let filas = [];

    pintarSelectorVl(true);

    try {
        filas = (await pedir('lotes', { empresa: empresaId(), finca: vl.a.finca, referencia: pfaVl() ? vl.a.referencia : '' })).filas || [];
    } catch (error) {
        avisar(error);
    }

    if (turno !== vl.turno) return;

    const propio = filas.find((l) => texto(l.codigo) === vl.a.lote);

    vl.disponibles = filas.filter((l) => texto(l.codigo) !== vl.a.lote);
    vl.lotes[0].palmas = Number(propio?.palmas) || 0;
    pintarSelectorVl();
    calcularVl();
};

const agregarLoteVl = (codigo) => {
    const l = vl.disponibles.find((x) => texto(x.codigo) === codigo);

    if (!l || vl.lotes.length >= MAX_LOTES || vl.lotes.some((x) => x.codigo === codigo)) return;

    vl.lotes.push({ codigo, nombre: texto(l.descripcion), palmas: Number(l.palmas) || 0, enPfa: Number(l.enPfa) === 1, planeado: false });
    vl.sucio = true;
    errorCampo('error_vl_lotes', false);
    pintarSelectorVl();
    pintarTablaVl();
    camposVl().find((c) => c.dataset.vlLote === codigo)?.focus();
};

const quitarLoteVl = (codigo) => {
    vl.lotes = vl.lotes.filter((l) => l.planeado || l.codigo !== codigo);
    presentesVl().forEach((t) => vl.raw.delete(`${t.tercero}|${codigo}`));
    vl.sucio = true;
    pintarSelectorVl();
    pintarTablaVl();
};

const abrirVariosLotes = async (id) => {
    const a = actividad(id);
    const modalCierre = $id('modalCierre');

    if (!a || a.estado !== 'PENDIENTE' || !variosLotes()) return;

    if (modalCierre.classList.contains('show')) {
        await new Promise((fin) => {
            modalCierre.addEventListener('hidden.bs.modal', fin, { once: true });
            bootstrap.Modal.getOrCreateInstance(modalCierre).hide();
        });
    }

    Object.assign(vl, { a, lotes: [{ codigo: a.lote, nombre: texto(a.loteNombre), palmas: 0, enPfa: true, planeado: true }], disponibles: [], raw: new Map(), sucio: false, cerrar: false, enviando: false });
    presentesVl().forEach((t) => vl.raw.set(`${t.tercero}|${a.lote}`, valorCant(Number(t.cantidad))));
    $id('vl_chip').textContent = `${a.lote} · ${a.novedad}`;
    $id('vl_referencia').innerHTML = `<div class="d-flex flex-wrap gap-3">
        <div><span class="gp-etiqueta-mini d-block">Lote planeado</span><span class="font-monospace fw-semibold">${escaparHtml(a.lote)}</span> ${escaparHtml(a.loteNombre)}</div>
        <div class="min-w-0 flex-grow-1"><span class="gp-etiqueta-mini d-block">Labor</span><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> ${escaparHtml(a.novedadNombre)}</div>
        ${texto(a.referencia) ? `<div><span class="gp-etiqueta-mini d-block">Referencia</span><span class="gp-chip-ambito seccion font-monospace">PFA-${escaparHtml(a.referencia)}</span></div>` : ''}
        <div class="text-end"><span class="gp-etiqueta-mini d-block">Total planeado</span><span class="tx-num fw-semibold">${cantUm(a.cantidad, a.uMedida)}</span></div>
    </div>`;
    $id('vl_contador').textContent = fmt(presentesVl().length);
    $id('vl_observacion').value = '';
    $id('vl_observacion').classList.remove('is-invalid');
    $id('contador_vl').textContent = '0 / 250';
    ['error_vl_lotes', 'error_vl_celdas', 'error_vl_observacion'].forEach((campo) => errorCampo(campo, false));
    pintarTablaVl();
    bootstrap.Modal.getOrCreateInstance($id('modalVariosLotes')).show();
    cargarLotesVl();
};

const validarVl = () => {
    const a = vl.a;
    const um = a.uMedida;
    const campos = camposVl();
    const extras = vl.lotes.filter((l) => !l.planeado);
    const lista = (lote) => presentesVl().map((t) => ({ tercero: t.tercero, codigo: texto(t.codigo) || t.tercero, cantidad: valorVl(t.tercero, lote) }));
    const marcar = (lote, tercero = null) => campos.filter((c) => c.dataset.vlLote === lote && (!tercero || c.dataset.vlTercero === tercero)).forEach((c) => c.classList.add('is-invalid'));
    const lotes = !extras.length ? 'Agregue al menos un lote adicional donde se ejecutó la labor.'
        : (vl.lotes.length > MAX_LOTES ? 'Puede distribuir la actividad en máximo 10 lotes.' : '');
    let celdas = '';

    errorCampo('error_vl_lotes', lotes !== '', lotes);
    campos.forEach((c) => c.classList.toggle('is-invalid', malaCant(aNumero(c.value), um)));

    if (lotes) return lotes;

    const vacio = extras.find((l) => milesimas(sumaLoteVl(l.codigo)) <= 0);
    const exceso = vl.lotes.find((l) => excesoPalmas(lista(l.codigo), um, l.palmas, l.codigo));
    const repetido = extras.flatMap((l) => lista(l.codigo).filter((t) => t.cantidad > 0 && duplicado(t.tercero, a.novedad, a.finca, l.codigo, a.id)).map((t) => ({ l, t })))[0];

    if (campos.some((c) => c.classList.contains('is-invalid'))) {
        celdas = msgCant(um);
    } else if (milesimas(sumaLoteVl(vl.lotes[0].codigo)) <= 0) {
        celdas = `Indique la cantidad ejecutada en el lote planeado ${vl.lotes[0].codigo}.`;
        marcar(vl.lotes[0].codigo);
    } else if (vacio) {
        celdas = `El lote ${vacio.codigo} no tiene cantidades; indíquelas o quítelo.`;
        marcar(vacio.codigo);
    } else if (exceso) {
        celdas = excesoPalmas(lista(exceso.codigo), um, exceso.palmas, exceso.codigo);
        marcar(exceso.codigo);
    } else if (repetido) {
        celdas = `${repetido.t.codigo} ya tiene esta labor en ${repetido.l.codigo}`;
        marcar(repetido.l.codigo, repetido.t.tercero);
    }

    errorCampo('error_vl_celdas', celdas !== '', celdas);

    if (celdas) {
        $id('vl_tabla').querySelector('.is-invalid')?.focus();

        return celdas;
    }

    const falta = estadoMadreVl() === 'PARCIAL' && texto($id('vl_observacion').value) === '';

    errorCampo('error_vl_observacion', falta);
    $id('vl_observacion').classList.toggle('is-invalid', falta);

    if (falta) $id('vl_observacion').focus();

    return falta ? 'Indique el motivo del cierre parcial.' : '';
};

const guardarVl = async (evento) => {
    evento.preventDefault();

    if (vl.enviando || estado.guardando || !vl.a) return;

    const error = validarVl();

    if (error) {
        Toast.fire({ icon: 'warning', title: escaparHtml(error) });

        return;
    }

    const a = vl.a;
    const n = conCantidadVl().length;
    const distribucion = vl.lotes.map((l) => ({
        lote: l.codigo,
        trabajadores: presentesVl().map((t) => ({ tercero: t.tercero, cantidad: numVl(t.tercero, l.codigo) })).filter((t) => l.planeado || t.cantidad > 0)
    }));

    vl.enviando = true;
    botonVl();

    const ok = await ejecutar('cerrarVariosLotes', {
        empresa: empresaId(), fecha: fechaPlan(), linea: a.id, observacionCierre: texto($id('vl_observacion').value), distribucion: JSON.stringify(distribucion)
    }, `Actividad cerrada en ${fmt(n)} lotes`, (r) => (Array.isArray(r.ids) && r.ids.length ? r.ids.map(Number) : a.id), 'No se pudo cerrar la actividad');

    vl.enviando = false;
    botonVl();

    if (!ok) return;

    vl.cerrar = true;
    bootstrap.Modal.getOrCreateInstance($id('modalVariosLotes')).hide();
};

const aus = { tercero: '', codigo: '', nombre: '', pasos: [], i: 0 };

const visiblesPaso = (paso) => paso.candidatos.filter((c) => !c.otro || paso.ampliar);
const marcadosPaso = (paso) => visiblesPaso(paso).filter((c) => c.marcado);

const repartirPaso = (paso) => {
    const marcados = marcadosPaso(paso);

    paso.candidatos.forEach((c) => { if (!marcados.includes(c)) c.cantidad = 0; });

    if (marcados.length) repartir(paso.cantidad, marcados.length, esRac(paso.a.uMedida)).forEach((valor, i) => { marcados[i].cantidad = valor; });
};

const motivoPaso = (paso) => {
    const marcados = marcadosPaso(paso);

    if (marcados.some((c) => malaCant(c.cantidad, paso.a.uMedida))) return 'Hay cantidades inválidas';
    if (!marcados.length) return paso.cantidad === 0 && paso.a.trabajadores.length > 1 ? '' : 'Seleccione al menos un trabajador';

    return '';
};

const pasoValido = (paso) => !motivoPaso(paso)
    && milesimas(marcadosPaso(paso).reduce((suma, c) => suma + c.cantidad, 0)) === milesimas(paso.cantidad);

const actualizarPaso = () => {
    const paso = aus.pasos[aus.i];
    const ultimo = aus.i === aus.pasos.length - 1;

    pintarIndicador($id('aus_indicador'), marcadosPaso(paso).reduce((suma, c) => suma + c.cantidad, 0), paso.cantidad, paso.a.uMedida, motivoPaso(paso));
    $id('aus_siguiente').disabled = !pasoValido(paso) || !MOTIVOS_AUSENCIA.length;
    $id('aus_siguiente').innerHTML = ultimo ? '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>Confirmar ausencia' : 'Siguiente<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>';

    if (ultimo) {
        $id('aus_resumen').innerHTML = aus.pasos.map((p) => `<div><span class="font-monospace fw-semibold">${escaparHtml(p.a.lote)}</span> ${escaparHtml(p.a.novedadNombre || p.a.novedad)} → ${marcadosPaso(p).map((c) => `<span class="font-monospace">${escaparHtml(c.codigo || c.tercero)}</span> ${Number.isNaN(c.cantidad) ? '—' : cant(c.cantidad)}`).join(', ') || '<span class="text-muted">sin reparto</span>'}</div>`).join('');
    }
};

const candidatoHtml = (c, k) => `<div class="pl-candidato${c.marcado ? ' activo' : ''}">
    <input class="form-check-input" type="checkbox" id="aus_c_${k}" data-cand="${k}"${c.marcado ? ' checked' : ''}>
    <label class="form-check-label min-w-0" for="aus_c_${k}"><span class="font-monospace fw-semibold me-1">${escaparHtml(c.codigo || c.tercero)}</span><span class="gp-truncar d-inline-block align-bottom">${escaparHtml(c.nombre)}</span></label>
    <div class="d-flex align-items-center justify-content-end gap-2"><span class="gp-gramos d-none" id="g_cand_${k}"></span><input type="text" class="form-control form-control-sm tx-input-num font-monospace" data-cand-cant="${k}" value="${c.marcado ? valorInput(c.cantidad) : ''}" inputmode="${modoNum(aus.pasos[aus.i]?.a.uMedida)}" maxlength="14" autocomplete="off" aria-label="Cantidad para ${escaparHtml(c.nombre)}" data-gramos="${escaparHtml(aus.pasos[aus.i]?.a.uMedida || '-')}" aria-describedby="g_cand_${k}"${c.marcado ? '' : ' disabled'}></div>
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
            <div><span class="gp-etiqueta-mini d-block">Total</span><span class="tx-num">${cantUm(a.cantidad, a.uMedida)}</span></div>
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

const marcarMotivo = (invalido) => {
    errorCampo('error_aus_motivo', invalido);
    $id('aus_motivo').closest('.tx-select-ancho').classList.toggle('is-invalid', invalido);
};

const validarMotivo = () => {
    const falta = !$id('aus_motivo').value;

    marcarMotivo(falta);

    if (falta) $id('aus_motivo').nextElementSibling?.querySelector('.select2-selection')?.focus();

    return !falta;
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
    $('#aus_motivo').val('').trigger('change.select2');
    marcarMotivo(false);

    if (!aus.pasos.length) {
        if (!MOTIVOS_AUSENCIA.length) {
            Toast.fire({ icon: 'warning', title: 'Registre motivos en Maestros Palma › Configuración › Motivos de ausencia.' });

            return;
        }

        const { value } = await Swal.fire({
            icon: 'question',
            title: escaparHtml(`¿Marcar ausente a ${persona.codigo || tercero}?`),
            text: 'No tiene actividades pendientes en el plan.',
            input: 'select',
            inputOptions: Object.fromEntries(MOTIVOS_AUSENCIA.map((m) => [m.codigo, `${escaparHtml(m.codigo)} — ${escaparHtml(m.descripcion)}`])),
            inputPlaceholder: 'Seleccione el motivo…',
            showCancelButton: true,
            confirmButtonText: 'Sí, marcar ausente',
            cancelButtonText: 'Cancelar',
            inputValidator: (valor) => (valor ? undefined : 'Seleccione el motivo de la ausencia.')
        });

        if (!value) return;

        $('#aus_motivo').val(value).trigger('change.select2');
        enviarAusencia([]);

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
                <span class="tx-dato"><span class="gp-etiqueta-mini">Saldo</span><span class="tx-dato-valor">${Number(p.saldo) > 0 ? `${cant(p.saldo)} ${escaparHtml(p.uMedida)}` : '<span class="text-muted">Sin cantidad</span>'}</span></span>
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
        invalidarSug();
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

const duplicarPlan = async () => {
    const lista = actividades().filter((a) => !a.division);
    const origen = fechaPlan();
    const fincas = agrupar([...lista].sort((x, y) => x.finca.localeCompare(y.finca, 'es', { numeric: true })), (a) => a.finca);
    const nombre = (finca) => `${finca} — ${lista.find((a) => a.finca === finca)?.fincaNombre || ''}`;
    const totalAct = (n) => `${fmt(n)} ${n === 1 ? 'actividad' : 'actividades'}`;
    let previos = new Set();

    if (estado.guardando || !lista.length) return;

    const resumen = (finca) => {
        const items = lista.filter((a) => !finca || a.finca === finca);
        const fuera = nitsAusentes();
        const personas = new Set(items.flatMap((a) => a.trabajadores.filter((t) => !ausente(t) && !fuera.has(t.tercero)).map((t) => t.tercero))).size;

        return `Se copiarán <strong>${totalAct(items.length)}</strong> con <strong>${fmt(personas)} ${personas === 1 ? 'trabajador' : 'trabajadores'}</strong>${finca ? ` de la finca <strong>${escaparHtml(nombre(finca))}</strong>` : ''} del <strong>${escaparHtml(fechaLegible(origen))}</strong>, con sus cantidades, horario y observación.`;
    };
    const campoFinca = fincas.length > 1
        ? `<label class="form-label mb-1 small fw-semibold text-muted" for="dup_finca">Finca</label>
            <select class="form-select" id="dup_finca"><option value="">Todas las fincas (${totalAct(lista.length)})</option>${fincas.map(([finca, items]) => `<option value="${escaparHtml(finca)}">${escaparHtml(nombre(finca))} (${totalAct(items.length)})</option>`).join('')}</select>`
        : `<span class="form-label d-block mb-1 small fw-semibold text-muted">Finca</span>
            <div class="form-control-plaintext"><span class="font-monospace fw-semibold me-1">${escaparHtml(fincas[0][0])}</span>${escaparHtml(fincas[0][1][0].fincaNombre)}</div>`;

    const { value: r } = await Swal.fire({
        title: 'Duplicar plan',
        width: '38rem',
        focusConfirm: false,
        html: `<div class="text-start">
            <p class="mb-3" id="dup_resumen" aria-live="polite">${resumen('')}</p>
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <label class="form-label mb-1 small fw-semibold text-muted" for="dup_fecha">Fecha destino <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="dup_fecha" value="${sumarDias(origen, 1)}">
                    <span class="small text-muted d-block mt-1" id="dup_legible" aria-live="polite"></span>
                </div>
                <div class="col-12 col-sm-6">${campoFinca}</div>
            </div>
            <small class="text-muted d-block mt-3">Se agregan como pendientes. Si el plan destino ya existe, se suman a sus actividades; se omiten los trabajadores ausentes en el destino y las actividades repetidas.</small>
        </div>`,
        showCancelButton: true,
        confirmButtonText: 'Duplicar plan',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        didOpen: () => {
            const fecha = $id('dup_fecha');
            const legible = () => { $id('dup_legible').innerHTML = fechaRel(fecha.value); };

            fecha.addEventListener('input', legible);
            $id('dup_finca')?.addEventListener('change', () => { $id('dup_resumen').innerHTML = resumen($id('dup_finca').value); });
            legible();
            fecha.focus();
        },
        preConfirm: async () => {
            const destino = $id('dup_fecha').value;
            const finca = $id('dup_finca')?.value || '';

            if (!destino || destino === origen) {
                Swal.showValidationMessage(destino ? 'Elija una fecha distinta a la del plan actual.' : 'Seleccione la fecha destino.');

                return false;
            }

            const botones = [...document.querySelectorAll('#barra_plan button:not(:disabled)')];

            estado.guardando = true;
            botones.forEach((b) => { b.disabled = true; });

            try {
                previos = new Set(((await pedir('plan', { empresa: empresaId(), fecha: destino }).catch(() => null))?.plan?.actividades || []).map((a) => a.id));

                return { ...await pedir('duplicar', { empresa: empresaId(), fecha: destino, origen, finca }), destino, finca };
            } catch (error) {
                Swal.showValidationMessage(escaparHtml(error.message));

                return false;
            } finally {
                estado.guardando = false;
                botones.forEach((b) => { b.disabled = false; });
            }
        }
    });

    if (!r) return;

    const n = Number(r.duplicadas) || 0;
    const omitidas = r.omitidas || [];
    const deFinca = r.finca ? escaparHtml(nombre(r.finca)) : '';

    invalidarSug();
    $id('fecha').value = r.destino;
    await cargar();

    if (!estado.error && estado.plan) aplicarPlan(estado.plan, actividades().filter((a) => !previos.has(a.id)).map((a) => a.id));

    if (!omitidas.length) {
        Toast.fire({
            icon: 'success',
            title: `${r.finca ? (n === 1 ? `1 actividad de ${deFinca} duplicada` : `${fmt(n)} actividades de ${deFinca} duplicadas`) : `${fmt(n)} ${n === 1 ? 'actividad duplicada' : 'actividades duplicadas'}`} al ${escaparHtml(fechaCorta(r.destino))}`,
            timer: 5000
        });

        return;
    }

    Swal.fire({
        icon: 'warning',
        title: 'Plan duplicado con omisiones',
        html: `Se duplicaron <strong>${fmt(n)} de ${fmt(n + omitidas.length)}</strong> actividades${r.finca ? ` de la finca ${deFinca}` : ''}. Se omitieron:
            <ul class="list-group list-group-flush text-start pl-omitidas mt-2">${omitidas.map((o) => `<li class="list-group-item px-0 py-2 small"><span class="font-monospace fw-semibold">${escaparHtml(o.actividad)}</span> — <span class="text-muted">${escaparHtml(o.motivo)}</span></li>`).join('')}</ul>`,
        confirmButtonText: 'Entendido'
    });
};

const exportar = async () => {
    if (estado.exportando) return;

    if (!estado.plan || !exportable()) {
        Toast.fire({ icon: 'info', title: 'No hay actividades para exportar.' });

        return;
    }

    const boton = $id('btn_excel');
    const botones = document.querySelectorAll('#barra_plan [data-accion="excel"]');
    const html = boton.innerHTML;
    const fecha = fechaPlan();
    const cuerpo = new FormData();

    cuerpo.append('empresa', empresaId());
    cuerpo.append('fecha', fecha);
    estado.exportando = true;
    botones.forEach((b) => { b.disabled = true; });
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Exportando…';

    try {
        const respuesta = await fetch(URL_PL + 'excel', { method: 'POST', body: cuerpo }).catch(() => {
            throw new Error('Error de conexión.');
        });

        if (!respuesta.ok || (respuesta.headers.get('Content-Type') || '').includes('json')) {
            const resultado = await respuesta.json().catch(() => null);

            throw Object.assign(new Error(texto(resultado?.message) || (respuesta.status === 403 ? 'No tiene permiso para realizar esta acción. Solicítelo al administrador.' : 'No se pudo generar el archivo.')), { status: respuesta.status });
        }

        const nombre = (respuesta.headers.get('Content-Disposition') || '').match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)?.[1];
        const enlace = document.createElement('a');

        enlace.href = URL.createObjectURL(await respuesta.blob());
        enlace.download = nombre ? decodeURIComponent(nombre) : `planeacion_labores_${fecha}.xlsx`;
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(enlace.href), 1000);
    } catch (error) {
        Swal.fire({ icon: error.status === 403 ? 'warning' : 'error', title: error.status === 403 ? 'Acción no permitida' : 'No se pudo exportar', html: escaparHtml(error.message), confirmButtonText: 'Entendido' });
    }

    estado.exportando = false;
    boton.innerHTML = html;
    botones.forEach((b) => { b.disabled = !exportable(); });
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
            const fuera = padre !== '#modalActividad' || fechaActividad() === fechaPlan() ? nitsAusentes() : new Set();

            return {
                results: (datos.filas || []).filter((fila) => !fuera.has(texto(fila.nit)))
                    .map((fila) => ({ id: texto(fila.nit), text: texto(fila.razonSocial), nit: texto(fila.nit), codigo: texto(fila.codigo) }))
            };
        }
    }
});

const pla = { finca: '', filas: [], uid: 0, intento: false, abierta: null, lotes: '', pfa: null, mapa: [], turno: 0, visible: false, fijada: false };

const ETIQUETAS_PLA = { codigo: 'Código de labor', lote: 'Lote', labor: 'Labor', um: 'Unidad de medida', inicio: 'Hora inicio', fin: 'Hora fin', trab: 'Trabajadores', obs: 'Observación', pfa: 'Referencia PFA' };

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

const firmaPla = (f) => JSON.stringify(envioPla(f));
const vaciaPla = (f) => !f.id && !campoPla(f, 'labor').value && !f.trabajadores.length && !texto(campoPla(f, 'obs').value);
const tipoPla = (f) => (f.eliminada ? 'eliminada' : (!f.id ? 'nueva' : (firmaPla(f) !== f.original ? 'editada' : '')));
const suciaPla = () => pla.filas.some((f) => (f.id ? f.eliminada || firmaPla(f) !== f.original : !vaciaPla(f)));
const invalidaPla = (f) => f.trabajadores.some((t) => malaCant(t.cantidad, campoPla(f, 'um').value));
const cosechaPla = (f) => campoPla(f, 'labor').selectedOptions[0]?.dataset.clase === '2';
const palmasPla = (f) => Number(campoPla(f, 'lote').selectedOptions[0]?.dataset.palmas || 0);
const kgPla = (f) => (cosechaPla(f) && esRac(campoPla(f, 'um').value) && pla.finca && campoPla(f, 'lote').value ? pesoDe(pla.finca, campoPla(f, 'lote').value, fechaPlan(), refrescarPla) : null);

const erroresPla = (f, claves) => {
    const d = datosPla(f);
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
    if (Boolean(d.horaInicio) !== Boolean(d.horaFin) || (d.horaInicio && d.horaFin && d.horaFin <= d.horaInicio)) {
        agregar('inicio', 'Horario', d.horaInicio && d.horaFin ? 'La hora fin debe ser posterior a la de inicio.' : 'Indique la hora de inicio y la hora fin, o deje ambas vacías.');
    }
    if (!f.trabajadores.length) agregar('trab', 'Trabajadores', 'Seleccione al menos un trabajador.');
    else if (invalidaPla(f)) agregar('detalle', 'Cantidades', `Hay cantidades inválidas. ${msgCant(d.uMedida)}`);
    else if (excesoPalmas(f.trabajadores, d.uMedida, palmasPla(f), d.lote)) agregar('detalle', 'Palmas', excesoPalmas(f.trabajadores, d.uMedida, palmasPla(f), d.lote));

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
    <tr class="pl-planilla-error d-none" data-fila="${uid}"><td colspan="9"><ul class="list-unstyled mb-0 small text-danger"></ul></td></tr>
    <tr class="pl-planilla-detalle d-none" id="pla_det_${uid}" data-fila="${uid}"><td colspan="9"></td></tr>`;

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
    const f = { uid: ++pla.uid, id: d.id || null, trabajadores: (d.trabajadores || []).map((t) => ({ ...t })), referencia: texto(d.referencia), servidor: '', errores: [], dups: [], umLabor: '', total: '' };
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
    campoPla(f, 'inicio').value = texto(d.horaInicio).slice(0, 5);
    campoPla(f, 'fin').value = texto(d.horaFin).slice(0, 5);
    campoPla(f, 'obs').value = texto(d.observacion);
    f.umLabor = texto(campoPla(f, 'labor').selectedOptions[0]?.dataset.um);
    f.original = f.id ? firmaPla(f) : null;
    cargarPfaPla(f);

    return f;
};

const pintarDetallePla = (f) => {
    const um = campoPla(f, 'um').value;

    f.det.firstElementChild.innerHTML = `<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
            <span class="gp-etiqueta-mini mt-1">Cantidades por trabajador <span class="fw-normal text-muted">(opcional)</span></span>
            <div class="min-w-0">
                <div class="input-group input-group-sm pl-total-grupo">
                    <span class="input-group-text">Total</span>
                    <input type="text" class="form-control font-monospace text-end tx-num" data-total value="${escaparHtml(f.total)}" inputmode="${modoNum(um)}" placeholder="0" maxlength="14" autocomplete="off" aria-label="Total del grupo" data-gramos="${escaparHtml(um || '-')}" aria-describedby="g_total_${f.uid}">
                    <span class="input-group-text" data-total-um>${escaparHtml(um || '—')}</span>
                    <button type="button" class="btn btn-outline-success" data-pla="repartir"><i class="bi bi-distribute-horizontal me-1" aria-hidden="true"></i>Repartir</button>
                </div>
                <div class="invalid-feedback d-block d-none" data-total-error></div>
                <span class="gp-gramos d-block text-end mt-1 d-none" id="g_total_${f.uid}"></span>
                <div class="form-text mt-1">Reparte el total en partes iguales; luego puede ajustar cada trabajador.</div>
            </div>
            <div class="tx-reparto tx-reparto--inactivo pl-indicador ms-auto" data-indicador></div>
        </div>
        ${f.trabajadores.length ? `<div class="pl-planilla-reparto">${f.trabajadores.map((t, i) => {
        const nombre = escaparHtml(t.nombre || t.tercero);

        return `<div class="pl-planilla-item" data-item="${i}">
                <span class="font-monospace fw-semibold">${escaparHtml(t.codigo || t.tercero)}</span>
                <span class="gp-truncar flex-grow-1" title="${nombre}">${nombre}</span>
                ${t.reasignadoDe ? `<span class="gp-chip-fila ambar">recibe de ${escaparHtml(codigoDe(t.reasignadoDe))}</span>` : ''}
                <div class="d-flex align-items-center justify-content-end gap-2"><span class="gp-gramos d-none" id="g_cant_${f.uid}_${i}"></span><input type="text" class="form-control form-control-sm tx-input-num font-monospace${malaCant(t.cantidad, um) ? ' is-invalid' : ''}" data-cant="${i}" value="${valorCant(t.cantidad)}" placeholder="Opcional" inputmode="${modoNum(um)}" maxlength="14" autocomplete="off" aria-label="Cantidad de ${nombre}" data-gramos="${escaparHtml(um || '-')}" aria-describedby="g_cant_${f.uid}_${i}"></div>
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
    const invalida = invalidaPla(f);
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

        const bloqueado = f.eliminada || (campo === 'um' && cosechaPla(f) && esRac(control.value));

        if (control.disabled !== bloqueado) control.disabled = bloqueado;
    });

    f.tr.querySelector('[data-pla="duplicar"]').disabled = f.eliminada || estado.guardando;
    eliminar.disabled = estado.guardando;
    eliminar.className = `btn btn-sm ${f.eliminada ? 'btn-outline-secondary' : 'btn-outline-danger'}`;
    eliminar.title = f.eliminada ? 'Deshacer' : 'Eliminar fila';
    eliminar.setAttribute('aria-label', eliminar.title);
    eliminar.innerHTML = `<i class="bi ${f.eliminada ? 'bi-arrow-counterclockwise' : 'bi-trash'}" aria-hidden="true"></i>`;

    const palmas = palmasPla(f);
    const conTope = !invalida && esPal(d.uMedida) && Boolean(d.lote) && palmas > 0;
    const kg = kgPla(f);

    mostrar(boton, f.trabajadores.length > 0 && !f.eliminada);
    boton.classList.toggle('pl-mal', invalida || (conTope && milesimas(suma) > milesimas(palmas)));
    boton.innerHTML = `<i class="bi bi-people" aria-hidden="true"></i><span class="tx-chip-cont verde">${fmt(f.trabajadores.length)}</span>${invalida ? 'Cantidades inválidas'
        : (conTope ? `<span class="tx-num">${cant(suma)} / ${cant(palmas)}</span> palmas` : (suma > 0 ? `Total <span class="tx-num">${cant(suma)}</span>${textoKg(suma, kg)}` : 'Sin cantidades'))}`;
    boton.setAttribute('aria-expanded', String(pla.abierta === f.uid));
    mas.textContent = `+${ocultos}`;
    mostrar(mas, ocultos > 0);

    mostrar(f.err, errores.length > 0);
    f.err.querySelector('ul').innerHTML = errores.map((e) => `<li><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>${escaparHtml(e.mensaje)}</li>`).join('');

    mostrar(f.det, pla.abierta === f.uid);

    if (pla.abierta !== f.uid) return;

    f.det.querySelectorAll('[data-gramos]').forEach((campo) => { campo.dataset.gramos = d.uMedida || '-'; });

    const indicador = f.det.querySelector('[data-indicador]');
    const total = aNumero(f.total);
    const totalMalo = texto(f.total) !== '' && malaCant(total, d.uMedida);
    const campoTotal = f.det.querySelector('[data-total]');

    if (indicador) pintarCantidades(indicador, f.trabajadores, d.uMedida, metaDe(f.total, d.uMedida), palmas, d.lote, kg);

    if (campoTotal) {
        campoTotal.classList.toggle('is-invalid', totalMalo);
        campoTotal.setAttribute('inputmode', modoNum(d.uMedida));
        f.det.querySelector('[data-total-um]').textContent = d.uMedida || '—';
        f.det.querySelector('[data-total-error]').textContent = msgCant(d.uMedida);
        mostrar(f.det.querySelector('[data-total-error]'), totalMalo);
        f.det.querySelector('[data-pla="repartir"]').disabled = estado.guardando || !f.trabajadores.length || !(total > 0) || totalMalo;
    }

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

        const suma = sumaPla(f);

        if (d.uMedida && suma > 0) totales.set(d.uMedida, (totales.get(d.uMedida) || 0) + suma);
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
    const nueva = crearFilaPla({ ...d, novedad: '', referencia: '', trabajadores: f.trabajadores.map((t) => ({ ...t, reasignadoDe: '' })) }, f);

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

const repartirPla = (f) => {
    const boton = f?.det.querySelector('[data-pla="repartir"]');

    if (!boton || boton.disabled) return;

    const um = campoPla(f, 'um').value;

    repartir(aNumero(f.total), f.trabajadores.length, esRac(um)).forEach((valor, i) => { f.trabajadores[i].cantidad = valor; });
    f.det.querySelectorAll('[data-cant]').forEach((campo) => {
        campo.value = valorCant(f.trabajadores[Number(campo.dataset.cant)].cantidad);
        campo.classList.remove('is-invalid');
    });
    f.servidor = '';
    refrescarPla();
    f.det.querySelectorAll('.pl-planilla-item').forEach(destellar);
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
                <thead class="gp-thead"><tr><th>Lote</th><th>Labor</th><th class="text-end">Total</th><th>Trabajadores</th><th>Estado</th></tr></thead>
                <tbody>${cerradas.map((a) => `<tr>
                    <td class="font-monospace">${escaparHtml(a.lote)}</td>
                    <td><span class="font-monospace fw-semibold">${escaparHtml(a.novedad)}</span> ${escaparHtml(a.novedadNombre)}</td>
                    <td class="text-end tx-num text-nowrap">${cantUm(a.cantidad, a.uMedida)}</td>
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
        crearFilaPla({ ...a, trabajadores });
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

        if (cosechaPla(f)) {
            asegurarOpcion(um, 'RAC');
            $(um).val('RAC').trigger('change.select2');
        }

        f.umLabor = umLabor;
        cargarPfaPla(f);
    } else if (campo === 'trab') {
        const datos = $(control).select2('data').filter((opcion) => opcion.id);

        f.trabajadores = datos.map((opcion) => f.trabajadores.find((t) => t.tercero === opcion.id)
            || { tercero: opcion.id, codigo: codigoTercero(opcion), nombre: texto(opcion.text), cantidad: 0, reasignadoDe: '' });

        if (pla.abierta === f.uid) pintarDetallePla(f);
    } else if (campo === 'pfa') {
        f.referencia = control.value;
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

            f.trabajadores[Number(cantidad.dataset.cant)].cantidad = valor;
            cantidad.classList.toggle('is-invalid', malaCant(valor, campoPla(f, 'um').value));
        }

        if (evento.target.matches('[data-total]')) f.total = evento.target.value;

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
            duplicar: () => duplicarFilaPla(f),
            eliminar: () => eliminarFilaPla(f),
            repartir: () => repartirPla(f),
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

            if (control.matches('[data-total]')) repartirPla(filaDe(control));
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
    $id('aus_motivo').innerHTML = MOTIVOS_AUSENCIA.length
        ? '<option value=""></option>' + MOTIVOS_AUSENCIA.map((m) => `<option value="${escaparHtml(m.codigo)}">${escaparHtml(m.codigo)} — ${escaparHtml(m.descripcion)}</option>`).join('')
        : '<option value="">No hay motivos activos</option>';
    $id('aus_motivo').disabled = !MOTIVOS_AUSENCIA.length;
    mostrar($id('aus_motivo_vacio'), !MOTIVOS_AUSENCIA.length);
    $('#aus_motivo').select2({ width: '100%', language: IDIOMA_SELECT2, dropdownParent: $('#modalAusencia'), placeholder: MOTIVOS_AUSENCIA.length ? 'Seleccione el motivo…' : 'No hay motivos activos' });
    $('#edit_tercero').select2({
        ...selectTerceros('#modalEditarTrabajador'),
        placeholder: 'Código, identificación o nombre…',
        templateSelection: (opcion) => (opcion.id
            ? $(`<span><span class="font-monospace fw-semibold">${escaparHtml(codigoTercero(opcion) || opcion.id)}</span> — ${escaparHtml(opcion.text)}</span>`)
            : opcion.text)
    });
};

const sug = {
    cache: new Map(), filas: [], dias: 15, estado: 'todas', cargando: false, error: '', decisiones: true, aviso: '', fecha: '', turno: 0, espera: null,
    contador: 0, esperaContador: null, abierto: false, reabrir: false, origen: '', pestana: 'lista',
    his: { filas: [], cargado: false, cargando: false, error: '', turno: 0 }
};

const plural = (n, uno, varios) => `${fmt(n)} ${n === 1 ? uno : varios}`;
const claveSug = (dias) => `${empresaId()}|${fechaPlan()}|${dias}`;
const sugPor = (clave) => sug.filas.find((s) => s.clave === clave) || null;
const codigosSug = (s) => (s.trabajadores || []).map((t) => texto(t.codigo) || t.tercero);
const ultimoSug = (s) => (texto(s.fechaUltima) ? escaparHtml(fechaCorta(s.fechaUltima)) : '<span class="text-muted">—</span>');
const cicloSug = (s) => (Number(s.ciclo) > 0 ? plural(Number(s.ciclo), 'día', 'días') : '');

const urgencia = (dias) => {
    const n = Math.abs(Number(dias));

    if (Number(dias) < 0) return { clase: 'pl-e-noejecutada', icono: 'bi-exclamation-octagon', texto: `Vencida hace ${plural(n, 'día', 'días')}` };
    if (n === 0) return { clase: 'pl-e-parcial', icono: 'bi-alarm', texto: 'Vence hoy' };

    return { clase: 'pl-e-registrada', icono: 'bi-calendar-event', texto: `En ${plural(n, 'día', 'días')}` };
};

const chipUrgencia = (s) => {
    const u = urgencia(s.dias);

    return `<span class="pl-chip ${u.clase}"><i class="bi ${u.icono}" aria-hidden="true"></i>${u.texto}</span>`;
};

const ORIGENES = {
    CATALOGO: ['tx-chip-neutro', 'bi-arrow-repeat', 'Ciclo', 'Ciclo definido en el catálogo de labores'],
    HISTORIAL: ['gris', 'bi-graph-up', 'Estimado', 'Frecuencia estimada según el historial de pases'],
    PLAN: ['verde', 'bi-droplet', 'Plan de fertilización', 'Programada en un plan de fertilización']
};

const chipOrigen = (s) => {
    const [clase, icono, nombre, titulo] = ORIGENES[s.origen] || ORIGENES.HISTORIAL;

    return `<span class="gp-chip-fila ${clase}" title="${titulo}"><i class="bi ${icono} me-1" aria-hidden="true"></i>${nombre}${s.origen === 'PLAN' && texto(s.referencia) ? ` <span class="font-monospace small">PFA-${escaparHtml(s.referencia)}</span>` : ''}</span>`;
};

const laborSug = (s, truncar = false) => {
    const nombre = escaparHtml(s.labor);

    return `${texto(s.novedad) ? `<span class="font-monospace fw-semibold me-1">${escaparHtml(s.novedad)}</span>` : ''}${nombre
        ? (truncar ? `<span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span>` : nombre)
        : '<span class="text-muted">Labor por definir</span>'}`;
};

const trabajadoresSug = (s) => {
    const codigos = codigosSug(s);
    const todos = (s.trabajadores || []).map((t) => `${texto(t.codigo) || t.tercero} ${texto(t.nombre)}`.trim()).join(', ');

    if (!codigos.length) return '<span class="text-muted">Sin historial</span>';

    return `<span class="font-monospace">${escaparHtml(codigos.slice(0, 2).join(', '))}</span>${codigos.length > 2 ? ` <span class="gp-chip-fila tx-chip-neutro" title="${escaparHtml(todos)}">+${codigos.length - 2}</span>` : ''}`;
};

const referenciaSug = (s) => `<div class="d-flex flex-wrap gap-3">
    <div><span class="gp-etiqueta-mini d-block">Lote</span><span class="font-monospace fw-semibold">${escaparHtml(s.finca)} / ${escaparHtml(s.lote)}</span></div>
    <div class="min-w-0"><span class="gp-etiqueta-mini d-block">Labor</span>${laborSug(s)}</div>
    <div><span class="gp-etiqueta-mini d-block">Último pase</span>${ultimoSug(s)}</div>
    <div><span class="gp-etiqueta-mini d-block">Ciclo</span>${cicloSug(s) || '<span class="text-muted">—</span>'}</div>
    <div><span class="gp-etiqueta-mini d-block">Próxima</span>${escaparHtml(fechaCorta(s.fechaProxima))}</div>
    <div><span class="gp-etiqueta-mini d-block">Origen</span>${chipOrigen(s)}</div>
</div>`;

const botonesSug = (s, movil) => {
    const clave = escaparHtml(s.clave);
    const desc = escaparHtml(`${s.lote} ${texto(s.novedad)} ${texto(s.labor)}`.replace(/\s+/g, ' ').trim());
    const motivo = sug.decisiones ? '' : escaparHtml(sug.aviso || 'Falta ejecutar el script crear_planeacion_sugerencias.sql.');
    const titulo = motivo ? ` title="${motivo}"` : '';
    const inactivo = motivo ? ` disabled title="${motivo}"` : '';

    return `<span class="d-inline-flex${movil ? ' flex-grow-1' : ''}"${titulo}><button type="button" class="btn btn-success btn-sm flex-grow-1" data-accion="sug-aprobar" data-clave="${clave}" aria-label="Revisar y aprobar ${desc}"${inactivo}><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Revisar y aprobar</button></span>
        <span class="d-inline-flex${movil ? '' : ' ms-1'}"${titulo}><button type="button" class="btn btn-outline-danger btn-sm" data-accion="sug-rechazar" data-clave="${clave}" aria-label="Rechazar ${desc}"${inactivo || ' title="Rechazar"'}><i class="bi bi-x-circle${movil ? ' me-1' : ''}" aria-hidden="true"></i>${movil ? 'Rechazar' : ''}</button></span>`;
};

const filaSug = (s, puede) => `<tr class="pl-sug-fila ${urgencia(s.dias).clase}" data-sug="${escaparHtml(s.clave)}">
    <td class="font-monospace fw-semibold" title="${escaparHtml(s.loteNombre)}">${escaparHtml(s.lote)}</td>
    <td>${laborSug(s, true)}<span class="gp-subtexto">${escaparHtml(s.grupoNombre || s.grupo)} · ${escaparHtml(s.uMedida)}</span></td>
    <td class="text-nowrap">${ultimoSug(s)}${cicloSug(s) ? `<span class="gp-subtexto">Ciclo ${cicloSug(s)}</span>` : ''}</td>
    <td><span class="d-block text-nowrap">${escaparHtml(fechaCorta(s.fechaProxima))}</span><span class="d-inline-block mt-1">${chipOrigen(s)}</span></td>
    <td>${chipUrgencia(s)}</td>
    <td>${trabajadoresSug(s)}</td>
    ${puede ? `<td class="text-end text-nowrap">${botonesSug(s, false)}</td>` : ''}
</tr>`;

const tarjetaSug = (s, puede) => `<div class="pl-pendiente ${urgencia(s.dias).clase}" data-sug="${escaparHtml(s.clave)}">
    <div class="d-flex align-items-start gap-2">
        <div class="min-w-0 flex-grow-1"><span class="font-monospace fw-semibold d-block">${escaparHtml(s.finca)} / ${escaparHtml(s.lote)}</span>${laborSug(s)}</div>
        ${chipUrgencia(s)}
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
        <span class="tx-dato"><span class="gp-etiqueta-mini">Último pase</span><span class="tx-dato-valor">${ultimoSug(s)}</span></span>
        <span class="tx-dato"><span class="gp-etiqueta-mini">Próxima</span><span class="tx-dato-valor">${escaparHtml(fechaCorta(s.fechaProxima))}</span></span>
        ${cicloSug(s) ? `<span class="tx-dato"><span class="gp-etiqueta-mini">Ciclo</span><span class="tx-dato-valor">${cicloSug(s)}</span></span>` : ''}
        ${chipOrigen(s)}
    </div>
    <span class="gp-subtexto mt-1"><i class="bi bi-people me-1" aria-hidden="true"></i>${codigosSug(s).length ? escaparHtml(codigosSug(s).join(', ')) : 'Sin historial'}</span>
    ${puede ? `<div class="d-flex gap-2 mt-2">${botonesSug(s, true)}</div>` : ''}
</div>`;

const PH_SUG = Array(4).fill('<div class="tx-tarjeta pl-ph-tarjeta placeholder-glow"><span class="placeholder col-7 mb-3"></span><span class="placeholder col-12 mb-2"></span><span class="placeholder col-9"></span></div>').join('');

const vacioSug = (icono, titulo, mensaje = '', boton = '', clase = '') => `<div class="gp-estado-vacio text-center py-5">
    <div class="gp-medallon chico ${clase} d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi ${icono}" aria-hidden="true"></i></div>
    <h6 class="mb-1">${titulo}</h6>
    ${mensaje ? `<p class="text-muted small mb-3">${mensaje}</p>` : ''}
    ${boton}
</div>`;

const reintentarSug = (accion) => `<button type="button" class="btn btn-outline-secondary btn-sm" data-accion="${accion}"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>`;

const opcionesSug = (selector, filas, todas) => {
    const actual = selector.value;
    const pares = [...new Map(filas.map((f) => [f.finca, f.fincaNombre])).entries()].sort(([x], [y]) => x.localeCompare(y, 'es', { numeric: true }));

    selector.innerHTML = `<option value="">${todas}</option>` + pares.map(([valor, nombre]) => `<option value="${escaparHtml(valor)}">${escaparHtml(valor)} — ${escaparHtml(nombre)}</option>`).join('');
    selector.value = pares.some(([valor]) => valor === actual) ? actual : '';
};

const consultarSug = (dias) => {
    const clave = claveSug(dias);

    if (!sug.cache.has(clave)) {
        sug.cache.set(clave, pedir('sugerencias', { empresa: empresaId(), fecha: fechaPlan(), dias }).catch((error) => {
            sug.cache.delete(clave);
            throw error;
        }));
    }

    return sug.cache.get(clave);
};

const pintarContadorSug = (lista) => {
    const vencidas = (lista || []).filter((s) => Number(s.dias) < 0).length;
    const proximas = (lista || []).length - vencidas;
    const titulo = `Sugerencias de planeación (próximos 15 días): ${plural(vencidas, 'vencida', 'vencidas')}, ${plural(proximas, 'próxima', 'próximas')}`;

    document.querySelectorAll('[data-cont="sugerencias"]').forEach((chip) => {
        chip.textContent = lista ? fmt(lista.length) : '—';
        chip.classList.toggle('pl-rojo', vencidas > 0);
        chip.classList.toggle('pl-ambar', !vencidas && proximas > 0);
    });
    document.querySelectorAll('#barra_plan [data-accion="sugerencias"]').forEach((boton) => {
        boton.title = titulo;
        boton.setAttribute('aria-label', titulo);
    });
};

const contarSug = () => {
    if (!$id('panelSugerencias')) return;

    clearTimeout(sug.esperaContador);
    sug.esperaContador = setTimeout(async () => {
        const turno = ++sug.contador;
        let lista = null;

        try {
            lista = (await consultarSug(15)).sugerencias || [];
        } catch (error) {
            lista = null;
        }

        if (turno === sug.contador) pintarContadorSug(lista);
    }, 300);
};

const invalidarSug = () => {
    if (!$id('panelSugerencias')) return;

    sug.cache.clear();
    contarSug();

    if (sug.abierto) cargarSug();
};

const pintarSug = () => {
    const lista = $id('sug_lista');
    const puede = PERMISOS.registrar;

    $id('sug_subtitulo').textContent = `Según los últimos registros de labores · Referencia: ${fechaCorta(fechaPlan())}`;
    $id('sug_filtros').querySelectorAll('select, .btn-check').forEach((control) => { control.disabled = sug.cargando; });
    lista.setAttribute('aria-busy', String(sug.cargando));
    mostrar($id('sug_aviso'), !sug.cargando && !sug.error && !sug.decisiones && Boolean(sug.aviso));
    $id('sug_aviso').innerHTML = `<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>${escaparHtml(sug.aviso)}`;

    if (sug.cargando || sug.error) {
        $id('sug_resumen').textContent = sug.cargando ? 'Calculando sugerencias…' : '';
        mostrar($id('sug_limpiar'), false);
        lista.innerHTML = sug.cargando ? PH_SUG : vacioSug('bi-exclamation-triangle', 'No se pudieron cargar las sugerencias', escaparHtml(sug.error), reintentarSug('sug-reintentar'), 'gp-ppto-error');

        return;
    }

    opcionesSug($id('sug_finca'), sug.filas, 'Todas las fincas');

    const grupoActual = $id('sug_grupo').value;
    const grupos = [...new Map(sug.filas.map((s) => [s.grupo, s.grupoNombre])).entries()].sort(([x], [y]) => x.localeCompare(y, 'es', { numeric: true }));

    $id('sug_grupo').innerHTML = '<option value="">Todos los grupos</option>' + grupos.map(([valor, nombre]) => `<option value="${escaparHtml(valor)}">${escaparHtml(valor)} — ${escaparHtml(nombre)}</option>`).join('');
    $id('sug_grupo').value = grupos.some(([valor]) => valor === grupoActual) ? grupoActual : '';

    const finca = $id('sug_finca').value;
    const grupo = $id('sug_grupo').value;
    const base = sug.filas.filter((s) => (!finca || s.finca === finca) && (!grupo || s.grupo === grupo));
    const vencidas = base.filter((s) => Number(s.dias) < 0).length;
    const totalVencidas = sug.filas.filter((s) => Number(s.dias) < 0).length;
    const conteos = { total: sug.filas.length, vencidas, proximas: base.length - vencidas };
    const visibles = base.filter((s) => sug.estado === 'todas' || (sug.estado === 'vencidas') === (Number(s.dias) < 0))
        .sort((x, y) => x.finca.localeCompare(y.finca, 'es', { numeric: true }) || Number(x.dias) - Number(y.dias)
            || x.lote.localeCompare(y.lote, 'es', { numeric: true }) || texto(x.novedad).localeCompare(texto(y.novedad), 'es', { numeric: true }));

    document.querySelectorAll('[data-sug-cont]').forEach((chip) => {
        const clave = chip.dataset.sugCont;

        chip.textContent = fmt(conteos[clave]);
        chip.classList.toggle('pl-rojo', clave === 'total' ? totalVencidas > 0 : clave === 'vencidas' && vencidas > 0);
        chip.classList.toggle('pl-ambar', clave === 'total' && !totalVencidas && sug.filas.length > 0);
    });
    $id('sug_resumen').textContent = plural(visibles.length, 'sugerencia', 'sugerencias');
    mostrar($id('sug_limpiar'), Boolean(finca || grupo || sug.estado !== 'todas' || sug.dias !== 15));

    if (!sug.filas.length) {
        lista.innerHTML = vacioSug('bi-check2-all', 'Todo al día', `No hay labores vencidas ni próximas en los próximos ${plural(sug.dias, 'día', 'días')}.`);

        return;
    }

    if (!visibles.length) {
        lista.innerHTML = vacioSug('bi-funnel', 'Ninguna sugerencia coincide con los filtros', '', '<button type="button" class="btn btn-outline-secondary btn-sm" data-accion="sug-limpiar">Limpiar filtros</button>');

        return;
    }

    const porFinca = agrupar(visibles, (s) => s.finca);

    lista.innerHTML = `<div class="table-responsive d-none d-lg-block">
        <table class="table table-sm align-middle mb-0 gp-tabla pl-sug-tabla">
            <caption class="visually-hidden">Sugerencias de planeación agrupadas por finca</caption>
            <thead class="gp-thead"><tr><th>Lote</th><th>Labor</th><th>Último pase</th><th>Próxima</th><th>Estado</th><th>Trabajadores</th>${puede ? '<th class="text-end"><span class="visually-hidden">Acciones</span></th>' : ''}</tr></thead>
            <tbody>${porFinca.map(([clave, items]) => `<tr class="pl-sug-grupo"><td colspan="${puede ? 7 : 6}"><div class="d-flex justify-content-between gap-2"><span>${escaparHtml(clave)} — ${escaparHtml(items[0].fincaNombre)}</span><span>${plural(items.length, 'sugerencia', 'sugerencias')}</span></div></td></tr>${items.map((s) => filaSug(s, puede)).join('')}`).join('')}</tbody>
        </table>
    </div>
    <div class="d-lg-none">${porFinca.map(([clave, items]) => `<div class="mb-3">
        <span class="gp-grupo-titulo mb-2"><span class="font-monospace me-1">${escaparHtml(clave)}</span>${escaparHtml(items[0].fincaNombre)}</span>
        ${items.map((s) => tarjetaSug(s, puede)).join('')}
    </div>`).join('')}</div>`;
};

const cargarSug = async () => {
    const turno = ++sug.turno;
    const fecha = fechaPlan();
    const cacheada = sug.cache.has(claveSug(sug.dias));
    const promesa = consultarSug(sug.dias);

    sug.error = '';

    if (!cacheada) {
        sug.cargando = true;
        pintarSug();
    }

    try {
        const respuesta = await promesa;

        if (turno !== sug.turno) return;

        sug.filas = respuesta.sugerencias || [];
        sug.decisiones = respuesta.decisiones !== false;
        sug.aviso = texto(respuesta.message);
    } catch (error) {
        if (turno !== sug.turno) return;

        sug.error = error.message;
    }

    sug.fecha = fecha;
    sug.cargando = false;
    pintarSug();

    if (sug.dias === 15 && !sug.error) pintarContadorSug(sug.filas);
};

const retirarSug = async (clave) => {
    document.querySelectorAll('#sug_lista [data-sug]').forEach((nodo) => { if (nodo.dataset.sug === clave) nodo.classList.add('pl-ocupado', 'pl-saliendo'); });
    await new Promise((fin) => setTimeout(fin, reducido() ? 0 : 250));
    sug.filas = sug.filas.filter((s) => s.clave !== clave);
    sug.cache.clear();
    sug.cache.set(claveSug(sug.dias), Promise.resolve({ sugerencias: [...sug.filas], decisiones: sug.decisiones, message: sug.aviso }));
    sug.his.cargado = false;
    pintarSug();

    if (sug.dias === 15) pintarContadorSug(sug.filas);
    else contarSug();
};

const limpiarSug = () => {
    $id('sug_finca').value = '';
    $id('sug_grupo').value = '';
    $id('sug_e_todas').checked = true;
    sug.estado = 'todas';
    $id('sug_dias').value = '15';
    $id('sug_dias').classList.remove('is-invalid');
    clearTimeout(sug.espera);

    if (sug.dias !== 15) {
        sug.dias = 15;
        cargarSug();
    } else {
        pintarSug();
    }
};

const chipDecision = (f) => (f.estado === 'APROBADA'
    ? '<span class="pl-chip pl-e-completada"><i class="bi bi-check2-circle" aria-hidden="true"></i>Aprobada</span>'
    : '<span class="pl-chip pl-e-noejecutada"><i class="bi bi-x-circle" aria-hidden="true"></i>Rechazada</span>');

const detalleDecision = (f) => (f.estado === 'APROBADA'
    ? `Planeada para el ${texto(f.fechaPlaneacion) ? escaparHtml(fechaCorta(f.fechaPlaneacion)) : '—'}`
    : `<span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(f.motivo)}">${escaparHtml(f.motivo)}</span>`);

const pintarHistorial = () => {
    const h = sug.his;
    const lista = $id('his_lista');

    $id('his_filtros').querySelectorAll('input, select').forEach((control) => { control.disabled = h.cargando; });
    lista.setAttribute('aria-busy', String(h.cargando));

    if (h.cargando || h.error) {
        $id('his_resumen').textContent = h.cargando ? 'Cargando historial…' : '';
        lista.innerHTML = h.cargando ? PH_SUG : vacioSug('bi-exclamation-triangle', 'No se pudo cargar el historial', escaparHtml(h.error), reintentarSug('his-reintentar'), 'gp-ppto-error');

        return;
    }

    opcionesSug($id('his_finca'), h.filas, 'Todas las fincas');

    const decision = $id('his_decision').value;
    const finca = $id('his_finca').value;
    const filas = h.filas.filter((f) => (!decision || f.estado === decision) && (!finca || f.finca === finca))
        .sort((x, y) => texto(y.fecha).localeCompare(texto(x.fecha)) || Number(y.id) - Number(x.id));

    $id('his_resumen').textContent = plural(filas.length, 'decisión', 'decisiones');

    if (!filas.length) {
        lista.innerHTML = vacioSug('bi-journal', h.filas.length ? 'Ninguna decisión coincide con los filtros' : 'Aún no hay decisiones registradas');

        return;
    }

    lista.innerHTML = `<div class="table-responsive d-none d-lg-block">
        <table class="table table-sm align-middle mb-0 gp-tabla pl-sug-tabla">
            <caption class="visually-hidden">Historial de decisiones sobre sugerencias</caption>
            <thead class="gp-thead"><tr><th>Fecha decisión</th><th>Lote</th><th>Labor</th><th>Decisión</th><th>Detalle</th><th>Usuario</th></tr></thead>
            <tbody>${filas.map((f) => `<tr>
                <td class="text-nowrap">${escaparHtml(fechaLegible(f.fecha))}<span class="gp-subtexto">${escaparHtml(texto(f.fecha).slice(11, 16))}</span></td>
                <td><span class="font-monospace fw-semibold">${escaparHtml(f.lote)}</span><span class="gp-subtexto">${escaparHtml(f.fincaNombre || f.finca)}</span></td>
                <td>${laborSug(f, true)}</td>
                <td>${chipDecision(f)}</td>
                <td>${detalleDecision(f)}</td>
                <td>${escaparHtml(f.usuario)}</td>
            </tr>`).join('')}</tbody>
        </table>
    </div>
    <div class="d-lg-none">${filas.map((f) => `<div class="pl-pendiente ${f.estado === 'APROBADA' ? 'pl-e-completada' : 'pl-e-noejecutada'}">
        <div class="d-flex align-items-start gap-2">
            <div class="min-w-0 flex-grow-1"><span class="font-monospace fw-semibold d-block">${escaparHtml(f.finca)} / ${escaparHtml(f.lote)}</span>${laborSug(f)}</div>
            ${chipDecision(f)}
        </div>
        <span class="gp-subtexto mt-1">${detalleDecision(f)}</span>
        <span class="gp-subtexto">${escaparHtml(fechaLegible(f.fecha))} ${escaparHtml(texto(f.fecha).slice(11, 16))} · ${escaparHtml(f.usuario)}</span>
    </div>`).join('')}</div>`;
};

const cargarHistorial = async () => {
    const h = sug.his;
    const turno = ++h.turno;

    h.cargando = true;
    h.error = '';
    pintarHistorial();

    try {
        const respuesta = await pedir('sugerencias/historial', { empresa: empresaId(), desde: $id('his_desde').value, hasta: $id('his_hasta').value });

        if (turno !== h.turno) return;

        h.filas = respuesta.filas || [];
        h.cargado = true;
    } catch (error) {
        if (turno !== h.turno) return;

        h.error = error.message;
    }

    h.cargando = false;
    pintarHistorial();
};

const pestanaSug = (pestana) => {
    sug.pestana = pestana;
    document.querySelectorAll('[data-sug-tab]').forEach((boton) => {
        boton.classList.toggle('active', boton.dataset.sugTab === pestana);
        boton.setAttribute('aria-selected', String(boton.dataset.sugTab === pestana));
    });
    mostrar($id('sug_panel_lista'), pestana === 'lista');
    mostrar($id('sug_panel_historial'), pestana === 'historial');

    if (pestana === 'historial' && !sug.his.cargado && !sug.his.cargando) cargarHistorial();
};

const abrirPanelSug = () => {
    bootstrap.Offcanvas.getOrCreateInstance($id('panelSugerencias')).show();
    cargarSug();
    pestanaSug(sug.pestana);
};

const verActividadSug = (id) => {
    bootstrap.Offcanvas.getOrCreateInstance($id('panelSugerencias')).hide();
    $id(`act_${id}`)?.scrollIntoView({ behavior: reducido() ? 'auto' : 'smooth', block: 'center' });
};

const abrirSugerencia = async (clave) => {
    const s = sugPor(clave);
    const panel = $id('panelSugerencias');

    if (!s || !PERMISOS.registrar || estado.guardando) return;

    sug.origen = clave;
    sug.reabrir = panel.classList.contains('show');

    if (sug.reabrir) {
        await new Promise((fin) => {
            panel.addEventListener('hidden.bs.offcanvas', fin, { once: true });
            bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
        });
    }

    abrirActividad({
        finca: s.finca,
        lote: s.lote,
        novedad: texto(s.novedad),
        uMedida: texto(s.uMedida),
        referencia: texto(s.referencia),
        horaInicio: '',
        horaFin: '',
        observacion: '',
        trabajadores: (s.trabajadores || []).map((t) => ({ ...t, cantidad: 0 }))
    }, false, s);
};

const aprobarSug = async () => {
    if (estado.guardando) return;

    const s = form.sug;
    const fecha = $id('act_fecha').value;
    const vista = fechaPlan();

    estado.guardando = true;
    botonActividad();

    try {
        const respuesta = await pedir('sugerencias/aprobar', { empresa: empresaId(), clave: s.clave, fecha, actividad: JSON.stringify(datosActividad()) });
        const id = Number(respuesta.linea) || null;

        bootstrap.Modal.getOrCreateInstance($id('modalActividad')).hide();
        retirarSug(s.clave);

        if (fecha === vista) {
            aplicarPlan(respuesta.plan, id);
            Toast.fire({ icon: 'success', title: `Actividad planeada en ${escaparHtml(s.lote)}`, showConfirmButton: Boolean(id), confirmButtonText: 'Ver', timer: 5000 })
                .then((r) => { if (r.isConfirmed) verActividadSug(id); });
        } else {
            Toast.fire({ icon: 'success', title: `Actividad planeada para el ${escaparHtml(fechaCorta(fecha))}`, showConfirmButton: true, confirmButtonText: 'Ir a la fecha', timer: 5000 })
                .then(async (r) => {
                    if (!r.isConfirmed) return;

                    bootstrap.Offcanvas.getOrCreateInstance($id('panelSugerencias')).hide();
                    $id('fecha').value = fecha;
                    await cargar();

                    if (estado.plan && id) aplicarPlan(estado.plan, id);
                });
        }
    } catch (error) {
        errorSwal(error, 'No se pudo aprobar la sugerencia').then(() => { if (/recargue el plan/i.test(error.message)) cargar(true); });
    } finally {
        estado.guardando = false;
        botonActividad();
    }
};

const rechazarSug = async (clave) => {
    const s = sugPor(clave);

    if (!s || !PERMISOS.registrar) return;

    const { isConfirmed } = await Swal.fire({
        target: $id('panelSugerencias'),
        title: 'Rechazar sugerencia',
        html: `<strong>${escaparHtml(s.lote)}</strong> · ${escaparHtml(`${texto(s.novedad)} ${texto(s.labor)}`.trim())}<br><small class="text-muted">No se volverá a sugerir hasta el próximo ciclo.</small>`,
        input: 'textarea',
        inputLabel: 'Motivo del rechazo',
        inputPlaceholder: 'Ej. Lote en renovación, labor ejecutada por contratista',
        inputAttributes: { maxlength: 250, 'aria-label': 'Motivo del rechazo' },
        showCancelButton: true,
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d6293e',
        showLoaderOnConfirm: true,
        keydownListenerCapture: true,
        allowOutsideClick: () => !Swal.isLoading(),
        didOpen: () => {
            Swal.getContainer().addEventListener('keydown', (evento) => { if (evento.key === 'Escape') evento.stopPropagation(); });
            Swal.getInput()?.focus();
        },
        inputValidator: (valor) => {
            const largo = texto(valor).length;

            if (largo < 5) return 'Escriba un motivo de al menos 5 caracteres.';

            return largo > 250 ? 'Máximo 250 caracteres.' : undefined;
        },
        preConfirm: (motivo) => pedir('sugerencias/rechazar', { empresa: empresaId(), clave, motivo: texto(motivo), fecha: fechaPlan() }).catch((error) => {
            Swal.showValidationMessage(escaparHtml(error.message));

            return false;
        })
    });

    if (!isConfirmed) return;

    Toast.fire({ icon: 'success', title: 'Sugerencia rechazada' });
    retirarSug(clave);
};

const iniciarSug = () => {
    const panel = $id('panelSugerencias');
    const hoy = aIso(new Date());

    if (!panel) return;

    $id('his_hasta').value = hoy;
    $id('his_desde').value = sumarDias(hoy, -30);
    panel.addEventListener('show.bs.offcanvas', () => { sug.abierto = true; });
    panel.addEventListener('hide.bs.offcanvas', () => { sug.abierto = false; });
    $id('sug_dias').addEventListener('input', () => {
        const valor = Number($id('sug_dias').value);
        const valido = Number.isInteger(valor) && valor >= 1 && valor <= 90;

        $id('sug_dias').classList.toggle('is-invalid', !valido);
        clearTimeout(sug.espera);

        if (!valido || valor === sug.dias) return;

        sug.espera = setTimeout(() => {
            sug.dias = valor;
            cargarSug();
        }, 400);
    });
    ['sug_finca', 'sug_grupo'].forEach((id) => $id(id).addEventListener('change', pintarSug));
    document.querySelectorAll('[name="sug_estado"]').forEach((radio) => radio.addEventListener('change', () => {
        sug.estado = radio.value;
        pintarSug();
    }));
    document.querySelectorAll('[data-sug-tab]').forEach((boton) => boton.addEventListener('click', () => pestanaSug(boton.dataset.sugTab)));
    ['his_decision', 'his_finca'].forEach((id) => $id(id).addEventListener('change', pintarHistorial));
    ['his_desde', 'his_hasta'].forEach((id) => $id(id).addEventListener('change', () => {
        if ($id('his_desde').value && $id('his_hasta').value) cargarHistorial();
    }));
    $id('modalActividad').addEventListener('hidden.bs.modal', () => {
        if (!form.sug) return;

        form.sug = null;

        if (!sug.reabrir) return;

        sug.reabrir = false;
        panel.addEventListener('shown.bs.offcanvas', () => {
            [...panel.querySelectorAll('[data-accion="sug-aprobar"]')].find((boton) => boton.dataset.clave === sug.origen && boton.offsetParent)?.focus();
        }, { once: true });
        bootstrap.Offcanvas.getOrCreateInstance(panel).show();
    });
    $id('act_fecha').addEventListener('change', () => {
        errorCampo('error_act_fecha', false);
        $id('act_fecha').classList.remove('is-invalid');
        fechaDestino();

        if (grupoFormulario() === '02') cargarPfa($id('act_pfa').value || $id('act_pfa').dataset.previo || '');

        pintarReparto();
    });
};

const ACCIONES = {
    sugerencias: abrirPanelSug,
    'sug-aprobar': (d) => abrirSugerencia(d.clave),
    'sug-rechazar': (d) => rechazarSug(d.clave),
    'sug-limpiar': limpiarSug,
    'sug-reintentar': () => cargarSug(),
    'his-reintentar': () => cargarHistorial(),
    agregar: () => abrirActividad(),
    planilla: (d) => abrirPlanilla(d.finca || ''),
    pendientes: () => bootstrap.Offcanvas.getOrCreateInstance($id('panelPendientes')).show(),
    ausentes: elegirAusente,
    excel: exportar,
    duplicar_plan: duplicarPlan,
    'ver-todas': () => filtrar(null),
    reintentar: () => cargar(),
    editar: (d) => abrirActividad(actividad(d.act)),
    duplicar: (d) => abrirActividad(actividad(d.act), true),
    eliminar: (d) => eliminarActividad(d.act),
    reabrir: async (d) => {
        const a = actividad(d.act);
        const b = a ? bloque(a) : [];
        const hijas = b.slice(1).map((x) => x.lote);
        const ok = hijas.length
            ? await confirmar('¿Reabrir la actividad dividida?', `${b[0].lote} vuelve a Pendiente y se ${hijas.length === 1 ? `eliminará la actividad creada en ${hijas[0]}` : `eliminarán las ${hijas.length} actividades creadas en ${listaY(hijas)}`}.`, 'Sí, reabrir', true)
            : await confirmar('¿Reabrir la actividad?', 'La actividad vuelve a Pendiente.', 'Sí, reabrir');

        if (ok) ejecutar('reabrirActividad', { linea: d.act }, 'Actividad reabierta', b[0]?.id || Number(d.act));
    },
    'varios-lotes': (d) => abrirVariosLotes(d.act || cierre.id),
    'ver-division': (d) => {
        const a = actividad(d.act);

        if (a) aplicarPlan(estado.plan, bloque(a).map((x) => x.id));
    },
    completar: (d) => ejecutar('cerrarActividad', { linea: d.act, estado: 'COMPLETADA', cantidadEjecutada: '', observacionCierre: '' }, 'Actividad completada', Number(d.act), 'No se pudo cerrar la actividad'),
    parcial: (d) => abrirCierre(d.act, 'PARCIAL'),
    noejecutada: (d) => abrirCierre(d.act, 'NO_EJECUTADA'),
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
    iniciarSug();

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
        pintarReparto();
    });
    $id('act_total').addEventListener('input', indicadorActividad);
    $id('act_total').addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter') return;

        evento.preventDefault();
        repartirActividad();
    });
    $id('act_repartir').addEventListener('click', repartirActividad);
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
    $id('act_observacion').addEventListener('input', contarObservacion);
    $id('act_filas').addEventListener('input', (evento) => {
        const campo = evento.target.closest('[data-reparto]');

        if (!campo) return;

        const valor = aNumero(campo.value);

        form.trabajadores[Number(campo.dataset.reparto)].cantidad = valor;
        campo.classList.toggle('is-invalid', malaCant(valor, umFormulario()));
        refrescarKg();
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

    $id('formulario_varios_lotes').addEventListener('submit', guardarVl);
    $id('vl_tabla').addEventListener('input', (evento) => {
        const campo = evento.target.closest('[data-vl-lote]');

        if (!campo) return;

        vl.raw.set(`${campo.dataset.vlTercero}|${campo.dataset.vlLote}`, campo.value);
        vl.sucio = true;
        campo.classList.toggle('is-invalid', malaCant(aNumero(campo.value), vl.a.uMedida));
        errorCampo('error_vl_celdas', false);
        calcularVl();
    });
    $id('vl_tabla').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-vl-quitar]');

        if (boton) quitarLoteVl(boton.dataset.vlQuitar);
    });
    $('#vl_agregar_lote').on('change', () => {
        const codigo = $id('vl_agregar_lote').value;

        if (codigo) setTimeout(() => agregarLoteVl(codigo), 0);
    });
    $id('vl_observacion').addEventListener('input', () => {
        vl.sucio = true;
        $id('contador_vl').textContent = `${$id('vl_observacion').value.length} / 250`;
        errorCampo('error_vl_observacion', false);
        $id('vl_observacion').classList.remove('is-invalid');
    });
    $id('modalVariosLotes').addEventListener('shown.bs.modal', () => camposVl()[0]?.focus());
    $id('modalVariosLotes').addEventListener('hide.bs.modal', async (evento) => {
        if (vl.cerrar || (!vl.sucio && !vl.enviando)) return;

        evento.preventDefault();

        if (vl.enviando || !await descartarPla('¿Descartar la distribución por lotes?')) return;

        vl.cerrar = true;
        bootstrap.Modal.getOrCreateInstance($id('modalVariosLotes')).hide();
    });
    $id('modalVariosLotes').addEventListener('hidden.bs.modal', () => {
        vl.cerrar = false;
        vl.turno++;
    });

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
        campo.classList.toggle('is-invalid', malaCant(valor, paso.a.uMedida));
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
    $('#aus_motivo').on('change', () => marcarMotivo(false));
    $id('aus_siguiente').addEventListener('click', () => {
        if (!pasoValido(aus.pasos[aus.i]) || ((aus.i === 0 || aus.i === aus.pasos.length - 1) && !validarMotivo())) return;

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
