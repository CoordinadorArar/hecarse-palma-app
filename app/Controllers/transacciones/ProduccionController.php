<?php

namespace App\Controllers\Transacciones;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\ProduccionModel;

/**
 * Registro de la transacción de producción tipo TLC.
 *
 * El operario captura el tiquete de báscula y reparte los RACIMOS entre los
 * lotes; los kilos de cada línea los calcula el servidor con
 * ProduccionModel::repartirKilos() para que cierren exactos contra el peso neto.
 *
 * La validación de racimos es asimétrica: un exceso sobre los del tiquete se
 * bloquea siempre, y un faltante solo se guarda si llega "confirmaFaltante".
 */
class ProduccionController extends BaseController
{
    private $session;
    private $produccionModel;
    private ?array $permisos = null;

    private const MODULO = 58;

    private const MAX_LINEAS       = 500;
    private const MAX_TRABAJADORES = 500;

    public function __construct()
    {
        $this->session         = session();
        $this->produccionModel = new ProduccionModel();
    }

    /**
     * Vista principal del módulo "Transacciones > Producción".
     *
     * @param int $id_loseta Identificador de la loseta "Transacciones".
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        if (! $this->permisos()['registrar'] && ! $this->permisos()['consultar']) {
            return redirect()->to(base_url('losetas'));
        }

        $empresas    = $this->produccionModel->getEmpresas();
        $empresaSel  = $empresas ? (int) $empresas[0]['id'] : 0;
        $pedida      = (int) $this->request->getGet('empresa');

        if ($pedida > 0 && in_array($pedida, array_map('intval', array_column($empresas, 'id')), true)) {
            $empresaSel = $pedida;
        }

        $data['title']       = 'Transacciones · Producción';
        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresaSel;
        $data['extractoras'] = $empresaSel > 0 ? $this->produccionModel->extractoras($empresaSel) : [];
        $data['fincas']      = $empresaSel > 0 ? $this->produccionModel->fincas($empresaSel) : [];
        $data['anios']       = $empresaSel > 0 ? $this->produccionModel->anios($empresaSel) : [];
        $actual              = $empresaSel > 0 ? $this->produccionModel->periodoActual($empresaSel) : null;
        $data['anio_actual'] = $actual['anio'] ?? (int) date('Y');
        $data['mes_actual']  = $actual['mes'] ?? (int) date('n');
        $data['permisos']    = $this->permisos();

        return view('transacciones/produccion', $data);
    }

    public function lotes(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca = trim($this->campo('finca'));

        if ($finca === '') {
            return $this->error('El campo "Finca" es obligatorio.');
        }

        try {
            $filas = $this->produccionModel->lotes($empresa, $finca);
        } catch (\Throwable $e) {
            log_message('error', 'Error listando los lotes: ' . $e->getMessage());

            return $this->error('No se pudo leer el listado de lotes.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'filas'   => $filas,
        ]);
    }

    public function pesoPromedio(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $fecha = $this->fechaValida($this->campo('fecha'));

        if ($fecha === null) {
            return $this->error('El campo "Fecha" es obligatorio y debe tener el formato AAAA-MM-DD.');
        }

        $finca = trim($this->campo('finca'));
        $lote  = trim($this->campo('lote'));

        if ($finca === '' || $lote === '') {
            return $this->error('Los campos "Finca" y "Lote" son obligatorios.');
        }

        try {
            $peso = $this->produccionModel->pesoPromedio(
                $empresa,
                (int) substr($fecha, 0, 4),
                (int) substr($fecha, 5, 2),
                $finca,
                $lote
            );
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el peso promedio del lote: ' . $e->getMessage());

            return $this->error('No se pudo consultar el peso promedio del lote.', 500);
        }

        return $this->response->setJSON([
            'success'    => true,
            'message'    => $peso > 0 ? '' : 'El lote ' . $lote . ' no tiene peso promedio registrado en el periodo.',
            'finca'      => $finca,
            'lote'       => $lote,
            'pesoRacimo' => $peso,
            'existe'     => $peso > 0 ? 1 : 0,
        ]);
    }

    public function buscarTiquete(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $tiquete = trim($this->campo('tiquete'));

        if ($tiquete === '') {
            return $this->error('El campo "Tiquete" es obligatorio.');
        }

        if (strlen($tiquete) > 50) {
            return $this->error('El campo "Tiquete" admite máximo 50 caracteres.');
        }

        try {
            $datos = $this->produccionModel->buscarTiquete($empresa, $tiquete);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el tiquete: ' . $e->getMessage());

            return $this->error('No se pudo consultar el tiquete.', 500);
        }

        return $this->response->setJSON(array_merge(['success' => true, 'message' => ''], $datos));
    }

    public function terceros(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        try {
            $filas = $this->produccionModel->buscarTercero($empresa, $this->campo('termino'));
        } catch (\Throwable $e) {
            log_message('error', 'Error buscando terceros: ' . $e->getMessage());

            return $this->error('No se pudo buscar el tercero.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'filas'   => $filas,
        ]);
    }

    public function labores(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clase = trim($this->campo('clase'));

        if ($clase !== '' && preg_match('/^\d{1,3}$/', $clase) !== 1) {
            return $this->error('El campo "Clase de labor" solo admite números enteros.');
        }

        try {
            $filas = $this->produccionModel->labores($empresa, $clase);
        } catch (\Throwable $e) {
            log_message('error', 'Error listando las labores: ' . $e->getMessage());

            return $this->error('No se pudo leer el listado de labores.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'filas'   => $filas,
        ]);
    }

    /**
     * Precio sugerido de una labor para un trabajador en un lote. Nunca falla
     * porque no haya precio: devuelve existe = 0 para que el frontend pueda
     * decirlo en vez de mostrar un cero engañoso.
     */
    public function precioLabor(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $fecha = $this->fechaValida($this->campo('fecha'));

        if ($fecha === null) {
            return $this->error('El campo "Fecha" es obligatorio y debe tener el formato AAAA-MM-DD.');
        }

        $novedad = trim($this->campo('novedad'));
        $finca   = trim($this->campo('finca'));
        $lote    = trim($this->campo('lote'));
        $tercero = trim($this->campo('tercero'));

        if ($novedad === '' || $finca === '' || $lote === '' || $tercero === '') {
            return $this->error('Los campos "Labor", "Finca", "Lote" y "Trabajador" son obligatorios.');
        }

        if (! $this->produccionModel->laborValida($empresa, $novedad)) {
            return $this->error('La labor ' . $novedad . ' no existe.');
        }

        if (! $this->produccionModel->terceroValido($empresa, $tercero)) {
            return $this->error('El trabajador ' . $tercero . ' no existe.');
        }

        try {
            $precio = $this->produccionModel->precioLabor(
                $empresa,
                $novedad,
                (int) substr($fecha, 0, 4),
                $tercero,
                $fecha,
                $finca,
                $lote
            );
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el precio de la labor: ' . $e->getMessage());

            return $this->error('No se pudo consultar el precio de la labor.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $precio > 0 ? '' : 'La labor ' . $novedad . ' no tiene precio en la lista del año para este lote.',
            'precio'  => $precio,
            'existe'  => $precio > 0 ? 1 : 0,
        ]);
    }

    /**
     * Previsualización del reparto: devuelve los mismos kilos que usará el
     * guardado y el faltante o sobrante de racimos.
     */
    public function previsualizar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(trim($this->campo('numero')) === '' ? ['registrar', 'editar'] : ['editar'], trim($this->campo('numero')) === '' ? 'registrar o editar transacciones de producción' : 'editar transacciones de producción')) {
            return $sinSesion;
        }

        $datos = $this->datosValidos();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $numero = trim($this->campo('numero'));

        if ($numero !== '' && strlen($numero) <= 50) {
            try {
                $datos = $this->produccionModel->conservarReparto($datos, $this->produccionModel->detalle($datos['empresa'], $numero));
            } catch (\Throwable $e) {
                log_message('error', 'Error consultando la transacción a previsualizar: ' . $e->getMessage());
            }
        }

        $reparto = $this->produccionModel->reparto($datos);
        $lineas  = [];

        foreach ($datos['lineas'] as $i => $linea) {
            $lineas[] = [
                'registro'   => $i + 1,
                'finca'      => $linea['finca'],
                'lote'       => $linea['lote'],
                'racimos'    => (int) $linea['racimos'],
                'sacos'      => (int) $linea['sacos'],
                'pesoRacimo' => (float) $linea['pesoRacimo'],
                'cantidad'   => $reparto['kilos'][$i],
            ];
        }

        return $this->response->setJSON([
            'success'                => true,
            'message'                => '',
            'pesoNeto'               => (int) $datos['bascula']['pesoNeto'],
            'racimos'                => (int) $datos['bascula']['racimos'],
            'distribuidos'           => $datos['racimosLineas'],
            'diferencia'             => $datos['bascula']['racimos'] - $datos['racimosLineas'],
            'faltante'               => max(0, $datos['bascula']['racimos'] - $datos['racimosLineas']),
            'sobrante'               => max(0, $datos['racimosLineas'] - $datos['bascula']['racimos']),
            'total'                  => $reparto['total'],
            'trabajadores'           => $datos['totalTrabajadores'],
            'lineasSinTrabajadores'  => $datos['lineasSinTrabajadores'],
            'avisos'                 => $this->avisosSinPeso($reparto['sinPeso'], $datos['lineas'], $datos['fecha']),
            'lineas'                 => $lineas,
        ]);
    }

    public function guardar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar'], 'registrar transacciones de producción')) {
            return $sinSesion;
        }

        $datos = $this->datosValidos();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if ($bloqueo = $this->bloqueoGuardar($datos)) {
            return $bloqueo;
        }

        unset($datos['racimosLineas'], $datos['confirmaFaltante'], $datos['totalTrabajadores'], $datos['lineasSinTrabajadores'], $datos['periodoCerrado']);

        try {
            $resultado = $this->produccionModel->guardar($datos, $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error registrando la transacción de producción: ' . $e->getMessage());

            return $this->error('No se pudo registrar la transacción.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message'], 500);
        }

        return $this->response->setJSON($resultado);
    }

    public function actualizar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['editar'], 'editar transacciones de producción')) {
            return $sinSesion;
        }

        $numero = trim($this->campo('numero'));

        if ($numero === '' || strlen($numero) > 50) {
            return $this->error('El campo "Número" es obligatorio y admite máximo 50 caracteres.');
        }

        $datos = $this->datosValidos();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $antes = $this->transaccionEditable($datos['empresa'], $numero, 'editar');

        if ($antes instanceof ResponseInterface) {
            return $antes;
        }

        if ($bloqueo = $this->bloqueoGuardar($datos)) {
            return $bloqueo;
        }

        unset($datos['racimosLineas'], $datos['confirmaFaltante'], $datos['totalTrabajadores'], $datos['lineasSinTrabajadores'], $datos['periodoCerrado']);

        $datos = $this->produccionModel->conservarReparto($datos, $antes);

        try {
            $resultado = $this->produccionModel->actualizar($datos, $antes, $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error actualizando la transacción de producción: ' . $e->getMessage());

            return $this->error('No se pudo actualizar la transacción.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message'], 500);
        }

        return $this->response->setJSON($resultado);
    }

    public function eliminar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['eliminar'], 'eliminar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $numero = trim($this->campo('numero'));

        if ($numero === '' || strlen($numero) > 50) {
            return $this->error('El campo "Número" es obligatorio y admite máximo 50 caracteres.');
        }

        $motivo = trim($this->campo('motivo'));

        if (strlen($motivo) > 250) {
            return $this->error('El campo "Motivo" admite máximo 250 caracteres.');
        }

        if (! $this->produccionModel->tablaEliminadas()) {
            return $this->error('La eliminación de transacciones no está disponible: falta crear la tabla aTransaccionEliminada.', 500);
        }

        $antes = $this->transaccionEditable($empresa, $numero, 'eliminar');

        if ($antes instanceof ResponseInterface) {
            return $antes;
        }

        try {
            $resultado = $this->produccionModel->eliminar($empresa, $antes, $motivo, $this->usuarioRegistro(), $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error eliminando la transacción de producción: ' . $e->getMessage());

            return $this->error('No se pudo eliminar la transacción.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message'], 500);
        }

        return $this->response->setJSON($resultado);
    }

    public function periodos(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = trim($this->campo('anio'));

        if (preg_match('/^\d{4}$/', $anio) !== 1) {
            return $this->error('El campo "Año" es obligatorio y debe tener 4 dígitos.');
        }

        try {
            $filas = $this->produccionModel->periodos($empresa, (int) $anio);
        } catch (\Throwable $e) {
            log_message('error', 'Error listando los periodos: ' . $e->getMessage());

            return $this->error('No se pudo leer el listado de periodos.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'filas'   => $filas,
        ]);
    }

    private function bloqueoGuardar(array $datos): ?ResponseInterface
    {
        if ($datos['periodoCerrado'] === 1) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($datos['anio'], $datos['mes']) . ' está cerrado.');
        }

        $faltante = $datos['bascula']['racimos'] - $datos['racimosLineas'];

        if ($faltante < 0) {
            return $this->error('Las líneas suman ' . $datos['racimosLineas'] . ' racimos y el tiquete registra '
                . $datos['bascula']['racimos'] . ': sobran ' . abs($faltante) . ' racimos. Corrija el reparto.');
        }

        if ($datos['totalTrabajadores'] === 0) {
            return $this->error('La transacción debe tener al menos un trabajador.');
        }

        if ($datos['lineasSinTrabajadores'] !== [] && (int) $this->campo('confirmaLineasSinTrabajadores') !== 1) {
            return $this->response->setJSON([
                'success'               => false,
                'message'               => 'Las líneas ' . implode(', ', $datos['lineasSinTrabajadores'])
                    . ' no tienen trabajadores. Confirme para continuar.',
                'lineasSinTrabajadores' => $datos['lineasSinTrabajadores'],
                'requiereConfirmacion'  => true,
            ])->setStatusCode(422);
        }

        if ($faltante > 0 && (int) $datos['confirmaFaltante'] !== 1) {
            return $this->response->setJSON([
                'success'  => false,
                'message'  => 'Las líneas suman ' . $datos['racimosLineas'] . ' racimos y el tiquete registra '
                    . $datos['bascula']['racimos'] . ': faltan ' . $faltante . ' racimos por repartir. Confirme para continuar.',
                'faltante' => $faltante,
                'requiereConfirmacion' => true,
            ])->setStatusCode(422);
        }

        return null;
    }

    private function transaccionEditable(int $empresa, string $numero, string $accion): array|ResponseInterface
    {
        try {
            $antes = $this->produccionModel->detalle($empresa, $numero);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la transacción: ' . $e->getMessage());

            return $this->error('No se pudo consultar la transacción.', 500);
        }

        if ($antes === null) {
            return $this->error('No se encontró la transacción ' . $numero . '.', 404);
        }

        if ($antes['encabezado']['anulado'] === 1) {
            return $this->error('La transacción ' . $numero . ' está anulada y no se puede ' . $accion . '.');
        }

        if ($this->produccionModel->liquidada($empresa, $numero)) {
            return $this->error('La transacción ' . $numero . ' ya fue liquidada o ejecutada en nómina y no se puede ' . $accion . '.');
        }

        $periodo = $this->produccionModel->periodo($empresa, $antes['encabezado']['anio'], $antes['encabezado']['mes']);

        if ($periodo !== null && $periodo['cerrado'] === 1) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($antes['encabezado']['anio'], $antes['encabezado']['mes']) . ' está cerrado.');
        }

        return $antes;
    }

    private function etiquetaPeriodo(int $anio, int $mes): string
    {
        return $anio . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
    }

    public function consultar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['consultar'], 'consultar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $desde = trim($this->campo('fechaDesde'));
        $hasta = trim($this->campo('fechaHasta'));

        if ($desde !== '' && $this->fechaValida($desde) === null) {
            return $this->error('El campo "Fecha desde" debe tener el formato AAAA-MM-DD.');
        }

        if ($hasta !== '' && $this->fechaValida($hasta) === null) {
            return $this->error('El campo "Fecha hasta" debe tener el formato AAAA-MM-DD.');
        }

        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            return $this->error('La "Fecha desde" no puede ser mayor que la "Fecha hasta".');
        }

        $tiquete = trim($this->campo('tiquete'));
        $numero  = trim($this->campo('numero'));

        if (strlen($tiquete) > 50) {
            return $this->error('El campo "Tiquete" admite máximo 50 caracteres.');
        }

        if (strlen($numero) > 50) {
            return $this->error('El campo "Número" admite máximo 50 caracteres.');
        }

        $estado = trim($this->campo('estado'));

        if (! in_array($estado, ['', '0', '1'], true)) {
            return $this->error('El campo "Estado" no es válido.');
        }

        try {
            $filas = $this->produccionModel->consultar($empresa, $desde, $hasta, $tiquete, $numero, $estado);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando las transacciones de producción: ' . $e->getMessage());

            return $this->error('No se pudieron consultar las transacciones.', 500);
        }

        $truncado = count($filas) > 500;

        if ($truncado) {
            $filas = array_slice($filas, 0, 500);
        }

        return $this->response->setJSON([
            'success'  => true,
            'message'  => '',
            'total'    => count($filas),
            'truncado' => $truncado,
            'filas'    => $filas,
        ]);
    }

    public function detalle(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['consultar'], 'consultar transacciones de producción')) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $numero = trim($this->campo('numero'));

        if ($numero === '') {
            return $this->error('El campo "Número" es obligatorio.');
        }

        if (strlen($numero) > 50) {
            return $this->error('El campo "Número" admite máximo 50 caracteres.');
        }

        try {
            $datos = $this->produccionModel->detalle($empresa, $numero);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el detalle de la transacción: ' . $e->getMessage());

            return $this->error('No se pudo consultar el detalle de la transacción.', 500);
        }

        if ($datos === null) {
            return $this->error('No se encontró la transacción ' . $numero . '.', 404);
        }

        return $this->response->setJSON(array_merge(['success' => true, 'message' => ''], $datos));
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /**
     * Normaliza y valida el encabezado, el tiquete y las líneas. El peso neto y
     * el peso promedio de cada lote se resuelven en el servidor: lo que mande el
     * cliente no se usa.
     *
     * @return array{empresa: int, fecha: string, observacion: string, usuario: string, confirmaFaltante: int,
     *               racimosLineas: int, totalTrabajadores: int, lineasSinTrabajadores: array<int, int>,
     *               bascula: array<string, mixed>, lineas: array<int, array<string, mixed>>}|string
     */
    private function datosValidos(): array|string
    {
        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return 'Debe seleccionar una empresa válida.';
        }

        $fecha = $this->fechaValida($this->campo('fecha'));

        if ($fecha === null) {
            return 'El campo "Fecha" es obligatorio y debe tener el formato AAAA-MM-DD.';
        }

        $anio = trim($this->campo('anio'));
        $mes  = trim($this->campo('mes'));

        if (preg_match('/^\d{4}$/', $anio) !== 1 || preg_match('/^\d{1,2}$/', $mes) !== 1) {
            return 'Los campos "Año" y "Periodo" son obligatorios.';
        }

        $anio    = (int) $anio;
        $mes     = (int) $mes;
        $periodo = $this->produccionModel->periodo($empresa, $anio, $mes);

        if ($periodo === null) {
            return 'El periodo ' . $this->etiquetaPeriodo($anio, $mes) . ' no existe para la empresa.';
        }

        if ($periodo['fechaInicial'] !== null && $periodo['fechaFinal'] !== null) {
            if ($fecha < $periodo['fechaInicial'] || $fecha > $periodo['fechaFinal']) {
                return 'La fecha ' . $fecha . ' no pertenece al periodo ' . $this->etiquetaPeriodo($anio, $mes)
                    . ' (del ' . $periodo['fechaInicial'] . ' al ' . $periodo['fechaFinal'] . ').';
            }
        } elseif (substr($fecha, 0, 7) !== $this->etiquetaPeriodo($anio, $mes)) {
            return 'La fecha ' . $fecha . ' no pertenece al periodo ' . $this->etiquetaPeriodo($anio, $mes) . '.';
        }

        $tiquete = trim($this->campo('tiquete'));

        if ($tiquete === '') {
            return 'El campo "Tiquete" es obligatorio.';
        }

        if (strlen($tiquete) > 50) {
            return 'El campo "Tiquete" admite máximo 50 caracteres.';
        }

        $pesoBruto = $this->enteroValido('pesoBruto', 'Peso bruto');

        if (is_string($pesoBruto)) {
            return $pesoBruto;
        }

        $pesoTara = $this->enteroValido('pesoTara', 'Peso tara');

        if (is_string($pesoTara)) {
            return $pesoTara;
        }

        if ($pesoTara > $pesoBruto) {
            return 'El peso tara no puede ser mayor que el peso bruto.';
        }

        $sacos = $this->enteroValido('sacos', 'Sacos');

        if (is_string($sacos)) {
            return $sacos;
        }

        $racimos = $this->enteroValido('racimos', 'Racimos');

        if (is_string($racimos)) {
            return $racimos;
        }

        foreach (['vehiculo' => 'Vehículo', 'remolque' => 'Remolque', 'planta' => 'Planta', 'codigoConductor' => 'Cédula del conductor'] as $campo => $etiqueta) {
            if (strlen(trim($this->campo($campo))) > 50) {
                return 'El campo "' . $etiqueta . '" admite máximo 50 caracteres.';
            }
        }

        $nombreConductor = trim($this->campo('nombreConductor'));

        if (strlen($nombreConductor) > 550) {
            return 'El campo "Nombre del conductor" admite máximo 550 caracteres.';
        }

        $observacion = trim($this->campo('observacion'));

        if (strlen($observacion) > 2550) {
            return 'El campo "Observación" admite máximo 2550 caracteres.';
        }

        $extractora = trim($this->campo('extractora'));

        if ($extractora !== '') {
            if (strlen($extractora) > 50) {
                return 'El campo "Extractora" admite máximo 50 caracteres.';
            }

            if (! $this->produccionModel->extractoraValida($empresa, $extractora)) {
                return 'La extractora seleccionada no existe o no está marcada como extractora.';
            }
        }

        $lineas = $this->lineasValidas($empresa, $fecha);

        if (is_string($lineas)) {
            return $lineas;
        }

        $racimosLineas    = 0;
        $totalTrabajadores = 0;
        $sinTrabajadores  = [];

        foreach ($lineas as $i => $linea) {
            $racimosLineas += (int) $linea['racimos'];

            if ($linea['trabajadores'] === []) {
                $sinTrabajadores[] = $i + 1;

                continue;
            }

            $totalTrabajadores += count($linea['trabajadores']);
        }

        return [
            'empresa'               => $empresa,
            'fecha'                 => $fecha,
            'anio'                  => $anio,
            'mes'                   => $mes,
            'periodoCerrado'        => $periodo['cerrado'],
            'observacion'           => $observacion,
            'usuario'               => $this->usuarioRegistro(),
            'confirmaFaltante'      => (int) $this->campo('confirmaFaltante') === 1 ? 1 : 0,
            'racimosLineas'         => $racimosLineas,
            'totalTrabajadores'     => $totalTrabajadores,
            'lineasSinTrabajadores' => $sinTrabajadores,
            'bascula'               => [
                'tiquete'         => $tiquete,
                'planta'          => trim($this->campo('planta')) === '' ? null : trim($this->campo('planta')),
                'pesoBruto'       => $pesoBruto,
                'pesoTara'        => $pesoTara,
                'pesoNeto'        => $pesoBruto - $pesoTara,
                'sacos'           => $sacos,
                'racimos'         => $racimos,
                'vehiculo'        => trim($this->campo('vehiculo')),
                'remolque'        => trim($this->campo('remolque')),
                'interno'         => (int) $this->campo('externo') === 1 ? 0 : 1,
                'codigoConductor' => trim($this->campo('codigoConductor')) === '' ? null : trim($this->campo('codigoConductor')),
                'nombreConductor' => $nombreConductor === '' ? null : $nombreConductor,
                'extractora'      => $extractora === '' ? null : $extractora,
            ],
            'lineas'                => $lineas,
        ];
    }

    /**
     * Las líneas llegan como JSON en el campo "lineas", igual que el editor
     * masivo de precios.
     *
     * @return array<int, array<string, mixed>>|string
     */
    private function lineasValidas(int $empresa, string $fecha): array|string
    {
        $crudo = $this->campo('lineas');

        if (trim($crudo) === '') {
            return 'Debe registrar al menos una línea de distribución.';
        }

        $recibidas = json_decode($crudo, true);

        if (! is_array($recibidas) || $recibidas === []) {
            return 'No se pudieron leer las líneas de distribución enviadas.';
        }

        if (count($recibidas) > self::MAX_LINEAS) {
            return 'La transacción admite máximo ' . self::MAX_LINEAS . ' líneas de distribución.';
        }

        $anio   = (int) substr($fecha, 0, 4);
        $mes    = (int) substr($fecha, 5, 2);
        $lineas = [];

        foreach ($recibidas as $i => $fila) {
            $orden = $i + 1;

            if (! is_array($fila)) {
                return 'No se pudieron leer los datos de la línea ' . $orden . '.';
            }

            $finca = trim((string) ($fila['finca'] ?? ''));
            $lote  = trim((string) ($fila['lote'] ?? ''));

            if ($finca === '' || $lote === '') {
                return 'La línea ' . $orden . ' debe tener finca y lote.';
            }

            if (strlen($finca) > 50 || strlen($lote) > 50) {
                return 'La finca y el lote de la línea ' . $orden . ' admiten máximo 50 caracteres.';
            }

            if (! $this->produccionModel->fincaValida($empresa, $finca)) {
                return 'La finca ' . $finca . ' de la línea ' . $orden . ' no existe.';
            }

            if (! $this->produccionModel->loteDeFinca($empresa, $finca, $lote)) {
                return 'El lote ' . $lote . ' no pertenece a la finca ' . $finca . ' (línea ' . $orden . ').';
            }

            $racimos = $this->enteroDe($fila['racimos'] ?? null);

            if ($racimos === null || $racimos <= 0) {
                return 'Los racimos de la línea ' . $orden . ' deben ser un número entero mayor que cero.';
            }

            $sacos = $this->enteroDe($fila['sacos'] ?? null);

            if ($sacos === null || $sacos < 0) {
                return 'Los sacos de la línea ' . $orden . ' deben ser un número entero mayor o igual a cero.';
            }

            $trabajadores = $this->trabajadoresValidos($empresa, $fecha, $fila['trabajadores'] ?? [], $orden);

            if (is_string($trabajadores)) {
                return $trabajadores;
            }

            $lineas[] = [
                'finca'        => $finca,
                'lote'         => $lote,
                'racimos'      => $racimos,
                'sacos'        => $sacos,
                'pesoRacimo'   => $this->produccionModel->pesoPromedio($empresa, $anio, $mes, $finca, $lote),
                'trabajadores' => $trabajadores,
            ];
        }

        return $lineas;
    }

    /**
     * @return array<int, array<string, mixed>>|string
     */
    private function trabajadoresValidos(int $empresa, string $fecha, $recibidos, int $orden): array|string
    {
        if (! is_array($recibidos)) {
            return 'No se pudieron leer los trabajadores de la línea ' . $orden . '.';
        }

        if (count($recibidos) > self::MAX_TRABAJADORES) {
            return 'La línea ' . $orden . ' admite máximo ' . self::MAX_TRABAJADORES . ' trabajadores.';
        }

        $trabajadores = [];

        foreach ($recibidos as $fila) {
            if (! is_array($fila)) {
                return 'No se pudieron leer los trabajadores de la línea ' . $orden . '.';
            }

            $tercero = trim((string) ($fila['tercero'] ?? ''));

            if ($tercero === '' || strlen($tercero) > 50) {
                return 'El trabajador de la línea ' . $orden . ' es obligatorio y admite máximo 50 caracteres.';
            }

            if (! $this->produccionModel->terceroValido($empresa, $tercero)) {
                return 'El trabajador ' . $tercero . ' de la línea ' . $orden . ' no existe.';
            }

            $novedad = trim((string) ($fila['novedad'] ?? ''));

            if ($novedad === '' || strlen($novedad) > 50) {
                return 'La labor del trabajador ' . $tercero . ' es obligatoria y admite máximo 50 caracteres.';
            }

            if (! $this->produccionModel->laborValida($empresa, $novedad)) {
                return 'La labor ' . $novedad . ' del trabajador ' . $tercero . ' no existe.';
            }

            $cantidad = $this->decimalDe($fila['cantidad'] ?? null);

            if ($cantidad === null || $cantidad < 0) {
                return 'La cantidad del trabajador ' . $tercero . ' de la línea ' . $orden . ' debe ser un número mayor o igual a cero.';
            }

            $jornales = $this->decimalDe($fila['jornales'] ?? null);

            if ($jornales === null || $jornales < 0) {
                return 'Los jornales del trabajador ' . $tercero . ' de la línea ' . $orden . ' deben ser un número mayor o igual a cero.';
            }

            $precio = $this->decimalDe($fila['precioLabor'] ?? null);

            if ($precio === null || $precio < 0) {
                return 'El precio del trabajador ' . $tercero . ' de la línea ' . $orden . ' debe ser un número mayor o igual a cero.';
            }

            $racimos = $this->enteroDe($fila['racimos'] ?? 0);

            if ($racimos === null || $racimos < 0) {
                return 'Los racimos del trabajador ' . $tercero . ' de la línea ' . $orden . ' deben ser un número entero mayor o igual a cero.';
            }

            $fechaNovedad = trim((string) ($fila['fechaNovedad'] ?? ''));
            $fechaNovedad = $fechaNovedad === '' ? $fecha : $this->fechaValida($fechaNovedad);

            if ($fechaNovedad === null) {
                return 'La fecha de la labor del trabajador ' . $tercero . ' de la línea ' . $orden . ' no es válida.';
            }

            $trabajadores[] = [
                'tercero'      => $tercero,
                'novedad'      => $novedad,
                'cantidad'     => $cantidad,
                'jornales'     => $jornales,
                'precioLabor'  => $precio,
                'racimos'      => $racimos,
                'contratista'  => (int) ($fila['contratista'] ?? 0) === 1 ? 1 : 0,
                'fechaNovedad' => $fechaNovedad,
            ];
        }

        return $trabajadores;
    }

    /**
     * @param array<int, int>                  $sinPeso
     * @param array<int, array<string, mixed>> $lineas
     *
     * @return array<int, string>
     */
    private function avisosSinPeso(array $sinPeso, array $lineas, string $fecha): array
    {
        $avisos = [];

        foreach ($sinPeso as $i) {
            $avisos[] = 'El lote ' . $lineas[$i]['lote'] . ' de la finca ' . $lineas[$i]['finca']
                . ' no tiene peso promedio registrado en el periodo ' . substr($fecha, 0, 7) . ', por lo que quedó en 0 kilos.';
        }

        return $avisos;
    }

    private function fechaValida(string $fecha): ?string
    {
        $fecha = trim($fecha);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes) !== 1) {
            return null;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? $fecha : null;
    }

    /**
     * @return int|string El entero, o el mensaje de error.
     */
    private function enteroValido(string $nombre, string $etiqueta): int|string
    {
        $valor = $this->enteroDe($this->campo($nombre));

        if ($valor === null || $valor < 0) {
            return 'El campo "' . $etiqueta . '" debe ser un número entero mayor o igual a cero.';
        }

        return $valor;
    }

    private function enteroDe($valor): ?int
    {
        if (is_array($valor)) {
            return null;
        }

        $valor = trim((string) ($valor ?? ''));

        if ($valor === '') {
            return 0;
        }

        if (preg_match('/^-?\d{1,10}$/', $valor) !== 1) {
            return null;
        }

        return (int) $valor;
    }

    private function decimalDe($valor): ?float
    {
        if (is_array($valor)) {
            return null;
        }

        $valor = trim((string) ($valor ?? ''));

        if ($valor === '') {
            return 0.0;
        }

        if (preg_match('/^-?\d{1,15}([.,]\d{1,4})?$/', $valor) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $valor);
    }

    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : substr($usuario, 0, 50);
    }

    private function sesionRequerida(array $acciones = [], string $mensaje = ''): ?ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Sesión expirada. Vuelva a iniciar sesión.',
            ])->setStatusCode(401);
        }

        foreach ($acciones as $accion) {
            if ($this->permisos()[$accion]) {
                return null;
            }
        }

        return $acciones === [] ? null : $this->error('No tiene permiso para ' . $mensaje . '.', 403);
    }

    private function permisos(): array
    {
        if ($this->permisos === null) {
            $acciones = $this->produccionModel->accionesPermitidas((int) $this->session->get('usu_id'), self::MODULO);

            foreach (['registrar', 'consultar', 'editar', 'eliminar'] as $accion) {
                $this->permisos[$accion] = in_array($accion . '-produccion', $acciones, true);
            }
        }

        return $this->permisos;
    }

    /**
     * Un parámetro que llega como arreglo se trata como entrada vacía: así
     * termina en el 422 de validación que corresponda y no en un
     * "Array to string conversion" con traza y ruta absoluta.
     */
    private function campo(string $nombre): string
    {
        $valor = $this->request->getVar($nombre);

        if (is_array($valor)) {
            return '';
        }

        return (string) ($valor ?? '');
    }

    private function empresaValida(): ?int
    {
        $empresa = (int) $this->campo('empresa');

        if ($empresa <= 0 || ! $this->produccionModel->empresaExiste($empresa)) {
            return null;
        }

        return $empresa;
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode($codigo);
    }
}
