document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';

    window.Tutoriales?.registrar('gp-variedades', {
        titulo: 'Variedades',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fila = $q('#cuerpo_tabla_variedades tr');
            const hayFila = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const enUso = Boolean(fila?.querySelector('.btn-outline-danger:disabled'));
            const col = (id) => document.getElementById(id)?.closest('[class*="col-"]');

            return [
                { id: 'bienvenida', title: 'Variedades', text: 'Aquí administra las variedades de palma de cada empresa, que luego se asignan a los lotes. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => col('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Elija la empresa cuyas variedades desea ver. La lista se actualiza al instante.' },
                { id: 'estado', element: () => col('filtro_estado'), side: 'bottom', title: 'Estado', text: 'Muestre todos los registros o solo los activos o los inactivos. La lista se actualiza al instante.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo', text: 'Pulse Nuevo para registrar una variedad. En el formulario elija la empresa, revise el código que se propone solo y escriba la descripción y la procedencia (por ejemplo, Guineensis). Deje marcado Activo para que se pueda usar.' },
                { id: 'contador', element: '#contador_variedades', side: 'bottom', title: 'Registros', text: 'Indica cuántas variedades hay con los filtros elegidos.' },
                { id: 'sin_registros', element: '#gp_estado_vacio', side: 'top', title: 'Sin registros', text: 'No hay variedades con la empresa y el estado elegidos. Ajuste el filtro de estado o pulse Nueva variedad para registrar una.' },
                { id: 'buscar', element: '#tabla_variedades_wrapper .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Todos los registros aparecen en una sola lista. Escriba en Buscar para filtrarla por cualquier dato visible; a la izquierda verá cuántos se muestran. Pulse el título de una columna para ordenarla.' },
                { id: 'excel', element: '#tabla_variedades_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_variedades_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'sin_coincidencias', element: '#cuerpo_tabla_variedades td.dataTables_empty', side: 'top', title: 'Sin coincidencias', text: 'Ningún registro coincide con lo que escribió en Buscar. Borre o cambie el texto para ver la lista completa.' },
                { id: 'fila', element: () => (md ? fila : fila?.querySelector('td')), side: 'bottom', title: 'Registros', text: 'Cada fila muestra el código, la descripción, la procedencia y el estado de la variedad. La columna Lotes indica cuántos lotes la usan.' + (md ? '' : ' Deslice la tabla hacia los lados para ver todas las columnas.'), cuando: () => hayFila },
                { id: 'acciones', element: '#cuerpo_tabla_variedades tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, activar o eliminar', text: 'Use el lápiz para editar el registro en el mismo formulario de Nuevo (la empresa y el código no se pueden cambiar). El interruptor lo activa o desactiva, previa confirmación. La papelera lo elimina definitivamente.' + (enUso ? ' En esta fila la papelera aparece atenuada porque el registro está en uso; si ya no lo necesita, desactívelo.' : ' Si la papelera aparece atenuada, el registro está en uso y no se puede eliminar; en ese caso desactívelo.'), cuando: () => hayFila },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
