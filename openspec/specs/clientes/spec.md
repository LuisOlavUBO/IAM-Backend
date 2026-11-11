# clientes Specification

## Purpose
Define cómo el portal consulta los clientes OAuth registrados en IAM (el listado con búsqueda y el detalle de cada cliente con su aplicación, sus concesiones, sus redirecciones y sus roles, sin exponer el secreto ni identificadores internos y sin escribir en la base) y cómo rota el secreto de un cliente confidencial: el secreto nuevo se muestra una sola vez y la rotación queda en la auditoría sin su valor.

## Requirements

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
