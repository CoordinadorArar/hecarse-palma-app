document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const visible = (el) => Boolean(el) && el.getClientRects().length > 0 && !el.parentElement?.closest('.d-none');
    const cargandoSelect = (id, texto = 'Cargando…') => {
        const el = document.getElementById(id);

        return Boolean(el?.disabled) && [...el.options].some((opcion) => opcion.text.trim() === texto) && visible(el);
    };

    window.Tutoriales?.registrar('fertilizacion', {
        titulo: 'Fertilización',
        antesDeIniciar: () => {
            const periodo = document.getElementById('periodo');
            const ocupado = (periodo?.disabled && periodo.options[0]?.text.trim() === 'Cargando…' && visible(periodo))
                || ['#panel_registro.active #lista_lineas [aria-busy="true"]', '#btn_liquidar .spinner-border', '#btn_guardar .spinner-border', '#panel_consulta.active #con_aviso .spinner-border']
                    .some((selector) => [...document.querySelectorAll(selector)].some(visible))
                || cargandoSelect('cap_seccion')
                || cargandoSelect('cap_lote')
                || cargandoSelect('pfa_lote')
                || cargandoSelect('referencia', 'Cargando planes…');

            return ocupado ? 'Espere a que termine de cargar para iniciar el tutorial.' : null;
        },
        pasos: () => {
            const reg = Boolean($q('#panel_registro.active'));
            const con = Boolean($q('#panel_consulta.active'));
            const md = window.matchMedia('(min-width: 768px)').matches;
            const lg = window.matchMedia('(min-width: 992px)').matches;
            const sinTipo = Boolean($q('#bloque_transaccion[inert]'));
            const edicion = Boolean($q('#chip_estado.ambar')) || $q('#btn_guardar')?.textContent.trim() === 'Guardar cambios';
            const linea = $q('#lista_lineas .tx-tarjeta');
            const permisos = typeof PERMISOS !== 'undefined' ? PERMISOS : {};
            const textoAcciones = permisos.editar
                ? (permisos.eliminar
                    ? 'Use el lápiz para abrir la transacción en el formulario y editarla, o la papelera para eliminarla con un motivo opcional. Los botones atenuados no están disponibles porque la transacción está anulada, en un periodo cerrado o liquidada en nómina.'
                    : 'Use el lápiz para abrir la transacción en el formulario y editarla. Si aparece atenuado, la transacción no se puede modificar.')
                : 'Use la papelera para eliminar la transacción con un motivo opcional. Si aparece atenuada, la transacción no se puede eliminar.';
            const habilita = sinTipo ? ' Se habilita al elegir el tipo.' : '';
            const esFecha = $q('#con_campo')?.value === 'fecha';
            const candado = $q('#con_filas .bi-lock, #con_filas .bi-lock-fill, #con_filas .bi-unlock-fill');
            const liquidada = Boolean(candado?.classList.contains('bi-lock-fill'));
            const abierto = Boolean(candado?.classList.contains('bi-unlock-fill'));
            const vacio = $q('#con_aviso .gp-estado-vacio');
            const textoVacio = $q('#btn_reintentar')
                ? 'No se pudo completar la consulta. Pulse Reintentar o ajuste el filtro.'
                : (vacio?.querySelector('.bi-lock') ? 'Su usuario no tiene permiso para consultar estas transacciones.' : 'Ninguna transacción coincide con el filtro. Ajústelo o deje el valor vacío para ver todas.');
            const pfa = $q('#zona_formulario')?.dataset.tipo === 'PFA';
            const sinFinca = !$q('#cap_finca')?.value;
            const sinPlanes = visible($q('#ayuda_sin_planes'));
            const planeada = Boolean($q('#lista_lineas .tx-tarjeta--planeada'));
            const btnPlaOff = Boolean($q('#btn_planeaciones')?.disabled);
            const loteOff = Boolean($q('#cap_lote')?.disabled);
            const pfaLoteOff = Boolean($q('#pfa_lote')?.disabled);
            const tarjetaPfa = $q('#pfa_lista .tx-tarjeta');

            return [
                { id: 'bienvenida', title: 'Fertilización', text: con ? 'Aquí consulta las transacciones de fertilización guardadas y, según sus permisos, las edita o elimina. Use Siguiente para recorrer las opciones principales.' : 'Aquí registra las labores de fertilización (RLF) con sus insumos y crea los planes de fertilización por lote (PFA); también consulta, edita o elimina las transacciones guardadas. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => document.getElementById('filtro_empresa')?.closest('.input-group'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa con la que va a registrar o consultar.' },
                { id: 'pestanas', element: '#pestanas', side: lg ? 'left' : 'bottom', title: 'Registro y consulta', text: reg ? 'Use Consulta para buscar transacciones guardadas y editarlas o eliminarlas.' : 'Use Registro para crear una transacción nueva.' },
                { id: 'planeacion', element: '#nota_planeacion', side: 'bottom', title: 'Actividades de la planeación', text: 'Indica cuántas actividades de la planeación agregó al detalle. Esas líneas llevan la etiqueta Planeación; revise palmas, insumos y terceros y guarde para registrarlas.', cuando: () => reg },
                { id: 'tipo', element: '#card_tipo', side: 'bottom', title: 'Tipo de transacción', text: edicion ? 'Está editando una transacción existente; el tipo y el número no se pueden cambiar.' : 'Elija RLF para registrar labores de fertilización o PFA para crear un plan de fertilización por lote. El formulario se adapta al tipo y el número se asigna al guardar.', cuando: () => reg },
                { id: 'datos', element: '#card_datos', side: md ? 'top' : 'bottom', title: 'Encabezado de la transacción', text: pfa ? 'Indique año, periodo, fecha inicial y final del plan y la finca; la fecha final, por defecto, es la inicial. La observación es opcional.' : `Indique año, periodo, fecha y finca. La observación es opcional.${habilita}`, cuando: () => reg },
                { id: 'referencia', element: () => document.getElementById('referencia')?.closest('[data-solo]'), side: 'bottom', title: 'Referencia', text: sinPlanes ? 'La finca no tiene planes PFA vigentes. Cree el plan con el tipo PFA o elija otra finca.' : `Elija el plan PFA de la finca. Define los lotes que puede registrar, sus palmas y los insumos que se sugieren en cada línea.${sinFinca ? ' Se habilita al elegir la finca.' : ''}`, cuando: () => reg },
                { id: 'planeaciones', element: '#btn_planeaciones', side: 'bottom', title: 'Planeaciones completadas', text: btnPlaOff ? 'Aquí agrega al detalle las labores de fertilización cerradas como Completada o Parcial en la planeación que aún no se han registrado; el número verde indica cuántas hay. Se habilita al elegir la finca y la referencia.' : 'Aquí agrega al detalle las labores de fertilización cerradas como Completada o Parcial en la planeación que aún no se han registrado; el número verde indica cuántas hay. Márquelas (o use la casilla del encabezado para marcarlas todas) y pulse Agregar al detalle: se crean las líneas con sus terceros, palmas e insumos del plan.', cuando: () => reg },
                { id: 'planeaciones_filtros', element: '#btn_planeaciones', side: 'bottom', title: 'Filtros de planeaciones', text: 'Se muestran las del periodo y la finca seleccionados. Puede acotar las fechas o buscar por lote, labor o trabajador. Las filas atenuadas no se pueden elegir y muestran el motivo: ya agregada, fuera del periodo u otra referencia. Máximo 50 actividades por transacción.', cuando: () => reg && !btnPlaOff },
                { id: 'labor', element: () => document.getElementById('cap_codigo')?.closest('.col-12'), side: 'bottom', title: 'Labor', text: 'Escriba el código de la labor de fertilización o búsquela en la lista. La unidad de medida se completa sola; ajústela si es necesario.', cuando: () => reg },
                { id: 'lote', element: () => document.getElementById('cap_lote')?.closest('.col-6'), side: 'bottom', title: 'Sección y lote', text: `Elija el lote del plan; la sección es opcional y sirve para filtrar los lotes. Al elegirlo verá las palmas del plan bajo Cantidad.${loteOff ? ' Se habilita al elegir la referencia.' : ''}`, cuando: () => reg },
                { id: 'fecha_labor', element: () => document.getElementById('cap_fecha')?.closest('.col-12'), side: 'bottom', title: 'Fecha labor', text: 'Por defecto es la fecha de la transacción. Cámbiela si la labor se hizo otro día; debe quedar dentro del periodo.', cuando: () => reg },
                { id: 'terceros', element: () => document.getElementById('cap_terceros')?.closest('.col-12'), side: md ? 'top' : 'bottom', title: 'Terceros', text: 'Busque y agregue los terceros por código, identificación o nombre (mínimo 3 caracteres).', cuando: () => reg },
                { id: 'cargar', element: () => document.getElementById('btn_cargar')?.parentElement, side: md ? 'top' : 'bottom', title: 'Cargar y liquidar', text: 'Indique la cantidad de palmas fertilizadas y pulse Cargar: se reparte en partes iguales entre los terceros. Si supera las palmas del plan verá un aviso. Liquidar recalcula los precios de todas las líneas.', cuando: () => reg },
                { id: 'lineas', element: () => linea || document.getElementById('estado_sin_lineas'), side: 'top', title: 'Líneas cargadas', text: linea ? `Cada línea muestra labor, fecha, sección / lote, terceros e insumos. Revise los insumos sugeridos del plan (dosis por palma), agregue o quite los que necesite, edite o quite terceros, o elimine la línea completa.${planeada ? ' Las líneas con la etiqueta Planeación vienen de la planeación; si las quita, vuelven a quedar disponibles en Planeaciones completadas.' : ''}` : 'Aquí aparecerán las líneas que cargue o agregue desde Planeaciones completadas.', cuando: () => reg },
                { id: 'totales', element: () => document.getElementById('total_lineas')?.closest('.card-footer'), side: 'top', title: 'Totales', text: 'Revise líneas, trabajadores, cantidad, kg de insumos y valor; los insumos en otras unidades se muestran debajo. El valor queda pendiente si hay líneas sin liquidar.', cuando: () => reg },
                { id: 'pfa_uso', element: '#pfa_nota_uso', side: 'bottom', title: 'Plan en uso', text: 'Este plan ya está referenciado por registros de labores (RLF): puede editarlo, pero no quitar lotes que ya tengan labores registradas.', cuando: () => reg },
                { id: 'pfa_captura', element: () => document.getElementById('pfa_lote')?.closest('.gp-panel-opcion'), side: 'bottom', title: 'Cargar insumos al plan', text: `Elija el lote y el insumo, indique unidad, dosis por palma y peso del bulto, y pulse Cargar. Las palmas vienen del lote y los bultos se calculan solos.${pfaLoteOff && sinFinca ? ' El lote se habilita al elegir la finca.' : ''}`, cuando: () => reg },
                { id: 'pfa_lotes', element: () => tarjetaPfa || document.getElementById('pfa_vacio'), side: 'top', title: 'Lotes del plan', text: tarjetaPfa ? 'Cada lote muestra sus palmas, insumos, total kg y bultos. Lo cargado no se modifica: quite el insumo o el lote y vuelva a cargar.' : 'Aquí aparecerán los lotes con los insumos que cargue.', cuando: () => reg },
                { id: 'pfa_totales', element: () => document.getElementById('pfa_total_lotes')?.closest('.card-footer'), side: 'top', title: 'Totales del plan', text: 'Revise el total de lotes, insumos, kg y bultos del plan.', cuando: () => reg },
                { id: 'guardar', element: () => document.getElementById('btn_guardar')?.parentElement, side: 'top', title: 'Guardar', text: `${edicion ? 'Pulse Guardar cambios para actualizar la transacción; Cancelar sale de la edición.' : 'Pulse Guardar (o Ctrl+S) para registrar la transacción; Cancelar descarta los datos.'}${sinTipo ? ' Guardar se habilita al elegir el tipo.' : ''}`, cuando: () => reg },
                { id: 'campo', element: () => document.getElementById('con_campo')?.closest('.col-12'), side: 'bottom', title: 'Campo', text: 'Elija el campo por el que desea filtrar: fecha, finca, número, observación, referencia o tipo.', cuando: () => con },
                { id: 'operador', element: () => document.getElementById('con_operador')?.parentElement, side: 'bottom', title: 'Operador', text: esFecha ? 'Elija cómo comparar la fecha: igual, diferente, mayor o menor. Contiene solo está disponible para campos de texto.' : 'Elija cómo comparar el valor. Contiene busca el texto en cualquier parte del campo.', cuando: () => con },
                { id: 'valor', element: () => document.getElementById('con_valor')?.closest('.col-6'), side: 'bottom', title: 'Valor', text: esFecha ? 'Seleccione la fecha a comparar. Déjela vacía para ver todas las transacciones.' : 'Escriba el valor a buscar. Déjelo vacío para ver todas las transacciones.', cuando: () => con },
                { id: 'botones_consulta', element: () => document.getElementById('btn_consultar')?.parentElement, side: 'bottom', title: 'Filtrar', text: 'Pulse Filtrar (o Enter) para aplicar el filtro. Limpiar vuelve a Fecha con el valor vacío y muestra todas.', cuando: () => con },
                { id: 'contador', element: '#contador_consulta', side: 'bottom', title: 'Resultados', text: 'Indica cuántas transacciones coinciden con el filtro.', cuando: () => con },
                { id: 'truncado', element: '#con_truncado', side: 'bottom', title: 'Límite de resultados', text: 'Solo se muestran los primeros 500 resultados. Use un filtro más específico para ver el resto.', cuando: () => con },
                { id: 'sin_resultados', element: '#con_aviso .gp-estado-vacio', side: 'top', title: 'Sin resultados', text: textoVacio, cuando: () => con },
                { id: 'buscar_resultados', element: '#con_tabla .tx-dt-barra:not(.tx-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Busque dentro de los resultados por número, finca, referencia u observación sin volver a filtrar. Elija también cuántos registros ver por página.', cuando: () => con },
                { id: 'acciones_consulta', element: '#con_filas tr .btn-group', side: md ? 'right' : 'bottom', title: 'Editar o eliminar', text: textoAcciones, cuando: () => con && Boolean(permisos.editar || permisos.eliminar) },
                { id: 'habilitar', element: '#con_filas [data-habilitar]', side: md ? 'right' : 'bottom', title: 'Habilitar edición', text: 'Habilite una transacción liquidada para que se edite una sola vez. Debe indicar el motivo; la habilitación vence en 24 horas y la transacción se bloquea de nuevo al guardar.', cuando: () => con && Boolean(permisos.habilitar) },
                { id: 'candado', element: () => candado, side: md ? 'right' : 'bottom', title: abierto ? 'Edición habilitada' : 'Transacción bloqueada', text: abierto ? 'El candado abierto indica que su superior habilitó la transacción para una sola edición. Pase el cursor sobre él para ver hasta cuándo está vigente; al guardar, queda bloqueada de nuevo.' : liquidada ? 'El candado cerrado indica que la transacción está liquidada en nómina: no se puede editar ni eliminar. Si necesita corregirla, solicite a su superior que la habilite para edición.' : 'El candado indica que la transacción está anulada o en un periodo cerrado: no se puede editar ni eliminar. Pase el cursor sobre él para ver el motivo.', cuando: () => con },
                { id: 'estado', element: '#con_filas tr .badge', side: 'left', title: 'Estado', text: 'Vigente indica una transacción activa; Anulado, una transacción anulada que se muestra atenuada y no admite cambios.', cuando: () => con && md },
                { id: 'paginacion', element: '#con_tabla .tx-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.', cuando: () => con },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
