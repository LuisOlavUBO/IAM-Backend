# aplicaciones Specification

## Purpose
Define cómo el portal consulta las aplicaciones registradas en IAM: el listado con búsqueda y el detalle de cada aplicación con sus permisos y sus roles, y qué permisos da cada rol, sin exponer identificadores internos ni escribir en la base.

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

### Requirement: Permiso para administrar el catálogo
Crear y editar aplicaciones, permisos y roles, y cambiar los permisos de un rol, SHALL exigir la sesión del portal, `iam:aplicaciones.administrar` y el token CSRF. Sin el permiso MUST responder 403 `ACCESO_DENEGADO`, y una aplicación, permiso o rol inexistente en la ruta, 404 (`APLICACION_NO_ENCONTRADA`, `PERMISO_NO_ENCONTRADO`, `ROL_NO_ENCONTRADO`).

#### Scenario: Mesa de ayuda
- **GIVEN** la mesa de ayuda, con `iam:aplicaciones.leer` pero sin `iam:aplicaciones.administrar`
- **WHEN** pide crear una aplicación
- **THEN** recibe 403 `ACCESO_DENEGADO` y no se crea nada

### Requirement: Crear y describir aplicaciones
`POST /api/iam/v1/aplicaciones` con `nombre` y `descripcion` opcional SHALL crear la aplicación y responder 201; `PATCH /api/iam/v1/aplicaciones/{nombre}` con `descripcion` SHALL cambiar su descripción y responder 200. El nombre MUST ser un slug en minúsculas (`^[a-z0-9]+(-[a-z0-9]+)*$`, hasta 100 caracteres; si no, 422) y no repetirse (409 `APLICACION_EXISTENTE`).

#### Scenario: Registrar un sistema nuevo
- **WHEN** un administrador pide `POST /api/iam/v1/aplicaciones` con `{"nombre": "biblioteca", "descripcion": "Sistema de biblioteca"}`
- **THEN** responde 201, `GET /api/iam/v1/aplicaciones/biblioteca` la muestra sin permisos ni roles y hay una entrada `aplicacion.creacion`

#### Scenario: Nombre repetido
- **WHEN** se pide crear `Biblioteca` y ya existe `biblioteca`
- **THEN** responde 422 por el formato (mayúscula) y, con `biblioteca`, 409 `APLICACION_EXISTENTE`

### Requirement: Crear y describir permisos
`POST /api/iam/v1/aplicaciones/{nombre}/permisos` con `nombre` y `descripcion` SHALL crear un permiso de esa aplicación y responder 201; `PATCH /api/iam/v1/aplicaciones/{nombre}/permisos/{permiso}` con `descripcion` SHALL cambiar su descripción. El nombre MUST tener solo minúsculas, dígitos y `:._-`, empezar con letra o dígito, hasta 100 caracteres (si no, 422), y ser único en todo IAM (409 `PERMISO_EXISTENTE`). La descripción es obligatoria.

#### Scenario: Permiso de la biblioteca
- **WHEN** se crea `biblioteca:prestamos.leer` con descripción «Ver préstamos» en `biblioteca`
- **THEN** responde 201, aparece en los permisos de `biblioteca` y hay una entrada `permiso.creacion`

### Requirement: Crear y describir roles
`POST /api/iam/v1/aplicaciones/{nombre}/roles` con `nombre` y `descripcion` opcional SHALL crear un rol de esa aplicación, sin permisos, y responder 201; `PATCH /api/iam/v1/aplicaciones/{nombre}/roles/{rol}` con `descripcion` SHALL cambiar su descripción. El nombre MUST tener letras, dígitos, espacios o `._-`, sin `/`, hasta 100 caracteres (si no, 422), y ser único dentro de la aplicación sin distinguir mayúsculas (409 `ROL_EXISTENTE`).

#### Scenario: Rol de la biblioteca
- **WHEN** se crea el rol `bibliotecario` en `biblioteca`
- **THEN** responde 201, aparece en los roles de `biblioteca` sin permisos y hay una entrada `rol.creacion` con `nombre` `biblioteca/bibliotecario`

### Requirement: Permisos que da un rol
`PUT /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/permisos` con `permisos` (lista de nombres, de cualquier aplicación) SHALL reemplazar los permisos que da el rol y responder 200. Cada permiso agregado SHALL registrar `rol.permiso.asignacion` y cada quitado `rol.permiso.retiro`, con `{"permiso": "<nombre>"}`, en la misma transacción. Un permiso inexistente MUST responder 422 sin cambiar nada. Los cambios rigen en el siguiente token de quienes tienen el rol.

#### Scenario: Dar permisos a un rol
- **GIVEN** el rol `bibliotecario` sin permisos
- **WHEN** se piden sus permisos `["biblioteca:prestamos.leer", "geografia:leer"]`
- **THEN** responde 200, el detalle de `biblioteca` muestra el rol con esos dos permisos y hay dos entradas `rol.permiso.asignacion`

#### Scenario: Quitar un permiso
- **WHEN** se piden los permisos `["biblioteca:prestamos.leer"]` para ese rol
- **THEN** responde 200, el rol da solo ese permiso y hay una entrada `rol.permiso.retiro` con `geografia:leer`

### Requirement: Catálogo de iam-admin protegido
Crear permisos o roles en `iam-admin`, editar la aplicación `iam-admin`, sus permisos o sus roles, cambiar los permisos de un rol de `iam-admin`, y agregar un permiso de `iam-admin` a un rol de otra aplicación MUST responder 409 `CATALOGO_DE_IAM_ADMIN` sin cambiar nada, y el intento SHALL quedar en la auditoría como `rechazado`.

#### Scenario: Ampliar la mesa de ayuda
- **WHEN** un administrador intenta agregar `iam:clientes.administrar` a los permisos de `iam-admin/mesa-de-ayuda`
- **THEN** responde 409 `CATALOGO_DE_IAM_ADMIN`, el rol no cambia y hay una entrada con `resultado` `rechazado`

#### Scenario: Permiso iam en otra aplicación
- **WHEN** se intenta dar `iam:usuarios.administrar` a un rol de `biblioteca`
- **THEN** responde 409 `CATALOGO_DE_IAM_ADMIN`
