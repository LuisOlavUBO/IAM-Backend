<?php

use App\Domains\Usuarios\Features\AsignarRol\AsignarRolEndpoint;
use App\Domains\Usuarios\Features\ListarUsuarios\ListarUsuariosEndpoint;
use App\Domains\Usuarios\Features\QuitarRol\QuitarRolEndpoint;
use App\Domains\Usuarios\Features\RevocarSesiones\RevocarSesionesEndpoint;
use App\Domains\Usuarios\Features\VerUsuario\VerUsuarioEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Usuarios, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// Una línea por caso de uso: app/Domains/Usuarios/Features/<CasoDeUso>/.

Route::middleware('can:iam:usuarios.leer')->group(function () {
    Route::get('usuarios', ListarUsuariosEndpoint::class)->name('usuarios.listar');
    Route::get('usuarios/{uuid}', VerUsuarioEndpoint::class)->name('usuarios.ver');
});

Route::middleware('can:iam:usuarios.administrar')->group(function () {
    Route::post('usuarios/{uuid}/roles', AsignarRolEndpoint::class)->name('usuarios.roles.asignar');
    Route::delete('usuarios/{uuid}/roles/{aplicacion}/{rol}', QuitarRolEndpoint::class)->name('usuarios.roles.quitar');
    Route::post('usuarios/{uuid}/sesiones/revocar', RevocarSesionesEndpoint::class)->name('usuarios.sesiones.revocar');
});
