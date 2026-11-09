<?php

namespace App\Domains\Usuarios;

use App\Domains\Usuarios\Contracts\IUsuarioRepository;
use App\Domains\Usuarios\Infrastructure\Eloquent\EloquentUsuarioRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings of the Usuarios domain. Its routes are mounted by routes/web.php, in the web group with the
 * portal's session (design D1).
 */
class UsuariosServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IUsuarioRepository::class => EloquentUsuarioRepository::class,
    ];
}
