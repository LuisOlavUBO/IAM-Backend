<?php

namespace App\Domains\Clientes\Features\RotarSecreto;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/iam/v1/clientes/{id}/secreto (design D1): an optional confirmar, the client's name,
 * that the clients of iam-admin require. The permission is checked by the route
 * (can:iam:clientes.administrar).
 */
final class RotarSecretoRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'confirmar' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function confirmar(): ?string
    {
        $confirmar = $this->validated('confirmar');

        return $confirmar === null ? null : (string) $confirmar;
    }
}
