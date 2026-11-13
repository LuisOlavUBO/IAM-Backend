<?php

use App\Domains\Aplicaciones\Features\BorrarAplicacion\BorrarAplicacionEndpoint;
use App\Domains\Aplicaciones\Features\BorrarPermiso\BorrarPermisoEndpoint;
use App\Domains\Aplicaciones\Features\BorrarRol\BorrarRolEndpoint;
use App\Domains\Aplicaciones\Features\CambiarPermisosDeRol\CambiarPermisosDeRolEndpoint;
use App\Domains\Aplicaciones\Features\CrearAplicacion\CrearAplicacionEndpoint;
use App\Domains\Aplicaciones\Features\CrearPermiso\CrearPermisoEndpoint;
use App\Domains\Aplicaciones\Features\CrearRol\CrearRolEndpoint;
use App\Domains\Aplicaciones\Features\EditarAplicacion\EditarAplicacionEndpoint;
use App\Domains\Aplicaciones\Features\EditarPermiso\EditarPermisoEndpoint;
use App\Domains\Aplicaciones\Features\EditarRol\EditarRolEndpoint;
use App\Domains\Aplicaciones\Features\ListarAplicaciones\ListarAplicacionesEndpoint;
use App\Domains\Aplicaciones\Features\VerAplicacion\VerAplicacionEndpoint;
use Illuminate\Support\Facades\Route;

// Rutas del dominio Aplicaciones, bajo /api/iam/v1 en el grupo web, con sesión (routes/web.php).
// Una línea por caso de uso: app/Domains/Aplicaciones/Features/<CasoDeUso>/.

Route::middleware('can:iam:aplicaciones.leer')->group(function () {
    Route::get('aplicaciones', ListarAplicacionesEndpoint::class)->name('aplicaciones.listar');
    Route::get('aplicaciones/{nombre}', VerAplicacionEndpoint::class)->name('aplicaciones.ver');
});

Route::middleware('can:iam:aplicaciones.administrar')->group(function () {
    Route::post('aplicaciones', CrearAplicacionEndpoint::class)->name('aplicaciones.crear');
    Route::patch('aplicaciones/{nombre}', EditarAplicacionEndpoint::class)->name('aplicaciones.editar');
    Route::post('aplicaciones/{nombre}/permisos', CrearPermisoEndpoint::class)->name('aplicaciones.permisos.crear');
    Route::patch('aplicaciones/{nombre}/permisos/{permiso}', EditarPermisoEndpoint::class)->name('aplicaciones.permisos.editar');
    Route::post('aplicaciones/{nombre}/roles', CrearRolEndpoint::class)->name('aplicaciones.roles.crear');
    Route::patch('aplicaciones/{nombre}/roles/{rol}', EditarRolEndpoint::class)->name('aplicaciones.roles.editar');
    Route::put('aplicaciones/{nombre}/roles/{rol}/permisos', CambiarPermisosDeRolEndpoint::class)->name('aplicaciones.roles.permisos');
    Route::delete('aplicaciones/{nombre}', BorrarAplicacionEndpoint::class)->name('aplicaciones.borrar');
    Route::delete('aplicaciones/{nombre}/permisos/{permiso}', BorrarPermisoEndpoint::class)->name('aplicaciones.permisos.borrar');
    Route::delete('aplicaciones/{nombre}/roles/{rol}', BorrarRolEndpoint::class)->name('aplicaciones.roles.borrar');
});
