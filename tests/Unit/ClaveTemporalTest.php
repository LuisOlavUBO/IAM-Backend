<?php

namespace Tests\Unit;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaveTemporalTest extends TestCase
{
    public function test_it_has_twelve_unambiguous_characters_in_three_groups(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $clave = ClaveTemporal::generar()->clave;

            $this->assertMatchesRegularExpression('/^[a-zA-Z2-9]{4}-[a-zA-Z2-9]{4}-[a-zA-Z2-9]{4}$/', $clave);
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $clave);
        }
    }

    public function test_it_expires_after_the_configured_hours(): void
    {
        $this->freezeTime();
        config(['iam.clave_temporal_horas' => 72]);

        $this->assertTrue(ClaveTemporal::generar()->venceEn->equalTo(now()->addHours(72)));
    }

    public function test_its_columns_store_a_bcrypt_hash_and_the_expiry(): void
    {
        $clave = ClaveTemporal::generar();
        $columnas = $clave->columnas();

        $this->assertStringStartsWith('$2y$', $columnas['password']);
        $this->assertTrue(Hash::check($clave->clave, $columnas['password']));
        $this->assertTrue($columnas['temporary_password_expires_at']->equalTo($clave->venceEn));
    }
}
