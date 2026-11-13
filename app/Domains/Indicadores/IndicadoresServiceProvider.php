<?php

namespace App\Domains\Indicadores;

use App\Domains\Indicadores\Domain\Interfaces\IIndicadoresQueryRepository;
use App\Domains\Indicadores\Infrastructure\IndicadoresQueryRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings of the Indicadores domain. Its routes are mounted by routes/web.php, in the web group with
 * the portal's session.
 */
class IndicadoresServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IIndicadoresQueryRepository::class => IndicadoresQueryRepository::class,
    ];
}
