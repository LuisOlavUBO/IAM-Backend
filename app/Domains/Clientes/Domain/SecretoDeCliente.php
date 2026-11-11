<?php

namespace App\Domains\Clientes\Domain;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The secret of a confidential OAuth client: forty random characters, as iam:install creates them. Only its
 * bcrypt hash is stored (API-Auth checks it with Hash::check); the secret itself is shown once and never
 * logged or audited.
 */
final readonly class SecretoDeCliente
{
    public const LONGITUD = 40;

    private function __construct(public string $secreto) {}

    public static function generar(): self
    {
        return new self(Str::random(self::LONGITUD));
    }

    /** The value of oauth_clients.secret. */
    public function hash(): string
    {
        return Hash::make($this->secreto);
    }
}
