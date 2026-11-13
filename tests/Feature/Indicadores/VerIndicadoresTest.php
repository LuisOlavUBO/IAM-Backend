<?php

namespace Tests\Feature\Indicadores;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\TablasDeApiAuth;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec indicadores: GET /api/iam/v1/indicadores, its blocks by permission, the cache and that it
 * never writes; spec usuarios: the inactivity filter matches the indicator.
 */
class VerIndicadoresTest extends TestCase
{
    use RefreshDatabase;
    use TablasDeApiAuth;

    private const TODOS = ['iam:usuarios.leer', 'iam:aplicaciones.leer', 'iam:clientes.leer', 'iam:auditoria.leer'];

    private CarbonImmutable $ahora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablasDeApiAuth();
        $this->ahora = CarbonImmutable::parse('2026-11-12 12:00:00');
        $this->travelTo($this->ahora);
    }

    /**
     * @param  list<string>  $permisos
     */
    private function conPermisos(array $permisos): void
    {
        UboLogin::actingAs(id: (string) Str::uuid(), roles: ['administrador'], scopes: $permisos);
    }

    private function persona(string $nombre, ?CarbonImmutable $ultimoToken): int
    {
        $id = DB::table('users')->insertGetId(['uuid' => (string) Str::uuid(), 'name' => $nombre, 'email' => Str::slug($nombre).'@ubo.cl']);
        if ($ultimoToken !== null) {
            $this->tokenEmitido($id, (string) Str::uuid(), $ultimoToken);
        }

        return $id;
    }

    public function test_the_help_desk_gets_only_the_blocks_of_its_permissions(): void
    {
        $this->conPermisos(['iam:usuarios.leer', 'iam:aplicaciones.leer']);

        $data = $this->getJson('/api/iam/v1/indicadores')->assertOk()->json('data');

        $this->assertSame(['entorno', 'personas', 'privilegios', 'senales'], array_keys($data));
    }

    public function test_every_block_with_all_the_read_permissions(): void
    {
        $this->conPermisos(self::TODOS);

        $this->getJson('/api/iam/v1/indicadores')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'entorno' => ['aplicaciones', 'roles', 'permisos', 'roles_sin_asignar', 'roles_con_mas_personas'],
                    'personas' => ['activas', 'de_baja', 'inactivas_30', 'inactivas_90', 'sin_actividad', 'ingresos_por_dia', 'sesiones_abiertas', 'claves_temporales_vencidas', 'solo_clave'],
                    'privilegios' => ['administradores', 'administradores_inactivos'],
                    'clientes' => ['login', 'servicio', 'revocados', 'tokens_24h', 'tokens_7d', 'tokens_por_cliente', 'sin_uso_30', 'sin_roles', 'secretos_sin_rotar'],
                    'auditoria' => ['rechazos', 'cambios_7d'],
                    'senales' => ['sesiones_desde_varias_ips'],
                ],
                'meta' => ['calculado_en'],
            ])
            ->assertJsonPath('meta.calculado_en', $this->ahora->toIso8601String());
    }

    public function test_without_any_read_permission_data_is_an_empty_object(): void
    {
        $this->conPermisos(['iam:otra.cosa']);

        $respuesta = $this->getJson('/api/iam/v1/indicadores')->assertOk();

        $this->assertStringContainsString('"data":{}', (string) $respuesta->getContent());
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->getJson('/api/iam/v1/indicadores')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');
    }

    public function test_a_forgotten_person_and_one_who_never_entered(): void
    {
        $this->persona('Olvidada', $this->ahora->subDays(100));
        $this->persona('Nunca', null);
        $this->conPermisos(['iam:usuarios.leer']);

        $this->getJson('/api/iam/v1/indicadores')
            ->assertOk()
            ->assertJsonPath('data.personas.inactivas_30', 1)
            ->assertJsonPath('data.personas.inactivas_90', 1)
            ->assertJsonPath('data.personas.sin_actividad', 1)
            ->assertJsonCount(30, 'data.personas.ingresos_por_dia');
    }

    public function test_audit_refusals_are_an_object_by_reason(): void
    {
        foreach (['ESCALAMIENTO_DE_PRIVILEGIOS', 'ESCALAMIENTO_DE_PRIVILEGIOS', 'ESCALAMIENTO_DE_PRIVILEGIOS', 'ACCION_SOBRE_SI_MISMO'] as $motivo) {
            DB::table('iam_auditoria')->insert([
                'ocurrido_en' => $this->ahora->subDay(), 'actor_tipo' => 'persona', 'accion' => 'usuario.rol.asignacion',
                'entidad_tipo' => 'usuario', 'entidad_id' => 'x', 'resultado' => 'rechazado', 'motivo' => $motivo,
            ]);
        }
        $this->conPermisos(['iam:auditoria.leer']);

        $this->getJson('/api/iam/v1/indicadores')
            ->assertOk()
            ->assertExactJson([
                'data' => ['auditoria' => ['rechazos' => ['ESCALAMIENTO_DE_PRIVILEGIOS' => 3, 'ACCION_SOBRE_SI_MISMO' => 1], 'cambios_7d' => 0]],
                'meta' => ['calculado_en' => $this->ahora->toIso8601String()],
            ]);
    }

    /**
     * With a store that serializes (file, as in development and production), every block comes back
     * from the cache intact: the array store of the other tests never serializes.
     */
    public function test_the_blocks_read_back_from_a_serializing_cache(): void
    {
        config(['cache.default' => 'file']);
        Cache::store('file')->flush();
        $admin = $this->persona('Admin olvidado', $this->ahora->subDays(45));
        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $rol = DB::table('roles')->insertGetId(['application_id' => $iamAdmin, 'name' => 'administrador']);
        DB::table('role_user')->insert(['user_id' => $admin, 'role_id' => $rol]);
        $this->conPermisos(self::TODOS);

        $primera = $this->getJson('/api/iam/v1/indicadores')->assertOk()->json();
        $segunda = $this->getJson('/api/iam/v1/indicadores')->assertOk()->json();

        $this->assertSame($primera, $segunda);
        $this->assertSame('Admin olvidado', $segunda['data']['privilegios']['administradores_inactivos'][0]['nombre']);
        Cache::store('file')->flush();
    }

    public function test_a_second_request_within_5_minutes_uses_the_cache(): void
    {
        $this->persona('Olvidada', $this->ahora->subDays(100));
        $this->conPermisos(self::TODOS);
        $primera = $this->getJson('/api/iam/v1/indicadores')->assertOk();

        $this->persona('Otra olvidada', $this->ahora->subDays(100));
        $this->travelTo($this->ahora->addMinutes(4));
        $consultas = 0;
        Event::listen(QueryExecuted::class, function () use (&$consultas) {
            $consultas++;
        });

        $segunda = $this->getJson('/api/iam/v1/indicadores')->assertOk();

        $this->assertSame(0, $consultas);
        $this->assertSame($primera->json('meta.calculado_en'), $segunda->json('meta.calculado_en'));
        $this->assertSame(1, $segunda->json('data.personas.inactivas_30'));

        $this->travelTo($this->ahora->addMinutes(6));
        $tercera = $this->getJson('/api/iam/v1/indicadores')->assertOk();
        $this->assertSame(2, $tercera->json('data.personas.inactivas_30'));
        $this->assertSame($this->ahora->addMinutes(6)->toIso8601String(), $tercera->json('meta.calculado_en'));
    }

    public function test_it_never_writes_and_shows_no_numeric_ids_or_secrets(): void
    {
        $admin = DB::table('roles')->insertGetId([
            'application_id' => DB::table('applications')->insertGetId(['name' => 'iam-admin']),
            'name' => 'administrador',
        ]);
        $persona = $this->persona('Admin', $this->ahora->subDays(45));
        DB::table('role_user')->insert(['user_id' => $persona, 'role_id' => $admin]);
        $this->conPermisos(self::TODOS);
        $escrituras = [];
        Event::listen(QueryExecuted::class, function (QueryExecuted $q) use (&$escrituras) {
            if (preg_match('/^\s*(insert|update|delete|merge|create|alter|drop|truncate)\b/i', $q->sql)) {
                $escrituras[] = $q->sql;
            }
        });

        $respuesta = $this->getJson('/api/iam/v1/indicadores')->assertOk();

        $this->assertSame([], $escrituras);
        $inactivo = $respuesta->json('data.privilegios.administradores_inactivos.0');
        $this->assertSame(['uuid', 'nombre', 'ultima_actividad'], array_keys($inactivo));
        $this->assertTrue(Str::isUuid($inactivo['uuid']));
        foreach (['"id":'.$persona, '"password"', '"secret"', '"secreto"', '"token"', '"access_token"'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, (string) $respuesta->getContent());
        }
    }

    public function test_the_inactive_people_list_matches_the_indicator(): void
    {
        $this->persona('Olvidada', $this->ahora->subDays(100));
        $this->persona('Del mes', $this->ahora->subDays(45));
        $this->persona('Activa', $this->ahora->subDay());
        $this->persona('Nunca', null);
        $this->conPermisos(['iam:usuarios.leer']);

        $personas = $this->getJson('/api/iam/v1/indicadores')->assertOk()->json('data.personas');

        foreach (['30' => 'inactivas_30', '90' => 'inactivas_90', 'nunca' => 'sin_actividad'] as $filtro => $indicador) {
            $this->getJson("/api/iam/v1/usuarios?estado=activos&inactivas={$filtro}")
                ->assertOk()
                ->assertJsonPath('meta.total', $personas[$indicador]);
        }
    }
}
