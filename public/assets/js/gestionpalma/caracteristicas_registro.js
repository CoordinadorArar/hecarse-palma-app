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

const URL_CARACTERISTICAS = BASE_URL + 'gestion-palma/caracteristicas/registro/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroGrupo = () => $id('filtro_grupo').value;
const filtroEstado = () => $id('filtro_estado').value;
const filtroBusqueda = () => $id('filtro_busqueda').value.trim();

// ── Tabla ─────────────────────────────────────────────────────────────────────

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_caracteristicas')) {
        $('#tabla_caracteristicas').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_caracteristicas').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_caracteristicas').DataTable({
        paging: false,
        info: false,
        searching: false,
        lengthChange: false,
        columnDefs: [{ targets: [5], orderable: false }],
        order: [[1, 'asc'], [2, 'asc']],
        language: {
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay datos disponibles'
        }
    });
};

const actualizarEstado = (total) => {
    const vacio = Number(total) === 0;
    $id('contador_caracteristicas').innerHTML =
        `<i class="bi bi-bug me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

let variantes = VARIANTES;

const aplicarRespuesta = (result) => {
    if (result.variantes !== undefined && result.variantes !== null) {
        variantes = result.variantes;
    }
    destruirTabla();
    $id('cuerpo_tabla_caracteristicas').innerHTML = result.tabla ?? '';
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
    empresa: filtroEmpresa(),
    f_grupo: filtroGrupo(),
    f_estado: filtroEstado(),
    f_busqueda: filtroBusqueda()
});

const enviar = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));

    try {
        const response = await fetch(URL_CARACTERISTICAS + accion, { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result || result.success === false) {
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

const listar = async () => {
    try {
        const body = new FormData();
        Object.entries(filtrosActuales()).forEach(([clave, valor]) => body.append(clave, valor));

        const response = await fetch(URL_CARACTERISTICAS + 'listar', { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result || result.success === false) {
            avisar(response, result ? result.message : null);
            return;
        }

        aplicarRespuesta(result);
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

// ── Filtros ───────────────────────────────────────────────────────────────────

$id('btn_buscar').addEventListener('click', listar);
['filtro_empresa', 'filtro_grupo', 'filtro_estado'].forEach((id) => {
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

const modalCaracteristica = () => bootstrap.Modal.getOrCreateInstance($id('modalCaracteristica'));

$('#car_grupo').select2({
    width: '100%',
    dropdownParent: $('#modalCaracteristica'),
    placeholder: 'Seleccione un grupo',
    allowClear: true,
    language: {
        noResults: () => 'No se encontraron resultados',
        searching: () => 'Buscando…'
    }
});

let grupoRecordado = '';
let duplicadoBloqueante = false;

const valorGrupo = () => {
    const valor = $id('car_grupo').value;
    return valor === '' ? null : valor;
};

const descripcionGrupo = (codigo) => {
    if (codigo === null) return null;
    const grupo = GRUPOS.find((g) => g.codigo === String(codigo));
    return grupo ? grupo.descripcion : null;
};

const normalizar = (texto) => String(texto ?? '').replace(/\s+/gu, ' ').trim().toUpperCase();

const codigoGrupo = (valor) => (valor === null || valor === undefined || valor === '' ? null : String(valor));

const datosFila = (fila) => {
    const celdas = fila.querySelectorAll('td');
    const celdaDesc = celdas[1];
    const rotulo = celdaDesc ? celdaDesc.querySelector('[title]') : null;
    const grupo = codigoGrupo(fila.dataset.grupo);
    return {
        codigo: fila.dataset.codigo ?? '',
        descripcion: (rotulo ? rotulo.getAttribute('title') : (celdaDesc ? celdaDesc.textContent : '')).trim(),
        grupo,
        grupoDescripcion: descripcionGrupo(grupo),
        activo: fila.dataset.activo === '1' || (fila.dataset.activo === undefined && fila.querySelector('.gp-badge-abierto') !== null)
    };
};

const variantesDe = (texto) => {
    const clave = normalizar(texto);
    if (Array.isArray(variantes[clave])) return variantes[clave];
    const encontrada = Object.keys(variantes).find((k) => normalizar(k) === clave);
    return encontrada === undefined ? [] : variantes[encontrada];
};

const revisarDuplicado = () => {
    const contenedor = $id('car_aviso_duplicado');
    const caja = $id('car_aviso_duplicado_texto');
    duplicadoBloqueante = false;

    const escrito = $id('car_descripcion').value.trim();
    const conGrupo = $id('car_maneja_grupo').checked;
    const grupo = conGrupo ? valorGrupo() : null;
    const grupoDesc = descripcionGrupo(grupo);

    if (escrito === '' || (conGrupo && grupoDesc === null)) {
        contenedor.classList.add('d-none');
        return;
    }

    const codigoEditado = $id('car_modo').value === 'editar' ? String($id('car_codigo_pk').value) : null;
    const otros = [];
    let mismo = false;

    variantesDe(escrito).forEach((variante) => {
        if (codigoEditado !== null && String(variante.codigo) === codigoEditado) return;
        const suGrupo = codigoGrupo(variante.grupo);
        if (suGrupo === grupo) {
            mismo = true;
            return;
        }
        if (suGrupo === null) return;
        const suDescripcion = variante.grupoDescripcion ?? descripcionGrupo(suGrupo);
        if (suDescripcion !== null && !otros.includes(suDescripcion)) {
            otros.push(suDescripcion);
        }
    });

    if (mismo) {
        duplicadoBloqueante = true;
        caja.className = 'gp-nota-alerta';
        caja.innerHTML = conGrupo
            ? `<i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Ya existe «${escrito}» en este grupo. Cambie la descripción o elija otro grupo.`
            : `<i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Ya existe «${escrito}» sin grupo. Cambie la descripción o asigne un grupo.`;
        contenedor.classList.remove('d-none');
        return;
    }

    if (conGrupo && otros.length > 0) {
        caja.className = 'gp-nota';
        caja.innerHTML = `<i class="bi bi-info-circle me-1"></i>Esta descripción ya existe en ${otros.join(', ')}. Es válido registrarla también en este grupo.`;
        contenedor.classList.remove('d-none');
        return;
    }

    contenedor.classList.add('d-none');
};

const aplicarManejaGrupo = () => {
    const con = $id('car_maneja_grupo').checked;
    const panel = $id('car_panel_grupo');

    if (con) {
        $('#car_grupo').prop('disabled', false).val(grupoRecordado).trigger('change.select2');
    } else {
        grupoRecordado = $id('car_grupo').value;
        $('#car_grupo').prop('disabled', true).trigger('change.select2');
    }

    panel.classList.toggle('activo', con);
    panel.classList.toggle('inhabilitado', !con);
    $id('car_nota_sin_grupo').classList.toggle('d-none', con);
    revisarDuplicado();
};

const bloquearLlave = (bloquear) => {
    $id('car_empresa').disabled = bloquear;
    $id('car_codigo').disabled = bloquear;
    $id('car_aviso_llave').classList.toggle('d-none', !bloquear);
    $id('car_ayuda_codigo').classList.toggle('d-none', bloquear);
};

const proponerCodigo = async (empresa) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);

        const response = await fetch(URL_CARACTERISTICAS + 'siguiente-codigo', { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (response.ok && result && result.success && $id('car_modo').value === 'crear') {
            $id('car_codigo').value = result.codigo ?? '';
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

const prepararFormulario = () => {
    $id('formulario_caracteristica').reset();
    grupoRecordado = '';
    duplicadoBloqueante = false;
    $id('car_aviso_duplicado').classList.add('d-none');
    $('#car_grupo').prop('disabled', false).val('').trigger('change.select2');
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('car_modo').value = 'crear';
    $id('titulo_modal_caracteristica').innerHTML = '<i class="bi bi-bug me-1"></i>Nueva característica';
    $id('car_empresa').value = filtroEmpresa();
    $id('car_codigo').value = SIGUIENTE_CODIGO;
    $id('car_activo').checked = true;
    $id('car_maneja_grupo').checked = true;
    const grupoFiltrado = filtroGrupo();
    if (grupoFiltrado !== '') {
        grupoRecordado = grupoFiltrado;
    }
    aplicarManejaGrupo();
    bloquearLlave(false);
    modalCaracteristica().show();
    proponerCodigo($id('car_empresa').value);
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

$id('car_empresa').addEventListener('change', () => {
    if ($id('car_modo').value === 'crear') {
        proponerCodigo($id('car_empresa').value);
    }
});

$id('car_maneja_grupo').addEventListener('change', aplicarManejaGrupo);
$id('car_descripcion').addEventListener('input', revisarDuplicado);
$('#car_grupo').on('change', revisarDuplicado);

const editar = async (codigo) => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('codigo', codigo);

        const response = await fetch(URL_CARACTERISTICAS + 'obtener', { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result || result.success === false || !result.caracteristica) {
            avisar(response, result ? result.message : 'No se encontró la característica.');
            return;
        }

        const caracteristica = result.caracteristica;
        prepararFormulario();
        $id('car_modo').value = 'editar';
        $id('titulo_modal_caracteristica').innerHTML = '<i class="bi bi-bug me-1"></i>Editar característica';
        $id('car_empresa').value = filtroEmpresa();
        $id('car_empresa_pk').value = filtroEmpresa();
        $id('car_codigo').value = caracteristica.codigo;
        $id('car_codigo_pk').value = caracteristica.codigo;
        $id('car_descripcion').value = caracteristica.descripcion ?? '';
        $id('car_activo').checked = Number(caracteristica.activo) === 1;

        grupoRecordado = codigoGrupo(caracteristica.grupo) ?? '';
        $('#car_grupo').val(grupoRecordado).trigger('change.select2');
        $id('car_maneja_grupo').checked = Number(caracteristica.manejaGrupo) === 1;
        aplicarManejaGrupo();
        bloquearLlave(true);
        modalCaracteristica().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_caracteristica').addEventListener('submit', async (e) => {
    e.preventDefault();

    const conGrupo = $id('car_maneja_grupo').checked;
    const grupo = conGrupo ? valorGrupo() : null;

    if (conGrupo && grupo === null) {
        Toast.fire({ icon: 'warning', title: 'Seleccione el grupo característico.' });
        return;
    }

    revisarDuplicado();
    if (duplicadoBloqueante) {
        Toast.fire({ icon: 'warning', title: 'Ya existe esa descripción en este grupo.' });
        return;
    }

    const btn = $id('btn_guardar_caracteristica');
    const edicion = $id('car_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        ...filtrosActuales(),
        empresa: edicion ? $id('car_empresa_pk').value : $id('car_empresa').value,
        codigo: edicion ? $id('car_codigo_pk').value : $id('car_codigo').value,
        descripcion: $id('car_descripcion').value.trim(),
        manejaGrupo: conGrupo ? 1 : 0,
        grupo: grupo === null ? '' : grupo,
        activo: $id('car_activo').checked ? 1 : 0
    });

    if (ok) modalCaracteristica().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

const cambiarEstado = async (fila, codigo) => {
    const datos = datosFila(fila);

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${datos.activo ? 'inactivar' : 'activar'} la característica «${datos.descripcion}».`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', {
            ...filtrosActuales(),
            codigo,
            activo: datos.activo ? 0 : 1
        });
    }
};

const eliminar = async (fila, codigo) => {
    const datos = datosFila(fila);
    const texto = datos.grupoDescripcion === null
        ? `Se eliminará «${datos.descripcion}». Esta acción no se puede deshacer.`
        : `Se eliminará «${datos.descripcion}» del grupo ${datos.grupoDescripcion}. Esta acción no se puede deshacer.`;

    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar característica?',
        text: texto,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { ...filtrosActuales(), codigo });
    }
};

$id('cuerpo_tabla_caracteristicas').addEventListener('click', (e) => {
    const boton = e.target.closest('[data-accion]');
    if (!boton) return;

    const fila = boton.closest('tr');
    const codigo = boton.dataset.codigo ?? (fila ? fila.dataset.codigo : '');

    if (boton.dataset.accion === 'editar') editar(codigo);
    if (boton.dataset.accion === 'estado') cambiarEstado(fila, codigo);
    if (boton.dataset.accion === 'eliminar') eliminar(fila, codigo);
});

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_caracteristicas').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_caracteristicas').querySelector('strong').textContent);
});
