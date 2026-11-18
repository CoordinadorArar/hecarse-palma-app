<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\UnidadesNegocioModel;

class UnidadesNegocioController extends GestionPalmaController
{
    private const SIN_TABLAS = 'Falta ejecutar el script crear_unidades_negocio.sql';

    private $unidadesModel;

    public function __construct()
    {
        parent::__construct();

        $this->unidadesModel = new UnidadesNegocioModel();
    }

    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->unidadesModel->getEmpresas();
        $empresaSel = $this->empresaSeleccionada($empresas);
        $disponible = $this->unidadesModel->disponible();

        $filas = $disponible && $empresaSel > 0 ? $this->unidadesModel->listar($empresaSel) : [];

        $data['title']              = 'Gestión Palma · Unidades de negocio';
        $data['empresas']           = $empresas;
        $data['empresa_sel']        = $empresaSel;
        $data['fincas']             = $empresaSel > 0 ? $this->unidadesModel->getFincas($empresaSel) : [];
        $data['filas']              = $this->renderizarFilasUnidades($filas);
        $data['total']              = count($filas);
        $data['disponible']         = $disponible;
        $data['mensaje_disponible'] = $disponible ? '' : self::SIN_TABLAS;

        return view('gestionpalma/unidades_negocio', $data);
    }

    public function listar(): ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos()) {
            return $sinRequisitos;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->unidadesModel->listar($empresa, $this->campo('finca'), $this->campo('estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasUnidades($filas),
        ]);
    }

    public function obtener(): ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos()) {
            return $sinRequisitos;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $unidad = $this->unidadesModel->obtener($empresa, $this->campo('codigo'));

        if ($unidad === null) {
            return $this->error('La unidad de negocio no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'unidad'  => [
                'empresa'     => (int) $unidad['empresa'],
                'codigo'      => trim((string) $unidad['codigo']),
                'descripcion' => (string) $unidad['descripcion'],
                'activo'      => (int) $unidad['activo'],
                'fincas'      => array_map(static fn ($f) => [
                    'finca' => trim((string) $f['finca']),
                    'lotes' => (int) $f['lotes'],
                ], $unidad['fincas']),
            ],
        ]);
    }

    public function crear(): ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos()) {
            return $sinRequisitos;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if ($this->unidadesModel->existe($empresa, $codigo)) {
            return $this->error("Ya existe una unidad de negocio con el código {$codigo}.");
        }

        $datos = $this->datosUnidad();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $fincas = $this->fincasValidas($empresa, []);

        if (is_string($fincas)) {
            return $this->error($fincas);
        }

        if (! $this->unidadesModel->crear($empresa, $codigo, $datos, $fincas, $this->session->get('usu_id'), $this->usuarioRegistro())) {
            return $this->error('No se pudo crear la unidad de negocio. La operación fue revertida.');
        }

        return $this->respuestaTabla($empresa, 'Unidad de negocio creada.');
    }

    public function actualizar(): ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos()) {
            return $sinRequisitos;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $unidad = $this->unidadesModel->obtener($empresa, $this->campo('codigo'));

        if ($unidad === null) {
            return $this->error('La unidad de negocio no existe.');
        }

        $datos = $this->datosUnidad();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $fincas = $this->fincasValidas($empresa, $unidad);

        if (is_string($fincas)) {
            return $this->error($fincas);
        }

        if (! $this->unidadesModel->actualizar($empresa, $unidad['codigo'], $datos, $fincas, $this->session->get('usu_id'))) {
            return $this->error('No se pudo actualizar la unidad de negocio. La operación fue revertida.');
        }

        return $this->respuestaTabla($empresa, 'Unidad de negocio actualizada.');
    }

    public function eliminar(): ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos()) {
            return $sinRequisitos;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $unidad = $this->unidadesModel->obtener($empresa, $this->campo('codigo'));

        if ($unidad === null) {
            return $this->error('La unidad de negocio no existe.');
        }

        if ($unidad['fincas'] !== [] || (int) $unidad['lotes'] > 0) {
            return $this->error('No se puede eliminar: la unidad de negocio tiene fincas asignadas o lotes que la usan; desactívela.');
        }

        $this->unidadesModel->eliminar($empresa, $unidad['codigo'], $this->session->get('usu_id'));

        return $this->respuestaTabla($empresa, 'Unidad de negocio eliminada.');
    }

    public function cambiarEstado(): ResponseInterface
    {
        if ($sinRequisitos = $this->requisitos()) {
            return $sinRequisitos;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $unidad = $this->unidadesModel->obtener($empresa, $this->campo('codigo'));

        if ($unidad === null) {
            return $this->error('La unidad de negocio no existe.');
        }

        $this->unidadesModel->cambiarEstado(
            $empresa,
            $unidad['codigo'],
            (int) $unidad['activo'] === 1 ? 0 : 1,
            $this->session->get('usu_id')
        );

        return $this->respuestaTabla($empresa, 'Estado actualizado.');
    }

    private function requisitos(): ?ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Sesión expirada. Vuelva a iniciar sesión.',
            ])->setStatusCode(401);
        }

        return $this->unidadesModel->disponible() ? null : $this->error(self::SIN_TABLAS . '.');
    }

    private function empresaValida(): ?int
    {
        $empresa = $this->request->getPost('empresa');

        if ($empresa === null || ! is_numeric($empresa) || ! $this->unidadesModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
    }

    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : mb_substr($usuario, 0, 50);
    }

    private function codigoValido(): string|array
    {
        $codigo = mb_strtoupper($this->campo('codigo'));

        if ($codigo === '') {
            return ['Ingrese el código de la unidad de negocio.'];
        }

        if (mb_strlen($codigo) > 10) {
            return ['El código admite hasta 10 caracteres.'];
        }

        if (preg_match('/\s/', $codigo)) {
            return ['El código no puede contener espacios.'];
        }

        return $codigo;
    }

    private function datosUnidad(): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'Ingrese la descripción.';
        }

        if (mb_strlen($descripcion) > 100) {
            return 'La descripción admite hasta 100 caracteres.';
        }

        return [
            'descripcion' => $descripcion,
            'activo'      => $this->bit('activo'),
        ];
    }

    private function fincasValidas(int $empresa, array $unidad): array|string
    {
        $recibidas = json_decode((string) $this->request->getPost('fincas'), true);

        if ($recibidas !== null && ! is_array($recibidas)) {
            return 'Las fincas seleccionadas no son válidas.';
        }

        $existentes = array_column($this->unidadesModel->getFincas($empresa), 'descripcion', 'codigo');
        $fincas     = [];

        foreach ($recibidas ?? [] as $finca) {
            $finca = is_scalar($finca) ? trim((string) $finca) : '';

            if (! array_key_exists($finca, $existentes)) {
                return "La finca «{$finca}» no existe en la empresa.";
            }

            $fincas[$finca] = $finca;
        }

        foreach ($unidad['fincas'] ?? [] as $actual) {
            $codigo = trim((string) $actual['finca']);

            if ((int) $actual['lotes'] > 0 && ! isset($fincas[$codigo])) {
                $nombre = trim((string) ($actual['descripcion'] ?? '')) ?: $codigo;

                return "No se puede quitar la finca «{$nombre}»: " . number_format((int) $actual['lotes'], 0, ',', '.')
                    . ((int) $actual['lotes'] === 1 ? ' lote usa' : ' lotes usan')
                    . ' la unidad de negocio «' . trim((string) $unidad['descripcion']) . '».';
            }
        }

        return array_values($fincas);
    }

    private function respuestaTabla(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->unidadesModel->listar($empresa, $this->campo('filtro_finca'), $this->campo('filtro_estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasUnidades($filas),
        ]);
    }

    private function renderizarFilasUnidades(array $unidades): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        foreach ($unidades as $u) {
            $empresa     = (int) $u['empresa'];
            $codigo      = trim((string) $u['codigo']);
            $descripcion = trim((string) $u['descripcion']);
            $activo      = (int) $u['activo'] === 1 ? 1 : 0;
            $lotes       = (int) ($u['lotes'] ?? 0);
            $fincas      = $u['fincas'] ?? [];
            $enUso       = $fincas !== [] || $lotes > 0;

            $nombres = array_map(
                static fn ($f) => trim((string) ($f['descripcion'] ?? '')) ?: trim((string) $f['finca']),
                $fincas
            );

            $chips = '';

            foreach (array_slice($fincas, 0, 4, true) as $i => $f) {
                $chips .= '<span class="gp-chip-fila verde" title="Cód. ' . htmlspecialchars(trim((string) $f['finca'])) . '">'
                    . htmlspecialchars($nombres[$i]) . '</span>';
            }

            if (count($fincas) > 4) {
                $chips .= '<span class="gp-chip-fila gris" title="' . htmlspecialchars(implode(', ', array_slice($nombres, 4))) . '">+'
                    . (count($fincas) - 4) . '</span>';
            }

            if ($fincas === []) {
                $chips = '<span class="gp-chip-fila ambar" title="Asigne fincas editando la unidad de negocio.">Sin fincas</span>';
            }

            $lotesFmt = number_format($lotes, 0, ',', '.');

            $celdaLotes = $lotes > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" title="' . $lotesFmt
                    . ($lotes === 1 ? ' lote usa' : ' lotes usan') . ' esta unidad de negocio.">' . $lotesFmt . '</span>'
                : '<span class="gp-chip-rol">0</span>';

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar unidad de negocio' : 'Activar unidad de negocio';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($codigo) . '&#039;';

            $botonEliminar = $enUso
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarUnidadNegocio(' . $claveJs . ',&#039;' . $aJs($descripcion) . '&#039;)"><i class="bi bi-trash"></i></button>';

            $tituloAcciones = $enUso
                ? ' title="No se puede eliminar: la unidad de negocio tiene fincas asignadas o lotes que la usan; desactívela."'
                : '';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . htmlspecialchars($codigo)
                . '" data-activo="' . $activo . '" data-lotes="' . $lotes . '" data-fincas="' . count($fincas) . '">'
                . '<td class="text-center"><span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span></td>'
                . '<td data-order="' . htmlspecialchars($descripcion) . '">' . htmlspecialchars($descripcion) . '</td>'
                . '<td><div class="d-flex flex-wrap gap-1">' . $chips . '</div></td>'
                . '<td class="text-center" data-order="' . $lotes . '">' . $celdaLotes . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarUnidadNegocio(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoUnidadNegocio(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    private function campo(string $nombre): string
    {
        return trim((string) $this->request->getPost($nombre));
    }

    private function bit(string $nombre): int
    {
        return in_array($this->campo($nombre), ['1', 'on', 'true'], true) ? 1 : 0;
    }

    private function error(string $mensaje): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode(422);
    }
}
