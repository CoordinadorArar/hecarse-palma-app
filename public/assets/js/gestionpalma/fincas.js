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

const URL_FINCAS = BASE_URL + 'gestion-palma/fincas/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroEstado = () => $id('filtro_estado').value;

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_fincas')) {
        $('#tabla_fincas').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_fincas').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_fincas').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [7, 9], orderable: false }],
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
    $id('contador_fincas').innerHTML =
        `<i class="bi bi-tree me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_fincas').innerHTML = result.tabla || '';
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
        const response = await fetch(URL_FINCAS + accion, { method: 'POST', body });
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

        const response = await fetch(URL_FINCAS + 'listar', { method: 'POST', body });
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

// ── Ciudad (typeahead) ────────────────────────────────────────────────────────

const inputCiudad = $id('finca_ciudad_buscar');
const resultadosCiudad = $id('finca_ciudad_resultados');
let temporizadorCiudad;

const alternarLimpiarCiudad = () => {
    $id('finca_ciudad_limpiar').classList.toggle('d-none', $id('finca_ciudad').value === '');
};

const limpiarCiudad = () => {
    $id('finca_ciudad').value = '';
    inputCiudad.value = '';
    resultadosCiudad.innerHTML = '';
    $id('finca_ciudad_ok').classList.add('d-none');
    $id('finca_ciudad_error').classList.add('d-none');
    alternarLimpiarCiudad();
};

const fijarCiudad = (codigo, texto) => {
    $id('finca_ciudad').value = codigo;
    inputCiudad.value = texto;
    resultadosCiudad.innerHTML = '';
    $id('finca_ciudad_ok').classList.toggle('d-none', codigo === '');
    $id('finca_ciudad_error').classList.add('d-none');
    alternarLimpiarCiudad();
};

inputCiudad.addEventListener('input', () => {
    $id('finca_ciudad').value = '';
    $id('finca_ciudad_ok').classList.add('d-none');
    $id('finca_ciudad_error').classList.add('d-none');
    alternarLimpiarCiudad();
    clearTimeout(temporizadorCiudad);

    const termino = inputCiudad.value.trim();
    if (termino.length < 2) {
        resultadosCiudad.innerHTML = '';
        return;
    }

    temporizadorCiudad = setTimeout(async () => {
        try {
            const body = new FormData();
            body.append('termino', termino);
            body.append('empresa', $id('finca_empresa').value);

            const response = await fetch(URL_FINCAS + 'buscar-ciudad', { method: 'POST', body });
            const result = await response.json().catch(() => null);

            resultadosCiudad.innerHTML = '';
            if (!response.ok || !result || result.success === false) return;

            (result.ciudades || []).slice(0, 10).forEach((ciudad) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = ciudad.nombre;
                const departamento = document.createElement('small');
                departamento.className = 'text-muted d-block';
                departamento.textContent = ciudad.nombreDepartamento || '';
                item.appendChild(departamento);
                item.addEventListener('click', () => fijarCiudad(
                    ciudad.codigo,
                    ciudad.nombreDepartamento ? `${ciudad.nombre} (${ciudad.nombreDepartamento})` : ciudad.nombre
                ));
                resultadosCiudad.appendChild(item);
            });

            if (result.hayMas) {
                const aviso = document.createElement('div');
                aviso.className = 'list-group-item disabled small text-muted';
                aviso.textContent = 'Refine la búsqueda para ver más resultados.';
                resultadosCiudad.appendChild(aviso);
            }
        } catch (error) {
            resultadosCiudad.innerHTML = '';
        }
    }, 300);
});

$id('finca_ciudad_limpiar').addEventListener('click', limpiarCiudad);

document.addEventListener('click', (e) => {
    if (!resultadosCiudad.contains(e.target) && e.target !== inputCiudad) {
        resultadosCiudad.innerHTML = '';
    }
});

// ── Modal ─────────────────────────────────────────────────────────────────────

const modalFinca = () => bootstrap.Modal.getOrCreateInstance($id('modalFinca'));

const bloquearLlave = (bloquear) => {
    $id('finca_empresa').disabled = bloquear;
    $id('finca_codigo').disabled = bloquear;
    $id('finca_aviso_llave').classList.toggle('d-none', !bloquear);
};

const seleccionarPropietario = (id, nombre) => {
    const select = $id('finca_propietario');
    select.querySelectorAll('option[data-inactivo]').forEach((opcion) => opcion.remove());

    const valor = id === null || id === undefined ? '' : String(id);
    if (valor === '') {
        select.value = '';
        return;
    }

    if (!select.querySelector(`option[value="${CSS.escape(valor)}"]`)) {
        const opcion = document.createElement('option');
        opcion.value = valor;
        opcion.dataset.inactivo = '1';
        opcion.textContent = `${nombre || valor} (inactivo)`;
        select.options[0].after(opcion);
    }

    select.value = valor;
};

const prepararFormulario = () => {
    $id('formulario_finca').reset();
    limpiarCiudad();
    seleccionarPropietario('', '');
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('finca_modo').value = 'crear';
    $id('titulo_modal_finca').innerHTML = '<i class="bi bi-tree me-1"></i>Nueva finca';
    $id('finca_empresa').value = filtroEmpresa();
    $id('finca_activo').checked = true;
    bloquearLlave(false);
    modalFinca().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

$id('finca_empresa').addEventListener('change', limpiarCiudad);

window.editarFinca = async (empresa, codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);

        const response = await fetch(URL_FINCAS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró la finca.');
            return;
        }

        const finca = result.finca;
        prepararFormulario();
        $id('finca_modo').value = 'editar';
        $id('titulo_modal_finca').innerHTML = '<i class="bi bi-tree me-1"></i>Editar finca';
        $id('finca_empresa').value = finca.empresa;
        $id('finca_codigo').value = finca.codigo;
        $id('finca_empresa_pk').value = finca.empresa;
        $id('finca_codigo_pk').value = finca.codigo;
        $id('finca_descripcion').value = finca.descripcion || '';
        $id('finca_codigo_eq').value = finca.codigoEquivalencia || '';
        $id('finca_hectareas').value = finca.hectareas ?? '';
        $id('finca_zona').value = finca.zonaGeografica || '';
        $id('finca_centro_operacion').value = finca.centroOperacion || '';
        $id('finca_activo').checked = Number(finca.activo) === 1;
        $id('finca_interna').checked = Number(finca.interna) === 1;
        $id('finca_socio').checked = Number(finca.socio) === 1;
        seleccionarPropietario(finca.proveedor ?? '', finca.propietarioNombre);
        if (finca.ciudad) fijarCiudad(finca.ciudad, finca.nombreCiudad || finca.ciudad);
        bloquearLlave(true);
        modalFinca().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_finca').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (inputCiudad.value.trim() !== '' && $id('finca_ciudad').value === '') {
        $id('finca_ciudad_error').classList.remove('d-none');
        inputCiudad.focus();
        return;
    }

    const btn = $id('btn_guardar_finca');
    const edicion = $id('finca_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('finca_empresa_pk').value : $id('finca_empresa').value,
        codigo: edicion ? $id('finca_codigo_pk').value : $id('finca_codigo').value,
        descripcion: $id('finca_descripcion').value,
        proveedor: $id('finca_propietario').value,
        ciudad: $id('finca_ciudad').value,
        hectareas: $id('finca_hectareas').value,
        zonaGeografica: $id('finca_zona').value,
        codigoEquivalencia: $id('finca_codigo_eq').value,
        centroOperacion: $id('finca_centro_operacion').value,
        activo: $id('finca_activo').checked ? 1 : 0,
        interna: $id('finca_interna').checked ? 1 : 0,
        socio: $id('finca_socio').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalFinca().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.cambiarEstadoFinca = async (empresa, codigo) => {
    const fila = document.querySelector(`#cuerpo_tabla_fincas tr[data-empresa="${CSS.escape(empresa)}"][data-codigo="${CSS.escape(codigo)}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado de la' : (activo ? 'inactivar la' : 'activar la');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} finca seleccionada.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, ...filtrosActuales() });
    }
};

window.eliminarFinca = async (empresa, codigo, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar finca?',
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
    actualizarEstado($id('cuerpo_tabla_fincas').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_fincas').querySelector('strong').textContent);
});
