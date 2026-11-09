<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use UBO\Common\Auth\Testing\TokenFactory;

/**
 * The portal's login against a fake IAM, as UBO.Common's own tests do: the browser reaches IAM at
 * iam.test, this server calls it at api-auth.test, and the tokens are signed with the test key.
 */
trait IniciaSesionEnIam
{
    protected const IAM_TOKEN_URL = 'https://api-auth.test/api/auth/oauth/token';

    protected const PORTAL_CLIENT = 'portal-client';

    protected TokenFactory $tokens;

    protected function setUpIam(): void
    {
        config([
            'app.url' => 'https://iam.ubo.test',
            'ubo-auth.url' => 'https://api-auth.test',
            'ubo-login.public_url' => 'https://iam.test',
            'ubo-login.client_id' => self::PORTAL_CLIENT,
            'ubo-login.client_secret' => 'portal-secret',
            'ubo-login.home' => '/',
        ]);

        $this->tokens = TokenFactory::fake();
    }

    /**
     * The browser starts a login: the parameters of the redirect to IAM.
     *
     * @return array<string, string>
     */
    protected function startLogin(string $query = ''): array
    {
        $response = $this->get('/api/iam/v1/auth/login'.$query);
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $params);

        return $params;
    }

    protected function iamIssues(string $accessToken, int $status = 200): void
    {
        Http::fake([self::IAM_TOKEN_URL => Http::response(
            $status === 200
                ? ['token_type' => 'Bearer', 'expires_in' => 3600, 'access_token' => $accessToken, 'refresh_token' => 'refresh-token']
                : ['error' => 'invalid_grant', 'error_description' => 'The provided authorization grant is invalid.'],
            $status,
        )]);
    }

    /**
     * A person's token of the portal client, with their roles and scopes in iam-admin.
     *
     * @param  list<string>  $roles
     * @param  list<string>  $scopes
     */
    protected function personToken(string $uuid, array $roles = ['administrador'], array $scopes = ['iam:usuarios.leer']): string
    {
        return $this->tokens->forUser('42', self::PORTAL_CLIENT, $scopes, roles: $roles, audiences: ['iam-admin'], uuid: $uuid);
    }

    protected function returnFromIam(string $state): TestResponse
    {
        return $this->withBrowserSession()->get('/api/iam/v1/auth/callback?code=the-code&state='.$state);
    }

    /**
     * Laravel skips the CSRF check while running tests: check it as in production.
     */
    protected function enforceCsrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    /**
     * The next request carries the session cookie the browser would hold.
     */
    protected function withBrowserSession(): static
    {
        return $this->withCookie(config('session.cookie'), app('session.store')->getId());
    }
}
