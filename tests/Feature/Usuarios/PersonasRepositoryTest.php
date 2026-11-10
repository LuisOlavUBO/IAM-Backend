<?php

namespace Tests\Feature\Usuarios;

use App\Domains\Usuarios\Domain\CorreoEnUsoException;
use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Domains\Usuarios\Domain\Interfaces\IPersonasRepository;
use App\Domains\Usuarios\Domain\RutEnUsoException;
use App\Domains\Usuarios\Infrastructure\PersonasRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Design D4 of usuarios-alta: when two simultaneous requests both pass the checks, the unique index of
 * SQL Server decides and the repository turns its violation into the exception of that field.
 */
class PersonasRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const NUEVO = '0199a1b2-c3d4-7e5f-8a9b-000000000010';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert(['uuid' => '0199a1b2-c3d4-7e5f-8a9b-000000000009', 'name' => 'Ana', 'email' => 'ana@ubo.cl', 'rut' => '12345678-5']);
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
}
