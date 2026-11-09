<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Auth\Exceptions\AuthServerUnavailable;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec acceso-portal: inicio y cierre de sesión por el SSO de IAM (módulo Login de UBO.Common).
 */
class LoginTest extends TestCase
{
    use IniciaSesionEnIam;

    private const ANA = '0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpIam();
    }

    public function test_the_login_sends_the_browser_to_iam_with_pkce(): void
    {
        $response = $this->get('/api/iam/v1/auth/login?redirect=/usuarios');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://iam.test/api/auth/oauth/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame(self::PORTAL_CLIENT, $params['client_id']);
        $this->assertSame('https://iam.ubo.test/api/iam/v1/auth/callback', $params['redirect_uri']);
        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertNotEmpty($params['state']);
        $this->assertNotEmpty($params['code_challenge']);
    }

    public function test_a_successful_login_goes_back_without_exposing_the_tokens(): void
    {
        $params = $this->startLogin('?redirect=/usuarios');
        $token = $this->personToken(self::ANA);
        $this->iamIssues($token);

        $response = $this->returnFromIam($params['state'])->assertRedirect('/usuarios');

        $this->assertStringNotContainsString($token, (string) $response->getContent());
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringNotContainsString($token, (string) $cookie->getValue());
            $this->assertStringNotContainsString('refresh-token', (string) $cookie->getValue());
        }
    }

    public function test_an_external_destination_is_ignored(): void
    {
        $params = $this->startLogin('?redirect='.urlencode('https://otro-sitio.cl/'));
        $this->iamIssues($this->personToken(self::ANA));

        $this->returnFromIam($params['state'])->assertRedirect('/');
    }

    public function test_a_state_that_does_not_match_goes_back_with_login_invalido(): void
    {
        $this->startLogin();
        Http::fake();

        $this->returnFromIam('otro-state')->assertRedirect('/?error_login=LOGIN_INVALIDO');

        Http::assertNothingSent();
        $this->assertNull(session('ubo_login'));
    }

    public function test_a_person_without_roles_in_iam_admin_goes_back_with_sin_acceso(): void
    {
        $params = $this->startLogin();
        $this->iamIssues($this->personToken(self::ANA, roles: [], scopes: []));

        $this->returnFromIam($params['state'])->assertRedirect('/?error_login=SIN_ACCESO');

        $this->assertNull(session('ubo_login'));
    }

    public function test_iam_unavailable_goes_back_with_iam_no_disponible(): void
    {
        $params = $this->startLogin();
        Http::fake([self::IAM_TOKEN_URL => fn () => throw new AuthServerUnavailable('IAM no responde')]);

        $this->returnFromIam($params['state'])->assertRedirect('/?error_login=IAM_NO_DISPONIBLE');
    }

    public function test_the_logout_closes_the_session_and_goes_to_the_central_logout(): void
    {
        $this->enforceCsrf();
        UboLogin::actingAs(id: self::ANA, roles: ['administrador']);
        $this->withBrowserSession()->get('/api/iam/v1/auth/login');
        Http::fake();

        $response = $this->withBrowserSession()->post('/api/iam/v1/auth/logout', ['_token' => csrf_token()]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://iam.test/api/auth/logout?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame(self::PORTAL_CLIENT, $params['client_id']);
        $this->assertStringStartsWith('https://iam.ubo.test', $params['return']);
        $this->assertNull(session('ubo_login'));
    }

    public function test_the_logout_without_a_csrf_token_is_refused(): void
    {
        $this->enforceCsrf();
        UboLogin::actingAs(id: self::ANA, roles: ['administrador']);

        $this->withBrowserSession()->post('/api/iam/v1/auth/logout')->assertStatus(419);

        $this->assertNotNull(session('ubo_login'));
    }
}
