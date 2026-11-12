<?php

namespace App\Domains\Aplicaciones\Features\EditarAplicacion;

/**
 * PATCH /api/iam/v1/aplicaciones/{nombre}. Only translates HTTP: 200 with the application, whether the
 * description changed or not (design D1 of aplicaciones-catalogo).
 */
final class EditarAplicacionEndpoint
{
    public function __invoke(string $nombre, EditarAplicacionRequest $request, EditarAplicacionCommandHandler $editar): EditarAplicacionResource
    {
        return new EditarAplicacionResource($editar($nombre, $request->descripcion()));
    }
}
