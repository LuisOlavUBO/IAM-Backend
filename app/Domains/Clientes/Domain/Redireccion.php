<?php

namespace App\Domains\Clientes\Domain;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * A redirect URI of an authorization-code client (design D3 of clientes-crear): an absolute https URL, or
 * http on localhost, without a fragment. Shared by the portal and iam:install. Also a validation rule.
 */
final class Redireccion implements ValidationRule
{
    public const MENSAJE = 'La redirección debe ser una URL absoluta https (o http en localhost), sin fragmento.';

    public static function esValida(string $url): bool
    {
        $partes = parse_url($url);

        if (! is_array($partes) || ! isset($partes['scheme'], $partes['host']) || isset($partes['fragment'])) {
            return false;
        }

        $host = Str::lower($partes['host']);
        $local = $host === 'localhost' || str_ends_with($host, '.localhost');
        $esquema = Str::lower($partes['scheme']);

        return $esquema === 'https' || ($esquema === 'http' && $local);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::esValida($value)) {
            $fail(self::MENSAJE);
        }
    }
}
