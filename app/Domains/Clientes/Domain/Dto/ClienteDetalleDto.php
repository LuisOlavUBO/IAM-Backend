<?php

namespace App\Domains\Clientes\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * One OAuth client with its grant types, redirections and roles (spec clientes). It never carries the
 * secret: only whether it has one.
 */
final readonly class ClienteDetalleDto
{
    /**
     * @param  list<string>  $concesiones
     * @param  list<string>  $redirecciones
     * @param  list<RolClienteDto>  $roles
     */
    public function __construct(
        public string $id,
        public string $nombre,
        public string $aplicacion,
        public array $concesiones,
        public bool $confidencial,
        public array $redirecciones,
        public bool $revocado,
        public ?CarbonImmutable $creado,
        public ?CarbonImmutable $actualizado,
        public array $roles,
    ) {}
}
