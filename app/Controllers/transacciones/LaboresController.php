<?php

namespace App\Controllers\transacciones;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\LaboresTransaccionModel;

class LaboresController extends BaseController
{
    protected $session;
    protected $laboresModel;
    protected ?array $permisos = null;

    protected const MAX_LINEAS       = 500;
    protected const MAX_TRABAJADORES = 500;

    protected const CAMPOS     = ['fecha', 'finca', 'numero', 'observacion', 'seccion', 'tipo'];
    protected const OPERADORES = ['igual', 'diferente', 'mayor', 'menor', 'mayorIgual', 'menorIgual', 'contiene'];

    protected const MODELO  = LaboresTransaccionModel::class;
    protected const TITULO  = 'Transacciones · Labores';
    protected const VISTA   = 'transacciones/labores';
    protected const NOMBRE  = 'labores';
    protected const PERMISO = 'labores';

    public function __construct()
    {
        $this->session      = session();
        $this->laboresModel = new (static::MODELO)();
    }

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

        $empresas   = $this->laboresModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;
        $pedida     = (int) $this->request->getGet('empresa');

        if ($pedida > 0 && in_array($pedida, array_map('intval', array_column($empresas, 'id')), true)) {
            $empresaSel = $pedida;
        }

        $data['title']       = static::TITULO;
        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresaSel;
        $data['tipos']       = $empresaSel > 0 ? $this->laboresModel->tipos($empresaSel) : [];
        $data['labores']     = $empresaSel > 0 ? $this->laboresModel->labores($empresaSel) : [];
        $data['unidades']    = $empresaSel > 0 ? $this->laboresModel->unidades($empresaSel) : [];
        $data['fincas']      = $empresaSel > 0 ? $this->laboresModel->fincas($empresaSel) : [];
        $data['anios']       = $empresaSel > 0 ? $this->laboresModel->anios($empresaSel) : [];
        $actual              = $empresaSel > 0 ? $this->laboresModel->periodoActual($empresaSel) : null;
        $data['anio_actual'] = $actual['anio'] ?? (int) date('Y');
        $data['mes_actual']  = $actual['mes'] ?? (int) date('n');
        $data['permisos']    = $this->permisos();
        $data               += $empresaSel > 0 ? $this->datosVista($empresaSel) : [];

        return view(static::VISTA, $data);
    }

    public function periodos(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de ' . static::NOMBRE)) {
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

        return $this->listado(fn () => $this->laboresModel->periodos($empresa, (int) $anio), 'periodos');
    }

    public function secciones(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de ' . static::NOMBRE)) {
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

        return $this->listado(fn () => $this->laboresModel->secciones($empresa, $finca), 'secciones');
    }

    public function lotes(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de ' . static::NOMBRE)) {
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

        return $this->listado(fn () => $this->laboresModel->lotes($empresa, $finca, trim($this->campo('seccion'))), 'lotes');
    }

    public function terceros(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        return $this->listado(fn () => $this->laboresModel->buscarTercero($empresa, $this->campo('termino')), 'terceros');
    }

    public function liquidar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar transacciones de ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $datos = $this->datosValidos(true);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $lineas = [];

        foreach ($datos['lineas'] as $i => $linea) {
            $lineas[] = [
                'registro'     => $i + 1,
                'trabajadores' => array_map(static fn ($t) => [
                    'tercero'     => $t['tercero'],
                    'precioLabor' => $t['precioLabor'],
                    'valorTotal'  => $t['valorTotal'],
                    'existe'      => $t['precioLabor'] > 0 ? 1 : 0,
                ], $linea['trabajadores']),
                'valorTotal'   => $linea['valorTotal'],
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'lineas'  => $lineas,
            'total'   => round(array_sum(array_column($lineas, 'valorTotal')), 0),
        ]);
    }

    public function guardar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar'], 'registrar transacciones de ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $datos = $this->datosValidos();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if ($datos['periodoCerrado'] === 1) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($datos['anio'], $datos['mes']) . ' está cerrado.');
        }

        try {
            $resultado = $this->laboresModel->guardar($datos, $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error registrando la transacción de labores: ' . $e->getMessage());

            return $this->error('No se pudo registrar la transacción.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message'], 500);
        }

        return $this->response->setJSON($resultado);
    }

    public function actualizar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['editar'], 'editar transacciones de ' . static::NOMBRE)) {
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

        if ($datos['periodoCerrado'] === 1) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($datos['anio'], $datos['mes']) . ' está cerrado.');
        }

        try {
            $resultado = $this->laboresModel->actualizar($datos, $antes, $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error actualizando la transacción de labores: ' . $e->getMessage());

            return $this->error('No se pudo actualizar la transacción.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message'], 500);
        }

        return $this->response->setJSON($resultado);
    }

    public function consultar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['consultar'], 'consultar transacciones de ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $campo    = trim($this->campo('campo'));
        $operador = trim($this->campo('operador'));
        $valor    = trim($this->campo('valor'));

        if ($valor !== '') {
            if (! in_array($campo, static::CAMPOS, true)) {
                return $this->error('El campo de filtro no es válido.');
            }

            if (! in_array($operador, self::OPERADORES, true)) {
                return $this->error('El operador de filtro no es válido.');
            }

            if (strlen($valor) > 250) {
                return $this->error('El valor del filtro admite máximo 250 caracteres.');
            }

            if ($campo === 'fecha') {
                if ($operador === 'contiene') {
                    return $this->error('El operador "Contiene" no aplica al campo "Fecha".');
                }

                if ($this->fechaValida($valor) === null) {
                    return $this->error('El valor de "Fecha" debe tener el formato AAAA-MM-DD.');
                }
            }
        }

        try {
            $filas = $this->laboresModel->filtrar($empresa, $campo, $operador, $valor);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando las transacciones de labores: ' . $e->getMessage());

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
        if ($sinSesion = $this->sesionRequerida(['consultar', 'editar'], 'consultar transacciones de ' . static::NOMBRE)) {
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

        try {
            $datos = $this->laboresModel->detalle($empresa, $numero);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el detalle de la transacción: ' . $e->getMessage());

            return $this->error('No se pudo consultar el detalle de la transacción.', 500);
        }

        if ($datos === null) {
            return $this->error('No se encontró la transacción ' . $numero . '.', 404);
        }

        return $this->response->setJSON(array_merge(['success' => true, 'message' => ''], $datos));
    }

    public function eliminar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['eliminar'], 'eliminar transacciones de ' . static::NOMBRE)) {
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

        if (! $this->laboresModel->tablaEliminadas()) {
            return $this->error('La eliminación de transacciones no está disponible: falta crear la tabla aTransaccionEliminada.', 500);
        }

        $antes = $this->transaccionEditable($empresa, $numero, 'eliminar');

        if ($antes instanceof ResponseInterface) {
            return $antes;
        }

        try {
            $resultado = $this->laboresModel->eliminar($empresa, $antes, $motivo, $this->usuarioRegistro(), $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error eliminando la transacción de labores: ' . $e->getMessage());

            return $this->error('No se pudo eliminar la transacción.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message'], 500);
        }

        return $this->response->setJSON($resultado);
    }

    protected function listado(callable $consulta, string $nombre): ResponseInterface
    {
        try {
            $filas = $consulta();
        } catch (\Throwable $e) {
            log_message('error', 'Error listando ' . $nombre . ': ' . $e->getMessage());

            return $this->error('No se pudo leer el listado de ' . $nombre . '.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'filas'   => $filas,
        ]);
    }

    protected function transaccionEditable(int $empresa, string $numero, string $accion): array|ResponseInterface
    {
        try {
            $antes = $this->laboresModel->detalle($empresa, $numero);
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

        if ($this->laboresModel->liquidada($empresa, $numero)) {
            return $this->error('La transacción ' . $numero . ' ya fue liquidada o ejecutada en nómina y no se puede ' . $accion . '.');
        }

        if ($antes['encabezado']['cerrado'] === 1) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($antes['encabezado']['anio'], $antes['encabezado']['mes']) . ' está cerrado.');
        }

        return $antes;
    }

    protected function datosValidos(bool $liquidar = false): array|string
    {
        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return 'Debe seleccionar una empresa válida.';
        }

        if (! $liquidar && trim($this->campo('tipo')) !== $this->laboresModel::TIPO) {
            return 'El campo "Tipo transacción" es obligatorio y debe ser ' . $this->laboresModel::TIPO . '.';
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
        $periodo = $this->laboresModel->periodo($empresa, $anio, $mes);

        if ($periodo === null) {
            return 'El periodo ' . $this->etiquetaPeriodo($anio, $mes) . ' no existe para la empresa.';
        }

        if ($error = $this->fueraDePeriodo($fecha, $periodo, $anio, $mes)) {
            return $error;
        }

        $observacion = trim($this->campo('observacion'));

        if (strlen($observacion) > 2550) {
            return 'El campo "Observación" admite máximo 2550 caracteres.';
        }

        $extra = $this->encabezadoValido($empresa, $liquidar);

        if (is_string($extra)) {
            return $extra;
        }

        $lineas = $this->lineasValidas($empresa, $fecha, $periodo, $anio, $mes, $extra);

        if (is_string($lineas)) {
            return $lineas;
        }

        unset($extra['todasFechas']);

        return array_merge([
            'empresa'        => $empresa,
            'fecha'          => $fecha,
            'anio'           => $anio,
            'mes'            => $mes,
            'periodoCerrado' => $periodo['cerrado'],
            'observacion'    => $observacion,
            'usuario'        => $this->usuarioRegistro(),
            'lineas'         => $lineas,
        ], $extra);
    }

    protected function encabezadoValido(int $empresa, bool $liquidar): array|string
    {
        $remision = trim($this->campo('remision'));

        if (strlen($remision) > 50) {
            return 'El campo "Remisión" admite máximo 50 caracteres.';
        }

        return ['remision' => $remision, 'todasFechas' => $liquidar || $this->campo('todasFechas') === '1'];
    }

    protected function lineaValida(int $empresa, int $orden, array &$linea, array $extra, array $fila): ?string
    {
        return null;
    }

    protected function datosVista(int $empresa): array
    {
        return [];
    }

    protected function lineasValidas(int $empresa, string $fecha, array $periodo, int $anio, int $mes, array $extra): array|string
    {
        $recibidas = json_decode($this->campo('lineas'), true);

        if (! is_array($recibidas) || $recibidas === []) {
            return 'Debe registrar al menos una línea en el detalle.';
        }

        if (count($recibidas) > self::MAX_LINEAS) {
            return 'La transacción admite máximo ' . self::MAX_LINEAS . ' líneas.';
        }

        $lineas = [];

        foreach (array_values($recibidas) as $i => $fila) {
            $orden = $i + 1;

            if (! is_array($fila)) {
                return 'No se pudieron leer los datos de la línea ' . $orden . '.';
            }

            $valores = [];

            foreach (['novedad', 'uMedida', 'finca', 'seccion', 'lote'] as $c) {
                $valores[$c] = is_array($fila[$c] ?? null) ? '' : trim((string) ($fila[$c] ?? ''));

                if (strlen($valores[$c]) > 50) {
                    return 'Los datos de la línea ' . $orden . ' admiten máximo 50 caracteres por campo.';
                }
            }

            ['novedad' => $novedad, 'uMedida' => $uMedida, 'finca' => $finca, 'seccion' => $seccion, 'lote' => $lote] = $valores;

            if ($novedad === '' || $uMedida === '' || $finca === '' || $lote === '') {
                return 'La línea ' . $orden . ' debe tener labor, unidad de medida, finca y lote.';
            }

            if (! $this->laboresModel->laborValida($empresa, $novedad)) {
                return 'La labor ' . $novedad . ' de la línea ' . $orden . ' no existe.';
            }

            if (! $this->laboresModel->unidadValida($empresa, $uMedida)) {
                return 'La unidad de medida ' . $uMedida . ' de la línea ' . $orden . ' no existe.';
            }

            if (! $this->laboresModel->fincaValida($empresa, $finca)) {
                return 'La finca ' . $finca . ' de la línea ' . $orden . ' no existe.';
            }

            if ($seccion !== '' && ! $this->laboresModel->seccionDeFinca($empresa, $finca, $seccion)) {
                return 'La sección ' . $seccion . ' no pertenece a la finca ' . $finca . ' (línea ' . $orden . ').';
            }

            if (! $this->laboresModel->loteDeFinca($empresa, $finca, $lote)) {
                return 'El lote ' . $lote . ' no pertenece a la finca ' . $finca . ' (línea ' . $orden . ').';
            }

            $cantidad = $this->decimalDe($fila['cantidad'] ?? null);

            if ($cantidad === null || $cantidad <= 0) {
                return 'La cantidad de la línea ' . $orden . ' debe ser un número mayor que cero con máximo 2 decimales.';
            }

            $jornales = $this->decimalDe($fila['jornales'] ?? null);

            if ($jornales === null || $jornales < 0) {
                return 'Los jornales de la línea ' . $orden . ' deben ser un número mayor o igual a cero con máximo 2 decimales.';
            }

            $fechaLinea = $this->fechaValida(is_array($fila['fecha'] ?? null) ? '' : (string) ($fila['fecha'] ?? ''));

            if ($fechaLinea === null) {
                return 'La fecha de la línea ' . $orden . ' es obligatoria y debe tener el formato AAAA-MM-DD.';
            }

            if (! $extra['todasFechas'] && $fechaLinea !== $fecha) {
                return 'La fecha de la línea ' . $orden . ' debe ser igual a la fecha de la transacción (' . $fecha . ').';
            }

            if ($error = $this->fueraDePeriodo($fechaLinea, $periodo, $anio, $mes)) {
                return 'Línea ' . $orden . ': ' . $error;
            }

            $recibidos = $fila['trabajadores'] ?? null;

            if (! is_array($recibidos) || $recibidos === []) {
                return 'La línea ' . $orden . ' debe tener al menos un trabajador.';
            }

            if (count($recibidos) > self::MAX_TRABAJADORES) {
                return 'La línea ' . $orden . ' admite máximo ' . self::MAX_TRABAJADORES . ' trabajadores.';
            }

            $trabajadores = [];
            $sumaCantidad = 0;
            $sumaJornales = 0;

            foreach (array_values($recibidos) as $trabajador) {
                $tercero = is_array($trabajador) && ! is_array($trabajador['tercero'] ?? null) ? trim((string) ($trabajador['tercero'] ?? '')) : '';

                if ($tercero === '' || strlen($tercero) > 50) {
                    return 'Los trabajadores de la línea ' . $orden . ' deben tener tercero (máximo 50 caracteres).';
                }

                if (isset($trabajadores[$tercero])) {
                    return 'El tercero ' . $tercero . ' está repetido en la línea ' . $orden . '.';
                }

                if (! $this->laboresModel->terceroValido($empresa, $tercero)) {
                    return 'El tercero ' . $tercero . ' de la línea ' . $orden . ' no existe.';
                }

                $cantidadT = $this->decimalDe($trabajador['cantidad'] ?? null);

                if ($cantidadT === null || $cantidadT <= 0) {
                    return 'La cantidad del tercero ' . $tercero . ' en la línea ' . $orden . ' debe ser un número mayor que cero con máximo 2 decimales.';
                }

                $jornalesT = $this->decimalDe($trabajador['jornales'] ?? null);

                if ($jornalesT === null || $jornalesT < 0) {
                    return 'Los jornales del tercero ' . $tercero . ' en la línea ' . $orden . ' deben ser un número mayor o igual a cero con máximo 2 decimales.';
                }

                $precio        = $this->laboresModel->precioLabor($empresa, $novedad, (int) substr($fechaLinea, 0, 4), $tercero, $fechaLinea, $finca, $lote, $seccion);
                $sumaCantidad += (int) round($cantidadT * 100);
                $sumaJornales += (int) round($jornalesT * 100);

                $trabajadores[$tercero] = [
                    'tercero'     => $tercero,
                    'cantidad'    => $cantidadT,
                    'jornales'    => $jornalesT,
                    'precioLabor' => $precio,
                    'valorTotal'  => round($cantidadT * $precio, 0),
                ];
            }

            if ($sumaCantidad !== (int) round($cantidad * 100)) {
                return 'En la línea ' . $orden . ' la suma de cantidades de los trabajadores (' . number_format($sumaCantidad / 100, 2, '.', '')
                    . ') no coincide con la cantidad total (' . number_format($cantidad, 2, '.', '') . ').';
            }

            if ($sumaJornales !== (int) round($jornales * 100)) {
                return 'En la línea ' . $orden . ' la suma de jornales de los trabajadores (' . number_format($sumaJornales / 100, 2, '.', '')
                    . ') no coincide con los jornales totales (' . number_format($jornales, 2, '.', '') . ').';
            }

            $trabajadores = array_values($trabajadores);

            $linea = [
                'novedad'      => $novedad,
                'uMedida'      => $uMedida,
                'fecha'        => $fechaLinea,
                'finca'        => $finca,
                'seccion'      => $seccion,
                'lote'         => $lote,
                'cantidad'     => $cantidad,
                'jornales'     => $jornales,
                'precioLabor'  => $trabajadores[0]['precioLabor'],
                'valorTotal'   => round(array_sum(array_column($trabajadores, 'valorTotal')), 0),
                'concepto'     => $this->laboresModel->concepto($empresa, $novedad),
                'trabajadores' => $trabajadores,
            ];

            if ($error = $this->lineaValida($empresa, $orden, $linea, $extra, $fila)) {
                return $error;
            }

            $lineas[] = $linea;
        }

        return $lineas;
    }

    protected function fueraDePeriodo(string $fecha, array $periodo, int $anio, int $mes): ?string
    {
        if ($periodo['fechaInicial'] !== null && $periodo['fechaFinal'] !== null) {
            if ($fecha < $periodo['fechaInicial'] || $fecha > $periodo['fechaFinal']) {
                return 'La fecha ' . $fecha . ' no pertenece al periodo ' . $this->etiquetaPeriodo($anio, $mes)
                    . ' (del ' . $periodo['fechaInicial'] . ' al ' . $periodo['fechaFinal'] . ').';
            }
        } elseif (substr($fecha, 0, 7) !== $this->etiquetaPeriodo($anio, $mes)) {
            return 'La fecha ' . $fecha . ' no pertenece al periodo ' . $this->etiquetaPeriodo($anio, $mes) . '.';
        }

        return null;
    }

    protected function etiquetaPeriodo(int $anio, int $mes): string
    {
        return $anio . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
    }

    protected function fechaValida(string $fecha): ?string
    {
        $fecha = trim($fecha);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes) !== 1) {
            return null;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? $fecha : null;
    }

    protected function decimalDe($valor): ?float
    {
        if (is_array($valor)) {
            return null;
        }

        $valor = trim((string) ($valor ?? ''));

        if ($valor === '') {
            return 0.0;
        }

        if (preg_match('/^-?\d{1,15}([.,]\d{1,2})?$/', $valor) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $valor);
    }

    protected function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : substr($usuario, 0, 50);
    }

    protected function sesionRequerida(array $acciones = [], string $mensaje = ''): ?ResponseInterface
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

    protected function permisos(): array
    {
        if ($this->permisos === null) {
            $modulo   = $this->laboresModel->idModulo();
            $acciones = $modulo > 0 ? $this->laboresModel->accionesPermitidas((int) $this->session->get('usu_id'), $modulo) : [];

            foreach (['registrar', 'consultar', 'editar', 'eliminar'] as $accion) {
                $this->permisos[$accion] = in_array($accion . '-' . static::PERMISO, $acciones, true);
            }
        }

        return $this->permisos;
    }

    protected function campo(string $nombre): string
    {
        $valor = $this->request->getVar($nombre);

        if (is_array($valor)) {
            return '';
        }

        return (string) ($valor ?? '');
    }

    protected function empresaValida(): ?int
    {
        $empresa = (int) $this->campo('empresa');

        if ($empresa <= 0 || ! $this->laboresModel->empresaExiste($empresa)) {
            return null;
        }

        return $empresa;
    }

    protected function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode($codigo);
    }
}
