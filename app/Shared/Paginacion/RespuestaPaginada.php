<?php

namespace App\Shared\Paginacion;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The response of every list: {data, meta: {total, pagina, por_pagina}}, the MetaPaginacion schema of
 * the OpenAPI.
 */
final class RespuestaPaginada
{
    /**
     * @param  Pagina<mixed>  $pagina
     * @param  class-string<JsonResource>  $resource  Formats each item of the page
     */
    public static function de(Pagina $pagina, string $resource): JsonResponse
    {
        return $resource::collection($pagina->items)
            ->additional(['meta' => [
                'total' => $pagina->total,
                'pagina' => $pagina->pagina,
                'por_pagina' => $pagina->porPagina,
            ]])
            ->response();
    }
}
