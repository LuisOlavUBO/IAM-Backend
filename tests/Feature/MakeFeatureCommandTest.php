<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use Tests\Unit\ArquitecturaTest;

/**
 * Spec arquitectura-de-dominios, "Generador de casos de uso". The use case is generated in a domain
 * that only exists for this test, and removed afterwards.
 */
class MakeFeatureCommandTest extends TestCase
{
    private const DOMINIO = 'PruebaGenerador';

    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('Domains/'.self::DOMINIO));
        File::deleteDirectory(base_path('tests/Feature/'.self::DOMINIO));

        parent::tearDown();
    }

    public function test_a_new_use_case_gets_its_files_its_test_and_the_route_to_add(): void
    {
        $this->artisan('iam:make-feature', ['dominio' => self::DOMINIO, 'caso' => 'ListarAplicaciones'])
            ->expectsOutputToContain("Route::get('<url>', ListarAplicacionesEndpoint::class)")
            ->assertSuccessful();

        $carpeta = app_path('Domains/'.self::DOMINIO.'/Features/ListarAplicaciones');
        foreach (['Endpoint', 'Request', 'QueryHandler', 'Resource'] as $pieza) {
            $this->assertFileExists("{$carpeta}/ListarAplicaciones{$pieza}.php");
        }
        $test = base_path('tests/Feature/'.self::DOMINIO.'/ListarAplicacionesTest.php');
        $this->assertFileExists($test);

        $this->assertSame([], ArquitecturaTest::violacionesEn(app_path()));
        $pint = Process::path(base_path())->run(['php', 'vendor/bin/pint', '--test', $carpeta, $test]);
        $this->assertTrue($pint->successful(), "Pint encontró cambios en el código generado:\n".$pint->output());
    }

    public function test_a_use_case_that_writes_gets_a_command_handler(): void
    {
        $this->artisan('iam:make-feature', ['dominio' => self::DOMINIO, 'caso' => 'CrearAplicacion', '--command' => true])
            ->assertSuccessful();

        $carpeta = app_path('Domains/'.self::DOMINIO.'/Features/CrearAplicacion');
        $this->assertFileExists("{$carpeta}/CrearAplicacionCommandHandler.php");
        $this->assertFileDoesNotExist("{$carpeta}/CrearAplicacionQueryHandler.php");
        $this->assertStringContainsString('CrearAplicacionCommandHandler $handler', File::get("{$carpeta}/CrearAplicacionEndpoint.php"));
    }

    public function test_an_existing_use_case_is_not_overwritten(): void
    {
        $existente = app_path('Domains/'.self::DOMINIO.'/Features/VerAplicacion/VerAplicacionQueryHandler.php');
        File::ensureDirectoryExists(dirname($existente));
        File::put($existente, '<?php // escrito a mano');

        $this->artisan('iam:make-feature', ['dominio' => self::DOMINIO, 'caso' => 'VerAplicacion'])
            ->expectsOutputToContain('no se sobrescribe')
            ->assertFailed();

        $this->assertSame('<?php // escrito a mano', File::get($existente));
        $this->assertFileDoesNotExist(dirname($existente).'/VerAplicacionEndpoint.php');
        $this->assertFileDoesNotExist(base_path('tests/Feature/'.self::DOMINIO.'/VerAplicacionTest.php'));
    }

    public function test_a_name_that_is_not_pascal_case_is_refused(): void
    {
        $this->artisan('iam:make-feature', ['dominio' => 'pruebaGenerador', 'caso' => 'listar-aplicaciones'])
            ->expectsOutputToContain('PascalCase')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Domains/pruebaGenerador'));
    }
}
