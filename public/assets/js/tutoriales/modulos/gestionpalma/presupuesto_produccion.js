document.addEventListener('DOMContentLoaded', () => {
    const $q = (s) => document.querySelector(s);
    const $id = (id) => document.getElementById(id);
    const desliza = (sel) => { const el = $q(sel); return Boolean(el) && el.scrollWidth > el.clientWidth; };
    const ajustar = (el) => (el && el.offsetHeight > window.innerHeight * 0.7 ? (el.querySelector('.card-header') || el.firstElementChild) : el);

    window.Tutoriales?.registrar('gp-presupuesto-produccion', {
        titulo: 'Presupuesto de producción',
        antesDeIniciar: () => ($q('#ppto_zona[aria-busy="true"], #ppto_metas[aria-busy="true"], #form_guardar .spinner-border, #meta_emp_guardar .spinner-border, #meta_finca_guardar .spinner-border, #meta_generar .spinner-border') ? 'Espere a que termine de cargar para iniciar el tutorial.' : null),
        pasos: () => {
            const finca = Boolean($id('filtro_finca')?.value);
            const abierto = Boolean($q('#metas_cuerpo.show'));
            const sucio = Boolean($q('#metas_sucio:not(.d-none)'));
            const notaFinca = $q('#meta_finca_nota:not(.d-none)');
            const motivo = ($id('meta_motivo')?.textContent.trim() || '').replace(/\.+$/, '');
            const editandoCelda = Boolean($q('#ppto_form.gp-ppto-form-editando'));
            const sinTabla = Boolean($q('#alerta_tabla:not(.d-none)'));
            const celda = $q('#cuerpo_ppto .gp-ppto-celda-btn:not(:disabled)');
            const bloqueadas = Boolean($q('#leyenda_bloqueada:not(.d-none)'));
            const elegirFinca = Boolean($q('#vacio_icono.bi-geo-alt'));

            return [
                { id: 'bienvenida', title: 'Presupuesto de producción', text: 'Aquí define el presupuesto de producción en kilos por lote y mes, que alimenta el informe Presupuesto vs Ejecutado. Puede generarlo desde una meta y una curva de estacionalidad o registrarlo celda por celda. Use Siguiente para recorrer las opciones principales.' },
                { id: 'alerta_tabla', element: '#alerta_tabla', side: 'bottom', title: 'Edición deshabilitada', text: 'La tabla de presupuesto no existe en la base de datos, por eso no se puede editar. Solicite al administrador ejecutar el script de creación.' },
                { id: 'filtros', element: () => $id('filtro_empresa')?.closest('.gp-filtros'), side: 'bottom', title: 'Filtros', text: 'Elija la empresa, el año y la finca; al cambiar cualquiera se consulta de inmediato y Consultar vuelve a cargar los datos. Si hay metas sin guardar, se le pedirá confirmar.' + (!finca ? ' Seleccione una finca para ver y registrar su presupuesto.' : '') },
                { id: 'kpis', element: () => $id('kpi_total')?.closest('.row'), side: 'bottom', title: 'Indicadores', text: 'Total presupuestado del año para la finca y cuánto representa de su meta, lotes activos con presupuesto y la comparación con el año anterior.' + (!finca ? ' Se llenan al elegir una finca.' : '') },
                { id: 'metas', element: '#ppto_metas > .card-header', side: 'bottom', title: 'Meta y estacionalidad', text: (abierto ? 'Aquí define la meta anual de la empresa y de la finca, y la curva de estacionalidad. El resumen y la etiqueta indican la meta vigente y si la curva está completa. Use la flecha para plegar la sección.' : 'Pulse la flecha para desplegar la meta de la empresa, la meta de la finca y la curva de estacionalidad. Se pliega sola cuando la finca ya tiene meta y curva completas.') + (sucio ? ' La etiqueta ámbar indica cambios sin guardar.' : '') },
                { id: 'metas_sin_tablas', element: '#metas_sin_tablas', side: 'bottom', title: 'Faltan tablas de metas', text: 'Las tablas de metas y estacionalidad no existen. Pulse Copiar ruta y entregue el script al administrador.' },
                { id: 'metas_error', element: '#metas_error', side: 'bottom', title: 'Metas no disponibles', text: 'No se pudieron cargar las metas. Pulse Reintentar.' },
                { id: 'meta_empresa', element: '#metas_emp_campos', side: 'bottom', title: 'Meta de la empresa', text: 'Indique la meta en t/ha o los kilos anuales totales y pulse Guardar meta empresa. Al lado verá las hectáreas netas, la equivalencia en t/ha y cuánto suman las metas de las fincas.' },
                { id: 'meta_finca', element: () => notaFinca || $id('meta_finca_kilos')?.closest('.row'), side: 'bottom', title: 'Meta de la finca', text: notaFinca ? 'Seleccione una finca en los filtros para definir su meta y su curva.' : 'Indique la meta anual de la finca en kilos y, si quiere, la meta en t/ha. Si solo tiene t/ha, use Calcular kg desde t/ha.' },
                { id: 'curva', element: () => ajustar($q('#metas_finca_campos .gp-ppto-curva')), side: 'top', title: 'Curva de estacionalidad', text: 'Reparta el año en porcentajes por mes; la suma debe dar 100 %. La etiqueta del total indica cuánto falta o sobra, debajo de cada mes ve los kilos resultantes y la gráfica muestra la forma de la curva. Copiar curva de… trae la del año anterior o la de otra finca; Repartir uniforme la divide en partes iguales.', cuando: () => !notaFinca },
                { id: 'guardar_generar', element: () => $id('meta_finca_guardar')?.parentElement, side: 'top', title: 'Guardar y generar', text: 'Pulse Guardar meta y curva. Luego, Generar presupuesto por lotes reparte la meta entre los lotes activos según sus hectáreas netas y la curva; antes de guardar verá una previsualización con las celdas que se sobrescribirán y debe pulsar Confirmar y guardar.' + (motivo ? ` Ahora no está disponible: ${motivo.charAt(0).toLowerCase() + motivo.slice(1)}.` : ''), cuando: () => !notaFinca },
                { id: 'registro', element: '#ppto_form', side: 'bottom', title: editandoCelda ? 'Editar presupuesto' : 'Registrar presupuesto', text: editandoCelda ? 'Está editando una celda: cambie los kilos y pulse Actualizar. Guardar 0 o Eliminar borran el presupuesto de ese lote y mes; Cancelar edición vuelve al registro.' : 'Elija el mes y el lote, escriba los kilos y pulse Guardar (o Enter). Si el lote ya tiene valor en ese mes, se reemplaza. Los meses con meta de finca no se pueden elegir.' + (sinTabla ? ' Está deshabilitado porque la tabla de presupuesto no existe.' : (!finca ? ' Se habilita al seleccionar una finca.' : '')) },
                { id: 'cabecera_matriz', element: () => $id('contador_ppto')?.closest('.card-header'), side: 'bottom', title: 'Presupuesto por lote', text: 'Indica el año y cuántos lotes tiene la finca; los valores están en kilogramos. Las celdas rayadas no tienen presupuesto' + (bloqueadas ? '; las de rayas grises están bloqueadas por meta de finca.' : '.') },
                { id: 'nota_historico', element: '#nota_historico', side: 'bottom', title: 'Metas de finca (histórico)', text: 'La finca tiene metas a nivel de finca en los meses indicados, marcados con un candado en el encabezado. Mientras existan, esos meses no admiten presupuesto por lote.' },
                { id: 'nota_sin_datos', element: '#nota_sin_datos', side: 'bottom', title: 'Sin presupuesto', text: 'Aún no hay presupuesto para este año. Use el formulario, pulse una celda o genérelo desde la meta y la curva.' },
                { id: 'vacio', element: '#gp_estado_vacio', side: 'top', title: elegirFinca ? 'Seleccione una finca' : 'Finca sin lotes', text: elegirFinca ? 'Elija empresa, año y finca para ver y registrar el presupuesto por lote.' : 'La finca no tiene lotes registrados. Regístrelos en Gestión Palma › Lotes para poder presupuestar.' },
                { id: 'error', element: '#gp_estado_error', side: 'top', title: 'No se pudo cargar', text: 'Ocurrió un problema al consultar el presupuesto. Pulse Reintentar.' },
                { id: 'historico_fila', element: '#cuerpo_ppto tr.gp-ppto-historico', side: 'bottom', title: 'Fila de meta de finca', text: $q('#cuerpo_ppto tr.gp-ppto-historico [data-eliminar]') ? 'Muestra las metas de finca por mes. Use la papelera de un mes para eliminarla y habilitarlo para cargar por lote.' : 'Muestra las metas de finca por mes.' },
                { id: 'celda', element: () => celda?.closest('td'), side: 'bottom', title: 'Celdas', text: 'Pulse una celda para llevarla al formulario: si está vacía registra un valor nuevo y, si tiene valor, lo edita. El mes en curso se subraya en verde en el encabezado.' + (desliza('#ppto_contenido .table-responsive') ? ' Deslice la tabla hacia los lados para ver todos los meses; la columna Lote queda fija.' : ''), cuando: () => Boolean(celda) },
                { id: 'inactivo', element: '#cuerpo_ppto tr.gp-ppto-inactivo > th', side: 'right', title: 'Lotes inactivos', text: 'Los lotes inactivos no se pueden editar; si tienen presupuesto, use la papelera de la celda para eliminarlo.' },
                { id: 'totales', element: '#pie_ppto tr', side: 'top', title: 'Totales', text: 'Total mes suma cada mes de todos los lotes, incluida la meta de finca si existe. La columna Total muestra el total anual de cada lote y, abajo, el total de la finca.' },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
