<?php

namespace App\Controllers\Dashboards;

use App\Controllers\BaseController;
use App\Models\InformesGerencialesModel;
use App\Models\MapeoPalmasModel;
use App\Models\ReportesModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class InformesGerencialesController extends BaseController
{
    private const RUTA = 'dashboards/admin';

    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const MESES_NOMBRE = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private const MAPAS = [1 => ['002' => 'cruceiro', '003' => 'canaguate']];

    private $session;
    private InformesGerencialesModel $model;
    private ReportesModel $reportesModel;
    private MapeoPalmasModel $mapeoModel;

    public function __construct()
    {
        $this->session       = session();
        $this->model         = new InformesGerencialesModel();
        $this->reportesModel = new ReportesModel();
        $this->mapeoModel    = new MapeoPalmasModel();
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

        $empresas   = $this->model->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;
        $anios      = $empresaSel > 0 ? $this->model->getAnios($empresaSel) : [];
        $anioSel    = in_array((int) date('Y'), $anios, true) ? (int) date('Y') : ($anios[0] ?? (int) date('Y'));
        $mesSel     = $empresaSel > 0 ? $this->model->ultimoMesFacturado($empresaSel, $anioSel) : 0;

        $data['title']       = 'Informes Gerenciales';
        $data['informes']    = [[
            'clave'       => 'presupuesto-ejecutado',
            'nombre'      => 'Presupuesto vs Ejecutado',
            'icono'       => 'bi bi-clipboard-data',
            'descripcion' => 'Producción en kg: presupuesto vs báscula y labores de cosecha',
        ], [
            'clave'       => 'ciclos-cosecha',
            'nombre'      => 'Ciclos de cosecha',
            'icono'       => 'bi bi-map',
            'descripcion' => 'Días desde el último pase de cosecha por lote sobre el mapa de la finca',
        ], [
            'clave'       => 'ciclos-poda',
            'nombre'      => 'Ciclos de poda',
            'icono'       => 'bi bi-scissors',
            'descripcion' => 'Días para el próximo ingreso de poda por lote sobre el mapa de la finca',
        ]];
        $data['empresas']    = $empresas;
        $data['anios']       = $anios;
        $data['fincas']      = $empresaSel > 0 ? $this->model->getFincas($empresaSel) : [];
        $data['empresa_sel'] = $empresaSel;
        $data['anio_sel']    = $anioSel;
        $data['mes_sel']     = $mesSel > 0 ? $mesSel : (int) date('n');
        $data['mapas']       = self::MAPAS;

        return view('dashboards/informesGerenciales', $data);
    }

    public function fincas(): ResponseInterface
    {
        $acceso = $this->acceso();

        if ($acceso !== null) {
            return $acceso;
        }

        $empresa = (int) $this->request->getPost('empresa');

        if ($empresa <= 0 || ! $this->model->empresaExiste($empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        return $this->response->setJSON(['success' => true, 'message' => '', 'fincas' => $this->model->getFincas($empresa)]);
    }

    public function consultar(): ResponseInterface
    {
        $acceso = $this->acceso();

        if ($acceso !== null) {
            return $acceso;
        }

        return match (trim((string) $this->request->getPost('informe'))) {
            'presupuesto-ejecutado' => $this->presupuestoEjecutado(),
            'ciclos-cosecha'        => $this->ciclos('ciclos-cosecha'),
            'ciclos-poda'           => $this->ciclos('ciclos-poda'),
            default                 => $this->error('El informe solicitado no existe.', 404),
        };
    }

    private function presupuestoEjecutado(): ResponseInterface
    {
        $empresa = (int) $this->request->getPost('empresa');
        $anio   = (int) $this->request->getPost('anio');
        $mes     = (int) $this->request->getPost('mes');
        $finca   = trim((string) $this->request->getPost('finca'));

        if ($empresa <= 0 || ! $this->model->empresaExiste($empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        if ($anio < 2000 || $anio > 2100) {
            return $this->error('Debe seleccionar un año válido.');
        }

        if ($mes < 1 || $mes > 12) {
            return $this->error('Debe seleccionar un mes de corte válido.');
        }

        if ($finca !== '' && ! in_array($finca, array_column($this->model->getFincas($empresa), 'codigo'), true)) {
            return $this->error('La finca seleccionada no es válida.');
        }

        try {
            $existe      = $this->model->presupuestoExiste();
            $presupuesto = $this->model->getPresupuesto($empresa, $anio, $finca);
            $ejecutado   = $this->model->getEjecutado($empresa, $anio, $finca);
            $nombre      = '';

            foreach ($this->model->getEmpresas() as $e) {
                if ((int) $e['id'] === $empresa) {
                    $nombre = trim($e['razonSocial']);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el informe presupuesto-ejecutado: ' . $e->getMessage());

            return $this->error('No se pudo consultar el informe.', 500);
        }

        $fincas = [];

        foreach ($presupuesto as $r) {
            $f = &$fincas[$r['finca']];
            $f['nombre'] = $r['fincaNombre'];

            if ($r['lote'] === '') {
                $f['p'][(int) $r['mes']] = ($f['p'][(int) $r['mes']] ?? 0) + (float) $r['kilos'];
            } else {
                $f['lotes'][$r['lote']]['nombre'] = $r['loteNombre'];
                $f['lotes'][$r['lote']]['p'][(int) $r['mes']] = ($f['lotes'][$r['lote']]['p'][(int) $r['mes']] ?? 0) + (float) $r['kilos'];
            }

            unset($f);
        }

        foreach ($ejecutado as $r) {
            $f = &$fincas[$r['finca']];
            $f['nombre'] = $r['fincaNombre'];
            $l = &$f['lotes'][$r['lote']];
            $l['nombre'] = $r['loteNombre'];
            $l['f'][(int) $r['mes']] = ($l['f'][(int) $r['mes']] ?? 0) + (float) $r['facturado'];
            $l['l'][(int) $r['mes']] = ($l['l'][(int) $r['mes']] ?? 0) + (float) $r['labores'];

            unset($f, $l);
        }

        ksort($fincas, SORT_STRING);

        $id       = 'e' . $empresa;
        $hijosE   = [];
        $totE     = ['p' => null, 'f' => [], 'l' => []];

        foreach ($fincas as $codF => $f) {
            $idF    = $id . '-f' . $codF;
            $totF   = ['p' => isset($f['p']) ? $f['p'] : null, 'f' => [], 'l' => []];
            $hijosF = [];
            $lotes  = $f['lotes'] ?? [];

            ksort($lotes, SORT_STRING);

            foreach ($lotes as $codL => $l) {
                $idL    = $idF . '-l' . $codL;
                $p      = $l['p'] ?? null;
                $hijosL = [];

                for ($i = 1; $i <= $mes; $i++) {
                    $hijosL[] = $this->nodo($idL . '-m' . $i, 'mes', $i, self::MESES[$i], $p === null ? null : [$i => $p[$i] ?? 0], [$i => $l['f'][$i] ?? 0], [$i => $l['l'][$i] ?? 0], $mes, null);
                }

                $hijosF[] = $this->nodo($idL, 'lote', (string) $codL, $l['nombre'], $p, $l['f'] ?? [], $l['l'] ?? [], $mes, $hijosL);
                $totF     = $this->sumar($totF, $p, $l['f'] ?? [], $l['l'] ?? []);
            }

            $hijosE[] = $this->nodo($idF, 'finca', (string) $codF, $f['nombre'], $totF['p'], $totF['f'], $totF['l'], $mes, $hijosF);
            $totE     = $this->sumar($totE, $totF['p'], $totF['f'], $totF['l']);
        }

        $raiz   = $this->nodo($id, 'empresa', (string) $empresa, $nombre, $totE['p'], $totE['f'], $totE['l'], $mes, $hijosE);
        $avisos = [];

        if (! $existe) {
            $avisos[] = 'La tabla de presupuesto de producción no existe: solo se muestra lo ejecutado.';
        } elseif ($totE['p'] === null) {
            $avisos[] = 'No hay presupuesto registrado para ' . $anio . ($finca !== '' ? ' en la finca seleccionada' : '') . '.';
        }

        foreach ($raiz['meses'] as $m) {
            if ($m['facturado'] !== null && abs($m['facturado'] - $m['labores']) >= 0.5) {
                $avisos[] = $m['nombre'] . ': facturado ' . number_format($m['facturado'], 0, ',', '.') . ' kg vs labores ' . number_format($m['labores'], 0, ',', '.') . ' kg';
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'avisos'  => $avisos,
            'corte'   => ['anio' => $anio, 'mes' => $mes, 'mesNombre' => self::MESES_NOMBRE[$mes]],
            'kpis'    => [
                'presupuesto'      => $raiz['presupuesto'],
                'presupuestoAnual' => $totE['p'] === null ? null : round(array_sum($totE['p']), 2),
                'facturado'        => $raiz['facturado'],
                'labores'          => $raiz['labores'],
                'diferencia'       => $raiz['diferencia'],
                'cumplimiento'     => $raiz['cumplimiento'],
                'faltante'         => $raiz['faltante'],
            ],
            'meses'   => $raiz['meses'],
            'arbol'   => $hijosE === [] ? [] : [$raiz],
        ]);
    }

    private function ciclos(string $informe): ResponseInterface
    {
        $empresa = (int) $this->request->getPost('empresa');
        $finca   = trim((string) $this->request->getPost('finca'));
        $corte   = $this->request->getPost('corte') ?? '';
        $corte   = is_string($corte) ? trim($corte) : '-';
        $corte   = $corte === '' ? date('Y-m-d') : $corte;
        $fecha   = \DateTime::createFromFormat('!Y-m-d', $corte);

        if ($empresa <= 0 || ! $this->model->empresaExiste($empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        if ($fecha === false || $fecha->format('Y-m-d') !== $corte) {
            return $this->error('Debe indicar una fecha de corte válida.');
        }

        if ($corte > date('Y-m-d')) {
            return $this->error('La fecha de corte no puede ser futura.');
        }

        $fincas = array_column($this->model->getFincas($empresa), 'nombre', 'codigo');

        if ($finca === '' || ! array_key_exists($finca, $fincas)) {
            return $this->error('Debe seleccionar una finca válida.');
        }

        try {
            return $this->response->setJSON($this->datosCiclos($informe, $empresa, $finca, (string) $fincas[$finca], $corte));
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el informe ' . $informe . ': ' . $e->getMessage());

            return $this->error('No se pudo consultar el informe.', 500);
        }
    }

    private function datosCiclos(string $informe, int $empresa, string $finca, string $nombre, string $corte): array
    {
        $poda   = $informe === 'ciclos-poda';
        $dia    = static fn (string $f) => intdiv(strtotime($f . ' 00:00:00 UTC'), 86400);
        $fechaD = static fn (int $d) => gmdate('Y-m-d', $d * 86400);
        $hoy    = $dia($corte);
        $brecha = $poda ? 7 : 1;
        $rangos = $poda ? [
            ['clave' => 'vencido', 'etiqueta' => 'Vencido', 'desde' => null, 'hasta' => -1, 'color' => '#FF0000'],
            ['clave' => 'proximo', 'etiqueta' => 'Próximo (0 a 45 días)', 'desde' => 0, 'hasta' => 45, 'color' => '#FF9900'],
            ['clave' => 'intermedio', 'etiqueta' => 'Intermedio (46 a 90 días)', 'desde' => 46, 'hasta' => 90, 'color' => '#FFD600'],
            ['clave' => 'al-dia', 'etiqueta' => 'Al día (más de 90 días)', 'desde' => 91, 'hasta' => null, 'color' => '#00B050'],
        ] : [
            ['clave' => 'seguimiento', 'etiqueta' => 'En seguimiento (menos de 10 días)', 'desde' => 0, 'hasta' => 9, 'color' => '#0d6efd'],
            ['clave' => 'normal', 'etiqueta' => 'Próximo a cosechar (10 a 12 días)', 'desde' => 10, 'hasta' => 12, 'color' => '#00B050'],
            ['clave' => 'alerta', 'etiqueta' => 'Listo para cosechar (13 a 15 días)', 'desde' => 13, 'hasta' => 15, 'color' => '#FF9900'],
            ['clave' => 'sobremaduracion', 'etiqueta' => 'Sobremaduración (más de 15 días)', 'desde' => 16, 'hasta' => null, 'color' => '#FF0000'],
        ];
        $rangos[] = ['clave' => 'sin-dato', 'etiqueta' => 'Sin registro', 'desde' => null, 'hasta' => null, 'color' => '#ADB5BD'];

        $fechas = [];

        foreach ($this->model->getFechasLabor($empresa, $finca, $poda ? 'TLA' : 'TLC', $fechaD($hoy - ($poda ? 730 : 400)), $corte) as $r) {
            $actual = $fechas[$r['lote']][$r['fecha']] ?? null;

            if ($actual === null || (int) $r['ciclos'] > (int) $actual['ciclos']) {
                $fechas[$r['lote']][$r['fecha']] = $r;
            }
        }

        $porEstado = array_fill_keys(array_column($rangos, 'clave'), 0);
        $lotes     = [];
        $hectareas = 0;

        foreach ($this->model->getLotesCiclo($empresa, $finca) as $l) {
            $dias   = array_keys($fechas[$l['codigo']] ?? []);
            $ciclo  = $poda ? 180 : 10;
            $item   = ['codigo' => $l['codigo'], 'descripcion' => $l['descripcion'], 'hectareas' => round((float) $l['hectareas'], 2), 'inicio' => '', 'fin' => '', 'diasLabor' => null,
                'ciclo' => $ciclo, 'proximo' => '', 'diasTranscurridos' => null, 'diasParaIngreso' => null, 'estado' => 'sin-dato', 'labor' => ''];

            if ($dias !== []) {
                $fin    = $dia(end($dias));
                $inicio = $fin;

                for ($i = count($dias) - 2; $i >= 0 && $inicio - $dia($dias[$i]) <= $brecha; $i--) {
                    $inicio = $dia($dias[$i]);
                }

                $ultima = $fechas[$l['codigo']][$fechaD($fin)];
                $ciclo  = $poda && (int) $ultima['ciclos'] > 0 ? (int) $ultima['ciclos'] : $ciclo;
                $trans  = $hoy - $fin;
                $para   = $fin + $ciclo - $hoy;

                $item = array_merge($item, [
                    'inicio'            => $fechaD($inicio),
                    'fin'               => $fechaD($fin),
                    'diasLabor'         => $fin - $inicio + 1,
                    'ciclo'             => $ciclo,
                    'proximo'           => $fechaD($fin + $ciclo),
                    'diasTranscurridos' => $trans,
                    'diasParaIngreso'   => $para,
                    'estado'            => $poda
                        ? ($para < 0 ? 'vencido' : ($para <= 45 ? 'proximo' : ($para <= 90 ? 'intermedio' : 'al-dia')))
                        : ($trans < 10 ? 'seguimiento' : ($trans <= 12 ? 'normal' : ($trans <= 15 ? 'alerta' : 'sobremaduracion'))),
                    'labor'             => $ultima['labor'] . ' — ' . $ultima['laborNombre'],
                ]);
            }

            $porEstado[$item['estado']]++;
            $hectareas += $item['hectareas'];
            $lotes[]    = $item;
        }

        $mapa    = self::MAPAS[$empresa][$finca] ?? null;
        $sinLote = [];

        if ($mapa !== null) {
            preg_match_all('/ name="([^"]+)"/', (string) file_get_contents(FCPATH . 'public/assets/mapas/' . $mapa . '.svg'), $m);
            $sinLote = array_values(array_diff($m[1], array_column($lotes, 'codigo')));
        }

        return [
            'success' => true,
            'message' => '',
            'informe' => $informe,
            'corte'   => $corte,
            'finca'   => ['codigo' => $finca, 'descripcion' => $nombre],
            'mapa'    => $mapa,
            'mapaUrl' => $mapa === null ? null : base_url('public/assets/mapas/' . $mapa . '.svg'),
            'rangos'  => $rangos,
            'kpis'    => ['lotes' => count($lotes), 'hectareas' => round($hectareas, 2), 'porEstado' => $porEstado],
            'lotes'   => $lotes,
            'sinLote' => $sinLote,
            'geo'     => $this->geoFinca($empresa, $finca),
        ];
    }

    public function palmas(): ResponseInterface
    {
        $acceso = $this->acceso();

        if ($acceso !== null) {
            return $acceso;
        }

        if (! $this->mapeoModel->tablasExisten()) {
            return $this->error('Las tablas del mapeo de palmas no existen. Ejecute crear_mapeo_palmas.sql.', 503);
        }

        $empresa = (int) $this->request->getPost('empresa');
        $finca   = trim((string) $this->request->getPost('finca'));
        $lote    = trim((string) $this->request->getPost('lote'));

        if ($empresa <= 0 || ! $this->model->empresaExiste($empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        try {
            if ($this->mapeoModel->lote($empresa, $finca, $lote) === null) {
                return $this->error('El lote no existe en la finca seleccionada.');
            }

            $datos = $this->mapeoModel->palmasCompactas($empresa, $finca, $lote);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando palmas del informe: ' . $e->getMessage());

            return $this->error('No se pudieron consultar las palmas.', 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => '', 'lote' => $lote, 'total' => count($datos['palmas'])] + $datos);
    }

    private function geoFinca(int $empresa, string $finca): ?array
    {
        $filas = $this->mapeoModel->tablasExisten() ? $this->mapeoModel->geometrias($empresa, $finca) : [];

        if ($filas === []) {
            return null;
        }

        $features = [];
        $area     = 0.0;
        $lat      = 0.0;
        $lon      = 0.0;

        foreach ($filas as $g) {
            $peso       = max((float) $g['areaHa'], 0.0001);
            $area      += $peso;
            $lat       += (float) $g['centroLat'] * $peso;
            $lon       += (float) $g['centroLon'] * $peso;
            $features[] = [
                'type'       => 'Feature',
                'properties' => [
                    'codigo'      => $g['codigo'],
                    'descripcion' => $g['descripcion'],
                    'areaHa'      => (float) $g['areaHa'],
                    'centro'      => [(float) $g['centroLat'], (float) $g['centroLon']],
                ],
                'geometry'   => json_decode($g['geojson'], true),
            ];
        }

        return [
            'lotes'  => ['type' => 'FeatureCollection', 'features' => $features],
            'centro' => [round($lat / $area, 7), round($lon / $area, 7)],
        ];
    }

    private function sumar(array $tot, ?array $p, array $f, array $l): array
    {
        foreach ($p ?? [] as $i => $v) {
            $tot['p'][$i] = ($tot['p'][$i] ?? 0) + $v;
        }

        foreach ($f as $i => $v) {
            $tot['f'][$i] = ($tot['f'][$i] ?? 0) + $v;
        }

        foreach ($l as $i => $v) {
            $tot['l'][$i] = ($tot['l'][$i] ?? 0) + $v;
        }

        return $tot;
    }

    private function nodo(string $id, string $nivel, int|string $codigo, string $nombre, ?array $p, array $f, array $l, int $corte, ?array $hijos): array
    {
        $acum = static fn (array $v) => array_sum(array_filter($v, static fn ($i) => $i <= $corte, ARRAY_FILTER_USE_KEY));
        $pres = $p === null ? null : $acum($p);
        $fact = $acum($f);
        $lab  = $acum($l);

        $nodo = [
            'id'           => $id,
            'nivel'        => $nivel,
            'codigo'       => $codigo,
            'nombre'       => $nombre,
            'presupuesto'  => $pres === null ? null : round($pres, 2),
            'facturado'    => round($fact, 2),
            'labores'      => round($lab, 2),
            'diferencia'   => round($fact - $lab, 2),
            'cumplimiento' => $pres > 0 ? round($fact / $pres, 4) : null,
            'faltante'     => $p === null ? null : round(array_sum($p) - $fact, 2),
        ];

        if ($hijos === null) {
            return $nodo + ['hijos' => []];
        }

        $nodo['meses'] = [];

        for ($i = 1; $i <= 12; $i++) {
            $nodo['meses'][] = [
                'mes'         => $i,
                'nombre'      => self::MESES[$i],
                'presupuesto' => $p === null ? null : round($p[$i] ?? 0, 2),
                'facturado'   => $i > $corte ? null : round($f[$i] ?? 0, 2),
                'labores'     => $i > $corte ? null : round($l[$i] ?? 0, 2),
            ];
        }

        return $nodo + ['hijos' => $hijos];
    }

    private function acceso(): ?ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->error('Sesión expirada. Vuelva a iniciar sesión.', 401);
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::RUTA)) {
            return $this->error('No tiene permiso para consultar los informes gerenciales.', 403);
        }

        return null;
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON(['success' => false, 'message' => $mensaje])->setStatusCode($codigo);
    }
}
