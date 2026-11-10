<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Giving or taking a role of iam-admin needs every permission of iam-admin (spec usuarios): the help desk
 * cannot make anyone an administrator.
 */
final class EscalamientoDePrivilegiosException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Para asignar o quitar roles de iam-admin necesita todos los permisos de iam-admin.');
    }

    public function code(): string
    {
        return 'ESCALAMIENTO_DE_PRIVILEGIOS';
    }

    public function status(): int
    {
        return 403;
    }

    public function title(): string
    {
        return 'Escalamiento de privilegios';
    }
}
