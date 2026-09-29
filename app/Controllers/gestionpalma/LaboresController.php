<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\GruposLaborModel;
use App\Models\LaboresModel;
use App\Models\ItemsModel;

class LaboresController extends GestionPalmaController
{
    /**
     * Etiquetas de negocio cableadas en el legado, no en la base: cambiarlas aquí, en un solo lugar.
     * CLASES_LABOR es el mapeo literal confirmado por el usuario, que no calza con los valores
     * realmente guardados (1, 2, 5 y 6): el valor 6 (9 labores) queda sin etiqueta a propósito.
     * Ver datosLaborJson()/renderizarFilasLabores() para el tratamiento de esos valores sin mapa.
     */
    public const CLASES_LABOR = [
        0 => 'Mantenimiento',
        1 => 'Cosecha',
        2 => 'Cargue',
        3 => 'Transporte',
        4 => 'Sanidad',
        5 => 'Fertilizante',
    ];

    public const NATURALEZAS = [
        0 => 'No aplica',
        1 => 'Suma',
        2 => 'Resta',
        3 => 'Erradica',
    ];

    public const TIPOS_APLICACION = [
        0 => 'No aplica',
        1 => 'Por hectárea neta',
        2 => 'Por hectárea bruta',
        3 => 'Por hectárea de producción',
    ];

    private $gruposLaborModel;
    private $laboresModel;
    private $itemsModel;

    public function __construct()
    {
        parent::__construct();

        $this->gruposLaborModel = new GruposLaborModel();
        $this->laboresModel     = new LaboresModel();
        $this->itemsModel       = new ItemsModel();
    }

    /**
     * Vista principal del módulo "Grupos de labor".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function grupos($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->gruposLaborModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->gruposLaborModel->listar($empresaSel) : [];

        $data['title']         = 'Gestión Palma · Grupos de labor';
        $data['empresas']      = $empresas;
        $data['empresa_sel']   = $empresaSel;
        $data['centros_costo'] = $empresaSel > 0 ? $this->gruposLaborModel->getCentrosCosto($empresaSel) : [];
        $data['tabla_grupos']  = $this->renderizarFilasGrupos($filas);
        $data['total_grupos']  = count($filas);

        return view('gestionpalma/labores_grupos', $data);
    }

    /**
     * Vista principal del módulo "Labores / Registro".
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

        $empresas   = $this->laboresModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->laboresModel->listar($empresaSel) : [];

        $data['title']            = 'Gestión Palma · Registro de labores';
        $data['empresas']         = $empresas;
        $data['empresa_sel']      = $empresaSel;
        $data['grupos']           = $empresaSel > 0 ? $this->laboresModel->getGrupos($empresaSel) : [];
        $data['unidades_medida']  = $empresaSel > 0 ? $this->laboresModel->getUnidadesMedida($empresaSel) : [];
        $data['conceptos']        = $empresaSel > 0 ? $this->laboresModel->getConceptos($empresaSel) : [];
        $data['tipos_canal']      = $empresaSel > 0 ? $this->laboresModel->getTiposCanal($empresaSel) : [];
        $data['clases_labor']     = self::CLASES_LABOR;
        $data['naturalezas']      = self::NATURALEZAS;
        $data['tipos_aplicacion'] = self::TIPOS_APLICACION;
        $data['tabla_labores']    = $this->renderizarFilasLabores($filas);
        $data['total_labores']    = count($filas);

        return view('gestionpalma/labores_registro', $data);
    }

    /**
     * Vista principal del módulo "Labores / Ítems".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function items($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->itemsModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->itemsModel->listar($empresaSel) : [];

        $data['title']           = 'Gestión Palma · Ítems';
        $data['empresas']        = $empresas;
        $data['empresa_sel']     = $empresaSel;
        $data['unidades_medida'] = $empresaSel > 0 ? $this->itemsModel->getUnidadesMedida($empresaSel) : [];
        $data['tabla_items']     = $this->renderizarFilasItems($filas);
        $data['total_items']     = count($filas);

        return view('gestionpalma/labores_items', $data);
    }

    // ── Endpoints JSON ────────────────────────────────────────────────────────

    public function listarGrupos(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->gruposLaborModel->listar($empresa, $this->campo('estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasGrupos($filas),
        ]);
    }

    public function obtenerGrupo(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $grupo = $this->gruposLaborModel->obtener($empresa, $this->campo('codigo'));

        if ($grupo === null) {
            return $this->error('El grupo no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'grupo'   => [
                'empresa'           => (int) $grupo['empresa'],
                'codigo'            => trim((string) $grupo['codigo']),
                'descripcion'       => (string) $grupo['descripcion'],
                'activo'            => (int) $grupo['activo'],
                'ccosto'            => $grupo['ccosto'] !== null ? trim((string) $grupo['ccosto']) : '',
                'manejaCcostoSiigo' => (int) $grupo['manejaCcostoSiigo'],
                'ccostoSiigo'       => $grupo['ccostoSiigo'] !== null ? trim((string) $grupo['ccostoSiigo']) : '',
                'labores'           => (int) ($grupo['labores'] ?? 0),
            ],
        ]);
    }

    public function crearGrupo(): ResponseInterface
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

        if ($this->gruposLaborModel->existe($empresa, $codigo)) {
            return $this->error("Ya existe un grupo con el código {$codigo}.");
        }

        $datos = $this->datosGrupo($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->gruposLaborModel->crear($empresa, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaGrupos($empresa, 'Grupo creado correctamente.');
    }

    public function actualizarGrupo(): ResponseInterface
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

        if (! $this->gruposLaborModel->existe($empresa, $codigo)) {
            return $this->error('El grupo no existe.');
        }

        $datos = $this->datosGrupo($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->gruposLaborModel->actualizar($empresa, $codigo, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaGrupos($empresa, 'Grupo actualizado correctamente.');
    }

    public function eliminarGrupo(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');

        if (! $this->gruposLaborModel->existe($empresa, $codigo)) {
            return $this->error('El grupo no existe.');
        }

        $enUso = $this->gruposLaborModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error("No se puede eliminar: el grupo está asignado a {$enUso} labores.");
        }

        $this->gruposLaborModel->eliminar($empresa, $codigo, $this->session->get('usu_id'));

        return $this->respuestaTablaGrupos($empresa, 'Grupo eliminado correctamente.');
    }

    public function cambiarEstadoGrupo(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->campo('codigo');
        $grupo  = $this->gruposLaborModel->obtener($empresa, $codigo);

        if ($grupo === null) {
            return $this->error('El grupo no existe.');
        }

        $activo = (int) $grupo['activo'] === 1 ? 0 : 1;

        $this->gruposLaborModel->cambiarEstado($empresa, $codigo, $activo, $this->session->get('usu_id'));

        return $this->respuestaTablaGrupos(
            $empresa,
            $activo === 1 ? 'Grupo activado correctamente.' : 'Grupo desactivado correctamente.'
        );
    }

    public function siguienteCodigoGrupo(): ResponseInterface
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
            'codigo'  => $this->gruposLaborModel->siguienteCodigo($empresa),
        ]);
    }

    // ── Endpoints JSON: labores (aNovedad) ─────────────────────────────────────

    public function listarLabores(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->laboresModel->listar($empresa, $this->campo('grupo'), $this->campo('estado'), $this->campo('busqueda'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasLabores($filas),
        ]);
    }

    public function obtenerLabor(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $labor = $this->laboresModel->obtener($empresa, $this->campo('codigo'), $this->campo('concepto'));

        if ($labor === null) {
            return $this->error('La labor no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'labor'   => $this->datosLaborJson($labor),
        ]);
    }

    public function crearLabor(): ResponseInterface
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

        $concepto = $this->conceptoValidoCampo($empresa);

        if (is_array($concepto)) {
            return $this->error($concepto[0]);
        }

        if ($this->laboresModel->existe($empresa, $codigo, $concepto)) {
            return $this->error("Ya existe una labor con el código {$codigo} y el concepto {$concepto}.");
        }

        $datos = $this->datosLabor($empresa, true);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->laboresModel->crear($empresa, $codigo, $concepto, $datos, $this->session->get('usu_id'), $this->usuarioRegistro());

        return $this->respuestaTablaLabores($empresa, 'Labor creada correctamente.');
    }

    public function actualizarLabor(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo   = $this->campo('codigo');
        $concepto = $this->campo('concepto');

        if (! $this->laboresModel->existe($empresa, $codigo, $concepto)) {
            return $this->error('La labor no existe.');
        }

        $datos = $this->datosLabor($empresa, false);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->laboresModel->actualizar($empresa, $codigo, $concepto, $datos, $this->session->get('usu_id'));

        return $this->respuestaTablaLabores($empresa, 'Labor actualizada correctamente.');
    }

    public function eliminarLabor(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo   = $this->campo('codigo');
        $concepto = $this->campo('concepto');

        if (! $this->laboresModel->existe($empresa, $codigo, $concepto)) {
            return $this->error('La labor no existe.');
        }

        $enUso = $this->laboresModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error('No se puede eliminar: la labor tiene ' . number_format($enUso, 0, ',', '.') . ' registros asociados.');
        }

        $this->laboresModel->eliminar($empresa, $codigo, $concepto, $this->session->get('usu_id'));

        return $this->respuestaTablaLabores($empresa, 'Labor eliminada correctamente.');
    }

    public function cambiarEstadoLabor(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo   = $this->campo('codigo');
        $concepto = $this->campo('concepto');
        $labor    = $this->laboresModel->obtener($empresa, $codigo, $concepto);

        if ($labor === null) {
            return $this->error('La labor no existe.');
        }

        $activo = (int) $labor['activo'] === 1 ? 0 : 1;

        $this->laboresModel->cambiarEstado($empresa, $codigo, $concepto, $activo, $this->session->get('usu_id'));

        return $this->respuestaTablaLabores(
            $empresa,
            $activo === 1 ? 'Labor activada correctamente.' : 'Labor desactivada correctamente.'
        );
    }

    // ── Endpoints JSON: ítems (iItems) ─────────────────────────────────────────

    public function listarItems(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->itemsModel->listar($empresa, $this->campo('estado'), $this->campo('busqueda'));

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasItems($filas),
        ]);
    }

    public function obtenerItem(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoItemValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        $item = $this->itemsModel->obtener($empresa, $codigo);

        if ($item === null) {
            return $this->error('El ítem no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'item'    => [
                'empresa'              => (int) $item['empresa'],
                'codigo'               => (int) $item['codigo'],
                'descripcion'          => (string) $item['descripcion'],
                'descripcionAbreviada' => (string) $item['descripcionAbreviada'],
                'referencia'           => (string) $item['referencia'],
                'uMedida'              => trim((string) $item['uMedida']),
                'notas'                => (string) $item['notas'],
                'activo'               => (int) $item['activo'],
                'usos'                 => (int) ($item['usos'] ?? 0),
                'usuarioActualiza'     => trim((string) ($item['usuarioActualiza'] ?? '')),
                'fechaActualiza'       => $this->formatearFechaHora($item['fechaActualiza'] ?? null),
            ],
        ]);
    }

    public function crearItem(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoItemValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if ($this->itemsModel->existe($empresa, $codigo)) {
            return $this->error("Ya existe un ítem con el código {$codigo}.");
        }

        $datos = $this->datosItem($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->itemsModel->crear($empresa, $codigo, $datos, $this->session->get('usu_id'), $this->usuarioRegistro());

        return $this->respuestaTablaItems($empresa, 'Ítem creado correctamente.');
    }

    public function actualizarItem(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoItemValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if (! $this->itemsModel->existe($empresa, $codigo)) {
            return $this->error('El ítem no existe.');
        }

        $datos = $this->datosItem($empresa);

        if (is_string($datos)) {
            return $this->error($datos);
        }

        $this->itemsModel->actualizar($empresa, $codigo, $datos, $this->session->get('usu_id'), $this->usuarioRegistro());

        return $this->respuestaTablaItems($empresa, 'Ítem actualizado correctamente.');
    }

    public function eliminarItem(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoItemValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        if (! $this->itemsModel->existe($empresa, $codigo)) {
            return $this->error('El ítem no existe.');
        }

        $enUso = $this->itemsModel->enUso($empresa, $codigo);

        if ($enUso > 0) {
            return $this->error('No se puede eliminar: el ítem tiene ' . number_format($enUso, 0, ',', '.') . ' registros asociados.');
        }

        $this->itemsModel->eliminar($empresa, $codigo, $this->session->get('usu_id'));

        return $this->respuestaTablaItems($empresa, 'Ítem eliminado correctamente.');
    }

    public function cambiarEstadoItem(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $codigo = $this->codigoItemValido();

        if (is_array($codigo)) {
            return $this->error($codigo[0]);
        }

        $item = $this->itemsModel->obtener($empresa, $codigo);

        if ($item === null) {
            return $this->error('El ítem no existe.');
        }

        $activo = (int) $item['activo'] === 1 ? 0 : 1;

        $this->itemsModel->cambiarEstado($empresa, $codigo, $activo, $this->session->get('usu_id'), $this->usuarioRegistro());

        return $this->respuestaTablaItems(
            $empresa,
            $activo === 1 ? 'Ítem activado correctamente.' : 'Ítem desactivado correctamente.'
        );
    }

    public function siguienteCodigoItem(): ResponseInterface
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
            'codigo'  => $this->itemsModel->siguienteCodigo($empresa),
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

        if ($empresa === null || ! is_numeric($empresa) || ! $this->gruposLaborModel->empresaExiste((int) $empresa)) {
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
     * Valida el formulario del grupo de labor y arma las columnas a persistir.
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosGrupo(int $empresa): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (strlen($descripcion) > 50) {
            return 'La descripción no puede superar los 50 caracteres.';
        }

        $ccosto = $this->campo('ccosto');

        if ($ccosto !== '') {
            if (strlen($ccosto) > 50) {
                return 'El centro de costo no puede superar los 50 caracteres.';
            }

            if (! $this->gruposLaborModel->ccostoValido($empresa, $ccosto)) {
                return 'El centro de costo seleccionado no existe.';
            }
        }

        $manejaCcostoSiigo = $this->bit('manejaCcostoSiigo');
        $ccostoSiigo       = $this->campo('ccostoSiigo');

        if ($manejaCcostoSiigo === 1) {
            if ($ccostoSiigo === '') {
                return 'El centro de costo Siigo es obligatorio cuando se maneja centro de costo Siigo.';
            }

            if (strlen($ccostoSiigo) > 50) {
                return 'El centro de costo Siigo no puede superar los 50 caracteres.';
            }

            if (! $this->gruposLaborModel->ccostoValido($empresa, $ccostoSiigo)) {
                return 'El centro de costo Siigo seleccionado no existe.';
            }
        } else {
            $ccostoSiigo = '';
        }

        return [
            'descripcion'       => $descripcion,
            'activo'            => $this->bit('activo'),
            'ccosto'            => $ccosto !== '' ? $ccosto : null,
            'manejaCcostoSiigo' => $manejaCcostoSiigo,
            'ccostoSiigo'       => $ccostoSiigo !== '' ? $ccostoSiigo : null,
        ];
    }

    /**
     * Respuesta estándar con la tabla re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaGrupos(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->gruposLaborModel->listar($empresa, $this->campo('filtro_estado'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasGrupos($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de grupos de labor.
     */
    private function renderizarFilasGrupos(array $grupos): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        $vacio = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($grupos as $g) {
            $empresa = (int) $g['empresa'];
            $codigo  = trim((string) $g['codigo']);
            $activo  = (int) $g['activo'] === 1 ? 1 : 0;
            $labores = (int) ($g['labores'] ?? 0);

            $descripcion       = trim((string) $g['descripcion']);
            $ccosto            = $g['ccosto'] !== null ? trim((string) $g['ccosto']) : '';
            $ccostoDescripcion = trim((string) ($g['ccostoDescripcion'] ?? ''));

            if ($ccosto === '') {
                $celdaCcosto = $vacio;
            } else {
                $celdaCcosto = ($ccostoDescripcion !== '' ? htmlspecialchars($ccostoDescripcion) : htmlspecialchars($ccosto))
                    . '<div class="gp-subtexto">' . htmlspecialchars($ccosto) . '</div>';
            }

            $celdaLabores = $labores > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" title="' . $labores
                    . ' labores usan este grupo; no se puede eliminar mientras esté en uso.">' . $labores . '</span>'
                : '<span class="gp-chip-rol">0</span>';

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar grupo' : 'Activar grupo';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($codigo) . '&#039;';
            $descJs  = $aJs($descripcion);

            $tituloAcciones = $labores > 0
                ? ' title="No se puede eliminar: ' . $labores . ' labores usan este grupo."'
                : '';

            $botonEliminar = $labores > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarGrupo(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . htmlspecialchars($codigo)
                . '" data-activo="' . $activo . '" data-usos="' . $labores . '">'
                . '<td class="text-center"><span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span></td>'
                . '<td data-order="' . htmlspecialchars($descripcion) . '">' . htmlspecialchars($descripcion) . '</td>'
                . '<td data-order="' . htmlspecialchars($ccostoDescripcion !== '' ? $ccostoDescripcion : $ccosto) . '">' . $celdaCcosto . '</td>'
                . '<td class="text-center" data-order="' . $labores . '">' . $celdaLabores . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarGrupo(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoGrupo(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
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
     * Valida el concepto recibido por POST, contra el catálogo nConcepto. Parte de la llave.
     *
     * @return string|array El código recortado o [mensaje de error].
     */
    private function conceptoValidoCampo(int $empresa): string|array
    {
        $concepto = $this->campo('concepto');

        if ($concepto === '') {
            return ['El concepto es obligatorio.'];
        }

        if (! $this->laboresModel->conceptoValido($empresa, $concepto)) {
            return ['El concepto seleccionado no existe.'];
        }

        return $concepto;
    }

    /**
     * Valida el formulario de la labor y arma las columnas a persistir en aNovedad. No incluye
     * codigo ni concepto (llave), fechaRegistro, usuario, muestraInforme ni muestraInformeContratista:
     * fuera de alcance o preservados sin cambios.
     *
     * @param bool $esCreacion claseLabor solo exige ser una clave de CLASES_LABOR al crear: el
     *             mapeo confirmado por el usuario no cubre el valor 6 (9 labores existentes), así
     *             que al actualizar solo se exige un entero >= 0 para no bloquear su edición.
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosLabor(int $empresa, bool $esCreacion): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (strlen($descripcion) > 200) {
            return 'La descripción no puede superar los 200 caracteres.';
        }

        $desCorta = $this->campo('desCorta');

        if ($desCorta === '') {
            return 'La descripción corta es obligatoria.';
        }

        if (strlen($desCorta) > 50) {
            return 'La descripción corta no puede superar los 50 caracteres.';
        }

        $grupo = $this->campo('grupo');

        if ($grupo === '') {
            return 'El grupo es obligatorio.';
        }

        if (! $this->laboresModel->grupoValido($empresa, $grupo)) {
            return 'El grupo seleccionado no existe.';
        }

        $uMedida = $this->campo('uMedida');

        if ($uMedida === '') {
            return 'La unidad de medida es obligatoria.';
        }

        if (! $this->laboresModel->uMedidaValida($empresa, $uMedida)) {
            return 'La unidad de medida seleccionada no existe.';
        }

        $equivalencia = $this->campo('equivalencia');

        if ($equivalencia !== '') {
            if (strlen($equivalencia) > 50) {
                return 'La equivalencia no puede superar los 50 caracteres.';
            }

            if (! $this->laboresModel->conceptoValido($empresa, $equivalencia)) {
                return 'La equivalencia seleccionada no existe.';
            }
        }

        $ciclos = $this->enteroRequerido('ciclos', 'Los ciclos');

        if (is_string($ciclos)) {
            return $ciclos;
        }

        $tarea = $this->enteroRequerido('tarea', 'El rendimiento');

        if (is_string($tarea)) {
            return $tarea;
        }

        $naturaleza = $this->opcionValida('naturaleza', self::NATURALEZAS, 'El signo');

        if (is_string($naturaleza)) {
            return $naturaleza;
        }

        $claseLabor = $esCreacion
            ? $this->opcionValida('claseLabor', self::CLASES_LABOR, 'La clase de labor')
            : $this->enteroRequerido('claseLabor', 'La clase de labor');

        if (is_string($claseLabor)) {
            return $claseLabor;
        }

        $tipoAplicacion = $this->opcionValida('tipoAplicacion', self::TIPOS_APLICACION, 'El tipo de aplicación');

        if (is_string($tipoAplicacion)) {
            return $tipoAplicacion;
        }

        $manejaRango = $this->bit('manejaRango');
        $anioDesde   = 0;
        $anioHasta   = 0;

        if ($manejaRango === 1) {
            $anioDesde = $this->enteroRequerido('anioDesde', 'El año desde');

            if (is_string($anioDesde)) {
                return $anioDesde;
            }

            $anioHasta = $this->enteroRequerido('anioHasta', 'El año hasta');

            if (is_string($anioHasta)) {
                return $anioHasta;
            }

            if ($anioHasta < $anioDesde) {
                return 'El año hasta no puede ser menor que el año desde.';
            }
        }

        $impuesto = $this->bit('impuesto');
        $grupoIR  = null;

        if ($impuesto === 1) {
            $grupoIR = $this->campo('grupoIR');

            if ($grupoIR === '') {
                return 'El grupo de impuesto es obligatorio cuando la labor maneja impuesto.';
            }

            if (strlen($grupoIR) > 5) {
                return 'El grupo de impuesto no puede superar los 5 caracteres.';
            }
        }

        $manejaCanal = $this->bit('manejaCanal');
        $tipoCanal   = null;

        if ($manejaCanal === 1) {
            $tipoCanal = $this->campo('tipoCanal');

            if ($tipoCanal === '') {
                return 'El tipo de canal es obligatorio cuando la labor maneja canal.';
            }

            if (! $this->laboresModel->tipoCanalValido($empresa, $tipoCanal)) {
                return 'El tipo de canal seleccionado no existe.';
            }
        }

        return [
            'descripcion'           => $descripcion,
            'desCorta'              => $desCorta,
            'grupo'                 => $grupo,
            'uMedida'               => $uMedida,
            'equivalencia'          => $equivalencia !== '' ? $equivalencia : null,
            'ciclos'                => $ciclos,
            'tarea'                 => $tarea,
            'naturaleza'            => $naturaleza,
            'claseLabor'            => $claseLabor,
            'porHaNeta'             => $tipoAplicacion === 1 ? 1 : 0,
            'porHaBruta'            => $tipoAplicacion === 2 ? 1 : 0,
            'porHaProduccion'       => $tipoAplicacion === 3 ? 1 : 0,
            'manejaRango'           => $manejaRango,
            'anioDesde'             => $anioDesde,
            'anioHasta'             => $anioHasta,
            'impuesto'              => $impuesto,
            'grupoIR'               => $grupoIR,
            'manejaCanal'           => $manejaCanal,
            'tipoCanal'             => $tipoCanal,
            'manejaLote'            => $this->bit('manejaLote'),
            'manejaSaldo'           => $this->bit('manejaSaldo'),
            'manejaLinea'           => $this->bit('manejaLinea'),
            'manejaPalma'           => $this->bit('manejaPalma'),
            'manejaRacimo'          => $this->bit('manejaRacimo'),
            'manejaJornal'          => $this->bit('manejaJornal'),
            'manejaBascula'         => $this->bit('manejaBascula'),
            'manejaFecha'           => $this->bit('manejaFecha'),
            'manejaDecimal'         => $this->bit('manejaDecimal'),
            'manejaCaracteristica'  => $this->bit('manejaCaracteristica'),
            'noPrestacional'        => $this->bit('noPrestacional'),
            'activo'                => $this->bit('activo'),
            'calculaJornal'         => $this->bit('calculaJornal'),
        ];
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
     * Valida que el valor recibido por POST sea una de las claves del mapa de constantes indicado.
     *
     * @return int|string La clave o el mensaje de error.
     */
    private function opcionValida(string $campo, array $mapa, string $etiqueta): int|string
    {
        $valor = $this->campo($campo);

        if ($valor === '' || ! ctype_digit($valor) || ! array_key_exists((int) $valor, $mapa)) {
            return "{$etiqueta} no es válido.";
        }

        return (int) $valor;
    }

    /**
     * Etiqueta de CLASES_LABOR para un valor guardado, o un texto de reserva cuando el valor no
     * tiene correspondencia en el mapa (el 6, hoy en 9 labores): el <select> del formulario debe
     * inyectar esta opción antes de asignarla, igual que el patrón "auto-reparable" de Empleados
     * de nómina, para no perder el dato al guardar.
     */
    private function etiquetaClaseLabor(int $valor): string
    {
        return self::CLASES_LABOR[$valor] ?? "Sin etiqueta ({$valor})";
    }

    /**
     * Estructura de la labor devuelta al cliente para el formulario de edición.
     */
    private function datosLaborJson(array $labor): array
    {
        $tipoAplicacion = 0;

        if ((int) $labor['porHaNeta'] === 1) {
            $tipoAplicacion = 1;
        } elseif ((int) $labor['porHaBruta'] === 1) {
            $tipoAplicacion = 2;
        } elseif ((int) $labor['porHaProduccion'] === 1) {
            $tipoAplicacion = 3;
        }

        return [
            'empresa'               => (int) $labor['empresa'],
            'codigo'                => trim((string) $labor['codigo']),
            'descripcion'           => (string) $labor['descripcion'],
            'desCorta'              => (string) $labor['desCorta'],
            'grupo'                 => trim((string) $labor['grupo']),
            'uMedida'               => trim((string) $labor['uMedida']),
            'concepto'              => trim((string) $labor['concepto']),
            'equivalencia'          => $labor['equivalencia'] !== null ? trim((string) $labor['equivalencia']) : '',
            'ciclos'                => (int) $labor['ciclos'],
            'tarea'                 => (int) $labor['tarea'],
            'naturaleza'            => (int) $labor['naturaleza'],
            'claseLabor'            => $labor['claseLabor'] !== null ? (int) $labor['claseLabor'] : null,
            'claseLaborEtiqueta'    => $labor['claseLabor'] !== null ? $this->etiquetaClaseLabor((int) $labor['claseLabor']) : null,
            'tipoAplicacion'        => $tipoAplicacion,
            'manejaRango'           => (int) $labor['manejaRango'],
            'anioDesde'             => (int) $labor['anioDesde'],
            'anioHasta'             => (int) $labor['anioHasta'],
            'impuesto'              => (int) $labor['impuesto'],
            'grupoIR'               => $labor['grupoIR'] !== null ? trim((string) $labor['grupoIR']) : '',
            'manejaCanal'           => (int) $labor['manejaCanal'],
            'tipoCanal'             => $labor['tipoCanal'] !== null ? trim((string) $labor['tipoCanal']) : '',
            'manejaLote'            => (int) $labor['manejaLote'],
            'manejaSaldo'           => (int) $labor['manejaSaldo'],
            'manejaLinea'           => (int) $labor['manejaLinea'],
            'manejaPalma'           => (int) $labor['manejaPalma'],
            'manejaRacimo'          => (int) $labor['manejaRacimo'],
            'manejaJornal'          => (int) $labor['manejaJornal'],
            'manejaBascula'         => (int) $labor['manejaBascula'],
            'manejaFecha'           => (int) $labor['manejaFecha'],
            'manejaDecimal'         => (int) $labor['manejaDecimal'],
            'manejaCaracteristica'  => (int) $labor['manejaCaracteristica'],
            'noPrestacional'        => (int) $labor['noPrestacional'],
            'activo'                => (int) $labor['activo'],
            'calculaJornal'         => (int) $labor['calculaJornal'],
            'usos'                  => (int) ($labor['usos'] ?? 0),
        ];
    }

    /**
     * Respuesta estándar con la tabla de labores re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaLabores(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->laboresModel->listar(
            $empresa, $this->campo('filtro_grupo'), $this->campo('filtro_estado'), $this->campo('filtro_busqueda')
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasLabores($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de labores, con las 11 columnas aprobadas por UI/UX.
     * El uso se muestra como badge binario (En uso / Sin uso): 143 de 144 labores están en uso,
     * así que la cifra exacta no aporta y solo interesa si bloquea el borrado.
     */
    private function renderizarFilasLabores(array $labores): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        $vacio = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($labores as $l) {
            $empresa  = (int) $l['empresa'];
            $codigo   = trim((string) $l['codigo']);
            $concepto = trim((string) $l['concepto']);
            $activo   = (int) $l['activo'] === 1 ? 1 : 0;
            $usos     = (int) ($l['usos'] ?? 0);

            $descripcion   = trim((string) $l['descripcion']);
            $desCorta      = trim((string) $l['desCorta']);
            $grupoNombre   = trim((string) ($l['grupoDescripcion'] ?? ''));
            $grupo         = trim((string) $l['grupo']);
            $conceptoNombre = trim((string) ($l['conceptoDescripcion'] ?? ''));
            $uMedida       = trim((string) $l['uMedida']);
            $uMedidaNombre = trim((string) ($l['uMedidaDescripcion'] ?? ''));
            $ciclos        = (int) $l['ciclos'];
            $tarea         = (int) $l['tarea'];
            $naturaleza    = (int) $l['naturaleza'];

            // 1) Código
            $celdaCodigo = '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($codigo) . '</span>';

            // 2) Descripción
            $celdaDescripcion = htmlspecialchars($descripcion)
                . ($desCorta !== '' && $desCorta !== $descripcion ? '<div class="gp-subtexto">' . htmlspecialchars($desCorta) . '</div>' : '');

            // 3) Grupo
            $celdaGrupo = $grupoNombre !== ''
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1">' . htmlspecialchars($grupoNombre) . '</span>'
                : ($grupo !== '' ? htmlspecialchars($grupo) : $vacio);

            // 4) Concepto
            $celdaConcepto = $conceptoNombre !== ''
                ? '<span class="gp-truncar" title="' . htmlspecialchars($conceptoNombre) . '">' . htmlspecialchars($conceptoNombre) . '</span>'
                : $vacio;

            // 5) Unidad
            $celdaUnidad = $uMedida !== ''
                ? '<span title="' . htmlspecialchars($uMedidaNombre !== '' ? $uMedidaNombre : $uMedida) . '">' . htmlspecialchars($uMedida) . '</span>'
                : $vacio;

            // 8) Signo
            $celdaSigno = htmlspecialchars(self::NATURALEZAS[$naturaleza] ?? (string) $naturaleza);

            // 9) Uso
            $celdaUso = $usos > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1" title="Esta labor tiene registros asociados; no se puede eliminar mientras esté en uso.">En uso</span>'
                : '<span class="gp-chip-rol">Sin uso</span>';

            // 10) Estado
            $badgeEstado = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar labor' : 'Activar labor';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',&#039;' . $aJs($codigo) . '&#039;,&#039;' . $aJs($concepto) . '&#039;';
            $descJs  = $aJs($descripcion);

            $tituloAcciones = $usos > 0
                ? ' title="No se puede eliminar: la labor tiene registros asociados."'
                : '';

            $botonEliminar = $usos > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarLabor(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            // 11) Acciones
            $celdaAcciones = '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarLabor(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoLabor(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
                . $botonEliminar
                . '</div>';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . htmlspecialchars($codigo)
                . '" data-concepto="' . htmlspecialchars($concepto) . '" data-activo="' . $activo . '" data-usos="' . $usos . '">'
                . '<td>' . $celdaCodigo . '</td>'
                . '<td>' . $celdaDescripcion . '</td>'
                . '<td data-order="' . htmlspecialchars($grupoNombre !== '' ? $grupoNombre : $grupo) . '">' . $celdaGrupo . '</td>'
                . '<td data-order="' . htmlspecialchars($conceptoNombre) . '">' . $celdaConcepto . '</td>'
                . '<td class="text-center" data-order="' . htmlspecialchars($uMedida) . '">' . $celdaUnidad . '</td>'
                . '<td class="text-end font-monospace">' . $ciclos . '</td>'
                . '<td class="text-end font-monospace">' . $tarea . '</td>'
                . '<td class="text-center">' . $celdaSigno . '</td>'
                . '<td class="text-center" data-order="' . $usos . '">' . $celdaUso . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badgeEstado . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>' . $celdaAcciones . '</td>'
                . '</tr>';
        }

        return $html;
    }

    /**
     * Normaliza a 'Y-m-d H:i:s' una fecha proveniente de la BD (DateTime o cadena), o null.
     */
    private function formatearFechaHora($fecha): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d H:i:s');
        }

        if ($fecha === null || $fecha === '') {
            return null;
        }

        $ts = strtotime((string) $fecha);

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    /**
     * Valida el código recibido por POST para un ítem (entero >= 1).
     *
     * @return int|array El código o [mensaje de error].
     */
    private function codigoItemValido(): int|array
    {
        $codigo = $this->campo('codigo');

        if ($codigo === '' || ! ctype_digit($codigo) || (int) $codigo < 1) {
            return ['El código debe ser un número entero mayor o igual a 1.'];
        }

        return (int) $codigo;
    }

    /**
     * Valida el formulario del ítem y arma las columnas a persistir. No incluye codigo (llave).
     *
     * @return array|string Columnas listas para el INSERT/UPDATE o el mensaje de error.
     */
    private function datosItem(int $empresa): array|string
    {
        $descripcion = $this->campo('descripcion');

        if ($descripcion === '') {
            return 'La descripción es obligatoria.';
        }

        if (strlen($descripcion) > 950) {
            return 'La descripción no puede superar los 950 caracteres.';
        }

        $descripcionAbreviada = $this->campo('descripcionAbreviada');

        if ($descripcionAbreviada === '') {
            return 'La descripción abreviada es obligatoria.';
        }

        if (strlen($descripcionAbreviada) > 50) {
            return 'La descripción abreviada no puede superar los 50 caracteres.';
        }

        $referencia = $this->campo('referencia');

        if (strlen($referencia) > 250) {
            return 'La referencia no puede superar los 250 caracteres.';
        }

        $uMedida = $this->campo('uMedida');

        if ($uMedida === '') {
            return 'La unidad de medida es obligatoria.';
        }

        if (! $this->itemsModel->uMedidaValida($empresa, $uMedida)) {
            return 'La unidad de medida seleccionada no existe.';
        }

        $notas = $this->campo('notas');

        if (strlen($notas) > 1550) {
            return 'Las notas no pueden superar los 1550 caracteres.';
        }

        return [
            'descripcion'          => $descripcion,
            'descripcionAbreviada' => $descripcionAbreviada,
            'referencia'           => $referencia,
            'uMedida'              => $uMedida,
            'notas'                => $notas,
            'activo'               => $this->bit('activo'),
        ];
    }

    /**
     * Respuesta estándar con la tabla de ítems re-renderizada según los filtros vigentes.
     */
    private function respuestaTablaItems(int $empresa, string $mensaje): ResponseInterface
    {
        $filtroEmpresa = $this->request->getPost('filtro_empresa');

        if ($filtroEmpresa !== null && $filtroEmpresa !== '' && is_numeric($filtroEmpresa)) {
            $empresa = (int) $filtroEmpresa;
        }

        $filas = $this->itemsModel->listar($empresa, $this->campo('filtro_estado'), $this->campo('filtro_busqueda'));

        return $this->response->setJSON([
            'success' => true,
            'message' => $mensaje,
            'total'   => count($filas),
            'tabla'   => $this->renderizarFilasItems($filas),
        ]);
    }

    /**
     * Construye las filas <tr> de la tabla de ítems.
     */
    private function renderizarFilasItems(array $items): string
    {
        $html = '';

        $aJs = static fn (string $texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', $texto)),
            ENT_QUOTES
        );

        $vacio = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($items as $i) {
            $empresa = (int) $i['empresa'];
            $codigo  = (int) $i['codigo'];
            $activo  = (int) $i['activo'] === 1 ? 1 : 0;
            $usos    = (int) ($i['usos'] ?? 0);

            $descripcion          = trim((string) $i['descripcion']);
            $descripcionAbreviada = trim((string) $i['descripcionAbreviada']);
            $referencia            = trim((string) $i['referencia']);
            $uMedida               = trim((string) $i['uMedida']);
            $uMedidaNombre         = trim((string) ($i['uMedidaDescripcion'] ?? ''));
            $notas                 = trim((string) $i['notas']);

            $celdaCodigo = '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . $codigo . '</span>';

            $celdaDescripcion = htmlspecialchars($descripcion)
                . ($descripcionAbreviada !== '' && $descripcionAbreviada !== $descripcion
                    ? '<div class="gp-subtexto">' . htmlspecialchars($descripcionAbreviada) . '</div>' : '');

            $celdaUnidad = $uMedida !== ''
                ? '<span title="' . htmlspecialchars($uMedidaNombre !== '' ? $uMedidaNombre : $uMedida) . '">' . htmlspecialchars($uMedida) . '</span>'
                : $vacio;

            $mismaDescripcion = $referencia !== '' && mb_strtoupper($referencia) === mb_strtoupper($descripcion);

            $celdaReferencia = $referencia === ''
                ? $vacio
                : ($mismaDescripcion
                    ? '<span class="text-muted">' . htmlspecialchars($referencia) . '</span>'
                    : htmlspecialchars($referencia));

            $celdaNotas = $notas !== ''
                ? '<i class="bi bi-sticky-fill text-muted" title="' . htmlspecialchars(str_replace(["\r\n", "\r", "\n"], ' ', $notas)) . '"></i>'
                : '';

            $celdaUso = $usos > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace" title="'
                    . $usos . ' registros usan este ítem; no se puede eliminar mientras esté en uso.">' . $usos . '</span>'
                : '<span class="gp-chip-rol">0</span>';

            $badge = $activo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>'
                : '<span class="badge gp-badge-cerrado rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>';

            $tituloEstado = $activo === 1 ? 'Desactivar ítem' : 'Activar ítem';
            $iconoEstado  = $activo === 1 ? 'bi bi-toggle-on' : 'bi bi-toggle-off';

            $claveJs = $empresa . ',' . $codigo;
            $descJs  = $aJs($descripcion);

            $tituloAcciones = $usos > 0
                ? ' title="No se puede eliminar: el ítem tiene registros asociados."'
                : '';

            $botonEliminar = $usos > 0
                ? '<button type="button" class="btn btn-outline-danger" disabled><i class="bi bi-trash"></i></button>'
                : '<button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="eliminarItem(' . $claveJs . ',&#039;' . $descJs . '&#039;)"><i class="bi bi-trash"></i></button>';

            $html .= '<tr data-empresa="' . $empresa . '" data-codigo="' . $codigo
                . '" data-activo="' . $activo . '" data-usos="' . $usos . '">'
                . '<td class="text-center">' . $celdaCodigo . '</td>'
                . '<td>' . $celdaDescripcion . '</td>'
                . '<td data-order="' . htmlspecialchars($referencia) . '">' . $celdaReferencia . '</td>'
                . '<td class="text-center" data-order="' . htmlspecialchars($uMedida) . '">' . $celdaUnidad . '</td>'
                . '<td class="text-center">' . $celdaNotas . '</td>'
                . '<td class="text-center" data-order="' . $usos . '">' . $celdaUso . '</td>'
                . '<td class="text-center" data-order="' . $activo . '">' . $badge . '</td>'
                . '<td class="text-center gp-col-sticky"' . $tituloAcciones . '>'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" onclick="editarItem(' . $claveJs . ')"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="' . $tituloEstado . '" onclick="cambiarEstadoItem(' . $claveJs . ')"><i class="' . $iconoEstado . '"></i></button>'
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
