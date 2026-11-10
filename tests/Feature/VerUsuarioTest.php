<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec usuarios: detalle de un usuario, y respuestas sin credenciales ni identificadores internos.
 */
class VerUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private const ANA = '0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60';

    private const OID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    protected function setUp(): void
    {
        parent::setUp();

        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: ['iam:usuarios.leer']);
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function ana(array $columnas = []): int
    {
        return (int) DB::table('users')->insertGetId(
            ['uuid' => self::ANA, 'name' => 'Ana Pérez', 'email' => 'ana@ubo.cl'] + $columnas,
        );
    }

    private function darRol(int $userId, string $aplicacion, string $rol): void
    {
        $applicationId = DB::table('applications')->where('name', $aplicacion)->value('id')
            ?? DB::table('applications')->insertGetId(['name' => $aplicacion, 'description' => "Aplicación {$aplicacion}"]);
        $roleId = DB::table('roles')->insertGetId(['application_id' => $applicationId, 'name' => $rol, 'description' => "Rol {$rol}"]);
        DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId]);
    }

    public function test_a_person_with_roles_in_two_applications(): void
    {
        $id = $this->ana(['rut' => '12345678-5', 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-02 11:30:00']);
        $this->darRol($id, 'iam-admin', 'administrador');
        $this->darRol($id, 'api-auth', 'user');

        $response = $this->getJson('/api/iam/v1/usuarios/'.self::ANA)->assertOk();

        $response->assertJsonPath('data.uuid', self::ANA)
            ->assertJsonPath('data.nombre', 'Ana Pérez')
            ->assertJsonPath('data.correo', 'ana@ubo.cl')
            ->assertJsonPath('data.rut', '12345678-5')
            ->assertJsonPath('data.estado', 'activo')
            ->assertJsonPath('data.baja_desde', null)
            ->assertJsonPath('data.tiene_clave', false)
            ->assertJsonPath('data.microsoft_vinculado', false)
            ->assertJsonPath('data.creado', '2026-11-01T10:00:00+00:00')
            ->assertJsonPath('data.actualizado', '2026-11-02T11:30:00+00:00')
            ->assertJsonPath('data.aplicaciones', [
                ['nombre' => 'api-auth', 'descripcion' => 'Aplicación api-auth', 'roles' => [['nombre' => 'user', 'descripcion' => 'Rol user']]],
                ['nombre' => 'iam-admin', 'descripcion' => 'Aplicación iam-admin', 'roles' => [['nombre' => 'administrador', 'descripcion' => 'Rol administrador']]],
            ]);
    }

    public function test_a_person_without_roles(): void
    {
        $this->ana();

        $this->getJson('/api/iam/v1/usuarios/'.self::ANA)
            ->assertOk()
            ->assertJsonPath('data.aplicaciones', []);
    }

    public function test_a_disabled_person_with_a_temporary_password(): void
    {
        $this->ana([
            'password' => bcrypt('Temporal-123'),
            'disabled_at' => '2026-11-05 08:00:00',
            'temporary_password_expires_at' => '2026-11-08 08:00:00',
        ]);

        $this->getJson('/api/iam/v1/usuarios/'.self::ANA)
            ->assertOk()
            ->assertJsonPath('data.estado', 'baja')
            ->assertJsonPath('data.baja_desde', '2026-11-05T08:00:00+00:00')
            ->assertJsonPath('data.clave_temporal_vence', '2026-11-08T08:00:00+00:00')
            ->assertJsonPath('data.tiene_clave', true);
    }

    public function test_the_uuid_in_uppercase_finds_the_person(): void
    {
        $this->ana();

        $this->getJson('/api/iam/v1/usuarios/'.strtoupper(self::ANA))
            ->assertOk()
            ->assertJsonPath('data.uuid', self::ANA);
    }

    public function test_an_unknown_or_malformed_uuid_answers_404(): void
    {
        foreach (['/api/iam/v1/usuarios/'.self::OID, '/api/iam/v1/usuarios/no-es-un-uuid'] as $url) {
            $this->getJson($url)
                ->assertNotFound()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('error', 'USUARIO_NO_ENCONTRADO');
        }
    }

    public function test_a_person_without_the_permission_gets_403(): void
    {
        $this->ana();
        UboLogin::actingAs(id: 'a', roles: ['otro'], scopes: ['iam:aplicaciones.leer']);

        $this->getJson('/api/iam/v1/usuarios/'.self::ANA)
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
    }

    public function test_no_credentials_or_internal_ids_and_nothing_is_written(): void
    {
        $hash = bcrypt('Secreta-123');
        $id = $this->ana(['password' => $hash, 'remember_token' => 'token-de-recordar', 'entra_oid' => self::OID]);
        $this->darRol($id, 'iam-admin', 'administrador');
        $filas = fn () => [DB::table('users')->count(), DB::table('role_user')->count(), DB::table('iam_auditoria')->count()];
        $antes = $filas();

        $listado = $this->getJson('/api/iam/v1/usuarios')->assertOk();
        $detalle = $this->getJson('/api/iam/v1/usuarios/'.self::ANA)->assertOk();

        foreach ([$listado, $detalle] as $response) {
            $json = $response->getContent();
            foreach ([$hash, 'token-de-recordar', self::OID, '"password"', '"remember_token"', '"entra_oid"', '"id"'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $json);
            }
        }
        $detalle->assertJsonPath('data.microsoft_vinculado', true);
        $this->assertSame($antes, $filas());
    }
}
