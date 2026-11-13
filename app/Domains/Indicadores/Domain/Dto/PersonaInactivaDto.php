<?php

namespace App\Domains\Indicadores\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * A person and their last activity (null when they never got a token).
 */
final readonly class PersonaInactivaDto
{
    public function __construct(
        public string $uuid,
        public string $nombre,
        public ?CarbonImmutable $ultimaActividad,
    ) {}
}
