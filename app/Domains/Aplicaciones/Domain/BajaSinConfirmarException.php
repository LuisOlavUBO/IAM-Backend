<?php

namespace App\Domains\Aplicaciones\Domain;

use App\Domains\Aplicaciones\Domain\Dto\ImpactoDto;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * A deactivation is applied only once confirmed: without "confirmar_baja" the problem carries who would lose
 * access or permissions (spec aplicaciones, "Dar de baja y reactivar"; design D2 of aplicaciones-baja).
 */
final class BajaSinConfirmarException extends DomainProblem
{
    public function __construct(public readonly ImpactoDto $impacto)
    {
        parent::__construct('Confirma la baja: quienes la usan pierden el acceso o los permisos que da.');
    }

    public function code(): string
    {
        return 'BAJA_SIN_CONFIRMAR';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Baja sin confirmar';
    }

    /**
     * @return array{impacto: array<string, int|list<string>>}
     */
    public function extensions(): array
    {
        return ['impacto' => $this->impacto->detalle];
    }
}
