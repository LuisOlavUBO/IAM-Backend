<?php

namespace App\Domains\Clientes;

use App\Domains\Clientes\Contracts\IDirectorioClientesService;
use App\Domains\Clientes\Domain\Interfaces\IClienteQueryRepository;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Infrastructure\ClienteQueryRepository;
use App\Domains\Clientes\Infrastructure\ClientesRepository;
use App\Domains\Clientes\Infrastructure\DirectorioClientesService;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings of the Clientes domain: each interface of Domain/Interfaces to its implementation in
 * Infrastructure. Its routes are mounted by routes/web.php, in the web group with the portal's session.
 */
class ClientesServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IClienteQueryRepository::class => ClienteQueryRepository::class,
        IDirectorioClientesService::class => DirectorioClientesService::class,
        IClientesRepository::class => ClientesRepository::class,
    ];
}
