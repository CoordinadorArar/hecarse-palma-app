document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const campo = (id) => document.getElementById(id)?.parentElement;
    const busqueda = () => document.getElementById('filtro_busqueda')?.closest('.input-group')?.parentElement;

    window.Tutoriales?.registrar('gp-terceros', {
        titulo: 'Terceros',
        pasos: () => {
            const md = window.matchMedia('(min-width: 768px)').matches;
            const fino = window.matchMedia('(pointer: fine)').matches;
            const fila = $q('#cuerpo_tabla_terceros tr');
            const conDatos = Boolean(fila) && !fila.querySelector('td.dataTables_empty');
            const resp = $q('#tabla_terceros_wrapper .table-responsive');
            const desborda = Boolean(resp) && resp.scrollWidth > resp.clientWidth;
            const estado = () => (conDatos ? fila.querySelector('.gp-badge-abierto, .gp-badge-cerrado') : null);
            const acciones = () => (conDatos ? fila.querySelector('.btn-group') : null);
            const deslizar = desborda ? ' Deslice la tabla hacia los lados para ver todas las columnas.' : '';

            return [
                { id: 'bienvenida', title: 'Terceros', text: 'Aquí administra los terceros de cada empresa (clientes, proveedores, empleados y demás): los busca, crea, edita, desactiva o elimina. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => campo('filtro_empresa'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa. Al cambiarla, la página se recarga con sus terceros.' },
                { id: 'tipo', element: () => campo('filtro_tipo'), side: 'bottom', title: 'Tipo', text: 'Filtre por persona natural o jurídica.' },
                { id: 'rol', element: () => campo('filtro_rol'), side: 'bottom', title: 'Rol', text: 'Muestre solo los terceros con un rol: clientes, proveedores, empleados, accionistas, contratistas, extractoras o comercializadoras.' },
                { id: 'estado_filtro', element: () => campo('filtro_estado'), side: 'bottom', title: 'Estado', text: 'Por defecto se listan los activos. Elija Inactivos o Todos para ver los demás. La lista se actualiza al instante al cambiar Tipo, Rol o Estado.' },
                { id: 'busqueda', element: busqueda, side: 'bottom', title: 'Búsqueda', text: 'Escriba el Nit, el código, el nombre o la razón social y pulse Enter o Buscar. La X borra la búsqueda.' },
                { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo tercero', text: 'Abre el formulario para crear un tercero en la empresa elegida. Indique el tipo de persona, el tipo de documento y el Nit (el dígito de verificación se calcula solo cuando es NIT), los nombres o la razón social y los roles; la ubicación y el contacto son opcionales. El código y la descripción se completan solos y el consecutivo lo asigna el sistema. Pulse Guardar.' },
                { id: 'contador', element: '#contador_terceros', side: 'bottom', title: 'Resultados', text: 'Indica cuántos terceros coinciden con los filtros.' },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin terceros', text: 'Ningún tercero coincide con los filtros. Ajústelos o pulse Nuevo tercero para crearlo.' },
                { id: 'buscar_resultados', element: '#tabla_terceros_wrapper .gp-dt-barra:not(.gp-dt-pie)', side: 'bottom', title: 'Buscar y mostrar', text: 'Use Buscar en resultados para filtrar lo que ya está listado sin volver a consultar, y elija cuántos registros ver por página. Pulse el título de una columna para ordenar.' + deslizar },
                { id: 'fila', element: () => (conDatos ? (desborda ? fila.cells[2] : fila) : null), side: 'bottom', title: 'Terceros listados', text: 'Cada fila muestra el código, el tipo y número de documento, la descripción con su ciudad y si es persona natural o jurídica.', cuando: () => conDatos },
                { id: 'roles', element: () => $q('#cuerpo_tabla_terceros .gp-chip-rol')?.closest('td'), side: 'bottom', title: 'Roles', text: 'CLI (cliente), PRO (proveedor) y EMPL (empleado) se muestran siempre: en verde si el tercero tiene el rol y en gris si no. ACC, CON, EXT y COM (accionista, contratista, extractora y comercializadora) solo aparecen si los tiene.' + (fino ? ' Pase el cursor sobre una etiqueta para ver su nombre.' : ''), cuando: () => conDatos },
                { id: 'estado', element: estado, side: md ? 'left' : 'bottom', title: 'Estado', text: 'Activo indica que el tercero está en uso. Los inactivos no aparecen para escoger en otras pantallas, como Empleados.', cuando: () => conDatos },
                { id: 'acciones', element: acciones, side: md ? 'left' : 'bottom', title: 'Editar o eliminar', text: 'Use el lápiz para editar los datos del tercero (la empresa y el consecutivo no cambian) y la papelera para eliminarlo, con confirmación. Si el tercero se usa en Nómina o Agronomía no se puede eliminar; en ese caso el sistema le ofrece Desactivar tercero.', cuando: () => conDatos },
                { id: 'paginacion', element: '#tabla_terceros_wrapper .gp-dt-pie', side: 'top', title: 'Páginas', text: 'Aquí ve qué registros se están mostrando y puede pasar de una página a otra.' },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
