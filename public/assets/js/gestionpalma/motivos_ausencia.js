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

const URL_MOTIVOS = BASE_URL + 'gestion-palma/parametros/motivo-ausencia/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroEstado = () => $id('filtro_estado').value;

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_motivos')) {
        $('#tabla_motivos').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_motivos').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_motivos').DataTable({
        paging: false,
        dom: "<'gp-dt-barra'if><'table-responsive't>",
        columnDefs: [{ targets: [3, 5], orderable: false }],
        order: [[0, 'asc']],
        language: {
            info: '_TOTAL_ registros',
            infoEmpty: '0 registros',
            infoFiltered: '(de _MAX_)'
        }
    });
};

const actualizarEstado = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_motivos').innerHTML =
        `<i class="bi bi-person-x me-1" aria-hidden="true"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_motivos').innerHTML = result.tabla || '';
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
        const response = await fetch(URL_MOTIVOS + accion, { method: 'POST', body });
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

        const response = await fetch(URL_MOTIVOS + 'listar', { method: 'POST', body });
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

$id('filtro_empresa').addEventListener('change', listar);
$id('filtro_estado').addEventListener('change', listar);

const modalMotivo = () => bootstrap.Modal.getOrCreateInstance($id('modalMotivo'));

const limpiarErrores = () => ['mo_codigo', 'mo_descripcion'].forEach((id) => $id(id).classList.remove('is-invalid'));

const bloquearLlave = (bloquear) => {
    $id('mo_empresa').disabled = bloquear;
    $id('mo_codigo').disabled = bloquear;
    $id('mo_aviso_llave').classList.toggle('d-none', !bloquear);
};

const abrirNuevo = () => {
    $id('formulario_motivo').reset();
    limpiarErrores();
    $id('mo_modo').value = 'crear';
    $id('titulo_modal_motivo').innerHTML = '<i class="bi bi-person-x me-1" aria-hidden="true"></i>Nuevo motivo';
    $id('mo_empresa').value = filtroEmpresa();
    $id('mo_codigo').value = '';
    $id('mo_activo').checked = true;
    if ($id('mo_remunerado')) $id('mo_remunerado').checked = false;
    bloquearLlave(false);
    modalMotivo().show();
    $id('modalMotivo').addEventListener('shown.bs.modal', () => $id('mo_codigo').focus(), { once: true });
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

window.editarMotivo = async (empresa, codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);

        const response = await fetch(URL_MOTIVOS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el motivo de ausencia.');
            return;
        }

        const motivo = result.motivo;
        $id('formulario_motivo').reset();
        limpiarErrores();
        $id('mo_modo').value = 'editar';
        $id('titulo_modal_motivo').innerHTML = '<i class="bi bi-person-x me-1" aria-hidden="true"></i>Editar motivo';
        $id('mo_empresa').value = motivo.empresa;
        $id('mo_codigo').value = motivo.codigo;
        $id('mo_empresa_pk').value = motivo.empresa;
        $id('mo_codigo_pk').value = motivo.codigo;
        $id('mo_descripcion').value = motivo.descripcion || '';
        $id('mo_activo').checked = Number(motivo.activo) === 1;
        if ($id('mo_remunerado')) $id('mo_remunerado').checked = Number(motivo.remunerado) === 1;
        bloquearLlave(true);
        modalMotivo().show();
        $id('modalMotivo').addEventListener('shown.bs.modal', () => $id('mo_descripcion').focus(), { once: true });
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

['mo_codigo', 'mo_descripcion'].forEach((id) => $id(id).addEventListener('input', () => $id(id).classList.remove('is-invalid')));

$id('formulario_motivo').addEventListener('submit', async (e) => {
    e.preventDefault();
    const edicion = $id('mo_modo').value === 'editar';
    const faltaCodigo = !edicion && $id('mo_codigo').value.trim() === '';
    const faltaDescripcion = $id('mo_descripcion').value.trim() === '';

    $id('mo_codigo').classList.toggle('is-invalid', faltaCodigo);
    $id('mo_descripcion').classList.toggle('is-invalid', faltaDescripcion);

    if (faltaCodigo || faltaDescripcion) {
        $id(faltaCodigo ? 'mo_codigo' : 'mo_descripcion').focus();
        return;
    }

    const btn = $id('btn_guardar_motivo');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('mo_empresa_pk').value : $id('mo_empresa').value,
        codigo: edicion ? $id('mo_codigo_pk').value : $id('mo_codigo').value.trim().toUpperCase(),
        descripcion: $id('mo_descripcion').value.trim(),
        activo: $id('mo_activo').checked ? 1 : 0,
        ...($id('mo_remunerado') ? { remunerado: $id('mo_remunerado').checked ? 1 : 0 } : {}),
        ...filtrosActuales()
    });

    if (ok) modalMotivo().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar';
});

window.cambiarEstadoMotivo = async (empresa, codigo) => {
    const fila = document.querySelector(`#cuerpo_tabla_motivos tr[data-empresa="${CSS.escape(String(empresa))}"][data-codigo="${CSS.escape(codigo)}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado del' : (activo ? 'desactivar el' : 'activar el');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} motivo de ausencia ${codigo}.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, ...filtrosActuales() });
    }
};

window.eliminarMotivo = async (empresa, codigo, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar motivo de ausencia?',
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
    actualizarEstado($id('cuerpo_tabla_motivos').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_motivos').querySelector('strong').textContent);
});
