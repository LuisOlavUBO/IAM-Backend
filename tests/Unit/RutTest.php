<?php

namespace Tests\Unit;

use App\Rules\Rut;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RutTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function writtenRuts(): array
    {
        return [
            'con puntos y k minúscula' => ['12.345.678-k', '12345678-K'],
            'con espacios' => [' 12345678 5 ', '12345678-5'],
            'sin guion' => ['123456785', '12345678-5'],
            'ya normalizado' => ['7654321-6', '7654321-6'],
        ];
    }

    #[DataProvider('writtenRuts')]
    public function test_a_written_rut_is_normalized(string $written, string $normalized): void
    {
        $this->assertSame($normalized, Rut::normalize($written));
    }

    public function test_the_check_digit_uses_modulo_11(): void
    {
        $this->assertSame('5', Rut::checkDigit('12345678'));
        $this->assertSame('K', Rut::checkDigit('10000013'));
        $this->assertSame('0', Rut::checkDigit('10000004'));
    }

    public function test_the_rule_accepts_a_valid_rut_and_refuses_a_wrong_check_digit(): void
    {
        $this->assertTrue(Validator::make(['rut' => '12345678-5'], ['rut' => new Rut])->passes());
        $this->assertFalse(Validator::make(['rut' => '12345678-4'], ['rut' => new Rut])->passes());
        $this->assertFalse(Validator::make(['rut' => '12.345.678-5'], ['rut' => new Rut])->passes());
    }
}
