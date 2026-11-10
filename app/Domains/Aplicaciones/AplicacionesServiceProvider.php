<?php

namespace App\Domains\Aplicaciones;

use App\Domains\Aplicaciones\Contracts\IDirectorioAplicacionesService;
use App\Domains\Aplicaciones\Domain\Interfaces\IAplicacionQueryRepository;
use App\Domains\Aplicaciones\Infrastructure\AplicacionQueryRepository;
use App\Domains\Aplicaciones\Infrastructure\DirectorioAplicacionesService;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings of the Aplicaciones domain: each interface of Domain/Interfaces to its implementation in
 * Infrastructure. Its routes are mounted by routes/web.php, in the web group with the portal's session.
 */
class AplicacionesServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IAplicacionQueryRepository::class => AplicacionQueryRepository::class,
        IDirectorioAplicacionesService::class => DirectorioAplicacionesService::class,
    ];
}
