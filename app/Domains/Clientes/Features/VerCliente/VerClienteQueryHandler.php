<?php

namespace App\Domains\Clientes\Features\VerCliente;

use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\Dto\ClienteDetalleDto;
use App\Domains\Clientes\Domain\Interfaces\IClienteQueryRepository;
use Illuminate\Support\Str;

/**
 * Use case: one OAuth client with its redirections and roles (spec clientes).
 */
final class VerClienteQueryHandler
{
    public function __construct(private readonly IClienteQueryRepository $clientes) {}

    public function __invoke(string $id): ClienteDetalleDto
    {
        // A value that is not a UUID would not convert to uniqueidentifier in SQL Server.
        if (! Str::isUuid($id)) {
            throw new ClienteNoEncontradoException;
        }

        return $this->clientes->detalle($id) ?? throw new ClienteNoEncontradoException;
    }
}
