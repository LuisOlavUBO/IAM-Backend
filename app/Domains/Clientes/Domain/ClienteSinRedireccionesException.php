<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Only login clients (authorization-code) have redirections (spec clientes, "Cambiar las redirecciones de
 * un cliente de login").
 */
final class ClienteSinRedireccionesException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('El cliente no es de login: no usa redirecciones.');
    }

    public function code(): string
    {
        return 'CLIENTE_SIN_REDIRECCIONES';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Cliente sin redirecciones';
    }
}
