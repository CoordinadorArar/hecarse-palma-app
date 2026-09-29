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

const URL_EMPLEADOS = BASE_URL + 'gestion-palma/nomina/empleados/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroEstado = () => $id('filtro_estado').value;
const filtroVinculo = () => $id('filtro_vinculo').value;
const filtroBusqueda = () => $id('filtro_busqueda').value.trim();

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_empleados')) {
        $('#tabla_empleados').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_empleados').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_empleados').DataTable({
        searching: false,
        columnDefs: [{ targets: [5, 7], orderable: false }],
        order: [[1, 'asc']],
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
    $id('contador_empleados').innerHTML =
        `<i class="bi bi-people me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_empleados').innerHTML = result.tabla || '';
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
        const response = await fetch(URL_EMPLEADOS + accion, { method: 'POST', body });
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
    filtro_busqueda: filtroBusqueda(),
    filtro_estado: filtroEstado(),
    filtro_vinculo: filtroVinculo()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('busqueda', filtroBusqueda());
        body.append('estado', filtroEstado());
        body.append('vinculo', filtroVinculo());

        const response = await fetch(URL_EMPLEADOS + 'listar', { method: 'POST', body });
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
$id('filtro_estado').addEventListener('change', listar);
$id('filtro_vinculo').addEventListener('change', listar);
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

const modalEmpleado = () => bootstrap.Modal.getOrCreateInstance($id('modalEmpleado'));

$('#empleado_tercero').select2({
    width: '100%',
    dropdownParent: $('#modalEmpleado'),
    placeholder: 'Seleccione una opción…',
    allowClear: true,
    language: {
        noResults: () => 'No se encontraron resultados',
        searching: () => 'Buscando…'
    },
    templateResult: (opcion) => {
        if (!opcion.id) return opcion.text;
        const nit = $(opcion.element).data('nit') || '';
        return $(`<span>${opcion.text}<span class="gp-subtexto font-monospace">${nit}</span></span>`);
    }
});

const marcarCampo = (columna, activo) => {
    $id(columna).classList.toggle('gp-campo-activo', activo);
    $id(columna).classList.toggle('gp-campo-condicionado', !activo);
};

const CAMPOS_TERCERO = ['codigo', 'tipo_doc', 'apellido1', 'apellido2', 'nombre1', 'nombre2', 'descripcion', 'telefono', 'direccion'];

const nombreCompletoTercero = () => ['apellido1', 'apellido2', 'nombre1', 'nombre2']
    .map((campo) => $id('empleado_nt_' + campo).value.trim())
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim();

const refrescarTerceroNuevo = () => {
    const activo = !$id('empleado_nt_codigo').disabled;
    const completo = nombreCompletoTercero();
    const descripcion = $id('empleado_nt_descripcion');

    $id('empleado_nt_nombre_completo').textContent = completo === '' ? '' : `Nombre completo: ${completo}`;
    if (descripcion.dataset.tocado !== '1') descripcion.value = completo;

    const obligatoria = activo && completo === '';
    descripcion.required = obligatoria;
    $id('empleado_nt_descripcion_req').classList.toggle('d-none', !obligatoria);
};

['apellido1', 'apellido2', 'nombre1', 'nombre2'].forEach((campo) => {
    $id('empleado_nt_' + campo).addEventListener('input', refrescarTerceroNuevo);
});
$id('empleado_nt_descripcion').addEventListener('input', (e) => { e.target.dataset.tocado = '1'; });

const aplicarOpcionTercero = () => {
    const crea = $id('empleado_crea_tercero').checked;
    const bloque = $id('empleado_bloque_tercero');

    $id('empleado_ayuda_opcion').textContent = crea
        ? 'Se creará un tercero nuevo con los datos del recuadro. El selector de terceros queda inactivo.'
        : 'Se vinculará un tercero ya existente. Escoja el tercero y la identificación y la descripción se completan solas.';

    $id('empleado_panel_opcion').querySelector('.gp-panel-opcion').classList.toggle('activo', crea);

    if (crea) $('#empleado_tercero').val('');
    $('#empleado_tercero').prop('disabled', crea).trigger('change.select2');
    marcarCampo('col_empleado_tercero', !crea);

    $id('col_empleado_tercero').classList.toggle('d-none', crea);
    $id('empleado_tercero_vacio').classList.toggle('d-none', crea || $id('empleado_tercero').options.length > 1);

    ['identificacion', 'descripcion'].forEach((campo) => {
        $id('col_empleado_' + campo).classList.toggle('d-none', crea);
        $id('empleado_' + campo).disabled = crea;
        marcarCampo('col_empleado_' + campo, false);
    });

    $id('empleado_aviso_nuevo').classList.toggle('d-none', !crea);
    bloque.classList.toggle('d-none', !crea);
    bloque.classList.remove('gp-revelar');
    CAMPOS_TERCERO.forEach((campo) => { $id('empleado_nt_' + campo).disabled = !crea; });
    $id('empleado_nt_codigo').required = crea;
    $id('empleado_nt_tipo_doc').required = crea;
    refrescarTerceroNuevo();

    if (crea) {
        void bloque.offsetWidth;
        bloque.classList.add('gp-revelar');
        bloque.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        $id('empleado_nt_codigo').focus();
    }
};

$id('empleado_crea_tercero').addEventListener('change', aplicarOpcionTercero);
aplicarOpcionTercero();

$('#empleado_tercero').on('change', async function () {
    if ($id('empleado_modo').value === 'editar' || $id('empleado_crea_tercero').checked) return;

    const tercero = this.value;
    if (!tercero) {
        $id('empleado_identificacion').value = '';
        $id('empleado_descripcion').value = '';
        return;
    }

    try {
        const body = new FormData();
        body.append('empresa', $id('empleado_empresa').value);
        body.append('tercero', tercero);

        const response = await fetch(URL_EMPLEADOS + 'buscar-tercero', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message);
            return;
        }

        $id('empleado_identificacion').value = result.tercero.nit || '';
        $id('empleado_descripcion').value = result.tercero.nombre || '';
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
});

const bloquearLlave = (bloquear) => {
    if (bloquear) {
        $id('empleado_crea_tercero').checked = false;
        aplicarOpcionTercero();
    }
    $id('empleado_empresa').disabled = bloquear;
    $('#empleado_tercero').prop('disabled', bloquear).trigger('change.select2');
    $id('empleado_panel_opcion').classList.toggle('d-none', bloquear);
    $id('empleado_aviso_llave').classList.toggle('d-none', !bloquear);
    $id('col_empleado_empresa').classList.toggle('gp-campo-condicionado', bloquear);
    $id('col_empleado_tercero').classList.toggle('gp-campo-condicionado', bloquear);
    $id('col_empleado_tercero').classList.toggle('gp-campo-activo', false);
    if (bloquear) $id('empleado_tercero_vacio').classList.add('d-none');
};

const asegurarOpcion = (select, valor, texto, nit) => {
    if (!valor || Array.from(select.options).some((opcion) => opcion.value === valor)) return;
    const opcion = new Option(texto, valor);
    if (nit !== undefined) opcion.dataset.nit = nit;
    select.add(opcion);
};

const repoblarCatalogos = (empresa) => {
    const selectTercero = $id('empleado_tercero');
    const terceros = TERCEROS_DISPONIBLES[empresa] || [];
    selectTercero.innerHTML = '<option value=""></option>';
    terceros.forEach((tercero) => asegurarOpcion(selectTercero, String(tercero.id), tercero.nombre || '', String(tercero.nit || '').trim()));
    $id('empleado_tercero_vacio').classList.toggle('d-none', terceros.length > 0);
    $('#empleado_tercero').val('').trigger('change.select2');

    const selectProveedor = $id('empleado_proveedor');
    selectProveedor.innerHTML = '<option value="">Seleccione…</option>';
    (PROVEEDORES[empresa] || []).forEach((proveedor) => asegurarOpcion(selectProveedor, String(proveedor.nit || '').trim(), proveedor.nombre || ''));
    $id('empleado_proveedor_legado').classList.add('d-none');
};

const fijarProveedor = (codigo, nombre) => {
    const valor = String(codigo ?? '').trim();
    const legado = valor !== '' && !nombre;
    if (valor) asegurarOpcion($id('empleado_proveedor'), valor, legado ? `— código heredado: ${valor} —` : nombre);
    $id('empleado_proveedor').value = valor;
    $id('empleado_proveedor_legado').classList.toggle('d-none', !legado);
};

$id('empleado_empresa').addEventListener('change', () => {
    repoblarCatalogos($id('empleado_empresa').value);
    if (!$id('empleado_crea_tercero').checked) {
        $id('empleado_identificacion').value = '';
        $id('empleado_descripcion').value = '';
    }
});

const abrirNuevo = () => {
    $id('formulario_empleado').reset();
    $id('empleado_modo').value = 'crear';
    $id('titulo_modal_empleado').innerHTML = '<i class="bi bi-person-badge me-1"></i>Nuevo empleado';
    $id('empleado_empresa').value = filtroEmpresa();
    $id('empleado_identificacion').value = '';
    $id('empleado_descripcion').value = '';
    $id('empleado_salario').value = 0;
    $id('empleado_fecha_ingreso').value = new Date().toISOString().slice(0, 10);
    $id('empleado_activo').checked = true;
    delete $id('empleado_nt_descripcion').dataset.tocado;
    repoblarCatalogos($id('empleado_empresa').value);
    bloquearLlave(false);
    $id('empleado_crea_tercero').checked = false;
    aplicarOpcionTercero();
    modalEmpleado().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

window.editarEmpleado = async (empresa, tercero) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('tercero', tercero);

        const response = await fetch(URL_EMPLEADOS + 'obtener', { method: 'POST', body });
        const result = await response.json();

        if (!response.ok || !result.success) {
            avisar(response, result.message || 'No se encontró el empleado.');
            return;
        }

        const empleado = result.empleado;
        $id('formulario_empleado').reset();
        $id('empleado_modo').value = 'editar';
        $id('titulo_modal_empleado').innerHTML = '<i class="bi bi-person-badge me-1"></i>Editar empleado';
        $id('empleado_crea_tercero').checked = false;
        aplicarOpcionTercero();
        $id('empleado_empresa').value = empleado.empresa;
        $id('empleado_empresa_pk').value = empleado.empresa;
        $id('empleado_tercero_pk').value = empleado.tercero;
        repoblarCatalogos(empleado.empresa);
        asegurarOpcion($id('empleado_tercero'), String(empleado.tercero), empleado.terceroNombre || empleado.descripcion || '', String(empleado.terceroNit || '').trim());
        $('#empleado_tercero').val(String(empleado.tercero)).trigger('change.select2');
        $id('empleado_identificacion').value = empleado.codigo || '';
        $id('empleado_descripcion').value = empleado.descripcion || '';
        fijarProveedor(empleado.proveedor, empleado.proveedorNombre);
        $id('empleado_salario').value = empleado.salario ?? 0;
        $id('empleado_fecha_ingreso').value = empleado.fechaIngreso || '';
        $id('empleado_activo').checked = Number(empleado.activo) === 1;
        $id('empleado_contratista').checked = Number(empleado.contratista) === 1;
        $id('empleado_conductor').checked = Number(empleado.conductor) === 1;
        $id('empleado_otros').checked = Number(empleado.otros) === 1;
        bloquearLlave(true);
        modalEmpleado().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_empleado').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $id('btn_guardar_empleado');
    const edicion = $id('empleado_modo').value === 'editar';
    const crea = $id('empleado_crea_tercero').checked;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const nuevoTercero = crea && !edicion ? {
        t_codigo: $id('empleado_nt_codigo').value,
        t_tipoDocumento: $id('empleado_nt_tipo_doc').value,
        t_apellido1: $id('empleado_nt_apellido1').value,
        t_apellido2: $id('empleado_nt_apellido2').value,
        t_nombre1: $id('empleado_nt_nombre1').value,
        t_nombre2: $id('empleado_nt_nombre2').value,
        t_descripcion: $id('empleado_nt_descripcion').value,
        t_telefono: $id('empleado_nt_telefono').value,
        t_direccion: $id('empleado_nt_direccion').value
    } : {};

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('empleado_empresa_pk').value : $id('empleado_empresa').value,
        tercero: edicion ? $id('empleado_tercero_pk').value : $id('empleado_tercero').value,
        crea_tercero: crea && !edicion ? 1 : 0,
        identificacion: $id('empleado_identificacion').value,
        descripcion: $id('empleado_descripcion').value,
        proveedor: $id('empleado_proveedor').value,
        salario: $id('empleado_salario').value,
        fechaIngreso: $id('empleado_fecha_ingreso').value,
        activo: $id('empleado_activo').checked ? 1 : 0,
        contratista: $id('empleado_contratista').checked ? 1 : 0,
        conductor: $id('empleado_conductor').checked ? 1 : 0,
        otros: $id('empleado_otros').checked ? 1 : 0,
        ...nuevoTercero,
        ...filtrosActuales()
    });

    if (ok) modalEmpleado().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.eliminarEmpleado = async (empresa, tercero, etiqueta) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar empleado?',
        text: `Se eliminará «${etiqueta}». Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, tercero, ...filtrosActuales() });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_empleados').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_empleados').querySelector('strong').textContent);
});
