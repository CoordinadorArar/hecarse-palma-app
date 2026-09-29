DECLARE @acciones TABLE (Nombre VARCHAR(100));

INSERT INTO @acciones (Nombre)
VALUES ('registrar-produccion'), ('consultar-produccion'), ('editar-produccion'), ('eliminar-produccion');

INSERT INTO ModulosAcciones (IdModulo, Nombre, Icono, IdUsuario, FechaInicio)
SELECT 58, a.Nombre, NULL, 1, GETDATE()
  FROM @acciones a
 WHERE NOT EXISTS (SELECT 1 FROM ModulosAcciones ma WHERE ma.IdModulo = 58 AND ma.Nombre = a.Nombre);

INSERT INTO ModulosPermisosAcciones (IdRol, IdAccion, IdUsuario, FechaInicio)
SELECT 1, ma.Id, 1, GETDATE()
  FROM ModulosAcciones ma
  JOIN @acciones a ON a.Nombre = ma.Nombre
 WHERE ma.IdModulo = 58
   AND ma.FechaFinalizacion IS NULL
   AND NOT EXISTS (SELECT 1 FROM ModulosPermisosAcciones mpa
                    WHERE mpa.IdRol = 1 AND mpa.IdAccion = ma.Id AND mpa.FechaFinalizacion IS NULL);
