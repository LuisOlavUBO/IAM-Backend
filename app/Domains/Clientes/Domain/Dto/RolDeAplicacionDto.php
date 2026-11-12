<?php

namespace App\Domains\Clientes\Domain\Dto;

/**
 * A role found for a write on a client. Its id is internal: it never reaches a response.
 */
final readonly class RolDeAplicacionDto
{
    public function __construct(
        public int $id,
        public string $aplicacion,
        public string $nombre,
    ) {}

    /** "application/role", as the audit log and iam:install write it. */
    public function nombreCompleto(): string
    {
        return "{$this->aplicacion}/{$this->nombre}";
    }
}
