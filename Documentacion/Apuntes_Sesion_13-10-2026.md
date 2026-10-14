# Requerimientos y mejoras del sistema agrícola

## 1. Integración y actualización de datos

* **Cambio de origen de datos:** Ajustar el sistema para que la información requerida se consulte directamente desde SIESA.
* **Actualización de información:** Revisar y ajustar los datos que actualmente se muestran en el sistema, de acuerdo con la nueva fuente de información.

## 2. Gestión de presupuesto por lotes

* **Nuevo módulo de gestión de presupuesto:** Desarrollar un módulo que permita registrar, consultar y administrar los presupuestos agrícolas por lote.
* Permitir la comparación entre los valores presupuestados y los valores ejecutados, facilitando el seguimiento y control de los recursos asignados.

## 3. Planeación de labores y fertilización

* **Nuevo módulo de planeación:** Implementar un módulo para planificar las labores agrícolas y las actividades de fertilización por lote.
* Permitir la organización y consulta de las actividades programadas, con el fin de facilitar el seguimiento de su ejecución.
* Restricción para evitar repetir la misma labor en el mismo lote por trabajador

## 4. Informes gerenciales

Cambiar el nombre de la opción del menú **«Admin Reportes Power BI»** a **«Informes Gerenciales»**. Este apartado centralizará los informes actuales y los nuevos reportes que se incorporen al sistema.

### 4.1. Migración de informes

* Migrar los tableros e informes existentes en Power BI al sistema, buscando centralizar la consulta de la información gerencial.

### 4.2. Consulta de presupuesto y ejecución

* Implementar una consulta con navegación detallada (*drill-down*) desde el nivel de compañía hasta el detalle por lote.
* Mostrar la información del presupuesto y su ejecución.
* Validar la ejecución mediante el cruce de los valores de facturación con las labores registradas en el sistema, para identificar diferencias entre las actividades reportadas y los valores facturados.

### 4.3. Indicador de toneladas por hectárea

* Incorporar un informe que permita consultar y comparar las toneladas producidas por hectárea frente a las cantidades presupuestadas.
* Representar los resultados mediante una **gráfica de líneas** que facilite la identificación de tendencias, variaciones y desviaciones respecto al presupuesto.

## 5. Planeación y periodicidad de cosecha

Implementar una funcionalidad que permita crear, gestioanr y analizar planeación de cosechas, este módulo debe mostrar el mapa de los lotes y semaforizar los lotes según los rangos definidos a continuación.

Clasificación inicial propuesta:

* **Menos de 10 días:** Rango inferior al intervalo esperado; requiere seguimiento.
* **Entre 10 y 12 días:** Periodicidad normal - próximo o listo para cosechar.
* **Más de 12 y hasta 15 días:** Estado de alerta - listo para cosechar.
* **Más de 15 días:** Riesgo de sobremaduración.

La clasificación deberá visualizarse en el módulo de planeación para facilitar el seguimiento oportuno de los lotes.

## 6. Mapeo geográfico de cultivos

* Implementar una funcionalidad de visualización geográfica que permita consultar la ubicación de cada planta dentro de los lotes.
* Mostrar las plantas sobre un mapa, utilizando sus coordenadas geográficas, para facilitar la identificación espacial y el análisis de los cultivos.

## 7. Cruce de labores con información biométrica

* Desarrollar una funcionalidad que permita cruzar los registros de las labores agrícolas con la información biométrica disponible.
* Facilitar la consulta y el análisis conjunto de ambas fuentes de información, con el fin de evaluar la relación entre las labores ejecutadas y las variables biométricas de los cultivos.

## 8. Aplicación móvil con funcionamiento sin conexión

* Adaptar la aplicación móvil para permitir el registro y la consulta de información en zonas sin conexión a internet.
* Implementar un mecanismo de almacenamiento local temporal que permita conservar los datos registrados durante los periodos sin conectividad.
* Sincronizar automáticamente la información con el servidor cuando se restablezca la conexión, contemplando controles para evitar duplicidades y gestionar posibles conflictos de sincronización.
