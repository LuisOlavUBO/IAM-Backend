<?php

namespace Tests\Unit\Clientes;

use App\Domains\Clientes\Domain\Redireccion;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Design D3 of clientes-crear: the same redirect rule for the portal and iam:install.
 */
class RedireccionTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function redirecciones(): array
    {
        return [
            'https' => ['https://satelite.ubo.cl/auth/callback', true],
            'https en mayúsculas' => ['HTTPS://Satelite.ubo.cl/callback', true],
            'http en localhost' => ['http://localhost:4200/api/iam/v1/auth/callback', true],
            'http en un subdominio de localhost' => ['http://iam.localhost/callback', true],
            'http fuera de localhost' => ['http://satelite.ubo.cl/callback', false],
            'relativa' => ['/auth/callback', false],
            'con fragmento' => ['https://satelite.ubo.cl/callback#x', false],
            'otro esquema' => ['ftp://satelite.ubo.cl/callback', false],
            'sin host' => ['https:///callback', false],
            'vacía' => ['', false],
        ];
    }

    #[DataProvider('redirecciones')]
    public function test_a_redirect_is_valid_only_with_https_or_http_on_localhost_and_no_fragment(string $url, bool $valida): void
    {
        $this->assertSame($valida, Redireccion::esValida($url));
    }

    public function test_the_rule_refuses_an_invalid_redirect_with_its_message(): void
    {
        $this->assertTrue(Validator::make(['r' => 'https://satelite.ubo.cl/callback'], ['r' => new Redireccion])->passes());

        $validacion = Validator::make(['r' => 'http://satelite.ubo.cl/callback'], ['r' => new Redireccion]);
        $this->assertTrue($validacion->fails());
        $this->assertSame([Redireccion::MENSAJE], $validacion->errors()->get('r'));

        $this->assertTrue(Validator::make(['r' => ['https://satelite.ubo.cl']], ['r' => new Redireccion])->fails());
    }
}
