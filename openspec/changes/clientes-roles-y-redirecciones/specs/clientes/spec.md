## ADDED Requirements

### Requirement: Asignar un rol a un cliente de servicio
`POST /api/iam/v1/clientes/{id}/roles` con `aplicacion` y `rol` SHALL asignar ese rol a un cliente `client-credentials` y registrar `cliente.rol.asignacion` con `despues` `{"rol": "<aplicacion>/<rol>"}`, en la misma transacción. MUST responder 201 al asignarlo y 200 si ya lo tenía, sin cambiar ni auditar. Un rol inexistente MUST responder 422 `VALIDACION`. MUST exigir sesión, `iam:clientes.administrar` y CSRF.

#### Scenario: Rol de otra aplicación
- **GIVEN** el cliente de servicio `pago-honorarios-backend`
- **WHEN** un administrador le asigna `api-maestros/maestros-lector`
- **THEN** responde 201, el detalle del cliente muestra ese rol con su aplicación y hay una entrada `cliente.rol.asignacion`

#### Scenario: Sin el permiso
- **GIVEN** la mesa de ayuda, sin `iam:clientes.administrar`
- **WHEN** intenta asignar un rol a un cliente
- **THEN** recibe 403 `ACCESO_DENEGADO`

### Requirement: Quitar un rol a un cliente de servicio
`DELETE /api/iam/v1/clientes/{id}/roles/{aplicacion}/{rol}` SHALL quitar el rol y registrar `cliente.rol.retiro` con `antes` `{"rol": "<aplicacion>/<rol>"}`, en la misma transacción, y responder 200. Si el cliente no tiene ese rol MUST responder 404 `ROL_NO_ASIGNADO`.

#### Scenario: Quitar un rol
- **GIVEN** un cliente de servicio con `api-maestros/maestros-lector`
- **WHEN** se le quita ese rol
- **THEN** responde 200, el rol ya no aparece en su detalle y hay una entrada `cliente.rol.retiro`

### Requirement: Roles solo para clientes de servicio y sin iam-admin
Asignar o quitar roles a un cliente `authorization-code` MUST responder 409 `CLIENTE_SIN_ROLES`, porque sus tokens llevan los roles de la persona. Asignar a un cliente un rol de la aplicación `iam-admin` MUST responder 409 `ROL_DE_IAM_ADMIN`. Ambos rechazos SHALL quedar en la auditoría como `rechazado`, sin cambiar nada.

#### Scenario: Rol de iam-admin a un cliente
- **WHEN** se intenta asignar `iam-admin/administrador` a un cliente de servicio
- **THEN** responde 409 `ROL_DE_IAM_ADMIN` y hay una entrada `cliente.rol.asignacion` con `resultado` `rechazado`

#### Scenario: Rol a un cliente de login
- **WHEN** se intenta asignar un rol a un cliente `authorization-code`
- **THEN** responde 409 `CLIENTE_SIN_ROLES`

### Requirement: Cambiar las redirecciones de un cliente de login
`PUT /api/iam/v1/clientes/{id}/redirecciones` SHALL reemplazar las redirecciones de un cliente `authorization-code` y registrar `cliente.redirecciones.cambio` (`antes` y `despues`) en la misma transacción; si son las mismas, 200 sin cambiar ni auditar. MUST exigir al menos una, cada una `https` (o `http` en `localhost`) sin fragmento (422 `VALIDACION`), y responder 409 `CLIENTE_SIN_REDIRECCIONES` en un cliente `client-credentials`.

#### Scenario: Agregar un ambiente
- **GIVEN** el cliente `satelite-web` con la redirección `https://satelite.ubo.cl/auth/callback`
- **WHEN** se cambian sus redirecciones por esa y `https://satelite-qa.ubo.cl/auth/callback`
- **THEN** responde 200, el detalle muestra las dos y hay una entrada `cliente.redirecciones.cambio` con la lista anterior y la nueva

#### Scenario: Sin redirecciones
- **WHEN** se piden las redirecciones vacías
- **THEN** responde 422 con el mensaje en `errores.redirecciones`

### Requirement: Clientes de iam-admin sin cambios desde el portal
Asignar o quitar roles y cambiar redirecciones de un cliente de la aplicación `iam-admin` MUST responder 409 `CLIENTE_DE_IAM_ADMIN`, y el intento SHALL quedar en la auditoría como `rechazado`.

#### Scenario: Quitar un rol a iam-admin-sistema
- **WHEN** se intenta quitar `api-auth/session-revoker` a `iam-admin-sistema`
- **THEN** responde 409 `CLIENTE_DE_IAM_ADMIN` y el cliente conserva el rol
