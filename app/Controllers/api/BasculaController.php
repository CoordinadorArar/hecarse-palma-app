<?php

namespace App\Controllers\api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class BasculaController extends BaseController
{
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

        $tabla = rtrim(preg_replace('/[^A-Za-z0-9_.]/', '_', $datos['tabla']), '.');

        if ($tabla === '') {
            return $this->response->setJSON(['ok' => false, 'error' => 'payload inválido'])->setStatusCode(400);
        }

        $lote = (int) ($datos['lote'] ?? 0);
        $carpeta = WRITEPATH . 'datos_bascula/' . date('Ymd') . '/' . $tabla;

        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            return $this->response->setJSON(['ok' => false, 'error' => 'no se pudo crear la carpeta'])->setStatusCode(500);
        }

        $archivo = sprintf('%s/lote_%05d.json', $carpeta, $lote);
        $contenido = json_encode($datos['filas'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($contenido === false || file_put_contents($archivo, $contenido, LOCK_EX) === false) {
            return $this->response->setJSON(['ok' => false, 'error' => 'no se pudo guardar el lote'])->setStatusCode(500);
        }

        return $this->response->setJSON(['ok' => true, 'tabla' => $tabla, 'lote' => $lote, 'filas' => count($datos['filas'])])->setStatusCode(200);
    }
}
