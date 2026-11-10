## MODIFIED Requirements

### Requirement: Autorización por permisos
Cada ruta del portal que exige un permiso `iam:*` MUST responder 403 en RFC 9457 cuando el token de la sesión no lo trae. Los permisos SHALL ser los del token de la persona en `iam-admin`.

#### Scenario: Mesa de ayuda en una ruta de clientes
- **GIVEN** una persona con sesión y los permisos `iam:usuarios.leer`, `iam:usuarios.administrar` e `iam:aplicaciones.leer`
- **WHEN** llama a una ruta que exige `iam:clientes.leer`
- **THEN** la respuesta es 403 `application/problem+json`
