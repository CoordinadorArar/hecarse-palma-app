<?php

namespace App\Controllers\Dashboards;

use App\Controllers\BaseController;
use App\Models\BasculaInformeModel;
use App\Models\BasculaModel;
use App\Models\ReportesModel;
use App\Models\TablerosModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TablerosController extends BaseController
{
    private const TABLEROS = [
        'produccion'    => ['ruta' => 'dashboards/produccion', 'titulo' => 'Tablero de producción', 'vista' => 'dashboards/tablero_produccion'],
        'fertilizacion' => ['ruta' => 'dashboards/fertilizacion', 'titulo' => 'Tablero de fertilización', 'vista' => 'dashboards/tablero_fertilizacion'],
        'bascula'       => ['ruta' => 'dashboards/bascula', 'titulo' => 'Informe báscula', 'vista' => 'dashboards/tablero_bascula'],
    ];

    private const MAX_PERDIDA_KG = 100;

    private const BASCULA_CERO = ['viajes' => 0, 'kgBascula' => 0.0, 'kgCruzado' => 0.0, 'kgExtractora' => 0.0, 'cruzados' => 0, 'sin_tlc' => 0, 'fuera_regla' => 0, 'cumple' => 0, 'incompleto' => 0, 'kgSinTlc' => 0.0, 'kgFaltante' => 0.0, 'kgSobrante' => 0.0, 'faltante' => 0, 'sobrante' => 0];

    private const EXTRACTORAS = [
        '/MONTER|MOTERR/' => ['800116749', 'MONTERREY'],
        '/CERESAGRO/'     => ['901203105', 'CERESAGRO'],
        '/SAN ALBERTO/'   => ['900654394', 'PALMAS DE SAN ALBERTO'],
        '/SAN FERNANDO/'  => ['900121530', 'SAN FERNANDO'],
    ];

    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const GRAMOS = ['G', 'GR', 'GRS', 'GRAMO', 'GRAMOS'];

    private const KILOS = ['KG', 'KGS', 'KILO', 'KILOS', 'KILOGRAMO', 'KILOGRAMOS'];

    private $session;
    private TablerosModel $model;
    private ReportesModel $reportesModel;
    private BasculaInformeModel $basculaModel;

    public function __construct()
    {
        $this->session       = session();
        $this->model         = new TablerosModel();
        $this->reportesModel = new ReportesModel();
        $this->basculaModel  = new BasculaInformeModel();
    }

    public function produccion($id_loseta): string|RedirectResponse
    {
        return $this->vista('produccion', $id_loseta);
    }

    public function fertilizacion($id_loseta): string|RedirectResponse
    {
        return $this->vista('fertilizacion', $id_loseta);
    }

    public function bascula($id_loseta): string|RedirectResponse
    {
        return $this->vista('bascula', $id_loseta);
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

        $data = array_merge($data, $tablero === 'bascula' ? array_combine(['desde_sel', 'hasta_sel'], $this->rangoBascula()) + ['fincas_disp' => $empresaSel > 0 ? $this->basculaModel->fincasEmpresa($empresaSel) : []] : $this->filtrosEmpresa($tablero, $empresaSel));

        return view(self::TABLEROS[$tablero]['vista'], $data);
    }

    private function filtrosEmpresa(string $tablero, int $empresa): array
    {
        if ($tablero === 'produccion') {
            $anios = $empresa > 0 ? $this->model->getAnios($empresa) : [];

            return [
                'desde_sel'   => ((int) date('Y') - 1) . date('-m'),
                'hasta_sel'   => date('Y-m'),
                'desde_max'   => date('Y-m'),
                'periodo_min' => min(array_merge($anios, [(int) date('Y') - 1])) . '-01',
                'periodo_max' => date('Y') . '-12',
                'fincas'      => $empresa > 0 ? $this->model->getFincas($empresa) : [],
            ];
        }

        if ($empresa <= 0) {
            return ['anios' => [], 'fincas' => [], 'anio_sel' => (int) date('Y'), 'mes_sel' => (int) date('n')];
        }

        $anios   = $this->model->aniosFertilizacion($empresa);
        $anioSel = in_array((int) date('Y'), $anios, true) ? (int) date('Y') : ($anios[0] ?? (int) date('Y'));
        $mesSel  = $anioSel === (int) date('Y') ? (int) date('n') : 12;

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

        if (! in_array($tablero, ['produccion', 'fertilizacion'], true)) {
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

    private function filtrosBase(bool $rango = false): array|ResponseInterface
    {
        $empresa = (int) $this->request->getPost('empresa');
        $anio    = (int) $this->request->getPost('anio');
        $mes     = (int) $this->request->getPost('mes');
        $modo    = trim((string) ($this->request->getPost('modo') ?? 'acumulado'));
        $desde   = trim((string) $this->request->getPost('desde'));
        $hasta   = trim((string) $this->request->getPost('hasta'));
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

        if ($rango) {
            $formato = static fn (string $v) => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) === 1 && $v >= '2000-01';

            if (! $formato($desde)) {
                return $this->error('Debe seleccionar un periodo inicial válido (AAAA-MM).');
            }

            if (! $formato($hasta)) {
                return $this->error('Debe seleccionar un periodo final válido (AAAA-MM).');
            }

            if ($desde > date('Y-m')) {
                return $this->error('«Desde» no puede ser posterior al mes actual.');
            }

            if ($desde > $hasta) {
                return $this->error('El periodo inicial no puede ser posterior al periodo final.');
            }

            if (((int) substr($hasta, 0, 4) - (int) substr($desde, 0, 4)) * 12 + (int) substr($hasta, 5) - (int) substr($desde, 5) + 1 > 24) {
                return $this->error('El rango no puede superar 24 meses.');
            }

            if ($hasta > date('Y') . '-12') {
                return $this->error('El periodo final no puede ser posterior a diciembre de ' . date('Y') . '.');
            }
        } else {
            if ($anio < 2000 || $anio > 2100) {
                return $this->error('Debe seleccionar un año válido.');
            }

            if ($mes < 1 || $mes > 12) {
                return $this->error('Debe seleccionar un mes válido.');
            }

            if (! in_array($modo, ['acumulado', 'mes'], true)) {
                return $this->error('El modo de mes debe ser "acumulado" o "mes".');
            }
        }

        if ($finca !== '' && ! array_key_exists($finca, $fincas)) {
            return $this->error('La finca seleccionada no es válida.');
        }

        return $rango ? ['empresa' => $empresa, 'desde' => $desde, 'hasta' => $hasta, 'finca' => $finca, 'fincas' => $fincas]
            : ['empresa' => $empresa, 'anio' => $anio, 'mes' => $mes, 'modo' => $modo, 'finca' => $finca, 'fincas' => $fincas];
    }

    public function datosProduccion(): ResponseInterface
    {
        $acceso = $this->acceso('produccion');

        if ($acceso !== null) {
            return $acceso;
        }

        $f = $this->filtrosBase(true);

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
        $empresa   = $f['empresa'];
        $finca     = $f['finca'];
        [$aD, $mD] = array_map('intval', explode('-', $f['desde']));
        [$aH, $mH] = array_map('intval', explode('-', $f['hasta']));
        $hoy       = date('Y-m');
        $txt       = static fn (int $d) => strtolower(self::MESES[$mD]) . ' ' . ($aD - $d) . ' – ' . strtolower(self::MESES[$mH]) . ' ' . ($aH - $d);
        $lotesBd   = array_column($this->model->lotes($empresa), null, 'codigo');
        $periodos  = [];
        $avisos    = [];
        $excl      = false;
        $fincas    = [];
        $lotes     = [];

        for ($i = $aD * 12 + $mD - 1; $i <= $aH * 12 + $mH - 1; $i++) {
            $periodos[sprintf('%04d-%02d', intdiv($i, 12), $i % 12 + 1)] = [intdiv($i, 12), $i % 12 + 1];
        }

        $agregar = function (array $r, string $serie, float $kilos, int $anio) use (&$fincas, &$lotes, &$excl, $lotesBd, $siembra, $finca, $periodos) {
            $p = sprintf('%04d-%02d', $anio, (int) $r['mes']);

            if (! isset($periodos[$p])) {
                return;
            }

            $lote = trim((string) ($r['lote'] ?? ''));
            $codF = $lote !== '' && isset($lotesBd[$lote]) ? $lotesBd[$lote]['finca'] : trim((string) ($r['finca'] ?? ''));

            if ($finca !== '' && $codF !== $finca) {
                return;
            }

            if ($siembra > 0 && ($lote === '' || (int) ($lotesBd[$lote]['siembra'] ?? 0) !== $siembra)) {
                $excl = $excl || ($lote === '' && $kilos != 0);

                return;
            }

            $fincas[$codF][$serie][$p] = ($fincas[$codF][$serie][$p] ?? 0) + $kilos;

            if ($lote !== '') {
                $lotes[$lote][$serie][$p] = ($lotes[$lote][$serie][$p] ?? 0) + $kilos;
            }
        };

        $existe = $this->model->presupuestoExiste();
        $hist   = [];
        $prod   = [];

        foreach ($this->model->historico($empresa, $aD - 1, $aH, $finca) as $r) {
            $hist[(int) $r['anio']][] = $r;
        }

        for ($a = $aD - 1; $a <= $aH; $a++) {
            $prod[$a] = $this->produccionAnio($empresa, $a, $finca, $lotesBd, $hist[$a] ?? []);

            foreach ($prod[$a]['filas'] as $r) {
                $agregar($r, 'real', $r['kilos'], $a);
                $agregar($r, 'anterior', $r['kilos'], $a + 1);
            }
        }

        $metaFinca = false;

        for ($a = $aD; $a <= $aH; $a++) {
            foreach ($this->model->getPresupuesto($empresa, $a, $finca) as $r) {
                $metaFinca = $metaFinca || (trim((string) $r['lote']) === '' && isset($periodos[sprintf('%04d-%02d', $a, (int) $r['mes'])]));
                $agregar($r, 'meta', (float) $r['kilos'], $a);
            }

            foreach ($this->model->racimos($empresa, $a) as $r) {
                $agregar($r + ['finca' => ''], 'racimos', (float) $r['racimos'], $a);
            }
        }

        foreach ($lotes as $codigo => $s) {
            foreach ($s['racimos'] ?? [] as $p => $v) {
                if ($v > 0 && ($s['real'][$p] ?? 0) > 0) {
                    $agregar(['lote' => (string) $codigo, 'finca' => '', 'mes' => $periodos[$p][1]], 'pprKg', (float) $s['real'][$p], $periodos[$p][0]);
                    $agregar(['lote' => (string) $codigo, 'finca' => '', 'mes' => $periodos[$p][1]], 'pprRac', (float) $v, $periodos[$p][0]);
                }
            }
        }

        $conBas = [];
        $haH    = [];

        foreach ($periodos as [$a, $m]) {
            foreach ($prod[$a]['fuentes'] as $codF => $ms) {
                if (($ms[$m] ?? null) === 'bascula') {
                    $conBas[$codF] = true;
                }
            }
        }

        for ($a = $aH; $a >= $aD; $a--) {
            $haH += array_diff_key($prod[$a]['haHist'], $conBas);
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

        $suma  = static fn (array $s, string $serie) => array_sum(array_filter($s[$serie] ?? [], static fn ($p) => $p <= $hoy, ARRAY_FILTER_USE_KEY));
        $hayM  = static fn (array $s) => array_filter($s['meta'] ?? [], static fn ($p) => $p <= $hoy, ARRAY_FILTER_USE_KEY) !== [];
        $haF   = [];
        $filaL = [];

        foreach ($lotes as $codigo => $s) {
            $l    = $lotesBd[$codigo] ?? ['finca' => '', 'nombre' => '', 'siembra' => 0, 'hectareas' => 0, 'palmas' => 0, 'vigente' => 0];
            $conH = isset($haH[$l['finca']]);
            $ha   = $conH ? (float) ($haH[$l['finca']][$codigo] ?? 0) : (float) $l['hectareas'];
            $real = $suma($s, 'real');
            $meta = $hayM($s) ? $suma($s, 'meta') : null;
            $ant  = $suma($s, 'anterior');
            $rac  = $suma($s, 'racimos');
            $kgR  = $suma($s, 'pprKg');
            $racR = $suma($s, 'pprRac');

            $haF[$l['finca']] = ($haF[$l['finca']] ?? 0) + ($conH || (int) $l['vigente'] === 1 ? $ha : 0);
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
            $real = $suma($s, 'real');
            $meta = $hayM($s) ? $suma($s, 'meta') : null;
            $ant  = $suma($s, 'anterior');
            $rac  = $suma($s, 'racimos');
            $kgR  = $suma($s, 'pprKg');
            $racR = $suma($s, 'pprRac');

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
                'fuente'       => $this->fuente(array_map(static fn ($am) => $prod[$am[0]]['fuentes'][$codF][$am[1]] ?? null, array_filter($periodos, static fn ($p) => $p <= $hoy, ARRAY_FILTER_USE_KEY))),
                'kgReal'       => $real,
            ];
        }

        foreach ($filaF as &$fila) {
            $fila['participacion'] = $tot['real'] > 0 ? round($fila['kgReal'] / $tot['real'], 4) : null;
            unset($fila['kgReal']);
        }

        unset($fila);

        $meses = [];

        foreach ($periodos as $p => [$a, $i]) {
            $real = $p > $hoy ? null : (float) ($serieM['real'][$p] ?? 0);
            $meta = isset($serieM['meta']) ? (float) ($serieM['meta'][$p] ?? 0) : null;
            $ant  = (float) ($serieM['anterior'][$p] ?? 0);
            $rac  = (float) ($serieM['racimos'][$p] ?? 0);
            $kgR  = (float) ($serieM['pprKg'][$p] ?? 0);
            $racR = (float) ($serieM['pprRac'][$p] ?? 0);

            $meses[] = [
                'periodo'      => $p,
                'anio'         => $a,
                'mes'          => $i,
                'nombre'       => self::MESES[$i] . ' ' . $a,
                'real'         => $real === null ? null : $this->ton($real),
                'meta'         => $meta === null ? null : $this->ton($meta),
                'anterior'     => $this->ton($ant),
                'cumplimiento' => $real !== null && $meta > 0 ? round($real / $meta, 4) : null,
                'vsAnterior'   => $real !== null && $ant > 0 ? round($real / $ant - 1, 4) : null,
                'racimos'      => $real === null ? null : (int) round($rac),
                'ppr'          => $real !== null && $racR > 0 ? round($kgR / $racR, 2) : null,
                'tonHa'        => $real !== null && $tot['ha'] > 0 ? round($real / 1000 / $tot['ha'], 3) : null,
                'fuente'         => $real === null ? null : $this->fuente(array_column($prod[$a]['fuentes'], $i)),
                'fuenteAnterior' => $this->fuente(array_column($prod[$a - 1]['fuentes'], $i)),
            ];
        }

        $fuentes = [
            'real'     => $this->fuente(array_column($meses, 'fuente')),
            'anterior' => $this->fuente(array_column($meses, 'fuenteAnterior')),
        ];

        foreach (['real' => 0, 'anterior' => 1] as $serie => $d) {
            if ($fuentes[$serie] === 'mixto') {
                $avisos[] = 'La producción ' . ($serie === 'real' ? 'del periodo' : 'del periodo anterior') . ' (' . $txt($d) . ') combina báscula e histórico: por finca y mes se usa la báscula cuando tiene kilos y, si no, el histórico de producción.';
            }
        }

        $haHist = array_intersect_key($haH, array_flip(array_column($filaF, 'codigo')));

        if ($haHist !== []) {
            $avisos[] = 'Sin báscula en el periodo (' . $txt(0) . ') para ' . implode(', ', array_map(static fn ($c) => (string) ($nombres[$c] ?? $c), array_keys($haHist))) . ': las hectáreas y la t/ha se toman del histórico de producción del año más reciente del periodo que lo tenga.';
        }

        if (! $existe) {
            $avisos[] = 'La tabla de presupuesto de producción no existe: no hay meta para comparar.';
        } elseif ($tot['meta'] === null) {
            $avisos[] = 'No hay presupuesto registrado para el periodo ' . $txt(0) . ($finca !== '' ? ' en la finca seleccionada' : '') . '.';
        } elseif ($metaFinca) {
            $avisos[] = 'Parte del presupuesto está registrado a nivel de finca (sin lote): suma en los totales pero no en la tabla por lote.';
        }

        try {
            $metaTonHa = $this->model->metaTonHa($empresa, $aH, $finca);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la meta t/ha del tablero de producción: ' . $e->getMessage());
            $metaTonHa = [null, null];
        }

        if ($excl) {
            $avisos[] = 'Con el filtro de año de siembra se excluyen la producción y el presupuesto registrados sin lote.';
        }

        if ($tot['real'] > 0 && $tot['racimos'] <= 0 && $fuentes['real'] !== 'historico') {
            $avisos[] = 'No hay racimos registrados en el periodo: no se puede calcular el peso promedio de racimo.';
        }

        return [
            'success'  => true,
            'message'  => '',
            'avisos'   => $avisos,
            'unidad'   => 'ton',
            'filtros'  => ['desde' => $f['desde'], 'hasta' => $f['hasta'], 'corte' => min($f['hasta'], $hoy), 'finca' => $finca, 'siembra' => $siembra ?: null, 'ultimoMes' => array_key_last(array_filter(array_column($meses, 'real', 'periodo'), static fn ($v) => $v > 0))],
            'opciones' => ['siembras' => array_keys($siembras)],
            'kpis'     => [
                'real'           => $this->ton($tot['real']),
                'meta'           => $tot['meta'] === null ? null : $this->ton($tot['meta']),
                'anterior'       => $this->ton($tot['anterior']),
                'cumplimiento'   => $tot['meta'] > 0 ? round($tot['real'] / $tot['meta'], 4) : null,
                'difMeta'        => $tot['meta'] === null ? null : $this->ton($tot['real'] - $tot['meta']),
                'vsAnterior'     => $tot['anterior'] > 0 ? round($tot['real'] / $tot['anterior'] - 1, 4) : null,
                'difAnterior'    => $this->ton($tot['real'] - $tot['anterior']),
                'hectareas'      => round($tot['ha'], 2),
                'tonHa'          => $tot['ha'] > 0 ? round($tot['real'] / 1000 / $tot['ha'], 3) : null,
                'tonHaAnualizada' => $tot['ha'] > 0 ? round($tot['real'] / 1000 / $tot['ha'] * 12 / max(1, count(array_filter($meses, static fn ($m) => $m['real'] !== null))), 3) : null,
                'metaTonHa'      => $metaTonHa[0],
                'metaTonHaOrigen' => $metaTonHa[1],
                'racimos'        => (int) round($tot['racimos']),
                'ppr'            => $tot['pprRac'] > 0 ? round($tot['pprKg'] / $tot['pprRac'], 2) : null,
                'fuentes'        => $fuentes,
            ],
            'fincas'   => $filaF,
            'meses'    => $meses,
            'lotes'    => $filaL,
        ];
    }

    public function historicoProduccion(): ResponseInterface
    {
        $acceso = $this->acceso('produccion');

        if ($acceso !== null) {
            return $acceso;
        }

        $empresa = (int) $this->request->getPost('empresa');
        $finca   = trim((string) $this->request->getPost('finca'));
        $siembra = trim((string) $this->request->getPost('siembra'));

        if ($siembra !== '' && preg_match('/^\d{4}$/', $siembra) !== 1) {
            return $this->error('El año de siembra no es válido.');
        }

        try {
            if ($empresa <= 0 || ! $this->model->empresaExiste($empresa)) {
                return $this->error('Debe seleccionar una empresa válida.');
            }

            if ($finca !== '' && ! array_key_exists($finca, array_column($this->model->getFincas($empresa), 'nombre', 'codigo'))) {
                return $this->error('La finca seleccionada no es válida.');
            }

            return $this->response->setJSON($this->historicoDatos($empresa, $finca, (int) $siembra));
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el histórico de producción: ' . $e->getMessage());

            return $this->error('No se pudo consultar el histórico de producción.', 500);
        }
    }

    private function historicoDatos(int $empresa, string $finca, int $siembra): array
    {
        $avisos = $this->model->historicoExiste() ? [] : ['La tabla dbo.aProduccionHistorica no existe: ejecute Documentacion/BaseDeDatos/crear_produccion_historica.sql y luego cargar_produccion_historica.sql. Solo se muestra la producción de báscula.'];
        $bascula = $this->model->aniosBascula($empresa);
        $lista   = array_unique(array_merge($bascula, $this->model->aniosHistorico($empresa)));

        if ($lista === []) {
            return ['success' => true, 'message' => '', 'anios' => [], 'desde' => null, 'hasta' => null, 'avisos' => $avisos];
        }

        $desde   = min($lista);
        $hasta   = max($lista);
        $lotesBd = array_column($this->model->lotes($empresa), null, 'codigo');
        $deSiem  = static fn (array $l) => $siembra === 0 || (int) $l['siembra'] === $siembra;
        $hist    = [];
        $anios   = [];
        $mixtos  = [];
        $conHa   = [];
        $excl    = false;

        foreach ($this->model->historico($empresa, $desde, $hasta, $finca) as $r) {
            $hist[(int) $r['anio']][] = $r;
        }

        for ($a = $desde; $a <= $hasta; $a++) {
            $p      = $this->produccionAnio($empresa, $a, $finca, $lotesBd, $hist[$a] ?? [], in_array($a, $bascula, true));
            $meses  = array_fill(1, 12, null);
            $usadas = [];
            $fuents = [];

            foreach ($p['filas'] as $r) {
                $lote = trim((string) ($r['lote'] ?? ''));

                if ($siembra > 0 && ($lote === '' || ! isset($lotesBd[$lote]) || ! $deSiem($lotesBd[$lote]))) {
                    $excl = $excl || ($lote === '' && $r['kilos'] != 0);

                    continue;
                }

                $meses[(int) $r['mes']] = ($meses[(int) $r['mes']] ?? 0) + $r['kilos'];

                if ($r['fuente'] !== null) {
                    $usadas[$r['codF']] = true;
                    $fuents[] = $r['fuente'];
                }
            }

            $ha = 0.0;

            foreach (array_keys($usadas) as $f) {
                if ($a < (int) date('Y') && isset($p['haHist'][$f])) {
                    $conHa[$a] = true;

                    foreach ($p['haHist'][$f] as $lote => $h) {
                        $ha += isset($lotesBd[$lote]) && ! $deSiem($lotesBd[$lote]) ? 0 : $h;
                    }

                    continue;
                }

                foreach ($lotesBd as $l) {
                    $ha += $l['finca'] === (string) $f && (int) $l['vigente'] === 1 && $deSiem($l) ? (float) $l['hectareas'] : 0;
                }
            }

            $con    = array_filter($meses, static fn ($v) => $v !== null);
            $total  = $con === [] ? null : array_sum($con);
            $fuente = $this->fuente($fuents);

            if ($fuente === 'mixto') {
                $mixtos[] = $a;
            }

            $anios[] = [
                'anio'      => $a,
                'meses'     => array_map(static fn ($v) => $v === null ? null : round($v, 2), array_values($meses)),
                'total'     => $total === null ? null : round($total, 2),
                'hectareas' => $ha > 0 ? round($ha, 2) : null,
                'tonHa'     => $ha > 0 && $total !== null ? round($total / 1000 / $ha, 3) : null,
                'fuente'    => $fuente,
            ];
        }

        if ($mixtos !== []) {
            $avisos[] = 'Los años ' . implode(', ', $mixtos) . ' combinan báscula e histórico: por finca y mes se usa la báscula cuando tiene kilos y, si no, el histórico de producción.';
        }

        if ($conHa !== []) {
            $avisos[] = 'En ' . implode(', ', array_keys($conHa)) . ' las hectáreas de las fincas con hectáreas registradas en el histórico de producción del año se toman de ese histórico, haya o no báscula; en las demás fincas y en el año en curso, de los lotes vigentes.';
        }

        if ($hasta === (int) date('Y')) {
            $avisos[] = 'El año ' . $hasta . ' está en curso: su total y su t/ha son parciales.';
        }

        if ($excl) {
            $avisos[] = 'Con el filtro de año de siembra se excluye la producción registrada sin lote.';
        }

        return ['success' => true, 'message' => '', 'anios' => $anios, 'desde' => $desde, 'hasta' => $hasta, 'avisos' => $avisos];
    }

    private function produccionAnio(int $empresa, int $anio, string $finca, array $lotesBd, array $hist, bool $bascula = true): array
    {
        $codF    = static fn (array $r) => ($l = trim((string) ($r['lote'] ?? ''))) !== '' && isset($lotesBd[$l]) ? (string) $lotesBd[$l]['finca'] : trim((string) ($r['finca'] ?? ''));
        $tlc     = [];
        $bas     = [];
        $his     = [];
        $fuentes = [];
        $haHist  = [];

        foreach ($bascula ? $this->model->getEjecutado($empresa, $anio, $finca) : [] as $r) {
            $f = $codF($r);
            $m = (int) $r['mes'];
            $tlc[$f][$m] = ($tlc[$f][$m] ?? 0) + (float) $r['facturado'];
            $bas[] = $r + ['kilos' => (float) $r['facturado'], 'codF' => $f];
        }

        foreach ($hist as $r) {
            $his[$codF($r)][(int) $r['mes']][] = $r;
        }

        $filas = array_values(array_map(static fn ($r) => $r + ['fuente' => ($tlc[$r['codF']][(int) $r['mes']] ?? 0) > 0 ? 'bascula' : null], array_filter($bas, static fn ($r) => ($tlc[$r['codF']][(int) $r['mes']] ?? 0) > 0 || ! isset($his[$r['codF']][(int) $r['mes']]))));

        foreach ($tlc as $f => $ms) {
            foreach ($ms as $m => $v) {
                if ($v > 0) {
                    $fuentes[$f][$m] = 'bascula';
                }
            }
        }

        foreach ($his as $f => $ms) {
            ksort($ms);

            foreach ($ms as $m => $rs) {
                foreach ($rs as $r) {
                    if ((float) $r['hectareas'] > 0) {
                        $haHist[$f][trim((string) $r['lote'])] = (float) $r['hectareas'];
                    }

                    if (($tlc[$f][$m] ?? 0) <= 0) {
                        $filas[] = ['lote' => trim((string) $r['lote']), 'finca' => trim((string) $r['finca']), 'mes' => $m, 'kilos' => (float) $r['kilos'], 'codF' => (string) $f, 'fuente' => 'historico'];
                    }
                }

                if (($tlc[$f][$m] ?? 0) <= 0) {
                    $fuentes[$f][$m] = 'historico';
                }
            }
        }

        return ['filas' => $filas, 'fuentes' => $fuentes, 'haHist' => $haHist];
    }

    private function fuente(array $valores): ?string
    {
        $u = array_values(array_unique(array_filter($valores)));

        return $u === [] ? null : (count($u) === 1 ? $u[0] : 'mixto');
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

    public function datosBascula(): ResponseInterface
    {
        $acceso = $this->acceso('bascula');

        if ($acceso !== null) {
            return $acceso;
        }

        $f = $this->filtrosBascula();

        if ($f instanceof ResponseInterface) {
            return $f;
        }

        try {
            return $this->response->setJSON($this->basculaDatos(...$f));
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el informe de báscula: ' . $e->getMessage());

            return $this->error('No se pudo consultar el informe.', 500);
        }
    }

    public function excelBascula(): ResponseInterface
    {
        $acceso = $this->acceso('bascula');

        if ($acceso !== null) {
            return $acceso;
        }

        $f = $this->filtrosBascula();

        if ($f instanceof ResponseInterface) {
            return $f;
        }

        if (! class_exists(Spreadsheet::class)) {
            return $this->error('La exportación a Excel no está disponible en el servidor.', 500);
        }

        ini_set('memory_limit', '512M');
        set_time_limit(120);

        try {
            $d       = $this->basculaDatos(...$f);
            $nFincas = array_column($f[4], 'nombre', 'codigo');
            $empresa = array_column($this->model->getEmpresas(), 'razonSocial', 'id')[$f[0]] ?? $f[0];
            $pct     = static fn ($v) => $v === null ? null : $v / 100;
            $si      = static fn ($v) => $v ? 'Sí' : 'No';
            $estados = ['cumple' => 'Cumple', 'fuera_regla' => 'Fuera de regla', 'sin_tlc' => 'Sin TLC', 'incompleto' => 'Incompleto'];
            $info    = ['Empresa: ' . $empresa, 'Desde: ' . $f[1] . '   Hasta: ' . $f[2], 'Fincas: ' . ($f[3] === [] ? 'Todas' : implode(', ', array_map(static fn ($c) => $nFincas[$c] ?? $c, $f[3]))), 'Generado: ' . date('Y-m-d H:i')];
            $viajes  = $d['viajes'];
            usort($viajes, static fn ($a, $b) => [(string) $a['entrada'], (string) $a['fecha'], $a['numero']] <=> [(string) $b['entrada'], (string) $b['fecha'], $b['numero']]);
            $suma       = static fn ($campo, $cond) => round(array_sum(array_map(static fn ($v) => $cond($v) ? (float) $v[$campo] : 0, $viajes)), 2);
            $todos      = static fn () => true;
            $sin2       = static fn ($v) => $v['estado'] === 'incompleto' || (float) $v['pesada2'] == 0;
            $completos  = $suma('kgBascula', static fn ($v) => $v['estado'] !== 'incompleto');
            $noLlegados = $suma('perdidaKg', static fn ($v) => (float) $v['perdidaKg'] > 0) + $suma('kgBascula', static fn ($v) => $v['estado'] === 'sin_tlc');
            $libro      = new Spreadsheet();
            $libro->removeSheetByIndex(0);

            $filas   = array_map(static fn ($v) => [
                $v['fecha'], substr((string) $v['entrada'], 11, 8), $v['placa'], implode(', ', array_map(static fn ($c) => $nFincas[$c] ?? $c, $v['fincas'])), $v['conductor'], $v['cedula'], $v['extractora'],
                $v['pesada1'], $sin2($v) ? null : $v['pesada2'], $sin2($v) ? null : $v['kgBascula'], implode(', ', $v['tlcs']), $v['tiquete'], $v['kgExtractora'], $v['perdidaKg'], $pct($v['perdidaPct']), $estados[$v['estado']] ?? $v['estado'], $si($v['parcial']),
            ], $viajes);
            $filas[] = array_replace(array_fill(0, 17, null), [0 => 'Total · ' . count($viajes) . ' viajes', 9 => $completos, 12 => $suma('kgExtractora', $todos), 13 => $suma('perdidaKg', $todos), 14 => $completos > 0 ? $noLlegados / $completos : null]);

            $hoja = $this->hojaBascula($libro, 'Viajes del periodo', $info, [
                'Fecha' => null, 'Hora entrada' => null, 'Placa' => null, 'Fincas' => null, 'Conductor' => null, 'Cédula' => null, 'Extractora' => null,
                '1.ª pesada' => '#,##0', '2.ª pesada' => '#,##0', 'Kg báscula' => '#,##0.00', 'TLC(s)' => null, 'Tiquete' => null, 'Kg extractora' => '#,##0.00',
                'Dif. kg' => '#,##0.00', '% pérdida (ref.)' => '0.00%', 'Estado' => null, 'Prorrateado' => null,
            ], $filas);

            $encabezado = 3 + count($info);
            $total      = $encabezado + count($filas);
            $hoja->getStyle('A' . $total . ':Q' . $total)->getFont()->setBold(true);
            $hoja->setAutoFilter('A' . $encabezado . ':Q' . ($total - 1));

            $fac      = $d['facturacion'];
            $facturas = $fac['facturas'];
            $conTabla = $fac['disponible'] && $facturas !== [];
            $infoFac  = array_merge($info, $fac['disponible'] ? array_filter([
                'Comparativo de kg facturados en Siesa contra kg TLC y kg báscula en el periodo de cada factura.',
                $f[3] !== [] ? 'La facturación de Siesa no se discrimina por finca: este comparativo muestra el total de la empresa.' : '',
                'TLC sin facturar: ' . number_format((float) $fac['totales']['kgSinFacturar'], 2, ',', '.') . ' kg en ' . array_sum(array_column($fac['sinFacturar'], 'tlcs')) . ' TLC',
                $facturas === [] ? $fac['mensaje'] : '',
            ]) : [$fac['mensaje']]);
            usort($facturas, static fn ($a, $b) => strcmp((string) $a['inicio'], (string) $b['inicio']) ?: strcmp((string) $a['extractora'], (string) $b['extractora']) ?: strcmp((string) $a['documento'], (string) $b['documento']));
            $cuadra  = static fn ($v) => $si(abs((float) $v) < 1);
            $filas   = array_map(static fn ($v) => [
                $v['documento'], $v['fecha'], $v['nit'], $v['extractora'], $v['inicio'], $v['fin'], $si($v['parcial']), $si($v['periodoPorFecha']), $v['kgFacturado'], $v['kgTlc'], $v['tlcs'], $v['difTlc'], $cuadra($v['difTlc']),
                $v['kgBascula'], $v['difBascula'], $v['tlcsSinPesaje'], $v['valorFacturado'], $v['ajusteValor'], implode(', ', $v['notasCredito']), $v['notas'],
            ], $facturas);
            $t       = $fac['totales'] ?? [];
            $filas[] = array_replace(array_fill(0, 20, null), [0 => 'Total · ' . count($facturas) . ' facturas', 8 => $t['kgFacturado'] ?? null, 9 => $t['kgTlc'] ?? null, 10 => array_sum(array_column($facturas, 'tlcs')), 11 => $t['difTlc'] ?? null, 12 => $cuadra($t['difTlc'] ?? 0), 13 => $t['kgBascula'] ?? null, 14 => $t['difBascula'] ?? null, 15 => array_sum(array_column($facturas, 'tlcsSinPesaje')), 16 => $t['valorFacturado'] ?? null, 17 => $t['ajusteValor'] ?? null]);

            $hoja = $this->hojaBascula($libro, 'Báscula vs facturado', $infoFac, $conTabla ? [
                'Factura' => null, 'Fecha factura' => null, 'NIT' => null, 'Extractora' => null, 'Inicio periodo' => null, 'Fin periodo' => null, 'Parcial' => null, 'Periodo estimado' => null,
                'Kg facturado' => '#,##0.00', 'Kg TLC' => '#,##0.00', 'Nº TLC' => '#,##0', 'Dif. vs TLC' => '#,##0.00', 'Cuadra con TLC' => null, 'Kg báscula (ref.)' => '#,##0.00', 'Dif. vs báscula' => '#,##0.00',
                'TLC sin pesaje' => '#,##0', 'Valor facturado' => '#,##0', 'Ajuste valor' => '#,##0', 'Notas crédito' => null, 'Notas' => null,
            ] : [], $filas);

            if ($conTabla) {
                $encabezado = 3 + count($infoFac);
                $total      = $encabezado + count($filas);
                $hoja->getStyle('A' . $total . ':T' . $total)->getFont()->setBold(true);
                $hoja->setAutoFilter('A' . $encabezado . ':T' . ($total - 1));
            }

            $libro->setActiveSheetIndex(0);

            ob_start();
            (new Xlsx($libro))->save('php://output');
            $contenido = ob_get_clean();
        } catch (\Throwable $e) {
            log_message('error', 'Error exportando el informe de báscula: ' . $e->getMessage());

            return $this->error('No se pudo generar el archivo de Excel.', 500);
        }

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="informe-bascula-' . $f[1] . '-' . $f[2] . '.xlsx"')
            ->setBody($contenido);
    }

    private function hojaBascula(Spreadsheet $libro, string $titulo, array $info, array $columnas, array $filas): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle($titulo);
        $hoja->setCellValueExplicit('A1', 'Informe báscula · ' . $titulo, DataType::TYPE_STRING);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $fila = 2;

        foreach ($info as $linea) {
            $hoja->setCellValueExplicit('A' . $fila++, (string) $linea, DataType::TYPE_STRING);
        }

        if ($columnas === []) {
            $hoja->getColumnDimension('A')->setAutoSize(true);

            return $hoja;
        }

        $fila++;
        $encabezado = $fila;
        $formatos   = array_values($columnas);
        $ultima     = Coordinate::stringFromColumnIndex(count($columnas));

        foreach (array_keys($columnas) as $i => $nombre) {
            $hoja->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . $fila, $nombre, DataType::TYPE_STRING);
        }

        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFont()->setBold(true);
        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9EEF4');

        foreach ($formatos as $i => $formato) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);

            if ($formato !== null && $filas !== []) {
                $hoja->getStyle($letra . ($encabezado + 1) . ':' . $letra . ($encabezado + count($filas)))->getNumberFormat()->setFormatCode($formato);
            }

            count($filas) > 5000 ? $hoja->getColumnDimension($letra)->setWidth(18) : $hoja->getColumnDimension($letra)->setAutoSize(true);
        }

        foreach ($filas as $registro) {
            $fila++;

            foreach (array_values($registro) as $i => $valor) {
                $celda   = Coordinate::stringFromColumnIndex($i + 1) . $fila;
                $formato = $formatos[$i] ?? null;

                if (is_array($valor)) {
                    [$valor, $formato] = $valor;

                    if ($formato !== null) {
                        $hoja->getStyle($celda)->getNumberFormat()->setFormatCode($formato);
                    }
                }

                if ($valor === null) {
                    continue;
                }

                $formato === null ? $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING) : $hoja->setCellValue($celda, $valor);
            }
        }

        $hoja->freezePane('A' . ($encabezado + 1));

        return $hoja;
    }

    private function filtrosBascula(): array|ResponseInterface
    {
        $empresa = (int) $this->request->getPost('empresa');
        $desde   = trim((string) $this->request->getPost('desde'));
        $hasta   = trim((string) $this->request->getPost('hasta'));

        if ($desde === '' && $hasta === '') {
            [$desde, $hasta] = $this->rangoBascula();
        } elseif ($desde === '' || $hasta === '') {
            return $this->error('Debe indicar la fecha inicial y la fecha final.');
        }

        if (! $this->fechaBascula($desde)) {
            return $this->error('La fecha inicial no es válida.');
        }

        if (! $this->fechaBascula($hasta)) {
            return $this->error('La fecha final no es válida.');
        }

        if ($desde > $hasta) {
            return $this->error('La fecha inicial no puede ser mayor que la final.');
        }

        if ((new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1 > 366) {
            return $this->error('El rango no puede superar 366 días.');
        }

        try {
            if ($empresa <= 0 || ! $this->model->empresaExiste($empresa)) {
                return $this->error('Debe seleccionar una empresa válida.');
            }

            $fincasDisp = $this->basculaModel->fincasEmpresa($empresa);
            $sel        = $this->request->getPost('fincas');
            $sel        = array_values(array_unique(array_filter(array_map(static fn ($f) => is_scalar($f) ? trim((string) $f) : '?', (array) $sel), static fn ($f) => $f !== '')));

        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el informe de báscula: ' . $e->getMessage());

            return $this->error('No se pudo consultar el informe.', 500);
        }

        if (array_diff($sel, array_column($fincasDisp, 'codigo')) !== []) {
            return $this->error('Finca no válida.');
        }

        return [$empresa, $desde, $hasta, $sel, $fincasDisp];
    }

    private function fechaBascula(string $fecha): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 2000 && (int) $m[1] <= 2100;
    }

    private function rangoBascula(): array
    {
        try {
            $ultimo = $this->basculaModel->ultimoPesaje();
        } catch (\Throwable $e) {
            $ultimo = null;
        }

        $desde = substr($ultimo ?? date('Y-m'), 0, 7) . '-01';

        return [$desde, date('Y-m-t', strtotime($desde))];
    }

    private function basculaDatos(int $empresa, string $desde, string $hasta, array $sel, array $fincasDisp): array
    {
        $ultimo   = $this->basculaModel->ultimoPesaje();
        $porLargo = BasculaModel::porLargo($fincasDisp);
        $porCo    = [];

        foreach ($fincasDisp as $f) {
            if ($f['co'] !== '') {
                $porCo[$f['co']][] = (string) $f['codigo'];
            }
        }

        try {
            $fac = $empresa === 1 ? $this->facturasBascula($empresa, $desde, $hasta) : null;
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la facturación de Siesa del informe de báscula: ' . $e->getMessage());
            $fac = false;
        }

        $antes   = date('Y-m-d', strtotime(min([$desde, ...array_column($fac['incl'] ?? [], 'inicio')]) . ' -1 day'));
        $despues = date('Y-m-d', strtotime(max([$hasta, ...array_column($fac['incl'] ?? [], 'fin')]) . ' +1 day'));
        $tlcs    = $this->basculaModel->tlc($empresa, $antes, $despues);
        $idx     = [];
        $usadas  = [];
        $nombres = [];
        $total   = self::BASCULA_CERO;
        $dias    = [];
        $placas  = [];
        $exts    = [];
        $cond    = [];
        $viajes  = [];
        $cargas  = [];
        $deTlc   = [];
        $asignar = [];

        foreach ($tlcs as $i => $t) {
            $tiq   = ltrim(trim($t['tiquete']), '0');
            $clave = $t['placa'] . '|' . ($tiq !== '' ? $tiq : '#' . $i);
            $cargas[$clave] ??= ['kg' => 0.0, 'tlcs' => [], 'tiquete' => $t['tiquete'], 'items' => []];
            $cargas[$clave]['kg'] += (float) $t['kg'];
            $cargas[$clave]['tlcs'][] = (int) $t['tlc'];
            $cargas[$clave]['items'][] = ['tlc' => (int) $t['tlc'], 'finca' => (string) $t['finca'], 'kg' => (float) $t['kg']];
            $deTlc[$i] = $clave;
            $idx[$t['placa'] . '|' . $t['fecha']][$clave] = true;
        }

        foreach ($cargas as &$c) {
            sort($c['tlcs']);
        }

        unset($c);
        $pesajes = $this->basculaModel->pesajes($antes, $despues);

        foreach ([0, 1] as $pasada) {
            foreach ($pesajes as $n => $p) {
                $kg = (float) $p['neto'];

                if (isset($asignar[$n]) || $p['placa'] === '' || (float) $p['pesada2'] == 0 || $kg <= 0) {
                    continue;
                }

                $dia    = substr($p['entrada'], 0, 10);
                $fechas = $pasada === 0 ? [$dia, substr((string) $p['salida'], 0, 10)] : [date('Y-m-d', strtotime($dia . ' -1 day')), $dia, date('Y-m-d', strtotime($dia . ' +1 day'))];
                $mejor  = null;

                foreach (array_unique($fechas) as $f) {
                    foreach (array_keys($idx[$p['placa'] . '|' . $f] ?? []) as $c) {
                        if (! isset($usadas[$c]) && ($mejor === null || abs($cargas[$c]['kg'] - $kg) < abs($cargas[$mejor]['kg'] - $kg))) {
                            $mejor = $c;
                        }
                    }
                }

                if ($mejor !== null) {
                    $usadas[$mejor] = true;
                    $asignar[$n]    = $mejor;
                }
            }
        }

        foreach ($pesajes as $n => $p) {
            $kg         = (float) $p['neto'];
            $fecha      = substr($p['entrada'], 0, 10);

            if ($fecha < $desde || $fecha > $hasta) {
                continue;
            }

            $extractora = $this->extractoraBascula($empresa, $p['destino'], $nombres);
            $completo   = (float) $p['pesada2'] != 0 && $kg > 0;
            $t          = isset($asignar[$n]) ? $cargas[$asignar[$n]] : null;
            $kgExt      = $t === null ? null : $t['kg'];
            $pct        = $t === null ? null : round(($kg - $kgExt) / $kg * 100, 4);
            $estado     = ! $completo ? 'incompleto' : ($t === null ? 'sin_tlc' : (round($kg - $kgExt, 2) > self::MAX_PERDIDA_KG ? 'fuera_regla' : 'cumple'));
            $items      = $t === null ? [] : array_filter($t['items'], static fn ($i) => $i['finca'] !== '');
            $origen     = $this->fincasOrigen($p['origen'], $porLargo);
            $co         = $porCo[$p['coFinca'] ?? ''] ?? [];
            $fincas     = $items !== [] ? array_values(array_unique(array_column($items, 'finca'))) : (count($origen) > 1 || $co === [] ? $origen : [array_values(array_intersect($origen, $co))[0] ?? $co[0]]);
            $tlcsV      = $t === null ? [] : $t['tlcs'];
            $parcial    = false;

            if ($sel !== []) {
                $dentro = array_values(array_intersect($fincas, $sel));

                if ($dentro === []) {
                    continue;
                }

                if ($items !== []) {
                    $elegidas = array_filter($t['items'], static fn ($i) => in_array($i['finca'], $sel, true));
                    $parcial  = count($elegidas) < count($t['items']);
                    $share    = $t['kg'] > 0 ? array_sum(array_column($elegidas, 'kg')) / $t['kg'] : count($elegidas) / count($t['items']);
                    $kgExt    = array_sum(array_column($elegidas, 'kg'));
                    $tlcsV    = array_column($elegidas, 'tlc');
                    sort($tlcsV);
                } else {
                    $parcial = count($dentro) < count($fincas);
                    $share   = count($dentro) / count($fincas);
                    $kgExt   = $kgExt === null ? null : $kgExt * $share;
                }

                $kg  *= $share;
                $pct  = $kgExt === null || $kg <= 0 ? null : round(($kg - $kgExt) / $kg * 100, 4);
            }

            $kg    = round($kg, 2);
            $kgExt = $kgExt === null ? null : round($kgExt, 2);

            $nombreC    = mb_strtoupper(preg_replace('/\s+/', ' ', $p['conductor']), 'UTF-8');
            $clave      = $p['cedula'] !== '' ? 'C' . $p['cedula'] : 'N' . $nombreC;

            $total          = $this->acumularBascula($total, $estado, $kg, $kgExt);
            $dias[$fecha]   = $this->acumularBascula($dias[$fecha] ?? null, $estado, $kg, $kgExt);
            $cond[$clave]   = $this->acumularBascula($cond[$clave] ?? null, $estado, $kg, $kgExt);
            $cond[$clave]['cedula'] = $p['cedula'];
            $cond[$clave]['nombres'][$p['conductor']] = ($cond[$clave]['nombres'][$p['conductor']] ?? 0) + 1;
            $exts[$extractora] = true;

            if ($p['placa'] !== '') {
                $placas[$fecha][$extractora][$p['placa']] = true;
            }

            $viajes[] = [
                'numero'       => (int) $p['numero'],
                'fecha'        => $fecha,
                'entrada'      => $p['entrada'],
                'salida'       => $p['salida'],
                'placa'        => $p['placa'],
                'conductor'    => $p['conductor'],
                'cedula'       => $p['cedula'],
                'origen'       => $p['origen'],
                'extractora'   => $extractora,
                'pesada1'      => round((float) $p['pesada1'], 2),
                'pesada2'      => round((float) $p['pesada2'], 2),
                'kgBascula'    => $kg,
                'tlc'          => $tlcsV[0] ?? null,
                'tlcs'         => $tlcsV,
                'tiquete'      => $t['tiquete'] ?? null,
                'kgExtractora' => $kgExt,
                'perdidaKg'    => $kgExt === null ? null : round($kg - $kgExt, 2),
                'perdidaPct'   => $pct,
                'estado'       => $estado,
                'fincas'       => $fincas,
                'parcial'      => $parcial,
            ];
        }

        $sinPesaje = [];

        foreach ($tlcs as $i => $t) {
            if (! isset($usadas[$deTlc[$i]]) && $t['fecha'] >= $desde && $t['fecha'] <= $hasta && ($sel === [] || in_array((string) $t['finca'], $sel, true))) {
                $sinPesaje[] = [
                    'tlc'          => (int) $t['tlc'],
                    'fecha'        => $t['fecha'],
                    'placa'        => $t['placa'],
                    'conductor'    => $t['conductor'],
                    'tiquete'      => $t['tiquete'],
                    'kgExtractora' => (float) $t['kg'],
                    'extractora'   => trim($t['nombre']) !== '' ? trim($t['nombre']) : ($t['nit'] !== '' ? $t['nit'] : 'Sin extractora'),
                ];
            }
        }

        $kpis = $this->resumenBascula($total) + $this->noLlegadosBascula($total);
        ksort($dias);
        ksort($placas);
        ksort($exts);

        $conductores = array_map(function (array $c) {
            arsort($c['nombres']);

            return ['cedula' => $c['cedula'], 'conductor' => (string) array_key_first($c['nombres']), 'viajes' => $c['viajes']] + $this->resumenBascula($c) + ['fueraRegla' => $c['fuera_regla'], 'kgSinTlc' => round($c['kgSinTlc'], 2), 'kgSobrante' => round($c['kgSobrante'], 2)] + $this->noLlegadosBascula($c);
        }, array_values($cond));

        usort($conductores, static fn ($a, $b) => $b['kgBascula'] <=> $a['kgBascula']);

        return [
            'success' => true,
            'message' => '',
            'filtros' => ['empresa' => $empresa, 'desde' => $desde, 'hasta' => $hasta, 'dias' => (new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1, 'fincas' => $sel, 'ultimoPesaje' => $ultimo],
            'fincasDisponibles' => $fincasDisp,
            'regla'   => ['maxPerdidaKg' => self::MAX_PERDIDA_KG],
            'kpis'    => [
                'viajes'         => $total['viajes'],
                'kgBascula'      => $kpis['kgBascula'],
                'kgExtractora'   => $kpis['kgExtractora'],
                'perdidaKg'      => $kpis['perdidaKg'],
                'perdidaPct'     => $kpis['perdidaPct'],
                'cruzados'       => $total['cruzados'],
                'sinTlc'         => $total['sin_tlc'],
                'fueraRegla'     => $total['fuera_regla'],
                'cumplen'        => $total['cumple'],
                'incompletos'    => $total['incompleto'],
                'kgNoLlegados'   => $kpis['kgNoLlegados'],
                'pctNoLlegados'  => $kpis['pctNoLlegados'],
                'kgSinTlc'       => round($total['kgSinTlc'], 2),
                'kgSobrante'     => round($total['kgSobrante'], 2),
                'viajesSobrante' => $total['sobrante'],
                'viajesFaltante' => $total['faltante'],
                'tlcSinPesaje'   => count($sinPesaje),
                'kgTlcSinPesaje' => round(array_sum(array_column($sinPesaje, 'kgExtractora')), 2),
                'cumpleMes'      => $total['cumple'] + $total['fuera_regla'] === 0 ? null : $total['fuera_regla'] === 0,
            ],
            'dias' => array_map(fn (string $fecha, array $d) => ['fecha' => $fecha, 'viajes' => $d['viajes'], 'kgBasculaTotal' => round($d['kgBascula'], 2)] + $this->resumenBascula($d, true) + $this->noLlegadosBascula($d), array_keys($dias), $dias),
            'viajes'       => $viajes,
            'tlcSinPesaje' => $sinPesaje,
            'placas'       => [
                'extractoras' => array_map('strval', array_keys($exts)),
                'dias'        => array_map(static fn (string $fecha, array $e) => [
                    'fecha'         => $fecha,
                    'total'         => count(array_replace(...array_values($e))),
                    'porExtractora' => (object) array_map(static function (array $l) {
                        $lista = array_map('strval', array_keys($l));
                        sort($lista);

                        return ['placas' => count($lista), 'lista' => $lista];
                    }, $e),
                ], array_keys($placas), $placas),
            ],
            'conductores' => $conductores,
            'facturacion' => $this->facturacionBascula($empresa, $desde, $hasta, $fac, $tlcs, $deTlc, array_combine($asignar, array_map(static fn ($n) => (float) $pesajes[$n]['neto'], array_keys($asignar)))),
        ];
    }

    private function facturasBascula(int $empresa, string $desde, string $hasta): array
    {
        $fves = [];
        $nces = [];

        foreach ($this->basculaModel->facturasSiesa(date('Y-m-d', strtotime($desde . ' -60 days')), date('Y-m-d', strtotime($hasta . ' +60 days'))) as $r) {
            $x = [
                'documento' => $r['tipo'] . '-' . (int) $r['consec'],
                'consec'    => (int) $r['consec'],
                'fecha'     => trim($r['fecha']),
                'nit'       => preg_replace('/\D/', '', (string) $r['nit']),
                'razon'     => (string) $r['razon'],
                'kg'        => (float) $r['kg'],
                'valor'     => abs((float) $r['valor']),
                'nota'      => $this->normalizarBascula((string) $r['notas']),
                'notas'     => mb_substr(trim(preg_replace('/\s+/', ' ', (string) $r['notas'])), 0, 250),
            ];

            if ($r['tipo'] === 'FVE') {
                $p = $this->periodoNota($x['nota'], $x['fecha']);
                $fves[$x['documento']] = $x + ['inicio' => $p[0] ?? $x['fecha'], 'fin' => $p[1] ?? $x['fecha'], 'periodoPorFecha' => $p === null, 'ajuste' => 0.0, 'nce' => [], 'anuladaPor' => null];
            } else {
                $nces[] = $x;
            }
        }

        $nombres = [];
        $ajustes = [];

        foreach ($nces as $c) {
            $ref = preg_match('/\bFVE\s*-?\s*(\d+)/', $c['nota'], $m) === 1 ? 'FVE-' . (int) $m[1] : null;

            if ($ref !== null && isset($fves[$ref]) && $fves[$ref]['nit'] === $c['nit']) {
                if ($c['valor'] >= 0.9 * $fves[$ref]['valor']) {
                    $fves[$ref]['anuladaPor'] ??= $c['documento'];
                } else {
                    $fves[$ref]['ajuste'] += $c['valor'];
                    $fves[$ref]['nce'][]   = $c['documento'];
                }
            } elseif ($c['fecha'] >= $desde && $c['fecha'] <= $hasta) {
                $ajustes[] = ['documento' => $c['documento'], 'fecha' => $c['fecha'], 'nit' => $c['nit'], 'extractora' => $this->nombreNit($empresa, $c['nit'], $c['razon'], $nombres), 'kg' => round($c['kg'], 2), 'valor' => round($c['valor']), 'referencia' => $ref, 'notas' => $c['notas']];
            }
        }

        $cruza = array_filter($fves, static fn ($v) => $v['inicio'] <= $hasta && $v['fin'] >= $desde);

        return ['fves' => $fves, 'cruza' => $cruza, 'incl' => array_filter($cruza, static fn ($v) => $v['anuladaPor'] === null), 'ajustes' => $ajustes];
    }

    private function facturacionBascula(int $empresa, string $desde, string $hasta, array|false|null $fac, array $tlcsCruce, array $deTlc, array $neto): array
    {
        $base = ['disponible' => false, 'mensaje' => '', 'reintentar' => false, 'porFinca' => false, 'facturas' => [], 'anuladas' => [], 'ajustesSinFactura' => [], 'sinFacturar' => [], 'totales' => null];

        if ($fac === null) {
            return ['mensaje' => 'El comparativo con la facturación de Siesa solo está disponible para la empresa 1.'] + $base;
        }

        if ($fac === false) {
            return ['mensaje' => 'No se pudo consultar la facturación en Siesa.', 'reintentar' => true] + $base;
        }

        try {
            $incl    = $fac['incl'];
            $nombres = [];
            $conoc   = array_flip(array_merge(array_column($fac['fves'], 'nit'), array_column(self::EXTRACTORAS, 0)));
            $cae     = static fn (array $x, array $v) => $x['nit'] === $v['nit'] && $x['fecha'] >= $v['inicio'] && $x['fecha'] <= $v['fin'];
            $nitN    = static fn (string $nit) => ! isset($conoc[$n = preg_replace('/\D/', '', $nit)]) && strlen($n) > 6 && isset($conoc[substr($n, 0, -1)]) ? substr($n, 0, -1) : $n;
            $tlcs    = array_map(static fn ($t, $i) => ['nit' => $nitN((string) $t['nit']), 'fecha' => $t['fecha'], 'kg' => (float) $t['kg'], 'nombre' => trim((string) $t['nombre']), 'carga' => $deTlc[$i]], $tlcsCruce, array_keys($tlcsCruce));

            $facturas = [];

            foreach ($incl as $v) {
                $tv     = array_filter($tlcs, static fn ($x) => $cae($x, $v));
                $cargas = array_column($tv, 'carga');
                $kgTlc  = round(array_sum(array_column($tv, 'kg')), 2);
                $kgBas  = round(array_sum(array_intersect_key($neto, array_flip($cargas))), 2);
                $kgFac  = round($v['kg'], 2);

                $facturas[] = [
                    'documento'       => $v['documento'],
                    'consec'          => $v['consec'],
                    'fecha'           => $v['fecha'],
                    'nit'             => $v['nit'],
                    'extractora'      => $this->nombreNit($empresa, $v['nit'], $v['razon'], $nombres),
                    'inicio'          => $v['inicio'],
                    'fin'             => $v['fin'],
                    'periodoPorFecha' => $v['periodoPorFecha'],
                    'parcial'         => $v['inicio'] < $desde || $v['fin'] > $hasta,
                    'kgFacturado'     => $kgFac,
                    'valorFacturado'  => round($v['valor'] - $v['ajuste']),
                    'ajusteValor'     => round($v['ajuste']),
                    'notasCredito'    => $v['nce'],
                    'kgTlc'           => $kgTlc,
                    'tlcs'            => count($tv),
                    'kgBascula'       => $kgBas,
                    'tlcsSinPesaje'   => count(array_filter($cargas, static fn ($c) => ! isset($neto[$c]))),
                    'difTlc'          => round($kgFac - $kgTlc, 2),
                    'difBascula'      => round($kgFac - $kgBas, 2),
                    'notas'           => $v['notas'],
                ];
            }

            $sin = [];

            foreach ($tlcs as $t) {
                if ($t['fecha'] < $desde || $t['fecha'] > $hasta || array_filter($incl, static fn ($v) => $cae($t, $v)) !== []) {
                    continue;
                }

                $sin[$t['nit']] ??= ['nit' => $t['nit'], 'extractora' => $this->nombreNit($empresa, $t['nit'], $t['nombre'], $nombres), 'tlcs' => 0, 'kg' => 0.0, 'desde' => $t['fecha'], 'hasta' => $t['fecha']];
                $sin[$t['nit']]['tlcs']++;
                $sin[$t['nit']]['kg']   += $t['kg'];
                $sin[$t['nit']]['desde'] = min($sin[$t['nit']]['desde'], $t['fecha']);
                $sin[$t['nit']]['hasta'] = max($sin[$t['nit']]['hasta'], $t['fecha']);
            }

            $sin   = array_values(array_map(static fn ($s) => array_replace($s, ['kg' => round($s['kg'], 2)]), $sin));
            $suma  = static fn (string $c) => array_sum(array_column($facturas, $c));
            $kgFac = round($suma('kgFacturado'), 2);
            $kgTlc = round($suma('kgTlc'), 2);
            $kgBas = round($suma('kgBascula'), 2);

            return [
                'disponible'        => true,
                'mensaje'           => $facturas === [] ? 'No hay facturas de fruta en Siesa para el rango consultado.' : '',
                'reintentar'        => false,
                'porFinca'          => false,
                'facturas'          => $facturas,
                'anuladas'          => array_values(array_map(fn ($v) => ['documento' => $v['documento'], 'fecha' => $v['fecha'], 'nit' => $v['nit'], 'extractora' => $this->nombreNit($empresa, $v['nit'], $v['razon'], $nombres), 'kg' => round($v['kg'], 2), 'valor' => round($v['valor']), 'anuladaPor' => $v['anuladaPor']], array_filter($fac['cruza'], static fn ($v) => $v['anuladaPor'] !== null))),
                'ajustesSinFactura' => $fac['ajustes'],
                'sinFacturar'       => $sin,
                'totales'           => [
                    'kgFacturado'    => $kgFac,
                    'valorFacturado' => round($suma('valorFacturado')),
                    'ajusteValor'    => round($suma('ajusteValor')),
                    'kgTlc'          => $kgTlc,
                    'kgBascula'      => $kgBas,
                    'difTlc'         => round($kgFac - $kgTlc, 2),
                    'difBascula'     => round($kgFac - $kgBas, 2),
                    'kgSinFacturar'  => round(array_sum(array_column($sin, 'kg')), 2),
                ],
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la facturación de Siesa del informe de báscula: ' . $e->getMessage());

            return ['mensaje' => 'No se pudo consultar la facturación en Siesa.', 'reintentar' => true] + $base;
        }
    }

    private function periodoNota(string $nota, string $fecha): ?array
    {
        $meses = ['ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8, 'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12];
        $mes   = '(' . implode('|', array_keys($meses)) . ')\b(?:\s*(?:DE\s+|DEL\s+)?(\/\s*\d+|\d+))?';
        $hall  = [];

        if (preg_match('/(?<!\d)(\d{1,2})\s*(?:AL|A)\s*(\d{1,2})(?!\d)\s*(?:DEL?\s+)?(?:MES\s+)?(?:DE\s+)?' . $mes . '/', $nota, $m, PREG_OFFSET_CAPTURE) === 1) {
            $hall[$m[0][1]] = [(int) $m[1][0], (int) $m[2][0], $meses[$m[3][0]], $m[4][0] ?? ''];
        }

        if (preg_match('/\b(1RA|1ERA|1A|PRIMERA|2DA|2A|SEGUNDA)\s+QUINCENA\s+(?:DE\s+|DEL\s+)?(?:MES\s+)?(?:DE\s+)?' . $mes . '/', $nota, $m, PREG_OFFSET_CAPTURE) === 1) {
            $seg            = in_array($m[1][0], ['2DA', '2A', 'SEGUNDA'], true);
            $hall[$m[0][1]] = [$seg ? 16 : 1, $seg ? 31 : 15, $meses[$m[2][0]], $m[3][0] ?? ''];
        }

        if ($hall === []) {
            return null;
        }

        ksort($hall);
        [$d1, $d2, $mm, $a] = reset($hall);
        $dig  = preg_replace('/\D/', '', $a);
        $y    = (int) substr($fecha, 0, 4);
        $cerc = static fn (int $p) => abs(mktime(0, 0, 0, $mm, max(1, $d1), $p) - strtotime($fecha));
        $a    = strlen($dig) === 4 && (int) $dig >= 2000 && (int) $dig <= 2100 ? (int) $dig : ($a !== '' && $a[0] === '/' && strlen($dig) === 2 ? 2000 + (int) $dig : array_reduce([$y, $y - 1, $y + 1], static fn ($m, $p) => $m === null || $cerc($p) < $cerc($m) ? $p : $m));
        $d2  = min($d2, (int) date('t', mktime(0, 0, 0, $mm, 1, $a)));

        return $d1 < 1 || $d1 > $d2 ? null : [sprintf('%04d-%02d-%02d', $a, $mm, $d1), sprintf('%04d-%02d-%02d', $a, $mm, $d2)];
    }

    private function acumularBascula(?array $a, string $estado, float $kg, ?float $kgExt): array
    {
        $a ??= self::BASCULA_CERO;
        $a['viajes']++;
        $a[$estado]++;
        $a['kgBascula'] += $estado === 'incompleto' ? 0 : $kg;

        if ($kgExt !== null) {
            $a['cruzados']++;
            $a['kgCruzado']    += $kg;
            $a['kgExtractora'] += $kgExt;
            $a['kgFaltante']   += max(0, $kg - $kgExt);
            $a['kgSobrante']   += max(0, $kgExt - $kg);
            $a['faltante']     += $kg > $kgExt ? 1 : 0;
            $a['sobrante']     += $kgExt > $kg ? 1 : 0;
        }

        $a['kgSinTlc'] += $estado === 'sin_tlc' ? $kg : 0;

        return $a;
    }

    private function noLlegadosBascula(array $a): array
    {
        $kg = $a['kgFaltante'] + $a['kgSinTlc'];

        return ['kgNoLlegados' => round($kg, 2), 'pctNoLlegados' => $a['kgBascula'] > 0 ? round($kg / $a['kgBascula'] * 100, 4) : null];
    }

    private function resumenBascula(array $a, bool $soloCruzados = false): array
    {
        $perdida = $a['kgCruzado'] - $a['kgExtractora'];

        return [
            'kgBascula'    => round($soloCruzados ? $a['kgCruzado'] : $a['kgBascula'], 2),
            'kgExtractora' => round($a['kgExtractora'], 2),
            'perdidaKg'    => round($perdida, 2),
            'perdidaPct'   => $a['kgCruzado'] > 0 ? round($perdida / $a['kgCruzado'] * 100, 4) : null,
        ];
    }

    private function normalizarBascula(string $texto): string
    {
        return BasculaModel::normalizar($texto);
    }

    private function fincasOrigen(string $origen, array $porLargo): array
    {
        return BasculaModel::fincasOrigen($origen, $porLargo);
    }

    private function extractoraBascula(int $empresa, string $destino, array &$cache): string
    {
        $texto = $this->normalizarBascula($destino);

        if ($texto === '') {
            return 'Sin destino';
        }

        foreach (self::EXTRACTORAS as $patron => [$nit]) {
            if (preg_match($patron, $texto) === 1) {
                return $this->nombreNit($empresa, $nit, '', $cache);
            }
        }

        return preg_match('/CRUCEIRO|CANAGUATE/', $texto) === 1 ? 'Movimiento interno' : $texto;
    }

    private function nombreNit(int $empresa, string $nit, string $alterno, array &$cache): string
    {
        if ($nit === '') {
            return $alterno !== '' ? $alterno : 'Sin extractora';
        }

        return $cache[$nit] ??= $this->basculaModel->nombreExtractora($empresa, $nit) ?? (array_column(self::EXTRACTORAS, 1, 0)[$nit] ?? ($alterno !== '' ? $alterno : $nit));
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
