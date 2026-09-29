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

const URL_TERCEROS = BASE_URL + 'gestion-palma/contabilidad/terceros/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroTipo = () => $id('filtro_tipo').value;
const filtroRol = () => $id('filtro_rol').value;
const filtroEstado = () => $id('filtro_estado').value;
const filtroBusqueda = () => $id('filtro_busqueda').value.trim();

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_terceros')) {
        $('#tabla_terceros').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_terceros').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_terceros').DataTable({
        searching: false,
        pageLength: 25,
        columnDefs: [{ targets: [4, 6], orderable: false }],
        order: [[2, 'asc']],
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
    $id('contador_terceros').innerHTML =
        `<i class="bi bi-list-ol me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_terceros').innerHTML = result.tabla || '';
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

const enviar = async (accion, datos, manejarError) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));

    try {
        const response = await fetch(URL_TERCEROS + accion, { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result || result.success === false || typeof result.tabla === 'undefined') {
            if (manejarError && await manejarError(response, result)) return false;
            avisar(response, result ? result.message : null);
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
    filtro_tipo: filtroTipo(),
    filtro_rol: filtroRol(),
    filtro_estado: filtroEstado(),
    filtro_busqueda: filtroBusqueda()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('tipo', filtroTipo());
        body.append('rol', filtroRol());
        body.append('estado', filtroEstado());
        body.append('busqueda', filtroBusqueda());

        const response = await fetch(URL_TERCEROS + 'listar', { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result || result.success === false || typeof result.tabla === 'undefined') {
            avisar(response, result ? result.message : null);
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
};

// ── Filtros ───────────────────────────────────────────────────────────────────

$id('btn_buscar').addEventListener('click', listar);
['filtro_empresa', 'filtro_tipo', 'filtro_rol', 'filtro_estado'].forEach((id) => {
    $id(id).addEventListener('change', listar);
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

const modalTercero = () => bootstrap.Modal.getOrCreateInstance($id('modalTercero'));

const calcularDV = (nit) => {
    const pesos = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
    const digitos = String(nit).replace(/\D/g, '').padStart(15, '0').split('');

    const suma = digitos.reduce((acc, digito, i) => acc + (parseInt(digito, 10) * pesos[i]), 0);
    const residuo = suma % 11;

    return (residuo === 0 || residuo === 1) ? residuo : 11 - residuo;
};

const nombreCompleto = () => ['tercero_apellido1', 'tercero_apellido2', 'tercero_nombre1', 'tercero_nombre2']
    .map((id) => $id(id).value.trim())
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim();

const marcarTocado = (id) => $id(id).addEventListener('input', (e) => { e.target.dataset.tocado = '1'; });
['tercero_codigo', 'tercero_descripcion'].forEach(marcarTocado);

const refrescarDerivados = () => {
    const juridica = $id('tercero_tipo').value === '2';
    const completo = nombreCompleto();

    $id('tercero_nombre_completo').textContent = juridica || completo === '' ? '' : `Nombre completo: ${completo}`;

    if ($id('tercero_codigo').dataset.tocado !== '1') {
        $id('tercero_codigo').value = $id('tercero_nit').value.trim();
    }
    if ($id('tercero_descripcion').dataset.tocado !== '1') {
        $id('tercero_descripcion').value = juridica ? $id('tercero_razon_social').value.trim() : completo;
    }
};

const refrescarDV = () => {
    const aplica = $id('tercero_tipo_documento').value === '31';
    const nit = $id('tercero_nit').value.trim();
    $id('col_tercero_dv').classList.toggle('d-none', !aplica);
    $id('tercero_dv').value = aplica && nit !== '' ? calcularDV(nit) : '';
};

const aplicarTipoPersona = (sugerir) => {
    const juridica = $id('tercero_tipo').value === '2';
    $id('bloque_natural').classList.toggle('d-none', juridica);
    $id('bloque_juridica').classList.toggle('d-none', !juridica);
    $id('tercero_razon_social').required = juridica;

    if (sugerir && $id('tercero_tipo_documento').value === '') {
        $id('tercero_tipo_documento').value = juridica ? '31' : '13';
    }
    refrescarDV();
    refrescarDerivados();
};

$id('tercero_tipo').addEventListener('change', () => aplicarTipoPersona(true));
$id('tercero_tipo_documento').addEventListener('change', refrescarDV);
$id('tercero_nit').addEventListener('input', () => {
    refrescarDV();
    refrescarDerivados();
});
['tercero_apellido1', 'tercero_apellido2', 'tercero_nombre1', 'tercero_nombre2', 'tercero_razon_social']
    .forEach((id) => $id(id).addEventListener('input', refrescarDerivados));

const bloquearLlave = (bloquear) => {
    $id('tercero_empresa').disabled = bloquear;
    $id('tercero_id').disabled = bloquear;
    $id('tercero_aviso_llave').classList.toggle('d-none', !bloquear);
};

const limpiarCiudad = () => {
    $id('tercero_ciudad').value = '';
    $id('tercero_ciudad_buscar').value = '';
    $id('tercero_ciudad_resultados').innerHTML = '';
    $id('tercero_ciudad_ok').classList.add('d-none');
};

const prepararFormulario = () => {
    $id('formulario_tercero').reset();
    limpiarCiudad();
    $id('tercero_fecha_registro').classList.add('d-none');
    $id('tercero_fecha_registro').textContent = '';
    ['tercero_codigo', 'tercero_descripcion'].forEach((id) => { delete $id(id).dataset.tocado; });
};

const abrirCreacion = async () => {
    prepararFormulario();
    $id('tercero_modo').value = 'crear';
    $id('titulo_modal_tercero').innerHTML = '<i class="bi bi-person-vcard me-1"></i>Nuevo tercero';
    $id('tercero_empresa').value = filtroEmpresa();
    $id('tercero_tipo').value = filtroTipo() === '2' ? '2' : '1';
    $id('tercero_tipo_documento').value = '';
    $id('tercero_id').value = '';
    bloquearLlave(false);
    aplicarTipoPersona(true);
    modalTercero().show();

    try {
        const body = new FormData();
        body.append('empresa', $id('tercero_empresa').value);
        const response = await fetch(URL_TERCEROS + 'siguiente-id', { method: 'POST', body });
        const result = await response.json().catch(() => null);
        if (response.ok && result && result.success !== false && typeof result.id !== 'undefined') {
            $id('tercero_id').value = result.id;
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('btn_nuevo').addEventListener('click', abrirCreacion);
$id('btn_nuevo_vacio').addEventListener('click', abrirCreacion);

const obtenerTercero = async (empresa, id) => {
    const body = new FormData();
    body.append('empresa', empresa);
    body.append('id', id);

    const response = await fetch(URL_TERCEROS + 'obtener', { method: 'POST', body });
    const result = await response.json().catch(() => null);

    if (!response.ok || !result || result.success === false || !result.tercero) {
        avisar(response, result ? result.message : 'No se encontró el tercero.');
        return null;
    }

    return result.tercero;
};

const formatearFecha = (valor) => {
    const fecha = new Date(String(valor).replace(' ', 'T'));
    if (isNaN(fecha)) return '';
    return fecha.toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' });
};

window.editarTercero = async (empresa, id) => {
    try {
        const tercero = await obtenerTercero(empresa, id);
        if (!tercero) return;

        prepararFormulario();
        $id('tercero_modo').value = 'editar';
        $id('titulo_modal_tercero').innerHTML = '<i class="bi bi-person-vcard me-1"></i>Editar tercero';
        $id('tercero_empresa').value = tercero.empresa;
        $id('tercero_id').value = tercero.id;
        $id('tercero_empresa_pk').value = tercero.empresa;
        $id('tercero_id_pk').value = tercero.id;
        $id('tercero_tipo').value = String(tercero.tipo || '1');
        $id('tercero_tipo_documento').value = tercero.tipoDocumento || '';
        $id('tercero_nit').value = tercero.nit || '';
        $id('tercero_codigo').value = tercero.codigo || '';
        $id('tercero_razon_social').value = tercero.razonSocial || '';
        $id('tercero_apellido1').value = tercero.apellido1 || '';
        $id('tercero_apellido2').value = tercero.apellido2 || '';
        $id('tercero_nombre1').value = tercero.nombre1 || '';
        $id('tercero_nombre2').value = tercero.nombre2 || '';
        $id('tercero_descripcion').value = tercero.descripcion || '';
        $id('tercero_departamento').value = tercero.departamento || '';
        $id('tercero_barrio').value = tercero.barrio || '';
        $id('tercero_direccion').value = tercero.direccion || '';
        $id('tercero_telefono').value = tercero.telefono || '';
        $id('tercero_fax').value = tercero.fax || '';
        $id('tercero_email').value = tercero.email || '';
        $id('tercero_contacto').value = tercero.contacto || '';
        $id('tercero_codigo_equivalencia').value = tercero.codigoEquivalencia || '';

        ['activo', 'cliente', 'proveedor', 'empleado', 'accionista', 'contratista', 'extractora', 'comercializadora']
            .forEach((campo) => { $id('tercero_' + campo).checked = Number(tercero[campo]) === 1; });

        if (tercero.ciudad) {
            $id('tercero_ciudad').value = tercero.ciudad;
            $id('tercero_ciudad_buscar').value = tercero.nombreDepartamento
                ? `${tercero.nombreCiudad} (${tercero.nombreDepartamento})`
                : (tercero.nombreCiudad || tercero.ciudad);
            $id('tercero_ciudad_ok').classList.remove('d-none');
        }

        ['tercero_codigo', 'tercero_descripcion'].forEach((campo) => { $id(campo).dataset.tocado = '1'; });
        bloquearLlave(true);
        aplicarTipoPersona(false);
        if (tercero.dv !== null && tercero.dv !== '') $id('tercero_dv').value = tercero.dv;

        const fecha = formatearFecha(tercero.fechaRegistro);
        if (fecha !== '') {
            $id('tercero_fecha_registro').textContent = `Registrado el ${fecha}`;
            $id('tercero_fecha_registro').classList.remove('d-none');
        }

        modalTercero().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

const datosFormulario = () => {
    const edicion = $id('tercero_modo').value === 'editar';
    const datos = {
        empresa: edicion ? $id('tercero_empresa_pk').value : $id('tercero_empresa').value,
        codigo: $id('tercero_codigo').value,
        tipoDocumento: $id('tercero_tipo_documento').value,
        tipo: $id('tercero_tipo').value,
        nit: $id('tercero_nit').value,
        dv: $id('tercero_dv').value,
        razonSocial: $id('tercero_razon_social').value,
        apellido1: $id('tercero_apellido1').value,
        apellido2: $id('tercero_apellido2').value,
        nombre1: $id('tercero_nombre1').value,
        nombre2: $id('tercero_nombre2').value,
        descripcion: $id('tercero_descripcion').value,
        ciudad: $id('tercero_ciudad').value,
        departamento: $id('tercero_departamento').value,
        telefono: $id('tercero_telefono').value,
        direccion: $id('tercero_direccion').value,
        barrio: $id('tercero_barrio').value,
        fax: $id('tercero_fax').value,
        email: $id('tercero_email').value,
        contacto: $id('tercero_contacto').value,
        codigoEquivalencia: $id('tercero_codigo_equivalencia').value
    };

    ['activo', 'cliente', 'proveedor', 'empleado', 'accionista', 'contratista', 'extractora', 'comercializadora']
        .forEach((campo) => { datos[campo] = $id('tercero_' + campo).checked ? 1 : 0; });

    if (edicion) datos.id = $id('tercero_id_pk').value;

    return { ...datos, ...filtrosActuales() };
};

$id('formulario_tercero').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $id('btn_guardar_tercero');
    const edicion = $id('tercero_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', datosFormulario());

    if (ok) modalTercero().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

const desactivarTercero = async (empresa, id) =>
    enviar('cambiar-estado', { empresa, id, activo: 0, ...filtrosActuales() });

window.eliminarTercero = async (empresa, id, descripcion) => {
    const fila = document.querySelector(`#cuerpo_tabla_terceros tr[data-empresa="${empresa}"][data-id="${id}"]`);
    const nit = fila ? (fila.dataset.nit || '') : '';

    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar tercero?',
        text: `Se eliminará «${descripcion}»${nit ? ` (Nit ${nit})` : ''}. Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) return;

    await enviar('eliminar', { empresa, id, ...filtrosActuales() }, async (response, result) => {
        if (response.status !== 422 || !result || !result.message || result.bloqueado !== true) return false;

        const salida = await Alerta.fire({
            icon: 'warning',
            title: 'Atención',
            text: result.message,
            showCancelButton: true,
            confirmButtonText: 'Desactivar tercero',
            cancelButtonText: 'Entendido'
        });

        if (salida.isConfirmed) await desactivarTercero(empresa, id);

        return true;
    });
};

// ── Ciudad (typeahead) ────────────────────────────────────────────────────────

const inputCiudad = $id('tercero_ciudad_buscar');
const resultadosCiudad = $id('tercero_ciudad_resultados');
let temporizadorCiudad;

inputCiudad.addEventListener('input', () => {
    $id('tercero_ciudad').value = '';
    $id('tercero_ciudad_ok').classList.add('d-none');
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
            body.append('departamento', $id('tercero_departamento').value);

            const response = await fetch(URL_TERCEROS + 'buscar-ciudad', { method: 'POST', body });
            const result = await response.json().catch(() => null);

            resultadosCiudad.innerHTML = '';
            if (!response.ok || !result || result.success === false) return;

            (result.ciudades || []).forEach((ciudad) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `${ciudad.nombre}<small class="text-muted d-block">${ciudad.nombreDepartamento || ''}</small>`;
                item.addEventListener('click', () => {
                    $id('tercero_ciudad').value = ciudad.codigo;
                    inputCiudad.value = ciudad.nombreDepartamento
                        ? `${ciudad.nombre} (${ciudad.nombreDepartamento})`
                        : ciudad.nombre;
                    if (ciudad.departamento) $id('tercero_departamento').value = ciudad.departamento;
                    $id('tercero_ciudad_ok').classList.remove('d-none');
                    resultadosCiudad.innerHTML = '';
                });
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

document.addEventListener('click', (e) => {
    if (!resultadosCiudad.contains(e.target) && e.target !== inputCiudad) {
        resultadosCiudad.innerHTML = '';
    }
});

$id('tercero_departamento').addEventListener('change', limpiarCiudad);

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_terceros').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_terceros').querySelector('strong').textContent);
});
