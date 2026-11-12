<?php

namespace App\Domains\Aplicaciones\Features\EditarRol;

/**
 * PATCH /api/iam/v1/aplicaciones/{nombre}/roles/{rol}. Only translates HTTP: 200 with the role, whether the
 * description changed or not (design D1 of aplicaciones-catalogo).
 */
final class EditarRolEndpoint
{
    public function __invoke(string $nombre, string $rol, EditarRolRequest $request, EditarRolCommandHandler $editar): EditarRolResource
    {
        return new EditarRolResource($editar($nombre, $rol, $request->descripcion()));
    }
}
