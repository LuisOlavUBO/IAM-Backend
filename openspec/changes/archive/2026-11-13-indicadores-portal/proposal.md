# Proposal

## Why

El inicio del portal hoy solo saluda. Quien administra IAM no tiene una vista de conjunto: cuántas personas y sistemas hay, quiénes no usan su cuenta hace meses (cuentas olvidadas que siguen activas), si hay administradores inactivos, qué clientes de servicio ya no se usan o no rotan su secreto, y qué se rechazó en la auditoría. Son las primeras preguntas de una revisión de accesos, y hoy exigen consultar la base.

## What Changes

- `GET /api/iam/v1/indicadores`: los indicadores del entorno en una sola respuesta, con bloques que dependen de los permisos de quien pide (sin permiso nuevo):
  - **Entorno** (`iam:aplicaciones.leer`): aplicaciones, roles y permisos; roles sin nadie asignado; los roles con más personas.
  - **Personas** (`iam:usuarios.leer`): activas y de baja; activas **sin actividad hace más de 30 y de 90 días** y **sin actividad nunca**; ingresos por día de los últimos 30 días; sesiones abiertas ahora en el login central; claves temporales vencidas; personas que entran solo con clave.
  - **Privilegios** (`iam:usuarios.leer`): administradores de IAM, y cuáles llevan más de 30 días sin actividad.
  - **Clientes y M2M** (`iam:clientes.leer`): clientes de login y de servicio, revocados; tokens de servicio emitidos en 24 horas y 7 días, por cliente; **clientes de servicio sin uso hace más de 30 días**; clientes de servicio sin roles; secretos sin rotar hace más de 180 días.
  - **Auditoría** (`iam:auditoria.leer`): rechazos de los últimos 30 días por motivo y cambios administrativos de los últimos 7 días.
  - **Señales** (`iam:usuarios.leer`): personas con sesiones abiertas desde más de una IP a la vez (señal débil, hasta tener `eventos-de-autenticacion` de API-Auth).
- «Actividad» es el último token emitido a la persona (login o renovación), leído de `oauth_access_tokens` de API-Auth (solo lectura); las sesiones, de `sessions` (solo lectura). La respuesta se calcula con consultas agregadas y se guarda 5 minutos.
- `GET /api/iam/v1/usuarios` acepta `inactivas=30|90|nunca` para llegar a la lista desde un indicador (con `estado=activos`).
- Frontend (IAM-Frontend): el inicio pasa a ser un dashboard con tarjetas y barras simples en CSS (sin librería de gráficos), con cada indicador enlazado a su lista (personas inactivas, cliente, auditoría filtrada).
- Fuera de este cambio: intentos fallidos, IPs nuevas y fuerza bruta (requieren API-Auth `eventos-de-autenticacion`, que viene después), exportar y alertas por correo.

## Capabilities

### New Capabilities

- `indicadores`: los indicadores del entorno IAM que muestra el inicio del portal.

### Modified Capabilities

- `usuarios`: el listado filtra por inactividad.

## Impact

- Backend: dominio nuevo `app/Domains/Indicadores/` (caso de uso `VerIndicadores`, repositorio de lectura que agrega datos de IAM y de API-Auth), filtro de inactividad en `ListarUsuarios`, tests, OpenAPI y Bruno.
- Frontend: `dominios/acceso/paginas/inicio` (o un dominio `indicadores`) y el filtro en `lista-usuarios`; tests.
- Datos: solo lectura de tablas de API-IAM y de `oauth_access_tokens` y `sessions` de API-Auth (nunca se escriben). Sin migraciones ni dependencias nuevas.
- API-Auth: sin cambios. `oauth_access_tokens` no tiene índice por `client_id` ni por fecha: las agregaciones recorren la tabla, de ahí la caché; el cambio `eventos-de-autenticacion` dará una fuente indexada.
