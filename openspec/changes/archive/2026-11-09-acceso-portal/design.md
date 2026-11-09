# Design

## Context

Ver `proposal.md` → Why. Requisitos: `specs/acceso-portal`, `specs/comandos-de-operacion` y `specs/auditoria`.

Estado y restricciones que dan forma al enfoque:

- **El esqueleto** monta los dominios (`app/Domains/*/routes/v1.php`) desde `routes/api.php`, en el grupo `api` (sin sesión). `ProblemRenderer` atiende `api/*` y `shouldRenderJsonWhen` es verdadero para `api/*`. La sesión y la caché son `file`. El modelo `User` de Laravel se eliminó.
- **Módulo Login de `ubo/common` 3.3** (apagado por defecto):
  - registra `GET <prefijo>/login`, `GET <prefijo>/callback` y `POST <prefijo>/logout` en el grupo `web`, el guard `ubo`, el middleware `ubo.login` y una regla `Gate::before` que convierte los scopes del token en habilidades (`can:iam:usuarios.leer`);
  - guarda los tokens cifrados en una bóveda en la caché, renueva el token y revalida contra IAM cada `UBO_LOGIN_REVALIDATE` segundos con la estrategia de `UBO_AUTH_STRATEGY` y las credenciales M2M;
  - sin sesión, `ubo.login` lanza `AuthenticationException`, y el callback lanza `LoginFailed` (400 `invalid_state` o `rejected`, 403 `denied`), `NoAccess` (403, sin roles en la aplicación) o `AuthServerUnavailable` (503);
  - `UboUser` trae `id` (uuid), roles, scopes y audiencias, pero no nombre ni correo;
  - los tests usan `UboLogin::actingAs()`.
- **API-Auth** (con `account-lifecycle` desplegado):
  - su `RoleScopeSeeder` define el catálogo base de `api-auth`: permisos `user:read`, `user:write`, `introspect` y `sessions:revoke`, y roles `user`, `admin` (ambos con `user:*`), `introspector` y `session-revoker`;
  - crea clientes con los campos que replica `Application::createClient`;
  - impide el código a quien no tiene roles en la aplicación del cliente, y obliga a cambiar la contraseña temporal antes de entregarlo.
- **Common Tracing** expone `TraceContext::traceId()` y `begin()`. En HTTP la traza la inicia su middleware; en consola no hay middleware.

## Goals / Non-Goals

**Goals:**
- Que el Angular del mismo origen inicie y cierre sesión, sepa quién es la persona y qué puede ver, sin tocar un token.
- Que un ambiente nuevo quede operativo con `migrate` + `iam:install`, sin SQL a mano.
- Dejar una sola forma de auditar, que usarán todos los cambios siguientes.

**Non-Goals:**
- Endpoints de administración (usuarios, catálogo, clientes) y la consulta de la auditoría.
- Revocar sesiones de otras personas (llega con `usuarios`); aquí el cliente M2M solo se crea y se usa para revalidar.
- Varias réplicas del contenedor (la bóveda en `file` asume una).

## Decisions

### D1. Rutas del portal en el grupo `web`, mismo prefijo

`routes/web.php` define `Route::prefix('api/iam/v1')->middleware('ubo.login')->group(...)` con `me` y el `require` de cada `app/Domains/<Dominio>/routes/v1.php`. `routes/api.php` deja de montar los dominios y conserva `v1/salud`, los docs y, en el futuro, los endpoints M2M con `ubo.auth`. El módulo Login registra `api/iam/v1/auth/*` con `UBO_LOGIN_ROUTE_PREFIX=api/iam/v1/auth`.

- **Por qué:** el BFF identifica a la persona con la cookie de sesión, y eso exige el grupo `web` (cookies cifradas, sesión y CSRF). Es el patrón de PagoHonorarios.
- **Errores sin cambios:** las rutas siguen bajo `api/*`, así que `ProblemRenderer` responde en RFC 9457. `AuthenticationException` → 401 `NO_AUTENTICADO`, sin redirigir; `can:` sin el scope → 403.
- **Alternativa descartada:** agregar sesión y CSRF al grupo `api`. Mezcla dos modelos en un grupo y complica los endpoints M2M que vienen.

### D2. CSRF compatible con Angular

Se usa `ValidateCsrfToken` de Laravel, que ya acepta `X-XSRF-TOKEN` (con el valor de la cookie `XSRF-TOKEN`) y `X-CSRF-TOKEN`. Angular `HttpClient` lee `XSRF-TOKEN` y envía `X-XSRF-TOKEN` en las peticiones a URLs relativas, sin configurar nada. `/me` devuelve además `csrf`, que Angular usa en el formulario `POST` del logout (una navegación completa, no `HttpClient`).

### D3. Errores del login: redirección a Angular

Las excepciones del callback ocurren en una navegación del navegador, no en una llamada de `HttpClient`, así que una respuesta JSON quedaría en pantalla. En `bootstrap/app.php`, para las rutas `api/iam/v1/auth/*`, se renderizan como un 302 a `UBO_LOGIN_HOME` con `?error_login=<código>`:

| Excepción | Código |
|---|---|
| `NoAccess`, `LoginFailed` 403 (`denied`) | `SIN_ACCESO` |
| `LoginFailed` 400 (`invalid_state`, `rejected`) | `LOGIN_INVALIDO` |
| `AuthServerUnavailable` | `IAM_NO_DISPONIBLE` |

Angular muestra el mensaje según el código. `LoginNotConfigured` y los errores inesperados siguen como 500 RFC 9457: son fallas de operación, no de la persona.

### D4. `/me` en el dominio `Acceso`

Un controlador invocable en `app/Domains/Acceso` arma la respuesta con `UboUser` (uuid, roles y scopes) más `name` y `email` de `users`, leídos por `uuid` con el query builder (sin modelo Eloquent todavía). El `uuid` se normaliza a minúsculas. Si la persona no existe en `users` (no debería: el token sale de esa tabla), `nombre` y `correo` van en `null`.

### D5. Revalidación con `hybrid`

`.env.example`: `UBO_AUTH_STRATEGY=hybrid`, credenciales M2M (`UBO_AUTH_CLIENT_ID` y `UBO_AUTH_CLIENT_SECRET`, las que muestra `iam:install`), `UBO_LOGIN_REVALIDATE=300` y `UBO_AUTH_DEGRADED_MODE=false`.

- **Por qué:** con `jwt` una baja recién se notaría al renovar el token (hasta 60 minutos), y el portal de administración es justo donde importa cortar antes.
- **`UBO_AUTH_DEGRADED_MODE=false`:** si la introspección se cae, la revalidación no pasa por alto una revocación. Common igual tolera que IAM no responda y sigue con el token vigente.

### D6. Auditoría: tabla y un solo servicio

La migración `crear_iam_auditoria` crea la tabla (columnas como en la exploración: `id`, `ocurrido_en` datetime2, `actor_tipo`, `actor_uuid` uniqueidentifier nullable, `accion`, `entidad_tipo`, `entidad_id`, `resultado`, `motivo` nullable, `antes` y `despues` `nvarchar(max)` nullable, `trace_id` char(32) nullable), con índices `(entidad_tipo, entidad_id, ocurrido_en)`, `(actor_uuid, ocurrido_en)` y `(ocurrido_en)`, y sin FK.

`App\Domains\Auditoria\Auditoria` es el único punto de escritura:
- `registrar(accion, entidadTipo, entidadId, antes, despues)`: exige una transacción abierta (`DB::transactionLevel() > 0`) y lanza `LogicException` si no la hay, para que nadie audite fuera de la transacción del cambio.
- `rechazar(accion, entidadTipo, entidadId, motivo)`: escribe sola, fuera de cualquier transacción.
- El actor sale del guard `ubo` (persona) o, si no hay, es `comando`. El `trace_id` sale de `TraceContext::traceId()`; los comandos llaman a `begin()` al empezar.
- `antes` y `despues` se reciben ya filtrados: cada acción pasa solo sus campos auditables. Además, `registrar` rechaza con `LogicException` cualquier clave `password`, `secret` o `secreto`, como segunda barrera.

Las reglas de permisos de base (`INSERT` y `SELECT` sin `UPDATE` ni `DELETE`) se aplican con los `GRANT` del segundo cambio de API-Auth. Hasta entonces, "inalterable" lo cumple el código: no hay ninguna operación de actualización ni borrado.

### D7. `iam:install`

`app/Console/Commands/InstallCommand.php`, firma `iam:install {--admin-email=} {--admin-nombre=} {--admin-rut=} {--admin-acceso=clave} {--redirect=*}`.

1. Valida las opciones antes de preguntar: email válido; nombre obligatorio solo si la persona no existe; RUT normalizado y válido (regla `Rut` copiada de API-Auth, junto con un normalizador); `acceso` en `clave|microsoft`; al menos una `--redirect`, absoluta, sin fragmento y `https` salvo `localhost` o `*.localhost`.
2. Muestra `APP_ENV`, `DB_HOST` y `DB_DATABASE` y pide confirmación (`confirm`). Falla si existe `iam-admin`.
3. En `DB::transaction`:
   - asegura `api-auth` y su catálogo (crear lo que falte; las asignaciones rol-permiso con inserción condicional);
   - crea `iam-admin`, sus permisos, los roles con sus permisos y los dos clientes (`iam-admin-portal` e `iam-admin-sistema`, con los mismos campos que `Application::createClient` de API-Auth y secreto de 40 caracteres con `Str::random`, guardado con `Hash::make`);
   - asigna `session-revoker` e `introspector` al cliente M2M;
   - crea o reutiliza al administrador y le asigna `administrador`;
   - audita cada creación (actor `comando`).
4. Recién al confirmar la transacción imprime el bloque `.env` y, si corresponde, la contraseña temporal.

- **Contraseña temporal:** 12 caracteres de un alfabeto sin ambiguos (sin `0`, `O`, `1`, `l`, `I`), en tres grupos de 4 separados por guion, generados con `random_int`. Vence a las `IAM_CLAVE_TEMPORAL_HORAS` horas (72 por defecto, `config/iam.php`).
- **UUID:** `Str::uuid7()` en minúsculas.
- **Por qué un comando con escrituras directas por query builder:** los modelos del catálogo y de los clientes llegan con sus cambios (`catalogo`, `clientes-oauth`). Crearlos aquí solo para el instalador adelantaría diseño sin uso real (KISS). La lógica de creación de clientes y de contraseñas temporales se escribe en clases pequeñas de su dominio (`Clientes\NuevoCliente`, `Usuarios\ClaveTemporal`), que esos cambios reutilizarán.

### D8. `iam:grant-admin`

`iam:grant-admin {email} {--reactivar}`: confirma, busca a la persona (falla si no existe), falla si está dada de baja salvo con `--reactivar` (que pone `disabled_at` en `null`), asigna `iam-admin/administrador` si no lo tiene, y audita `usuario.rol.asignacion.emergencia` (más `usuario.alta` si reactivó), todo en una transacción. No toca la contraseña.

### D9. Configuración del módulo Login

`.env.example`: `UBO_COMMON_LOGIN=true`, `UBO_LOGIN_ROUTE_PREFIX=api/iam/v1/auth`, `UBO_LOGIN_PUBLIC_URL`, `UBO_LOGIN_CLIENT_ID`, `UBO_LOGIN_CLIENT_SECRET`, `UBO_LOGIN_HOME=/` y `UBO_LOGIN_STORE=file`. `APP_URL` es el origen del Angular, porque de ahí sale la `redirect_uri`. En desarrollo es `http://localhost:4200`, con el proxy de `ng serve` enviando `/api/iam` a `:8003`. El README documenta esa configuración y que la `redirect_uri` del ambiente debe estar entre las `--redirect` de `iam:install`.

## Risks / Trade-offs

- [Se ejecuta `iam:install` en un ambiente donde ya existe `api-auth` con otro catálogo] → solo agrega lo que falta y nunca quita ni cambia (spec). Los roles `api-auth/user` y `api-auth/admin` se aseguran por compatibilidad con el seeder actual, aunque API-IAM no los use.
- [Se pierde la salida de `iam:install` antes de copiar los secretos] → no hay forma de volver a verlos. La recuperación es `iam:rotate-secret` (cambio `clientes-oauth`). Mientras no exista, el procedimiento de emergencia del README rota el secreto con un hash generado a mano. Se documenta en el README.
- [`UBO_AUTH_DEGRADED_MODE=false` con la introspección caída] → Common tolera la caída y sigue con el token vigente (no expulsa a nadie); solo se pierde la detección inmediata de bajas mientras dure.
- [Angular servido en otro origen] → la cookie, el CSRF y el retorno del logout fallan. El mismo origen es requisito y está en `proposal.md` → Impact.
- [Auditoría escrita fuera de transacción por error] → `registrar` lanza `LogicException` sin una transacción abierta.

## Migration Plan

1. Desplegar API-IAM con este cambio y ejecutar a mano `php artisan migrate` (crea `iam_auditoria`).
2. Ejecutar a mano `php artisan iam:install` con el primer administrador y las redirecciones del ambiente, y copiar al `.env` del ambiente las credenciales que muestra.
3. Recargar la configuración (`php artisan optimize` o reiniciar el contenedor) y probar el login desde el Angular.

**Rollback:** retirar la versión. Lo que creó `iam:install` (aplicación, clientes, roles) queda en la base sin efecto mientras nadie use esos clientes; se revoca o se borra en el portal cuando exista `clientes-oauth`. `iam_auditoria` no la lee nadie más.

## Open Questions

- La URL pública definitiva del Angular en desarrollo y producción (`APP_URL` y `--redirect` de `iam:install`). No cambia el código: se define al instalar cada ambiente.
