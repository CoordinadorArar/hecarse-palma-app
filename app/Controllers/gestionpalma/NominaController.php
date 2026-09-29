<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\FuncionariosModel;
use App\Models\PeriodosNominaModel;
use App\Models\TercerosModel;

class NominaController extends GestionPalmaController
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /** Topes de longitud de las columnas de cTercero usadas en el alta en línea. */
    private const LIMITES_TERCERO = [
        'codigo'        => [50, 'El código'],
        'tipoDocumento' => [3, 'El tipo de documento'],
        'nit'           => [25, 'El Nit'],
        'dv'            => [3, 'El dígito de verificación'],
        'razonSocial'   => [550, 'La razón social'],
        'apellido1'     => [250, 'El primer apellido'],
        'apellido2'     => [250, 'El segundo apellido'],
        'nombre1'       => [550, 'El primer nombre'],
        'nombre2'       => [250, 'El segundo nombre'],
        'descripcion'   => [950, 'La descripción'],
        'telefono'      => [50, 'El teléfono'],
        'direccion'     => [550, 'La dirección'],
    ];

    private $periodosModel;

    private $funcionariosModel;

    private $tercerosModel;

    /** Mapa empresa => [codigo => descripcion] del catálogo de tipos de nómina. */
    private $tiposNomina = [];

    public function __construct()
    {
        parent::__construct();

        $this->periodosModel     = new PeriodosNominaModel();
        $this->funcionariosModel = new FuncionariosModel();
        $this->tercerosModel     = new TercerosModel();
    }

    /**
     * Vista principal del módulo "Periodos de nómina".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function periodos($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas = $this->periodosModel->getEmpresas();
        $anios    = $this->periodosModel->getAnios();
        $actual   = (int) date('Y');

        if (! in_array($actual, $anios, true)) {
            $anios[] = $actual;
            rsort($anios);
        }

        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;
        $anioSel    = in_array($actual, $anios, true) ? $actual : (int) ($anios[0] ?? $actual);

        $filas = $empresaSel > 0 ? $this->periodosModel->listar($empresaSel, $anioSel) : [];

        $data['title']          = 'Gestión Palma · Periodos de nómina';
        $data['empresas']       = $empresas;
        $data['anios']          = $anios;
        $data['tipos_nomina']   = $this->periodosModel->getTiposNominaPorEmpresa();
        $data['empresa_sel']    = $empresaSel;
        $data['anio_sel']       = $anioSel;
        $data['tabla_periodos'] = $this->renderizarFilas($filas);
        $data['total_periodos'] = count($filas);

        return view('gestionpalma/nomina_periodos', $data);
    }

    /**
     * Vista principal del módulo "Empleados".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function empleados($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->funcionariosModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->funcionariosModel->listar($empresaSel, '', '1') : [];

        $data['title']                = 'Gestión Palma · Empleados';
        $data['empresas']             = $empresas;
        $data['terceros_disponibles'] = $this->funcionariosModel->getTercerosDisponiblesPorEmpresa();
        $data['proveedores']          = $this->funcionariosModel->getProveedoresPorEmpresa();
        $data['tipos_documento']      = $this->tercerosModel->getTiposDocumento();
        $data['empresa_sel']          = $empresaSel;
        $data['tabla_empleados']      = $this->renderizarFilasEmpleados($filas);
        $data['total_empleados']      = count($filas);

        return view('gestionpalma/nomina_empleados', $data);
    }

    // ── Endpoints JSON ────────────────────────────────────────────────────────

    public function listarPeriodosNomina(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->request->getPost('anio');
        $anio = ($anio === null || $anio === '') ? null : (int) $anio;

        $filas = $this->periodosModel->listar($empresa, $anio, (string) $this->request->getPost('busqueda'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilas($filas),
        ]);
    }

    public function obtenerPeriodoNomina(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $periodo = $this->periodosModel->obtener(
            $empresa,
            (int) $this->request->getPost('anio'),
            (int) $this->request->getPost('mes'),
            (int) $this->request->getPost('noPeriodo')
        );

        if ($periodo === null) {
            return $this->error('El periodo no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'periodo' => [
                'empresa'        => (int) $periodo['empresa'],
                'año'            => (int) $periodo['anio'],
                'mes'            => (int) $periodo['mes'],
                'noPeriodo'      => (int) $periodo['noPeriodo'],
                'fechaInicial'   => $this->formatearFecha($periodo['fechaInicial'], 'Y-m-d'),
                'fechaFinal'     => $this->formatearFecha($periodo['fechaFinal'], 'Y-m-d'),
                'fechaCorte'     => $this->formatearFecha($periodo['fechaCorte'], 'Y-m-d'),
                'fechaPago'      => $this->formatearFecha($periodo['fechaPago'], 'Y-m-d'),
                'cerrado'        => (int) $periodo['cerrado'],
                'tipoNomina'     => trim((string) $periodo['tipoNomina']),
                'diasNomina'     => $periodo['diasNomina'] === null ? null : (int) $periodo['diasNomina'],
                'agronomico'     => (int) $periodo['agronomico'],
                'ejecutaLabores' => (int) $periodo['ejecutaLabores'],
                'nombrePeriodo'  => $periodo['nombrePeriodo'],
            ],
        ]);
    }

    public function crearPeriodoNomina(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave = $this->claveValida();

        if (is_string($clave)) {
            return $this->error($clave);
        }

        [$anio, $mes, $noPeriodo] = $clave;

        if ($this->periodosModel->existe($empresa, $anio, $mes, $noPeriodo)) {
            return $this->error("Ya existe el periodo {$noPeriodo} del mes {$mes} de {$anio} para esta empresa.");
        }

        $datos = $this->datosPeriodo($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $usuario = trim((string) $this->session->get('usu_login'));

        $this->periodosModel->crear(
            $empresa,
            $anio,
            $mes,
            $noPeriodo,
            $datos,
            $this->session->get('usu_id'),
            $usuario !== '' ? $usuario : 'WEB'
        );

        return $this->respuestaTabla($empresa, 'Periodo creado correctamente.');
    }

    public function actualizarPeriodoNomina(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave = $this->claveValida();

        if (is_string($clave)) {
            return $this->error($clave);
        }

        [$anio, $mes, $noPeriodo] = $clave;

        if (! $this->periodosModel->existe($empresa, $anio, $mes, $noPeriodo)) {
            return $this->error('El periodo no existe.');
        }

        $datos = $this->datosPeriodo($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->periodosModel->actualizar($empresa, $anio, $mes, $noPeriodo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, 'Periodo actualizado correctamente.');
    }

    public function eliminarPeriodoNomina(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio      = (int) $this->request->getPost('anio');
        $mes       = (int) $this->request->getPost('mes');
        $noPeriodo = (int) $this->request->getPost('noPeriodo');

        if (! $this->periodosModel->existe($empresa, $anio, $mes, $noPeriodo)) {
            return $this->error('El periodo no existe.');
        }

        if ($this->periodosModel->tieneMovimientos($empresa, $anio, $mes, $noPeriodo)) {
            return $this->error('El periodo tiene nómina liquidada o conceptos fijos asociados y no puede eliminarse.');
        }

        $this->periodosModel->eliminar($empresa, $anio, $mes, $noPeriodo, $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, 'Periodo eliminado correctamente.');
    }

    public function alternarCerradoNomina(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio      = (int) $this->request->getPost('anio');
        $mes       = (int) $this->request->getPost('mes');
        $noPeriodo = (int) $this->request->getPost('noPeriodo');
        $periodo   = $this->periodosModel->obtener($empresa, $anio, $mes, $noPeriodo);

        if ($periodo === null) {
            return $this->error('El periodo no existe.');
        }

        $cerrado = (int) $periodo['cerrado'] === 1 ? 0 : 1;

        $this->periodosModel->alternarCerrado($empresa, $anio, $mes, $noPeriodo, $cerrado, $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, $cerrado === 1 ? 'Periodo cerrado.' : 'Periodo abierto.');
    }

    // ── Endpoints JSON de empleados ───────────────────────────────────────────

    public function listarEmpleados(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->funcionariosModel->listar(
            $empresa,
            (string) $this->request->getPost('busqueda'),
            $this->campo('estado'),
            $this->campo('vinculo')
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasEmpleados($filas),
        ]);
    }

    public function obtenerEmpleado(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $empleado = $this->funcionariosModel->obtener($empresa, (int) $this->request->getPost('tercero'));

        if ($empleado === null) {
            return $this->error('El empleado no existe.');
        }

        return $this->response->setJSON([
            'success'  => true,
            'empleado' => [
                'empresa'         => (int) $empleado['empresa'],
                'tercero'         => (int) $empleado['tercero'],
                'codigo'          => (string) $empleado['codigo'],
                'descripcion'     => (string) $empleado['descripcion'],
                'proveedor'       => trim((string) ($empleado['proveedor'] ?? '')),
                'salario'         => $empleado['salario'] === null ? null : (float) $empleado['salario'],
                'fechaIngreso'    => $this->formatearFecha($empleado['fechaIngreso'], 'Y-m-d'),
                'activo'          => (int) $empleado['activo'],
                'conductor'       => (int) $empleado['conductor'],
                'contratista'     => (int) $empleado['contratista'],
                'otros'           => (int) $empleado['otros'],
                'proveedorNombre' => ($pn = trim((string) ($empleado['proveedorNombre'] ?? ''))) !== '' ? $pn : null,
                'terceroNit'      => trim((string) ($empleado['terceroNit'] ?? '')),
                'terceroNombre'   => trim((string) ($empleado['terceroNombre'] ?? '')),
            ],
        ]);
    }

    public function crearEmpleado(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $usu_id = $this->session->get('usu_id');

        if ($this->bit('crea_tercero') === 0) {
            $datos = $this->datosEmpleado();

            if (is_string($datos)) {
                return $this->error($datos);
            }

            $tercero = (int) $this->request->getPost('tercero');

            if ($tercero <= 0 || ! $this->funcionariosModel->terceroExiste($empresa, $tercero)) {
                return $this->error('Debe seleccionar un tercero válido.');
            }

            if ($this->funcionariosModel->existe($empresa, $tercero)) {
                return $this->error('El tercero ya está registrado como empleado.');
            }

            $this->funcionariosModel->crear($empresa, $tercero, $datos, $usu_id);

            return $this->respuestaTablaEmpleados($empresa, 'Empleado creado correctamente.');
        }

        $tercero = $this->datosTerceroEnLinea($empresa);

        if (is_string($tercero)) {
            return $this->error($tercero);
        }

        $datos = $this->datosEmpleado($tercero['codigo'], $tercero['descripcion']);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $db = db_connect();
        $db->transStart();

        $id = $this->tercerosModel->crear($empresa, $tercero, $usu_id);

        if ($id !== null) {
            $this->funcionariosModel->crear($empresa, $id, $datos, $usu_id);
        }

        $db->transComplete();

        if ($id === null || $db->transStatus() === false) {
            return $this->error('No fue posible crear el empleado. Intente nuevamente.');
        }

        return $this->respuestaTablaEmpleados($empresa, 'Empleado creado correctamente.');
    }

    public function actualizarEmpleado(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $tercero = (int) $this->request->getPost('tercero');

        if (! $this->funcionariosModel->existe($empresa, $tercero)) {
            return $this->error('El empleado no existe.');
        }

        $datos = $this->datosEmpleado();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->funcionariosModel->actualizar($empresa, $tercero, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaEmpleados($empresa, 'Empleado actualizado correctamente.');
    }

    public function eliminarEmpleado(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $tercero = (int) $this->request->getPost('tercero');

        if (! $this->funcionariosModel->existe($empresa, $tercero)) {
            return $this->error('El empleado no existe.');
        }

        if ($this->funcionariosModel->tieneMovimientos($empresa, $tercero)) {
            return $this->error('El empleado tiene contratos o movimientos de nómina asociados y no puede eliminarse.');
        }

        $this->funcionariosModel->eliminar($empresa, $tercero, $this->session->get('usu_id'));

        return $this->respuestaTablaEmpleados($empresa, 'Empleado eliminado correctamente.');
    }

    public function buscarTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $tercero = $this->funcionariosModel->obtenerTercero($empresa, (int) $this->request->getPost('tercero'));

        if ($tercero === null) {
            return $this->error('El tercero no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'tercero' => [
                'id'     => (int) $tercero['id'],
                'nit'    => trim((string) $tercero['nit']),
                'nombre' => trim((string) ($tercero['nombre'] ?? '')),
            ],
        ]);
    }

    // ── Utilidades internas ───────────────────────────────────────────────────

    /**
     * Corta la petición con 401 cuando no hay sesión activa.
     */
    private function sesionRequerida(): ?ResponseInterface
    {
        if (! empty($this->session->get('usu_id'))) {
            return null;
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Sesión expirada. Vuelva a iniciar sesión.',
        ])->setStatusCode(401);
    }

    /**
     * Empresa recibida por POST, o null si no es válida.
     */
    private function empresaValida(): ?int
    {
        $empresa = $this->request->getPost('empresa');

        if ($empresa === null || ! is_numeric($empresa) || ! $this->periodosModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
    }

    /**
     * Valida la llave del periodo recibida por POST.
     *
     * @return array|string Terna [anio, mes, noPeriodo] o el mensaje de error.
     */
    private function claveValida(): array|string
    {
        $anio      = (int) $this->request->getPost('anio');
        $mes       = (int) $this->request->getPost('mes');
        $noPeriodo = (int) $this->request->getPost('noPeriodo');

        if ($anio < 1900 || $anio > 2999) {
            return 'El año no es válido.';
        }

        if ($mes < 1 || $mes > 12) {
            return 'El mes debe estar entre 1 y 12.';
        }

        if ($noPeriodo < 1 || $noPeriodo > 60) {
            return 'El número de periodo debe estar entre 1 y 60.';
        }

        return [$anio, $mes, $noPeriodo];
    }

    /**
     * Valida el formulario del periodo y arma las columnas a persistir.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosPeriodo(int $empresa): array|string
    {
        $tipoNomina = $this->campo('tipoNomina');

        if ($tipoNomina === '') {
            return 'Debe seleccionar el tipo de nómina.';
        }

        if (! $this->periodosModel->tipoNominaValido($empresa, $tipoNomina)) {
            return 'El tipo de nómina seleccionado no es válido para esta empresa.';
        }

        $dias = $this->campo('diasNomina');

        if ($dias !== '') {
            if (! ctype_digit($dias) || (int) $dias < 1 || (int) $dias > 31) {
                return 'Los días de nómina deben ser un número entre 1 y 31.';
            }

            $dias = (int) $dias;
        } else {
            $dias = null;
        }

        $inicial = $this->campo('fechaInicial');
        $final   = $this->campo('fechaFinal');
        $corte   = $this->campo('fechaCorte');
        $pago    = $this->campo('fechaPago');

        $inicial = $inicial !== '' ? $inicial : null;
        $final   = $final !== '' ? $final : null;
        $corte   = $corte !== '' ? $corte : null;
        $pago    = $pago !== '' ? $pago : null;

        if ($inicial !== null && $final !== null && strtotime($final) < strtotime($inicial)) {
            return 'La fecha final no puede ser anterior a la fecha inicial.';
        }

        if ($inicial !== null && $corte !== null && strtotime($corte) < strtotime($inicial)) {
            return 'La fecha de corte no puede ser anterior a la fecha inicial.';
        }

        $nombre = $this->campo('nombrePeriodo');

        if (strlen($nombre) > 250) {
            return 'El nombre del periodo no puede superar los 250 caracteres.';
        }

        return [
            'fechaInicial'   => $inicial,
            'fechaFinal'     => $final,
            'fechaCorte'     => $corte,
            'fechaPago'      => $pago,
            'cerrado'        => $this->bit('cerrado'),
            'tipoNomina'     => $tipoNomina,
            'diasNomina'     => $dias,
            'agronomico'     => $this->bit('agronomico'),
            'ejecutaLabores' => $this->bit('ejecutaLabores'),
            'nombrePeriodo'  => $nombre !== '' ? $nombre : null,
        ];
    }

    /**
     * Valida el formulario del empleado y arma las columnas a persistir.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosEmpleado(string $codigoTercero = '', string $descripcionTercero = ''): array|string
    {
        $codigo = $codigoTercero !== '' ? $codigoTercero : $this->campo('identificacion');

        if ($codigo === '') {
            return 'La identificación es obligatoria.';
        }

        if (strlen($codigo) > 50) {
            return 'La identificación no puede superar los 50 caracteres.';
        }

        $descripcion = $descripcionTercero !== '' ? $descripcionTercero : $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (strlen($descripcion) > 950) {
            return 'La descripción no puede superar los 950 caracteres.';
        }

        $proveedor = $this->campo('proveedor');

        if (strlen($proveedor) > 50) {
            return 'El proveedor no puede superar los 50 caracteres.';
        }

        $salario = str_replace([' ', '$'], '', $this->campo('salario'));

        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $salario)) {
            $salario = str_replace(['.', ','], '', $salario);
        } else {
            $decimal = strrpos($salario, ',') > strrpos($salario, '.') ? ',' : '.';
            $salario = str_replace($decimal === ',' ? '.' : ',', '', $salario);
            $salario = str_replace(',', '.', $salario);
        }

        if ($salario !== '') {
            if (! is_numeric($salario) || (float) $salario < 0) {
                return 'El salario debe ser un número mayor o igual a cero.';
            }

            $salario = (float) $salario;
        } else {
            $salario = null;
        }

        $fechaIngreso = $this->campo('fechaIngreso');

        return [
            'codigo'       => $codigo,
            'descripcion'  => $descripcion,
            'proveedor'    => $proveedor !== '' ? $proveedor : null,
            'salario'      => $salario,
            'fechaIngreso' => $fechaIngreso !== '' ? $fechaIngreso : null,
            'activo'       => $this->bit('activo'),
            'conductor'    => $this->bit('conductor'),
            'contratista'  => $this->bit('contratista'),
            'otros'        => $this->bit('otros'),
        ];
    }

    /**
     * Valida el recuadro "Información del tercero" y arma las columnas de cTercero.
     *
     * @return array|string Columnas listas para el INSERT o el mensaje de error.
     */
    private function datosTerceroEnLinea(int $empresa): array|string
    {
        $tipoDocumento = $this->campo('t_tipoDocumento');

        if ($tipoDocumento === '' || ! $this->tercerosModel->tipoDocumentoExiste($tipoDocumento)) {
            return 'Debe seleccionar un tipo de identificación válido.';
        }

        $codigo = preg_replace('/\D/', '', $this->campo('t_codigo'));

        if ($codigo === '') {
            return 'Debe indicar la identificación del nuevo tercero.';
        }

        if (strlen($codigo) < 5 || strlen($codigo) > 25) {
            return 'La identificación del tercero debe tener entre 5 y 25 dígitos.';
        }

        $apellido1 = $this->campo('t_apellido1');
        $apellido2 = $this->campo('t_apellido2');
        $nombre1   = $this->campo('t_nombre1');
        $nombre2   = $this->campo('t_nombre2');

        $completo    = preg_replace('/\s+/', ' ', trim("{$apellido1} {$apellido2} {$nombre1} {$nombre2}"));
        $descripcion = $this->campo('t_descripcion');

        if ($descripcion === '') {
            $descripcion = $completo;
        }

        if ($descripcion === '') {
            return 'La descripción del tercero es obligatoria.';
        }

        $tipo      = $tipoDocumento === '31' ? 2 : 1;
        $telefono  = $this->campo('t_telefono');
        $direccion = $this->campo('t_direccion');

        if ($this->tercerosModel->nitDuplicado($empresa, $codigo)) {
            return "El Nit {$codigo} ya está registrado para otro tercero.";
        }

        if ($this->tercerosModel->codigoDuplicado($empresa, $codigo)) {
            return "El código {$codigo} ya está registrado.";
        }

        $datos = [
            'codigo'        => $codigo,
            'tipoDocumento' => $tipoDocumento,
            'tipo'          => $tipo,
            'nit'           => $codigo,
            'dv'            => $tipo === 2 ? (string) $this->tercerosModel->calcularDV($codigo) : null,
            'razonSocial'   => mb_substr(($tipo === 2 || $completo === '') ? $descripcion : $completo, 0, self::LIMITES_TERCERO['razonSocial'][0]),
            'apellido1'     => $apellido1,
            'apellido2'     => $apellido2,
            'nombre1'       => $nombre1,
            'nombre2'       => $nombre2 !== '' ? $nombre2 : null,
            'descripcion'   => $descripcion,
            'activo'        => 1,
            'telefono'      => $telefono !== '' ? $telefono : null,
            'direccion'     => $direccion !== '' ? $direccion : null,
        ];

        foreach (self::LIMITES_TERCERO as $columna => [$tope, $etiqueta]) {
            if (mb_strlen((string) ($datos[$columna] ?? '')) > $tope) {
                return "{$etiqueta} no puede superar los {$tope} caracteres.";
            }
        }

        foreach (array_keys(TercerosModel::ROLES) as $rol) {
            $datos[$rol] = $rol === 'empleado' ? 1 : 0;
        }

        return $datos;
    }

    /**
     * Respuesta estándar con la tabla de empleados re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaEmpleados(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->funcionariosModel->listar(
            $empresa,
            (string) $this->request->getPost('filtro_busqueda'),
            $this->campo('filtro_estado'),
            $this->campo('filtro_vinculo')
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasEmpleados($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de empleados.
     */
    private function renderizarFilasEmpleados(array $empleados): string
    {
        $html  = '';
        $vacio = '<span class="text-muted">&mdash;</span>';

        $legado = 'Código heredado del sistema anterior. Al editar el empleado y escoger el proveedor, quedará actualizado.';

        $chip = static fn ($valor, $texto, $si, $no) => (int) $valor === 1
            ? '<span class="gp-chip-rol activo" title="' . $si . '">' . $texto . '</span>'
            : '<span class="gp-chip-rol" title="' . $no . '">' . $texto . '</span>';

        foreach ($empleados as $e) {
            $empresa = (int) $e['empresa'];
            $tercero = (int) $e['tercero'];
            $activo  = (int) $e['activo'] === 1 ? 1 : 0;

            $codigo      = trim((string) $e['codigo']);
            $descripcion = trim((string) $e['descripcion']);

            $fechaOrden = $this->formatearFecha($e['fechaIngreso'], 'Y-m-d');
            $fechaTexto = $this->formatearFecha($e['fechaIngreso'], 'd/m/Y');

            $salario      = (float) ($e['salario'] ?? 0);
            $celdaSalario = $salario > 0
                ? number_format($salario, 2, '.', ',')
                : '<span class="text-muted">0.00</span>';

            $proveedor       = trim((string) ($e['proveedor'] ?? ''));
            $proveedorNombre = trim((string) ($e['proveedorNombre'] ?? ''));

            if ($proveedor === '') {
                $celdaProveedor = '<td class="gp-truncar">' . $vacio . '</td>';
            } elseif ($proveedorNombre !== '') {
                $celdaProveedor = '<td class="gp-truncar" title="' . htmlspecialchars($proveedorNombre) . '">'
                    . htmlspecialchars($proveedorNombre) . '</td>';
            } else {
                $celdaProveedor = '<td class="gp-truncar" title="' . htmlspecialchars($legado) . '">'
                    . '<i class="bi bi-info-circle text-muted"></i> <span class="gp-dato-legado">'
                    . htmlspecialchars($proveedor) . '</span></td>';
            }

            $vinculos = $chip($e['contratista'], 'CTA', 'Contratista', 'No es contratista')
                . ' ' . $chip($e['conductor'], 'CON', 'Conductor interno', 'No es conductor interno')
                . ' ' . $chip($e['otros'], 'OCA', 'Ocasional', 'No es ocasional');

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $descJs = htmlspecialchars(
                str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $descripcion)),
                ENT_QUOTES
            );

            $claveJs = $empresa . ',' . $tercero;

            $html .= '<tr data-empresa="' . $empresa . '" data-tercero="' . $tercero . '" data-activo="' . $activo . '">'
                . '<td class="text-nowrap font-monospace text-muted small">' . ($codigo !== '' ? htmlspecialchars($codigo) : $vacio) . '</td>'
                . '<td data-order="' . htmlspecialchars($descripcion) . '">' . htmlspecialchars($descripcion)
                . '<div class="gp-subtexto font-monospace">Tercero ' . $tercero . '</div></td>'
                . '<td class="text-center text-nowrap" data-order="' . ($fechaOrden ?? '') . '">' . ($fechaTexto ?? $vacio) . '</td>'
                . '<td class="text-end font-monospace" data-order="' . $salario . '">' . $celdaSalario . '</td>'
                . $celdaProveedor
                . '<td class="text-center">' . $vinculos . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarEmpleado(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarEmpleado(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    /**
     * Valor de texto recibido por POST, ya recortado.
     */
    private function campo(string $nombre): string
    {
        return trim((string) $this->request->getPost($nombre));
    }

    /**
     * Normaliza a 0/1 una casilla del formulario.
     */
    private function bit(string $nombre): int
    {
        return in_array($this->campo($nombre), ['1', 'on', 'true'], true) ? 1 : 0;
    }

    /**
     * Normaliza una fecha proveniente de la BD (DateTime o cadena).
     */
    private function formatearFecha($fecha, string $formato): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format($formato);
        }

        if ($fecha === null || $fecha === '') {
            return null;
        }

        $ts = strtotime((string) $fecha);

        return $ts === false ? null : date($formato, $ts);
    }

    private function error(string $mensaje): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode(422);
    }

    /**
     * Respuesta estándar con la tabla re-renderizada según los filtros vigentes.
     */
    private function respuestaTabla(int $empresa, string $mensaje): ResponseInterface
    {
        $anio = $this->request->getPost('filtro_anio');
        $anio = ($anio === null || $anio === '') ? null : (int) $anio;

        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->periodosModel->listar($empresa, $anio, (string) $this->request->getPost('filtro_busqueda'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilas($filas),
        ]);
    }

    /**
     * Descripción capitalizada del tipo de nómina, tomada del catálogo cacheado.
     */
    private function descripcionTipoNomina(int $empresa, string $codigo): string
    {
        if (! isset($this->tiposNomina[$empresa])) {
            $this->tiposNomina[$empresa] = array_column($this->periodosModel->getTiposNomina($empresa), 'descripcion', 'codigo');
        }

        $descripcion = trim((string) ($this->tiposNomina[$empresa][$codigo] ?? ''));

        return $descripcion !== '' ? ucfirst(mb_strtolower($descripcion)) : $codigo;
    }

    /**
     * Construye las filas <tr> de la tabla de periodos de nómina.
     */
    private function renderizarFilas(array $periodos): string
    {
        $html  = '';
        $vacio = '<span class="text-muted">—</span>';

        foreach ($periodos as $p) {
            $empresa   = (int) $p['empresa'];
            $anio      = (int) $p['anio'];
            $mes       = (int) $p['mes'];
            $noPeriodo = (int) $p['noPeriodo'];
            $cerrado   = (int) $p['cerrado'] === 1 ? 1 : 0;

            $nombreMes = self::MESES[$mes] ?? (string) $mes;
            $celdaMes  = str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . ' &mdash; ' . htmlspecialchars($nombreMes);

            $tipoNomina = trim((string) ($p['tipoNomina'] ?? ''));
            $celdaTipo  = $vacio;

            if ($tipoNomina !== '') {
                $celdaTipo = '<span class="badge gp-badge-doc rounded-pill px-2 py-1" title="' . htmlspecialchars($tipoNomina) . '">'
                    . htmlspecialchars($this->descripcionTipoNomina($empresa, $tipoNomina)) . '</span>';
            }

            $dias   = $p['diasNomina'] === null ? null : (int) $p['diasNomina'];
            $nombre = trim((string) ($p['nombrePeriodo'] ?? ''));

            $inicial = $this->formatearFecha($p['fechaInicial'], 'd/m/Y');
            $final   = $this->formatearFecha($p['fechaFinal'], 'd/m/Y');
            $corte   = $this->formatearFecha($p['fechaCorte'], 'd/m/Y');
            $pago    = $this->formatearFecha($p['fechaPago'], 'd/m/Y');

            if ($inicial !== null && $final !== null) {
                $vigencia = '<span class="gp-rango-fecha">' . $inicial . ' <i class="bi bi-arrow-right"></i> ' . $final . '</span>';
            } elseif ($inicial !== null || $final !== null) {
                $vigencia = '<span class="gp-rango-fecha">' . ($inicial ?? $final) . '</span>';
            } else {
                $vigencia = $vacio;
            }

            $cortePago = '<span class="gp-etiqueta-mini">Corte</span> ' . ($corte ?? $vacio)
                . '<div class="gp-subtexto"><span class="gp-etiqueta-mini">Pago</span> ' . ($pago ?? $vacio) . '</div>';

            $indicadores = ((int) $p['agronomico'] === 1
                    ? '<span class="gp-chip-rol activo" title="Agronómico">AGR</span>'
                    : '<span class="gp-chip-rol" title="No es agronómico">AGR</span>')
                . ' '
                . ((int) $p['ejecutaLabores'] === 1
                    ? '<span class="gp-chip-rol activo" title="Ejecuta labores">LAB</span>'
                    : '<span class="gp-chip-rol" title="No ejecuta labores">LAB</span>');

            $badge = $cerrado === 1
                ? '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-lock-fill me-1"></i>Cerrado</span>'
                : '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-unlock me-1"></i>Abierto</span>';

            $tituloAlternar = $cerrado === 1 ? 'Abrir periodo' : 'Cerrar periodo';
            $iconoAlternar  = $cerrado === 1 ? 'bi bi-unlock' : 'bi bi-lock';

            $descripcion = "Periodo {$noPeriodo} · {$nombreMes} de {$anio}";

            $descJs = htmlspecialchars(
                str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $descripcion)),
                ENT_QUOTES
            );

            $claveJs = $empresa . ',' . $anio . ',' . $mes . ',' . $noPeriodo;

            $html .= '<tr data-empresa="' . $empresa . '" data-anio="' . $anio . '" data-mes="' . $mes
                . '" data-noperiodo="' . $noPeriodo . '" data-cerrado="' . $cerrado . '">'
                . '<td class="text-center">' . $anio . '</td>'
                . '<td class="text-nowrap" data-order="' . $mes . '">' . $celdaMes . '</td>'
                . '<td class="text-center"><span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . $noPeriodo . '</span></td>'
                . '<td class="text-center" data-order="' . htmlspecialchars($tipoNomina) . '">' . $celdaTipo . '</td>'
                . '<td class="text-center font-monospace text-muted">' . ($dias !== null ? $dias : $vacio) . '</td>'
                . '<td data-order="' . ($this->formatearFecha($p['fechaInicial'], 'Y-m-d') ?? '') . '">' . $vigencia . '</td>'
                . '<td data-order="' . ($this->formatearFecha($p['fechaCorte'], 'Y-m-d') ?? '') . '">' . $cortePago . '</td>'
                . ($nombre !== ''
                    ? '<td class="gp-truncar" title="' . htmlspecialchars($nombre) . '">' . htmlspecialchars($nombre) . '</td>'
                    : '<td class="gp-truncar">' . $vacio . '</td>')
                . '<td class="text-center">' . $indicadores . '</td>'
                . '<td class="text-center" data-order="' . $cerrado . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarPeriodoNomina(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloAlternar . '" onclick="alternarPeriodoNomina(' . $claveJs . ')"><i class="' . $iconoAlternar . '"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarPeriodoNomina(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }
}
