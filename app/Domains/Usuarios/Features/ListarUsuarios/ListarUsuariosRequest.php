<?php

namespace App\Domains\Usuarios\Features\ListarUsuarios;

use App\Domains\Usuarios\Domain\Dto\FiltroUsuariosDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query of GET /api/iam/v1/usuarios. The permission is checked by the route (can:iam:usuarios.leer).
 */
final class ListarUsuariosRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'estado' => ['sometimes', 'in:activos,baja,todos'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filtro(): FiltroUsuariosDto
    {
        return new FiltroUsuariosDto(
            buscar: trim((string) $this->validated('buscar', '')),
            estado: $this->validated('estado', 'todos'),
            pagina: (int) $this->validated('pagina', 1),
            porPagina: (int) $this->validated('por_pagina', FiltroUsuariosDto::POR_PAGINA),
        );
    }
}
