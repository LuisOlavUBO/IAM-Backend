# auditoria Specification

## Purpose

Define el registro de las acciones de administración de IAM: quién hizo qué, sobre qué entidad, con qué valores antes y después, y si se aplicó o se rechazó. Lo escriben el portal y los comandos, nadie lo modifica, y el portal lo consulta con filtros y con los nombres actuales del actor y de la entidad.

## Requirements

### Requirement: Registro de cada acción aplicada
Cada acción de administración aplicada SHALL dejar una entrada en la auditoría con la fecha, el tipo de actor (`persona` o `comando`), el `uuid` de la persona cuando corresponde, un código de acción estable, el tipo y el identificador de la entidad, los valores anteriores y nuevos, `resultado` igual a `ok` y el `trace_id` de la petición o del comando.

#### Scenario: Acción de un comando
- **WHEN** `iam:grant-admin ana@ubo.cl` asigna el rol de administrador
- **THEN** existe una entrada con actor `comando`, sin `uuid` de actor, la acción `usuario.rol.asignacion.emergencia`, la entidad `usuario` con el `uuid` de Ana, el rol asignado en los valores nuevos y `resultado` igual a `ok`

### Requirement: Registro en la misma transacción
La entrada de una acción aplicada MUST guardarse en la misma transacción que el cambio: si el cambio no se guarda, la entrada tampoco, y si la entrada no se puede guardar, el cambio no se aplica.

#### Scenario: Falla al guardar el cambio
- **WHEN** una acción falla después de escribir su entrada de auditoría
- **THEN** ni el cambio ni la entrada quedan en la base

### Requirement: Registro de intentos rechazados
Una acción rechazada por una regla de negocio SHALL dejar una entrada con `resultado` igual a `rechazado` y el código del motivo, aunque no haya cambio que guardar.

#### Scenario: Intento rechazado
- **WHEN** una acción de administración se rechaza con el código `ESCALAMIENTO_DE_PRIVILEGIOS`
- **THEN** existe una entrada con `resultado` igual a `rechazado` y `motivo` igual a `ESCALAMIENTO_DE_PRIVILEGIOS`

### Requirement: Sin secretos en la auditoría
Los valores anteriores y nuevos MUST incluir solo los campos que cada acción declara como auditables, y MUST NOT contener contraseñas, secretos de clientes ni sus hashes. La generación de un secreto SHALL registrarse solo como acción.

#### Scenario: Instalación
- **WHEN** `iam:install` crea los clientes y un administrador con contraseña temporal
- **THEN** ninguna entrada de la auditoría contiene los secretos, la contraseña temporal ni sus hashes

### Requirement: Auditoría inalterable
API-IAM MUST NOT modificar ni borrar entradas de la auditoría, y sus entradas MUST NOT depender de la existencia de la entidad que describen.

#### Scenario: Entidad borrada
- **GIVEN** una entrada de auditoría sobre un rol
- **WHEN** ese rol se borra
- **THEN** la entrada sigue existiendo sin cambios

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
Cada entrada SHALL traer `ocurrido_en` (ISO 8601), `actor` (`tipo`, `uuid`, `nombre` y `correo`), `accion`, `entidad` (`tipo`, `id` y `nombre`), `resultado`, `motivo`, `antes`, `despues` y `trace_id`. `antes` y `despues` MUST ir como objetos JSON o `null`. El `nombre` y el `correo` del actor MUST venir de Usuarios cuando el actor es una persona que existe, y ser `null` en otro caso.

#### Scenario: Acción de un comando
- **GIVEN** la entrada `usuario.rol.asignacion` de `iam:install` sobre Ana, con `despues` igual a `{"rol": "iam-admin/administrador"}`
- **WHEN** se pide el listado
- **THEN** la entrada trae `actor` `{tipo: "comando", uuid: null, nombre: null, correo: null}`, `entidad` `{tipo: "usuario", id: <uuid de Ana>, nombre: "Ana Pérez"}`, `antes` `null` y `despues` `{rol: "iam-admin/administrador"}`

#### Scenario: Acción de una persona
- **GIVEN** una entrada hecha desde el portal por Ana Pérez (`ana@ubo.cl`)
- **WHEN** se pide el listado
- **THEN** la entrada trae `actor` con `tipo` `persona`, el `uuid` de Ana, `nombre` `Ana Pérez` y `correo` `ana@ubo.cl`

#### Scenario: Actor que ya no existe
- **GIVEN** una entrada de una persona cuyo `uuid` no está en `users`
- **WHEN** se pide el listado
- **THEN** la entrada trae su `uuid` con `nombre` y `correo` `null`

### Requirement: Nombre de la entidad
`entidad.nombre` SHALL ser el nombre actual de la entidad, obtenido del dominio dueño: el de la aplicación, el del rol con su aplicación (`aplicacion/rol`), el del permiso, el de la persona o el del cliente. MUST ser `null` cuando la entidad ya no existe o su tipo no tiene nombre conocido; `entidad.id` MUST seguir siendo el identificador guardado.

#### Scenario: Rol registrado con su id numérico
- **GIVEN** las entradas `rol.permiso.asignacion` sobre el rol 16, que es `mesa-de-ayuda` de `iam-admin`
- **WHEN** se pide el listado
- **THEN** cada una trae `entidad` `{tipo: "rol", id: "16", nombre: "iam-admin/mesa-de-ayuda"}`

#### Scenario: Aplicación, permiso y cliente
- **GIVEN** entradas sobre la aplicación `iam-admin`, el permiso `iam:usuarios.leer` y el cliente `iam-admin-sistema`
- **WHEN** se pide el listado
- **THEN** sus `entidad.nombre` son `iam-admin`, `iam:usuarios.leer` e `iam-admin-sistema`

#### Scenario: Entidad borrada
- **GIVEN** una entrada sobre un rol que ya no existe
- **WHEN** se pide el listado
- **THEN** la entrada trae su `entidad.id` con `entidad.nombre` `null`

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
