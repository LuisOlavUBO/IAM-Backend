<?php

namespace App\Domains\Usuarios\Features\CrearUsuario;

use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Shared\Rut\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Body of POST /api/iam/v1/usuarios (design D3): normalizes email, RUT and name before validating. The
 * permission is checked by the route (can:iam:usuarios.administrar).
 */
final class CrearUsuarioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $rut = $this->input('rut');

        $this->merge([
            'nombre' => is_string($this->input('nombre')) ? trim($this->input('nombre')) : $this->input('nombre'),
            'correo' => is_string($this->input('correo')) ? Str::lower(trim($this->input('correo'))) : $this->input('correo'),
            'rut' => is_string($rut) ? (trim($rut) === '' ? null : Rut::normalize($rut)) : $rut,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'string', 'email', 'max:255'],
            'rut' => ['nullable', 'string', new Rut],
            'acceso' => ['required', 'string', 'in:'.NuevaPersonaDto::ACCESO_MICROSOFT.','.NuevaPersonaDto::ACCESO_CLAVE],
        ];
    }

    /** The validated data, already normalized. */
    public function persona(): NuevaPersonaDto
    {
        $rut = $this->validated('rut');

        return new NuevaPersonaDto(
            (string) $this->validated('nombre'),
            (string) $this->validated('correo'),
            $rut === null ? null : (string) $rut,
            (string) $this->validated('acceso'),
        );
    }
}
