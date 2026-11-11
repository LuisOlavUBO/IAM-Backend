<?php

namespace App\Domains\Clientes\Features\CrearCliente;

/**
 * The client just created: its id and its secret in clear, which only goes back in the response, once.
 */
final readonly class ClienteCreadoDto
{
    public function __construct(
        public string $id,
        public string $secreto,
    ) {}
}
