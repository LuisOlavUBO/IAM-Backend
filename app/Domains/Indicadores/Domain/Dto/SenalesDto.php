<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * Block `senales` (spec indicadores): the people with open sessions of the central login from more
 * than one IP at the same time. A weak signal until API-Auth records authentication events.
 */
final readonly class SenalesDto
{
    /**
     * @param  list<PersonaConVariasIpsDto>  $sesionesDesdeVariasIps
     */
    public function __construct(
        public array $sesionesDesdeVariasIps,
    ) {}
}
