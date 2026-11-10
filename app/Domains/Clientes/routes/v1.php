<?php

use App\Domains\Clientes\Features\ListarClientes\ListarClientesEndpoint;
use App\Domains\Clientes\Features\VerCliente\VerClienteEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Clientes, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// Una línea por caso de uso: app/Domains/Clientes/Features/<CasoDeUso>/.

Route::middleware('can:iam:clientes.leer')->group(function () {
    Route::get('clientes', ListarClientesEndpoint::class)->name('clientes.listar');
    Route::get('clientes/{id}', VerClienteEndpoint::class)->name('clientes.ver');
});
