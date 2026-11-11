<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The clients of iam-admin are used by API-IAM itself (the portal's login and the revocation of sessions):
 * revoking one would leave the portal unable to undo it (spec clientes, "Clientes de iam-admin no se
 * revocan").
 */
final class ClienteDeIamAdminException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('El cliente es de iam-admin: API-IAM lo usa y no se puede revocar.');
    }

    public function code(): string
    {
        return 'CLIENTE_DE_IAM_ADMIN';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Cliente de iam-admin';
    }
}
