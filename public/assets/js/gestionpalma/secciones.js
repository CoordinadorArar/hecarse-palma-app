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

const URL_SECCIONES = BASE_URL + 'gestion-palma/secciones/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroFinca = () => $id('filtro_finca').value;
const filtroEstado = () => $id('filtro_estado').value;

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_secciones')) {
        $('#tabla_secciones').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_secciones').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_secciones').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [6], orderable: false }],
        order: [[1, 'asc'], [0, 'asc']],
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

const formatoHa = (n) => Number(n).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const actualizarLineaHectareas = (haAsignadas, haFinca) => {
    const el = $id('contador_hectareas');
    if (filtroFinca() === '' || haAsignadas === null || haAsignadas === undefined) {
        el.classList.add('d-none');
        el.textContent = '';
        return;
    }
    const asignadas = formatoHa(haAsignadas);
    if (haFinca) {
        const pct = (haAsignadas / haFinca * 100).toFixed(1).replace('.', ',');
        el.textContent = `${asignadas} ha asignadas de ${formatoHa(haFinca)} ha brutas (${pct}%)`;
    } else {
        el.textContent = `${asignadas} ha asignadas`;
    }
    el.classList.remove('d-none');
};

const actualizarEstado = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_secciones').innerHTML =
        `<i class="bi bi-grid-1x2 me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);

    if (vacio) {
        const primerUso = filtroFinca() === '' && filtroEstado() === '';
        $id('gp_estado_vacio_titulo').textContent = primerUso
            ? 'Divida sus fincas en secciones o bloques'
            : 'No se encontraron secciones';
        $id('gp_estado_vacio_texto').textContent = primerUso
            ? 'Una sección (o bloque) es una subdivisión de una finca: agrupa un área específica de sus hectáreas para llevar el control por zona. Cree la primera sección de una finca para empezar.'
            : 'Ajuste los filtros de finca o estado, o registre una nueva sección.';
    }
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_secciones').innerHTML = result.tabla || '';
    renderizarTabla();
    actualizarEstado(result.total ?? 0);
    actualizarLineaHectareas(result.ha_asignadas, result.ha_finca);
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
    filtro_finca: filtroFinca(),
    filtro_estado: filtroEstado()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('finca', filtroFinca());
        body.append('estado', filtroEstado());

        const response = await fetch(URL_SECCIONES + 'listar', { method: 'POST', body });
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
        const response = await fetch(URL_SECCIONES + accion, { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
            return false;
        }

        await listar();
        Toast.fire({ icon: 'success', title: result.message });
        return true;
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
        return false;
    }
};

// ── Filtros ───────────────────────────────────────────────────────────────────

$id('filtro_empresa').addEventListener('change', listar);
$id('filtro_finca').addEventListener('change', listar);
$id('filtro_estado').addEventListener('change', listar);

// ── Modal ─────────────────────────────────────────────────────────────────────

const modalSeccion = () => bootstrap.Modal.getOrCreateInstance($id('modalSeccion'));

const bloquearLlave = (bloquear) => {
    $id('seccion_finca').disabled = bloquear;
    $id('seccion_codigo').disabled = bloquear;
    $id('seccion_aviso_llave').classList.toggle('d-none', !bloquear);
};

const prepararFormulario = () => {
    $id('formulario_seccion').reset();
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('seccion_modo').value = 'crear';
    $id('titulo_modal_seccion').innerHTML = '<i class="bi bi-grid-1x2 me-1"></i>Nueva sección';
    $id('seccion_finca').value = filtroFinca();
    $id('seccion_activo').checked = true;
    bloquearLlave(false);
    modalSeccion().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

window.editarSeccion = async (empresa, finca, codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('finca', finca);
        body.append('codigo', codigo);

        const response = await fetch(URL_SECCIONES + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró la sección.');
            return;
        }

        const seccion = result.seccion;
        prepararFormulario();
        $id('seccion_modo').value = 'editar';
        $id('titulo_modal_seccion').innerHTML = '<i class="bi bi-grid-1x2 me-1"></i>Editar sección';
        $id('seccion_finca').value = seccion.finca;
        $id('seccion_codigo').value = seccion.codigo;
        $id('seccion_empresa_pk').value = seccion.empresa;
        $id('seccion_finca_pk').value = seccion.finca;
        $id('seccion_codigo_pk').value = seccion.codigo;
        $id('seccion_descripcion').value = seccion.descripcion || '';
        $id('seccion_hbrutas').value = seccion.hBrutas ?? '';
        $id('seccion_activo').checked = Number(seccion.activo) === 1;
        bloquearLlave(true);
        modalSeccion().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_seccion').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = $id('btn_guardar_seccion');
    const edicion = $id('seccion_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('seccion_empresa_pk').value : filtroEmpresa(),
        finca: edicion ? $id('seccion_finca_pk').value : $id('seccion_finca').value,
        codigo: edicion ? $id('seccion_codigo_pk').value : $id('seccion_codigo').value,
        descripcion: $id('seccion_descripcion').value,
        hBrutas: $id('seccion_hbrutas').value,
        activo: $id('seccion_activo').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalSeccion().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.cambiarEstadoSeccion = async (empresa, finca, codigo) => {
    const fila = document.querySelector(`#cuerpo_tabla_secciones tr[data-empresa="${CSS.escape(empresa)}"][data-finca="${CSS.escape(finca)}"][data-codigo="${CSS.escape(codigo)}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado de la' : (activo ? 'inactivar la' : 'activar la');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} sección seleccionada.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, finca, codigo, ...filtrosActuales() });
    }
};

window.eliminarSeccion = async (empresa, finca, codigo, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar sección?',
        text: `Se eliminará «${descripcion}». Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, finca, codigo, ...filtrosActuales() });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_secciones').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_secciones').querySelector('strong').textContent);
});
