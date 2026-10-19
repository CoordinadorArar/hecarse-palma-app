<?php

namespace App\Controllers\Dashboards;

use App\Controllers\BaseController;
use App\Models\ReportesModel;
use App\Models\TablerosModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class TablerosController extends BaseController
{
    private const TABLEROS = [
        'produccion'    => ['ruta' => 'dashboards/produccion', 'titulo' => 'Tablero de producción', 'vista' => 'dashboards/tablero_produccion'],
        'fertilizacion' => ['ruta' => 'dashboards/fertilizacion', 'titulo' => 'Tablero de fertilización', 'vista' => 'dashboards/tablero_fertilizacion'],
    ];

    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const GRAMOS = ['G', 'GR', 'GRS', 'GRAMO', 'GRAMOS'];

    private const KILOS = ['KG', 'KGS', 'KILO', 'KILOS', 'KILOGRAMO', 'KILOGRAMOS'];

    private $session;
    private TablerosModel $model;
    private ReportesModel $reportesModel;

    public function __construct()
    {
        $this->session       = session();
        $this->model         = new TablerosModel();
        $this->reportesModel = new ReportesModel();
    }

    public function produccion($id_loseta): string|RedirectResponse
    {
        return $this->vista('produccion', $id_loseta);
    }

    public function fertilizacion($id_loseta): string|RedirectResponse
    {
        return $this->vista('fertilizacion', $id_loseta);
    }

    private function vista(string $tablero, $id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::TABLEROS[$tablero]['ruta'])) {
            return redirect()->to(base_url('losetas'));
        }

        $empresas   = $this->model->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $data['title']       = self::TABLEROS[$tablero]['titulo'];
        $data['tablero']     = $tablero;
        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresaSel;
        $data['meses']       = self::MESES;

        $data = array_merge($data, $this->filtrosEmpresa($tablero, $empresaSel));

        return view(self::TABLEROS[$tablero]['vista'], $data);
    }

    private function filtrosEmpresa(string $tablero, int $empresa): array
    {
        if ($empresa <= 0) {
            return ['anios' => [], 'fincas' => [], 'anio_sel' => (int) date('Y'), 'mes_sel' => (int) date('n')];
        }

        $anios   = $tablero === 'produccion' ? $this->model->getAnios($empresa) : $this->model->aniosFertilizacion($empresa);
        $anioSel = in_array((int) date('Y'), $anios, true) ? (int) date('Y') : ($anios[0] ?? (int) date('Y'));
        $mesSel  = $tablero === 'produccion' ? $this->model->ultimoMesFacturado($empresa, $anioSel) : ($anioSel === (int) date('Y') ? (int) date('n') : 12);

        return [
            'anios'    => $anios,
            'fincas'   => $this->model->getFincas($empresa),
            'anio_sel' => $anioSel,
            'mes_sel'  => $mesSel > 0 ? $mesSel : (int) date('n'),
        ];
    }

    public function fincas(): ResponseInterface
    {
        $tablero = trim((string) $this->request->getPost('tablero'));

        if (! isset(self::TABLEROS[$tablero])) {
            return $this->error('El tablero solicitado no existe.', 404);
        }

        $acceso = $this->acceso($tablero);

        if ($acceso !== null) {
            return $acceso;
        }

        $empresa = (int) $this->request->getPost('empresa');

        try {
            if ($empresa <= 0 || ! $this->model->empresaExiste($empresa)) {
                return $this->error('Debe seleccionar una empresa válida.');
            }

            $filtros = $this->filtrosEmpresa($tablero, $empresa);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando filtros del tablero ' . $tablero . ': ' . $e->getMessage());

            return $this->error('No se pudieron consultar los filtros.', 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => ''] + $filtros);
    }

    private function filtrosBase(): array|ResponseInterface
    {
        $empresa = (int) $this->request->getPost('empresa');
        $anio    = (int) $this->request->getPost('anio');
        $mes     = (int) $this->request->getPost('mes');
        $modo    = trim((string) ($this->request->getPost('modo') ?? 'acumulado'));
        $finca   = trim((string) $this->request->getPost('finca'));

        try {
            $existe = $empresa > 0 && $this->model->empresaExiste($empresa);
            $fincas = $existe ? array_column($this->model->getFincas($empresa), 'nombre', 'codigo') : [];
        } catch (\Throwable $e) {
            log_message('error', 'Error validando filtros del tablero: ' . $e->getMessage());

            return $this->error('No se pudo consultar el tablero.', 500);
        }

        if (! $existe) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        if ($anio < 2000 || $anio > 2100) {
            return $this->error('Debe seleccionar un año válido.');
        }

        if ($mes < 1 || $mes > 12) {
            return $this->error('Debe seleccionar un mes válido.');
        }

        if (! in_array($modo, ['acumulado', 'mes'], true)) {
            return $this->error('El modo de mes debe ser "acumulado" o "mes".');
        }

        if ($finca !== '' && ! array_key_exists($finca, $fincas)) {
            return $this->error('La finca seleccionada no es válida.');
        }

        return ['empresa' => $empresa, 'anio' => $anio, 'mes' => $mes, 'modo' => $modo, 'finca' => $finca, 'fincas' => $fincas];
    }

    public function datosProduccion(): ResponseInterface
    {
        $acceso = $this->acceso('produccion');

        if ($acceso !== null) {
            return $acceso;
        }

        $f = $this->filtrosBase();

        if ($f instanceof ResponseInterface) {
            return $f;
        }

        $siembra = trim((string) $this->request->getPost('siembra'));

        if ($siembra !== '' && preg_match('/^\d{4}$/', $siembra) !== 1) {
            return $this->error('El año de siembra no es válido.');
        }

        try {
            return $this->response->setJSON($this->produccionDatos($f, (int) $siembra));
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el tablero de producción: ' . $e->getMessage());

            return $this->error('No se pudo consultar el tablero.', 500);
        }
    }

    private function produccionDatos(array $f, int $siembra): array
    {
        $empresa = $f['empresa'];
        $anio    = $f['anio'];
        $mes     = $f['mes'];
        $finca   = $f['finca'];
        $enRango = static fn (int $i) => $f['modo'] === 'mes' ? $i === $mes : $i <= $mes;
        $lotesBd = array_column($this->model->lotes($empresa), null, 'codigo');
        $avisos  = [];
        $excl    = false;
        $fincas  = [];
        $lotes   = [];

        $agregar = function (array $r, string $serie, float $kilos) use (&$fincas, &$lotes, &$excl, $lotesBd, $siembra, $finca) {
            $lote = trim((string) ($r['lote'] ?? ''));
            $codF = $lote !== '' && isset($lotesBd[$lote]) ? $lotesBd[$lote]['finca'] : trim((string) ($r['finca'] ?? ''));

            if ($finca !== '' && $codF !== $finca) {
                return;
            }

            if ($siembra > 0 && ($lote === '' || (int) ($lotesBd[$lote]['siembra'] ?? 0) !== $siembra)) {
                $excl = $excl || ($lote === '' && $kilos != 0);

                return;
            }

            $m = (int) $r['mes'];
            $fincas[$codF][$serie][$m] = ($fincas[$codF][$serie][$m] ?? 0) + $kilos;

            if ($lote !== '') {
                $lotes[$lote][$serie][$m] = ($lotes[$lote][$serie][$m] ?? 0) + $kilos;
            }
        };

        $existe = $this->model->presupuestoExiste();

        foreach ($this->model->getEjecutado($empresa, $anio, $finca) as $r) {
            $agregar($r, 'real', (float) $r['facturado']);
        }

        foreach ($this->model->getEjecutado($empresa, $anio - 1, $finca) as $r) {
            $agregar($r, 'anterior', (float) $r['facturado']);
        }

        $metaFinca = false;

        foreach ($this->model->getPresupuesto($empresa, $anio, $finca) as $r) {
            $metaFinca = $metaFinca || trim((string) $r['lote']) === '';
            $agregar($r, 'meta', (float) $r['kilos']);
        }

        foreach ($this->model->racimos($empresa, $anio) as $r) {
            $agregar($r + ['finca' => ''], 'racimos', (float) $r['racimos']);
        }

        foreach ($lotes as $codigo => $s) {
            foreach ($s['racimos'] ?? [] as $m => $v) {
                if ($v > 0 && ($s['real'][$m] ?? 0) > 0) {
                    $agregar(['lote' => (string) $codigo, 'finca' => '', 'mes' => $m], 'pprKg', (float) $s['real'][$m]);
                    $agregar(['lote' => (string) $codigo, 'finca' => '', 'mes' => $m], 'pprRac', (float) $v);
                }
            }
        }

        $siembras = [];

        foreach ($lotesBd as $codigo => $l) {
            if ($finca !== '' && $l['finca'] !== $finca) {
                continue;
            }

            if ((int) $l['siembra'] > 0 && ((int) $l['vigente'] === 1 || isset($lotes[$codigo]))) {
                $siembras[(int) $l['siembra']] = true;
            }

            if ((int) $l['vigente'] === 1 && ($siembra === 0 || (int) $l['siembra'] === $siembra)) {
                $lotes[$codigo] ??= [];
                $fincas[$l['finca']] ??= [];
            }
        }

        krsort($siembras);

        $suma  = static fn (array $s, string $serie, callable $en) => array_sum(array_filter($s[$serie] ?? [], static fn ($i) => $en((int) $i), ARRAY_FILTER_USE_KEY));
        $hayM  = static fn (array $s) => isset($s['meta']);
        $haF   = [];
        $filaL = [];

        foreach ($lotes as $codigo => $s) {
            $l    = $lotesBd[$codigo] ?? ['finca' => '', 'nombre' => '', 'siembra' => 0, 'hectareas' => 0, 'palmas' => 0];
            $ha   = (float) $l['hectareas'];
            $real = $suma($s, 'real', $enRango);
            $meta = $hayM($s) ? $suma($s, 'meta', $enRango) : null;
            $ant  = $suma($s, 'anterior', $enRango);
            $rac  = $suma($s, 'racimos', $enRango);
            $kgR  = $suma($s, 'pprKg', $enRango);
            $racR = $suma($s, 'pprRac', $enRango);

            $haF[$l['finca']] = ($haF[$l['finca']] ?? 0) + $ha;
            $filaL[] = [
                'finca'        => $l['finca'],
                'codigo'       => (string) $codigo,
                'nombre'       => $l['nombre'],
                'siembra'      => (int) $l['siembra'],
                'hectareas'    => round($ha, 2),
                'palmas'       => (int) $l['palmas'],
                'real'         => $this->ton($real),
                'meta'         => $meta === null ? null : $this->ton($meta),
                'anterior'     => $this->ton($ant),
                'cumplimiento' => $meta > 0 ? round($real / $meta, 4) : null,
                'variacion'    => $meta === null ? null : $this->ton($real - $meta),
                'racimos'      => (int) round($rac),
                'ppr'          => $racR > 0 ? round($kgR / $racR, 2) : null,
                'tonHa'        => $ha > 0 ? round($real / 1000 / $ha, 3) : null,
            ];
        }

        $nombres = $f['fincas'];
        $filaF   = [];
        $tot     = ['real' => 0.0, 'meta' => null, 'anterior' => 0.0, 'racimos' => 0.0, 'pprKg' => 0.0, 'pprRac' => 0.0, 'ha' => 0.0];
        $serieM  = [];

        ksort($fincas, SORT_STRING);

        foreach ($fincas as $codF => $s) {
            $ha   = $haF[$codF] ?? 0;
            $real = $suma($s, 'real', $enRango);
            $meta = $hayM($s) ? $suma($s, 'meta', $enRango) : null;
            $ant  = $suma($s, 'anterior', $enRango);
            $rac  = $suma($s, 'racimos', $enRango);
            $kgR  = $suma($s, 'pprKg', $enRango);
            $racR = $suma($s, 'pprRac', $enRango);

            $tot['real']     += $real;
            $tot['anterior'] += $ant;
            $tot['racimos']  += $rac;
            $tot['pprKg']    += $kgR;
            $tot['pprRac']   += $racR;
            $tot['ha']       += $ha;
            $tot['meta']      = $meta === null ? $tot['meta'] : ($tot['meta'] ?? 0) + $meta;

            foreach (['real', 'meta', 'anterior', 'racimos', 'pprKg', 'pprRac'] as $serie) {
                foreach ($s[$serie] ?? [] as $i => $v) {
                    $serieM[$serie][$i] = ($serieM[$serie][$i] ?? 0) + $v;
                }
            }

            $filaF[] = [
                'codigo'       => (string) $codF,
                'nombre'       => (string) $codF === '' ? 'Sin finca' : (string) ($nombres[$codF] ?? $codF),
                'hectareas'    => round($ha, 2),
                'real'         => $this->ton($real),
                'meta'         => $meta === null ? null : $this->ton($meta),
                'anterior'     => $this->ton($ant),
                'cumplimiento' => $meta > 0 ? round($real / $meta, 4) : null,
                'vsAnterior'   => $ant > 0 ? round($real / $ant - 1, 4) : null,
                'racimos'      => (int) round($rac),
                'ppr'          => $racR > 0 ? round($kgR / $racR, 2) : null,
                'tonHa'        => $ha > 0 ? round($real / 1000 / $ha, 3) : null,
                'kgReal'       => $real,
            ];
        }

        foreach ($filaF as &$fila) {
            $fila['participacion'] = $tot['real'] > 0 ? round($fila['kgReal'] / $tot['real'], 4) : null;
            unset($fila['kgReal']);
        }

        unset($fila);

        $meses = [];

        for ($i = 1; $i <= 12; $i++) {
            $real = $i > $mes ? null : (float) ($serieM['real'][$i] ?? 0);
            $meta = isset($serieM['meta']) ? (float) ($serieM['meta'][$i] ?? 0) : null;
            $ant  = (float) ($serieM['anterior'][$i] ?? 0);
            $rac  = (float) ($serieM['racimos'][$i] ?? 0);
            $kgR  = (float) ($serieM['pprKg'][$i] ?? 0);
            $racR = (float) ($serieM['pprRac'][$i] ?? 0);

            $meses[] = [
                'mes'          => $i,
                'nombre'       => self::MESES[$i],
                'enRango'      => $enRango($i),
                'real'         => $real === null ? null : $this->ton($real),
                'meta'         => $meta === null ? null : $this->ton($meta),
                'anterior'     => $this->ton($ant),
                'cumplimiento' => $real !== null && $meta > 0 ? round($real / $meta, 4) : null,
                'vsAnterior'   => $real !== null && $ant > 0 ? round($real / $ant - 1, 4) : null,
                'racimos'      => $real === null ? null : (int) round($rac),
                'ppr'          => $real !== null && $racR > 0 ? round($kgR / $racR, 2) : null,
                'tonHa'        => $real !== null && $tot['ha'] > 0 ? round($real / 1000 / $tot['ha'], 3) : null,
            ];
        }

        if (! $existe) {
            $avisos[] = 'La tabla de presupuesto de producción no existe: no hay meta para comparar.';
        } elseif ($tot['meta'] === null) {
            $avisos[] = 'No hay presupuesto registrado para ' . $anio . ($finca !== '' ? ' en la finca seleccionada' : '') . '.';
        } elseif ($metaFinca) {
            $avisos[] = 'Parte del presupuesto está registrado a nivel de finca (sin lote): suma en los totales pero no en la tabla por lote.';
        }

        if ($excl) {
            $avisos[] = 'Con el filtro de año de siembra se excluyen la producción y el presupuesto registrados sin lote.';
        }

        if ($tot['real'] > 0 && $tot['racimos'] <= 0) {
            $avisos[] = 'No hay racimos registrados en el periodo: no se puede calcular el peso promedio de racimo.';
        }

        return [
            'success'  => true,
            'message'  => '',
            'avisos'   => $avisos,
            'unidad'   => 'ton',
            'filtros'  => ['anio' => $anio, 'mes' => $mes, 'modo' => $f['modo'], 'finca' => $finca, 'siembra' => $siembra ?: null, 'ultimoMes' => $this->model->ultimoMesFacturado($empresa, $anio)],
            'opciones' => ['siembras' => array_keys($siembras)],
            'kpis'     => [
                'real'           => $this->ton($tot['real']),
                'meta'           => $tot['meta'] === null ? null : $this->ton($tot['meta']),
                'metaAnual'      => isset($serieM['meta']) ? $this->ton(array_sum($serieM['meta'])) : null,
                'anterior'       => $this->ton($tot['anterior']),
                'cumplimiento'   => $tot['meta'] > 0 ? round($tot['real'] / $tot['meta'], 4) : null,
                'difMeta'        => $tot['meta'] === null ? null : $this->ton($tot['real'] - $tot['meta']),
                'vsAnterior'     => $tot['anterior'] > 0 ? round($tot['real'] / $tot['anterior'] - 1, 4) : null,
                'difAnterior'    => $this->ton($tot['real'] - $tot['anterior']),
                'hectareas'      => round($tot['ha'], 2),
                'tonHa'          => $tot['ha'] > 0 ? round($tot['real'] / 1000 / $tot['ha'], 3) : null,
                'racimos'        => (int) round($tot['racimos']),
                'ppr'            => $tot['pprRac'] > 0 ? round($tot['pprKg'] / $tot['pprRac'], 2) : null,
            ],
            'fincas'   => $filaF,
            'meses'    => $meses,
            'lotes'    => $filaL,
        ];
    }

    public function datosFertilizacion(): ResponseInterface
    {
        $acceso = $this->acceso('fertilizacion');

        if ($acceso !== null) {
            return $acceso;
        }

        $f = $this->filtrosBase();

        if ($f instanceof ResponseInterface) {
            return $f;
        }

        foreach (['seccion', 'lote', 'producto'] as $c) {
            $f[$c] = trim((string) $this->request->getPost($c));

            if (strlen($f[$c]) > 50) {
                return $this->error('El filtro ' . $c . ' no es válido.');
            }
        }

        try {
            return $this->response->setJSON($this->fertilizacionDatos($f));
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el tablero de fertilización: ' . $e->getMessage());

            return $this->error('No se pudo consultar el tablero.', 500);
        }
    }

    private function fertilizacionDatos(array $f): array
    {
        $anio    = $f['anio'];
        $desde   = sprintf('%04d-%02d-01', $anio, $f['modo'] === 'mes' ? $f['mes'] : 1);
        $hasta   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $f['mes'])));
        $lotesBd = array_column($this->model->lotes($f['empresa']), null, 'codigo');
        $avisos  = [];
        $unidades = [];
        $opc     = ['secciones' => [], 'lotes' => [], 'productos' => []];
        $lp      = [];
        $meses   = [];
        $prod    = [];
        $palmasP = [];
        $sinBulto = 0.0;

        $filtrar = function (array $r) use ($f, $lotesBd, &$opc, &$unidades): ?array {
            $lote = $r['lote'];
            $l    = $lotesBd[$lote] ?? ['finca' => '', 'seccion' => '', 'nombre' => '', 'hectareas' => 0, 'palmas' => 0];

            if ($f['finca'] !== '' && $l['finca'] !== $f['finca']) {
                return null;
            }

            if ($l['seccion'] !== '') {
                $opc['secciones'][$l['seccion']] = true;
            }

            $opc['lotes'][$lote] = ['codigo' => $lote, 'nombre' => $l['nombre'], 'finca' => $l['finca'], 'seccion' => $l['seccion']];
            $opc['productos'][$r['item']] = ['codigo' => $r['item'], 'nombre' => trim((string) $r['itemNombre'])];

            if (($f['seccion'] !== '' && $l['seccion'] !== $f['seccion']) || ($f['lote'] !== '' && $lote !== $f['lote']) || ($f['producto'] !== '' && $r['item'] !== $f['producto'])) {
                return null;
            }

            $u      = strtoupper(trim((string) $r['uMedida']));
            $factor = in_array($u, self::GRAMOS, true) ? 0.001 : 1.0;

            if (! in_array($u, self::KILOS, true) && $factor === 1.0) {
                $unidades[$u === '' ? '(vacía)' : $u] = true;
            }

            return [$lote, $r['item'], $factor];
        };

        foreach ($this->model->fertilizacionAplicada($f['empresa'], $desde, $hasta) as $r) {
            $res = $filtrar($r);

            if ($res === null) {
                continue;
            }

            [$lote, $item, $factor] = $res;
            $kg = (float) $r['cantidad'] * $factor;
            $b  = (float) $r['bultos'];
            $m  = (int) $r['mes'];

            $lp[$lote][$item]['apl']  = ($lp[$lote][$item]['apl'] ?? 0) + $kg;
            $lp[$lote][$item]['bApl'] = ($lp[$lote][$item]['bApl'] ?? 0) + $b;
            $meses[$m]['kg']          = ($meses[$m]['kg'] ?? 0) + $kg;
            $meses[$m]['bultos']      = ($meses[$m]['bultos'] ?? 0) + $b;
            $prod[$item]['nombre']    = trim((string) $r['itemNombre']);
            $prod[$item]['apl']       = ($prod[$item]['apl'] ?? 0) + $kg;
            $prod[$item]['bApl']      = ($prod[$item]['bApl'] ?? 0) + $b;
            $sinBulto                += (float) $r['sinBulto'] * $factor;
        }

        foreach ($this->model->fertilizacionPlan($f['empresa'], $desde, $hasta) as $r) {
            $res = $filtrar($r);

            if ($res === null) {
                continue;
            }

            [$lote, $item, $factor] = $res;
            $kg = (float) $r['cantidad'] * $factor;

            $lp[$lote][$item]['plan']  = ($lp[$lote][$item]['plan'] ?? 0) + $kg;
            $lp[$lote][$item]['bPlan'] = ($lp[$lote][$item]['bPlan'] ?? 0) + (float) $r['bultos'];
            $prod[$item]['nombre']     = trim((string) $r['itemNombre']);
            $prod[$item]['plan']       = ($prod[$item]['plan'] ?? 0) + $kg;
            $prod[$item]['bPlan']      = ($prod[$item]['bPlan'] ?? 0) + (float) $r['bultos'];
            $palmasP[$lote]            = max($palmasP[$lote] ?? 0, (float) $r['palmas']);
        }

        $tot   = ['apl' => 0.0, 'plan' => 0.0, 'bApl' => 0.0, 'bPlan' => 0.0, 'pend' => 0.0, 'palmas' => 0.0, 'ha' => 0.0];
        $filaL = [];
        $filaD = [];

        ksort($lp, SORT_STRING);

        foreach ($lp as $lote => $items) {
            $l      = $lotesBd[$lote] ?? ['finca' => '', 'seccion' => '', 'nombre' => '', 'hectareas' => 0, 'palmas' => 0];
            $palmas = (float) $l['palmas'] > 0 ? (float) $l['palmas'] : (float) ($palmasP[$lote] ?? 0);
            $ha     = (float) $l['hectareas'];
            $s      = ['apl' => 0.0, 'plan' => 0.0, 'bApl' => 0.0, 'bPlan' => 0.0, 'pend' => 0.0];

            foreach ($items as $item => $v) {
                $apl  = $v['apl'] ?? 0.0;
                $plan = $v['plan'] ?? 0.0;
                $s['apl']   += $apl;
                $s['plan']  += $plan;
                $s['bApl']  += $v['bApl'] ?? 0.0;
                $s['bPlan'] += $v['bPlan'] ?? 0.0;
                $s['pend']  += max(0, $plan - $apl);

                $filaD[] = [
                    'lote'          => (string) $lote,
                    'producto'      => (string) $item,
                    'aplicadoKg'    => round($apl, 2),
                    'planificadoKg' => round($plan, 2),
                    'kgPalma'       => $palmas > 0 ? round($apl / $palmas, 4) : null,
                    'metaKgPalma'   => $palmas > 0 && $plan > 0 ? round($plan / $palmas, 4) : null,
                    'cumplimiento'  => $plan > 0 ? round($apl / $plan, 4) : null,
                ];
            }

            foreach ($s as $k => $v) {
                $tot[$k] += $v;
            }

            $tot['palmas'] += $palmas;
            $tot['ha']     += $ha;

            $filaL[] = [
                'finca'             => $l['finca'],
                'seccion'           => $l['seccion'],
                'codigo'            => (string) $lote,
                'nombre'            => $l['nombre'],
                'palmas'            => (int) round($palmas),
                'hectareas'         => round($ha, 2),
                'aplicadoKg'        => round($s['apl'], 2),
                'planificadoKg'     => round($s['plan'], 2),
                'bultosAplicados'   => round($s['bApl'], 2),
                'bultosPlan'        => round($s['bPlan'], 2),
                'kgPalma'           => $palmas > 0 ? round($s['apl'] / $palmas, 4) : null,
                'metaKgPalma'       => $palmas > 0 && $s['plan'] > 0 ? round($s['plan'] / $palmas, 4) : null,
                'desviacionKgPalma' => $palmas > 0 && $s['plan'] > 0 ? round(($s['apl'] - $s['plan']) / $palmas, 4) : null,
                'pendienteKgPalma'  => $palmas > 0 && $s['plan'] > 0 ? round($s['pend'] / $palmas, 4) : null,
                'cumplimiento'      => $s['plan'] > 0 ? round($s['apl'] / $s['plan'], 4) : null,
                'kgHa'              => $ha > 0 ? round($s['apl'] / $ha, 2) : null,
                'bultosHa'          => $ha > 0 ? round($s['bApl'] / $ha, 2) : null,
            ];
        }

        $filaP = [];

        ksort($prod, SORT_STRING);

        foreach ($prod as $item => $p) {
            $filaP[] = [
                'codigo'          => (string) $item,
                'nombre'          => $p['nombre'],
                'aplicadoKg'      => round($p['apl'] ?? 0, 2),
                'planificadoKg'   => round($p['plan'] ?? 0, 2),
                'bultosAplicados' => round($p['bApl'] ?? 0, 2),
                'bultosPlan'      => round($p['bPlan'] ?? 0, 2),
                'cumplimiento'    => ($p['plan'] ?? 0) > 0 ? round(($p['apl'] ?? 0) / $p['plan'], 4) : null,
            ];
        }

        $filaM = [];

        for ($i = 1; $i <= 12; $i++) {
            $filaM[] = ['mes' => $i, 'nombre' => self::MESES[$i], 'aplicadoKg' => round($meses[$i]['kg'] ?? 0, 2), 'bultosAplicados' => round($meses[$i]['bultos'] ?? 0, 2)];
        }

        if ($tot['plan'] <= 0) {
            $avisos[] = 'No hay planes de fertilización (PFA) vigentes en el periodo para los filtros seleccionados.';
        }

        if ($sinBulto > 0) {
            $avisos[] = number_format($sinBulto, 2, ',', '.') . ' kg aplicados no tienen peso de bulto en su plan: no suman en bultos.';
        }

        if ($unidades !== []) {
            $avisos[] = 'Unidades de medida tratadas como kg: ' . implode(', ', array_keys($unidades)) . '.';
        }

        ksort($opc['secciones'], SORT_STRING);
        ksort($opc['lotes'], SORT_STRING);
        uasort($opc['productos'], static fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return [
            'success'  => true,
            'message'  => '',
            'avisos'   => $avisos,
            'filtros'  => ['anio' => $anio, 'mes' => $f['mes'], 'modo' => $f['modo'], 'desde' => $desde, 'hasta' => $hasta, 'finca' => $f['finca'], 'seccion' => $f['seccion'], 'lote' => $f['lote'], 'producto' => $f['producto']],
            'opciones' => ['secciones' => array_keys($opc['secciones']), 'lotes' => array_values($opc['lotes']), 'productos' => array_values($opc['productos'])],
            'kpis'     => [
                'aplicadoKg'        => round($tot['apl'], 2),
                'planificadoKg'     => round($tot['plan'], 2),
                'cumplimiento'      => $tot['plan'] > 0 ? round($tot['apl'] / $tot['plan'], 4) : null,
                'bultosAplicados'   => round($tot['bApl'], 2),
                'bultosPlan'        => round($tot['bPlan'], 2),
                'palmas'            => (int) round($tot['palmas']),
                'hectareas'         => round($tot['ha'], 2),
                'kgHa'              => $tot['ha'] > 0 ? round($tot['apl'] / $tot['ha'], 2) : null,
                'bultosHa'          => $tot['ha'] > 0 ? round($tot['bApl'] / $tot['ha'], 2) : null,
                'kgPalma'           => $tot['palmas'] > 0 ? round($tot['apl'] / $tot['palmas'], 4) : null,
                'metaKgPalma'       => $tot['palmas'] > 0 && $tot['plan'] > 0 ? round($tot['plan'] / $tot['palmas'], 4) : null,
                'desviacionKgPalma' => $tot['palmas'] > 0 && $tot['plan'] > 0 ? round(($tot['apl'] - $tot['plan']) / $tot['palmas'], 4) : null,
                'pendienteKgPalma'  => $tot['palmas'] > 0 && $tot['plan'] > 0 ? round($tot['pend'] / $tot['palmas'], 4) : null,
            ],
            'lotes'     => $filaL,
            'productos' => $filaP,
            'meses'     => $filaM,
            'detalle'   => $filaD,
        ];
    }

    private function ton(float $kilos): float
    {
        return round($kilos / 1000, 3);
    }

    private function acceso(string $tablero): ?ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->error('Sesión expirada. Vuelva a iniciar sesión.', 401);
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::TABLEROS[$tablero]['ruta'])) {
            return $this->error('No tiene permiso para consultar el ' . mb_strtolower(self::TABLEROS[$tablero]['titulo']) . '.', 403);
        }

        return null;
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON(['success' => false, 'message' => $mensaje])->setStatusCode($codigo);
    }
}
