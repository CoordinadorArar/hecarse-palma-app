<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\PresupuestoProduccionModel;
use App\Models\ReportesModel;

class PresupuestoProduccionController extends GestionPalmaController
{
    private const RUTA = 'gestion-palma/contabilidad/presupuesto';

    private const SIN_METAS = 'Las tablas de metas del presupuesto aún no han sido creadas. Ejecute el script Documentacion/BaseDeDatos/crear_presupuesto_metas.sql.';

    private $presupuestoModel;
    private ReportesModel $reportesModel;

    public function __construct()
    {
        parent::__construct();

        $this->presupuestoModel = new PresupuestoProduccionModel();
        $this->reportesModel    = new ReportesModel();
    }

    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::RUTA)) {
            return redirect()->to(base_url('losetas'));
        }

        $empresas = $this->presupuestoModel->getEmpresas();

        $data['title']        = 'Gestión Palma · Presupuesto de producción';
        $data['empresas']     = $empresas;
        $data['empresa_sel']  = $this->empresaSeleccionada($empresas);
        $data['anios']        = $this->presupuestoModel->getAnios();
        $data['anio_sel']     = (int) date('Y');
        $data['tabla_existe'] = $this->presupuestoModel->tablaExiste();

        return view('gestionpalma/presupuesto_produccion', $data);
    }

    public function listar(): ResponseInterface
    {
        $base = $this->validarBase(false);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio] = $base;
        $existe = $this->presupuestoModel->tablaExiste();
        $finca  = trim((string) $this->request->getPost('finca'));

        if ($finca !== '' && ! $this->presupuestoModel->fincaExiste($empresa, $finca)) {
            return $this->error('La finca seleccionada no existe para esta empresa.');
        }

        return $this->response->setJSON([
            'success'     => true,
            'message'     => $existe ? '' : 'La tabla de presupuesto de producción aún no ha sido creada.',
            'tablaExiste' => $existe,
            'data'        => $this->presupuestoModel->listar($empresa, $anio, $finca),
            'anterior'    => $this->presupuestoModel->listar($empresa, $anio - 1, $finca),
        ]);
    }

    public function guardar(): ResponseInterface
    {
        $base = $this->validarBase(true);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio, $finca] = $base;

        $mes = trim((string) $this->request->getPost('mes'));

        if (! ctype_digit($mes) || (int) $mes < 1 || (int) $mes > 12) {
            return $this->error('El mes debe estar entre 1 y 12.');
        }

        $mes  = (int) $mes;
        $lote = trim((string) $this->request->getPost('lote'));

        if ($lote === '' || ! $this->presupuestoModel->loteValido($empresa, $finca, $lote)) {
            return $this->error('El lote seleccionado no existe, no está activo o no pertenece a la finca.');
        }

        $kilos = $this->request->getPost('kilos');
        $kilos = is_scalar($kilos) ? str_replace(' ', '', trim((string) $kilos)) : null;

        if ($kilos === null || ! preg_match('/^\d{1,16}(\.\d{1,2})?$/', $kilos)) {
            return $this->error('Los kilos deben ser un número mayor o igual a 0 con máximo 2 decimales.');
        }

        if ((float) $kilos > 0 && $this->presupuestoModel->tieneHistorico($empresa, $anio, $mes, $finca)) {
            return $this->error('La finca ya tiene una meta de presupuesto a nivel finca para este mes. Elimine primero esa meta para registrar el presupuesto por lote y evitar duplicarlo.', 409);
        }

        if (! $this->presupuestoModel->guardar($empresa, $anio, $mes, $finca, $lote, $kilos, $this->usuarioRegistro())) {
            return $this->error('No se pudo guardar el presupuesto. La operación fue revertida.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => (float) $kilos > 0 ? 'Presupuesto guardado correctamente.' : 'Presupuesto del lote eliminado correctamente.',
        ]);
    }

    public function eliminar(): ResponseInterface
    {
        $base = $this->validarBase(true);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio, $finca] = $base;

        $mes = trim((string) $this->request->getPost('mes'));

        if ($mes !== '' && (! ctype_digit($mes) || (int) $mes < 1 || (int) $mes > 12)) {
            return $this->error('El mes debe estar entre 1 y 12.');
        }

        $lote = trim((string) $this->request->getPost('lote'));

        if (mb_strlen($lote) > 50) {
            return $this->error('El lote no es válido.');
        }

        if (! $this->presupuestoModel->eliminar($empresa, $anio, $finca, $mes === '' ? null : (int) $mes, $lote)) {
            return $this->error('No se pudo eliminar el presupuesto.', 500);
        }

        $periodo = $mes === '' ? ' del año' : ' del mes';

        return $this->response->setJSON([
            'success' => true,
            'message' => $lote === '' ? "Meta de finca{$periodo} eliminada correctamente." : "Presupuesto del lote{$periodo} eliminado correctamente.",
        ]);
    }

    public function metas(): ResponseInterface
    {
        $base = $this->validarBase(false);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio] = $base;
        $data = $this->presupuestoModel->metas($empresa, $anio);

        return $this->response->setJSON([
            'success' => true,
            'message' => $data['tablasMetas'] ? '' : self::SIN_METAS,
        ] + $data);
    }

    public function guardarMeta(): ResponseInterface
    {
        $base = $this->validarBase(false);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio] = $base;

        if (! $this->presupuestoModel->metasExisten()) {
            return $this->error(self::SIN_METAS, 409);
        }

        $finca = trim((string) $this->request->getPost('finca'));

        if ($finca !== '' && ! $this->presupuestoModel->fincaExiste($empresa, $finca)) {
            return $this->error('La finca seleccionada no existe para esta empresa.');
        }

        $campos = [];
        $reglas = [
            'kilosAnual' => ['/^\d{1,16}(\.\d{1,2})?$/', 'Los kilos anuales deben ser un número mayor o igual a 0 con máximo 2 decimales.'],
            'metaTonHa'  => ['/^\d{1,7}(\.\d{1,2})?$/', 'La meta de t/ha debe ser un número mayor o igual a 0 con máximo 2 decimales.'],
        ];

        foreach ($reglas as $campo => [$patron, $mensaje]) {
            $valor = $this->request->getPost($campo);

            if ($valor === null) {
                continue;
            }

            $valor = is_scalar($valor) ? str_replace(' ', '', trim((string) $valor)) : false;

            if ($valor !== '' && ($valor === false || ! preg_match($patron, $valor))) {
                return $this->error($mensaje);
            }

            $campos[$campo] = $valor === '' ? null : $valor;
        }

        $curva = $this->request->getPost('curva');

        if ($curva !== null) {
            if ($finca === '') {
                return $this->error('La curva de estacionalidad se define por finca, no para la empresa.');
            }

            $curva = $this->leerCurva($curva);

            if (is_string($curva)) {
                return $this->error($curva);
            }
        }

        if ($campos === [] && $curva === null) {
            return $this->error('No hay datos de meta para guardar.');
        }

        if (! $this->presupuestoModel->guardarMeta($empresa, $anio, $finca, $campos, $curva, $this->usuarioRegistro())) {
            return $this->error('No se pudo guardar la meta. La operación fue revertida.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $finca === '' ? 'Meta de la empresa guardada correctamente.' : 'Meta de la finca guardada correctamente.',
            'meta'    => $this->presupuestoModel->meta($empresa, $anio, $finca),
        ]);
    }

    public function generar(): ResponseInterface
    {
        $base = $this->validarBase(true);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio, $finca] = $base;

        if (! $this->presupuestoModel->metasExisten()) {
            return $this->error(self::SIN_METAS, 409);
        }

        $meses = $this->request->getPost('meses');
        $lista = is_array($meses) ? $meses : json_decode((string) $meses, true);
        $meses = $meses === null || $meses === '' ? range(1, 12) : (is_array($lista) ? $lista : explode(',', (string) $meses));
        $meses = array_map(static fn ($m) => is_scalar($m) ? trim((string) $m) : '', $meses);

        foreach ($meses as $m) {
            if (! ctype_digit($m) || (int) $m < 1 || (int) $m > 12) {
                return $this->error('Los meses deben estar entre 1 y 12.');
            }
        }

        $meses = array_values(array_unique(array_map('intval', $meses)));
        sort($meses);

        if ($meses === []) {
            return $this->error('Debe indicar al menos un mes.');
        }

        $reemplazar = filter_var($this->request->getPost('reemplazarHistorico'), FILTER_VALIDATE_BOOLEAN);
        $simular    = filter_var($this->request->getPost('simular'), FILTER_VALIDATE_BOOLEAN);
        $meta       = $this->presupuestoModel->meta($empresa, $anio, $finca);

        if ($meta['kilosAnual'] === null) {
            return $this->error('La finca no tiene meta anual de kilos para el año seleccionado.');
        }

        if (! $meta['completa']) {
            return $this->error('La curva de estacionalidad de la finca debe tener los 12 meses y sumar 100.');
        }

        $dist = $this->presupuestoModel->distribucion($empresa, $anio, $finca, $meses, $meta['kilosAnual'], $meta['curva']);

        if ($dist === null) {
            return $this->error('La finca no tiene lotes activos con hectáreas netas para repartir el presupuesto.');
        }

        $historico = $this->presupuestoModel->mesesHistorico($empresa, $anio, $finca, $meses);

        $respuesta = [
            'success'            => true,
            'simulado'           => $simular,
            'finca'              => $finca,
            'meses'              => $meses,
            'kilosAnual'         => $meta['kilosAnual'],
            'curva'              => array_values($meta['curva']),
            'mesesHistorico'     => $historico,
            'historicoEliminado' => false,
        ] + $dist;

        if ($simular) {
            return $this->response->setJSON(['message' => 'Simulación del presupuesto por lote (no se guardó).'] + $respuesta);
        }

        if ($historico !== [] && ! $reemplazar) {
            return $this->response->setJSON([
                'success'        => false,
                'message'        => 'La finca tiene metas de presupuesto a nivel finca en los meses ' . implode(', ', $historico) . '. Confirme reemplazarlas para generar el presupuesto por lote.',
                'mesesHistorico' => $historico,
            ])->setStatusCode(409);
        }

        if (! $this->presupuestoModel->aplicarDistribucion($empresa, $anio, $finca, $meses, $dist['lotes'], $historico !== [], $this->usuarioRegistro())) {
            return $this->error('No se pudo generar el presupuesto. La operación fue revertida.', 500);
        }

        $respuesta['historicoEliminado'] = $historico !== [];

        return $this->response->setJSON(['message' => 'Presupuesto por lote generado correctamente. Celdas sobrescritas: ' . $dist['celdasSobrescritas'] . '.'] + $respuesta);
    }

    private function leerCurva($curva): array|string
    {
        $curva = is_array($curva) ? $curva : trim((string) $curva);

        if ($curva === '' || $curva === []) {
            return [];
        }

        $curva = is_array($curva) ? $curva : json_decode($curva, true);

        if (! is_array($curva) || count($curva) !== 12 || ! array_is_list($curva)) {
            return 'La curva debe tener exactamente 12 porcentajes (enero a diciembre).';
        }

        $valores = [];

        foreach ($curva as $p) {
            $p = is_float($p) ? rtrim(rtrim(number_format($p, 6, '.', ''), '0'), '.') : (is_scalar($p) ? str_replace(' ', '', trim((string) $p)) : '');

            if (! preg_match('/^\d{1,5}(\.\d{1,4})?$/', $p) || (float) $p > 100) {
                return 'Cada porcentaje de la curva debe ser un número entre 0 y 100 con máximo 4 decimales.';
            }

            $valores[] = $p;
        }

        if (abs(array_sum(array_map('floatval', $valores)) - 100) > 0.01) {
            return 'Los 12 porcentajes de la curva deben sumar 100.';
        }

        return $valores;
    }

    private function validarBase(bool $escritura): array|ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->error('Sesión expirada. Vuelva a iniciar sesión.', 401);
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::RUTA)) {
            return $this->error('No tiene permiso para gestionar el presupuesto de producción.', 403);
        }

        $empresa = $this->request->getPost('empresa');

        if ($empresa === null || ! ctype_digit((string) $empresa) || ! $this->presupuestoModel->empresaExiste((int) $empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = trim((string) $this->request->getPost('anio'));

        if (! ctype_digit($anio) || (int) $anio < 2000 || (int) $anio > 2100) {
            return $this->error('El año debe estar entre 2000 y 2100.');
        }

        if (! $escritura) {
            return [(int) $empresa, (int) $anio];
        }

        if (! $this->presupuestoModel->tablaExiste()) {
            return $this->error('La tabla de presupuesto de producción aún no ha sido creada.', 409);
        }

        $finca = trim((string) $this->request->getPost('finca'));

        if ($finca === '' || ! $this->presupuestoModel->fincaExiste((int) $empresa, $finca)) {
            return $this->error('La finca seleccionada no existe para esta empresa.');
        }

        return [(int) $empresa, (int) $anio, $finca];
    }

    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : mb_substr($usuario, 0, 50);
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode($codigo);
    }
}
