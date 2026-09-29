<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'LoginController::index');
$routes->get('login/', 'LoginController::login');
$routes->get('logout', 'LoginController::logout');
$routes->post('/verifyUser', 'LoginController::verifyUser');
$routes->post('/cerrar_sesion', 'LoginController::cerrar_sesion');
$routes->get('/obtener_usuario/(:num)', 'admin\UsuariosController::getUserById/$1');


/**
 * Rutas para recuperación de contraseña
 */
$routes->get('recover/', 'RecoverController::index');
$routes->get('resetPass/(:any)', 'ResetPassController::index/$1');
$routes->post('recover/verifyEmail', 'RecoverController::verifyEmail');
$routes->post('recover/envioEnlace', 'RecoverController::envioEnlace');
$routes->post('resetPass/verifyPasswords', 'ResetPassController::verifyPasswords');

/**
 * Rutas para Index (Landing Page)
 */
$routes->post('contacto/enviar', 'IndexController::enviar');

/**
 * Rutas de la loseta
 */
$routes->get('losetas/', 'LosetasController::index');

/** Rutas de la loseta "Administracion"*/
$routes->get('admin/onboarding/(:num)', 'admin\AdminController::index/$1');
$routes->get('admin/modulos/(:num)', 'admin\ModulosController::index/$1');
$routes->get('admin/roles/(:num)', 'admin\RolesController::index/$1');
$routes->get('admin/usuarios/(:num)', 'admin\UsuariosController::index/$1');
$routes->get('admin/acciones/(:num)', 'admin\AccionesController::index/$1');
$routes->get('admin/tabs/(:num)', 'admin\TabsController::index/$1');
$routes->get('admin/sitio/(:num)', 'admin\SiteController::index/$1');

/** Rutas de módulo usuarios */
$routes->post('admin/usuarios/(:num)', 'admin\UsuariosController::getUserById/$1');
$routes->post('admin/usuarios/actualizarUsuario', 'admin\UsuariosController::updateUser');
$routes->post('admin/usuarios/updatePass', 'admin\UsuariosController::updatePass');
$routes->post('admin/usuarios/buscarUsuario', 'admin\UsuariosController::findUserByName');
$routes->post('admin/usuarios/buscarUsuariosActivos', 'admin\UsuariosController::getActiveUsers');
$routes->post('admin/usuarios/obtenerRolesUsuario', 'admin\UsuariosController::getAllRolesByUser');
$routes->post('admin/usuarios/quitarRolUsuario', 'admin\UsuariosController::deleteUserRole');
$routes->post('admin/usuarios/asignarRolUsuario', 'admin\UsuariosController::addUserRole');
$routes->post('admin/usuarios/actualizarRolUsuarios', 'admin\UsuariosController::addUsersRole');
$routes->post('admin/usuarios/crearUsuario', 'admin\UsuariosController::createUser');
$routes->post('admin/usuarios/guardarFotoPerfil', 'admin\UsuariosController::guardarFotoPerfil');

/**Rutas de consultas varias de admin */
$routes->post('admin/admin/obtenerDepartamentos', 'admin\AdminController::getDepartamentos');
$routes->post('admin/admin/obtenerCiudades', 'admin\AdminController::getCiudades');
$routes->get('admin/documentos/(:any)', 'admin\AdminController::getDocumentos/$1');

/** Rutas de módulo roles */
$routes->post('admin/roles/(:num)', 'admin\RolesController::getRolById/$1');
$routes->post('admin/roles/actualizarRol', 'admin\RolesController::updateRol');
$routes->post('admin/roles/buscarRol', 'admin\RolesController::findRolByName');
$routes->post('admin/roles/obtenerModulosRol', 'admin\RolesController::getAllModulesByRol');
$routes->post('admin/roles/quitarModuloRol', 'admin\RolesController::deleteRolModule');
$routes->post('admin/roles/asignarModuloRol', 'admin\RolesController::addRolModule');
$routes->post('admin/roles/actualizarModuloRoles', 'admin\RolesController::addRolsModule');
$routes->post('admin/roles/crearRol', 'admin\RolesController::createRol');

/** Rutas de módulo administración de módulos */
$routes->post('admin/modulos/(:num)', 'admin\ModulosController::getModuleById/$1');
$routes->post('admin/modulos/obtenerModulosLoseta', 'admin\ModulosController::getModulesByLosetaId');
$routes->post('admin/modulos/crearModulo', 'admin\ModulosController::createModule');
$routes->post('admin/modulos/actualizarModulo', 'admin\ModulosController::updateModule');
$routes->post('admin/modulos/buscarModulo', 'admin\ModulosController::findModuleByName');
$routes->post('admin/modulos/quitarModulo', 'admin\ModulosController::deleteModule');

/** Rutas de módulo administración de acciones */
$routes->post('admin/acciones/(:num)', 'admin\AccionesController::getAccionById/$1');
$routes->post('admin/acciones/crearAccion', 'admin\AccionesController::createAccion');
$routes->post('admin/acciones/actualizarAccion', 'admin\AccionesController::updateAccion');
$routes->post('admin/acciones/buscarRol', 'admin\AccionesController::findRolByName');
$routes->post('admin/acciones/obtenerAccionesRol', 'admin\AccionesController::getAllAccionByRol');
$routes->post('admin/acciones/quitarAccionRol', 'admin\AccionesController::deleteRolAccion');
$routes->post('admin/acciones/asignarAccionRol', 'admin\AccionesController::addRolAccion');

/** Rutas de módulo administración de pestañas */
$routes->post('admin/tabs/(:num)', 'admin\TabsController::getTabById/$1');
$routes->post('admin/tabs/crearTab', 'admin\TabsController::createTab');
$routes->post('admin/tabs/actualizarTab', 'admin\TabsController::updateTab');
$routes->post('admin/tabs/buscarRol', 'admin\TabsController::findRolByName');
$routes->post('admin/tabs/obtenerTabsRol', 'admin\TabsController::getAllTabByRol');
$routes->post('admin/tabs/quitarTabRol', 'admin\TabsController::deleteRolTab');
$routes->post('admin/tabs/asignarTabRol', 'admin\TabsController::addRolTab');


/** Rutas de la loseta "Maestros".*/
$routes->get('maestros/onboarding/(:num)', 'maestros\MaestrosController::index/$1');
$routes->get('maestros/empresas/(:num)', 'maestros\EmpresasController::index/$1');

/** Rutas de módulo administración de empresas */
$routes->post('maestros/empresas/(:num)', 'maestros\EmpresasController::getEmpresaById/$1');
$routes->post('maestros/empresas/siguienteCodigo', 'maestros\EmpresasController::siguienteCodigo');
$routes->post('maestros/empresas/crearEmpresa', 'maestros\EmpresasController::createEmpresa');
$routes->post('maestros/empresas/actualizarEmpresa', 'maestros\EmpresasController::updateEmpresa');
$routes->post('maestros/empresas/eliminarEmpresa', 'maestros\EmpresasController::eliminarEmpresa');
$routes->post('maestros/empresas/buscarTercero', 'maestros\EmpresasController::buscarTercero');

/** Rutas de módulo administración de parámetros generales */
$routes->get('maestros/parametros-generales/(:num)', 'maestros\ParametrosController::index/$1');
$routes->post('maestros/parametros-generales/listar', 'maestros\ParametrosController::listarParametros');
$routes->post('maestros/parametros-generales/obtener', 'maestros\ParametrosController::obtenerParametro');
$routes->post('maestros/parametros-generales/crear', 'maestros\ParametrosController::crearParametro');
$routes->post('maestros/parametros-generales/actualizar', 'maestros\ParametrosController::actualizarParametro');
$routes->post('maestros/parametros-generales/eliminar', 'maestros\ParametrosController::eliminarParametro');
$routes->post('maestros/parametros-generales/tablas', 'maestros\ParametrosController::tablasOrigen');
$routes->post('maestros/parametros-generales/columnas', 'maestros\ParametrosController::columnasOrigen');
$routes->post('maestros/parametros-generales/valores', 'maestros\ParametrosController::valoresOrigen');

/** Rutas de módulo administración de entidades auxiliares */
$routes->get('maestros/entidades-auxiliares/(:num)', 'maestros\EntidadesController::index/$1');
$routes->post('maestros/entidades-auxiliares/entidades', 'maestros\EntidadesController::entidades');
$routes->post('maestros/entidades-auxiliares/metadatos', 'maestros\EntidadesController::metadatos');
$routes->post('maestros/entidades-auxiliares/listar', 'maestros\EntidadesController::listar');
$routes->post('maestros/entidades-auxiliares/obtener', 'maestros\EntidadesController::obtener');
$routes->post('maestros/entidades-auxiliares/crear', 'maestros\EntidadesController::crear');
$routes->post('maestros/entidades-auxiliares/actualizar', 'maestros\EntidadesController::actualizar');
$routes->post('maestros/entidades-auxiliares/eliminar', 'maestros\EntidadesController::eliminar');

/** Rutas de módulo administración de tipos de documento */
$routes->get('maestros/tipos-documentos/(:num)', 'maestros\TiposDocumentosController::index/$1');
$routes->post('maestros/tipos-documentos/listar', 'maestros\TiposDocumentosController::listar');
$routes->post('maestros/tipos-documentos/obtener', 'maestros\TiposDocumentosController::obtener');
$routes->post('maestros/tipos-documentos/crear', 'maestros\TiposDocumentosController::crear');
$routes->post('maestros/tipos-documentos/actualizar', 'maestros\TiposDocumentosController::actualizar');
$routes->post('maestros/tipos-documentos/eliminar', 'maestros\TiposDocumentosController::eliminar');

/** Rutas de módulo administración de tipos de transacción */
$routes->get('maestros/tipos-transaccion/registro/(:num)', 'maestros\TiposTransaccionController::index/$1');
$routes->post('maestros/tipos-transaccion/registro/listar', 'maestros\TiposTransaccionController::listar');
$routes->post('maestros/tipos-transaccion/registro/obtener', 'maestros\TiposTransaccionController::obtener');
$routes->post('maestros/tipos-transaccion/registro/crear', 'maestros\TiposTransaccionController::crear');
$routes->post('maestros/tipos-transaccion/registro/actualizar', 'maestros\TiposTransaccionController::actualizar');
$routes->post('maestros/tipos-transaccion/registro/eliminar', 'maestros\TiposTransaccionController::eliminar');

/** Rutas de la loseta "Gestión Palma".*/
$routes->get('gestion-palma/onboarding/(:num)', 'gestionpalma\GestionPalmaController::index/$1');
$routes->get('gestion-palma/fincas/(:num)', 'gestionpalma\FincasController::index/$1');
$routes->post('gestion-palma/fincas/listar', 'gestionpalma\FincasController::listarFincas');
$routes->post('gestion-palma/fincas/obtener', 'gestionpalma\FincasController::obtenerFinca');
$routes->post('gestion-palma/fincas/crear', 'gestionpalma\FincasController::crearFinca');
$routes->post('gestion-palma/fincas/actualizar', 'gestionpalma\FincasController::actualizarFinca');
$routes->post('gestion-palma/fincas/eliminar', 'gestionpalma\FincasController::eliminarFinca');
$routes->post('gestion-palma/fincas/cambiar-estado', 'gestionpalma\FincasController::cambiarEstadoFinca');
$routes->post('gestion-palma/fincas/buscar-ciudad', 'gestionpalma\FincasController::buscarCiudadFinca');
$routes->get('gestion-palma/secciones/(:num)', 'gestionpalma\SeccionesController::index/$1');
$routes->post('gestion-palma/secciones/listar', 'gestionpalma\SeccionesController::listarSecciones');
$routes->post('gestion-palma/secciones/obtener', 'gestionpalma\SeccionesController::obtenerSeccion');
$routes->post('gestion-palma/secciones/crear', 'gestionpalma\SeccionesController::crearSeccion');
$routes->post('gestion-palma/secciones/actualizar', 'gestionpalma\SeccionesController::actualizarSeccion');
$routes->post('gestion-palma/secciones/eliminar', 'gestionpalma\SeccionesController::eliminarSeccion');
$routes->post('gestion-palma/secciones/cambiar-estado', 'gestionpalma\SeccionesController::cambiarEstadoSeccion');
$routes->get('gestion-palma/contabilidad/periodos/(:num)', 'gestionpalma\ContabilidadController::periodos/$1');
$routes->post('gestion-palma/contabilidad/periodos/listar', 'gestionpalma\ContabilidadController::listarPeriodos');
$routes->post('gestion-palma/contabilidad/periodos/obtener', 'gestionpalma\ContabilidadController::obtenerPeriodo');
$routes->post('gestion-palma/contabilidad/periodos/crear', 'gestionpalma\ContabilidadController::crearPeriodo');
$routes->post('gestion-palma/contabilidad/periodos/actualizar', 'gestionpalma\ContabilidadController::actualizarPeriodo');
$routes->post('gestion-palma/contabilidad/periodos/eliminar', 'gestionpalma\ContabilidadController::eliminarPeriodo');
$routes->post('gestion-palma/contabilidad/periodos/alternar', 'gestionpalma\ContabilidadController::alternarCerrado');
$routes->post('gestion-palma/contabilidad/periodos/generar-anio', 'gestionpalma\ContabilidadController::generarAnio');
$routes->post('gestion-palma/contabilidad/periodos/cerrar-anio', 'gestionpalma\ContabilidadController::cerrarAnio');
$routes->post('gestion-palma/contabilidad/periodos/eliminar-anio', 'gestionpalma\ContabilidadController::eliminarAnio');
$routes->get('gestion-palma/contabilidad/terceros/(:num)', 'gestionpalma\ContabilidadController::terceros/$1');
$routes->post('gestion-palma/contabilidad/terceros/listar', 'gestionpalma\ContabilidadController::listarTerceros');
$routes->post('gestion-palma/contabilidad/terceros/obtener', 'gestionpalma\ContabilidadController::obtenerTercero');
$routes->post('gestion-palma/contabilidad/terceros/crear', 'gestionpalma\ContabilidadController::crearTercero');
$routes->post('gestion-palma/contabilidad/terceros/actualizar', 'gestionpalma\ContabilidadController::actualizarTercero');
$routes->post('gestion-palma/contabilidad/terceros/eliminar', 'gestionpalma\ContabilidadController::eliminarTercero');
$routes->post('gestion-palma/contabilidad/terceros/cambiar-estado', 'gestionpalma\ContabilidadController::cambiarEstadoTercero');
$routes->post('gestion-palma/contabilidad/terceros/siguiente-id', 'gestionpalma\ContabilidadController::siguienteIdTercero');
$routes->post('gestion-palma/contabilidad/terceros/buscar-ciudad', 'gestionpalma\ContabilidadController::buscarCiudad');
$routes->get('gestion-palma/nomina/periodos/(:num)', 'gestionpalma\NominaController::periodos/$1');
$routes->post('gestion-palma/nomina/periodos/listar', 'gestionpalma\NominaController::listarPeriodosNomina');
$routes->post('gestion-palma/nomina/periodos/obtener', 'gestionpalma\NominaController::obtenerPeriodoNomina');
$routes->post('gestion-palma/nomina/periodos/crear', 'gestionpalma\NominaController::crearPeriodoNomina');
$routes->post('gestion-palma/nomina/periodos/actualizar', 'gestionpalma\NominaController::actualizarPeriodoNomina');
$routes->post('gestion-palma/nomina/periodos/eliminar', 'gestionpalma\NominaController::eliminarPeriodoNomina');
$routes->post('gestion-palma/nomina/periodos/alternar', 'gestionpalma\NominaController::alternarCerradoNomina');
$routes->get('gestion-palma/nomina/empleados/(:num)', 'gestionpalma\NominaController::empleados/$1');
$routes->post('gestion-palma/nomina/empleados/listar', 'gestionpalma\NominaController::listarEmpleados');
$routes->post('gestion-palma/nomina/empleados/obtener', 'gestionpalma\NominaController::obtenerEmpleado');
$routes->post('gestion-palma/nomina/empleados/crear', 'gestionpalma\NominaController::crearEmpleado');
$routes->post('gestion-palma/nomina/empleados/actualizar', 'gestionpalma\NominaController::actualizarEmpleado');
$routes->post('gestion-palma/nomina/empleados/eliminar', 'gestionpalma\NominaController::eliminarEmpleado');
$routes->post('gestion-palma/nomina/empleados/buscar-tercero', 'gestionpalma\NominaController::buscarTercero');
$routes->get('gestion-palma/parametros/variedad/(:num)', 'gestionpalma\ParametrosAgronomicosController::variedad/$1');
$routes->post('gestion-palma/parametros/variedad/listar', 'gestionpalma\ParametrosAgronomicosController::listarVariedades');
$routes->post('gestion-palma/parametros/variedad/obtener', 'gestionpalma\ParametrosAgronomicosController::obtenerVariedad');
$routes->post('gestion-palma/parametros/variedad/crear', 'gestionpalma\ParametrosAgronomicosController::crearVariedad');
$routes->post('gestion-palma/parametros/variedad/actualizar', 'gestionpalma\ParametrosAgronomicosController::actualizarVariedad');
$routes->post('gestion-palma/parametros/variedad/eliminar', 'gestionpalma\ParametrosAgronomicosController::eliminarVariedad');
$routes->post('gestion-palma/parametros/variedad/cambiar-estado', 'gestionpalma\ParametrosAgronomicosController::cambiarEstadoVariedad');
$routes->post('gestion-palma/parametros/variedad/siguiente-codigo', 'gestionpalma\ParametrosAgronomicosController::siguienteCodigoVariedad');
$routes->get('gestion-palma/parametros/unidad-medida/(:num)', 'gestionpalma\ParametrosAgronomicosController::unidadMedida/$1');
$routes->post('gestion-palma/parametros/unidad-medida/listar', 'gestionpalma\ParametrosAgronomicosController::listarUnidades');
$routes->post('gestion-palma/parametros/unidad-medida/obtener', 'gestionpalma\ParametrosAgronomicosController::obtenerUnidad');
$routes->post('gestion-palma/parametros/unidad-medida/crear', 'gestionpalma\ParametrosAgronomicosController::crearUnidad');
$routes->post('gestion-palma/parametros/unidad-medida/actualizar', 'gestionpalma\ParametrosAgronomicosController::actualizarUnidad');
$routes->post('gestion-palma/parametros/unidad-medida/eliminar', 'gestionpalma\ParametrosAgronomicosController::eliminarUnidad');
$routes->post('gestion-palma/parametros/unidad-medida/cambiar-estado', 'gestionpalma\ParametrosAgronomicosController::cambiarEstadoUnidad');
$routes->get('gestion-palma/lotes/registro/(:num)', 'gestionpalma\LotesController::registro/$1');
$routes->post('gestion-palma/lotes/registro/listar', 'gestionpalma\LotesController::listarLotes');
$routes->post('gestion-palma/lotes/registro/obtener', 'gestionpalma\LotesController::obtenerLote');
$routes->post('gestion-palma/lotes/registro/crear', 'gestionpalma\LotesController::crearLote');
$routes->post('gestion-palma/lotes/registro/actualizar', 'gestionpalma\LotesController::actualizarLote');
$routes->post('gestion-palma/lotes/registro/eliminar', 'gestionpalma\LotesController::eliminarLote');
$routes->post('gestion-palma/lotes/registro/cambiar-estado', 'gestionpalma\LotesController::cambiarEstadoLote');
$routes->post('gestion-palma/lotes/registro/lineas/listar', 'gestionpalma\LotesController::listarLineasLote');
$routes->post('gestion-palma/lotes/registro/lineas/crear', 'gestionpalma\LotesController::crearLineaLote');
$routes->post('gestion-palma/lotes/registro/lineas/actualizar', 'gestionpalma\LotesController::actualizarLineaLote');
$routes->post('gestion-palma/lotes/registro/lineas/eliminar', 'gestionpalma\LotesController::eliminarLineaLote');
$routes->get('gestion-palma/lotes/peso-rff/(:num)', 'gestionpalma\LotesController::pesoRff/$1');
$routes->post('gestion-palma/lotes/peso-rff/listar', 'gestionpalma\LotesController::listarPesos');
$routes->post('gestion-palma/lotes/peso-rff/obtener', 'gestionpalma\LotesController::obtenerPeso');
$routes->post('gestion-palma/lotes/peso-rff/crear', 'gestionpalma\LotesController::crearPeso');
$routes->post('gestion-palma/lotes/peso-rff/actualizar', 'gestionpalma\LotesController::actualizarPeso');
$routes->post('gestion-palma/lotes/peso-rff/eliminar', 'gestionpalma\LotesController::eliminarPeso');
$routes->post('gestion-palma/lotes/peso-rff/lotes-por-finca', 'gestionpalma\LotesController::lotesPorFinca');
$routes->get('gestion-palma/labores/grupos/(:num)', 'gestionpalma\LaboresController::grupos/$1');
$routes->post('gestion-palma/labores/grupos/listar', 'gestionpalma\LaboresController::listarGrupos');
$routes->post('gestion-palma/labores/grupos/obtener', 'gestionpalma\LaboresController::obtenerGrupo');
$routes->post('gestion-palma/labores/grupos/crear', 'gestionpalma\LaboresController::crearGrupo');
$routes->post('gestion-palma/labores/grupos/actualizar', 'gestionpalma\LaboresController::actualizarGrupo');
$routes->post('gestion-palma/labores/grupos/eliminar', 'gestionpalma\LaboresController::eliminarGrupo');
$routes->post('gestion-palma/labores/grupos/cambiar-estado', 'gestionpalma\LaboresController::cambiarEstadoGrupo');
$routes->post('gestion-palma/labores/grupos/siguiente-codigo', 'gestionpalma\LaboresController::siguienteCodigoGrupo');
$routes->get('gestion-palma/labores/registro/(:num)', 'gestionpalma\LaboresController::registro/$1');
$routes->post('gestion-palma/labores/registro/listar', 'gestionpalma\LaboresController::listarLabores');
$routes->post('gestion-palma/labores/registro/obtener', 'gestionpalma\LaboresController::obtenerLabor');
$routes->post('gestion-palma/labores/registro/crear', 'gestionpalma\LaboresController::crearLabor');
$routes->post('gestion-palma/labores/registro/actualizar', 'gestionpalma\LaboresController::actualizarLabor');
$routes->post('gestion-palma/labores/registro/eliminar', 'gestionpalma\LaboresController::eliminarLabor');
$routes->post('gestion-palma/labores/registro/cambiar-estado', 'gestionpalma\LaboresController::cambiarEstadoLabor');
$routes->get('gestion-palma/labores/items/(:num)', 'gestionpalma\LaboresController::items/$1');
$routes->post('gestion-palma/labores/items/listar', 'gestionpalma\LaboresController::listarItems');
$routes->post('gestion-palma/labores/items/obtener', 'gestionpalma\LaboresController::obtenerItem');
$routes->post('gestion-palma/labores/items/crear', 'gestionpalma\LaboresController::crearItem');
$routes->post('gestion-palma/labores/items/actualizar', 'gestionpalma\LaboresController::actualizarItem');
$routes->post('gestion-palma/labores/items/eliminar', 'gestionpalma\LaboresController::eliminarItem');
$routes->post('gestion-palma/labores/items/cambiar-estado', 'gestionpalma\LaboresController::cambiarEstadoItem');
$routes->post('gestion-palma/labores/items/siguiente-codigo', 'gestionpalma\LaboresController::siguienteCodigoItem');
$routes->get('gestion-palma/precios/por-labor/(:num)', 'gestionpalma\ListaPreciosController::porLabor/$1');
$routes->post('gestion-palma/precios/por-labor/listar', 'gestionpalma\ListaPreciosController::listarAnios');
$routes->post('gestion-palma/precios/por-labor/detalle', 'gestionpalma\ListaPreciosController::detalleAnio');
$routes->post('gestion-palma/precios/por-labor/guardar', 'gestionpalma\ListaPreciosController::guardarPrecios');
$routes->post('gestion-palma/precios/por-labor/crear-anio', 'gestionpalma\ListaPreciosController::crearAnio');
$routes->post('gestion-palma/precios/por-labor/eliminar-anio', 'gestionpalma\ListaPreciosController::eliminarAnio');
$routes->get('gestion-palma/precios/por-lote/(:num)', 'gestionpalma\ListaPreciosController::porLote/$1');
$routes->post('gestion-palma/precios/por-lote/listar', 'gestionpalma\ListaPreciosController::listarPreciosLote');
$routes->post('gestion-palma/precios/por-lote/obtener', 'gestionpalma\ListaPreciosController::obtenerPrecioLote');
$routes->post('gestion-palma/precios/por-lote/crear', 'gestionpalma\ListaPreciosController::crearPrecioLote');
$routes->post('gestion-palma/precios/por-lote/actualizar', 'gestionpalma\ListaPreciosController::actualizarPrecioLote');
$routes->post('gestion-palma/precios/por-lote/eliminar', 'gestionpalma\ListaPreciosController::eliminarPrecioLote');
$routes->post('gestion-palma/precios/por-lote/lotes-finca', 'gestionpalma\ListaPreciosController::lotesDeFinca');
$routes->post('gestion-palma/precios/por-lote/precio-base', 'gestionpalma\ListaPreciosController::precioBaseLabor');
$routes->get('gestion-palma/precios/reliquidar/(:num)', 'gestionpalma\ListaPreciosController::reliquidar/$1');
$routes->get('gestion-palma/caracteristicas/registro/(:num)', 'gestionpalma\CaracteristicasController::registro/$1');
$routes->post('gestion-palma/caracteristicas/registro/listar', 'gestionpalma\CaracteristicasController::listarCaracteristicas');
$routes->post('gestion-palma/caracteristicas/registro/obtener', 'gestionpalma\CaracteristicasController::obtenerCaracteristica');
$routes->post('gestion-palma/caracteristicas/registro/crear', 'gestionpalma\CaracteristicasController::crearCaracteristica');
$routes->post('gestion-palma/caracteristicas/registro/actualizar', 'gestionpalma\CaracteristicasController::actualizarCaracteristica');
$routes->post('gestion-palma/caracteristicas/registro/eliminar', 'gestionpalma\CaracteristicasController::eliminarCaracteristica');
$routes->post('gestion-palma/caracteristicas/registro/cambiar-estado', 'gestionpalma\CaracteristicasController::cambiarEstadoCaracteristica');
$routes->post('gestion-palma/caracteristicas/registro/siguiente-codigo', 'gestionpalma\CaracteristicasController::siguienteCodigoCaracteristica');

/** Rutas de la loseta "Transacciones".*/
$routes->get('transacciones/onboarding/(:num)', 'transacciones\TransaccionesController::index/$1');
$routes->get('transacciones/produccion/(:num)', 'transacciones\ProduccionController::index/$1');
$routes->post('transacciones/produccion/lotes', 'transacciones\ProduccionController::lotes');
$routes->post('transacciones/produccion/pesoPromedio', 'transacciones\ProduccionController::pesoPromedio');
$routes->post('transacciones/produccion/buscarTiquete', 'transacciones\ProduccionController::buscarTiquete');
$routes->post('transacciones/produccion/terceros', 'transacciones\ProduccionController::terceros');
$routes->post('transacciones/produccion/labores', 'transacciones\ProduccionController::labores');
$routes->post('transacciones/produccion/precioLabor', 'transacciones\ProduccionController::precioLabor');
$routes->post('transacciones/produccion/previsualizar', 'transacciones\ProduccionController::previsualizar');
$routes->post('transacciones/produccion/guardar', 'transacciones\ProduccionController::guardar');
$routes->post('transacciones/produccion/consultar', 'transacciones\ProduccionController::consultar');
$routes->post('transacciones/produccion/detalle', 'transacciones\ProduccionController::detalle');
$routes->post('transacciones/produccion/periodos', 'transacciones\ProduccionController::periodos');
$routes->post('transacciones/produccion/eliminar', 'transacciones\ProduccionController::eliminar');
$routes->post('transacciones/produccion/actualizar', 'transacciones\ProduccionController::actualizar');
$routes->get('transacciones/labores/(:num)', 'transacciones\LaboresController::index/$1');
$routes->post('transacciones/labores/periodos', 'transacciones\LaboresController::periodos');
$routes->post('transacciones/labores/secciones', 'transacciones\LaboresController::secciones');
$routes->post('transacciones/labores/lotes', 'transacciones\LaboresController::lotes');
$routes->post('transacciones/labores/terceros', 'transacciones\LaboresController::terceros');
$routes->post('transacciones/labores/liquidar', 'transacciones\LaboresController::liquidar');
$routes->post('transacciones/labores/guardar', 'transacciones\LaboresController::guardar');
$routes->post('transacciones/labores/actualizar', 'transacciones\LaboresController::actualizar');
$routes->post('transacciones/labores/consultar', 'transacciones\LaboresController::consultar');
$routes->post('transacciones/labores/detalle', 'transacciones\LaboresController::detalle');
$routes->post('transacciones/labores/eliminar', 'transacciones\LaboresController::eliminar');
$routes->get('transacciones/fertilizacion/(:num)', 'transacciones\FertilizacionController::index/$1');
$routes->post('transacciones/fertilizacion/periodos', 'transacciones\FertilizacionController::periodos');
$routes->post('transacciones/fertilizacion/secciones', 'transacciones\FertilizacionController::secciones');
$routes->post('transacciones/fertilizacion/planes', 'transacciones\FertilizacionController::planes');
$routes->post('transacciones/fertilizacion/lotes', 'transacciones\FertilizacionController::lotes');
$routes->post('transacciones/fertilizacion/lotes-finca', 'transacciones\FertilizacionController::lotesFinca');
$routes->post('transacciones/fertilizacion/terceros', 'transacciones\FertilizacionController::terceros');
$routes->post('transacciones/fertilizacion/liquidar', 'transacciones\FertilizacionController::liquidar');
$routes->post('transacciones/fertilizacion/guardar', 'transacciones\FertilizacionController::guardar');
$routes->post('transacciones/fertilizacion/actualizar', 'transacciones\FertilizacionController::actualizar');
$routes->post('transacciones/fertilizacion/consultar', 'transacciones\FertilizacionController::consultar');
$routes->post('transacciones/fertilizacion/detalle', 'transacciones\FertilizacionController::detalle');
$routes->post('transacciones/fertilizacion/eliminar', 'transacciones\FertilizacionController::eliminar');

/** Rutas de la loseta de  "Dashboards".*/
$routes->get('dashboards/onboarding/(:num)', 'dashboards\DashboardsController::index/$1');

/** Rutas de la loseta de  "Reportes".*/
$routes->get('informes/onboarding/(:num)', 'informes\InformeController::index/$1');
$routes->get('informes/liquidacion/(:num)', 'informes\InformeController::liquidacion/$1');
$routes->get('informes/contabilizar/(:num)', 'informes\InformeController::contabilizar/$1');

$routes->post('informes/temporales', 'informes\InformeController::temporales');
$routes->post('informes/empleadosSinTemporal', 'informes\InformeController::empleadosSinTemporal');
$routes->post('informes/consultar-reporte', 'informes\InformeController::consultarReporte');
// $routes->get('informes/procesar-reporte', 'informes\InformeController::procesarReporte');
$routes->post('informes/exportar-excel', 'informes\InformeController::exportarExcel');
$routes->post('informes/verificar-procesado', 'informes\InformeController::verificarProcesado');
$routes->post('informes/procesar-reporte', 'informes\InformeController::procesarReporte');
$routes->post('informes/actualizar-valores-temporal', 'informes\InformeController::actualizarValoresTemporal');
$routes->post('informes/total-pagos', 'informes\InformeController::totalPagos');
$routes->post('informes/pagos-labor', 'informes\InformeController::pagosLabor');

$routes->get('informes/generar-txt', 'informes\InformeController::generarTXT');

$routes->get('informes/administracion/(:num)', 'informes\ReportesController::modulo/administracion/$1');
$routes->get('informes/revision-labores/(:num)', 'informes\ReportesController::modulo/revision-labores/$1');
$routes->get('informes/transacciones/(:num)', 'informes\ReportesController::modulo/transacciones/$1');
$routes->get('informes/indicadores/(:num)', 'informes\ReportesController::modulo/indicadores/$1');
$routes->get('informes/sanidad/(:num)', 'informes\ReportesController::modulo/sanidad/$1');
$routes->get('informes/fertilizacion/(:num)', 'informes\ReportesController::modulo/fertilizacion/$1');
$routes->post('informes/reportes/consultar', 'informes\ReportesController::consultar');
$routes->post('informes/reportes/exportar', 'informes\ReportesController::exportar');
$routes->post('informes/reportes/opciones', 'informes\ReportesController::opciones');
