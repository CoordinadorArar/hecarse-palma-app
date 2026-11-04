<?php

namespace App\Controllers\Dashboards;

use App\Controllers\BaseController;
use App\Models\BasculaInformeModel;
use App\Models\ReportesModel;
use App\Models\TablerosModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class TablerosController extends BaseController
{
    private const TABLEROS = [
        'produccion'    => ['ruta' => 'dashboards/produccion', 'titulo' => 'Tablero de producción', 'vista' => 'dashboards/tablero_produccion'],
        'fertilizacion' => ['ruta' => 'dashboards/fertilizacion', 'titulo' => 'Tablero de fertilización', 'vista' => 'dashboards/tablero_fertilizacion'],
        'bascula'       => ['ruta' => 'dashboards/bascula', 'titulo' => 'Informe báscula', 'vista' => 'dashboards/tablero_bascula'],
    ];

    private const MAX_PERDIDA_PCT = 0.02;

    private const BASCULA_CERO = ['viajes' => 0, 'kgBascula' => 0.0, 'kgCruzado' => 0.0, 'kgExtractora' => 0.0, 'cruzados' => 0, 'sin_tlc' => 0, 'fuera_regla' => 0, 'cumple' => 0, 'incompleto' => 0, 'noLlegados' => 0.0, 'faltante' => 0];

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
        $hist   = [];

        foreach ($this->model->historico($empresa, $anio - 1, $anio, $finca) as $r) {
            $hist[(int) $r['anio']][] = $r;
        }

        $act  = $this->produccionAnio($empresa, $anio, $finca, $lotesBd, $hist[$anio] ?? []);
        $prev = $this->produccionAnio($empresa, $anio - 1, $finca, $lotesBd, $hist[$anio - 1] ?? []);

        foreach ($act['filas'] as $r) {
            $agregar($r, 'real', $r['kilos']);
        }

        foreach ($prev['filas'] as $r) {
            $agregar($r, 'anterior', $r['kilos']);
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
            $ha   = isset($act['haHist'][$l['finca']]) ? (float) ($act['haHist'][$l['finca']][$codigo] ?? 0) : (float) $l['hectareas'];
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
                'fuente'       => $this->fuente(array_filter($act['fuentes'][$codF] ?? [], static fn ($i) => $enRango((int) $i) && $i <= $mes, ARRAY_FILTER_USE_KEY)),
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
                'fuente'         => $real === null ? null : $this->fuente(array_column($act['fuentes'], $i)),
                'fuenteAnterior' => $this->fuente(array_column($prev['fuentes'], $i)),
            ];
        }

        $fuentes = [
            'real'     => $this->fuente(array_column(array_filter($meses, static fn ($m) => $m['enRango']), 'fuente')),
            'anterior' => $this->fuente(array_column(array_filter($meses, static fn ($m) => $m['enRango']), 'fuenteAnterior')),
        ];

        foreach (['real' => $anio, 'anterior' => $anio - 1] as $serie => $a) {
            if ($fuentes[$serie] === 'mixto') {
                $avisos[] = 'La producción ' . ($serie === 'real' ? 'del periodo' : 'del año anterior') . ' (' . $a . ') combina báscula e histórico: por finca y mes se usa la báscula cuando tiene kilos y, si no, el histórico de producción.';
            }
        }

        $haHist = array_intersect_key($act['haHist'], array_flip(array_column($filaF, 'codigo')));

        if ($haHist !== []) {
            $avisos[] = 'Sin báscula en ' . $anio . ' para ' . implode(', ', array_map(static fn ($c) => (string) ($nombres[$c] ?? $c), array_keys($haHist))) . ': las hectáreas y la t/ha se toman del histórico de producción de ese año.';
        }

        if (! $existe) {
            $avisos[] = 'La tabla de presupuesto de producción no existe: no hay meta para comparar.';
        } elseif ($tot['meta'] === null) {
            $avisos[] = 'No hay presupuesto registrado para ' . $anio . ($finca !== '' ? ' en la finca seleccionada' : '') . '.';
        } elseif ($metaFinca) {
            $avisos[] = 'Parte del presupuesto está registrado a nivel de finca (sin lote): suma en los totales pero no en la tabla por lote.';
        }

        try {
            $metaTonHa = $this->model->metaTonHa($empresa, $anio, $finca);
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
                'tonHaAnualizada' => $tot['ha'] > 0 ? round($tot['real'] / 1000 / $tot['ha'] * 12 / ($f['modo'] === 'mes' ? 1 : $mes), 3) : null,
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
                if (isset($p['haHist'][$f])) {
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
            $avisos[] = 'En ' . implode(', ', array_keys($conHa)) . ' las hectáreas de las fincas sin báscula en el año se toman del histórico de producción; en las demás, de los lotes vigentes.';
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
            $sinBascula = ! isset($fuentes[$f]);

            foreach ($ms as $m => $rs) {
                foreach ($rs as $r) {
                    if ($sinBascula && (float) $r['hectareas'] > 0) {
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

            if (array_diff($sel, array_column($fincasDisp, 'codigo')) !== []) {
                return $this->error('Finca no válida.');
            }

            return $this->response->setJSON($this->basculaDatos($empresa, $desde, $hasta, $sel, $fincasDisp));
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el informe de báscula: ' . $e->getMessage());

            return $this->error('No se pudo consultar el informe.', 500);
        }
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
        $porLargo = array_map(fn ($n) => $this->normalizarBascula($n), array_column($fincasDisp, 'nombre', 'codigo'));
        uksort($porLargo, static fn ($a, $b) => strlen($porLargo[$b]) <=> strlen($porLargo[$a]) ?: strcmp((string) $a, (string) $b));
        $antes    =date('Y-m-d', strtotime($desde . ' -1 day'));
        $despues = date('Y-m-d', strtotime($hasta . ' +1 day'));
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
            $estado     = ! $completo ? 'incompleto' : ($t === null ? 'sin_tlc' : ($pct > self::MAX_PERDIDA_PCT ? 'fuera_regla' : 'cumple'));
            $items      = $t === null ? [] : array_filter($t['items'], static fn ($i) => $i['finca'] !== '');
            $fincas     = $items !== [] ? array_values(array_unique(array_column($items, 'finca'))) : $this->fincasOrigen($p['origen'], $porLargo);
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

        $kpis = $this->resumenBascula($total);
        ksort($dias);
        ksort($placas);
        ksort($exts);

        $conductores = array_map(function (array $c) {
            arsort($c['nombres']);

            return ['cedula' => $c['cedula'], 'conductor' => (string) array_key_first($c['nombres']), 'viajes' => $c['viajes']] + $this->resumenBascula($c) + ['fueraRegla' => $c['fuera_regla'], 'kgNoLlegados' => round($c['noLlegados'], 2)];
        }, array_values($cond));

        usort($conductores, static fn ($a, $b) => $b['kgBascula'] <=> $a['kgBascula']);

        return [
            'success' => true,
            'message' => '',
            'filtros' => ['empresa' => $empresa, 'desde' => $desde, 'hasta' => $hasta, 'dias' => (new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1, 'fincas' => $sel, 'ultimoPesaje' => $ultimo],
            'fincasDisponibles' => $fincasDisp,
            'regla'   => ['maxPerdidaPct' => self::MAX_PERDIDA_PCT],
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
                'kgNoLlegados'   => round($total['noLlegados'], 2),
                'viajesFaltante' => $total['faltante'],
                'tlcSinPesaje'   => count($sinPesaje),
                'kgTlcSinPesaje' => round(array_sum(array_column($sinPesaje, 'kgExtractora')), 2),
                'cumpleMes'      => $kpis['perdidaPct'] === null ? null : $kpis['perdidaPct'] <= self::MAX_PERDIDA_PCT,
            ],
            'dias' => array_map(fn (string $fecha, array $d) => ['fecha' => $fecha, 'viajes' => $d['viajes'], 'kgBasculaTotal' => round($d['kgBascula'], 2)] + $this->resumenBascula($d, true), array_keys($dias), $dias),
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
        ];
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
            $a['noLlegados']   += max(0, $kg - $kgExt);
            $a['faltante']     += $kg - $kgExt > 0 ? 1 : 0;
        }

        return $a;
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
        return strtr(mb_strtoupper(trim(preg_replace('/\s+/', ' ', $texto)), 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    }

    private function fincasOrigen(string $origen, array $porLargo): array
    {
        $texto  = preg_replace(['/\bCRUEIRO\b/', '/\bCR\b/', '/\bCG\b/'], ['CRUCEIRO', 'CRUCEIRO', 'CANAGUATE'], $this->normalizarBascula($origen));
        $fincas = [];

        foreach ($porLargo as $codigo => $nombre) {
            if ($nombre !== '' && str_contains($texto, $nombre)) {
                $fincas[] = (string) $codigo;
                $texto    = str_replace($nombre, '|', $texto);
            }
        }

        return $fincas;
    }

    private function extractoraBascula(int $empresa, string $destino, array &$cache): string
    {
        $texto = $this->normalizarBascula($destino);

        if ($texto === '') {
            return 'Sin destino';
        }

        foreach (self::EXTRACTORAS as $patron => [$nit, $nombre]) {
            if (preg_match($patron, $texto) === 1) {
                return $cache[$nit] ??= $this->basculaModel->nombreExtractora($empresa, $nit) ?? $nombre;
            }
        }

        return preg_match('/CRUCEIRO|CANAGUATE/', $texto) === 1 ? 'Movimiento interno' : $texto;
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
