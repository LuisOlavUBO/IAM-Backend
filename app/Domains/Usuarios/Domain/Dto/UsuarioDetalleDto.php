<?php

namespace App\Domains\Usuarios\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * One person with their account data and roles (spec usuarios). The credentials only reach it as
 * booleans: the query never returns them.
 */
final readonly class UsuarioDetalleDto
{
    /**
     * @param  list<RolAsignadoDto>  $roles  Ordered by application name and role name
     */
    public function __construct(
        public string $uuid,
        public string $nombre,
        public string $correo,
        public ?string $rut,
        public ?CarbonImmutable $bajaDesde,
        public bool $tieneClave,
        public bool $microsoftVinculado,
        public ?CarbonImmutable $claveTemporalVence,
        public ?CarbonImmutable $creado,
        public ?CarbonImmutable $actualizado,
        public array $roles,
    ) {}
}
