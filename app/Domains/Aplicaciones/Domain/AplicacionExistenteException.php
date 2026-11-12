<?php

namespace App\Domains\Aplicaciones\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Another application already uses that name (spec aplicaciones, "Crear y describir aplicaciones").
 */
final class AplicacionExistenteException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Ya existe una aplicación con ese nombre.');
    }

    public function code(): string
    {
        return 'APLICACION_EXISTENTE';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Aplicación existente';
    }
}
