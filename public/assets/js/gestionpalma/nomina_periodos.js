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

const Alerta = Swal.mixin({ confirmButtonColor: '#1dab4d' });

const URL_PERIODOS = BASE_URL + 'gestion-palma/nomina/periodos/';

const DIAS_TIPO = { M: 30, Q: 15, S: 7 };

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroAnio = () => $id('filtro_anio').value;
const filtroBusqueda = () => $id('filtro_busqueda').value.trim();

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_periodos_nomina')) {
        $('#tabla_periodos_nomina').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_periodos').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_periodos_nomina').DataTable({
        searching: false,
        columnDefs: [{ targets: [8, 10], orderable: false }],
        order: [[0, 'desc'], [1, 'asc'], [2, 'asc']],
        language: {
            lengthMenu: 'Mostrar _MENU_ registros por página',
            zeroRecords: 'No se encontraron resultados',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            emptyTable: 'No hay datos disponibles',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });
};

const actualizarEstado = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_periodos').innerHTML =
        `<i class="bi bi-list-ol me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_periodos').innerHTML = result.tabla || '';
    renderizarTabla();
    actualizarEstado(result.total ?? 0);
};

const avisar = (response, mensaje) => {
    const texto = mensaje || 'No se pudo completar la operación.';
    if (response.status === 401 || texto.length > 70) {
        Alerta.fire({ icon: 'warning', title: response.status === 401 ? 'Sesión expirada' : 'Atención', text: texto });
        return;
    }
    Toast.fire({ icon: 'warning', title: texto });
};

const enviar = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));

    try {
        const response = await fetch(URL_PERIODOS + accion, { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
            return false;
        }

        aplicarRespuesta(result);
        Toast.fire({ icon: 'success', title: result.message });
        return true;
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
        return false;
    }
};

const filtrosActuales = () => ({
    filtro_empresa: filtroEmpresa(),
    filtro_anio: filtroAnio(),
    filtro_busqueda: filtroBusqueda()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('anio', filtroAnio());
        body.append('busqueda', filtroBusqueda());

        const response = await fetch(URL_PERIODOS + 'listar', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
            return;
        }

        aplicarRespuesta(result);
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

// ── Filtros ───────────────────────────────────────────────────────────────────

$id('btn_buscar').addEventListener('click', listar);
$id('filtro_empresa').addEventListener('change', listar);
$id('filtro_anio').addEventListener('change', listar);
$id('btn_limpiar_busqueda').addEventListener('click', () => {
    $id('filtro_busqueda').value = '';
    listar();
});
$id('filtro_busqueda').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        listar();
    }
});

// ── Modal ─────────────────────────────────────────────────────────────────────

const modalPeriodo = () => bootstrap.Modal.getOrCreateInstance($id('modalPeriodoNomina'));

const bloquearLlave = (bloquear) => {
    ['periodo_empresa', 'periodo_anio', 'periodo_mes', 'periodo_no'].forEach((id) => {
        $id(id).disabled = bloquear;
    });
    $id('periodo_aviso_llave').classList.toggle('d-none', !bloquear);
};

const repoblarTipos = (empresaId, seleccion) => {
    const select = $id('periodo_tipo');
    const tipos = TIPOS_NOMINA[empresaId] || [];
    const deseado = seleccion ?? select.value;
    select.innerHTML = tipos.map((tipo) => {
        const codigo = String(tipo.codigo).trim();
        const descripcion = String(tipo.descripcion).trim().toLowerCase().replace(/(^|\s)\p{L}/gu, (letra) => letra.toUpperCase());
        return `<option value="${codigo}">${codigo} — ${descripcion}</option>`;
    }).join('');
    select.value = tipos.some((tipo) => String(tipo.codigo).trim() === deseado)
        ? deseado
        : (tipos.length ? String(tipos[0].codigo).trim() : '');
};

$id('periodo_empresa').addEventListener('change', () => repoblarTipos($id('periodo_empresa').value));

$id('periodo_tipo').addEventListener('change', () => {
    if ($id('periodo_modo').value === 'crear') {
        $id('periodo_dias').value = DIAS_TIPO[$id('periodo_tipo').value] ?? '';
    }
});

const abrirNuevo = () => {
    const hoy = new Date();
    $id('formulario_periodo').reset();
    $id('periodo_modo').value = 'crear';
    $id('titulo_modal_periodo').innerHTML = '<i class="bi bi-calendar-week me-1"></i>Nuevo periodo de nómina';
    $id('periodo_empresa').value = filtroEmpresa();
    $id('periodo_anio').value = filtroAnio() || hoy.getFullYear();
    $id('periodo_mes').value = String(hoy.getMonth() + 1);
    $id('periodo_no').value = hoy.getMonth() + 1;
    repoblarTipos(filtroEmpresa(), 'M');
    $id('periodo_dias').value = DIAS_TIPO[$id('periodo_tipo').value] ?? '';
    bloquearLlave(false);
    modalPeriodo().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

window.editarPeriodoNomina = async (empresa, anio, mes, noPeriodo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('anio', anio);
        body.append('mes', mes);
        body.append('noPeriodo', noPeriodo);

        const response = await fetch(URL_PERIODOS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el periodo.');
            return;
        }

        const periodo = result.periodo;
        $id('formulario_periodo').reset();
        $id('periodo_modo').value = 'editar';
        $id('titulo_modal_periodo').innerHTML = '<i class="bi bi-calendar-week me-1"></i>Editar periodo de nómina';
        $id('periodo_empresa').value = periodo.empresa;
        $id('periodo_anio').value = periodo['año'];
        $id('periodo_mes').value = String(periodo.mes);
        $id('periodo_no').value = periodo.noPeriodo;
        $id('periodo_empresa_pk').value = periodo.empresa;
        $id('periodo_anio_pk').value = periodo['año'];
        $id('periodo_mes_pk').value = periodo.mes;
        $id('periodo_no_pk').value = periodo.noPeriodo;
        repoblarTipos(periodo.empresa, (periodo.tipoNomina || '').trim());
        $id('periodo_dias').value = periodo.diasNomina ?? '';
        $id('periodo_nombre').value = periodo.nombrePeriodo || '';
        $id('periodo_fecha_inicial').value = periodo.fechaInicial || '';
        $id('periodo_fecha_final').value = periodo.fechaFinal || '';
        $id('periodo_fecha_corte').value = periodo.fechaCorte || '';
        $id('periodo_fecha_pago').value = periodo.fechaPago || '';
        $id('periodo_agronomico').checked = Number(periodo.agronomico) === 1;
        $id('periodo_ejecuta_labores').checked = Number(periodo.ejecutaLabores) === 1;
        $id('periodo_cerrado').checked = Number(periodo.cerrado) === 1;
        bloquearLlave(true);
        modalPeriodo().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_periodo').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $id('btn_guardar_periodo');
    const edicion = $id('periodo_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('periodo_empresa_pk').value : $id('periodo_empresa').value,
        anio: edicion ? $id('periodo_anio_pk').value : $id('periodo_anio').value,
        mes: edicion ? $id('periodo_mes_pk').value : $id('periodo_mes').value,
        noPeriodo: edicion ? $id('periodo_no_pk').value : $id('periodo_no').value,
        tipoNomina: $id('periodo_tipo').value,
        diasNomina: $id('periodo_dias').value,
        fechaInicial: $id('periodo_fecha_inicial').value,
        fechaFinal: $id('periodo_fecha_final').value,
        fechaCorte: $id('periodo_fecha_corte').value,
        fechaPago: $id('periodo_fecha_pago').value,
        nombrePeriodo: $id('periodo_nombre').value,
        cerrado: $id('periodo_cerrado').checked ? 1 : 0,
        agronomico: $id('periodo_agronomico').checked ? 1 : 0,
        ejecutaLabores: $id('periodo_ejecuta_labores').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalPeriodo().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.eliminarPeriodoNomina = async (empresa, anio, mes, noPeriodo, etiqueta) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar periodo de nómina?',
        text: `Se eliminará «${etiqueta}». Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, anio, mes, noPeriodo, ...filtrosActuales() });
    }
};

window.alternarPeriodoNomina = async (empresa, anio, mes, noPeriodo) => {
    const fila = document.querySelector(`#cuerpo_tabla_periodos tr[data-empresa="${empresa}"][data-anio="${anio}"][data-mes="${mes}"][data-noperiodo="${noPeriodo}"]`);
    const cerrado = fila ? fila.dataset.cerrado === '1' : null;
    const accion = cerrado === null ? 'cambiar el estado del' : (cerrado ? 'abrir el' : 'cerrar el');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} periodo seleccionado.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('alternar', { empresa, anio, mes, noPeriodo, ...filtrosActuales() });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_periodos').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_periodos').querySelector('strong').textContent);
});
