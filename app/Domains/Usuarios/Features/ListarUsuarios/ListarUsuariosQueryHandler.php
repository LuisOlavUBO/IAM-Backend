<?php

namespace App\Domains\Usuarios\Features\ListarUsuarios;

use App\Domains\Usuarios\Domain\Dto\FiltroUsuariosDto;
use App\Domains\Usuarios\Domain\Dto\UsuarioResumenDto;
use App\Domains\Usuarios\Domain\Interfaces\IUsuarioQueryRepository;
use App\Shared\Paginacion\Pagina;
use App\Shared\Rut\Rut;

/**
 * Use case: list the people of IAM (spec usuarios). A searched text that is a valid RUT, with or
 * without dots, also matches that RUT.
 */
final class ListarUsuariosQueryHandler
{
    public function __construct(private readonly IUsuarioQueryRepository $usuarios) {}

    /**
     * @return Pagina<UsuarioResumenDto>
     */
    public function __invoke(FiltroUsuariosDto $filtro): Pagina
    {
        return $this->usuarios->listar($filtro->conRut(self::rutBuscado($filtro->buscar)));
    }

    private static function rutBuscado(string $texto): ?string
    {
        if ($texto === '') {
            return null;
        }

        $rut = Rut::normalize($texto);

        return Rut::esValido($rut) ? $rut : null;
    }
}
