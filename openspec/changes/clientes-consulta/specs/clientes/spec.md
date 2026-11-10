## ADDED Requirements

### Requirement: Permiso para consultar clientes
`GET /api/iam/v1/clientes` y `GET /api/iam/v1/clientes/{id}` SHALL exigir la sesión del portal y el permiso `iam:clientes.leer`. Sin sesión MUST responder 401 `NO_AUTENTICADO` y sin el permiso 403 `ACCESO_DENEGADO`, ambos en RFC 9457.

#### Scenario: Administrador consulta clientes
- **GIVEN** una persona con sesión y el permiso `iam:clientes.leer`
- **WHEN** pide `GET /api/iam/v1/clientes`
- **THEN** recibe 200 con el listado

#### Scenario: Persona sin el permiso
- **GIVEN** una persona con sesión cuyos permisos no incluyen `iam:clientes.leer`
- **WHEN** pide el listado o el detalle de un cliente
- **THEN** recibe 403 con `application/problem+json` y `error` igual a `ACCESO_DENEGADO`

#### Scenario: Sin sesión
- **WHEN** se pide `GET /api/iam/v1/clientes` sin la cookie de sesión del portal
- **THEN** la respuesta es 401 con `error` igual a `NO_AUTENTICADO`

### Requirement: Listado paginado de clientes
`GET /api/iam/v1/clientes` SHALL devolver los clientes OAuth en `data`, ordenados por nombre y luego por id, con la paginación en `meta` (`total`, `pagina`, `por_pagina`). Cada elemento MUST traer `id` (en minúsculas), `nombre`, `aplicacion` (nombre de su aplicación), `revocado` y `roles` (cantidad de roles asignados). Los clientes revocados MUST aparecer. La página por omisión es 1 y el tamaño por omisión 20; `por_pagina` MUST aceptar de 1 a 100.

#### Scenario: Primera página
- **GIVEN** 25 clientes
- **WHEN** se pide `GET /api/iam/v1/clientes`
- **THEN** `data` trae los 20 primeros por nombre y `meta` es `{total: 25, pagina: 1, por_pagina: 20}`

#### Scenario: Cliente revocado y conteo de roles
- **GIVEN** el cliente `iam-admin-sistema` de `iam-admin` con 2 roles y un cliente revocado sin roles
- **WHEN** se pide el listado
- **THEN** `iam-admin-sistema` trae `aplicacion` `iam-admin`, `revocado` `false` y `roles` 2, y el revocado aparece con `revocado` `true` y `roles` 0

#### Scenario: Paginación inválida
- **WHEN** se pide `GET /api/iam/v1/clientes?por_pagina=500` o `?pagina=0`
- **THEN** la respuesta es 422 con `error` igual a `VALIDACION` y el mensaje del campo en `errores`

### Requirement: Búsqueda de clientes
El parámetro `buscar` (hasta 100 caracteres) SHALL filtrar los clientes cuyo nombre o el nombre de su aplicación contienen el texto, sin distinguir mayúsculas. Si `buscar` es un UUID, MUST traer además el cliente con ese id. Los comodines de `LIKE` (`%`, `_`, `[`) MUST tratarse como texto.

#### Scenario: Buscar por aplicación
- **GIVEN** un cliente de `pago-honorarios` y otro de `iam-admin`
- **WHEN** se pide `GET /api/iam/v1/clientes?buscar=honor`
- **THEN** `data` trae solo el cliente de `pago-honorarios`

#### Scenario: Buscar por id
- **GIVEN** un cliente con id `0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b`
- **WHEN** se pide `GET /api/iam/v1/clientes?buscar=0199A1B2-C3D4-7E5F-8A9B-0C1D2E3F4A5B`
- **THEN** `data` trae ese cliente

#### Scenario: Comodín como texto
- **WHEN** se pide `GET /api/iam/v1/clientes?buscar=%`
- **THEN** `data` trae solo los clientes cuyo nombre o aplicación contienen `%`

### Requirement: Detalle de un cliente
`GET /api/iam/v1/clientes/{id}` SHALL devolver en `data` el cliente: `id` (en minúsculas), `nombre`, `aplicacion`, `concesiones` (los `grant_types`), `confidencial` (`true` si tiene secreto), `redirecciones`, `revocado`, `creado`, `actualizado` (ISO 8601 o `null`) y `roles` (cada uno con `aplicacion`, `nombre` y `descripcion`, por aplicación y nombre). Un id inválido o inexistente MUST responder 404 `CLIENTE_NO_ENCONTRADO`.

#### Scenario: Cliente client-credentials con roles de otra aplicación
- **GIVEN** el cliente `iam-admin-sistema` de `iam-admin`, con secreto, concesión `client_credentials` y los roles `introspector` y `session-revoker` de `api-auth`
- **WHEN** se pide su detalle
- **THEN** `data` trae `confidencial` `true`, `concesiones` `["client_credentials"]`, `redirecciones` vacía y `roles` con `introspector` y `session-revoker`, ambos con `aplicacion` `api-auth`

#### Scenario: Cliente authorization-code
- **GIVEN** el cliente `iam-admin-portal` con concesiones `authorization_code` y `refresh_token` y una redirección
- **WHEN** se pide su detalle
- **THEN** `data.concesiones` es `["authorization_code", "refresh_token"]`, `data.redirecciones` trae esa redirección y `data.roles` es una lista vacía

#### Scenario: Cliente público
- **GIVEN** un cliente sin secreto
- **WHEN** se pide su detalle
- **THEN** `data.confidencial` es `false`

#### Scenario: Id en mayúsculas
- **WHEN** se pide el detalle con el id del cliente en mayúsculas
- **THEN** la respuesta es 200 y `data.id` va en minúsculas

#### Scenario: Cliente inexistente o id inválido
- **WHEN** se pide `GET /api/iam/v1/clientes/no-es-uuid` o el detalle de un UUID que no existe
- **THEN** la respuesta es 404 con `application/problem+json` y `error` igual a `CLIENTE_NO_ENCONTRADO`

### Requirement: Sin secretos, ids internos ni escritura
Las respuestas de clientes MUST NOT incluir el secreto ni su hash (`secret`), el dueño (`owner_type`, `owner_id`), `provider`, ni los ids numéricos de `applications` y `roles` (`application_id`, `role_id`). La consulta MUST NOT escribir en la base `IAM`.

#### Scenario: Respuesta sin secreto ni ids
- **WHEN** se piden el listado y el detalle de un cliente con secreto
- **THEN** ninguna respuesta contiene las claves `secret`, `secreto`, `application_id`, `role_id` ni `owner_id`, ni el hash del secreto, y la cantidad de filas de `oauth_clients`, `oauth_client_role` e `iam_auditoria` no cambia
