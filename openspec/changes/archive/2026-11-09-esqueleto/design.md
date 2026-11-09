# Design

## Context

Ver `proposal.md` → Why. Requisitos: `specs/esquema-de-datos` y `specs/operacion-del-servicio`.

Estado y restricciones que dan forma al enfoque:

- **API-Auth es el molde.** Laravel 13.17, PHP 8.4, `laravel/boost` 2.x, PHPUnit contra SQL Server en Docker (perfil `tests`, base `apiauth_test`, intercalación `SQL_Latin1_General_CP1_CI_AS`, puerto 14334; API-Maestros usa 14333). Producción: `Dockerfile.prod` multi-etapa con `sqlsrv`, entrypoint que exige variables, corre `optimize` y migra solo con `RUN_MIGRATIONS=true` (por defecto `false`), y nginx que envía todo a `public/index.php`. Usa `apiPrefix` para publicar sus rutas bajo `/api/auth` y registra `ProblemRenderer` en `bootstrap/app.php`.
- **Esquema actual de IAM** (de las migraciones de API-Auth, leídas el 2026-11-06): `users` (`uuid` único, `rut` y `entra_oid` con índices únicos filtrados `users_rut_unique` y `users_entra_oid_unique`, `password` nullable); `applications` (renombrada desde `apis`); `oauth_scopes` (`name` único, `description` obligatoria, `application_id` FK sin cascada); `roles` (único `(application_id, name)`, `application_id` FK sin cascada); `oauth_scope_role`, `role_user` y `oauth_client_role` (claves compuestas, FK con `ON DELETE CASCADE`); `oauth_clients` (tabla de Passport con `id` UUID, `owner` morph nullable y `application_id` FK). `account-lifecycle` agregará `users.disabled_at` y `users.temporary_password_expires_at` (datetime nullable).
- **Las tablas de API-Auth no tienen FK hacia las de IAM** (`oauth_access_tokens.client_id`, `oauth_auth_codes.user_id` y `sessions.user_id` son columnas indexadas sin restricción). Por eso el esquema de IAM se crea y evoluciona sin coordinar FK entre servicios.
- **Laravel no soporta `schema:dump` en SQL Server**: la consolidación se escribe a mano.
- **SQL Server ejecuta DDL dentro de transacciones**, lo que permite probar estados de la base sin dejar rastro.
- **`ubo/common` 3.3.0** es la última versión. Health responde `{"estado","base_datos"}` en la ruta que registre la aplicación; Problems atiende `api/*` por defecto con los códigos `RUTA_NO_ENCONTRADA`, `METODO_NO_PERMITIDO` y `ERROR_INTERNO`; Tracing agrega `X-Trace-Id`.

## Goals / Non-Goals

**Goals:**
- Que un ambiente nuevo y uno existente terminen con exactamente el mismo esquema de IAM.
- Que los cambios siguientes solo agreguen migraciones normales en `iam_migrations`, sin tocar API-Auth.
- Copiar las decisiones operativas de API-Auth donde aplican, para que operar los dos servicios sea igual.

**Non-Goals:**
- Modelos Eloquent, rutas de negocio o el módulo Login de Common (`UBO_COMMON_LOGIN=false` en este cambio).
- Varias réplicas del contenedor: sesión y caché en `file` asumen uno solo.
- Corregir el esquema heredado (tipos, nombres de índices): la migración base lo reproduce tal como está.

## Decisions

### D1. Tabla de control `iam_migrations`

`config/database.php` → `migrations.table = 'iam_migrations'`.

- **Por qué:** compartir `migrations` mezcla lotes: un `migrate:rollback` revertiría el último lote, sea de quien sea, y un nombre de archivo repetido en ambos repos haría que el segundo nunca corra.
- **Alternativa descartada:** compartir la tabla. `migrate` funciona por casualidad, pero `status` y `rollback` no.

### D2. Migración base idempotente, por tabla

Un único archivo `database/migrations/<fecha>_000000_esquema_base_iam.php`:

1. Si `users` existe y le falta `disabled_at` o `temporary_password_expires_at`, lanza una excepción antes de tocar nada ("aplique primero las migraciones de API-Auth (account-lifecycle)").
2. Por cada tabla, en orden de dependencias (`applications` → `users` → `oauth_scopes`, `roles`, `oauth_clients` → tablas pivote), la crea solo si `Schema::hasTable` es falso, con su forma final.
3. Los índices filtrados se crean con `DB::statement` (`CREATE UNIQUE INDEX ... WHERE ... IS NOT NULL`), igual que en API-Auth.
4. `down()` lanza una excepción: el esquema base no se revierte.

El encabezado del archivo lista las migraciones de API-Auth que consolida y el commit de API-Auth del que se tomó el esquema.

- **Por qué `hasTable` por tabla y no "todo o nada":** en una base existente todas existen y nada corre. En una base vacía todas se crean. No hay estados intermedios legítimos, y si los hubiera, crear solo lo que falta es lo menos destructivo.
- **Por qué no copiar las 18 migraciones de API-Auth:** contienen renombres (`apis` → `applications`, `api_id` → `application_id`), traspasos de datos (`users.role_id` → `role_user`) y tablas que no son de IAM. Son historia, y la historia queda en el git de API-Auth.
- **Alternativa descartada:** insertar a mano las filas de las migraciones originales en `iam_migrations`. Es SQL manual en producción, que el documento base prohíbe.
- **`down()` que falla** en vez de no hacer nada: un rollback silencioso dejaría `iam_migrations` sin la base, y el siguiente `migrate` la "ejecutaría" de nuevo sin efecto, confundiendo el historial.

### D3. Fidelidad de nombres heredados

La migración base usa **los mismos nombres de índices y restricciones que existen en la base real**, incluidos los heredados del renombre de `apis` (por ejemplo, el índice único de `applications.name` creado como `apis_name_unique`). Los nombres exactos se obtienen consultando `sys.indexes`, `sys.foreign_keys` y `sys.key_constraints` en la base de desarrollo (tarea 3.1), no se deducen.

- **Por qué:** una migración futura que borre o recree una restricción por nombre debe funcionar igual en un ambiente creado por API-Auth y en uno creado por API-IAM.
- **Excepción:** los nombres que SQL Server genera al azar (claves primarias `PK__tabla__hash`) no se pueden reproducir. En una base nueva se usan los de Laravel, y ninguna migración debe referirse a una clave primaria por nombre (regla para `CLAUDE.md`).

### D4. Protección contra comandos destructivos

`AppServiceProvider::boot()` → `DB::prohibitDestructiveCommands(! $this->app->environment('testing'))`. Cubre `migrate:fresh`, `migrate:refresh`, `migrate:reset` y `db:wipe`.

- **Por qué en todos los ambientes salvo `testing`, y no solo en producción:** la base `IAM` de desarrollo también es compartida con API-Auth, y `migrate:fresh` borra **todas** las tablas de la base, no solo las de IAM.

### D5. Rutas: `apiPrefix` y un archivo por dominio

`bootstrap/app.php` usa `apiPrefix: 'api/iam'` y no registra la ruta `/up` de Laravel (el proxy solo envía `/api/iam/*`). `routes/api.php` registra `v1/salud` y monta, con prefijo `v1`, el `routes/v1.php` de cada dominio en `app/Domains/{Acceso,Catalogo,Usuarios,Clientes,Auditoria}`. En este cambio esos archivos existen vacíos.

- **Por qué por dominio:** es la convención de API-Maestros y lo que el documento base llama "monolito modular". Cada cambio siguiente trabaja dentro de su carpeta.
- **Pendiente para `acceso-portal`:** las rutas del BFF necesitan sesión y CSRF (grupo `web`). Ese cambio decidirá cómo se monta ese grupo bajo el mismo prefijo, como en PagoHonorarios.

### D6. Errores y traza

`ProblemRenderer` se registra para `api/*` con `shouldRenderJsonWhen` sobre `api/*`, como en API-Auth (sin las excepciones de páginas web, que API-IAM no tiene). Tracing y Health quedan con su configuración por defecto.

### D7. Configuración de sesión, caché y hashing

`.env.example`: `SESSION_DRIVER=file`, `CACHE_STORE=file`, `SESSION_ENCRYPT=true`, `SESSION_SAME_SITE=lax`, `HASH_DRIVER` no se cambia (bcrypt), `BCRYPT_ROUNDS=12`, `TRUSTED_PROXIES`, `DB_DATABASE=IAM`, y las variables de Common de Login y Auth comentadas para `acceso-portal`.

- **Por qué `file`:** las tablas `sessions` y `cache` de la base son de API-Auth, y un contenedor no necesita almacenamiento compartido. Se acepta que un despliegue cierre las sesiones del portal: el SSO hace casi invisible el nuevo login.
- **Por qué fijar bcrypt 12 por escrito:** API-Auth valida con `Hash::check` las contraseñas y los secretos que escribirá API-IAM. Un driver distinto haría que nadie pudiera entrar.

### D8. Docker y documentación copiados de API-Auth

`Dockerfile.dev`, `Dockerfile.prod`, `docker-compose.yml`, `docker-compose.prod.yml`, `docker-entrypoint*.sh` y `nginx/default.conf` se copian de API-Auth, con estos cambios: nombres `api-iam`, puerto de desarrollo `8003`, SQL Server de tests en `14335` con la base `IAM_test`, y sin llaves de Passport (API-IAM no firma tokens). Producción conserva `RUN_MIGRATIONS=false`, rotación de logs, healthcheck de PHP-FPM y nginx en la red externa `proxy`.

La documentación sigue el patrón de API-Auth: `docs/openapi/openapi.yaml` servido en `GET /api/iam/docs/openapi.yaml`, Swagger UI en `/api/iam/docs/swagger` y un `DocsTest` que exige que toda ruta `/api/iam/*` esté documentada (y al revés).

### D9. Pruebas del esquema base

- **Base vacía:** la suite usa `RefreshDatabase`, así que el esquema completo se crea desde cero en cada corrida. Un test revisa columnas, índices filtrados y unicidades.
- **Base existente, precondición y rollback:** tests dentro de una transacción que crean un estado (tablas "de API-Auth" con datos, o `users` sin `temporary_password_expires_at`), ejecutan `up()` o `down()` de la migración base y verifican el resultado. La transacción se revierte al terminar, gracias al DDL transaccional de SQL Server.
- **Fidelidad con la base real:** no se automatiza. Es una verificación manual única (tarea 3.4) que compara `INFORMATION_SCHEMA.COLUMNS` y los índices de `IAM_test` con los de la base `IAM` de desarrollo.

### D10. Laravel Boost y `CLAUDE.md`

`laravel/boost` en `require-dev` y `boost.json` como en API-Auth. El `CLAUDE.md` generado se completa con una sección propia: base compartida y dueño por tabla, solo agregar columnas en tablas que lee API-Auth, prefijo `iam_` para tablas propias, bcrypt 12, rutas en español bajo `/api/iam/v1`, nada de SQL manual, comandos nunca desde el entrypoint, y no referirse a claves primarias por nombre.

## Risks / Trade-offs

- [La migración base no reproduce fielmente el esquema real] → la tarea 3.1 toma los nombres y tipos de la base de desarrollo, y la 3.4 compara ambos esquemas antes de desplegar. Si un ambiente (QA, producción) difiere del de desarrollo, se detecta comparándolo antes de migrar allí.
- [Un ambiente sin `account-lifecycle` aplicado] → la precondición hace fallar la migración con un mensaje claro y sin cambios.
- [Un ambiente nuevo se migra en el orden equivocado (API-Auth primero)] → tras `traspaso-migraciones`, API-Auth ya no crea `users`, así que sus migraciones fallarían. El README de ambos repos fija el orden: API-IAM y después API-Auth.
- [Se despliega `traspaso-migraciones` antes que este cambio] → habría un intervalo en que ningún repo crea las tablas de IAM en un ambiente nuevo. Se despliega primero API-IAM (ver Migration Plan).
- [Sesiones perdidas en cada despliegue] → aceptado (D7).
- [`prohibitDestructiveCommands` impide limpiar la base de desarrollo] → es intencional. Para empezar de cero se usa la base de pruebas en Docker.

## Migration Plan

1. Verificar que `account-lifecycle` esté aplicado en la base `IAM` de desarrollo.
2. Tomar del desarrollo los nombres de índices y restricciones (tarea 3.1) y escribir la migración base.
3. Desplegar API-IAM en desarrollo y ejecutar a mano `php artisan migrate`. Resultado esperado: solo aparece `iam_migrations`, con la migración base registrada.
4. Comparar esquemas (tarea 3.4).
5. Después, en el repositorio de API-Auth, desplegar `traspaso-migraciones`.
6. Repetir 1, 3 y 4 en cada ambiente (QA, producción).

**Rollback:** borrar la tabla `iam_migrations` (la única que este cambio crea en una base existente) y retirar el contenedor. Las tablas de IAM no se tocaron.

## Open Questions

- Si QA y producción tienen diferencias de esquema con desarrollo (índices creados a mano, intercalación distinta). Se responde comparándolos antes de migrar en cada uno (paso 6), sin cambiar el enfoque.
