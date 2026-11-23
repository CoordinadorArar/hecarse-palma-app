document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';
    const campo = (id) => document.getElementById(id)?.parentElement;
    const busqueda = () => document.getElementById('filtro_busqueda')?.closest('.input-group')?.parentElement;

    window.Tutoriales?.registrar('gp-nomina-periodos', {
        titulo: 'Periodos de nómina',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fino = window.matchMedia('(pointer: fine)').matches;
            const fila = $q('#cuerpo_tabla_periodos tr');
            const conDatos = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const resp = $q('#tabla_periodos_nomina_wrapper .table-responsive');
            const desborda = Boolean(resp) && resp.scrollWidth > resp.clientWidth;
            const estado = () => (conDatos ? fila.querySelector('.gp-badge-abierto, .gp-badge-cerrado') : null);
            const acciones = () => (conDatos ? fila.querySelector('.btn-group') : null);
            const deslizar = desborda ? ' La tabla es ancha: deslícela hacia los lados para ver todas las columnas. La columna Acciones queda fija a la derecha.' : '';

            return [
                { id: 'bienvenida', title: 'Periodos de nómina', text: 'Aquí administra los periodos de nómina de cada empresa: su tipo, sus fechas de vigencia, corte y pago, y si están abiertos o cerrados. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => campo('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa. La lista se actualiza al cambiarla.' },
                { id: 'anio', element: () => campo('filtro_anio'), side: 'bottom', title: 'Año', text: 'Elija el año que desea ver o Todos los años. La lista se actualiza al cambiarlo.' },
                { id: 'busqueda', element: busqueda, side: 'bottom', title: 'Búsqueda', text: 'Escriba el mes, el número del periodo o su nombre y pulse Enter o Buscar. La X borra la búsqueda.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo periodo', text: 'Abre el formulario para crear un periodo. Indique la empresa, el año, el mes, el número del periodo y el tipo de nómina (los días se sugieren según el tipo). Luego complete las fechas inicial, final, de corte y de pago y los indicadores Agronómico, Ejecuta labores y Periodo cerrado. El nombre es opcional. Pulse Guardar.' },
                { id: 'contador', element: '#contador_periodos', side: 'bottom', title: 'Resultados', text: 'Indica cuántos periodos coinciden con los filtros.' },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin periodos', text: 'No hay periodos de nómina para los filtros elegidos. Ajústelos o pulse Nuevo periodo para crearlo.' },
                { id: 'buscar_resultados', element: '#tabla_periodos_nomina_wrapper .gp-dt-barra:not(.gp-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Use Buscar en resultados para filtrar lo que ya está listado sin volver a consultar, y elija cuántos registros ver por página. Pulse el título de una columna para ordenar.' + deslizar },
                { id: 'excel', element: '#tabla_periodos_nomina_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_periodos_nomina_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'fila', element: () => (conDatos ? (desborda ? fila.cells[1] : fila) : null), side: 'bottom', title: 'Periodos listados', text: 'Cada fila muestra el año, el mes, el número y el tipo del periodo, los días, la vigencia (fecha inicial y final), las fechas de corte y pago y el nombre.', cuando: () => conDatos },
                { id: 'indicadores', element: () => (conDatos ? fila.cells[8] : null), side: 'bottom', title: 'Indicadores', text: 'AGR indica un periodo agronómico y LAB un periodo que ejecuta labores: en verde si aplica y en gris si no.' + (fino ? ' Pase el cursor sobre una etiqueta para ver su significado.' : ''), cuando: () => conDatos },
                { id: 'estado', element: estado, side: md ? 'left' : 'bottom', title: 'Estado', text: 'Abierto permite modificar el periodo. Cerrado lo bloquea para cambios.', cuando: () => conDatos },
                { id: 'acciones', element: acciones, side: md ? 'left' : 'bottom', title: 'Editar, abrir/cerrar o eliminar', text: 'Use el lápiz para editar el periodo (la empresa, el año, el mes y el número no cambian), el candado para cerrarlo o abrirlo y la papelera para eliminarlo. El candado y la papelera piden confirmación. Un periodo cerrado debe abrirse antes de editarlo o eliminarlo, y no se elimina si tiene nómina liquidada o conceptos fijos asociados.', cuando: () => conDatos },
                { id: 'paginacion', element: '#tabla_periodos_nomina_wrapper .gp-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.' },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
