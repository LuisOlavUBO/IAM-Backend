## ADDED Requirements

### Requirement: Alta de una persona
`POST /api/iam/v1/usuarios` con `nombre`, `correo`, `rut` (opcional) y `acceso` (`microsoft` o `clave`) SHALL crear la persona con un `uuid` nuevo, el correo en minúsculas y sin espacios, y el RUT normalizado (`12345678-K`). MUST exigir la sesión del portal, `iam:usuarios.administrar` y el token CSRF, y responder 201 con `data.uuid`. Datos inválidos (correo sin formato, RUT con dígito verificador incorrecto, `acceso` desconocido, nombre vacío) MUST responder 422 `VALIDACION`.

#### Scenario: Alta con acceso Microsoft
- **WHEN** la mesa de ayuda pide `POST /api/iam/v1/usuarios` con `{"nombre": "Ana Pérez", "correo": " Ana.Perez@UBO.cl ", "rut": "12.345.678-5", "acceso": "microsoft"}`
- **THEN** responde 201 con `data.uuid`, la persona queda con correo `ana.perez@ubo.cl`, RUT `12345678-5`, sin clave y sin roles, y `data.clave_temporal` es `null`

#### Scenario: RUT inválido
- **WHEN** se pide el alta con `rut` `12.345.678-9`
- **THEN** responde 422 con el mensaje en `errores.rut` y no se crea nada

#### Scenario: Sin el permiso
- **GIVEN** una persona con `iam:usuarios.leer` pero sin `iam:usuarios.administrar`
- **WHEN** pide el alta
- **THEN** recibe 403 `ACCESO_DENEGADO`

### Requirement: Clave temporal mostrada una sola vez
Con `acceso` `clave`, el alta SHALL generar una clave temporal que vence a las `IAM_CLAVE_TEMPORAL_HORAS` horas, guardar solo su hash bcrypt y su vencimiento, y devolverla en `data.clave_temporal` (`clave` y `vence`) en esa única respuesta, con `Cache-Control: no-store`. La clave MUST NOT quedar en logs, en la auditoría ni en ninguna otra respuesta.

#### Scenario: Alta con clave
- **WHEN** se pide el alta con `acceso` `clave`
- **THEN** responde 201 con `data.clave_temporal.clave` de 14 caracteres (`xxxx-xxxx-xxxx`) y `data.clave_temporal.vence` en ISO 8601, el hash guardado valida esa clave con `Hash::check`, y `GET /api/iam/v1/usuarios/{uuid}` muestra `tiene_clave` `true` y `clave_temporal_vence` sin la clave

#### Scenario: La clave no queda en la auditoría
- **WHEN** se da de alta una persona con clave
- **THEN** ninguna fila de `iam_auditoria` contiene la clave temporal ni su hash

### Requirement: Correo y RUT únicos
El alta MUST rechazar un correo que ya usa otra persona (sin distinguir mayúsculas) con 409 `CORREO_EN_USO`, y un RUT que ya usa otra persona con 409 `RUT_EN_USO`, sin crear nada.

#### Scenario: Correo repetido
- **GIVEN** una persona con correo `ana.perez@ubo.cl`
- **WHEN** se pide el alta con `ANA.PEREZ@ubo.cl`
- **THEN** responde 409 `CORREO_EN_USO` y la cantidad de personas no cambia

#### Scenario: RUT repetido
- **GIVEN** una persona con RUT `12345678-5`
- **WHEN** se pide el alta de otra persona con `12.345.678-5`
- **THEN** responde 409 `RUT_EN_USO`

### Requirement: Alta auditada
Cada alta SHALL registrar `usuario.creacion` en la auditoría, sobre la entidad `usuario` con el `uuid` nuevo y `despues` igual a `{nombre, correo, rut, acceso}`, en la misma transacción que la creación.

#### Scenario: Entrada del alta
- **WHEN** la mesa de ayuda da de alta a Ana con acceso Microsoft
- **THEN** hay una entrada `usuario.creacion` con el `uuid` de Ana, el actor de la mesa de ayuda y `despues` `{"nombre": "Ana Pérez", "correo": "ana.perez@ubo.cl", "rut": "12345678-5", "acceso": "microsoft"}`
