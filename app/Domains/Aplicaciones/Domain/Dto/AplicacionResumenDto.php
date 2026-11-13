<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * An application in the list, with how many permissions and roles it defines and since when it is disabled
 * (spec aplicaciones).
 */
final readonly class AplicacionResumenDto
{
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        public int $permisos,
        public int $roles,
        public ?CarbonImmutable $bajaDesde = null,
    ) {}
}
