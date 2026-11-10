<?php

namespace App\Domains\Clientes\Features\ListarClientes;

use App\Domains\Clientes\Domain\Dto\FiltroClientesDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query of GET /api/iam/v1/clientes. The permission is checked by the route (can:iam:clientes.leer).
 */
final class ListarClientesRequest extends FormRequest
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

    public function filtro(): FiltroClientesDto
    {
        return new FiltroClientesDto(
            buscar: trim((string) $this->validated('buscar', '')),
            pagina: (int) $this->validated('pagina', 1),
            porPagina: (int) $this->validated('por_pagina', FiltroClientesDto::POR_PAGINA),
        );
    }
}
