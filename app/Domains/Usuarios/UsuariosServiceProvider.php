<?php

namespace App\Domains\Usuarios;

use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Domains\Usuarios\Domain\Interfaces\IUsuarioQueryRepository;
use App\Domains\Usuarios\Infrastructure\DirectorioUsuariosService;
use App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings of the Usuarios domain: each interface (Domain/Interfaces, Contracts) to its implementation in
 * Infrastructure. Its routes are mounted by routes/web.php, in the web group with the portal's session.
 */
class UsuariosServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IUsuarioQueryRepository::class => UsuarioQueryRepository::class,
        IDirectorioUsuariosService::class => DirectorioUsuariosService::class,
    ];
}
