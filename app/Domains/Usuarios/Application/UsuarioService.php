<?php

namespace App\Domains\Usuarios\Application;

use App\Domains\Usuarios\Contracts\IUsuarioRepository;
use App\Domains\Usuarios\Exceptions\UsuarioNoEncontrado;
use App\Domains\Usuarios\Models\Usuario;
use App\Rules\Rut;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Queries of the people of IAM for the portal (spec usuarios). Depends on the repository contract,
 * not on Eloquent (design D1).
 */
final class UsuarioService
{
    public function __construct(private readonly IUsuarioRepository $usuarios) {}

    /**
     * @return LengthAwarePaginator<int, Usuario>
     */
    public function listar(FiltroUsuarios $filtro): LengthAwarePaginator
    {
        return $this->usuarios->listar($filtro->conRut(self::rutBuscado($filtro->buscar)));
    }

    /** The person by uuid (case-insensitive) with their roles. */
    public function obtener(string $uuid): DetalleUsuario
    {
        // A value that is not a UUID would not convert to uniqueidentifier in SQL Server (design D6).
        if (! Str::isUuid($uuid)) {
            throw new UsuarioNoEncontrado;
        }

        $usuario = $this->usuarios->buscarPorUuid($uuid) ?? throw new UsuarioNoEncontrado;

        return new DetalleUsuario($usuario, $this->usuarios->rolesDe($usuario));
    }

    /** The searched text as a stored RUT, when it is a valid one with or without dots (design D4). */
    private static function rutBuscado(string $texto): ?string
    {
        if ($texto === '') {
            return null;
        }

        $rut = Rut::normalize($texto);

        return Validator::make(['rut' => $rut], ['rut' => [new Rut]])->passes() ? $rut : null;
    }
}
