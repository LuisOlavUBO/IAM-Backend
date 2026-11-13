<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * A person with open sessions from `ips` different addresses (the addresses themselves are not shown).
 */
final readonly class PersonaConVariasIpsDto
{
    public function __construct(
        public string $uuid,
        public string $nombre,
        public int $ips,
    ) {}
}
