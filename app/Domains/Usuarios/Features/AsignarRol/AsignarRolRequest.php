<?php

namespace App\Domains\Usuarios\Features\AsignarRol;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/iam/v1/usuarios/{uuid}/roles. The permission is checked by the route
 * (can:iam:usuarios.administrar).
 */
final class AsignarRolRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'aplicacion' => ['required', 'string', 'max:100'],
            'rol' => ['required', 'string', 'max:100'],
        ];
    }
}
