<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * Service tokens (without a person) a client got in the last 24 hours and 7 days.
 */
final readonly class TokensDeClienteDto
{
    public function __construct(
        public string $id,
        public string $nombre,
        public int $tokens24h,
        public int $tokens7d,
    ) {}
}
