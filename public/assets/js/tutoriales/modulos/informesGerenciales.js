document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const ancho = (px) => window.matchMedia(`(min-width: ${px}px)`).matches;

    window.Tutoriales?.registrar('informes-gerenciales', {
        titulo: 'Informes Gerenciales',
        antesDeIniciar: () => ($q('#btn_consultar .spinner-border, #resultado_cuerpo[aria-busy="true"], #resultado_cuerpo .placeholder-glow, #mapa_ciclo:empty')
            ? 'Espere a que termine de cargar el informe para iniciar el tutorial.' : null),
        pasos: () => {
            const tab = $q('.ig-informe-tab[aria-selected="true"]');
            const informe = tab?.dataset.informe || '';
            const ciclo = informe.startsWith('ciclos-');
            const ingreso = ciclo && informe !== 'ciclos-cosecha';
            const labores = ['ciclos-sanidad', 'ciclos-mantenimiento'].includes(informe);
            const tema = { 'ciclos-poda': 'poda', 'ciclos-fertilizacion': 'fertilización', 'ciclos-sanidad': 'sanidad', 'ciclos-mantenimiento': 'mantenimiento' }[informe] || 'cosecha';
            const [sm, md, xl, xxl] = [576, 768, 1200, 1400].map(ancho);
            const fino = window.matchMedia('(pointer: fine)').matches;
            const geo = Boolean($q('#ig_geo_palmas'));
            const conMapa = Boolean($q('#mapa_ciclo'));
            const lado = Boolean($q('#mapa_ciclo')?.closest('.col-xl-7, .col-xxl-7')) && (geo ? xxl : xl);
            const planear = Boolean($q('#resultado_cuerpo [data-accion="planear"], #resultado_cuerpo [data-accion="planear-cosecha"]'));
            const planearLote = Boolean($id('btn_planear_lote'));
            const vacio = $q('#resultado_cuerpo > .card > .gp-estado-vacio');
            const barra = $q('.ig-informes');
            const fincas = Boolean($q('#grafica_fincas .apexcharts-canvas'));
            const abierto = Boolean($q('#colapso_diferencia.show'));

            const [tituloVacio, textoVacio] = vacio?.querySelector('.bi-exclamation-octagon')
                ? ['No se pudo generar', 'Ocurrió un problema al consultar. Pulse Reintentar o revise los filtros.']
                : (vacio?.querySelector('.bi-geo-alt')
                    ? ['Seleccione una finca', 'Los informes de ciclo se calculan por finca. Selecciónela en los filtros y pulse Consultar.']
                    : (vacio?.querySelector('.bi-inbox')
                        ? ['Sin resultados', ciclo ? 'La finca no tiene lotes con registros de este ciclo a la fecha de corte. Verifique la finca o la fecha.' : 'No hay producción ni presupuesto para el corte elegido. Ajuste el año, el mes de corte o la finca.']
                        : ['Consulte el informe', 'Elija los filtros y pulse Consultar para generar el informe.']));
            const textoMapa = geo
                ? (fino
                    ? 'Cada lote se colorea según su estado; pulse uno para seleccionarlo en la tabla. Acérquese o seleccione un lote para ver sus palmas y pulse una para ver su detalle. Para acercar con la rueda del ratón, haga clic antes dentro del mapa.'
                    : 'Cada lote se colorea según su estado; tóquelo para ver su detalle y seleccionarlo en la tabla. Acérquese con dos dedos o seleccione un lote para ver sus palmas, y toque una para ver su detalle.')
                : (fino
                    ? 'Cada lote se colorea según su estado. Pase el cursor para ver su detalle y pulse un lote para seleccionarlo en la tabla.'
                    : 'Cada lote se colorea según su estado. Toque un lote para ver su detalle y seleccionarlo en la tabla.');

            return [
                { id: 'bienvenida', title: 'Informes Gerenciales', text: ciclo ? `Aquí ve en qué punto del ciclo de ${tema} está cada lote de la finca, sobre el mapa y en una tabla${planear ? ', y puede planear las labores pendientes' : ''}. Use Siguiente para recorrer las opciones principales.` : 'Aquí compara el presupuesto de producción con lo ejecutado en báscula y en labores de cosecha, por compañía, finca y lote. Use Siguiente para recorrer las opciones principales.' },
                { id: 'informes', element: '.ig-informes', side: 'bottom', title: 'Informes', text: 'Elija el informe que desea ver: Presupuesto vs Ejecutado o los ciclos de cosecha, poda, fertilización, sanidad y mantenimiento. Al cambiar de informe se consulta de nuevo de inmediato.' + (barra && barra.scrollWidth > barra.clientWidth ? ' Deslice la barra hacia los lados para ver todos.' : '') },
                { id: 'filtros', element: '#form_filtros', side: 'bottom', title: 'Filtros', text: ciclo ? 'Elija la empresa, la fecha de corte y la finca. En los informes de ciclo la finca es obligatoria y los días de cada lote se calculan a la fecha de corte.' : 'Elija la empresa, el año y el mes de corte; el presupuesto y lo ejecutado se acumulan de enero hasta ese mes. La finca es opcional: sin ella verá todas las fincas de la empresa.' },
                { id: 'unidad', element: () => $id('unidad_kg')?.closest('.btn-group'), side: 'bottom', title: 'Unidad', text: 'Muestre las cifras en kilogramos o en toneladas. El cambio se aplica al instante, sin volver a consultar.', cuando: () => !ciclo },
                { id: 'consultar', element: '#btn_consultar', side: 'bottom', title: 'Consultar', text: 'Pulse Consultar para generar el informe con los filtros elegidos. Si cambia un filtro y no consulta, se le avisará que lo que ve está desactualizado.' },
                { id: 'contexto', element: '#chips_contexto', side: 'bottom', title: 'Informe consultado', text: `Resume el corte, la empresa, la finca y ${ciclo ? 'el ciclo' : 'la unidad'} con que se generó el informe que está viendo.`, cuando: () => $q('#chips_contexto')?.children.length > 0 },
                { id: 'desactualizado', element: '#chip_desactualizado', side: 'bottom', title: 'Filtros modificados', text: 'Cambió los filtros después de consultar: lo que ve aún corresponde a la consulta anterior. Pulse Consultar para actualizarlo.' },
                { id: 'avisos', element: '#resultado_cuerpo > .gp-nota-alerta', side: 'bottom', title: 'Avisos', text: 'Revise estos avisos antes de interpretar las cifras: señalan datos faltantes o inconsistentes en el informe.' },
                { id: 'sin_resultados', element: () => vacio, side: 'top', title: tituloVacio, text: textoVacio },
                { id: 'kpis', element: () => { const k = $q('#resultado_cuerpo .inf-kpi'); return sm ? k?.closest('.row') : k; }, side: 'bottom', title: 'Indicadores', text: 'Presupuesto y ejecutado acumulados al corte (báscula y labores), el cumplimiento, lo que falta para la meta anual y la diferencia entre facturado y labores. El color indica el cumplimiento: verde desde 100 %, ámbar desde 90 % y rojo por debajo.', cuando: () => !ciclo },
                { id: 'mensual', element: () => $id('grafica_mensual')?.closest('.card'), side: xl ? 'right' : 'bottom', title: 'Evolución mensual', text: 'Compara cada mes el presupuesto con lo facturado y lo registrado en labores; la línea azul es el % de cumplimiento. Muestra el total general o la compañía o finca que elija en la tabla; use Ver total para volver.', cuando: () => !ciclo },
                { id: 'fincas', element: () => $id('grafica_fincas')?.closest('.card'), side: xl ? 'left' : 'top', title: 'Cumplimiento por finca', text: fincas ? 'Ordena las fincas de menor a mayor cumplimiento acumulado frente a la meta del 100 %. Pulse una barra para ver esa finca en la gráfica mensual y ubicarla en la tabla.' : 'No hay fincas con presupuesto al corte, por eso esta gráfica aparece vacía.', cuando: () => !ciclo },
                { id: 'diferencia', element: () => $id('colapso_diferencia')?.closest('.card'), side: 'top', title: 'Diferencia facturado vs labores', text: abierto ? 'Muestra mes a mes la diferencia entre lo facturado en báscula y lo registrado en labores; las barras rojas indican meses con menos facturado que labores. Pulse el título para plegarla.' : 'Pulse este título para desplegar, mes a mes, la diferencia entre lo facturado en báscula y lo registrado en labores. Las barras rojas indican meses con menos facturado que labores.', cuando: () => !ciclo },
                { id: 'detalle', element: () => $id('tabla_arbol')?.closest('.card')?.querySelector('.card-header'), side: 'bottom', title: 'Detalle por compañía, finca y lote', text: 'Use Expandir todo o Contraer todo para abrir o cerrar todos los niveles de la tabla.', cuando: () => !ciclo },
                { id: 'niveles', element: '#tabla_arbol tbody tr', side: 'bottom', title: 'Navegar por niveles', text: 'Use la flecha para abrir cada compañía y finca hasta sus lotes. Pulse el nombre de una compañía o finca para ver su evolución en la gráfica mensual. El guion indica que no hay presupuesto y el triángulo ámbar, que facturado y labores no cuadran.', cuando: () => !ciclo },
                { id: 'planear_cosecha', element: '#resultado_cuerpo [data-accion="planear-cosecha"]', side: md ? 'left' : 'bottom', title: 'Planear cosecha', text: 'Abre el formulario para planear una labor de cosecha con sus trabajadores sin salir del informe. Si eligió una finca en la tabla o en los filtros, llega precargada.', cuando: () => !ciclo },
                { id: 'kpis_ciclo', element: () => $q('#resultado_cuerpo .ig-ciclo-kpi')?.closest('.row'), side: 'bottom', title: 'Estados del ciclo', text: `Cuántos lotes y hectáreas hay en cada estado según ${ingreso ? 'los días que faltan para el próximo ingreso' : 'los días desde el último pase de cosecha'}. Pulse un estado para filtrar el mapa y la tabla; púlselo de nuevo o use Lotes evaluados para ver todos.`, cuando: () => ciclo },
                { id: 'sin_mapa', element: '#resultado_cuerpo > .gp-nota', side: 'bottom', title: 'Sin mapa', text: 'Esta finca aún no tiene mapa configurado, por eso el informe se muestra solo con la tabla de lotes.', cuando: () => ciclo },
                { id: 'mapa_error', element: () => $q('#cuerpo_mapa .gp-estado-vacio')?.closest('.card'), side: 'bottom', title: 'Mapa no disponible', text: 'No se pudo cargar el mapa en este momento; la tabla de lotes sigue disponible. Pulse Consultar para intentarlo de nuevo.', cuando: () => ciclo },
                { id: 'controles_mapa', element: () => $id('mapa_ciclo')?.closest('.card')?.querySelector('.card-header'), side: 'bottom', title: 'Opciones del mapa', text: (geo ? 'Active o desactive las palmas y elija si se colorean por el estado del informe o por su estado sanitario. Restablecer vista vuelve al encuadre inicial y quita la selección.' : 'Restablecer vista vuelve al encuadre inicial del mapa y quita la selección del lote.') + ($id('btn_planear_lote')?.hidden ? ' Al seleccionar un lote aparece aquí el botón para planear en ese lote.' : ''), cuando: () => ciclo && conMapa && (geo || md) },
                { id: 'planear_lote', element: '#btn_planear_lote', side: md ? 'left' : 'bottom', title: 'Planear en el lote', text: `Planea una actividad de ${tema} directamente en el lote seleccionado.`, cuando: () => ciclo },
                { id: 'mapa', element: () => (conMapa ? $id('cuerpo_mapa') : null), side: lado ? 'right' : 'bottom', title: 'Mapa de lotes', text: textoMapa, cuando: () => ciclo },
                { id: 'leyenda', element: '#pie_mapa', side: 'top', title: 'Leyenda', text: 'Indica qué significa cada color y su rango de días. «Sin lote en sistema» marca áreas del mapa sin lote registrado.' + (geo ? ' Cuando las palmas están visibles, debajo verá cuántas hay en cada estado.' : ''), cuando: () => ciclo },
                { id: 'lotes', element: () => $id('tabla_ciclo')?.closest('.card')?.querySelector('.card-header'), side: 'bottom', title: 'Lotes', text: (ingreso ? 'Los lotes se ordenan del más urgente al menos urgente según los días para el próximo ingreso; los valores en rojo ya están vencidos.' : 'Los lotes se ordenan del que lleva más días sin cosecha al más reciente, con su estado, fechas y próximo ingreso.') + (labores ? ' Bajo el estado verá la labor más urgente del lote.' : ''), cuando: () => ciclo },
                { id: 'filtro_estado', element: '#chip_estado', side: 'bottom', title: 'Filtro activo', text: 'Solo ve los lotes en este estado. Pulse Ver todos para quitar el filtro.', cuando: () => ciclo },
                { id: 'planear', element: '#resultado_cuerpo [data-accion="planear"]', side: md ? 'left' : 'bottom', title: 'Planear actividad', text: `Abre el formulario para planear una labor de ${tema} en esta finca, con fecha, lote y trabajadores, sin salir del informe.`, cuando: () => ciclo },
                { id: 'fila', element: '#tabla_ciclo tbody tr', side: 'bottom', title: 'Seleccionar un lote', text: (fino ? 'Pulse una fila para ubicar el lote en el mapa; al pasar el cursor, el lote se resalta.' : 'Toque una fila para ubicar el lote en el mapa.') + (planear && planearLote ? ' Luego use Planear en el lote, sobre el mapa, para programar una labor en él.' : ''), cuando: () => ciclo && conMapa },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
