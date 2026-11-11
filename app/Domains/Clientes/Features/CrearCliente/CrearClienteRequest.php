<?php

namespace App\Domains\Clientes\Features\CrearCliente;

use App\Domains\Clientes\Domain\NuevoCliente;
use App\Domains\Clientes\Domain\Redireccion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/iam/v1/clientes (design D4 of clientes-crear). The application is resolved by the
 * handler; the permission is checked by the route (can:iam:clientes.administrar).
 */
final class CrearClienteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->input('nombre')) ? trim($this->input('nombre')) : $this->input('nombre'),
            'aplicacion' => is_string($this->input('aplicacion')) ? trim($this->input('aplicacion')) : $this->input('aplicacion'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'aplicacion' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'in:'.implode(',', NuevoCliente::TIPOS)],
            'redirecciones' => [
                'required_if:tipo,'.NuevoCliente::AUTHORIZATION_CODE,
                'prohibited_if:tipo,'.NuevoCliente::CLIENT_CREDENTIALS,
                'nullable',
                'array',
                'list',
            ],
            'redirecciones.*' => ['required', 'string', 'max:2000', new Redireccion],
        ];
    }

    /** The validated data. */
    public function cliente(): DatosNuevoClienteDto
    {
        return new DatosNuevoClienteDto(
            (string) $this->validated('nombre'),
            (string) $this->validated('aplicacion'),
            (string) $this->validated('tipo'),
            array_values(array_map('strval', (array) $this->validated('redirecciones', []))),
        );
    }
}
