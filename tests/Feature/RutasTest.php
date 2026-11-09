<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Spec operacion-del-servicio: toda ruta de API-IAM está bajo /api/iam (el proxy no reescribe la ruta).
 */
class RutasTest extends TestCase
{
    public function test_every_application_route_is_under_api_iam(): void
    {
        $outside = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->reject(fn (string $uri) => str_starts_with($uri, 'api/iam/') || str_starts_with($uri, '_boost/'))
            ->values()
            ->all();

        $this->assertSame([], $outside);
    }
}
