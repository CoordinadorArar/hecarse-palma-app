# Guía portable: tutoriales interactivos por módulo

Esta guía permite implementar en **cualquier proyecto web** el sistema de tutoriales que se construyó en AppPalma: el usuario pulsa un botón de ayuda (`?`), confirma, y un recorrido guiado resalta los elementos de la pantalla con una explicación en cada paso.

No depende del framework del servidor (CodeIgniter, Laravel, .NET, Node, HTML estático…). Solo necesita un navegador moderno y que la página pueda cargar un archivo JS y uno CSS propios.

---

## 1. Qué se obtiene

- **Botón de ayuda `?`** que aparece solo en las páginas que tienen tutorial.
- **Confirmación** antes de iniciar: «¿Desea ver el tutorial del módulo?» con «Sí, iniciar tutorial» y «No, gracias».
- **Recorrido paso a paso** con resaltado del elemento, título, explicación, progreso («3 de 10») y botones Anterior, Siguiente, Finalizar y cerrar (×). También se cierra con Escape o con clic en el fondo.
- **Pasos inteligentes**: se omiten solos si el elemento no existe, está oculto o el usuario no tiene permiso. El progreso cuenta solo los pasos que quedan.
- **Protecciones**: no inicia con un modal abierto ni mientras la pantalla carga datos, no se inicia dos veces y avisa si la librería no se pudo descargar.
- **Sin efectos sobre los datos**: el tutorial nunca hace clic, no abre ventanas ni envía peticiones; solo resalta y explica.

## 2. Requisitos

| Requisito | Obligatorio | Notas |
|---|---|---|
| [Driver.js](https://driverjs.com/) 1.3.1 | Sí | Licencia **MIT**, libre para uso comercial. Se descarga del CDN bajo demanda; no hay que instalar nada. |
| [SweetAlert2](https://sweetalert2.github.io/) | No | Si está cargada se usa para la confirmación y los avisos. Si no, se usan `confirm()` y `alert()` del navegador. |
| Bootstrap 5 | No | Si existe, se cierran sus tooltips al iniciar y se detectan sus modales/offcanvas abiertos. |

> **¿Por qué no Shepherd.js?** Desde la versión 12 su licencia es AGPL-3.0 o comercial de pago. Driver.js ofrece lo mismo con licencia MIT.

## 3. Archivos

```text
assets/
├── js/
│   └── tutoriales/
│       ├── tutorial-manager.js        ← gestor común (se copia tal cual)
│       └── modulos/
│           └── <modulo>.js            ← un archivo por pantalla con tutorial
└── css/
    └── tutoriales.css                 ← estilos (se ajustan los colores)
```

Adapte las rutas a la estructura del proyecto; no cree carpetas duplicadas si ya existe una organización equivalente.

---

## 4. Implementación paso a paso

### 4.1. Copiar el gestor común

Cree `assets/js/tutoriales/tutorial-manager.js` con este contenido. **Solo se edita el bloque `CONFIG`**.

```javascript
(() => {
    const CONFIG = {
        cdn: 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/',
        botonSelector: '.tut-trigger',
        contenedorBoton: null,
        ventanasAbiertas: '.modal.show, .offcanvas.show',
        colorConfirmar: '#1dab4d',
        colorCancelar: '#6c757d',
        claseCuadro: 'app-tour',
        textos: {
            pregunta: '¿Desea ver el tutorial del módulo?',
            confirmar: 'Sí, iniciar tutorial',
            cancelar: 'No, gracias',
            anterior: 'Anterior',
            siguiente: 'Siguiente',
            finalizar: 'Finalizar',
            progreso: '{{current}} de {{total}}',
            cerrar: 'Cerrar tutorial',
            ventanaAbierta: 'Cierre la ventana abierta para iniciar el tutorial.',
            sinPasos: 'No hay elementos del tutorial disponibles en este momento.',
            errorCarga: 'No se pudo cargar el tutorial. Verifique su conexión e intente de nuevo.'
        }
    };

    const T = CONFIG.textos;
    const registros = {};
    const boton = document.querySelector(CONFIG.botonSelector);
    let clave = null;
    let activo = false;
    let carga = null;

    const aviso = (icon, title) => {
        if (window.Swal) {
            return Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true, icon, title });
        }
        alert(title);
    };

    const confirmar = async () => {
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'question',
                title: T.pregunta,
                showCancelButton: true,
                confirmButtonText: T.confirmar,
                cancelButtonText: T.cancelar,
                allowOutsideClick: false,
                confirmButtonColor: CONFIG.colorConfirmar,
                cancelButtonColor: CONFIG.colorCancelar
            });
            return r.isConfirmed;
        }
        return window.confirm(T.pregunta);
    };

    const cargar = () => {
        if (window.driver?.js?.driver) return Promise.resolve();
        if (!carga) {
            carga = new Promise((resolve, reject) => {
                if (!document.querySelector('link[data-tut-driver]')) {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = `${CONFIG.cdn}driver.css`;
                    link.dataset.tutDriver = '1';
                    document.head.appendChild(link);
                }
                const script = document.createElement('script');
                const fallo = (error) => { clearTimeout(espera); script.remove(); reject(error); };
                const espera = setTimeout(() => fallo(new Error('Tiempo de espera agotado')), 10000);
                script.src = `${CONFIG.cdn}driver.js.iife.js`;
                script.onload = () => (window.driver?.js?.driver ? (clearTimeout(espera), resolve()) : fallo(new Error('Driver.js no disponible')));
                script.onerror = () => fallo(new Error('Error al descargar Driver.js'));
                document.head.appendChild(script);
            }).catch((error) => { carga = null; throw error; });
        }
        return carga;
    };

    const visible = (el) => el instanceof Element
        && el.getClientRects().length > 0
        && getComputedStyle(el).visibility !== 'hidden';

    const resolver = (element) => (typeof element === 'function' ? element() : document.querySelector(element));

    const construir = (definicion) => (definicion.pasos() || []).reduce((lista, paso) => {
        try {
            if (paso.cuando && !paso.cuando()) return lista;
            const el = paso.element ? resolver(paso.element) : null;
            if (paso.element && !visible(el)) return lista;
            lista.push({
                element: el || undefined,
                popover: {
                    title: paso.title,
                    description: paso.text,
                    side: typeof paso.side === 'function' ? paso.side() : paso.side
                }
            });
        } catch (error) {
            console.warn(`Tutorial: paso "${paso.id}" omitido.`, error);
        }
        return lista;
    }, []);

    const liberar = () => {
        activo = false;
        if (boton) boton.disabled = false;
        document.body.classList.remove('tut-activo');
    };

    const ocultarTooltips = () => {
        if (!window.bootstrap?.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
            try { bootstrap.Tooltip.getInstance(el)?.hide(); } catch (error) { console.warn('Tutorial:', error); }
        });
    };

    const iniciar = async () => {
        const definicion = registros[clave];
        if (!definicion || activo) return;

        activo = true;
        if (boton) boton.disabled = true;
        let iniciado = false;

        try {
            if (CONFIG.ventanasAbiertas && document.querySelector(CONFIG.ventanasAbiertas)) {
                aviso('info', T.ventanaAbierta);
                return;
            }

            const bloqueo = definicion.antesDeIniciar?.();
            if (bloqueo) {
                aviso('info', bloqueo);
                return;
            }

            if (!(await confirmar())) return;

            ocultarTooltips();

            try {
                await cargar();
            } catch (error) {
                console.warn('Tutorial:', error);
                aviso('error', T.errorCarga);
                return;
            }

            const pasos = construir(definicion);
            if (!pasos.some((paso) => paso.element)) {
                aviso('info', T.sinPasos);
                return;
            }

            document.body.classList.add('tut-activo');

            window.driver.js.driver({
                steps: pasos,
                showProgress: true,
                progressText: T.progreso,
                nextBtnText: T.siguiente,
                prevBtnText: T.anterior,
                doneBtnText: T.finalizar,
                allowClose: true,
                overlayClickBehavior: 'close',
                overlayColor: '#000',
                overlayOpacity: 0.55,
                stagePadding: 6,
                stageRadius: 8,
                smoothScroll: true,
                popoverClass: CONFIG.claseCuadro,
                onPopoverRender: (popover, { state }) => {
                    popover.closeButton.setAttribute('aria-label', T.cerrar);
                    if (state.activeIndex === 0) popover.previousButton.style.display = 'none';
                },
                onDestroyed: liberar
            }).drive();

            iniciado = true;
        } catch (error) {
            console.warn('Tutorial:', error);
        } finally {
            if (!iniciado) liberar();
        }
    };

    boton?.addEventListener('click', iniciar);

    window.Tutoriales = {
        registrar(nombre, definicion) {
            if (!boton || !definicion || typeof definicion.pasos !== 'function') return;
            registros[nombre] = definicion;
            clave = nombre;
            const contenedor = CONFIG.contenedorBoton ? boton.closest(CONFIG.contenedorBoton) : null;
            (contenedor || boton).hidden = false;
        },
        iniciar
    };
})();
```

**Qué ajustar en `CONFIG`:**

| Clave | Para qué |
|---|---|
| `botonSelector` | Selector del botón `?`. |
| `contenedorBoton` | Si el botón va dentro de un `<li>` u otro contenedor que también debe ocultarse, su selector (p. ej. `'li'`). Si no, `null`. |
| `ventanasAbiertas` | Selector de ventanas que impiden iniciar (modales del framework de UI que use). `null` para no validar. |
| `colorConfirmar`, `colorCancelar` | Colores de la confirmación (si hay SweetAlert2). |
| `textos` | Todos los textos visibles; tradúzcalos si el proyecto no está en español. |
| `cdn` | Cambie la URL si sirve Driver.js desde su propio servidor (ver 6.3). |

### 4.2. Agregar el botón de ayuda

Póngalo una sola vez en el layout o plantilla común (barra superior, encabezado…). Empieza **oculto** (`hidden`) y el gestor lo muestra cuando la página registra un tutorial.

```html
<button type="button" class="tut-trigger" hidden aria-label="Tutorial del módulo" title="Tutorial del módulo">
    <i class="bi bi-question-circle" aria-hidden="true"></i>
</button>
```

- Si no usa Bootstrap Icons, reemplace el `<i>` por un SVG o el texto `?`.
- Si el botón va dentro de un contenedor (por ejemplo `<li>`), ponga `hidden` en el contenedor y configure `contenedorBoton: 'li'`.

### 4.3. Agregar los estilos

Cree `assets/css/tutoriales.css`. **Cambie solo las variables del bloque `:root`** para adaptarlo a la marca del proyecto.

```css
:root {
    --tut-acento: #1dab4d;
    --tut-boton: #137a36;
    --tut-boton-hover: #0f6a2e;
    --tut-titulo: #012970;
    --tut-texto: #444;
    --tut-tenue: #6c757d;
    --tut-fuente-titulo: "Nunito", sans-serif;
    --tut-fuente-texto: "Open Sans", sans-serif;
    --tut-header-fijo: 80px;
}

.tut-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    padding: 0;
    font-size: 20px;
    color: inherit;
    background: transparent;
    border: 0;
    border-radius: 50%;
    cursor: pointer;
}

.tut-trigger[hidden] { display: none; }
.tut-trigger:hover, .tut-trigger:focus-visible { background-color: rgba(127, 127, 127, .15); }
.tut-trigger:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
.tut-trigger:disabled { opacity: .6; cursor: default; }

body.tut-activo * { scroll-margin-top: var(--tut-header-fijo); }

.driver-popover.app-tour {
    background-color: #fff;
    color: var(--tut-texto);
    max-width: 340px;
    padding: 16px 18px;
    border: 0;
    border-top: 4px solid var(--tut-acento);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
}

.driver-popover.app-tour .driver-popover-title {
    font-family: var(--tut-fuente-titulo);
    font-size: 16px;
    font-weight: 700;
    line-height: 1.35;
    color: var(--tut-titulo);
    padding-right: 24px;
}

.driver-popover.app-tour .driver-popover-description {
    margin-top: 6px;
    font-family: var(--tut-fuente-texto);
    font-size: 14px;
    line-height: 1.5;
    color: var(--tut-texto);
}

.driver-popover.app-tour .driver-popover-footer { margin-top: 14px; gap: 8px; }

.driver-popover.app-tour .driver-popover-progress-text {
    font-family: var(--tut-fuente-texto);
    font-size: 12px;
    color: var(--tut-tenue);
    margin-right: auto;
}

.driver-popover.app-tour .driver-popover-navigation-btns { justify-content: flex-end; gap: 8px; }
.driver-popover.app-tour .driver-popover-navigation-btns button + button { margin-left: 0; }

.driver-popover.app-tour .driver-popover-footer button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    font-family: var(--tut-fuente-texto);
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    text-shadow: none;
    border-radius: 6px;
}

.driver-popover.app-tour .driver-popover-prev-btn {
    color: var(--tut-tenue);
    background-color: #fff;
    border: 1px solid var(--tut-tenue);
}

.driver-popover.app-tour .driver-popover-prev-btn:hover,
.driver-popover.app-tour .driver-popover-prev-btn:focus-visible { color: #fff; background-color: var(--tut-tenue); }

.driver-popover.app-tour .driver-popover-next-btn {
    color: #fff;
    background-color: var(--tut-boton);
    border: 1px solid var(--tut-boton);
}

.driver-popover.app-tour .driver-popover-next-btn:hover,
.driver-popover.app-tour .driver-popover-next-btn:focus-visible {
    background-color: var(--tut-boton-hover);
    border-color: var(--tut-boton-hover);
}

.driver-popover.app-tour .driver-popover-footer button:focus-visible { outline: 2px solid var(--tut-acento); outline-offset: 2px; }
.driver-popover.app-tour .driver-popover-arrow-side-bottom { border-bottom-color: var(--tut-acento); }

.driver-popover.app-tour .driver-popover-close-btn {
    top: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    line-height: 1;
    color: var(--tut-tenue);
    border-radius: 50%;
}

.driver-popover.app-tour .driver-popover-close-btn:hover,
.driver-popover.app-tour .driver-popover-close-btn:focus-visible { color: #212529; background-color: #f1f3f5; }

@media (max-width: 767.98px) {
    .driver-popover.app-tour { max-width: calc(100vw - 24px); }
    .driver-popover.app-tour .driver-popover-footer button { min-height: 36px; }
}
```

**Notas de estilo:**
- Los selectores llevan el prefijo `.driver-popover.app-tour` para ganarle a `driver.css`, que se inyecta después y usa `all: unset`. No necesita `!important`.
- `--tut-boton` debe tener **contraste ≥ 4,5:1 con texto blanco** (WCAG AA). El color de marca suele ser muy claro para el botón; úselo en `--tut-acento` (franja superior y flecha) y una versión más oscura en `--tut-boton`.
- `--tut-header-fijo`: altura del header fijo más un margen, para que el elemento resaltado no quede tapado.

### 4.4. Cargar los archivos

En el layout común, en todas las páginas donde pueda haber tutorial:

```html
<link rel="stylesheet" href="/assets/css/tutoriales.css">
...
<!-- al final del body, DESPUÉS de los scripts de cada página -->
<script src="/assets/js/tutoriales/tutorial-manager.js"></script>
```

El gestor debe cargarse **después del HTML del botón**. Driver.js **no** se incluye aquí: el gestor lo descarga la primera vez que alguien inicia un tutorial.

Equivalencias habituales:

| Stack | Dónde va |
|---|---|
| CodeIgniter 4 | `layout.php`, después de `renderSection('scripts')`. |
| Laravel (Blade) | Layout principal, después de `@stack('scripts')` o `@yield('scripts')`. |
| ASP.NET MVC / Razor | `_Layout.cshtml`, después de `@RenderSection("Scripts")`. |
| HTML estático | Al final del `<body>` de cada página. |
| SPA (React, Vue…) | Ver 6.4. |

### 4.5. Crear el tutorial de una pantalla

Cree `assets/js/tutoriales/modulos/<modulo>.js` e inclúyalo **solo en esa pantalla**, después de su JS propio:

```javascript
document.addEventListener('DOMContentLoaded', () => window.Tutoriales?.registrar('clientes', {
    titulo: 'Clientes',
    antesDeIniciar: () => (document.querySelector('#cargando:not([hidden])')
        ? 'Espere a que termine de cargar la información para iniciar el tutorial.'
        : null),
    pasos: () => {
        const escritorio = window.matchMedia('(min-width: 768px)').matches;

        return [
            { id: 'bienvenida', title: 'Clientes', text: 'Aquí consulta y administra los clientes. Use Siguiente para recorrer las opciones principales.' },
            { id: 'buscar', element: '#buscar_cliente', side: 'bottom', title: 'Buscar', text: 'Escriba el nombre o documento para filtrar la lista.' },
            { id: 'nuevo', element: '#btn_nuevo', side: 'bottom', title: 'Nuevo cliente', text: 'Abre el formulario para registrar un cliente.' },
            { id: 'exportar', element: '#btn_exportar', side: 'left', title: 'Exportar', text: 'Descarga la lista en Excel.', cuando: () => escritorio },
            { id: 'acciones', element: () => document.querySelector('#tabla_clientes tbody tr .acciones'), side: 'left', title: 'Acciones', text: 'Edite o desactive el cliente desde aquí.' },
            { id: 'fin', title: '¡Listo!', text: 'Puede volver a ver este tutorial con el botón de ayuda (?).' }
        ];
    }
}));
```

- Se registra dentro de `DOMContentLoaded` porque el gestor se carga después de los scripts de la página.
- `window.Tutoriales?.` evita errores si la página no tiene el gestor.

---

## 5. Referencia de la definición

```javascript
Tutoriales.registrar(clave, {
    titulo: 'Nombre de la pantalla',
    antesDeIniciar: () => null,
    pasos: () => [ /* pasos */ ]
});
```

| Propiedad | Tipo | Descripción |
|---|---|---|
| `clave` | string | Identificador único de la pantalla. |
| `titulo` | string | Nombre de la pantalla (informativo). |
| `antesDeIniciar` | función opcional | Devuelve un **mensaje** para impedir el inicio (por ejemplo, mientras carga AJAX) o `null` para continuar. |
| `pasos` | función | Devuelve el arreglo de pasos. Se evalúa **al iniciar**, así que puede leer el estado actual de la pantalla. |

Cada paso:

| Propiedad | Descripción |
|---|---|
| `id` | Identificador del paso (aparece en los avisos de consola). |
| `element` | Selector CSS o función que devuelve un `Element` o `null`. **Sin `element`** el cuadro aparece centrado (bienvenida, cierre). |
| `title` | Título corto. |
| `text` | Explicación de 1–2 frases, orientada a la acción. |
| `side` | `'top'`, `'bottom'`, `'left'`, `'right'`, o función que lo devuelve (útil para cambiar según el ancho). |
| `cuando` | Función opcional; si devuelve `false`, el paso se omite. Úsela para permisos, estados y escritorio/móvil. |

**Reglas de omisión automática:** un paso con `element` se omite si el elemento no existe, no ocupa espacio en pantalla (él o un ancestro está oculto con `display: none`) o tiene `visibility: hidden`. Si al final no queda ningún paso con elemento, el tutorial no inicia y se avisa al usuario.

**API pública:** `window.Tutoriales.iniciar()` inicia el tutorial registrado desde otro lugar (un enlace «Ver tutorial», por ejemplo) con las mismas validaciones.

---

## 6. Adaptaciones frecuentes

### 6.1. Sin SweetAlert2
Funciona igual con `confirm()` y `alert()` del navegador. Para usar otra librería de diálogos, reemplace solo las funciones `aviso` y `confirmar` del gestor.

### 6.2. Sin Bootstrap
- Ajuste `ventanasAbiertas` al selector de los modales que use (por ejemplo `'dialog[open], .mi-modal.abierto'`) o déjelo en `null`.
- `ocultarTooltips` no hace nada si Bootstrap no existe.

### 6.3. Sin acceso a CDN (intranet)
Descargue `driver.js.iife.js` y `driver.css` de la versión 1.3.1 desde `https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/`, guárdelos en el proyecto (por ejemplo `assets/vendor/driver/`) y cambie `CONFIG.cdn` a esa carpeta, terminada en `/`.

### 6.4. Aplicaciones SPA (React, Vue, Angular)
- Cargue el gestor una vez al arrancar la aplicación, con el botón ya presente en el DOM.
- Registre el tutorial cuando el componente de la pantalla esté montado (en lugar de `DOMContentLoaded`).
- Al cambiar de ruta, vuelva a registrar el de la nueva pantalla. Si la nueva no tiene tutorial, oculte el botón (por ejemplo `boton.hidden = true` desde el router).

### 6.5. Varias secciones dentro de una página
Registre una clave distinta cuando el usuario cambie de sección; el botón siempre inicia el último tutorial registrado.

### 6.6. Elementos cargados por AJAX
- Use `antesDeIniciar` para bloquear mientras haya indicadores de carga **visibles** (por ejemplo `'#kpis:not(.d-none) .placeholder'`).
- Ojo con los indicadores que quedan en el DOM pero ocultos: si el selector no exige visibilidad, el tutorial puede quedar bloqueado para siempre.
- Para filas o tarjetas generadas dinámicamente, use `element` como función: `() => document.querySelector('#lista .tarjeta .acciones')`.

---

## 7. Reglas de implementación

- El tutorial **nunca** hace `click()`, `dispatchEvent`, peticiones AJAX ni abre modales o menús. Solo resalta y explica.
- Use **ids estables** (`id="btn_nuevo"`) en lugar de clases visuales. Agregar un id no debe cambiar el aspecto ni la lógica.
- Si un botón se genera en varios lugares, asigne el id solo en uno para que sea único.
- No asuma que un elemento está disponible porque aparece en el HTML: el gestor valida la visibilidad al iniciar.
- Textos breves, con un mismo trato en todo el producto (usted o tú) y orientados a la acción.
- Use `cuando()` para permisos, estados (registro cerrado, botón deshabilitado) y diferencias entre escritorio y móvil.
- No guarde datos del usuario ni del formulario como parte del tutorial.

## 8. Lista de pruebas

**Funcionales**
1. El botón `?` aparece solo en las pantallas con tutorial y no aparece en las demás.
2. «No, gracias» y Escape cierran la confirmación sin efectos; clic fuera no la cierra.
3. «Sí, iniciar tutorial» inicia el recorrido; Anterior, Siguiente, Finalizar, × y Escape funcionan; clic en el fondo lo cierra.
4. «Anterior» no aparece en el primer paso y el progreso «N de M» es coherente.
5. Doble clic en `?` o en «Sí» no inicia dos recorridos.

**Casos especiales**
6. Con un modal abierto o durante una carga: aviso y no inicia.
7. Sin permisos, con datos vacíos o en otra vista/pestaña: los pasos sin elemento se omiten.
8. Sin conexión al CDN: aviso de error, la pantalla sigue funcionando y se puede reintentar.
9. Si ningún paso tiene elemento disponible: aviso y no inicia.

**Interfaz**
10. Escritorio y móvil (< 768 px): posiciones, desplazamiento al elemento y que el header fijo no lo tape.
11. El cuadro no queda detrás de modales ni del header.
12. Contraste AA de textos y botones del cuadro.

**Regresión**
13. Después de cerrar, todos los botones, formularios y peticiones de la pantalla siguen funcionando y el `?` vuelve a estar habilitado.
14. No hay peticiones de red durante el recorrido (salvo la descarga inicial de Driver.js).
15. Consola sin errores no controlados en las pantallas con y sin tutorial.

## 9. Checklist para un proyecto nuevo

- [ ] Copiar `tutorial-manager.js` y ajustar `CONFIG`.
- [ ] Copiar `tutoriales.css` y ajustar las variables de `:root`, verificando el contraste del botón.
- [ ] Agregar el botón `?` (oculto) en el layout común.
- [ ] Cargar el CSS en el `<head>` y el gestor al final del `<body>`, después de los scripts de cada página.
- [ ] Elegir una pantalla piloto, agregar ids estables a 3–14 elementos y crear su archivo en `tutoriales/modulos/`.
- [ ] Ejecutar la lista de pruebas de la sección 8.
- [ ] Documentar la lista de pantallas pendientes y extender el patrón.

## 10. Referencia en AppPalma

Implementación original de donde se extrajo esta guía:

| Archivo | Descripción |
|---|---|
| `public/assets/js/tutoriales/tutorial-manager.js` | Gestor (versión con selectores fijos del proyecto). |
| `public/assets/js/tutoriales/modulos/planeacion.js` | Tutorial de Planeación de labores: 14 pasos, variantes por estado y móvil. |
| `public/assets/css/tutoriales.css` | Estilos con la paleta de la compañía. |
| `Documentacion/Tutoriales_Como_Agregar.md` | Cómo agregar un tutorial dentro de AppPalma. |
