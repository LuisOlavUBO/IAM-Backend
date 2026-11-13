<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * Block `entorno` (spec indicadores): the size of the catalog, the roles nobody holds (no person and
 * no client) and the five roles with most active people.
 */
final readonly class EntornoDto
{
    /**
     * @param  list<RolConPersonasDto>  $rolesConMasPersonas
     */
    public function __construct(
        public int $aplicaciones,
        public int $roles,
        public int $permisos,
        public int $rolesSinAsignar,
        public array $rolesConMasPersonas,
    ) {}
}
