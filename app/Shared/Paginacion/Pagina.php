<?php

namespace App\Shared\Paginacion;

/**
 * One page of results, as the query repositories return it: their interfaces do not expose Laravel's
 * paginator (spec arquitectura-de-dominios).
 *
 * @template T
 */
final readonly class Pagina
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $pagina,
        public int $porPagina,
    ) {}
}
