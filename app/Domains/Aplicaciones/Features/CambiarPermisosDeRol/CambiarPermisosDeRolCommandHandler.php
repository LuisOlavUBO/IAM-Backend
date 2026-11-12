<?php

namespace App\Domains\Aplicaciones\Features\CambiarPermisosDeRol;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Domains\Aplicaciones\Domain\RolNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: replace the permissions a role grants, of any application (spec aplicaciones, "Permisos que da un
 * rol"; design D5 of aplicaciones-catalogo). Each permission added and each removed is one audit entry, the
 * granularity of iam:install, in the same transaction as the change. The same list changes nothing and is not
 * audited. The roles of iam-admin and the permissions of iam-admin are refused and the attempt audited.
 * API-Auth applies the change from the next token of those who have the role.
 */
final class CambiarPermisosDeRolCommandHandler
{
    private const ASIGNACION = 'rol.permiso.asignacion';

    private const RETIRO = 'rol.permiso.retiro';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    /**
     * @param  list<string>  $nombres  The names of the permissions the role must grant
     * @return list<string> The names of the permissions the role grants after the change, ordered
     */
    public function __invoke(string $aplicacion, string $nombreRol, array $nombres): array
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;
        $rol = $this->catalogo->rol($app->id, $nombreRol) ?? throw new RolNoEncontradoException;

        $nuevos = $this->resolver($nombres);
        $actuales = $this->catalogo->permisosDeRol($rol->id);
        $agregados = self::diferencia($nuevos, $actuales);
        $quitados = self::diferencia($actuales, $nuevos);

        $this->verificar($rol, $agregados === [] && $quitados !== [] ? self::RETIRO : self::ASIGNACION, function () use ($rol, $agregados) {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($rol->aplicacion);
            ReglasDeCatalogo::verificarPermisosAsignables($agregados);
        });

        if ($agregados !== [] || $quitados !== []) {
            DB::transaction(function () use ($rol, $agregados, $quitados) {
                foreach ($agregados as $permiso) {
                    $this->catalogo->asignarPermiso($rol->id, $permiso->id);
                    $this->auditoria->registrar(self::ASIGNACION, 'rol', (string) $rol->id, null, ['permiso' => $permiso->nombre]);
                }
                foreach ($quitados as $permiso) {
                    $this->catalogo->quitarPermiso($rol->id, $permiso->id);
                    $this->auditoria->registrar(self::RETIRO, 'rol', (string) $rol->id, ['permiso' => $permiso->nombre], null);
                }
            });
        }

        return array_map(fn (PermisoParaEscribirDto $permiso) => $permiso->nombre, $nuevos);
    }

    /**
     * The permissions with those names; a name that does not exist is a 422 on "permisos", before any change.
     *
     * @param  list<string>  $nombres
     * @return list<PermisoParaEscribirDto>
     */
    private function resolver(array $nombres): array
    {
        $permisos = $this->catalogo->permisos($nombres);
        $encontrados = array_map(fn (PermisoParaEscribirDto $permiso) => mb_strtolower($permiso->nombre), $permisos);
        $faltantes = array_values(array_filter(
            array_unique($nombres),
            fn (string $nombre) => ! in_array(mb_strtolower($nombre), $encontrados, true),
        ));

        if ($faltantes !== []) {
            throw ValidationException::withMessages(['permisos' => 'No existen estos permisos: '.implode(', ', $faltantes).'.']);
        }

        return $permisos;
    }

    /** Runs the rules; a refusal is audited, with the action attempted, before it reaches the portal. */
    private function verificar(RolParaEscribirDto $rol, string $accion, callable $reglas): void
    {
        try {
            $reglas();
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar($accion, 'rol', (string) $rol->id, $rechazo->code());

            throw $rechazo;
        }
    }

    /**
     * The permissions of $de that are not in $menos, by id.
     *
     * @param  list<PermisoParaEscribirDto>  $de
     * @param  list<PermisoParaEscribirDto>  $menos
     * @return list<PermisoParaEscribirDto>
     */
    private static function diferencia(array $de, array $menos): array
    {
        $ids = array_map(fn (PermisoParaEscribirDto $permiso) => $permiso->id, $menos);

        return array_values(array_filter($de, fn (PermisoParaEscribirDto $permiso) => ! in_array($permiso->id, $ids, true)));
    }
}
