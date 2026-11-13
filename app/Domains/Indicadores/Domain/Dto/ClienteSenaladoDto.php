<?php

namespace App\Domains\Indicadores\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * A client an indicator points at, with the date that explains it (last use, last rotation), if any.
 */
final readonly class ClienteSenaladoDto
{
    public function __construct(
        public string $id,
        public string $nombre,
        public ?CarbonImmutable $fecha,
    ) {}
}
