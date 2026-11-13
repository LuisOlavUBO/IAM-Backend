<?php

namespace App\Domains\Aplicaciones\Features\BorrarPermiso;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\EnUsoException;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\PermisoNoEncontradoException;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: delete a permission created by mistake (spec aplicaciones, "Borrar lo que no está en uso"; design D3
 * of aplicaciones-renombrar-y-borrar). While some role grants it, it is in use and nothing is deleted. The
 * permissions of iam-admin are refused and the attempt audited.
 */
final class BorrarPermisoCommandHandler
{
    private const ACCION = 'permiso.borrado';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $aplicacion, string $nombre): void
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

        $impacto = $this->catalogo->impactoDePermiso($permiso->id);
        if ($impacto->enUso) {
            throw new EnUsoException($impacto);
        }

        DB::transaction(function () use ($permiso) {
            $this->catalogo->borrarPermiso($permiso->id);
            $this->auditoria->registrar(self::ACCION, 'permiso', (string) $permiso->id, ['nombre' => $permiso->nombre, 'descripcion' => $permiso->descripcion]);
        });
    }
}
