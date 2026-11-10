<?php

namespace Tests\Feature\Usuarios;

use App\Domains\Usuarios\Infrastructure\ApiAuthRevocadorDeSesiones;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UBO\Common\Auth\Contracts\ServiceTokenProvider;
use UBO\Common\Auth\Exceptions\ServiceTokenException;

/**
 * Design D5: the revocation of a person's sessions in API-Auth, against a fake API-Auth.
 */
class ApiAuthRevocadorDeSesionesTest extends TestCase
{
    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const URL = 'https://api-auth.test/api/auth/v1/users/'.self::ANA.'/revoke';

    private ServiceTokenProviderFalso $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ubo-auth.url' => 'https://api-auth.test/']);
        $this->tokens = new ServiceTokenProviderFalso;
    }

    private function revocar(): bool
    {
        return (new ApiAuthRevocadorDeSesiones($this->tokens))->revocar(self::ANA);
    }

    public function test_api_auth_confirms_with_204(): void
    {
        Http::fake([self::URL => Http::response(null, 204)]);

        $this->assertTrue($this->revocar());

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::URL
            && $r->hasHeader('Authorization', 'Bearer token-de-servicio'));
        $this->assertSame([['sessions:revoke']], $this->tokens->pedidos);
    }

    public function test_a_person_unknown_to_api_auth_has_nothing_to_close(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'NO_ENCONTRADO'], 404)]);

        $this->assertTrue($this->revocar());
    }

    public function test_an_error_of_api_auth_is_false(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'ERROR_INTERNO'], 500)]);

        $this->assertFalse($this->revocar());
    }

    public function test_a_refused_token_is_forgotten_for_the_next_attempt(): void
    {
        Http::fake([self::URL => Http::response(null, 401)]);

        $this->assertFalse($this->revocar());
        $this->assertSame([['sessions:revoke']], $this->tokens->olvidados);
    }

    public function test_no_service_token_or_no_connection_is_false(): void
    {
        $this->tokens->falla = true;
        $this->assertFalse($this->revocar());

        $this->tokens->falla = false;
        Http::fake([self::URL => fn () => throw new ConnectionException('timeout')]);
        $this->assertFalse($this->revocar());
    }
}

/**
 * A fake ServiceTokenProvider that records what it is asked.
 */
final class ServiceTokenProviderFalso implements ServiceTokenProvider
{
    public bool $falla = false;

    /** @var list<list<string>> */
    public array $pedidos = [];

    /** @var list<list<string>> */
    public array $olvidados = [];

    public function token(array $scopes = [], ?string $api = null): string
    {
        if ($this->falla) {
            throw ServiceTokenException::notConfigured();
        }
        $this->pedidos[] = $scopes;

        return 'token-de-servicio';
    }

    public function forget(array $scopes = [], ?string $api = null): void
    {
        $this->olvidados[] = $scopes;
    }
}
