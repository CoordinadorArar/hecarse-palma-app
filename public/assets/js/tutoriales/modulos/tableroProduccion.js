document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const ancho = (px) => window.matchMedia(`(min-width: ${px}px)`).matches;
    const tarjeta = (icono) => $q(`#resultado_cuerpo .card-header .${icono}`)?.closest('.card');
    const vacia = (el, texto) => (el?.querySelector('.tb-grafica-vacia') ? 'No hay datos para esta gráfica con los filtros elegidos.' : texto);

    window.Tutoriales?.registrar('tablero-produccion', {
        titulo: 'Análisis de Producción',
        antesDeIniciar: () => ($q('#btn_consultar .spinner-border, #resultado_cuerpo[aria-busy="true"], #resultado_cuerpo .placeholder-glow, #resultado_cuerpo:empty')
            ? 'Espere a que termine de cargar el tablero para iniciar el tutorial.' : null),
        pasos: () => {
            const historico = Boolean($id('vista_historico')?.checked);
            const [sm, xl] = [576, 1200].map(ancho);
            const chips = [...($id('chips_contexto')?.children || [])];
            const tiene = (t) => chips.some((c) => c.textContent.includes(t));
            const vacio = $q('#resultado_cuerpo > .card > .gp-estado-vacio');
            const todas = Boolean($q('#chips_contexto [data-accion="todas"]'));

            const [tituloVacio, textoVacio] = vacio?.querySelector('.bi-exclamation-octagon')
                ? ['No se pudo cargar', 'Ocurrió un problema al consultar. Pulse Reintentar o revise los filtros.']
                : (vacio?.querySelector('.bi-shield-lock')
                    ? ['Acción no permitida', 'Su usuario no tiene permiso para consultar este tablero. Comuníquese con el administrador.']
                    : (vacio?.querySelector('.bi-database-exclamation')
                        ? ['Histórico no disponible', 'El histórico de producción aún no está cargado en el sistema. Comuníquese con el administrador.']
                        : ['Sin resultados', historico ? 'No hay producción histórica para la finca o el año de siembra elegidos. Ajuste los filtros.' : 'No hay producción, meta ni año anterior para los filtros elegidos. Ajuste el año, el mes de corte, la finca o el año de siembra.']));

            return [
                { id: 'bienvenida', title: 'Análisis de Producción', text: 'Aquí compara la producción real con la meta y con el año anterior, por finca y lote, o revisa el histórico de todos los años. Use Siguiente para recorrer las opciones principales.' },
                { id: 'vista', element: '.tb-vista', side: 'bottom', title: 'Vista', text: 'Elija Año actual para ver el acumulado al corte frente a la meta, o Histórico para comparar la producción de todos los años registrados. Al cambiar de vista se consulta de inmediato.' + (historico ? ' En el histórico no aplican el año ni el mes de corte.' : '') },
                { id: 'filtros', element: '#form_filtros', side: 'bottom', title: 'Filtros', text: historico ? 'Elija la empresa, la finca y el año de siembra. Sin finca verá el total de todas las fincas de la empresa.' : 'Elija la empresa, el año y el mes de corte; la producción y la meta se acumulan de enero hasta ese mes. La finca y el año de siembra son opcionales: sin finca verá todas las fincas comparadas entre sí.' },
                { id: 'siembra', element: () => $id('filtro_siembra')?.closest('.col-12'), side: 'bottom', title: 'Año de siembra', text: 'Filtre los lotes por su año de siembra. La lista se completa con los años disponibles después de cada consulta.' },
                { id: 'consultar', element: '#btn_consultar', side: 'bottom', title: 'Consultar', text: 'Pulse Consultar para actualizar el tablero con los filtros elegidos. Si cambia un filtro y no consulta, se le avisará que lo que ve está desactualizado.' },
                { id: 'contexto', element: '#chips_contexto', side: 'bottom', title: 'Consulta actual', text: 'Resume el corte, la empresa, la finca y el año de siembra con que se generó lo que está viendo.' + (todas ? ' Pulse la × de la finca para volver a ver todas.' : '') + (tiene('Último mes facturado') ? ' «Último mes facturado» indica hasta qué mes hay báscula; si aparece en ámbar, los meses posteriores aún no tienen datos.' : '') + (tiene('Datos:') ? ' «Datos» indica que parte de las cifras proviene del histórico cargado desde Excel.' : ''), cuando: () => chips.length > 0 },
                { id: 'desactualizado', element: '#chip_desactualizado', side: 'bottom', title: 'Filtros modificados', text: 'Cambió los filtros después de consultar: lo que ve aún corresponde a la consulta anterior. Pulse Consultar para actualizarlo.' },
                { id: 'avisos', element: '#resultado_cuerpo > .alert', side: 'bottom', title: 'Avisos', text: 'Revise estos avisos antes de interpretar las cifras: señalan datos faltantes, combinados o parciales.' },
                { id: 'sin_resultados', element: () => vacio, side: 'top', title: tituloVacio, text: textoVacio },
                { id: 'kpis', element: () => { const k = $q('#resultado_cuerpo .inf-kpi'); return sm ? k?.closest('.row') : k; }, side: 'bottom', title: 'Indicadores', text: 'Producción real acumulada, meta al corte, diferencia frente a la meta y frente al año anterior, y rendimiento en toneladas por hectárea. El color indica el cumplimiento: verde desde 100 %, ámbar desde 90 % y rojo por debajo. Las minigráficas muestran la producción de cada mes.', cuando: () => !historico },
                { id: 'mensual', element: () => tarjeta('bi-bar-chart-line'), side: xl ? 'right' : 'bottom', title: 'Producción mensual vs presupuesto', text: vacia(tarjeta('bi-bar-chart-line'), 'Compara cada mes las toneladas ejecutadas con el presupuesto; la línea punteada marca el mes de corte.' + (todas ? ' Con una finca elegida verá además su rendimiento en t/ha frente a la meta, en el eje derecho.' : '')), cuando: () => !historico },
                { id: 'gauge', element: () => tarjeta('bi-speedometer2'), side: xl ? 'left' : 'top', title: 'Cumplimiento al corte', text: $id('grafica_gauge') ? 'La aguja indica el porcentaje de la meta alcanzado al corte: rojo por debajo de 90 %, ámbar entre 90 y 100 % y verde desde 100 %.' : 'No hay meta cargada para estos filtros, por eso no se puede calcular el cumplimiento.', cuando: () => !historico },
                { id: 'por_anterior', element: () => tarjeta('bi-bar-chart'), side: 'top', title: 'Real vs año anterior por finca', text: vacia(tarjeta('bi-bar-chart'), 'Compara la producción de cada finca con la del año anterior en el mismo periodo. Pulse una barra para ver el detalle de esa finca.'), cuando: () => !historico },
                { id: 'por_meta', element: () => tarjeta('bi-bar-chart-steps'), side: 'top', title: 'Real vs meta por finca', text: vacia(tarjeta('bi-bar-chart-steps'), 'Compara lo producido por cada finca con su meta al corte. Pulse una barra para ver esa finca.'), cuando: () => !historico },
                { id: 'participacion', element: () => tarjeta('bi-pie-chart'), side: 'top', title: 'Participación por finca', text: vacia(tarjeta('bi-pie-chart'), 'Muestra qué porcentaje de la producción aporta cada finca. Pulse un sector para ver esa finca.'), cuando: () => !historico },
                { id: 'ppr', element: () => tarjeta('bi-boxes'), side: 'top', title: 'Peso promedio de racimo', text: vacia(tarjeta('bi-boxes'), 'Kilos promedio por racimo en cada mes para la finca elegida.'), cuando: () => !historico },
                { id: 'lotes', element: () => tarjeta('bi-table')?.querySelector('.card-header'), side: 'bottom', title: 'Producción por lote', text: vacia(tarjeta('bi-table'), 'Producción real y meta de cada lote al corte, con su año de siembra. El cumplimiento usa el mismo semáforo y la variación negativa aparece en rojo. La fila Total suma todos los lotes.'), cuando: () => !historico },
                { id: 'historico', element: () => tarjeta('bi-graph-up'), side: xl ? 'right' : 'bottom', title: 'Producción mensual por año', text: 'Una línea por año con la producción de cada mes; el año más reciente se resalta en verde. Pulse un año en la leyenda para ocultarlo o mostrarlo y comparar los que le interesen.', cuando: () => historico },
                { id: 'tonha_anual', element: () => tarjeta('bi-bar-chart'), side: xl ? 'left' : 'top', title: 'Toneladas por hectárea anual', text: vacia(tarjeta('bi-bar-chart'), 'Rendimiento de cada año frente a la línea de meta. El asterisco marca el año en curso, que aún es parcial.'), cuando: () => historico },
                { id: 'tabla_historico', element: () => $q('#resultado_cuerpo .tb-tabla-historico')?.closest('.card')?.querySelector('.card-header'), side: 'bottom', title: 'Producción histórica mensual', text: 'Kilos de cada mes por año: entre más intenso el color de la celda, mayor la producción. La etiqueta bajo cada año indica la fuente: Báscula del sistema, Histórico Excel o Mixto.', cuando: () => historico },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
