<?php

namespace Tests\Unit;

use App\Shared\Paginacion\Pagina;
use App\Shared\Paginacion\RespuestaPaginada;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Tests\TestCase;

/**
 * Spec arquitectura-de-dominios: the paginated response of every list.
 */
class RespuestaPaginadaTest extends TestCase
{
    public function test_it_builds_data_and_meta_from_a_page_and_a_resource(): void
    {
        $pagina = new Pagina([['n' => 'Ana'], ['n' => 'Juan']], total: 7, pagina: 2, porPagina: 2);

        $json = RespuestaPaginada::de($pagina, NombreResource::class)->getData(true);

        $this->assertSame([
            'data' => [['nombre' => 'Ana'], ['nombre' => 'Juan']],
            'meta' => ['total' => 7, 'pagina' => 2, 'por_pagina' => 2],
        ], $json);
    }

    public function test_an_empty_page_keeps_its_meta(): void
    {
        $json = RespuestaPaginada::de(new Pagina([], 0, 1, 20), NombreResource::class)->getData(true);

        $this->assertSame(['data' => [], 'meta' => ['total' => 0, 'pagina' => 1, 'por_pagina' => 20]], $json);
    }
}

/**
 * @property array{n: string} $resource
 */
class NombreResource extends JsonResource
{
    /**
     * @return array{nombre: string}
     */
    public function toArray(Request $request): array
    {
        return ['nombre' => $this->resource['n']];
    }
}
