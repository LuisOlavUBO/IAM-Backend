## ADDED Requirements

### Requirement: Indicadores según los permisos
`GET /api/iam/v1/indicadores` SHALL exigir la sesión del portal y responder 200 con `data` que contiene solo los bloques que permiten los permisos de quien pide: `entorno` con `iam:aplicaciones.leer`; `personas`, `privilegios` y `senales` con `iam:usuarios.leer`; `clientes` con `iam:clientes.leer`; `auditoria` con `iam:auditoria.leer`. Sin ninguno de esos permisos MUST responder 200 con `data` vacío. Sin sesión, 401 `NO_AUTENTICADO`.

#### Scenario: Mesa de ayuda
- **GIVEN** la mesa de ayuda, con `iam:usuarios.leer` e `iam:aplicaciones.leer`
- **WHEN** pide los indicadores
- **THEN** recibe `entorno`, `personas`, `privilegios` y `senales`, y no `clientes` ni `auditoria`

### Requirement: Actividad de las personas
El bloque `personas` SHALL incluir `activas`, `de_baja` y, entre las activas, `inactivas_30`, `inactivas_90` y `sin_actividad`. La actividad MUST ser la fecha del último access token emitido a la persona (login o renovación). También SHALL incluir `ingresos_por_dia` (30 días, personas distintas con token ese día), `sesiones_abiertas` (actividad en las últimas 2 horas), `claves_temporales_vencidas` y `solo_clave` (con clave y sin Microsoft).

#### Scenario: Persona olvidada
- **GIVEN** una persona activa cuyo último token se emitió hace 100 días
- **WHEN** se piden los indicadores
- **THEN** cuenta en `inactivas_30` y en `inactivas_90`

#### Scenario: Persona que nunca entró
- **GIVEN** una persona activa sin ningún token emitido
- **WHEN** se piden los indicadores
- **THEN** cuenta en `sin_actividad` y no en `inactivas_30`

### Requirement: Administradores de IAM
El bloque `privilegios` SHALL incluir la cantidad de personas activas con `iam-admin/administrador` y la lista (`uuid`, `nombre`, `ultima_actividad`) de las que no tienen actividad hace más de 30 días.

#### Scenario: Administrador inactivo
- **GIVEN** dos administradores activos, uno sin actividad hace 45 días
- **WHEN** se piden los indicadores
- **THEN** `privilegios.administradores` es 2 y `privilegios.administradores_inactivos` lista solo al de 45 días

### Requirement: Clientes y tokens de servicio
El bloque `clientes` SHALL incluir los clientes de login, de servicio y revocados; los tokens de servicio (sin persona) emitidos en las últimas 24 horas y 7 días, en total y por cliente (los diez con más tokens); los clientes de servicio activos sin tokens en los últimos 30 días; los clientes de servicio sin roles; y los clientes confidenciales cuyo secreto no se rota hace más de 180 días (la fecha de la última `cliente.secreto.rotacion`, o la de creación si nunca se rotó).

#### Scenario: Cliente de servicio en desuso
- **GIVEN** un cliente `client-credentials` activo cuyo último token fue hace 40 días
- **WHEN** se piden los indicadores
- **THEN** aparece en `clientes.sin_uso_30`

### Requirement: Entorno, auditoría y señales
El bloque `entorno` SHALL incluir la cantidad de aplicaciones, roles y permisos, los roles sin personas ni clientes, y los cinco roles con más personas. El bloque `auditoria` SHALL incluir los rechazos de los últimos 30 días agrupados por `motivo` y la cantidad de cambios aplicados de los últimos 7 días. El bloque `senales` SHALL listar las personas con sesiones del login central abiertas a la vez desde más de una IP.

#### Scenario: Rechazos del mes
- **GIVEN** tres rechazos `ESCALAMIENTO_DE_PRIVILEGIOS` y uno `ACCION_SOBRE_SI_MISMO` en los últimos 30 días
- **WHEN** se piden los indicadores con `iam:auditoria.leer`
- **THEN** `auditoria.rechazos` es `{"ESCALAMIENTO_DE_PRIVILEGIOS": 3, "ACCION_SOBRE_SI_MISMO": 1}`

### Requirement: Indicadores sin escritura y en caché
Calcular los indicadores MUST NOT escribir en la base `IAM`. La respuesta SHALL guardarse 5 minutos (por bloque) e incluir `meta.calculado_en`. Las respuestas MUST NOT incluir contraseñas, secretos, ids numéricos ni tokens.

#### Scenario: Segunda consulta
- **WHEN** se piden los indicadores dos veces en menos de 5 minutos
- **THEN** la segunda respuesta trae el mismo `meta.calculado_en` y no consulta la base
