<?php

namespace Tests\Unit\Clientes;

use App\Domains\Clientes\Domain\SecretoDeCliente;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecretoDeClienteTest extends TestCase
{
    public function test_it_has_forty_alphanumeric_characters_and_changes_every_time(): void
    {
        $secretos = [];
        for ($i = 0; $i < 20; $i++) {
            $secretos[] = SecretoDeCliente::generar()->secreto;
        }

        foreach ($secretos as $secreto) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $secreto);
        }
        $this->assertCount(20, array_unique($secretos));
    }

    public function test_its_hash_is_bcrypt_and_validates_only_that_secret(): void
    {
        $secreto = SecretoDeCliente::generar();
        $hash = $secreto->hash();

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(Hash::check($secreto->secreto, $hash));
        $this->assertFalse(Hash::check(SecretoDeCliente::generar()->secreto, $hash));
    }
}
