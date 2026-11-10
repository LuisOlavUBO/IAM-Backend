<?php

use App\Domains\Auditoria\Features\ListarAuditoria\ListarAuditoriaEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Auditoria, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// Una línea por caso de uso: app/Domains/Auditoria/Features/<CasoDeUso>/.

Route::middleware('can:iam:auditoria.leer')->group(function () {
    Route::get('auditoria', ListarAuditoriaEndpoint::class)->name('auditoria.listar');
});
