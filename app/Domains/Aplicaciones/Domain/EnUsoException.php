<?php

namespace App\Domains\Aplicaciones\Domain;

use App\Domains\Aplicaciones\Domain\Dto\ImpactoDto;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Only what is not in use is deleted: the problem carries the impact that makes it in use (spec aplicaciones,
 * "Borrar lo que no está en uso"; design D3 of aplicaciones-renombrar-y-borrar). What is in use is disabled
 * instead (aplicaciones-baja).
 */
final class EnUsoException extends DomainProblem
{
    public function __construct(public readonly ImpactoDto $impacto)
    {
        parent::__construct('No se borra porque está en uso.');
    }

    public function code(): string
    {
        return 'EN_USO';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'En uso';
    }

    /**
     * @return array{impacto: array<string, int|list<string>>}
     */
    public function extensions(): array
    {
        return ['impacto' => $this->impacto->detalle];
    }
}
