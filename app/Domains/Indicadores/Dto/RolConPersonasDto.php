<?php

namespace App\Domains\Indicadores\Dto;

/**
 * A role and how many active people hold it.
 */
final readonly class RolConPersonasDto
{
    public function __construct(
        public string $aplicacion,
        public string $rol,
        public int $personas,
    ) {}
}
