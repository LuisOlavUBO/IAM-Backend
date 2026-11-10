<?php

namespace App\Domains\Aplicaciones\Features\ListarAplicaciones;

use App\Domains\Aplicaciones\Domain\Dto\FiltroAplicacionesDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query of GET /api/iam/v1/aplicaciones. The permission is checked by the route (can:iam:aplicaciones.leer).
 */
final class ListarAplicacionesRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filtro(): FiltroAplicacionesDto
    {
        return new FiltroAplicacionesDto(
            buscar: trim((string) $this->validated('buscar', '')),
            pagina: (int) $this->validated('pagina', 1),
            porPagina: (int) $this->validated('por_pagina', FiltroAplicacionesDto::POR_PAGINA),
        );
    }
}
