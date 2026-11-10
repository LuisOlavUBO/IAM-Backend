<?php

namespace App\Domains\Usuarios\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Another person already uses that RUT (spec usuarios, "Correo y RUT únicos").
 */
final class RutEnUsoException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Otra persona ya usa ese RUT.');
    }

    public function code(): string
    {
        return 'RUT_EN_USO';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'RUT en uso';
    }
}
