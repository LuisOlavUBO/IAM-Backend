<?php

namespace App\Domains\Auditoria;

use App\Domains\Auditoria\Domain\Interfaces\IAuditoriaQueryRepository;
use App\Domains\Auditoria\Infrastructure\AuditoriaQueryRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings of the Auditoria domain (reading the log; writing it is IAuditor in Shared). Its routes are
 * mounted by routes/web.php, in the web group with the portal's session.
 */
class AuditoriaServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IAuditoriaQueryRepository::class => AuditoriaQueryRepository::class,
    ];
}
