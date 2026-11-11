<?php

namespace Tests\Feature\Usuarios;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Domains\Usuarios\Domain\CorreoEnUsoException;
use App\Domains\Usuarios\Domain\Dto\EstadoPersonaDto;
use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Usuarios\Domain\Interfaces\IPersonasRepository;
use App\Domains\Usuarios\Domain\RutEnUsoException;
use App\Domains\Usuarios\Infrastructure\PersonasRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Design D4 of usuarios-alta: when two simultaneous requests both pass the checks, the unique index of
 * SQL Server decides and the repository turns its violation into the exception of that field.
 */
class PersonasRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-000000000009';

    private const NUEVO = '0199a1b2-c3d4-7e5f-8a9b-000000000010';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert(['uuid' => self::ANA, 'name' => 'Ana', 'email' => 'ana@ubo.cl', 'rut' => '12345678-5']);
    }

    public function test_the_port_is_bound_to_its_sql_adapter(): void
    {
        $this->assertInstanceOf(PersonasRepository::class, app(IPersonasRepository::class));
    }

    public function test_a_violation_of_the_email_index_becomes_correo_en_uso(): void
    {
        $this->expectException(CorreoEnUsoException::class);

        app(IPersonasRepository::class)->crear(self::NUEVO, new NuevaPersonaDto('Otra Ana', 'ANA@ubo.cl', null, 'microsoft'), null);
    }

    public function test_a_violation_of_the_rut_index_becomes_rut_en_uso(): void
    {
        $this->expectException(RutEnUsoException::class);

        app(IPersonasRepository::class)->crear(self::NUEVO, new NuevaPersonaDto('Otra', 'otra@ubo.cl', '12345678-5', 'microsoft'), null);
    }

    // --- usuarios-baja-y-clave ---

    public function test_the_state_of_a_person(): void
    {
        $personas = app(IPersonasRepository::class);
        $id = (int) DB::table('users')->where('uuid', self::ANA)->value('id');

        $this->assertEquals(new EstadoPersonaDto($id, true), $personas->estado(self::ANA));
        $this->assertNull($personas->estado(self::NUEVO));
    }

    public function test_disabling_and_reactivating_change_only_what_is_needed(): void
    {
        $personas = app(IPersonasRepository::class);
        $id = $personas->estado(self::ANA)->id;

        $this->assertTrue($personas->darDeBaja($id));
        $this->assertFalse($personas->estado(self::ANA)->activa);
        $this->assertFalse($personas->darDeBaja($id), 'Una segunda baja no cambia nada.');

        $this->assertTrue($personas->reactivar($id));
        $this->assertTrue($personas->estado(self::ANA)->activa);
        $this->assertFalse($personas->reactivar($id), 'Una segunda reactivación no cambia nada.');
    }

    public function test_changing_the_password_saves_its_hash_and_expiry(): void
    {
        $personas = app(IPersonasRepository::class);
        $clave = ClaveTemporal::generar();

        $personas->cambiarClave($personas->estado(self::ANA)->id, $clave);

        $fila = DB::table('users')->where('uuid', self::ANA)->first(['password', 'temporary_password_expires_at']);
        $this->assertTrue(Hash::check($clave->clave, $fila->password));
        $this->assertNotNull($fila->temporary_password_expires_at);
    }

    public function test_the_roles_of_iam_admin_of_a_person(): void
    {
        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $otra = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        $administrador = DB::table('roles')->insertGetId(['application_id' => $iamAdmin, 'name' => 'administrador']);
        $revisor = DB::table('roles')->insertGetId(['application_id' => $otra, 'name' => 'revisor']);
        $personas = app(IPersonasRepository::class);
        $id = $personas->estado(self::ANA)->id;

        $this->assertSame([], $personas->rolesDeIamAdmin($id));

        DB::table('role_user')->insert([['user_id' => $id, 'role_id' => $administrador], ['user_id' => $id, 'role_id' => $revisor]]);

        $this->assertEquals([new RolDeAplicacionDto($administrador, 'iam-admin', 'administrador')], $personas->rolesDeIamAdmin($id));
    }
}
