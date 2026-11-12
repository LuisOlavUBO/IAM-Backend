<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

/**
 * A role found for a write, with the name of its application. Its id is internal.
 */
final readonly class RolParaEscribirDto
{
    public function __construct(
        public int $id,
        public string $aplicacion,
        public string $nombre,
        public ?string $descripcion,
    ) {}

    /** "application/role", as the audit log and iam:install write it. */
    public function nombreCompleto(): string
    {
        return "{$this->aplicacion}/{$this->nombre}";
    }
}
