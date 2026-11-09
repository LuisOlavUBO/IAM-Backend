# API-IAM

Backend de administración de IAM de la UBO (Laravel 13, PHP 8.4). Es dueño de los datos de identidad y acceso que usa [API-Auth](../../API-Auth) para emitir tokens: usuarios, aplicaciones, roles, permisos, clientes OAuth y auditoría. Lo consume el portal Angular de IAM como BFF (backend-for-frontend).

- **Rutas:** todas bajo `/api/iam`, las de la versión 1 bajo `/api/iam/v1`, en español.
- **Documentación:** Swagger UI en `/api/iam/docs/swagger`, sobre [`docs/openapi/openapi.yaml`](docs/openapi/openapi.yaml) (servido en `/api/iam/docs/openapi.yaml`). Un test verifica que documente todas las rutas. Colección de [Bruno](https://www.usebruno.com/) en [`docs/bruno`](docs/bruno).
- **Estándar UBO:** errores RFC 9457, tracing y salud con [`ubo/common`](https://github.com/LuisOlavUBO/UBO-Common).
- **Convenciones:** [`AGENTS.md`](AGENTS.md) (generado por Laravel Boost; las propias están en [`.ai/guidelines/iam`](.ai/guidelines/iam/core.blade.php)). Cambios con [OpenSpec](openspec/).

## Base de datos compartida

API-IAM y API-Auth usan **la misma base, `IAM`**, y cada tabla tiene un solo servicio que escribe:

| Dueño | Tablas |
|---|---|
| API-IAM | `users`, `applications`, `oauth_scopes`, `roles`, `oauth_scope_role`, `role_user`, `oauth_client_role`, `oauth_clients` y toda tabla `iam_*` |
| API-Auth | `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_auth_codes`, `oauth_device_codes`, `sessions`, `cache`, `jobs`, `password_reset_tokens` |

- Las migraciones de API-IAM se registran en **`iam_migrations`**. La tabla `migrations` es el historial de API-Auth y API-IAM no la toca.
- La primera migración, `esquema_base_iam`, consolida el esquema que antes creaba API-Auth. En una base vacía crea las ocho tablas; en una base donde ya existen no hace nada y solo queda registrada. Exige que API-Auth tenga aplicado `account-lifecycle` (`users.disabled_at` y `users.temporary_password_expires_at`) y **no se revierte**.
- En las tablas que lee API-Auth los cambios solo **agregan** columnas o tablas.
- **`migrate:fresh`, `migrate:refresh`, `migrate:reset` y `db:wipe` están prohibidos** fuera del ambiente `testing`: borrarían también las tablas de API-Auth.
- No se cargan datos a mano por SQL.

### Orden en un ambiente nuevo

Desde que API-Auth ya no crea las tablas de IAM (cambio `traspaso-migraciones` de API-Auth), una base vacía se prepara en este orden:

1. `php artisan migrate` en **API-IAM** (crea `users`, el catálogo y los clientes).
2. `php artisan migrate` en **API-Auth** (crea tokens, sesiones, caché y colas).
3. `php artisan iam:install` en API-IAM (cuando exista: crea el catálogo base y el primer administrador).

## Desarrollo

Requisitos: Docker. Para correr los tests o Artisan desde el host, además PHP 8.4 con `pdo_sqlsrv` y Composer.

```bash
cp .env.example .env        # completar DB_* (base IAM de desarrollo) y DOCKER_MSSQL_SA_PASSWORD
docker compose up -d --build
docker compose exec app php artisan key:generate
```

La API queda en `http://localhost:8003` (se cambia con `DOCKER_HTTP_PORT`): `http://localhost:8003/api/iam/v1/salud`. El código se monta desde el repositorio, así que los cambios se ven sin reconstruir. Si cambia `composer.json` o `composer.lock`: `docker compose up -d --build -V`.

La sesión y la caché usan archivos (`SESSION_DRIVER=file`, `CACHE_STORE=file`) y las colas corren en el acto (`QUEUE_CONNECTION=sync`): las tablas `sessions`, `cache` y `jobs` de la base son de API-Auth.

## Tests

Corren contra **SQL Server en Docker**, en la base `IAM_test` ([phpunit.xml](phpunit.xml)), nunca contra la base de desarrollo ni con SQLite. Define `DOCKER_MSSQL_SA_PASSWORD` en el `.env` (mínimo 8 caracteres con mayúsculas, minúsculas, números y símbolos) y levanta el SQL Server del perfil `tests`, que crea `IAM_test` con la intercalación de `IAM` (`SQL_Latin1_General_CP1_CI_AS`) en el puerto `14335` (se cambia con `DOCKER_MSSQL_PORT`; API-Maestros usa `14333` y API-Auth `14334`):

```bash
docker compose --profile tests up -d sqlserver sqlserver-init
```

Espera a que `sqlserver-init` termine (`docker compose ps -a sqlserver-init` muestra `Exited (0)`) y corre los tests desde el host con el usuario `sa`. Las variables de entorno tienen prioridad sobre el `.env`, que no se modifica.

En bash:

```bash
DB_HOST=127.0.0.1 DB_PORT=14335 DB_USERNAME=sa DB_TRUST_SERVER_CERTIFICATE=true \
  DB_PASSWORD="$(grep '^DOCKER_MSSQL_SA_PASSWORD=' .env | cut -d= -f2-)" php artisan test
```

En PowerShell:

```powershell
$env:DB_HOST='127.0.0.1'; $env:DB_PORT='14335'; $env:DB_USERNAME='sa'; $env:DB_TRUST_SERVER_CERTIFICATE='true'
$env:DB_PASSWORD=(Select-String '^DOCKER_MSSQL_SA_PASSWORD=' .env).Line.Split('=', 2)[1]
php artisan test
```

Lint: `vendor/bin/pint --test`.

## Despliegue

`docker-compose.prod.yml` levanta `app` (imagen de `Dockerfile.prod`) y `nginx`. El healthcheck de nginx llama a `/api/iam/v1/salud`, que consulta la base, así que el estado `healthy` cubre nginx → PHP-FPM → SQL Server. La imagen no incluye dependencias de desarrollo ni `.env`. Al arrancar, el entrypoint valida las variables críticas y ejecuta `php artisan optimize`.

Construir y publicar, en el PC:

```bash
IMAGE=ghcr.io/luisolavubo/api-iam:0.1.0 docker compose -f docker-compose.prod.yml build app
docker push ghcr.io/luisolavubo/api-iam:0.1.0
```

En el servidor, la carpeta del proyecto contiene solo:

| Archivo | Contenido |
|---|---|
| `docker-compose.prod.yml` | El compose |
| `nginx/default.conf` | Configuración de nginx |
| `.env` | Variables de Compose: `IMAGE` y `DOCKER_HTTP_PORT_PROD` |
| `.env.prod` | Configuración de Laravel: `APP_KEY`, `APP_URL`, `DB_*`, `TRUSTED_PROXIES`, `UBO_*` |

Los `.env*` no se versionan. En el servidor:

```bash
chmod 600 .env .env.prod
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --no-build
```

**Migraciones y comandos, siempre a mano.** El entrypoint no migra (`RUN_MIGRATIONS=false`) y los comandos `iam:*` nunca se ejecutan desde el entrypoint ni desde CI, para que los secretos que muestran no queden en los logs:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

El compose fuerza `APP_ENV=production`, `APP_DEBUG=false` y `LOG_CHANNEL=stderr` (logs con `docker compose -f docker-compose.prod.yml logs app`). HTTPS se termina en el proxy inverso.

**Proxy inverso:** `ubo-nginx-proxy` elige el contenedor por el segmento que sigue a `/api` (`/api/iam/...` → `api-iam-nginx-prod`, por la red externa `proxy`, que debe existir antes de levantar el compose) y pasa la ruta sin reescribirla. Un despliegue nuevo cierra las sesiones del portal (sesión en archivos dentro del contenedor).

## Estructura

```
app/Domains/<Dominio>/routes/v1.php   rutas de cada dominio (Acceso, Catalogo, Usuarios, Clientes, Auditoria)
routes/api.php                        /api/iam/v1/salud, docs y montaje de los dominios
database/migrations/                  esquema_base_iam y las migraciones de API-IAM
docs/openapi/, docs/bruno/            contrato y colección
openspec/                             specs y cambios
```
