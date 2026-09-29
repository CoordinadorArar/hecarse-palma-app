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

const URL_LABORES = BASE_URL + 'gestion-palma/labores/registro/';

const $id = (id) => document.getElementById(id);

const filtroEmpresa = () => $id('filtro_empresa').value;
const filtroGrupo = () => $id('filtro_grupo').value;
const filtroEstado = () => $id('filtro_estado').value;
const filtroBusqueda = () => $id('filtro_busqueda').value.trim();

// ── Tabla de labores ─────────────────────────────────────────────────────────

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_labores')) {
        $('#tabla_labores').DataTable().destroy();
    }
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_labores').querySelectorAll('tr').length === 0) {
        return;
    }
    $('#tabla_labores').DataTable({
        searching: false,
        pageLength: 25,
        columnDefs: [{ targets: [10], orderable: false }],
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
    $id('contador_labores').innerHTML =
        `<i class="bi bi-clipboard-check me-1"></i><strong>${total}</strong> ${Number(total) === 1 ? 'registro encontrado' : 'registros encontrados'}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio);
};

const aplicarRespuesta = (result) => {
    destruirTabla();
    $id('cuerpo_tabla_labores').innerHTML = result.tabla || '';
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
    filtro_empresa: filtroEmpresa(),
    filtro_grupo: filtroGrupo(),
    filtro_estado: filtroEstado(),
    filtro_busqueda: filtroBusqueda()
});

const listar = async () => {
    try {
        const body = new FormData();
        body.append('empresa', filtroEmpresa());
        body.append('grupo', filtroGrupo());
        body.append('estado', filtroEstado());
        body.append('busqueda', filtroBusqueda());

        const response = await fetch(URL_LABORES + 'listar', { method: 'POST', body });
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

const enviar = async (accion, datos) => {
    const body = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => body.append(clave, valor));

    try {
        const response = await fetch(URL_LABORES + accion, { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result || result.success === false || typeof result.tabla === 'undefined') {
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

// ── Campos condicionales (gp-revelar) ────────────────────────────────────────

const revelar = (idSwitch, idsColumnas) => {
    const activo = $id(idSwitch).checked;
    idsColumnas.forEach((idColumna) => {
        const col = $id(idColumna);
        col.classList.toggle('d-none', !activo);
        if (activo) {
            col.classList.remove('gp-revelar');
            void col.offsetWidth;
            col.classList.add('gp-revelar');
        }
    });
};

const aplicarManejaRango = () => revelar('labor_maneja_rango', ['labor_col_anio_desde', 'labor_col_anio_hasta']);
const aplicarManejaCanal = () => revelar('labor_maneja_canal', ['labor_col_tipo_canal']);
const aplicarImpuesto = () => revelar('labor_impuesto', ['labor_col_grupo_ir']);

$id('labor_maneja_rango').addEventListener('change', aplicarManejaRango);
$id('labor_maneja_canal').addEventListener('change', aplicarManejaCanal);
$id('labor_impuesto').addEventListener('change', aplicarImpuesto);

// ── Autocompletado de la descripción corta ───────────────────────────────────

$id('labor_des_corta').addEventListener('input', (e) => { e.target.dataset.tocado = '1'; });
$id('labor_descripcion').addEventListener('input', () => {
    if ($id('labor_des_corta').dataset.tocado === '1') return;
    $id('labor_des_corta').value = $id('labor_descripcion').value.trim().slice(0, 50);
});

// ── Modal Labor ───────────────────────────────────────────────────────────────

const modalLabor = () => bootstrap.Modal.getOrCreateInstance($id('modalLabor'));

const bloquearLlave = (bloquear) => {
    $id('labor_empresa').disabled = bloquear;
    $id('labor_codigo').disabled = bloquear;
    $id('labor_concepto').disabled = bloquear;
    $id('labor_aviso_llave').classList.toggle('d-none', !bloquear);
};

const prepararFormulario = () => {
    $id('formulario_labor').reset();
    delete $id('labor_des_corta').dataset.tocado;
    aplicarManejaRango();
    aplicarManejaCanal();
    aplicarImpuesto();
};

const abrirNuevo = () => {
    prepararFormulario();
    $id('labor_modo').value = 'crear';
    $id('titulo_modal_labor').innerHTML = '<i class="bi bi-clipboard-check me-1"></i>Nueva labor';
    $id('labor_empresa').value = filtroEmpresa();
    $id('labor_grupo').value = filtroGrupo();
    $id('labor_activo').checked = true;
    bloquearLlave(false);
    modalLabor().show();
};

$id('btn_nuevo').addEventListener('click', abrirNuevo);
$id('btn_nuevo_vacio').addEventListener('click', abrirNuevo);

const asegurarOpcionClase = (valor, etiqueta) => {
    const select = $id('labor_clase');
    if (valor === null || valor === undefined || valor === '') return;
    const existe = Array.from(select.options).some((o) => o.value === String(valor));
    if (existe) return;
    const opcion = document.createElement('option');
    opcion.value = valor;
    opcion.textContent = etiqueta || String(valor);
    select.appendChild(opcion);
};

window.editarLabor = async (empresa, codigo, concepto) => {
    try {
        const body = new FormData();
        body.append('empresa', empresa);
        body.append('codigo', codigo);
        body.append('concepto', concepto);

        const response = await fetch(URL_LABORES + 'obtener', { method: 'POST', body });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result || result.success === false || !result.labor) {
            avisar(response, result ? result.message : 'No se encontró la labor.');
            return;
        }

        const labor = result.labor;
        prepararFormulario();
        $id('labor_modo').value = 'editar';
        $id('titulo_modal_labor').innerHTML = '<i class="bi bi-clipboard-check me-1"></i>Editar labor';
        $id('labor_empresa').value = labor.empresa;
        $id('labor_codigo').value = labor.codigo;
        $id('labor_concepto').value = labor.concepto;
        $id('labor_empresa_pk').value = labor.empresa;
        $id('labor_codigo_pk').value = labor.codigo;
        $id('labor_concepto_pk').value = labor.concepto;
        $id('labor_descripcion').value = labor.descripcion || '';
        $id('labor_des_corta').value = labor.desCorta || '';
        $id('labor_des_corta').dataset.tocado = '1';
        $id('labor_grupo').value = labor.grupo || '';
        $id('labor_umedida').value = labor.uMedida || '';
        asegurarOpcionClase(labor.claseLabor, labor.claseLaborEtiqueta);
        $id('labor_clase').value = labor.claseLabor ?? '';
        $id('labor_equivalencia').value = labor.equivalencia || '';
        $id('labor_ciclos').value = labor.ciclos ?? '';
        $id('labor_tarea').value = labor.tarea ?? '';
        $id('labor_naturaleza').value = labor.naturaleza ?? '';
        $id('labor_tipo_aplicacion').value = labor.tipoAplicacion ?? '';
        $id('labor_maneja_rango').checked = Number(labor.manejaRango) === 1;
        $id('labor_anio_desde').value = labor.anioDesde ?? '';
        $id('labor_anio_hasta').value = labor.anioHasta ?? '';
        $id('labor_maneja_fecha').checked = Number(labor.manejaFecha) === 1;
        $id('labor_maneja_lote').checked = Number(labor.manejaLote) === 1;
        $id('labor_maneja_linea').checked = Number(labor.manejaLinea) === 1;
        $id('labor_maneja_palma').checked = Number(labor.manejaPalma) === 1;
        $id('labor_maneja_racimo').checked = Number(labor.manejaRacimo) === 1;
        $id('labor_maneja_caracteristica').checked = Number(labor.manejaCaracteristica) === 1;
        $id('labor_maneja_jornal').checked = Number(labor.manejaJornal) === 1;
        $id('labor_calcula_jornal').checked = Number(labor.calculaJornal) === 1;
        $id('labor_maneja_decimal').checked = Number(labor.manejaDecimal) === 1;
        $id('labor_activo').checked = Number(labor.activo) === 1;
        $id('labor_no_prestacional').checked = Number(labor.noPrestacional) === 1;
        $id('labor_maneja_canal').checked = Number(labor.manejaCanal) === 1;
        $id('labor_tipo_canal').value = labor.tipoCanal || '';
        $id('labor_impuesto').checked = Number(labor.impuesto) === 1;
        $id('labor_grupo_ir').value = labor.grupoIR || '';
        $id('labor_maneja_saldo').checked = Number(labor.manejaSaldo) === 1;
        $id('labor_maneja_bascula').checked = Number(labor.manejaBascula) === 1;
        aplicarManejaRango();
        aplicarManejaCanal();
        aplicarImpuesto();
        bloquearLlave(true);
        modalLabor().show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

$id('formulario_labor').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = $id('btn_guardar_labor');
    const edicion = $id('labor_modo').value === 'editar';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const ok = await enviar(edicion ? 'actualizar' : 'crear', {
        empresa: edicion ? $id('labor_empresa_pk').value : $id('labor_empresa').value,
        codigo: edicion ? $id('labor_codigo_pk').value : $id('labor_codigo').value,
        concepto: edicion ? $id('labor_concepto_pk').value : $id('labor_concepto').value,
        descripcion: $id('labor_descripcion').value,
        desCorta: $id('labor_des_corta').value,
        grupo: $id('labor_grupo').value,
        uMedida: $id('labor_umedida').value,
        equivalencia: $id('labor_equivalencia').value,
        ciclos: $id('labor_ciclos').value,
        tarea: $id('labor_tarea').value,
        naturaleza: $id('labor_naturaleza').value,
        claseLabor: $id('labor_clase').value,
        tipoAplicacion: $id('labor_tipo_aplicacion').value,
        manejaRango: $id('labor_maneja_rango').checked ? 1 : 0,
        anioDesde: $id('labor_maneja_rango').checked ? $id('labor_anio_desde').value : '',
        anioHasta: $id('labor_maneja_rango').checked ? $id('labor_anio_hasta').value : '',
        manejaCanal: $id('labor_maneja_canal').checked ? 1 : 0,
        tipoCanal: $id('labor_maneja_canal').checked ? $id('labor_tipo_canal').value : '',
        impuesto: $id('labor_impuesto').checked ? 1 : 0,
        grupoIR: $id('labor_impuesto').checked ? $id('labor_grupo_ir').value : '',
        manejaLote: $id('labor_maneja_lote').checked ? 1 : 0,
        manejaLinea: $id('labor_maneja_linea').checked ? 1 : 0,
        manejaPalma: $id('labor_maneja_palma').checked ? 1 : 0,
        manejaRacimo: $id('labor_maneja_racimo').checked ? 1 : 0,
        manejaFecha: $id('labor_maneja_fecha').checked ? 1 : 0,
        manejaCaracteristica: $id('labor_maneja_caracteristica').checked ? 1 : 0,
        manejaJornal: $id('labor_maneja_jornal').checked ? 1 : 0,
        calculaJornal: $id('labor_calcula_jornal').checked ? 1 : 0,
        manejaDecimal: $id('labor_maneja_decimal').checked ? 1 : 0,
        manejaSaldo: $id('labor_maneja_saldo').checked ? 1 : 0,
        manejaBascula: $id('labor_maneja_bascula').checked ? 1 : 0,
        noPrestacional: $id('labor_no_prestacional').checked ? 1 : 0,
        activo: $id('labor_activo').checked ? 1 : 0,
        ...filtrosActuales()
    });

    if (ok) modalLabor().hide();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar';
});

// ── Acciones de fila ──────────────────────────────────────────────────────────

window.cambiarEstadoLabor = async (empresa, codigo, concepto) => {
    const fila = document.querySelector(`#cuerpo_tabla_labores tr[data-empresa="${CSS.escape(String(empresa))}"][data-codigo="${CSS.escape(codigo)}"][data-concepto="${CSS.escape(String(concepto))}"]`);
    const activo = fila ? fila.dataset.activo === '1' : null;
    const accion = activo === null ? 'cambiar el estado de la' : (activo ? 'inactivar la' : 'activar la');

    const confirmacion = await Alerta.fire({
        icon: 'question',
        title: '¿Confirmar cambio de estado?',
        text: `Se va a ${accion} labor seleccionada.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('cambiar-estado', { empresa, codigo, concepto, ...filtrosActuales() });
    }
};

window.eliminarLabor = async (empresa, codigo, concepto, descripcion) => {
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar labor?',
        text: `Se eliminará «${descripcion}». Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
        await enviar('eliminar', { empresa, codigo, concepto, ...filtrosActuales() });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    actualizarEstado($id('cuerpo_tabla_labores').querySelectorAll('tr').length === 0
        ? 0
        : $id('contador_labores').querySelector('strong').textContent);
});
