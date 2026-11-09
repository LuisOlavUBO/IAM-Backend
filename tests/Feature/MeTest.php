<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec acceso-portal: persona con sesión y rutas que exigen sesión.
 */
class MeTest extends TestCase
{
    use RefreshDatabase;

    private const ANA = '0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60';

    private const PERMISOS = [
        'iam:usuarios.leer', 'iam:usuarios.administrar', 'iam:catalogo.leer', 'iam:catalogo.administrar',
        'iam:clientes.leer', 'iam:clientes.administrar', 'iam:auditoria.leer',
    ];

    public function test_an_administrator_with_a_session_gets_who_they_are(): void
    {
        DB::table('users')->insert(['uuid' => self::ANA, 'name' => 'Ana Pérez', 'email' => 'ana@ubo.cl']);
        UboLogin::actingAs(id: strtoupper(self::ANA), roles: ['administrador'], scopes: self::PERMISOS);

        $response = $this->getJson('/api/iam/v1/me')->assertOk();

        $response->assertJsonPath('data.uuid', self::ANA)
            ->assertJsonPath('data.nombre', 'Ana Pérez')
            ->assertJsonPath('data.correo', 'ana@ubo.cl')
            ->assertJsonPath('data.roles', ['administrador'])
            ->assertJsonPath('data.permisos', self::PERMISOS);
        $this->assertNotEmpty($response->json('data.csrf'));
        $this->assertSame(['uuid', 'nombre', 'correo', 'roles', 'permisos', 'csrf'], array_keys($response->json('data')));
    }

    public function test_me_without_a_session_answers_401(): void
    {
        $this->get('/api/iam/v1/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'NO_AUTENTICADO');
    }
}
