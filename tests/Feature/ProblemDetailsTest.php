<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec operacion-del-servicio: errores en RFC 9457 bajo /api/iam.
 */
class ProblemDetailsTest extends TestCase
{
    public function test_an_unknown_route_answers_ruta_no_encontrada(): void
    {
        $this->get('/api/iam/v1/no-existe')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'RUTA_NO_ENCONTRADA');
    }

    public function test_a_wrong_method_answers_metodo_no_permitido(): void
    {
        $this->post('/api/iam/v1/salud')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'METODO_NO_PERMITIDO');
    }

    public function test_an_unexpected_error_hides_its_internal_details(): void
    {
        config(['app.debug' => false]);
        Route::get('api/iam/v1/prueba-error', fn () => throw new RuntimeException('detalle interno secreto'));

        $response = $this->get('/api/iam/v1/prueba-error')
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'ERROR_INTERNO');

        $this->assertStringNotContainsString('detalle interno secreto', $response->getContent());
        $this->assertStringNotContainsString('ProblemDetailsTest', $response->getContent());
    }
}
