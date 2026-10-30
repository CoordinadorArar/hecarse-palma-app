document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const visible = (el) => Boolean(el) && el.getClientRects().length > 0 && !el.parentElement?.closest('.d-none');

    window.Tutoriales?.registrar('produccion', {
        titulo: 'Producción',
        antesDeIniciar: () => {
            const periodo = document.getElementById('periodo');
            const ocupado = (periodo?.disabled && periodo.options[0]?.text.trim() === 'Cargando…' && visible(periodo))
                || ($q('#panel_registro.active') && document.getElementById('cap_lote')?.options[0]?.text.trim() === 'Cargando lotes…')
                || ['#btn_buscar_tiquete .spinner-border', '#btn_guardar .spinner-border', '#btn_consultar .spinner-border', '#panel_consulta.active #con_aviso .spinner-border']
                    .some((selector) => [...document.querySelectorAll(selector)].some(visible))
                || Boolean($q('#velo_cargando, #velo_guardando'));

            return ocupado ? 'Espere a que termine de cargar para iniciar el tutorial.' : null;
        },
        pasos: () => {
            const reg = Boolean($q('#panel_registro.active'));
            const con = Boolean($q('#panel_consulta.active'));
            const md = window.matchMedia('(min-width: 768px)').matches;
            const lg = window.matchMedia('(min-width: 992px)').matches;
            const sinTiquete = Boolean($q('#detalle_cuerpo[inert]'));
            const externo = Boolean($q('#tiquete_externo')?.checked);
            const edicion = Boolean($q('#chip_estado.ambar'));
            const sellado = Boolean($q('#velo_registrado'));
            const medidor = $q('#medidor')?.className || '';
            const linea = $q('#lista_lineas .tx-tarjeta');
            const agregarOff = Boolean($q('#btn_agregar_linea')?.disabled);
            const permisos = typeof PERMISOS !== 'undefined' ? PERMISOS : {};
            const vacio = $q('#con_aviso .gp-estado-vacio');
            const candado = $q('#con_filas .bi-lock, #con_filas .bi-lock-fill, #con_filas .bi-unlock-fill');
            const liquidada = Boolean(candado?.classList.contains('bi-lock-fill'));
            const abierto = Boolean(candado?.classList.contains('bi-unlock-fill'));
            const sinLabores = Boolean(linea?.querySelector('.btn-group[role="group"] button')?.disabled);

            const textoTiquete = externo
                ? 'Está capturando un tiquete externo: escriba el número, la extractora, el conductor y el vehículo a mano. Verifique los datos contra el tiquete físico.'
                : (sinTiquete
                    ? 'Escriba el número del tiquete y pulse Buscar (o Enter) para traer los datos de báscula. Si no está en báscula, active Tiquete externo para capturarlo a mano.'
                    : 'El tiquete se cargó desde báscula; si cambia el número, pulse Buscar de nuevo. Active Tiquete externo solo si el tiquete no está en báscula.');
            const textoPesos = sinTiquete
                ? 'Aquí se muestran los racimos, sacos y pesos del tiquete. Se habilitan al buscar el tiquete o al activar Tiquete externo.'
                : (externo
                    ? 'Digite racimos, sacos, peso bruto y tara del tiquete; el peso neto se calcula solo.'
                    : 'El peso bruto viene de báscula. Digite racimos, sacos y tara del tiquete físico; el peso neto se calcula solo.');
            const textoMedidor = medidor.includes('tx-reparto--inactivo')
                ? 'Aquí controla el reparto: los racimos del tiquete frente a los repartidos en las líneas. Se activa al cargar el tiquete; usted reparte racimos y el sistema calcula los kg de cada línea.'
                : (medidor.includes('tx-reparto--sinracimos')
                    ? 'Primero digite los racimos del tiquete y luego repártalos en las líneas hasta lograr «Reparto cuadrado». El sistema reparte el peso neto según los racimos y el peso promedio de cada lote.'
                    : 'Compare los racimos del tiquete con los repartidos hasta lograr «Reparto cuadrado». Usted reparte racimos; el sistema reparte el peso neto según los racimos y el peso promedio de cada lote.');
            const textoCaptura = sinTiquete
                ? 'Aquí agrega las líneas del reparto: finca, lote, fecha, racimos y sacos. Se habilita al cargar el tiquete.'
                : (agregarOff
                    ? 'Elija finca y lote e indique racimos y sacos. Agregar se habilita cuando el tiquete tiene racimos y la finca tiene lotes.'
                    : 'Elija finca y lote, indique racimos y sacos (y la fecha, si es distinta a la del tiquete) y pulse Agregar. Debajo verá el peso promedio del lote.');
            const textoGuardar = sellado
                ? 'La transacción quedó registrada con el número que ve arriba. Pulse Nueva transacción para comenzar otra.'
                : (edicion
                    ? 'Pulse Guardar cambios (o Ctrl+S) para actualizar la transacción; Cancelar edición descarta los cambios.'
                    : 'Pulse Guardar (o Ctrl+S) para registrar la transacción; Cancelar descarta los datos. Si faltan racimos o hay líneas sin trabajadores, se le pedirá confirmar.');
            const textoVacio = $q('#btn_reintentar')
                ? 'No se pudo completar la consulta. Pulse Reintentar o ajuste los filtros.'
                : (vacio?.querySelector('.bi-lock') ? 'Su usuario no tiene permiso para consultar estas transacciones.' : 'Ninguna transacción coincide con los filtros. Amplíe el rango de fechas o pulse Limpiar.');
            const textoAcciones = permisos.editar
                ? (permisos.eliminar
                    ? 'Use el ojo (o doble clic en la fila) para ver el detalle completo. El lápiz abre la transacción para editarla y la papelera la elimina; no aparecen si está anulada, en un periodo cerrado o liquidada en nómina. Si su superior la habilitó, el lápiz aparece para una sola edición.'
                    : 'Use el ojo (o doble clic en la fila) para ver el detalle y el lápiz para editarla. El lápiz no aparece si la transacción está anulada, en un periodo cerrado o liquidada en nómina sin habilitar.')
                : (permisos.eliminar
                    ? 'Use el ojo (o doble clic en la fila) para ver el detalle y la papelera para eliminarla. La papelera no aparece si está anulada, en un periodo cerrado o liquidada en nómina.'
                    : 'Use el ojo para ver el detalle completo de la transacción. También puede abrirlo con doble clic en la fila.');

            return [
                { id: 'bienvenida', title: 'Producción', text: con ? 'Aquí consulta las transacciones de producción guardadas y, según sus permisos, las ve, edita o elimina. Use Siguiente para recorrer las opciones principales.' : 'Aquí registra la producción de cada tiquete: reparte sus racimos por lote y asigna los trabajadores que participaron. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => document.getElementById('filtro_empresa')?.closest('.input-group'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa con la que va a registrar o consultar.' },
                { id: 'pestanas', element: '#pestanas', side: lg ? 'left' : 'bottom', title: 'Registro y consulta', text: reg ? (edicion ? 'Está en Edición. Use Consulta para volver a la lista de transacciones.' : 'Use Consulta para buscar transacciones guardadas y verlas, editarlas o eliminarlas.') : 'Use Registro para crear una transacción nueva.' },
                { id: 'planeacion', element: '#nota_planeacion', side: 'bottom', title: 'Precargado desde planeación', text: 'Esta transacción viene de una actividad de la planeación. Cargue el tiquete, revise los datos y guarde.', cuando: () => reg },
                { id: 'edicion', element: '#chip_estado', side: 'bottom', title: 'Editando', text: 'Está editando la transacción indicada. Los cambios solo se aplican al pulsar Guardar cambios.', cuando: () => reg && edicion },
                { id: 'tiquete', element: () => document.getElementById('tiquete')?.closest('.row'), side: 'bottom', title: 'Tiquete', text: textoTiquete, cuando: () => reg && !sellado },
                { id: 'pesos', element: '#bloque_pesos', side: 'bottom', title: 'Racimos y pesos', text: textoPesos, cuando: () => reg && !sellado },
                { id: 'datos', element: '#card_observacion', side: md ? 'top' : 'bottom', title: 'Datos de la transacción', text: 'Elija el año y un periodo abierto; se le avisará si la fecha del tiquete queda fuera de él. La observación es opcional.', cuando: () => reg && !sellado },
                { id: 'medidor', element: '#medidor', side: 'bottom', title: 'Reparto de racimos', text: textoMedidor, cuando: () => reg },
                { id: 'captura', element: () => document.getElementById('cap_finca')?.closest('.gp-panel-opcion'), side: 'bottom', title: 'Agregar líneas', text: textoCaptura, cuando: () => reg && !sellado },
                { id: 'lineas', element: () => linea?.querySelector('.tx-franja') || document.getElementById('estado_sin_lineas'), side: 'bottom', title: 'Líneas del reparto', text: linea ? 'Puede corregir aquí los racimos y sacos de cada línea; el sistema recalcula los kg asignados a todas. Si faltan racimos, Asignar faltantes los suma a la línea.' : 'Aquí aparecerán las líneas que agregue, una por lote o por cuadrilla.', cuando: () => reg && !sinTiquete },
                { id: 'trabajadores', element: () => linea?.querySelector('.btn-group[role="group"]'), side: md ? 'left' : 'bottom', title: 'Trabajadores', text: sinLabores ? 'No hay labores definidas en el maestro de labores, así que no puede agregar trabajadores.' : 'Use + Cosecha, + Cargue o + Transporte para agregar los trabajadores de la línea con su actividad y precio. Los kg de la línea se reparten en partes iguales entre los trabajadores de la misma actividad.', cuando: () => reg && !sellado },
                { id: 'totales', element: '#totales_detalle', side: 'top', title: 'Totales', text: 'Revise los racimos repartidos frente a los del tiquete, los sacos, los valores de cosecha, cargue y transporte, y los jornales. Los kg son estimados; el valor definitivo se calcula al guardar.', cuando: () => reg },
                { id: 'guardar', element: () => document.getElementById('btn_guardar')?.parentElement, side: 'top', title: 'Guardar', text: textoGuardar, cuando: () => reg },
                { id: 'filtros', element: '#formulario_consulta', side: 'bottom', title: 'Filtros', text: 'Por defecto se consulta el último mes. Ajuste el rango de fechas y, si lo necesita, filtre por número de tiquete, número de transacción o estado.', cuando: () => con },
                { id: 'botones_consulta', element: () => document.getElementById('btn_consultar')?.parentElement, side: 'bottom', title: 'Consultar', text: 'Pulse Consultar (o Enter) para aplicar los filtros. Limpiar vuelve al último mes y quita los demás filtros.', cuando: () => con },
                { id: 'contador', element: () => document.getElementById('contador_consulta')?.parentElement, side: 'bottom', title: 'Resultados', text: 'Indica cuántas transacciones coinciden con los filtros y el rango de fechas consultado.', cuando: () => con },
                { id: 'truncado', element: '#con_truncado', side: 'bottom', title: 'Límite de resultados', text: 'Solo se muestran los primeros 500 resultados. Acote el rango de fechas o use otros filtros para ver el resto.', cuando: () => con },
                { id: 'sin_resultados', element: '#con_aviso .gp-estado-vacio', side: 'top', title: 'Sin resultados', text: textoVacio, cuando: () => con },
                { id: 'buscar_resultados', element: '#con_tabla .tx-dt-barra:not(.tx-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Busque dentro de los resultados por número, tiquete, vehículo u observación sin volver a consultar. Elija también cuántos registros ver por página.', cuando: () => con },
                { id: 'estado', element: '#con_filas tr .badge', side: 'left', title: 'Estado', text: 'Vigente indica una transacción activa; Anulado, una transacción anulada que solo se puede consultar.', cuando: () => con && md },
                { id: 'acciones_consulta', element: '#con_filas tr .btn-group', side: md ? 'left' : 'bottom', title: 'Acciones', text: textoAcciones, cuando: () => con },
                { id: 'habilitar', element: '#con_filas [data-habilitar]', side: md ? 'left' : 'bottom', title: 'Habilitar edición', text: 'Habilite una transacción liquidada para que se edite una sola vez. Debe indicar el motivo; la habilitación vence en 24 horas y la transacción se bloquea de nuevo al guardar.', cuando: () => con && Boolean(permisos.habilitar) },
                { id: 'candado', element: () => candado, side: md ? 'left' : 'bottom', title: abierto ? 'Edición habilitada' : 'Transacción bloqueada', text: abierto ? 'El candado abierto indica que su superior habilitó la transacción para una sola edición. Pase el cursor sobre él para ver hasta cuándo está vigente; al guardar, queda bloqueada de nuevo.' : liquidada ? 'El candado cerrado indica que la transacción está liquidada en nómina: no se puede editar ni eliminar. Si necesita corregirla, solicite a su superior que la habilite para edición.' : 'El candado indica que el periodo está cerrado: la transacción solo se puede ver. Pase el cursor sobre él para ver el motivo.', cuando: () => con },
                { id: 'paginacion', element: '#con_tabla .tx-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.', cuando: () => con },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
