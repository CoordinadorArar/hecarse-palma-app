document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);
    const DESLIZAR = ' Deslice la tabla hacia los lados para ver todas las columnas; las acciones quedan fijas a la derecha.';

    window.Tutoriales?.registrar('gp-lotes-registro', {
        titulo: 'Lotes / Registro',
        pasos: () => {
            const [md, lg] = [768, 992].map((px) => window.matchMedia(`(min-width: ${px}px)`).matches);
            const fino = window.matchMedia('(pointer: fine)').matches;
            const hayFilas = () => !$q('#tabla_lotes tbody td.dataTables_empty');
            const r = $q('#tabla_lotes_wrapper .table-responsive');
            const desborda = Boolean(r) && r.scrollWidth > r.clientWidth + 1;
            const bloqueada = Boolean($q('#cuerpo_tabla_lotes .btn-outline-danger[disabled]'));
            const fincaFiltrada = $id('filtro_finca')?.value !== '';
            const un = Boolean($id('filtro_unidad_negocio'));

            return [
                { id: 'bienvenida', title: 'Lotes', text: 'Aquí registra y mantiene los lotes de cada finca: variedad, siembra, palmas, líneas, hectáreas y canales de agua. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => $id('filtro_empresa')?.parentElement, side: 'bottom', title: 'Empresa', text: 'Elija la empresa. Al cambiarla, la página se recarga con sus fincas y lotes.' },
                { id: 'finca', element: () => $id('filtro_finca')?.parentElement, side: 'bottom', title: 'Finca', text: 'Muestre los lotes de una finca o de todas. La lista se actualiza al instante.' },
                { id: 'unidad', element: () => $id('filtro_unidad_negocio')?.parentElement, side: 'bottom', title: 'Unidad de negocio', text: 'Muestre solo los lotes de una unidad de negocio (por ejemplo Palma o Búfalos), o deje Todas. La lista se actualiza al instante.', cuando: () => un },
                { id: 'estado', element: () => $id('filtro_estado')?.parentElement, side: 'bottom', title: 'Estado', text: 'Muestre todos los lotes o solo los activos o inactivos.' },
                { id: 'descuadre', element: () => $id('filtro_descuadre')?.parentElement, side: 'bottom', title: 'Descuadre', text: 'Elija Con descuadre para ver solo los lotes cuyas palmas o líneas declaradas no coinciden con la suma de su detalle de líneas.' },
                { id: 'nuevo', element: '#btn_nuevo', side: lg ? 'left' : 'bottom', title: 'Nuevo', text: 'Abre el formulario del lote con su identificación' + (un ? ' (empresa, finca, unidad de negocio y código),' : ',') + ' variedad, centro de costo, año de siembra, sección (si la maneja), palmas y líneas, medidas, canales de agua y estado. Al indicar la distancia de siembra se calculan la densidad y las hectáreas netas; Administrar líneas se habilita después de guardar.' + (un ? ' La unidad de negocio solo ofrece las habilitadas en la finca elegida; si la finca no tiene ninguna, asígnelas primero en Unidades de negocio.' : '') + (fincaFiltrada ? ' La finca del filtro llega precargada.' : '') },
                { id: 'sin_resultados', element: '#gp_estado_vacio', side: 'top', title: 'Sin lotes', text: 'No hay lotes con los filtros elegidos. Ajuste la finca, ' + (un ? 'la unidad de negocio, ' : '') + 'el estado o el descuadre, o pulse Nuevo lote.' },
                { id: 'buscar', element: '#tabla_lotes_wrapper > .gp-dt-barra', side: 'bottom', title: 'Buscar y ordenar', text: 'Indica cuántos lotes se muestran. Escriba en Buscar para filtrar por cualquier dato visible y pulse el título de una columna para ordenar.' },
                { id: 'fila', element: '#tabla_lotes tbody tr', side: 'bottom', title: 'Datos del lote', text: 'Cada fila muestra el código (con número y letra), la descripción y el año de siembra, ' + (un ? 'la finca con su unidad de negocio y la sección' : 'la finca y la sección') + ', la variedad, las palmas brutas y de producción, las líneas, las hectáreas brutas y netas, y la densidad y distancia de siembra. En Estado, ACT y DES en verde indican lote activo y en desarrollo.' + (desborda ? DESLIZAR : ''), cuando: hayFilas },
                { id: 'marca_descuadre', element: '#cuerpo_tabla_lotes .gp-badge-cierre', side: 'bottom', title: 'Lote descuadrado', text: 'El triángulo indica que lo declarado en el lote no coincide con la suma de su detalle de líneas.' + (fino ? ' Pase el cursor sobre él para ver las cifras.' : '') + ' Revíselo con el botón de líneas de la fila.' },
                { id: 'sin_unidad', element: '#cuerpo_tabla_lotes .gp-chip-fila.ambar', side: 'bottom', title: 'Lote sin unidad de negocio', text: 'La etiqueta Sin unidad indica un lote que aún no tiene unidad de negocio. Edítelo y elija una para poder guardarlo.' },
                { id: 'acciones', element: '#cuerpo_tabla_lotes tr .btn-group', side: md ? 'left' : 'bottom', title: 'Acciones', text: 'Use el lápiz para editar el lote (' + (un ? 'la finca y el código no se pueden cambiar; la unidad de negocio sí, entre las habilitadas en la finca' : 'la finca y el código no se pueden cambiar') + '). El botón de lista abre sus líneas, donde agrega, corrige o elimina cada línea con sus palmas y erradicadas. El interruptor activa o inactiva el lote y la papelera lo elimina; ambos piden confirmación.' + (bloqueada ? ' La papelera atenuada indica que el lote tiene registros asociados y no se puede eliminar; en ese caso inactívelo.' : ''), cuando: hayFilas },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
