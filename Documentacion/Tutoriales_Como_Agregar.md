# Cómo agregar un tutorial interactivo a un módulo

Los tutoriales usan **Driver.js 1.3.1** (licencia MIT), cargado bajo demanda desde jsDelivr la primera vez que el usuario inicia un recorrido. La lógica común está en un gestor central; cada módulo solo declara sus pasos.

## 1. Piezas existentes

| Archivo | Función |
|---|---|
| `public/assets/js/tutoriales/tutorial-manager.js` | Gestor central (`window.Tutoriales`). Se incluye en `app/Views/template/layout.php` en todas las páginas con header, después de `renderSection('scripts')`. |
| `public/assets/css/tutoriales.css` | Estilos del botón de ayuda y del cuadro del tutorial (`.driver-popover.app-tour`). Incluido en `layout.php`. |
| `app/Views/template/header.php` | Botón `?` (`.tut-trigger-header`). Permanece oculto hasta que un módulo registra un tutorial. |
| `public/assets/js/tutoriales/modulos/planeacion.js` | Tutorial piloto (Planeación de labores). Úselo como referencia. |

El gestor se encarga de: mostrar la confirmación, bloquear el inicio si hay un modal u offcanvas abierto, cargar la librería (con aviso si falla), omitir los pasos cuyo elemento no exista o no esté visible, evitar el doble inicio y dejar el módulo usable al cerrar.

## 2. Pasos para un módulo nuevo

1. Cree `public/assets/js/tutoriales/modulos/<modulo>.js`.
2. Inclúyalo en la vista del módulo, dentro de la sección `scripts` y **después** del JS propio del módulo:

   ```php
   <script src="<?= asset_url('public/assets/js/tutoriales/modulos/<modulo>.js') ?>"></script>
   ```

3. Registre el tutorial dentro de `DOMContentLoaded` (el gestor se carga después de la sección `scripts`, por eso el registro debe esperar a ese evento) y con `?.` para no fallar en vistas sin header:

   ```javascript
   document.addEventListener('DOMContentLoaded', () => window.Tutoriales?.registrar('clave_modulo', {
       titulo: 'Nombre del módulo',
       antesDeIniciar: () => (document.getElementById('cargando')?.getClientRects().length ? 'Espere a que termine de cargar.' : null),
       pasos: () => [
           { id: 'bienvenida', title: 'Nombre del módulo', text: 'Qué permite hacer este módulo.' },
           { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo registro', text: 'Abre el formulario para crear un registro.' },
           { id: 'fin', title: '¡Listo!', text: 'Puede volver a ver este tutorial con el botón de ayuda (?).' }
       ]
   }));
   ```

## 3. Definición

- `titulo`: nombre del módulo.
- `antesDeIniciar()` (opcional): devuelve un mensaje para impedir el inicio (por ejemplo, mientras carga AJAX) o `null` para continuar.
- `pasos()`: función que devuelve el arreglo de pasos. Se evalúa al iniciar, así que puede consultar el estado actual de la pantalla.

Cada paso:

| Propiedad | Descripción |
|---|---|
| `id` | Identificador del paso (aparece en los avisos de consola). |
| `element` | Selector CSS o función que devuelve un `Element` (o `null`). Sin `element`, el cuadro se muestra centrado. |
| `title`, `text` | Título y explicación. Textos breves, en segunda persona formal y orientados a la acción. |
| `side` | `'top'`, `'bottom'`, `'left'`, `'right'` o función que lo devuelve (útil para cambiar según el ancho de pantalla). |
| `cuando()` | Condición opcional; si devuelve `false` el paso se omite. |

Un paso con `element` se omite si el elemento no existe, no es visible (`getClientRects()` vacío) o tiene un ancestro `.d-none`. Si al final no queda ningún paso con elemento, el tutorial no se inicia y se informa al usuario.

## 4. Buenas prácticas

- Use **ids estables** (`id="btn_agregar"`) en lugar de clases visuales. Añadirlos no debe cambiar el aspecto ni la lógica.
- Si un botón se genera por JavaScript en varios lugares, asigne el id solo en uno para que sea único.
- Para elementos dinámicos (tarjetas, filas) use una función: `element: () => document.querySelector('#lista .tarjeta .acciones')`.
- Use `cuando()` para permisos, estados (plan cerrado, botón deshabilitado) y diferencias de escritorio/móvil (`window.matchMedia('(min-width: 768px)').matches`).
- El tutorial **nunca** debe hacer `click()`, abrir modales/dropdowns ni modificar datos; solo resalta y explica.
- Sin comentarios en el código, siguiendo la convención del proyecto.

## 5. Pruebas manuales

1. El botón `?` aparece solo en el módulo con tutorial registrado.
2. Al pulsarlo se muestra la confirmación; «No, gracias» la cierra sin efectos.
3. «Sí, iniciar tutorial» inicia el recorrido; Anterior/Siguiente/Finalizar y la × funcionan; clic en el fondo lo cierra.
4. Con un modal abierto o durante la carga, se muestra el aviso y no se inicia.
5. Los pasos sin elemento disponible se omiten (probar sin permisos, con datos vacíos y con distintas vistas o pestañas).
6. Probar en escritorio y en móvil (< 768 px): posiciones, desplazamiento y que el header fijo no tape el elemento.
7. Sin conexión al CDN: se muestra «No se pudo cargar el tutorial…» y el módulo sigue funcionando.
8. Después de cerrar el tutorial, todos los botones y formularios del módulo siguen funcionando y el `?` vuelve a estar habilitado.
9. Consola sin errores no controlados.

## 6. Módulos pendientes

| Módulo | Estado |
|---|---|
| Transacciones · Planeación de labores | Implementado (piloto) |
| Transacciones · Registro de labores | Implementado (`tutoriales/modulos/labores.js`) |
| Transacciones · Consulta de labores | Implementado (`tutoriales/modulos/labores.js`) |
| Transacciones · Fertilización (PFA) | Pendiente |
| Transacciones · Producción | Implementado (`tutoriales/modulos/produccion.js`) |
| Transacciones · Consulta de producción | Implementado (`tutoriales/modulos/produccion.js`) |
| Gestión palma | Pendiente |
| Maestros | Pendiente |
| Informes | Pendiente |
| Dashboards | Pendiente |
| Administración | Pendiente |
