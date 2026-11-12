<?php

namespace App\Domains\Aplicaciones\Features\EditarPermiso;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\PermisoNoEncontradoException;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: change the description of a permission of an application (spec aplicaciones, design D6 of
 * aplicaciones-catalogo). A permission of another application is not found here. The same description changes
 * nothing and is not audited; the permissions of iam-admin are refused and the attempt audited.
 */
final class EditarPermisoCommandHandler
{
    private const ACCION = 'permiso.edicion';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $aplicacion, string $nombre, string $descripcion): PermisoParaEscribirDto
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;
        $permiso = $this->catalogo->permiso($nombre);
        if ($permiso === null || strcasecmp($permiso->aplicacion, $app->nombre) !== 0) {
            throw new PermisoNoEncontradoException;
        }

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($app->nombre);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'permiso', (string) $permiso->id, $rechazo->code());

            throw $rechazo;
        }

        if ($permiso->descripcion === $descripcion) {
            return $permiso;
        }

        DB::transaction(function () use ($permiso, $descripcion) {
            $this->catalogo->describirPermiso($permiso->id, $descripcion);
            $this->auditoria->registrar(self::ACCION, 'permiso', (string) $permiso->id, ['descripcion' => $permiso->descripcion], ['descripcion' => $descripcion]);
        });

        return new PermisoParaEscribirDto($permiso->id, $permiso->aplicacion, $permiso->nombre, $descripcion);
    }
}
