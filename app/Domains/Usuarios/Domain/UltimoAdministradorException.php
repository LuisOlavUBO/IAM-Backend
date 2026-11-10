<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * IAM must keep at least one active person with iam-admin/administrador (spec usuarios).
 */
final class UltimoAdministradorException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Es la última persona activa con iam-admin/administrador: asigne el rol a otra persona antes de quitárselo.');
    }

    public function code(): string
    {
        return 'ULTIMO_ADMINISTRADOR';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Último administrador';
    }
}
