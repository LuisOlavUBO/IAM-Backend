<?php

namespace App\Domains\Aplicaciones\Features\CrearRol;

use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/iam/v1/aplicaciones/{nombre}/roles. The permission is checked by the route
 * (can:iam:aplicaciones.administrar).
 */
final class CrearRolRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.ReglasDeCatalogo::LARGO_MAXIMO, 'regex:'.ReglasDeCatalogo::FORMATO_ROL],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nombre.regex' => 'El nombre lleva letras, dígitos, espacios o . _ -, y empieza con letra o dígito (por ejemplo, mesa-de-ayuda).'];
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
