<?php

namespace App\Controllers\Maestros;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\EntidadesAuxiliaresModel;

/**
 * CRUD genérico de las entidades auxiliares (16 tablas de catálogo con empresa más
 * dos columnas de datos).
 *
 * La entidad llega del cliente: se valida contra la lista blanca del modelo y las
 * columnas se resuelven desde los metadatos, que además definen los rótulos y las
 * reglas de validación (tipo, longitud y obligatoriedad varían por tabla).
 *
 * El listado siempre pagina en el servidor (gDiagnostico tiene más de 14.000 filas).
 */
class EntidadesController extends BaseController
{
    private $session;
    private $entidadesModel;

    public function __construct()
    {
        $this->session        = session();
        $this->entidadesModel = new EntidadesAuxiliaresModel();
    }

    /**
     * Vista principal del módulo "Entidades Auxiliares".
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

        $empresas = $this->entidadesModel->getEmpresas();

        $data['title']       = 'Maestros · Entidades Auxiliares';
        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresas ? (int) $empresas[0]['id'] : 0;
        $data['entidades']   = $this->entidadesModel->entidades();
        $data['por_pagina']  = EntidadesAuxiliaresModel::POR_PAGINA;

        return view('maestros/entidades_auxiliares', $data);
    }

    /**
     * Lista blanca de entidades disponibles.
     */
    public function entidades(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $entidades = $this->entidadesModel->entidades();

        return $this->response->setJSON([
            'success'   => true,
            'total'     => count($entidades),
            'entidades' => $entidades,
        ]);
    }

    /**
     * Metadatos de las dos columnas de datos de la entidad.
     */
    public function metadatos(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $meta = $this->metaValida();

        if ($meta === null) {
            return $this->error('La entidad seleccionada no es válida.');
        }

        return $this->response->setJSON([
            'success'       => true,
            'entidad'       => $meta['entidad'],
            'etiqueta'      => $meta['etiqueta'],
            'columnas'      => $meta['columnas'],
            'llaveCompleta' => $meta['llaveCompleta'],
        ]);
    }

    public function listar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $meta = $this->metaValida();

        if ($meta === null) {
            return $this->error('La entidad seleccionada no es válida.');
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $pagina    = (int) $this->campo('pagina');
        $porPagina = (int) $this->campo('porPagina');

        $resultado = $this->entidadesModel->listar(
            $meta,
            $empresa,
            $pagina > 0 ? $pagina : 1,
            $porPagina > 0 ? $porPagina : EntidadesAuxiliaresModel::POR_PAGINA,
            $this->campo('busqueda')
        );

        if ($resultado === null) {
            return $this->error('No se pudo leer el listado de la entidad seleccionada.', 500);
        }

        return $this->response->setJSON(array_merge([
            'success'       => true,
            'message'       => '',
            'entidad'       => $meta['entidad'],
            'etiqueta'      => $meta['etiqueta'],
            'columnas'      => $meta['columnas'],
            'llaveCompleta' => $meta['llaveCompleta'],
        ], $resultado));
    }

    public function obtener(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $meta = $this->metaValida();

        if ($meta === null) {
            return $this->error('La entidad seleccionada no es válida.');
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $valor1 = trim($this->campo('valor1'));
        $valor2 = trim($this->campo('valor2'));

        if ($meta['llaveCompleta'] && $valor2 === '') {
            return $this->error('El campo "' . $meta['columnas'][1]['etiqueta'] . '" es obligatorio para identificar el registro.');
        }

        $fila = $valor1 === '' ? null : $this->entidadesModel->obtener($meta, $empresa, $valor1, $valor2);

        if ($fila === null) {
            return $this->error('El registro no existe en la entidad seleccionada.');
        }

        return $this->response->setJSON([
            'success'       => true,
            'entidad'       => $meta['entidad'],
            'columnas'      => $meta['columnas'],
            'llaveCompleta' => $meta['llaveCompleta'],
            'fila'          => $fila,
        ]);
    }

    public function crear(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $meta = $this->metaValida();

        if ($meta === null) {
            return $this->error('La entidad seleccionada no es válida.');
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $valores = $this->valoresValidos($meta);

        if (is_string($valores)) {
            return $this->error($valores);
        }

        if ($this->entidadesModel->existe($meta, $empresa, $valores['valor1'], $valores['valor2'])) {
            return $this->error('Ya existe un registro con esa llave en la entidad seleccionada.');
        }

        if (! $this->entidadesModel->crear($meta, $empresa, $valores['valor1'], $valores['valor2'], $this->session->get('usu_id'))) {
            return $this->error('No se pudo registrar el dato en la entidad seleccionada.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Registro creado correctamente.',
        ]);
    }

    public function actualizar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $meta = $this->metaValida();

        if ($meta === null) {
            return $this->error('La entidad seleccionada no es válida.');
        }

        if ($meta['llaveCompleta']) {
            return $this->error(
                'En la entidad "' . $meta['etiqueta'] . '" los dos datos hacen parte de la llave primaria, por lo que no se pueden modificar. Elimine el registro y cree uno nuevo.'
            );
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $valores = $this->valoresValidos($meta);

        if (is_string($valores)) {
            return $this->error($valores);
        }

        if (! $this->entidadesModel->existe($meta, $empresa, $valores['valor1'])) {
            return $this->error('El registro que intenta actualizar no existe.');
        }

        $valor1Nuevo = trim($this->campo('valor1Nuevo'));

        if ($valor1Nuevo !== '' && strcasecmp($valor1Nuevo, $valores['valor1']) !== 0) {
            return $this->error(
                'El campo "' . $meta['columnas'][0]['etiqueta'] . '" hace parte de la llave y no se puede modificar. Cree un registro nuevo y elimine el anterior.'
            );
        }

        if (! $this->entidadesModel->actualizar($meta, $empresa, $valores['valor1'], $valores['valor2'], $this->session->get('usu_id'))) {
            return $this->error('No se pudo actualizar el dato de la entidad seleccionada.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Registro actualizado correctamente.',
        ]);
    }

    public function eliminar(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $meta = $this->metaValida();

        if ($meta === null) {
            return $this->error('La entidad seleccionada no es válida.');
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $valor1 = trim($this->campo('valor1'));
        $valor2 = trim($this->campo('valor2'));

        if ($meta['llaveCompleta'] && $valor2 === '') {
            return $this->error('El campo "' . $meta['columnas'][1]['etiqueta'] . '" es obligatorio para identificar el registro.');
        }

        $fila = $valor1 === '' ? null : $this->entidadesModel->obtener($meta, $empresa, $valor1, $valor2);

        if ($fila === null) {
            return $this->error('El registro que intenta eliminar no existe.');
        }

        $dependencias = $this->entidadesModel->dependencias($meta, $empresa, $valor1, $valor2);

        if ($dependencias !== []) {
            return $this->response->setJSON([
                'success'      => false,
                'message'      => 'No se puede eliminar el registro porque está siendo utilizado en: ' . $this->textoDependencias($dependencias) . '.',
                'dependencias' => $dependencias,
            ])->setStatusCode(422);
        }

        if (! $this->entidadesModel->eliminar($meta, $empresa, $valor1, $valor2, $this->session->get('usu_id'), $fila)) {
            return $this->error('No se pudo eliminar el dato de la entidad seleccionada.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /**
     * Normaliza y valida los dos datos según los metadatos de la entidad.
     *
     * @return array{valor1: string, valor2: ?string}|string Valores listos, o el mensaje de error.
     */
    private function valoresValidos(array $meta): array|string
    {
        $valores = [];

        foreach ($meta['columnas'] as $indice => $columna) {
            $valor   = trim($this->campo('valor' . ($indice + 1)));
            $mensaje = $this->validarValor($columna, $valor, $indice === 0);

            if ($mensaje !== null) {
                return $mensaje;
            }

            $valores['valor' . ($indice + 1)] = $valor === '' && $columna['admiteNulo'] ? null : $valor;
        }

        return $valores;
    }

    /**
     * Reglas derivadas del catálogo: obligatoriedad, tipo y longitud real.
     * La longitud se mide con strlen() porque la collation es CP1252 y un
     * varchar(N) admite N bytes.
     */
    private function validarValor(array $columna, string $valor, bool $esLlave): ?string
    {
        if ($valor === '') {
            if ($esLlave || ! $columna['admiteNulo']) {
                return 'El campo "' . $columna['etiqueta'] . '" es obligatorio.';
            }

            return null;
        }

        if ($columna['esNumerico']) {
            if (preg_match('/^\d+$/', $valor) !== 1) {
                return 'El campo "' . $columna['etiqueta'] . '" solo admite números enteros.';
            }

            if ((int) $valor > 2147483647) {
                return 'El campo "' . $columna['etiqueta'] . '" excede el valor numérico permitido.';
            }

            return null;
        }

        $maximo = (int) $columna['longitud'];

        if ($maximo > 0 && strlen($valor) > $maximo) {
            return $maximo === 1
                ? 'El campo "' . $columna['etiqueta'] . '" admite un solo carácter.'
                : 'El campo "' . $columna['etiqueta'] . '" admite máximo ' . $maximo . ' caracteres.';
        }

        return null;
    }

    private function textoDependencias(array $dependencias): string
    {
        return implode(', ', array_map(
            static fn (array $dependencia): string => $dependencia['tabla'] . ' (' . $dependencia['filas'] . ' registro(s))',
            $dependencias
        ));
    }

    /**
     * Metadatos de la entidad recibida, validada contra la lista blanca.
     */
    private function metaValida(): ?array
    {
        $entidad = trim($this->campo('entidad'));

        if ($entidad === '' || $this->entidadesModel->entidadValida($entidad) === null) {
            return null;
        }

        return $this->entidadesModel->metadatos($entidad);
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

        if ($empresa <= 0 || ! $this->entidadesModel->empresaExiste($empresa)) {
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
