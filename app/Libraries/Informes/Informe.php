<?php

namespace App\Libraries\Informes;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

abstract class Informe
{
    public const MAX_FILAS = 20000;

    protected BaseConnection $db;

    public function __construct(protected int $empresa)
    {
        $this->db = Database::connect();
    }

    abstract public function filtros(): array;

    abstract public function consultar(array $filtros): array;

    public function opciones(string $filtro, array $dependencias): array
    {
        return [];
    }

    public function validar(array $entrada): array|string
    {
        $valores = [];

        foreach ($this->filtros() as $filtro) {
            $valor = $entrada[$filtro['clave']] ?? '';
            $valor = is_array($valor) ? '' : trim((string) $valor);
            $valor = $valor === '' ? (string) ($filtro['defecto'] ?? '') : $valor;

            if ($valor === '') {
                if (! empty($filtro['obligatorio'])) {
                    return 'El filtro "' . $filtro['etiqueta'] . '" es obligatorio.';
                }

                $valores[$filtro['clave']] = '';

                continue;
            }

            if (isset($filtro['opciones']) && ! in_array($valor, array_map('strval', array_column($filtro['opciones'], 'valor')), true)) {
                return 'El valor del filtro "' . $filtro['etiqueta'] . '" no es válido.';
            }

            if ($filtro['tipo'] === 'fecha' && (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $p) !== 1 || ! checkdate((int) $p[2], (int) $p[3], (int) $p[1]))) {
                return 'El filtro "' . $filtro['etiqueta'] . '" debe tener el formato AAAA-MM-DD.';
            }

            $valores[$filtro['clave']] = $valor;
        }

        return $valores;
    }

    public function descripcion(array $valores): array
    {
        $descripcion = [];

        foreach ($this->filtros() as $filtro) {
            $valor = $valores[$filtro['clave']] ?? '';
            $texto = $valor === '' ? ($filtro['placeholder'] ?? 'Todos') : $valor;

            foreach ($filtro['opciones'] ?? [] as $opcion) {
                if ((string) $opcion['valor'] === $valor) {
                    $texto = $opcion['texto'];
                }
            }

            $descripcion[] = ['etiqueta' => $filtro['etiqueta'], 'valor' => $texto];
        }

        return $descripcion;
    }
}
