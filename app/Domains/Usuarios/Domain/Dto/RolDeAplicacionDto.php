<?php

namespace App\Domains\Usuarios\Domain\Dto;

/**
 * A role found for a write. Its id is internal: it stays inside Usuarios and never reaches a response.
 */
final readonly class RolDeAplicacionDto
{
    public function __construct(
        public int $id,
        public string $aplicacion,
        public string $nombre,
    ) {}

    /** "application/role", as the audit log and the portal write it. */
    public function nombreCompleto(): string
    {
        return "{$this->aplicacion}/{$this->nombre}";
    }
}
