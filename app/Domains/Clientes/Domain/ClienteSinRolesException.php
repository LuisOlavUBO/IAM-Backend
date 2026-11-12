<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Only service clients (client-credentials) get roles: the tokens of a login client carry the roles of the
 * person (spec clientes, "Roles solo para clientes de servicio y sin iam-admin").
 */
final class ClienteSinRolesException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('El cliente no es de servicio: sus tokens llevan los roles de la persona, no los del cliente.');
    }

    public function code(): string
    {
        return 'CLIENTE_SIN_ROLES';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Cliente sin roles';
    }
}
