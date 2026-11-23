document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';
    const campo = (id) => document.getElementById(id)?.parentElement;
    const busqueda = () => document.getElementById('filtro_busqueda')?.closest('.input-group')?.parentElement;
    const legado = () => $q('#cuerpo_tabla_empleados .gp-dato-legado')?.closest('td');

    window.Tutoriales?.registrar('gp-nomina-empleados', {
        titulo: 'Empleados',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fino = window.matchMedia('(pointer: fine)').matches;
            const fila = $q('#cuerpo_tabla_empleados tr');
            const conDatos = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const resp = $q('#tabla_empleados_wrapper .table-responsive');
            const desborda = Boolean(resp) && resp.scrollWidth > resp.clientWidth;
            const estado = () => (conDatos ? fila.querySelector('.gp-badge-abierto, .gp-badge-cerrado') : null);
            const acciones = () => (conDatos ? fila.querySelector('.btn-group') : null);
            const deslizar = desborda ? ' La tabla es ancha: deslícela hacia los lados para ver todas las columnas. La columna Acciones queda fija a la derecha.' : '';

            return [
                { id: 'bienvenida', title: 'Empleados', text: 'Aquí administra los empleados de cada empresa: el tercero al que corresponden, el salario, la fecha de ingreso, el proveedor y la clasificación. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => campo('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa. Al cambiarla, la página se recarga con sus empleados.' },
                { id: 'estado_filtro', element: () => campo('filtro_estado'), side: 'bottom', title: 'Estado', text: 'Por defecto se listan los activos. Elija Inactivos o Todos para ver los demás. La lista se actualiza al instante.' },
                { id: 'vinculo', element: () => campo('filtro_vinculo'), side: 'bottom', title: 'Vínculo', text: 'Muestre solo contratistas, conductores u ocasionales. La lista se actualiza al instante.' },
                { id: 'busqueda', element: busqueda, side: 'bottom', title: 'Búsqueda', text: 'Escriba el código, el número de tercero o el nombre y pulse Enter o Buscar. La X borra la búsqueda.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo empleado', text: 'Abre el formulario para registrar un empleado. Escoja en la lista un tercero activo que aún no sea empleado (su identificación y su nombre se toman de él) o active Crear un tercero nuevo para registrarlo ahí mismo. Luego indique el proveedor, el salario, la fecha de ingreso y la clasificación: Activo con contrato, Contratista, Conductor interno u Ocasional. Pulse Guardar.' },
                { id: 'contador', element: '#contador_empleados', side: 'bottom', title: 'Resultados', text: 'Indica cuántos empleados coinciden con los filtros.' },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin empleados', text: 'Ningún empleado coincide con los filtros. Ajústelos o pulse Nuevo empleado para registrarlo.' },
                { id: 'buscar_resultados', element: '#tabla_empleados_wrapper .gp-dt-barra:not(.gp-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Use Buscar en resultados para filtrar lo que ya está listado sin volver a consultar, y elija cuántos registros ver por página. Pulse el título de una columna para ordenar.' + deslizar },
                { id: 'excel', element: '#tabla_empleados_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_empleados_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'fila', element: () => (conDatos ? (desborda ? fila.cells[1] : fila) : null), side: 'bottom', title: 'Empleados listados', text: 'Cada fila muestra el código, el nombre con su número de tercero, la fecha de ingreso, el salario y el proveedor.', cuando: () => conDatos },
                { id: 'legado', element: legado, side: 'bottom', title: 'Código heredado', text: 'El ícono de información indica un código de proveedor que viene del sistema anterior. Edite el empleado y escoja el proveedor en la lista para corregirlo.' },
                { id: 'vinculo_fila', element: () => (conDatos ? fila.cells[5] : null), side: 'bottom', title: 'Vínculo', text: 'CTA indica contratista, CON conductor interno y OCA ocasional: en verde si aplica y en gris si no.' + (fino ? ' Pase el cursor sobre una etiqueta para ver su significado.' : ''), cuando: () => conDatos },
                { id: 'estado', element: estado, side: md ? 'left' : 'bottom', title: 'Estado', text: 'Activo indica que el empleado está marcado como Activo con contrato. Inactivo indica que no.', cuando: () => conDatos },
                { id: 'acciones', element: acciones, side: md ? 'left' : 'bottom', title: 'Editar o eliminar', text: 'Use el lápiz para editar el proveedor, el salario, la fecha de ingreso y la clasificación. La empresa y el tercero no cambian; la identificación y el nombre se corrigen en Terceros, con el enlace Abrir Terceros del formulario. La papelera elimina el empleado con confirmación, pero no es posible si tiene contratos o movimientos de nómina.', cuando: () => conDatos },
                { id: 'paginacion', element: '#tabla_empleados_wrapper .gp-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.' },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
