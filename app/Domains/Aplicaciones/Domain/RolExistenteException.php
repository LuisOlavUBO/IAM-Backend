<?php

namespace App\Domains\Aplicaciones\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The application already has a role with that name, without distinguishing case (spec aplicaciones, "Crear y
 * describir roles").
 */
final class RolExistenteException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('La aplicación ya tiene un rol con ese nombre.');
    }

    public function code(): string
    {
        return 'ROL_EXISTENTE';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Rol existente';
    }
}
