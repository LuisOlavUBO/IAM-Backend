## MODIFIED Requirements

### Requirement: Aplicación iam-admin
`iam:install` SHALL crear la aplicación `iam-admin` con los permisos `iam:usuarios.leer`, `iam:usuarios.administrar`, `iam:aplicaciones.leer`, `iam:aplicaciones.administrar`, `iam:clientes.leer`, `iam:clientes.administrar` e `iam:auditoria.leer`; el rol `administrador`, que da los siete, y el rol `mesa-de-ayuda`, que da `iam:usuarios.leer`, `iam:usuarios.administrar` e `iam:aplicaciones.leer`.

#### Scenario: Roles del portal
- **WHEN** termina `iam:install`
- **THEN** `iam-admin/administrador` da los siete permisos `iam:*` y `iam-admin/mesa-de-ayuda` da exactamente `iam:usuarios.leer`, `iam:usuarios.administrar` e `iam:aplicaciones.leer`

#### Scenario: Sin permisos de Catálogo
- **WHEN** termina `iam:install`
- **THEN** no existe ningún permiso `iam:catalogo.*`
