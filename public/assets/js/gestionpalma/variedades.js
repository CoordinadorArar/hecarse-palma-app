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

const URL_VARIEDADES = BASE_URL + 'gestion-palma/parametros/variedad/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroEstado = () => $id('filtro_estado').value;

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_variedades')) {
        $('#tabla_variedades').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_variedades').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_variedades').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [3, 5], orderable: false }],
        order: [[0, 'asc']],
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
    $id('contador_variedades').innerHTML =
        `<i class="bi bi-tags me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_variedades').innerHTML = result.tabla || '';
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
        const response = await fetch(URL_VARIEDADES + accion, { method: 'POST', body });
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
    filtro_estado: filtroEstado()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('estado', filtroEstado());

        const response = await fetch(URL_VARIEDADES + 'listar', { method: 'POST', body });
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

$id('filtro_empresa').addEventListener('change', listar);
$id('filtro_estado').addEventListener('change', listar);

// ── Modal ─────────────────────────────────────────────────────────────────────

const modalVariedad = () => bootstrap.Modal.getOrCreateInstance($id('modalVariedad'));

const bloquearLlave = (bloquear) => {
    $id('variedad_empresa').disabled = bloquear;
    $id('variedad_codigo').disabled = bloquear;
    $id('variedad_aviso_llave').classList.toggle('d-none', !bloquear);
};

const proponerCodigo = async (empresa) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);

        const response = await fetch(URL_VARIEDADES + 'siguiente-codigo', { method: 'POST', body });
        const result = await response.json();

        if (response.ok && result.success && $id('variedad_modo').value === 'crear') {
            $id('variedad_codigo').value = result.codigo ?? '';
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

const abrirNuevo = () => {
    $id('formulario_variedad').reset();
    $id('variedad_modo').value = 'crear';
    $id('titulo_modal_variedad').innerHTML = '<i class="bi bi-flower1 me-1"></i>Nueva variedad';
    $id('variedad_empresa').value = filtroEmpresa();
    $id('variedad_activo').checked = true;
    bloquearLlave(false);
    modalVariedad().show();
    proponerCodigo(filtroEmpresa());
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

$id('variedad_empresa').addEventListener('change', () => {
    if ($id('variedad_modo').value === 'crear') {
        proponerCodigo($id('variedad_empresa').value);
    }
});

window.editarVariedad = async (empresa, codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);

        const response = await fetch(URL_VARIEDADES + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró la variedad.');
            return;
        }

        const variedad = result.variedad;
        $id('formulario_variedad').reset();
        $id('variedad_modo').value = 'editar';
        $id('titulo_modal_variedad').innerHTML = '<i class="bi bi-flower1 me-1"></i>Editar variedad';
        $id('variedad_empresa').value = variedad.empresa;
        $id('variedad_codigo').value = variedad.codigo;
        $id('variedad_empresa_pk').value = variedad.empresa;
        $id('variedad_codigo_pk').value = variedad.codigo;
        $id('variedad_descripcion').value = variedad.descripcion || '';
        $id('variedad_procedencia').value = variedad.procedencia || '';
        $id('variedad_activo').checked = Number(variedad.activo) === 1;
        bloquearLlave(true);
        modalVariedad().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_variedad').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $id('btn_guardar_variedad');
    const edicion = $id('variedad_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('variedad_empresa_pk').value : $id('variedad_empresa').value,
        codigo: edicion ? $id('variedad_codigo_pk').value : $id('variedad_codigo').value,
        descripcion: $id('variedad_descripcion').value,
        procedencia: $id('variedad_procedencia').value,
        activo: $id('variedad_activo').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalVariedad().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.cambiarEstadoVariedad = async (empresa, codigo) => {
    const fila = document.querySelector(`#cuerpo_tabla_variedades tr[data-empresa="${CSS.escape(empresa)}"][data-codigo="${CSS.escape(codigo)}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado de la' : (activo ? 'inactivar la' : 'activar la');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} variedad seleccionada.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, ...filtrosActuales() });
    }
};

window.eliminarVariedad = async (empresa, codigo, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar variedad?',
        text: `Se eliminará «${descripcion}». Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, codigo, ...filtrosActuales() });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_variedades').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_variedades').querySelector('strong').textContent);
});
