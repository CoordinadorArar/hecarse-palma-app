document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const campo = (id) => document.getElementById(id)?.parentElement;
    const busqueda = () => document.getElementById('filtro_busqueda')?.closest('.input-group')?.parentElement;

    window.Tutoriales?.registrar('gp-periodos', {
        titulo: 'Periodos',
        antesDeIniciar: () => ($q('#gp_acciones_masivas .bi-hourglass-split, #btn_generar_anio_vacio .bi-hourglass-split') ? 'Espere a que termine el proceso para iniciar el tutorial.' : null),
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fila = $q('#cuerpo_tabla_periodos tr');
            const conDatos = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const resp = $q('#tabla_periodos_wrapper .table-responsive');
            const desborda = Boolean(resp) && resp.scrollWidth > resp.clientWidth;
            const estado = () => (conDatos ? fila.querySelector('.gp-badge-abierto, .gp-badge-cerrado') : null);
            const acciones = () => (conDatos ? fila.querySelector('.btn-group') : null);
            const deslizar = desborda ? ' Deslice la tabla hacia los lados para ver todas las columnas.' : '';
            const sinAnio = $q('#filtro_anio')?.value === '';
            const habilitar = sinAnio ? ' Seleccione un año en el filtro Año para habilitarlo.' : '';

            return [
                { id: 'bienvenida', title: 'Periodos', text: 'Aquí administra los periodos contables de cada empresa: los crea, los abre o cierra y los elimina, uno a uno o por año completo. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => campo('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa. La lista se actualiza al cambiarla.' },
                { id: 'anio', element: () => campo('filtro_anio'), side: 'bottom', title: 'Año', text: 'Elija el año que desea ver o Todos los años. Las acciones por año solo se habilitan con un año específico.' },
                { id: 'busqueda', element: busqueda, side: 'bottom', title: 'Búsqueda', text: 'Escriba parte de la descripción, el periodo (por ejemplo 202608) o el mes y pulse Enter o Buscar. La X borra la búsqueda.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo periodo', text: 'Abre el formulario para crear un periodo. Elija empresa, año y mes (el 13 es el cierre del año). El código del periodo y la descripción se completan solos. Indique las fechas inicial y final y si queda cerrado, y pulse Guardar.' },
                { id: 'contador', element: '#contador_periodos', side: 'bottom', title: 'Resultados', text: 'Indica cuántos periodos coinciden con los filtros.' },
                { id: 'generar_anio', element: '#btn_generar_anio', side: 'bottom', title: 'Generar periodos año', text: 'Crea de una vez los 13 periodos del año elegido (12 meses y el cierre) con sus fechas. Los periodos que ya existen no se modifican.' + habilitar },
                { id: 'toggle_anio', element: '#btn_toggle_anio', side: 'bottom', title: 'Abrir/cerrar periodos año', text: 'Cierra o abre todos los periodos del año elegido. En la confirmación elija Cerrar todos o Abrir todos.' + habilitar },
                { id: 'eliminar_anio', element: '#btn_eliminar_anio', side: 'bottom', title: 'Eliminar periodos año', text: 'Elimina todos los periodos del año elegido y no se puede deshacer. Solo es posible si ninguno está cerrado ni tiene movimientos contabilizados.' + habilitar },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin periodos', text: sinAnio ? 'No hay periodos para los filtros elegidos. Ajuste la búsqueda o seleccione un año para poder generar sus periodos.' : 'No hay periodos para los filtros elegidos. Pulse Generar periodos año para crear los 13 periodos del año, o ajuste la búsqueda.' },
                { id: 'buscar_resultados', element: '#tabla_periodos_wrapper .gp-dt-barra:not(.gp-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Use Buscar en resultados para filtrar lo que ya está listado sin volver a consultar, y elija cuántos registros ver por página. Pulse el título de una columna para ordenar.' + deslizar },
                { id: 'fila', element: () => (conDatos ? (desborda ? fila.cells[2] : fila) : null), side: 'bottom', title: 'Periodos listados', text: 'Cada fila muestra el año, el mes, la descripción, el código del periodo y sus fechas inicial y final.', cuando: () => conDatos },
                { id: 'cierre', element: '#cuerpo_tabla_periodos tr.gp-fila-cierre', side: 'bottom', title: 'Cierre del año', text: 'La fila resaltada en ámbar es el periodo 13, Cierre del año. Se genera sin fechas inicial ni final.' },
                { id: 'estado', element: estado, side: md ? 'left' : 'bottom', title: 'Estado', text: 'Abierto permite modificar el periodo. Cerrado lo bloquea para cambios.', cuando: () => conDatos },
                { id: 'acciones', element: acciones, side: md ? 'left' : 'bottom', title: 'Editar, abrir/cerrar o eliminar', text: 'Use el lápiz para editar la descripción, las fechas o el estado (la empresa, el año y el mes no cambian). El candado cierra o abre el periodo y la papelera lo elimina; ambos piden confirmación. Un periodo cerrado debe abrirse antes de editarlo o eliminarlo, y no se eliminan periodos con movimientos contabilizados.', cuando: () => conDatos },
                { id: 'paginacion', element: '#tabla_periodos_wrapper .gp-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.' },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
