## ADDED Requirements

### Requirement: Permiso para consultar la auditoría
`GET /api/iam/v1/auditoria` SHALL exigir la sesión del portal y el permiso `iam:auditoria.leer`. Sin sesión MUST responder 401 `NO_AUTENTICADO` y sin el permiso 403 `ACCESO_DENEGADO`, ambos en RFC 9457.

#### Scenario: Administrador consulta la auditoría
- **GIVEN** una persona con sesión y el permiso `iam:auditoria.leer`
- **WHEN** pide `GET /api/iam/v1/auditoria`
- **THEN** recibe 200 con el listado

#### Scenario: Persona sin el permiso
- **GIVEN** una persona con sesión cuyos permisos no incluyen `iam:auditoria.leer`
- **WHEN** pide `GET /api/iam/v1/auditoria`
- **THEN** recibe 403 con `application/problem+json` y `error` igual a `ACCESO_DENEGADO`

#### Scenario: Sin sesión
- **WHEN** se pide `GET /api/iam/v1/auditoria` sin la cookie de sesión del portal
- **THEN** la respuesta es 401 con `error` igual a `NO_AUTENTICADO`

### Requirement: Listado paginado de la auditoría
`GET /api/iam/v1/auditoria` SHALL devolver las entradas en `data`, de la más reciente a la más antigua, con la paginación en `meta` (`total`, `pagina`, `por_pagina`). La página por omisión es 1 y el tamaño por omisión 20; `por_pagina` MUST aceptar de 1 a 100. Consultar la auditoría MUST NOT escribir en la base `IAM`, tampoco en `iam_auditoria`.

#### Scenario: Más recientes primero
- **GIVEN** 25 entradas de auditoría en fechas distintas
- **WHEN** se pide `GET /api/iam/v1/auditoria`
- **THEN** `data` trae las 20 más recientes, de la más nueva a la más antigua, y `meta` es `{total: 25, pagina: 1, por_pagina: 20}`

#### Scenario: Consultar no deja rastro
- **WHEN** se piden varias páginas de la auditoría
- **THEN** la cantidad de filas de `iam_auditoria` no cambia

### Requirement: Forma de cada entrada
Cada entrada SHALL traer `ocurrido_en` (ISO 8601), `actor` (`tipo`, `uuid`, `nombre` y `correo`), `accion`, `entidad` (`tipo` e `id`), `resultado`, `motivo`, `antes`, `despues` y `trace_id`. `antes` y `despues` MUST ir como objetos JSON o `null`. El `nombre` y el `correo` del actor MUST venir de Usuarios cuando el actor es una persona que existe, y ser `null` en otro caso.

#### Scenario: Acción de un comando
- **GIVEN** la entrada `usuario.rol.asignacion` de `iam:install` sobre Ana, con `despues` igual a `{"rol": "iam-admin/administrador"}`
- **WHEN** se pide el listado
- **THEN** la entrada trae `actor` `{tipo: "comando", uuid: null, nombre: null, correo: null}`, `entidad` `{tipo: "usuario", id: <uuid de Ana>}`, `antes` `null` y `despues` `{rol: "iam-admin/administrador"}`

#### Scenario: Acción de una persona
- **GIVEN** una entrada hecha desde el portal por Ana Pérez (`ana@ubo.cl`)
- **WHEN** se pide el listado
- **THEN** la entrada trae `actor` con `tipo` `persona`, el `uuid` de Ana, `nombre` `Ana Pérez` y `correo` `ana@ubo.cl`

#### Scenario: Actor que ya no existe
- **GIVEN** una entrada de una persona cuyo `uuid` no está en `users`
- **WHEN** se pide el listado
- **THEN** la entrada trae su `uuid` con `nombre` y `correo` `null`

### Requirement: Filtros de la auditoría
El listado SHALL aceptar estos filtros, combinables: `desde` y `hasta` (fechas `AAAA-MM-DD`, ambos días incluidos, en la zona de la aplicación), `actor` (`uuid`), `accion` (prefijo del código, por ejemplo `cliente.`), `entidad_tipo` y `entidad_id` (exactos) y `resultado` (`ok` o `rechazado`). Un filtro inválido MUST responder 422 `VALIDACION`.

#### Scenario: Rango de fechas
- **GIVEN** entradas el 8, el 9 y el 10 de noviembre de 2026
- **WHEN** se pide `GET /api/iam/v1/auditoria?desde=2026-11-09&hasta=2026-11-09`
- **THEN** `data` trae solo las del 9 de noviembre, incluidas las de las 23:59

#### Scenario: Acciones sobre clientes
- **WHEN** se pide `GET /api/iam/v1/auditoria?accion=cliente.`
- **THEN** `data` trae solo entradas cuya acción empieza con `cliente.`

#### Scenario: Historia de una entidad
- **WHEN** se pide `GET /api/iam/v1/auditoria?entidad_tipo=cliente&entidad_id=<id>`
- **THEN** `data` trae solo las entradas de ese cliente

#### Scenario: Intentos rechazados de una persona
- **WHEN** se pide `GET /api/iam/v1/auditoria?actor=<uuid>&resultado=rechazado`
- **THEN** `data` trae solo las entradas rechazadas de esa persona

#### Scenario: Filtro inválido
- **WHEN** se pide `?hasta=2026-11-01&desde=2026-11-09`, `?actor=no-es-uuid` o `?resultado=otro`
- **THEN** la respuesta es 422 con `error` igual a `VALIDACION` y el mensaje del campo en `errores`
