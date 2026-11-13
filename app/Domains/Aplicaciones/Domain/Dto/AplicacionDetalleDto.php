<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * One application with its permissions and roles, both ordered by name (spec aplicaciones).
 */
final readonly class AplicacionDetalleDto
{
    /**
     * @param  list<PermisoDto>  $permisos
     * @param  list<RolDto>  $roles
     */
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        public ?CarbonImmutable $creada,
        public ?CarbonImmutable $actualizada,
        public array $permisos,
        public array $roles,
        public ?CarbonImmutable $bajaDesde = null,
    ) {}
}
