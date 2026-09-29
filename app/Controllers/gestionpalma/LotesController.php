<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\LotesModel;
use App\Models\PesosLoteModel;

class LotesController extends GestionPalmaController
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    private $lotesModel;
    private $pesosLoteModel;

    public function __construct()
    {
        parent::__construct();

        $this->lotesModel     = new LotesModel();
        $this->pesosLoteModel = new PesosLoteModel();
    }

    /**
     * Vista principal del módulo "Lotes / Registro".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function registro($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->lotesModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->lotesModel->listar($empresaSel) : [];

        $data['title']         = 'Gestión Palma · Registro de lotes';
        $data['empresas']      = $empresas;
        $data['empresa_sel']   = $empresaSel;
        $data['fincas']        = $empresaSel > 0 ? $this->lotesModel->getFincas($empresaSel) : [];
        $data['secciones']     = $empresaSel > 0 ? $this->lotesModel->getSecciones($empresaSel) : [];
        $data['variedades']    = $empresaSel > 0 ? $this->lotesModel->getVariedades($empresaSel) : [];
        $data['tipos_canal']   = $empresaSel > 0 ? $this->lotesModel->getTiposCanal($empresaSel) : [];
        $data['centros_costo'] = $empresaSel > 0 ? $this->lotesModel->getCentrosCosto($empresaSel) : [];
        $data['tabla_lotes']   = $this->renderizarFilasLotes($filas);
        $data['total_lotes']   = count($filas);

        return view('gestionpalma/lotes_registro', $data);
    }

    /**
     * Vista principal del módulo "Peso RFF por lotes". Abre filtrada por el año y mes actuales:
     * la tabla tiene ~11.000 filas y nunca se debe listar de golpe.
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function pesoRff($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->pesosLoteModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $anioSel = (int) date('Y');
        $mesSel  = (int) date('n');

        $filas = $empresaSel > 0 ? $this->pesosLoteModel->listar($empresaSel, $anioSel, $mesSel) : [];

        $data['title']        = 'Gestión Palma · Peso RFF por lote';
        $data['empresas']     = $empresas;
        $data['empresa_sel']  = $empresaSel;
        $data['fincas']       = $empresaSel > 0 ? $this->pesosLoteModel->getFincas($empresaSel) : [];
        $data['lotes']        = $empresaSel > 0 ? $this->pesosLoteModel->getLotes($empresaSel) : [];
        $data['anios']        = $empresaSel > 0 ? $this->pesosLoteModel->getAnios($empresaSel) : [];
        $data['anio_sel']     = $anioSel;
        $data['mes_sel']      = $mesSel;
        $data['tabla_pesos']  = $this->renderizarFilasPesos($filas);
        $data['total_pesos']  = count($filas);

        return view('gestionpalma/lotes_peso_rff', $data);
    }

    // ── Endpoints JSON ────────────────────────────────────────────────────────

    public function listarLotes(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->lotesModel->listar($empresa, $this->campo('finca'), $this->campo('estado'), $this->campo('descuadre'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasLotes($filas),
        ]);
    }

    public function obtenerLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->campo('finca');
        $lote   = $this->lotesModel->obtener($empresa, $codigo, $finca);

        if ($lote === null) {
            return $this->error('El lote no existe.');
        }

        $canales = $this->lotesModel->getCanales($empresa, $codigo);

        return $this->response->setJSON([
            'success' => true,
            'lote'    => [
                'empresa'           => (int) $lote['empresa'],
                'codigo'            => trim((string) $lote['codigo']),
                'finca'             => trim((string) $lote['finca']),
                'fincaNombre'       => trim((string) ($lote['fincaDescripcion'] ?? '')),
                'manejaSeccion'     => (int) $lote['manejaSeccion'],
                'seccion'           => $lote['seccion'] !== null ? trim((string) $lote['seccion']) : null,
                'descripcion'       => (string) $lote['descripcion'],
                'anioSiembra'       => (int) $lote['anioSiembra'],
                'palmasBrutas'      => (int) $lote['palmasBrutas'],
                'palmasProduccion'  => (int) $lote['palmasProduccion'],
                'hBrutas'           => (float) $lote['hBrutas'],
                'hNetas'            => (float) $lote['hNetas'],
                'dSiembra'          => (float) $lote['dSiembra'],
                'variedad'          => trim((string) $lote['variedad']),
                'densidad'          => (float) $lote['densidad'],
                'NoLineas'          => (int) $lote['NoLineas'],
                'numero'            => $lote['numero'] !== null ? (int) $lote['numero'] : null,
                'letra'             => $lote['letra'] !== null ? trim((string) $lote['letra']) : null,
                'ccosto'            => trim((string) $lote['ccosto']),
                'activo'            => (int) $lote['activo'],
                'desarrollo'        => (int) $lote['desarrollo'],
                'usos'              => (int) ($lote['usos'] ?? 0),
                'detalleLineas'     => (int) ($lote['detalleLineas'] ?? 0),
                'detallePalmas'     => (int) ($lote['detallePalmas'] ?? 0),
                'detalleProduccion' => (int) ($lote['detalleProduccion'] ?? 0),
            ],
            'canales' => array_map(static fn ($c) => [
                'tipoCanal'   => trim((string) $c['tipoCanal']),
                'descripcion' => trim((string) ($c['descripcion'] ?? '')),
                'metros'      => (float) $c['metros'],
            ], $canales),
        ]);
    }

    public function crearLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca = $this->fincaValidaCampo($empresa);

        if (is_array($finca)) {
            return $this->error($finca[0]);
        }

        $codigo = $this->codigoValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if ($this->lotesModel->existe($empresa, $codigo, $finca)) {
            return $this->error("Ya existe un lote con el código {$codigo} en la finca {$finca}.");
        }

        $datos = $this->datosLote($empresa, $finca);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $canales = $this->datosCanales($empresa, $this->arrayJson('canales'));

        if (is_string($canales)) {
            return $this->error($canales);
        }

        $ok = $this->lotesModel->crear(
            $empresa,
            $codigo,
            $finca,
            $datos,
            $canales,
            $this->session->get('usu_id'),
            $this->usuarioRegistro()
        );

        if (! $ok) {
            return $this->error('No se pudo crear el lote. La operación fue revertida.');
        }

        return $this->respuestaTablaLotes($empresa, 'Lote creado correctamente.');
    }

    public function actualizarLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->campo('finca');

        if (! $this->lotesModel->existe($empresa, $codigo, $finca)) {
            return $this->error('El lote no existe.');
        }

        $datos = $this->datosLote($empresa, $finca);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $canales = $this->datosCanales($empresa, $this->arrayJson('canales'));

        if (is_string($canales)) {
            return $this->error($canales);
        }

        $ok = $this->lotesModel->actualizar($empresa, $codigo, $finca, $datos, $canales, $this->session->get('usu_id'));

        if (! $ok) {
            return $this->error('No se pudo actualizar el lote. La operación fue revertida.');
        }

        return $this->respuestaTablaLotes($empresa, 'Lote actualizado correctamente.');
    }

    public function eliminarLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->campo('finca');

        if (! $this->lotesModel->existe($empresa, $codigo, $finca)) {
            return $this->error('El lote no existe.');
        }

        $enUso = $this->lotesModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error('No se puede eliminar: el lote tiene ' . number_format($enUso, 0, ',', '.') . ' registros asociados.');
        }

        $ok = $this->lotesModel->eliminar($empresa, $codigo, $finca, $this->session->get('usu_id'));

        if (! $ok) {
            return $this->error('No se pudo eliminar el lote. La operación fue revertida.');
        }

        return $this->respuestaTablaLotes($empresa, 'Lote eliminado correctamente.');
    }

    public function cambiarEstadoLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->campo('finca');
        $lote   = $this->lotesModel->obtener($empresa, $codigo, $finca);

        if ($lote === null) {
            return $this->error('El lote no existe.');
        }

        $activo = (int) $lote['activo'] === 1 ? 0 : 1;

        $this->lotesModel->cambiarEstado($empresa, $codigo, $finca, $activo, $this->session->get('usu_id'));

        return $this->respuestaTablaLotes(
            $empresa,
            $activo === 1 ? 'Lote activado correctamente.' : 'Lote desactivado correctamente.'
        );
    }

    // ── Endpoints JSON: líneas del lote (aLotesDetalle), fila por fila ────────

    public function listarLineasLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->campo('finca');

        if (! $this->lotesModel->existe($empresa, $codigo, $finca)) {
            return $this->error('El lote no existe.');
        }

        return $this->respuestaLineasLote($empresa, $codigo, $finca, '');
    }

    public function crearLineaLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->campo('finca');

        if (! $this->lotesModel->existe($empresa, $codigo, $finca)) {
            return $this->error('El lote no existe.');
        }

        $datos = $this->datosLinea();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if ($this->lotesModel->lineaExiste($empresa, $codigo, $finca, $datos['linea'])) {
            return $this->error("La línea {$datos['linea']} ya existe en este lote.");
        }

        $ok = $this->lotesModel->crearLinea(
            $empresa, $codigo, $finca, $datos['linea'], $datos['noPalma'], $datos['palmaErradicada'],
            $this->session->get('usu_id')
        );

        if (! $ok) {
            return $this->error('No se pudo crear la línea. La operación fue revertida.');
        }

        return $this->respuestaLineasLote($empresa, $codigo, $finca, 'Línea creada correctamente.');
    }

    public function actualizarLineaLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $finca  = $this->campo('finca');

        if (! $this->lotesModel->existe($empresa, $codigo, $finca)) {
            return $this->error('El lote no existe.');
        }

        $datos = $this->datosLinea();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if (! $this->lotesModel->lineaExiste($empresa, $codigo, $finca, $datos['linea'])) {
            return $this->error("La línea {$datos['linea']} no existe en este lote.");
        }

        $ok = $this->lotesModel->actualizarLinea(
            $empresa, $codigo, $finca, $datos['linea'], $datos['noPalma'], $datos['palmaErradicada'],
            $this->session->get('usu_id')
        );

        if (! $ok) {
            return $this->error('No se pudo actualizar la línea. La operación fue revertida.');
        }

        return $this->respuestaLineasLote($empresa, $codigo, $finca, 'Línea actualizada correctamente.');
    }

    public function eliminarLineaLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo   = $this->campo('codigo');
        $finca    = $this->campo('finca');
        $lineaTxt = $this->campo('linea');

        if (! $this->lotesModel->existe($empresa, $codigo, $finca)) {
            return $this->error('El lote no existe.');
        }

        if ($lineaTxt === '' || ! ctype_digit($lineaTxt) || (int) $lineaTxt < 1) {
            return $this->error('El número de línea debe ser un entero mayor o igual a 1.');
        }

        $linea = (int) $lineaTxt;

        if (! $this->lotesModel->lineaExiste($empresa, $codigo, $finca, $linea)) {
            return $this->error("La línea {$linea} no existe en este lote.");
        }

        $ok = $this->lotesModel->eliminarLinea($empresa, $codigo, $finca, $linea, $this->session->get('usu_id'));

        if (! $ok) {
            return $this->error('No se pudo eliminar la línea. La operación fue revertida.');
        }

        return $this->respuestaLineasLote($empresa, $codigo, $finca, 'Línea eliminada correctamente.');
    }

    // ── Endpoints JSON: peso RFF por lote (aLotePesosPeriodo) ─────────────────

    public function lotesPorFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $lotes = $this->pesosLoteModel->getLotes($empresa, $this->campo('finca'));

        return $this->response->setJSON([
            'success' => true,
            'lotes'   => array_map(static fn ($l) => [
                'codigo'      => trim((string) $l['codigo']),
                'descripcion' => (string) $l['descripcion'],
            ], $lotes),
        ]);
    }

    public function listarPesos(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anioTxt   = $this->campo('anio');
        $mesTxt    = $this->campo('mes');
        $problemas = $this->campo('problemas');

        $anioValido = $anioTxt !== '' && ctype_digit($anioTxt);
        $mesValido  = $mesTxt !== '' && ctype_digit($mesTxt);

        if ($problemas !== '1' && (! $anioValido || ! $mesValido)) {
            return $this->error('Debe indicar el año y el mes.');
        }

        $filas = $this->pesosLoteModel->listar(
            $empresa, $anioValido ? (int) $anioTxt : null, $mesValido ? (int) $mesTxt : null,
            $this->campo('finca'), $this->campo('origen'), $problemas
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasPesos($filas),
        ]);
    }

    public function obtenerPeso(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anioTxt = $this->campo('anio');
        $mesTxt  = $this->campo('mes');

        if ($anioTxt === '' || ! ctype_digit($anioTxt) || $mesTxt === '' || ! ctype_digit($mesTxt)) {
            return $this->error('El registro solicitado no es válido.');
        }

        $peso = $this->pesosLoteModel->obtener($empresa, (int) $anioTxt, (int) $mesTxt, $this->campo('finca'), $this->campo('lote'));

        if ($peso === null) {
            return $this->error('El registro no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'peso'    => [
                'empresa'         => (int) $peso['empresa'],
                'anio'            => (int) $peso['anio'],
                'mes'             => (int) $peso['mes'],
                'finca'           => trim((string) $peso['finca']),
                'fincaNombre'     => trim((string) ($peso['fincaDescripcion'] ?? '')),
                'lote'            => trim((string) $peso['lote']),
                'pesoRacimo'      => (float) $peso['pesoRacimo'],
                'automatico'      => (int) $peso['automatico'],
                'fechaInicial'    => $this->formatearFecha($peso['fechaInicial'], 'Y-m-d'),
                'fechaFinal'      => $this->formatearFecha($peso['fechaFinal'], 'Y-m-d'),
                'loteInexistente' => (int) ($peso['loteInexistente'] ?? 0),
            ],
        ]);
    }

    public function crearPeso(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if (is_array($anio)) {
            return $this->error($anio[0]);
        }

        $mes = $this->mesValido();

        if (is_array($mes)) {
            return $this->error($mes[0]);
        }

        $finca = $this->fincaValidaPeso($empresa);

        if (is_array($finca)) {
            return $this->error($finca[0]);
        }

        $lote = $this->campo('lote');

        if ($lote === '') {
            return $this->error('El lote es obligatorio.');
        }

        if (! $this->pesosLoteModel->loteValido($empresa, $finca, $lote)) {
            return $this->error('El lote seleccionado no existe en la finca indicada.');
        }

        if ($this->pesosLoteModel->existe($empresa, $anio, $mes, $finca, $lote)) {
            return $this->error("Ya existe un peso registrado para el período {$mes}/{$anio}, finca {$finca}, lote {$lote}.");
        }

        $datos = $this->datosPeso();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->pesosLoteModel->crear($empresa, $anio, $mes, $finca, $lote, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaPesos($empresa, $anio, $mes, 'Peso creado correctamente.');
    }

    public function actualizarPeso(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave = $this->claveVigente($empresa);

        if (is_string($clave)) {
            return $this->error($clave);
        }

        [$anio, $mes, $finca, $lote] = $clave;

        $datos = $this->datosPeso();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->pesosLoteModel->actualizar($empresa, $anio, $mes, $finca, $lote, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaPesos($empresa, $anio, $mes, 'Peso actualizado correctamente.');
    }

    public function eliminarPeso(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave = $this->claveVigente($empresa);

        if (is_string($clave)) {
            return $this->error($clave);
        }

        [$anio, $mes, $finca, $lote] = $clave;

        $this->pesosLoteModel->eliminar($empresa, $anio, $mes, $finca, $lote, $this->session->get('usu_id'));

        return $this->respuestaTablaPesos($empresa, $anio, $mes, 'Peso eliminado correctamente.');
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

        if ($empresa === null || ! is_numeric($empresa) || ! $this->lotesModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
    }

    /**
     * Login que queda en la columna usuario, al crear.
     */
    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : mb_substr($usuario, 0, 50);
    }

    /**
     * Valida la finca recibida por POST.
     *
     * @return string|array El código de finca recortado o [mensaje de error].
     */
    private function fincaValidaCampo(int $empresa): string|array
    {
        $finca = $this->campo('finca');

        if ($finca === '') {
            return ['La finca es obligatoria.'];
        }

        if (! $this->lotesModel->fincaValida($empresa, $finca)) {
            return ['La finca seleccionada no existe.'];
        }

        return $finca;
    }

    /**
     * Valida el código recibido por POST. A diferencia de Fincas/Secciones, sí admite espacios
     * internos (hay códigos como "CRL 14B-21"); solo se recorta al inicio/fin.
     *
     * @return string|array El código en mayúsculas o [mensaje de error].
     */
    private function codigoValido(): string|array
    {
        $codigo = mb_strtoupper($this->campo('codigo'));

        if ($codigo === '') {
            return ['El código es obligatorio.'];
        }

        if (mb_strlen($codigo) > 50) {
            return ['El código no puede superar los 50 caracteres.'];
        }

        return $codigo;
    }

    /**
     * Valida el formulario del lote y arma las columnas a persistir en aLotes.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosLote(int $empresa, string $finca): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (strlen($descripcion) > 550) {
            return 'La descripción no puede superar los 550 caracteres.';
        }

        $variedad = mb_strtoupper($this->campo('variedad'));

        if ($variedad === '') {
            return 'La variedad es obligatoria.';
        }

        if (! $this->lotesModel->variedadValida($empresa, $variedad)) {
            return 'La variedad seleccionada no existe.';
        }

        $ccosto = $this->campo('ccosto');

        if ($ccosto === '') {
            return 'El centro de costo es obligatorio.';
        }

        if (! $this->lotesModel->ccostoValido($empresa, $ccosto)) {
            return 'El centro de costo seleccionado no existe.';
        }

        $manejaSeccion = $this->bit('manejaSeccion');
        $seccion       = $this->campo('seccion');

        if ($manejaSeccion === 1) {
            if ($seccion === '') {
                return 'La sección es obligatoria cuando el lote maneja secciones.';
            }

            if (! $this->lotesModel->seccionValida($empresa, $finca, $seccion)) {
                return 'La sección seleccionada no existe en la finca indicada.';
            }
        } else {
            $seccion = null;
        }

        $anioTxt = $this->campo('anioSiembra');

        if ($anioTxt === '' || ! ctype_digit($anioTxt)) {
            return 'El año de siembra es obligatorio.';
        }

        $anio    = (int) $anioTxt;
        $anioMax = (int) date('Y') + 1;

        if ($anio < 1900 || $anio > $anioMax) {
            return "El año de siembra debe estar entre 1900 y {$anioMax}.";
        }

        $palmasBrutas = $this->enteroRequerido('palmasBrutas', 'Las palmas brutas');

        if (is_string($palmasBrutas)) {
            return $palmasBrutas;
        }

        $palmasProduccion = $this->enteroRequerido('palmasProduccion', 'Las palmas de producción');

        if (is_string($palmasProduccion)) {
            return $palmasProduccion;
        }

        $noLineas = $this->enteroRequerido('NoLineas', 'El número de líneas');

        if (is_string($noLineas)) {
            return $noLineas;
        }

        $numero = $this->enteroOpcional('numero', 'El número');

        if (is_string($numero)) {
            return $numero;
        }

        $hBrutas = $this->decimalOpcional('hBrutas', 'Las hectáreas brutas');

        if (is_string($hBrutas)) {
            return $hBrutas;
        }

        $hNetas = $this->decimalOpcional('hNetas', 'Las hectáreas netas');

        if (is_string($hNetas)) {
            return $hNetas;
        }

        $dSiembra = $this->decimalOpcional('dSiembra', 'La distancia de siembra');

        if (is_string($dSiembra)) {
            return $dSiembra;
        }

        $densidad = $this->decimalOpcional('densidad', 'La densidad');

        if (is_string($densidad)) {
            return $densidad;
        }

        $letra = $this->campo('letra');

        if (mb_strlen($letra) > 50) {
            return 'La letra no puede superar los 50 caracteres.';
        }

        return [
            'manejaSeccion'    => $manejaSeccion,
            'seccion'          => $seccion,
            'descripcion'      => $descripcion,
            'anioSiembra'      => $anio,
            'palmasBrutas'     => $palmasBrutas,
            'palmasProduccion' => $palmasProduccion,
            'hBrutas'          => $hBrutas,
            'hNetas'           => $hNetas,
            'dSiembra'         => $dSiembra,
            'variedad'         => $variedad,
            'densidad'         => $densidad,
            'NoLineas'         => $noLineas,
            'activo'           => $this->bit('activo'),
            'desarrollo'       => $this->bit('desarrollo'),
            'numero'           => $numero,
            'letra'            => $letra !== '' ? $letra : null,
            'ccosto'           => $ccosto,
        ];
    }

    /**
     * Valida una línea del detalle (aLotesDetalle) recibida por POST (alta/edición individual).
     *
     * @return array|string ['linea' => int, 'noPalma' => int, 'palmaErradicada' => ?int] o el mensaje de error.
     */
    private function datosLinea(): array|string
    {
        $lineaTxt = $this->campo('linea');

        if ($lineaTxt === '' || ! ctype_digit($lineaTxt) || (int) $lineaTxt < 1) {
            return 'El número de línea debe ser un entero mayor o igual a 1.';
        }

        $noPalmaTxt = $this->campo('noPalma');

        if ($noPalmaTxt === '' || ! ctype_digit($noPalmaTxt)) {
            return 'El número de palmas debe ser un entero mayor o igual a cero.';
        }

        $palmaErrTxt     = $this->campo('palmaErradicada');
        $palmaErradicada = null;

        if ($palmaErrTxt !== '') {
            if (! ctype_digit($palmaErrTxt)) {
                return 'Las palmas erradicadas deben ser un entero mayor o igual a cero.';
            }

            $palmaErradicada = (int) $palmaErrTxt;
        }

        return ['linea' => (int) $lineaTxt, 'noPalma' => (int) $noPalmaTxt, 'palmaErradicada' => $palmaErradicada];
    }

    /**
     * Valida los canales (aLotesCanal) recibidos del cliente.
     *
     * @return array|string Canales normalizados o el mensaje de error.
     */
    private function datosCanales(int $empresa, array $canales): array|string
    {
        $vistos    = [];
        $resultado = [];

        foreach ($canales as $c) {
            $tipoCanal = mb_strtoupper(trim((string) ($c['tipoCanal'] ?? '')));

            if ($tipoCanal === '') {
                return 'El tipo de canal es obligatorio.';
            }

            if (! $this->lotesModel->tipoCanalValido($empresa, $tipoCanal)) {
                return "El tipo de canal {$tipoCanal} no existe.";
            }

            if (isset($vistos[$tipoCanal])) {
                return "El tipo de canal {$tipoCanal} está repetido.";
            }

            $vistos[$tipoCanal] = true;

            $metros = $c['metros'] ?? null;

            if (! is_numeric($metros) || (float) $metros < 0) {
                return "Los metros del canal {$tipoCanal} deben ser un número mayor o igual a cero.";
            }

            $resultado[] = ['tipoCanal' => $tipoCanal, 'metros' => (float) $metros];
        }

        return $resultado;
    }

    /**
     * Entero >= 0 obligatorio recibido por POST.
     *
     * @return int|string El valor o el mensaje de error.
     */
    private function enteroRequerido(string $campo, string $etiqueta): int|string
    {
        $valor = $this->campo($campo);

        if ($valor === '' || ! ctype_digit($valor)) {
            return "{$etiqueta} debe ser un número entero mayor o igual a cero.";
        }

        return (int) $valor;
    }

    /**
     * Entero >= 0 opcional recibido por POST.
     *
     * @return int|string|null El valor, null si viene vacío, o el mensaje de error.
     */
    private function enteroOpcional(string $campo, string $etiqueta): int|string|null
    {
        $valor = $this->campo($campo);

        if ($valor === '') {
            return null;
        }

        if (! ctype_digit($valor)) {
            return "{$etiqueta} debe ser un número entero mayor o igual a cero.";
        }

        return (int) $valor;
    }

    /**
     * Decimal >= 0 recibido por POST, aceptando coma o punto decimal. Vacío se normaliza a 0.
     *
     * @return float|string El valor o el mensaje de error.
     */
    private function decimalOpcional(string $campo, string $etiqueta): float|string
    {
        $texto = str_replace([' ', '$'], '', $this->campo($campo));

        if ($texto === '') {
            return 0.0;
        }

        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $texto)) {
            $texto = str_replace(['.', ','], '', $texto);
        } else {
            $decimal = strrpos($texto, ',') > strrpos($texto, '.') ? ',' : '.';
            $texto   = str_replace($decimal === ',' ? '.' : ',', '', $texto);
            $texto   = str_replace(',', '.', $texto);
        }

        if (! is_numeric($texto) || (float) $texto < 0) {
            return "{$etiqueta} debe ser un número mayor o igual a cero.";
        }

        return (float) $texto;
    }

    /**
     * Decodifica un campo POST con un array JSON (los canales del lote). Cualquier valor inválido
     * se resuelve como array vacío; la validación de contenido corre por cuenta del llamador.
     */
    private function arrayJson(string $nombre): array
    {
        $valor = json_decode((string) $this->request->getPost($nombre), true);

        return is_array($valor) ? $valor : [];
    }

    /**
     * Respuesta estándar con la tabla re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaLotes(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->lotesModel->listar(
            $empresa,
            $this->campo('filtro_finca'),
            $this->campo('filtro_estado'),
            $this->campo('filtro_descuadre')
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasLotes($filas),
        ]);
    }

    /**
     * Respuesta estándar del modal de líneas: filas vigentes del lote más sus totales.
     */
    private function respuestaLineasLote(int $empresa, string $codigo, string $finca, string $mensaje): ResponseInterface
    {
        $lineas   = $this->lotesModel->getLineas($empresa, $codigo, $finca);
        $totales  = $this->lotesModel->totalesLineas($empresa, $codigo, $finca);

        return $this->response->setJSON([
            'success'  => true,
            'message'  => $mensaje,
            'lineas'   => array_map(static fn ($l) => [
                'linea'           => (int) $l['linea'],
                'noPalma'         => (int) $l['noPalma'],
                'palmaErradicada' => $l['palmaErradicada'] !== null ? (int) $l['palmaErradicada'] : null,
            ], $lineas),
            'totales' => $totales,
        ]);
    }

    /**
     * Formatea un decimal con 2 posiciones y recorta los ceros sobrantes (9,00 → 9; 9,50 se conserva).
     */
    private function numeroCompacto(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
    }

    /**
     * Construye las filas <tr> de la tabla de lotes, con el diseño de 10 columnas aprobado y el
     * badge de descuadre entre los campos tecleados del maestro y los totales del detalle (líneas).
     */
    private function renderizarFilasLotes(array $lotes): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        $vacio = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($lotes as $l) {
            $empresa = (int) $l['empresa'];
            $codigo  = trim((string) $l['codigo']);
            $finca   = trim((string) $l['finca']);
            $activo  = (int) $l['activo'] === 1 ? 1 : 0;
            $usos    = (int) ($l['usos'] ?? 0);

            $descripcion    = trim((string) $l['descripcion']);
            $fincaNombre    = trim((string) ($l['fincaDescripcion'] ?? ''));
            $variedadNombre = trim((string) ($l['variedadNombre'] ?? ''));
            $manejaSeccion  = (int) $l['manejaSeccion'] === 1;
            $seccion        = $l['seccion'] !== null ? trim((string) $l['seccion']) : '';
            $numero         = $l['numero'] !== null ? (int) $l['numero'] : null;
            $letra          = $l['letra'] !== null ? trim((string) $l['letra']) : '';
            $anioSiembra    = (int) $l['anioSiembra'];
            $desarrollo     = (int) $l['desarrollo'] === 1;

            $palmasBrutas      = (int) $l['palmasBrutas'];
            $palmasProduccion  = (int) $l['palmasProduccion'];
            $noLineas          = (int) $l['NoLineas'];
            $detalleLineas     = (int) ($l['detalleLineas'] ?? 0);
            $detallePalmas     = (int) ($l['detallePalmas'] ?? 0);
            $detalleProduccion = (int) ($l['detalleProduccion'] ?? 0);
            $hBrutas           = (float) $l['hBrutas'];
            $hNetas            = (float) $l['hNetas'];
            $densidad          = (float) $l['densidad'];
            $dSiembra          = (float) $l['dSiembra'];

            $mismatchPalmas = $palmasBrutas !== $detallePalmas || $palmasProduccion !== $detalleProduccion;
            $mismatchLineas = $noLineas !== $detalleLineas;
            $descuadre      = $mismatchPalmas || $mismatchLineas;

            $tituloDescuadre = 'El maestro declara ' . number_format($palmasBrutas, 0, ',', '.') . ' palmas brutas y '
                . number_format($palmasProduccion, 0, ',', '.') . ' de producción; el detalle de líneas suma '
                . number_format($detallePalmas, 0, ',', '.') . ' palmas en ' . number_format($detalleLineas, 0, ',', '.')
                . ' líneas. Verifique las líneas.';

            $badgeDescuadre = '<span class="badge gp-badge-cierre rounded-pill px-2 py-1" title="' . htmlspecialchars($tituloDescuadre) . '">'
                . '<i class="bi bi-exclamation-triangle-fill"></i></span>';

            // 1) Código
            $subCodigo = [];

            if ($numero !== null) {
                $subCodigo[] = 'N.° ' . $numero;
            }

            if ($letra !== '') {
                $subCodigo[] = htmlspecialchars($letra);
            }

            $celdaCodigo = '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span>'
                . ($subCodigo !== [] ? '<div class="gp-subtexto">' . implode(' · ', $subCodigo) . '</div>' : '');

            // 2) Descripción
            $celdaDescripcion = htmlspecialchars($descripcion)
                . ($anioSiembra > 0 ? '<div class="gp-subtexto">Siembra ' . $anioSiembra . '</div>' : '');

            // 3) Finca / Sección
            $subFinca = '';

            if ($manejaSeccion) {
                $subFinca = $seccion !== ''
                    ? '<div class="gp-subtexto">Sección: ' . htmlspecialchars($seccion) . '</div>'
                    : '<div class="gp-subtexto">Sección: &mdash;</div>';
            }

            $celdaFinca = ($fincaNombre !== '' ? htmlspecialchars($fincaNombre) : htmlspecialchars($finca)) . $subFinca;

            // 4) Variedad
            $celdaVariedad = $variedadNombre !== '' ? htmlspecialchars($variedadNombre) : $vacio;

            // 5) Palmas
            $celdaPalmas = number_format($palmasBrutas, 0, ',', '.') . ' brutas · '
                . number_format($palmasProduccion, 0, ',', '.') . ' prod.'
                . ($mismatchPalmas ? ' ' . $badgeDescuadre : '');

            // 6) Líneas
            $celdaLineas = number_format($noLineas, 0, ',', '.') . ($mismatchLineas ? ' ' . $badgeDescuadre : '');

            // 7) Hectáreas
            $celdaHectareas = number_format($hBrutas, 2, ',', '.') . ' / ' . number_format($hNetas, 2, ',', '.')
                . ' <span class="gp-etiqueta-mini">ha</span>';

            // 8) Densidad / Distancia
            $celdaDensidad = $this->numeroCompacto($densidad) . ' palma/ha · ' . $this->numeroCompacto($dSiembra) . ' m';

            // 9) Estado
            $celdaEstado = ($activo
                    ? '<span class="gp-chip-rol activo" title="Lote activo">ACT</span>'
                    : '<span class="gp-chip-rol" title="Lote inactivo">ACT</span>')
                . ' '
                . ($desarrollo
                    ? '<span class="gp-chip-rol activo" title="Lote en desarrollo">DES</span>'
                    : '<span class="gp-chip-rol" title="Lote fuera de desarrollo">DES</span>');

            $tituloEstado = $activo ? 'Desactivar lote' : 'Activar lote';
            $iconoEstado  = $activo ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($codigo) . '&#039;,&#039;' . $aJs($finca) . '&#039;';
            $descJs  = $aJs($descripcion);

            $usosFmt = number_format($usos, 0, ',', '.');

            $botonEliminar = $usos > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarLote(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            $tituloAcciones = $usos > 0
                ? ' title="No se puede eliminar: el lote tiene ' . $usosFmt . ' registros asociados."'
                : '';

            $tituloLineas = $detalleLineas === 1 ? '1 línea registrada' : number_format($detalleLineas, 0, ',', '.') . ' líneas registradas';

            // 10) Acciones
            $celdaAcciones = '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarLote(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-secondary" title="' . htmlspecialchars($tituloLineas) . '" onclick="lineasLote(' . $claveJs . ')"><i class="bi bi-list-ol"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoLote(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . htmlspecialchars($codigo)
                . '" data-finca="' . htmlspecialchars($finca) . '" data-activo="' . $activo . '" data-usos="' . $usos
                . '" data-descuadre="' . ($descuadre ? 1 : 0) . '">'
                . '<td>' . $celdaCodigo . '</td>'
                . '<td>' . $celdaDescripcion . '</td>'
                . '<td data-order="' . htmlspecialchars($fincaNombre !== '' ? $fincaNombre : $finca) . '">' . $celdaFinca . '</td>'
                . '<td class="text-center">' . $celdaVariedad . '</td>'
                . '<td class="text-end font-monospace" data-order="' . $palmasBrutas . '">' . $celdaPalmas . '</td>'
                . '<td class="text-end font-monospace" data-order="' . $noLineas . '">' . $celdaLineas . '</td>'
                . '<td class="text-end font-monospace" data-order="' . $hBrutas . '">' . $celdaHectareas . '</td>'
                . '<td>' . $celdaDensidad . '</td>'
                . '<td class="text-center" data-order="' . ($activo * 2 + ($desarrollo ? 1 : 0)) . '">' . $celdaEstado . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>' . $celdaAcciones . '</td>'
                . '</tr>';
        }

        return $html;
    }

    /**
     * Valida el año recibido por POST, al crear un peso (2000..2100).
     *
     * @return int|array El año o [mensaje de error].
     */
    private function anioValido(): int|array
    {
        $texto = $this->campo('anio');

        if ($texto === '' || ! ctype_digit($texto)) {
            return ['El año es obligatorio.'];
        }

        $anio = (int) $texto;

        if ($anio < 2000 || $anio > 2100) {
            return ['El año debe estar entre 2000 y 2100.'];
        }

        return $anio;
    }

    /**
     * Valida el mes recibido por POST, al crear un peso (1..13: el 13 es el cierre de año,
     * igual que en cPeriodo).
     *
     * @return int|array El mes o [mensaje de error].
     */
    private function mesValido(): int|array
    {
        $texto = $this->campo('mes');

        if ($texto === '' || ! ctype_digit($texto)) {
            return ['El mes es obligatorio.'];
        }

        $mes = (int) $texto;

        if ($mes < 1 || $mes > 13) {
            return ['El mes debe estar entre 1 y 13.'];
        }

        return $mes;
    }

    /**
     * Valida la finca recibida por POST, contra el catálogo aFinca.
     *
     * @return string|array El código de finca recortado o [mensaje de error].
     */
    private function fincaValidaPeso(int $empresa): string|array
    {
        $finca = $this->campo('finca');

        if ($finca === '') {
            return ['La finca es obligatoria.'];
        }

        if (! $this->pesosLoteModel->fincaValida($empresa, $finca)) {
            return ['La finca seleccionada no existe.'];
        }

        return $finca;
    }

    /**
     * Llave completa (año, mes, finca, lote) recibida por POST al actualizar/eliminar. No se
     * valida el rango de año/mes: las filas con datos legado inválidos deben poder editarse o
     * borrarse igual, sin poder replicarlas.
     *
     * @return array|string [anio, mes, finca, lote] o el mensaje de error.
     */
    private function claveVigente(int $empresa): array|string
    {
        $anioTxt = $this->campo('anio');
        $mesTxt  = $this->campo('mes');
        $finca   = $this->campo('finca');
        $lote    = $this->campo('lote');

        if ($anioTxt === '' || ! ctype_digit($anioTxt) || $mesTxt === '' || ! ctype_digit($mesTxt) || $finca === '' || $lote === '') {
            return 'El registro indicado no es válido.';
        }

        $anio = (int) $anioTxt;
        $mes  = (int) $mesTxt;

        if (! $this->pesosLoteModel->existe($empresa, $anio, $mes, $finca, $lote)) {
            return 'El registro no existe.';
        }

        return [$anio, $mes, $finca, $lote];
    }

    /**
     * Peso del racimo (>= 0) recibido por POST, aceptando coma o punto decimal. Cero es un valor
     * legítimo y se acepta explícitamente.
     *
     * @return float|string El valor o el mensaje de error.
     */
    private function pesoRacimoValido(): float|string
    {
        $texto = str_replace([' ', '$'], '', $this->campo('pesoRacimo'));

        if ($texto === '') {
            return 'El peso del racimo es obligatorio.';
        }

        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $texto)) {
            $texto = str_replace(['.', ','], '', $texto);
        } else {
            $decimal = strrpos($texto, ',') > strrpos($texto, '.') ? ',' : '.';
            $texto   = str_replace($decimal === ',' ? '.' : ',', '', $texto);
            $texto   = str_replace(',', '.', $texto);
        }

        if (! is_numeric($texto) || (float) $texto < 0) {
            return 'El peso del racimo debe ser un número mayor o igual a cero.';
        }

        return (float) $texto;
    }

    /**
     * Valida el rango de fechas del formulario del peso.
     *
     * @return array|string Par [fechaInicial, fechaFinal] o el mensaje de error.
     */
    private function fechasValidasPeso(): array|string
    {
        $inicial = $this->campo('fechaInicial');
        $final   = $this->campo('fechaFinal');

        $inicial = $inicial !== '' ? $inicial : null;
        $final   = $final !== '' ? $final : null;

        if ($inicial !== null && $final !== null && strtotime($final) < strtotime($inicial)) {
            return 'La fecha final no puede ser anterior a la fecha inicial.';
        }

        return [$inicial, $final];
    }

    /**
     * Valida el formulario del peso y arma las columnas a persistir (pesoRacimo, automatico,
     * fechaInicial, fechaFinal). No incluye la llave ni "seccion": se dejan intactas.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosPeso(): array|string
    {
        $peso = $this->pesoRacimoValido();

        if (is_string($peso)) {
            return $peso;
        }

        $fechas = $this->fechasValidasPeso();

        if (is_string($fechas)) {
            return $fechas;
        }

        return [
            'pesoRacimo'   => $peso,
            'automatico'   => $this->bit('automatico'),
            'fechaInicial' => $fechas[0],
            'fechaFinal'   => $fechas[1],
        ];
    }

    /**
     * Respuesta estándar con la tabla de pesos re-renderizada según los filtros vigentes. Si el
     * filtro "problemas" está activo, el periodo puede quedar vacío (pasada de limpieza sin
     * restringir a un año/mes); si no, cae de vuelta al periodo de la fila recién creada/editada/
     * eliminada para no quedarse sin período.
     */
    private function respuestaTablaPesos(int $empresa, ?int $anio, ?int $mes, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filtroAnio      = (string) $this->request->getPost('filtro_anio');
        $filtroMes       = (string) $this->request->getPost('filtro_mes');
        $filtroProblemas = $this->campo('filtro_problemas');

        $anioFiltrado = $filtroAnio !== '' && ctype_digit($filtroAnio) ? (int) $filtroAnio : null;
        $mesFiltrado  = $filtroMes !== '' && ctype_digit($filtroMes) ? (int) $filtroMes : null;

        if ($filtroProblemas === '1') {
            $anio = $anioFiltrado;
            $mes  = $mesFiltrado;
        } else {
            $anio = $anioFiltrado ?? $anio;
            $mes  = $mesFiltrado ?? $mes;
        }

        $filas = $this->pesosLoteModel->listar(
            $empresa, $anio, $mes,
            $this->campo('filtro_finca'), $this->campo('filtro_origen'), $filtroProblemas
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasPesos($filas),
        ]);
    }

    /**
     * Badge de error para un problema real de datos legado (mes/año fuera de rango o lote
     * inexistente). "gp-badge-cierre" queda reservado para el cierre de año (mes 13), así que
     * este usa utilidades de Bootstrap para no confundirse con él.
     */
    private function badgeProblema(string $titulo): string
    {
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" title="' . htmlspecialchars($titulo) . '">'
            . '<i class="bi bi-exclamation-triangle-fill"></i></span>';
    }

    /**
     * Normaliza una fecha proveniente de la BD (DateTime o cadena).
     */
    private function formatearFecha($fecha, string $formato): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format($formato);
        }

        if ($fecha === null || $fecha === '') {
            return null;
        }

        $ts = strtotime((string) $fecha);

        return $ts === false ? null : date($formato, $ts);
    }

    /**
     * Construye las filas <tr> de la tabla de pesos, con las 7 columnas aprobadas y las
     * banderas de datos legado (mes/año inválidos, lote inexistente, peso en cero) resueltas
     * en la misma consulta del listado.
     */
    private function renderizarFilasPesos(array $pesos): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        $vacio = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($pesos as $p) {
            $empresa = (int) $p['empresa'];
            $anio    = (int) $p['anio'];
            $mes     = (int) $p['mes'];
            $finca   = trim((string) $p['finca']);
            $lote    = trim((string) $p['lote']);

            $fincaNombre = trim((string) ($p['fincaDescripcion'] ?? ''));
            $automatico  = (int) $p['automatico'] === 1;
            $pesoRacimo  = (float) $p['pesoRacimo'];

            $loteInexistente = (int) ($p['loteInexistente'] ?? 0) === 1;
            $mesInvalido      = (int) ($p['mesInvalido'] ?? 0) === 1;
            $anioInvalido     = (int) ($p['anioInvalido'] ?? 0) === 1;
            $esCierre         = $mes === 13 && ! $mesInvalido;
            $conProblemas     = $loteInexistente || $mesInvalido || $anioInvalido;

            // 1) Periodo
            if ($mesInvalido || $anioInvalido) {
                $celdaPeriodo = '<span class="gp-dato-legado" title="Período inválido en la base de datos">' . $mes . '/' . $anio . '</span>'
                    . ' ' . $this->badgeProblema('Mes o año fuera de rango.');
            } elseif ($esCierre) {
                $celdaPeriodo = '<span class="badge gp-badge-cierre rounded-pill px-2 py-1">Cierre del año ' . $anio . '</span>';
            } else {
                $celdaPeriodo = '<span class="badge gp-badge-doc rounded-pill px-2 py-1">' . self::MESES[$mes] . ' ' . $anio . '</span>';
            }

            $ordenPeriodo = $anio * 100 + $mes;

            // 2) Finca
            $celdaFinca = ($fincaNombre !== '' ? htmlspecialchars($fincaNombre) : htmlspecialchars($finca))
                . '<div class="gp-subtexto">' . htmlspecialchars($finca) . '</div>';

            // 3) Lote
            $celdaLote = $loteInexistente
                ? '<span class="gp-dato-legado" title="El lote no existe en aLotes para esta finca">' . htmlspecialchars($lote) . '</span> '
                    . $this->badgeProblema('El lote no existe en aLotes para esta finca.')
                : '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($lote) . '</span>';

            // 4) Peso
            $celdaPeso = number_format($pesoRacimo, 3, ',', '.');

            // 5) Origen
            $celdaOrigen = $automatico
                ? '<span class="gp-chip-rol activo" title="Peso calculado automáticamente">AUTO</span>'
                : '<span class="gp-chip-rol" title="Peso registrado manualmente">MANUAL</span>';

            // 6) Vigencia
            $inicial = $this->formatearFecha($p['fechaInicial'], 'd/m/Y');
            $final   = $this->formatearFecha($p['fechaFinal'], 'd/m/Y');

            if ($inicial !== null && $final !== null) {
                $celdaVigencia = '<span class="gp-rango-fecha">' . $inicial . ' &ndash; ' . $final . '</span>';
            } elseif ($inicial !== null || $final !== null) {
                $celdaVigencia = '<span class="gp-rango-fecha">' . ($inicial ?? $final) . '</span>';
            } else {
                $celdaVigencia = $vacio;
            }

            $ordenVigencia = $this->formatearFecha($p['fechaInicial'], 'Y-m-d') ?? '';

            $claveJs = $empresa . ',' . $anio . ',' . $mes . ',&#039;' . $aJs($finca) . '&#039;,&#039;' . $aJs($lote) . '&#039;';

            // 7) Acciones
            $celdaAcciones = '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarPeso(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarPeso(' . $claveJs . ')"><i class="bi bi-trash"></i></button>'
                . '</div>';

            $html .= '<tr' . ($esCierre ? ' class="gp-fila-cierre"' : '')
                . ' data-empresa="' . $empresa . '" data-anio="' . $anio . '" data-mes="' . $mes
                . '" data-finca="' . htmlspecialchars($finca) . '" data-lote="' . htmlspecialchars($lote)
                . '" data-problemas="' . ($conProblemas ? 1 : 0) . '">'
                . '<td data-order="' . $ordenPeriodo . '">' . $celdaPeriodo . '</td>'
                . '<td data-order="' . htmlspecialchars($fincaNombre !== '' ? $fincaNombre : $finca) . '">' . $celdaFinca . '</td>'
                . '<td class="text-center">' . $celdaLote . '</td>'
                . '<td class="text-end font-monospace" data-order="' . $pesoRacimo . '">' . $celdaPeso . '</td>'
                . '<td class="text-center" data-order="' . ($automatico ? 1 : 0) . '">' . $celdaOrigen . '</td>'
                . '<td data-order="' . $ordenVigencia . '">' . $celdaVigencia . '</td>'
                . '<td class="text-center gp-col-sticky">' . $celdaAcciones . '</td>'
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
