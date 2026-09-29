<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\CaracteristicasModel;

class CaracteristicasController extends GestionPalmaController
{
    private $caracteristicasModel;

    public function __construct()
    {
        parent::__construct();

        $this->caracteristicasModel = new CaracteristicasModel();
    }

    /**
     * Vista principal del módulo "Características / Registro".
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

        $empresas   = $this->caracteristicasModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas     = $empresaSel > 0 ? $this->caracteristicasModel->listar($empresaSel) : [];
        $variantes = $empresaSel > 0 ? $this->caracteristicasModel->variantes($empresaSel) : [];

        $data['title']                 = 'Gestión Palma · Registro de características';
        $data['empresas']              = $empresas;
        $data['empresa_sel']           = $empresaSel;
        $data['grupos']                = $empresaSel > 0 ? $this->caracteristicasModel->getGrupos($empresaSel) : [];
        $data['variantes']             = $variantes;
        $data['tabla_caracteristicas'] = $this->renderizarFilasCaracteristicas($filas, $variantes);
        $data['total_caracteristicas'] = count($filas);
        $data['siguiente_codigo']      = $empresaSel > 0 ? $this->caracteristicasModel->siguienteCodigo($empresaSel) : 1;

        return view('gestionpalma/caracteristicas_registro', $data);
    }

    // ── Endpoints JSON: características (aCaracteristica) ─────────────────────

    public function listarCaracteristicas(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        return $this->respuestaTabla($empresa, '');
    }

    public function obtenerCaracteristica(): ResponseInterface
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

        $caracteristica = $this->caracteristicasModel->obtener($empresa, $codigo);

        if ($caracteristica === null) {
            return $this->error('La característica no existe.');
        }

        $grupo = $caracteristica['grupoCaracteristica'];

        return $this->response->setJSON([
            'success'        => true,
            'message'        => '',
            'caracteristica' => [
                'codigo'           => (int) $caracteristica['codigo'],
                'descripcion'      => trim((string) $caracteristica['descripcion']),
                'manejaGrupo'      => (int) $caracteristica['manejaCaractistica'] === 1 ? 1 : 0,
                'grupo'            => $grupo === null ? null : (int) $grupo,
                'grupoDescripcion' => trim((string) ($caracteristica['grupoDescripcion'] ?? '')),
                'activo'           => (int) $caracteristica['activo'] === 1 ? 1 : 0,
                'usos'             => (int) ($caracteristica['usos'] ?? 0),
            ],
        ]);
    }

    public function crearCaracteristica(): ResponseInterface
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

        if ($this->caracteristicasModel->existe($empresa, $codigo)) {
            return $this->error("Ya existe una característica con el código {$codigo}.");
        }

        $datos = $this->datosCaracteristica($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if ($this->caracteristicasModel->descripcionDuplicada($empresa, $datos['descripcion'], $datos['grupoCaracteristica'])) {
            return $this->error($this->mensajeDuplicado($empresa, $datos));
        }

        if (! $this->caracteristicasModel->crear($empresa, $codigo, $datos, $this->session->get('usu_id'))) {
            return $this->error('No fue posible crear la característica.');
        }

        return $this->respuestaTabla($empresa, 'Característica creada correctamente.', $codigo);
    }

    public function actualizarCaracteristica(): ResponseInterface
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

        if (! $this->caracteristicasModel->existe($empresa, $codigo)) {
            return $this->error('La característica no existe.');
        }

        $datos = $this->datosCaracteristica($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if ($this->caracteristicasModel->descripcionDuplicada($empresa, $datos['descripcion'], $datos['grupoCaracteristica'], $codigo)) {
            return $this->error($this->mensajeDuplicado($empresa, $datos));
        }

        if (! $this->caracteristicasModel->actualizar($empresa, $codigo, $datos, $this->session->get('usu_id'))) {
            return $this->error('No fue posible actualizar la característica.');
        }

        return $this->respuestaTabla($empresa, 'Característica actualizada correctamente.', $codigo);
    }

    public function eliminarCaracteristica(): ResponseInterface
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

        if (! $this->caracteristicasModel->existe($empresa, $codigo)) {
            return $this->error('La característica no existe.');
        }

        $enUso = $this->caracteristicasModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error('No se puede eliminar: la característica tiene '
                . number_format($enUso, 0, ',', '.') . ' movimientos asociados.');
        }

        if (! $this->caracteristicasModel->eliminar($empresa, $codigo, $this->session->get('usu_id'))) {
            return $this->error('No fue posible eliminar la característica.');
        }

        return $this->respuestaTabla($empresa, 'Característica eliminada correctamente.');
    }

    public function cambiarEstadoCaracteristica(): ResponseInterface
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

        if (! $this->caracteristicasModel->existe($empresa, $codigo)) {
            return $this->error('La característica no existe.');
        }

        $activo = $this->bitValido('activo', 'estado');

        if (is_array($activo)) {
            return $this->error($activo[0]);
        }

        if (! $this->caracteristicasModel->cambiarEstado($empresa, $codigo, $activo, $this->session->get('usu_id'))) {
            return $this->error('No fue posible cambiar el estado de la característica.');
        }

        return $this->respuestaTabla(
            $empresa,
            $activo === 1 ? 'Característica activada correctamente.' : 'Característica desactivada correctamente.',
            $codigo
        );
    }

    /**
     * Código sugerido para prellenar el formulario. Es una sugerencia, no una reserva: dos usuarios
     * simultáneos reciben el mismo número y el segundo alta será rechazada por código duplicado.
     */
    public function siguienteCodigoCaracteristica(): ResponseInterface
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
            'message' => '',
            'codigo'  => $this->caracteristicasModel->siguienteCodigo($empresa),
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

        if ($empresa === null || ! is_numeric($empresa) || ! $this->caracteristicasModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
    }

    /**
     * Valida el código recibido por POST (entero >= 1).
     *
     * @return int|array El código o [mensaje de error].
     */
    private function codigoValido(): int|array
    {
        $codigo = $this->campo('codigo');

        if ($codigo === '' || ! ctype_digit($codigo) || (int) $codigo < 1) {
            return ['El código debe ser un número entero mayor o igual a 1.'];
        }

        return (int) $codigo;
    }

    /**
     * Valida el formulario y arma las columnas a persistir. No incluye codigo (llave).
     * El grupo '0' es válido, por lo que se distingue de la cadena vacía antes de castear;
     * cuando no se maneja grupo se persiste NULL. manejaCaractistica se persiste tal como llega
     * (0/1 validado), nunca deducido de si hay grupo o no.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosCaracteristica(int $empresa): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (strlen($descripcion) > 500) {
            return 'La descripción no puede superar los 500 caracteres.';
        }

        $manejaGrupo = $this->bitValido('manejaGrupo', 'maneja grupo');

        if (is_array($manejaGrupo)) {
            return $manejaGrupo[0];
        }

        $activo = $this->bitValido('activo', 'estado');

        if (is_array($activo)) {
            return $activo[0];
        }

        $grupo = null;

        if ($manejaGrupo === 1) {
            $recibido = $this->campo('grupo');

            if ($recibido === '') {
                return 'El grupo es obligatorio cuando la característica maneja grupo.';
            }

            if (! ctype_digit($recibido)) {
                return 'El grupo seleccionado no es válido.';
            }

            $grupo = (int) $recibido;

            if (! $this->caracteristicasModel->grupoExiste($empresa, $grupo)) {
                return 'El grupo seleccionado no existe.';
            }
        }

        return [
            'descripcion'         => $descripcion,
            'manejaCaractistica'  => $manejaGrupo,
            'grupoCaracteristica' => $grupo,
            'activo'              => $activo,
        ];
    }

    /**
     * Mensaje de duplicado que nombra el grupo en el que ya existe la descripción.
     */
    private function mensajeDuplicado(int $empresa, array $datos): string
    {
        $grupo = $datos['grupoCaracteristica'];

        if ($grupo === null) {
            return 'Ya existe una característica con la descripción «' . $datos['descripcion'] . '» sin grupo.';
        }

        $nombre = $this->caracteristicasModel->nombreGrupo($empresa, $grupo);

        return 'Ya existe una característica con la descripción «' . $datos['descripcion']
            . '» en el grupo «' . ($nombre !== '' ? $nombre : (string) $grupo) . '».';
    }

    /**
     * Respuesta estándar con la tabla re-renderizada según los filtros vigentes. Los filtros viajan
     * con el prefijo f_ para no confundirse con el campo "grupo" del registro que se está guardando.
     */
    private function respuestaTabla(int $empresa, string $mensaje, ?int $codigo = null): ResponseInterface
    {
        $filas = $this->caracteristicasModel->listar(
            $empresa,
            $this->campo('f_grupo'),
            $this->campo('f_estado'),
            $this->campo('f_busqueda')
        );

        $variantes = $this->caracteristicasModel->variantes($empresa);

        $salida = [
            'success'   => true,
            'message'   => $mensaje,
            'total'     => count($filas),
            'tabla'     => $this->renderizarFilasCaracteristicas($filas, $variantes),
            'variantes' => $variantes,
        ];

        if ($codigo !== null) {
            $salida['codigo'] = $codigo;
        }

        return $this->response->setJSON($salida);
    }

    /**
     * Construye las filas <tr> de la tabla de características.
     *
     * @param array $variantes Universo completo de la empresa (ver CaracteristicasModel::variantes):
     *                         la marca de variante se calcula sobre él, no sobre las filas filtradas.
     */
    private function renderizarFilasCaracteristicas(array $caracteristicas, array $variantes): string
    {
        $html = '';

        foreach ($caracteristicas as $c) {
            $codigo = (int) $c['codigo'];
            $activo = (int) $c['activo'] === 1 ? 1 : 0;
            $usos   = (int) ($c['usos'] ?? 0);
            $grupo  = $c['grupoCaracteristica'];

            $descripcion      = trim((string) $c['descripcion']);
            $grupoDescripcion = trim((string) ($c['grupoDescripcion'] ?? ''));

            $celdaCodigo = '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . $codigo . '</span>';

            $celdaGrupo = $grupo === null
                ? '<span class="gp-chip-rol">Sin grupo</span>'
                : '<span class="badge gp-badge-doc rounded-pill px-2 py-1">'
                    . htmlspecialchars($grupoDescripcion !== '' ? $grupoDescripcion : (string) (int) $grupo) . '</span>';

            $celdaDescripcion = '<span class="gp-truncar-ancho" title="' . htmlspecialchars($descripcion) . '">'
                . htmlspecialchars($descripcion) . '</span>'
                . $this->marcaVariante($variantes, $descripcion, $codigo);

            $celdaUso = $usos > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1" title="Tiene ' . $usos
                    . ($usos === 1 ? ' registro asociado' : ' registros asociados')
                    . '; no se puede eliminar mientras esté en uso.">En uso</span>'
                : '<span class="gp-chip-rol">Sin uso</span>';

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar característica' : 'Activar característica';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $tituloAcciones = $usos > 0
                ? ' title="No se puede eliminar: la característica tiene movimientos asociados."'
                : '';

            $botonEliminar = $usos > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" data-codigo="' . $codigo . '" data-accion="eliminar"><i class="bi bi-trash"></i></button>';

            $html .= '<tr data-codigo="' . $codigo . '" data-activo="' . $activo
                . '" data-grupo="' . ($grupo === null ? '' : (int) $grupo) . '">'
                . '<td class="text-center">' . $celdaCodigo . '</td>'
                . '<td>' . $celdaDescripcion . '</td>'
                . '<td data-order="' . ($grupo === null ? 9999 : (int) $grupo) . '">' . $celdaGrupo . '</td>'
                . '<td class="text-center" data-order="' . $usos . '">' . $celdaUso . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" data-codigo="' . $codigo . '" data-accion="editar"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" data-codigo="' . $codigo . '" data-accion="estado"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    /**
     * Marca la descripción que también existe en otros grupos, nombrándolos en el title.
     * Devuelve cadena vacía cuando la descripción es única en la empresa.
     */
    private function marcaVariante(array $variantes, string $descripcion, int $codigo): string
    {
        $entradas = $variantes[CaracteristicasModel::normalizar($descripcion)] ?? [];

        if (count($entradas) < 2) {
            return '';
        }

        $otros = [];

        foreach ($entradas as $e) {
            if ((int) $e['codigo'] === $codigo) {
                continue;
            }

            $nombre = trim((string) ($e['grupoDescripcion'] ?? ''));

            if ($nombre === '') {
                $nombre = $e['grupo'] === null ? 'Sin grupo' : (string) (int) $e['grupo'];
            }

            $otros[$nombre] = true;
        }

        if ($otros === []) {
            return '';
        }

        $texto = htmlspecialchars('La misma descripción también está registrada en: ' . implode(', ', array_keys($otros)));

        return '<i class="bi bi-layers-half gp-marca-variante ms-1" role="img" title="' . $texto . '" aria-label="' . $texto . '"></i>';
    }

    /**
     * Valor de texto recibido por POST, ya recortado.
     */
    private function campo(string $nombre): string
    {
        return trim((string) $this->request->getPost($nombre));
    }

    /**
     * Bandera 0/1 del formulario. No coacciona: cualquier otro valor se rechaza, para que un
     * "activo=7" no termine desactivando el registro en silencio.
     *
     * @return int|array El valor 0/1 o [mensaje de error].
     */
    private function bitValido(string $nombre, string $etiqueta): int|array
    {
        $valor = $this->campo($nombre);

        if ($valor !== '0' && $valor !== '1') {
            return ['El campo ' . $etiqueta . ' solo admite los valores 0 o 1.'];
        }

        return (int) $valor;
    }

    private function error(string $mensaje): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode(422);
    }
}
