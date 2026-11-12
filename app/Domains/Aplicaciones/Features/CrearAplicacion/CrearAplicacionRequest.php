<?php

namespace App\Domains\Aplicaciones\Features\CrearAplicacion;

use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/iam/v1/aplicaciones. The permission is checked by the route
 * (can:iam:aplicaciones.administrar).
 */
final class CrearAplicacionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.ReglasDeCatalogo::LARGO_MAXIMO, 'regex:'.ReglasDeCatalogo::FORMATO_APLICACION],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nombre.regex' => 'El nombre va en minúsculas, con dígitos y guiones (por ejemplo, pago-honorarios).'];
    }

    public function nombre(): string
    {
        return (string) $this->validated('nombre');
    }

    public function descripcion(): ?string
    {
        $descripcion = $this->validated('descripcion');

        return $descripcion === null ? null : (string) $descripcion;
    }
}
