document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';
    const $id = (id) => document.getElementById(id);

    window.Tutoriales?.registrar('gp-precios-por-labor', {
        titulo: 'Precios por labor',
        antesDeIniciar: () => ($q('#btn_guardar_cambios .bi-hourglass-split') ? 'Espere a que terminen de guardarse los precios para iniciar el tutorial.' : null),
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const editor = !$id('panel_editor')?.classList.contains('d-none');
            const cambios = document.querySelectorAll('#cuerpo_grilla_precios tr.gp-fila-modificada').length;
            const conAnios = Boolean($q('#cuerpo_tabla_anios tr')) && !$q('#cuerpo_tabla_anios td.dataTables_empty');
            const sinPrecio = Boolean($q('#cuerpo_grilla_precios tr.gp-fila-sinprecio:not(.d-none)'));

            return [
                { id: 'bienvenida', title: 'Precios por labor', text: editor ? 'Está editando los precios de un año: ajuste destajo, contratistas, otros, porcentaje y base sueldo de cada labor y guarde los cambios. Use Siguiente para recorrer las opciones.' : 'Aquí administra la lista general de precios de las labores por año: cree un año, edite sus precios, duplíquelo o elimínelo. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => $id('filtro_empresa')?.closest('.col-md-3'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa; la lista de años se actualiza al instante.', cuando: () => !editor },
                { id: 'busqueda', element: () => $id('filtro_busqueda')?.closest('.col-md-4'), side: 'bottom', title: 'Búsqueda', text: 'Escriba un año y pulse Buscar o Enter. La X borra la búsqueda.', cuando: () => !editor },
                { id: 'nuevo_anio', element: '#btn_nuevo_anio', side: 'bottom', title: 'Nuevo año', text: 'Abre la ventana para crear la lista de precios de un año. Elija la empresa y el año, y decida si replica los precios de un año existente o inicia en ceros con una fila por cada labor activa. Al guardar se abre el editor del año nuevo.', cuando: () => !editor },
                { id: 'contador', element: '#contador_anios', side: 'bottom', title: 'Años registrados', text: 'Indica cuántos años de precios tiene la empresa.', cuando: () => !editor },
                { id: 'sin_anios', element: '#gp_estado_vacio', side: 'top', title: 'Sin años', text: 'No hay años de precios para esta empresa o ninguno coincide con la búsqueda. Pulse Nuevo año para crear el primero.', cuando: () => !editor },
                { id: 'buscar_tabla', element: '#tabla_anios_wrapper .gp-dt-barra', side: 'bottom', title: 'Buscar en resultados', text: 'Muestra el total de años y filtra al instante lo listado, sin volver a consultar.', cuando: () => !editor },
                { id: 'excel', element: '#tabla_anios_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_anios_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : ''), cuando: () => !editor },
                { id: 'fila', element: '#cuerpo_tabla_anios tr', side: 'bottom', title: 'Años', text: 'Cada fila muestra el año, cuántas labores tiene, cuántas con precio, la última actualización y el usuario que la hizo. Sin precios indica que ninguna labor tiene valor.', cuando: () => !editor && conAnios },
                { id: 'acciones', element: '#cuerpo_tabla_anios tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, duplicar o eliminar', text: 'Use el lápiz para abrir el editor de precios del año, el botón de copiar para duplicarlo en un año nuevo y la papelera para eliminar el año con todos sus precios.', cuando: () => !editor && conAnios },
                { id: 'volver', element: '#btn_volver', side: 'bottom', title: 'Volver', text: 'Regresa a la lista de años. Si tiene cambios sin guardar, se le pedirá confirmar antes de salir.', cuando: () => editor },
                { id: 'resumen', element: () => $id('editor_resumen')?.parentElement, side: 'bottom', title: 'Año en edición', text: 'Indica el año que está editando, el total de labores, cuántas tienen precio y cuántas ya no están registradas en el maestro.', cuando: () => editor },
                { id: 'guardar', element: () => $id('btn_guardar_cambios')?.parentElement, side: 'bottom', title: 'Guardar cambios', text: cambios ? `Tiene ${cambios} ${cambios === 1 ? 'cambio' : 'cambios'} sin guardar. Pulse Guardar cambios para guardarlos todos a la vez o Descartar para volver a los valores guardados.` : 'Guardar cambios se habilita cuando modifica un valor. Aquí verá cuántos cambios tiene pendientes y el botón Descartar para deshacerlos.', cuando: () => editor },
                { id: 'filtros_editor', element: () => $id('editor_anio')?.closest('.card-body'), side: 'bottom', title: 'Año y filtros', text: 'Cambie de año sin volver a la lista, filtre por grupo, busque por código o descripción y, en Mostrar, elija todas, solo con precio, solo sin precio o solo las no registradas. Los filtros no borran sus cambios; si cambia de año con cambios pendientes, se le pedirá confirmar.', cuando: () => editor },
                { id: 'aviso_huerfanas', element: '#editor_aviso_huerfanas', side: 'bottom', title: 'Labores no registradas', text: 'Algunas labores con precio en este año ya no existen en el maestro. Se conservan por sus movimientos históricos y se pueden editar; aparecen al final con la etiqueta Labor no registrada.', cuando: () => editor },
                { id: 'grupo', element: '#cuerpo_grilla_precios tr.gp-fila-grupo:not(.d-none)', side: 'bottom', title: 'Grupos', text: 'Las labores se agrupan por grupo; el separador indica cuántas hay y cuántas tienen cambios. Los separadores se ocultan al filtrar por grupo o buscar.', cuando: () => editor },
                { id: 'fila_precio', element: '#cuerpo_grilla_precios tr[data-novedad]:not(.d-none)', side: 'bottom', title: 'Editar precios', text: 'Escriba destajo, contratistas, otros y porcentaje directamente en la fila; Enter baja a la misma columna de la siguiente labor. Los valores modificados se marcan en ámbar y la fila muestra un punto hasta que guarde.' + (sinPrecio ? ' La etiqueta Sin precio indica que la labor aún no tiene precio en este año; se crea al guardar.' : ''), cuando: () => editor },
                { id: 'base_sueldo', element: () => $q('#cuerpo_grilla_precios tr[data-novedad]:not(.d-none) [data-campo="baseSueldo"]')?.closest('td'), side: 'left', title: 'Base sueldo', text: 'Actívelo para que la liquidación use el salario mínimo diario en lugar del destajo, que se muestra tachado. Si además indica un porcentaje, se liquida con ese porcentaje del salario. Mientras esté apagado, el porcentaje aparece tachado.', cuando: () => editor },
                { id: 'editor_vacio', element: '#gp_editor_vacio', side: 'top', title: 'Sin coincidencias', text: 'Ninguna labor coincide con los filtros. Pulse Limpiar filtros para ver todas.', cuando: () => editor },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
