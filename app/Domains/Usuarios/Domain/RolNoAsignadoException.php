<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The person does not have that role, or the role does not exist (spec usuarios).
 */
final class RolNoAsignadoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('La persona no tiene ese rol.');
    }

    public function code(): string
    {
        return 'ROL_NO_ASIGNADO';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Rol no asignado';
    }
}
