<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * Block `auditoria` (spec indicadores): refusals of the last 30 days by motivo, most frequent first,
 * and the applied changes of the last 7 days.
 */
final readonly class AuditoriaDto
{
    /**
     * @param  array<string, int>  $rechazos
     */
    public function __construct(
        public array $rechazos,
        public int $cambios7d,
    ) {}
}
