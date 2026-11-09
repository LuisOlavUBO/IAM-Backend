<?php

use App\Domains\Usuarios\Http\Controllers\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Usuarios, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).

Route::middleware('can:iam:usuarios.leer')->group(function () {
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.listar');
    Route::get('usuarios/{uuid}', [UsuarioController::class, 'show'])->name('usuarios.ver');
});
