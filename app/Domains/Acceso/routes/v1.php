<?php

use App\Domains\Acceso\MeController;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Acceso, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).

// The person with a session (Angular builds its menu from "permisos").
Route::get('me', MeController::class);
