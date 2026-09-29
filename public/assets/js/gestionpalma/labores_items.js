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

const URL_ITEMS = BASE_URL + 'gestion-palma/labores/items/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroEstado = () => $id('filtro_estado').value;

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_items')) {
        $('#tabla_items').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_items').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_items').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [4, 7], orderable: false }],
        order: [[0, 'asc']],
        language: {
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay datos disponibles'
        }
    });
};

const actualizarEstado = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_items').innerHTML =
        `<i class="bi bi-box-seam me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_items').innerHTML = result.tabla || '';
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
        const response = await fetch(URL_ITEMS + accion, { method: 'POST', body });
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

        const response = await fetch(URL_ITEMS + 'listar', { method: 'POST', body });
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

const modalItem = () => bootstrap.Modal.getOrCreateInstance($id('modalItem'));

const bloquearLlave = (bloquear) => {
    $id('item_empresa').disabled = bloquear;
    $id('item_codigo').disabled = bloquear;
    $id('item_aviso_llave').classList.toggle('d-none', !bloquear);
};

const formatearFecha = (fecha) => {
    const partes = String(fecha).slice(0, 10).split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : fecha;
};

const mostrarActualizacion = (item) => {
    const bloque = $id('item_actualizado');
    if (item.fechaActualiza) {
        const porUsuario = item.usuarioActualiza ? ` por ${item.usuarioActualiza}` : '';
        bloque.innerHTML = `<i class="bi bi-clock-history me-1"></i>Última actualización: ${formatearFecha(item.fechaActualiza)}${porUsuario}`;
        bloque.classList.remove('d-none');
    } else {
        bloque.classList.add('d-none');
        bloque.innerHTML = '';
    }
};

const proponerCodigo = async (empresa) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);

        const response = await fetch(URL_ITEMS + 'siguiente-codigo', { method: 'POST', body });
        const result = await response.json();

        if (response.ok && result.success && $id('item_modo').value === 'crear') {
            $id('item_codigo').value = result.codigo ?? '';
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

const prepararFormulario = () => {
    $id('formulario_item').reset();
    $id('item_actualizado').classList.add('d-none');
    $id('item_actualizado').innerHTML = '';
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('item_modo').value = 'crear';
    $id('titulo_modal_item').innerHTML = '<i class="bi bi-box-seam me-1"></i>Nuevo item';
    $id('item_empresa').value = filtroEmpresa();
    $id('item_activo').checked = true;
    bloquearLlave(false);
    modalItem().show();
    proponerCodigo(filtroEmpresa());
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

$id('item_empresa').addEventListener('change', () => {
    if ($id('item_modo').value === 'crear') {
        proponerCodigo($id('item_empresa').value);
    }
});

window.editarItem = async (empresa, codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);

        const response = await fetch(URL_ITEMS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el item.');
            return;
        }

        const item = result.item;
        prepararFormulario();
        $id('item_modo').value = 'editar';
        $id('titulo_modal_item').innerHTML = '<i class="bi bi-box-seam me-1"></i>Editar item';
        $id('item_empresa').value = item.empresa;
        $id('item_codigo').value = item.codigo;
        $id('item_empresa_pk').value = item.empresa;
        $id('item_codigo_pk').value = item.codigo;
        $id('item_descripcion').value = item.descripcion || '';
        $id('item_descorta').value = item.descripcionAbreviada || '';
        $id('item_referencia').value = item.referencia || '';
        $id('item_umedida').value = item.uMedida || '';
        $id('item_notas').value = item.notas || '';
        $id('item_activo').checked = Number(item.activo) === 1;
        mostrarActualizacion(item);
        bloquearLlave(true);
        modalItem().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_item').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $id('btn_guardar_item');
    const edicion = $id('item_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('item_empresa_pk').value : $id('item_empresa').value,
        codigo: edicion ? $id('item_codigo_pk').value : $id('item_codigo').value,
        descripcion: $id('item_descripcion').value,
        descripcionAbreviada: $id('item_descorta').value,
        referencia: $id('item_referencia').value,
        uMedida: $id('item_umedida').value,
        notas: $id('item_notas').value,
        activo: $id('item_activo').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalItem().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.cambiarEstadoItem = async (empresa, codigo) => {
    const fila = document.querySelector(`#cuerpo_tabla_items tr[data-empresa="${CSS.escape(empresa)}"][data-codigo="${CSS.escape(codigo)}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado del' : (activo ? 'inactivar el' : 'activar el');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} item seleccionado.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, ...filtrosActuales() });
    }
};

window.eliminarItem = async (empresa, codigo, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar item?',
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
    actualizarEstado($id('cuerpo_tabla_items').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_items').querySelector('strong').textContent);
});
