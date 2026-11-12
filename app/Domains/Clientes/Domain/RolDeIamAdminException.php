<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The roles of iam-admin are not given to clients (spec clientes, "Roles solo para clientes de servicio y
 * sin iam-admin").
 */
final class RolDeIamAdminException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Los roles de iam-admin no se asignan a clientes.');
    }

    public function code(): string
    {
        return 'ROL_DE_IAM_ADMIN';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Rol de iam-admin';
    }
}
