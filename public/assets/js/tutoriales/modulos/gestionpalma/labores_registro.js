document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';
    const $id = (id) => document.getElementById(id);
    const desliza = (sel) => { const el = $q(sel); return Boolean(el) && el.scrollWidth > el.clientWidth; };

    window.Tutoriales?.registrar('gp-labores-registro', {
        titulo: 'Labores / Registro',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fila = $q('#cuerpo_tabla_labores tr');
            const conFilas = Boolean(fila) && !$q('#cuerpo_tabla_labores td.dataTables_empty');
            const usos = Number(fila?.dataset.usos || 0);
            const activa = fila?.dataset.activo === '1';

            return [
                { id: 'bienvenida', title: 'Labores / Registro', text: 'Aquí administra el catálogo de labores de la empresa: concepto de nómina, unidad, rendimiento y lo que maneja cada una. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => $id('filtro_empresa')?.closest('.col-md-2'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa. Al cambiarla, la página se recarga con sus labores.' },
                { id: 'grupo', element: () => $id('filtro_grupo')?.closest('.col-md-2'), side: 'bottom', title: 'Grupo', text: 'Muestre solo las labores de un grupo. La lista se actualiza al elegirlo.' },
                { id: 'estado', element: () => $id('filtro_estado')?.closest('.col-md-2'), side: 'bottom', title: 'Estado', text: 'Elija ver todas las labores, solo las activas o solo las inactivas.' },
                { id: 'busqueda', element: () => $id('filtro_busqueda')?.closest('.col-md-3'), side: 'bottom', title: 'Búsqueda', text: 'Escriba un código o parte de la descripción y pulse Buscar o Enter. La X borra la búsqueda y vuelve a listar.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nueva labor', text: 'Abre el formulario para crear una labor. Allí indica empresa, grupo, código y concepto; descripción y descripción corta; unidad de medida, clase y equivalencia; ciclos, rendimiento, signo y tipo de aplicación, y con los interruptores lo que maneja la labor (fecha, lote, palma, jornales, entre otros). Al elegir el grupo se propone el siguiente código libre (código del grupo más un consecutivo, por ejemplo 0312), marcado con una varita; puede cambiarlo y, si ya existe en otra labor, se le avisará al guardar. Si filtró un grupo, llega preseleccionado con su código propuesto. Pulse Guardar para registrarla.' },
                { id: 'contador', element: '#contador_labores', side: 'bottom', title: 'Resultados', text: 'Indica cuántas labores coinciden con los filtros.' },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin resultados', text: 'Ninguna labor coincide con los filtros. Ajuste grupo, estado o búsqueda, o pulse Nueva labor para registrar una.' },
                { id: 'buscar_tabla', element: '#tabla_labores_wrapper .gp-dt-barra:not(.gp-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Buscar en resultados filtra al instante lo que ya está listado, sin volver a consultar. También puede elegir cuántos registros ver por página.' },
                { id: 'excel', element: '#tabla_labores_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_labores_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'fila', element: '#cuerpo_tabla_labores tr', side: 'bottom', title: 'Labores', text: 'Cada fila muestra código, descripción (con la descripción corta debajo), grupo, concepto, unidad, ciclos, tarea (rendimiento) y signo. Uso indica si la labor ya tiene registros y Estado si está activa. Pulse un encabezado para ordenar.' + (desliza('#tabla_labores_wrapper .table-responsive') ? ' Deslice la tabla hacia los lados para ver todas las columnas.' : ''), cuando: () => conFilas },
                { id: 'acciones', element: '#cuerpo_tabla_labores tr .btn-group', side: md ? 'left' : 'top', title: 'Editar, activar o eliminar', text: `Use el lápiz para editar la labor (empresa, código y concepto no se pueden cambiar), el interruptor para ${activa ? 'inactivarla' : 'activarla'} y la papelera para eliminarla.` + (usos > 0 ? ' Esta labor está en uso: la papelera aparece atenuada porque no se puede eliminar, solo inactivar.' : ' Las labores en uso no se pueden eliminar; en ellas la papelera aparece atenuada.'), cuando: () => conFilas },
                { id: 'paginacion', element: '#tabla_labores_wrapper .gp-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.' },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
