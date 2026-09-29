<?php

namespace App\Controllers\Maestros;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\ParametrosGeneralesModel;

/**
 * Administración de los parámetros generales (tabla gConfigParametrosGenerales).
 *
 * El parámetro puede guardar un valor escrito a mano (manejaDS = 0, con tipo de
 * dato) o tomarlo de otra tabla de la base (manejaDS = 1, con ds/cValor/cEtiqueta).
 * Los identificadores del origen llegan del cliente y siempre se validan contra los
 * metadatos antes de usarse.
 */
class ParametrosController extends BaseController
{
    private $session;
    private $parametrosModel;

    /** Tipos de dato admitidos para los parámetros sin origen de datos. */
    private const TIPOS_DATO = [
        'varchar(500)' => 'Texto',
        'datetime'     => 'Fecha',
        'int'          => 'Numérico',
    ];

    private const MAX_NOMBRE = 150;
    private const MAX_ORIGEN = 150;
    private const MAX_VALOR  = 8000;

    public function __construct()
    {
        $this->session         = session();
        $this->parametrosModel = new ParametrosGeneralesModel();
    }

    /**
     * Vista principal del módulo "Parámetros Generales".
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

        $empresas   = $this->parametrosModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;
        $filas      = $empresaSel > 0 ? $this->parametrosModel->listar($empresaSel) : [];

        $data['title']             = 'Maestros · Parámetros Generales';
        $data['empresas']          = $empresas;
        $data['empresa_sel']       = $empresaSel;
        $data['tipos_dato']        = self::TIPOS_DATO;
        $data['parametros']        = $filas;
        $data['tabla_parametros']  = $this->renderizarFilasParametros($filas);
        $data['total_parametros']  = count($filas);
        $data['limite_valores']    = ParametrosGeneralesModel::LIMITE_VALORES;

        return view('maestros/parametros_generales', $data);
    }

    /**
     * Filas (<tr>) del listado; el HTML lo construye el frontend.
     */
    private function renderizarFilasParametros(array $parametros): string
    {
        if ($parametros === []) {
            return '<tr><td colspan="6" class="text-center text-muted py-4">Esta empresa no tiene parámetros generales registrados.</td></tr>';
        }

        $esc = static fn ($texto) => htmlspecialchars((string) $texto, ENT_QUOTES);
        $aJs = static fn ($texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', (string) $texto)),
            ENT_QUOTES
        );

        $html = '';

        foreach ($parametros as $fila) {
            $parametro = $this->formatearFila($fila);
            $nombre    = $parametro['nombre'];
            $manejaDS  = $parametro['manejaDS'] === 1;
            $valor     = trim((string) ($parametro['valor'] ?? ''));

            $celdaOrigen = $manejaDS
                ? '<span class="badge maestros-badge-si rounded-pill px-3 py-2"><i class="bi bi-table me-1" aria-hidden="true"></i>Tabla</span>'
                : '<span class="badge maestros-badge-inactivo rounded-pill px-3 py-2"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Valor fijo</span>';

            $celdaTipo = $manejaDS
                ? '<span class="maestros-vacio">No aplica</span>'
                : '<span class="badge maestros-badge-doc rounded-pill px-3 py-2" title="' . $esc($parametro['tipoDato']) . '">' . $esc($parametro['tipoDatoNombre']) . '</span>';

            $celdaFuente = $parametro['ds'] === null
                ? '<span class="maestros-vacio">Sin origen de datos</span>'
                : '<span class="d-block text-nowrap"><i class="bi bi-table maestros-icono-guia" aria-hidden="true"></i><span class="font-monospace">' . $esc($parametro['ds']) . '</span></span>'
                    . '<span class="maestros-subtexto"><span class="maestros-etiqueta-mini">valor</span>' . $esc((string) $parametro['cValor'])
                    . '<span class="maestros-sep">·</span><span class="maestros-etiqueta-mini">etiqueta</span>' . $esc((string) $parametro['cEtiqueta']) . '</span>';

            $celdaValor = $valor === ''
                ? '<span class="maestros-vacio">Sin valor</span>'
                : '<span class="font-monospace maestros-truncar" title="' . $esc($valor) . '">' . $esc($valor) . '</span>';

            $html .= '<tr data-nombre="' . $esc($nombre) . '">'
                . /* 1 Parámetro */ '<td data-order="' . $esc($nombre) . '"><span class="font-monospace fw-semibold maestros-truncar maestros-truncar-nombre" title="' . $esc($nombre) . '">' . $esc($nombre) . '</span></td>'
                . /* 2 Origen */ '<td class="text-center" data-order="' . ($manejaDS ? 1 : 0) . '">' . $celdaOrigen . '</td>'
                . /* 3 Tipo de dato */ '<td class="text-center" data-order="' . $esc($manejaDS ? '' : $parametro['tipoDatoNombre']) . '">' . $celdaTipo . '</td>'
                . /* 4 Origen de datos */ '<td data-order="' . $esc((string) $parametro['ds']) . '">' . $celdaFuente . '</td>'
                . /* 5 Valor */ '<td data-order="' . $esc($valor) . '">' . $celdaValor . '</td>'
                . /* 6 Acciones */ '<td class="text-center maestros-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar parámetro" aria-label="Editar parámetro"'
                . ' data-bs-toggle="modal" data-bs-target="#modalEditarParametro" onclick="obtenerParametro(&#039;' . $aJs($nombre) . '&#039;)"><i class="bi bi-pencil" aria-hidden="true"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar parámetro" aria-label="Eliminar parámetro"'
                . ' onclick="eliminarParametro(&#039;' . $aJs($nombre) . '&#039;)"><i class="bi bi-trash" aria-hidden="true"></i></button>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    public function listarParametros(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->parametrosModel->listar($empresa, $this->campo('manejaDS'));

        return $this->response->setJSON([
            'success'    => true,
            'message'    => '',
            'total'      => count($filas),
            'parametros' => array_map([$this, 'formatearFila'], $filas),
            'tabla'      => $this->renderizarFilasParametros($filas),
        ]);
    }

    public function obtenerParametro(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $fila = $this->parametrosModel->obtener($empresa, $this->campo('nombre'));

        if ($fila === null) {
            return $this->error('El parámetro no existe.');
        }

        $parametro = $this->formatearFila($fila);
        $etiqueta  = null;
        $resuelto  = null;

        if ($parametro['manejaDS'] === 1 && $parametro['ds'] !== null && $parametro['cValor'] !== null && $parametro['cEtiqueta'] !== null) {
            $etiqueta = $this->parametrosModel->resolverEtiqueta(
                $parametro['ds'],
                $parametro['cValor'],
                $parametro['cEtiqueta'],
                $empresa,
                (string) $parametro['valor']
            );
            $resuelto = $etiqueta !== null;
        }

        return $this->response->setJSON([
            'success'   => true,
            'parametro' => $parametro,
            'etiqueta'  => $etiqueta,
            'resuelto'  => $resuelto,
        ]);
    }

    public function crearParametro(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $nombre = substr(trim((string) $this->campo('nombre')), 0, self::MAX_NOMBRE);

        if ($nombre === '') {
            return $this->error('El nombre del parámetro es obligatorio.');
        }

        if ($this->parametrosModel->existe($empresa, $nombre)) {
            return $this->error("Ya existe un parámetro llamado '{$nombre}' en la empresa seleccionada.");
        }

        $datos = $this->datosParametro();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if (! $this->parametrosModel->crear($empresa, $nombre, $datos, $this->session->get('usu_id'))) {
            return $this->error('No se pudo registrar el parámetro.', 500);
        }

        return $this->respuestaTabla($empresa, 'Parámetro creado correctamente.');
    }

    public function actualizarParametro(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $nombre = trim((string) $this->campo('nombre'));

        if ($nombre === '' || ! $this->parametrosModel->existe($empresa, $nombre)) {
            return $this->error('El parámetro no existe.');
        }

        $nombreNuevo = trim((string) $this->campo('nombreNuevo'));

        if ($nombreNuevo !== '' && strcasecmp($nombreNuevo, $nombre) !== 0) {
            return $this->error('El nombre del parámetro hace parte de la llave y no se puede modificar. Cree uno nuevo y elimine el anterior.');
        }

        $datos = $this->datosParametro();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        if (! $this->parametrosModel->actualizar($empresa, $nombre, $datos, $this->session->get('usu_id'))) {
            return $this->error('No se pudo actualizar el parámetro.', 500);
        }

        return $this->respuestaTabla($empresa, 'Parámetro actualizado correctamente.');
    }

    public function eliminarParametro(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $nombre = trim((string) $this->campo('nombre'));
        $fila   = $nombre === '' ? null : $this->parametrosModel->obtener($empresa, $nombre);

        if ($fila === null) {
            return $this->error('El parámetro que intenta eliminar no existe.');
        }

        if (! $this->parametrosModel->eliminar($empresa, $nombre, $this->session->get('usu_id'), $fila)) {
            return $this->error('No se pudo eliminar el parámetro.', 500);
        }

        return $this->respuestaTabla($empresa, 'Parámetro eliminado correctamente.');
    }

    /**
     * Tablas de usuario disponibles como origen de datos.
     */
    public function tablasOrigen(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $tablas = array_map(static fn (array $tabla): array => [
            'nombre'       => (string) $tabla['nombre'],
            'esquema'      => (string) $tabla['esquema'],
            'tieneEmpresa' => (int) $tabla['tieneEmpresa'] === 1,
        ], $this->parametrosModel->tablas());

        return $this->response->setJSON([
            'success' => true,
            'total'   => count($tablas),
            'tablas'  => $tablas,
        ]);
    }

    /**
     * Columnas de la tabla origen seleccionada.
     */
    public function columnasOrigen(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $tabla    = trim((string) $this->campo('ds'));
        $columnas = $tabla === '' ? null : $this->parametrosModel->columnas($tabla);

        if ($columnas === null) {
            return $this->error('La tabla de origen seleccionada no existe.');
        }

        return $this->response->setJSON([
            'success'  => true,
            'tabla'    => $tabla,
            'columnas' => array_map(static fn (array $columna): array => [
                'nombre'     => (string) $columna['nombre'],
                'tipo'       => (string) $columna['tipo'],
                'admiteNulo' => (int) $columna['admiteNulo'] === 1,
            ], $columnas),
        ]);
    }

    /**
     * Valores disponibles en el origen de datos, con tope de filas y búsqueda en servidor.
     */
    public function valoresOrigen(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $ds        = trim((string) $this->campo('ds'));
        $cValor    = trim((string) $this->campo('cValor'));
        $cEtiqueta = trim((string) $this->campo('cEtiqueta'));

        if ($ds === '' || $cValor === '' || $cEtiqueta === '') {
            return $this->error('Debe indicar la tabla de origen y las columnas de valor y etiqueta.');
        }

        if ($this->parametrosModel->validarOrigen($ds, $cValor, $cEtiqueta) === null) {
            return $this->error($this->parametrosModel->errorOrigen() ?? 'El origen de datos indicado no existe (tabla o columnas inválidas).');
        }

        $resultado = $this->parametrosModel->valores($ds, $cValor, $cEtiqueta, $empresa, (string) $this->campo('termino'));

        if ($resultado === null) {
            return $this->error('No se pudieron leer los valores del origen de datos; revise el tipo de dato de las columnas.', 500);
        }

        return $this->response->setJSON(array_merge(['success' => true], $resultado));
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /**
     * Normaliza y valida el cuerpo del formulario.
     *
     * @return array|string Datos listos para el modelo, o el mensaje de error.
     */
    private function datosParametro(): array|string
    {
        $tipoDato = trim((string) $this->campo('tipoDato'));
        $manejaDS = $this->campo('manejaDS') == 1 ? 1 : 0;
        $valor    = (string) $this->campo('valor');

        if (! isset(self::TIPOS_DATO[$tipoDato])) {
            return 'El tipo de dato debe ser Texto, Fecha o Numérico.';
        }

        if (strlen($valor) > self::MAX_VALOR) {
            $valor = substr($valor, 0, self::MAX_VALOR);
        }

        $ds        = null;
        $cValor    = null;
        $cEtiqueta = null;

        if ($manejaDS === 1) {
            $ds        = substr(trim((string) $this->campo('ds')), 0, self::MAX_ORIGEN);
            $cValor    = substr(trim((string) $this->campo('cValor')), 0, self::MAX_ORIGEN);
            $cEtiqueta = substr(trim((string) $this->campo('cEtiqueta')), 0, self::MAX_ORIGEN);

            if ($ds === '' || $cValor === '' || $cEtiqueta === '') {
                return 'Debe indicar la tabla de origen y las columnas de valor y etiqueta.';
            }

            $origen = $this->parametrosModel->validarOrigen($ds, $cValor, $cEtiqueta);

            if ($origen === null) {
                return $this->parametrosModel->errorOrigen() ?? 'El origen de datos indicado no existe (tabla o columnas inválidas).';
            }

            $ds        = $origen['tabla'];
            $cValor    = $origen['cValor'];
            $cEtiqueta = $origen['cEtiqueta'];

            if (trim($valor) === '') {
                return 'Debe seleccionar un valor del origen de datos.';
            }
        } else {
            $mensaje = $this->validarValorLibre($tipoDato, $valor);

            if ($mensaje !== null) {
                return $mensaje;
            }
        }

        return [
            'tipoDato'  => substr($tipoDato, 0, 50),
            'manejaDS'  => $manejaDS,
            'ds'        => $ds,
            'cValor'    => $cValor,
            'cEtiqueta' => $cEtiqueta,
            'valor'     => $valor,
        ];
    }

    /**
     * Valida el valor escrito a mano contra el tipo de dato elegido.
     */
    private function validarValorLibre(string $tipoDato, string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return 'El valor del parámetro es obligatorio.';
        }

        if ($tipoDato === 'int' && preg_match('/^-?\d+$/', $valor) !== 1) {
            return 'El valor debe ser un número entero.';
        }

        if ($tipoDato === 'datetime' && strtotime($valor) === false) {
            return 'El valor debe ser una fecha válida.';
        }

        if ($tipoDato === 'varchar(500)' && strlen($valor) > 500) {
            return 'El valor de tipo texto admite máximo 500 caracteres.';
        }

        return null;
    }

    private function formatearFila(array $fila): array
    {
        $tipoDato = trim((string) $fila['tipoDato']);

        return [
            'empresa'         => (int) $fila['empresa'],
            'nombre'          => trim((string) $fila['nombre']),
            'tipoDato'        => $tipoDato,
            'tipoDatoNombre'  => self::TIPOS_DATO[$tipoDato] ?? $tipoDato,
            'manejaDS'        => (int) $fila['manejaDS'],
            'ds'              => $this->texto($fila['ds']),
            'cValor'          => $this->texto($fila['cValor']),
            'cEtiqueta'       => $this->texto($fila['cEtiqueta']),
            'valor'           => $fila['valor'] === null ? null : (string) $fila['valor'],
        ];
    }

    private function texto($valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function respuestaTabla(int $empresa, string $mensaje): ResponseInterface
    {
        $filas = $this->parametrosModel->listar($empresa);

        return $this->response->setJSON([
            'success'    => true,
            'message'    => $mensaje,
            'total'      => count($filas),
            'parametros' => array_map([$this, 'formatearFila'], $filas),
            'tabla'      => $this->renderizarFilasParametros($filas),
        ]);
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

        if ($empresa <= 0 || ! $this->parametrosModel->empresaExiste($empresa)) {
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
