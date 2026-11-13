## Context

El inicio del portal (`dominios/acceso/paginas/inicio`) solo saluda. Los datos están repartidos:
- **API-IAM**: `users` (`disabled_at`, `password`, `entra_oid`, `temporary_password_expires_at`), el catálogo, los clientes y `iam_auditoria`.
- **API-Auth** (solo lectura para API-IAM; nunca se escriben): `oauth_access_tokens` (`user_id` nulo en tokens de servicio, `client_id`, `created_at`, con índice solo en `user_id`, sin purga) y `sessions` (`user_id`, `ip_address`, `last_activity` en epoch, del login central).
API-Auth no registra logins ni intentos fallidos: eso lo agrega `eventos-de-autenticacion`. La base remota tiene ~120 ms por ida y vuelta.

## Goals / Non-Goals

**Goals:**
- Una vista de revisión de accesos en el inicio, con números que llevan a la acción.
- Pocas consultas agregadas y caché, sin tocar las tablas de API-Auth.

**Non-Goals:**
- Eventos de autenticación (fase 2), alertas, exportación, historial de indicadores.

## Decisions

**D1. Dominio `Indicadores`** con un caso de uso `VerIndicadores` (QueryHandler) y un `IIndicadoresQueryRepository` con un método por bloque. Los datos de personas los lee el propio repositorio con SQL agregado (no por `Contracts/`: son conteos sobre varias tablas, y pasar por los directorios haría N consultas); es lectura pura documentada, igual que la regla «Lecturas por un QueryRepository». Lee tablas de API-Auth solo dentro de este repositorio.

**D2. Actividad = último token.** `MAX(oauth_access_tokens.created_at)` por `user_id`, en una subconsulta agregada unida a `users` (usa el índice por `user_id`). Incluye renovaciones, así que mide uso y no solo login; es la mejor fuente hasta la fase 2 y se nombra «actividad» en la interfaz. Para M2M, `user_id IS NULL` agrupado por `client_id` con filtro de fecha (recorre la tabla: por eso D4).

**D3. Bloques por permiso.** El handler recibe los permisos de quien pide y solo calcula los bloques permitidos; cada bloque es un DTO y el Resource arma `data` con los presentes.

**D4. Caché por bloque, 5 minutos** (`Cache::remember` con clave por bloque), con `calculado_en`. Se cachean los DTO del bloque; como `config/cache.php` bloquea la deserialización de clases (`serializable_classes`), se permiten explícitamente esos DTO y `CarbonImmutable` (objetos de datos, sin riesgo de gadget chains si se filtrara `APP_KEY`). Ningún dato personal sensible: nombres y uuid de administradores inactivos y de personas en `senales`, que ya ve quien tiene `iam:usuarios.leer`.

**D5. Secretos sin rotar.** Fecha de la última `cliente.secreto.rotacion` en `iam_auditoria` por cliente, o `oauth_clients.created_at` si no hay; solo confidenciales y activos.

**D6. Filtro `inactivas`** en `ListarUsuarios` reutilizando la misma subconsulta de D2 (en el repositorio de Usuarios, que lee `oauth_access_tokens` en ese caso), para que el número del indicador y la lista coincidan.

**D7. Frontend.** El inicio carga `GET /indicadores` y muestra, por bloque presente:
- tarjetas con números;
- barras horizontales en CSS (ingresos por día, tokens por cliente, rechazos por motivo);
- listas cortas (administradores inactivos, clientes sin uso).
Cada número enlaza:
- inactividad → `/usuarios?estado=activos&inactivas=…`;
- un cliente → su detalle;
- un motivo de rechazo → `/auditoria?resultado=rechazado`.
Sin librería de gráficos (si más adelante se quieren, ApexCharts sería una dependencia nueva a aprobar).

## Risks / Trade-offs

- [`oauth_access_tokens` crece sin purga y sin índice por fecha] → Caché de 5 minutos; la fase 2 da una fuente indexada y permitirá purgar tokens sin perder historia.
- [Si API-Auth empieza a purgar tokens] → «sin actividad» se volvería impreciso; se documenta la dependencia y la fase 2 la elimina.
- [Leer tablas de otro servicio] → Solo lectura y en un único repositorio; si API-Auth cambia esas tablas, se ajusta aquí.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; el endpoint es nuevo.
