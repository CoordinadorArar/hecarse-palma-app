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

const URL_ENTIDADES = BASE_URL + 'maestros/entidades-auxiliares/';
const ENTIDADES_PROTEGIDAS = ['gBanco', 'gTipoCuenta'];

const ICONOS_COL1 = { codigo: 'bi-upc', tipo: 'bi-tag', item: 'bi-box-seam' };
const ICONOS_COL2 = { producto: 'bi-box', bodega: 'bi-shop' };

const $id = (id) => document.getElementById(id);
const numero = (valor) => Number(valor || 0).toLocaleString('es-CO');
const mostrar = (elemento, visible) => elemento.classList.toggle('d-none', !visible);

const escaparHtml = (texto) => String(texto ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

const estado = {
    entidad: '',
    meta: null,
    pagina: 1,
    porPagina: POR_PAGINA,
    busqueda: '',
    total: 0,
    totalSinFiltro: 0,
    totalPaginas: 0,
    modo: 'crear'
};

const cacheMeta = {};
const tbody = () => $id('cuerpo_tabla_entidades');
const empresaActual = () => $id('filtro_empresa').value;

const pedir = async (ruta, datos) => {
    const cuerpo = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor ?? ''));

    let respuesta;
    let resultado;

    try {
        respuesta = await fetch(URL_ENTIDADES + ruta, { method: 'POST', body: cuerpo });
        resultado = await respuesta.json();
    } catch (error) {
        throw new Error('Error de conexión.');
    }

    if (!respuesta.ok || !resultado.success) {
        const fallo = new Error(resultado.message || 'Error de conexión.');

        fallo.datos = resultado;
        throw fallo;
    }

    return resultado;
};

const avisar = (error) => Toast.fire({
    icon: error.message === 'Error de conexión.' ? 'error' : 'warning',
    title: error.message
});

// ── Metadatos ─────────────────────────────────────────────────────────────────

const tipoTexto = (columna) => columna.esNumerico ? columna.tipo : `${columna.tipo}(${columna.longitud})`;

const esUnCaracter = (columna) => !columna.esNumerico && Number(columna.longitud) === 1;

const cargarMetadatos = async (entidad) => {
    if (cacheMeta[entidad]) {
        return cacheMeta[entidad];
    }

    return cacheMeta[entidad] = await pedir('metadatos', { entidad });
};

const actualizarRotulos = () => {
    const [col1, col2] = estado.meta.columnas;
    const info = '<i class="bi bi-info-circle ms-1" aria-hidden="true"></i>';

    $id('th_col1').innerHTML = escaparHtml(col1.etiqueta) + info;
    $id('th_col1').title = `columna ${col1.nombre} · ${tipoTexto(col1)} · Ordenado por ${col1.etiqueta} ascendente`;
    $id('th_col2').innerHTML = escaparHtml(col2.etiqueta) + info;
    $id('th_col2').title = `columna ${col2.nombre} · ${tipoTexto(col2)}`;
};

// ── Grilla ────────────────────────────────────────────────────────────────────

const accionesFila = (v1, v2) => {
    const editar = estado.meta.llaveCompleta
        ? '<span class="d-inline-block maestros-accion-bloqueada" tabindex="0"'
            + ' title="Las dos columnas forman la llave del registro: no hay nada que editar. Elimine la fila y cree la correcta.">'
            + '<button type="button" class="btn btn-outline-secondary btn-sm pe-none" disabled aria-disabled="true" tabindex="-1">'
            + '<i class="bi bi-pencil" aria-hidden="true"></i></button></span>'
        : '<button type="button" class="btn btn-outline-secondary btn-sm" data-accion="editar" title="Editar registro">'
            + '<i class="bi bi-pencil" aria-hidden="true"></i></button>';

    return '<div class="d-flex justify-content-center gap-1">' + editar
        + `<button type="button" class="btn btn-outline-danger btn-sm" data-accion="eliminar" title="Eliminar registro" data-valor1="${v1}" data-valor2="${v2}">`
        + '<i class="bi bi-trash" aria-hidden="true"></i></button></div>';
};

const filaHtml = (fila) => {
    const col2 = estado.meta.columnas[1];
    const v1 = escaparHtml(fila.valor1);
    const v2 = escaparHtml(fila.valor2);
    const texto2 = fila.valor2 === null
        ? `<span class="maestros-vacio">Sin ${escaparHtml(col2.etiqueta.toLowerCase())}</span>`
        : `<span class="maestros-truncar maestros-truncar-dato" title="${v2}">${v2}</span>`;

    return `<tr data-valor1="${v1}" data-valor2="${v2}">`
        + `<td class="font-monospace fw-semibold">${v1}</td>` /* 1 */
        + `<td>${texto2}</td>` /* 2 */
        + `<td class="text-center">${accionesFila(v1, v2)}</td>` /* 3 */
        + '</tr>';
};

const pintarEsqueleto = () => {
    const celda = (ancho) => `<td><span class="placeholder" style="width:${ancho}"></span></td>`;

    tbody().innerHTML = Array.from({ length: 5 }, () =>
        '<tr class="placeholder-glow">'
        + celda('60%') /* 1 */
        + celda('85%') /* 2 */
        + celda('40%') /* 3 */
        + '</tr>'
    ).join('');
};

const velo = (activo) => {
    const contenedor = $id('contenedor_tabla');

    contenedor.classList.toggle('maestros-grilla-relativa', activo);
    contenedor.setAttribute('aria-busy', activo ? 'true' : 'false');
    contenedor.querySelector('.maestros-velo')?.remove();

    if (!activo) {
        return;
    }

    const capa = document.createElement('div');

    capa.className = 'maestros-velo';
    capa.innerHTML = '<span class="spinner-border text-primary" role="status" aria-hidden="true"></span>';
    contenedor.appendChild(capa);
};

const bloquearControles = (activo) => {
    $id('btn_buscar').disabled = activo || estado.entidad === '';
    $id('tam_pagina').disabled = activo;

    if (activo) {
        $id('paginador').querySelectorAll('.page-link').forEach((boton) => { boton.disabled = true; });
        return;
    }

    actualizarPaginado();
};

const actualizarContador = () => {
    const plural = estado.total === 1 ? 'registro encontrado' : 'registros encontrados';
    const detalle = estado.busqueda === ''
        ? `<strong>${numero(estado.total)}</strong> ${plural}`
        : `<strong>${numero(estado.total)}</strong> de ${numero(estado.totalSinFiltro)} ${plural}`;

    $id('contador_registros').innerHTML = `<i class="bi bi-collection me-1"></i>${detalle}`;
};

const actualizarPaginado = () => {
    const desde = (estado.pagina - 1) * estado.porPagina + 1;
    const hasta = Math.min(estado.pagina * estado.porPagina, estado.total);

    $id('info_paginado').innerHTML =
        `Mostrando <strong>${numero(desde)}–${numero(hasta)}</strong> de <strong>${numero(estado.total)}</strong> registros`;

    const control = (etiqueta, destino, inhabilitado) =>
        `<li class="page-item${inhabilitado ? ' disabled' : ''}">`
        + `<button type="button" class="page-link" data-pagina="${destino}"${inhabilitado ? ' disabled' : ''}>${etiqueta}</button></li>`;

    const primera = estado.pagina <= 1;
    const ultima = estado.pagina >= estado.totalPaginas;

    $id('paginador').innerHTML = control('Primero', 1, primera)
        + control('Anterior', estado.pagina - 1, primera)
        + `<li class="page-item disabled"><button type="button" class="page-link" data-fija="1" disabled>Página ${numero(estado.pagina)} de ${numero(estado.totalPaginas)}</button></li>`
        + control('Siguiente', estado.pagina + 1, ultima)
        + control('Último', estado.totalPaginas, ultima);
};

const aplicarEstados = (cantidad) => {
    const sinEntidad = estado.entidad === '';
    const conFiltro = cantidad === 0 && estado.busqueda !== '';
    const vacia = cantidad === 0 && estado.busqueda === '';

    mostrar($id('estado_sin_entidad'), sinEntidad);
    mostrar($id('estado_vacio'), !sinEntidad && vacia);
    mostrar($id('estado_filtro'), !sinEntidad && conFiltro);
    mostrar($id('estado_error'), false);
    mostrar($id('nota_desactualizada'), false);
    mostrar($id('contenedor_tabla'), !sinEntidad && cantidad > 0);
    mostrar($id('pie_tabla'), !sinEntidad && cantidad > 0 && estado.totalPaginas > 1);

    if (vacia && !sinEntidad) {
        $id('titulo_estado_vacio').innerHTML = `«${escaparHtml(estado.entidad)}» no tiene registros en esta empresa`;
    }

    if (conFiltro) {
        $id('titulo_estado_filtro').innerHTML = `Ningún registro coincide con «${escaparHtml(estado.busqueda)}»`;
    }
};

const mostrarEstadoError = (mensaje) => {
    mostrar($id('estado_sin_entidad'), false);
    mostrar($id('estado_vacio'), false);
    mostrar($id('estado_filtro'), false);
    mostrar($id('nota_desactualizada'), false);
    $id('mensaje_estado_error').textContent = mensaje;
    mostrar($id('estado_error'), true);
};

const mostrarNotaDesactualizada = (mensaje) => {
    $id('mensaje_nota_desactualizada').textContent =
        `${mensaje} Los registros que ve quedaron sin actualizar; reintente para verlos al día.`;
    mostrar($id('nota_desactualizada'), true);
};

const cargar = async () => {
    const conFilas = tbody().querySelector('tr[data-valor1]') !== null;

    if (conFilas) {
        velo(true);
    } else {
        pintarEsqueleto();
        mostrar($id('contenedor_tabla'), true);
        mostrar($id('estado_sin_entidad'), false);
        mostrar($id('estado_vacio'), false);
        mostrar($id('estado_filtro'), false);
        mostrar($id('estado_error'), false);
    }

    bloquearControles(true);

    try {
        const resultado = await pedir('listar', {
            entidad: estado.entidad,
            empresa: empresaActual(),
            pagina: estado.pagina,
            porPagina: estado.porPagina,
            busqueda: estado.busqueda
        });

        estado.meta = {
            entidad: resultado.entidad,
            columnas: resultado.columnas,
            llaveCompleta: resultado.llaveCompleta
        };
        estado.pagina = resultado.pagina;
        estado.porPagina = resultado.porPagina;
        estado.total = resultado.total;
        estado.totalSinFiltro = resultado.totalSinFiltro;
        estado.totalPaginas = resultado.totalPaginas;

        actualizarRotulos();
        tbody().innerHTML = resultado.filas.map(filaHtml).join('');
        actualizarContador();
        actualizarPaginado();
        aplicarEstados(resultado.filas.length);
    } catch (error) {
        avisar(error);

        if (conFilas) {
            mostrarNotaDesactualizada(error.message);
        } else {
            tbody().innerHTML = '';
            mostrar($id('contenedor_tabla'), false);
            mostrar($id('pie_tabla'), false);
            mostrarEstadoError(error.message);
        }
    } finally {
        velo(false);
        bloquearControles(false);
    }
};

// ── Filtros ───────────────────────────────────────────────────────────────────

const reiniciarGrilla = () => {
    tbody().innerHTML = '';
    estado.total = 0;
    estado.totalSinFiltro = 0;
    estado.totalPaginas = 0;
    aplicarEstados(0);
};

const cambiarEntidad = async () => {
    estado.entidad = $id('filtro_entidad').value;
    estado.busqueda = '';
    estado.pagina = 1;
    $id('filtro_busqueda').value = '';
    tbody().innerHTML = '';
    mostrar($id('contenedor_tabla'), false);

    const buscador = $id('filtro_busqueda');

    if (estado.entidad === '') {
        estado.meta = null;
        buscador.disabled = true;
        buscador.placeholder = 'Elija primero una entidad';
        $id('btn_limpiar_busqueda').disabled = true;
        $id('btn_buscar').disabled = true;
        $id('btn_nuevo').disabled = true;
        mostrar($id('nota_llave_completa'), false);
        $id('contador_registros').innerHTML = '';
        reiniciarGrilla();
        return;
    }

    try {
        const meta = await cargarMetadatos(estado.entidad);

        estado.meta = { entidad: meta.entidad, columnas: meta.columnas, llaveCompleta: meta.llaveCompleta };
        buscador.disabled = false;
        buscador.placeholder = `Buscar por ${meta.columnas[0].etiqueta} o ${meta.columnas[1].etiqueta}`;
        $id('btn_limpiar_busqueda').disabled = false;
        $id('btn_buscar').disabled = false;
        $id('btn_nuevo').disabled = false;
        mostrar($id('nota_llave_completa'), meta.llaveCompleta);
        actualizarRotulos();
    } catch (error) {
        estado.meta = null;
        buscador.disabled = true;
        buscador.placeholder = 'No se pudo preparar la entidad';
        $id('btn_limpiar_busqueda').disabled = true;
        $id('btn_buscar').disabled = true;
        $id('btn_nuevo').disabled = true;
        mostrar($id('pie_tabla'), false);
        $id('contador_registros').innerHTML = '';
        avisar(error);
        mostrarEstadoError(error.message);
        return;
    }

    await cargar();
};

const buscar = () => {
    if (estado.entidad === '') {
        return;
    }

    estado.busqueda = $id('filtro_busqueda').value.trim();
    estado.pagina = 1;
    cargar();
};

let temporizador = null;

$id('filtro_busqueda').addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(buscar, 400);
});

$id('filtro_busqueda').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(temporizador);
        buscar();
    }
});

$id('btn_buscar').addEventListener('click', () => {
    clearTimeout(temporizador);
    buscar();
});

const limpiarBusqueda = () => {
    clearTimeout(temporizador);
    $id('filtro_busqueda').value = '';

    if (estado.busqueda !== '') {
        buscar();
    }
};

$id('btn_limpiar_busqueda').addEventListener('click', limpiarBusqueda);
$id('btn_limpiar_filtro').addEventListener('click', limpiarBusqueda);

const reintentar = () => {
    if (estado.entidad !== '') {
        cargar();
    }
};

$id('btn_reintentar').addEventListener('click', reintentar);
$id('btn_reintentar_nota').addEventListener('click', reintentar);

$id('filtro_empresa').addEventListener('change', () => {
    if (estado.entidad === '') {
        return;
    }

    estado.pagina = 1;
    cargar();
});

$id('tam_pagina').addEventListener('change', () => {
    estado.porPagina = Number($id('tam_pagina').value) || POR_PAGINA;
    estado.pagina = 1;
    cargar();
});

$id('paginador').addEventListener('click', (e) => {
    const boton = e.target.closest('.page-link');

    if (!boton || boton.disabled || boton.dataset.pagina === undefined) {
        return;
    }

    const destino = Number(boton.dataset.pagina);

    if (destino < 1 || destino > estado.totalPaginas || destino === estado.pagina) {
        return;
    }

    estado.pagina = destino;
    cargar();
});

// ── Modal ─────────────────────────────────────────────────────────────────────

const modal = () => bootstrap.Modal.getOrCreateInstance($id('modalEntidadAuxiliar'));

const limpiarInvalidos = () => {
    $id('formulario_entidad').querySelectorAll('.is-invalid')
        .forEach((campo) => campo.classList.remove('is-invalid'));
};

const enfocar = (elemento) => {
    const seleccion = elemento.closest('.input-group')?.querySelector('.select2-selection');

    (seleccion || elemento).focus();
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

const prepararCampo = (indice, columna) => {
    const entrada = $id('valor' + indice);
    const icono = $id('icono_valor' + indice);
    const mapa = indice === 1 ? ICONOS_COL1 : ICONOS_COL2;
    const respaldo = indice === 1 ? 'bi-hash' : 'bi-card-text';

    $id('rotulo_valor' + indice).textContent = columna.etiqueta;
    icono.className = 'bi ' + (mapa[columna.nombre.toLowerCase()] || respaldo);

    entrada.classList.remove('maestros-campo-mini', 'maestros-campo-corto');
    entrada.removeAttribute('inputmode');

    if (columna.esNumerico) {
        entrada.classList.add('maestros-campo-corto');
        entrada.setAttribute('inputmode', 'numeric');
        entrada.maxLength = 10;
        $id('ayuda_valor' + indice).textContent = 'Solo números enteros.';
    } else if (esUnCaracter(columna)) {
        entrada.classList.add('maestros-campo-mini');
        entrada.maxLength = 1;
        $id('ayuda_valor' + indice).textContent = 'Un solo carácter.';
    } else {
        entrada.maxLength = Number(columna.longitud) > 0 ? Number(columna.longitud) : 50;
        $id('ayuda_valor' + indice).textContent = `Máximo ${numero(entrada.maxLength)} caracteres.`;
    }

    entrada.placeholder = columna.nombre;
};

const abrirModal = (modo, fila) => {
    const [col1, col2] = estado.meta.columnas;
    const edicion = modo === 'editar';

    estado.modo = modo;
    limpiarInvalidos();

    $id('titulo_modal').innerHTML = edicion
        ? '<i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar registro'
        : '<i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo registro';
    $id('chip_entidad').textContent = estado.entidad;

    prepararCampo(1, col1);
    prepararCampo(2, col2);
    mostrar($id('obligatorio_valor2'), !col2.admiteNulo);

    $id('valor1').value = edicion ? (fila.valor1 ?? '') : '';
    $id('valor2').value = edicion ? (fila.valor2 ?? '') : '';

    $id('valor1').readOnly = edicion;
    $id('valor1').classList.toggle('bg-light', edicion);
    mostrar($id('candado_valor1'), edicion);

    if (edicion) {
        $id('valor1').setAttribute('tabindex', '-1');
        $id('valor1').setAttribute('aria-readonly', 'true');
        $id('ayuda_valor1').textContent = `El ${col1.etiqueta} identifica el registro y no se puede cambiar aquí.`
            + ' Para corregirlo, cree uno nuevo con el valor correcto y elimine este.';
    } else {
        $id('valor1').removeAttribute('tabindex');
        $id('valor1').removeAttribute('aria-readonly');
    }

    modal().show();
    setTimeout(() => $id(edicion ? 'valor2' : 'valor1').focus(), 300);
};

const validar = () => {
    limpiarInvalidos();

    const campos = [
        { entrada: $id('valor1'), columna: estado.meta.columnas[0], llave: true },
        { entrada: $id('valor2'), columna: estado.meta.columnas[1], llave: false }
    ];

    for (const campo of campos) {
        if (estado.modo === 'editar' && campo.llave) {
            continue;
        }

        const valor = campo.entrada.value.trim();
        const rotulo = campo.columna.etiqueta;

        if (valor === '') {
            if (campo.llave || !campo.columna.admiteNulo) {
                return marcarInvalido(campo.entrada, `Escriba el ${rotulo}.`);
            }

            continue;
        }

        if (campo.columna.esNumerico && !/^-?\d+$/.test(valor)) {
            return marcarInvalido(campo.entrada, `El ${rotulo} debe ser un número entero.`);
        }

        if (esUnCaracter(campo.columna) && valor.length !== 1) {
            return marcarInvalido(campo.entrada, `El ${rotulo} debe tener un solo carácter.`);
        }
    }

    return true;
};

$id('btn_nuevo').addEventListener('click', () => abrirModal('crear'));
$id('btn_crear_primero').addEventListener('click', () => abrirModal('crear'));

$id('formulario_entidad').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validar()) {
        return;
    }

    const boton = $id('btn_guardar');
    const original = boton.innerHTML;
    const edicion = estado.modo === 'editar';

    boton.disabled = true;
    boton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';

    try {
        await pedir(edicion ? 'actualizar' : 'crear', {
            entidad: estado.entidad,
            empresa: empresaActual(),
            valor1: $id('valor1').value.trim(),
            valor2: $id('valor2').value.trim()
        });

        modal().hide();
        Toast.fire({ icon: 'success', title: edicion ? 'Registro actualizado.' : 'Registro creado.' });
        await cargar();
    } catch (error) {
        if (!edicion && /ya existe/i.test(error.message)) {
            const rotulo = estado.meta.columnas[0].etiqueta;

            Swal.fire({
                icon: 'warning',
                title: `Ya existe un registro con ese ${escaparHtml(rotulo)}`,
                html: `«${escaparHtml(estado.entidad)}» ya tiene un registro con el ${escaparHtml(rotulo)}`
                    + ` «${escaparHtml($id('valor1').value.trim())}» en esta empresa.`
                    + ` Cambie el ${escaparHtml(rotulo)} o cierre esta ventana y edite el registro existente desde la lista.`,
                confirmButtonText: 'Entendido'
            });
        } else {
            avisar(error);
        }
    } finally {
        boton.disabled = false;
        boton.innerHTML = original;
    }
});

// ── Editar y eliminar ─────────────────────────────────────────────────────────

const abrirEdicion = async (valor1, valor2) => {
    try {
        const resultado = await pedir('obtener', {
            entidad: estado.entidad,
            empresa: empresaActual(),
            valor1,
            valor2
        });

        abrirModal('editar', resultado.fila);
    } catch (error) {
        avisar(error);
    }
};

const botonesFila = (fila) => [...fila.querySelectorAll('.btn')];

const avisarDependencias = (datos, valor2) => {
    const detalle = (datos.dependencias || [])
        .map((dependencia) => `<li><strong>${escaparHtml(dependencia.tabla)}</strong> · ${numero(dependencia.filas)} `
            + `${Number(dependencia.filas) === 1 ? 'registro' : 'registros'}</li>`)
        .join('');

    Swal.fire({
        icon: 'error',
        title: `«${escaparHtml(valor2)}» está en uso`,
        html: `${escaparHtml(datos.message)}<ul class="text-start small mb-0">${detalle}</ul>`,
        confirmButtonText: 'Entendido'
    });
};

const confirmarEliminacion = (valor1, valor2) => {
    const v1 = escaparHtml(valor1);
    const v2 = escaparHtml(valor2);
    const entidad = escaparHtml(estado.entidad);
    const rotuloSeguro = escaparHtml(estado.meta.columnas[0].etiqueta);
    const comun = {
        icon: 'warning',
        title: `¿Eliminar «${v1} — ${v2}»?`,
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        focusCancel: true
    };

    if (ENTIDADES_PROTEGIDAS.includes(estado.entidad)) {
        return Swal.fire({
            ...comun,
            html: `<div class="text-start small">Se eliminará del catálogo ${entidad} de esta empresa.<br>`
                + 'Si existe un movimiento registrado que lo referencie, la base de datos bloqueará el borrado.<br>'
                + 'Otros registros pueden usarlo sin que la base lo impida: en esas pantallas quedaría el código sin su descripción.<br>'
                + 'Esta acción no se puede deshacer.</div>',
            confirmButtonText: 'Sí, eliminar'
        });
    }

    return Swal.fire({
        ...comun,
        html: '<div class="text-start small">'
            + `<p class="mb-2">Se eliminará del catálogo ${entidad} de esta empresa.</p>`
            + '<p class="mb-2">Otros registros pueden guardar este código en una columna sin validación.'
            + ` Si alguno lo usa, seguirá guardando «${v1}» y en esa pantalla se verá el código sin su descripción.</p>`
            + '<p class="mb-0">Esta acción no se puede deshacer.</p>'
            + '</div>',
        input: 'text',
        inputLabel: `Escriba el ${rotuloSeguro} para confirmar`,
        inputValidator: (valor) => (String(valor ?? '').trim() === valor1 ? undefined : `El ${rotuloSeguro} no coincide.`),
        confirmButtonText: 'Eliminar registro',
        allowEnterKey: false
    });
};

const eliminarFila = async (fila, valor1, valor2) => {
    const confirmacion = await confirmarEliminacion(valor1, valor2);

    if (!confirmacion.isConfirmed) {
        return;
    }

    const botones = botonesFila(fila);
    const iconoBorrar = botones.find((boton) => boton.querySelector('.bi-trash'));

    botones.forEach((boton) => { boton.disabled = true; });
    if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i>';

    try {
        await pedir('eliminar', {
            entidad: estado.entidad,
            empresa: empresaActual(),
            valor1,
            valor2
        });

        Toast.fire({ icon: 'success', title: 'Registro eliminado.' });

        if (estado.total - 1 <= (estado.pagina - 1) * estado.porPagina && estado.pagina > 1) {
            estado.pagina -= 1;
        }

        await cargar();
    } catch (error) {
        if (error.datos?.dependencias?.length) {
            avisarDependencias(error.datos, valor2);
            await cargar();
            return;
        }

        avisar(error);
        botones.forEach((boton) => { boton.disabled = false; });
        if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
    }
};

tbody().addEventListener('click', (e) => {
    const boton = e.target.closest('[data-accion]');

    if (!boton) {
        return;
    }

    const fila = boton.closest('tr');
    const valor1 = fila.dataset.valor1 ?? '';
    const valor2 = fila.dataset.valor2 ?? '';

    if (boton.dataset.accion === 'editar') {
        abrirEdicion(valor1, valor2);
        return;
    }

    eliminarFila(fila, valor1, valor2);
});

// ── Arranque ──────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    $('#filtro_entidad').select2({
        width: '100%',
        placeholder: 'Escriba para buscar la entidad…',
        allowClear: false,
        templateResult: (dato) => dato.children ? dato.text : $(`<span class="font-monospace">${escaparHtml(dato.text)}</span>`),
        templateSelection: (dato) => dato.id === '' ? dato.text : $(`<span class="font-monospace">${escaparHtml(dato.text)}</span>`),
        language: {
            noResults: () => 'No se encontraron resultados',
            searching: () => 'Buscando…'
        }
    }).on('change', cambiarEntidad);

    $id('tam_pagina').value = String(estado.porPagina);
    aplicarEstados(0);
});
