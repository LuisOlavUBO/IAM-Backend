<?php

namespace App\Domains\Auditoria\Features\ListarAuditoria;

use App\Domains\Auditoria\Domain\Dto\FiltroAuditoriaDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query of GET /api/iam/v1/auditoria (design D6). The permission is checked by the route
 * (can:iam:auditoria.leer).
 */
final class ListarAuditoriaRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'desde' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'actor' => ['sometimes', 'nullable', 'uuid'],
            'accion' => ['sometimes', 'nullable', 'string', 'max:100'],
            'entidad_tipo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'entidad_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'resultado' => ['sometimes', 'nullable', 'in:ok,rechazado'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filtro(): FiltroAuditoriaDto
    {
        return new FiltroAuditoriaDto(
            desde: $this->texto('desde'),
            hasta: $this->texto('hasta'),
            actor: $this->texto('actor'),
            accion: $this->texto('accion'),
            entidadTipo: $this->texto('entidad_tipo'),
            entidadId: $this->texto('entidad_id'),
            resultado: $this->texto('resultado'),
            pagina: (int) $this->validated('pagina', 1),
            porPagina: (int) $this->validated('por_pagina', FiltroAuditoriaDto::POR_PAGINA),
        );
    }

    /** A validated text filter without surrounding spaces, or null when absent or empty. */
    private function texto(string $campo): ?string
    {
        $valor = trim((string) $this->validated($campo, ''));

        return $valor === '' ? null : $valor;
    }
}
