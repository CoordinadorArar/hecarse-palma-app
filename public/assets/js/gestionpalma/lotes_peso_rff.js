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

const URL_PESOS = BASE_URL + 'gestion-palma/lotes/peso-rff/';

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
    'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroAnio = () => $id('filtro_anio').value;
const filtroMes = () => $id('filtro_mes').value;
const filtroFinca = () => $id('filtro_finca').value;
const filtroProblemas = () => $id('filtro_problemas').value;
const nombreFincaSel = () => $id('filtro_finca').selectedOptions[0]?.textContent.trim() || '';

const nombrePeriodo = (anio, mes) => (Number(mes) === 13 ? `el Cierre del año ${anio}` : `${MESES[Number(mes) - 1]} de ${anio}`);

// ── Tabla de pesos ────────────────────────────────────────────────────────────

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_pesos')) {
        $('#tabla_pesos').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_pesos').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_pesos').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [6], orderable: false }],
        order: [[1, 'asc'], [2, 'asc']],
        language: {
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay datos disponibles'
        }
    });
};

const actualizarEstado = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_pesos').innerHTML =
        `<i class="bi bi-speedometer2 me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);

    if (vacio) {
        const anio = filtroAnio() || ANIO_SEL;
        const mes = filtroMes() || MES_SEL;
        const finca = nombreFincaSel();
        $id('gp_mensaje_vacio').textContent =
            `No hay pesos de RFF registrados${finca ? ` para ${finca}` : ''} en ${nombrePeriodo(anio, mes)}. Ajuste el periodo o la finca, o registre el peso del mes.`;
    }
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_pesos').innerHTML = result.tabla || '';
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

const filtrosActuales = () => ({
    filtro_empresa: filtroEmpresa(),
    filtro_anio: filtroAnio(),
    filtro_mes: filtroMes(),
    filtro_finca: filtroFinca(),
    filtro_problemas: filtroProblemas()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('anio', filtroAnio());
        body.append('mes', filtroMes());
        body.append('finca', filtroFinca());
        body.append('problemas', filtroProblemas());

        const response = await fetch(URL_PESOS + 'listar', { method: 'POST', body });
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

const enviar = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));

    try {
        const response = await fetch(URL_PESOS + accion, { method: 'POST', body });
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

// ── Filtros ───────────────────────────────────────────────────────────────────

['filtro_empresa', 'filtro_anio', 'filtro_mes', 'filtro_finca', 'filtro_problemas'].forEach((id) => {
    $id(id).addEventListener('change', listar);
});

// ── Lote (según finca) ────────────────────────────────────────────────────────

const repoblarLotes = (finca, seleccion = '') => {
    const lista = LOTES_POR_FINCA[finca] || [];
    const select = $id('peso_lote');
    select.innerHTML = '<option value="">Seleccione&hellip;</option>' +
        lista.map((l) => `<option value="${l.codigo}">${l.codigo} &mdash; ${l.descripcion}</option>`).join('');
    select.value = lista.some((l) => l.codigo === seleccion) ? seleccion : '';
};

$id('peso_finca').addEventListener('change', () => {
    if ($id('peso_modo').value === 'crear') repoblarLotes($id('peso_finca').value);
});

// ── Fechas calculadas según periodo ────────────────────────────────────────────

const pad = (n) => String(n).padStart(2, '0');

const aplicarFechasPeriodo = () => {
    if ($id('peso_modo').value !== 'crear') return;

    const anio = Number($id('peso_anio').value);
    const mes = Number($id('peso_mes').value);

    if (!anio || !mes || mes === 13) {
        $id('peso_fecha_inicial').value = '';
        $id('peso_fecha_final').value = '';
        return;
    }

    const ultimoDia = new Date(anio, mes, 0).getDate();
    $id('peso_fecha_inicial').value = `${anio}-${pad(mes)}-01`;
    $id('peso_fecha_final').value = `${anio}-${pad(mes)}-${pad(ultimoDia)}`;
};

$id('peso_anio').addEventListener('input', aplicarFechasPeriodo);
$id('peso_mes').addEventListener('change', aplicarFechasPeriodo);

// ── Modal Peso ────────────────────────────────────────────────────────────────

const modalPeso = () => bootstrap.Modal.getOrCreateInstance($id('modalPeso'));

const bloquearLlave = (bloquear) => {
    ['peso_anio', 'peso_mes', 'peso_finca', 'peso_lote'].forEach((id) => {
        $id(id).disabled = bloquear;
        $id(id).classList.toggle('bg-light', bloquear);
    });
    $id('peso_aviso_llave').classList.toggle('d-none', !bloquear);
};

const abrirNuevo = () => {
    $id('formulario_peso').reset();
    $id('peso_modo').value = 'crear';
    $id('peso_titulo_texto').textContent = 'Nuevo peso RFF';
    $id('peso_subtitulo').textContent = '';
    $id('peso_empresa').value = filtroEmpresa();
    $id('peso_anio').value = filtroAnio() || ANIO_SEL;
    $id('peso_mes').value = String(filtroMes() || MES_SEL);
    $id('peso_finca').value = filtroFinca();
    repoblarLotes($id('peso_finca').value);
    $id('peso_automatico').checked = false;
    aplicarFechasPeriodo();
    bloquearLlave(false);
    modalPeso().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

window.editarPeso = async (empresa, anio, mes, finca, lote) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('anio', anio);
        body.append('mes', mes);
        body.append('finca', finca);
        body.append('lote', lote);

        const response = await fetch(URL_PESOS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el registro.');
            return;
        }

        const peso = result.peso;
        $id('formulario_peso').reset();
        $id('peso_modo').value = 'editar';
        $id('peso_titulo_texto').textContent = 'Editar peso RFF';
        $id('peso_subtitulo').textContent =
            `· Finca ${peso.fincaNombre || peso.finca} — Lote ${peso.lote} — ${nombrePeriodo(peso.anio, peso.mes)}`;
        $id('peso_empresa').value = peso.empresa;
        $id('peso_anio_pk').value = peso.anio;
        $id('peso_mes_pk').value = peso.mes;
        $id('peso_finca_pk').value = peso.finca;
        $id('peso_lote_pk').value = peso.lote;
        $id('peso_anio').value = peso.anio;
        $id('peso_mes').value = String(peso.mes);
        $id('peso_finca').value = peso.finca;
        repoblarLotes(peso.finca, peso.lote);
        $id('peso_racimo').value = peso.pesoRacimo;
        $id('peso_automatico').checked = Number(peso.automatico) === 1;
        $id('peso_fecha_inicial').value = peso.fechaInicial || '';
        $id('peso_fecha_final').value = peso.fechaFinal || '';
        bloquearLlave(true);
        modalPeso().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_peso').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = $id('btn_guardar_peso');
    const edicion = $id('peso_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: $id('peso_empresa').value,
        anio: edicion ? $id('peso_anio_pk').value : $id('peso_anio').value,
        mes: edicion ? $id('peso_mes_pk').value : $id('peso_mes').value,
        finca: edicion ? $id('peso_finca_pk').value : $id('peso_finca').value,
        lote: edicion ? $id('peso_lote_pk').value : $id('peso_lote').value,
        pesoRacimo: $id('peso_racimo').value,
        automatico: $id('peso_automatico').checked ? 1 : 0,
        fechaInicial: $id('peso_fecha_inicial').value,
        fechaFinal: $id('peso_fecha_final').value,
        ...filtrosActuales()
    });

    if (ok) modalPeso().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.eliminarPeso = async (empresa, anio, mes, finca, lote) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar peso RFF?',
        text: `Se eliminará el peso del lote ${lote} para ${nombrePeriodo(anio, mes)}. Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, anio, mes, finca, lote, ...filtrosActuales() });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_pesos').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_pesos').querySelector('strong').textContent);
});
