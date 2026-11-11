<?php

use App\Domains\Clientes\Features\CrearCliente\CrearClienteEndpoint;
use App\Domains\Clientes\Features\ListarClientes\ListarClientesEndpoint;
use App\Domains\Clientes\Features\ReactivarCliente\ReactivarClienteEndpoint;
use App\Domains\Clientes\Features\RevocarCliente\RevocarClienteEndpoint;
use App\Domains\Clientes\Features\RotarSecreto\RotarSecretoEndpoint;
use App\Domains\Clientes\Features\VerCliente\VerClienteEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Clientes, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// Una línea por caso de uso: app/Domains/Clientes/Features/<CasoDeUso>/.

Route::middleware('can:iam:clientes.leer')->group(function () {
    Route::get('clientes', ListarClientesEndpoint::class)->name('clientes.listar');
    Route::get('clientes/{id}', VerClienteEndpoint::class)->name('clientes.ver');
});

Route::middleware('can:iam:clientes.administrar')->group(function () {
    Route::post('clientes', CrearClienteEndpoint::class)->name('clientes.crear');
    Route::post('clientes/{id}/secreto', RotarSecretoEndpoint::class)->name('clientes.secreto.rotar');
    Route::post('clientes/{id}/revocacion', RevocarClienteEndpoint::class)->name('clientes.revocacion');
    Route::post('clientes/{id}/reactivacion', ReactivarClienteEndpoint::class)->name('clientes.reactivacion');
});
