<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

/**
 * A role of an application with the names of the permissions it grants, ordered.
 */
final readonly class RolDto
{
    /**
     * @param  list<string>  $permisos
     */
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        public array $permisos,
    ) {}
}
