<?php

namespace App\Controllers\informes;

use App\Controllers\BaseController;
use App\Libraries\Informes\Catalogo;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Models\ReportesModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportesController extends BaseController
{
    private $session;
    private ReportesModel $reportesModel;

    public function __construct()
    {
        $this->session       = session();
        $this->reportesModel = new ReportesModel();
    }

    public function modulo(string $clave, $id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        if (! isset(Catalogo::MODULOS[$clave]) || ! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), 'informes/' . $clave)) {
            return redirect()->to(base_url('losetas'));
        }

        $empresas   = $this->reportesModel->getEmpresas();
        $empresaSel = $empresas ? (int) $empresas[0]['id'] : 0;
        $pedida     = (int) $this->request->getGet('empresa');

        if ($pedida > 0 && in_array($pedida, array_map('intval', array_column($empresas, 'id')), true)) {
            $empresaSel = $pedida;
        }

        $informes = [];

        foreach (Catalogo::informes($clave) as $informe) {
            $clase     = $informe['clase'];
            $filtros   = [];

            if ($clase !== null && $empresaSel > 0) {
                try {
                    $filtros = (new $clase($empresaSel))->filtros();
                } catch (\Throwable $e) {
                    log_message('error', 'Error cargando los filtros del informe ' . $informe['clave'] . ': ' . $e->getMessage());
                }
            }

            unset($informe['clase']);
            $informes[] = $informe + ['filtros' => $filtros];
        }

        $pedido = trim((string) $this->request->getGet('informe'));

        $data['title']       = 'Informes · ' . Catalogo::MODULOS[$clave]['nombre'];
        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresaSel;
        $data['modulo']      = ['clave' => $clave] + Catalogo::MODULOS[$clave];
        $data['informes']    = $informes;
        $data['informe_sel'] = in_array($pedido, array_column($informes, 'clave'), true) ? $pedido : '';

        return view('informes/modulo', $data);
    }

    public function consultar(): ResponseInterface
    {
        $contexto = $this->contexto();

        if ($contexto instanceof ResponseInterface) {
            return $contexto;
        }

        return $this->response->setJSON(array_merge([
            'success' => true,
            'message' => '',
            'informe' => ['clave' => $contexto['informe']['clave'], 'nombre' => $contexto['informe']['nombre']],
            'kpis'    => [],
            'grafica' => null,
            'agrupar' => null,
            'avisos'  => [],
        ], $contexto['resultado']));
    }

    public function exportar(): ResponseInterface
    {
        $contexto = $this->contexto();

        if ($contexto instanceof ResponseInterface) {
            return $contexto;
        }

        if (! class_exists(Spreadsheet::class)) {
            return $this->error('La exportación a Excel no está disponible en el servidor.', 500);
        }

        $resultado = $contexto['resultado'];
        $columnas  = $resultado['columnas'];
        $libro     = new Spreadsheet();
        $hoja      = $libro->getActiveSheet();
        $ultima    = Coordinate::stringFromColumnIndex(max(1, count($columnas)));
        $formatos  = ['entero' => '#,##0', 'decimal' => '#,##0.00', 'moneda' => '#,##0'];

        $hoja->setTitle('Informe');
        $hoja->setCellValueExplicit('A1', $contexto['informe']['nombre'], DataType::TYPE_STRING);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->setCellValue('A2', 'Generado: ' . date('Y-m-d H:i'));

        $fila = 3;

        foreach ($contexto['descripcion'] as $filtro) {
            $hoja->setCellValueExplicit('A' . $fila, $filtro['etiqueta'] . ': ' . $filtro['valor'], DataType::TYPE_STRING);
            $fila++;
        }

        $fila++;
        $encabezado = $fila;

        foreach ($columnas as $i => $columna) {
            $hoja->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . $fila, $columna['titulo'], DataType::TYPE_STRING);
        }

        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFont()->setBold(true);
        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9EEF4');

        foreach ($resultado['filas'] as $registro) {
            $fila++;

            foreach ($columnas as $i => $columna) {
                $celda = Coordinate::stringFromColumnIndex($i + 1) . $fila;
                $valor = $registro[$columna['clave']] ?? '';

                isset($formatos[$columna['tipo']]) ? $hoja->setCellValue($celda, $valor) : $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
            }
        }

        if (array_filter(array_column($columnas, 'total'))) {
            $fila++;
            $hoja->setCellValue('A' . $fila, 'Totales');

            foreach ($columnas as $i => $columna) {
                if ($columna['total'] && isset($resultado['totales'][$columna['clave']])) {
                    $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $fila, $resultado['totales'][$columna['clave']]);
                }
            }

            $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFont()->setBold(true);
        }

        foreach ($columnas as $i => $columna) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);

            if (isset($formatos[$columna['tipo']])) {
                $hoja->getStyle($letra . ($encabezado + 1) . ':' . $letra . $fila)->getNumberFormat()->setFormatCode($formatos[$columna['tipo']]);
            }

            $hoja->getColumnDimension($letra)->setAutoSize(true);
        }

        $hoja->freezePane('A' . ($encabezado + 1));

        ob_start();
        (new Xlsx($libro))->save('php://output');
        $contenido = ob_get_clean();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $contexto['informe']['clave'] . '-' . date('Ymd-His') . '.xlsx"')
            ->setBody($contenido);
    }

    public function opciones(): ResponseInterface
    {
        $informe = $this->informeSolicitado();

        if ($informe instanceof ResponseInterface) {
            return $informe;
        }

        $dependencias = $this->arreglo('dependencias');

        try {
            $filas = $informe['instancia']->opciones(trim((string) $this->request->getPost('filtro')), $dependencias);
        } catch (\Throwable $e) {
            log_message('error', 'Error cargando opciones del informe: ' . $e->getMessage());

            return $this->error('No se pudieron cargar las opciones del filtro.', 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => '', 'filas' => $filas]);
    }

    private function contexto(): array|ResponseInterface
    {
        $informe = $this->informeSolicitado();

        if ($informe instanceof ResponseInterface) {
            return $informe;
        }

        $valores = $informe['instancia']->validar($this->arreglo('filtros'));

        if (is_string($valores)) {
            return $this->error($valores);
        }

        try {
            $resultado = $informe['instancia']->consultar($valores);
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando el informe ' . $informe['clave'] . ': ' . $e->getMessage());

            return $this->error('No se pudo consultar el informe.', 500);
        }

        return [
            'informe'     => $informe,
            'resultado'   => $resultado,
            'descripcion' => $informe['instancia']->descripcion($valores),
        ];
    }

    private function informeSolicitado(): array|ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->response->setJSON(['success' => false, 'message' => 'Sesión expirada. Vuelva a iniciar sesión.'])->setStatusCode(401);
        }

        $modulo = trim((string) $this->request->getPost('modulo'));

        if (! isset(Catalogo::MODULOS[$modulo])) {
            return $this->error('El módulo de informes no es válido.');
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), 'informes/' . $modulo)) {
            return $this->error('No tiene permiso para consultar los informes de ' . Catalogo::MODULOS[$modulo]['nombre'] . '.', 403);
        }

        $empresa = (int) $this->request->getPost('empresa');

        if ($empresa <= 0 || ! $this->reportesModel->empresaExiste($empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $informe = Catalogo::informe($modulo, trim((string) $this->request->getPost('informe')));

        if ($informe === null) {
            return $this->error('El informe solicitado no existe.', 404);
        }

        if ($informe['clase'] === null) {
            return $this->error('El informe "' . $informe['nombre'] . '" aún no está disponible.');
        }

        $clase = $informe['clase'];

        $informe['instancia'] = new $clase($empresa);

        return $informe;
    }

    private function arreglo(string $nombre): array
    {
        $valor = $this->request->getPost($nombre);

        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }

        return is_array($valor) ? $valor : [];
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON(['success' => false, 'message' => $mensaje])->setStatusCode($codigo);
    }
}
