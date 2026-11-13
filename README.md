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

1. `php artisan migrate` en **API-IAM** (crea `users`, las aplicaciones, permisos y roles, y los clientes).
2. `php artisan migrate` en **API-Auth** (crea tokens, sesiones, caché y colas).
3. `php artisan iam:install` en API-IAM (ver [Instalación de un ambiente](#instalación-de-un-ambiente)).

API-Auth lee columnas que crea API-IAM, así que en un ambiente que ya existe la migración de API-IAM va antes que la versión de API-Auth que la usa. Por ejemplo, `agregar_baja_a_aplicaciones_y_roles` (cambio `aplicaciones-baja`) se corre antes de desplegar API-Auth con `catalogo-dado-de-baja`; al revés, API-Auth fallaría al leer `disabled_at`.

## Portal (BFF) y sesión

El portal es un Angular que se sirve **en el mismo origen** que `/api/iam/*` y usa URLs relativas. Nunca ve un token: el login lo hace API-IAM con el módulo Login de `ubo/common` (Authorization Code con PKCE contra el SSO de IAM) y el navegador solo recibe la cookie de sesión.

| Ruta | Grupo | Qué hace |
|---|---|---|
| `GET /api/iam/v1/auth/login?redirect=/ruta` | web | Navegación al login de IAM |
| `GET /api/iam/v1/auth/callback` | web | Vuelta desde IAM; con un error, redirige a `UBO_LOGIN_HOME?error_login=SIN_ACCESO\|LOGIN_INVALIDO\|IAM_NO_DISPONIBLE` |
| `POST /api/iam/v1/auth/logout` | web | Formulario con `_token` (el `csrf` de `/me`); cierra también la sesión central |
| `GET /api/iam/v1/me` | web | La persona: `uuid`, `nombre`, `correo`, `roles`, `permisos` y `csrf` |
| Rutas de los dominios (`app/Domains/*/routes/v1.php`) | web | Exigen sesión (`401 NO_AUTENTICADO`), un permiso `iam:*` (`403`) y, si cambian datos, el header `X-XSRF-TOKEN` (`419`) |
| `GET /api/iam/v1/salud`, docs y futuros endpoints M2M | api | Sin sesión |

La sesión se revalida contra IAM cada `UBO_LOGIN_REVALIDATE` segundos (300) con el cliente M2M de API-IAM: una persona dada de baja pierde el acceso en minutos.

**Desarrollo con Angular:** `ng serve` en `http://localhost:4200` con un `proxy.conf.json` que envía `/api/iam` a `http://localhost:8003`, `APP_URL=http://localhost:4200` y `SESSION_SECURE_COOKIE=false`. La redirección `http://localhost:4200/api/iam/v1/auth/callback` debe estar entre las `--redirect` de `iam:install`.

## Instalación de un ambiente

Una vez por ambiente, **a mano** (nunca desde el entrypoint ni desde CI: muestra secretos):

```bash
php artisan migrate
php artisan iam:install --admin-email=ana@ubo.cl --admin-nombre="Ana Pérez" \
  --redirect=https://<origen del portal>/api/iam/v1/auth/callback \
  --redirect=http://localhost:4200/api/iam/v1/auth/callback
```

- Muestra el ambiente y la base y pide confirmación. Se niega a correr si la aplicación `iam-admin` ya existe.
- Asegura el catálogo base de `api-auth` (permisos `user:read`, `user:write`, `introspect` y `sessions:revoke`, y roles `user`, `admin`, `introspector` y `session-revoker`), agregando solo lo que falta.
- Crea:
  - la aplicación `iam-admin` con sus siete permisos `iam:*`;
  - los roles `administrador` (todos) y `mesa-de-ayuda` (`iam:usuarios.leer`, `iam:usuarios.administrar` e `iam:aplicaciones.leer`);
  - el cliente del portal (`iam-admin-portal`) y el cliente M2M (`iam-admin-sistema`, con `session-revoker` e `introspector`);
  - el primer administrador, o le da el rol si ya existe, sin tocar su clave. Con `--admin-acceso=microsoft` queda sin contraseña; por defecto recibe una temporal que vence en `IAM_CLAVE_TEMPORAL_HORAS` (72).
- Todo ocurre en una transacción y queda en la auditoría (sin secretos). **Al final, y una sola vez**, imprime `UBO_LOGIN_CLIENT_ID`, `UBO_LOGIN_CLIENT_SECRET`, `UBO_AUTH_CLIENT_ID`, `UBO_AUTH_CLIENT_SECRET` y `CLAVE_TEMPORAL`. Se copian al `.env` del ambiente y se recarga la configuración (`php artisan optimize` o reiniciar el contenedor).

**Acceso de emergencia**, si nadie puede entrar al portal:

```bash
php artisan iam:grant-admin ana@ubo.cl              # da iam-admin/administrador a una persona existente
php artisan iam:grant-admin ana@ubo.cl --reactivar  # además la reactiva si estaba dada de baja
```

No crea personas ni cambia contraseñas, pide confirmación y queda en la auditoría.

**Procedimiento de emergencia (secreto perdido):** si se pierde un secreto (la salida de `iam:install` sin copiar, o el `.env` de un ambiente), se rota con:

```bash
php artisan iam:rotate-secret iam-admin-portal   # por nombre; o por id (UUID) si el nombre se repite
```

Muestra el ambiente, la base y el cliente, y pide confirmación. Guarda solo el hash del secreto nuevo (el anterior deja de servir de inmediato), lo registra en la auditoría (`cliente.secreto.rotacion`, sin el secreto) y lo imprime **una sola vez**:

- para `iam-admin-portal` e `iam-admin-sistema`, la línea del `.env` lista para copiar (`UBO_LOGIN_CLIENT_SECRET=...` o `UBO_AUTH_CLIENT_SECRET=...`). Después se recarga la configuración (`php artisan optimize` o reiniciar el contenedor);
- para otros clientes, `client_id` y `client_secret`, que se entregan al sistema que lo usa.

Un cliente público (PKCE) no tiene secreto y el comando lo rechaza. Desde el portal, quien tenga `iam:clientes.administrar` rota el secreto en el detalle del cliente.

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

**Orden con API-Auth.** La revocación de clientes (`clientes-revocar`) se despliega después de API-Auth `introspeccion-cliente-revocado`. Sin ese cambio todo funciona, pero los tokens vigentes de un cliente revocado siguen aceptados por las APIs que introspectan hasta vencer (máximo 60 minutos).

## Estructura

```
app/Domains/<Dominio>/                dominios: Acceso, Aplicaciones, Usuarios, Clientes, Auditoria
app/Shared/                           núcleo compartido: Paginacion, Rut, Auditoria (IAuditor)
app/Console/Commands/                 iam:install, iam:grant-admin, iam:rotate-secret, iam:make-feature
routes/web.php                        montaje de los dominios bajo /api/iam/v1 (grupo web, sesión del portal)
routes/api.php                        /api/iam/v1/salud y docs
stubs/feature/                        plantillas del generador de casos de uso
database/migrations/                  esquema_base_iam y las migraciones de API-IAM
docs/openapi/, docs/bruno/            contrato y colección
openspec/                             specs y cambios
```

### Arquitectura

Monolito modular por dominio, con un caso de uso por carpeta, CQRS lite e interfaces solo en los bordes (spec
`arquitectura-de-dominios`). Usuarios es el dominio de referencia:

```
app/Domains/Usuarios/
+-- UsuariosServiceProvider.php   enlaza cada interfaz con su implementación
+-- routes/v1.php                 una línea por caso de uso
+-- Features/                     QUÉ HACE: un caso de uso por carpeta
|   +-- ListarUsuarios/           ListarUsuariosEndpoint, Request, QueryHandler, Resource
|   +-- VerUsuario/               VerUsuarioEndpoint, QueryHandler, Resource
+-- Domain/                       EL NÚCLEO, sin HTTP ni SQL: ClaveTemporal, UsuarioNoEncontradoException
|   +-- Dto/                      FiltroUsuariosDto, UsuarioResumenDto, UsuarioDetalleDto, RolAsignadoDto
|   +-- Interfaces/               IUsuarioQueryRepository
+-- Infrastructure/               CÓMO: UsuarioQueryRepository, DirectorioUsuariosService (SQL)
+-- Contracts/                    LO PÚBLICO para otros dominios: IDirectorioUsuariosService, PersonaBasicaDto
```

Flujo de una consulta (`GET /usuarios/{uuid}`):

```
routes/v1.php → VerUsuarioEndpoint → VerUsuarioQueryHandler → IUsuarioQueryRepository → UsuarioDetalleDto → VerUsuarioResource
                                                                 +-- UsuarioQueryRepository (Infrastructure)
```

- El **endpoint** traduce HTTP (toma lo validado, llama al handler y elige cómo se responde), el **request** valida,
  el **handler** es el caso de uso (recibe datos tipados y solo las interfaces que usa) y el **resource** arma el JSON
  con una lista blanca de campos.
- CQRS lite: un `QueryHandler` lee por un `I<Entidad>QueryRepository` que devuelve DTO; un `CommandHandler` escribe
  con la entidad de `Domain/`, su `I<Entidad>Repository`, la transacción y `IAuditor`. Sin bus ni eventos.
- Nombres al estilo .NET: `I…` interfaz, `…Dto`, `…Exception`, `…QueryRepository` / `…Repository`, `…Service`.
- Las capas `Domain`, `Infrastructure` y `Contracts` se crean solo cuando tienen algo (Acceso no tiene
  `Domain/`; Auditoria no tiene `Contracts/`); las subcarpetas por tipo, cuando una carpeta pasa de unos diez archivos.
- Entre dominios solo se usa `Contracts/`; `Shared` no conoce dominios; un caso de uso no importa otro ni
  `Infrastructure`. `tests/Unit/ArquitecturaTest.php` lo verifica en cada `php artisan test`.
- Un caso de uso nuevo se crea con:

  ```bash
  php artisan iam:make-feature Aplicaciones ListarAplicaciones
  ```

  Crea la carpeta con sus cuatro archivos (`ListarAplicacionesQueryHandler`; con `--command`, un `CommandHandler`) y
  su test en `tests/Feature/Aplicaciones/`, y muestra la línea de ruta que hay que agregar en
  `app/Domains/Aplicaciones/routes/v1.php` (método, URL y permiso).

El `ServiceProvider` de cada dominio solo enlaza interfaces con implementaciones: las rutas las monta
`routes/web.php`, que da la sesión del portal. Las excepciones de negocio extienden `DomainProblem` de `ubo/common`.
