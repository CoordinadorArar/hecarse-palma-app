document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const DESLIZAR = ' Deslice la tabla hacia los lados para ver todas las columnas; las acciones quedan fijas a la derecha.';
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';

    window.Tutoriales?.registrar('gp-lotes-peso-rff', {
        titulo: 'Lotes / Peso RFF',
        pasos: () => {
            const [md, lg] = [768, 992].map((px) => window.matchMedia(`(min-width: ${px}px)`).matches);
            const fino = window.matchMedia('(pointer: fine)').matches;
            const hayFilas = () => !$q('#tabla_pesos tbody td.dataTables_empty');
            const r = $q('#tabla_pesos_wrapper .table-responsive');
            const desborda = Boolean(r) && r.scrollWidth > r.clientWidth + 1;

            return [
                { id: 'bienvenida', title: 'Peso RFF', text: 'Aquí registra y consulta el peso promedio del racimo de fruta fresca (RFF) de cada lote por mes, con su origen y vigencia. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => $id('filtro_empresa')?.parentElement, side: 'bottom', title: 'Empresa', text: 'Elija la empresa. Al cambiarla, la página se recarga con sus fincas y lotes.' },
                { id: 'anio', element: () => $id('filtro_anio')?.parentElement, side: 'bottom', title: 'Año', text: 'Elija el año de los pesos que desea ver. La lista se actualiza al instante.' },
                { id: 'mes', element: () => $id('filtro_mes')?.parentElement, side: 'bottom', title: 'Mes', text: 'Elija el mes o Cierre del año, que guarda el peso de cierre anual del lote.' },
                { id: 'finca', element: () => $id('filtro_finca')?.parentElement, side: 'bottom', title: 'Finca', text: 'Muestre los pesos de una finca o de todas.' },
                { id: 'problemas', element: () => $id('filtro_problemas')?.parentElement, side: 'bottom', title: 'Problemas', text: 'Elija Solo con problemas para ver registros con datos inconsistentes: periodo fuera de rango o lote que no existe en la finca. Con este filtro, año y mes permiten también la opción Todos.' },
                { id: 'nuevo', element: '#btn_nuevo', side: lg ? 'left' : 'bottom', title: 'Nuevo', text: 'Abre el formulario para registrar el peso de un lote en un periodo: año, mes, finca y lote; el peso en kilogramos; si es Automático; y la vigencia (fecha inicial y final), que se precarga con el mes. Llega con el periodo y la finca de los filtros.' },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin registros', text: 'No hay pesos registrados para el periodo y la finca elegidos. Ajuste los filtros o pulse Registrar peso.' },
                { id: 'buscar', element: '#tabla_pesos_wrapper > .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Indica cuántos registros se muestran. Escriba en Buscar para filtrar por cualquier dato visible y pulse el título de una columna para ordenar.' },
                { id: 'excel', element: '#tabla_pesos_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_pesos_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'fila', element: '#tabla_pesos tbody tr', side: 'bottom', title: 'Datos del peso', text: 'Cada fila muestra el periodo (Cierre del año se resalta aparte), la finca con su código, el lote, el peso en kilogramos, el origen (AUTO si se calculó automáticamente, MANUAL si se registró a mano) y la vigencia.' + (desborda ? DESLIZAR : ''), cuando: hayFilas },
                { id: 'problema', element: '#cuerpo_tabla_pesos .bg-danger-subtle', side: 'bottom', title: 'Dato con problema', text: 'El triángulo rojo señala un periodo fuera de rango o un lote que no existe en la finca.' + (fino ? ' Pase el cursor sobre él para ver el detalle.' : '') + ' Como el periodo y el lote no se pueden editar, elimine el registro y regístrelo de nuevo con los datos correctos.' },
                { id: 'acciones', element: '#cuerpo_tabla_pesos tr .btn-group', side: md ? 'left' : 'bottom', title: 'Acciones', text: 'Use el lápiz para corregir el peso, el origen o la vigencia; el año, el mes, la finca y el lote no se pueden cambiar. La papelera elimina el registro tras confirmar.', cuando: hayFilas },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
