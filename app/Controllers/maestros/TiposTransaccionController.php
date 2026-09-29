<?php

namespace App\Controllers\Maestros;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\TiposTransaccionModel;

/**
 * CRUD de los tipos de transacción del legado (gTipoTransaccion).
 *
 * La llave primaria es (empresa, codigo): el código no se puede modificar y el
 * borrado se bloquea si el código está en uso, verificado contra la lista curada
 * de tablas del modelo (las FK declaradas no cubren el uso real).
 */
class TiposTransaccionController extends BaseController
{
    private $session;
    private $tiposModel;

    public function __construct()
    {
        $this->session    = session();
        $this->tiposModel = new TiposTransaccionModel();
    }

    /**
     * Vista principal del módulo "Tipo de Transacción > Registro".
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

        $data['title']       = 'Maestros · Tipo de Transacción · Registro';
        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresas ? (int) $empresas[0]['id'] : 0;
        $data['modulos']     = $this->tiposModel->modulos();

        return view('maestros/tipos_transaccion_registro', $data);
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
            log_message('error', 'Error listando los tipos de transacción: ' . $e->getMessage());

            return $this->error('No se pudo leer el listado de tipos de transacción.', 500);
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

        try {
            $fila = $this->tiposModel->obtener($empresa, $codigo);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el tipo de transacción: ' . $e->getMessage());

            return $this->error('No se pudo leer el tipo de transacción.', 500);
        }

        if ($fila === null) {
            return $this->error('El tipo de transacción no existe.');
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

        $codigo = $datos['codigo'];
        unset($datos['codigo']);

        try {
            if ($this->tiposModel->existe($empresa, $codigo)) {
                return $this->error('Ya existe un tipo de transacción con el código "' . $codigo . '" en la empresa seleccionada.');
            }

            $creado = $this->tiposModel->crear($empresa, $codigo, $datos, $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error registrando el tipo de transacción: ' . $e->getMessage());

            return $this->error('No se pudo registrar el tipo de transacción.', 500);
        }

        if (! $creado) {
            return $this->error('No se pudo registrar el tipo de transacción.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tipo de transacción creado correctamente.',
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
            return $this->error('El campo "Código" es obligatorio para identificar el registro.');
        }

        $datos = $this->datosValidos();

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $codigo = $datos['codigo'];
        unset($datos['codigo']);

        try {
            if (! $this->tiposModel->existe($empresa, $codigoOriginal)) {
                return $this->error('El tipo de transacción que intenta actualizar no existe.');
            }

            if (strcasecmp($codigo, $codigoOriginal) !== 0) {
                return $this->error('El campo "Código" hace parte de la llave primaria y no se puede modificar. Cree un tipo de transacción nuevo y elimine el anterior.');
            }

            $actualizado = $this->tiposModel->actualizar($empresa, $codigoOriginal, $datos, $this->session->get('usu_id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error actualizando el tipo de transacción: ' . $e->getMessage());

            return $this->error('No se pudo actualizar el tipo de transacción.', 500);
        }

        if (! $actualizado) {
            return $this->error('No se pudo actualizar el tipo de transacción.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tipo de transacción actualizado correctamente.',
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

        try {
            $fila = $this->tiposModel->obtener($empresa, $codigo);

            if ($fila === null) {
                return $this->error('El tipo de transacción que intenta eliminar no existe.');
            }

            $dependencias = $this->tiposModel->dependencias($empresa, $codigo);
        } catch (\Throwable $e) {
            log_message('error', 'Error eliminando el tipo de transacción: ' . $e->getMessage());

            return $this->error('No se pudo eliminar el tipo de transacción.', 500);
        }

        if ($dependencias === null) {
            return $this->error('No se pudo verificar si el tipo de transacción está en uso; no se eliminó nada. Intente de nuevo.', 500);
        }

        if ($dependencias !== []) {
            $detalle = [];

            foreach ($dependencias as $dependencia) {
                $detalle[] = $dependencia['tabla'] . ' (' . $dependencia['filas'] . ')';
            }

            return $this->response->setJSON([
                'success'      => false,
                'message'      => 'No se puede eliminar el tipo de transacción porque está en uso en: ' . implode(', ', $detalle) . '.',
                'dependencias' => $dependencias,
            ])->setStatusCode(422);
        }

        if (! $this->tiposModel->eliminar($empresa, $codigo, $this->session->get('usu_id'), $fila)) {
            return $this->error('No se pudo eliminar el tipo de transacción.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tipo de transacción eliminado correctamente.',
        ]);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /**
     * Normaliza y valida los datos del formulario.
     *
     * Las longitudes se miden con strlen() porque la collation es CP1252 y un
     * varchar(N) admite N bytes. Los SP del legado declaran descripcion y formato
     * como varchar(550), pero las columnas reales son varchar(50).
     *
     * @return array{codigo: string, descripcion: string, numeracion: int, actual: ?int, prefijo: ?string, longitud: ?int, naturaleza: int, modulo: string, modoAnulacion: string, referencia: int, vistaDs: ?string, activo: int, formato: ?string}|string
     */
    private function datosValidos(): array|string
    {
        $codigo = trim($this->campo('codigo'));

        if ($codigo === '') {
            return 'El campo "Código" es obligatorio.';
        }

        if (strlen($codigo) > 50) {
            return 'El campo "Código" admite máximo 50 caracteres.';
        }

        $descripcion = trim($this->campo('descripcion'));

        if ($descripcion === '') {
            return 'El campo "Descripción" es obligatorio.';
        }

        if (strlen($descripcion) > 50) {
            return 'El campo "Descripción" admite máximo 50 caracteres.';
        }

        $prefijo = trim($this->campo('prefijo'));

        if (strlen($prefijo) > 50) {
            return 'El campo "Prefijo" admite máximo 50 caracteres.';
        }

        $vistaDs = trim($this->campo('vistaDs'));

        if (strlen($vistaDs) > 250) {
            return 'El campo "Vista DS" admite máximo 250 caracteres.';
        }

        $referencia = (int) $this->campo('referencia') === 1 ? 1 : 0;

        if ($referencia === 1 && $vistaDs === '') {
            return 'Escriba la vista o procedimiento de referencia, o desactive «Requiere documento de referencia».';
        }

        $formato = trim($this->campo('formato'));

        if (strlen($formato) > 50) {
            return 'El campo "Formato" admite máximo 50 caracteres.';
        }

        $actual = $this->enteroOpcional('actual', 'Actual');

        if (is_string($actual)) {
            return $actual;
        }

        $longitud = $this->enteroOpcional('longitud', 'Longitud');

        if (is_string($longitud)) {
            return $longitud;
        }

        $naturaleza = trim($this->campo('naturaleza'));

        if ($naturaleza === '') {
            return 'El campo "Naturaleza" es obligatorio.';
        }

        if (! in_array($naturaleza, ['0', '1', '2', '3'], true)) {
            return 'El campo "Naturaleza" solo admite los valores 0 (No aplica), 1 (Resta), 2 (Suma) o 3 (Suma y resta).';
        }

        $modoAnulacion = strtoupper(trim($this->campo('modoAnulacion')));

        if ($modoAnulacion === '') {
            return 'El campo "Modo de anulación" es obligatorio.';
        }

        if (! in_array($modoAnulacion, ['A', 'E'], true)) {
            return 'El campo "Modo de anulación" solo admite los valores "A" (Anular) o "E" (Eliminar).';
        }

        $modulo = trim($this->campo('modulo'));

        if ($modulo === '') {
            return 'El campo "Módulo" es obligatorio.';
        }

        if (strlen($modulo) > 50) {
            return 'El campo "Módulo" admite máximo 50 caracteres.';
        }

        if (! $this->tiposModel->moduloValido($modulo)) {
            return 'El módulo "' . $modulo . '" no pertenece al catálogo de módulos. Seleccione uno de la lista.';
        }

        return [
            'codigo'        => $codigo,
            'descripcion'   => $descripcion,
            'numeracion'    => (int) $this->campo('numeracion') === 1 ? 1 : 0,
            'actual'        => $actual,
            'prefijo'       => $prefijo === '' ? null : $prefijo,
            'longitud'      => $longitud,
            'naturaleza'    => (int) $naturaleza,
            'modulo'        => $modulo,
            'modoAnulacion' => $modoAnulacion,
            'referencia'    => $referencia,
            'vistaDs'       => $vistaDs === '' ? null : $vistaDs,
            'activo'        => (int) $this->campo('activo') === 1 ? 1 : 0,
            'formato'       => $formato === '' ? null : $formato,
        ];
    }

    /**
     * @return int|null|string El entero, null si viene vacío, o el mensaje de error.
     */
    private function enteroOpcional(string $nombre, string $etiqueta): int|null|string
    {
        $valor = trim($this->campo($nombre));

        if ($valor === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $valor) !== 1) {
            return 'El campo "' . $etiqueta . '" solo admite números enteros mayores o iguales a cero.';
        }

        if ((int) $valor > 2147483647 || strlen(ltrim($valor, '0')) > 10) {
            return 'El campo "' . $etiqueta . '" excede el valor numérico permitido.';
        }

        return (int) $valor;
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

    /**
     * Un parámetro que llega como arreglo (codigo[]=X) se trata como entrada
     * vacía: así termina en el 422 de validación que corresponda y no en un
     * "Array to string conversion" con traza y ruta absoluta.
     */
    private function campo(string $nombre): string
    {
        $valor = $this->request->getVar($nombre);

        if (is_array($valor)) {
            return '';
        }

        return (string) ($valor ?? '');
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
