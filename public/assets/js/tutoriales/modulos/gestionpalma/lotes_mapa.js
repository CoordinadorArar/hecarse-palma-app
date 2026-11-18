document.addEventListener('DOMContentLoaded', () => {
    const $q = (selector) => document.querySelector(selector);
    const $id = (id) => document.getElementById(id);

    window.Tutoriales?.registrar('gp-lotes-mapa', {
        titulo: 'Lotes / Mapeo de palmas',
        antesDeIniciar: () => ($q('.gp-mapa-card.gp-pantalla-completa') ? 'Salga de pantalla completa para iniciar el tutorial.'
            : $q('#mapa_cargando:not(.d-none), #historial .spinner-border') ? 'Espere a que termine de cargar el lote para iniciar el tutorial.'
                : $q('#banner_modo:not(.d-none)') ? 'Termine o cancele la acción en curso sobre el mapa para iniciar el tutorial.'
                    : null),
        pasos: () => {
            const lg = window.matchMedia('(min-width: 992px)').matches;
            const fino = window.matchMedia('(pointer: fine)').matches;
            const toque = fino ? 'Pulse' : 'Toque';
            const toqueMin = toque.toLowerCase();
            const sinLote = !$id('lote')?.value;
            const cargado = !$id('kpis')?.classList.contains('d-none');
            const ficha = Boolean($q('#panel_contenido .gp-mapa-acciones'));
            const form = $q('#panel_contenido form');
            const abierto = lg || !$q('#panel_mapa.colapsado');
            const deshab = Boolean($q('[data-modo="agregar"]')?.disabled);
            const dibujar = $id('texto_poligono')?.textContent.trim() === 'Dibujar polígono';
            const aviso = $q('#panel_contenido .gp-nota-alerta');
            const ladoPanel = lg ? 'left' : 'top';
            const textoMapa = cargado
                ? `Cada punto es una palma, coloreada según su estado sanitario; el borde ámbar punteado marca las que están fuera del polígono. ${toque} una palma para ver su ficha o el contorno de otro lote para abrirlo.` + (fino ? ' Acerque con la rueda del ratón.' : ' Acerque con dos dedos.')
                : `Muestra la finca con el contorno y el código de cada lote. ${toque} un lote para abrirlo y ver sus palmas.` + (fino ? ' Acerque con la rueda del ratón.' : ' Acerque con dos dedos.');
            const textoPanel = (sinLote ? 'Aquí verá la información del lote que elija y la ficha de cada palma que seleccione.'
                : form ? 'Muestra el formulario abierto para la palma seleccionada.'
                    : ficha ? 'Muestra la ficha de la palma seleccionada: estado sanitario, línea, número, coordenadas y último censo. Use la X para cerrarla y volver al resumen del lote.'
                        : 'Muestra el código, la descripción y el área del lote, y la leyenda de estados sanitarios.') + (!lg ? ' Toque la barra superior del panel para desplegarlo u ocultarlo.' : '');
            const censo = form?.id === 'form_censo';

            return [
                { id: 'bienvenida', title: 'Mapeo de palmas', text: 'Aquí ubica las palmas de cada lote sobre el mapa satelital, revisa su estado sanitario, registra censos y dibuja el contorno del lote. Use Siguiente para recorrer las opciones principales.' },
                { id: 'empresa', element: () => $id('empresa')?.parentElement, side: 'bottom', title: 'Empresa', text: 'Elija la empresa; se cargan sus fincas.' },
                { id: 'finca', element: () => $id('finca')?.parentElement, side: 'bottom', title: 'Finca', text: 'Elija la finca: el mapa se centra en ella y muestra el contorno de sus lotes.' },
                { id: 'lote', element: () => $id('lote')?.closest('.col-md-3'), side: 'bottom', title: 'Lote', text: `Elija el lote (puede escribir para buscarlo) o ${toqueMin} su contorno en el mapa. Se cargan sus palmas sobre el mapa.` },
                { id: 'buscar_palma', element: () => $id('form_buscar')?.parentElement, side: 'bottom', title: 'Buscar palma', text: 'Escriba la línea y la palma separadas por punto (ej. 12.34) y pulse la flecha o Enter para ubicarla y abrir su ficha.' + (sinLote ? ' Se habilita al cargar un lote.' : '') },
                { id: 'kpis', element: '#kpis', side: 'bottom', title: 'Resumen del lote', text: 'Palmas activas frente a las palmas brutas del lote, con su avance y el aviso de descuadre si no coinciden; además, erradicadas, con censo, sin censo y fuera del polígono.' },
                { id: 'fuera', element: '[data-accion="ver-fuera"]', side: 'bottom', title: 'Palmas fuera del polígono', text: `Hay palmas ubicadas fuera del contorno del lote. ${toque} esta tarjeta para resaltarlas y encuadrarlas; vuelva a ${fino ? 'pulsarla' : 'tocarla'} para quitar el resaltado.` },
                { id: 'modos', element: '.gp-mapa-barra [role="group"]', side: 'bottom', title: 'Modo de trabajo', text: `Seleccionar permite ${fino ? 'pulsar' : 'tocar'} palmas y lotes. Agregar palmas ubica palmas nuevas por línea o por coordenadas.` + (dibujar ? ' Dibujar polígono traza el contorno del lote.' : ' Polígono permite ajustar el contorno del lote.') + ' Mover aún no está disponible.' + (deshab ? ' Se habilitan al cargar un lote.' : '') },
                { id: 'vista', element: '.gp-mapa-barra > .ms-auto', side: 'bottom', title: 'Erradicadas y encuadre', text: 'Erradicadas muestra u oculta las palmas erradicadas. Encuadrar lote vuelve a centrar el mapa en el lote.' },
                { id: 'mapa', element: '#mapa_lote', side: 'bottom', title: 'Mapa', text: textoMapa },
                { id: 'capas', element: '#mapa_lote .leaflet-control-layers', side: 'left', title: 'Tipo de mapa', text: 'Cambie entre la vista Satélite y la de Calles.' },
                { id: 'pantalla', element: '#mapa_lote .gp-control-pantalla', side: 'right', title: 'Pantalla completa', text: 'Amplía el mapa y el panel a toda la pantalla. Púlselo de nuevo o use Esc para salir.' },
                { id: 'mapa_error', element: '#mapa_error', side: 'bottom', title: 'No se pudo cargar el lote', text: 'Ocurrió un problema al cargar las palmas. Pulse Reintentar para intentarlo de nuevo.' },
                { id: 'panel', element: '#panel_mapa', side: ladoPanel, title: 'Panel del lote', text: textoPanel },
                { id: 'aviso_lote', element: () => aviso, side: ladoPanel, title: 'Aviso del lote', text: aviso?.textContent.includes('no tiene polígono') ? 'Este lote no tiene polígono. Use Dibujar polígono, en la barra del mapa, para delimitarlo; así se detectan las palmas fuera del lote.' : 'Algunas palmas quedaron fuera del polígono guardado. Pulse Ver palmas para resaltarlas en el mapa.', cuando: () => abierto },
                { id: 'empezar', element: () => $q('#panel_contenido [data-accion="modo"]')?.parentElement, side: ladoPanel, title: 'Empezar a mapear', text: 'Este lote aún no tiene palmas en el mapa. Use Agregar palmas para ubicarlas por línea o por coordenadas' + ($q('#panel_contenido [data-valor="poligono"]') ? ', y Dibujar polígono para trazar el contorno del lote.' : '.'), cuando: () => abierto },
                { id: 'leyenda', element: () => $q('#panel_contenido .gp-mapa-leyenda')?.closest('.gp-mapa-panel-cuerpo'), side: ladoPanel, title: 'Estado sanitario', text: 'Cada color corresponde a un estado sanitario y el número indica cuántas palmas lo tienen. Desmarque un estado para ocultar esas palmas; Todas y Ninguna las muestran u ocultan a la vez.', cuando: () => abierto },
                { id: 'acciones_palma', element: '#panel_contenido .gp-mapa-acciones', side: ladoPanel, title: 'Acciones de la palma', text: 'Registrar censo abre aquí el formulario para anotar la fecha y la característica observada. Editar cambia la línea, el número, el estado (activa o erradicada) y la observación. Eliminar borra la palma y su ubicación tras confirmar.', cuando: () => abierto },
                { id: 'historial', element: () => $id('historial')?.closest('.gp-mapa-panel-cuerpo'), side: ladoPanel, title: 'Historial de censos', text: 'Lista los censos de la palma con su fecha, característica, grupo y quién los registró. Use Ver todos si hay más de ocho.', cuando: () => abierto },
                { id: 'formulario', element: '#panel_contenido form', side: ladoPanel, title: censo ? 'Registrar censo' : 'Editar palma', text: censo ? 'Indique la fecha (no posterior a hoy) y la característica observada; la observación es opcional. Pulse Guardar, o Cancelar para volver a la ficha.' : 'Cambie la línea, el número o el estado; si la marca como erradicada, indique la fecha. Pulse Guardar, o Cancelar para volver a la ficha.', cuando: () => abierto },
                { id: 'fin', title: '¡Listo!', text: 'Ya conoce las opciones principales. Puede volver a ver este tutorial cuando quiera con el botón de ayuda (?) de la barra superior.' }
            ];
        }
    });
});
