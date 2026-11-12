<?php

namespace App\Domains\Clientes\Features\CambiarRedirecciones;

use App\Domains\Clientes\Domain\Redireccion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of PUT /api/iam/v1/clientes/{id}/redirecciones: the whole list, at least one, each a Redireccion. The
 * permission is checked by the route (can:iam:clientes.administrar).
 */
final class CambiarRedireccionesRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'redirecciones' => ['required', 'array', 'list', 'min:1'],
            'redirecciones.*' => ['required', 'string', 'max:2000', new Redireccion],
        ];
    }

    /**
     * The validated redirections, in the order received.
     *
     * @return list<string>
     */
    public function redirecciones(): array
    {
        return array_values(array_map('strval', (array) $this->validated('redirecciones')));
    }
}
