<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * Andamio temporal de validacion del CRUD de Nomina > Periodos.
 * Confinado a empresa 1 / anio 2099. Borrar tras ejecutar.
 */
final class PeriodosNominaTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const EMPRESA = 1;
    private const ANIO    = 2099;
    private const MES     = 12;
    private const NO      = 99;
    private const RUTA    = 'gestion-palma/nomina/periodos/';

    private $cnx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cnx = Database::connect();
    }

    private function sesion(): array
    {
        return [
            'usu_autenticado' => 'si',
            'usu_id'          => 6,
            'usu_login'       => 'coordinadordesarrollo',
            'usu_nombres'     => 'Coordinador',
            'usu_apellidos'   => 'Desarrollo',
            'usu_email'       => 'coordinadordesarrollo@inversionesarar.com',
            'usu_empresa'     => 1,
            'ultimo_acceso'   => time(),
        ];
    }

    private function enviar(string $accion, array $datos, bool $conSesion = true)
    {
        if (! $conSesion) {
            return $this->call('post', self::RUTA . $accion, $datos);
        }

        return $this->withSession($this->sesion())->call('post', self::RUTA . $accion, $datos);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'empresa'        => self::EMPRESA,
            'anio'           => self::ANIO,
            'mes'            => self::MES,
            'noPeriodo'      => self::NO,
            'tipoNomina'     => 'M',
            'diasNomina'     => 30,
            'fechaInicial'   => '2099-12-01',
            'fechaFinal'     => '2099-12-31',
            'fechaCorte'     => '2099-12-31',
            'fechaPago'      => '2100-01-05',
            'nombrePeriodo'  => 'QA andamio temporal',
            'cerrado'        => 0,
            'agronomico'     => 1,
            'ejecutaLabores' => 1,
        ], $extra);
    }

    private function fila(): ?array
    {
        return $this->cnx->query(
            'SELECT *, [año] AS anio FROM nPeriodoDetalle WHERE empresa = ? AND [año] = ? AND mes = ? AND noPeriodo = ?',
            [self::EMPRESA, self::ANIO, self::MES, self::NO]
        )->getRowArray();
    }

    private function filasAnioPrueba(): int
    {
        return (int) $this->cnx->query(
            'SELECT COUNT(*) AS n FROM nPeriodoDetalle WHERE [año] = ?',
            [self::ANIO]
        )->getRowArray()['n'];
    }

    private function totalFilas(): int
    {
        return (int) $this->cnx->query('SELECT COUNT(*) AS n FROM nPeriodoDetalle')->getRowArray()['n'];
    }

    private function asientos(): int
    {
        return (int) $this->cnx->query(
            "SELECT COUNT(*) AS n FROM Auditoria WHERE TablaAfectada = 'nPeriodoDetalle'"
        )->getRowArray()['n'];
    }

    private function texto(string $etiqueta, $valor): void
    {
        fwrite(STDERR, "\n  [{$etiqueta}] {$valor}");
    }

    public function testEstadoInicialLimpio(): void
    {
        $this->texto('INICIAL total', $this->totalFilas());
        $this->texto('INICIAL anio 2099', $this->filasAnioPrueba());
        $this->texto('INICIAL asientos', $this->asientos());

        $this->assertSame(0, $this->filasAnioPrueba(), 'El anio de pruebas debe estar vacio antes de empezar.');
    }

    public function testRenderAutenticado(): void
    {
        $result = $this->withSession($this->sesion())->call('get', 'gestion-palma/nomina/periodos/25');

        $result->assertStatus(200);
        $cuerpo = $result->getBody();

        $this->assertStringContainsString('Periodos de nómina', $cuerpo);
        $this->assertStringContainsString('id="tabla_periodos_nomina"', $cuerpo);
        $this->assertStringContainsString('TIPOS_NOMINA', $cuerpo);

        $this->assertSame(11, substr_count($cuerpo, '<th'), 'El thead debe tener 11 columnas.');

        preg_match('/const TIPOS_NOMINA = (.+?);\s*<\/script>/s', $cuerpo, $m);
        $this->assertNotEmpty($m, 'TIPOS_NOMINA debe emitirse en la vista.');
        $mapa = json_decode($m[1], true);
        $this->assertIsArray($mapa);
        $this->assertArrayHasKey('1', $mapa);
        $this->texto('RENDER tipos empresa 1', count($mapa['1']));
    }

    public function testSinSesionDevuelve401(): void
    {
        foreach (['listar', 'obtener', 'crear', 'actualizar', 'eliminar', 'alternar'] as $accion) {
            $this->enviar($accion, $this->datos(), false)->assertStatus(401);
        }

        $this->assertSame(0, $this->filasAnioPrueba());
    }

    public function testCicloCrud(): void
    {
        $asientosAntes = $this->asientos();

        $crear = $this->enviar('crear', $this->datos());
        $crear->assertStatus(200);
        $this->assertTrue($crear->getJSON() !== null && json_decode($crear->getJSON())->success);

        $fila = $this->fila();
        $this->assertNotNull($fila, 'La fila debe existir tras crear.');
        $this->assertSame('coordinadordesarrollo', trim((string) $fila['usuario']));
        $this->assertSame('M', trim((string) $fila['tipoNomina']));
        $this->assertSame(30, (int) $fila['diasNomina']);
        $this->assertSame(1, (int) $fila['agronomico']);
        $this->assertSame(1, (int) $fila['ejecutaLabores']);
        $this->assertSame(0, (int) $fila['cerrado']);
        $this->assertNotEmpty($fila['fechaRegistro']);
        $this->texto('CREAR ok, filas 2099', $this->filasAnioPrueba());

        $duplicado = $this->enviar('crear', $this->datos());
        $duplicado->assertStatus(422);
        $this->assertSame(1, $this->filasAnioPrueba(), 'El duplicado no debe insertar una segunda fila.');
        $this->texto('DUPLICADO rechazado', json_decode($duplicado->getJSON())->message);

        $obtener = $this->enviar('obtener', [
            'empresa' => self::EMPRESA, 'anio' => self::ANIO, 'mes' => self::MES, 'noPeriodo' => self::NO,
        ]);
        $obtener->assertStatus(200);
        $periodo = json_decode($obtener->getJSON(), true)['periodo'];
        $this->assertSame(self::ANIO, $periodo['año']);
        $this->assertSame('2099-12-01', $periodo['fechaInicial']);
        $this->assertSame('2100-01-05', $periodo['fechaPago']);
        $this->texto('OBTENER ok', $periodo['nombrePeriodo']);

        $actualizar = $this->enviar('actualizar', $this->datos([
            'tipoNomina'     => 'Q',
            'diasNomina'     => 15,
            'fechaInicial'   => '2099-12-05',
            'fechaFinal'     => '2099-12-20',
            'fechaCorte'     => '2099-12-20',
            'fechaPago'      => '2099-12-28',
            'nombrePeriodo'  => 'QA andamio editado',
            'agronomico'     => 0,
            'ejecutaLabores' => 0,
        ]));
        $actualizar->assertStatus(200);

        $fila = $this->fila();
        $this->assertNotNull($fila, 'La llave no debe cambiar al actualizar.');
        $this->assertSame('Q', trim((string) $fila['tipoNomina']));
        $this->assertSame(15, (int) $fila['diasNomina']);
        $this->assertSame(0, (int) $fila['agronomico']);
        $this->assertSame('QA andamio editado', trim((string) $fila['nombrePeriodo']));
        $this->assertSame(1, $this->filasAnioPrueba());
        $this->texto('ACTUALIZAR ok', 'llave intacta');

        $clave = ['empresa' => self::EMPRESA, 'anio' => self::ANIO, 'mes' => self::MES, 'noPeriodo' => self::NO];

        $this->enviar('alternar', $clave)->assertStatus(200);
        $this->assertSame(1, (int) $this->fila()['cerrado']);
        $this->enviar('alternar', $clave)->assertStatus(200);
        $this->assertSame(0, (int) $this->fila()['cerrado']);
        $this->texto('ALTERNAR ok', '0 -> 1 -> 0');

        $this->enviar('eliminar', $clave)->assertStatus(200);
        $this->assertNull($this->fila(), 'La fila debe desaparecer tras eliminar.');
        $this->assertSame(0, $this->filasAnioPrueba());

        $reintento = $this->enviar('eliminar', $clave);
        $reintento->assertStatus(422);
        $this->texto('ELIMINAR ok, reintento', json_decode($reintento->getJSON())->message);

        $nuevos = $this->asientos() - $asientosAntes;
        $this->texto('AUDITORIA asientos nuevos', $nuevos);
        $this->assertSame(5, $nuevos, 'Deben quedar 5 asientos: crear, actualizar, alternar x2, eliminar.');
    }

    public function testValidacionesRechazadas(): void
    {
        $casos = [
            'mes 13'                  => ['mes' => 13],
            'mes 0'                   => ['mes' => 0],
            'anio 1800'               => ['anio' => 1800],
            'noPeriodo 0'             => ['noPeriodo' => 0],
            'tipo inexistente'        => ['tipoNomina' => 'X'],
            'diasNomina 45'           => ['diasNomina' => 45],
            'fechaFinal anterior'     => ['fechaFinal' => '2099-11-01'],
            'fechaCorte anterior'     => ['fechaCorte' => '2099-11-01'],
            'empresa inexistente'     => ['empresa' => 999],
        ];

        foreach ($casos as $etiqueta => $extra) {
            $respuesta = $this->enviar('crear', $this->datos($extra));
            $respuesta->assertStatus(422);
            $this->texto('RECHAZA ' . $etiqueta, json_decode($respuesta->getJSON())->message);
        }

        $this->assertSame(0, $this->filasAnioPrueba(), 'Ninguna validacion rechazada debe escribir.');
    }

    public function testEstadoFinalLimpio(): void
    {
        $this->texto('FINAL total', $this->totalFilas());
        $this->texto('FINAL anio 2099', $this->filasAnioPrueba());
        $this->texto('FINAL asientos', $this->asientos());

        $this->assertSame(0, $this->filasAnioPrueba());
        $this->assertSame(79, $this->totalFilas());
    }
}
