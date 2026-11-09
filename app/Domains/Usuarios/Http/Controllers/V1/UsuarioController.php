<?php

namespace App\Domains\Usuarios\Http\Controllers\V1;

use App\Domains\Usuarios\Application\UsuarioService;
use App\Domains\Usuarios\Http\Requests\V1\ListarUsuariosRequest;
use App\Domains\Usuarios\Http\Resources\V1\UsuarioDetalleResource;
use App\Domains\Usuarios\Http\Resources\V1\UsuarioResource;
use Illuminate\Http\JsonResponse;

/**
 * The people of IAM for the portal (spec usuarios). Only translates HTTP: the rules live in UsuarioService.
 */
final class UsuarioController
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    /**
     * {data, meta: {total, pagina, por_pagina}}: the paginator's own JSON (current_page, links...) is not
     * the published contract (design D5).
     */
    public function index(ListarUsuariosRequest $request): JsonResponse
    {
        $pagina = $this->usuarios->listar($request->filtro());

        return UsuarioResource::collection($pagina->items())
            ->additional(['meta' => [
                'total' => $pagina->total(),
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
            ]])
            ->response();
    }

    public function show(string $uuid): UsuarioDetalleResource
    {
        return new UsuarioDetalleResource($this->usuarios->obtener($uuid));
    }
}
