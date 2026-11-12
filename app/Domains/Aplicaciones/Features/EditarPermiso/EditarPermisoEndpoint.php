<?php

namespace App\Domains\Aplicaciones\Features\EditarPermiso;

/**
 * PATCH /api/iam/v1/aplicaciones/{nombre}/permisos/{permiso}. Only translates HTTP: 200 with the permission,
 * whether the description changed or not (design D1 of aplicaciones-catalogo).
 */
final class EditarPermisoEndpoint
{
    public function __invoke(string $nombre, string $permiso, EditarPermisoRequest $request, EditarPermisoCommandHandler $editar): EditarPermisoResource
    {
        return new EditarPermisoResource($editar($nombre, $permiso, $request->descripcion()));
    }
}
