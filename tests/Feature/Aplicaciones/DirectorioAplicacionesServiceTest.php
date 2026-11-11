<?php

namespace Tests\Feature\Aplicaciones;

use App\Domains\Aplicaciones\Contracts\IDirectorioAplicacionesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The contract Aplicaciones offers other domains, against SQL Server.
 */
class DirectorioAplicacionesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_of_applications_roles_and_permissions_by_id(): void
    {
        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $fuas = DB::table('applications')->insertGetId(['name' => 'fuas']);
        $mesa = DB::table('roles')->insertGetId(['application_id' => $iamAdmin, 'name' => 'mesa-de-ayuda']);
        $lector = DB::table('roles')->insertGetId(['application_id' => $fuas, 'name' => 'lector']);
        $permiso = DB::table('oauth_scopes')->insertGetId(['application_id' => $iamAdmin, 'name' => 'iam:usuarios.leer', 'description' => 'Ver usuarios']);
        $directorio = app(IDirectorioAplicacionesService::class);

        DB::enableQueryLog();
        $roles = $directorio->roles([$mesa, $lector, $mesa, 9999]);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame([$mesa => 'iam-admin/mesa-de-ayuda', $lector => 'fuas/lector'], $roles);
        $this->assertSame([$fuas => 'fuas'], $directorio->aplicaciones([$fuas, 9999]));
        $this->assertSame([$permiso => 'iam:usuarios.leer'], $directorio->permisos([$permiso]));
    }

    public function test_the_id_of_an_application_by_its_name_without_distinguishing_case(): void
    {
        $fuas = DB::table('applications')->insertGetId(['name' => 'fuas']);
        $directorio = app(IDirectorioAplicacionesService::class);

        $this->assertSame($fuas, $directorio->idDeAplicacion('fuas'));
        $this->assertSame($fuas, $directorio->idDeAplicacion('FUAS'));
        $this->assertNull($directorio->idDeAplicacion('no-existe'));
    }

    public function test_empty_lists_do_not_query(): void
    {
        $directorio = app(IDirectorioAplicacionesService::class);
        DB::enableQueryLog();

        $this->assertSame([], $directorio->aplicaciones([]));
        $this->assertSame([], $directorio->roles([]));
        $this->assertSame([], $directorio->permisos([]));
        $this->assertSame([], DB::getQueryLog());
    }
}
