<?php

namespace Tests\Feature\Usuarios;

use App\Domains\Usuarios\Domain\Dto\FiltroUsuariosDto;
use App\Domains\Usuarios\Domain\Interfaces\IUsuarioQueryRepository;
use App\Domains\Usuarios\Infrastructure\UsuarioQueryRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Spec arquitectura-de-dominios, "Lecturas por repositorios de consulta": the read repository of Usuarios
 * against SQL Server.
 */
class UsuarioQueryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_port_is_bound_to_its_sql_adapter(): void
    {
        $this->assertInstanceOf(UsuarioQueryRepository::class, app(IUsuarioQueryRepository::class));
    }

    public function test_the_list_selects_only_the_public_columns(): void
    {
        DB::table('users')->insert([
            'uuid' => '0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60', 'name' => 'Ana', 'email' => 'ana@ubo.cl', 'password' => 'hash',
        ]);
        $selects = [];
        DB::listen(function (QueryExecuted $consulta) use (&$selects) {
            if (str_contains($consulta->sql, 'from [users]') && ! str_contains($consulta->sql, 'count(')) {
                $selects[] = $consulta->sql;
            }
        });

        $pagina = app(IUsuarioQueryRepository::class)->listar(new FiltroUsuariosDto);

        $this->assertCount(1, $selects);
        // SQL Server pages with "top n" or "offset … fetch"; what matters is the column list.
        $this->assertMatchesRegularExpression('/^select (top \d+ )?\[uuid\], \[name\], \[email\], \[rut\], \[disabled_at\] from \[users\]/', $selects[0]);
        $this->assertSame(1, $pagina->total);
        $this->assertSame('ana@ubo.cl', $pagina->items[0]->correo);
    }
}
