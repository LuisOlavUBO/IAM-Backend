<?php

namespace Tests\Feature\Usuarios;

use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Domains\Usuarios\Contracts\PersonaBasicaDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The contract Usuarios offers other domains, against SQL Server.
 */
class DirectorioUsuariosServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const LUIS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c';

    public function test_several_people_in_one_query_keyed_by_lowercase_uuid(): void
    {
        DB::table('users')->insert(['uuid' => self::ANA, 'name' => 'Ana Pérez', 'email' => 'ana@ubo.cl']);
        DB::table('users')->insert(['uuid' => self::LUIS, 'name' => 'Luis Soto', 'email' => 'luis@ubo.cl']);
        $directorio = app(IDirectorioUsuariosService::class);

        DB::enableQueryLog();
        $personas = $directorio->personas([self::ANA, self::LUIS, self::ANA, '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d']);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertEquals([
            self::ANA => new PersonaBasicaDto('Ana Pérez', 'ana@ubo.cl'),
            self::LUIS => new PersonaBasicaDto('Luis Soto', 'luis@ubo.cl'),
        ], $personas);
    }

    public function test_an_empty_list_does_not_query(): void
    {
        DB::enableQueryLog();

        $this->assertSame([], app(IDirectorioUsuariosService::class)->personas([]));
        $this->assertSame([], DB::getQueryLog());
    }
}
