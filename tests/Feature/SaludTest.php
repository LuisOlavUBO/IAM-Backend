<?php

namespace Tests\Feature;

use Tests\TestCase;
use UBO\Common\Health\DatabaseHealth;

/**
 * Spec operacion-del-servicio: salud con la base disponible y caída.
 */
class SaludTest extends TestCase
{
    public function test_health_answers_ok_without_credentials_when_the_database_responds(): void
    {
        $this->getJson('/api/iam/v1/salud')
            ->assertOk()
            ->assertExactJson(['estado' => 'OK', 'base_datos' => 'OK']);
    }

    public function test_health_answers_503_without_details_when_the_database_is_down(): void
    {
        $this->mock(DatabaseHealth::class)->expects('isAvailable')->andReturnFalse();

        $this->getJson('/api/iam/v1/salud')
            ->assertServiceUnavailable()
            ->assertExactJson(['estado' => 'ERROR', 'base_datos' => 'NO_DISPONIBLE']);
    }
}
