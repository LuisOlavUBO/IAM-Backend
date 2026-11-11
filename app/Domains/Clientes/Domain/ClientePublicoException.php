<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * A public client (PKCE, without secret) does not get one: it would break the application that uses it
 * (spec clientes, "Clientes públicos sin secreto").
 */
final class ClientePublicoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('El cliente es público (PKCE): no tiene secreto que rotar.');
    }

    public function code(): string
    {
        return 'CLIENTE_PUBLICO';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Cliente público';
    }
}
