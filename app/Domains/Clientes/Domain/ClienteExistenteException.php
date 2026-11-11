<?php

namespace App\Domains\Clientes\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Another client already uses that name, without distinguishing case (spec clientes, "Nombre único del
 * cliente"; design D5 of clientes-crear): iam:rotate-secret finds clients by name.
 */
final class ClienteExistenteException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('Ya existe un cliente con ese nombre.');
    }

    public function code(): string
    {
        return 'CLIENTE_EXISTENTE';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Cliente existente';
    }
}
