<?php

use Illuminate\Support\Facades\Route;
use UBO\Common\Health\Http\Controllers\HealthController;

// Stateless routes under /api/iam (apiPrefix in bootstrap/app.php): no session and no CSRF.
// The portal's routes are in routes/web.php. Machine-to-machine endpoints (with ubo.auth) go here.

Route::prefix('v1')->group(function () {
    // Health of the API and its database (nginx healthcheck), without authentication.
    Route::get('salud', HealthController::class);
});

// Docs: Swagger UI over docs/openapi/openapi.yaml, without authentication.
Route::view('docs/swagger', 'docs.swagger');

Route::get('docs/openapi.yaml', fn () => response()->file(base_path('docs/openapi/openapi.yaml'), [
    'Content-Type' => 'application/yaml; charset=UTF-8',
]));
