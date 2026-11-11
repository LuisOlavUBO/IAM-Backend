## ADDED Requirements

### Requirement: Permiso para la baja, la reactivación y el reinicio de clave
`POST /api/iam/v1/usuarios/{uuid}/baja`, `POST /api/iam/v1/usuarios/{uuid}/reactivacion` y `POST /api/iam/v1/usuarios/{uuid}/clave` SHALL exigir la sesión del portal, `iam:usuarios.administrar` y el token CSRF. Sin el permiso MUST responder 403 `ACCESO_DENEGADO`, y una persona inexistente 404 `USUARIO_NO_ENCONTRADO`.

#### Scenario: Solo lectura
- **GIVEN** una persona con `iam:usuarios.leer` pero sin `iam:usuarios.administrar`
- **WHEN** pide la baja de otra persona
- **THEN** recibe 403 `ACCESO_DENEGADO` y `disabled_at` no cambia

### Requirement: Dar de baja a una persona
`POST /api/iam/v1/usuarios/{uuid}/baja` SHALL poner `disabled_at`, conservando sus roles, y registrar `usuario.baja` (`{"activo": true}` → `{"activo": false}`) en la misma transacción. Después MUST pedir a API-Auth que revoque sus sesiones y responder 200 con `data.sesiones_revocadas`. Si ya estaba de baja, MUST responder 200 con `sesiones_revocadas` `null`, sin cambiar, auditar ni llamar a API-Auth.

#### Scenario: Baja de una persona activa
- **GIVEN** una persona activa con `pago-honorarios/revisor` y API-Auth disponible
- **WHEN** la mesa de ayuda pide su baja
- **THEN** responde 200 con `sesiones_revocadas` `true`, la persona tiene `disabled_at`, conserva su rol, API-Auth recibió la revocación y hay una entrada `usuario.baja`

#### Scenario: API-Auth no responde
- **GIVEN** API-Auth no disponible
- **WHEN** se da de baja a una persona
- **THEN** responde 200 con `sesiones_revocadas` `false` y la baja queda guardada y auditada

#### Scenario: Persona ya de baja
- **WHEN** se pide la baja de una persona que ya tiene `disabled_at`
- **THEN** responde 200 con `sesiones_revocadas` `null`, `disabled_at` no cambia y no hay entrada nueva en la auditoría

### Requirement: Reactivar a una persona
`POST /api/iam/v1/usuarios/{uuid}/reactivacion` SHALL quitar `disabled_at` y registrar `usuario.alta` con `antes` `{"activo": false}` y `despues` `{"activo": true}`, en la misma transacción, y responder 200. Si la persona ya estaba activa MUST responder 200 sin cambiar nada ni auditar.

#### Scenario: Reactivación
- **GIVEN** una persona de baja con el rol `pago-honorarios/revisor`
- **WHEN** se pide su reactivación
- **THEN** responde 200, `disabled_at` queda en `null`, conserva su rol y hay una entrada `usuario.alta`

### Requirement: Reiniciar la clave
`POST /api/iam/v1/usuarios/{uuid}/clave` SHALL generar una clave temporal nueva que vence a las `IAM_CLAVE_TEMPORAL_HORAS` horas, guardar solo su hash y su vencimiento, registrar `usuario.clave.reinicio` sin valores, en la misma transacción, y después revocar las sesiones de la persona. MUST responder 200 con `data.clave_temporal` (`clave` y `vence`) y `data.sesiones_revocadas`, con `Cache-Control: no-store`. La clave MUST NOT quedar en logs, en la auditoría ni en otra respuesta.

#### Scenario: Reinicio de clave
- **GIVEN** una persona con clave
- **WHEN** la mesa de ayuda reinicia su clave
- **THEN** responde 200 con una clave `xxxx-xxxx-xxxx` que valida contra el hash guardado, la clave anterior ya no valida, `clave_temporal_vence` aparece en el detalle, API-Auth recibió la revocación y la entrada `usuario.clave.reinicio` no contiene la clave ni su hash

#### Scenario: Persona sin clave
- **GIVEN** una persona que entra solo con Microsoft
- **WHEN** se reinicia su clave
- **THEN** responde 200 con una clave temporal y la persona puede entrar también con ella

### Requirement: Sin escalamiento sobre personas de iam-admin
Si la persona tiene algún rol de `iam-admin`, darla de baja, reactivarla o reiniciar su clave MUST exigir que quien actúa tenga todos los permisos de `iam-admin`; si no, MUST responder 403 `ESCALAMIENTO_DE_PRIVILEGIOS`. Cada rechazo SHALL quedar en la auditoría como `rechazado`, sin cambiar nada ni llamar a API-Auth.

#### Scenario: Mesa de ayuda reinicia la clave de un administrador
- **GIVEN** una persona con `iam-admin/administrador` y la mesa de ayuda, sin todos los permisos de `iam-admin`
- **WHEN** la mesa de ayuda intenta reiniciar su clave o darla de baja
- **THEN** responde 403 `ESCALAMIENTO_DE_PRIVILEGIOS`, su clave y `disabled_at` no cambian y hay entradas `rechazado`

### Requirement: Nada sobre uno mismo ni sin administradores
La baja, la reactivación y el reinicio de clave MUST responder 403 `ACCION_SOBRE_SI_MISMO` cuando quien actúa es la misma persona, y la baja de la última persona activa con `iam-admin/administrador` MUST responder 409 `ULTIMO_ADMINISTRADOR`. Ambos rechazos SHALL quedar en la auditoría como `rechazado`, sin cambiar nada ni llamar a API-Auth.

#### Scenario: Darse de baja a uno mismo
- **WHEN** una persona intenta darse de baja o reiniciar su propia clave
- **THEN** responde 403 `ACCION_SOBRE_SI_MISMO`

#### Scenario: Último administrador
- **GIVEN** Ana es la única persona activa con `iam-admin/administrador`
- **WHEN** otra persona con todos los permisos de `iam-admin` pide su baja
- **THEN** responde 409 `ULTIMO_ADMINISTRADOR` y Ana sigue activa
