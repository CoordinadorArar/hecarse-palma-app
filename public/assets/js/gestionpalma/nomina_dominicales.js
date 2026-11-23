const URL_DOMINICALES = BASE_URL + 'gestion-palma/nomina/dominicales/';
const DOM = { datos: null, clave: '', turno: 0, ocupado: false };
const DOM_NF = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 });
const DOM_DIAS = [['L', 'Lunes'], ['M', 'Martes'], ['X', 'Miércoles'], ['J', 'Jueves'], ['V', 'Viernes'], ['S', 'Sábado']];
const DOM_ESTADOS = {
    con_derecho: [0, 'gp-badge-abierto', 'bi-check-circle-fill', 'Con derecho'],
    sin_derecho: [1, 'gp-badge-cerrado', 'bi-x-circle', 'Sin derecho'],
    manual: [2, 'inf-badge-facturado', 'bi-pencil-square', 'Registrado manualmente'],
    sin_configuracion: [3, 'gp-badge-cierre', 'bi-exclamation-triangle', 'Sin labor dominical configurada']
};

const esc = (valor) => String(valor ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const moneda = (valor) => DOM_NF.format(Math.round(Number(valor) || 0));
const numero = (valor) => Number(valor || 0).toLocaleString('es-CO');
const porcentaje = (valor) => `${Number(valor || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %`;
const fecha = (iso, corta = false) => {
    const p = String(iso ?? '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/);

    return p ? `${p[3]}/${p[2]}${corta ? '' : `/${p[1]}`}${p[4] && !corta ? ` ${p[4]}` : ''}` : esc(iso);
};
const sumarDias = (iso, dias) => {
    const d = new Date(`${iso}T00:00:00`);

    d.setDate(d.getDate() + dias);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const esDomingo = (iso) => /^\d{4}-\d{2}-\d{2}$/.test(iso) && new Date(`${iso}T00:00:00`).getDay() === 0;
const filtros = () => ({ empresa: $('#filtro_empresa').val(), domingo: $('#filtro_domingo').val() });
const claveActual = () => `${filtros().empresa}|${filtros().domingo}`;
const nombreEmpresa = () => $('#filtro_empresa option:selected').text().trim();

const pedir = async (accion, datos) => {
    const cuerpo = new FormData();
    let respuesta;
    let resultado = null;

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    try {
        respuesta = await fetch(URL_DOMINICALES + accion, { method: 'POST', body: cuerpo });
    } catch (e) {
        throw new Error('No fue posible conectar con el servidor. Intente de nuevo.');
    }

    try {
        resultado = await respuesta.json();
    } catch (e) {
        resultado = null;
    }

    if (!respuesta.ok || !resultado || resultado.success !== true) {
        throw new Error(resultado?.message || (respuesta.status === 401 ? 'La sesión expiró. Vuelva a iniciar sesión.' : respuesta.status === 403 ? 'No tiene permiso para generar dominicales.' : 'No se pudo completar la operación.'));
    }

    return resultado;
};

const estadoHtml = (medallon, titulo, texto = '', extra = '') => `
    <div class="gp-estado-vacio text-center py-5">
        ${medallon}
        <h6 class="mb-${texto || extra ? '1' : '0'}">${titulo}</h6>
        ${texto ? `<p class="text-muted mb-${extra ? '3' : '0'}">${texto}</p>` : ''}
        ${extra}
    </div>`;
const medallon = (icono, clase = '') => `<div class="gp-medallon ${clase} d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi ${icono}" aria-hidden="true"></i></div>`;

const mostrarEstado = (html) => {
    $('#dom_estado_cuerpo').html(html);
    $('#dom_estado').removeClass('d-none');
    $('#dom_resultados').addClass('d-none');
};

const semanaTexto = () => {
    const { domingo } = filtros();
    const valido = esDomingo(domingo);

    $('#filtro_domingo').toggleClass('is-invalid', !valido);
    $('#semana_texto').html(valido ? `<i class="bi bi-calendar-range me-1" aria-hidden="true"></i>Semana: lun ${fecha(sumarDias(domingo, -6))} – sáb ${fecha(sumarDias(domingo, -1))}`
        : '<span class="text-danger"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Seleccione un domingo.</span>');

    return valido;
};

const motivoBloqueo = () => {
    const d = DOM.datos;

    return DOM.ocupado ? 'Espere a que termine el cálculo.'
        : !d ? 'Pulse Calcular para ver los trabajadores con derecho.'
        : DOM.clave !== claveActual() ? 'Cambió la empresa o el domingo. Pulse Calcular para actualizar.'
        : d.bloqueo ? d.bloqueo
        : !Number(d.resumen?.conDerecho) && !d.transaccion ? 'No hay trabajadores con derecho a dominical.' : '';
};

const actualizarBotones = () => {
    const motivo = motivoBloqueo();
    const regenerar = Boolean(DOM.datos?.transaccion) && DOM.clave === claveActual();

    $('#btn_generar').prop('disabled', Boolean(motivo))
        .html(regenerar ? '<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Regenerar dominicales' : '<i class="bi bi-lightning-charge me-1" aria-hidden="true"></i>Generar dominicales');
    $('#envoltura_generar').attr('title', motivo);
    $('#btn_calcular').prop('disabled', DOM.ocupado || !esDomingo(filtros().domingo));
};

const revisarCambio = () => {
    const cambio = Boolean(DOM.datos) && DOM.clave !== claveActual();

    $('#nota_cambio').toggleClass('d-none', !cambio).toggleClass('d-flex', cambio);
    $('#dom_resultados, #aviso_generado').toggleClass('inf-desactualizado', cambio);
    semanaTexto();
    actualizarBotones();
};

const kpi = (icono, etiqueta, valor, subtexto) => `
    <div class="col">
        <div class="inf-kpi">
            <div class="gp-icono d-flex align-items-center justify-content-center rounded-3"><i class="bi ${icono}" aria-hidden="true"></i></div>
            <div class="min-w-0">
                <span class="gp-etiqueta-mini text-truncate">${etiqueta}</span>
                <div class="inf-kpi-valor" title="${esc(valor)}">${valor}</div>
                <span class="gp-subtexto text-truncate" title="${esc(subtexto)}">${subtexto}</span>
            </div>
        </div>
    </div>`;

const pintarAviso = () => {
    const d = DOM.datos;
    const t = d.transaccion;

    if (!t && !d.bloqueo) {
        $('#aviso_generado').empty();
        return;
    }

    const modificado = t && t.fechaModificacion ? ` Última regeneración el ${fecha(t.fechaModificacion)} por ${esc(t.usuarioModificacion)}.` : '';
    const texto = t
        ? `Ya se generaron los dominicales de este domingo en la TLA <b>${esc(t.numero)}</b> el ${fecha(t.fechaRegistro)} por ${esc(t.usuarioRegistro)}.${modificado} Al regenerar se reemplazan sus líneas.`
        : '';
    const bloqueo = d.bloqueo ? `<span class="d-block${t ? ' mt-1 fw-semibold' : ''}">${esc(d.bloqueo)}</span>` : '';

    $('#aviso_generado').html(`
        <div class="gp-nota-alerta d-flex align-items-start gap-2 mb-3">
            <i class="bi ${t ? 'bi-info-circle' : 'bi-exclamation-triangle'}" aria-hidden="true"></i>
            <div>${texto ? `<span class="d-block">${texto}</span>` : ''}${bloqueo}</div>
        </div>`);
};

const chipsDias = (w) => {
    const desde = DOM.datos.semana.desde;
    const dias = new Set(w.dias || []);

    return DOM_DIAS.map(([letra, nombre], i) => {
        const dia = sumarDias(desde, i);
        const activo = dias.has(dia);

        return `<span class="gp-chip-rol gp-chip-dia${activo ? ' activo' : ''}" title="${nombre} ${fecha(dia, true)}: ${activo ? 'con labor' : 'sin labor'}">${letra}</span>`;
    }).join('');
};

const chipsAusencias = (w) => {
    if (!(w.ausencias || []).length) return '<span class="text-muted">—</span>';

    return `<div class="inf-etiquetas">${w.ausencias.map((a) => {
        const remunerada = Number(a.remunerado) === 1;

        return `<span class="gp-chip-fila ${remunerada ? 'verde' : 'gp-badge-cerrado'}" title="${esc(a.motivo || a.codigo)} · ${remunerada ? 'Remunerada' : 'No remunerada'}">${esc(a.codigo || '—')} ${fecha(a.fecha, true)}</span>`;
    }).join('')}</div>`;
};

const detalleEstado = (w) => {
    if (w.estado === 'sin_derecho') {
        const no = (w.ausencias || []).filter((a) => Number(a.remunerado) !== 1);

        return no.length ? `${no.map((a) => `${esc(a.codigo || '—')} ${fecha(a.fecha, true)}`).join(', ')} · no remunerada` : esc(String(w.estadoTexto || '').replace(/^Sin derecho:\s*/i, ''));
    }

    if (w.estado === 'manual') return esc((w.manuales || []).join(', '));

    if (w.estado === 'sin_configuracion') return esc(String(w.estadoTexto || '').split(':').slice(1).join(':').trim());

    return '';
};

const insigniaEstado = (w) => {
    const [, clase, icono, texto] = DOM_ESTADOS[w.estado] || [9, 'gp-badge-doc', 'bi-question-circle', w.estadoTexto];
    const detalle = detalleEstado(w);

    return `<span class="badge ${clase} rounded-pill px-3 py-2"><i class="bi ${icono} me-1" aria-hidden="true"></i>${esc(texto)}</span>${detalle ? `<span class="gp-subtexto d-block mt-1">${detalle}</span>` : ''}`;
};

const rutaAmbito = (r) => `<span class="gp-ruta-ambito">${[r.finca, r.seccion, r.lote].filter((p) => String(p ?? '').trim() !== '')
    .map((p) => `<span class="font-monospace">${esc(p)}</span>`).join('<span class="gp-ruta-sep">›</span>') || '—'}</span>`;

const detalleReparto = (w) => `
    <div class="gp-reparto px-4 py-2 bg-light">
        <table class="table table-sm small mb-0 gp-tabla">
            <thead>
                <tr class="text-muted">
                    <th>Labor dominical</th>
                    <th>Finca › Sección › Lote</th>
                    <th class="text-end">%</th>
                    <th class="text-end">Valor</th>
                </tr>
            </thead>
            <tbody>
                ${(w.reparto || []).map((r) => `
                <tr>
                    <td><span class="font-monospace me-1">${esc(r.novedad)}</span>${esc(r.novedadNombre)}</td>
                    <td>${rutaAmbito(r)}</td>
                    <td class="text-end inf-num">${porcentaje(r.porcentaje)}</td>
                    <td class="text-end inf-num">${moneda(r.valor)}</td>
                </tr>`).join('')}
                <tr class="fw-semibold">
                    <td colspan="2">Total</td>
                    <td class="text-end inf-num">${porcentaje(100)}</td>
                    <td class="text-end inf-num">${moneda(w.valor)}</td>
                </tr>
            </tbody>
        </table>
    </div>`;

const pintarTabla = (trabajadores) => {
    if ($.fn.DataTable.isDataTable('#tabla_dominicales')) $('#tabla_dominicales').DataTable().clear().destroy();

    $('#tabla_dominicales').DataTable({
        data: trabajadores,
        pageLength: 25,
        order: [[5, 'asc'], [2, 'asc']],
        dom: "<'table-responsive't><'tx-dt-barra tx-dt-pie'ip>",
        columns: [
            {
                data: null, orderable: false, searchable: false, className: 'text-center',
                render: (v, tipo, w) => tipo === 'display' && w.estado === 'con_derecho' && (w.reparto || []).length
                    ? '<button type="button" class="btn btn-sm btn-link p-0 gp-expandir" aria-expanded="false" aria-label="Ver reparto" title="Ver reparto"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>' : ''
            },
            { data: 'tercero', render: (v, tipo) => tipo === 'display' ? `<span class="font-monospace">${esc(v)}</span>` : v },
            {
                data: 'nombre', render: (v, tipo, w) => tipo === 'display'
                    ? `<span class="fw-semibold">${esc(v)}</span>${w.codigo ? `<span class="gp-subtexto font-monospace">${esc(w.codigo)}</span>` : ''}` : `${v} ${w.codigo || ''}`
            },
            { data: 'diasConLabor', className: 'text-center d-none d-md-table-cell', searchable: false, render: (v, tipo, w) => tipo === 'display' ? `<span class="text-nowrap">${chipsDias(w)}</span>` : Number(v) || 0 },
            { data: 'ausencias', searchable: false, orderable: false, render: (v, tipo, w) => tipo === 'display' ? chipsAusencias(w) : '' },
            {
                data: 'estado', className: 'text-center', render: (v, tipo, w) => tipo === 'display' ? insigniaEstado(w)
                    : tipo === 'sort' || tipo === 'type' ? (DOM_ESTADOS[v] || [9])[0] : w.estadoTexto
            },
            {
                data: 'valor', className: 'text-end inf-num', searchable: false, render: (v, tipo, w) => {
                    if (tipo !== 'display') return Number(v) || 0;

                    return w.estado === 'con_derecho' ? moneda(v) : w.estado === 'sin_derecho' ? `<span class="text-muted">${moneda(0)}</span>` : '<span class="text-muted">—</span>';
                }
            }
        ],
        language: {
            info: 'Mostrando _START_ a _END_ de _TOTAL_ trabajadores',
            infoEmpty: 'Sin trabajadores para mostrar',
            infoFiltered: '(filtrado de _MAX_ trabajadores)',
            zeroRecords: 'Ningún trabajador coincide con la búsqueda'
        }
    });

    $('#tabla_dominicales').DataTable().search($('#dom_buscar').val() || '').draw();
};

const pintar = () => {
    const d = DOM.datos;
    const r = d.resumen || {};
    const trabajadores = d.trabajadores || [];

    pintarAviso();

    if (!trabajadores.length) {
        mostrarEstado(estadoHtml(medallon('bi-person-dash'), 'No hay trabajadores con labores en la semana', `Semana del ${fecha(d.semana?.desde)} al ${fecha(d.semana?.hasta)}.`));
        return;
    }

    const sinConf = Number(r.sinConfiguracion) || 0;

    $('#dom_kpis').html([
        kpi('bi-person-check', 'Con derecho', numero(r.conDerecho), 'Se les genera dominical'),
        kpi('bi-person-x', 'Sin derecho', numero((Number(r.sinDerecho) || 0) + sinConf), sinConf ? `No remunerada · ${numero(sinConf)} sin configurar` : 'Ausencia no remunerada'),
        kpi('bi-pencil-square', 'Registrados manualmente', numero(r.manuales), 'Excluidos del cálculo'),
        kpi('bi-cash-coin', 'Valor total', moneda(r.valorTotal), '1 jornal por trabajador')
    ].join(''));

    $('#dom_contador').html(`<i class="bi bi-people me-1" aria-hidden="true"></i><strong>${numero(trabajadores.length)}</strong> ${trabajadores.length === 1 ? 'trabajador' : 'trabajadores'}`);
    $('#dom_total').text(moneda(r.valorTotal));
    $('#dom_estado').addClass('d-none');
    $('#dom_resultados').removeClass('d-none');
    pintarTabla(trabajadores);
};

const ocupado = (estado) => {
    DOM.ocupado = estado;
    $('#btn_calcular').html(estado ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Calculando…' : '<i class="bi bi-calculator me-1" aria-hidden="true"></i>Calcular');
    actualizarBotones();
};

async function calcular() {
    if (!semanaTexto()) {
        $('#filtro_domingo').trigger('focus');
        return;
    }

    const datos = filtros();
    const turno = ++DOM.turno;

    if (DOM.datos && (DOM.datos.trabajadores || []).length) {
        $('#dom_resultados, #aviso_generado').addClass('inf-desactualizado');
    } else {
        mostrarEstado('<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Calculando dominicales…</div>');
    }

    ocupado(true);

    try {
        const resultado = await pedir('calcular', datos);

        if (turno !== DOM.turno) return;

        DOM.datos = resultado;
        DOM.clave = `${datos.empresa}|${datos.domingo}`;
        pintar();
    } catch (error) {
        if (turno !== DOM.turno) return;

        DOM.datos = null;
        DOM.clave = '';
        $('#aviso_generado').empty();
        mostrarEstado(estadoHtml(medallon('bi-exclamation-octagon', 'inf-medallon-error'), 'No fue posible calcular los dominicales', esc(error.message),
            '<button type="button" class="btn btn-outline-primary" id="btn_reintentar"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Reintentar</button>'));
    } finally {
        if (turno === DOM.turno) {
            ocupado(false);
            revisarCambio();
        }
    }
}

const dato = (etiqueta, valor) => `<div><span class="gp-etiqueta-mini">${etiqueta}</span><div class="tx-ficha-valor">${valor}</div></div>`;

async function generar() {
    if (motivoBloqueo()) return;

    const d = DOM.datos;
    const t = d.transaccion;
    const r = d.resumen || {};
    const { empresa, domingo } = filtros();

    const confirmacion = await Swal.fire({
        icon: 'question',
        width: 560,
        title: t ? '¿Regenerar dominicales?' : '¿Generar dominicales?',
        html: `
            <div class="gp-referencia text-start mb-3">
                <div class="tx-ficha">
                    ${dato('Empresa', esc(nombreEmpresa()))}
                    ${dato('Domingo', fecha(domingo))}
                    ${dato('Semana', `${fecha(d.semana.desde)} – ${fecha(d.semana.hasta)}`)}
                    ${dato('Trabajadores', numero(r.conDerecho))}
                    ${dato('Valor total', moneda(r.valorTotal))}
                </div>
            </div>
            ${t && !Number(r.conDerecho)
                ? `<div class="gp-nota-alerta text-start">No hay trabajadores con derecho: se anulará la TLA <b>${esc(t.numero)}</b>. Los dominicales registrados manualmente no se modifican.</div>`
                : t
                ? `<div class="gp-nota-alerta text-start">Se reemplazarán las líneas de la TLA <b>${esc(t.numero)}</b>. Los dominicales registrados manualmente no se modifican.</div>`
                : `<div class="gp-nota text-start">Se creará una TLA con fecha ${fecha(domingo)}, repartida por labor, finca, sección y lote.</div>`}`,
        showCancelButton: true,
        focusCancel: true,
        confirmButtonText: t ? 'Sí, regenerar' : 'Sí, generar',
        confirmButtonColor: '#1dab4d',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) return;

    Swal.fire({ title: 'Generando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resultado = await pedir('generar', { empresa, domingo });

        const registrados = Number(resultado.trabajadores) > 0 && resultado.numero;

        await Swal.fire({
            icon: 'success',
            title: resultado.accion === 'anulada' ? 'TLA de dominicales anulada' : registrados ? 'Dominicales generados' : 'Dominicales actualizados',
            html: registrados
                ? `Se registraron ${numero(resultado.trabajadores)} trabajadores en la TLA <b>${esc(resultado.numero)}</b> por ${moneda(resultado.valorTotal)}.`
                : esc(resultado.message || 'La operación se completó correctamente.'),
            confirmButtonColor: '#1dab4d'
        });
    } catch (error) {
        await Swal.fire({ icon: 'error', title: 'No se generaron los dominicales', text: error.message, confirmButtonColor: '#1dab4d' });
    }

    calcular();
}

$(function () {
    $('#form_dominicales').on('submit', (e) => {
        e.preventDefault();
        calcular();
    });
    $('#filtro_empresa, #filtro_domingo').on('change input', revisarCambio);
    $('#btn_generar').on('click', generar);
    $('#dom_estado').on('click', '#btn_reintentar', calcular);
    $('#dom_buscar').on('input', function () {
        if ($.fn.DataTable.isDataTable('#tabla_dominicales')) $('#tabla_dominicales').DataTable().search(this.value).draw();
    });
    $('#tabla_dominicales tbody').on('click', '.gp-expandir', function () {
        const fila = $('#tabla_dominicales').DataTable().row($(this).closest('tr'));
        const abierto = fila.child.isShown();

        abierto ? fila.child.hide() : fila.child(detalleReparto(fila.data()), 'p-0').show();
        $(this).attr('aria-expanded', String(!abierto)).attr('aria-label', abierto ? 'Ver reparto' : 'Ocultar reparto');
    });

    revisarCambio();
});
