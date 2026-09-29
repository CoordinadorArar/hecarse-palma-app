<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\VariedadesModel;
use App\Models\UnidadesMedidaModel;

class ParametrosAgronomicosController extends GestionPalmaController
{
    private $variedadesModel;
    private $unidadesMedidaModel;

    public function __construct()
    {
        parent::__construct();

        $this->variedadesModel     = new VariedadesModel();
        $this->unidadesMedidaModel = new UnidadesMedidaModel();
    }

    /**
     * Vista principal del módulo "Variedad".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function variedad($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->variedadesModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->variedadesModel->listar($empresaSel) : [];

        $data['title']            = 'Gestión Palma · Variedad';
        $data['empresas']         = $empresas;
        $data['empresa_sel']      = $empresaSel;
        $data['tabla_variedades'] = $this->renderizarFilasVariedades($filas);
        $data['total_variedades'] = count($filas);

        return view('gestionpalma/variedades', $data);
    }

    /**
     * Vista principal del módulo "Unidad de medida".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function unidadMedida($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->unidadesMedidaModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->unidadesMedidaModel->listar($empresaSel) : [];

        $data['title']           = 'Gestión Palma · Unidad de medida';
        $data['empresas']        = $empresas;
        $data['empresa_sel']     = $empresaSel;
        $data['tabla_unidades']  = $this->renderizarFilasUnidades($filas);
        $data['total_unidades']  = count($filas);

        return view('gestionpalma/unidades_medida', $data);
    }

    // ── Endpoints JSON ────────────────────────────────────────────────────────

    public function listarVariedades(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->variedadesModel->listar($empresa, $this->campo('estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasVariedades($filas),
        ]);
    }

    public function obtenerVariedad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $variedad = $this->variedadesModel->obtener($empresa, $this->campo('codigo'));

        if ($variedad === null) {
            return $this->error('La variedad no existe.');
        }

        return $this->response->setJSON([
            'success'  => true,
            'variedad' => [
                'empresa'     => (int) $variedad['empresa'],
                'codigo'      => trim((string) $variedad['codigo']),
                'descripcion' => (string) $variedad['descripcion'],
                'procedencia' => (string) $variedad['procedencia'],
                'activo'      => (int) $variedad['activo'],
                'enLotes'     => (int) ($variedad['enLotes'] ?? 0),
            ],
        ]);
    }

    public function crearVariedad(): ResponseInterface
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

        if ($this->variedadesModel->existe($empresa, $codigo)) {
            return $this->error("Ya existe una variedad con el código {$codigo}.");
        }

        $datos = $this->datosVariedad();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->variedadesModel->crear($empresa, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaVariedades($empresa, 'Variedad creada correctamente.');
    }

    public function actualizarVariedad(): ResponseInterface
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

        if (! $this->variedadesModel->existe($empresa, $codigo)) {
            return $this->error('La variedad no existe.');
        }

        $datos = $this->datosVariedad();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->variedadesModel->actualizar($empresa, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaVariedades($empresa, 'Variedad actualizada correctamente.');
    }

    public function eliminarVariedad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');

        if (! $this->variedadesModel->existe($empresa, $codigo)) {
            return $this->error('La variedad no existe.');
        }

        $enUso = $this->variedadesModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error("No se puede eliminar: la variedad está asignada a {$enUso} registros.");
        }

        $this->variedadesModel->eliminar($empresa, $codigo, $this->session->get('usu_id'));

        return $this->respuestaTablaVariedades($empresa, 'Variedad eliminada correctamente.');
    }

    public function cambiarEstadoVariedad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo   = $this->campo('codigo');
        $variedad = $this->variedadesModel->obtener($empresa, $codigo);

        if ($variedad === null) {
            return $this->error('La variedad no existe.');
        }

        $activo = (int) $variedad['activo'] === 1 ? 0 : 1;

        $this->variedadesModel->cambiarEstado($empresa, $codigo, $activo, $this->session->get('usu_id'));

        return $this->respuestaTablaVariedades(
            $empresa,
            $activo === 1 ? 'Variedad activada correctamente.' : 'Variedad desactivada correctamente.'
        );
    }

    public function siguienteCodigoVariedad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        return $this->response->setJSON([
            'success' => true,
            'codigo'  => $this->variedadesModel->siguienteCodigo($empresa),
        ]);
    }

    public function listarUnidades(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->unidadesMedidaModel->listar($empresa, $this->campo('estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasUnidades($filas),
        ]);
    }

    public function obtenerUnidad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $unidad = $this->unidadesMedidaModel->obtener($empresa, $this->campo('codigo'));

        if ($unidad === null) {
            return $this->error('La unidad de medida no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'unidad'  => [
                'empresa'     => (int) $unidad['empresa'],
                'codigo'      => trim((string) $unidad['codigo']),
                'desCorta'    => trim((string) $unidad['desCorta']),
                'descripcion' => (string) $unidad['descripcion'],
                'activo'      => (int) $unidad['activo'],
                'usos'        => (int) ($unidad['usos'] ?? 0),
            ],
        ]);
    }

    public function crearUnidad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoUnidadValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if ($this->unidadesMedidaModel->existe($empresa, $codigo)) {
            return $this->error("Ya existe una unidad de medida con el código {$codigo}.");
        }

        $datos = $this->datosUnidad();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->unidadesMedidaModel->crear($empresa, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaUnidades($empresa, 'Unidad de medida creada correctamente.');
    }

    public function actualizarUnidad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoUnidadValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if (! $this->unidadesMedidaModel->existe($empresa, $codigo)) {
            return $this->error('La unidad de medida no existe.');
        }

        $datos = $this->datosUnidad();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->unidadesMedidaModel->actualizar($empresa, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaUnidades($empresa, 'Unidad de medida actualizada correctamente.');
    }

    public function eliminarUnidad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');

        if (! $this->unidadesMedidaModel->existe($empresa, $codigo)) {
            return $this->error('La unidad de medida no existe.');
        }

        $enUso = $this->unidadesMedidaModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error('No se puede eliminar: la unidad de medida está asignada a ' . number_format($enUso, 0, ',', '.') . ' registros.');
        }

        $this->unidadesMedidaModel->eliminar($empresa, $codigo, $this->session->get('usu_id'));

        return $this->respuestaTablaUnidades($empresa, 'Unidad de medida eliminada correctamente.');
    }

    public function cambiarEstadoUnidad(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $unidad = $this->unidadesMedidaModel->obtener($empresa, $codigo);

        if ($unidad === null) {
            return $this->error('La unidad de medida no existe.');
        }

        $activo = (int) $unidad['activo'] === 1 ? 0 : 1;

        $this->unidadesMedidaModel->cambiarEstado($empresa, $codigo, $activo, $this->session->get('usu_id'));

        return $this->respuestaTablaUnidades(
            $empresa,
            $activo === 1 ? 'Unidad de medida activada correctamente.' : 'Unidad de medida desactivada correctamente.'
        );
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

        if ($empresa === null || ! is_numeric($empresa) || ! $this->variedadesModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
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

        if (mb_strlen($codigo) > 5) {
            return ['El código no puede superar los 5 caracteres.'];
        }

        if (preg_match('/\s/', $codigo)) {
            return ['El código no puede contener espacios.'];
        }

        return $codigo;
    }

    /**
     * Valida el formulario de la variedad y arma las columnas a persistir.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosVariedad(): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (mb_strlen($descripcion) > 550) {
            return 'La descripción no puede superar los 550 caracteres.';
        }

        $procedencia = $this->campo('procedencia');

        if ($procedencia === '') {
            return 'La procedencia es obligatoria.';
        }

        if (mb_strlen($procedencia) > 550) {
            return 'La procedencia no puede superar los 550 caracteres.';
        }

        return [
            'descripcion' => $descripcion,
            'procedencia' => $procedencia,
            'activo'      => $this->bit('activo'),
        ];
    }

    /**
     * Respuesta estándar con la tabla re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaVariedades(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->variedadesModel->listar($empresa, $this->campo('filtro_estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasVariedades($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de variedades.
     */
    private function renderizarFilasVariedades(array $variedades): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        foreach ($variedades as $v) {
            $empresa = (int) $v['empresa'];
            $codigo  = trim((string) $v['codigo']);
            $activo  = (int) $v['activo'] === 1 ? 1 : 0;
            $enLotes = (int) ($v['enLotes'] ?? 0);

            $descripcion = trim((string) $v['descripcion']);
            $procedencia = trim((string) $v['procedencia']);

            $celdaLotes = $enLotes > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" title="' . $enLotes
                    . ' registros usan esta variedad; no se puede eliminar mientras esté en uso.">' . $enLotes . '</span>'
                : '<span class="gp-chip-rol">0</span>';

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar variedad' : 'Activar variedad';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($codigo) . '&#039;';
            $descJs  = $aJs($descripcion);

            $botonEliminar = $enLotes > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarVariedad(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . htmlspecialchars($codigo)
                . '" data-activo="' . $activo . '" data-enlotes="' . $enLotes . '">'
                . '<td class="text-center"><span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span></td>'
                . '<td data-order="' . htmlspecialchars($descripcion) . '">' . htmlspecialchars($descripcion) . '</td>'
                . '<td class="gp-truncar" title="' . htmlspecialchars($procedencia) . '">' . htmlspecialchars($procedencia) . '</td>'
                . '<td class="text-center" data-order="' . $enLotes . '">' . $celdaLotes . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarVariedad(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoVariedad(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    /**
     * Valida el código de la unidad de medida recibido por POST.
     *
     * @return string|array El código recortado o [mensaje de error].
     */
    private function codigoUnidadValido(): string|array
    {
        $codigo = mb_strtoupper($this->campo('codigo'));

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
     * Valida el formulario de la unidad de medida y arma las columnas a persistir.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosUnidad(): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (mb_strlen($descripcion) > 50) {
            return 'La descripción no puede superar los 50 caracteres.';
        }

        $desCorta = $this->campo('desCorta');

        if ($desCorta === '') {
            return 'La descripción corta es obligatoria.';
        }

        if (mb_strlen($desCorta) > 3) {
            return 'La descripción corta no puede superar los 3 caracteres.';
        }

        return [
            'descripcion' => $descripcion,
            'desCorta'    => $desCorta,
            'activo'      => $this->bit('activo'),
        ];
    }

    /**
     * Respuesta estándar con la tabla de unidades re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaUnidades(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->unidadesMedidaModel->listar($empresa, $this->campo('filtro_estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasUnidades($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de unidades de medida.
     */
    private function renderizarFilasUnidades(array $unidades): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        foreach ($unidades as $u) {
            $empresa = (int) $u['empresa'];
            $codigo  = trim((string) $u['codigo']);
            $activo  = (int) $u['activo'] === 1 ? 1 : 0;
            $usos    = (int) ($u['usos'] ?? 0);

            $descripcion = trim((string) $u['descripcion']);
            $desCorta    = trim((string) $u['desCorta']);

            $celdaDesCorta = $desCorta === '' ? '<span class="gp-chip-rol">&mdash;</span>' : htmlspecialchars($desCorta);

            $usosFmt = number_format($usos, 0, ',', '.');

            $celdaUsos = $usos > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" title="' . $usosFmt
                    . ' registros usan esta unidad de medida; no se puede eliminar mientras esté en uso.">' . $usosFmt . '</span>'
                : '<span class="gp-chip-rol">0</span>';

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar unidad de medida' : 'Activar unidad de medida';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($codigo) . '&#039;';
            $descJs  = $aJs($descripcion);

            $botonEliminar = $usos > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarUnidad(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . htmlspecialchars($codigo)
                . '" data-activo="' . $activo . '" data-usos="' . $usos . '">'
                . '<td class="text-center"><span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span></td>'
                . '<td class="text-center font-monospace">' . $celdaDesCorta . '</td>'
                . '<td data-order="' . htmlspecialchars($descripcion) . '">' . htmlspecialchars($descripcion) . '</td>'
                . '<td class="text-center" data-order="' . $usos . '">' . $celdaUsos . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarUnidad(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoUnidad(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
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
