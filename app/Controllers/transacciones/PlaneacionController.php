<?php

namespace App\Controllers\transacciones;

use CodeIgniter\HTTP\ResponseInterface;

use App\Models\FertilizacionTransaccionModel;
use App\Models\LaboresTransaccionModel;
use App\Models\PlaneacionModel;

class PlaneacionController extends LaboresController
{
    protected const MODELO  = PlaneacionModel::class;
    protected const TITULO  = 'Transacciones · Planeación de labores';
    protected const VISTA   = 'transacciones/planeacion';
    protected const NOMBRE  = 'planeación de labores';
    protected const PERMISO = 'planeacion';

    private const SIN_TABLAS = 'La planeación de labores no está disponible: ejecute el script crear_planeacion_labores.sql.';

    private const MAX_ACTIVIDADES = 500;

    private array $detalle = [];

    private array $conflictos = [];

    protected function puedeAbrir(): bool
    {
        return parent::puedeAbrir() || $this->permisos()['editar'];
    }

    protected function datosVista(int $empresa): array
    {
        $disponible = $this->laboresModel->planeacionDisponible();
        $usuario    = (int) $this->session->get('usu_id');
        $puede      = fn (string $modelo, string $accion): bool => ($modulo = (new $modelo())->idModulo()) > 0
            && in_array($accion, $this->laboresModel->accionesPermitidas($usuario, $modulo), true);

        return [
            'fecha_defecto'      => date('Y-m-d', strtotime('+1 day')),
            'tablas_disponibles' => $disponible,
            'mensaje_tablas'     => $disponible ? '' : self::SIN_TABLAS,
            'registrar_destino'  => [
                'labores'       => $puede(LaboresTransaccionModel::class, 'registrar-labores'),
                'fertilizacion' => $puede(FertilizacionTransaccionModel::class, 'registrar-fertilizacion'),
            ],
        ];
    }

    public function plan(): ResponseInterface
    {
        $datos = $this->empresaFecha(['consultar', 'registrar', 'editar'], 'consultar');

        if ($datos instanceof ResponseInterface) {
            return $datos;
        }

        try {
            $plan = $this->laboresModel->plan($datos['empresa'], $datos['fecha']);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la planeación de labores: ' . $e->getMessage());

            return $this->error('No se pudo consultar el plan.', 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => '', 'plan' => $plan]);
    }

    public function pendientes(): ResponseInterface
    {
        $datos = $this->empresaFecha(['consultar', 'registrar', 'editar'], 'consultar');

        if ($datos instanceof ResponseInterface) {
            return $datos;
        }

        return $this->listado(fn () => $this->laboresModel->pendientes($datos['empresa'], $datos['fecha']), 'pendientes');
    }

    public function terceros(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida(['registrar', 'editar'], 'registrar o editar la ' . static::NOMBRE)) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        return $this->listado(fn () => array_values(array_filter(
            $this->laboresModel->buscarTercero($empresa, $this->campo('termino'), 20),
            static fn ($t) => (int) $t['activo'] === 1
        )), 'terceros');
    }

    public function planesFertilizacion(): ResponseInterface
    {
        $datos = $this->empresaFecha(['registrar', 'editar'], 'registrar o editar');

        if ($datos instanceof ResponseInterface) {
            return $datos;
        }

        $finca = trim($this->campo('finca'));

        if ($finca === '') {
            return $this->error('El campo "Finca" es obligatorio.');
        }

        return $this->listado(fn () => (new FertilizacionTransaccionModel())->planes($datos['empresa'], $finca, $datos['fecha']), 'planes');
    }

    public function guardar(): ResponseInterface
    {
        $datos = $this->empresaFecha(['registrar', 'editar'], 'registrar o editar');

        if ($datos instanceof ResponseInterface) {
            return $datos;
        }

        ['empresa' => $empresa, 'fecha' => $fecha] = $datos;

        $observacion = trim($this->campo('observacion'));

        if (strlen($observacion) > 500) {
            return $this->error('El campo "Observación" admite máximo 500 caracteres.');
        }

        $antes = $this->planDestino($empresa, $fecha);

        if ($antes instanceof ResponseInterface) {
            return $antes;
        }

        $recibidas = json_decode($this->campo('actividades'), true);

        if (! is_array($recibidas)) {
            return $this->error('No se pudieron leer las actividades del plan.');
        }

        if (count($recibidas) > self::MAX_ACTIVIDADES) {
            return $this->error('El plan admite máximo ' . self::MAX_ACTIVIDADES . ' actividades.');
        }

        $existentes = array_column($antes['actividades'] ?? [], null, 'id');
        $ausentes   = array_column($antes['ausencias'] ?? [], 'tercero');
        $eliminadas = trim($this->campo('eliminadas')) === '' ? [] : json_decode($this->campo('eliminadas'), true);

        if (! is_array($eliminadas)) {
            return $this->error('No se pudieron leer las actividades a eliminar.');
        }

        $eliminar = [];

        foreach ($eliminadas as $id) {
            $id = is_array($id) ? 0 : (int) $id;

            if (! isset($existentes[$id])) {
                return $this->error('La actividad ' . $id . ' a eliminar no pertenece al plan del ' . $fecha . '; recargue el plan.');
            }

            if ($existentes[$id]['estado'] !== 'PENDIENTE' || $existentes[$id]['vinculada'] === 1) {
                return $this->error('La actividad ' . $existentes[$id]['orden'] . ' ya fue cerrada o registrada y no se puede eliminar; recargue el plan.');
            }

            $eliminar[$id] = $id;
        }

        $recibidosIds = [];

        foreach ($recibidas as $fila) {
            $id = is_array($fila) && ! is_array($fila['id'] ?? null) ? (int) ($fila['id'] ?? 0) : 0;

            if ($id > 0) {
                $recibidosIds[$id] = true;
            }
        }

        $usados = [];

        foreach ($existentes as $id => $existente) {
            if (! isset($eliminar[$id]) && ! isset($recibidosIds[$id])) {
                $this->marcarUsados($usados, $existente);
            }
        }

        $actividades = [];
        $conservar   = [];

        foreach (array_values($recibidas) as $i => $fila) {
            $orden = $i + 1;

            if (! is_array($fila)) {
                return $this->errorActividad('No se pudieron leer los datos de la actividad ' . $orden . '.', $orden);
            }

            $id = is_array($fila['id'] ?? null) ? 0 : (int) ($fila['id'] ?? 0);

            if ($id > 0) {
                if (! isset($existentes[$id]) || isset($conservar[$id]) || isset($eliminar[$id])) {
                    return $this->errorActividad('La actividad ' . $orden . ' no pertenece al plan del ' . $fecha . ', está repetida o marcada para eliminar.', $orden);
                }

                if ($existentes[$id]['estado'] !== 'PENDIENTE') {
                    return $this->errorActividad('La actividad ' . $existentes[$id]['orden'] . ' ya fue cerrada por otro usuario; recargue el plan.', $orden);
                }

                $conservar[$id] = true;
            }

            $actividad = $this->actividadValida($empresa, $fecha, $orden, $fila, $ausentes, $usados);

            if (is_string($actividad)) {
                return $this->errorActividad($actividad, $orden);
            }

            $actividades[] = $actividad + ['id' => $id > 0 ? $id : null, 'orden' => $orden];
        }

        if ($actividades === [] && count($existentes) === count($eliminar)) {
            return $this->error('Debe registrar al menos una actividad en el plan.');
        }

        $eliminar = array_values($eliminar);
        return $this->resultado(fn () => $this->laboresModel->guardarPlan([
            'empresa'     => $empresa,
            'fecha'       => $fecha,
            'observacion' => $observacion === '' ? null : $observacion,
            'usuario'     => $this->usuarioRegistro(),
            'actividades' => $actividades,
            'eliminar'    => $eliminar,
        ], $antes, $this->session->get('usu_id')));
    }

    public function trasladar(): ResponseInterface
    {
        $datos = $this->empresaFecha(['registrar', 'editar'], 'registrar o editar');

        if ($datos instanceof ResponseInterface) {
            return $datos;
        }

        ['empresa' => $empresa, 'fecha' => $fecha] = $datos;

        $ids = json_decode($this->campo('lineas'), true);

        if (! is_array($ids) || $ids === [] || count($ids) > self::MAX_ACTIVIDADES) {
            return $this->error('Debe seleccionar entre 1 y ' . self::MAX_ACTIVIDADES . ' actividades pendientes para trasladar.');
        }

        $destino = $this->planDestino($empresa, $fecha);

        if ($destino instanceof ResponseInterface) {
            return $destino;
        }

        $ausentes = array_column($destino['ausencias'] ?? [], 'tercero');
        $usados   = [];

        foreach ($destino['actividades'] ?? [] as $existente) {
            $this->marcarUsados($usados, $existente);
        }

        $fertilizacion = new FertilizacionTransaccionModel();
        $lineas        = [];

        foreach (array_unique(array_map(static fn ($id) => is_array($id) ? 0 : (int) $id, $ids)) as $id) {
            $linea = $id > 0 ? $this->laboresModel->linea($id) : null;

            if ($linea === null || $linea['empresa'] !== $empresa) {
                return $this->error('La actividad ' . $id . ' no existe.');
            }

            $etiqueta = $this->etiqueta($linea);

            if ($linea['fecha'] >= $fecha) {
                return $this->error('Solo se pueden trasladar pendientes de días anteriores al ' . $fecha . ' (' . $etiqueta . ').');
            }

            if ($linea['saldo'] <= 0) {
                return $this->error('La actividad ' . $etiqueta . ' no tiene saldo pendiente.');
            }

            if ($linea['trasladada'] === 1) {
                return $this->error('La actividad ' . $etiqueta . ' ya fue trasladada.');
            }

            $trabajadores = array_values(array_filter($linea['trabajadores'], static fn ($t) => ! in_array($t['tercero'], $ausentes, true)));

            if ($trabajadores === []) {
                return $this->error('Todos los trabajadores de la actividad ' . $etiqueta . ' están ausentes el ' . $fecha . '.');
            }

            foreach ($trabajadores as $trabajador) {
                if ($error = $this->repetido($usados, $trabajador, $linea, 'traslado de ' . $linea['fecha'])) {
                    return $this->error($error);
                }
            }

            $cantidades = $this->repartir($linea['saldo'], array_column($trabajadores, 'cantidad'));
            $referencia = $linea['referencia'] !== '' && $fertilizacion->planValido($empresa, $linea['referencia'], $linea['finca'], $fecha)
                ? $linea['referencia'] : null;

            $lineas[] = [
                'origen'       => $linea['id'],
                'finca'        => $linea['finca'],
                'lote'         => $linea['lote'],
                'novedad'      => $linea['novedad'],
                'uMedida'      => $linea['uMedida'],
                'cantidad'     => $linea['saldo'],
                'horaInicio'   => $linea['horaInicio'] === '' ? null : $linea['horaInicio'],
                'horaFin'      => $linea['horaFin'] === '' ? null : $linea['horaFin'],
                'observacion'  => $linea['observacion'] === '' ? null : $linea['observacion'],
                'referencia'   => $referencia,
                'trabajadores' => array_map(static fn ($t, $c) => ['tercero' => $t['tercero'], 'cantidad' => $c, 'reasignadoDe' => null], $trabajadores, $cantidades),
            ];
        }

        return $this->resultado(fn () => $this->laboresModel->trasladar([
            'empresa' => $empresa,
            'fecha'   => $fecha,
            'usuario' => $this->usuarioRegistro(),
            'lineas'  => $lineas,
        ], $destino, $this->session->get('usu_id')));
    }

    public function ausencia(): ResponseInterface
    {
        $plan = $this->planPedido();

        if ($plan instanceof ResponseInterface) {
            return $plan;
        }

        $nit     = trim($this->campo('tercero'));
        $tercero = $nit === '' || strlen($nit) > 50 ? null : $this->laboresModel->tercero($plan['empresa'], $nit);

        if ($tercero === null) {
            return $this->error('Debe seleccionar un trabajador válido y activo.');
        }

        $nombre   = $this->nombre($tercero);
        $ausentes = array_column($plan['ausencias'], 'tercero');

        if (in_array($tercero['nit'], $ausentes, true)) {
            return $this->error('El trabajador ' . $nombre . ' ya está marcado como ausente.');
        }

        $motivo = trim($this->campo('motivo'));

        if (strlen($motivo) > 250) {
            return $this->error('El campo "Motivo" admite máximo 250 caracteres.');
        }

        $recibido = trim($this->campo('reparto')) === '' ? [] : json_decode($this->campo('reparto'), true);

        if (! is_array($recibido)) {
            return $this->error('No se pudo leer el reparto de las actividades.');
        }

        $lineas    = array_column($plan['actividades'], null, 'id');
        $afectadas = [];
        $usados    = [];

        foreach ($lineas as $id => $linea) {
            $this->marcarUsados($usados, $linea, $tercero['nit']);

            if ($linea['estado'] === 'PENDIENTE' && in_array($tercero['nit'], array_column($linea['trabajadores'], 'tercero'), true)) {
                $afectadas[$id] = array_column($linea['trabajadores'], 'cantidad', 'tercero')[$tercero['nit']];
            }
        }

        $reparto = [];

        foreach (array_values($recibido) as $fila) {
            $id = is_array($fila) && ! is_array($fila['linea'] ?? null) ? (int) ($fila['linea'] ?? 0) : 0;

            if (! isset($afectadas[$id]) || isset($reparto[$id])) {
                return $this->error('La actividad ' . $id . ' no tiene asignado a ' . $nombre . ', está cerrada o está repetida en el reparto.');
            }

            $linea    = $lineas[$id];
            $etiqueta = $this->etiqueta($linea);
            $actuales = array_column($linea['trabajadores'], 'tercero');
            $nuevos   = 0;
            $suma     = 0;

            $reparto[$id] = [];

            foreach (is_array($fila['asignaciones'] ?? null) ? array_values($fila['asignaciones']) : [] as $asignacion) {
                $nitR      = is_array($asignacion) && ! is_array($asignacion['tercero'] ?? null) ? trim((string) ($asignacion['tercero'] ?? '')) : '';
                $receptor  = $nitR === '' || strlen($nitR) > 50 ? null : $this->laboresModel->tercero($plan['empresa'], $nitR);
                $cantidad  = $this->decimalDe(is_array($asignacion) ? ($asignacion['cantidad'] ?? null) : null);

                if ($receptor === null) {
                    return $this->error('Un trabajador del reparto de la actividad ' . $etiqueta . ' no existe o está inactivo.');
                }

                if ($receptor['nit'] === $tercero['nit'] || in_array($receptor['nit'], $ausentes, true)) {
                    return $this->error('El trabajador ' . $this->nombre($receptor) . ' está ausente y no puede recibir el reparto.');
                }

                if (isset($reparto[$id][$receptor['nit']])) {
                    return $this->error('El trabajador ' . $this->nombre($receptor) . ' está repetido en el reparto de la actividad ' . $etiqueta . '.');
                }

                if ($cantidad === null || $cantidad < 0) {
                    return $this->error('La cantidad repartida a ' . $this->nombre($receptor) . ' debe ser un número mayor o igual a cero con máximo 2 decimales.');
                }

                if (! in_array($receptor['nit'], $actuales, true)) {
                    if ($error = $this->repetido($usados, $receptor + ['tercero' => $receptor['nit']], $linea, 'actividad ' . $linea['orden'])) {
                        return $this->error($error);
                    }

                    $nuevos++;
                }

                $suma += (int) round($cantidad * 100);

                $reparto[$id][$receptor['nit']] = ['tercero' => $receptor['nit'], 'cantidad' => $cantidad];
            }

            if ($suma !== (int) round($afectadas[$id] * 100)) {
                return $this->error('En la actividad ' . $etiqueta . ' la suma repartida (' . number_format($suma / 100, 2, '.', '')
                    . ') no coincide con la cantidad de ' . $nombre . ' (' . number_format($afectadas[$id], 2, '.', '') . ').');
            }

            if (count($actuales) - 1 + $nuevos < 1) {
                return $this->error('La actividad ' . $etiqueta . ' quedaría sin trabajadores.');
            }

            $reparto[$id] = array_values($reparto[$id]);
        }

        foreach ($afectadas as $id => $cantidad) {
            if (! isset($reparto[$id]) && ($cantidad > 0 || count($lineas[$id]['trabajadores']) < 2)) {
                return $this->error('Debe repartir la actividad ' . $this->etiqueta($lineas[$id]) . ' de ' . $nombre . '.');
            }
        }

        return $this->resultado(fn () => $this->laboresModel->marcarAusencia(
            $plan['id'], $tercero['nit'], $motivo, $this->usuarioRegistro(), array_keys($afectadas), $reparto, $this->session->get('usu_id')
        ));
    }

    public function quitarAusencia(): ResponseInterface
    {
        $plan = $this->planPedido();

        if ($plan instanceof ResponseInterface) {
            return $plan;
        }

        $tercero = trim($this->campo('tercero'));

        if (! in_array($tercero, array_column($plan['ausencias'], 'tercero'), true)) {
            return $this->error('El trabajador no está marcado como ausente en el plan.');
        }

        return $this->resultado(fn () => $this->laboresModel->quitarAusencia($plan['id'], $tercero, $this->usuarioRegistro(), $this->session->get('usu_id')));
    }

    public function cerrarActividad(): ResponseInterface
    {
        $linea = $this->lineaPedida();

        if ($linea instanceof ResponseInterface) {
            return $linea;
        }

        if ($linea['estado'] !== 'PENDIENTE') {
            return $this->error('La actividad ' . $this->etiqueta($linea) . ' ya está cerrada.');
        }

        $estado      = strtoupper(trim($this->campo('estado')));
        $ejecutada   = $this->decimalDe($this->campo('cantidadEjecutada'));
        $observacion = trim($this->campo('observacionCierre'));

        if (! in_array($estado, PlaneacionModel::ESTADOS, true)) {
            return $this->error('El estado debe ser COMPLETADA, PARCIAL o NO_EJECUTADA.');
        }

        if (strlen($observacion) > 250) {
            return $this->error('La observación admite máximo 250 caracteres.');
        }

        if ($estado === 'COMPLETADA') {
            $ejecutada = $linea['cantidad'];
        } elseif ($estado === 'NO_EJECUTADA') {
            $ejecutada = 0.0;
        } elseif ($ejecutada === null || $ejecutada <= 0 || $ejecutada >= $linea['cantidad']) {
            return $this->error('La cantidad ejecutada debe ser mayor que cero y menor que la meta (' . number_format($linea['cantidad'], 2, '.', '') . ').');
        }

        if ($estado !== 'COMPLETADA' && $observacion === '') {
            return $this->error('La observación es obligatoria para cerrar la actividad como ' . $estado . '.');
        }

        return $this->resultado(fn () => $this->laboresModel->cerrarLinea(
            $linea, $estado, $ejecutada, $observacion, $this->usuarioRegistro(), $this->session->get('usu_id')
        ));
    }

    public function reabrirActividad(): ResponseInterface
    {
        $linea = $this->lineaPedida();

        if ($linea instanceof ResponseInterface) {
            return $linea;
        }

        if ($linea['estado'] === 'PENDIENTE') {
            return $this->error('La actividad ' . $this->etiqueta($linea) . ' no está cerrada.');
        }

        if ($linea['vinculada'] === 1) {
            return $this->error('La actividad ' . $this->etiqueta($linea) . ' ya fue registrada en ' . $linea['tipoTransaccion'] . ' ' . $linea['numeroTransaccion'] . '.');
        }

        if ($linea['trasladada'] === 1) {
            return $this->error('El pendiente de la actividad ' . $this->etiqueta($linea) . ' ya fue trasladado a otro día.');
        }

        return $this->resultado(fn () => $this->laboresModel->reabrirLinea($linea, $this->usuarioRegistro(), $this->session->get('usu_id')));
    }

    public function cerrarPlan(): ResponseInterface
    {
        $plan = $this->planPedido();

        if ($plan instanceof ResponseInterface) {
            return $plan;
        }

        if ($plan['actividades'] === []) {
            return $this->error('El plan no tiene actividades.');
        }

        if ($plan['resumen']['pendientes'] > 0) {
            return $this->error('Hay ' . $plan['resumen']['pendientes'] . ' actividades pendientes por cerrar.');
        }

        return $this->resultado(fn () => $this->laboresModel->estadoPlan($plan, 'CERRADA', $this->usuarioRegistro(), $this->session->get('usu_id')));
    }

    public function reabrirPlan(): ResponseInterface
    {
        $plan = $this->planPedido(false);

        if ($plan instanceof ResponseInterface) {
            return $plan;
        }

        if ($plan['estado'] !== 'CERRADA') {
            return $this->error('El plan ya está abierto.');
        }

        return $this->resultado(fn () => $this->laboresModel->estadoPlan($plan, 'ABIERTA', $this->usuarioRegistro(), $this->session->get('usu_id')));
    }

    public function eliminar(): ResponseInterface
    {
        $plan = $this->planPedido(true, ['eliminar'], 'eliminar');

        if ($plan instanceof ResponseInterface) {
            return $plan;
        }

        if ($plan['resumen']['pendientes'] !== $plan['resumen']['actividades'] || $plan['resumen']['vinculadas'] > 0) {
            return $this->error('El plan tiene actividades cerradas o registradas y no se puede eliminar.');
        }

        return $this->resultado(fn () => $this->laboresModel->eliminarPlan($plan, $this->usuarioRegistro(), $this->session->get('usu_id')), false);
    }

    public function precarga(): ResponseInterface
    {
        $linea = $this->lineaPedida(['consultar', 'registrar', 'editar'], 'consultar');

        if ($linea instanceof ResponseInterface) {
            return $linea;
        }

        $etiqueta = $this->etiqueta($linea);

        if ($linea['tipoDestino'] === null) {
            return $this->error('La actividad ' . $etiqueta . ' es de cosecha: se registra en Producción con el tiquete.');
        }

        if ($linea['vinculada'] === 1) {
            return $this->error('La actividad ' . $etiqueta . ' ya fue registrada en ' . $linea['tipoTransaccion'] . ' ' . $linea['numeroTransaccion'] . '.');
        }

        if ($linea['registrable'] !== 1) {
            return $this->error('La actividad ' . $etiqueta . ' debe estar cerrada como COMPLETADA o PARCIAL con cantidad ejecutada.');
        }

        $cantidades = $this->repartir($linea['cantidadEjecutada'], array_column($linea['trabajadores'], 'cantidad'));

        return $this->response->setJSON([
            'success'       => true,
            'message'       => '',
            'destino'       => $linea['destino'],
            'tipo'          => $linea['tipoDestino'],
            'empresa'       => $linea['empresa'],
            'planeacion'    => $linea['planeacion'],
            'linea'         => $linea['id'],
            'fecha'         => $linea['fecha'],
            'finca'         => $linea['finca'],
            'seccion'       => $linea['seccion'],
            'lote'          => $linea['lote'],
            'novedad'       => $linea['novedad'],
            'novedadNombre' => $linea['novedadNombre'],
            'uMedida'       => $linea['uMedida'],
            'referencia'    => $linea['referencia'],
            'cantidad'      => $linea['cantidadEjecutada'],
            'trabajadores'  => array_map(static fn ($t, $c) => [
                'tercero'  => $t['tercero'],
                'codigo'   => $t['codigo'],
                'nombre'   => $t['nombre'],
                'cantidad' => $c,
            ], $linea['trabajadores'], $cantidades),
        ]);
    }

    private function requisitos(array $acciones, string $verbo): ?ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida($acciones, $verbo . ' la ' . static::NOMBRE)) {
            return $sinSesion;
        }

        try {
            $disponible = $this->laboresModel->planeacionDisponible();
        } catch (\Throwable $e) {
            log_message('error', 'Error verificando las tablas de planeación: ' . $e->getMessage());

            return $this->error('No se pudo verificar la planeación de labores.', 500);
        }

        return $disponible ? null : $this->error(self::SIN_TABLAS, 503);
    }

    private function empresaFecha(array $acciones, string $verbo): array|ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos($acciones, $verbo)) {
            return $sinRequisitos;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $fecha = $this->fechaValida($this->campo('fecha'));

        if ($fecha === null) {
            return $this->error('El campo "Fecha" es obligatorio y debe tener el formato AAAA-MM-DD.');
        }

        return ['empresa' => $empresa, 'fecha' => $fecha];
    }

    private function planDestino(int $empresa, string $fecha): array|ResponseInterface|null
    {
        try {
            $horas = $this->laboresModel->columnasHoras();
            $plan  = $this->laboresModel->plan($empresa, $fecha);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la planeación de labores: ' . $e->getMessage());

            return $this->error('No se pudo consultar el plan.', 500);
        }

        if (! $horas) {
            return $this->error('Ejecute el script alterar_planeacion_horario.sql.', 503);
        }

        if ($plan === null && ! $this->permisos()['registrar']) {
            return $this->error('No tiene permiso para registrar planes de labores.', 403);
        }

        if ($plan !== null && ! $this->permisos()['editar']) {
            return $this->error('No tiene permiso para editar planes de labores.', 403);
        }

        if ($plan !== null && $plan['estado'] !== 'ABIERTA') {
            return $this->error('El plan del ' . $fecha . ' está cerrado; reábralo para modificarlo.');
        }

        return $plan;
    }

    private function planPedido(bool $abierto = true, array $acciones = ['editar'], string $verbo = 'editar'): array|ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos($acciones, $verbo)) {
            return $sinRequisitos;
        }

        $id = (int) $this->campo('planeacion');

        try {
            $plan = $id > 0 ? $this->laboresModel->planPorId($id) : null;
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la planeación de labores: ' . $e->getMessage());

            return $this->error('No se pudo consultar el plan.', 500);
        }

        if ($plan === null) {
            return $this->error('No se encontró el plan.', 404);
        }

        if ($abierto && $plan['estado'] !== 'ABIERTA') {
            return $this->error('El plan del ' . $plan['fecha'] . ' está cerrado; reábralo para modificarlo.');
        }

        return $plan;
    }

    private function lineaPedida(array $acciones = ['editar'], string $verbo = 'editar'): array|ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos($acciones, $verbo)) {
            return $sinRequisitos;
        }

        $id = (int) $this->campo('linea');

        try {
            $linea = $id > 0 ? $this->laboresModel->linea($id) : null;
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la actividad de planeación: ' . $e->getMessage());

            return $this->error('No se pudo consultar la actividad.', 500);
        }

        if ($linea === null) {
            return $this->error('No se encontró la actividad.', 404);
        }

        if ($acciones === ['editar'] && $linea['planEstado'] !== 'ABIERTA') {
            return $this->error('El plan del ' . $linea['fecha'] . ' está cerrado; reábralo para modificarlo.');
        }

        return $linea;
    }

    private function actividadValida(int $empresa, string $fecha, int $orden, array $fila, array $ausentes, array &$usados): array|string
    {
        $v             = [];
        $this->detalle = [];

        foreach (['finca' => 50, 'lote' => 50, 'novedad' => 50, 'uMedida' => 50, 'referencia' => 50, 'observacion' => 250] as $c => $maximo) {
            $v[$c] = is_array($fila[$c] ?? null) ? '' : trim((string) ($fila[$c] ?? ''));

            if (strlen($v[$c]) > $maximo) {
                return 'El campo "' . $c . '" de la actividad ' . $orden . ' admite máximo ' . $maximo . ' caracteres.';
            }
        }

        foreach (['horaInicio' => 'Hora inicio', 'horaFin' => 'Hora fin'] as $c => $nombre) {
            $v[$c] = is_array($fila[$c] ?? null) ? '' : trim((string) ($fila[$c] ?? ''));

            if ($v[$c] !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v[$c])) {
                return 'El campo "' . $nombre . '" de la actividad ' . $orden . ' debe tener el formato HH:MM (00:00 a 23:59).';
            }
        }

        if (($v['horaInicio'] === '') !== ($v['horaFin'] === '')) {
            return 'La actividad ' . $orden . ' debe tener hora de inicio y hora de fin, o ninguna de las dos.';
        }

        if ($v['horaInicio'] !== '' && $v['horaFin'] <= $v['horaInicio']) {
            return 'La hora de fin de la actividad ' . $orden . ' debe ser mayor que la hora de inicio.';
        }

        if ($v['finca'] === '' || $v['lote'] === '' || $v['novedad'] === '' || $v['uMedida'] === '') {
            return 'La actividad ' . $orden . ' debe tener labor, unidad de medida, finca y lote.';
        }

        $labor = $this->laboresModel->labor($empresa, $v['novedad']);

        if ($labor === null) {
            return 'La labor ' . $v['novedad'] . ' de la actividad ' . $orden . ' no existe o no está activa.';
        }

        if (! $this->laboresModel->unidadValida($empresa, $v['uMedida'])) {
            return 'La unidad de medida ' . $v['uMedida'] . ' de la actividad ' . $orden . ' no existe.';
        }

        if (! $this->laboresModel->fincaValida($empresa, $v['finca'])) {
            return 'La finca ' . $v['finca'] . ' de la actividad ' . $orden . ' no existe.';
        }

        if ($this->laboresModel->seccionDeLote($empresa, $v['finca'], $v['lote']) === null) {
            return 'El lote ' . $v['lote'] . ' no pertenece a la finca ' . $v['finca'] . ' (actividad ' . $orden . ').';
        }

        if ($v['referencia'] !== '') {
            if ($labor['grupo'] !== FertilizacionTransaccionModel::GRUPO) {
                return 'La referencia (plan de fertilización) de la actividad ' . $orden . ' solo aplica a labores de fertilización.';
            }

            if (! (new FertilizacionTransaccionModel())->planValido($empresa, $v['referencia'], $v['finca'], $fecha)) {
                return 'La referencia ' . $v['referencia'] . ' de la actividad ' . $orden . ' no es un plan ' . FertilizacionTransaccionModel::PLAN
                    . ' vigente de la finca ' . $v['finca'] . '.';
            }
        }

        $recibidos = $fila['trabajadores'] ?? null;

        if (! is_array($recibidos) || $recibidos === []) {
            return 'La actividad ' . $orden . ' debe tener al menos un trabajador.';
        }

        if (count($recibidos) > self::MAX_TRABAJADORES) {
            return 'La actividad ' . $orden . ' admite máximo ' . self::MAX_TRABAJADORES . ' trabajadores.';
        }

        $v['novedad']       = $labor['codigo'];
        $v['novedadNombre'] = $labor['descripcion'];
        $trabajadores       = [];
        $suma               = 0;

        foreach (array_values($recibidos) as $recibido) {
            $nit         = is_array($recibido) && ! is_array($recibido['tercero'] ?? null) ? trim((string) ($recibido['tercero'] ?? '')) : '';
            $reasignado  = is_array($recibido) && ! is_array($recibido['reasignadoDe'] ?? null) ? trim((string) ($recibido['reasignadoDe'] ?? '')) : '';
            $tercero     = $nit === '' || strlen($nit) > 50 ? null : $this->laboresModel->tercero($empresa, $nit);

            $this->detalle = ['tercero' => $nit];

            if ($tercero === null) {
                return 'El trabajador ' . $nit . ' de la actividad ' . $orden . ' no existe o está inactivo.';
            }

            if (isset($trabajadores[$tercero['nit']])) {
                return 'El trabajador ' . $this->nombre($tercero) . ' está repetido en la actividad ' . $orden . '.';
            }

            if (in_array($tercero['nit'], $ausentes, true)) {
                return 'El trabajador ' . $this->nombre($tercero) . ' está marcado como ausente en el plan (actividad ' . $orden . ').';
            }

            if (strlen($reasignado) > 50) {
                return 'El campo "reasignadoDe" del trabajador ' . $this->nombre($tercero) . ' admite máximo 50 caracteres.';
            }

            $cantidad = $this->decimalDe(is_array($recibido) ? ($recibido['cantidad'] ?? null) : null);

            if ($cantidad === null || $cantidad < 0) {
                return 'La cantidad de ' . $this->nombre($tercero) . ' en la actividad ' . $orden . ' debe ser un número mayor o igual a cero con máximo 2 decimales.';
            }

            if ($error = $this->repetido($usados, $tercero + ['tercero' => $tercero['nit']], $v, 'actividad ' . $orden)) {
                return $error;
            }

            $suma += (int) round($cantidad * 100);

            $trabajadores[$tercero['nit']] = ['tercero' => $tercero['nit'], 'cantidad' => $cantidad, 'reasignadoDe' => $reasignado === '' ? null : $reasignado];
        }

        unset($v['novedadNombre']);

        $this->detalle = [];

        return array_merge($v, [
            'horaInicio'   => $v['horaInicio'] === '' ? null : $v['horaInicio'],
            'horaFin'      => $v['horaFin'] === '' ? null : $v['horaFin'],
            'observacion'  => $v['observacion'] === '' ? null : $v['observacion'],
            'referencia'   => $v['referencia'] === '' ? null : $v['referencia'],
            'cantidad'     => $suma / 100,
            'trabajadores' => array_values($trabajadores),
        ]);
    }

    private function marcarUsados(array &$usados, array $linea, string $excepto = ''): void
    {
        foreach ($linea['trabajadores'] as $trabajador) {
            if ($trabajador['tercero'] !== $excepto) {
                $usados[$this->llave($trabajador['tercero'], $linea)]     = 'actividad ' . $linea['orden'];
                $this->conflictos[$this->llave($trabajador['tercero'], $linea)] = $linea['id'];
            }
        }
    }

    private function repetido(array &$usados, array $trabajador, array $linea, string $donde): ?string
    {
        $llave = $this->llave($trabajador['tercero'], $linea);

        if (isset($usados[$llave])) {
            if (isset($this->conflictos[$llave])) {
                $this->detalle['conflicto'] = $this->conflictos[$llave];
            }

            return 'El trabajador ' . $this->nombre($trabajador) . ' ya tiene asignada la labor ' . $linea['novedad']
                . (($linea['novedadNombre'] ?? '') === '' ? '' : ' (' . $linea['novedadNombre'] . ')')
                . ' en el lote ' . $linea['lote'] . ' de la finca ' . $linea['finca'] . ' en este plan (' . $usados[$llave] . ').';
        }

        $usados[$llave] = $donde;

        return null;
    }

    private function errorActividad(string $mensaje, int $orden): ResponseInterface
    {
        return $this->response->setJSON(['success' => false, 'message' => $mensaje, 'actividad' => $orden] + $this->detalle)->setStatusCode(422);
    }

    private function llave(string $tercero, array $linea): string
    {
        return $tercero . '|' . $linea['novedad'] . '|' . $linea['finca'] . '|' . $linea['lote'];
    }

    private function nombre(array $tercero): string
    {
        return trim(($tercero['codigo'] ?? '') . ' ' . ($tercero['nombre'] ?? '')) ?: (string) ($tercero['tercero'] ?? $tercero['nit'] ?? '');
    }

    private function etiqueta(array $linea): string
    {
        return $linea['orden'] . ' del ' . $linea['fecha'] . ' (' . $linea['novedad'] . ' lote ' . $linea['lote'] . ')';
    }

    private function repartir(float $total, array $pesos): array
    {
        $pesos = array_map(static fn ($p) => max(0.0, (float) $p), array_values($pesos));

        if (array_sum($pesos) <= 0) {
            $pesos = array_fill(0, count($pesos), 1.0);
        }

        $suma     = array_sum($pesos);
        $centavos = (int) round($total * 100);
        $base     = [];
        $restos   = [];

        foreach ($pesos as $i => $peso) {
            $exacto     = $centavos * $peso / $suma;
            $base[$i]   = (int) floor($exacto);
            $restos[$i] = $exacto - $base[$i];
        }

        arsort($restos);

        foreach (array_slice(array_keys($restos), 0, max(0, $centavos - array_sum($base))) as $i) {
            $base[$i]++;
        }

        return array_map(static fn ($c) => $c / 100, $base);
    }

    private function resultado(callable $accion, bool $conPlan = true): ResponseInterface
    {
        try {
            $resultado = $accion();
        } catch (\Throwable $e) {
            log_message('error', 'Error en la planeación de labores: ' . $e->getMessage());

            return $this->error('No se pudo completar la operación.', 500);
        }

        if (! $resultado['success']) {
            return $this->error($resultado['message']);
        }

        if ($conPlan) {
            try {
                $resultado['plan'] = $this->laboresModel->planPorId((int) $resultado['id']);
            } catch (\Throwable $e) {
                log_message('error', 'Error consultando la planeación de labores: ' . $e->getMessage());
                $resultado['plan'] = null;
            }
        }

        return $this->response->setJSON($resultado);
    }
}
