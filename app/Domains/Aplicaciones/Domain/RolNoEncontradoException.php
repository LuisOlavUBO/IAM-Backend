<?php

namespace App\Domains\Aplicaciones\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The application has no role with that name (spec aplicaciones, "Permiso para administrar el catálogo").
 */
final class RolNoEncontradoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('La aplicación no tiene un rol con ese nombre.');
    }

    public function code(): string
    {
        return 'ROL_NO_ENCONTRADO';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Rol no encontrado';
    }
}
