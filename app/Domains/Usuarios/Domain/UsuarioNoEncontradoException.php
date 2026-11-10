<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The person asked for does not exist (spec usuarios): 404 USUARIO_NO_ENCONTRADO, not the
 * RUTA_NO_ENCONTRADA of a missing route (design D6).
 */
final class UsuarioNoEncontradoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('No existe una persona con ese identificador.');
    }

    public function code(): string
    {
        return 'USUARIO_NO_ENCONTRADO';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Usuario no encontrado';
    }
}
