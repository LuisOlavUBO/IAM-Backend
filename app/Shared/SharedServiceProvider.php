<?php

namespace App\Shared;

use App\Shared\Auditoria\AuditorBaseDatos;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings of the shared kernel (spec arquitectura-de-dominios).
 */
class SharedServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IAuditor::class => AuditorBaseDatos::class,
    ];
}
