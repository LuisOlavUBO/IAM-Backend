<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * A role of an application with the names of the permissions it grants, ordered, and since when it is disabled.
 */
final readonly class RolDto
{
    /**
     * @param  list<string>  $permisos
     */
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        public array $permisos,
        public ?CarbonImmutable $bajaDesde = null,
    ) {}
}
