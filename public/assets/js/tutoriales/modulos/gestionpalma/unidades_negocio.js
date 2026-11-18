document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);

    window.Tutoriales?.registrar('gp-unidades-negocio', {
        titulo: 'Unidades de negocio',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fino = window.matchMedia('(pointer: fine)').matches;
            const fila = $q('#cuerpo_tabla_unidades tr');
            const hayFila = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const enUso = Boolean(fila?.querySelector('.btn-outline-danger:disabled'));
            const col = (id) => document.getElementById(id)?.closest('[class*="col-"]');
            const primerUso = $q('#filtro_finca')?.value === '' && $q('#filtro_estado')?.value === '';

            return [
                { id: 'bienvenida', title: 'Unidades de negocio', text: 'Aquí define las unidades de negocio de la empresa (por ejemplo Palma, Búfalos o Ganadería) y en qué fincas se maneja cada una. Cada lote pertenece a una unidad de negocio habilitada en su finca. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => col('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Elija la empresa cuyas unidades de negocio desea ver. Al cambiarla, la página se recarga con los datos de esa empresa.' },
                { id: 'finca', element: () => col('filtro_finca'), side: 'bottom', title: 'Finca', text: 'Elija una finca para ver solo las unidades de negocio que se manejan en ella, o deje Todas. La lista se actualiza al instante.' },
                { id: 'estado', element: () => col('filtro_estado'), side: 'bottom', title: 'Estado', text: 'Muestre todos los registros o solo los activos o los inactivos. La lista se actualiza al instante.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo', text: 'Pulse Nuevo para registrar una unidad de negocio. Escriba un código corto (hasta 10 caracteres, por ejemplo PAL) y la descripción, y marque las fincas donde se maneja; Marcar todas y Quitar todas agilizan la selección. Deje marcado Activo para que se pueda asignar a los lotes.' },
                { id: 'contador', element: '#contador_unidades', side: 'bottom', title: 'Registros', text: 'Indica cuántas unidades de negocio hay con los filtros elegidos.' },
                { id: 'sin_registros', element: '#gp_estado_vacio', side: 'top', title: primerUso ? 'Aún no hay unidades de negocio' : 'Sin registros', text: primerUso ? 'Una unidad de negocio agrupa los lotes según su actividad, como Palma o Búfalos. Pulse Nueva unidad de negocio para crear la primera.' : 'Ninguna unidad de negocio coincide con los filtros de finca o estado. Ajústelos o pulse Nueva unidad de negocio para registrar una.' },
                { id: 'buscar', element: '#tabla_unidades_wrapper .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Todos los registros aparecen en una sola lista. Escriba en Buscar para filtrarla por cualquier dato visible; a la izquierda verá cuántos se muestran. Pulse el título de una columna para ordenarla.' },
                { id: 'sin_coincidencias', element: '#cuerpo_tabla_unidades td.dataTables_empty', side: 'top', title: 'Sin coincidencias', text: 'Ningún registro coincide con lo que escribió en Buscar. Borre o cambie el texto para ver la lista completa.' },
                { id: 'fila', element: () => (md ? fila : fila?.querySelector('td')), side: 'bottom', title: 'Registros', text: 'Cada fila muestra el código, la descripción, las fincas donde se maneja la unidad, cuántos lotes la usan y el estado.' + (md ? '' : ' Deslice la tabla hacia los lados para ver todas las columnas.'), cuando: () => hayFila },
                { id: 'fincas', element: '#cuerpo_tabla_unidades .gp-chip-fila', side: 'bottom', title: 'Fincas', text: 'Cada etiqueta es una finca donde se maneja la unidad de negocio; si son muchas, la etiqueta +N agrupa las restantes' + (fino ? ' y al pasar el cursor muestra sus nombres.' : '.') + ' Para agregar o quitar fincas, edite el registro. Una finca no se puede quitar mientras alguno de sus lotes use la unidad: en el formulario aparece con candado.', cuando: () => hayFila },
                { id: 'acciones', element: '#cuerpo_tabla_unidades tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, activar o eliminar', text: 'Use el lápiz para editar el registro en el mismo formulario de Nuevo (el código no se puede cambiar). El interruptor lo activa o desactiva, previa confirmación; una unidad inactiva deja de ofrecerse al registrar lotes. La papelera la elimina definitivamente.' + (enUso ? ' En esta fila la papelera aparece atenuada porque la unidad tiene fincas asignadas o lotes que la usan; si ya no la necesita, desactívela.' : ' Si la papelera aparece atenuada, la unidad tiene fincas asignadas o lotes que la usan y no se puede eliminar; en ese caso desactívela.'), cuando: () => hayFila },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
