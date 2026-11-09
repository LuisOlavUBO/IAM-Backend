<?php

namespace App\Domains\Usuarios\Infrastructure\Eloquent;

use App\Domains\Usuarios\Application\FiltroUsuarios;
use App\Domains\Usuarios\Application\RolAsignado;
use App\Domains\Usuarios\Contracts\IUsuarioRepository;
use App\Domains\Usuarios\Models\Usuario;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentUsuarioRepository implements IUsuarioRepository
{
    public function listar(FiltroUsuarios $filtro): LengthAwarePaginator
    {
        return Usuario::query()
            ->when($filtro->estado === 'activos', fn (Builder $q) => $q->whereNull('disabled_at'))
            ->when($filtro->estado === 'baja', fn (Builder $q) => $q->whereNotNull('disabled_at'))
            ->when($filtro->buscar !== '', fn (Builder $q) => $this->buscar($q, $filtro))
            ->orderBy('name')
            ->orderBy('uuid')
            ->paginate(perPage: $filtro->porPagina, page: $filtro->pagina);
    }

    public function buscarPorUuid(string $uuid): ?Usuario
    {
        return Usuario::query()->where('uuid', $uuid)->first();
    }

    public function rolesDe(Usuario $usuario): array
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('role_user.user_id', $usuario->id)
            ->orderBy('applications.name')
            ->orderBy('roles.name')
            ->get([
                'applications.name as aplicacion', 'applications.description as aplicacion_descripcion',
                'roles.name as rol', 'roles.description as rol_descripcion',
            ])
            ->map(fn (object $fila) => new RolAsignado(
                $fila->aplicacion,
                $fila->aplicacion_descripcion,
                $fila->rol,
                $fila->rol_descripcion,
            ))
            ->all();
    }

    /**
     * Name or email containing the text (the CI collation ignores case), or the exact RUT the
     * service found in it. LIKE wildcards of SQL Server are escaped (design D4).
     */
    private function buscar(Builder $consulta, FiltroUsuarios $filtro): void
    {
        $patron = '%'.str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $filtro->buscar).'%';

        $consulta->where(function (Builder $condicion) use ($patron, $filtro) {
            $condicion->whereRaw("name LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$patron])
                ->when($filtro->rut !== null, fn (Builder $q) => $q->orWhere('rut', $filtro->rut));
        });
    }
}
