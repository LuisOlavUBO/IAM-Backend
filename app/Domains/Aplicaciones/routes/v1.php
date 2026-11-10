<?php

use App\Domains\Aplicaciones\Features\ListarAplicaciones\ListarAplicacionesEndpoint;
use App\Domains\Aplicaciones\Features\VerAplicacion\VerAplicacionEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Aplicaciones, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// Una línea por caso de uso: app/Domains/Aplicaciones/Features/<CasoDeUso>/.

Route::middleware('can:iam:aplicaciones.leer')->group(function () {
    Route::get('aplicaciones', ListarAplicacionesEndpoint::class)->name('aplicaciones.listar');
    Route::get('aplicaciones/{nombre}', VerAplicacionEndpoint::class)->name('aplicaciones.ver');
});
