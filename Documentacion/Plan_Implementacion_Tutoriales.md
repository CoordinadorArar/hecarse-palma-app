# Plan de implementación: tutoriales interactivos por módulo

## 1. Objetivo

Implementar en la aplicación desarrollada con **CodeIgniter 4, JavaScript y Bootstrap** un sistema reutilizable de tutoriales interactivos. Los tutoriales guiarán al usuario por los elementos de cada módulo, resaltando botones, campos y secciones, y mostrando explicaciones paso a paso.

El acceso se realizará desde el botón de ayuda (`?`) ubicado en el header global, en la parte superior derecha; el botón solo se muestra en los módulos que registran un tutorial.

## 2. Experiencia esperada para el usuario

1. El usuario entra a un módulo.
2. Pulsa el botón de ayuda (`?`).
3. El sistema muestra la confirmación: **«¿Desea ver el tutorial del módulo?»**.
4. Si selecciona **«Sí, iniciar tutorial»**, comienza el recorrido guiado.
5. El sistema resalta el elemento correspondiente y presenta un texto explicativo.
6. El usuario puede avanzar, retroceder o cerrar el tutorial.
7. Al llegar al último paso, puede finalizar el recorrido.
8. Si selecciona **«No, gracias»**, la ventana se cierra y el módulo continúa funcionando normalmente.

## 3. Tecnologías propuestas

- **CodeIgniter 4:** integración con las vistas y estructura existente de la aplicación.
- **JavaScript:** definición y control de los pasos.
- **Driver.js 1.3.1 (licencia MIT):** biblioteca para recorridos guiados, resaltado de elementos y cuadros explicativos. Se carga bajo demanda desde jsDelivr (versión fijada) la primera vez que se inicia un tutorial. Se eligió en lugar de Shepherd.js por su licencia MIT y su menor tamaño.
- **Bootstrap:** conservar los estilos y la identidad visual actuales.
- **SweetAlert2:** mostrar la confirmación inicial, si ya está disponible en el proyecto. Si existe otro componente de alertas estándar, reutilizarlo en lugar de añadir una dependencia innecesaria.

Documentación de referencia:
- Driver.js: https://driverjs.com/
- Repositorio de Driver.js: https://github.com/kamranahmedse/driver.js
- CodeIgniter 4: https://codeigniter.com/user_guide/

Antes de instalar dependencias, revisar cómo administra el proyecto sus recursos JavaScript y CSS. Si no utiliza npm, evaluar integrar los archivos estáticos de la biblioteca de forma compatible con la estructura actual.

## 4. Alcance funcional

### Incluido en la primera versión

- Reutilizar el botón de ayuda existente en cada módulo.
- Mostrar la confirmación para iniciar el tutorial.
- Definir recorridos independientes por módulo.
- Resaltar botones, campos, tablas, pestañas y secciones existentes.
- Mostrar título y explicación en cada paso.
- Incluir acciones de **Anterior**, **Siguiente**, **Finalizar** y cierre del tutorial.
- Desplazar la página hasta el elemento resaltado cuando sea necesario.
- Mostrar el tutorial solo si los elementos requeridos están disponibles.
- Mantener el funcionamiento normal de la aplicación si el usuario cancela o cierra el tutorial.
- Adaptar el aspecto visual a Bootstrap y a los estilos actuales.

### Fuera del alcance inicial

- Editor visual para crear tutoriales sin programar.
- Guardado de progreso individual en base de datos.
- Seguimiento analítico del uso de los tutoriales.
- Tutoriales que realicen automáticamente operaciones reales sobre los datos.
- Rediseño de los módulos existentes.

Estos puntos pueden evaluarse en una fase posterior.

## 5. Diseño técnico propuesto

Crear un componente común para evitar implementar la misma lógica en cada módulo.

### 5.1. Componente central

Responsabilidades:

- Mostrar la confirmación inicial.
- Identificar el tutorial correspondiente al módulo.
- Crear e iniciar el recorrido.
- Configurar los botones de navegación.
- Validar la existencia de los elementos antes de resaltarlos.
- Gestionar el cierre y la finalización.
- Controlar errores si falta algún elemento o si el tutorial no está configurado.

Nombre sugerido: `TutorialManager` o `tutoriales.js`.

### 5.2. Configuración por módulo

Cada módulo define sus propios pasos, sin duplicar la lógica central. Para cada paso se recomienda especificar:

- Identificador del paso.
- Título.
- Texto explicativo.
- Selector CSS del elemento objetivo.
- Posición preferida del cuadro explicativo.
- Acciones opcionales necesarias para llegar al elemento.
- Reglas para omitir el paso cuando el elemento no exista o no esté disponible.

Ejemplo conceptual de configuración:

```javascript
const tutorialModulo = [
    {
        title: 'Crear un registro',
        text: 'Utiliza este botón para iniciar el registro.',
        element: '#btnNuevoRegistro',
        position: 'bottom'
    },
    {
        title: 'Completar la información',
        text: 'Ingresa aquí la información solicitada.',
        element: '#campoNombre',
        position: 'bottom'
    },
    {
        title: 'Consultar registros',
        text: 'Utiliza esta opción para buscar registros existentes.',
        element: '#btnBuscar',
        position: 'top'
    }
];
```

El formato definitivo, adaptado a Driver.js, está descrito en `Documentacion/Tutoriales_Como_Agregar.md`.

### 5.3. Integración con el botón existente

- Botón `?` único en el header global (`app/Views/template/header.php`, clase `.tut-trigger-header`), oculto hasta que el módulo registra su tutorial.
- No se crean botones de ayuda por módulo.
- Obtener el identificador del módulo desde la vista o su configuración.
- Al pulsarlo, mostrar la confirmación.
- Si el usuario confirma, solicitar al componente central que inicie el tutorial de ese módulo.
- Si cancela, cerrar la confirmación sin cambiar el estado del módulo.

### 5.4. Organización de archivos sugerida

Adaptar estas rutas a la estructura real del proyecto; no crear carpetas duplicadas si ya existe una organización equivalente.

```text
public/
└── assets/
    ├── js/
    │   └── tutoriales/
    │       ├── tutorial-manager.js
    │       └── modulos/
    │           ├── modulo-ejemplo.js
    │           └── otro-modulo.js
    └── css/
        └── tutoriales.css
```

La biblioteca Driver.js y sus estilos se cargan por CDN fijado (`driver.js@1.3.1`) bajo demanda desde `tutorial-manager.js`, igual que otras librerías del proyecto que se consumen por CDN.

## 6. Fases de implementación

### Fase 1. Revisión del proyecto

1. Revisar la versión de CodeIgniter 4 y la estructura de vistas.
2. Identificar cómo se cargan JavaScript y CSS.
3. Confirmar si el proyecto utiliza npm, archivos estáticos o un proceso de compilación.
4. Identificar la biblioteca de alertas disponible.
5. Localizar el botón de ayuda y comprobar si se comparte entre módulos.
6. Seleccionar un módulo piloto y documentar sus elementos principales.
7. Verificar cómo funcionan los modales, las pestañas y las cargas AJAX de ese módulo.

**Resultado esperado:** decisión de integración documentada, sin modificar innecesariamente la arquitectura existente.

### Fase 2. Integración de dependencias

1. Integrar Driver.js 1.3.1 con el método compatible con el proyecto (CDN fijado, carga bajo demanda).
2. Cargar sus estilos.
3. Confirmar que no existan conflictos con Bootstrap, otros estilos o bibliotecas JavaScript.
4. Evitar duplicar bibliotecas ya instaladas.
5. Verificar que los recursos funcionen en los ambientes de desarrollo y pruebas.

**Resultado esperado:** biblioteca cargada correctamente y lista para utilizarse.

### Fase 3. Desarrollo del componente central

1. Crear el gestor común de tutoriales.
2. Implementar la confirmación inicial.
3. Configurar inicio, navegación, cierre y finalización.
4. Permitir registrar una configuración por módulo.
5. Validar selectores antes de iniciar cada paso.
6. Definir el comportamiento para pasos cuyo elemento no exista.
7. Agregar manejo de errores y limpieza de eventos al cerrar el recorrido.
8. Aplicar los estilos compatibles con el sistema.

**Resultado esperado:** componente reutilizable probado con un recorrido de ejemplo.

### Fase 4. Implementación del módulo piloto

1. Seleccionar un módulo representativo.
2. Identificar entre tres y seis elementos importantes para el usuario.
3. Asignar identificadores estables a esos elementos cuando sea necesario.
4. Redactar explicaciones breves, claras y orientadas a la acción.
5. Crear la configuración del recorrido.
6. Conectar el botón de ayuda existente.
7. Verificar la secuencia completa.
8. Ajustar posiciones, desplazamiento, estilos y comportamiento en pantallas pequeñas.

**Resultado esperado:** un tutorial funcional en un módulo real.

### Fase 5. Pruebas y validación

Realizar las pruebas indicadas en la sección 8 y corregir los problemas encontrados antes de ampliar la implementación.

**Resultado esperado:** módulo piloto aprobado por desarrollo y por usuarios que conozcan el proceso funcional.

### Fase 6. Despliegue progresivo

1. Priorizar los módulos según su uso, complejidad y necesidad de capacitación.
2. Definir y revisar los pasos de cada módulo.
3. Reutilizar el componente central sin duplicar su lógica.
4. Probar cada módulo después de agregar el tutorial.
5. Publicar los cambios siguiendo el proceso habitual de despliegue.
6. Recoger observaciones y ajustar los textos o recorridos.

**Resultado esperado:** tutoriales coherentes en los módulos priorizados, con un mecanismo común de mantenimiento.

## 7. Reglas de implementación

- No reemplazar ni alterar la lógica funcional existente de los módulos.
- No activar operaciones de creación, edición o eliminación de datos automáticamente.
- No modificar datos reales durante un tutorial.
- Usar selectores estables y únicos; evitar depender de clases visuales genéricas.
- No asumir que un elemento está disponible solo porque aparece en el HTML inicial.
- Si el paso depende de un modal, una pestaña o una petición AJAX, esperar a que la interfaz esté lista antes de mostrarlo.
- Si el elemento no está disponible por permisos, estado del registro o configuración, omitir el paso o mostrar un mensaje apropiado.
- Mantener accesibles las opciones para cancelar y finalizar.
- Evitar que el tutorial bloquee indefinidamente la interfaz.
- No almacenar datos personales ni información del formulario como parte del progreso del tutorial.
- Mantener la compatibilidad con las versiones de JavaScript y navegadores soportadas por el proyecto.
- Centralizar los estilos propios del tutorial y evitar cambios globales innecesarios.
- Documentar cómo agregar un tutorial nuevo y cómo probarlo.

## 8. Plan de pruebas

### Pruebas funcionales

- El botón `?` abre la confirmación correcta.
- La opción de cancelar cierra la confirmación sin iniciar el tutorial.
- La opción de confirmar inicia el tutorial del módulo actual.
- Los botones Anterior y Siguiente recorren los pasos correctamente.
- El botón Finalizar termina el recorrido.
- El icono de cierre permite salir en cualquier paso.
- El tutorial no se inicia dos veces por una sola interacción.
- El usuario puede seguir utilizando el módulo después de cerrar el tutorial.

### Pruebas de interfaz

- El elemento correcto queda resaltado en cada paso.
- El cuadro explicativo aparece en una posición adecuada.
- La página se desplaza al elemento cuando está fuera de la pantalla.
- Los cuadros no quedan ocultos detrás de modales u otros componentes.
- El recorrido funciona en las resoluciones soportadas.
- El estilo se integra con Bootstrap y con el tema actual.

### Casos especiales

- Un selector no encuentra ningún elemento.
- Un elemento está oculto o deshabilitado.
- El usuario no tiene permisos para ver una opción.
- Una tabla o sección se carga por AJAX.
- Un paso requiere abrir un modal o cambiar de pestaña.
- El usuario cambia de página o abandona el módulo durante el recorrido.
- Se produce un error de JavaScript o no se carga la biblioteca.

### Regresión

- Los botones originales siguen funcionando.
- Los formularios mantienen sus validaciones.
- Las peticiones AJAX siguen operando.
- No se alteran los permisos ni las reglas de negocio.
- No se afectan otros módulos que no tengan tutorial.

## 9. Criterios de aceptación

La primera versión se considerará lista cuando:

1. El botón de ayuda existente permita iniciar el tutorial del módulo correspondiente.
2. Se muestre la confirmación con las opciones para iniciar o cancelar.
3. El recorrido resalte los elementos configurados y muestre sus explicaciones.
4. El usuario pueda avanzar, retroceder y cerrar el tutorial.
5. La ausencia de un elemento no provoque que la pantalla quede bloqueada ni genere un error no controlado.
6. La interfaz siga siendo utilizable después de finalizar o cancelar.
7. No haya regresiones funcionales en el módulo piloto.
8. El procedimiento para añadir tutoriales a otros módulos esté documentado.

## 10. Entregables

- Componente JavaScript central de tutoriales.
- Integración de Driver.js 1.3.1 y sus estilos.
- Integración con el botón de ayuda existente.
- Configuración del tutorial del módulo piloto.
- Estilos de personalización, si se requieren.
- Evidencias de pruebas funcionales y de regresión.
- Instrucciones técnicas para crear y mantener tutoriales por módulo.
- Listado de módulos pendientes de implementación, si se realiza el despliegue progresivo.

## 11. Recomendaciones para una segunda fase

Una vez validado el piloto, se puede evaluar:

- Administrar los textos y pasos desde una pantalla de configuración.
- Almacenar tutoriales en base de datos cuando se necesite editarlos sin desplegar código.
- Registrar si el usuario completó o cerró un tutorial, respetando las políticas de privacidad.
- Ofrecer una opción de «Volver a ver el tutorial».
- Agregar tutoriales contextuales para formularios o procesos específicos.
- Versionar los tutoriales para actualizarlos cuando cambie la interfaz.

No se recomienda construir un editor de tutoriales ni diseñar tablas de base de datos en la primera fase, salvo que exista una necesidad concreta de administrar el contenido sin intervención de desarrollo.

## 12. Orden de trabajo recomendado

1. Inspeccionar la estructura real del proyecto y el botón de ayuda.
2. Integrar la biblioteca sin afectar recursos existentes.
3. Construir el componente central.
4. Implementar un tutorial piloto.
5. Validar funcionalidad, accesibilidad y regresión.
6. Documentar el patrón para agregar nuevos tutoriales.
7. Extenderlo progresivamente a los demás módulos.

**Decisión técnica recomendada:** comenzar con un gestor central en JavaScript, tutoriales configurados por módulo y el botón de ayuda existente. Esta estrategia reduce duplicación, permite validar la experiencia con poco riesgo y deja abierta la posibilidad de administrar los tutoriales desde base de datos en una fase posterior.
