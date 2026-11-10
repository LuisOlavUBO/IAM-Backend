<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The OAuth client asked for does not exist, or its id is not a UUID (spec clientes): 404
 * CLIENTE_NO_ENCONTRADO, not the RUTA_NO_ENCONTRADA of a missing route.
 */
final class ClienteNoEncontradoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('No existe un cliente con ese id.');
    }

    public function code(): string
    {
        return 'CLIENTE_NO_ENCONTRADO';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Cliente no encontrado';
    }
}
