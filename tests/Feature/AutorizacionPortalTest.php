<?php

namespace Tests\Feature;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec acceso-portal: autorización por permisos iam:* y protección CSRF de las rutas del portal.
 */
class AutorizacionPortalTest extends TestCase
{
    use IniciaSesionEnIam;

    private const MESA_DE_AYUDA = ['iam:usuarios.leer', 'iam:usuarios.administrar', 'iam:aplicaciones.leer'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpIam();

        // A portal route like the ones the next changes add: web group, session and a permission.
        Route::middleware(['web', 'ubo.login'])->prefix('api/iam/v1')->group(function () {
            Route::get('prueba-clientes', fn () => ['data' => 'clientes'])->middleware('can:iam:clientes.leer');
            Route::post('prueba-usuarios', fn () => ['data' => 'guardado'])->middleware('can:iam:usuarios.administrar');
        });
    }

    public function test_the_help_desk_cannot_use_a_route_of_clients(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: self::MESA_DE_AYUDA);

        $this->getJson('/api/iam/v1/prueba-clientes')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_a_person_with_the_permission_can_use_the_route(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['administrador'], scopes: ['iam:clientes.leer']);

        $this->getJson('/api/iam/v1/prueba-clientes')->assertOk()->assertJsonPath('data', 'clientes');
    }

    public function test_a_request_of_angular_with_the_xsrf_header_passes_the_csrf_check(): void
    {
        $this->enforceCsrf();
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: self::MESA_DE_AYUDA);

        // What Angular sends: the XSRF-TOKEN cookie's value back in the X-XSRF-TOKEN header.
        $xsrfCookie = encrypt(CookieValuePrefix::create('XSRF-TOKEN', app('encrypter')->getKey()).csrf_token(), false);

        $this->withBrowserSession()
            ->withHeader('X-XSRF-TOKEN', $xsrfCookie)
            ->postJson('/api/iam/v1/prueba-usuarios')
            ->assertOk();
    }

    public function test_a_change_without_a_csrf_token_is_refused(): void
    {
        $this->enforceCsrf();
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: self::MESA_DE_AYUDA);

        $this->withBrowserSession()->postJson('/api/iam/v1/prueba-usuarios')->assertStatus(419);
    }
}
