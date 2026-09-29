const conEstado = { hecha: false, empresa: null, filas: [], turno: 0 };

const conVacio = (icono, titulo, cuerpo) => `
    <div class="gp-estado-vacio text-center py-5">
        <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
            <i class="bi ${icono}" aria-hidden="true"></i>
        </div>
        <h6 class="mb-1">${titulo}</h6>
        ${cuerpo}
    </div>`;

const conAviso = (html) => {
    $id('con_aviso').innerHTML = html;
    mostrar($id('con_tabla'), html === '');
};

const conContador = (total) => {
    $id('contador_consulta').innerHTML = `<i class="bi bi-hash me-1" aria-hidden="true"></i><strong>${fmt(total)}</strong> ${total === 1 ? 'registro encontrado' : 'registros encontrados'}`;
};

const conAjustarCampo = () => {
    const campo = $id('con_campo').value;
    const esFecha = campo === 'fecha';
    const valor = $id('con_valor');
    const contiene = $id('con_operador').querySelector('option[value="contiene"]');

    contiene.hidden = esFecha;
    contiene.disabled = esFecha;

    if (esFecha && $id('con_operador').value === 'contiene') $id('con_operador').value = 'igual';

    if ((valor.type === 'date') !== esFecha) valor.value = '';

    valor.type = esFecha ? 'date' : 'text';
    $id('con_valor_icono').className = `bi ${esFecha ? 'bi-calendar-event' : ({ numero: 'bi-hash', referencia: 'bi-journal-check' }[campo] || 'bi-search')}`;
};

const conMotivoBloqueo = (fila) => {
    if (Number(fila.anulado) === 1) return 'Transacción anulada';

    if (Number(fila.cerrado) === 1) return 'Periodo cerrado';

    return Number(fila.bloqueada) === 1 ? 'Liquidada en nómina' : '';
};

const conFila = (fila) => {
    const numero = escaparHtml(texto(fila.numero));
    const tipo = escaparHtml(texto(fila.tipo));
    const anulado = Number(fila.anulado) === 1;
    const observacion = texto(fila.observacion);
    const bloqueo = conMotivoBloqueo(fila);
    const claseBloqueo = bloqueo ? ' tx-accion-bloqueada' : '';
    const fecha = texto(fila.fecha).substring(0, 10);

    return `<tr${anulado ? ' class="tx-fila-anulada"' : ''} data-numero="${numero}">
        <td class="text-center text-nowrap">
            <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de ${numero}">
                ${PERMISOS.editar ? `<button type="button" class="btn btn-outline-primary${claseBloqueo}" data-editar="${numero}" title="Editar transacción ${tipo} ${numero}" aria-label="Editar transacción ${tipo} ${numero}"><i class="bi bi-pencil" aria-hidden="true"></i></button>` : ''}
                ${PERMISOS.eliminar ? `<button type="button" class="btn btn-outline-danger${claseBloqueo}" data-eliminar="${numero}" title="Eliminar transacción ${tipo} ${numero}" aria-label="Eliminar transacción ${tipo} ${numero}"><i class="bi bi-trash" aria-hidden="true"></i></button>` : ''}
            </div>${bloqueo && (PERMISOS.editar || PERMISOS.eliminar) ? `<i class="bi bi-lock text-muted ms-1" role="img" title="${bloqueo}" aria-label="${bloqueo}"></i>` : ''}
        </td>
        <td><span class="gp-chip-fila tx-chip-neutro">${tipo}</span></td>
        <td class="font-monospace fw-semibold">${numero}</td>
        <td class="text-nowrap" data-order="${escaparHtml(fecha)}">${fechaCorta(fecha)}</td>
        <td class="font-monospace">${texto(fila.finca) ? escaparHtml(texto(fila.finca)) : '<span class="text-muted">—</span>'}</td>
        ${CFG.referencia ? `<td class="font-monospace text-nowrap">${texto(fila.referencia) ? escaparHtml(texto(fila.referencia)) : '<span class="text-muted">—</span>'}</td>` : ''}
        <td>${observacion === '' ? '<span class="text-muted">—</span>' : `<span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(observacion)}">${escaparHtml(observacion)}</span>`}</td>
        <td class="text-center" data-order="${anulado ? 1 : 0}">${anulado
            ? '<span class="badge rounded-pill gp-badge-cerrado"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Anulado</span>'
            : '<span class="badge rounded-pill gp-badge-abierto"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Vigente</span>'}</td>
    </tr>`;
};

const conIniciarTabla = () => $('.tx-tabla-consulta').DataTable({
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    autoWidth: false,
    orderClasses: false,
    order: [[3, 'desc'], [2, 'desc']],
    columnDefs: [{ targets: [0], orderable: false, searchable: false }],
    dom: "<'tx-dt-barra'lf><'table-responsive't><'tx-dt-barra tx-dt-pie'ip>",
    language: {
        lengthMenu: 'Mostrar _MENU_ registros por página',
        zeroRecords: 'Ninguna transacción coincide con la búsqueda',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'No hay registros disponibles',
        infoFiltered: '(filtrado de _MAX_ registros totales)',
        emptyTable: 'No hay datos disponibles',
        search: 'Buscar en resultados:',
        searchPlaceholder: 'Número, finca, observación…',
        paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
    }
});

const consultar = async () => {
    const turno = ++conEstado.turno;
    const boton = $id('btn_consultar');

    conEstado.hecha = true;

    if ($.fn.DataTable.isDataTable('.tx-tabla-consulta')) $('.tx-tabla-consulta').DataTable().destroy();

    boton.dataset.original = boton.dataset.original || boton.innerHTML;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Filtrando…';
    mostrar($id('con_truncado'), false);
    conAviso('<div class="text-center py-5 text-muted" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Consultando transacciones…</div>');

    let resultado;

    try {
        resultado = await pedir('consultar', {
            empresa: empresaId(), campo: $id('con_campo').value, operador: $id('con_operador').value, valor: texto($id('con_valor').value)
        });
    } catch (error) {
        if (turno !== conEstado.turno) return;
        boton.disabled = false;
        boton.innerHTML = boton.dataset.original;
        conContador(0);
        conAviso(error.status === 403
            ? conVacio('bi-lock', 'Sin permiso para consultar', `<p class="text-muted mb-0">${escaparHtml(error.message)}</p>`)
            : conVacio('bi-exclamation-triangle', 'No se pudo consultar',
                `<p class="text-muted mb-3">${escaparHtml(error.message)}</p><button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reintentar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>`));

        return;
    }

    if (turno !== conEstado.turno) return;

    boton.disabled = false;
    boton.innerHTML = boton.dataset.original;

    const filas = resultado.filas || [];

    conEstado.filas = filas;
    conContador(Number(resultado.total ?? filas.length));
    mostrar($id('con_truncado'), resultado.truncado === true || Number(resultado.truncado) === 1);
    $id('con_filas').innerHTML = filas.map(conFila).join('');
    conAviso(filas.length === 0
        ? conVacio('bi-search', 'No se encontraron transacciones', '<p class="text-muted mb-0">Ajuste el filtro o deje el valor vacío para ver todas.</p>')
        : '');

    if (filas.length > 0) conIniciarTabla();
};

const volverConsulta = async (numero, refrescar) => {
    bootstrap.Tab.getOrCreateInstance($id('tab_consulta')).show();

    if (refrescar) await consultar();

    const fila = Array.from($id('con_filas').querySelectorAll('tr[data-numero]')).find((item) => item.dataset.numero === numero);

    if (fila && refrescar) fila.classList.add('gp-flash-guardado');
};

const conFilaDe = (numero) => conEstado.filas.find((item) => texto(item.numero) === numero) || { numero };

const conEditar = (numero) => {
    const fila = conFilaDe(numero);

    if (alertaBloqueo(fila, 'editar')) return;

    cargarEdicion(numero);
};

const conEliminar = async (numero) => {
    const fila = conFilaDe(numero);

    if (alertaBloqueo(fila, 'eliminar')) return;

    const referencia = [
        fechaCorta(fila.fecha),
        texto(fila.finca) ? `Finca <span class="font-monospace">${escaparHtml(texto(fila.finca))}</span>` : '',
        escaparHtml(texto(fila.observacion).substring(0, 80))
    ].filter(Boolean).join(' &middot; ');

    const respuesta = await Swal.fire({
        icon: 'warning',
        title: `¿Eliminar la transacción ${escaparHtml(numero)}?`,
        html: `<div class="gp-referencia mb-3">${referencia}</div>`
            + '<p class="mb-0">Dejará de mostrarse en la consulta. El registro se conserva para auditoría.</p>',
        input: 'textarea',
        inputLabel: 'Motivo (opcional)',
        inputAttributes: { maxlength: 250 },
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d6293e',
        focusCancel: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: (motivo) => pedir('eliminar', { empresa: empresaId(), numero, motivo: texto(motivo) })
            .catch((error) => Swal.showValidationMessage(escaparHtml(error.message)))
    });

    if (!respuesta.isConfirmed) return;

    Toast.fire({ icon: 'success', title: `Transacción ${escaparHtml(numero)} eliminada` });

    if (estado.edicion === numero) limpiarTodo();

    consultar();
};

const conCambiarEmpresa = async () => {
    const selector = $id('filtro_empresa');

    if (hayCambios()) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: '¿Cambiar de empresa?',
            html: 'Se perderán los datos sin guardar.',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        });

        if (!respuesta.isConfirmed) {
            selector.value = conEstado.empresa;

            return;
        }
    }

    const url = new URL(window.location.href);

    url.searchParams.set('empresa', selector.value);
    window.location.href = url.toString();
};

document.addEventListener('DOMContentLoaded', () => {
    conEstado.empresa = $id('filtro_empresa').value;
    $id('filtro_empresa').addEventListener('change', conCambiarEmpresa);

    if (!PERMISOS.consultar) return;

    conAjustarCampo();

    $id('tab_consulta')?.addEventListener('shown.bs.tab', () => {
        if (!conEstado.hecha) consultar();
    });

    $id('con_campo').addEventListener('change', conAjustarCampo);

    $id('formulario_consulta').addEventListener('submit', (evento) => {
        evento.preventDefault();
        consultar();
    });

    $id('btn_limpiar_consulta').addEventListener('click', () => {
        $id('con_campo').value = 'fecha';
        $id('con_operador').value = 'igual';
        $id('con_valor').value = '';
        conAjustarCampo();
        consultar();
    });

    $id('con_aviso').addEventListener('click', (evento) => {
        if (evento.target.closest('#btn_reintentar')) consultar();
    });

    $id('con_filas').addEventListener('click', (evento) => {
        const boton = evento.target.closest('button');

        if (!boton) return;

        if (boton.dataset.editar && PERMISOS.editar) conEditar(boton.dataset.editar);
        if (boton.dataset.eliminar && PERMISOS.eliminar) conEliminar(boton.dataset.eliminar);
    });

    if (!PERMISOS.registrar) consultar();
});
