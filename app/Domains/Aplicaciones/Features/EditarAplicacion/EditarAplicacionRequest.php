<?php

namespace App\Domains\Aplicaciones\Features\EditarAplicacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of PATCH /api/iam/v1/aplicaciones/{nombre}: only the description, which may be empty (null). The name
 * is not changed here. The permission is checked by the route (can:iam:aplicaciones.administrar).
 */
final class EditarAplicacionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }

    public function descripcion(): ?string
    {
        $descripcion = $this->validated('descripcion');

        return $descripcion === null ? null : (string) $descripcion;
    }
}
