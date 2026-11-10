<?php

namespace App\Domains\Clientes\Domain\Dto;

/**
 * A role assigned to a client, with the application it belongs to (it may differ from the client's).
 */
final readonly class RolClienteDto
{
    public function __construct(
        public string $aplicacion,
        public string $nombre,
        public ?string $descripcion,
    ) {}
}
