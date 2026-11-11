<?php

namespace App\Domains\Usuarios\Domain\Dto;

/**
 * What a write needs of a person: their internal id (it stays inside Usuarios) and whether they are active
 * (no disabled_at).
 */
final readonly class EstadoPersonaDto
{
    public function __construct(
        public int $id,
        public bool $activa,
    ) {}
}
