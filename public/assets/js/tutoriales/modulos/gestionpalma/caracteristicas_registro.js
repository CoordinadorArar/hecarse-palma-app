document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const DESLIZAR = ' Deslice la tabla hacia los lados para ver todas las columnas; las acciones quedan fijas a la derecha.';

    window.Tutoriales?.registrar('gp-caracteristicas-registro', {
        titulo: 'Características / Registro',
        pasos: () => {
            const [md, lg] = [768, 992].map((px) => window.matchMedia(`(min-width: ${px}px)`).matches);
            const fino = window.matchMedia('(pointer: fine)').matches;
            const hayFilas = () => !$q('#tabla_caracteristicas tbody td.dataTables_empty');
            const r = $q('#tabla_caracteristicas_wrapper .table-responsive');
            const desborda = Boolean(r) && r.scrollWidth > r.clientWidth + 1;
            const bloqueada = Boolean($q('#cuerpo_tabla_caracteristicas .btn-outline-danger[disabled]'));
            const grupoFiltrado = $id('filtro_grupo')?.value !== '';

            return [
                { id: 'bienvenida', title: 'Características', text: 'Aquí administra las características sanitarias (plagas, enfermedades o estados de la palma) que se usan en censos y tratamientos, organizadas por grupo. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => $id('filtro_empresa')?.parentElement, side: 'bottom', title: 'Empresa', text: 'Elija la empresa. Al cambiarla, la página se recarga con sus características.' },
                { id: 'grupo', element: () => $id('filtro_grupo')?.parentElement, side: 'bottom', title: 'Grupo', text: 'Muestre las características de un grupo o de todos. La lista se actualiza al instante.' },
                { id: 'estado', element: () => $id('filtro_estado')?.parentElement, side: 'bottom', title: 'Estado', text: 'Muestre todas las características o solo las activas o inactivas. La lista se actualiza al instante.' },
                { id: 'busqueda', element: () => $id('filtro_busqueda')?.closest('.col-md-4'), side: 'bottom', title: 'Búsqueda', text: 'Escriba parte del código o de la descripción y pulse Buscar o Enter. La X limpia la búsqueda y vuelve a mostrar todo.' },
                { id: 'nuevo', element: '#btn_nuevo', side: lg ? 'left' : 'bottom', title: 'Nueva característica', text: 'Abre el formulario para registrarla: el código (se sugiere el siguiente consecutivo y puede cambiarlo), la descripción de la plaga, enfermedad o estado de la palma, y su grupo característico. Sin grupo no se ofrece en censos ni tratamientos de campo. Si la descripción ya existe en ese grupo, se le avisa antes de guardar.' + (grupoFiltrado ? ' El grupo del filtro llega preseleccionado.' : '') },
                { id: 'resultados', element: () => $id('contador_caracteristicas')?.parentElement, side: 'bottom', title: 'Resultados', text: 'Indica cuántas características cumplen los filtros. Una misma característica puede repetirse en varios grupos: censarla y tratarla son registros distintos.' },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin características', text: 'No hay características con los filtros elegidos. Ajuste el grupo, el estado o la búsqueda, o pulse Nueva característica.' },
                { id: 'buscar_resultados', element: '#tabla_caracteristicas_wrapper > .gp-dt-barra', side: 'bottom', title: 'Buscar en resultados', text: 'Filtra al instante lo que ya está listado, sin volver a consultar. Pulse el título de una columna para ordenar.' },
                { id: 'fila', element: '#tabla_caracteristicas tbody tr', side: 'bottom', title: 'Datos de la característica', text: 'Cada fila muestra el código, la descripción, el grupo (o Sin grupo), si está En uso en censos o tratamientos y su estado.' + (desborda ? DESLIZAR : ''), cuando: hayFilas },
                { id: 'variante', element: '#cuerpo_tabla_caracteristicas .gp-marca-variante', side: 'bottom', title: 'Repetida en otros grupos', text: 'Este ícono indica que la misma descripción también está registrada en otros grupos.' + (fino ? ' Pase el cursor sobre él para ver cuáles.' : '') },
                { id: 'acciones', element: '#cuerpo_tabla_caracteristicas tr .btn-group', side: md ? 'left' : 'bottom', title: 'Acciones', text: 'Use el lápiz para editarla (la empresa y el código no se pueden cambiar), el interruptor para activarla o inactivarla y la papelera para eliminarla. Activar, inactivar y eliminar piden confirmación.' + (bloqueada ? ' La papelera atenuada indica que la característica está En uso y no se puede eliminar; en ese caso inactívela.' : ''), cuando: hayFilas },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
