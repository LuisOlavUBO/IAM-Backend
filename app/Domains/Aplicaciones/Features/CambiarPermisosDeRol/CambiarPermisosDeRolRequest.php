<?php

namespace App\Domains\Aplicaciones\Features\CambiarPermisosDeRol;

use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of PUT /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/permisos: the whole list of permission names, of
 * any application; an empty list leaves the role without permissions. The permission is checked by the route
 * (can:iam:aplicaciones.administrar).
 */
final class CambiarPermisosDeRolRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'permisos' => ['present', 'array', 'list', 'max:500'],
            'permisos.*' => ['required', 'string', 'max:'.ReglasDeCatalogo::LARGO_MAXIMO],
        ];
    }

    /**
     * The validated names, without repetitions.
     *
     * @return list<string>
     */
    public function permisos(): array
    {
        return array_values(array_unique(array_map('strval', (array) $this->validated('permisos'))));
    }
}
