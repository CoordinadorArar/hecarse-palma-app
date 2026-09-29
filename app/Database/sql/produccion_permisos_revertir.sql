BEGIN TRANSACTION;

DELETE FROM ModulosPermisosAcciones
 WHERE IdAccion IN (
       SELECT Id FROM ModulosAcciones
        WHERE IdModulo = 58
          AND Nombre IN ('registrar-produccion', 'consultar-produccion', 'editar-produccion', 'eliminar-produccion'));

DELETE FROM ModulosAcciones
 WHERE IdModulo = 58
   AND Nombre IN ('registrar-produccion', 'consultar-produccion', 'editar-produccion', 'eliminar-produccion');

COMMIT;
