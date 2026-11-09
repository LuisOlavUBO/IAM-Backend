# usuarios Specification

## Purpose

Define cómo el portal consulta a las personas de IAM: el listado con búsqueda y el detalle de cada persona con sus roles por aplicación, sin exponer nunca credenciales ni identificadores internos.

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
