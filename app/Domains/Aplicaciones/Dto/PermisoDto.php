<?php

namespace App\Domains\Aplicaciones\Dto;

/**
 * A permission (oauth_scopes row) of an application.
 */
final readonly class PermisoDto
{
    public function __construct(
        public string $nombre,
        public string $descripcion,
    ) {}
}
