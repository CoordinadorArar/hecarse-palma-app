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

const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const SIESA = { datos: null, doc: null, anio: '', mes: '', cambio: false, ocupado: false, turno: 0, error: null };
const SIESA_MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const SIESA_CAMBIO = 'Cambió el periodo. Pulse Consultar para actualizar.';
const SIESA_ESTADOS = {
    0: ['gp-badge-cierre', 'bi-hourglass-split'],
    1: ['gp-badge-abierto', 'bi-check-circle'],
    2: ['gp-badge-cerrado', 'bi-x-circle'],
    5: ['inf-badge-facturado', 'bi-receipt']
};
const SIESA_NF = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 });

const siesaDif = (valor) => Math.abs(Number(valor) || 0) < 1 ? 0 : Number(valor);
const siesaNum = (valor) => Number(valor || 0).toLocaleString('es-CO');
const siesaMoneda = (valor, signo) => {
    const n = siesaDif(valor);
    const t = SIESA_NF.format(Math.abs(n));

    return n < 0 ? `−${t}` : (signo && n > 0 ? `+${t}` : t);
};
const siesaFecha = (valor) => {
    const p = String(valor ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);

    return escaparHtml(p ? `${p[3]}/${p[2]}/${p[1]}` : valor);
};
const siesaPeriodo = () => ($('#anio').val() && $('#mes').val()) ? `${$('#anio').val()}-${$('#mes').val()}` : '';
const siesaBadge = (doc) => {
    const [clase, icono] = SIESA_ESTADOS[doc.estado] || ['gp-badge-doc', 'bi-question-circle'];

    return `<span class="badge ${clase}"><i class="bi ${icono} me-1" aria-hidden="true"></i>${escaparHtml(doc.estadoTexto)}</span>`;
};
const siesaNnm = (doc) => `<span class="badge gp-badge-doc font-monospace">NNM ${escaparHtml(doc.consecutivo)}</span>`;
const siesaRango = (doc) => `<span class="gp-rango-fecha">${siesaFecha(doc.fechaInicial)} <i class="bi bi-arrow-right" aria-hidden="true"></i> ${siesaFecha(doc.fechaFinal)}</span>`;
const siesaDato = (etiqueta, valor) => `<div><span class="gp-etiqueta-mini">${etiqueta}</span><div class="tx-ficha-valor">${valor}</div></div>`;
const siesaEstadoHtml = (icono, titulo, texto = '', extra = '', error = false) => `
    <div class="gp-estado-vacio text-center py-4">
        <div class="gp-medallon chico${error ? ' inf-medallon-error' : ''} d-inline-flex align-items-center justify-content-center rounded-circle mb-2"><i class="bi ${icono}" aria-hidden="true"></i></div>
        <h6 class="mb-${texto ? '1' : '0'}">${titulo}</h6>
        ${texto ? `<p class="small text-muted mb-${extra ? '3' : '0'}">${texto}</p>` : ''}
        ${extra}
    </div>`;

const siesaPedir = async (accion, datos) => {
    const cuerpo = new FormData();
    let respuesta;
    let resultado = null;

    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    try {
        respuesta = await fetch(CFG_SIESA.urls[accion], { method: 'POST', body: cuerpo });
    } catch (e) {
        throw Object.assign(new Error('No fue posible conectar con el servidor. Intente de nuevo.'), { status: 0 });
    }

    try {
        resultado = await respuesta.json();
    } catch (e) {
        resultado = null;
    }

    if (!respuesta.ok || !resultado || resultado.success !== true) {
        const mensaje = respuesta.status === 403 ? 'No tiene permiso para esta acción.' : (resultado?.message || 'No se pudo completar la operación.');

        throw Object.assign(new Error(mensaje), { status: respuesta.status });
    }

    return resultado;
};

const siesaPie = (visible) => {
    const pie = document.getElementById('siesa_pie');

    pie?.classList.toggle('d-none', !visible);
    pie?.classList.toggle('d-flex', visible);
};

const siesaBotones = () => {
    if (!document.getElementById('siesa_pie') || !SIESA.doc) return;

    const t = SIESA.datos.totales || {};
    const doc = SIESA.doc;
    const conPendientes = (SIESA.datos.empleados || []).filter((e) => e.lineasPendientes > 0).length;
    const motivoMarcar = SIESA.cambio ? SIESA_CAMBIO
        : !doc.marcable ? `El documento está ${doc.estadoTexto} en Siesa; solo se puede marcar si está Aprobado o Facturado.`
        : !t.lineasPendientes ? 'No hay líneas pendientes por marcar.' : '';
    const motivoRevertir = SIESA.cambio ? SIESA_CAMBIO : !t.lineasMarcadas ? 'No hay líneas marcadas para revertir.' : '';

    $('#btn_siesa_marcar').prop('disabled', !!motivoMarcar || SIESA.ocupado);
    $('#btn_siesa_revertir').prop('disabled', !!motivoRevertir || SIESA.ocupado);
    $('#siesa_motivo').text(motivoMarcar || `Se marcarán ${siesaNum(t.lineasPendientes)} líneas de ${siesaNum(conPendientes)} empleados.`);
};

const siesaOcupado = (estado) => {
    const boton = document.getElementById('consultar_liquidacion');

    SIESA.ocupado = estado;
    boton.dataset.original = boton.dataset.original || boton.innerHTML;
    boton.disabled = estado;
    boton.innerHTML = estado ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Consultando…' : boton.dataset.original;
    $('#siesa_documento').prop('disabled', estado);
    siesaBotones();
};

const siesaEstado = (html) => {
    SIESA.doc = null;
    $('#siesa_num_docs, #siesa_selector').addClass('d-none');
    $('#siesa_resultados').addClass('d-none');
    $('#siesa_cuerpo').removeClass('inf-desactualizado').html(html);
    siesaPie(false);
};

const siesaKpi = (icono, etiqueta, valor, subtexto, alerta = false) => `
    <div class="col">
        <div class="inf-kpi">
            <div class="gp-icono d-flex align-items-center justify-content-center rounded-3${alerta ? ' inf-medallon-error' : ''}"><i class="bi ${icono}" aria-hidden="true"></i></div>
            <div class="min-w-0">
                <span class="gp-etiqueta-mini text-truncate">${etiqueta}</span>
                <div class="inf-kpi-valor${alerta ? ' inf-dif-alerta' : ''}" title="${escaparHtml(valor)}">${valor}</div>
                <span class="gp-subtexto text-truncate">${subtexto}</span>
            </div>
        </div>
    </div>`;

const siesaTabla = (empleados) => {
    if ($.fn.DataTable.isDataTable('#tabla-siesa')) $('#tabla-siesa').DataTable().clear().destroy();

    const dinero = (v, tipo) => tipo === 'display' ? siesaMoneda(v) : Number(v) || 0;
    const conteo = (v, tipo) => tipo === 'display' ? (Number(v) ? siesaNum(v) : '<span class="tx-cero">0</span>') : Number(v) || 0;
    const chip = (e) => {
        const p = Number(e.lineasPendientes), m = Number(e.lineasMarcadas);

        return p > 0 && m === 0 ? ['tx-chip-neutro', 'bi-clock', 'Pendiente']
            : p > 0 ? ['ambar', 'bi-circle-half', 'Parcial']
            : m > 0 ? ['verde', 'bi-lock-fill', 'Liquidado']
            : ['inf-chip-pronto', 'bi-dash-circle', 'Sin labores'];
    };

    $('#tabla-siesa').DataTable({
        data: empleados,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Todos']],
        autoWidth: false,
        orderClasses: false,
        order: [[4, 'desc'], [1, 'asc']],
        dom: "<'tx-dt-barra'lf><'table-responsive't><'tx-dt-barra tx-dt-pie'ip>",
        columnDefs: [{ targets: [2, 3, 4, 5, 6, 7], searchable: false }],
        columns: [
            { data: 'cedula', className: 'font-monospace', render: (v, tipo) => tipo === 'display' ? escaparHtml(v) : v },
            { data: 'nombre', render: (v, tipo) => tipo === 'display' ? `<div class="gp-truncar" title="${escaparHtml(v)}">${escaparHtml(v)}</div>` : v },
            { data: 'valorSiesa', className: 'tx-num', render: dinero },
            { data: 'valorAppPalma', className: 'tx-num', render: dinero },
            {
                data: 'diferencia', className: 'tx-num', render: (v, tipo) => {
                    const n = siesaDif(v);

                    if (tipo === 'sort' || tipo === 'type') return Math.abs(n);
                    if (tipo !== 'display') return n;

                    return n ? `<span class="inf-dif-alerta">${siesaMoneda(n, true)}</span>` : `<span class="text-muted">${siesaMoneda(0)}</span>`;
                }
            },
            { data: 'lineasPendientes', className: 'text-center tx-num', render: conteo },
            { data: 'lineasMarcadas', className: 'text-center tx-num', render: conteo },
            {
                data: null, className: 'text-center', render: (v, tipo, e) => {
                    const [clase, icono, texto] = chip(e);

                    return tipo === 'display' ? `<span class="gp-chip-fila ${clase}"><i class="bi ${icono} me-1" aria-hidden="true"></i>${texto}</span>` : texto;
                }
            }
        ],
        createdRow: (fila, e) => fila.classList.toggle('inf-fila-diferencia', siesaDif(e.diferencia) !== 0),
        language: {
            lengthMenu: 'Mostrar _MENU_ registros por página',
            zeroRecords: 'Ningún empleado coincide con la búsqueda',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ empleados',
            infoEmpty: 'No hay empleados disponibles',
            infoFiltered: '(filtrado de _MAX_ empleados totales)',
            emptyTable: 'El documento no tiene empleados con labores (conceptos 070 a 078).',
            search: 'Buscar en resultados:',
            searchPlaceholder: 'Cédula o nombre',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        }
    });
};

const siesaPintar = () => {
    const d = SIESA.datos;
    const docs = d.documentos || [];

    if (!docs.length) {
        siesaEstado(siesaEstadoHtml('bi-file-earmark-x', `No hay nómina mensual en Siesa para ${SIESA_MESES[Number(SIESA.mes) - 1]} de ${escaparHtml(SIESA.anio)}`, 'Cuando el documento NNM esté registrado en Siesa podrá consultarlo aquí.'));
        return;
    }

    const doc = docs.find((x) => x.rowid === d.documentoSeleccionado) || docs[0];
    const t = d.totales || {};
    const empleados = d.empleados || [];
    const conDif = empleados.filter((e) => siesaDif(e.diferencia) !== 0).length;

    SIESA.doc = doc;
    $('#siesa_num_docs').toggleClass('d-none', docs.length < 2).text(`${docs.length} documentos`);
    $('#siesa_selector').toggleClass('d-none', docs.length < 2);
    $('#siesa_documento').html(docs.map((x) => `<option value="${x.rowid}"${x.rowid === doc.rowid ? ' selected' : ''}>${escaparHtml(`NNM ${x.consecutivo} · `)}${siesaFecha(x.fechaInicial)} – ${siesaFecha(x.fechaFinal)} · ${escaparHtml(x.estadoTexto)}</option>`).join(''));

    $('#siesa_cuerpo').removeClass('inf-desactualizado').html(`
        <div class="tx-ficha">
            ${siesaDato('Consecutivo', siesaNnm(doc))}
            ${siesaDato('Periodo', siesaRango(doc))}
            ${siesaDato('Total devengos', `<span class="tx-num">${siesaMoneda(doc.totalDevengos)}</span>`)}
            ${siesaDato('Estado', siesaBadge(doc))}
        </div>
        ${doc.marcable ? '' : `<div class="gp-nota-alerta mt-3 d-flex align-items-start gap-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>El documento está ${escaparHtml(doc.estadoTexto)} en Siesa. Podrá marcar las labores cuando esté Aprobado o Facturado.</span></div>`}
        ${CFG_SIESA.puedeMarcar ? '' : '<div class="mt-3"><span class="gp-chip-fila tx-chip-neutro"><i class="bi bi-eye me-1" aria-hidden="true"></i>Solo consulta</span></div>'}`);

    $('#siesa_kpis').html([
        siesaKpi('bi-people', 'Empleados', siesaNum(t.empleados), 'En el documento Siesa'),
        siesaKpi('bi-bank', 'Valor Siesa', siesaMoneda(t.valorSiesa), 'Conceptos 070 a 078'),
        siesaKpi('bi-journal-check', 'Valor AppPalma', siesaMoneda(t.valorAppPalma), 'Labores en el rango'),
        siesaKpi('bi-arrow-left-right', 'Diferencia', siesaMoneda(t.diferencia, true), conDif ? `${siesaNum(conDif)} empleados con diferencia` : 'Sin diferencias', conDif > 0),
        siesaKpi('bi-lock', 'Líneas pendientes', siesaNum(t.lineasPendientes), !t.lineasPendientes && t.lineasMarcadas > 0 ? 'Todas marcadas' : `${siesaNum(t.lineasMarcadas)} marcadas`)
    ].join(''));

    $('#siesa_contador').html(`<i class="bi bi-hash" aria-hidden="true"></i><strong>${siesaNum(empleados.length)}</strong> empleados`);
    $('#siesa_resultados').removeClass('d-none inf-desactualizado');
    siesaTabla(empleados);
    siesaPie(CFG_SIESA.puedeMarcar);
    siesaBotones();
};

async function consultarSiesa(documento) {
    const anio = $('#anio').val();
    const mes = $('#mes').val();

    if (!anio || !mes) {
        Swal.fire('Atención', 'Seleccione el año y mes a consultar', 'warning');
        return;
    }

    const turno = ++SIESA.turno;

    if (SIESA.doc) {
        $('#siesa_cuerpo, #siesa_resultados').addClass('inf-desactualizado');
    } else {
        siesaEstado('<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Consultando Siesa…</div>');
    }

    siesaOcupado(true);

    try {
        const datos = await siesaPedir('consultar', { anio, mes, documento: documento || '' });

        if (turno !== SIESA.turno) return;

        Object.assign(SIESA, { datos, anio, mes, cambio: siesaPeriodo() !== `${anio}-${mes}` });
        siesaPintar();
        $('#siesa_cuerpo, #siesa_resultados').toggleClass('inf-desactualizado', SIESA.cambio && !!SIESA.doc);
    } catch (error) {
        if (turno !== SIESA.turno) return;

        Object.assign(SIESA, { datos: null, anio, mes, cambio: false });
        siesaEstado(siesaEstadoHtml('bi-cloud-slash', 'No se pudo consultar Siesa', escaparHtml(error.message),
            '<button type="button" class="btn btn-outline-primary btn-sm" id="btn_siesa_reintentar"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Reintentar</button>', true));
    } finally {
        if (turno === SIESA.turno) siesaOcupado(false);
    }
}

const siesaConfirmar = (marcar) => {
    const doc = SIESA.doc;
    const t = SIESA.datos.totales || {};
    const empleados = SIESA.datos.empleados || [];
    const afectados = empleados.filter((e) => (marcar ? e.lineasPendientes : e.lineasMarcadas) > 0).length;
    const conDif = empleados.filter((e) => siesaDif(e.diferencia) !== 0);
    const montoDif = conDif.reduce((suma, e) => suma + Math.abs(Number(e.diferencia) || 0), 0);
    const notas = marcar
        ? '<div class="gp-nota-alerta text-start">Las labores marcadas quedarán bloqueadas para edición. Podrá desbloquearlas con “Revertir marca”.</div>'
            + (conDif.length ? `<div class="gp-nota-alerta text-start mt-2">Hay diferencias entre Siesa y AppPalma por ${siesaMoneda(montoDif)} en ${siesaNum(conDif.length)} empleados. Revíselas antes de continuar.</div>` : '')
        : '<div class="gp-nota text-start">Las labores quedarán desbloqueadas y podrán editarse de nuevo.</div>';

    SIESA.error = null;

    Swal.fire({
        icon: marcar ? 'question' : 'warning',
        width: 560,
        title: marcar ? '¿Marcar labores como liquidadas?' : '¿Revertir la marca de liquidación?',
        html: `
            <div class="gp-referencia text-start mb-3">
                <div class="tx-ficha">
                    ${siesaDato('Documento', `${siesaNnm(doc)} ${siesaBadge(doc)}`)}
                    ${siesaDato('Periodo', siesaRango(doc))}
                    ${siesaDato('Empleados', siesaNum(afectados))}
                    ${siesaDato(marcar ? 'Líneas a marcar' : 'Líneas a desmarcar', siesaNum(marcar ? t.lineasPendientes : t.lineasMarcadas))}
                </div>
            </div>
            ${notas}`,
        showCancelButton: true,
        confirmButtonText: marcar ? 'Sí, marcar' : 'Sí, revertir',
        confirmButtonColor: marcar ? '#1dab4d' : '#d6293e',
        cancelButtonText: 'Cancelar',
        focusCancel: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: () => siesaPedir(marcar ? 'marcar' : 'revertir', { anio: SIESA.anio, mes: SIESA.mes, documento: doc.rowid })
            .catch((error) => {
                SIESA.error = error;
                Swal.showValidationMessage(escaparHtml(error.message));
                return false;
            })
    }).then((resultado) => {
        if (resultado.isConfirmed && resultado.value) {
            const r = resultado.value;
            const lineas = Number(r.lineas) || 0;

            Toast.fire({
                icon: lineas ? 'success' : 'info',
                title: !lineas ? 'No había líneas por actualizar.'
                    : marcar ? `Se marcaron ${siesaNum(lineas)} líneas de ${siesaNum(r.empleados)} empleados.`
                    : `Se revirtió la marca de ${siesaNum(lineas)} líneas de ${siesaNum(r.empleados)} empleados.`
            });
            consultarSiesa(doc.rowid);
            return;
        }

        if (SIESA.error?.status === 403) {
            CFG_SIESA.puedeMarcar = false;
            $('#siesa_pie').remove();
            siesaPintar();
        } else if (SIESA.error?.status === 422) {
            consultarSiesa(doc.rowid);
        }

        SIESA.error = null;
    });
};

$(function () {
    $('#tab_temporales, #tab_siesa').on('shown.bs.tab', function () {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();

        const periodo = siesaPeriodo();

        if (this.id === 'tab_siesa' && periodo && periodo !== `${SIESA.anio}-${SIESA.mes}`) consultarSiesa();
    });

    $('#anio, #mes').on('change', () => {
        if (!SIESA.doc) return;

        SIESA.cambio = siesaPeriodo() !== `${SIESA.anio}-${SIESA.mes}`;
        $('#siesa_cuerpo, #siesa_resultados').toggleClass('inf-desactualizado', SIESA.cambio);
        siesaBotones();
    });

    $('#siesa_documento').on('change', function () {
        consultarSiesa(this.value);
    });

    $('#siesa_cuerpo').on('click', '#btn_siesa_reintentar', () => consultarSiesa());
    $('#btn_siesa_marcar').on('click', () => siesaConfirmar(true));
    $('#btn_siesa_revertir').on('click', () => siesaConfirmar(false));
});
