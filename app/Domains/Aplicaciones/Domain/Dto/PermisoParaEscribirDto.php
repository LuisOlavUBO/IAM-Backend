<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

/**
 * A permission (oauth_scopes) found for a write, with the name of its application. Its id is internal.
 */
final readonly class PermisoParaEscribirDto
{
    public function __construct(
        public int $id,
        public string $aplicacion,
        public string $nombre,
        public string $descripcion,
    ) {}
}
