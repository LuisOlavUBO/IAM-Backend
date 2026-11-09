<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;

/**
 * Spec acceso-portal: una persona dada de baja (o con sus sesiones revocadas) pierde el acceso al
 * portal en a lo más el intervalo de revalidación (design D5).
 */
class RevalidacionTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const ANA = '0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60';

    public function test_the_example_environment_revalidates_with_hybrid_and_without_degraded_mode(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^UBO_AUTH_STRATEGY=hybrid$/m', $example);
        $this->assertMatchesRegularExpression('/^UBO_AUTH_DEGRADED_MODE=false$/m', $example);
        $this->assertMatchesRegularExpression('/^UBO_LOGIN_REVALIDATE=300$/m', $example);
    }

    public function test_a_person_disabled_with_an_open_session_is_out_after_the_revalidation_interval(): void
    {
        $this->setUpIam();
        config([
            'ubo-auth.strategy' => 'hybrid',
            'ubo-auth.degraded_mode' => false,
            'ubo-auth.client_id' => 'iam-admin-sistema',
            'ubo-auth.client_secret' => 'sistema-secret',
            'ubo-login.revalidate' => 300,
        ]);

        $token = $this->personToken(self::ANA, scopes: ['iam:usuarios.leer']);
        $params = $this->startLogin();
        $this->iamIssues($token);
        $this->returnFromIam($params['state'])->assertRedirect('/');
        $this->withBrowserSession()->getJson('/api/iam/v1/me')->assertOk();

        // IAM disables Ana: her tokens become inactive in the introspection.
        $this->tokens->revoke($token);
        $this->travel(301)->seconds();

        $this->withBrowserSession()->getJson('/api/iam/v1/me')->assertUnauthorized();
    }
}
