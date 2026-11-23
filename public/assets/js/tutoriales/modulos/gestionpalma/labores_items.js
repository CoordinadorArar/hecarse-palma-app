document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';

    window.Tutoriales?.registrar('gp-labores-items', {
        titulo: 'Ítems',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fila = $q('#cuerpo_tabla_items tr');
            const hayFila = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const enUso = Boolean(fila?.querySelector('.btn-outline-danger:disabled'));
            const col = (id) => document.getElementById(id)?.closest('[class*="col-"]');

            return [
                { id: 'bienvenida', title: 'Ítems', text: 'Aquí administra los ítems de cada empresa, con su referencia, unidad de medida y notas. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => col('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Elija la empresa cuyos ítems desea ver. Al cambiarla, la página se recarga con los datos de esa empresa.' },
                { id: 'estado', element: () => col('filtro_estado'), side: 'bottom', title: 'Estado', text: 'Muestre todos los registros o solo los activos o los inactivos. La lista se actualiza al instante.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo', text: 'Pulse Nuevo para registrar un ítem en la empresa elegida. En el formulario revise el código que se propone solo, escriba la descripción y elija la unidad de medida; la descripción corta, la referencia y las notas son opcionales. Deje marcado Activo para que se pueda usar.' },
                { id: 'contador', element: '#contador_items', side: 'bottom', title: 'Registros', text: 'Indica cuántos ítems hay con los filtros elegidos.' },
                { id: 'sin_registros', element: '#gp_estado_vacio', side: 'top', title: 'Sin registros', text: 'No hay ítems con la empresa y el estado elegidos. Ajuste el filtro de estado o pulse Nuevo item para registrar uno.' },
                { id: 'buscar', element: '#tabla_items_wrapper .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Todos los registros aparecen en una sola lista. Escriba en Buscar para filtrarla por cualquier dato visible; a la izquierda verá cuántos se muestran. Pulse el título de una columna para ordenarla.' },
                { id: 'excel', element: '#tabla_items_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_items_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'sin_coincidencias', element: '#cuerpo_tabla_items td.dataTables_empty', side: 'top', title: 'Sin coincidencias', text: 'Ningún registro coincide con lo que escribió en Buscar. Borre o cambie el texto para ver la lista completa.' },
                { id: 'fila', element: () => (md ? fila : fila?.querySelector('td')), side: 'bottom', title: 'Registros', text: 'Cada fila muestra el código, la descripción (con la descripción corta debajo, si la tiene), la referencia, la unidad y el estado. El ícono de la columna Notas indica que el ítem tiene notas; pase el cursor sobre él para leerlas. La columna Uso indica cuántos registros usan el ítem.' + (md ? '' : ' Deslice la tabla hacia los lados para ver todas las columnas.'), cuando: () => hayFila },
                { id: 'acciones', element: '#cuerpo_tabla_items tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, activar o eliminar', text: 'Use el lápiz para editar el registro en el mismo formulario de Nuevo (la empresa y el código no se pueden cambiar). Al editar verá también la fecha de la última actualización, si la hay. El interruptor lo activa o desactiva, previa confirmación. La papelera lo elimina definitivamente.' + (enUso ? ' En esta fila la papelera aparece atenuada porque el registro está en uso; si ya no lo necesita, desactívelo.' : ' Si la papelera aparece atenuada, el registro está en uso y no se puede eliminar; en ese caso desactívelo.'), cuando: () => hayFila },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
