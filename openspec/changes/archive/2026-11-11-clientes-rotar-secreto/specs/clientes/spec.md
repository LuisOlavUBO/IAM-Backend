## ADDED Requirements

### Requirement: Rotar el secreto de un cliente
`POST /api/iam/v1/clientes/{id}/secreto` SHALL generar un secreto nuevo de 40 caracteres, guardar solo su hash bcrypt en `oauth_clients.secret` y responder 200 con `data.secreto` una sola vez, con `Cache-Control: no-store`. El secreto anterior MUST dejar de validar. MUST exigir la sesión del portal, `iam:clientes.administrar` y el token CSRF; un cliente inexistente MUST responder 404 `CLIENTE_NO_ENCONTRADO`.

#### Scenario: Rotación de un cliente de un sistema
- **GIVEN** el cliente confidencial `pago-honorarios-backend`
- **WHEN** un administrador pide `POST /api/iam/v1/clientes/{id}/secreto`
- **THEN** responde 200 con `data.secreto` de 40 caracteres, `Hash::check` lo valida contra el hash guardado y el secreto anterior ya no valida

#### Scenario: Sin el permiso
- **GIVEN** la mesa de ayuda, sin `iam:clientes.administrar`
- **WHEN** pide la rotación
- **THEN** recibe 403 `ACCESO_DENEGADO` y el secreto no cambia

### Requirement: Rotación auditada sin el secreto
Cada rotación SHALL registrar `cliente.secreto.rotacion` sobre la entidad `cliente` con su id, en la misma transacción que el cambio, sin `antes` ni `despues`. El secreto y su hash MUST NOT quedar en la auditoría, en logs ni en otra respuesta.

#### Scenario: Entrada de la rotación
- **WHEN** se rota el secreto de un cliente
- **THEN** hay una entrada `cliente.secreto.rotacion` con el id del cliente y el actor, y ninguna fila de `iam_auditoria` contiene el secreto nuevo ni su hash

### Requirement: Confirmación escrita para los clientes de iam-admin
Rotar un cliente de la aplicación `iam-admin` MUST exigir `confirmar` igual al nombre del cliente; si falta o no coincide, MUST responder 422 `VALIDACION` en `errores.confirmar` sin cambiar nada. Al rotarlo, la respuesta SHALL incluir en `data.variable_env` la variable del `.env` de API-IAM que se debe actualizar (`UBO_LOGIN_CLIENT_SECRET` para `iam-admin-portal`, `UBO_AUTH_CLIENT_SECRET` para `iam-admin-sistema`).

#### Scenario: Rotar el cliente del portal sin confirmar
- **WHEN** se pide la rotación de `iam-admin-portal` sin `confirmar`
- **THEN** responde 422 con el mensaje en `errores.confirmar` y el secreto no cambia

#### Scenario: Rotar el cliente del portal confirmando
- **WHEN** se pide la rotación de `iam-admin-portal` con `{"confirmar": "iam-admin-portal"}`
- **THEN** responde 200 con el secreto nuevo y `data.variable_env` igual a `UBO_LOGIN_CLIENT_SECRET`

### Requirement: Clientes públicos sin secreto
Un cliente sin secreto (público, con PKCE) MUST NOT recibir uno: la rotación MUST responder 409 `CLIENTE_PUBLICO` sin cambiar nada.

#### Scenario: Cliente público
- **GIVEN** un cliente con `secret` nulo
- **WHEN** se pide su rotación
- **THEN** responde 409 `CLIENTE_PUBLICO` y su `secret` sigue nulo
