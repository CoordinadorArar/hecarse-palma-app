document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const ancho = (px) => window.matchMedia(`(min-width: ${px}px)`).matches;
    const ajustar = (el) => (el && el.offsetHeight > window.innerHeight * 0.7 ? (el.querySelector('.card-header') || el.firstElementChild) : el);
    const sec = (c) => $id(`sec_${c}`)?.closest('section');
    const cab = (c) => sec(c)?.firstElementChild;
    const plegada = (c) => $id(`sec_${c}`) && !$id(`sec_${c}`).classList.contains('show');
    const nota = (c) => (plegada(c) ? ' Ahora está plegada: pulse la flecha de la derecha para desplegarla.' : '');

    window.Tutoriales?.registrar('informe-bascula', {
        titulo: 'Informe báscula',
        antesDeIniciar: () => ($q('#btn_excel .spinner-border')
            ? 'Espere a que termine la exportación para iniciar el tutorial.'
            : ($q('#btn_consultar .spinner-border, #resultado_cuerpo[aria-busy="true"], #resultado_cuerpo .placeholder-glow, #resultado_cuerpo:empty')
                ? 'Espere a que termine de cargar el informe para iniciar el tutorial.' : null)),
        pasos: () => {
            const xl = ancho(1200);
            const fino = window.matchMedia('(pointer: fine)').matches;
            const vacio = $q('#resultado_cuerpo > .card > .gp-estado-vacio');
            const tabs = $q('#resultado_cuerpo .nav-tabs');
            const activa = tabs?.querySelector('.nav-link.active')?.id;
            const fact = $id('panel_facturado') || (tabs ? null : $q('#resultado_cuerpo > .card .bi-receipt-cutoff')?.closest('.card'));
            const mensual = Boolean(sec('dias')?.querySelector('h6')?.textContent.includes('por mes'));
            const chips = [...($id('chips_contexto')?.children || [])];
            const factVacio = fact?.querySelector('.gp-estado-vacio');
            const viajes = () => activa === 'tab_panel_viajes';
            const facturado = () => activa === 'tab_panel_facturado' || !tabs;

            const [tituloVacio, textoVacio] = vacio?.querySelector('.bi-exclamation-triangle')
                ? ['No se pudo cargar', 'Ocurrió un problema al consultar. Pulse Reintentar o revise los filtros.']
                : ['Sin pesajes', 'No hay viajes en báscula para el periodo y las fincas elegidos.' + (vacio?.querySelector('[data-accion="ultimo"]') ? ' Pulse «Ver mes del último pesaje» para ir al último mes con datos.' : '') + (vacio?.querySelector('[data-accion="todas-fincas"]') ? ' Use «Ver todas las fincas» para quitar el filtro.' : '')];
            const [tituloFact, textoFact] = factVacio?.querySelector('.bi-cloud-slash')
                ? ['Facturación no disponible', 'No se pudo consultar la facturación en Siesa.' + (factVacio.querySelector('[data-accion="reintentar"]') ? ' Pulse Reintentar para intentarlo de nuevo.' : ' El resto del informe sigue disponible.')]
                : ['Sin facturas', 'No hay facturas de venta de las extractoras en el periodo consultado.'];

            return [
                { id: 'bienvenida', title: 'Informe báscula', text: 'Aquí compara los kilos pesados en la báscula de las fincas con los recibidos por las extractoras, revisa placas y conductores, y cruza lo facturado en Siesa. Use Siguiente para recorrer las opciones principales.' },
                { id: 'filtros', element: '#form_filtros', side: 'bottom', title: 'Filtros', text: 'Elija la empresa, las fincas y el rango de fechas, de máximo 366 días. El informe se actualiza solo al cambiar cualquier filtro.' },
                { id: 'fincas', element: '#col_fincas', side: 'bottom', title: 'Fincas', text: 'Elija una o varias fincas, o deje el campo vacío para ver todas. Si un viaje trae fruta de varias fincas, sus kilos se reparten en proporción a las fincas elegidas.' },
                { id: 'periodo', element: () => $q('[data-rango]')?.closest('.btn-group'), side: 'bottom', title: 'Periodo rápido', text: 'Consulte con un clic este mes, el mes anterior o los últimos 30 días. El botón resaltado indica el periodo activo.' },
                { id: 'rango_error', element: '#fila_error_hasta', side: 'bottom', title: 'Fechas no válidas', text: 'Corrija las fechas: la fecha fin debe ser igual o posterior a la de inicio y el rango no puede superar 366 días. Mientras tanto no se puede actualizar ni exportar.' },
                { id: 'excel', element: '#btn_excel', side: 'bottom', title: 'Exportar a Excel', text: 'Descarga un archivo con los viajes del periodo y el comparativo báscula vs facturado, con los filtros consultados.' + ($id('btn_excel')?.disabled ? ' Se habilita cuando el informe termina de cargar y las fechas son válidas.' : '') },
                { id: 'actualizar', element: '#btn_consultar', side: 'bottom', title: 'Actualizar', text: 'Vuelve a consultar con los mismos filtros para traer los pesajes más recientes.' },
                { id: 'contexto', element: '#chips_contexto', side: 'bottom', title: 'Consulta actual', text: 'Resume el periodo y sus días, las fincas, la empresa y la fecha del último pesaje registrado. Si el último pesaje aparece en ámbar, el periodo va más allá de los datos cargados.' + (chips.some((c) => c.textContent.includes('prorrateado')) ? ' También indica cuántos viajes se prorratearon entre fincas.' : ''), cuando: () => chips.length > 0 && !vacio?.querySelector('.bi-exclamation-triangle') },
                { id: 'avisos', element: '#resultado_cuerpo > .alert', side: 'bottom', title: 'Avisos', text: 'Revise estos avisos antes de interpretar las cifras: señalan datos faltantes, combinados o parciales.' },
                { id: 'sin_resultados', element: () => vacio, side: 'top', title: tituloVacio, text: textoVacio },
                { id: 'fact_sola', element: () => fact?.querySelector('h6')?.parentElement, side: 'bottom', title: 'Báscula vs facturado', text: 'Aunque no hay pesajes, sí hay facturación de las extractoras en el periodo. Revísela aquí.', cuando: () => !tabs },
                { id: 'regla', element: '#resultado_cuerpo .ba-regla', side: xl ? 'right' : 'bottom', title: 'Regla de kg no llegados', text: 'Cuenta los viajes cruzados con TLC a los que les faltan más de 100 kg (kilos de báscula menos kilos de extractora). Los kilos de más en la extractora no compensan. El periodo cumple, en verde, si ningún viaje se pasa; si alguno se pasa, se marca en rojo. El % no llegados del periodo es solo referencia y la barra muestra cómo se reparten los viajes por estado.' },
                { id: 'resumen', element: () => $q('#resultado_cuerpo .ba-regla')?.closest('.row')?.querySelector('.col-xl-8'), side: xl ? 'left' : 'bottom', title: 'Resumen del periodo', text: 'Viajes pesados (cruzados con tiquete de extractora y sin él), kilos de báscula, kilos recibidos por la extractora y kilos no llegados.' + (fino ? ' Pase el cursor sobre el texto inferior para ver su detalle.' : '') },
                { id: 'estados', element: () => $q('#resultado_cuerpo .ig-ciclo-kpi')?.closest('.row'), side: 'bottom', title: 'Estados de los viajes', text: 'Cumple, Fuera de regla, Sin TLC (sin tiquete de extractora) y Pesaje incompleto (sin segunda pesada). Pulse un estado para filtrar la tabla de viajes e ir a ella; púlselo de nuevo para quitar el filtro. TLC sin pesaje cuenta los tiquetes de extractora sin viaje en báscula.' },
                { id: 'pestanas', element: () => tabs, side: 'bottom', title: 'Pestañas', text: 'Cambie entre Báscula vs extractora, Placas por día, Conductores y Báscula vs facturado. Este recorrido explica la pestaña abierta; para ver la guía de otra, ábrala e inicie de nuevo el tutorial.' + (tabs?.querySelector('.ig-chip.rojo') ? ' El número rojo indica facturas que no cuadran con los tiquetes de la extractora.' : '') },
                { id: 'dias', element: () => sec('dias'), side: 'top', title: 'Báscula vs extractora', text: `Barras de kilos de báscula y de extractora ${mensual ? 'por mes' : 'por día'}; la línea naranja es el % de kilos no llegados, solo como referencia.` + (mensual ? ' Con rangos de más de 92 días se agrupa por mes.' : '') + ' Use la flecha de la derecha para plegar o desplegar cada sección; el sistema recuerda su preferencia.' + nota('dias'), cuando: viajes },
                { id: 'filtro_estado', element: '#sec_viajes .ba-estados', side: 'bottom', title: 'Filtrar por estado', text: 'Muestre solo los viajes de un estado; el número indica cuántos hay. Funciona igual que pulsar las tarjetas de estado.', cuando: viajes },
                { id: 'viajes', element: () => cab('viajes'), side: 'bottom', title: 'Viajes del periodo', text: 'Lista cada viaje pesado en la báscula con su cruce contra el tiquete de la extractora.' + nota('viajes'), cuando: viajes },
                { id: 'buscar_viajes', element: '#tabla_viajes_wrapper > .gp-dt-barra:not(.gp-dt-pie)', side: 'bottom', title: 'Buscar y paginar', text: 'Elija cuántos viajes ver por página y busque por placa, finca, conductor, cédula, extractora o TLC. Los totales del pie se recalculan con lo filtrado.', cuando: viajes },
                { id: 'fila_viaje', element: '#tabla_viajes tbody tr', side: 'bottom', title: 'Detalle del viaje', text: 'Verá la segunda pesada y debajo la primera, los kilos de báscula, el tiquete y los kilos de extractora, la diferencia y el % de pérdida. La diferencia se marca en rojo cuando el viaje queda fuera de regla por faltarle más de 100 kg; el % de pérdida es solo referencia. «Prorrateado» indica una carga de varias fincas repartida según las fincas elegidas.', cuando: () => viajes() && !$q('#tabla_viajes tbody td.dataTables_empty') },
                { id: 'sin_pesaje', element: () => cab('sin_pesaje'), side: 'top', title: 'TLC sin pesaje en báscula', text: 'Tiquetes que la extractora registró sin un viaje correspondiente en la báscula. Revíselos para identificar viajes no registrados.' + nota('sin_pesaje'), cuando: viajes },
                { id: 'placas_grafica', element: () => sec('placas_grafica'), side: 'top', title: 'Placas por extractora', text: `Cuántas placas fueron a cada extractora ${mensual ? 'por mes' : 'por día'}. Los grises corresponden a viajes sin destino o movimientos internos. Pulse un nombre de la leyenda para ocultarlo o mostrarlo.` + nota('placas_grafica'), cuando: () => activa === 'tab_panel_placas' },
                { id: 'placas_tabla', element: () => cab('placas_tabla'), side: 'bottom', title: 'Detalle de placas', text: 'Agrupa por día las placas que fueron a cada extractora. El encabezado indica el total de placas y cuántas son distintas; use el buscador para encontrar una placa o extractora.' + nota('placas_tabla'), cuando: () => activa === 'tab_panel_placas' },
                { id: 'conductores_grafica', element: () => ajustar(sec('conductores_grafica')), side: 'top', title: 'Kg por conductor', text: `Kilos de báscula por conductor, de mayor a menor. ${fino ? 'Pase el cursor sobre' : 'Toque'} una barra para ver los kilos de extractora, no llegados, de más y el % no llegados.` + nota('conductores_grafica'), cuando: () => activa === 'tab_panel_conductores' },
                { id: 'conductores_tabla', element: () => cab('conductores_tabla'), side: 'bottom', title: 'Detalle por conductor', text: 'Pulse el título de una columna para ordenar y use el buscador para encontrar un nombre o cédula. El % no llegados es solo referencia; la columna «Fuera de regla» indica cuántos viajes de cada conductor superaron los 100 kg de faltante.' + nota('conductores_tabla'), cuando: () => activa === 'tab_panel_conductores' },
                { id: 'fact_vacio', element: () => factVacio, side: 'top', title: tituloFact, text: textoFact, cuando: facturado },
                { id: 'fact_aviso', element: () => fact?.querySelector('.alert-info'), side: 'bottom', title: 'Sin detalle por finca', text: 'Siesa no discrimina la facturación por finca: este comparativo muestra el total de la empresa aunque haya elegido fincas.', cuando: facturado },
                { id: 'fact_kpis', element: () => fact?.querySelector('.row'), side: 'bottom', title: 'Resumen de facturación', text: 'Kilos facturados, la diferencia entre lo facturado y los tiquetes de la extractora (verde si cuadra y rojo si no), la diferencia frente a la báscula como referencia y los tiquetes aún sin facturar.', cuando: facturado },
                { id: 'fact_facturas', element: () => cab('fact_facturas'), side: 'bottom', title: 'Facturas de venta', text: 'Cada factura con su periodo, kilos facturados, kilos de tiquetes y la diferencia; las filas resaltadas no cuadran. «Parcial» indica que el periodo de la factura se sale del rango consultado y «Periodo estimado», que se usó la fecha de la factura.' + nota('fact_facturas'), cuando: facturado },
                { id: 'fact_sin', element: () => cab('fact_sin'), side: 'top', title: 'TLC sin facturar', text: 'Tiquetes de extractora del periodo que aún no aparecen en ninguna factura, agrupados por extractora.' + nota('fact_sin'), cuando: facturado },
                { id: 'fact_notas', element: () => cab('fact_notas'), side: 'top', title: 'Anulaciones y ajustes', text: 'Notas crédito que anulan una factura ya refacturada o que solo ajustan el valor. No suman kilos en el comparativo.' + nota('fact_notas'), cuando: facturado },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
