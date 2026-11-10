<?php

use App\Domains\Acceso\Features\VerPersonaConSesion\VerPersonaConSesionEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Acceso, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// El login, el callback y el logout los registra el módulo Login de ubo/common.

// La persona con sesión (Angular arma su menú con "permisos").
Route::get('me', VerPersonaConSesionEndpoint::class);
