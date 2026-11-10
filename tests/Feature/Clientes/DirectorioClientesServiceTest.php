<?php

namespace Tests\Feature\Clientes;

use App\Domains\Clientes\Contracts\IDirectorioClientesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The contract Clientes offers other domains, against SQL Server.
 */
class DirectorioClientesServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SISTEMA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    public function test_names_of_clients_by_id_in_one_query(): void
    {
        $aplicacion = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        DB::table('oauth_clients')->insert([
            'id' => self::SISTEMA, 'application_id' => $aplicacion, 'name' => 'iam-admin-sistema', 'secret' => 'hash',
            'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false,
        ]);
        $directorio = app(IDirectorioClientesService::class);

        DB::enableQueryLog();
        $nombres = $directorio->clientes([self::SISTEMA, self::SISTEMA, '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c']);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame([self::SISTEMA => 'iam-admin-sistema'], $nombres);
    }

    public function test_an_empty_list_does_not_query(): void
    {
        DB::enableQueryLog();

        $this->assertSame([], app(IDirectorioClientesService::class)->clientes([]));
        $this->assertSame([], DB::getQueryLog());
    }
}
