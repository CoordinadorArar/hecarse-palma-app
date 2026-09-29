<?php

namespace App\Controllers\transacciones;

use CodeIgniter\HTTP\ResponseInterface;

use App\Models\FertilizacionTransaccionModel;
use App\Models\PlanFertilizacionModel;

class FertilizacionController extends LaboresController
{
    protected const CAMPOS = ['fecha', 'finca', 'numero', 'observacion', 'referencia', 'tipo'];

    protected const MODELO  = FertilizacionTransaccionModel::class;
    protected const TITULO  = 'Transacciones · Fertilización';
    protected const VISTA   = 'transacciones/fertilizacion';
    protected const NOMBRE  = 'fertilización';
    protected const PERMISO = 'fertilizacion';

    private const MAX_INSUMOS = 20;
    private const MAX_ITEMS   = 2000;

    private ?PlanFertilizacionModel $planModel = null;

    private function plan(): PlanFertilizacionModel
    {
        return $this->planModel ??= new PlanFertilizacionModel();
    }

    private function tipoPedido(): string
    {
        $tipo = strtoupper(trim($this->campo('tipo')));

        if (in_array($tipo, [PlanFertilizacionModel::TIPO, FertilizacionTransaccionModel::TIPO], true)) {
            return $tipo;
        }

        $empresa = (int) $this->campo('empresa');
        $numero  = trim($this->campo('numero'));

        if ($empresa <= 0 || $numero === '' || strlen($numero) > 50) {
            return FertilizacionTransaccionModel::TIPO;
        }

        return $this->laboresModel->tipoDe($empresa, $numero) ?? FertilizacionTransaccionModel::TIPO;
    }

    public function lotesFinca(): ResponseInterface
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

        return $this->listado(fn () => $this->plan()->lotesFinca($empresa, $finca, trim($this->campo('seccion'))), 'lotes');
    }

    public function guardar(): ResponseInterface
    {
        if (strtoupper(trim($this->campo('tipo'))) !== PlanFertilizacionModel::TIPO) {
            return parent::guardar();
        }

        if ($sinSesion = $this->sesionRequerida(['registrar'], 'registrar transacciones de ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $datos = $this->datosPlan(null);

        if ($datos instanceof ResponseInterface) {
            return $datos;
        }

        return $this->resultadoPlan(fn () => $this->plan()->guardar($datos, $this->session->get('usu_id')), 'registrar');
    }

    public function actualizar(): ResponseInterface
    {
        if ($this->tipoPedido() !== PlanFertilizacionModel::TIPO) {
            return parent::actualizar();
        }

        if ($sinSesion = $this->sesionRequerida(['editar'], 'editar transacciones de ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $antes = $this->planEditable($empresa, trim($this->campo('numero')), 'editar');

        if ($antes instanceof ResponseInterface) {
            return $antes;
        }

        $datos = $this->datosPlan($antes);

        if ($datos instanceof ResponseInterface) {
            return $datos;
        }

        $quitados = array_values(array_diff($this->plan()->lotesUsados($empresa, $antes['encabezado']['numero']), array_column($datos['items'], 'lote')));

        if ($quitados !== []) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No se pueden quitar los lotes ' . implode(', ', $quitados) . ': tienen registros de labores que referencian el plan.',
                'lotes'   => $quitados,
            ])->setStatusCode(422);
        }

        return $this->resultadoPlan(fn () => $this->plan()->actualizar($datos, $antes, $this->session->get('usu_id')), 'actualizar');
    }

    public function detalle(): ResponseInterface
    {
        if ($this->tipoPedido() !== PlanFertilizacionModel::TIPO) {
            return parent::detalle();
        }

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
            $datos = $this->plan()->detalle($empresa, $numero);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el plan de fertilización: ' . $e->getMessage());

            return $this->error('No se pudo consultar el detalle del plan.', 500);
        }

        if ($datos === null) {
            return $this->error('No se encontró el plan ' . $numero . '.', 404);
        }

        return $this->response->setJSON(array_merge(['success' => true, 'message' => ''], $datos));
    }

    public function eliminar(): ResponseInterface
    {
        if ($this->tipoPedido() !== PlanFertilizacionModel::TIPO) {
            return parent::eliminar();
        }

        if ($sinSesion = $this->sesionRequerida(['eliminar'], 'eliminar transacciones de ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $motivo = trim($this->campo('motivo'));

        if (strlen($motivo) > 250) {
            return $this->error('El campo "Motivo" admite máximo 250 caracteres.');
        }

        if (! $this->plan()->tablaEliminadas()) {
            return $this->error('La eliminación de transacciones no está disponible: falta crear la tabla aTransaccionEliminada.', 500);
        }

        $antes = $this->planEditable($empresa, trim($this->campo('numero')), 'eliminar');

        if ($antes instanceof ResponseInterface) {
            return $antes;
        }

        if ($antes['encabezado']['usado'] === 1) {
            return $this->error('El plan está referenciado por ' . $antes['encabezado']['usos'] . ' registros de labores.');
        }

        return $this->resultadoPlan(
            fn () => $this->plan()->eliminar($empresa, $antes, $motivo, $this->usuarioRegistro(), $this->session->get('usu_id')),
            'eliminar'
        );
    }

    private function resultadoPlan(callable $accion, string $verbo): ResponseInterface
    {
        try {
            $resultado = $accion();
        } catch (\Throwable $e) {
            log_message('error', 'Error al ' . $verbo . ' el plan de fertilización: ' . $e->getMessage());

            return $this->error('No se pudo ' . $verbo . ' el plan.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message'], 500);
        }

        return $this->response->setJSON($resultado);
    }

    private function planEditable(int $empresa, string $numero, string $accion): array|ResponseInterface
    {
        if ($numero === '' || strlen($numero) > 50) {
            return $this->error('El campo "Número" es obligatorio y admite máximo 50 caracteres.');
        }

        try {
            $antes = $this->plan()->detalle($empresa, $numero);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el plan de fertilización: ' . $e->getMessage());

            return $this->error('No se pudo consultar el plan.', 500);
        }

        if ($antes === null) {
            return $this->error('No se encontró el plan ' . $numero . '.', 404);
        }

        if ($antes['encabezado']['anulado'] === 1) {
            return $this->error('El plan ' . $numero . ' está anulado y no se puede ' . $accion . '.');
        }

        if ($antes['encabezado']['cerrado'] === 1) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($antes['encabezado']['anio'], $antes['encabezado']['mes']) . ' está cerrado.');
        }

        return $antes;
    }

    private function datosPlan(?array $antes): array|ResponseInterface
    {
        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $fecha      = $this->fechaValida($this->campo('fecha'));
        $fechaFinal = $this->fechaValida($this->campo('fechaFinal'));

        if ($fecha === null || $fechaFinal === null) {
            return $this->error('Los campos "Fecha" y "Fecha final" son obligatorios y deben tener el formato AAAA-MM-DD.');
        }

        if ($fechaFinal < $fecha) {
            return $this->error('La "Fecha final" no puede ser menor que la "Fecha".');
        }

        $anio = trim($this->campo('anio'));
        $mes  = trim($this->campo('mes'));

        if (preg_match('/^\d{4}$/', $anio) !== 1 || preg_match('/^\d{1,2}$/', $mes) !== 1) {
            return $this->error('Los campos "Año" y "Periodo" son obligatorios.');
        }

        $anio    = (int) $anio;
        $mes     = (int) $mes;
        $periodo = $this->plan()->periodo($empresa, $anio, $mes);

        if ($periodo === null) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($anio, $mes) . ' no existe para la empresa.');
        }

        if ($periodo['cerrado'] === 1) {
            return $this->error('El periodo ' . $this->etiquetaPeriodo($anio, $mes) . ' está cerrado.');
        }

        if ($error = $this->fueraDePeriodo($fecha, $periodo, $anio, $mes)) {
            return $this->error($error);
        }

        $finca       = trim($this->campo('finca'));
        $observacion = trim($this->campo('observacion'));

        if ($finca === '' || strlen($finca) > 50 || ! $this->plan()->fincaValida($empresa, $finca)) {
            return $this->error('El campo "Finca" es obligatorio y debe existir.');
        }

        if (strlen($observacion) > 2550) {
            return $this->error('El campo "Observación" admite máximo 2550 caracteres.');
        }

        $recibidos = json_decode($this->campo('items'), true);

        if (! is_array($recibidos) || $recibidos === []) {
            return $this->error('Debe registrar al menos un insumo en el plan.');
        }

        if (count($recibidos) > self::MAX_ITEMS) {
            return $this->error('El plan admite máximo ' . self::MAX_ITEMS . ' insumos.');
        }

        $previas = [];

        foreach ($antes['items'] ?? [] as $previo) {
            $previas[$previo['lote'] . '|' . $previo['item']] = $previo['palmas'];
        }

        $palmas = [];
        $items  = [];

        foreach (array_values($recibidos) as $i => $fila) {
            $orden = $i + 1;
            $valor = [];

            foreach (['lote', 'item', 'uMedida'] as $c) {
                $valor[$c] = is_array($fila) && ! is_array($fila[$c] ?? null) ? trim((string) ($fila[$c] ?? '')) : '';
            }

            if ($valor['lote'] === '' || strlen($valor['lote']) > 50 || ! $this->plan()->loteDeFinca($empresa, $finca, $valor['lote'])) {
                return $this->error('El lote ' . $valor['lote'] . ' del insumo ' . $orden . ' no pertenece a la finca ' . $finca . '.');
            }

            if (preg_match('/^\d{1,9}$/', $valor['item']) !== 1 || ! $this->plan()->itemValido($empresa, $valor['item'])) {
                return $this->error('El ítem ' . $valor['item'] . ' del insumo ' . $orden . ' no existe o no está activo.');
            }

            if ($valor['uMedida'] === '' || strlen($valor['uMedida']) > 50 || ! $this->plan()->unidadValida($empresa, $valor['uMedida'])) {
                return $this->error('La unidad de medida del insumo ' . $orden . ' no existe.');
            }

            $dosis  = $this->decimalDe($fila['dosis'] ?? null);
            $pBulto = $this->decimalDe($fila['pBulto'] ?? null);

            if ($dosis === null || $dosis <= 0 || $pBulto === null || $pBulto <= 0) {
                return $this->error('La dosis y el peso del bulto del insumo ' . $orden . ' deben ser números mayores que cero con máximo 2 decimales.');
            }

            $palmas[$valor['lote']] ??= $this->plan()->palmasLote($empresa, $finca, $valor['lote']);
            $noPalmas = $previas[$valor['lote'] . '|' . $valor['item']] ?? $palmas[$valor['lote']];
            $cantidad = round($noPalmas * $dosis, 2);

            $items[] = $valor + [
                'dosis'    => $dosis,
                'pBulto'   => $pBulto,
                'noPalmas' => $noPalmas,
                'cantidad' => $cantidad,
                'mBulto'   => round($cantidad / $pBulto, 2),
            ];
        }

        return [
            'empresa'     => $empresa,
            'fecha'       => $fecha,
            'fechaFinal'  => $fechaFinal,
            'anio'        => $anio,
            'mes'         => $mes,
            'finca'       => $finca,
            'observacion' => $observacion,
            'usuario'     => $this->usuarioRegistro(),
            'items'       => $items,
        ];
    }

    public function planes(): ResponseInterface
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

        return $this->listado(fn () => $this->laboresModel->planes($empresa, $finca), 'planes');
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

        $finca      = trim($this->campo('finca'));
        $referencia = trim($this->campo('referencia'));

        if ($finca === '') {
            return $this->error('El campo "Finca" es obligatorio.');
        }

        return $this->listado(
            fn () => $referencia === '' ? [] : $this->laboresModel->lotesPlan($empresa, $finca, $referencia, trim($this->campo('seccion'))),
            'lotes'
        );
    }

    protected function encabezadoValido(int $empresa, bool $liquidar): array|string
    {
        $finca      = trim($this->campo('finca'));
        $referencia = trim($this->campo('referencia'));

        if ($finca === '' || strlen($finca) > 50) {
            return 'El campo "Finca" es obligatorio y admite máximo 50 caracteres.';
        }

        if (! $this->laboresModel->fincaValida($empresa, $finca)) {
            return 'La finca ' . $finca . ' no existe.';
        }

        if ($referencia === '' || strlen($referencia) > 50) {
            return 'El campo "Referencia" es obligatorio y admite máximo 50 caracteres.';
        }

        if (! $this->laboresModel->planValido($empresa, $referencia, $finca)) {
            return 'La referencia ' . $referencia . ' no es un plan ' . FertilizacionTransaccionModel::PLAN . ' vigente de la finca ' . $finca . '.';
        }

        return ['finca' => $finca, 'referencia' => $referencia, 'remision' => null, 'todasFechas' => true];
    }

    protected function datosVista(int $empresa): array
    {
        return ['items' => $this->laboresModel->items($empresa)];
    }

    protected function lineaValida(int $empresa, int $orden, array &$linea, array $extra, array $fila): ?string
    {
        $recibidos = $fila['insumos'] ?? [];

        if (! is_array($recibidos)) {
            return 'No se pudieron leer los insumos de la línea ' . $orden . '.';
        }

        if (count($recibidos) > self::MAX_INSUMOS) {
            return 'La línea ' . $orden . ' admite máximo ' . self::MAX_INSUMOS . ' insumos.';
        }

        $insumos = [];

        foreach (array_values($recibidos) as $insumo) {
            $item    = is_array($insumo) && ! is_array($insumo['item'] ?? null) ? trim((string) ($insumo['item'] ?? '')) : '';
            $uMedida = is_array($insumo) && ! is_array($insumo['uMedida'] ?? null) ? trim((string) ($insumo['uMedida'] ?? '')) : '';

            if (preg_match('/^\d{1,9}$/', $item) !== 1 || ! $this->laboresModel->itemValido($empresa, $item)) {
                return 'El insumo ' . $item . ' de la línea ' . $orden . ' no existe o no está activo.';
            }

            if (isset($insumos[$item])) {
                return 'El insumo ' . $item . ' está repetido en la línea ' . $orden . '.';
            }

            if ($uMedida === '' || strlen($uMedida) > 50 || ! $this->laboresModel->unidadValida($empresa, $uMedida)) {
                return 'La unidad de medida del insumo ' . $item . ' de la línea ' . $orden . ' no existe.';
            }

            $dosis = $this->decimalDe($insumo['dosis'] ?? null);

            if ($dosis === null || $dosis <= 0) {
                return 'La dosis del insumo ' . $item . ' de la línea ' . $orden . ' debe ser un número mayor que cero con máximo 2 decimales.';
            }

            $insumos[$item] = [
                'item'     => $item,
                'uMedida'  => $uMedida,
                'dosis'    => $dosis,
                'cantidad' => round($linea['cantidad'] * $dosis, 2),
            ];
        }

        $linea['insumos'] = array_values($insumos);

        if ($linea['finca'] !== $extra['finca']) {
            return 'La finca de la línea ' . $orden . ' debe ser la del encabezado (' . $extra['finca'] . ').';
        }

        if (! $this->laboresModel->loteDePlan($empresa, $extra['referencia'], $linea['lote'])) {
            return 'El lote ' . $linea['lote'] . ' de la línea ' . $orden . ' no está en el plan ' . $extra['referencia'] . '.';
        }

        if ($linea['jornales'] != 0 || array_filter(array_column($linea['trabajadores'], 'jornales')) !== []) {
            return 'Los jornales de la línea ' . $orden . ' deben ser 0.';
        }

        return null;
    }
}
