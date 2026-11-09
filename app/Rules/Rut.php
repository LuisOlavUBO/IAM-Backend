<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Copied from API-Auth (App\Rules\Rut), plus normalize().
 *
 * A Chilean RUT already normalized (no dots, with hyphen, "K" in uppercase:
 * "12345678-K") whose check digit matches its number (modulo 11).
 */
class Rut implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\d{7,8})-([\dK])$/', $value, $parts)) {
            $fail('El :attribute no tiene el formato 12345678-9.');

            return;
        }

        if (self::checkDigit($parts[1]) !== $parts[2]) {
            $fail('El :attribute no es un RUT válido.');
        }
    }

    /**
     * A RUT as people write it ("12.345.678-k", "12345678 K", "123456785") in the stored format:
     * no dots or spaces, uppercase K, and a hyphen before the check digit. Validate it afterwards.
     */
    public static function normalize(string $rut): string
    {
        $rut = strtoupper(str_replace(['.', ' ', '-'], '', trim($rut)));

        return strlen($rut) < 2 ? $rut : substr($rut, 0, -1).'-'.substr($rut, -1);
    }

    /**
     * The check digit of a RUT number: "0" to "9", or "K".
     */
    public static function checkDigit(string $number): string
    {
        $sum = 0;
        $multiplier = 2;

        foreach (array_reverse(str_split($number)) as $digit) {
            $sum += (int) $digit * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        return match ($remainder = 11 - $sum % 11) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };
    }
}
