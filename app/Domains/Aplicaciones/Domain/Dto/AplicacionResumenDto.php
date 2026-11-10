<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

/**
 * An application in the list, with how many permissions and roles it defines (spec aplicaciones).
 */
final readonly class AplicacionResumenDto
{
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        public int $permisos,
        public int $roles,
    ) {}
}
