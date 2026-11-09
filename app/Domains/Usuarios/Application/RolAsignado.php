<?php

namespace App\Domains\Usuarios\Application;

/**
 * A role a person has, with its application. A read object of Usuarios, so this domain does not
 * depend on the models of Catalogo (design D2).
 */
final readonly class RolAsignado
{
    public function __construct(
        public string $aplicacion,
        public ?string $aplicacionDescripcion,
        public string $rol,
        public ?string $rolDescripcion,
    ) {}
}
