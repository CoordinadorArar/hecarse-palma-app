document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';
    const $id = (id) => document.getElementById(id);
    const desliza = (sel) => { const el = $q(sel); return Boolean(el) && el.scrollWidth > el.clientWidth; };

    window.Tutoriales?.registrar('gp-precios-por-lote', {
        titulo: 'Precios labor por lote',
        antesDeIniciar: () => ($q('#panel_referencia:not(.d-none) #referencia_cargando:not(.d-none), #btn_guardar .bi-hourglass-split') ? 'Espere a que termine de cargar para iniciar el tutorial.' : null),
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const lg = window.matchMedia('(min-width: 992px)').matches;
            const lado = lg ? 'right' : 'bottom';
            const editando = !$id('registro_encabezado_editar')?.classList.contains('d-none');
            const copiado = Boolean($q('#chip_copiado:not(.d-none)'));
            const sinSecciones = Boolean($id('form_switch_seccion')?.disabled);
            const sinAnios = Boolean($q('#nota_anio:not(.d-none)'));
            const conFilas = Boolean($q('#cuerpo_tabla_precios tr')) && !$q('#cuerpo_tabla_precios td.dataTables_empty');

            return [
                { id: 'bienvenida', title: 'Precios labor por lote', text: 'Aquí registra precios de labor que reemplazan al precio general del año para una finca, una sección o un lote. Use Siguiente para recorrer las opciones principales.' },
                { id: 'nota_general', element: 'section.section > .gp-nota', side: 'bottom', title: 'Precio general', text: 'Estos precios tienen prioridad sobre la lista general del año. Use Abrir Precios por labor para ir a esa lista.' },
                { id: 'filtros', element: () => $id('filtro_empresa')?.closest('.gp-filtros'), side: 'bottom', title: 'Filtros', text: 'Elija la empresa y filtre el listado por año, finca o texto de labor o lote. Año y finca se aplican al elegirlos; la búsqueda, con Buscar o Enter. Al cambiar de empresa, el formulario se limpia.' },
                { id: 'encabezado', element: '.gp-panel-registro > .card-header', side: lado, title: editando ? 'Editar precio' : 'Registrar precio', text: editando ? 'Está editando un precio existente. Duplicar copia sus datos a un registro nuevo para otro ámbito; Cancelar edición deja el formulario en blanco.' : 'Aquí registra un precio nuevo para la empresa indicada.' + (copiado ? ' La etiqueta verde indica que el formulario viene copiado de otro precio; cambie el ámbito o la labor antes de guardar.' : '') },
                { id: 'ambito_llave', element: '#bloque_llave', side: lado, title: 'Año, finca y ámbito', text: 'Elija el año y la finca. Active Especificar sección o Especificar lote para que el precio aplique solo a esa sección o lote; si no, aplica a toda la finca.' + (sinSecciones ? ' Especificar sección está deshabilitado porque no hay secciones registradas.' : '') + (sinAnios ? ' Aún no hay años en la lista general: créelos primero en Precios por labor.' : '') },
                { id: 'llave_fija', element: '#llave_fija', side: lado, title: 'Datos fijos', text: 'Año, labor y ámbito del precio en edición. No se pueden modificar; para cambiarlos use Duplicar y elimine este registro.' },
                { id: 'ambito', element: '#gp_ambito', side: lado, title: 'A qué aplica', text: 'Resume a qué lotes aplica el precio. Debajo ve el orden en que la liquidación busca el precio: lote, sección, finca y, por último, la lista general; el nivel marcado es el que está definiendo.' },
                { id: 'labor', element: '#col_labor', side: lado, title: 'Labor', text: 'Busque y elija la labor. Al hacerlo verá el precio general del año como referencia.' },
                { id: 'referencia', element: '#panel_referencia', side: lado, title: 'Precio general', text: 'Muestra el precio general del año para la labor elegida. Copiar del general pasa esos valores al formulario para ajustarlos. Si aparece un aviso, el año o la labor no tienen precio general.' },
                { id: 'valores', element: () => $q('#formulario_precio .gp-seccion .bi-cash-coin')?.closest('.col-12'), side: lg ? 'right' : 'top', title: 'Valores', text: 'Indique precio destajo, contratistas, otros y porcentaje. Si la labor tiene precio general, debajo de cada campo verá ese valor y la diferencia: flecha verde si sube, roja si baja.' },
                { id: 'base_sueldo', element: () => $id('valor_baseSueldo')?.closest('.col-12'), side: lado, title: 'Base al sueldo', text: 'Actívelo para liquidar con el salario mínimo diario en lugar del destajo, o con el porcentaje del salario si es mayor que 0.' },
                { id: 'colision', element: '#aviso_colision', side: lado, title: 'Precio repetido', text: 'Ya existe un precio para esa labor, ámbito y año. Pulse Ir al registro existente para ubicarlo en el listado y editarlo.' },
                { id: 'guardar', element: () => $id('btn_guardar')?.parentElement, side: 'top', title: 'Guardar', text: editando ? 'Pulse Guardar cambios para actualizar el precio. Limpiar sale de la edición y deja el formulario en blanco.' : 'Pulse Guardar precio para registrarlo; si todos los valores están en cero, se le pedirá confirmar. Limpiar deja el formulario en blanco.' },
                { id: 'nuevo_precio', element: '#btn_nuevo_precio', side: md ? 'left' : 'bottom', title: 'Nuevo precio', text: 'Limpia el formulario para registrar un precio nuevo.' },
                { id: 'contador', element: '#contador_precios', side: 'bottom', title: 'Resultados', text: 'Indica cuántos precios coinciden con los filtros y cuántos hay de cada nivel: finca, sección y lote.' },
                { id: 'sin_precios', element: '#gp_estado_vacio', side: 'top', title: 'Sin precios por ámbito', text: 'Mientras no exista ninguno, todas las labores se liquidan con Precios por labor. Pulse Registrar el primero para empezar.' },
                { id: 'sin_filtro', element: '#gp_estado_filtro', side: 'top', title: 'Sin resultados', text: 'Ningún precio coincide con los filtros. Ajuste año, finca o búsqueda, o pulse Limpiar filtros.' },
                { id: 'buscar_tabla', element: '#tabla_precios_wrapper .gp-dt-barra:not(.gp-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Buscar en resultados filtra al instante lo listado, sin volver a consultar. También puede elegir cuántos registros ver por página.' },
                { id: 'excel', element: '#tabla_precios_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_precios_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'fila', element: '#cuerpo_tabla_precios tr', side: 'bottom', title: 'Precios registrados', text: 'Cada fila muestra año, labor, ámbito y valores. La barra de color a la izquierda y la etiqueta indican el nivel: gris para finca, azul para sección y verde para lote. Un destajo tachado no se usa porque Base sueldo está activo.' + (desliza('#tabla_precios_wrapper .table-responsive') ? ' Deslice la tabla hacia los lados para ver todas las columnas.' : ''), cuando: () => conFilas },
                { id: 'acciones', element: '#cuerpo_tabla_precios tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, duplicar o eliminar', text: 'Use el lápiz para cargar el precio en el formulario y editar sus valores, el botón de copiar para duplicarlo en otro ámbito y la papelera para eliminarlo; desde entonces esa labor se liquida con el precio general.', cuando: () => conFilas },
                { id: 'paginacion', element: '#tabla_precios_wrapper .gp-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.' },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
