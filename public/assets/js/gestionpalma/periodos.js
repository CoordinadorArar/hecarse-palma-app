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

const URL_PERIODOS = BASE_URL + 'gestion-palma/contabilidad/periodos/';

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
    'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroAnio = () => $id('filtro_anio').value;
const filtroBusqueda = () => $id('filtro_busqueda').value.trim();
const nombreEmpresa = () => $id('filtro_empresa').selectedOptions[0]?.textContent.trim() || '';

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_periodos')) {
        $('#tabla_periodos').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_periodos').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_periodos').DataTable({
        searching: false,
        columnDefs: [{ targets: [6, 7], orderable: false }],
        order: [[0, 'desc'], [1, 'asc']],
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
    actualizarAccionesMasivas();
};

const actualizarAccionesMasivas = () => {
    const sinAnio = filtroAnio() === '';
    $id('gp_acciones_masivas').title = sinAnio ? 'Seleccione un año específico para usar las acciones masivas' : '';
    ['btn_generar_anio', 'btn_toggle_anio', 'btn_eliminar_anio', 'btn_generar_anio_vacio'].forEach((id) => {
        $id(id).disabled = sinAnio;
    });
    $id('btn_generar_anio_vacio').classList.toggle('d-none', sinAnio);
    $id('gp_aviso_anio').classList.toggle('d-none', !sinAnio);
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

const conCarga = async (btn, tarea) => {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Procesando...';
    await tarea();
    btn.innerHTML = original;
    btn.disabled = false;
    actualizarAccionesMasivas();
};

// ── Filtros ───────────────────────────────────────────────────────────────────

$id('btn_buscar').addEventListener('click', listar);
$id('filtro_empresa').addEventListener('change', listar);
$id('filtro_anio').addEventListener('change', () => {
    actualizarAccionesMasivas();
    listar();
});
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

const modalPeriodo = () => bootstrap.Modal.getOrCreateInstance($id('modalPeriodo'));

const bloquearLlave = (bloquear) => {
    ['periodo_empresa', 'periodo_anio', 'periodo_mes'].forEach((id) => {
        $id(id).disabled = bloquear;
    });
    $id('periodo_aviso_llave').classList.toggle('d-none', !bloquear);
};

const calcularCodigo = () => {
    const anio = $id('periodo_anio').value;
    const mes = $id('periodo_mes').value;
    $id('periodo_codigo').value = anio && mes ? `${anio}${String(mes).padStart(2, '0')}` : '';
};

const autocompletarDescripcion = () => {
    const anio = $id('periodo_anio').value;
    const mes = Number($id('periodo_mes').value);
    if (anio && mes) {
        $id('periodo_descripcion').value = mes === 13
            ? `Cierre del año ${anio}`
            : `${MESES[mes - 1]} del año ${anio}`;
    }
};

$id('periodo_anio').addEventListener('input', () => {
    calcularCodigo();
    if ($id('periodo_modo').value === 'crear') autocompletarDescripcion();
});

$id('periodo_mes').addEventListener('change', () => {
    calcularCodigo();
    if ($id('periodo_modo').value === 'crear') autocompletarDescripcion();
});

$id('btn_nuevo').addEventListener('click', () => {
    $id('formulario_periodo').reset();
    $id('periodo_modo').value = 'crear';
    $id('titulo_modal_periodo').innerHTML = '<i class="bi bi-calendar3 me-1"></i>Nuevo periodo';
    $id('periodo_empresa').value = filtroEmpresa();
    $id('periodo_anio').value = filtroAnio() || new Date().getFullYear();
    $id('periodo_mes').value = String(new Date().getMonth() + 1);
    bloquearLlave(false);
    calcularCodigo();
    autocompletarDescripcion();
    modalPeriodo().show();
});

window.editarPeriodo = async (empresa, anio, mes) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('anio', anio);
        body.append('mes', mes);

        const response = await fetch(URL_PERIODOS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el periodo.');
            return;
        }

        const periodo = result.periodo;
        $id('formulario_periodo').reset();
        $id('periodo_modo').value = 'editar';
        $id('titulo_modal_periodo').innerHTML = '<i class="bi bi-calendar3 me-1"></i>Editar periodo';
        $id('periodo_empresa').value = periodo.empresa;
        $id('periodo_anio').value = periodo['año'];
        $id('periodo_mes').value = String(periodo.mes);
        $id('periodo_empresa_pk').value = periodo.empresa;
        $id('periodo_anio_pk').value = periodo['año'];
        $id('periodo_mes_pk').value = periodo.mes;
        $id('periodo_descripcion').value = periodo.descripcion || '';
        $id('periodo_codigo').value = periodo.periodo || '';
        $id('periodo_fecha_inicial').value = periodo.fechaInicial || '';
        $id('periodo_fecha_final').value = periodo.fechaFinal || '';
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
        descripcion: $id('periodo_descripcion').value,
        fechaInicial: $id('periodo_fecha_inicial').value,
        fechaFinal: $id('periodo_fecha_final').value,
        cerrado: $id('periodo_cerrado').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalPeriodo().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.eliminarPeriodo = async (empresa, anio, mes, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar periodo?',
        text: `Se eliminará «${descripcion}». Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, anio, mes, ...filtrosActuales() });
    }
};

window.alternarPeriodo = async (empresa, anio, mes) => {
    const fila = document.querySelector(`#cuerpo_tabla_periodos tr[data-empresa="${empresa}"][data-anio="${anio}"][data-mes="${mes}"]`);
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
        await enviar('alternar', { empresa, anio, mes, ...filtrosActuales() });
    }
};

// ── Acciones masivas ──────────────────────────────────────────────────────────

const generarAnio = async (btn) => {
    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: 'Generar periodos del año',
        text: `Se crearán los 13 periodos del año ${filtroAnio()} para ${nombreEmpresa()}. Los periodos existentes no se modificarán.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await conCarga(btn, () => enviar('generar-anio', {
            empresa: filtroEmpresa(),
            anio: filtroAnio(),
            ...filtrosActuales()
        }));
    }
};

$id('btn_generar_anio').addEventListener('click', (e) => generarAnio(e.currentTarget));
$id('btn_generar_anio_vacio').addEventListener('click', (e) => generarAnio(e.currentTarget));

$id('btn_toggle_anio').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: 'Abrir o cerrar el año',
        text: `Se aplicará el cambio a todos los periodos del año ${filtroAnio()} de ${nombreEmpresa()}.`,
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: 'Cerrar todos',
        denyButtonText: 'Abrir todos',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isDismissed) return;

    await conCarga(btn, () => enviar('cerrar-anio', {
        empresa: filtroEmpresa(),
        anio: filtroAnio(),
        cerrado: confirmacion.isConfirmed ? 1 : 0,
        ...filtrosActuales()
    }));
});

$id('btn_eliminar_anio').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar los periodos del año?',
        text: `Se eliminarán los periodos del año ${filtroAnio()} de ${nombreEmpresa()}. Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar el año',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await conCarga(btn, () => enviar('eliminar-anio', {
            empresa: filtroEmpresa(),
            anio: filtroAnio(),
            ...filtrosActuales()
        }));
    }
});

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_periodos').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_periodos').querySelector('strong').textContent);
});
