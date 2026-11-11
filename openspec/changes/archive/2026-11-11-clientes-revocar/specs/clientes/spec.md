## ADDED Requirements

### Requirement: Revocar un cliente
`POST /api/iam/v1/clientes/{id}/revocacion` SHALL marcar el cliente como revocado y registrar `cliente.revocacion` con `antes` `{"revocado": false}` y `despues` `{"revocado": true}`, en la misma transacción, y responder 200. Si ya estaba revocado MUST responder 200 sin cambiar nada ni auditar. MUST exigir la sesión del portal, `iam:clientes.administrar` y el token CSRF; un cliente inexistente MUST responder 404 `CLIENTE_NO_ENCONTRADO`.

#### Scenario: Revocar un cliente de un sistema
- **GIVEN** el cliente activo `pago-honorarios-backend`
- **WHEN** un administrador pide su revocación
- **THEN** responde 200, el cliente queda con `revocado` `true` en el detalle y hay una entrada `cliente.revocacion`

#### Scenario: Cliente ya revocado
- **WHEN** se revoca un cliente que ya está revocado
- **THEN** responde 200 y no hay entrada nueva en la auditoría

#### Scenario: Sin el permiso
- **GIVEN** la mesa de ayuda, sin `iam:clientes.administrar`
- **WHEN** pide la revocación de un cliente
- **THEN** recibe 403 `ACCESO_DENEGADO` y el cliente no cambia

### Requirement: Reactivar un cliente
`POST /api/iam/v1/clientes/{id}/reactivacion` SHALL quitar la marca de revocado y registrar `cliente.reactivacion` con `antes` `{"revocado": true}` y `despues` `{"revocado": false}`, en la misma transacción, y responder 200. Si ya estaba activo MUST responder 200 sin cambiar nada ni auditar.

#### Scenario: Reactivar un cliente revocado
- **GIVEN** un cliente revocado
- **WHEN** un administrador pide su reactivación
- **THEN** responde 200, el cliente queda con `revocado` `false` y hay una entrada `cliente.reactivacion`

### Requirement: Clientes de iam-admin no se revocan
Revocar un cliente de la aplicación `iam-admin` MUST responder 409 `CLIENTE_DE_IAM_ADMIN` sin cambiar nada, y el intento SHALL quedar en la auditoría como `rechazado` con ese código. Su reactivación sí se permite, para recuperar un cliente revocado por otra vía.

#### Scenario: Revocar el cliente del portal
- **WHEN** un administrador pide revocar `iam-admin-portal`
- **THEN** responde 409 `CLIENTE_DE_IAM_ADMIN`, el cliente sigue activo y hay una entrada `cliente.revocacion` con `resultado` `rechazado`
