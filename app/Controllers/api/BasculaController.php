<?php

namespace App\Controllers\api;

use App\Controllers\BaseController;
use App\Models\BasculaModel;
use CodeIgniter\HTTP\ResponseInterface;

class BasculaController extends BaseController
{
    private const TABLAS = [
        'registros' => [
            'destino'  => 'bBasculaRegistro',
            'llave'    => 'numero',
            'fechas'   => ['f_entrada', 'f_salida'],
            'columnas' => [
                'numero', 'placa', 'cliente', 'nit', 'direccion', 'telefono', 'conductor', 'cedula', 'origen', 'destino',
                'transportista', 'observaciones', 'producto', 'codigo', 'proceso', 'f_entrada', 'f_salida', 'tipo',
                'pesada1', 'pesada2', 'peso_neto', 'estado', 'usuario', 'n_registro', 'costo', 'camion',
                'eje1', 'eje2', 'eje3', 'eje4', 'eje5', 'eje6', 'eje7', 'eje8', 'ctda_eje',
            ],
        ],
    ];

    public function receiveData(): ResponseInterface
    {
        $token = (string) env('bascula.token');

        if ($token === '' || !hash_equals($token, $this->request->getHeaderLine('X-Api-Token'))) {
            return $this->response->setJSON(['ok' => false, 'error' => 'no autorizado'])->setStatusCode(401);
        }

        $datos = json_decode((string) $this->request->getBody(), true);

        if (!is_array($datos) || empty($datos['tabla']) || !is_string($datos['tabla']) || !isset($datos['filas']) || !is_array($datos['filas'])) {
            return $this->response->setJSON(['ok' => false, 'error' => 'payload inválido'])->setStatusCode(400);
        }

        $partes = explode('.', $datos['tabla']);
        $tabla = strtolower(trim(end($partes), " []\t\r\n"));

        if (!isset(self::TABLAS[$tabla])) {
            return $this->response->setJSON(['ok' => false, 'error' => 'tabla no soportada'])->setStatusCode(400);
        }

        $modelo = new BasculaModel();
        $fincas = $modelo->fincas();
        $cos = array_column($fincas, 'co', 'codigo');
        $codigo = $datos['codigoFinca'] ?? null;
        $codigo = is_string($codigo) || is_int($codigo) ? trim((string) $codigo) : '';
        $coEquipo = $codigo === '' ? '' : str_pad($codigo, 3, '0', STR_PAD_LEFT);

        if ($coEquipo === '' || !in_array($coEquipo, $cos, true)) {
            return $this->response->setJSON(['ok' => false, 'error' => 'codigoFinca inválido'])->setStatusCode(400);
        }

        if (!$modelo->columnasFinca()) {
            return $this->response->setJSON(['ok' => false, 'error' => 'pendiente actualización de base de datos'])->setStatusCode(503);
        }

        $porLargo = BasculaModel::porLargo($fincas);
        $config = self::TABLAS[$tabla];
        $lote = (int) ($datos['lote'] ?? 0);
        $filas = [];

        foreach ($datos['filas'] as $fila) {
            $numero = is_array($fila) ? filter_var($fila[$config['llave']] ?? null, FILTER_VALIDATE_INT) : false;

            if ($numero === false || isset($filas[$numero])) {
                continue;
            }

            $registro = [];

            foreach ($config['columnas'] as $columna) {
                $valor = $fila[$columna] ?? null;
                $registro[$columna] = is_scalar($valor) ? $valor : null;
            }

            foreach ($config['fechas'] as $columna) {
                $fecha = is_string($registro[$columna]) && $registro[$columna] !== '' ? \DateTime::createFromFormat('Y-m-d g:i:s A', trim($registro[$columna])) : false;
                $registro[$columna] = $fecha ? $fecha->format('Y-m-d\TH:i:s') : null;
            }

            $registro[$config['llave']] = $numero;
            $registro['lote'] = $lote;
            $registro['coEquipo'] = $coEquipo;
            $registro['coFinca'] = BasculaModel::coFinca((string) $registro['origen'], $coEquipo, $porLargo, $cos);
            $filas[$numero] = $registro;
        }

        $insertadas = $modelo->insertarNuevos($config['destino'], $config['llave'], array_values($filas), ['coEquipo' => $coEquipo]);

        if ($insertadas === null) {
            return $this->response->setJSON(['ok' => false, 'error' => 'no se pudo guardar el lote'])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'ok'          => true,
            'tabla'       => $tabla,
            'lote'        => $lote,
            'codigoFinca' => $coEquipo,
            'recibidas'   => count($datos['filas']),
            'insertadas'  => $insertadas,
            'existentes'  => count($filas) - $insertadas,
            'omitidas'    => count($datos['filas']) - count($filas),
        ])->setStatusCode(200);
    }
}
