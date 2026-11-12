<?php

namespace App\Domains\Aplicaciones\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The application has no permission with that name (spec aplicaciones, "Permiso para administrar el
 * catálogo").
 */
final class PermisoNoEncontradoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('La aplicación no tiene un permiso con ese nombre.');
    }

    public function code(): string
    {
        return 'PERMISO_NO_ENCONTRADO';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Permiso no encontrado';
    }
}
