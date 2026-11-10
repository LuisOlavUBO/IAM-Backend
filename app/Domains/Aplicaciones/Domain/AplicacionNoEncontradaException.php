<?php

namespace App\Domains\Aplicaciones\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The application asked for does not exist (spec aplicaciones): 404 APLICACION_NO_ENCONTRADA, not the
 * RUTA_NO_ENCONTRADA of a missing route.
 */
final class AplicacionNoEncontradaException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('No existe una aplicación con ese nombre.');
    }

    public function code(): string
    {
        return 'APLICACION_NO_ENCONTRADA';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Aplicación no encontrada';
    }
}
