<?php

namespace App\Domains\Clientes\Features\AsignarRolACliente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/iam/v1/clientes/{id}/roles. The permission is checked by the route
 * (can:iam:clientes.administrar).
 */
final class AsignarRolAClienteRequest extends FormRequest
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
