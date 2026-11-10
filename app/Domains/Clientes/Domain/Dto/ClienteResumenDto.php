<?php

namespace App\Domains\Clientes\Domain\Dto;

/**
 * An OAuth client in the list: its application, whether it is revoked and how many roles it has
 * (spec clientes).
 */
final readonly class ClienteResumenDto
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $aplicacion,
        public bool $revocado,
        public int $roles,
    ) {}
}
