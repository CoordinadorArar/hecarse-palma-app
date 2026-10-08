# TRANSACCIONES/PRODUCCIÓN

1. **Código único para terceros**

   *El campo de terceros codigoEquivalencia es el más utilizado al momento de realizar consultas en los formularios de registro de producción, labores etc. Ajustar esos sistemas para priorizar la busqueda de terceros por este código y en cualquier otro módulo similar. Validar si el campo codigoEquivalencia se mantiene para un empleado si por ejemplo termina contrato y luego vuelve (debería usar el mismo código que ya tenía).

2. **Visualización de registros de terceros**

   * Modificar la vista de los registros para que tenga un funcionamiento y presentación similar al sistema anterior, no se oculten los divs, que segun las pantallas se acomoden cada sección de cada nuevo registro. Revisar imagen @Documentacion/ImagenRegistrosProduccion.jpeg, en pantallas grandes que se visualice similar por columnas, en pantallas mas pequeñas que se listen como se hace actualmente en el módulo de registro de labores (no desplegables).
   * La información de los empleados debe mostrarse directamente en el listado, evitando tener que ingresar a cada registro individual para consultar sus datos principales.

3. **Agregar múltiples detalles a un mismo lote**

   * Permitir registrar más de un detalle asociado al mismo lote cuando los detalles correspondan a **fechas diferentes**.
   * El sistema debe permitir conservar los diferentes registros del lote sin sobrescribir la información previamente registrada.

# TRANSACCIONES/LABORES

1. **Selección de múltiples fechas en el detalle de la transacción**

   * Al registrar el detalle de una transacción, permitir seleccionar **varias fechas de manera predeterminada**, evitando tener que registrar cada fecha individualmente cuando corresponda al mismo proceso.

# TRANSACCIONES/FERTILIZACIÓN

1. **Edición de tercero y cantidad después del registro**

   * Una vez cargado y registrado un registro de fertilización, permitir modificar:

     * El **tercero** asociado.
     * La **cantidad** registrada.
   * Los cambios deben poder realizarse sobre el registro existente sin necesidad de eliminarlo y crearlo nuevamente.
