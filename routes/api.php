<?php

use Illuminate\Support\Facades\Route;
use UBO\Common\Health\Http\Controllers\HealthController;

// Every route here is under /api/iam (apiPrefix in bootstrap/app.php).

Route::prefix('v1')->group(function () {
    // Health of the API and its database (nginx healthcheck), without authentication.
    Route::get('salud', HealthController::class);

    // One route file per domain: app/Domains/<Dominio>/routes/v1.php
    foreach (['Acceso', 'Catalogo', 'Usuarios', 'Clientes', 'Auditoria'] as $domain) {
        require app_path("Domains/{$domain}/routes/v1.php");
    }
});

// Docs: Swagger UI over docs/openapi/openapi.yaml, without authentication.
Route::view('docs/swagger', 'docs.swagger');

Route::get('docs/openapi.yaml', fn () => response()->file(base_path('docs/openapi/openapi.yaml'), [
    'Content-Type' => 'application/yaml; charset=UTF-8',
]));
