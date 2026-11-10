<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Creates the folder of a use case with its endpoint, request, handler, resource and test (spec
 * arquitectura-de-dominios, "Generador de casos de uso"). The handler is a QueryHandler, or a
 * CommandHandler with --command (CQRS lite). It does not touch the routes: the HTTP method, the URL
 * and the permission are decided by whoever adds the line it prints.
 */
#[Signature('iam:make-feature
    {dominio : Domain in PascalCase, e.g. Aplicaciones}
    {caso : Use case in PascalCase, e.g. ListarAplicaciones}
    {--command : The use case writes: <Caso>CommandHandler instead of <Caso>QueryHandler}')]
#[Description('Crea un caso de uso en app/Domains/<Dominio>/Features/<CasoDeUso> con su test')]
class MakeFeatureCommand extends Command
{
    /** Stub => file it becomes, relative to the base path. */
    private const ARCHIVOS = [
        'endpoint' => 'app/Domains/{{dominio}}/Features/{{caso}}/{{caso}}Endpoint.php',
        'request' => 'app/Domains/{{dominio}}/Features/{{caso}}/{{caso}}Request.php',
        'handler' => 'app/Domains/{{dominio}}/Features/{{caso}}/{{caso}}{{tipo}}Handler.php',
        'resource' => 'app/Domains/{{dominio}}/Features/{{caso}}/{{caso}}Resource.php',
        'test' => 'tests/Feature/{{dominio}}/{{caso}}Test.php',
    ];

    public function __construct(private readonly Filesystem $archivos)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dominio = (string) $this->argument('dominio');
        $caso = (string) $this->argument('caso');

        foreach ([$dominio, $caso] as $nombre) {
            if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $nombre) !== 1) {
                $this->components->error("Los nombres van en PascalCase (por ejemplo Aplicaciones ListarAplicaciones): «{$nombre}» no lo es.");

                return self::FAILURE;
            }
        }

        $reemplazos = ['{{dominio}}' => $dominio, '{{caso}}' => $caso, '{{tipo}}' => $this->option('command') ? 'Command' : 'Query'];
        $destinos = array_map(fn (string $ruta) => base_path(strtr($ruta, $reemplazos)), self::ARCHIVOS);

        // All or nothing: one existing file means the use case is there, and nothing is written.
        foreach ($destinos as $destino) {
            if ($this->archivos->exists($destino)) {
                $this->components->error("Ya existe {$this->relativa($destino)}: el caso de uso {$dominio}/{$caso} no se sobrescribe.");

                return self::FAILURE;
            }
        }

        foreach ($destinos as $stub => $destino) {
            $this->archivos->ensureDirectoryExists(dirname($destino));
            $this->archivos->put($destino, strtr($this->archivos->get(base_path("stubs/feature/{$stub}.stub")), $reemplazos));
            $this->components->twoColumnDetail($this->relativa($destino), '<fg=green>creado</>');
        }

        $this->newLine();
        $this->line("Agregue la ruta en app/Domains/{$dominio}/routes/v1.php (método, URL y permiso):");
        $this->line("    use App\\Domains\\{$dominio}\\Features\\{$caso}\\{$caso}Endpoint;");
        $this->line("    Route::get('<url>', {$caso}Endpoint::class)->middleware('can:iam:<permiso>');");

        return self::SUCCESS;
    }

    private function relativa(string $ruta): string
    {
        return str_replace('\\', '/', substr($ruta, strlen(base_path()) + 1));
    }
}
