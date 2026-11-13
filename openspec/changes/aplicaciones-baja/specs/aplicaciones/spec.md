## ADDED Requirements

### Requirement: Dar de baja y reactivar una aplicación
`POST …/aplicaciones/{nombre}/baja` SHALL responder 409 `BAJA_SIN_CONFIRMAR` con `impacto` sin `confirmar_baja: true`; con ella SHALL poner `disabled_at`, auditar `aplicacion.baja` y responder 200. `POST …/reactivacion` SHALL quitar `disabled_at` y auditar `aplicacion.alta`. Ambos MUST ser idempotentes y no tocar permisos, roles, clientes ni asignaciones. MUST exigir sesión, `iam:aplicaciones.administrar` y CSRF.

#### Scenario: Retirar un sistema
- **GIVEN** la aplicación `fuas`, con 2 clientes y 5 personas con roles
- **WHEN** se pide su baja sin confirmar y luego confirmando
- **THEN** primero responde 409 `BAJA_SIN_CONFIRMAR` con ese impacto; después 200, el detalle muestra `baja_desde`, sus roles y clientes siguen asignados y hay una entrada `aplicacion.baja`

#### Scenario: Reactivar
- **WHEN** se reactiva `fuas`
- **THEN** responde 200, `baja_desde` es `null` y hay una entrada `aplicacion.alta`

### Requirement: Dar de baja y reactivar un rol
`POST /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/baja` y `/reactivacion` SHALL comportarse como los de la aplicación, sobre `roles.disabled_at`, con las acciones `rol.baja` y `rol.alta` y el `impacto` del rol (personas y clientes que lo tienen y sus permisos). La baja MUST conservar las personas y clientes que tienen el rol y los permisos que da.

#### Scenario: Retirar un rol
- **GIVEN** el rol `pago-honorarios/revisor`, que tienen 2 personas
- **WHEN** se da de baja confirmando
- **THEN** responde 200, el rol aparece con `baja_desde` en el detalle, las 2 personas lo siguen teniendo asignado y hay una entrada `rol.baja`

### Requirement: Estado en las consultas
El listado y el detalle de aplicaciones SHALL incluir `baja_desde` (ISO 8601 o `null`) para la aplicación, y el detalle, `baja_desde` para cada rol.

#### Scenario: Aplicación de baja en el listado
- **GIVEN** `fuas` de baja y `pago-honorarios` activa
- **WHEN** se pide el listado
- **THEN** `fuas` trae `baja_desde` con la fecha y `pago-honorarios` trae `null`

### Requirement: iam-admin no se da de baja
Dar de baja la aplicación `iam-admin` o uno de sus roles MUST responder 409 `CATALOGO_DE_IAM_ADMIN` sin cambiar nada, y el intento SHALL quedar en la auditoría como `rechazado`.

#### Scenario: Baja de mesa-de-ayuda
- **WHEN** se intenta dar de baja `iam-admin/mesa-de-ayuda` confirmando
- **THEN** responde 409 `CATALOGO_DE_IAM_ADMIN` y el rol sigue activo
