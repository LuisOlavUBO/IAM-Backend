<?php

namespace Tests\Feature\Aplicaciones;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Infrastructure\CatalogoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Design D2 of aplicaciones-catalogo: the write repository of the catalog.
 */
class CatalogoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ICatalogoRepository $catalogo;

    private int $fuas;

    private int $geografia;

    private int $leer;

    private int $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fuas = DB::table('applications')->insertGetId(['name' => 'fuas', 'description' => 'Becas']);
        $this->geografia = DB::table('applications')->insertGetId(['name' => 'geografia']);
        $this->leer = DB::table('oauth_scopes')->insertGetId(['application_id' => $this->geografia, 'name' => 'geografia:leer', 'description' => 'Leer']);
        DB::table('oauth_scopes')->insert(['application_id' => $this->fuas, 'name' => 'fuas:postular', 'description' => 'Postular']);
        $this->docente = DB::table('roles')->insertGetId(['application_id' => $this->fuas, 'name' => 'Docente']);

        $this->catalogo = app(ICatalogoRepository::class);
    }

    public function test_the_port_is_bound_to_its_sql_adapter(): void
    {
        $this->assertInstanceOf(CatalogoRepository::class, $this->catalogo);
    }

    public function test_it_finds_by_name_without_distinguishing_case(): void
    {
        $this->assertEquals(new AplicacionParaEscribirDto($this->fuas, 'fuas', 'Becas'), $this->catalogo->aplicacion('FUAS'));
        $this->assertNull($this->catalogo->aplicacion('otra'));

        $this->assertEquals(new PermisoParaEscribirDto($this->leer, 'geografia', 'geografia:leer', 'Leer'), $this->catalogo->permiso('GEOGRAFIA:leer'));
        $this->assertNull($this->catalogo->permiso('geografia:escribir'));

        $this->assertEquals(new RolParaEscribirDto($this->docente, 'fuas', 'Docente', null), $this->catalogo->rol($this->fuas, 'docente'));
        $this->assertNull($this->catalogo->rol($this->geografia, 'Docente'));
    }

    public function test_it_resolves_permissions_of_any_application_leaving_out_the_unknown(): void
    {
        $this->assertSame(
            ['fuas:postular', 'geografia:leer'],
            array_map(fn (PermisoParaEscribirDto $p) => $p->nombre, $this->catalogo->permisos(['geografia:leer', 'no-existe', 'fuas:postular', 'geografia:leer'])),
        );
        $this->assertSame([], $this->catalogo->permisos([]));
    }

    public function test_it_creates_and_describes_the_catalog(): void
    {
        $biblioteca = $this->catalogo->crearAplicacion('biblioteca', null);
        $permiso = $this->catalogo->crearPermiso($biblioteca, 'biblioteca:leer', 'Leer');
        $rol = $this->catalogo->crearRol($biblioteca, 'lector', 'Lee');

        $this->catalogo->describirAplicacion($biblioteca, 'Biblioteca');
        $this->catalogo->describirPermiso($permiso, 'Leer libros');
        $this->catalogo->describirRol($rol, null);

        $this->assertEquals(new AplicacionParaEscribirDto($biblioteca, 'biblioteca', 'Biblioteca'), $this->catalogo->aplicacion('biblioteca'));
        $this->assertEquals(new PermisoParaEscribirDto($permiso, 'biblioteca', 'biblioteca:leer', 'Leer libros'), $this->catalogo->permiso('biblioteca:leer'));
        $this->assertEquals(new RolParaEscribirDto($rol, 'biblioteca', 'lector', null), $this->catalogo->rol($biblioteca, 'lector'));
        $this->assertNotNull(DB::table('roles')->where('id', $rol)->value('created_at'));
        $this->assertNotNull(DB::table('applications')->where('id', $biblioteca)->value('updated_at'));
    }

    public function test_it_grants_and_removes_permissions_of_a_role(): void
    {
        $postular = (int) DB::table('oauth_scopes')->where('name', 'fuas:postular')->value('id');

        $this->assertSame([], $this->catalogo->permisosDeRol($this->docente));

        $this->catalogo->asignarPermiso($this->docente, $this->leer);
        $this->catalogo->asignarPermiso($this->docente, $postular);
        $this->assertSame(['fuas:postular', 'geografia:leer'], array_map(fn (PermisoParaEscribirDto $p) => $p->nombre, $this->catalogo->permisosDeRol($this->docente)));

        $this->catalogo->quitarPermiso($this->docente, $postular);
        $this->assertEquals([new PermisoParaEscribirDto($this->leer, 'geografia', 'geografia:leer', 'Leer')], $this->catalogo->permisosDeRol($this->docente));
    }

    public function test_the_impact_counts_distinct_people_and_clients(): void
    {
        $lector = DB::table('roles')->insertGetId(['application_id' => $this->geografia, 'name' => 'lector']);
        $this->catalogo->asignarPermiso($this->docente, $this->leer);
        $this->catalogo->asignarPermiso($lector, $this->leer);
        $ana = $this->persona([$this->docente, $lector]);
        $this->persona([$lector]);
        $this->cliente($this->geografia, [$this->docente, $lector]);

        $permiso = $this->catalogo->impactoDePermiso($this->leer);
        $this->assertSame(['roles' => ['fuas/Docente', 'geografia/lector'], 'personas' => 2, 'clientes' => 1], $permiso->detalle);
        $this->assertTrue($permiso->enUso);

        $rol = $this->catalogo->impactoDeRol($this->docente);
        $this->assertSame(['personas' => 1, 'clientes' => 1, 'permisos' => ['geografia:leer']], $rol->detalle);
        $this->assertTrue($rol->enUso);

        $aplicacion = $this->catalogo->impactoDeAplicacion($this->geografia);
        $this->assertSame(['permisos' => 1, 'roles' => 1, 'clientes' => 1], $aplicacion->detalle);
        $this->assertTrue($aplicacion->enUso);

        DB::table('role_user')->where('user_id', $ana)->where('role_id', $this->docente)->delete();
        DB::table('oauth_client_role')->where('role_id', $this->docente)->delete();
        $this->assertFalse($this->catalogo->impactoDeRol($this->docente)->enUso, 'The permissions a role grants do not make it in use.');
    }

    public function test_it_deletes_the_catalog(): void
    {
        $this->catalogo->asignarPermiso($this->docente, $this->leer);
        $this->catalogo->borrarRol($this->docente);
        $this->assertNull($this->catalogo->rol($this->fuas, 'Docente'));
        $this->assertSame(0, DB::table('oauth_scope_role')->count(), 'The permissions of the role go with it by the cascade.');
        $this->assertNotNull($this->catalogo->permiso('geografia:leer'));

        $this->catalogo->borrarPermiso($this->leer);
        $this->assertNull($this->catalogo->permiso('geografia:leer'));

        $this->catalogo->borrarAplicacion($this->geografia);
        $this->assertNull($this->catalogo->aplicacion('geografia'));
    }

    /**
     * @param  list<int>  $roles
     */
    private function persona(array $roles): int
    {
        $id = DB::table('users')->insertGetId(['name' => 'Persona', 'email' => fake()->unique()->safeEmail(), 'uuid' => fake()->uuid()]);
        foreach ($roles as $rol) {
            DB::table('role_user')->insert(['user_id' => $id, 'role_id' => $rol]);
        }

        return $id;
    }

    /**
     * @param  list<int>  $roles
     */
    private function cliente(int $aplicacion, array $roles): void
    {
        $id = fake()->uuid();
        DB::table('oauth_clients')->insert([
            'id' => $id, 'name' => 'Cliente', 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => false, 'application_id' => $aplicacion,
        ]);
        foreach ($roles as $rol) {
            DB::table('oauth_client_role')->insert(['client_id' => $id, 'role_id' => $rol]);
        }
    }
}
