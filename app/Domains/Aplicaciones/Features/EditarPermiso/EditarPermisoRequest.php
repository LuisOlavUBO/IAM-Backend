<?php

namespace App\Domains\Aplicaciones\Features\EditarPermiso;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of PATCH /api/iam/v1/aplicaciones/{nombre}/permisos/{permiso}: only the description, required as in
 * oauth_scopes. The permission is checked by the route (can:iam:aplicaciones.administrar).
 */
final class EditarPermisoRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:255'],
        ];
    }

    public function descripcion(): string
    {
        return (string) $this->validated('descripcion');
    }
}
