<?php

namespace App\Domains\Usuarios\Domain\Dto;

/**
 * A person in the list: only the public columns (spec usuarios).
 */
final readonly class UsuarioResumenDto
{
    public function __construct(
        public string $uuid,
        public string $nombre,
        public string $correo,
        public ?string $rut,
        public bool $deBaja,
    ) {}
}
