document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';

    window.Tutoriales?.registrar('gp-secciones', {
        titulo: 'Secciones / Bloques',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fila = $q('#cuerpo_tabla_secciones tr');
            const hayFila = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const enUso = Boolean(fila?.querySelector('.btn-outline-danger:disabled'));
            const col = (id) => document.getElementById(id)?.closest('[class*="col-"]');
            const primerUso = $q('#filtro_finca')?.value === '' && $q('#filtro_estado')?.value === '';

            return [
                { id: 'bienvenida', title: 'Secciones / Bloques', text: 'Aquí divide cada finca en secciones o bloques para llevar el control por zona y ver cuántas hectáreas tiene asignadas. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => col('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Elija la empresa cuyas secciones desea ver. Al cambiarla, la página se recarga con los datos de esa empresa.' },
                { id: 'finca', element: () => col('filtro_finca'), side: 'bottom', title: 'Finca', text: 'Elija una finca para ver solo sus secciones y cuántas hectáreas tiene asignadas, o deje Todas. La lista se actualiza al instante.' },
                { id: 'estado', element: () => col('filtro_estado'), side: 'bottom', title: 'Estado', text: 'Muestre todos los registros o solo los activos o los inactivos. La lista se actualiza al instante.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo', text: 'Pulse Nuevo para registrar una sección en la empresa elegida. En el formulario elija la finca (llega la del filtro, si eligió una) y escriba el código, la descripción y, si la conoce, el área en hectáreas brutas. Deje marcado Activo para que se pueda usar.' },
                { id: 'contador', element: '#contador_secciones', side: 'bottom', title: 'Registros', text: 'Indica cuántas secciones hay con los filtros elegidos.' },
                { id: 'hectareas', element: '#contador_hectareas', side: 'bottom', title: 'Hectáreas asignadas', text: 'Suma las hectáreas brutas de las secciones de la finca elegida y muestra qué porcentaje del área bruta de la finca representan.', cuando: () => Boolean($q('#contador_hectareas')?.textContent.trim()) },
                { id: 'sin_registros', element: '#gp_estado_vacio', side: 'top', title: primerUso ? 'Aún no hay secciones' : 'Sin registros', text: primerUso ? 'Una sección o bloque es una parte de una finca. Pulse Nueva sección para crear la primera.' : 'Ninguna sección coincide con los filtros de finca o estado. Ajústelos o pulse Nueva sección para registrar una.' },
                { id: 'buscar', element: '#tabla_secciones_wrapper .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Todos los registros aparecen en una sola lista. Escriba en Buscar para filtrarla por cualquier dato visible; a la izquierda verá cuántos se muestran. Pulse el título de una columna para ordenarla.' },
                { id: 'excel', element: '#tabla_secciones_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_secciones_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'sin_coincidencias', element: '#cuerpo_tabla_secciones td.dataTables_empty', side: 'top', title: 'Sin coincidencias', text: 'Ningún registro coincide con lo que escribió en Buscar. Borre o cambie el texto para ver la lista completa.' },
                { id: 'fila', element: () => (md ? fila : fila?.querySelector('td')), side: 'bottom', title: 'Registros', text: 'Cada fila muestra el código, la finca, la descripción, las hectáreas brutas y el estado. La columna Uso indica cuántos registros (lotes, pesos, sanidad y transacciones) usan la sección.' + (md ? '' : ' Deslice la tabla hacia los lados para ver todas las columnas.'), cuando: () => hayFila },
                { id: 'acciones', element: '#cuerpo_tabla_secciones tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, activar o eliminar', text: 'Use el lápiz para editar el registro en el mismo formulario de Nuevo (la finca y el código no se pueden cambiar). El interruptor lo activa o desactiva, previa confirmación. La papelera lo elimina definitivamente.' + (enUso ? ' En esta fila la papelera aparece atenuada porque el registro está en uso; si ya no lo necesita, desactívelo.' : ' Si la papelera aparece atenuada, el registro está en uso y no se puede eliminar; en ese caso desactívelo.'), cuando: () => hayFila },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
