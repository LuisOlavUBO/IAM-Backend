<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The client does not have that role, or the role does not exist (spec clientes): the same code as in
 * Usuarios.
 */
final class RolNoAsignadoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('El cliente no tiene ese rol.');
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
