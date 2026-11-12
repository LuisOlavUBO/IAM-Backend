<?php

namespace App\Domains\Aplicaciones\Features\EditarRol;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Domains\Aplicaciones\Domain\RolNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: change the description of a role (spec aplicaciones, design D6 of aplicaciones-catalogo). The same
 * description changes nothing and is not audited; the roles of iam-admin are refused and the attempt audited.
 */
final class EditarRolCommandHandler
{
    private const ACCION = 'rol.edicion';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $aplicacion, string $nombre, ?string $descripcion): RolParaEscribirDto
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;
        $rol = $this->catalogo->rol($app->id, $nombre) ?? throw new RolNoEncontradoException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($rol->aplicacion);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'rol', (string) $rol->id, $rechazo->code());

            throw $rechazo;
        }

        if ($rol->descripcion === $descripcion) {
            return $rol;
        }

        DB::transaction(function () use ($rol, $descripcion) {
            $this->catalogo->describirRol($rol->id, $descripcion);
            $this->auditoria->registrar(self::ACCION, 'rol', (string) $rol->id, ['descripcion' => $rol->descripcion], ['descripcion' => $descripcion]);
        });

        return new RolParaEscribirDto($rol->id, $rol->aplicacion, $rol->nombre, $descripcion);
    }
}
