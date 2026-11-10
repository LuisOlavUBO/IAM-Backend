# usuarios Specification

## Purpose

Define cómo el portal consulta y administra a las personas de IAM: el listado con búsqueda, el detalle de cada persona con sus roles por aplicación y la asignación y el retiro de roles con sus protecciones, sin exponer nunca credenciales ni identificadores internos.

## Requirements

### Requirement: Permiso para consultar usuarios
`GET /api/iam/v1/usuarios` y `GET /api/iam/v1/usuarios/{uuid}` SHALL exigir la sesión del portal y el permiso `iam:usuarios.leer`. Sin sesión MUST responder 401 `NO_AUTENTICADO` y sin el permiso 403 `ACCESO_DENEGADO`, ambos en RFC 9457.

#### Scenario: Mesa de ayuda consulta usuarios
- **GIVEN** una persona con sesión y el permiso `iam:usuarios.leer`
- **WHEN** pide `GET /api/iam/v1/usuarios`
- **THEN** recibe 200 con el listado

#### Scenario: Persona sin el permiso
- **GIVEN** una persona con sesión cuyos permisos no incluyen `iam:usuarios.leer`
- **WHEN** pide `GET /api/iam/v1/usuarios` o el detalle de una persona
- **THEN** recibe 403 con `application/problem+json` y `error` igual a `ACCESO_DENEGADO`

#### Scenario: Sin sesión
- **WHEN** se pide `GET /api/iam/v1/usuarios` sin la cookie de sesión del portal
- **THEN** la respuesta es 401 con `error` igual a `NO_AUTENTICADO`

### Requirement: Listado paginado de usuarios
`GET /api/iam/v1/usuarios` SHALL devolver las personas en `data`, ordenadas por nombre, con la paginación en `meta` (`total`, `pagina`, `por_pagina`). Cada elemento MUST traer `uuid` en minúsculas, `nombre`, `correo`, `rut` (normalizado o `null`) y `estado` (`activo` o `baja`). La página por omisión es 1 y el tamaño por omisión 20; `por_pagina` MUST aceptar de 1 a 100.

#### Scenario: Primera página
- **GIVEN** 25 personas en `users`
- **WHEN** se pide `GET /api/iam/v1/usuarios`
- **THEN** `data` trae las 20 primeras por nombre y `meta` es `{total: 25, pagina: 1, por_pagina: 20}`

#### Scenario: Página siguiente
- **GIVEN** 25 personas en `users`
- **WHEN** se pide `GET /api/iam/v1/usuarios?pagina=2`
- **THEN** `data` trae las 5 restantes y `meta.pagina` es 2

#### Scenario: Paginación inválida
- **WHEN** se pide `GET /api/iam/v1/usuarios?por_pagina=500` o `?pagina=0`
- **THEN** la respuesta es 422 con `error` igual a `VALIDACION` y el mensaje del campo en `errores`

### Requirement: Búsqueda de usuarios
El parámetro `buscar` (hasta 100 caracteres) SHALL filtrar las personas cuyo nombre o correo contienen el texto, sin distinguir mayúsculas. Si `buscar` es un RUT válido, con o sin puntos y guion, MUST incluir a la persona con ese RUT. El parámetro `estado` SHALL aceptar `activos`, `baja` o `todos` (por omisión `todos`).

#### Scenario: Buscar por parte del nombre
- **GIVEN** las personas "Ana Pérez" y "Juan Soto"
- **WHEN** se pide `GET /api/iam/v1/usuarios?buscar=pér`
- **THEN** `data` trae solo a "Ana Pérez"

#### Scenario: Buscar por correo
- **WHEN** se pide `GET /api/iam/v1/usuarios?buscar=ANA@UBO`
- **THEN** `data` trae a la persona cuyo correo es `ana@ubo.cl`

#### Scenario: Buscar por RUT con puntos
- **GIVEN** una persona con RUT `12345678-5`
- **WHEN** se pide `GET /api/iam/v1/usuarios?buscar=12.345.678-5`
- **THEN** `data` trae a esa persona

#### Scenario: Solo personas dadas de baja
- **GIVEN** una persona activa y otra con `disabled_at`
- **WHEN** se pide `GET /api/iam/v1/usuarios?estado=baja`
- **THEN** `data` trae solo a la persona dada de baja, con `estado` igual a `baja`

#### Scenario: Estado inválido
- **WHEN** se pide `GET /api/iam/v1/usuarios?estado=borrados`
- **THEN** la respuesta es 422 con `error` igual a `VALIDACION`

### Requirement: Detalle de un usuario
`GET /api/iam/v1/usuarios/{uuid}` SHALL devolver en `data` los datos de la persona: `uuid`, `nombre`, `correo`, `rut`, `estado`, `baja_desde`, `tiene_clave`, `microsoft_vinculado`, `clave_temporal_vence`, `creado`, `actualizado` y `aplicaciones`. `aplicaciones` MUST listar, ordenadas por nombre, las aplicaciones donde la persona tiene roles, cada una con su `nombre`, `descripcion` y sus `roles` (`nombre` y `descripcion`, ordenados por nombre). Las fechas MUST ir en ISO 8601 o `null`.

#### Scenario: Persona con roles en dos aplicaciones
- **GIVEN** una persona con el rol `administrador` de `iam-admin` y el rol `user` de `api-auth`
- **WHEN** se pide su detalle
- **THEN** `data.aplicaciones` trae `api-auth` con `[user]` y luego `iam-admin` con `[administrador]`

#### Scenario: Persona sin roles
- **GIVEN** una persona sin filas en `role_user`
- **WHEN** se pide su detalle
- **THEN** `data.aplicaciones` es una lista vacía

#### Scenario: Persona dada de baja con clave temporal
- **GIVEN** una persona con `disabled_at`, contraseña y `temporary_password_expires_at`
- **WHEN** se pide su detalle
- **THEN** `estado` es `baja`, `baja_desde` y `clave_temporal_vence` traen esas fechas y `tiene_clave` es `true`

#### Scenario: UUID en mayúsculas
- **WHEN** se pide el detalle con el `uuid` de una persona escrito en mayúsculas
- **THEN** la respuesta es 200 y `data.uuid` va en minúsculas

#### Scenario: Persona inexistente
- **WHEN** se pide `GET /api/iam/v1/usuarios/{uuid}` con un `uuid` que no existe o no tiene formato de UUID
- **THEN** la respuesta es 404 con `application/problem+json` y `error` igual a `USUARIO_NO_ENCONTRADO`

### Requirement: Sin credenciales ni identificadores internos
Las respuestas de usuarios MUST NOT incluir la contraseña ni su hash, `remember_token`, `entra_oid` ni el `id` numérico de `users`, `roles` o `applications`. La consulta MUST NOT escribir en la base `IAM`.

#### Scenario: Respuesta sin secretos
- **GIVEN** una persona con contraseña, `remember_token` y `entra_oid`
- **WHEN** se piden el listado y su detalle
- **THEN** ninguna respuesta contiene esos valores ni las claves `password`, `remember_token`, `entra_oid` o `id`, y `microsoft_vinculado` es `true`

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
