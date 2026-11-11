<?php

namespace Tests\Feature\Clientes;

use App\Domains\Clientes\Domain\Dto\ClienteParaRotarDto;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Infrastructure\ClientesRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Design D2 of clientes-rotar-secreto: the write repository of OAuth clients.
 */
class ClientesRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIDENCIAL = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const PUBLICO = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a02';

    private const OTRO = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a03';

    private IClientesRepository $clientes;

    protected function setUp(): void
    {
        parent::setUp();

        $fuas = DB::table('applications')->insertGetId(['name' => 'fuas']);
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        DB::table('oauth_clients')->insert([
            ['id' => self::CONFIDENCIAL, 'application_id' => $honorarios, 'name' => 'backend', 'secret' => 'hash-anterior', 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => false, 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => self::PUBLICO, 'application_id' => $fuas, 'name' => 'fuas-spa', 'secret' => null, 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => false, 'updated_at' => null],
            ['id' => self::OTRO, 'application_id' => $fuas, 'name' => 'backend', 'secret' => 'otro-hash', 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => false, 'updated_at' => null],
        ]);

        $this->clientes = app(IClientesRepository::class);
    }

    public function test_the_port_is_bound_to_its_sql_adapter(): void
    {
        $this->assertInstanceOf(ClientesRepository::class, $this->clientes);
    }

    public function test_it_finds_a_client_by_id_in_any_case(): void
    {
        $this->assertEquals(
            new ClienteParaRotarDto(self::CONFIDENCIAL, 'backend', 'pago-honorarios', true),
            $this->clientes->buscar(strtoupper(self::CONFIDENCIAL)),
        );
        $this->assertEquals(new ClienteParaRotarDto(self::PUBLICO, 'fuas-spa', 'fuas', false), $this->clientes->buscar(self::PUBLICO));
        $this->assertNull($this->clientes->buscar('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'));
    }

    public function test_it_finds_every_client_with_a_name_ordered_by_application(): void
    {
        $this->assertEquals([
            new ClienteParaRotarDto(self::OTRO, 'backend', 'fuas', true),
            new ClienteParaRotarDto(self::CONFIDENCIAL, 'backend', 'pago-honorarios', true),
        ], $this->clientes->buscarPorNombre('backend'));
        $this->assertSame([], $this->clientes->buscarPorNombre('no-existe'));
    }

    public function test_changing_the_secret_touches_only_that_client(): void
    {
        $this->freezeSecond();

        $this->clientes->cambiarSecreto(self::CONFIDENCIAL, 'hash-nuevo');

        $fila = DB::table('oauth_clients')->where('id', self::CONFIDENCIAL)->first(['secret', 'updated_at']);
        $this->assertSame('hash-nuevo', $fila->secret);
        $this->assertStringStartsWith(now()->format('Y-m-d H:i:s'), $fila->updated_at);
        $this->assertSame('otro-hash', DB::table('oauth_clients')->where('id', self::OTRO)->value('secret'));
    }
}
