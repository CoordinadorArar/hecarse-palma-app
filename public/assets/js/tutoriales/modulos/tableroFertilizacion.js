document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const ancho = (px) => window.matchMedia(`(min-width: ${px}px)`).matches;
    const tarjeta = (icono) => $q(`#resultado_cuerpo .card-header .${icono}`)?.closest('.card');
    const vacia = (el, texto, vacio = 'No hay datos para esta gráfica con los filtros elegidos.') => (el?.querySelector('.tb-grafica-vacia') ? vacio : texto);
    const ajustar = (el) => (el && el.offsetHeight > window.innerHeight * 0.7 ? (el.querySelector('.card-header') || el.firstElementChild) : el);

    window.Tutoriales?.registrar('tablero-fertilizacion', {
        titulo: 'Fertilización',
        antesDeIniciar: () => ($q('#btn_consultar .spinner-border, #resultado_cuerpo[aria-busy="true"], #resultado_cuerpo .placeholder-glow, #resultado_cuerpo:empty')
            ? 'Espere a que termine de cargar el tablero para iniciar el tutorial.' : null),
        pasos: () => {
            const [sm, xl] = [576, 1200].map(ancho);
            const fino = window.matchMedia('(pointer: fine)').matches;
            const chips = [...($id('chips_contexto')?.children || [])];
            const tiene = (t) => chips.some((c) => c.textContent.includes(t));
            const vacio = $q('#resultado_cuerpo > .card > .gp-estado-vacio');
            const mes = tiene('plan completo');

            const [tituloVacio, textoVacio] = vacio?.querySelector('.bi-exclamation-octagon')
                ? ['No se pudo cargar', 'Ocurrió un problema al consultar. Pulse Reintentar o revise los filtros.']
                : (vacio?.querySelector('.bi-shield-lock')
                    ? ['Acción no permitida', 'Su usuario no tiene permiso para consultar este tablero. Comuníquese con el administrador.']
                    : ['Sin resultados', 'No hay aplicaciones ni planes de fertilización para los filtros elegidos. Ajuste el año, el mes, la finca, el lote o el producto.']);

            return [
                { id: 'bienvenida', title: 'Fertilización', text: 'Aquí compara la dosis de fertilizante aplicada con la planificada, por finca, sector, lote y producto. Use Siguiente para recorrer las opciones principales.' },
                { id: 'filtros', element: '#form_filtros', side: 'bottom', title: 'Filtros', text: 'Elija la empresa, el año y el mes. Con el mes en «Todos» verá el acumulado del año; con un mes, solo lo aplicado en ese mes. Sin finca verá todas las fincas de la empresa.' },
                { id: 'ubicacion', element: () => $id('filtro_finca')?.closest('.col-12'), side: 'bottom', title: 'Finca o sector', text: 'Elija una finca completa o uno de sus sectores. Al elegirla se habilita el filtro de lote.' },
                { id: 'lote', element: () => $id('filtro_lote')?.closest('.col-12'), side: 'bottom', title: 'Lote', text: $id('filtro_lote')?.disabled ? 'Se habilita al elegir una finca o un sector y muestra solo sus lotes.' : 'Elija un lote de la finca o del sector seleccionado, o déjelo en «Todos».' },
                { id: 'producto', element: () => $id('filtro_producto')?.closest('.col-12'), side: 'bottom', title: 'Producto', text: 'Filtre por un fertilizante. Con «Todos los productos» la dosis es la suma de todos los aplicados. La lista se actualiza con los productos de cada consulta.' },
                { id: 'consultar', element: '#btn_consultar', side: 'bottom', title: 'Consultar', text: 'Pulse Consultar para actualizar el tablero con los filtros elegidos. Si cambia un filtro y no consulta, se le avisará que lo que ve está desactualizado.' },
                { id: 'contexto', element: '#chips_contexto', side: 'bottom', title: 'Consulta actual', text: 'Resume el periodo, la empresa, la finca, el lote y el producto con que se generó lo que está viendo.' + ($q('#chips_contexto [data-accion="todas"]') ? ' Pulse la × de la finca para volver a ver todas.' : '') + (tiene('Dosis sumada') ? ' En ámbar se advierte que la dosis suma todos los productos.' : '') + (mes ? ' Al ver un solo mes, la meta corresponde al plan completo vigente.' : ''), cuando: () => chips.length > 0 },
                { id: 'desactualizado', element: '#chip_desactualizado', side: 'bottom', title: 'Filtros modificados', text: 'Cambió los filtros después de consultar: lo que ve aún corresponde a la consulta anterior. Pulse Consultar para actualizarlo.' },
                { id: 'avisos', element: '#resultado_cuerpo > .alert', side: 'bottom', title: 'Avisos', text: 'Revise estos avisos antes de interpretar las cifras: señalan datos faltantes, combinados o parciales.' },
                { id: 'sin_resultados', element: () => vacio, side: 'top', title: tituloVacio, text: textoVacio },
                { id: 'kpis', element: () => { const k = $q('#resultado_cuerpo .inf-kpi'); return sm ? k?.closest('.row') : k; }, side: 'bottom', title: 'Indicadores', text: 'Cumplimiento de dosis compara los kilos aplicados con los planificados: verde si está dentro de ±5 % del plan, ámbar dentro de ±15 % y rojo si se aleja más, por defecto o por exceso. También verá los kilos y bultos por hectárea y los kilos por palma que faltan para la meta.' + (mes ? ' Al consultar un solo mes, el cumplimiento se muestra en gris porque se compara con el plan completo.' : '') },
                { id: 'aplicado', element: () => ajustar(tarjeta('bi-bar-chart-steps')), side: xl ? 'right' : 'bottom', title: 'Dosis aplicada por lote', text: vacia(tarjeta('bi-bar-chart-steps'), `Kilos por palma aplicados en cada lote. ${fino ? 'Pase el cursor sobre' : 'Toque'} una barra para ver el nombre del lote y su valor.`) },
                { id: 'desviacion', element: () => ajustar(tarjeta('bi-plus-slash-minus')), side: xl ? 'left' : 'top', title: 'Meta de dosis vs desviación', text: vacia(tarjeta('bi-plus-slash-minus'), 'Para cada lote, la barra gris es la meta en kilos por palma y la de color es la desviación: roja si falta, ámbar si hay exceso y verde si está dentro de ±5 % de la meta.', 'No hay meta de dosis para los filtros elegidos, por eso esta gráfica aparece vacía.') },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
