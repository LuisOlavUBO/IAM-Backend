<?php

namespace App\Domains\Aplicaciones\Domain;

use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * The catalog of iam-admin (its application, its iam:* permissions and its roles) is not changed from the
 * portal, and its permissions are not given to roles of other applications: widening the help desk, or
 * handing out iam:* permissions, would be a privilege escalation (spec aplicaciones, "Catálogo de iam-admin
 * protegido"; design D4 of aplicaciones-catalogo).
 */
final class CatalogoDeIamAdminException extends DomainProblem
{
    public function __construct()
    {
        parent::__construct('El catálogo de iam-admin no se cambia desde el portal.');
    }

    public function code(): string
    {
        return 'CATALOGO_DE_IAM_ADMIN';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Catálogo de iam-admin';
    }
}
