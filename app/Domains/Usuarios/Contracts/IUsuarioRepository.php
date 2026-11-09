<?php

namespace App\Domains\Usuarios\Contracts;

use App\Domains\Usuarios\Application\FiltroUsuarios;
use App\Domains\Usuarios\Application\RolAsignado;
use App\Domains\Usuarios\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read access to the people of IAM. Nothing here writes to the database.
 */
interface IUsuarioRepository
{
    /**
     * People that match the filter, ordered by name (and uuid to break ties).
     *
     * @return LengthAwarePaginator<int, Usuario>
     */
    public function listar(FiltroUsuarios $filtro): LengthAwarePaginator;

    /** By public uuid (already checked to have the UUID format), in any state. */
    public function buscarPorUuid(string $uuid): ?Usuario;

    /**
     * The person's roles, ordered by application name and role name.
     *
     * @return list<RolAsignado>
     */
    public function rolesDe(Usuario $usuario): array;
}
