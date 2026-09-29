<?php

namespace App\Controllers\Maestros;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\TiposDocumentoModel;

/**
 * CRUD de los tipos de documento de identificación del legado (gTipoDocumento),
 * el catálogo que consume cTercero.tipoDocumento.
 *
 * La llave primaria es (empresa, codigo): el código no se puede modificar y el
 * borrado se bloquea si hay terceros que lo usan (no hay FK que lo impida).
 */
class TiposDocumentosController extends BaseController
{
    private $session;
    private $tiposModel;

    public function __construct()
    {
        $this->session    = session();
        $this->tiposModel = new TiposDocumentoModel();
    }

    /**
     * Vista principal del módulo "Tipos de documento".
     *
     * @param int $id_loseta Identificador de la loseta "Maestros".
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas = $this->tiposModel->getEmpresas();

        $data['title']       = 'Maestros · Tipos de documento';
        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresas ? (int) $empresas[0]['id'] : 0;

        return view('maestros/tipos_documentos', $data);
    }

    public function listar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        try {
            $filas = $this->tiposModel->listar($empresa);
        } catch (\Throwable $e) {
            log_message('error', 'Error listando los tipos de documento: ' . $e->getMessage());

            return $this->error('No se pudo leer el listado de tipos de documento.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'empresa' => $empresa,
            'total'   => count($filas),
            'filas'   => $filas,
        ]);
    }

    public function obtener(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = trim($this->campo('codigo'));

        if ($codigo === '') {
            return $this->error('El campo "Código" es obligatorio para identificar el registro.');
        }

        $fila = $this->tiposModel->obtener($empresa, $codigo);

        if ($fila === null) {
            return $this->error('El tipo de documento no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'fila'    => $fila,
        ]);
    }

    public function crear(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $datos = $this->datosValidos();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if ($this->tiposModel->existe($empresa, $datos['codigo'])) {
            return $this->error('Ya existe un tipo de documento con el código "' . $datos['codigo'] . '" en la empresa seleccionada.');
        }

        $creado = $this->tiposModel->crear(
            $empresa,
            $datos['codigo'],
            $datos['descripcion'],
            $datos['descripcionCorta'],
            $datos['codigoTD'],
            $datos['mNit'],
            $datos['equivalencia'],
            $this->session->get('usu_id')
        );

        if (! $creado) {
            return $this->error('No se pudo registrar el tipo de documento.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tipo de documento creado correctamente.',
        ]);
    }

    public function actualizar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigoOriginal = trim($this->campo('codigoOriginal'));

        if ($codigoOriginal === '') {
            $codigoOriginal = trim($this->campo('codigo'));
        }

        if ($codigoOriginal === '') {
            return $this->error('El campo "Código" es obligatorio para identificar el registro.');
        }

        $datos = $this->datosValidos();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if (! $this->tiposModel->existe($empresa, $codigoOriginal)) {
            return $this->error('El tipo de documento que intenta actualizar no existe.');
        }

        if (strcasecmp($datos['codigo'], $codigoOriginal) !== 0) {
            return $this->error('El campo "Código" hace parte de la llave primaria y no se puede modificar. Cree un tipo de documento nuevo y elimine el anterior.');
        }

        $actualizado = $this->tiposModel->actualizar(
            $empresa,
            $codigoOriginal,
            $datos['descripcion'],
            $datos['descripcionCorta'],
            $datos['codigoTD'],
            $datos['mNit'],
            $datos['equivalencia'],
            $this->session->get('usu_id')
        );

        if (! $actualizado) {
            return $this->error('No se pudo actualizar el tipo de documento.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tipo de documento actualizado correctamente.',
        ]);
    }

    public function eliminar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = trim($this->campo('codigo'));

        if ($codigo === '') {
            return $this->error('El campo "Código" es obligatorio para identificar el registro.');
        }

        $fila = $this->tiposModel->obtener($empresa, $codigo);

        if ($fila === null) {
            return $this->error('El tipo de documento que intenta eliminar no existe.');
        }

        $terceros = $this->tiposModel->usoEnTerceros($empresa, $codigo);

        if ($terceros > 0) {
            return $this->response->setJSON([
                'success'  => false,
                'message'  => 'No se puede eliminar el tipo de documento porque lo están usando ' . $terceros . ' tercero(s) de la empresa seleccionada.',
                'terceros' => $terceros,
            ])->setStatusCode(422);
        }

        if (! $this->tiposModel->eliminar($empresa, $codigo, $this->session->get('usu_id'), $fila)) {
            return $this->error('No se pudo eliminar el tipo de documento.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tipo de documento eliminado correctamente.',
        ]);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /**
     * Normaliza y valida los datos del formulario.
     *
     * Las longitudes se miden con strlen() porque la collation es CP1252 y un
     * varchar(N) admite N bytes.
     *
     * @return array{codigo: string, descripcion: string, descripcionCorta: string, codigoTD: int, mNit: int, equivalencia: ?string}|string
     */
    private function datosValidos(): array|string
    {
        $codigo = trim($this->campo('codigo'));

        if ($codigo === '') {
            return 'El campo "Código" es obligatorio.';
        }

        if (strlen($codigo) > 3) {
            return 'El campo "Código" admite máximo 3 caracteres, porque la columna "tipoDocumento" de los terceros es de 3 posiciones y un código más largo impediría registrar terceros con este tipo de documento.';
        }

        $descripcion = trim($this->campo('descripcion'));

        if ($descripcion === '') {
            return 'El campo "Descripción" es obligatorio.';
        }

        if (strlen($descripcion) > 250) {
            return 'El campo "Descripción" admite máximo 250 caracteres.';
        }

        $descripcionCorta = trim($this->campo('descripcionCorta'));

        if ($descripcionCorta === '') {
            return 'El campo "Abreviatura" es obligatorio.';
        }

        if (strlen($descripcionCorta) > 50) {
            return 'El campo "Abreviatura" admite máximo 50 caracteres.';
        }

        $codigoTD = trim($this->campo('codigoTD'));

        if ($codigoTD === '') {
            return 'El campo "Código TD" es obligatorio.';
        }

        if (preg_match('/^\d+$/', $codigoTD) !== 1) {
            return 'El campo "Código TD" solo admite números enteros mayores o iguales a cero.';
        }

        if ((int) $codigoTD > 2147483647) {
            return 'El campo "Código TD" excede el valor numérico permitido.';
        }

        $equivalencia = trim($this->campo('equivalencia'));

        if (strlen($equivalencia) > 50) {
            return 'El campo "Equivalencia" admite máximo 50 caracteres.';
        }

        return [
            'codigo'           => $codigo,
            'descripcion'      => $descripcion,
            'descripcionCorta' => $descripcionCorta,
            'codigoTD'         => (int) $codigoTD,
            'mNit'             => (int) $this->campo('mNit') === 1 ? 1 : 0,
            'equivalencia'     => $equivalencia === '' ? null : $equivalencia,
        ];
    }

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

    private function campo(string $nombre): string
    {
        return (string) ($this->request->getVar($nombre) ?? '');
    }

    private function empresaValida(): ?int
    {
        $empresa = (int) $this->campo('empresa');

        if ($empresa <= 0 || ! $this->tiposModel->empresaExiste($empresa)) {
            return null;
        }

        return $empresa;
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode($codigo);
    }
}
