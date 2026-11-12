<?php

namespace App\Domains\Aplicaciones\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * A permission with that name already exists, in this or another application: the names of permissions are
 * unique in all of IAM (spec aplicaciones, "Crear y describir permisos").
 */
final class PermisoExistenteException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Ya existe un permiso con ese nombre.');
    }

    public function code(): string
    {
        return 'PERMISO_EXISTENTE';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Permiso existente';
    }
}
