<?php

namespace App\Domains\Aplicaciones\Features\CrearPermiso;

use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/iam/v1/aplicaciones/{nombre}/permisos: the description is required, as in oauth_scopes.
 * The permission is checked by the route (can:iam:aplicaciones.administrar).
 */
final class CrearPermisoRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.ReglasDeCatalogo::LARGO_MAXIMO, 'regex:'.ReglasDeCatalogo::FORMATO_PERMISO],
            'descripcion' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nombre.regex' => 'El nombre va en minúsculas, con dígitos y : . _ - (por ejemplo, biblioteca:prestamos.leer).'];
    }

    public function nombre(): string
    {
        return (string) $this->validated('nombre');
    }

    public function descripcion(): string
    {
        return (string) $this->validated('descripcion');
    }
}
