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

const URL_GRUPOS = BASE_URL + 'gestion-palma/labores/grupos/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroEstado = () => $id('filtro_estado').value;

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_grupos')) {
        $('#tabla_grupos').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_grupos').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_grupos').DataTable({
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
    $id('contador_grupos').innerHTML =
        `<i class="bi bi-collection me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_grupos').innerHTML = result.tabla || '';
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
        const response = await fetch(URL_GRUPOS + accion, { method: 'POST', body });
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

        const response = await fetch(URL_GRUPOS + 'listar', { method: 'POST', body });
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

const modalGrupo = () => bootstrap.Modal.getOrCreateInstance($id('modalGrupo'));

$('#grupo_ccosto, #grupo_ccosto_siigo').select2({
    width: '100%',
    dropdownParent: $('#modalGrupo'),
    placeholder: 'Seleccione una opción…',
    allowClear: true,
    language: {
        noResults: () => 'No se encontraron resultados',
        searching: () => 'Buscando…'
    }
});

const bloquearLlave = (bloquear) => {
    $id('grupo_empresa').disabled = bloquear;
    $id('grupo_codigo').disabled = bloquear;
    $id('grupo_aviso_llave').classList.toggle('d-none', !bloquear);
};

const aplicarManejaCcostoSiigo = () => {
    const activo = $id('grupo_maneja_ccosto_siigo').checked;
    const col = $id('grupo_col_ccosto_siigo');
    col.classList.toggle('d-none', !activo);
    if (activo) {
        col.classList.remove('gp-revelar');
        void col.offsetWidth;
        col.classList.add('gp-revelar');
    }
};

$id('grupo_maneja_ccosto_siigo').addEventListener('change', aplicarManejaCcostoSiigo);

const proponerCodigo = async (empresa) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);

        const response = await fetch(URL_GRUPOS + 'siguiente-codigo', { method: 'POST', body });
        const result = await response.json();

        if (response.ok && result.success && $id('grupo_modo').value === 'crear') {
            $id('grupo_codigo').value = result.codigo ?? '';
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

const prepararFormulario = () => {
    $id('formulario_grupo').reset();
    $('#grupo_ccosto').val('').trigger('change');
    $('#grupo_ccosto_siigo').val('').trigger('change');
    aplicarManejaCcostoSiigo();
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('grupo_modo').value = 'crear';
    $id('titulo_modal_grupo').innerHTML = '<i class="bi bi-collection me-1"></i>Nuevo grupo';
    $id('grupo_empresa').value = filtroEmpresa();
    $id('grupo_activo').checked = true;
    bloquearLlave(false);
    modalGrupo().show();
    proponerCodigo(filtroEmpresa());
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

$id('grupo_empresa').addEventListener('change', () => {
    if ($id('grupo_modo').value === 'crear') {
        proponerCodigo($id('grupo_empresa').value);
    }
});

window.editarGrupo = async (empresa, codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);

        const response = await fetch(URL_GRUPOS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el grupo.');
            return;
        }

        const grupo = result.grupo;
        prepararFormulario();
        $id('grupo_modo').value = 'editar';
        $id('titulo_modal_grupo').innerHTML = '<i class="bi bi-collection me-1"></i>Editar grupo';
        $id('grupo_empresa').value = grupo.empresa;
        $id('grupo_codigo').value = grupo.codigo;
        $id('grupo_empresa_pk').value = grupo.empresa;
        $id('grupo_codigo_pk').value = grupo.codigo;
        $id('grupo_descripcion').value = grupo.descripcion || '';
        $('#grupo_ccosto').val(grupo.ccosto || '').trigger('change');
        $id('grupo_activo').checked = Number(grupo.activo) === 1;
        $id('grupo_maneja_ccosto_siigo').checked = Number(grupo.manejaCcostoSiigo) === 1;
        $('#grupo_ccosto_siigo').val(grupo.ccostoSiigo || '').trigger('change');
        aplicarManejaCcostoSiigo();
        bloquearLlave(true);
        modalGrupo().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_grupo').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $id('btn_guardar_grupo');
    const edicion = $id('grupo_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('grupo_empresa_pk').value : $id('grupo_empresa').value,
        codigo: edicion ? $id('grupo_codigo_pk').value : $id('grupo_codigo').value,
        descripcion: $id('grupo_descripcion').value,
        ccosto: $id('grupo_ccosto').value,
        manejaCcostoSiigo: $id('grupo_maneja_ccosto_siigo').checked ? 1 : 0,
        ccostoSiigo: $id('grupo_ccosto_siigo').value,
        activo: $id('grupo_activo').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalGrupo().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.cambiarEstadoGrupo = async (empresa, codigo) => {
    const fila = document.querySelector(`#cuerpo_tabla_grupos tr[data-empresa="${CSS.escape(empresa)}"][data-codigo="${CSS.escape(codigo)}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado del' : (activo ? 'inactivar el' : 'activar el');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} grupo seleccionado.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, ...filtrosActuales() });
    }
};

window.eliminarGrupo = async (empresa, codigo, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar grupo?',
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
    actualizarEstado($id('cuerpo_tabla_grupos').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_grupos').querySelector('strong').textContent);
});
