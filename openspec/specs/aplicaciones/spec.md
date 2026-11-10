# aplicaciones Specification

## Purpose
TBD - created by archiving change aplicaciones-consulta. Update Purpose after archive.

## Requirements

### Requirement: Permiso para consultar aplicaciones
`GET /api/iam/v1/aplicaciones` y `GET /api/iam/v1/aplicaciones/{nombre}` SHALL exigir la sesión del portal y el permiso `iam:aplicaciones.leer`. Sin sesión MUST responder 401 `NO_AUTENTICADO` y sin el permiso 403 `ACCESO_DENEGADO`, ambos en RFC 9457.

#### Scenario: Mesa de ayuda consulta aplicaciones
- **GIVEN** una persona con sesión y el permiso `iam:aplicaciones.leer`
- **WHEN** pide `GET /api/iam/v1/aplicaciones`
- **THEN** recibe 200 con el listado

#### Scenario: Persona sin el permiso
- **GIVEN** una persona con sesión cuyos permisos no incluyen `iam:aplicaciones.leer`
- **WHEN** pide el listado o el detalle de una aplicación
- **THEN** recibe 403 con `application/problem+json` y `error` igual a `ACCESO_DENEGADO`

#### Scenario: Sin sesión
- **WHEN** se pide `GET /api/iam/v1/aplicaciones` sin la cookie de sesión del portal
- **THEN** la respuesta es 401 con `error` igual a `NO_AUTENTICADO`

### Requirement: Listado paginado de aplicaciones
`GET /api/iam/v1/aplicaciones` SHALL devolver las aplicaciones en `data`, ordenadas por nombre, con la paginación en `meta` (`total`, `pagina`, `por_pagina`). Cada elemento MUST traer `nombre`, `descripcion` (o `null`), `permisos` (cantidad de permisos de la aplicación) y `roles` (cantidad de roles). La página por omisión es 1 y el tamaño por omisión 20; `por_pagina` MUST aceptar de 1 a 100.

#### Scenario: Primera página
- **GIVEN** 25 aplicaciones
- **WHEN** se pide `GET /api/iam/v1/aplicaciones`
- **THEN** `data` trae las 20 primeras por nombre y `meta` es `{total: 25, pagina: 1, por_pagina: 20}`

#### Scenario: Conteo de permisos y roles
- **GIVEN** la aplicación `iam-admin` con 7 permisos y 2 roles, y `fuas` sin permisos ni roles
- **WHEN** se pide el listado
- **THEN** `iam-admin` trae `permisos` 7 y `roles` 2, y `fuas` trae `permisos` 0 y `roles` 0

#### Scenario: Paginación inválida
- **WHEN** se pide `GET /api/iam/v1/aplicaciones?por_pagina=500` o `?pagina=0`
- **THEN** la respuesta es 422 con `error` igual a `VALIDACION` y el mensaje del campo en `errores`

### Requirement: Búsqueda de aplicaciones
El parámetro `buscar` (hasta 100 caracteres) SHALL filtrar las aplicaciones cuyo nombre o descripción contienen el texto, sin distinguir mayúsculas. Los comodines de `LIKE` (`%`, `_`, `[`) MUST tratarse como texto.

#### Scenario: Buscar por parte de la descripción
- **GIVEN** las aplicaciones `pago-honorarios` («Pago de Honorarios») y `fuas` («FUAS»)
- **WHEN** se pide `GET /api/iam/v1/aplicaciones?buscar=honor`
- **THEN** `data` trae solo `pago-honorarios`

#### Scenario: Comodín como texto
- **WHEN** se pide `GET /api/iam/v1/aplicaciones?buscar=%`
- **THEN** `data` trae solo las aplicaciones cuyo nombre o descripción contienen `%`

### Requirement: Detalle de una aplicación
`GET /api/iam/v1/aplicaciones/{nombre}` SHALL devolver en `data` la aplicación: `nombre`, `descripcion`, `creada`, `actualizada`, `permisos` y `roles`. `permisos` MUST listar los permisos de la aplicación (`nombre` y `descripcion`), ordenados por nombre. `roles` MUST listar sus roles por nombre, cada uno con `nombre`, `descripcion` y `permisos` (los nombres de los permisos que da, ordenados). Las fechas MUST ir en ISO 8601 o `null`.

#### Scenario: Aplicación con permisos y roles
- **GIVEN** `iam-admin` con sus siete permisos `iam:*`, el rol `administrador` que da los siete y el rol `mesa-de-ayuda` que da tres
- **WHEN** se pide `GET /api/iam/v1/aplicaciones/iam-admin`
- **THEN** `data.permisos` trae los siete por nombre y `data.roles` trae `administrador` con los siete y `mesa-de-ayuda` con `iam:aplicaciones.leer`, `iam:usuarios.administrar` e `iam:usuarios.leer`

#### Scenario: Rol sin permisos
- **GIVEN** una aplicación con un rol que no da ningún permiso
- **WHEN** se pide su detalle
- **THEN** ese rol trae `permisos` como lista vacía

#### Scenario: Nombre en mayúsculas
- **WHEN** se pide `GET /api/iam/v1/aplicaciones/IAM-ADMIN`
- **THEN** la respuesta es 200 y `data.nombre` es `iam-admin`

#### Scenario: Aplicación inexistente
- **WHEN** se pide `GET /api/iam/v1/aplicaciones/no-existe`
- **THEN** la respuesta es 404 con `application/problem+json` y `error` igual a `APLICACION_NO_ENCONTRADA`

### Requirement: Sin identificadores internos ni escritura
Las respuestas de aplicaciones MUST NOT incluir el `id` numérico de `applications`, `oauth_scopes` ni `roles`, ni `application_id`. La consulta MUST NOT escribir en la base `IAM`.

#### Scenario: Respuesta sin ids
- **WHEN** se piden el listado y el detalle de una aplicación
- **THEN** ninguna respuesta contiene las claves `id` ni `application_id`, y la cantidad de filas de `applications`, `oauth_scopes`, `roles`, `oauth_scope_role` e `iam_auditoria` no cambia
