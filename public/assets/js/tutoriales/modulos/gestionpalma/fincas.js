document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const DESLIZAR = ' Deslice la tabla hacia los lados para ver todas las columnas; las acciones quedan fijas a la derecha.';
    const EXCEL = 'Descarga en Excel lo que está listado, de todas las páginas y respetando lo que haya escrito en Buscar. El archivo anota en el encabezado los filtros y la búsqueda usados, y no incluye la columna de acciones.';

    window.Tutoriales?.registrar('gp-fincas', {
        titulo: 'Fincas',
        pasos: () => {
            const [md, lg] = [768, 992].map((px) => window.matchMedia(`(min-width: ${px}px)`).matches);
            const hayFilas = () => !$q('#tabla_fincas tbody td.dataTables_empty');
            const r = $q('#tabla_fincas_wrapper .table-responsive');
            const desborda = Boolean(r) && r.scrollWidth > r.clientWidth + 1;
            const bloqueada = Boolean($q('#cuerpo_tabla_fincas .btn-outline-danger[disabled]'));
            const un = Boolean($q('#cuerpo_tabla_fincas .gp-chip-fila'));

            return [
                { id: 'bienvenida', title: 'Fincas', text: 'Aquí consulta y administra las fincas de cada empresa: sus datos, propietario, ubicación y estado. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => $id('filtro_empresa')?.parentElement, side: 'bottom', title: 'Empresa', text: 'Elija la empresa cuyas fincas desea ver. Al cambiarla, la página se recarga con sus fincas.' },
                { id: 'estado', element: () => $id('filtro_estado')?.parentElement, side: 'bottom', title: 'Estado', text: 'Muestre todas las fincas o solo las activas o inactivas. La lista se actualiza al instante.' },
                { id: 'nuevo', element: '#btn_nuevo', side: lg ? 'left' : 'bottom', title: 'Nuevo', text: 'Abre el formulario para registrar una finca: código, descripción y código de equivalencia; propietario y hectáreas; ciudad (escriba al menos 2 letras y elíjala de la lista), zona y centro de operación; y si es activa, interna o de socio.' },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin fincas', text: 'No hay fincas con el filtro elegido. Cambie el estado o pulse Nueva finca para registrar una.' },
                { id: 'buscar', element: '#tabla_fincas_wrapper > .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Indica cuántas fincas se muestran. Escriba en Buscar para filtrar por cualquier dato visible y pulse el título de una columna para ordenar.' },
                { id: 'excel', element: '#tabla_fincas_wrapper .gp-dt-excel', side: 'bottom', title: 'Exportar a Excel', text: EXCEL + ($q('#tabla_fincas_wrapper .gp-dt-excel .btn')?.matches(':disabled, .disabled') ? ' Se habilita cuando la lista tiene registros.' : '') },
                { id: 'fila', element: '#tabla_fincas tbody tr', side: 'bottom', title: 'Datos de la finca', text: 'Cada fila muestra código, nombre (con su código de equivalencia), propietario, ciudad y zona, hectáreas y centro de operación (CO).' + (un ? ' Unidades de negocio muestra las unidades que se manejan en la finca.' : '') + ' Uso indica cuántos registros dependen de la finca, y en Marcas se resaltan en verde INT (interna) y SOC (socio).' + (desborda ? DESLIZAR : ''), cuando: hayFilas },
                { id: 'unidades', element: '#cuerpo_tabla_fincas .gp-chip-fila', side: 'bottom', title: 'Unidades de negocio', text: 'Las etiquetas muestran las unidades de negocio que se manejan en la finca; Sin unidades, en ámbar, indica que aún no tiene ninguna y no se podrán registrar lotes en ella. Se asignan en Gestión Palma › Unidades de negocio.', cuando: () => un },
                { id: 'acciones', element: '#cuerpo_tabla_fincas tr .btn-group', side: md ? 'left' : 'bottom', title: 'Acciones', text: 'Use el lápiz para editar la finca (la empresa y el código no se pueden cambiar), el interruptor para activarla o inactivarla y la papelera para eliminarla. Activar, inactivar y eliminar piden confirmación.' + (bloqueada ? ' La papelera atenuada indica que la finca tiene registros asociados (columna Uso) y no se puede eliminar; en ese caso inactívela.' : ''), cuando: hayFilas },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
