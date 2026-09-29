<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\FincasModel;
use App\Models\TercerosModel;

class FincasController extends GestionPalmaController
{
    private $fincasModel;
    private $tercerosModel;

    public function __construct()
    {
        parent::__construct();

        $this->fincasModel   = new FincasModel();
        $this->tercerosModel = new TercerosModel();
    }

    /**
     * Vista principal del módulo "Fincas".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->fincasModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->fincasModel->listar($empresaSel) : [];

        $data['title']         = 'Gestión Palma · Fincas';
        $data['empresas']      = $empresas;
        $data['propietarios']  = $empresaSel > 0 ? $this->fincasModel->getPropietarios($empresaSel) : [];
        $data['empresa_sel']   = $empresaSel;
        $data['tabla_fincas']  = $this->renderizarFilasFincas($filas);
        $data['total_fincas']  = count($filas);

        return view('gestionpalma/fincas', $data);
    }

    // ── Endpoints JSON ────────────────────────────────────────────────────────

    public function listarFincas(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->fincasModel->listar($empresa, $this->campo('estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasFincas($filas),
        ]);
    }

    public function obtenerFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca = $this->fincasModel->obtener($empresa, $this->campo('codigo'));

        if ($finca === null) {
            return $this->error('La finca no existe.');
        }

        $ciudad = trim((string) $finca['ciudad']);

        return $this->response->setJSON([
            'success' => true,
            'finca'   => [
                'empresa'            => (int) $finca['empresa'],
                'codigo'             => trim((string) $finca['codigo']),
                'descripcion'        => (string) $finca['descripcion'],
                'proveedor'          => $finca['proveedor'] === null ? null : (int) $finca['proveedor'],
                'propietarioNombre'  => (string) ($finca['propietarioNombre'] ?? ''),
                'ciudad'             => $ciudad,
                'nombreCiudad'       => $this->tercerosModel->nombreCiudad($empresa, $ciudad)['nombreCiudad'],
                'hectareas'          => (float) $finca['hectareas'],
                'zonaGeografica'     => (string) $finca['zonaGeografica'],
                'codigoEquivalencia' => (string) $finca['codigoEquivalencia'],
                'centroOperacion'    => (string) $finca['centroOperacion'],
                'interna'            => (int) $finca['interna'],
                'socio'              => (int) $finca['socio'],
                'activo'             => (int) $finca['activo'],
                'usos'               => (int) ($finca['usos'] ?? 0),
            ],
        ]);
    }

    public function crearFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if ($this->fincasModel->existe($empresa, $codigo)) {
            return $this->error("Ya existe una finca con el código {$codigo}.");
        }

        $datos = $this->datosFinca($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->fincasModel->crear($empresa, $codigo, $datos, $this->session->get('usu_id'), $this->usuarioRegistro());

        return $this->respuestaTablaFincas($empresa, 'Finca creada correctamente.');
    }

    public function actualizarFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if (! $this->fincasModel->existe($empresa, $codigo)) {
            return $this->error('La finca no existe.');
        }

        $datos = $this->datosFinca($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->fincasModel->actualizar($empresa, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaFincas($empresa, 'Finca actualizada correctamente.');
    }

    public function eliminarFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');

        if (! $this->fincasModel->existe($empresa, $codigo)) {
            return $this->error('La finca no existe.');
        }

        $enUso = $this->fincasModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error('No se puede eliminar: la finca tiene ' . number_format($enUso, 0, ',', '.') . ' registros asociados.');
        }

        $this->fincasModel->eliminar($empresa, $codigo, $this->session->get('usu_id'));

        return $this->respuestaTablaFincas($empresa, 'Finca eliminada correctamente.');
    }

    public function cambiarEstadoFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->fincasModel->obtener($empresa, $codigo);

        if ($finca === null) {
            return $this->error('La finca no existe.');
        }

        $activo = (int) $finca['activo'] === 1 ? 0 : 1;

        $this->fincasModel->cambiarEstado($empresa, $codigo, $activo, $this->session->get('usu_id'));

        return $this->respuestaTablaFincas(
            $empresa,
            $activo === 1 ? 'Finca activada correctamente.' : 'Finca desactivada correctamente.'
        );
    }

    public function buscarCiudadFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $termino = $this->campo('termino');

        if (mb_strlen($termino) < 2) {
            return $this->response->setJSON(['success' => true, 'ciudades' => [], 'hayMas' => false]);
        }

        $filas  = $this->tercerosModel->buscarCiudades($empresa, $termino, $this->campo('departamento'));
        $hayMas = count($filas) > 15;

        return $this->response->setJSON([
            'success'  => true,
            'ciudades' => array_map(static fn ($c) => [
                'codigo'             => trim((string) $c['codigo']),
                'nombre'             => (string) $c['nombre'],
                'departamento'       => trim((string) $c['departamento']),
                'nombreDepartamento' => (string) ($c['nombreDepartamento'] ?? ''),
            ], array_slice($filas, 0, 15)),
            'hayMas'   => $hayMas,
        ]);
    }

    // ── Utilidades internas ───────────────────────────────────────────────────

    /**
     * Corta la petición con 401 cuando no hay sesión activa.
     */
    private function sesionRequerida(): ?ResponseInterface
    {
        if (! empty($this->session->get('usu_id'))) {
            return null;
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Sesión expirada. Vuelva a iniciar sesión.',
        ])->setStatusCode(401);
    }

    /**
     * Empresa recibida por POST, o null si no es válida.
     */
    private function empresaValida(): ?int
    {
        $empresa = $this->request->getPost('empresa');

        if ($empresa === null || ! is_numeric($empresa) || ! $this->fincasModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
    }

    /**
     * Login que queda en la columna usuarioRegistro.
     */
    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : mb_substr($usuario, 0, 50);
    }

    /**
     * Valida el código recibido por POST.
     *
     * @return string|array El código recortado o [mensaje de error].
     */
    private function codigoValido(): string|array
    {
        $codigo = $this->campo('codigo');

        if ($codigo === '') {
            return ['El código es obligatorio.'];
        }

        if (mb_strlen($codigo) > 50) {
            return ['El código no puede superar los 50 caracteres.'];
        }

        if (preg_match('/\s/', $codigo)) {
            return ['El código no puede contener espacios.'];
        }

        return $codigo;
    }

    /**
     * Valida el formulario de la finca y arma las columnas a persistir.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosFinca(int $empresa): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (mb_strlen($descripcion) > 950) {
            return 'La descripción no puede superar los 950 caracteres.';
        }

        $hectareas = str_replace([' ', '$'], '', $this->campo('hectareas'));

        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $hectareas)) {
            $hectareas = str_replace(['.', ','], '', $hectareas);
        } else {
            $decimal   = strrpos($hectareas, ',') > strrpos($hectareas, '.') ? ',' : '.';
            $hectareas = str_replace($decimal === ',' ? '.' : ',', '', $hectareas);
            $hectareas = str_replace(',', '.', $hectareas);
        }

        if ($hectareas === '') {
            return 'Las hectáreas son obligatorias.';
        }

        if (! is_numeric($hectareas) || (float) $hectareas < 0) {
            return 'Las hectáreas deben ser un número mayor o igual a cero.';
        }

        $proveedor = $this->campo('proveedor');

        if ($proveedor !== '') {
            if (! is_numeric($proveedor) || ! $this->fincasModel->propietarioValido($empresa, (int) $proveedor)) {
                return 'El propietario seleccionado no existe.';
            }
        }

        $ciudad = $this->campo('ciudad');

        if ($ciudad !== '' && $this->tercerosModel->nombreCiudad($empresa, $ciudad)['nombreCiudad'] === '') {
            return 'La ciudad seleccionada no existe.';
        }

        $zonaGeografica = $this->campo('zonaGeografica');

        if (mb_strlen($zonaGeografica) > 550) {
            return 'La zona geográfica no puede superar los 550 caracteres.';
        }

        $codigoEquivalencia = $this->campo('codigoEquivalencia');

        if (mb_strlen($codigoEquivalencia) > 50) {
            return 'El código de equivalencia no puede superar los 50 caracteres.';
        }

        $centroOperacion = $this->campo('centroOperacion');

        if (mb_strlen($centroOperacion) > 50) {
            return 'El centro de operación no puede superar los 50 caracteres.';
        }

        return [
            'descripcion'        => $descripcion,
            'proveedor'          => $proveedor !== '' ? (int) $proveedor : null,
            'ciudad'             => $ciudad !== '' ? $ciudad : null,
            'hectareas'          => (float) $hectareas,
            'zonaGeografica'     => $zonaGeografica !== '' ? $zonaGeografica : null,
            'codigoEquivalencia' => $codigoEquivalencia !== '' ? $codigoEquivalencia : null,
            'centroOperacion'    => $centroOperacion !== '' ? $centroOperacion : null,
            'interna'            => $this->bit('interna'),
            'socio'              => $this->bit('socio'),
            'activo'             => $this->bit('activo'),
        ];
    }

    /**
     * Respuesta estándar con la tabla re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaFincas(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->fincasModel->listar($empresa, $this->campo('filtro_estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasFincas($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de fincas.
     */
    private function renderizarFilasFincas(array $fincas): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        $ciudades = $this->resolverCiudades($fincas);
        $vacio    = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($fincas as $f) {
            $empresa = (int) $f['empresa'];
            $codigo  = trim((string) $f['codigo']);
            $activo  = (int) $f['activo'] === 1 ? 1 : 0;
            $usos    = (int) ($f['usos'] ?? 0);
            $interna = (int) $f['interna'] === 1 ? 1 : 0;
            $socio   = (int) $f['socio'] === 1 ? 1 : 0;

            $descripcion = trim((string) $f['descripcion']);
            $codigoEq    = trim((string) $f['codigoEquivalencia']);
            $propietario = trim((string) ($f['propietarioNombre'] ?? ''));
            $proveedor   = (int) ($f['proveedor'] ?? 0);
            $zona        = trim((string) $f['zonaGeografica']);
            $centro      = trim((string) $f['centroOperacion']);
            $hectareas   = (float) $f['hectareas'];

            $celdaFinca = htmlspecialchars($descripcion)
                . ($codigoEq !== '' ? '<div class="gp-subtexto">Cód. eq. ' . htmlspecialchars($codigoEq) . '</div>' : '');

            $codigoCiudad = trim((string) $f['ciudad']);
            $nombreCiudad = $codigoCiudad !== '' ? trim((string) ($ciudades[$codigoCiudad] ?? '')) : '';

            if ($codigoCiudad === '') {
                $celdaCiudad = $vacio;
            } elseif ($nombreCiudad === '') {
                $celdaCiudad = '<span class="gp-dato-legado" title="Código de ciudad sin correspondencia en gCiudad">'
                    . htmlspecialchars($codigoCiudad) . '</span>';
            } else {
                $celdaCiudad = htmlspecialchars($nombreCiudad);
            }

            if ($zona !== '') {
                $celdaCiudad .= '<div class="gp-subtexto">' . htmlspecialchars($zona) . '</div>';
            }

            $ordenCiudad = $nombreCiudad !== '' ? $nombreCiudad : $codigoCiudad;

            $celdaCentro = $centro !== ''
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($centro) . '</span>'
                : $vacio;

            $usosFmt = number_format($usos, 0, ',', '.');

            $celdaUsos = $usos > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" title="' . $usosFmt
                    . ' registros asociados (lotes, secciones, sanidad y transacciones); no se puede eliminar mientras esté en uso.">'
                    . $usosFmt . '</span>'
                : '<span class="gp-chip-rol">0</span>';

            $celdaMarcas = ($interna === 1
                    ? '<span class="gp-chip-rol activo" title="Finca interna">INT</span>'
                    : '<span class="gp-chip-rol" title="No es finca interna">INT</span>')
                . ' '
                . ($socio === 1
                    ? '<span class="gp-chip-rol activo" title="Finca de socio">SOC</span>'
                    : '<span class="gp-chip-rol" title="No es finca de socio">SOC</span>');

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar finca' : 'Activar finca';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($codigo) . '&#039;';
            $descJs  = $aJs($descripcion);

            $botonEliminar = $usos > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarFinca(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            $tituloAcciones = $usos > 0
                ? ' title="No se puede eliminar: la finca tiene ' . $usosFmt . ' registros asociados."'
                : '';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . htmlspecialchars($codigo)
                . '" data-activo="' . $activo . '" data-usos="' . $usos . '">'
                . '<td class="text-center"><span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span></td>'
                . '<td data-order="' . htmlspecialchars($descripcion) . '">' . $celdaFinca . '</td>'
                . '<td class="gp-truncar" title="' . htmlspecialchars($propietario) . '" data-order="' . $proveedor . '">'
                . ($propietario !== '' ? htmlspecialchars($propietario) : $vacio) . '</td>'
                . '<td data-order="' . htmlspecialchars($ordenCiudad) . '">' . $celdaCiudad . '</td>'
                . '<td class="text-end font-monospace" data-order="' . $hectareas . '">'
                . number_format($hectareas, 2, ',', '.') . ' <span class="gp-etiqueta-mini">ha</span></td>'
                . '<td class="text-center">' . $celdaCentro . '</td>'
                . '<td class="text-center" data-order="' . $usos . '">' . $celdaUsos . '</td>'
                . '<td class="text-center" data-order="' . ($interna * 2 + $socio) . '">' . $celdaMarcas . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarFinca(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoFinca(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    /**
     * Nombres de las ciudades del listado, resueltos en una sola consulta.
     */
    private function resolverCiudades(array $fincas): array
    {
        if ($fincas === []) {
            return [];
        }

        return $this->tercerosModel->nombresCiudades((int) $fincas[0]['empresa'], array_column($fincas, 'ciudad'));
    }

    /**
     * Valor de texto recibido por POST, ya recortado.
     */
    private function campo(string $nombre): string
    {
        return trim((string) $this->request->getPost($nombre));
    }

    /**
     * Normaliza a 0/1 una casilla del formulario.
     */
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
