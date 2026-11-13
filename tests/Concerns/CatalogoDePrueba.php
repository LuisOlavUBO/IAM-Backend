<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use UBO\Common\Login\Testing\UboLogin;

/**
 * The catalog the tests of aplicaciones-catalogo start from: iam-admin as iam:install leaves it (in short),
 * biblioteca without permissions or roles, and geografia with geografia:leer.
 */
trait CatalogoDePrueba
{
    protected const ADMIN = '0199a1b2-c3d4-7e5f-8a9b-000000000001';

    /** @var array<string, int> name => id of applications, permissions ("app:x") and roles ("app/rol") */
    protected array $ids = [];

    protected function crearCatalogo(): void
    {
        $iamAdmin = $this->aplicacion('iam-admin', 'Portal de administración de IAM');
        foreach (['iam:aplicaciones.leer', 'iam:usuarios.administrar', 'iam:clientes.administrar'] as $permiso) {
            $this->permiso($iamAdmin, $permiso);
        }
        $this->rol($iamAdmin, 'administrador', ['iam:aplicaciones.leer', 'iam:usuarios.administrar', 'iam:clientes.administrar']);
        $this->rol($iamAdmin, 'mesa-de-ayuda', ['iam:aplicaciones.leer', 'iam:usuarios.administrar']);

        $this->aplicacion('biblioteca', 'Sistema de biblioteca');
        $geografia = $this->aplicacion('geografia', null);
        $this->permiso($geografia, 'geografia:leer');
    }

    protected function aplicacion(string $nombre, ?string $descripcion): int
    {
        return $this->ids[$nombre] = DB::table('applications')->insertGetId(['name' => $nombre, 'description' => $descripcion, 'updated_at' => '2026-01-01 00:00:00']);
    }

    protected function permiso(int $aplicacion, string $nombre, ?string $descripcion = null): int
    {
        return $this->ids[$nombre] = DB::table('oauth_scopes')->insertGetId([
            'application_id' => $aplicacion, 'name' => $nombre, 'description' => $descripcion ?? "Descripción de {$nombre}", 'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    /**
     * @param  list<string>  $permisos
     */
    protected function rol(int $aplicacion, string $nombre, array $permisos = [], ?string $descripcion = null): int
    {
        $app = (string) DB::table('applications')->where('id', $aplicacion)->value('name');
        $id = $this->ids["{$app}/{$nombre}"] = DB::table('roles')->insertGetId([
            'application_id' => $aplicacion, 'name' => $nombre, 'description' => $descripcion, 'updated_at' => '2026-01-01 00:00:00',
        ]);
        foreach ($permisos as $permiso) {
            DB::table('oauth_scope_role')->insert(['role_id' => $id, 'oauth_scope_id' => $this->ids[$permiso]]);
        }

        return $id;
    }

    /**
     * A person with those roles ("app/rol").
     *
     * @param  list<string>  $roles
     */
    protected function persona(array $roles): int
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Persona', 'email' => fake()->unique()->safeEmail(), 'uuid' => fake()->uuid(),
        ]);
        foreach ($roles as $rol) {
            DB::table('role_user')->insert(['user_id' => $id, 'role_id' => $this->ids[$rol]]);
        }

        return $id;
    }

    /**
     * A service client of that application with those roles ("app/rol").
     *
     * @param  list<string>  $roles
     */
    protected function cliente(string $aplicacion, array $roles = []): string
    {
        $id = fake()->uuid();
        DB::table('oauth_clients')->insert([
            'id' => $id, 'name' => 'Cliente', 'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false,
            'application_id' => $this->ids[$aplicacion],
        ]);
        foreach ($roles as $rol) {
            DB::table('oauth_client_role')->insert(['client_id' => $id, 'role_id' => $this->ids[$rol]]);
        }

        return $id;
    }

    protected function comoAdmin(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['administrador'], scopes: ['iam:aplicaciones.leer', 'iam:aplicaciones.administrar']);
    }

    protected function comoMesaDeAyuda(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['mesa-de-ayuda'], scopes: ['iam:aplicaciones.leer', 'iam:usuarios.administrar']);
    }

    /**
     * The permissions the role grants, by name.
     *
     * @return list<string>
     */
    protected function permisosDe(string $rol): array
    {
        return DB::table('oauth_scope_role')
            ->join('oauth_scopes', 'oauth_scopes.id', '=', 'oauth_scope_role.oauth_scope_id')
            ->where('oauth_scope_role.role_id', $this->ids[$rol])
            ->orderBy('oauth_scopes.name')
            ->pluck('oauth_scopes.name')
            ->all();
    }

    /**
     * The only audit entry is the refusal of that action on that entity.
     */
    protected function rechazoAuditado(string $accion, string $tipo, int $id): void
    {
        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame($accion, $entrada->accion);
        $this->assertSame($tipo, $entrada->entidad_tipo);
        $this->assertSame((string) $id, $entrada->entidad_id);
        $this->assertSame('rechazado', $entrada->resultado);
        $this->assertSame('CATALOGO_DE_IAM_ADMIN', $entrada->motivo);
    }
}
