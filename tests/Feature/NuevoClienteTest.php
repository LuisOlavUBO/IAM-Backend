<?php

namespace Tests\Feature;

use App\Domains\Clientes\Domain\NuevoCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Clients are stored as API-Auth's Application::createClient does, so Passport accepts them.
 */
class NuevoClienteTest extends TestCase
{
    use RefreshDatabase;

    private int $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
    }

    public function test_an_authorization_code_client(): void
    {
        $nuevo = app(NuevoCliente::class)->crear($this->application, 'iam-admin-portal', NuevoCliente::AUTHORIZATION_CODE, ['https://iam.ubo.cl/api/iam/v1/auth/callback']);

        $client = DB::table('oauth_clients')->where('id', $nuevo['id'])->sole();

        $this->assertSame('users', $client->provider);
        $this->assertSame(['authorization_code', 'refresh_token'], json_decode($client->grant_types, true));
        $this->assertSame(['https://iam.ubo.cl/api/iam/v1/auth/callback'], json_decode($client->redirect_uris, true));
        $this->assertSame('0', (string) $client->revoked);
        $this->assertSame($this->application, (int) $client->application_id);
    }

    public function test_a_client_credentials_client(): void
    {
        $nuevo = app(NuevoCliente::class)->crear($this->application, 'iam-admin-sistema', NuevoCliente::CLIENT_CREDENTIALS);

        $client = DB::table('oauth_clients')->where('id', $nuevo['id'])->sole();

        $this->assertNull($client->provider);
        $this->assertSame(['client_credentials'], json_decode($client->grant_types, true));
        $this->assertSame([], json_decode($client->redirect_uris, true));
    }

    public function test_the_secret_has_40_characters_and_only_its_bcrypt_hash_is_stored(): void
    {
        $nuevo = app(NuevoCliente::class)->crear($this->application, 'iam-admin-sistema', NuevoCliente::CLIENT_CREDENTIALS);
        $stored = DB::table('oauth_clients')->where('id', $nuevo['id'])->value('secret');

        $this->assertSame(40, strlen($nuevo['secreto']));
        $this->assertNotSame($nuevo['secreto'], $stored);
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check($nuevo['secreto'], $stored));
    }

    public function test_an_authorization_code_client_needs_a_redirect(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NuevoCliente::class)->crear($this->application, 'sin-redireccion', NuevoCliente::AUTHORIZATION_CODE);
    }
}
