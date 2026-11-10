<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Nobody changes their own roles nor revokes their own sessions (spec usuarios).
 */
final class AccionSobreSiMismoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('No puede cambiar sus propios roles ni cerrar sus propias sesiones: pídaselo a otra persona administradora.');
    }

    public function code(): string
    {
        return 'ACCION_SOBRE_SI_MISMO';
    }

    public function status(): int
    {
        return 403;
    }

    public function title(): string
    {
        return 'Acción sobre uno mismo';
    }
}
