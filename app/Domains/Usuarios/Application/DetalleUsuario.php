<?php

namespace App\Domains\Usuarios\Application;

use App\Domains\Usuarios\Models\Usuario;

/**
 * A person with their roles, ordered by application and role name.
 */
final readonly class DetalleUsuario
{
    /**
     * @param  list<RolAsignado>  $roles
     */
    public function __construct(
        public Usuario $usuario,
        public array $roles,
    ) {}
}
