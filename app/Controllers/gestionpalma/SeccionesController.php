<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\SeccionesModel;

class SeccionesController extends GestionPalmaController
{
    private $seccionesModel;

    public function __construct()
    {
        parent::__construct();

        $this->seccionesModel = new SeccionesModel();
    }

    /**
     * Vista principal del módulo "Secciones / Bloques".
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

        $empresas   = $this->seccionesModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas    = $empresaSel > 0 ? $this->seccionesModel->listar($empresaSel) : [];
        $totales  = $empresaSel > 0 ? $this->seccionesModel->totalesHectareas($empresaSel, '') : ['ha_asignadas' => null, 'ha_finca' => null];

        $data['title']            = 'Gestión Palma · Secciones / Bloques';
        $data['empresas']         = $empresas;
        $data['empresa_sel']      = $empresaSel;
        $data['fincas']           = $empresaSel > 0 ? $this->seccionesModel->getFincas($empresaSel) : [];
        $data['tabla_secciones']  = $this->renderizarFilasSecciones($filas);
        $data['total_secciones']  = count($filas);
        $data['ha_asignadas']     = $totales['ha_asignadas'];
        $data['ha_finca']         = $totales['ha_finca'];

        return view('gestionpalma/secciones', $data);
    }

    // ── Endpoints JSON ────────────────────────────────────────────────────────

    public function listarSecciones(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca   = $this->campo('finca');
        $estado  = $this->campo('estado');
        $filas   = $this->seccionesModel->listar($empresa, $finca, $estado);
        $totales = $this->seccionesModel->totalesHectareas($empresa, $finca, $estado);

        return $this->response->setJSON([
            'success'      => true,
            'message'      => '',
            'total'        => count($filas),
            'tabla'        => $this->renderizarFilasSecciones($filas),
            'ha_asignadas' => $totales['ha_asignadas'],
            'ha_finca'     => $totales['ha_finca'],
        ]);
    }

    public function obtenerSeccion(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $seccion = $this->seccionesModel->obtener($empresa, $this->campo('finca'), $this->campo('codigo'));

        if ($seccion === null) {
            return $this->error('La sección no existe.');
        }

        return $this->response->setJSON([
            'success'  => true,
            'seccion'  => [
                'empresa'     => (int) $seccion['empresa'],
                'finca'       => trim((string) $seccion['finca']),
                'fincaNombre' => trim((string) ($seccion['fincaDescripcion'] ?? '')),
                'codigo'      => trim((string) $seccion['codigo']),
                'descripcion' => (string) $seccion['descripcion'],
                'hBrutas'     => $seccion['hBrutas'] === null ? null : (float) $seccion['hBrutas'],
                'activo'      => (int) $seccion['activo'],
                'usos'        => (int) ($seccion['usos'] ?? 0),
            ],
        ]);
    }

    public function crearSeccion(): ResponseInterface
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

        if ($this->seccionesModel->existe($empresa, $finca, $codigo)) {
            return $this->error("Ya existe una sección con el código {$codigo} en la finca {$finca}.");
        }

        $datos = $this->datosSeccion();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->seccionesModel->crear($empresa, $finca, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaSecciones($empresa, 'Sección creada correctamente.');
    }

    public function actualizarSeccion(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca  = $this->campo('finca');
        $codigo = $this->campo('codigo');

        if (! $this->seccionesModel->existe($empresa, $finca, $codigo)) {
            return $this->error('La sección no existe.');
        }

        $datos = $this->datosSeccion();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->seccionesModel->actualizar($empresa, $finca, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaSecciones($empresa, 'Sección actualizada correctamente.');
    }

    public function eliminarSeccion(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca  = $this->campo('finca');
        $codigo = $this->campo('codigo');

        if (! $this->seccionesModel->existe($empresa, $finca, $codigo)) {
            return $this->error('La sección no existe.');
        }

        $enUso = $this->seccionesModel->enUso($empresa, $finca, $codigo);

        if ($enUso > 0) {
            return $this->error('No se puede eliminar: la sección tiene ' . number_format($enUso, 0, ',', '.') . ' registros asociados.');
        }

        $this->seccionesModel->eliminar($empresa, $finca, $codigo, $this->session->get('usu_id'));

        return $this->respuestaTablaSecciones($empresa, 'Sección eliminada correctamente.');
    }

    public function cambiarEstadoSeccion(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca   = $this->campo('finca');
        $codigo  = $this->campo('codigo');
        $seccion = $this->seccionesModel->obtener($empresa, $finca, $codigo);

        if ($seccion === null) {
            return $this->error('La sección no existe.');
        }

        $activo = (int) $seccion['activo'] === 1 ? 0 : 1;

        $this->seccionesModel->cambiarEstado($empresa, $finca, $codigo, $activo, $this->session->get('usu_id'));

        return $this->respuestaTablaSecciones(
            $empresa,
            $activo === 1 ? 'Sección activada correctamente.' : 'Sección desactivada correctamente.'
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

        if ($empresa === null || ! is_numeric($empresa) || ! $this->seccionesModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
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

        if (! $this->seccionesModel->fincaValida($empresa, $finca)) {
            return ['La finca seleccionada no existe.'];
        }

        return $finca;
    }

    /**
     * Valida el código recibido por POST.
     *
     * @return string|array El código recortado y en mayúsculas, o [mensaje de error].
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

        if (preg_match('/\s/', $codigo)) {
            return ['El código no puede contener espacios.'];
        }

        return $codigo;
    }

    /**
     * Valida el formulario de la sección y arma las columnas a persistir.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosSeccion(): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (strlen($descripcion) > 550) {
            return 'La descripción no puede superar los 550 caracteres.';
        }

        $hBrutasTxt = str_replace([' ', '$'], '', $this->campo('hBrutas'));
        $hBrutas    = null;

        if ($hBrutasTxt !== '') {
            if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $hBrutasTxt)) {
                $hBrutasTxt = str_replace(['.', ','], '', $hBrutasTxt);
            } else {
                $decimal    = strrpos($hBrutasTxt, ',') > strrpos($hBrutasTxt, '.') ? ',' : '.';
                $hBrutasTxt = str_replace($decimal === ',' ? '.' : ',', '', $hBrutasTxt);
                $hBrutasTxt = str_replace(',', '.', $hBrutasTxt);
            }

            if (! is_numeric($hBrutasTxt) || (float) $hBrutasTxt < 0) {
                return 'Las hectáreas brutas deben ser un número mayor o igual a cero.';
            }

            $hBrutas = (float) $hBrutasTxt;
        }

        return [
            'descripcion' => $descripcion,
            'hBrutas'     => $hBrutas,
            'activo'      => $this->bit('activo'),
        ];
    }

    /**
     * Respuesta estándar con la tabla re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaSecciones(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->seccionesModel->listar($empresa, $this->campo('filtro_finca'), $this->campo('filtro_estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasSecciones($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de secciones.
     */
    private function renderizarFilasSecciones(array $secciones): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        $vacio = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($secciones as $s) {
            $empresa = (int) $s['empresa'];
            $finca   = trim((string) $s['finca']);
            $codigo  = trim((string) $s['codigo']);
            $activo  = (int) $s['activo'] === 1 ? 1 : 0;
            $usos    = (int) ($s['usos'] ?? 0);

            $descripcion  = trim((string) $s['descripcion']);
            $fincaNombre  = trim((string) ($s['fincaDescripcion'] ?? ''));
            $hBrutas      = $s['hBrutas'] === null ? null : (float) $s['hBrutas'];

            $celdaFinca = ($fincaNombre !== '' ? htmlspecialchars($fincaNombre) : htmlspecialchars($finca))
                . '<div class="gp-subtexto">' . htmlspecialchars($finca) . '</div>';

            $celdaHBrutas = $hBrutas === null
                ? $vacio
                : number_format($hBrutas, 2, ',', '.') . ' <span class="gp-etiqueta-mini">ha</span>';

            $usosFmt = number_format($usos, 0, ',', '.');

            $celdaUsos = $usos > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" title="' . $usosFmt
                    . ' registros asociados (lotes, pesos por período, sanidad y transacciones); no se puede eliminar mientras esté en uso.">'
                    . $usosFmt . '</span>'
                : '<span class="gp-chip-rol">0</span>';

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar sección' : 'Activar sección';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($finca) . '&#039;,&#039;' . $aJs($codigo) . '&#039;';
            $descJs  = $aJs($descripcion);

            $botonEliminar = $usos > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarSeccion(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            $tituloAcciones = $usos > 0
                ? ' title="No se puede eliminar: la sección tiene ' . $usosFmt . ' registros asociados."'
                : '';

            $html .= '<tr data-empresa="' . $empresa . '" data-finca="' . htmlspecialchars($finca)
                . '" data-codigo="' . htmlspecialchars($codigo) . '" data-activo="' . $activo . '" data-usos="' . $usos . '">'
                . '<td class="text-center"><span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span></td>'
                . '<td data-order="' . htmlspecialchars($fincaNombre !== '' ? $fincaNombre : $finca) . '">' . $celdaFinca . '</td>'
                . '<td>' . htmlspecialchars($descripcion) . '</td>'
                . '<td class="text-end font-monospace" data-order="' . ($hBrutas ?? -1) . '">' . $celdaHBrutas . '</td>'
                . '<td class="text-center" data-order="' . $usos . '">' . $celdaUsos . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarSeccion(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoSeccion(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
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
