<?php

namespace App\Domains\Usuarios\Domain\Dto;

/**
 * A role a person has, with its application. A read object of Usuarios, so this domain does not
 * depend on the models of Catalogo.
 */
final readonly class RolAsignadoDto
{
    public function __construct(
        public string $aplicacion,
        public ?string $aplicacionDescripcion,
        public string $rol,
        public ?string $rolDescripcion,
    ) {}
}
