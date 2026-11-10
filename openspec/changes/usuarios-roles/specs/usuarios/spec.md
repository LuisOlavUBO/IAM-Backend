## ADDED Requirements

### Requirement: Permiso para administrar roles de personas
`POST /api/iam/v1/usuarios/{uuid}/roles`, `DELETE /api/iam/v1/usuarios/{uuid}/roles/{aplicacion}/{rol}` y `POST /api/iam/v1/usuarios/{uuid}/sesiones/revocar` SHALL exigir la sesión del portal, el permiso `iam:usuarios.administrar` y el token CSRF. Sin sesión MUST responder 401 `NO_AUTENTICADO`, sin el permiso 403 `ACCESO_DENEGADO` y sin el token CSRF 419. Una persona inexistente MUST responder 404 `USUARIO_NO_ENCONTRADO`.

#### Scenario: Persona con solo lectura
- **GIVEN** una persona con `iam:usuarios.leer` pero sin `iam:usuarios.administrar`
- **WHEN** intenta asignar un rol
- **THEN** recibe 403 `ACCESO_DENEGADO` y `role_user` no cambia

#### Scenario: Persona inexistente
- **WHEN** se asigna un rol a un `uuid` que no existe
- **THEN** la respuesta es 404 `USUARIO_NO_ENCONTRADO`

### Requirement: Asignar un rol
`POST /api/iam/v1/usuarios/{uuid}/roles` con `aplicacion` y `rol` (nombres) SHALL asignar ese rol a la persona y registrar `usuario.rol.asignacion` en la auditoría, con la entidad `usuario` y `despues` igual a `{"rol": "<aplicacion>/<rol>"}`, en la misma transacción. MUST responder 201 al asignarlo y 200 si la persona ya lo tenía, sin cambiar nada ni auditar. Un rol inexistente MUST responder 422 `VALIDACION`.

#### Scenario: Mesa de ayuda asigna un rol
- **GIVEN** la mesa de ayuda con `iam:usuarios.administrar` y una persona sin roles en `pago-honorarios`
- **WHEN** pide `POST /api/iam/v1/usuarios/{uuid}/roles` con `{"aplicacion": "pago-honorarios", "rol": "revisor"}`
- **THEN** responde 201, la persona tiene ese rol y hay una entrada `usuario.rol.asignacion` con `despues` `{"rol": "pago-honorarios/revisor"}` y el `uuid` de la mesa de ayuda como actor

#### Scenario: Rol ya asignado
- **WHEN** se asigna un rol que la persona ya tiene
- **THEN** responde 200 y no se agrega ninguna fila a `role_user` ni a `iam_auditoria`

#### Scenario: Rol inexistente
- **WHEN** se asigna `{"aplicacion": "pago-honorarios", "rol": "no-existe"}`
- **THEN** responde 422 con `error` `VALIDACION` y el mensaje en `errores.rol`

### Requirement: Quitar un rol
`DELETE /api/iam/v1/usuarios/{uuid}/roles/{aplicacion}/{rol}` SHALL quitar el rol a la persona y registrar `usuario.rol.retiro` con `antes` igual a `{"rol": "<aplicacion>/<rol>"}`, en la misma transacción. Después de guardar, MUST pedir a API-Auth que revoque las sesiones de la persona y responder 200 con `data.sesiones_revocadas` (`true` o `false`). Si la persona no tiene ese rol, MUST responder 404 `ROL_NO_ASIGNADO`.

#### Scenario: Quitar un rol con revocación
- **GIVEN** una persona con `pago-honorarios/revisor` y API-Auth disponible
- **WHEN** se pide `DELETE /api/iam/v1/usuarios/{uuid}/roles/pago-honorarios/revisor`
- **THEN** responde 200 con `sesiones_revocadas` `true`, la persona ya no tiene el rol, API-Auth recibió `POST /api/auth/v1/users/{uuid}/revoke` y hay una entrada `usuario.rol.retiro`

#### Scenario: API-Auth no responde
- **GIVEN** API-Auth no disponible
- **WHEN** se quita un rol
- **THEN** responde 200 con `sesiones_revocadas` `false`, el rol queda quitado y su entrada de auditoría guardada

#### Scenario: Rol no asignado
- **WHEN** se quita un rol que la persona no tiene
- **THEN** responde 404 `ROL_NO_ASIGNADO` y no se llama a API-Auth

### Requirement: Revocar las sesiones de una persona
`POST /api/iam/v1/usuarios/{uuid}/sesiones/revocar` SHALL pedir a API-Auth que revoque las sesiones de la persona. MUST responder 204 si API-Auth lo confirma, registrando `usuario.sesiones.revocacion`, y 502 `REVOCACION_FALLIDA` si no, sin auditar.

#### Scenario: Reintento exitoso
- **GIVEN** un retiro de rol que respondió `sesiones_revocadas` `false` y API-Auth de nuevo disponible
- **WHEN** se pide `POST /api/iam/v1/usuarios/{uuid}/sesiones/revocar`
- **THEN** responde 204 y hay una entrada `usuario.sesiones.revocacion`

#### Scenario: Reintento fallido
- **GIVEN** API-Auth no disponible
- **WHEN** se pide la revocación
- **THEN** responde 502 con `error` `REVOCACION_FALLIDA`

### Requirement: Sin escalamiento de privilegios
Asignar o quitar un rol de la aplicación `iam-admin` MUST exigir que quien actúa tenga todos los permisos de `iam-admin`; si no, MUST responder 403 `ESCALAMIENTO_DE_PRIVILEGIOS`. Los roles de otras aplicaciones solo exigen `iam:usuarios.administrar`.

#### Scenario: Mesa de ayuda intenta dar un rol de iam-admin
- **GIVEN** la mesa de ayuda, que no tiene `iam:clientes.administrar` ni `iam:auditoria.leer`
- **WHEN** intenta asignar `iam-admin/administrador` a otra persona
- **THEN** responde 403 `ESCALAMIENTO_DE_PRIVILEGIOS`, la persona no recibe el rol y hay una entrada `usuario.rol.asignacion` con `resultado` `rechazado` y `motivo` `ESCALAMIENTO_DE_PRIVILEGIOS`

#### Scenario: Administrador asigna un rol de iam-admin
- **GIVEN** una persona con todos los permisos de `iam-admin`
- **WHEN** asigna `iam-admin/mesa-de-ayuda` a otra persona
- **THEN** responde 201

### Requirement: Nada sobre uno mismo
Nadie MUST asignar ni quitar sus propios roles ni revocar sus propias sesiones: MUST responder 403 `ACCION_SOBRE_SI_MISMO`.

#### Scenario: Cambiar los propios roles
- **WHEN** una persona intenta asignarse o quitarse un rol, o revocar sus propias sesiones
- **THEN** responde 403 `ACCION_SOBRE_SI_MISMO` y no se llama a API-Auth

### Requirement: Último administrador
Quitar `iam-admin/administrador` a la última persona activa (sin `disabled_at`) que lo tiene MUST responder 409 `ULTIMO_ADMINISTRADOR`.

#### Scenario: Último administrador
- **GIVEN** Ana es la única persona activa con `iam-admin/administrador`
- **WHEN** otra persona con todos los permisos de `iam-admin` intenta quitárselo
- **THEN** responde 409 `ULTIMO_ADMINISTRADOR` y Ana conserva el rol

### Requirement: Rechazos auditados
Cada rechazo por `ESCALAMIENTO_DE_PRIVILEGIOS`, `ACCION_SOBRE_SI_MISMO` o `ULTIMO_ADMINISTRADOR` SHALL quedar en la auditoría con la acción intentada, `resultado` `rechazado` y el código como `motivo`, y MUST NOT cambiar `role_user`.

#### Scenario: Intento de quitarse un rol
- **WHEN** una persona intenta quitarse `pago-honorarios/revisor`
- **THEN** hay una entrada `usuario.rol.retiro` sobre ella con `resultado` `rechazado` y `motivo` `ACCION_SOBRE_SI_MISMO`, y conserva el rol
