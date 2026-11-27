<?php

namespace App\Domains\Clientes\Dto;

/**
 * An OAuth client in the list: its application, its grant types, whether it is revoked and how many
 * roles it has (spec clientes).
 */
final readonly class ClienteResumenDto
{
    /**
     * @param  list<string>  $concesiones
     */
    public function __construct(
        public string $id,
        public string $nombre,
        public string $aplicacion,
        public array $concesiones,
        public bool $revocado,
        public int $roles,
    ) {}
}
