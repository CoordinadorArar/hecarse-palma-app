<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\PeriodosModel;
use App\Models\TercerosModel;

class ContabilidadController extends GestionPalmaController
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    private const MESES_ABR = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    private const ROLES_ABR = [
        'cliente'          => 'CLI',
        'proveedor'        => 'PRO',
        'empleado'         => 'EMPL',
        'accionista'       => 'ACC',
        'contratista'      => 'CON',
        'extractora'       => 'EXT',
        'comercializadora' => 'COM',
    ];

    /** Longitud máxima real de cada columna de texto de cTercero. */
    private const LIMITES_TERCERO = [
        'codigo'             => [50, 'El código'],
        'tipoDocumento'      => [3, 'El tipo de documento'],
        'nit'                => [25, 'El Nit'],
        'dv'                 => [3, 'El dígito de verificación'],
        'razonSocial'        => [550, 'La razón social'],
        'apellido1'          => [250, 'El primer apellido'],
        'apellido2'          => [250, 'El segundo apellido'],
        'nombre1'            => [550, 'El primer nombre'],
        'nombre2'            => [250, 'El segundo nombre'],
        'descripcion'        => [950, 'La descripción'],
        'ciudad'             => [50, 'La ciudad'],
        'contacto'           => [550, 'El contacto'],
        'telefono'           => [50, 'El teléfono'],
        'direccion'          => [550, 'La dirección'],
        'barrio'             => [550, 'El barrio'],
        'fax'                => [50, 'El fax'],
        'email'              => [250, 'El correo electrónico'],
        'departamento'       => [50, 'El departamento'],
        'codigoEquivalencia' => [50, 'El código de equivalencia'],
    ];

    private $periodosModel;
    private $tercerosModel;

    /** Mapa codigo => descripcionCorta del catálogo de tipos de documento. */
    private $tiposDocumento;

    public function __construct()
    {
        parent::__construct();

        $this->periodosModel = new PeriodosModel();
        $this->tercerosModel = new TercerosModel();
    }

    /**
     * Vista principal del módulo "Periodos contables".
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

        $data['title']           = 'Gestión Palma · Periodos';
        $data['empresas']        = $empresas;
        $data['anios']           = $anios;
        $data['empresa_sel']     = $empresaSel;
        $data['anio_sel']        = $anioSel;
        $data['tabla_periodos']  = $this->renderizarFilas($filas);
        $data['total_periodos']  = count($filas);

        return view('gestionpalma/periodos', $data);
    }

    /**
     * Vista principal del módulo "Terceros".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function terceros($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->tercerosModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->tercerosModel->listar($empresaSel, '', '', '1') : [];

        $data['title']            = 'Gestión Palma · Terceros';
        $data['empresas']         = $empresas;
        $data['tipos_documento']  = $this->tercerosModel->getTiposDocumento();
        $data['departamentos']    = $this->tercerosModel->getDepartamentos();
        $data['empresa_sel']      = $empresaSel;
        $data['tabla_terceros']   = $this->renderizarFilasTerceros($empresaSel, $filas);
        $data['total_terceros']   = count($filas);

        return view('gestionpalma/terceros', $data);
    }

    // ── Endpoints JSON ────────────────────────────────────────────────────────

    public function listarPeriodos(): ResponseInterface
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

    public function obtenerPeriodo(): ResponseInterface
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
            (int) $this->request->getPost('mes')
        );

        if ($periodo === null) {
            return $this->error('El periodo no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'periodo' => [
                'empresa'      => (int) $periodo['empresa'],
                'año'          => (int) $periodo['anio'],
                'mes'          => (int) $periodo['mes'],
                'descripcion'  => $periodo['descripcion'],
                'periodo'      => $periodo['periodo'],
                'cerrado'      => (int) $periodo['cerrado'],
                'fechaInicial' => $this->formatearFecha($periodo['fechaInicial'], 'Y-m-d'),
                'fechaFinal'   => $this->formatearFecha($periodo['fechaFinal'], 'Y-m-d'),
            ],
        ]);
    }

    public function crearPeriodo(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = (int) $this->request->getPost('anio');
        $mes  = (int) $this->request->getPost('mes');

        if ($mes < 1 || $mes > 13) {
            return $this->error('El mes debe estar entre 1 y 13.');
        }

        if ($anio < 1900 || $anio > 2999) {
            return $this->error('El año no es válido.');
        }

        $periodo = $anio . str_pad((string) $mes, 2, '0', STR_PAD_LEFT);

        if ($this->periodosModel->existe($empresa, $anio, $mes)) {
            return $this->error("El periodo {$periodo} ya existe para esta empresa.");
        }

        $fechas = $this->fechasValidas();

        if (is_string($fechas)) {
            return $this->error($fechas);
        }

        $descripcion = trim((string) $this->request->getPost('descripcion'));

        $this->periodosModel->crear($empresa, $anio, $mes, [
            'descripcion'  => $descripcion !== '' ? $descripcion : $this->descripcionAuto($anio, $mes),
            'periodo'      => $periodo,
            'cerrado'      => (int) $this->request->getPost('cerrado') === 1 ? 1 : 0,
            'fechaInicial' => $fechas[0],
            'fechaFinal'   => $fechas[1],
        ], $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, 'Periodo creado correctamente.');
    }

    public function actualizarPeriodo(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = (int) $this->request->getPost('anio');
        $mes  = (int) $this->request->getPost('mes');

        if (! $this->periodosModel->existe($empresa, $anio, $mes)) {
            return $this->error('El periodo no existe.');
        }

        $fechas = $this->fechasValidas();

        if (is_string($fechas)) {
            return $this->error($fechas);
        }

        $descripcion = trim((string) $this->request->getPost('descripcion'));

        $this->periodosModel->actualizar($empresa, $anio, $mes, [
            'descripcion'  => $descripcion !== '' ? $descripcion : $this->descripcionAuto($anio, $mes),
            'periodo'      => $anio . str_pad((string) $mes, 2, '0', STR_PAD_LEFT),
            'cerrado'      => (int) $this->request->getPost('cerrado') === 1 ? 1 : 0,
            'fechaInicial' => $fechas[0],
            'fechaFinal'   => $fechas[1],
        ], $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, 'Periodo actualizado correctamente.');
    }

    public function eliminarPeriodo(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio    = (int) $this->request->getPost('anio');
        $mes     = (int) $this->request->getPost('mes');
        $periodo = $this->periodosModel->obtener($empresa, $anio, $mes);

        if ($periodo === null) {
            return $this->error('El periodo no existe.');
        }

        if ($this->periodosModel->tieneMovimientos($empresa, $periodo['periodo'])) {
            return $this->error("El periodo {$periodo['periodo']} tiene movimientos contabilizados y no puede eliminarse.");
        }

        $this->periodosModel->eliminar($empresa, $anio, $mes, $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, 'Periodo eliminado correctamente.');
    }

    public function alternarCerrado(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio    = (int) $this->request->getPost('anio');
        $mes     = (int) $this->request->getPost('mes');
        $periodo = $this->periodosModel->obtener($empresa, $anio, $mes);

        if ($periodo === null) {
            return $this->error('El periodo no existe.');
        }

        $cerrado = (int) $periodo['cerrado'] === 1 ? 0 : 1;

        $this->periodosModel->alternarCerrado($empresa, $anio, $mes, $cerrado, $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, $cerrado === 1 ? 'Periodo cerrado.' : 'Periodo abierto.');
    }

    public function generarAnio(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe seleccionar un año válido.');
        }

        $existentes = $this->periodosModel->mesesExistentes($empresa, $anio);
        $usu_id     = $this->session->get('usu_id');
        $creados    = 0;
        $db         = db_connect();

        $db->transStart();

        for ($mes = 1; $mes <= 13; $mes++) {
            if (in_array($mes, $existentes, true)) {
                continue;
            }

            $inicial = $mes === 13 ? null : date('Y-m-d', mktime(0, 0, 0, $mes, 1, $anio));
            $final   = $mes === 13 ? null : date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));

            $this->periodosModel->crear($empresa, $anio, $mes, [
                'descripcion'  => $this->descripcionAuto($anio, $mes),
                'periodo'      => $anio . str_pad((string) $mes, 2, '0', STR_PAD_LEFT),
                'cerrado'      => 0,
                'fechaInicial' => $inicial,
                'fechaFinal'   => $final,
            ], $usu_id);

            $creados++;
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->error('No se pudo generar el año. La operación fue revertida.');
        }

        return $this->respuestaTabla(
            $empresa,
            $creados > 0 ? "Se generaron {$creados} periodos." : 'El año ya tenía todos sus periodos.'
        );
    }

    public function cerrarAnio(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe seleccionar un año válido.');
        }

        $cerrado = (int) $this->request->getPost('cerrado') === 1 ? 1 : 0;
        $db      = db_connect();

        $db->transStart();
        $afectados = $this->periodosModel->cerrarAnio($empresa, $anio, $cerrado, $this->session->get('usu_id'));
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->error('No se pudo actualizar el año. La operación fue revertida.');
        }

        return $this->respuestaTabla(
            $empresa,
            $cerrado === 1 ? "Se cerraron {$afectados} periodos." : "Se abrieron {$afectados} periodos."
        );
    }

    public function eliminarAnio(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe seleccionar un año válido.');
        }

        $bloqueados = $this->periodosModel->periodosConMovimientos($empresa, $anio);

        if ($bloqueados !== []) {
            return $this->error(
                'No se puede eliminar el año: los periodos ' . implode(', ', $bloqueados)
                . ' tienen movimientos contabilizados.'
            );
        }

        $db = db_connect();

        $db->transStart();
        $afectados = $this->periodosModel->eliminarAnio($empresa, $anio, $this->session->get('usu_id'));
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->error('No se pudo eliminar el año. La operación fue revertida.');
        }

        return $this->respuestaTabla($empresa, "Se eliminaron {$afectados} periodos.");
    }

    // ── Endpoints JSON de terceros ────────────────────────────────────────────

    public function listarTerceros(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->tercerosModel->listar(
            $empresa,
            (string) $this->request->getPost('tipo'),
            (string) $this->request->getPost('rol'),
            (string) $this->request->getPost('estado'),
            (string) $this->request->getPost('busqueda')
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasTerceros($empresa, $filas),
        ]);
    }

    public function obtenerTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $tercero = $this->tercerosModel->obtener($empresa, (int) $this->request->getPost('id'));

        if ($tercero === null) {
            return $this->error('El tercero no existe.');
        }

        $tercero['empresa']       = (int) $tercero['empresa'];
        $tercero['id']            = (int) $tercero['id'];
        $tercero['tipo']          = (int) $tercero['tipo'];
        $tercero['tipoDocumento'] = trim((string) $tercero['tipoDocumento']);
        $tercero['dv']            = trim((string) $tercero['dv']);
        $tercero['ciudad']        = trim((string) $tercero['ciudad']);
        $tercero['departamento']  = trim((string) $tercero['departamento']);
        $tercero['fechaRegistro'] = $this->formatearFecha($tercero['fechaRegistro'], 'Y-m-d H:i:s');

        foreach (array_keys(TercerosModel::ROLES) as $rol) {
            $tercero[$rol] = (int) $tercero[$rol];
        }

        $tercero['activo'] = (int) $tercero['activo'];

        return $this->response->setJSON([
            'success' => true,
            'tercero' => array_merge($tercero, $this->tercerosModel->nombreCiudad($empresa, $tercero['ciudad'])),
        ]);
    }

    public function crearTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $datos = $this->datosTercero($empresa, null);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if ($this->tercerosModel->crear($empresa, $datos, $this->session->get('usu_id')) === null) {
            return $this->errorEscrituraTercero('No se pudo crear el tercero. La operación fue revertida.');
        }

        return $this->respuestaTablaTerceros($empresa, 'Tercero creado correctamente.');
    }

    public function actualizarTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $id = (int) $this->request->getPost('id');

        if ($this->tercerosModel->obtener($empresa, $id) === null) {
            return $this->error('El tercero no existe.');
        }

        $datos = $this->datosTercero($empresa, $id);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if (! $this->tercerosModel->actualizar($empresa, $id, $datos, $this->session->get('usu_id'))) {
            return $this->errorEscrituraTercero('No se pudo guardar el tercero.');
        }

        return $this->respuestaTablaTerceros($empresa, 'Tercero actualizado correctamente.');
    }

    public function cambiarEstadoTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $id = (int) $this->request->getPost('id');

        if ($this->tercerosModel->obtener($empresa, $id) === null) {
            return $this->error('El tercero no existe.');
        }

        $activo = in_array((string) $this->request->getPost('activo'), ['1', 'on', 'true'], true) ? 1 : 0;

        if (! $this->tercerosModel->cambiarEstado($empresa, $id, $activo, $this->session->get('usu_id'))) {
            return $this->errorEscrituraTercero('No se pudo guardar el tercero.');
        }

        return $this->respuestaTablaTerceros(
            $empresa,
            $activo === 1 ? 'Tercero activado correctamente.' : 'Tercero desactivado correctamente.'
        );
    }

    public function eliminarTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $id      = (int) $this->request->getPost('id');
        $tercero = $this->tercerosModel->obtener($empresa, $id);

        if ($tercero === null) {
            return $this->error('El tercero no existe.');
        }

        if ($this->tercerosModel->tieneDependencias($empresa, $id, $tercero['codigo'])) {
            return $this->response->setJSON([
                'success'   => false,
                'message'   => '«' . trim((string) $tercero['descripcion']) . '» está siendo utilizado en Nómina y no se puede eliminar.'
                    . ' Si ya no lo utiliza, desmárquelo como Activo.',
                'bloqueado' => true,
            ])->setStatusCode(422);
        }

        if (! $this->tercerosModel->eliminar($empresa, $id, $this->session->get('usu_id'))) {
            return $this->errorEscrituraTercero('No se pudo eliminar el tercero.');
        }

        return $this->respuestaTablaTerceros($empresa, 'Tercero eliminado correctamente.');
    }

    public function siguienteIdTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        return $this->response->setJSON([
            'success' => true,
            'id'      => $this->tercerosModel->siguienteId($empresa),
        ]);
    }

    public function buscarCiudad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $termino = trim((string) $this->request->getPost('termino'));

        if (mb_strlen($termino) < 2) {
            return $this->response->setJSON(['success' => true, 'ciudades' => [], 'hayMas' => false]);
        }

        $filas  = $this->tercerosModel->buscarCiudades($empresa, $termino, trim((string) $this->request->getPost('departamento')));
        $hayMas = count($filas) > 15;

        return $this->response->setJSON([
            'success'  => true,
            'ciudades' => array_map(static fn ($c) => [
                'codigo'             => trim((string) $c['codigo']),
                'nombre'             => (string) $c['nombre'],
                'departamento'       => trim((string) $c['departamento']),
                'nombreDepartamento' => (string) ($c['nombreDepartamento'] ?? ''),
            ], array_slice($filas, 0, 15)),
            'hayMas'   => $hayMas,
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
     * Año recibido por POST para las acciones masivas, o null si no se envió.
     */
    private function anioValido(): ?int
    {
        $anio = $this->request->getPost('anio');

        if ($anio === null || $anio === '' || ! is_numeric($anio)) {
            return null;
        }

        $anio = (int) $anio;

        return ($anio < 1900 || $anio > 2999) ? null : $anio;
    }

    /**
     * Valida el rango de fechas del formulario.
     *
     * @return array|string Par [fechaInicial, fechaFinal] o el mensaje de error.
     */
    private function fechasValidas(): array|string
    {
        $inicial = trim((string) $this->request->getPost('fechaInicial'));
        $final   = trim((string) $this->request->getPost('fechaFinal'));

        $inicial = $inicial !== '' ? $inicial : null;
        $final   = $final !== '' ? $final : null;

        if ($inicial !== null && $final !== null && strtotime($final) < strtotime($inicial)) {
            return 'La fecha final no puede ser anterior a la fecha inicial.';
        }

        return [$inicial, $final];
    }

    private function descripcionAuto(int $anio, int $mes): string
    {
        return $mes === 13
            ? "Cierre del año {$anio}"
            : self::MESES[$mes] . " del año {$anio}";
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

    /**
     * Fecha corta en español: 01 ago, 2026.
     */
    private function formatearFechaEs($fecha): ?string
    {
        $iso = $this->formatearFecha($fecha, 'Y-m-d');

        if ($iso === null) {
            return null;
        }

        [$anio, $mes, $dia] = explode('-', $iso);

        return $dia . ' ' . (self::MESES_ABR[(int) $mes] ?? $mes) . ', ' . $anio;
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
     * Construye las filas <tr> de la tabla de periodos.
     */
    private function renderizarFilas(array $periodos): string
    {
        $html  = '';
        $vacio = '<span class="text-muted">—</span>';

        foreach ($periodos as $p) {
            $empresa = (int) $p['empresa'];
            $anio    = (int) $p['anio'];
            $mes     = (int) $p['mes'];
            $cerrado = (int) $p['cerrado'];

            $celdaMes = $mes === 13
                ? '<span class="badge gp-badge-cierre rounded-pill px-3 py-2">Cierre del año</span>'
                : htmlspecialchars(self::MESES[$mes] ?? (string) $mes);

            $descripcion = trim((string) ($p['descripcion'] ?? ''));
            $periodo     = trim((string) ($p['periodo'] ?? ''));

            $inicial    = $this->formatearFechaEs($p['fechaInicial']);
            $final      = $this->formatearFechaEs($p['fechaFinal']);
            $ordInicial = $this->formatearFecha($p['fechaInicial'], 'Y-m-d') ?? '';
            $ordFinal   = $this->formatearFecha($p['fechaFinal'], 'Y-m-d') ?? '';

            $badge = $cerrado === 1
                ? '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-lock-fill me-1"></i>Cerrado</span>'
                : '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-unlock me-1"></i>Abierto</span>';

            $tituloAlternar = $cerrado === 1 ? 'Abrir periodo' : 'Cerrar periodo';
            $iconoAlternar  = $cerrado === 1 ? 'bi bi-unlock' : 'bi bi-lock';

            $descJs = htmlspecialchars(
                str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $descripcion)),
                ENT_QUOTES
            );

            $html .= '<tr' . ($mes === 13 ? ' class="gp-fila-cierre"' : '')
                . ' data-empresa="' . $empresa . '" data-anio="' . $anio . '" data-mes="' . $mes . '" data-cerrado="' . $cerrado . '">'
                . '<td class="text-center">' . $anio . '</td>'
                . '<td class="text-center" data-order="' . $mes . '">' . $celdaMes . '</td>'
                . '<td>' . ($descripcion !== '' ? htmlspecialchars($descripcion) : $vacio) . '</td>'
                . '<td class="font-monospace">' . ($periodo !== '' ? htmlspecialchars($periodo) : $vacio) . '</td>'
                . '<td class="text-muted small" data-order="' . $ordInicial . '">' . ($inicial !== null ? htmlspecialchars($inicial) : $vacio) . '</td>'
                . '<td class="text-muted small" data-order="' . $ordFinal . '">' . ($final !== null ? htmlspecialchars($final) : $vacio) . '</td>'
                . '<td class="text-center">' . $badge . '</td>'
                . '<td class="text-center">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarPeriodo(' . $empresa . ',' . $anio . ',' . $mes . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloAlternar . '" onclick="alternarPeriodo(' . $empresa . ',' . $anio . ',' . $mes . ')"><i class="' . $iconoAlternar . '"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarPeriodo(' . $empresa . ',' . $anio . ',' . $mes . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    // ── Utilidades internas de terceros ───────────────────────────────────────

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
     * Valida el formulario de terceros y arma las columnas a persistir.
     *
     * @param int|null $id Registro a excluir de las validaciones de unicidad.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosTercero(int $empresa, ?int $id): array|string
    {
        $tipo = (int) $this->request->getPost('tipo');

        if ($tipo !== 1 && $tipo !== 2) {
            return 'Debe seleccionar el tipo de persona.';
        }

        $tipoDocumento = $this->campo('tipoDocumento');

        if ($tipoDocumento === '' || ! $this->tercerosModel->tipoDocumentoExiste($tipoDocumento)) {
            return 'Debe seleccionar un tipo de documento válido.';
        }

        $nit = preg_replace('/\D/', '', $this->campo('nit'));

        if ($nit === '' || strlen($nit) < 5) {
            return 'El Nit ingresado no es válido.';
        }

        $apellido1   = $this->campo('apellido1');
        $apellido2   = $this->campo('apellido2');
        $nombre1     = $this->campo('nombre1');
        $nombre2     = $this->campo('nombre2');
        $razonSocial = $this->campo('razonSocial');

        if ($tipo === 2 && $razonSocial === '') {
            return 'La razón social es obligatoria.';
        }

        $completo    = preg_replace('/\s+/', ' ', trim("{$apellido1} {$apellido2} {$nombre1} {$nombre2}"));
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            $descripcion = $tipo === 2 ? $razonSocial : $completo;
        }

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if ($razonSocial === '') {
            $razonSocial = mb_substr($completo !== '' ? $completo : $descripcion, 0, self::LIMITES_TERCERO['razonSocial'][0]);
        }

        $codigo = $this->campo('codigo');
        $codigo = $codigo !== '' ? $codigo : $nit;

        $email = $this->campo('email');

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'El correo electrónico no es válido.';
        }

        if ($this->tercerosModel->nitDuplicado($empresa, $nit, $id)) {
            return "El Nit {$nit} ya está registrado para otro tercero.";
        }

        if ($this->tercerosModel->codigoDuplicado($empresa, $codigo, $id)) {
            return "El código {$codigo} ya está registrado.";
        }

        $datos = [
            'codigo'            => $codigo,
            'tipoDocumento'     => $tipoDocumento,
            'tipo'              => $tipo,
            'nit'               => $nit,
            'dv'                => $tipoDocumento === '31' ? (string) $this->tercerosModel->calcularDV($nit) : null,
            'razonSocial'       => $razonSocial,
            'apellido1'         => $apellido1,
            'apellido2'         => $apellido2,
            'nombre1'           => $nombre1,
            'nombre2'           => $nombre2 !== '' ? $nombre2 : null,
            'descripcion'       => $descripcion,
            'activo'            => $this->bit('activo'),
            'ciudad'            => $this->campo('ciudad') !== '' ? $this->campo('ciudad') : null,
            'departamento'      => $this->campo('departamento') !== '' ? $this->campo('departamento') : null,
            'contacto'          => $this->campo('contacto') !== '' ? $this->campo('contacto') : null,
            'telefono'          => $this->campo('telefono') !== '' ? $this->campo('telefono') : null,
            'direccion'         => $this->campo('direccion') !== '' ? $this->campo('direccion') : null,
            'barrio'            => $this->campo('barrio') !== '' ? $this->campo('barrio') : null,
            'fax'               => $this->campo('fax') !== '' ? $this->campo('fax') : null,
            'email'             => $email !== '' ? $email : null,
            'codigoEquivalencia' => $this->campo('codigoEquivalencia') !== '' ? $this->campo('codigoEquivalencia') : null,
        ];

        foreach (self::LIMITES_TERCERO as $columna => [$tope, $etiqueta]) {
            if (mb_strlen((string) ($datos[$columna] ?? '')) > $tope) {
                return "{$etiqueta} no puede superar los {$tope} caracteres.";
            }
        }

        foreach (array_keys(TercerosModel::ROLES) as $rol) {
            $datos[$rol] = $this->bit($rol);
        }

        return $datos;
    }

    /**
     * Error 500 cuando la escritura sobre cTercero no se pudo ejecutar.
     */
    private function errorEscrituraTercero(string $mensaje): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode(500);
    }

    /**
     * Respuesta estándar con la tabla de terceros según los filtros vigentes.
     */
    private function respuestaTablaTerceros(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->tercerosModel->listar(
            $empresa,
            (string) $this->request->getPost('filtro_tipo'),
            (string) $this->request->getPost('filtro_rol'),
            (string) $this->request->getPost('filtro_estado'),
            (string) $this->request->getPost('filtro_busqueda')
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasTerceros($empresa, $filas),
        ]);
    }

    /**
     * Mapa codigo => descripcionCorta del catálogo de tipos de documento.
     */
    private function tiposDocumento(): array
    {
        if ($this->tiposDocumento === null) {
            $this->tiposDocumento = array_column($this->tercerosModel->getTiposDocumento(), 'descripcionCorta', 'codigo');
        }

        return $this->tiposDocumento;
    }

    /**
     * Construye las filas <tr> de la tabla de terceros.
     */
    private function renderizarFilasTerceros(int $empresa, array $terceros): string
    {
        if ($terceros === []) {
            return '';
        }

        $vacio    = '<span class="text-muted">—</span>';
        $tipos    = $this->tiposDocumento();
        $ciudades = $this->tercerosModel->nombresCiudades($empresa, array_column($terceros, 'ciudad'));
        $html     = '';

        foreach ($terceros as $t) {
            $emp = (int) $t['empresa'];
            $id  = (int) $t['id'];

            $codigo      = trim((string) $t['codigo']);
            $nit         = trim((string) $t['nit']);
            $dv          = trim((string) $t['dv']);
            $doc         = trim((string) $t['tipoDocumento']);
            $tipo        = (int) $t['tipo'];
            $activo      = (int) $t['activo'] === 1 ? 1 : 0;
            $descripcion = trim((string) $t['descripcion']);

            if ($descripcion === '') {
                $descripcion = trim((string) $t['razonSocial']);
            }

            if ($descripcion === '') {
                $descripcion = preg_replace('/\s+/', ' ', trim(
                    $t['apellido1'] . ' ' . $t['apellido2'] . ' ' . $t['nombre1'] . ' ' . $t['nombre2']
                ));
            }

            $ciudad = trim((string) $t['ciudad']);
            $ciudad = $ciudad !== '' ? ($ciudades[$ciudad] ?? $ciudad) : '';

            $celdaDoc = '<span class="badge gp-badge-doc rounded-pill px-2 py-1">'
                . htmlspecialchars($tipos[$doc] ?? $doc) . '</span>'
                . '<span class="gp-subtexto font-monospace">' . htmlspecialchars($nit)
                . ($dv !== '' ? '<span class="text-muted">-' . htmlspecialchars($dv) . '</span>' : '')
                . '</span>';

            $chips  = '';
            $activos = 0;

            foreach (TercerosModel::ROLES as $rol => $etiqueta) {
                $encendido = (int) $t[$rol] === 1;
                $activos  += $encendido ? 1 : 0;

                if (! $encendido && ! in_array($rol, ['cliente', 'proveedor', 'empleado'], true)) {
                    continue;
                }

                $chips .= '<span class="gp-chip-rol' . ($encendido ? ' activo' : '') . '" title="'
                    . htmlspecialchars($encendido ? $etiqueta : 'No es ' . mb_strtolower($etiqueta)) . '">'
                    . self::ROLES_ABR[$rol] . '</span>';
            }

            $badgeEstado = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $descJs = htmlspecialchars(
                str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $descripcion)),
                ENT_QUOTES
            );

            $html .= '<tr data-empresa="' . $emp . '" data-id="' . $id . '" data-nit="' . htmlspecialchars($nit) . '">'
                . '<td class="font-monospace text-muted small">' . ($codigo !== '' ? htmlspecialchars($codigo) : $vacio) . '</td>'
                . '<td class="text-nowrap" data-order="' . htmlspecialchars($nit) . '">' . $celdaDoc . '</td>'
                . '<td>' . ($descripcion !== '' ? htmlspecialchars($descripcion) : $vacio)
                . ($ciudad !== '' ? '<span class="gp-subtexto">' . htmlspecialchars($ciudad) . '</span>' : '')
                . '</td>'
                . '<td class="text-center" data-order="' . $tipo . '">'
                . '<span class="badge gp-badge-doc rounded-pill px-2 py-1">' . ($tipo === 2 ? 'Jurídica' : 'Natural') . '</span></td>'
                . '<td class="text-center">' . ($activos > 0 ? $chips : $vacio) . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badgeEstado . '</td>'
                . '<td class="text-center">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarTercero(' . $emp . ',' . $id . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarTercero(' . $emp . ',' . $id . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }
}
