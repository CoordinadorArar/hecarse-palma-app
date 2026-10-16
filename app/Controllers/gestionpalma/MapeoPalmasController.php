<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\MapeoPalmasModel;
use App\Models\ReportesModel;

class MapeoPalmasController extends GestionPalmaController
{
    private const RUTA = 'gestion-palma/lotes/mapa';

    private const SIN_TABLAS = 'Las tablas del mapeo de palmas no existen. Ejecute crear_mapeo_palmas.sql.';

    private MapeoPalmasModel $mapeoModel;
    private ReportesModel $reportesModel;

    public function __construct()
    {
        parent::__construct();

        $this->mapeoModel    = new MapeoPalmasModel();
        $this->reportesModel = new ReportesModel();
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

        $empresas   = $this->mapeoModel->getEmpresas();
        $empresaSel = $this->empresaSeleccionada($empresas);

        $data['title']          = 'Gestión Palma · Mapeo de palmas';
        $data['empresas']       = $empresas;
        $data['empresa_sel']    = $empresaSel;
        $data['fincas']         = $empresaSel > 0 ? $this->mapeoModel->getFincas($empresaSel) : [];
        $data['tablas_existen'] = $this->mapeoModel->tablasExisten();
        $data['mensaje_tablas'] = self::SIN_TABLAS;

        return view('gestionpalma/lotes_mapa', $data);
    }

    public function fincas(): ResponseInterface
    {
        $empresa = $this->base(false);

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        return $this->response->setJSON(['success' => true, 'message' => '', 'fincas' => $this->mapeoModel->getFincas($empresa)]);
    }

    public function lotes(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $finca = $this->campo('finca');

        if (! $this->mapeoModel->fincaExiste($empresa, $finca)) {
            return $this->error('Debe seleccionar una finca válida.');
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'lotes'   => array_map(static fn ($l) => [
                'codigo'       => $l['codigo'],
                'descripcion'  => $l['descripcion'],
                'activo'       => (int) $l['activo'] === 1,
                'palmasBrutas' => (int) $l['palmasBrutas'],
                'palmas'       => (int) $l['palmas'],
                'lineas'       => (int) $l['lineas'],
                'geojson'      => $l['geojson'] === null ? null : json_decode($l['geojson'], true),
                'areaHa'       => $l['areaHa'] === null ? null : (float) $l['areaHa'],
                'centro'       => $l['centroLat'] === null ? null : [(float) $l['centroLat'], (float) $l['centroLon']],
            ], $this->mapeoModel->lotes($empresa, $finca)),
        ]);
    }

    public function palmas(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $finca = $this->campo('finca');
        $lote  = $this->campo('lote');

        if ($this->mapeoModel->lote($empresa, $finca, $lote) === null) {
            return $this->error('El lote no existe en la finca seleccionada.');
        }

        $datos = $this->mapeoModel->palmasCompactas($empresa, $finca, $lote);

        return $this->response->setJSON(['success' => true, 'message' => '', 'lote' => $lote, 'total' => count($datos['palmas'])] + $datos);
    }

    public function guardarPalma(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $id       = $this->campo('id');
        $anterior = null;

        if ($id !== '') {
            $anterior = ctype_digit($id) ? $this->mapeoModel->obtenerPalma((int) $id) : null;

            if ($anterior === null || (int) $anterior['empresa'] !== $empresa) {
                return $this->error('La palma no existe.', 404);
            }
        }

        $finca = $this->campo('finca');
        $lote  = $this->campo('lote');
        $info  = $this->mapeoModel->lote($empresa, $finca, $lote);

        if ($info === null) {
            return $this->error('El lote no existe en la finca seleccionada.');
        }

        if ((int) $info['activo'] !== 1) {
            return $this->error('El lote está inactivo.');
        }

        $linea = $this->campo('linea');
        $palma = $this->campo('palma');

        if (! ctype_digit($linea) || (int) $linea < 1 || ! ctype_digit($palma) || (int) $palma < 1) {
            return $this->error('La línea y el número de palma deben ser enteros mayores a cero.');
        }

        $coordenadas = $this->coordenadas();

        if (is_string($coordenadas)) {
            return $this->error($coordenadas);
        }

        $estado = mb_strtoupper($this->campo('estado') ?: 'ACTIVA');

        if (! in_array($estado, ['ACTIVA', 'ERRADICADA'], true)) {
            return $this->error('El estado debe ser ACTIVA o ERRADICADA.');
        }

        $fechaErradicacion = null;

        if ($estado === 'ERRADICADA') {
            $fechaErradicacion = $this->fechaValida($this->campo('fechaErradicacion'));

            if ($fechaErradicacion === null) {
                return $this->error('Indique una fecha de erradicación válida, no posterior a hoy.');
            }
        }

        $observacion = $this->campo('observacion');

        if (mb_strlen($observacion) > 250) {
            return $this->error('La observación no puede superar los 250 caracteres.');
        }

        if ($this->mapeoModel->posicionOcupada($empresa, $finca, $lote, (int) $linea, (int) $palma, $anterior === null ? null : (int) $id)) {
            return $this->error("Ya existe la palma {$linea}-{$palma} en el lote {$lote}.");
        }

        $data = [
            'empresa'           => $empresa,
            'finca'             => $finca,
            'lote'              => $lote,
            'linea'             => (int) $linea,
            'palma'             => (int) $palma,
            'latitud'           => $coordenadas[0],
            'longitud'          => $coordenadas[1],
            'estado'            => $estado,
            'fechaErradicacion' => $fechaErradicacion,
            'observacion'       => $observacion === '' ? null : $observacion,
        ];

        try {
            if ($anterior === null) {
                $id = $this->mapeoModel->crearPalma($data, $this->usuarioRegistro(), $this->usuId());
                $ok = $id > 0;
            } else {
                $id = (int) $id;
                $ok = $this->mapeoModel->actualizarPalma($id, $data, $this->usuarioRegistro(), $this->usuId());
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error guardando palma: ' . $e->getMessage());
            $ok = false;
        }

        if (! $ok) {
            return $this->error('No se pudo guardar la palma. La operación fue revertida.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $anterior === null ? 'Palma creada correctamente.' : 'Palma actualizada correctamente.',
            'aviso'   => $this->avisoPoligono($info['geojson'], $coordenadas, $lote),
            'palma'   => $this->mapeoModel->palmaCompacta((int) $id),
        ]);
    }

    public function moverPalma(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $palma = $this->palmaDeEmpresa($empresa);

        if ($palma instanceof ResponseInterface) {
            return $palma;
        }

        $coordenadas = $this->coordenadas();

        if (is_string($coordenadas)) {
            return $this->error($coordenadas);
        }

        try {
            $ok = $this->mapeoModel->moverPalma((int) $palma['id'], $coordenadas[0], $coordenadas[1], $this->usuarioRegistro(), $this->usuId());
        } catch (\Throwable $e) {
            log_message('error', 'Error moviendo palma: ' . $e->getMessage());
            $ok = false;
        }

        if (! $ok) {
            return $this->error('No se pudo mover la palma. La operación fue revertida.', 500);
        }

        $info = $this->mapeoModel->lote($empresa, $palma['finca'], $palma['lote']);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Ubicación actualizada correctamente.',
            'aviso'   => $this->avisoPoligono($info['geojson'] ?? null, $coordenadas, $palma['lote']),
            'palma'   => $this->mapeoModel->palmaCompacta((int) $palma['id']),
        ]);
    }

    public function eliminarPalma(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $palma = $this->palmaDeEmpresa($empresa);

        if ($palma instanceof ResponseInterface) {
            return $palma;
        }

        try {
            $ok = $this->mapeoModel->eliminarPalma((int) $palma['id'], $this->usuId());
        } catch (\Throwable $e) {
            log_message('error', 'Error eliminando palma: ' . $e->getMessage());
            $ok = false;
        }

        if (! $ok) {
            return $this->error('No se pudo eliminar la palma. La operación fue revertida.', 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Palma eliminada correctamente.', 'id' => (int) $palma['id']]);
    }

    public function siguienteNumero(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $finca = $this->campo('finca');
        $lote  = $this->campo('lote');
        $linea = $this->campo('linea');

        if ($this->mapeoModel->lote($empresa, $finca, $lote) === null) {
            return $this->error('El lote no existe en la finca seleccionada.');
        }

        if (! ctype_digit($linea) || (int) $linea < 1) {
            return $this->error('La línea debe ser un entero mayor a cero.');
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => '',
            'siguiente' => $this->mapeoModel->siguienteNumero($empresa, $finca, $lote, (int) $linea),
        ]);
    }

    public function guardarGeometria(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $finca = $this->campo('finca');
        $lote  = $this->campo('lote');

        if ($this->mapeoModel->lote($empresa, $finca, $lote) === null) {
            return $this->error('El lote no existe en la finca seleccionada.');
        }

        $geo = $this->geometriaValida((string) $this->request->getPost('geojson'));

        if (is_string($geo)) {
            return $this->error($geo);
        }

        [$areaHa, $lat, $lon] = $this->medidas($geo);

        if ($areaHa <= 0) {
            return $this->error('El polígono no tiene área.');
        }

        $json = json_encode($geo);

        try {
            $ok = $this->mapeoModel->guardarGeometria($empresa, $finca, $lote, $json, $areaHa, $this->n7($lat), $this->n7($lon), $this->usuarioRegistro(), $this->usuId());
        } catch (\Throwable $e) {
            log_message('error', 'Error guardando geometría: ' . $e->getMessage());
            $ok = false;
        }

        if (! $ok) {
            return $this->error('No se pudo guardar el polígono. La operación fue revertida.', 500);
        }

        $fuera = 0;

        foreach ($this->mapeoModel->palmasCompactas($empresa, $finca, $lote)['palmas'] as $p) {
            if ($p[5] === 'ACTIVA' && ! $this->puntoEnGeometria($p[4], $p[3], $geo)) {
                $fuera++;
            }
        }

        return $this->response->setJSON([
            'success'         => true,
            'message'         => 'Polígono del lote guardado correctamente.',
            'aviso'           => $fuera > 0 ? "{$fuera} palma(s) activa(s) del lote quedan fuera del polígono." : null,
            'palmasFuera'     => $fuera,
            'geojson'         => $geo,
            'areaHa'          => $areaHa,
            'centro'          => [round($lat, 7), round($lon, 7)],
        ]);
    }

    public function catalogoCenso(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        return $this->response->setJSON([
            'success'         => true,
            'message'         => '',
            'caracteristicas' => array_map(static fn ($c) => [
                'grupo'            => (int) $c['grupo'],
                'grupoDescripcion' => $c['grupoDescripcion'],
                'codigo'           => (int) $c['codigo'],
                'descripcion'      => $c['descripcion'],
            ], $this->mapeoModel->catalogoCenso($empresa)),
        ]);
    }

    public function registrarCenso(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $palma = $this->palmaDeEmpresa($empresa, 'palma');

        if ($palma instanceof ResponseInterface) {
            return $palma;
        }

        $fecha = $this->fechaValida($this->campo('fecha'));

        if ($fecha === null) {
            return $this->error('Indique una fecha de censo válida, no posterior a hoy.');
        }

        $codigo         = $this->campo('caracteristica');
        $caracteristica = ctype_digit($codigo) ? $this->mapeoModel->caracteristicaCenso($empresa, (int) $codigo) : null;

        if ($caracteristica === null) {
            return $this->error('Seleccione una característica de censo válida.');
        }

        $observacion = $this->campo('observacion');

        if (mb_strlen($observacion) > 250) {
            return $this->error('La observación no puede superar los 250 caracteres.');
        }

        try {
            $id = $this->mapeoModel->registrarCenso(
                $empresa, (int) $palma['id'], $fecha, (int) $caracteristica['grupo'], (int) $caracteristica['codigo'],
                $observacion === '' ? null : $observacion, $this->usuarioRegistro(), $this->usuId()
            );
        } catch (\Throwable $e) {
            log_message('error', 'Error registrando censo: ' . $e->getMessage());
            $id = 0;
        }

        if ($id <= 0) {
            return $this->error('No se pudo registrar el censo. La operación fue revertida.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Censo registrado correctamente.',
            'id'      => $id,
            'palma'   => $this->mapeoModel->palmaCompacta((int) $palma['id']),
        ]);
    }

    public function historialCenso(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $palma = $this->palmaDeEmpresa($empresa, 'palma');

        if ($palma instanceof ResponseInterface) {
            return $palma;
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => '',
            'palma'     => $this->mapeoModel->palmaCompacta((int) $palma['id']),
            'detalle'   => array_intersect_key($palma, array_flip(['observacion', 'fechaErradicacion', 'usuarioRegistro', 'fechaRegistro', 'usuarioModificacion', 'fechaModificacion'])),
            'historial' => array_map(static fn ($h) => [
                'id'               => (int) $h['id'],
                'fecha'            => $h['fecha'],
                'grupo'            => (int) $h['grupo'],
                'grupoDescripcion' => $h['grupoDescripcion'],
                'caracteristica'   => (int) $h['caracteristica'],
                'descripcion'      => $h['descripcion'],
                'observacion'      => $h['observacion'],
                'usuario'          => $h['usuario'],
                'fechaRegistro'    => $h['fechaRegistro'],
            ], $this->mapeoModel->historialCenso((int) $palma['id'])),
        ]);
    }

    public function eliminarCenso(): ResponseInterface
    {
        $empresa = $this->base();

        if ($empresa instanceof ResponseInterface) {
            return $empresa;
        }

        $id    = $this->campo('id');
        $censo = ctype_digit($id) ? $this->mapeoModel->obtenerCenso((int) $id) : null;

        if ($censo === null || (int) $censo['empresa'] !== $empresa) {
            return $this->error('El censo no existe.', 404);
        }

        try {
            $ok = $this->mapeoModel->eliminarCenso((int) $id, $this->usuId());
        } catch (\Throwable $e) {
            log_message('error', 'Error eliminando censo: ' . $e->getMessage());
            $ok = false;
        }

        if (! $ok) {
            return $this->error('No se pudo eliminar el censo. La operación fue revertida.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Censo eliminado correctamente.',
            'palma'   => $this->mapeoModel->palmaCompacta((int) $censo['palma']),
        ]);
    }

    private function base(bool $tablas = true): int|ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->error('Sesión expirada. Vuelva a iniciar sesión.', 401);
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::RUTA)) {
            return $this->error('No tiene permiso para gestionar el mapeo de palmas.', 403);
        }

        if ($tablas && ! $this->mapeoModel->tablasExisten()) {
            return $this->error(self::SIN_TABLAS, 503);
        }

        $empresa = $this->campo('empresa');

        if (! ctype_digit($empresa) || ! $this->mapeoModel->empresaExiste((int) $empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        return (int) $empresa;
    }

    private function palmaDeEmpresa(int $empresa, string $campo = 'id'): array|ResponseInterface
    {
        $id    = $this->campo($campo);
        $palma = ctype_digit($id) ? $this->mapeoModel->obtenerPalma((int) $id) : null;

        if ($palma === null || (int) $palma['empresa'] !== $empresa) {
            return $this->error('La palma no existe.', 404);
        }

        return $palma;
    }

    private function coordenadas(): array|string
    {
        $lat = str_replace(',', '.', $this->campo('lat'));
        $lon = str_replace(',', '.', $this->campo('lon'));

        if (! is_numeric($lat) || ! is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
            return 'Las coordenadas no son válidas: latitud entre -90 y 90, longitud entre -180 y 180.';
        }

        return [$this->n7((float) $lat), $this->n7((float) $lon)];
    }

    private function fechaValida(string $fecha): ?string
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha && $fecha <= date('Y-m-d') ? $fecha : null;
    }

    private function avisoPoligono(?string $geojson, array $coordenadas, string $lote): ?string
    {
        $geo = $geojson === null ? null : json_decode($geojson, true);

        if (! is_array($geo) || $this->puntoEnGeometria((float) $coordenadas[1], (float) $coordenadas[0], $geo)) {
            return null;
        }

        return "La coordenada queda fuera del polígono del lote {$lote}.";
    }

    private function geometriaValida(string $texto): array|string
    {
        $geo = json_decode($texto, true);

        if (is_array($geo) && ($geo['type'] ?? '') === 'FeatureCollection' && count($geo['features'] ?? []) === 1) {
            $geo = $geo['features'][0];
        }

        if (is_array($geo) && ($geo['type'] ?? '') === 'Feature') {
            $geo = $geo['geometry'] ?? null;
        }

        if (! is_array($geo) || ! in_array($geo['type'] ?? '', ['Polygon', 'MultiPolygon'], true) || ! is_array($geo['coordinates'] ?? null)) {
            return 'La geometría debe ser un GeoJSON Polygon o MultiPolygon.';
        }

        $poligonos = $geo['type'] === 'Polygon' ? [$geo['coordinates']] : $geo['coordinates'];
        $limpios   = [];
        $vertices  = 0;

        foreach ($poligonos as $pol) {
            if (! is_array($pol) || $pol === []) {
                return 'El polígono no tiene anillos.';
            }

            $anillos = [];

            foreach ($pol as $anillo) {
                if (! is_array($anillo)) {
                    return 'Anillo de polígono inválido.';
                }

                $puntos = [];

                foreach ($anillo as $p) {
                    if (! is_array($p) || ! isset($p[0], $p[1]) || ! is_numeric($p[0]) || ! is_numeric($p[1]) || abs((float) $p[0]) > 180 || abs((float) $p[1]) > 90) {
                        return 'El polígono tiene coordenadas fuera de rango (longitud, latitud).';
                    }

                    $puntos[] = [round((float) $p[0], 7), round((float) $p[1], 7)];
                }

                if ($puntos !== [] && $puntos[0] !== end($puntos)) {
                    $puntos[] = $puntos[0];
                }

                if (count($puntos) < 4) {
                    return 'Cada anillo del polígono debe tener al menos 3 vértices.';
                }

                $vertices += count($puntos);
                $anillos[] = $puntos;
            }

            $limpios[] = $anillos;
        }

        if ($vertices > 20000) {
            return 'El polígono supera el máximo de 20.000 vértices.';
        }

        return $geo['type'] === 'Polygon'
            ? ['type' => 'Polygon', 'coordinates' => $limpios[0]]
            : ['type' => 'MultiPolygon', 'coordinates' => $limpios];
    }

    private function medidas(array $geo): array
    {
        $poligonos = $geo['type'] === 'Polygon' ? [$geo['coordinates']] : $geo['coordinates'];
        $lats      = array_merge(...array_map(static fn ($pol) => array_column($pol[0], 1), $poligonos));
        $s2        = sin(deg2rad(array_sum($lats) / count($lats))) ** 2;
        $e2        = 0.00669437999014;
        $ky        = deg2rad(1) * 6378137 * (1 - $e2) / (1 - $e2 * $s2) ** 1.5;
        $kx        = deg2rad(1) * 6378137 / sqrt(1 - $e2 * $s2) * sqrt(1 - $s2);
        $area      = 0.0;
        $sx        = 0.0;
        $sy        = 0.0;
        $sw        = 0.0;

        foreach ($poligonos as $pol) {
            foreach ($pol as $k => $r) {
                $a = 0.0;
                $x = 0.0;
                $y = 0.0;

                for ($i = 0, $n = count($r); $i < $n - 1; $i++) {
                    $c  = $r[$i][0] * $r[$i + 1][1] - $r[$i + 1][0] * $r[$i][1];
                    $a += $c;
                    $x += ($r[$i][0] + $r[$i + 1][0]) * $c;
                    $y += ($r[$i][1] + $r[$i + 1][1]) * $c;
                }

                if ($a == 0.0) {
                    continue;
                }

                $signo = $k === 0 ? 1 : -1;
                $w     = abs($a / 2);
                $area += $signo * $w * $kx * $ky;
                $sx   += $signo * $x / (3 * $a) * $w;
                $sy   += $signo * $y / (3 * $a) * $w;
                $sw   += $signo * $w;
            }
        }

        if ($sw <= 0) {
            return [0.0, 0.0, 0.0];
        }

        return [round($area / 10000, 2), $sy / $sw, $sx / $sw];
    }

    private function puntoEnGeometria(float $lon, float $lat, array $geo): bool
    {
        $dentro = static function (array $r) use ($lon, $lat): bool {
            $res = false;

            for ($i = 0, $j = count($r) - 1, $n = count($r); $i < $n; $j = $i++) {
                if ((($r[$i][1] > $lat) !== ($r[$j][1] > $lat))
                    && $lon < ($r[$j][0] - $r[$i][0]) * ($lat - $r[$i][1]) / ($r[$j][1] - $r[$i][1]) + $r[$i][0]) {
                    $res = ! $res;
                }
            }

            return $res;
        };

        foreach ($geo['type'] === 'Polygon' ? [$geo['coordinates']] : ($geo['coordinates'] ?? []) as $pol) {
            if ($dentro($pol[0])) {
                foreach (array_slice($pol, 1) as $hueco) {
                    if ($dentro($hueco)) {
                        continue 2;
                    }
                }

                return true;
            }
        }

        return false;
    }

    private function n7(float $valor): string
    {
        return number_format($valor, 7, '.', '');
    }

    private function usuId(): ?int
    {
        $id = $this->session->get('usu_id');

        return $id === null ? null : (int) $id;
    }

    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : mb_substr($usuario, 0, 50);
    }

    private function campo(string $nombre): string
    {
        $valor = $this->request->getPost($nombre);

        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON(['success' => false, 'message' => $mensaje])->setStatusCode($codigo);
    }
}
