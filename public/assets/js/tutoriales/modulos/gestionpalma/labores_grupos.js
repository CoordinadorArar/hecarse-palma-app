document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);

    window.Tutoriales?.registrar('gp-labores-grupos', {
        titulo: 'Grupos de labor',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fila = $q('#cuerpo_tabla_grupos tr');
            const hayFila = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const enUso = Boolean(fila?.querySelector('.btn-outline-danger:disabled'));
            const col = (id) => document.getElementById(id)?.closest('[class*="col-"]');

            return [
                { id: 'bienvenida', title: 'Grupos de labor', text: 'Aquí administra los grupos con que se clasifican las labores y el centro de costo de cada uno. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => col('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Elija la empresa cuyos grupos de labor desea ver. Al cambiarla, la página se recarga con los datos de esa empresa.' },
                { id: 'estado', element: () => col('filtro_estado'), side: 'bottom', title: 'Estado', text: 'Muestre todos los registros o solo los activos o los inactivos. La lista se actualiza al instante.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo', text: 'Pulse Nuevo para registrar un grupo en la empresa elegida. En el formulario revise el código que se propone solo, escriba la descripción y, si aplica, busque el centro de costo. Deje marcado Activo para que se pueda usar.' },
                { id: 'contador', element: '#contador_grupos', side: 'bottom', title: 'Registros', text: 'Indica cuántos grupos de labor hay con los filtros elegidos.' },
                { id: 'sin_registros', element: '#gp_estado_vacio', side: 'top', title: 'Sin registros', text: 'No hay grupos con la empresa y el estado elegidos. Ajuste el filtro de estado o pulse Nuevo grupo para registrar uno.' },
                { id: 'buscar', element: '#tabla_grupos_wrapper .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Todos los registros aparecen en una sola lista. Escriba en Buscar para filtrarla por cualquier dato visible; a la izquierda verá cuántos se muestran. Pulse el título de una columna para ordenarla.' },
                { id: 'sin_coincidencias', element: '#cuerpo_tabla_grupos td.dataTables_empty', side: 'top', title: 'Sin coincidencias', text: 'Ningún registro coincide con lo que escribió en Buscar. Borre o cambie el texto para ver la lista completa.' },
                { id: 'fila', element: () => (md ? fila : fila?.querySelector('td')), side: 'bottom', title: 'Registros', text: 'Cada fila muestra el código, la descripción, el centro de costo y el estado del grupo. La columna Labores indica cuántas labores pertenecen al grupo.' + (md ? '' : ' Deslice la tabla hacia los lados para ver todas las columnas.'), cuando: () => hayFila },
                { id: 'acciones', element: '#cuerpo_tabla_grupos tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, activar o eliminar', text: 'Use el lápiz para editar el registro en el mismo formulario de Nuevo (la empresa y el código no se pueden cambiar). El interruptor lo activa o desactiva, previa confirmación. La papelera lo elimina definitivamente.' + (enUso ? ' En esta fila la papelera aparece atenuada porque el registro está en uso; si ya no lo necesita, desactívelo.' : ' Si la papelera aparece atenuada, el registro está en uso y no se puede eliminar; en ese caso desactívelo.'), cuando: () => hayFila },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
