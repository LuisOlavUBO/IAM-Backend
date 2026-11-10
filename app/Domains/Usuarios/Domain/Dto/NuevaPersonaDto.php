<?php

namespace App\Domains\Usuarios\Domain\Dto;

/**
 * The data of a new person, already normalized: email in lowercase, RUT as 12345678-K (design D3).
 * $acceso is "microsoft" or "clave".
 */
final readonly class NuevaPersonaDto
{
    public const ACCESO_MICROSOFT = 'microsoft';

    public const ACCESO_CLAVE = 'clave';

    public function __construct(
        public string $nombre,
        public string $correo,
        public ?string $rut,
        public string $acceso,
    ) {}
}
