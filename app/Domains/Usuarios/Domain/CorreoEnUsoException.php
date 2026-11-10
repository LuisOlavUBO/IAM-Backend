<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Another person already uses that email (spec usuarios, "Correo y RUT únicos").
 */
final class CorreoEnUsoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Otra persona ya usa ese correo.');
    }

    public function code(): string
    {
        return 'CORREO_EN_USO';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Correo en uso';
    }
}
