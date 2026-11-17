<?php

namespace App\Domains\Usuarios\Features\CrearUsuario;

use App\Domains\Usuarios\Rules\ClaveTemporal;

/**
 * The person just created: their uuid and, with access by password, the temporary password that is shown
 * once and never logged or audited.
 */
final readonly class PersonaCreadaDto
{
    public function __construct(
        public string $uuid,
        public ?ClaveTemporal $claveTemporal,
    ) {}
}
