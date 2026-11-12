<?php

namespace App\Domains\Aplicaciones\Features\CrearPermiso;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\PermisoExistenteException;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: create a permission (an OAuth scope) of an application (spec aplicaciones, "Crear y describir
 * permisos"). Its name is unique in all of IAM. Nothing is created in iam-admin: the attempt is audited
 * against the application. The permission and its audit entry are one transaction.
 */
final class CrearPermisoCommandHandler
{
    private const ACCION = 'permiso.creacion';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    /** @param  string  $nombre  Already validated against ReglasDeCatalogo::FORMATO_PERMISO */
    public function __invoke(string $aplicacion, string $nombre, string $descripcion): PermisoParaEscribirDto
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($app->nombre);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'aplicacion', (string) $app->id, $rechazo->code());

            throw $rechazo;
        }

        if ($this->catalogo->permiso($nombre) !== null) {
            throw new PermisoExistenteException;
        }

        return DB::transaction(function () use ($app, $nombre, $descripcion) {
            $id = $this->catalogo->crearPermiso($app->id, $nombre, $descripcion);
            $this->auditoria->registrar(self::ACCION, 'permiso', (string) $id, null, ['nombre' => $nombre, 'aplicacion' => $app->nombre, 'descripcion' => $descripcion]);

            return new PermisoParaEscribirDto($id, $app->nombre, $nombre, $descripcion);
        });
    }
}
