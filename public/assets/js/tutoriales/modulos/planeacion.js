document.addEventListener('DOMContentLoaded', () => window.Tutoriales?.registrar('planeacion', {
    titulo: 'Planeación de labores',
    antesDeIniciar: () => (document.querySelector('#cargando_plan:not(.d-none), #kpis:not(.d-none) .placeholder, #estado_plan:not(.d-none) .placeholder') || (document.querySelector('#barra_plan.d-none') && !document.querySelector('#estado_plan [data-accion="reintentar"]'))
        ? 'Espere a que termine de cargar el plan para iniciar el tutorial.' : null),
    pasos: () => {
        const md = window.matchMedia('(min-width: 768px)').matches;
        const lg = window.matchMedia('(min-width: 992px)').matches;
        const botonera = document.querySelector('#vista_lote .pl-actividad .pl-botonera');
        const porRegistrar = document.querySelector('#vista_lote [data-chip="por-registrar"]');
        const cerrar = document.getElementById('btn_cerrar_plan');
        const kpis = document.getElementById('kpis');

        return [
            { id: 'bienvenida', title: 'Planeación de labores', text: 'Aquí organiza las actividades del día por lote y trabajador, registra su cierre y controla el avance del plan. Use Siguiente para recorrer las opciones principales.' },
            { id: 'empresa', element: () => document.getElementById('filtro_empresa')?.closest('.input-group'), side: 'bottom', title: 'Empresa', text: 'Seleccione la empresa cuyo plan desea consultar o planear.' },
            { id: 'fecha', element: '#grupo_fecha', side: 'bottom', title: 'Fecha del plan', text: 'Elija la fecha o use las flechas para ir al día anterior o al siguiente.' },
            { id: 'vista', element: '#pestanas_vista', side: lg ? 'left' : 'bottom', title: 'Vista del plan', text: 'Alterne entre ver las actividades agrupadas por lote o el resumen por trabajador.' },
            { id: 'kpis', element: '#kpis', side: 'bottom', title: 'Indicadores del día', text: 'Muestran actividades, trabajadores, ausentes y avance. Pulse Ausentes, Pendientes o Completadas para filtrar la lista.', cuando: () => Boolean(kpis?.children.length) && !kpis.querySelector('.placeholder') },
            { id: 'agregar', element: '#btn_agregar', side: 'bottom', title: 'Agregar actividad', text: 'Abre el formulario para planear una labor en un lote y asignar sus trabajadores.' },
            { id: 'planilla', element: '#btn_planilla', side: 'bottom', title: 'Planear finca', text: 'Abre una planilla para cargar varias actividades de una misma finca de una sola vez.' },
            { id: 'sugerencias', element: '#btn_sugerencias', side: 'bottom', title: 'Sugerencias', text: 'Revise las labores sugeridas según el programa y apruébelas o rechácelas; también puede consultar el historial.', cuando: () => md },
            { id: 'pendientes', element: '#btn_pendientes', side: 'bottom', title: 'Pendientes', text: 'Consulte las actividades con saldo de días anteriores y trasládelas a este plan.', cuando: () => md },
            { id: 'ausentes', element: '#btn_ausentes', side: 'bottom', title: 'Ausentes', text: 'Registre a los trabajadores ausentes y reasigne sus cantidades. Con Duplicar plan copia las actividades de todas las fincas o de una sola a otra fecha, y con Exportar a Excel descarga la planilla del día.', cuando: () => md && !document.getElementById('btn_ausentes')?.disabled },
            { id: 'mas_opciones', element: '#btn_mas_opciones', side: 'bottom', title: 'Más opciones', text: 'Aquí encuentra Sugerencias, Pendientes, Ausentes, Duplicar plan y Exportar a Excel.', cuando: () => !md },
            { id: 'cierre_actividad', element: () => botonera || porRegistrar, side: 'top', title: 'Cerrar la actividad', text: botonera ? 'Marque cada actividad como Completada, Parcial o No ejecutada al terminar la jornada. Luego regístrela desde Labores, Fertilización o Producción › Planeaciones completadas. Si la labor se hizo en más de un lote, use Se ejecutó en varios lotes.' : 'Las actividades cerradas quedan Por registrar: regístrelas desde Labores, Fertilización o Producción › Planeaciones completadas.' },
            { id: 'varios_lotes', element: '#vista_lote .pl-actividad [data-accion="varios-lotes"]', side: 'top', title: 'Ejecutada en varios lotes', text: 'Distribuya lo que ejecutó cada trabajador en el lote planeado y en otros lotes de la finca. El lote planeado queda Completado o Parcial y se crea una actividad completada por cada lote adicional.' },
            { id: 'division', element: '#vista_lote [data-chip="division"]', side: 'bottom', title: 'Actividad dividida', text: 'Este chip relaciona la actividad planeada con las creadas en otros lotes. Púlselo para resaltarlas; al reabrir cualquiera de ellas se reabre todo el bloque.' },
            { id: 'kebab', element: '#vista_lote .pl-actividad .pl-kebab > button', side: md ? 'left' : 'bottom', title: 'Más acciones', text: 'Desde este menú puede editar, duplicar, reabrir o eliminar la actividad, según su estado.' },
            { id: 'cerrar_plan', element: () => cerrar || document.getElementById('btn_reabrir_plan'), side: 'top', title: 'Cerrar el plan', text: cerrar ? 'Cuando todas las actividades estén cerradas, cierre el plan para bloquear cambios. El indicador junto a la fecha muestra si está Abierto o Cerrado.' : 'El plan está cerrado; si necesita corregirlo, use Reabrir plan.' },
            { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
        ];
    }
}));
