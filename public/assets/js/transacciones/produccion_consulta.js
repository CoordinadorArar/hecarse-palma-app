const VACIO = '<span class="text-muted">—</span>';

const consulta = { hecha: false, origen: null, empresa: null, filas: [] };

const fechaIso = (fecha) => new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const fFecha = (valor) => {
    const partes = texto(valor).match(/^(\d{4})-(\d{2})-(\d{2})/);

    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : escaparHtml(texto(valor));
};

const fFechaHora = (valor) => `${fFecha(valor)} ${escaparHtml(texto(valor).substring(11, 16))}`.trim();

const dato = (valor, clases) => texto(valor) === ''
    ? VACIO
    : (clases ? `<span class="${clases}">${escaparHtml(texto(valor))}</span>` : escaparHtml(texto(valor)));

const pesos = (valor, decimales = 0) => `$ ${fmt(valor, decimales)}`;
const jornal = (valor) => Number(valor || 0).toLocaleString('es-CO', { maximumFractionDigits: 2 });

const insignia = (anulado) => Number(anulado) === 1
    ? '<span class="badge rounded-pill gp-badge-cerrado"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Anulado</span>'
    : '<span class="badge rounded-pill gp-badge-abierto"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Vigente</span>';

const estadoVacio = (icono, titulo, cuerpo) => `
    <div class="gp-estado-vacio text-center py-5">
        <div class="gp-medallon d-inline-flex align-items-center justify-content-center rounded-circle mb-3">
            <i class="bi ${icono}" aria-hidden="true"></i>
        </div>
        <h6 class="mb-1">${titulo}</h6>
        ${cuerpo}
    </div>`;

const ultimoMes = () => {
    const hoy = new Date();
    const desde = new Date(hoy);

    desde.setMonth(desde.getMonth() - 1);
    if (desde.getDate() !== hoy.getDate()) desde.setDate(0);
    $id('con_desde').value = fechaIso(desde);
    $id('con_hasta').value = fechaIso(hoy);
};

const aviso = (html) => {
    $id('con_aviso').innerHTML = html;
    mostrar($id('con_tabla'), html === '');
};

const contador = (total) => {
    $id('contador_consulta').innerHTML = `<i class="bi bi-hash me-1" aria-hidden="true"></i><strong>${fmt(total)}</strong> ${total === 1 ? 'registro encontrado' : 'registros encontrados'}`;
};

const filaConsulta = (fila) => {
    const numero = texto(fila.numero);
    const anulado = Number(fila.anulado) === 1;
    const observacion = texto(fila.observacion);
    const bloqueo = anulado ? '' : (Number(fila.bloqueada) === 1 ? 'Liquidada en nómina' : (Number(fila.cerrado) === 1 ? 'Periodo cerrado' : ''));

    return `<tr${anulado ? ' class="tx-fila-anulada"' : ''} data-numero="${escaparHtml(numero)}">
        <td class="font-monospace fw-semibold" data-order="${escaparHtml(numero)}">${escaparHtml(numero)}</td>
        <td class="text-nowrap" data-order="${escaparHtml(texto(fila.fecha).substring(0, 10))}">${fFecha(fila.fecha)}</td>
        <td class="text-nowrap" data-order="${escaparHtml(texto(fila.tiquete))}"><span class="font-monospace">${escaparHtml(texto(fila.tiquete))}</span>${Number(fila.externo) === 1 ? ' <span class="gp-chip-fila ambar ms-1">Externo</span>' : ''}</td>
        <td data-order="${escaparHtml(texto(fila.vehiculo))}">${dato(fila.vehiculo, 'font-monospace text-uppercase')}</td>
        <td class="tx-num" data-order="${Number(fila.racimos || 0)}">${fmt(fila.racimos)}</td>
        <td class="tx-num fw-semibold" data-order="${Number(fila.pesoNeto || 0)}">${fmt(fila.pesoNeto)}</td>
        <td class="tx-num" data-order="${Number(fila.lineas || 0)}">${fmt(fila.lineas)}</td>
        <td class="tx-num" data-order="${Number(fila.trabajadores || 0)}">${fmt(fila.trabajadores)}</td>
        <td data-order="${escaparHtml(observacion)}">${observacion === '' ? VACIO : `<span class="gp-truncar d-inline-block align-bottom" title="${escaparHtml(observacion)}">${escaparHtml(observacion)}</span>`}</td>
        <td class="text-center" data-order="${anulado ? 1 : 0}">${insignia(fila.anulado)}</td>
        <td class="text-center gp-col-sticky">
            <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de ${escaparHtml(numero)}">
                <button type="button" class="btn btn-outline-primary" data-ver="${escaparHtml(numero)}" title="Ver transacción TLC ${escaparHtml(numero)}" aria-label="Ver transacción TLC ${escaparHtml(numero)}">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                ${anulado || bloqueo || !PERMISOS.editar ? '' : `<button type="button" class="btn btn-outline-secondary" data-editar="${escaparHtml(numero)}" title="Editar transacción TLC ${escaparHtml(numero)}" aria-label="Editar transacción TLC ${escaparHtml(numero)}">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                </button>`}
                ${anulado || bloqueo || !PERMISOS.eliminar ? '' : `<button type="button" class="btn btn-outline-danger" data-eliminar="${escaparHtml(numero)}" title="Eliminar transacción TLC ${escaparHtml(numero)}" aria-label="Eliminar transacción TLC ${escaparHtml(numero)}">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                </button>`}
            </div>${bloqueo && (PERMISOS.editar || PERMISOS.eliminar) ? `<i class="bi bi-lock text-muted ms-2" role="img" title="${bloqueo}" aria-label="${bloqueo}"></i>` : ''}
        </td>
    </tr>`;
};

const iniciarTabla = () => $('.tx-tabla-consulta').DataTable({
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    searching: true,
    autoWidth: false,
    orderClasses: false,
    order: [[1, 'desc'], [0, 'desc']],
    columnDefs: [
        { targets: [10], orderable: false, searchable: false },
        { targets: [4, 5, 6, 7], searchable: false }
    ],
    dom: "<'tx-dt-barra'lf><'table-responsive't><'tx-dt-barra tx-dt-pie'ip>",
    language: {
        lengthMenu: 'Mostrar _MENU_ registros por página',
        zeroRecords: 'Ninguna transacción coincide con la búsqueda',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'No hay registros disponibles',
        infoFiltered: '(filtrado de _MAX_ registros totales)',
        emptyTable: 'No hay datos disponibles',
        search: 'Buscar en resultados:',
        searchPlaceholder: 'Número, tiquete, vehículo…',
        paginate: {
            first: 'Primero',
            last: 'Último',
            next: 'Siguiente',
            previous: 'Anterior'
        }
    }
});

const consultar = async () => {
    const desde = $id('con_desde').value;
    const hasta = $id('con_hasta').value;
    const invalido = desde !== '' && hasta !== '' && desde > hasta;

    $id('con_hasta').classList.toggle('is-invalid', invalido);

    if (invalido) return;

    consulta.hecha = true;

    const turno = consulta.turno = (consulta.turno || 0) + 1;

    if ($.fn.DataTable.isDataTable('.tx-tabla-consulta')) $('.tx-tabla-consulta').DataTable().destroy();

    const boton = $id('btn_consultar');

    boton.dataset.original = boton.dataset.original || boton.innerHTML;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Consultando…';
    mostrar($id('con_truncado'), false);
    aviso('<div class="text-center py-5 text-muted" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Consultando transacciones…</div>');

    let resultado;

    try {
        resultado = await pedir('consultar', {
            empresa: empresaId(), fechaDesde: desde, fechaHasta: hasta,
            tiquete: texto($id('con_tiquete').value), numero: texto($id('con_numero').value),
            estado: $id('con_estado').value
        });
    } catch (error) {
        if (turno !== consulta.turno) return;
        boton.disabled = false;
        boton.innerHTML = boton.dataset.original;
        contador(0);
        aviso(error.status === 403
            ? estadoVacio('bi-lock', 'Sin permiso para consultar', `<p class="text-muted mb-0">${escaparHtml(error.message)}</p>`)
            : estadoVacio('bi-exclamation-triangle', 'No se pudo consultar',
            `<p class="text-muted mb-3">${escaparHtml(error.message)}</p><button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reintentar"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reintentar</button>`));
        return;
    }

    if (turno !== consulta.turno) return;

    boton.disabled = false;
    boton.innerHTML = boton.dataset.original;

    if ($.fn.DataTable.isDataTable('.tx-tabla-consulta')) $('.tx-tabla-consulta').DataTable().destroy();

    const filas = resultado.filas || [];

    consulta.filas = filas;

    contador(Number(resultado.total ?? filas.length));
    $id('rango_consulta').innerHTML = desde || hasta
        ? `${desde ? fFecha(desde) : '…'}<i class="bi bi-arrow-right" aria-hidden="true"></i>${hasta ? fFecha(hasta) : '…'}`
        : '';
    mostrar($id('con_truncado'), resultado.truncado === true || Number(resultado.truncado) === 1);
    $id('con_filas').innerHTML = filas.map(filaConsulta).join('');

    aviso(filas.length === 0
        ? estadoVacio('bi-receipt', 'No se encontraron transacciones', '<p class="text-muted mb-0">Ajuste el rango de fechas o los filtros y vuelva a consultar.</p>')
        : '');

    if (filas.length > 0) iniciarTabla();
};

const par = (etiqueta, valor, ancho) => `<div${ancho ? ' class="tx-ficha-ancho"' : ''}>
        <span class="gp-etiqueta-mini">${etiqueta}</span>
        <div class="tx-ficha-valor">${valor}</div>
    </div>`;

const cifra = (etiqueta, valor, grande) => `<div class="col-6 col-md">
        <span class="gp-etiqueta-mini">${etiqueta}</span>
        <div class="tx-total${grande ? ' fs-5 tx-total--cuadrado' : ''}">${valor}</div>
    </div>`;

const seccionEncabezado = (enc) => {
    const anulacion = [
        texto(enc.fechaAnulado) ? `el ${fFechaHora(enc.fechaAnulado)}` : '',
        texto(enc.usuarioAnulado) ? `por ${escaparHtml(texto(enc.usuarioAnulado))}` : ''
    ].filter(Boolean).join(' ');

    return `<section class="mb-4">
        <h6 class="gp-seccion"><i class="bi bi-card-heading" aria-hidden="true"></i>Encabezado</h6>
        ${Number(enc.anulado) === 1 ? `<div class="gp-nota-alerta mb-3"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Transacción anulada${anulacion ? ` ${anulacion}` : ''}.</div>` : ''}
        <div class="tx-ficha">
            ${par('Fecha', texto(enc.fecha) ? fFecha(enc.fecha) : VACIO)}
            ${par('Registrado por', `${dato(enc.usuarioRegistro)}${texto(enc.fechaRegistro) ? `<span class="gp-subtexto">${fFechaHora(enc.fechaRegistro)}</span>` : ''}`)}
            ${par('Observación', dato(enc.observacion), true)}
        </div>
    </section>`;
};

const seccionTiquete = (bas) => {
    if (!bas) {
        return `<section class="mb-4">
            <h6 class="gp-seccion"><i class="bi bi-receipt" aria-hidden="true"></i>Tiquete</h6>
            <div class="gp-nota"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Sin datos de tiquete</div>
        </section>`;
    }

    const conductor = texto(bas.codigoConductor) === '' && texto(bas.nombreConductor) === ''
        ? VACIO
        : `${dato(bas.codigoConductor, 'font-monospace')}${texto(bas.nombreConductor) ? `<span class="gp-subtexto">${escaparHtml(texto(bas.nombreConductor))}</span>` : ''}`;

    return `<section class="mb-4">
        <h6 class="gp-seccion"><i class="bi bi-receipt" aria-hidden="true"></i>Tiquete</h6>
        <div class="tx-ficha">
            ${par('Tiquete', dato(bas.tiquete, 'font-monospace'))}
            ${par('Origen', Number(bas.externo) === 1 ? '<span class="gp-chip-fila ambar">Externo</span>' : '<span class="gp-chip-fila verde">Báscula</span>')}
            ${par('Extractora', dato(texto(bas.extractoraNombre) || texto(bas.extractora)))}
            ${par('Conductor', conductor)}
            ${par('Vehículo', dato(bas.vehiculo, 'font-monospace text-uppercase'))}
            ${par('Remolque', dato(bas.remolque, 'font-monospace text-uppercase'))}
        </div>
        <div class="gp-referencia mt-2">
            <div class="row g-3 text-end">
                ${cifra('Racimos', fmt(bas.racimos))}
                ${cifra('Sacos', fmt(bas.sacos))}
                ${cifra('Bruto (kg)', fmt(bas.pesoBruto))}
                ${cifra('Tara (kg)', fmt(bas.pesoTara))}
                ${cifra('Neto (kg)', fmt(bas.pesoNeto), true)}
            </div>
        </div>
    </section>`;
};

const tarjetaLinea = (linea) => {
    const trabajadores = linea.trabajadores || [];
    const peso = Number(linea.pesoRacimo || 0);
    const suma = (clave) => trabajadores.reduce((total, fila) => total + Number(fila[clave] || 0), 0);

    const cuerpo = trabajadores.length === 0
        ? '<tr><td colspan="7" class="text-center text-muted py-3 small">Sin trabajadores registrados</td></tr>'
        : trabajadores.map((fila) => `<tr>
            <td><span class="font-monospace">${escaparHtml(texto(fila.tercero))}</span><span class="gp-subtexto">${escaparHtml(texto(fila.terceroNombre))}</span></td>
            <td><span class="font-monospace">${escaparHtml(texto(fila.novedad))}</span><span class="gp-subtexto">${escaparHtml(texto(fila.novedadNombre))}</span></td>
            <td class="text-nowrap">${texto(fila.fechaNovedad) ? fFecha(fila.fechaNovedad) : VACIO}</td>
            <td class="tx-num">${fmt(fila.cantidad, 2)}</td>
            <td class="tx-num">${jornal(fila.jornales)}</td>
            <td class="tx-num">${pesos(fila.precioLabor, 2)}</td>
            <td class="tx-num">${pesos(fila.valorTotal)}</td>
        </tr>`).join('');

    return `<div class="tx-tarjeta${peso > 0 ? '' : ' tx-tarjeta--sinpeso'}">
        <div class="tx-tarjeta-cab tx-tarjeta-cab--fija border-bottom">
            <span class="gp-chip-ambito finca">${escaparHtml(texto(linea.finca))}</span>
            <span class="fw-semibold">${escaparHtml(texto(linea.fincaNombre))}</span>
            <i class="bi bi-chevron-right gp-ruta-sep" aria-hidden="true"></i>
            <span class="gp-chip-ambito lote">${escaparHtml(texto(linea.lote))}</span>
            <span>${escaparHtml(texto(linea.loteNombre))}</span>
            <span class="ms-auto d-flex align-items-center gap-3">
                <span class="tx-cab-racimos">${fmt(linea.racimos)} <span class="tx-cab-kg">rac.</span></span>
                <span class="tx-cab-kg">${fmt(linea.sacos)} sacos</span>
                <span class="text-end">
                    <span class="tx-cab-racimos">${fmt(linea.cantidad, 2)} kg</span>
                    <span class="gp-subtexto">${peso > 0 ? `${fmt(peso, 2)} kg prom.` : 'Sin peso promedio'}</span>
                </span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 tx-grilla">
                <thead class="gp-thead">
                    <tr>
                        <th style="min-width:200px">Tercero</th>
                        <th style="min-width:200px">Labor</th>
                        <th>Fecha</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Jornales</th>
                        <th class="text-end">Precio</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>${cuerpo}</tbody>
                ${trabajadores.length === 0 ? '' : `<tfoot>
                    <tr class="tx-pie-tarjeta">
                        <td colspan="3" class="text-end">Total línea</td>
                        <td class="tx-num fw-semibold">${fmt(suma('cantidad'), 2)}</td>
                        <td class="tx-num fw-semibold">${jornal(suma('jornales'))}</td>
                        <td></td>
                        <td class="tx-num fw-semibold">${pesos(suma('valorTotal'))}</td>
                    </tr>
                </tfoot>`}
            </table>
        </div>
    </div>`;
};

const seccionLineas = (lineas) => {
    const todos = lineas.flatMap((linea) => linea.trabajadores || []);
    const suma = (lista, clave) => lista.reduce((total, fila) => total + Number(fila[clave] || 0), 0);

    return `<section class="mb-4">
        <h6 class="gp-seccion"><i class="bi bi-diagram-3" aria-hidden="true"></i>Detalle por línea <span class="tx-chip-cont ms-1">${fmt(lineas.length)}</span></h6>
        ${lineas.map(tarjetaLinea).join('')}
        <div class="gp-referencia mt-3">
            <div class="row g-2 text-end">
                ${cifra('Total racimos', fmt(suma(lineas, 'racimos')))}
                ${cifra('Total sacos', fmt(suma(lineas, 'sacos')))}
                ${cifra('Total kg', fmt(suma(lineas, 'cantidad'), 2))}
                ${cifra('Total jornales', jornal(suma(todos, 'jornales')))}
                <div class="col-6 col-md">
                    <span class="gp-etiqueta-mini">Total valor</span>
                    <div class="tx-total fs-5">${pesos(suma(todos, 'valorTotal'))}</div>
                </div>
            </div>
        </div>
    </section>`;
};

const verTransaccion = async (numero, origen) => {
    consulta.origen = origen;
    consulta.detalle = numero;
    $id('ver_numero').textContent = numero;
    $id('ver_estado').innerHTML = '';
    $id('ver_cuerpo').innerHTML = '<div class="text-center py-5 text-muted" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cargando transacción…</div>';
    bootstrap.Modal.getOrCreateInstance($id('modalVerTransaccion')).show();

    let resultado;

    try {
        resultado = await pedir('detalle', { empresa: empresaId(), numero });
    } catch (error) {
        if (consulta.detalle !== numero) return;
        $id('ver_cuerpo').innerHTML = `<div class="gp-nota-alerta" role="alert"><i class="bi ${error.status === 403 ? 'bi-lock' : 'bi-exclamation-triangle'} me-1" aria-hidden="true"></i>${escaparHtml(error.message)}</div>`;
        return;
    }

    if (consulta.detalle !== numero) return;

    const encabezado = resultado.encabezado || {};

    $id('ver_numero').textContent = texto(encabezado.numero) || numero;
    $id('ver_estado').innerHTML = insignia(encabezado.anulado);
    $id('ver_cuerpo').innerHTML = seccionEncabezado(encabezado)
        + seccionTiquete(resultado.bascula || null)
        + seccionLineas(resultado.lineas || []);
};

const volverConsulta = async (numero, refrescar) => {
    bootstrap.Tab.getOrCreateInstance($id('tab_consulta')).show();

    if (refrescar) await consultar();

    const fila = Array.from($id('con_filas').querySelectorAll('tr[data-numero]')).find((item) => item.dataset.numero === numero);

    if (!fila) return;

    if (refrescar) {
        fila.classList.add('gp-flash-guardado');
    } else {
        fila.querySelector('[data-editar]')?.focus();
    }
};

const eliminarTransaccion = async (numero) => {
    if (!PERMISOS.eliminar) return;

    const fila = consulta.filas.find((item) => texto(item.numero) === numero) || {};

    const respuesta = await Swal.fire({
        icon: 'warning',
        title: `¿Eliminar la transacción ${escaparHtml(numero)}?`,
        html: `<div class="gp-referencia mb-3">${fFecha(fila.fecha)} &middot; Tiquete <span class="font-monospace">${escaparHtml(texto(fila.tiquete))}</span> &middot; ${fmt(fila.pesoNeto)} kg neto</div>`
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

const cambiarEmpresa = async () => {
    const selector = $id('filtro_empresa');
    const sucio = Boolean(estado.edicion) || (Boolean($id('tiquete')) && !estado.sellado && (texto($id('tiquete').value) !== '' || estado.lineas.length > 0));

    if (sucio) {
        const respuesta = await Swal.fire({
            icon: 'warning',
            title: '¿Cambiar de empresa?',
            html: estado.edicion ? 'Se perderán los cambios de la edición.' : 'Se perderán los datos sin guardar.',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        });

        if (!respuesta.isConfirmed) {
            selector.value = consulta.empresa;
            return;
        }
    }

    const url = new URL(window.location.href);

    url.searchParams.set('empresa', selector.value);
    window.location.href = url.toString();
};

document.addEventListener('DOMContentLoaded', () => {
    consulta.empresa = $id('filtro_empresa').value;

    $id('filtro_empresa').addEventListener('change', cambiarEmpresa);

    if (!PERMISOS.consultar) {
        if (window.location.hash === '#consulta') history.replaceState(null, '', window.location.pathname + window.location.search);

        return;
    }

    ultimoMes();

    $id('tab_consulta')?.addEventListener('shown.bs.tab', () => {
        history.replaceState(null, '', '#consulta');
        if (!consulta.hecha) consultar();
    });

    $id('tab_registro')?.addEventListener('shown.bs.tab', () => {
        history.replaceState(null, '', window.location.pathname + window.location.search);
    });

    $id('formulario_consulta').addEventListener('submit', (evento) => {
        evento.preventDefault();
        consultar();
    });

    $id('btn_limpiar_consulta').addEventListener('click', () => {
        ultimoMes();
        $id('con_tiquete').value = '';
        $id('con_numero').value = '';
        $id('con_estado').value = '';
        consultar();
    });

    ['con_desde', 'con_hasta'].forEach((id) => $id(id).addEventListener('change', () => $id('con_hasta').classList.remove('is-invalid')));

    $id('con_aviso').addEventListener('click', (evento) => {
        if (evento.target.closest('#btn_reintentar')) consultar();
    });

    $id('con_filas').addEventListener('click', (evento) => {
        const boton = evento.target.closest('button');

        if (!boton) return;

        if (boton.dataset.ver) verTransaccion(boton.dataset.ver, boton);
        if (boton.dataset.editar && PERMISOS.editar) cargarEdicion(boton.dataset.editar);
        if (boton.dataset.eliminar && PERMISOS.eliminar) eliminarTransaccion(boton.dataset.eliminar);
    });

    $id('con_filas').addEventListener('dblclick', (evento) => {
        const fila = evento.target.closest('tr[data-numero]');

        if (fila && !evento.target.closest('button')) verTransaccion(fila.dataset.numero, fila.querySelector('[data-ver]'));
    });

    $id('modalVerTransaccion').addEventListener('shown.bs.modal', () => $id('btn_cerrar_ver').focus());
    $id('modalVerTransaccion').addEventListener('hidden.bs.modal', () => consulta.origen?.focus());

    if (!PERMISOS.registrar) {
        consultar();
    } else if (window.location.hash === '#consulta') {
        $id('tab_consulta').addEventListener('shown.bs.tab', () => $id('con_desde').focus(), { once: true });
        bootstrap.Tab.getOrCreateInstance($id('tab_consulta')).show();
    }
});
