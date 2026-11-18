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

const URL_UNIDADES = BASE_URL + 'gestion-palma/unidades-negocio/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroFinca = () => $id('filtro_finca').value;
const filtroEstado = () => $id('filtro_estado').value;

const casillasFinca = () => [...document.querySelectorAll('#unidad_fincas .unidad-finca')];

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_unidades')) {
        $('#tabla_unidades').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_unidades').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_unidades').DataTable({
        paging: false,
        dom: "<'gp-dt-barra'if><'table-responsive't>",
        columnDefs: [{ targets: [2, 5], orderable: false }],
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
    $id('contador_unidades').innerHTML =
        `<i class="bi bi-boxes me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);

    if (vacio) {
        const primerUso = filtroFinca() === '' && filtroEstado() === '';
        $id('gp_estado_vacio_titulo').textContent = primerUso
            ? 'Organice sus fincas por unidad de negocio'
            : 'No se encontraron unidades de negocio';
        $id('gp_estado_vacio_texto').textContent = primerUso
            ? 'Una unidad de negocio agrupa los lotes según la actividad que se desarrolla en ellos, como Palma, Búfalos o Ganadería. Cree la primera unidad y marque las fincas donde se maneja.'
            : 'Ajuste los filtros de finca o estado, o registre una nueva unidad de negocio.';
    }
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_unidades').innerHTML = result.filas ?? result.tabla ?? '';
    renderizarTabla();
    actualizarEstado(result.total ?? $id('cuerpo_tabla_unidades').querySelectorAll('tr').length);
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

        const response = await fetch(URL_UNIDADES + 'listar', { method: 'POST', body });
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
        const response = await fetch(URL_UNIDADES + accion, { method: 'POST', body });
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

$id('filtro_empresa').addEventListener('change', (e) => {
    const url = new URL(window.location.href);
    url.searchParams.set('empresa', e.target.value);
    window.location.assign(url);
});
$id('filtro_finca').addEventListener('change', listar);
$id('filtro_estado').addEventListener('change', listar);

const modalUnidad = () => bootstrap.Modal.getOrCreateInstance($id('modalUnidadNegocio'));

const actualizarFincas = () => {
    const casillas = casillasFinca();
    casillas.forEach((c) => c.closest('label').classList.toggle('activo', c.checked));
    const contador = $id('unidad_fincas_contador');
    if (contador) {
        const n = casillas.filter((c) => c.checked).length;
        contador.innerHTML = `<strong>${n}</strong> de ${casillas.length} ${casillas.length === 1 ? 'finca seleccionada' : 'fincas seleccionadas'}`;
    }
    $id('unidad_aviso_bloqueo').classList.toggle('d-none', !casillas.some((c) => c.disabled));
};

const marcarFincas = (marcar) => {
    casillasFinca().filter((c) => !c.disabled).forEach((c) => { c.checked = marcar; });
    actualizarFincas();
};

$id('unidad_fincas').addEventListener('change', actualizarFincas);
$id('btn_unidad_marcar_todas')?.addEventListener('click', () => marcarFincas(true));
$id('btn_unidad_quitar_todas')?.addEventListener('click', () => marcarFincas(false));

$id('unidad_codigo').addEventListener('input', (e) => {
    e.target.value = e.target.value.toUpperCase().replace(/\s+/g, '');
});

const prepararFormulario = () => {
    $id('formulario_unidad').reset();
    casillasFinca().forEach((c) => {
        const label = c.closest('label');
        c.checked = false;
        c.disabled = false;
        label.classList.remove('bloqueada');
        label.querySelector('.unidad-finca-detalle').textContent = `Cód. ${c.value}`;
        label.querySelector('.bi-lock-fill')?.remove();
    });
};

const bloquearLlave = (bloquear) => {
    $id('unidad_codigo').disabled = bloquear;
    $id('unidad_aviso_llave').classList.toggle('d-none', !bloquear);
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('unidad_modo').value = 'crear';
    $id('titulo_modal_unidad').innerHTML = '<i class="bi bi-boxes me-1"></i>Nueva unidad de negocio';
    $id('unidad_activo').checked = true;
    const finca = casillasFinca().find((c) => c.value === filtroFinca().trim());
    if (finca) finca.checked = true;
    bloquearLlave(false);
    actualizarFincas();
    $id('modalUnidadNegocio').addEventListener('shown.bs.modal', () => $id('unidad_codigo').focus(), { once: true });
    modalUnidad().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

window.editarUnidadNegocio = async (empresa, codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);

        const response = await fetch(URL_UNIDADES + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró la unidad de negocio.');
            return;
        }

        const unidad = result.unidad;
        prepararFormulario();
        $id('unidad_modo').value = 'editar';
        $id('titulo_modal_unidad').innerHTML = '<i class="bi bi-boxes me-1"></i>Editar unidad de negocio';
        $id('unidad_codigo').value = unidad.codigo;
        $id('unidad_empresa_pk').value = unidad.empresa;
        $id('unidad_codigo_pk').value = unidad.codigo;
        $id('unidad_descripcion').value = unidad.descripcion || '';
        $id('unidad_activo').checked = Number(unidad.activo) === 1;

        (unidad.fincas || []).forEach(({ finca, lotes }) => {
            const casilla = casillasFinca().find((c) => c.value === String(finca).trim());
            if (!casilla) return;
            casilla.checked = true;
            const n = Number(lotes) || 0;
            if (n === 0) return;
            const label = casilla.closest('label');
            const texto = `${n} ${n === 1 ? 'lote' : 'lotes'}`;
            casilla.disabled = true;
            label.classList.add('bloqueada');
            label.querySelector('.unidad-finca-detalle').textContent = `Cód. ${casilla.value} · ${texto}`;
            label.insertAdjacentHTML('beforeend', `<i class="bi bi-lock-fill text-muted flex-shrink-0" title="No se puede quitar: ${texto} de esta finca ${n === 1 ? 'usa' : 'usan'} la unidad de negocio."></i>`);
        });

        bloquearLlave(true);
        actualizarFincas();
        $id('modalUnidadNegocio').addEventListener('shown.bs.modal', () => $id('unidad_descripcion').focus(), { once: true });
        modalUnidad().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_unidad').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = $id('btn_guardar_unidad');
    const edicion = $id('unidad_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('unidad_empresa_pk').value : filtroEmpresa(),
        codigo: edicion ? $id('unidad_codigo_pk').value : $id('unidad_codigo').value.trim().toUpperCase(),
        descripcion: $id('unidad_descripcion').value.trim(),
        activo: $id('unidad_activo').checked ? 1 : 0,
        fincas: JSON.stringify(casillasFinca().filter((c) => c.checked).map((c) => c.value)),
        ...filtrosActuales()
    });

    if (ok) modalUnidad().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

const filaUnidad = (empresa, codigo) =>
    document.querySelector(`#cuerpo_tabla_unidades tr[data-empresa="${CSS.escape(String(empresa))}"][data-codigo="${CSS.escape(String(codigo))}"]`);

window.cambiarEstadoUnidadNegocio = async (empresa, codigo) => {
    const fila = filaUnidad(empresa, codigo);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado de la' : (activo ? 'inactivar la' : 'activar la');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} unidad de negocio seleccionada.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, ...filtrosActuales() });
    }
};

window.eliminarUnidadNegocio = async (empresa, codigo, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar unidad de negocio?',
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
    actualizarEstado($id('cuerpo_tabla_unidades').querySelectorAll('tr').length);
});
