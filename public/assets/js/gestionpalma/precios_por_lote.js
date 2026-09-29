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

const URL_PRECIOS = BASE_URL + 'gestion-palma/precios/por-lote/';

const $id = (id) => document.getElementById(id);

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
        const response = await fetch(URL_PRECIOS + accion, { method: 'POST', body });
        const result = await response.json();
        return { ok: response.ok && result.success === true, status: response.status, result };
    } catch (error) {
        return { ok: false, status: 0, result: null };
    }
};

const pedir = async (accion, datos) => {
    const envio = await enviar(accion, datos);
    if (envio.ok) return envio.result;
    if (envio.result === null) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
        return null;
    }
    avisar(envio, envio.result.message);
    return null;
};

const escapar = (texto) => String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const CAMPOS = ['precioDestajo', 'precioContratistas', 'precioOtros', 'porcentaje'];

const aNumero = (texto) => {
    const limpio = String(texto ?? '').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    const numero = parseFloat(limpio);
    return isNaN(numero) ? 0 : numero;
};

const decimales = (campo) => (campo === 'porcentaje' ? 3 : 4);

const canonico = (numero, dec) => String(Number(Number(numero).toFixed(dec)));

const formatear = (numero, dec) => new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: dec }).format(numero);

const pintarInput = (input) => {
    const numero = aNumero(input.value);
    input.value = formatear(numero, decimales(input.dataset.campo));
    input.classList.toggle('gp-cero', numero === 0);
};

const campoInput = (campo) => $id('valor_' + campo);

const valores = () => {
    const datos = { baseSueldo: $id('valor_baseSueldo').checked ? 1 : 0 };
    CAMPOS.forEach((campo) => { datos[campo] = aNumero(campoInput(campo).value); });
    return datos;
};

// ── Estado ────────────────────────────────────────────────────────────────────

const estado = {
    modo: 'crear',
    llave: null,
    lotes: [],
    secciones: [],
    hayAlgunaSeccion: false,
    base: null,
    sucio: false
};

const empresa = () => $id('filtro_empresa').value;

const textoOpcion = (select, atributo) => {
    const opcion = select.options[select.selectedIndex];
    return opcion ? (opcion.dataset[atributo] || '') : '';
};

const fincaSel = () => $id('form_finca').value;
const fincaNombre = () => textoOpcion($id('form_finca'), 'descripcion');
const laborSel = () => $id('form_labor').value;
const laborNombre = () => textoOpcion($id('form_labor'), 'descripcion');
const anioSel = () => $id('form_anio').value;
const loteSel = () => ($id('form_switch_lote').checked ? $id('form_lote').value : '');
const seccionSel = () => ($id('form_switch_seccion').checked ? $id('form_seccion').value : '');

const loteNombre = (codigo) => {
    const lote = estado.lotes.find((l) => l.codigo === codigo);
    return lote ? lote.descripcion : '';
};

const seccionNombre = (codigo) => {
    const seccion = estado.secciones.find((s) => s.codigo === codigo);
    return seccion ? seccion.descripcion : codigo;
};

const nivelDe = (finca, lote, seccion) => (finca === '' ? '' : (lote !== '' ? 'lote' : (seccion !== '' ? 'seccion' : 'finca')));

const CHIPS = {
    finca: { icono: 'bi-tree', texto: 'Finca' },
    seccion: { icono: 'bi-grid-1x2', texto: 'Sección' },
    lote: { icono: 'bi-geo-alt', texto: 'Lote' }
};

const chipAmbito = (nivel) => (CHIPS[nivel]
    ? `<span class="gp-chip-ambito ${nivel}"><i class="bi ${CHIPS[nivel].icono} me-1" aria-hidden="true"></i>${CHIPS[nivel].texto}</span>`
    : '');

const rutaAmbito = (finca, fincaDesc, lote, seccion, seccionDesc) => {
    const nombre = escapar(fincaDesc || finca);
    if (lote !== '') {
        return `${nombre} <span class="gp-ruta-sep">&rsaquo;</span> <span class="font-monospace">${escapar(lote)}</span>`;
    }
    if (seccion !== '') {
        return `${nombre} <span class="gp-ruta-sep">&rsaquo;</span> Sección ${escapar(seccionDesc || seccion)}`;
    }
    return `${nombre} <span class="gp-ruta-sep">&rsaquo;</span> todos los lotes`;
};

const ambitoTexto = (finca, fincaDesc, lote, seccion, seccionDesc) => {
    if (lote !== '' && seccion !== '') return `el lote ${lote} de la sección ${seccionDesc || seccion}`;
    if (lote !== '') return `el lote ${lote}`;
    if (seccion !== '') return `la sección ${seccionDesc || seccion}`;
    return `toda la finca ${fincaDesc || finca}`;
};

// ── Indicador de ámbito ───────────────────────────────────────────────────────

const pintarCascada = (nivel) => {
    const pasos = [
        { clave: 'lote', texto: 'Lote' },
        { clave: 'seccion', texto: 'Sección' },
        { clave: 'finca', texto: 'Finca' },
        { clave: 'general', texto: 'Lista general' }
    ];

    $id('ambito_cascada').innerHTML = pasos.map((paso, i) => {
        const activo = paso.clave === nivel;
        let clases = 'gp-cascada-paso';
        let title = '';
        if (activo) {
            clases += ' activo';
        } else if (paso.clave === 'seccion' && estado.secciones.length === 0) {
            clases += ' omitido';
            title = ' title="Hoy no hay secciones registradas; este nivel no se evalúa."';
        } else if (paso.clave === 'general' && nivel !== '') {
            clases += ' omitido';
            title = ' title="Este nivel ya no se alcanza para la combinación seleccionada."';
        }
        return (i > 0 ? '<span class="gp-cascada-sep">&rsaquo;</span>' : '')
            + `<span class="${clases}"${title}>${activo ? '<i class="bi bi-check-circle-fill me-1"></i>' : ''}${paso.texto}</span>`;
    }).join('');
};

const actualizarAmbito = () => {
    const finca = fincaSel();
    const lote = loteSel();
    const seccion = seccionSel();
    const nivel = nivelDe(finca, lote, seccion);
    const contenedor = $id('gp_ambito');
    const chip = $id('ambito_chip');

    contenedor.classList.toggle('vacio', nivel === '');
    chip.classList.toggle('d-none', nivel === '');
    chip.className = nivel === '' ? 'gp-chip-ambito d-none' : `gp-chip-ambito ${nivel}`;
    chip.innerHTML = CHIPS[nivel] ? `<i class="bi ${CHIPS[nivel].icono} me-1" aria-hidden="true"></i>${CHIPS[nivel].texto}` : '';

    const nombre = escapar(fincaNombre() || finca);
    let titulo = 'Seleccione una finca para definir el ámbito del precio.';

    if (nivel === 'finca') {
        titulo = `Aplica a toda la finca ${nombre} &middot; ${estado.lotes.length} ${estado.lotes.length === 1 ? 'lote' : 'lotes'}.`;
    } else if (nivel === 'seccion') {
        titulo = `Aplica a todos los lotes de la sección ${escapar(seccionNombre(seccion))}, en la finca ${nombre}.`;
    } else if (nivel === 'lote' && seccion !== '') {
        titulo = `Aplica solo al lote ${escapar(lote)} de la sección ${escapar(seccionNombre(seccion))}, en la finca ${nombre}.`;
    } else if (nivel === 'lote') {
        const desc = loteNombre(lote);
        titulo = `Aplica solo al lote ${escapar(lote)}${desc ? ' &middot; ' + escapar(desc) : ''}, en la finca ${nombre}.`;
    }

    $id('ambito_titulo').innerHTML = titulo;

    const anio = anioSel();
    const labor = laborSel();
    $id('ambito_consecuencia').innerHTML = labor !== ''
        ? `Sobrescribe el precio general de ${escapar(anio)} para la labor ${escapar(labor)} &middot; ${escapar(laborNombre())}.`
        : `Sobrescribirá el precio general de ${escapar(anio)} para la labor que seleccione.`;

    pintarCascada(nivel);
    contenedor.classList.remove('gp-revelar');
    void contenedor.offsetWidth;
    contenedor.classList.add('gp-revelar');
};

// ── Secciones y lotes de la finca ─────────────────────────────────────────────

const aplicarSecciones = () => {
    const hay = estado.secciones.length > 0;
    const panel = $id('panel_seccion');
    const check = $id('form_switch_seccion');

    panel.classList.toggle('inhabilitado', !hay);
    panel.title = hay ? '' : 'No hay secciones registradas';
    check.disabled = !hay;
    $id('nota_seccion').classList.toggle('d-none', hay);

    $id('nota_seccion_texto').textContent = (fincaSel() !== '' && estado.hayAlgunaSeccion)
        ? `La finca ${fincaNombre()} no tiene secciones registradas. El precio quedará a nivel de finca o de lote.`
        : 'Esta empresa aún no tiene secciones registradas. El precio quedará a nivel de finca o de lote. Puede crearlas en Gestión Palma › Secciones y esta opción se habilitará automáticamente.';

    $id('form_seccion').innerHTML = '<option value="">Seleccione&hellip;</option>'
        + estado.secciones.map((s) => `<option value="${escapar(s.codigo)}">${escapar(s.codigo)} &mdash; ${escapar(s.descripcion)}</option>`).join('');

    if (!hay) {
        check.checked = false;
        $id('col_seccion').classList.add('d-none');
    }
};

const aplicarLotes = () => {
    const finca = fincaSel();
    $('#form_lote').prop('disabled', finca === '');
    $id('form_lote').innerHTML = '<option value="">Seleccione&hellip;</option>'
        + estado.lotes.map((l) => `<option value="${escapar(l.codigo)}">${escapar(l.codigo)} &mdash; ${escapar(l.descripcion)}</option>`).join('');
    $('#form_lote').val('').trigger('change.select2');
    $id('ayuda_lote').textContent = finca === ''
        ? 'Seleccione primero la finca.'
        : `${estado.lotes.length} ${estado.lotes.length === 1 ? 'lote' : 'lotes'} en ${fincaNombre()}.`;
};

const cargarFinca = async (finca) => {
    estado.lotes = [];
    estado.secciones = [];

    if (finca !== '') {
        const result = await pedir('lotes-finca', { empresa: empresa(), finca });
        if (result) {
            estado.lotes = result.lotes || [];
            estado.secciones = result.secciones || [];
            if (estado.secciones.length > 0) estado.hayAlgunaSeccion = true;
        }
    }

    aplicarSecciones();
    aplicarLotes();
};

// ── Panel de referencia ───────────────────────────────────────────────────────

const limpiarAyudas = () => {
    CAMPOS.forEach((campo) => $id('ayuda_' + campo).classList.add('d-none'));
};

const pintarDeltas = () => {
    if (!estado.base || estado.base.existe !== 1) return;

    CAMPOS.forEach((campo) => {
        const dec = decimales(campo);
        const general = Number(estado.base.precio[campo] || 0);
        const actual = aNumero(campoInput(campo).value);
        const ayuda = $id('ayuda_' + campo);
        const delta = ayuda.querySelector('[data-delta]');
        const diferencia = Number((actual - general).toFixed(dec));

        ayuda.querySelector('[data-general]').textContent = `General: ${formatear(general, dec)}`;
        ayuda.classList.remove('d-none');

        if (diferencia === 0) {
            delta.className = 'gp-delta igual';
            delta.innerHTML = '<i class="bi bi-dash"></i>';
            return;
        }

        delta.className = 'gp-delta ' + (diferencia > 0 ? 'sube' : 'baja');
        delta.innerHTML = `<i class="bi bi-arrow-${diferencia > 0 ? 'up' : 'down'}-short"></i>${formatear(Math.abs(diferencia), dec)}`;
    });
};

const mostrarAvisoReferencia = (texto, conEnlace) => {
    $id('referencia_aviso').classList.toggle('d-none', texto === '');
    $id('referencia_aviso_texto').textContent = texto;
    $id('referencia_aviso_enlace').classList.toggle('d-none', !conEnlace);
};

const cargarReferencia = async () => {
    const anio = anioSel();
    const labor = laborSel();
    const panel = $id('panel_referencia');

    estado.base = null;
    limpiarAyudas();

    if (labor === '' || anio === '') {
        panel.classList.add('d-none');
        return;
    }

    panel.classList.remove('d-none');
    $id('referencia_titulo').textContent = `Precio general de ${anio} · Labor ${labor}`;
    $id('referencia_valores').classList.add('d-none');
    $id('referencia_cargando').classList.remove('d-none');
    $id('referencia_base').classList.add('d-none');
    $id('btn_copiar_general').disabled = true;

    if (!ANIOS_GENERALES.includes(String(anio))) {
        $id('referencia_cargando').classList.add('d-none');
        mostrarAvisoReferencia(`El año ${anio} no existe en Precios por labor. Puede registrar el precio por ámbito, pero conviene crear primero la lista general del año.`, true);
        return;
    }

    const result = await pedir('precio-base', { empresa: empresa(), anio, novedad: labor });

    $id('referencia_cargando').classList.add('d-none');

    if (!result) {
        panel.classList.add('d-none');
        return;
    }

    estado.base = { existe: Number(result.existe), precio: result.precio || {} };

    if (estado.base.existe !== 1) {
        mostrarAvisoReferencia(`La labor ${labor} no tiene precio en la lista general de ${anio}. Este será el único precio que use la liquidación para el ámbito seleccionado.`, false);
        return;
    }

    mostrarAvisoReferencia('', false);
    $id('referencia_valores').classList.remove('d-none');
    $id('btn_copiar_general').disabled = false;
    CAMPOS.forEach((campo) => {
        $id('referencia_' + campo).textContent = formatear(Number(estado.base.precio[campo] || 0), decimales(campo));
    });
    $id('referencia_base').classList.toggle('d-none', Number(estado.base.precio.baseSueldo) !== 1);
    pintarDeltas();
};

// ── Formulario ────────────────────────────────────────────────────────────────

const aplicarAtenuacion = () => {
    const base = $id('valor_baseSueldo').checked;
    const destajo = campoInput('precioDestajo');
    const porcentaje = campoInput('porcentaje');
    destajo.classList.toggle('gp-valor-ignorado', base);
    destajo.title = base ? 'Este valor no se usa mientras Base sueldo esté activo.' : '';
    porcentaje.classList.toggle('gp-valor-vigente', base && aNumero(porcentaje.value) > 0);
};

const marcarError = (id, visible) => {
    const control = $id(id.replace('error_', 'form_'));
    $id(id).classList.toggle('d-none', !visible);
    control.classList.toggle('is-invalid', visible);

    const seleccion = control.parentElement.querySelector('.select2-selection');
    if (seleccion) seleccion.classList.toggle('is-invalid', visible);
};

const limpiarErrores = () => ['error_anio', 'error_finca', 'error_labor', 'error_lote', 'error_seccion'].forEach((id) => marcarError(id, false));

const ponerValores = (datos, marcarEditado) => {
    CAMPOS.forEach((campo) => {
        const input = campoInput(campo);
        const dec = decimales(campo);
        const nuevo = canonico(Number(datos[campo] || 0), dec);
        const cambio = marcarEditado && nuevo !== canonico(aNumero(input.value), dec);
        input.value = formatear(Number(nuevo), dec);
        input.classList.toggle('gp-cero', Number(nuevo) === 0);
        input.classList.toggle('gp-editado', cambio);
    });
    $id('valor_baseSueldo').checked = Number(datos.baseSueldo) === 1;
    aplicarAtenuacion();
    pintarDeltas();
};

const modoCrear = () => {
    estado.modo = 'crear';
    estado.llave = null;
    $id('registro_encabezado_crear').classList.remove('d-none');
    $id('registro_encabezado_editar').classList.add('d-none');
    $id('bloque_llave').classList.remove('d-none');
    $id('col_labor').classList.remove('d-none');
    $id('titulo_labor').classList.remove('d-none');
    $id('titulo_ambito').classList.remove('d-none');
    $id('llave_fija').classList.add('d-none');
    $id('nota_llave').classList.add('d-none');
    $id('aviso_colision').classList.add('d-none');
    $id('btn_guardar').innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar precio';
};

const modoEditar = (precio) => {
    estado.modo = 'editar';
    estado.llave = {
        anio: precio.anio,
        novedad: precio.novedad,
        finca: precio.finca,
        lote: precio.lote,
        seccion: precio.seccion
    };

    $id('registro_encabezado_crear').classList.add('d-none');
    $id('registro_encabezado_editar').classList.remove('d-none');
    $id('bloque_llave').classList.add('d-none');
    $id('col_labor').classList.add('d-none');
    $id('titulo_labor').classList.add('d-none');
    $id('titulo_ambito').classList.add('d-none');
    $id('llave_fija').classList.remove('d-none');
    $id('nota_llave').classList.remove('d-none');
    $id('aviso_colision').classList.add('d-none');
    $id('btn_guardar').innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar cambios';

    const nivel = nivelDe(precio.finca, precio.lote, precio.seccion);
    const ruta = rutaAmbito(precio.finca, precio.fincaDescripcion, precio.lote, precio.seccion, precio.seccionDescripcion);
    $id('chip_ambito_editar').className = `gp-chip-ambito ${nivel}`;
    $id('chip_ambito_editar').innerHTML = CHIPS[nivel] ? `<i class="bi ${CHIPS[nivel].icono} me-1" aria-hidden="true"></i>${CHIPS[nivel].texto}` : '';
    $id('llave_fija_titulo').innerHTML = `${escapar(precio.anio)} <span class="gp-ruta-sep">&middot;</span> <span class="font-monospace">${escapar(precio.novedad)}</span> ${escapar(precio.descripcion)}`;
    $id('llave_fija_ruta').innerHTML = ruta;

    const panel = $id('llave_fija');
    panel.classList.remove('gp-revelar');
    void panel.offsetWidth;
    panel.classList.add('gp-revelar');
};

const limpiarFormulario = () => {
    modoCrear();
    limpiarErrores();
    $id('chip_copiado').classList.add('d-none');
    $id('form_switch_lote').checked = false;
    $id('form_switch_seccion').checked = false;
    $id('col_lote').classList.add('d-none');
    $id('col_seccion').classList.add('d-none');
    $id('form_finca').value = '';
    $id('form_anio').selectedIndex = 0;
    $('#form_labor').val('').trigger('change.select2');
    ponerValores({ precioDestajo: 0, precioContratistas: 0, precioOtros: 0, porcentaje: 0, baseSueldo: 0 }, false);
    CAMPOS.forEach((campo) => campoInput(campo).classList.remove('gp-editado'));
    estado.sucio = false;
    cargarFinca('');
    cargarReferencia();
    actualizarAmbito();
};

const cargarEnFormulario = async (precio) => {
    if (![...$id('form_anio').options].some((o) => o.value === String(precio.anio))) {
        $id('form_anio').insertAdjacentHTML('afterbegin', `<option value="${escapar(precio.anio)}">${escapar(precio.anio)}</option>`);
    }
    $id('form_anio').value = String(precio.anio);
    $id('form_finca').value = precio.finca;
    await cargarFinca(precio.finca);

    $id('form_switch_seccion').checked = precio.seccion !== '';
    $id('col_seccion').classList.toggle('d-none', precio.seccion === '');
    if (precio.seccion !== '') $id('form_seccion').value = precio.seccion;

    $id('form_switch_lote').checked = precio.lote !== '';
    $id('col_lote').classList.toggle('d-none', precio.lote === '');
    if (precio.lote !== '') $('#form_lote').val(precio.lote).trigger('change.select2');

    $('#form_labor').val(precio.novedad).trigger('change.select2');
    ponerValores(precio, false);
    await cargarReferencia();
    actualizarAmbito();
};

const irAlFormulario = () => {
    if (window.innerWidth < 992) {
        $id('formulario_precio').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

// ── Listado ───────────────────────────────────────────────────────────────────

const tablaDT = () => ($.fn.DataTable.isDataTable('#tabla_precios') ? $('#tabla_precios').DataTable() : null);

const destruirTabla = () => {
    const dt = tablaDT();
    if (dt) dt.destroy();
};

const renderizarTabla = () => {
    if ($id('cuerpo_tabla_precios').querySelectorAll('tr').length === 0) return;
    $('#tabla_precios').DataTable({
        paging: true,
        pageLength: 25,
        lengthChange: false,
        info: true,
        searching: false,
        columnDefs: [{ targets: [8], orderable: false }],
        order: [[0, 'desc'], [2, 'asc']],
        language: {
            info: 'Mostrando _START_ a _END_ de _TOTAL_ precios',
            infoEmpty: 'Sin precios para mostrar',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay datos disponibles'
        }
    });
};

const hayFiltros = () => $id('filtro_anio').value !== '' || $id('filtro_finca').value !== '' || $id('filtro_busqueda').value.trim() !== '';

const opcionesAnios = (anios) => anios.map((a) => `<option value="${escapar(a)}">${escapar(a)}</option>`).join('');

const opcionesCatalogo = (filas) => filas
    .map((f) => `<option value="${escapar(f.codigo)}" data-descripcion="${escapar(f.descripcion)}">${escapar(f.codigo)} &mdash; ${escapar(f.descripcion)}</option>`)
    .join('');

const repoblar = (id, html, previo) => {
    const select = $id(id);
    select.innerHTML = html;
    select.value = previo;
    if (select.value !== previo) select.selectedIndex = 0;
};

const refrescarCatalogos = (result) => {
    if (!result.anios || !result.fincas || !result.labores) return;

    const anios = opcionesAnios(result.anios);
    const fincas = opcionesCatalogo(result.fincas);

    repoblar('filtro_anio', '<option value="">Todos</option>' + anios, $id('filtro_anio').value);
    repoblar('filtro_finca', '<option value="">Todas</option>' + fincas, $id('filtro_finca').value);
    repoblar('form_anio', '<option value="">Seleccione&hellip;</option>' + anios, $id('form_anio').value);
    repoblar('form_finca', '<option value="">Seleccione&hellip;</option>' + fincas, $id('form_finca').value);
    repoblar('form_labor', '<option value=""></option>' + opcionesCatalogo(result.labores), $id('form_labor').value);

    $('#form_labor').trigger('change.select2');

    const sinAnios = result.anios.length === 0;
    $id('ayuda_anio').classList.toggle('d-none', sinAnios);
    $id('nota_anio').classList.toggle('d-none', !sinAnios);
};

const desgloseNiveles = (niveles) => {
    if (!niveles) return '';
    const partes = [];
    if (niveles.finca) partes.push(`Finca ${niveles.finca}`);
    if (niveles.seccion) partes.push(`Sección ${niveles.seccion}`);
    if (niveles.lote) partes.push(`Lote ${niveles.lote}`);
    return partes.length === 0 ? '' : ` <span class="gp-ruta-sep">&middot;</span> ${partes.join(' <span class="gp-ruta-sep">&middot;</span> ')}`;
};

const actualizarEstado = (total, niveles) => {
    const vacio = Number(total) === 0;
    const finca = $id('filtro_finca');
    const enFinca = finca.value === '' ? '' : ` en ${finca.options[finca.selectedIndex].dataset.descripcion || finca.value}`;
    $id('contador_precios').innerHTML =
        `<i class="bi bi-geo-alt me-1" aria-hidden="true"></i><strong>${total}</strong> ${Number(total) === 1 ? 'precio registrado' : 'precios registrados'}${enFinca}${desgloseNiveles(niveles)}`;
    $id('gp_contenedor_tabla').classList.toggle('d-none', vacio);
    $id('gp_estado_vacio').classList.toggle('d-none', !vacio || hayFiltros());
    $id('gp_estado_filtro').classList.toggle('d-none', !vacio || !hayFiltros());
};

const listar = async () => {
    const result = await pedir('listar', {
        empresa: empresa(),
        anio: $id('filtro_anio').value,
        finca: $id('filtro_finca').value,
        busqueda: $id('filtro_busqueda').value.trim()
    });
    if (!result) return;
    destruirTabla();
    $id('cuerpo_tabla_precios').innerHTML = result.tabla || '';
    renderizarTabla();
    refrescarCatalogos(result);
    actualizarEstado(result.total ?? 0, result.niveles);
};

const claveDe = (el) => ({
    anio: el.dataset.anio || '',
    novedad: el.dataset.novedad || '',
    finca: el.dataset.finca || '',
    lote: el.dataset.lote || '',
    seccion: el.dataset.seccion || ''
});

const filaDe = (clave) => {
    const dt = tablaDT();
    const nodos = dt ? dt.rows().nodes().toArray() : [...$id('cuerpo_tabla_precios').querySelectorAll('tr')];
    return nodos.find((tr) => {
        const otra = claveDe(tr);
        return Object.keys(clave).every((k) => String(otra[k]) === String(clave[k]));
    });
};

const resaltarFila = (clave) => {
    const fila = filaDe(clave);
    if (!fila) return;

    const dt = tablaDT();
    if (dt) {
        const posicion = dt.rows({ order: 'applied' }).indexes().indexOf(dt.row(fila).index());
        if (posicion >= 0) dt.page(Math.floor(posicion / dt.page.info().length)).draw(false);
    }

    fila.scrollIntoView({ behavior: 'smooth', block: 'center' });
    fila.classList.remove('gp-flash-guardado');
    void fila.offsetWidth;
    fila.classList.add('gp-flash-guardado');
    setTimeout(() => fila.classList.remove('gp-flash-guardado'), 1300);
};

// ── Eventos de filtros ────────────────────────────────────────────────────────

const confirmarCambioEmpresa = async () => {
    if (!estado.sucio) return true;
    const respuesta = await Alerta.fire({
        icon: 'warning',
        title: '¿Cambiar de empresa?',
        text: 'Hay datos sin guardar en el formulario. Si cambia de empresa se perderán.',
        showCancelButton: true,
        confirmButtonText: 'Cambiar de empresa',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Seguir aquí'
    });
    return respuesta.isConfirmed;
};

let empresaPrevia = $id('filtro_empresa').value;

$id('filtro_empresa').addEventListener('change', async (e) => {
    if (!(await confirmarCambioEmpresa())) {
        e.target.value = empresaPrevia;
        return;
    }
    empresaPrevia = e.target.value;
    $id('registro_empresa').textContent = e.target.options[e.target.selectedIndex].textContent.trim();
    estado.hayAlgunaSeccion = false;
    limpiarFormulario();
    listar();
});

$id('btn_buscar').addEventListener('click', listar);
$id('filtro_anio').addEventListener('change', listar);
$id('filtro_finca').addEventListener('change', listar);
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
$id('btn_limpiar_filtros').addEventListener('click', () => {
    $id('filtro_anio').value = '';
    $id('filtro_finca').value = '';
    $id('filtro_busqueda').value = '';
    listar();
});

// ── Eventos del formulario ────────────────────────────────────────────────────

const ensuciar = () => { estado.sucio = true; };

$id('form_anio').addEventListener('change', () => {
    ensuciar();
    cargarReferencia();
    actualizarAmbito();
});

$id('form_finca').addEventListener('change', async () => {
    ensuciar();
    marcarError('error_finca', false);
    await cargarFinca(fincaSel());
    actualizarAmbito();
});

const aplicarSwitch = (idSwitch, idCol) => {
    const activo = $id(idSwitch).checked;
    const col = $id(idCol);
    col.classList.toggle('d-none', !activo);
    if (activo) {
        col.classList.remove('gp-revelar');
        void col.offsetWidth;
        col.classList.add('gp-revelar');
    }
    actualizarAmbito();
};

$id('form_switch_lote').addEventListener('change', () => {
    ensuciar();
    marcarError('error_lote', false);
    aplicarSwitch('form_switch_lote', 'col_lote');
});

$id('form_switch_seccion').addEventListener('change', () => {
    ensuciar();
    marcarError('error_seccion', false);
    aplicarSwitch('form_switch_seccion', 'col_seccion');
});

$('#form_lote').on('change', () => {
    ensuciar();
    marcarError('error_lote', false);
    actualizarAmbito();
});

$id('form_seccion').addEventListener('change', () => {
    ensuciar();
    marcarError('error_seccion', false);
    actualizarAmbito();
});

$('#form_labor').on('change', () => {
    ensuciar();
    marcarError('error_labor', false);
    cargarReferencia();
    actualizarAmbito();
});

CAMPOS.forEach((campo) => {
    const input = campoInput(campo);
    input.addEventListener('focus', () => {
        input.value = String(aNumero(input.value)).replace('.', ',');
        input.select();
    });
    input.addEventListener('blur', () => {
        pintarInput(input);
        aplicarAtenuacion();
        pintarDeltas();
    });
    input.addEventListener('input', () => {
        ensuciar();
        input.classList.remove('gp-editado');
    });
});

$id('valor_baseSueldo').addEventListener('change', () => {
    ensuciar();
    aplicarAtenuacion();
});

$id('btn_copiar_general').addEventListener('click', () => {
    if (!estado.base || estado.base.existe !== 1) return;
    ponerValores(estado.base.precio, true);
    ensuciar();
});

$id('btn_limpiar').addEventListener('click', limpiarFormulario);
$id('btn_cancelar_edicion').addEventListener('click', limpiarFormulario);

$id('btn_nuevo_precio').addEventListener('click', () => {
    limpiarFormulario();
    irAlFormulario();
});

$id('btn_registrar_primero').addEventListener('click', () => {
    limpiarFormulario();
    irAlFormulario();
    $id('form_anio').focus();
});

$id('btn_ir_existente').addEventListener('click', () => {
    resaltarFila({
        anio: anioSel(),
        novedad: laborSel(),
        finca: fincaSel(),
        lote: loteSel(),
        seccion: seccionSel()
    });
});

window.addEventListener('beforeunload', (e) => {
    if (estado.sucio) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ── Guardar ───────────────────────────────────────────────────────────────────

const validar = () => {
    limpiarErrores();
    let ok = true;

    if (anioSel() === '') {
        marcarError('error_anio', true);
        ok = false;
    }
    if (fincaSel() === '') {
        marcarError('error_finca', true);
        ok = false;
    }
    if (laborSel() === '') {
        marcarError('error_labor', true);
        ok = false;
    }
    if ($id('form_switch_lote').checked && $id('form_lote').value === '') {
        marcarError('error_lote', true);
        ok = false;
    }
    if ($id('form_switch_seccion').checked && $id('form_seccion').value === '') {
        marcarError('error_seccion', true);
        ok = false;
    }

    return ok;
};

const confirmarCeros = async (datos, clave) => {
    if (datos.baseSueldo === 1 || CAMPOS.some((campo) => datos[campo] !== 0)) return true;

    const respuesta = await Alerta.fire({
        icon: 'warning',
        title: '¿Guardar con todos los valores en cero?',
        text: `La labor ${clave.novedad} quedará en cero para ${ambitoTexto(clave.finca, fincaNombre(), clave.lote, clave.seccion, seccionNombre(clave.seccion))}, por encima del precio general de ${clave.anio}.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Revisar valores'
    });
    return respuesta.isConfirmed;
};

const mensajeExito = (clave) => {
    if (clave.lote !== '') return `Precio registrado para el lote ${clave.lote}.`;
    if (clave.seccion !== '') return `Precio registrado para la sección ${seccionNombre(clave.seccion)}.`;
    return `Precio registrado para toda la finca ${fincaNombre() || clave.finca}.`;
};

$id('formulario_precio').addEventListener('submit', async (e) => {
    e.preventDefault();
    $id('aviso_colision').classList.add('d-none');

    const edicion = estado.modo === 'editar';

    if (!edicion && !validar()) return;

    const clave = edicion ? estado.llave : {
        anio: anioSel(),
        novedad: laborSel(),
        finca: fincaSel(),
        lote: loteSel(),
        seccion: seccionSel()
    };

    const datos = valores();

    if (!(await confirmarCeros(datos, clave))) return;

    const btn = $id('btn_guardar');
    const etiqueta = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando...';

    const envio = await enviar(edicion ? 'actualizar' : 'crear', { empresa: empresa(), ...clave, ...datos });

    btn.disabled = false;
    btn.innerHTML = etiqueta;

    if (!envio.ok) {
        if (envio.result === null) {
            Toast.fire({ icon: 'error', title: 'Error de conexión.' });
            return;
        }
        if (/^Ya existe un precio/.test(envio.result.message || '')) {
            $id('aviso_colision').classList.remove('d-none', 'gp-revelar');
            void $id('aviso_colision').offsetWidth;
            $id('aviso_colision').classList.add('gp-revelar');
            return;
        }
        avisar(envio, envio.result.message);
        return;
    }

    Toast.fire({ icon: 'success', title: edicion ? 'Precio actualizado.' : mensajeExito(clave) });
    estado.sucio = false;
    if (!edicion) limpiarFormulario();
    await listar();
    resaltarFila(clave);
});

// ── Acciones del listado ──────────────────────────────────────────────────────

const obtener = async (clave) => await pedir('obtener', { empresa: empresa(), ...clave });

const editar = async (clave) => {
    const result = await obtener(clave);
    if (!result) return;
    limpiarErrores();
    $id('chip_copiado').classList.add('d-none');
    await cargarEnFormulario(result.precio);
    modoEditar(result.precio);
    estado.sucio = false;
    irAlFormulario();
};

const duplicar = async (clave) => {
    const result = await obtener(clave);
    if (!result) return;
    limpiarErrores();
    await cargarEnFormulario(result.precio);
    modoCrear();
    const chip = $id('chip_copiado');
    chip.classList.remove('d-none');
    chip.innerHTML = '<i class="bi bi-files me-1"></i>Copiado de '
        + rutaAmbito(result.precio.finca, result.precio.fincaDescripcion, result.precio.lote, result.precio.seccion, result.precio.seccionDescripcion);
    estado.sucio = true;
    irAlFormulario();
};

const duplicarActual = () => {
    if (estado.llave) duplicar(estado.llave);
};

const eliminar = async (clave) => {
    const result = await obtener(clave);
    if (!result) return;

    const precio = result.precio;
    const ambito = ambitoTexto(precio.finca, precio.fincaDescripcion, precio.lote, precio.seccion, precio.seccionDescripcion);

    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: '¿Eliminar este precio?',
        text: `Se eliminará el precio de la labor ${precio.novedad} · ${precio.descripcion} para ${ambito} en ${precio.anio}. A partir de ese momento esa labor se liquidará con el precio general del año. Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    if (!(await pedir('eliminar', { empresa: empresa(), ...clave }))) return;

    Toast.fire({ icon: 'success', title: 'Precio eliminado.' });
    if (estado.modo === 'editar') limpiarFormulario();
    listar();
};

$id('btn_duplicar').addEventListener('click', duplicarActual);

$id('cuerpo_tabla_precios').addEventListener('click', (e) => {
    const boton = e.target.closest('[data-accion]');
    if (!boton) return;
    const clave = claveDe(boton.dataset.anio ? boton : boton.closest('tr'));
    if (boton.dataset.accion === 'editar') editar(clave);
    if (boton.dataset.accion === 'duplicar') duplicar(clave);
    if (boton.dataset.accion === 'eliminar') eliminar(clave);
});

document.addEventListener('DOMContentLoaded', () => {
    $('#form_lote, #form_labor').select2({
        width: '100%',
        placeholder: 'Seleccione una opción…',
        allowClear: true,
        language: {
            noResults: () => 'No se encontraron resultados',
            searching: () => 'Buscando…'
        }
    });
    $('#form_lote').val('').trigger('change.select2');
    $('#form_labor').val('').trigger('change.select2');
    renderizarTabla();
    actualizarEstado(TOTAL_INICIAL, NIVELES_INICIALES);
    aplicarSecciones();
    aplicarLotes();
    aplicarAtenuacion();
    actualizarAmbito();
});
