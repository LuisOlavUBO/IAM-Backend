<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La migración que renombra los permisos de Catálogo a Aplicaciones (spec esquema-de-datos).
 */
class PermisosAplicacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_installed_database_gets_the_new_names_with_the_same_ids_and_roles(): void
    {
        $ids = $this->instalarConNombresAntiguos();

        $this->migracion()->up();

        $this->assertSame('iam:aplicaciones.leer', DB::table('oauth_scopes')->where('id', $ids['leer'])->value('name'));
        $this->assertSame('iam:aplicaciones.administrar', DB::table('oauth_scopes')->where('id', $ids['administrar'])->value('name'));
        $this->assertSame(['iam:aplicaciones.administrar', 'iam:aplicaciones.leer'], $this->permisosDe('administrador'));
        $this->assertSame(['iam:aplicaciones.leer'], $this->permisosDe('mesa-de-ayuda'));
    }

    public function test_a_database_without_the_old_names_is_left_untouched(): void
    {
        $aplicacion = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        DB::table('oauth_scopes')->insert(['application_id' => $aplicacion, 'name' => 'iam:aplicaciones.leer', 'description' => 'Ver']);
        $antes = DB::table('oauth_scopes')->orderBy('id')->get()->toArray();

        $this->migracion()->up();

        $this->assertEquals($antes, DB::table('oauth_scopes')->orderBy('id')->get()->toArray());
    }

    public function test_reverting_restores_the_old_names(): void
    {
        $ids = $this->instalarConNombresAntiguos();
        $this->migracion()->up();

        $this->migracion()->down();

        $this->assertSame('iam:catalogo.leer', DB::table('oauth_scopes')->where('id', $ids['leer'])->value('name'));
        $this->assertSame(['iam:catalogo.administrar', 'iam:catalogo.leer'], $this->permisosDe('administrador'));
        $this->assertSame(['iam:catalogo.leer'], $this->permisosDe('mesa-de-ayuda'));
    }

    /**
     * @return array{leer: int, administrar: int}
     */
    private function instalarConNombresAntiguos(): array
    {
        $aplicacion = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $leer = DB::table('oauth_scopes')->insertGetId(['application_id' => $aplicacion, 'name' => 'iam:catalogo.leer', 'description' => 'Ver aplicaciones, roles y permisos']);
        $administrar = DB::table('oauth_scopes')->insertGetId(['application_id' => $aplicacion, 'name' => 'iam:catalogo.administrar', 'description' => 'Crear y editar aplicaciones, roles y permisos']);
        $administrador = DB::table('roles')->insertGetId(['application_id' => $aplicacion, 'name' => 'administrador']);
        $mesa = DB::table('roles')->insertGetId(['application_id' => $aplicacion, 'name' => 'mesa-de-ayuda']);

        DB::table('oauth_scope_role')->insert([
            ['role_id' => $administrador, 'oauth_scope_id' => $leer],
            ['role_id' => $administrador, 'oauth_scope_id' => $administrar],
            ['role_id' => $mesa, 'oauth_scope_id' => $leer],
        ]);

        return ['leer' => $leer, 'administrar' => $administrar];
    }

    /**
     * @return list<string>
     */
    private function permisosDe(string $rol): array
    {
        return DB::table('oauth_scope_role')
            ->join('roles', 'roles.id', '=', 'oauth_scope_role.role_id')
            ->join('oauth_scopes', 'oauth_scopes.id', '=', 'oauth_scope_role.oauth_scope_id')
            ->where('roles.name', $rol)
            ->orderBy('oauth_scopes.name')
            ->pluck('oauth_scopes.name')
            ->all();
    }

    private function migracion(): Migration
    {
        return require database_path('migrations/2026_11_11_000000_renombrar_permisos_catalogo_a_aplicaciones.php');
    }
}
