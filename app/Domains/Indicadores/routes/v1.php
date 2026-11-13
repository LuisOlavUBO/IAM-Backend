<?php

use App\Domains\Indicadores\Features\VerIndicadores\VerIndicadoresEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Indicadores, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// Una línea por caso de uso: app/Domains/Indicadores/Features/<CasoDeUso>/.

// Solo con sesión: cada bloque de la respuesta depende de un permiso de lectura.
Route::get('indicadores', VerIndicadoresEndpoint::class)->name('indicadores.ver');
