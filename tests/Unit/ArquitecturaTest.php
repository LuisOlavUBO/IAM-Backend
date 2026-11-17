<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Spec arquitectura-de-dominios: the dependency rules, checked on the `use` statements of every file in
 * app/ (design D3 of arquitectura-modular, D4 of nucleo-explicito). No database and no new dependencies.
 *
 * The core of a domain ("núcleo") is four folders that act as one layer: Rules, Exceptions, Dto, Interfaces.
 *
 * | From                 | May use                                         | May not use                          |
 * |----------------------|-------------------------------------------------|--------------------------------------|
 * | Features/X           | its folder, core, Contracts, Shared             | Features/Y, Infrastructure, root     |
 * | Features/X/…Handler  | the same                                        | also Illuminate\Http                 |
 * | core                 | core, Shared                                    | Features, Infrastructure, Contracts, HTTP |
 * | Infrastructure       | core, Contracts, Infrastructure, Shared         | Features                             |
 * | Contracts            | Contracts, core, Shared                         | Features, Infrastructure             |
 * | domain root, routes  | anything of its own domain, Shared              | —                                    |
 * | another domain       | only Contracts (its public contract)            | everything else                      |
 * | Shared               | Laravel, ubo/common                             | App\Domains                          |
 * | Console              | Shared; core, Contracts, Features of any domain | Infrastructure                       |
 *
 * A file in any other folder of a domain (e.g. a leftover Domain/) is reported as an unknown folder.
 */
class ArquitecturaTest extends TestCase
{
    /** The folders that make up the core of a domain. */
    private const NUCLEO = ['Rules', 'Exceptions', 'Dto', 'Interfaces'];

    /** Every folder a domain may have besides routes/ and the core. */
    private const CAPAS = ['Features', 'Infrastructure', 'Contracts'];

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
     * @return array<string, array{string, string, string}> file in app/, its code, what is reported after "→"
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
            'un QueryHandler importa HTTP' => [
                'Domains/Usuarios/Features/ListarUsuarios/ListarUsuariosQueryHandler.php',
                'use Illuminate\Http\Request;',
                'Illuminate\Http\Request',
            ],
            'un CommandHandler importa HTTP' => [
                'Domains/Usuarios/Features/CrearUsuario/CrearUsuarioCommandHandler.php',
                'use Illuminate\Http\Request;',
                'Illuminate\Http\Request',
            ],
            'el núcleo importa un caso de uso' => [
                'Domains/Usuarios/Rules/X.php',
                'use App\Domains\Usuarios\Features\VerUsuario\VerUsuarioQueryHandler;',
                'App\Domains\Usuarios\Features\VerUsuario\VerUsuarioQueryHandler',
            ],
            'una interfaz del núcleo importa HTTP' => [
                'Domains/Usuarios/Interfaces/X.php',
                'use Illuminate\Http\Request;',
                'Illuminate\Http\Request',
            ],
            'una excepción importa infraestructura' => [
                'Domains/Usuarios/Exceptions/XException.php',
                'use App\Domains\Usuarios\Infrastructure\PersonasRepository;',
                'App\Domains\Usuarios\Infrastructure\PersonasRepository',
            ],
            'un Dto importa Contracts' => [
                'Domains/Usuarios/Dto/XDto.php',
                'use App\Domains\Usuarios\Contracts\PersonaBasicaDto;',
                'App\Domains\Usuarios\Contracts\PersonaBasicaDto',
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
            'un dominio usa el núcleo de otro' => [
                'Domains/Aplicaciones/Rules/X.php',
                'use App\Domains\Usuarios\Rules\ClaveTemporal;',
                'App\Domains\Usuarios\Rules\ClaveTemporal',
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
            'un archivo en una carpeta Domain' => [
                'Domains/Usuarios/Domain/X.php',
                'use App\Shared\Rut\Rut;',
                'carpeta no reconocida: Domain',
            ],
        ];
    }

    #[DataProvider('prohibidas')]
    public function test_a_forbidden_dependency_is_reported_with_its_file(string $ruta, string $uso, string $reportado): void
    {
        $this->assertSame(["{$ruta} → {$reportado}"], self::violaciones($ruta, "<?php\n\nnamespace X;\n\n{$uso}\n"));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function permitidas(): array
    {
        return [
            'un caso de uso usa el núcleo y Shared' => ['Domains/Usuarios/Features/VerUsuario/X.php', "use App\\Domains\\Usuarios\\Interfaces\\IUsuarioQueryRepository;\nuse App\\Domains\\Usuarios\\Dto\\UsuarioDetalleDto;\nuse App\\Domains\\Usuarios\\Exceptions\\UsuarioNoEncontradoException;\nuse App\\Domains\\Usuarios\\Rules\\ReglasDeRoles;\nuse App\\Shared\\Rut\\Rut;"],
            'un endpoint usa HTTP' => ['Domains/Usuarios/Features/CrearUsuario/CrearUsuarioEndpoint.php', 'use Illuminate\Http\JsonResponse;'],
            'una regla usa un Dto y una excepción' => ['Domains/Usuarios/Rules/X.php', "use App\\Domains\\Usuarios\\Dto\\QuienActuaDto;\nuse App\\Domains\\Usuarios\\Exceptions\\UltimoAdministradorException;"],
            'una interfaz usa sus Dto' => ['Domains/Usuarios/Interfaces/X.php', 'use App\Domains\Usuarios\Dto\FiltroUsuariosDto;'],
            'la infraestructura implementa una interfaz con sus Dto' => ['Domains/Usuarios/Infrastructure/X.php', "use App\\Domains\\Usuarios\\Interfaces\\IUsuarioQueryRepository;\nuse App\\Domains\\Usuarios\\Dto\\UsuarioDetalleDto;"],
            'un dominio usa los Contracts de otro' => ['Domains/Acceso/Features/Me/X.php', 'use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;'],
            'el provider enlaza su infraestructura' => ['Domains/Usuarios/UsuariosServiceProvider.php', 'use App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository;'],
            'un comando usa el núcleo de otro' => ['Console/Commands/XCommand.php', 'use App\Domains\Clientes\Rules\NuevoCliente;'],
        ];
    }

    #[DataProvider('permitidas')]
    public function test_an_allowed_dependency_is_not_reported(string $ruta, string $usos): void
    {
        $this->assertSame([], self::violaciones($ruta, "<?php\n\nnamespace X;\n\n{$usos}\n"));
    }

    /**
     * Forbidden imports of one file, as "<file in app/> → <imported class>", or its unknown folder.
     *
     * @return list<string>
     */
    private static function violaciones(string $ruta, string $codigo): array
    {
        $origen = self::ubicarArchivo($ruta);

        if ($origen['capa'] === 'desconocida') {
            return ["{$ruta} → carpeta no reconocida: {$origen['carpeta']}"];
        }

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
     * Where a file of app/ lives: its zone (Domains, Shared, Console, other), domain, layer, feature and whether it
     * is a handler. The layer of the core folders is "nucleo"; any other unknown folder is "desconocida".
     *
     * @return array{zona: string, dominio: ?string, capa: ?string, caso: ?string, carpeta: ?string, handler: bool}
     */
    private static function ubicarArchivo(string $ruta): array
    {
        $partes = explode('/', $ruta);

        if ($partes[0] !== 'Domains') {
            return ['zona' => $partes[0], 'dominio' => null, 'capa' => null, 'caso' => null, 'carpeta' => null, 'handler' => false];
        }

        $carpeta = count($partes) > 3 && $partes[2] !== 'routes' ? $partes[2] : null;
        $capa = match (true) {
            $carpeta === null => 'raiz',
            in_array($carpeta, self::NUCLEO, true) => 'nucleo',
            in_array($carpeta, self::CAPAS, true) => $carpeta,
            default => 'desconocida',
        };

        return [
            'zona' => 'Domains',
            'dominio' => $partes[1],
            'capa' => $capa,
            'caso' => $capa === 'Features' ? $partes[3] : null,
            'carpeta' => $carpeta,
            'handler' => $capa === 'Features' && preg_match('/(Query|Command)Handler\.php$/', $ruta) === 1,
        ];
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
        $capa = in_array($capa, self::NUCLEO, true) ? 'nucleo' : $capa;

        return ['zona' => 'Domains', 'dominio' => $partes[2], 'capa' => $capa, 'caso' => $capa === 'Features' ? $partes[4] : null];
    }

    /**
     * @param  array{zona: string, dominio: ?string, capa: ?string, caso: ?string, carpeta: ?string, handler: bool}  $origen
     */
    private static function permitida(array $origen, string $importado): bool
    {
        $destino = self::ubicarClase($importado);

        // The core and the handlers know nothing of HTTP.
        if (($origen['capa'] === 'nucleo' || $origen['handler']) && str_starts_with($importado, 'Illuminate\\Http\\')) {
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
     * @param  array{zona: string, dominio: ?string, capa: ?string, caso: ?string, carpeta: ?string, handler: bool}  $origen
     * @param  array{zona: string, dominio: ?string, capa: ?string, caso: ?string}  $destino
     */
    private static function permitidaEnElDominio(array $origen, array $destino): bool
    {
        return match ($origen['capa']) {
            'Features' => $destino['capa'] === 'Features'
                ? $destino['caso'] === $origen['caso']
                : in_array($destino['capa'], ['nucleo', 'Contracts'], true),
            'nucleo' => $destino['capa'] === 'nucleo',
            'Infrastructure' => $destino['capa'] !== 'Features' && $destino['capa'] !== 'raiz',
            'Contracts' => in_array($destino['capa'], ['Contracts', 'nucleo'], true),
            default => true,
        };
    }
}
