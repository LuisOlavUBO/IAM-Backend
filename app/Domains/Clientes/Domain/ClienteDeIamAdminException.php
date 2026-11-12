<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The clients of iam-admin are used by API-IAM itself (the portal's login and the revocation of sessions):
 * revoking one, or changing its roles or redirections, could leave the portal without login or unable to undo
 * it (spec clientes, "Clientes de iam-admin no se revocan" and "Clientes de iam-admin sin cambios desde el
 * portal").
 */
final class ClienteDeIamAdminException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('El cliente es de iam-admin: API-IAM lo usa y no se cambia desde el portal.');
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
