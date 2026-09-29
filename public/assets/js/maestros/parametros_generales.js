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

const URL_PARAMETROS = BASE_URL + 'maestros/parametros-generales/';
const SUFIJOS = ['', '_editar'];

const $id = (id) => document.getElementById(id);
const el = (base, suf) => $id(base + suf);
const empresaActual = () => $id('filtro_empresa').value;
const formulario = (suf) => $id(suf ? 'formulario_editar_parametro' : 'formulario_creacion');
const numero = (valor) => Number(valor || 0).toLocaleString('es-CO');
const mostrar = (elemento, visible) => elemento.classList.toggle('d-none', !visible);

const escaparHtml = (texto) => String(texto ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const crearEstado = () => ({
    memoria: { texto: '', ds: '', cValor: '', cEtiqueta: '' },
    huerfano: null,
    pendiente: ''
});

const estado = { '': crearEstado(), _editar: crearEstado() };

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;
    let resultado;

    try {
        respuesta = await fetch(URL_PARAMETROS + ruta, { method: 'POST', body: cuerpo });
        resultado = await respuesta.json();
    } catch (error) {
        throw new Error('Error de conexión.');
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'Error de conexión.');
    }

    return resultado;
};

// ── Tabla ─────────────────────────────────────────────────────────────────────

const filasParametros = () => document.querySelectorAll('#cuerpo_tabla_parametros tr[data-nombre]');

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_parametros')) {
        $('#tabla_parametros').DataTable().destroy();
    }
};

const actualizarContador = () => {
    const total = filasParametros().length;

    $id('contador_parametros').innerHTML =
        `<i class="bi bi-sliders2 me-1"></i><strong>${total}</strong> ${total === 1 ? 'registro encontrado' : 'registros encontrados'}`;
};

const renderizarTabla = () => {
    destruirTabla();
    actualizarContador();

    if (filasParametros().length === 0) {
        return;
    }

    $('#tabla_parametros').DataTable({
        order: [[0, 'asc']],
        columnDefs: [{ targets: [5], orderable: false }],
        language: {
            lengthMenu: 'Mostrar _MENU_ registros por página',
            zeroRecords: 'No se encontraron resultados',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            search: 'Buscar:',
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

const aplicarHtmlTabla = (html) => {
    destruirTabla();
    $id('cuerpo_tabla_parametros').innerHTML = html;
    renderizarTabla();
};

const listar = async () => {
    try {
        const resultado = await pedir('listar', { empresa: empresaActual() });
        aplicarHtmlTabla(resultado.tabla);
    } catch (error) {
        Toast.fire({ icon: 'error', title: error.message });
    }
};

// ── Formulario: vista y colapso ───────────────────────────────────────────────

const temporizadores = new WeakMap();

const colapsar = (elemento, visible) => {
    clearTimeout(temporizadores.get(elemento));

    if (visible) {
        elemento.classList.remove('d-none');
        requestAnimationFrame(() => elemento.classList.add('abierto'));
        return;
    }

    elemento.classList.remove('abierto');
    temporizadores.set(elemento, setTimeout(() => elemento.classList.add('d-none'), 150));
};

const aplicarVista = (suf) => {
    const activo = el('maneja_ds', suf).checked;

    el('panel_opcion', suf).classList.toggle('activo', activo);
    el('ayuda_maneja_ds', suf).textContent = activo
        ? 'El valor se elige de una tabla de la base de datos.'
        : 'El valor se escribe a mano.';

    colapsar(el('bloque_valor_fijo', suf), !activo);
    colapsar(el('bloque_origen', suf), activo);

    mostrar(el('grupo_valor_texto', suf), !activo);
    mostrar(el('valor_texto', suf), !activo);
    mostrar(el('grupo_valor_select', suf), activo);
    mostrar(el('valor_select', suf), activo);

    el('ayuda_valor', suf).textContent = activo
        ? 'Se elige entre los registros de la tabla de origen.'
        : 'Se guarda tal cual se escribe, según el tipo de dato elegido.';
};

const enfocar = (elemento) => {
    const seleccion = elemento.closest('.input-group')?.querySelector('.select2-selection');
    (seleccion || elemento).focus();
};

// ── Tablas y columnas del origen ──────────────────────────────────────────────

let tablasOrigen = null;

const cargarTablas = async () => {
    if (tablasOrigen) {
        return tablasOrigen;
    }

    tablasOrigen = await pedir('tablas', {});

    const opciones = '<option value=""></option>' + tablasOrigen.tablas
        .map((tabla) => `<option value="${escaparHtml(tabla.nombre)}">${escaparHtml(tabla.nombre)}</option>`)
        .join('');

    SUFIJOS.forEach((suf) => {
        el('ds', suf).innerHTML = opciones;
        el('ayuda_ds', suf).textContent =
            `${numero(tablasOrigen.total)} tablas disponibles. Escriba parte del nombre para filtrar.`;
    });

    return tablasOrigen;
};

const cargandoColumnas = (suf, activo) => {
    ['c_valor', 'c_etiqueta'].forEach((base) => {
        const select = el(base, suf);

        select.disabled = activo;
        select.setAttribute('aria-busy', activo ? 'true' : 'false');

        if (activo) {
            select.innerHTML = '<option value="">Cargando columnas…</option>';
        }

        mostrar(el('cargando_' + base, suf), activo);
    });
};

const bloquearColumnas = (suf) => {
    ['c_valor', 'c_etiqueta'].forEach((base) => {
        const select = el(base, suf);

        select.innerHTML = '<option value=""></option>';
        select.disabled = true;
        select.setAttribute('aria-busy', 'false');
        el('ayuda_' + base, suf).classList.remove('maestros-nota');
        el('ayuda_' + base, suf).textContent = 'Seleccione primero la tabla.';
        mostrar(el('cargando_' + base, suf), false);
    });
};

const limpiarValor = (suf) => {
    const select = el('valor_select', suf);

    $(select).val(null).trigger('change.select2');
    select.innerHTML = '';
    select.disabled = true;
    limpiarHuerfano(suf);
    mostrar(el('nota_recortada', suf), false);
};

const origenCompleto = (suf) =>
    el('ds', suf).value !== '' && el('c_valor', suf).value !== '' && el('c_etiqueta', suf).value !== '';

const actualizarValor = (suf) => {
    el('valor_select', suf).disabled = !origenCompleto(suf);
    el('ayuda_valor', suf).textContent = origenCompleto(suf)
        ? 'Se elige entre los registros de la tabla de origen.'
        : 'Elija la tabla, el campo valor y el campo etiqueta para habilitar la lista.';
};

const cambioTabla = async (suf, notificar = true) => {
    const ds = el('ds', suf).value;
    const previos = { c_valor: el('c_valor', suf).value, c_etiqueta: el('c_etiqueta', suf).value };
    const habia = previos.c_valor !== '' || previos.c_etiqueta !== '';

    mostrar(el('nota_cambio_tabla', suf), false);

    if (ds === '') {
        bloquearColumnas(suf);
        limpiarValor(suf);
        actualizarValor(suf);
        return;
    }

    cargandoColumnas(suf, true);

    try {
        const resultado = await pedir('columnas', { ds });
        const nombres = resultado.columnas.map((columna) => columna.nombre);
        const opciones = '<option value=""></option>' + resultado.columnas
            .map((columna) => `<option value="${escaparHtml(columna.nombre)}" title="${escaparHtml(columna.tipo)}">${escaparHtml(columna.nombre)}</option>`)
            .join('');
        const conservados = [];
        const limpiados = [];

        cargandoColumnas(suf, false);

        ['c_valor', 'c_etiqueta'].forEach((base) => {
            const select = el(base, suf);

            select.innerHTML = opciones;
            select.disabled = false;

            if (previos[base] !== '') {
                if (nombres.includes(previos[base])) {
                    select.value = previos[base];
                    conservados.push({ base, nombre: previos[base] });
                } else {
                    limpiados.push({ base, nombre: previos[base] });
                }
            }

            const ayuda = el('ayuda_' + base, suf);
            const conservado = notificar && habia && previos[base] !== '' && select.value === previos[base];

            ayuda.classList.toggle('maestros-nota', conservado);
            ayuda.textContent = `${numero(resultado.columnas.length)} columnas en ${ds}.`
                + (conservado ? ` Se conservó «${previos[base]}» porque también existe aquí.` : '');
        });

        if (notificar && habia && limpiados.length > 0) {
            const aviso = el('nota_cambio_tabla', suf);
            const lista = (campos) => campos.map((campo) => `«${escaparHtml(campo.nombre)}»`).join(' y ');

            limpiarValor(suf);
            aviso.querySelector('span').innerHTML = `Cambió la tabla de origen a <strong>${escaparHtml(ds)}</strong>: `
                + (conservados.length > 0 ? `se conservó ${lista(conservados)} porque también existe allí y ` : '')
                + `se limpió ${lista(limpiados)} y el Valor.`;
            mostrar(aviso, true);
            enfocar(el(limpiados[0].base, suf));
        }

        if (!origenCompleto(suf)) {
            limpiarValor(suf);
        }

        actualizarValor(suf);
    } catch (error) {
        cargandoColumnas(suf, false);
        bloquearColumnas(suf);
        actualizarValor(suf);
        Toast.fire({ icon: 'error', title: error.message });
    }
};

// ── Valor tomado del origen ───────────────────────────────────────────────────

const pintarRecortada = (suf, datos) => {
    const nota = el('nota_recortada', suf);

    if (!datos.recortada) {
        nota.textContent = '';
        mostrar(nota, false);
        return;
    }

    nota.textContent = `${el('ds', suf).value} tiene ${numero(datos.total)} filas; se muestran las primeras ${numero(datos.limite)}.`
        + ' Si el valor que busca no aparece, escríbalo en el buscador para filtrarlo sobre el total.';
    mostrar(nota, true);
};

const limpiarHuerfano = (suf) => {
    estado[suf].huerfano = null;
    el('nota_huerfano', suf).innerHTML = '';
    mostrar(el('nota_huerfano', suf), false);

    if (suf) {
        mostrar($id('chip_huerfano'), false);
    }
};

const marcarHuerfano = (suf, valor, ds) => {
    const select = el('valor_select', suf);
    const texto = `${valor} — no existe en ${ds}`;
    const relleno = /^\d+$/.test(valor) && valor.length < 3 ? valor.padStart(3, '0') : '';

    estado[suf].huerfano = { valor, texto };

    select.innerHTML = '<optgroup label="Valor actual — no encontrado en la tabla">'
        + `<option value="${escaparHtml(valor)}" data-huerfano="1" class="maestros-opcion-huerfana" selected>${escaparHtml(texto)}</option>`
        + '</optgroup>';
    select.disabled = false;
    $(select).val(valor).trigger('change.select2');

    const nota = el('nota_huerfano', suf);

    nota.innerHTML = '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>'
        + `El valor ${suf ? 'guardado' : 'escrito'} (<span class="maestros-valor-huerfano" title="Valor ${suf ? 'guardado en el parámetro' : 'escrito en el formulario'}">${escaparHtml(valor)}</span>) no existe en ${escaparHtml(ds)}. `
        + `Puede que el código haya cambiado o que le falten ceros a la izquierda${relleno ? ` (${escaparHtml(relleno)})` : ''}. `
        + `Se conserva tal cual: si guarda sin tocarlo, ${suf ? 'no se modifica' : 'el parámetro se creará con este valor'}.</span>`;
    mostrar(nota, true);

    if (suf) {
        mostrar($id('chip_huerfano'), true);
    }
};

const seleccionarValor = (suf, valor, etiqueta) => {
    const select = el('valor_select', suf);

    select.innerHTML = `<option value="${escaparHtml(valor)}" selected>${escaparHtml(etiqueta || valor)}</option>`;
    select.disabled = false;
    $(select).val(String(valor)).trigger('change.select2');
};

const resolverPendiente = async (suf) => {
    const pendiente = estado[suf].pendiente;

    if (pendiente === '' || !origenCompleto(suf)) {
        return;
    }

    estado[suf].pendiente = '';

    try {
        const resultado = await pedir('valores', {
            empresa: empresaActual(),
            ds: el('ds', suf).value,
            cValor: el('c_valor', suf).value,
            cEtiqueta: el('c_etiqueta', suf).value,
            termino: pendiente
        });

        pintarRecortada(suf, resultado);

        const encontrado = (resultado.valores || [])
            .find((opcion) => String(opcion.valor).trim() === pendiente);

        if (encontrado) {
            limpiarHuerfano(suf);
            seleccionarValor(suf, encontrado.valor, encontrado.etiqueta);
            return;
        }

        marcarHuerfano(suf, pendiente, el('ds', suf).value);
    } catch (error) {
        Toast.fire({ icon: 'warning', title: error.message });
    }
};

const esHuerfano = (dato) => dato.huerfano === true || dato.element?.dataset?.huerfano === '1';

const formatoValor = (dato) => {
    return esHuerfano(dato)
        ? $(`<span><i class="bi bi-exclamation-triangle me-1"></i>${escaparHtml(dato.text)}</span>`)
        : dato.text;
};

const iniciarSelects = (suf) => {
    const padre = suf ? { dropdownParent: $('#modalEditarParametro') } : {};

    $(el('ds', suf)).select2({
        width: '100%',
        placeholder: 'Escriba para buscar la tabla…',
        allowClear: true,
        language: {
            noResults: () => 'No se encontraron resultados',
            searching: () => 'Buscando…'
        },
        ...padre
    }).on('change', () => cambioTabla(suf));

    $(el('valor_select', suf)).select2({
        width: '100%',
        placeholder: 'Escriba para buscar el valor…',
        templateResult: formatoValor,
        templateSelection: formatoValor,
        language: {
            noResults: () => 'No se encontraron resultados',
            searching: () => 'Buscando…'
        },
        ajax: {
            url: URL_PARAMETROS + 'valores',
            type: 'POST',
            delay: 300,
            data: (parametros) => ({
                empresa: empresaActual(),
                ds: el('ds', suf).value,
                cValor: el('c_valor', suf).value,
                cEtiqueta: el('c_etiqueta', suf).value,
                termino: parametros.term || ''
            }),
            processResults: (datos) => {
                pintarRecortada(suf, datos);

                const resultados = [];
                const huerfano = estado[suf].huerfano;

                if (huerfano) {
                    resultados.push({
                        text: 'Valor actual — no encontrado en la tabla',
                        children: [{ id: huerfano.valor, text: huerfano.texto, huerfano: true }]
                    });
                }

                (datos.valores || []).forEach((opcion) => {
                    resultados.push({ id: String(opcion.valor), text: opcion.etiqueta });
                });

                if (datos.recortada) {
                    resultados.push({
                        id: '__tope__',
                        text: `— Mostrando las primeras ${numero(datos.limite)} de ${numero(datos.total)} —`,
                        disabled: true
                    });
                }

                return { results: resultados };
            }
        }
    }).on('select2:select', (e) => {
        if (!esHuerfano(e.params.data)) {
            limpiarHuerfano(suf);
        }
    });

    ['c_valor', 'c_etiqueta'].forEach((base) => {
        el(base, suf).addEventListener('change', () => {
            mostrar(el('nota_cambio_tabla', suf), false);
            limpiarValor(suf);
            actualizarValor(suf);

            if (origenCompleto(suf)) {
                resolverPendiente(suf);
            }
        });
    });

    el('maneja_ds', suf).addEventListener('change', () => alternarManejaDS(suf));
};

// ── Alternar entre las dos formas del formulario ──────────────────────────────

const alternarManejaDS = async (suf) => {
    const activo = el('maneja_ds', suf).checked;
    const memoria = estado[suf].memoria;

    if (!activo) {
        memoria.ds = el('ds', suf).value;
        memoria.cValor = el('c_valor', suf).value;
        memoria.cEtiqueta = el('c_etiqueta', suf).value;

        aplicarVista(suf);
        el('valor_texto', suf).value = memoria.texto;
        el('valor_texto', suf).focus();
        return;
    }

    memoria.texto = el('valor_texto', suf).value;
    estado[suf].pendiente = memoria.texto.trim();

    aplicarVista(suf);

    try {
        await cargarTablas();
    } catch (error) {
        Toast.fire({ icon: 'error', title: error.message });
        return;
    }

    if (memoria.ds !== '') {
        el('ds', suf).value = memoria.ds;
        $(el('ds', suf)).trigger('change.select2');
        await cambioTabla(suf, false);

        ['c_valor', 'c_etiqueta'].forEach((base) => {
            const guardado = base === 'c_valor' ? memoria.cValor : memoria.cEtiqueta;

            if (guardado !== '' && el(base, suf).querySelector(`option[value="${CSS.escape(guardado)}"]`)) {
                el(base, suf).value = guardado;
            }
        });

        actualizarValor(suf);
        await resolverPendiente(suf);
    }

    enfocar(el('ds', suf));
};

// ── Validación ────────────────────────────────────────────────────────────────

const limpiarInvalidos = (suf) => {
    formulario(suf).querySelectorAll('.is-invalid')
        .forEach((campo) => campo.classList.remove('is-invalid'));
};

const marcarInvalido = (elemento, mensaje) => {
    const grupo = elemento.closest('.input-group') || elemento.parentElement;
    const feedback = grupo.querySelector('.invalid-feedback');
    const seleccion = grupo.querySelector('.select2-selection');

    elemento.classList.add('is-invalid');
    if (feedback) feedback.textContent = mensaje;
    if (seleccion) seleccion.classList.add('is-invalid');

    Toast.fire({ icon: 'warning', title: mensaje });
    enfocar(elemento);
    return false;
};

const tipoDatoElegido = (suf) =>
    document.querySelector(`input[name="tipo_dato${suf}"]:checked`)?.value || 'varchar(500)';

const validar = (suf) => {
    limpiarInvalidos(suf);

    const nombre = el('nombre', suf).value.trim();

    if (nombre === '') {
        return marcarInvalido(el('nombre', suf), 'Escriba el nombre del parámetro.');
    }

    if (el('maneja_ds', suf).checked) {
        if (el('ds', suf).value === '') return marcarInvalido(el('ds', suf), 'Elija la tabla de origen.');
        if (el('c_valor', suf).value === '') return marcarInvalido(el('c_valor', suf), 'Elija el campo valor.');
        if (el('c_etiqueta', suf).value === '') return marcarInvalido(el('c_etiqueta', suf), 'Elija el campo etiqueta.');
        if (el('valor_select', suf).value === '') return marcarInvalido(el('valor_select', suf), 'Elija un valor del origen de datos.');

        return true;
    }

    const valor = el('valor_texto', suf).value.trim();
    const tipo = tipoDatoElegido(suf);

    if (valor === '') return marcarInvalido(el('valor_texto', suf), 'Escriba el valor del parámetro.');
    if (tipo === 'int' && !/^-?\d+$/.test(valor)) return marcarInvalido(el('valor_texto', suf), 'El valor debe ser un número entero.');
    if (tipo === 'datetime' && Number.isNaN(Date.parse(valor))) return marcarInvalido(el('valor_texto', suf), 'El valor debe ser una fecha válida.');

    return true;
};

const datosParametro = (suf) => {
    const manejaDS = el('maneja_ds', suf).checked;

    return {
        empresa: empresaActual(),
        nombre: el('nombre', suf).value.trim(),
        tipoDato: tipoDatoElegido(suf),
        manejaDS: manejaDS ? 1 : 0,
        ds: manejaDS ? el('ds', suf).value : '',
        cValor: manejaDS ? el('c_valor', suf).value : '',
        cEtiqueta: manejaDS ? el('c_etiqueta', suf).value : '',
        valor: manejaDS ? el('valor_select', suf).value : el('valor_texto', suf).value
    };
};

// ── Crear ─────────────────────────────────────────────────────────────────────

const reiniciarFormulario = (suf) => {
    limpiarInvalidos(suf);
    el('nombre', suf).value = '';
    el('valor_texto', suf).value = '';
    el('maneja_ds', suf).checked = false;
    $(el('ds', suf)).val(null).trigger('change.select2');
    document.querySelector(`input[name="tipo_dato${suf}"]`).checked = true;
    estado[suf] = crearEstado();
    bloquearColumnas(suf);
    limpiarValor(suf);
    mostrar(el('nota_cambio_tabla', suf), false);
    aplicarVista(suf);
    actualizarValor(suf);
};

$id('btn_limpiar_parametro').addEventListener('click', () => {
    reiniciarFormulario('');
    el('nombre', '').focus();
});

$id('formulario_creacion').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validar('')) return;

    const boton = $id('btn_crear_parametro');
    const original = boton.innerHTML;

    boton.disabled = true;
    boton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';

    try {
        const resultado = await pedir('crear', datosParametro(''));

        aplicarHtmlTabla(resultado.tabla);
        Toast.fire({ icon: 'success', title: 'Parámetro creado.' });
        reiniciarFormulario('');
        bootstrap.Tab.getOrCreateInstance(document.querySelector('a[href="#listaParametros"]')).show();
    } catch (error) {
        if (/ya existe/i.test(error.message)) {
            Swal.fire({
                icon: 'warning',
                title: 'Ya existe un parámetro con ese nombre',
                html: `Esta empresa ya tiene un parámetro llamado «${escaparHtml(el('nombre', '').value.trim())}».`
                    + ' Cambie el nombre o edite el parámetro existente desde la lista.',
                confirmButtonText: 'Entendido'
            });
        } else {
            Toast.fire({ icon: error.message === 'Error de conexión.' ? 'error' : 'warning', title: error.message });
        }
    } finally {
        boton.disabled = false;
        boton.innerHTML = original;
    }
});

// ── Editar ────────────────────────────────────────────────────────────────────

const obtenerParametro = async (nombre) => {
    const suf = '_editar';

    reiniciarFormulario(suf);
    el('nombre', suf).value = nombre;

    try {
        const resultado = await pedir('obtener', { empresa: empresaActual(), nombre });
        const parametro = resultado.parametro;

        el('nombre', suf).value = parametro.nombre;
        el('maneja_ds', suf).checked = parametro.manejaDS === 1;

        const radio = document.querySelector(`input[name="tipo_dato${suf}"][value="${parametro.tipoDato}"]`);
        if (radio) radio.checked = true;

        aplicarVista(suf);

        if (parametro.manejaDS !== 1) {
            el('valor_texto', suf).value = parametro.valor ?? '';
            return;
        }

        await cargarTablas();

        el('ds', suf).value = parametro.ds ?? '';
        $(el('ds', suf)).trigger('change.select2');
        await cambioTabla(suf, false);

        el('c_valor', suf).value = parametro.cValor ?? '';
        el('c_etiqueta', suf).value = parametro.cEtiqueta ?? '';
        actualizarValor(suf);

        if (resultado.resuelto === false) {
            marcarHuerfano(suf, String(parametro.valor ?? ''), parametro.ds ?? '');
            return;
        }

        if ((parametro.valor ?? '') !== '') {
            seleccionarValor(suf, parametro.valor, resultado.etiqueta);
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: error.message });
    }
};

$id('formulario_editar_parametro').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validar('_editar')) return;

    const boton = $id('btn_guardar_edicion');
    const original = boton.innerHTML;

    boton.disabled = true;
    boton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';

    try {
        const resultado = await pedir('actualizar', datosParametro('_editar'));

        bootstrap.Modal.getInstance($id('modalEditarParametro')).hide();
        Toast.fire({ icon: 'success', title: 'Parámetro actualizado.' });
        aplicarHtmlTabla(resultado.tabla);
    } catch (error) {
        Toast.fire({ icon: error.message === 'Error de conexión.' ? 'error' : 'warning', title: error.message });
    } finally {
        boton.disabled = false;
        boton.innerHTML = original;
    }
});

// ── Eliminar ──────────────────────────────────────────────────────────────────

const botonesFila = (nombre) =>
    [...document.querySelectorAll('#cuerpo_tabla_parametros tr[data-nombre] .btn')]
        .filter((boton) => boton.closest('tr').dataset.nombre === nombre);

const eliminarParametro = async (nombre) => {
    const seguro = escaparHtml(nombre);

    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: `¿Eliminar el parámetro «${seguro}»?`,
        html: '<div class="text-start small">'
            + `<p class="mb-2">Se eliminará el parámetro «${seguro}» de esta empresa.</p>`
            + '<p class="mb-2">Ningún registro de la base de datos depende de él, pero los procesos lo buscan por nombre.'
            + ' Si algún proceso lo usa, fallará cuando lo necesite y el mensaje aparecerá en otro módulo, no en esta pantalla.</p>'
            + '<p class="mb-0">Esta acción no se puede deshacer.</p>'
            + '</div>',
        input: 'text',
        inputLabel: 'Escriba el nombre del parámetro para confirmar',
        inputValidator: (valor) => (String(valor ?? '').trim() === nombre ? undefined : 'El nombre no coincide.'),
        showCancelButton: true,
        confirmButtonText: 'Eliminar parámetro',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar',
        focusCancel: true,
        allowEnterKey: false
    });

    if (!confirmacion.isConfirmed) return;

    const botones = botonesFila(nombre);
    const iconoBorrar = botones.find((boton) => boton.querySelector('.bi-trash'));

    botones.forEach((boton) => { boton.disabled = true; });
    if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i>';

    try {
        const resultado = await pedir('eliminar', { empresa: empresaActual(), nombre });

        Toast.fire({ icon: 'success', title: 'Parámetro eliminado.' });
        aplicarHtmlTabla(resultado.tabla);
    } catch (error) {
        Toast.fire({ icon: error.message === 'Error de conexión.' ? 'error' : 'warning', title: error.message });
        botones.forEach((boton) => { boton.disabled = false; });
        if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
    }
};

// ── Arranque ──────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabla();
    SUFIJOS.forEach((suf) => {
        iniciarSelects(suf);
        aplicarVista(suf);
        actualizarValor(suf);
    });
    $id('filtro_empresa').addEventListener('change', listar);
});
