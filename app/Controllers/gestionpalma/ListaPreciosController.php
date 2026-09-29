<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\PreciosLaborModel;
use App\Models\PreciosLoteModel;

class ListaPreciosController extends GestionPalmaController
{
    /** Tope de los tres precios (money) y máximo de decimales admitidos por columna. */
    private const PRECIO_MAXIMO     = 99999999;
    private const PRECIO_DECIMALES  = 4;
    private const PORCENTAJE_MAXIMO = 100;
    private const PORCENTAJE_DECIMALES = 3;

    private $preciosLaborModel;
    private $preciosLoteModel;

    public function __construct()
    {
        parent::__construct();

        $this->preciosLaborModel = new PreciosLaborModel();
        $this->preciosLoteModel  = new PreciosLoteModel();
    }

    /**
     * Vista principal del módulo "Lista de precios / Precios por labor".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function porLabor($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->preciosLaborModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->preciosLaborModel->listarAnios($empresaSel) : [];

        $data['title']           = 'Gestión Palma · Precios por labor';
        $data['empresas']        = $empresas;
        $data['empresa_sel']     = $empresaSel;
        $data['anios']           = $empresaSel > 0 ? $this->preciosLaborModel->getAnios($empresaSel) : [];
        $data['labores_activas'] = $empresaSel > 0 ? $this->preciosLaborModel->totalLaboresActivas($empresaSel) : 0;
        $data['tabla_anios']     = $this->renderizarFilasAnios($filas);
        $data['total_anios']     = count($filas);

        return view('gestionpalma/precios_por_labor', $data);
    }

    /**
     * Vista principal del módulo "Lista de precios / Precios labor por lote".
     *
     * @param int $id_loseta Identificador de la loseta "Gestión Palma".
     */
    public function porLote($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $empresas   = $this->preciosLoteModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;

        $filas = $empresaSel > 0 ? $this->preciosLoteModel->listar($empresaSel) : [];

        $data['title']          = 'Gestión Palma · Precios labor por lote';
        $data['empresas']       = $empresas;
        $data['empresa_sel']    = $empresaSel;
        $data['anios']          = $empresaSel > 0 ? $this->preciosLoteModel->getAnios($empresaSel) : [];
        $data['fincas']         = $empresaSel > 0 ? $this->preciosLoteModel->getFincas($empresaSel) : [];
        $data['labores']        = $empresaSel > 0 ? $this->preciosLoteModel->getLabores($empresaSel) : [];
        $data['tabla_precios']  = $this->renderizarFilasPreciosLote($filas);
        $data['total_precios']  = count($filas);
        $data['niveles_precios'] = $this->nivelesPrecios($filas);

        return view('gestionpalma/precios_por_lote', $data);
    }

    public function reliquidar($id_loseta): string|RedirectResponse
    {
        return $this->enConstruccion(
            $id_loseta,
            'Reliquidar precios',
            'bi bi-arrow-repeat',
            'Reliquidación de los precios de labor ya registrados.'
        );
    }

    // ── Endpoints JSON: precios por labor (aNovedadLotePrecio) ────────────────

    public function listarAnios(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->preciosLaborModel->listarAnios($empresa, $this->campo('busqueda'));

        return $this->response->setJSON([
            'success'        => true,
            'message'        => '',
            'total'          => count($filas),
            'anios'          => $this->preciosLaborModel->getAnios($empresa),
            'laboresActivas' => $this->preciosLaborModel->totalLaboresActivas($empresa),
            'tabla'          => $this->renderizarFilasAnios($filas),
        ]);
    }

    public function detalleAnio(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe seleccionar un año válido.');
        }

        $filas = [];

        foreach ($this->preciosLaborModel->detalle($empresa, $anio) as $f) {
            $filas[] = [
                'novedad'          => trim((string) $f['novedad']),
                'registro'         => $f['registro'] === null ? null : (int) $f['registro'],
                'descripcion'      => trim((string) $f['descripcion']),
                'grupo'            => trim((string) $f['grupo']),
                'grupoDescripcion' => trim((string) $f['grupoDescripcion']),
                'uMedida'          => trim((string) $f['uMedida']),
                'precioDestajo'      => (float) $f['precioDestajo'],
                'precioContratistas' => (float) $f['precioContratistas'],
                'precioOtros'        => (float) $f['precioOtros'],
                'porcentaje'         => (float) $f['porcentaje'],
                'baseSueldo'       => (int) $f['baseSueldo'],
                'existe'           => (int) $f['existe'],
                'huerfana'         => (int) $f['huerfana'],
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'anio'    => $anio,
            'total'   => count($filas),
            'grupos'  => $this->preciosLaborModel->getGrupos($empresa),
            'filas'   => $filas,
        ]);
    }

    public function guardarPrecios(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe seleccionar un año válido.');
        }

        $filas = $this->filasPrecios($empresa, $anio);

        if (is_string($filas)) {
            return $this->error($filas);
        }

        if ($filas === []) {
            return $this->response->setJSON([
                'success'      => true,
                'message'      => 'No hay cambios por guardar.',
                'actualizados' => 0,
                'creados'      => 0,
            ]);
        }

        if (! $this->preciosLaborModel->existeAnio($empresa, $anio)) {
            return $this->error('No existe una lista de precios para el año ' . $anio . '.');
        }

        $resumen = $this->preciosLaborModel->guardar(
            $empresa,
            $anio,
            $filas,
            $this->session->get('usu_id'),
            $this->usuarioRegistro()
        );

        if ($resumen === null) {
            return $this->error('No se pudieron guardar los precios. La operación fue revertida.');
        }

        return $this->response->setJSON([
            'success'      => true,
            'message'      => 'Precios guardados correctamente.',
            'actualizados' => $resumen['actualizados'],
            'creados'      => $resumen['creados'],
        ]);
    }

    public function crearAnio(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null || (int) $anio < 2000 || (int) $anio > 2100) {
            return $this->error('El año debe tener 4 dígitos y estar entre 2000 y 2100.');
        }

        if ($this->preciosLaborModel->existeAnio($empresa, $anio)) {
            return $this->error('Ya existe una lista de precios para el año ' . $anio . '.');
        }

        $origen  = $this->campo('origen');
        $usu_id  = $this->session->get('usu_id');
        $usuario = $this->usuarioRegistro();

        if ($origen !== '') {
            if (! $this->preciosLaborModel->existeAnio($empresa, $origen)) {
                return $this->error('El año origen ' . $origen . ' no tiene precios registrados.');
            }

            $insertados = $this->preciosLaborModel->replicarAnio($empresa, $anio, $origen, $usu_id, $usuario);
            $mensaje    = 'Lista de precios del año ' . $anio . ' creada a partir del año ' . $origen . '.';
        } else {
            if ($this->preciosLaborModel->totalLaboresActivas($empresa) === 0) {
                return $this->error('La empresa no tiene labores activas para generar la lista de precios.');
            }

            $insertados = $this->preciosLaborModel->crearAnioVacio($empresa, $anio, $usu_id, $usuario);
            $mensaje    = 'Lista de precios del año ' . $anio . ' creada en ceros.';
        }

        if ($insertados === null) {
            return $this->error('No se pudo crear la lista de precios. La operación fue revertida.');
        }

        return $this->response->setJSON([
            'success'    => true,
            'message'    => $mensaje,
            'anio'       => $anio,
            'insertados' => $insertados,
        ]);
    }

    public function eliminarAnio(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe seleccionar un año válido.');
        }

        if (! $this->preciosLaborModel->existeAnio($empresa, $anio)) {
            return $this->error('No existe una lista de precios para el año ' . $anio . '.');
        }

        $transacciones = $this->preciosLaborModel->transaccionesAnio($empresa, $anio);

        if ($transacciones > 0) {
            return $this->error(
                'No se puede eliminar: el año ' . $anio . ' tiene '
                . number_format($transacciones, 0, ',', '.')
                . ' transacciones que dependen de esta lista de precios.'
            );
        }

        $eliminados = $this->preciosLaborModel->eliminarAnio($empresa, $anio, $this->session->get('usu_id'));

        if ($eliminados === null) {
            return $this->error('No se pudo eliminar la lista de precios. La operación fue revertida.');
        }

        return $this->response->setJSON([
            'success'    => true,
            'message'    => 'Lista de precios del año ' . $anio . ' eliminada correctamente.',
            'eliminados' => $eliminados,
        ]);
    }

    // ── Endpoints JSON: precios labor por lote (aNovedadPrecio) ───────────────

    public function listarPreciosLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $filas = $this->preciosLoteModel->listar(
            $empresa,
            $this->campo('anio'),
            $this->campo('finca'),
            $this->campo('busqueda')
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'total'   => count($filas),
            'niveles' => $this->nivelesPrecios($filas),
            'anios'   => $this->preciosLoteModel->getAnios($empresa),
            'fincas'  => $this->preciosLoteModel->getFincas($empresa),
            'labores' => $this->preciosLoteModel->getLabores($empresa),
            'tabla'   => $this->renderizarFilasPreciosLote($filas),
        ]);
    }

    public function obtenerPrecioLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave = $this->clavePrecioLote();

        if ($clave['anio'] === '') {
            return $this->error('Debe indicar el año del precio.');
        }

        $fila = $this->preciosLoteModel->obtener(
            $empresa,
            $clave['anio'],
            $clave['novedad'],
            $clave['finca'],
            $clave['lote'],
            $clave['seccion']
        );

        if ($fila === null) {
            return $this->error('El precio solicitado no existe.');
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => '',
            'precio'  => [
                'anio'               => trim((string) $fila['anio']),
                'novedad'            => trim((string) $fila['novedad']),
                'descripcion'        => trim((string) $fila['descripcion']),
                'finca'              => trim((string) $fila['finca']),
                'fincaDescripcion'   => trim((string) $fila['fincaDescripcion']),
                'lote'               => trim((string) $fila['lote']),
                'loteDescripcion'    => trim((string) $fila['loteDescripcion']),
                'seccion'            => trim((string) $fila['seccion']),
                'seccionDescripcion' => trim((string) $fila['seccionDescripcion']),
                'precioDestajo'      => (float) $fila['precioDestajo'],
                'precioContratistas' => (float) $fila['precioContratistas'],
                'precioOtros'        => (float) $fila['precioOtros'],
                'porcentaje'         => (float) $fila['porcentaje'],
                'baseSueldo'         => (int) $fila['baseSueldo'],
                'fechaRegistro'      => $this->formatearFechaHora($fila['fechaRegistro'] ?? null),
                'usuario'            => trim((string) $fila['usuario']),
            ],
        ]);
    }

    public function crearPrecioLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave  = $this->clavePrecioLote();
        $anio   = $this->anioValido();

        if ($anio === null || (int) $anio < 2000 || (int) $anio > 2100) {
            return $this->error('El año debe tener 4 dígitos y estar entre 2000 y 2100.');
        }

        if ($clave['novedad'] === '' || ! $this->preciosLoteModel->laborValida($empresa, $clave['novedad'])) {
            return $this->error('La labor ' . ($clave['novedad'] === '' ? '(sin código)' : $clave['novedad']) . ' no existe.');
        }

        if ($clave['finca'] === '' || ! $this->preciosLoteModel->fincaValida($empresa, $clave['finca'])) {
            return $this->error('La finca ' . ($clave['finca'] === '' ? '(sin código)' : $clave['finca']) . ' no existe.');
        }

        if ($clave['lote'] !== '') {
            if (! $this->preciosLoteModel->loteValido($empresa, $clave['lote'])) {
                return $this->error('El lote ' . $clave['lote'] . ' no existe.');
            }

            if (! $this->preciosLoteModel->loteDeFinca($empresa, $clave['finca'], $clave['lote'])) {
                return $this->error('El lote ' . $clave['lote'] . ' no pertenece a la finca ' . $clave['finca'] . '.');
            }
        }

        if ($clave['seccion'] !== '' && ! $this->preciosLoteModel->seccionValida($empresa, $clave['finca'], $clave['seccion'])) {
            return $this->error(
                $this->preciosLoteModel->totalSecciones($empresa, $clave['finca']) === 0
                    ? 'La finca ' . $clave['finca'] . ' no tiene secciones registradas: deje la sección vacía para que el precio aplique a toda la finca.'
                    : 'La sección ' . $clave['seccion'] . ' no existe en la finca ' . $clave['finca'] . '.'
            );
        }

        $valores = $this->valoresPrecioLote();

        if (is_string($valores)) {
            return $this->error($valores);
        }

        if ($this->preciosLoteModel->existe($empresa, $anio, $clave['novedad'], $clave['finca'], $clave['lote'], $clave['seccion'])) {
            return $this->error(
                'Ya existe un precio del año ' . $anio . ' para la labor ' . $clave['novedad']
                . ' en ' . $this->ambitoTexto($clave['finca'], $clave['lote'], $clave['seccion']) . '.'
            );
        }

        $ok = $this->preciosLoteModel->crear(
            $empresa,
            $anio,
            $clave['novedad'],
            $clave['finca'],
            $clave['lote'],
            $clave['seccion'],
            $valores,
            $this->session->get('usu_id'),
            $this->usuarioRegistro()
        );

        if (! $ok) {
            return $this->error('No se pudo crear el precio. La operación fue revertida.');
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Precio creado correctamente.',
        ]);
    }

    public function actualizarPrecioLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave = $this->clavePrecioLote();
        $anio  = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe indicar un año válido.');
        }

        if (! $this->preciosLoteModel->existe($empresa, $anio, $clave['novedad'], $clave['finca'], $clave['lote'], $clave['seccion'])) {
            return $this->error('El precio que intenta actualizar no existe.');
        }

        $valores = $this->valoresPrecioLote();

        if (is_string($valores)) {
            return $this->error($valores);
        }

        $ok = $this->preciosLoteModel->actualizar(
            $empresa,
            $anio,
            $clave['novedad'],
            $clave['finca'],
            $clave['lote'],
            $clave['seccion'],
            $valores,
            $this->session->get('usu_id'),
            $this->usuarioRegistro()
        );

        if (! $ok) {
            return $this->error('No se pudo actualizar el precio. La operación fue revertida.');
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Precio actualizado correctamente.',
        ]);
    }

    public function eliminarPrecioLote(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $clave = $this->clavePrecioLote();
        $anio  = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe indicar un año válido.');
        }

        if (! $this->preciosLoteModel->existe($empresa, $anio, $clave['novedad'], $clave['finca'], $clave['lote'], $clave['seccion'])) {
            return $this->error('El precio que intenta eliminar no existe.');
        }

        $ok = $this->preciosLoteModel->eliminar(
            $empresa,
            $anio,
            $clave['novedad'],
            $clave['finca'],
            $clave['lote'],
            $clave['seccion'],
            $this->session->get('usu_id')
        );

        if (! $ok) {
            return $this->error('No se pudo eliminar el precio. La operación fue revertida.');
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Precio eliminado correctamente.',
        ]);
    }

    /**
     * Lotes y secciones de una finca en una sola llamada. Hoy "secciones" siempre viene vacío:
     * aSecciones no tiene filas en ninguna empresa.
     */
    public function lotesDeFinca(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $finca = $this->campo('finca');

        if ($finca === '' || ! $this->preciosLoteModel->fincaValida($empresa, $finca)) {
            return $this->error('Debe seleccionar una finca válida.');
        }

        $lotes = [];

        foreach ($this->preciosLoteModel->getLotes($empresa, $finca) as $l) {
            $lotes[] = [
                'codigo'      => trim((string) $l['codigo']),
                'descripcion' => trim((string) $l['descripcion']),
                'activo'      => (int) $l['activo'],
            ];
        }

        $secciones = [];

        foreach ($this->preciosLoteModel->getSecciones($empresa, $finca) as $s) {
            $secciones[] = [
                'codigo'      => trim((string) $s['codigo']),
                'descripcion' => trim((string) $s['descripcion']),
            ];
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => '',
            'lotes'     => $lotes,
            'secciones' => $secciones,
        ]);
    }

    /**
     * Precio de la labor en la lista general del año (aNovedadLotePrecio), que es el valor que
     * esta pantalla sobrescribe. Que no exista no es un error.
     */
    public function precioBaseLabor(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $empresa = $this->empresaValida();

        if ($empresa === null) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = $this->anioValido();

        if ($anio === null) {
            return $this->error('Debe seleccionar un año válido.');
        }

        $novedad = $this->campo('novedad');

        if ($novedad === '') {
            return $this->error('Debe seleccionar una labor.');
        }

        $fila = $this->preciosLoteModel->precioBase($empresa, $anio, $novedad);

        return $this->response->setJSON([
            'success' => true,
            'message' => $fila === null ? 'La labor no tiene precio en la lista general del año ' . $anio . '.' : '',
            'existe'  => $fila === null ? 0 : 1,
            'precio'  => [
                'precioDestajo'      => (float) ($fila['precioDestajo'] ?? 0),
                'precioContratistas' => (float) ($fila['precioContratistas'] ?? 0),
                'precioOtros'        => (float) ($fila['precioOtros'] ?? 0),
                'porcentaje'         => (float) ($fila['porcentaje'] ?? 0),
                'baseSueldo'         => (int) ($fila['baseSueldo'] ?? 0),
            ],
        ]);
    }

    // ── Utilidades internas ───────────────────────────────────────────────────

    /**
     * Componentes de la llave de seis columnas recibidos por POST. "lote" y "seccion" viajan
     * vacíos cuando el precio aplica a toda la finca y así se guardan: cadena vacía, nunca NULL.
     */
    private function clavePrecioLote(): array
    {
        return [
            'anio'    => $this->campo('anio'),
            'novedad' => $this->campo('novedad'),
            'finca'   => $this->campo('finca'),
            'lote'    => $this->campo('lote'),
            'seccion' => $this->campo('seccion'),
        ];
    }

    /**
     * Valida los valores del formulario de precios por lote.
     *
     * @return array|string Valores listos para el modelo o el mensaje de error.
     */
    private function valoresPrecioLote(): array|string
    {
        $valores = [];

        foreach (['precioDestajo', 'precioContratistas', 'precioOtros'] as $columna) {
            $valor = $this->decimalValido($this->request->getPost($columna), self::PRECIO_MAXIMO, self::PRECIO_DECIMALES);

            if ($valor === null) {
                return 'El valor de ' . $columna . ' debe ser un número entre 0 y ' . self::PRECIO_MAXIMO
                    . ' con máximo ' . self::PRECIO_DECIMALES . ' decimales.';
            }

            $valores[$columna] = $valor;
        }

        $porcentaje = $this->decimalValido(
            $this->request->getPost('porcentaje'),
            self::PORCENTAJE_MAXIMO,
            self::PORCENTAJE_DECIMALES
        );

        if ($porcentaje === null) {
            return 'El porcentaje debe ser un número entre 0 y ' . self::PORCENTAJE_MAXIMO
                . ' con máximo ' . self::PORCENTAJE_DECIMALES . ' decimales.';
        }

        $baseSueldo = (string) ($this->request->getPost('baseSueldo') ?? 0);

        if (! in_array($baseSueldo, ['0', '1'], true)) {
            return 'El indicador de base sueldo solo admite 0 o 1.';
        }

        $valores['porcentaje'] = (float) $porcentaje;
        $valores['baseSueldo'] = (int) $baseSueldo;

        return $valores;
    }

    /**
     * Descripción del nivel al que aplica el precio dentro de la cascada del legado.
     */
    private function ambitoTexto(string $finca, string $lote, string $seccion): string
    {
        return 'la finca ' . $finca
            . ($seccion !== '' ? ', sección ' . $seccion : '')
            . ($lote !== '' ? ', lote ' . $lote : '');
    }

    /**
     * Nivel de la cascada al que aplica una fila: lote, seccion o finca.
     */
    private function nivelAmbito(string $lote, string $seccion): string
    {
        if ($lote !== '') {
            return 'lote';
        }

        return $seccion !== '' ? 'seccion' : 'finca';
    }

    /**
     * Cuántos precios hay por nivel, para el desglose del encabezado del listado.
     */
    private function nivelesPrecios(array $precios): array
    {
        $niveles = ['finca' => 0, 'seccion' => 0, 'lote' => 0];

        foreach ($precios as $p) {
            $nivel = $this->nivelAmbito(trim((string) $p['lote']), trim((string) $p['seccion']));
            $niveles[$nivel]++;
        }

        return $niveles;
    }

    /**
     * Chip del nivel de ámbito, con el mismo marcado que arma el JS.
     */
    private function chipAmbito(string $nivel): string
    {
        $chips = [
            'finca'   => ['bi-tree', 'Finca'],
            'seccion' => ['bi-grid-1x2', 'Sección'],
            'lote'    => ['bi-geo-alt', 'Lote'],
        ];

        return '<span class="gp-chip-ambito ' . $nivel . '"><i class="bi ' . $chips[$nivel][0]
            . ' me-1" aria-hidden="true"></i>' . $chips[$nivel][1] . '</span>';
    }

    /**
     * Ruta "finca › lote" de la celda de ámbito, con el mismo marcado que arma el JS.
     */
    private function rutaAmbito(
        string $finca,
        string $fincaNombre,
        string $lote,
        string $loteNombre,
        string $seccion,
        string $seccionNombre
    ): string {
        $nombre = htmlspecialchars($fincaNombre !== '' ? $fincaNombre : $finca);

        if ($lote !== '') {
            return $nombre . ' <span class="gp-ruta-sep">&rsaquo;</span> <span class="font-monospace"'
                . ($loteNombre !== '' ? ' title="' . htmlspecialchars($loteNombre) . '"' : '')
                . '>' . htmlspecialchars($lote) . '</span>';
        }

        if ($seccion !== '') {
            return $nombre . ' <span class="gp-ruta-sep">&rsaquo;</span> Sección '
                . htmlspecialchars($seccionNombre !== '' ? $seccionNombre : $seccion);
        }

        return $nombre . ' <span class="gp-ruta-sep">&rsaquo;</span> todos los lotes';
    }

    /**
     * Número con separador de miles y sin ceros decimales sobrantes.
     */
    private function formatearMonto(float $valor, int $decimales): string
    {
        $texto = number_format($valor, $decimales, ',', '.');

        return str_contains($texto, ',') ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }

    /**
     * Construye las filas <tr> de la tabla de precios por lote.
     */
    private function renderizarFilasPreciosLote(array $precios): string
    {
        $html  = '';
        $vacio = '<span class="gp-chip-rol">&mdash;</span>';

        foreach ($precios as $p) {
            $anio        = trim((string) $p['anio']);
            $novedad     = trim((string) $p['novedad']);
            $descripcion = trim((string) $p['descripcion']);
            $finca       = trim((string) $p['finca']);
            $fincaNombre = trim((string) $p['fincaDescripcion']);
            $lote        = trim((string) $p['lote']);
            $loteNombre  = trim((string) $p['loteDescripcion']);
            $seccion     = trim((string) $p['seccion']);
            $seccionNombre = trim((string) $p['seccionDescripcion']);
            $baseSueldo  = (int) $p['baseSueldo'] === 1 ? 1 : 0;

            $clave = ' data-anio="' . htmlspecialchars($anio) . '" data-novedad="' . htmlspecialchars($novedad)
                . '" data-finca="' . htmlspecialchars($finca) . '" data-lote="' . htmlspecialchars($lote)
                . '" data-seccion="' . htmlspecialchars($seccion) . '"';

            $celdaAnio = '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . htmlspecialchars($anio) . '</span>';

            $celdaLabor = '<span class="font-monospace">' . htmlspecialchars($novedad) . '</span>'
                . ($descripcion !== '' ? '<div class="gp-subtexto">' . htmlspecialchars($descripcion) . '</div>' : '');

            $nivel = $this->nivelAmbito($lote, $seccion);

            $ambito = $this->chipAmbito($nivel)
                . '<div class="gp-ruta-ambito">'
                . $this->rutaAmbito($finca, $fincaNombre, $lote, $loteNombre, $seccion, $seccionNombre)
                . '</div>';

            $celdaBase = $baseSueldo === 1
                ? '<span class="badge gp-badge-abierto rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Sí</span>'
                : $vacio;

            $html .= '<tr class="gp-fila-ambito-' . $nivel . '"' . $clave . '>'
                . '<td class="text-center">' . $celdaAnio . '</td>'
                . '<td data-order="' . htmlspecialchars($novedad) . '">' . $celdaLabor . '</td>'
                . '<td data-order="' . htmlspecialchars($nivel . ' ' . $finca . ' ' . $seccion . ' ' . $lote) . '">' . $ambito . '</td>';

            foreach (['precioDestajo', 'precioContratistas', 'precioOtros'] as $columna) {
                $valor  = (float) $p[$columna];
                $clases = 'text-end font-monospace';

                if ($valor === 0.0) {
                    $clases .= ' text-muted';
                }

                if ($columna === 'precioDestajo' && $baseSueldo === 1) {
                    $clases .= ' gp-valor-ignorado';
                }

                $html .= '<td class="' . $clases . '" data-order="' . $valor . '"'
                    . ($columna === 'precioDestajo' && $baseSueldo === 1
                        ? ' title="Este valor no se usa mientras Base sueldo esté activo."' : '')
                    . '>' . $this->formatearMonto($valor, self::PRECIO_DECIMALES) . '</td>';
            }

            $porcentaje = (float) $p['porcentaje'];
            $clasesPct  = 'text-end font-monospace';

            if ($porcentaje === 0.0) {
                $clasesPct .= ' text-muted';
            }

            if ($baseSueldo === 1 && $porcentaje > 0) {
                $clasesPct .= ' gp-valor-vigente';
            }

            $html .= '<td class="' . $clasesPct . '" data-order="' . $porcentaje . '">'
                . $this->formatearMonto($porcentaje, self::PORCENTAJE_DECIMALES) . ' %</td>'
                . '<td class="text-center" data-order="' . $baseSueldo . '">' . $celdaBase . '</td>'
                . '<td class="text-center gp-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar" aria-label="Editar" data-accion="editar"' . $clave . '><i class="bi bi-pencil" aria-hidden="true"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="Duplicar en otro ámbito" aria-label="Duplicar en otro ámbito" data-accion="duplicar"' . $clave . '><i class="bi bi-files" aria-hidden="true"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar" aria-label="Eliminar" data-accion="eliminar"' . $clave . '><i class="bi bi-trash" aria-hidden="true"></i></button>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }


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

        if ($empresa === null || ! is_numeric($empresa) || ! $this->preciosLaborModel->empresaExiste((int) $empresa)) {
            return null;
        }

        return (int) $empresa;
    }

    /**
     * Año recibido por POST. La columna es varchar pero siempre guarda 4 dígitos.
     */
    private function anioValido(): ?string
    {
        $anio = $this->campo('anio');

        return preg_match('/^\d{4}$/', $anio) === 1 ? $anio : null;
    }

    /**
     * Valida el JSON de filas modificadas y las normaliza para el modelo.
     *
     * @return array|string Filas listas para guardar o el mensaje de error.
     */
    private function filasPrecios(int $empresa, string $anio): array|string
    {
        $crudo = (string) $this->request->getPost('filas');

        if (trim($crudo) === '') {
            return [];
        }

        $recibidas = json_decode($crudo, true);

        if (! is_array($recibidas)) {
            return 'No se pudieron leer las filas enviadas.';
        }

        if ($recibidas === []) {
            return [];
        }

        $validas = $this->preciosLaborModel->novedadesValidas($empresa, $anio);
        $filas   = [];

        foreach ($recibidas as $fila) {
            if (! is_array($fila)) {
                return 'No se pudieron leer las filas enviadas.';
            }

            $novedad = trim((string) ($fila['novedad'] ?? ''));

            if ($novedad === '' || ! in_array($novedad, $validas, true)) {
                return 'La labor ' . ($novedad === '' ? '(sin código)' : $novedad) . ' no existe en el año ' . $anio . '.';
            }

            $valores = [];

            foreach (['precioDestajo', 'precioContratistas', 'precioOtros'] as $columna) {
                $valor = $this->decimalValido($fila[$columna] ?? null, self::PRECIO_MAXIMO, self::PRECIO_DECIMALES);

                if ($valor === null) {
                    return 'El valor de ' . $columna . ' de la labor ' . $novedad
                        . ' debe ser un número entre 0 y ' . self::PRECIO_MAXIMO . ' con máximo '
                        . self::PRECIO_DECIMALES . ' decimales.';
                }

                $valores[$columna] = $valor;
            }

            $porcentaje = $this->decimalValido($fila['porcentaje'] ?? null, self::PORCENTAJE_MAXIMO, self::PORCENTAJE_DECIMALES);

            if ($porcentaje === null) {
                return 'El porcentaje de la labor ' . $novedad . ' debe ser un número entre 0 y '
                    . self::PORCENTAJE_MAXIMO . ' con máximo ' . self::PORCENTAJE_DECIMALES . ' decimales.';
            }

            $baseSueldo = (string) ($fila['baseSueldo'] ?? 0);

            if (! in_array($baseSueldo, ['0', '1'], true)) {
                return 'El indicador de base sueldo de la labor ' . $novedad . ' solo admite 0 o 1.';
            }

            $filas[] = array_merge($valores, [
                'novedad'    => $novedad,
                'porcentaje' => $porcentaje,
                'baseSueldo' => (int) $baseSueldo,
            ]);
        }

        return $filas;
    }

    /**
     * Normaliza un valor numérico del formulario, o null si no cumple rango o decimales.
     */
    private function decimalValido($valor, float $maximo, int $decimales): ?float
    {
        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            return null;
        }

        $numero = (float) $valor;

        if ($numero < 0 || $numero > $maximo) {
            return null;
        }

        return abs($numero - round($numero, $decimales)) > 0.0000001 ? null : round($numero, $decimales);
    }

    /**
     * Login que queda en la columna usuario.
     */
    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : mb_substr($usuario, 0, 50);
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
     * Construye las filas <tr> de la tabla de años con lista de precios.
     */
    private function renderizarFilasAnios(array $anios): string
    {
        $html  = '';
        $vacio = '<span class="text-muted">&mdash;</span>';

        foreach ($anios as $a) {
            $anio      = trim((string) $a['anio']);
            $labores   = (int) $a['labores'];
            $conPrecio = (int) $a['conPrecio'];
            $fecha     = $this->formatearFechaHora($a['fechaRegistro'] ?? null);
            $usuario   = trim((string) ($a['usuario'] ?? ''));

            $celdaAnio = '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">'
                . htmlspecialchars($anio) . '</span>';

            $celdaLabores = '<span class="font-monospace">' . $labores . '</span>';

            $celdaConPrecio = $conPrecio > 0
                ? '<span class="badge gp-badge-doc rounded-pill px-2 py-1 font-monospace">' . $conPrecio . ' de ' . $labores . '</span>'
                : '<span class="badge gp-badge-cierre rounded-pill px-2 py-1">Sin precios</span>';

            $celdaFecha = $fecha === null
                ? $vacio
                : '<span class="font-monospace">' . date('d/m/Y', strtotime($fecha)) . '</span>';

            $celdaUsuario = $usuario === '' ? $vacio : htmlspecialchars($usuario);

            $html .= '<tr data-anio="' . htmlspecialchars($anio) . '" data-labores="' . $labores
                . '" data-con-precio="' . $conPrecio . '">'
                . '<td class="text-center">' . $celdaAnio . '</td>'
                . '<td class="text-end" data-order="' . $labores . '">' . $celdaLabores . '</td>'
                . '<td class="text-center" data-order="' . $conPrecio . '">' . $celdaConPrecio . '</td>'
                . '<td data-order="' . htmlspecialchars((string) $fecha) . '">' . $celdaFecha . '</td>'
                . '<td data-order="' . htmlspecialchars($usuario) . '">' . $celdaUsuario . '</td>'
                . '<td class="text-center gp-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar precios del año" data-accion="editar" data-anio="' . htmlspecialchars($anio) . '"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-primary" title="Duplicar en un año nuevo" data-accion="duplicar" data-anio="' . htmlspecialchars($anio) . '"><i class="bi bi-files"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar el año" data-accion="eliminar" data-anio="' . htmlspecialchars($anio) . '"><i class="bi bi-trash"></i></button>'
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

    private function error(string $mensaje): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode(422);
    }
}
