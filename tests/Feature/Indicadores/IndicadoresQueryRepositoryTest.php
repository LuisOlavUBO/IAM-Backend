<?php

namespace Tests\Feature\Indicadores;

use App\Domains\Indicadores\Domain\Dto\ClienteSenaladoDto;
use App\Domains\Indicadores\Domain\Dto\PersonaInactivaDto;
use App\Domains\Indicadores\Domain\Dto\TokensDeClienteDto;
use App\Domains\Indicadores\Domain\Interfaces\IIndicadoresQueryRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\TablasDeApiAuth;
use Tests\TestCase;

/**
 * IndicadoresQueryRepository against SQL Server, with data in oauth_access_tokens, sessions and the
 * audit log (spec indicadores, design D2 and D5).
 */
class IndicadoresQueryRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use TablasDeApiAuth;

    private CarbonImmutable $ahora;

    private string $portal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablasDeApiAuth();
        $this->ahora = CarbonImmutable::parse('2026-11-12 12:00:00');
        $this->travelTo($this->ahora);
        $this->portal = $this->cliente('portal', ['authorization_code', 'refresh_token']);
    }

    private function repositorio(): IIndicadoresQueryRepository
    {
        return app(IIndicadoresQueryRepository::class);
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function persona(string $nombre, array $columnas = []): int
    {
        return DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $nombre,
            'email' => Str::slug($nombre).'@ubo.cl',
        ] + $columnas);
    }

    private function rol(string $aplicacion, string $rol): int
    {
        $app = DB::table('applications')->where('name', $aplicacion)->value('id')
            ?? DB::table('applications')->insertGetId(['name' => $aplicacion]);

        return DB::table('roles')->insertGetId(['application_id' => $app, 'name' => $rol]);
    }

    /**
     * @param  list<string>  $grants
     */
    private function cliente(string $nombre, array $grants, bool $revocado = false, ?string $secreto = null, ?CarbonImmutable $creado = null): string
    {
        $app = DB::table('applications')->where('name', 'sistemas')->value('id')
            ?? DB::table('applications')->insertGetId(['name' => 'sistemas']);
        $id = (string) Str::uuid();

        DB::table('oauth_clients')->insert([
            'id' => $id,
            'name' => $nombre,
            'secret' => $secreto,
            'redirect_uris' => '[]',
            'grant_types' => json_encode($grants),
            'revoked' => $revocado,
            'application_id' => $app,
            'created_at' => $creado ?? $this->ahora->subDays(100),
        ]);

        return $id;
    }

    private function auditoria(string $accion, string $resultado, ?string $motivo, CarbonImmutable $cuando, string $entidadTipo = 'rol', string $entidadId = '1'): void
    {
        DB::table('iam_auditoria')->insert([
            'ocurrido_en' => $cuando,
            'actor_tipo' => 'persona',
            'accion' => $accion,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'resultado' => $resultado,
            'motivo' => $motivo,
        ]);
    }

    public function test_people_activity_counts_the_last_token_issued(): void
    {
        $olvidada = $this->persona('Olvidada');
        $this->tokenEmitido($olvidada, $this->portal, $this->ahora->subDays(100));
        $mes = $this->persona('Del mes pasado');
        $this->tokenEmitido($mes, $this->portal, $this->ahora->subDays(200));
        $this->tokenEmitido($mes, $this->portal, $this->ahora->subDays(45));
        $hoy = $this->persona('De hoy');
        $this->tokenEmitido($hoy, $this->portal, $this->ahora->subDays(100));
        $this->tokenEmitido($hoy, $this->portal, $this->ahora->subHour());
        $this->tokenEmitido($hoy, $this->portal, $this->ahora->subMinutes(5));
        $this->persona('Nunca entró');
        $deBaja = $this->persona('De baja', ['disabled_at' => $this->ahora->subDays(10)]);
        $this->tokenEmitido($deBaja, $this->portal, $this->ahora->subDays(300));

        $personas = $this->repositorio()->personas();

        $this->assertSame(4, $personas->activas);
        $this->assertSame(1, $personas->deBaja);
        $this->assertSame(2, $personas->inactivas30);
        $this->assertSame(1, $personas->inactivas90);
        $this->assertSame(1, $personas->sinActividad);
    }

    public function test_logins_per_day_count_different_people_over_30_days(): void
    {
        $ana = $this->persona('Ana');
        $beto = $this->persona('Beto');
        $this->tokenEmitido($ana, $this->portal, $this->ahora->subHours(2));
        $this->tokenEmitido($ana, $this->portal, $this->ahora->subHour());
        $this->tokenEmitido($beto, $this->portal, $this->ahora->subHour());
        $this->tokenEmitido($beto, $this->portal, $this->ahora->subDays(3));
        $this->tokenEmitido($beto, $this->portal, $this->ahora->subDays(40));
        $this->tokenEmitido(null, $this->portal, $this->ahora->subHour());

        $dias = $this->repositorio()->personas()->ingresosPorDia;

        $this->assertCount(30, $dias);
        $this->assertSame('2026-10-14', $dias[0]->fecha);
        $this->assertSame('2026-11-12', $dias[29]->fecha);
        $this->assertSame(2, $dias[29]->personas);
        $this->assertSame(1, $dias[26]->personas);
        $this->assertSame(3, array_sum(array_map(fn ($d) => $d->personas, $dias)));
    }

    public function test_sessions_temporary_passwords_and_password_only_people(): void
    {
        $ana = $this->persona('Ana', ['password' => 'hash', 'temporary_password_expires_at' => $this->ahora->subDay()]);
        $beto = $this->persona('Beto', ['password' => 'hash', 'entra_oid' => (string) Str::uuid()]);
        $this->persona('Carla', ['password' => 'hash', 'disabled_at' => $this->ahora->subDay(), 'temporary_password_expires_at' => $this->ahora->subDay()]);
        $this->persona('Dani', ['temporary_password_expires_at' => $this->ahora->addDay()]);
        $this->sesionAbierta($ana, '10.0.0.1', $this->ahora->subMinutes(10));
        $this->sesionAbierta($ana, '10.0.0.2', $this->ahora->subMinutes(20));
        $this->sesionAbierta($beto, '10.0.0.3', $this->ahora->subHours(3));

        $personas = $this->repositorio()->personas();

        $this->assertSame(1, $personas->sesionesAbiertas);
        $this->assertSame(1, $personas->clavesTemporalesVencidas);
        $this->assertSame(1, $personas->soloClave);
    }

    public function test_inactive_administrators_of_iam(): void
    {
        $admin = $this->rol('iam-admin', 'administrador');
        $activo = $this->persona('Activo');
        $inactivo = $this->persona('Inactivo');
        $nunca = $this->persona('Nunca');
        $deBaja = $this->persona('De baja', ['disabled_at' => $this->ahora->subDay()]);
        foreach ([$activo, $inactivo, $nunca, $deBaja] as $persona) {
            DB::table('role_user')->insert(['user_id' => $persona, 'role_id' => $admin]);
        }
        $this->tokenEmitido($activo, $this->portal, $this->ahora->subDays(2));
        $this->tokenEmitido($inactivo, $this->portal, $this->ahora->subDays(45));

        $privilegios = $this->repositorio()->privilegios();

        $this->assertSame(3, $privilegios->administradores);
        $this->assertSame(['Inactivo', 'Nunca'], array_map(fn (PersonaInactivaDto $p) => $p->nombre, $privilegios->administradoresInactivos));
        $this->assertSame('2026-09-28', $privilegios->administradoresInactivos[0]->ultimaActividad?->format('Y-m-d'));
        $this->assertNull($privilegios->administradoresInactivos[1]->ultimaActividad);
        $this->assertSame(Str::lower((string) DB::table('users')->where('id', $inactivo)->value('uuid')), $privilegios->administradoresInactivos[0]->uuid);
    }

    public function test_clients_and_service_tokens(): void
    {
        $nomina = $this->cliente('nomina', ['client_credentials'], secreto: 'hash');
        $olvidado = $this->cliente('olvidado', ['client_credentials'], secreto: 'hash');
        $nuevo = $this->cliente('nuevo', ['client_credentials'], secreto: 'hash', creado: $this->ahora->subDays(5));
        $this->cliente('revocado', ['client_credentials'], revocado: true);
        $rol = $this->rol('sistemas', 'lector');
        DB::table('oauth_client_role')->insert(['client_id' => $nomina, 'role_id' => $rol]);
        $this->tokenEmitido(null, $nomina, $this->ahora->subHours(2));
        $this->tokenEmitido(null, $nomina, $this->ahora->subDays(3));
        $this->tokenEmitido(null, $nomina, $this->ahora->subDays(10));
        $this->tokenEmitido(null, $olvidado, $this->ahora->subDays(40));
        $this->tokenEmitido($this->persona('Ana'), $this->portal, $this->ahora->subHour());

        $clientes = $this->repositorio()->clientes();

        $this->assertSame(1, $clientes->login);
        $this->assertSame(3, $clientes->servicio);
        $this->assertSame(1, $clientes->revocados);
        $this->assertSame(1, $clientes->tokens24h);
        $this->assertSame(2, $clientes->tokens7d);
        $this->assertEquals([new TokensDeClienteDto($nomina, 'nomina', 1, 2)], $clientes->tokensPorCliente);
        $this->assertSame([$olvidado], array_map(fn (ClienteSenaladoDto $c) => $c->id, $clientes->sinUso30));
        $this->assertSame('2026-10-03', $clientes->sinUso30[0]->fecha?->format('Y-m-d'));
        $this->assertSame([$nuevo, $olvidado], array_map(fn (ClienteSenaladoDto $c) => $c->id, $clientes->sinRoles));
    }

    public function test_secrets_not_rotated_for_more_than_180_days(): void
    {
        $viejo = $this->cliente('viejo', ['client_credentials'], secreto: 'hash', creado: $this->ahora->subDays(200));
        $rotado = $this->cliente('rotado', ['client_credentials'], secreto: 'hash', creado: $this->ahora->subDays(400));
        $this->auditoria('cliente.secreto.rotacion', 'ok', null, $this->ahora->subDays(10), 'cliente', $rotado);
        $rotadoHaceTiempo = $this->cliente('rotado hace tiempo', ['client_credentials'], secreto: 'hash', creado: $this->ahora->subDays(400));
        $this->auditoria('cliente.secreto.rotacion', 'ok', null, $this->ahora->subDays(190), 'cliente', $rotadoHaceTiempo);
        $this->cliente('publico', ['authorization_code'], creado: $this->ahora->subDays(400));
        $this->cliente('revocado', ['client_credentials'], revocado: true, secreto: 'hash', creado: $this->ahora->subDays(400));

        $secretos = $this->repositorio()->clientes()->secretosSinRotar;

        $this->assertSame([$rotadoHaceTiempo, $viejo], array_map(fn (ClienteSenaladoDto $c) => $c->id, $secretos));
        $this->assertSame('2026-05-06', $secretos[0]->fecha?->format('Y-m-d'));
        $this->assertSame('2026-04-26', $secretos[1]->fecha?->format('Y-m-d'));
    }

    public function test_environment_counts_and_roles_with_most_people(): void
    {
        $lector = $this->rol('notas', 'lector');
        $editor = $this->rol('notas', 'editor');
        $this->rol('notas', 'sin-nadie');
        $deCliente = $this->rol('notas', 'de-cliente');
        DB::table('oauth_scopes')->insert(['application_id' => DB::table('applications')->where('name', 'notas')->value('id'), 'name' => 'notas:leer', 'description' => 'Leer']);
        DB::table('oauth_client_role')->insert(['client_id' => $this->portal, 'role_id' => $deCliente]);
        foreach (['Ana', 'Beto', 'Carla'] as $nombre) {
            DB::table('role_user')->insert(['user_id' => $this->persona($nombre), 'role_id' => $lector]);
        }
        DB::table('role_user')->insert(['user_id' => $this->persona('Dani'), 'role_id' => $editor]);
        DB::table('role_user')->insert(['user_id' => $this->persona('Eva', ['disabled_at' => $this->ahora]), 'role_id' => $editor]);

        $entorno = $this->repositorio()->entorno();

        $this->assertSame(2, $entorno->aplicaciones);
        $this->assertSame(4, $entorno->roles);
        $this->assertSame(1, $entorno->permisos);
        $this->assertSame(1, $entorno->rolesSinAsignar);
        $this->assertSame([['notas', 'lector', 3], ['notas', 'editor', 1]], array_map(
            fn ($r) => [$r->aplicacion, $r->rol, $r->personas],
            $entorno->rolesConMasPersonas,
        ));
    }

    public function test_audit_refusals_by_reason_and_recent_changes(): void
    {
        foreach (range(1, 3) as $i) {
            $this->auditoria('usuario.rol.asignacion', 'rechazado', 'ESCALAMIENTO_DE_PRIVILEGIOS', $this->ahora->subDays($i));
        }
        $this->auditoria('usuario.baja', 'rechazado', 'ACCION_SOBRE_SI_MISMO', $this->ahora->subDays(20));
        $this->auditoria('usuario.baja', 'rechazado', 'ACCION_SOBRE_SI_MISMO', $this->ahora->subDays(40));
        $this->auditoria('rol.creacion', 'ok', null, $this->ahora->subDays(2));
        $this->auditoria('rol.creacion', 'ok', null, $this->ahora->subDays(8));

        $auditoria = $this->repositorio()->auditoria();

        $this->assertSame(['ESCALAMIENTO_DE_PRIVILEGIOS' => 3, 'ACCION_SOBRE_SI_MISMO' => 1], $auditoria->rechazos);
        $this->assertSame(1, $auditoria->cambios7d);
    }

    public function test_signals_list_people_with_open_sessions_from_several_ips(): void
    {
        $ana = $this->persona('Ana');
        $beto = $this->persona('Beto');
        $carla = $this->persona('Carla');
        $this->sesionAbierta($ana, '10.0.0.1', $this->ahora->subMinutes(5));
        $this->sesionAbierta($ana, '200.1.1.1', $this->ahora->subMinutes(30));
        $this->sesionAbierta($beto, '10.0.0.1', $this->ahora->subMinutes(5));
        $this->sesionAbierta($beto, '10.0.0.1', $this->ahora->subMinutes(15));
        $this->sesionAbierta($carla, '10.0.0.1', $this->ahora->subMinutes(5));
        $this->sesionAbierta($carla, '200.1.1.1', $this->ahora->subHours(5));

        $senales = $this->repositorio()->senales()->sesionesDesdeVariasIps;

        $this->assertCount(1, $senales);
        $this->assertSame('Ana', $senales[0]->nombre);
        $this->assertSame(2, $senales[0]->ips);
    }
}
