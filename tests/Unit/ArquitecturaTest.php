<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Spec arquitectura-de-dominios: the dependency rules, checked on the `use` statements of every file in
 * app/ (design D3). No database and no new dependencies.
 *
 * | From                 | May use                                         | May not use                          |
 * |----------------------|-------------------------------------------------|--------------------------------------|
 * | Features/X           | its folder, Domain, Contracts, Shared           | Features/Y, Infrastructure, root     |
 * | Domain               | Domain, Shared                                  | Features, Infrastructure, Contracts, HTTP |
 * | Infrastructure       | Domain, Contracts, Infrastructure, Shared       | Features                             |
 * | Contracts            | Contracts, Domain, Shared                       | Features, Infrastructure             |
 * | domain root, routes  | anything of its own domain, Shared              | —                                    |
 * | another domain       | only Contracts (its public contract)            | everything else                      |
 * | Shared               | Laravel, ubo/common                             | App\Domains                          |
 * | Console              | Shared; Domain, Contracts, Features of any domain | Infrastructure                     |
 */
class ArquitecturaTest extends TestCase
{
    public function test_the_code_follows_the_dependency_rules(): void
    {
        $violaciones = self::violacionesEn(dirname(__DIR__, 2).'/app');

        $this->assertSame([], $violaciones, "Dependencias prohibidas (spec arquitectura-de-dominios):\n".implode("\n", $violaciones));
    }

    /**
     * Forbidden imports of every PHP file under an app/ directory (also used by MakeFeatureCommandTest).
     *
     * @return list<string>
     */
    public static function violacionesEn(string $app): array
    {
        $violaciones = [];
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }
            $ruta = str_replace('\\', '/', substr($archivo->getPathname(), strlen($app) + 1));
            $violaciones = [...$violaciones, ...self::violaciones($ruta, (string) file_get_contents($archivo->getPathname()))];
        }

        return $violaciones;
    }

    /**
     * @return array<string, array{string, string, string}> file in app/, its code, the forbidden import
     */
    public static function prohibidas(): array
    {
        return [
            'un caso de uso importa a otro' => [
                'Domains/Usuarios/Features/VerUsuario/X.php',
                'use App\Domains\Usuarios\Features\ListarUsuarios\ListarUsuariosQueryHandler;',
                'App\Domains\Usuarios\Features\ListarUsuarios\ListarUsuariosQueryHandler',
            ],
            'un caso de uso importa infraestructura' => [
                'Domains/Usuarios/Features/ListarUsuarios/X.php',
                'use App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository;',
                'App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository',
            ],
            'el dominio importa un caso de uso' => [
                'Domains/Usuarios/Domain/X.php',
                'use App\Domains\Usuarios\Features\VerUsuario\VerUsuarioQueryHandler;',
                'App\Domains\Usuarios\Features\VerUsuario\VerUsuarioQueryHandler',
            ],
            'una interfaz del dominio importa HTTP' => [
                'Domains/Usuarios/Domain/Interfaces/X.php',
                'use Illuminate\Http\Request;',
                'Illuminate\Http\Request',
            ],
            'la infraestructura importa un caso de uso' => [
                'Domains/Usuarios/Infrastructure/X.php',
                'use App\Domains\Usuarios\Features\VerUsuario\VerUsuarioResource;',
                'App\Domains\Usuarios\Features\VerUsuario\VerUsuarioResource',
            ],
            'Contracts importa infraestructura' => [
                'Domains/Usuarios/Contracts/X.php',
                'use App\Domains\Usuarios\Infrastructure\DirectorioUsuariosService;',
                'App\Domains\Usuarios\Infrastructure\DirectorioUsuariosService',
            ],
            'un dominio usa el modelo de otro' => [
                'Domains/Aplicaciones/Domain/X.php',
                'use App\Domains\Usuarios\Domain\ClaveTemporal;',
                'App\Domains\Usuarios\Domain\ClaveTemporal',
            ],
            'un dominio usa un caso de uso de otro' => [
                'Domains/Acceso/Features/Me/X.php',
                'use App\Domains\Usuarios\Features\VerUsuario\VerUsuarioQueryHandler;',
                'App\Domains\Usuarios\Features\VerUsuario\VerUsuarioQueryHandler',
            ],
            'Shared depende de un dominio' => [
                'Shared/Paginacion/X.php',
                'use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;',
                'App\Domains\Usuarios\Contracts\IDirectorioUsuariosService',
            ],
            'un comando importa infraestructura' => [
                'Console/Commands/XCommand.php',
                'use App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository;',
                'App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository',
            ],
        ];
    }

    #[DataProvider('prohibidas')]
    public function test_a_forbidden_dependency_is_reported_with_its_file(string $ruta, string $uso, string $importado): void
    {
        $this->assertSame(["{$ruta} → {$importado}"], self::violaciones($ruta, "<?php\n\nnamespace X;\n\n{$uso}\n"));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function permitidas(): array
    {
        return [
            'un caso de uso usa su repositorio, sus Dto, su dominio y Shared' => ['Domains/Usuarios/Features/VerUsuario/X.php', "use App\\Domains\\Usuarios\\Domain\\Interfaces\\IUsuarioQueryRepository;\nuse App\\Domains\\Usuarios\\Domain\\Dto\\UsuarioDetalleDto;\nuse App\\Domains\\Usuarios\\Domain\\UsuarioNoEncontradoException;\nuse App\\Shared\\Rut\\Rut;"],
            'una interfaz usa sus Dto' => ['Domains/Usuarios/Domain/Interfaces/X.php', 'use App\Domains\Usuarios\Domain\Dto\FiltroUsuariosDto;'],
            'un dominio usa los Contracts de otro' => ['Domains/Acceso/Features/Me/X.php', 'use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;'],
            'el provider enlaza su infraestructura' => ['Domains/Usuarios/UsuariosServiceProvider.php', 'use App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository;'],
            'un comando usa el dominio de otro' => ['Console/Commands/XCommand.php', 'use App\Domains\Clientes\Domain\NuevoCliente;'],
        ];
    }

    #[DataProvider('permitidas')]
    public function test_an_allowed_dependency_is_not_reported(string $ruta, string $usos): void
    {
        $this->assertSame([], self::violaciones($ruta, "<?php\n\nnamespace X;\n\n{$usos}\n"));
    }

    /**
     * Forbidden imports of one file, as "<file in app/> → <imported class>".
     *
     * @return list<string>
     */
    private static function violaciones(string $ruta, string $codigo): array
    {
        $origen = self::ubicarArchivo($ruta);
        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)\s*;/m', $codigo, $usos);

        $violaciones = [];
        foreach ($usos[1] as $importado) {
            if (! self::permitida($origen, $importado)) {
                $violaciones[] = "{$ruta} → {$importado}";
            }
        }

        return $violaciones;
    }

    /**
     * Where a file of app/ lives: its zone (Domains, Shared, Console, other), domain, layer and feature.
     *
     * @return array{zona: string, dominio: ?string, capa: ?string, caso: ?string}
     */
    private static function ubicarArchivo(string $ruta): array
    {
        $partes = explode('/', $ruta);

        if ($partes[0] !== 'Domains') {
            return ['zona' => $partes[0], 'dominio' => null, 'capa' => null, 'caso' => null];
        }

        $capa = count($partes) > 3 && $partes[2] !== 'routes' ? $partes[2] : 'raiz';

        return ['zona' => 'Domains', 'dominio' => $partes[1], 'capa' => $capa, 'caso' => $capa === 'Features' ? $partes[3] : null];
    }

    /**
     * Where an imported class lives, from its namespace.
     *
     * @return array{zona: string, dominio: ?string, capa: ?string, caso: ?string}
     */
    private static function ubicarClase(string $clase): array
    {
        $partes = explode('\\', $clase);

        if ($partes[0] !== 'App') {
            return ['zona' => 'externa', 'dominio' => null, 'capa' => null, 'caso' => null];
        }
        if (($partes[1] ?? '') !== 'Domains') {
            return ['zona' => $partes[1] ?? '', 'dominio' => null, 'capa' => null, 'caso' => null];
        }

        $capa = count($partes) > 4 ? $partes[3] : 'raiz';

        return ['zona' => 'Domains', 'dominio' => $partes[2], 'capa' => $capa, 'caso' => $capa === 'Features' ? $partes[4] : null];
    }

    /**
     * @param  array{zona: string, dominio: ?string, capa: ?string, caso: ?string}  $origen
     */
    private static function permitida(array $origen, string $importado): bool
    {
        $destino = self::ubicarClase($importado);

        // Domain knows nothing of HTTP.
        if ($origen['capa'] === 'Domain' && str_starts_with($importado, 'Illuminate\\Http\\')) {
            return false;
        }

        if ($destino['zona'] !== 'Domains') {
            return true;
        }

        return match ($origen['zona']) {
            'Shared' => false,
            'Console' => $destino['capa'] !== 'Infrastructure',
            'Domains' => $origen['dominio'] === $destino['dominio']
                ? self::permitidaEnElDominio($origen, $destino)
                : $destino['capa'] === 'Contracts',
            default => true,
        };
    }

    /**
     * @param  array{zona: string, dominio: ?string, capa: ?string, caso: ?string}  $origen
     * @param  array{zona: string, dominio: ?string, capa: ?string, caso: ?string}  $destino
     */
    private static function permitidaEnElDominio(array $origen, array $destino): bool
    {
        return match ($origen['capa']) {
            'Features' => $destino['capa'] === 'Features'
                ? $destino['caso'] === $origen['caso']
                : in_array($destino['capa'], ['Domain', 'Contracts'], true),
            'Domain' => $destino['capa'] === 'Domain',
            'Infrastructure' => $destino['capa'] !== 'Features' && $destino['capa'] !== 'raiz',
            'Contracts' => in_array($destino['capa'], ['Contracts', 'Domain'], true),
            default => true,
        };
    }
}
