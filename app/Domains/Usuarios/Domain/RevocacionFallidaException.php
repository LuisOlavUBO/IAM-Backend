<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * API-Auth did not confirm the revocation of the person's sessions (spec usuarios): 502, it can be retried.
 */
final class RevocacionFallidaException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('IAM no confirmó el cierre de las sesiones. Intente de nuevo en unos minutos.');
    }

    public function code(): string
    {
        return 'REVOCACION_FALLIDA';
    }

    public function status(): int
    {
        return 502;
    }

    public function title(): string
    {
        return 'Revocación fallida';
    }
}
