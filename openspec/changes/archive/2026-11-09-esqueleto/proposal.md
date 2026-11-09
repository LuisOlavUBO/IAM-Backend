# Proposal

## Why

API-IAM es un repositorio vacío. Antes de construir el portal de administración hay que tener un proyecto que arranque, se pruebe contra SQL Server en Docker, se despliegue igual que API-Auth y, sobre todo, sea dueño del esquema de IAM. Hoy ese esquema lo crean las migraciones de API-Auth. Se decidió adelantar el traspaso a este primer cambio, para que los siguientes (acceso al portal, catálogo, usuarios, clientes) agreguen columnas y tablas sin depender de cambios en API-Auth.

## What Changes

- Proyecto Laravel 13 / PHP 8.4 con `ubo/common` (Problems, Tracing y Health activos), Pint, PHPUnit y Laravel Boost con un `CLAUDE.md` que incluye las convenciones de IAM.
- Estructura por dominio (`app/Domains/{Acceso,Catalogo,Usuarios,Clientes,Auditoria}`), montada bajo el prefijo `/api/iam/v1`. Los dominios todavía no tienen rutas de negocio.
- Endpoint de salud `GET /api/iam/v1/salud` y errores en RFC 9457 para todo `/api/iam/*`.
- Configuración base: base de datos `IAM` (compartida con API-Auth), migraciones registradas en la tabla `iam_migrations`, sesión y caché en `file`, hasher bcrypt igual al de API-Auth, proxies de confianza y `DB::prohibitDestructiveCommands` fuera del ambiente `testing`.
- Migración `esquema_base_iam`: crea con su forma actual las tablas de IAM que falten (`users`, `applications`, `oauth_scopes`, `roles`, `oauth_scope_role`, `role_user`, `oauth_client_role`, `oauth_clients`). En una base existente no cambia nada. Exige que `account-lifecycle` ya esté aplicado y no se puede revertir.
- Docker de desarrollo (app + nginx en `:8003` y SQL Server de tests en `:14335`) y de producción (`Dockerfile.prod`, `docker-compose.prod.yml`, imagen `ghcr.io/luisolavubo/api-iam`, nginx en la red externa `proxy`, sin migrar al arrancar).
- Documentación inicial: OpenAPI en `docs/openapi/`, colección de Bruno en `docs/bruno/` y README con el orden de migración de un ambiente nuevo (API-IAM, después API-Auth).
- `openspec/config.yaml` con el contexto del proyecto.

Fuera de alcance: login del portal, `/me`, permisos `iam:*`, comandos (`iam:install`, `iam:grant-admin`, `iam:rotate-secret`), auditoría y todo endpoint de negocio. Tampoco se crean modelos Eloquent de las tablas de IAM: los crea cada dominio cuando los usa. El cambio hermano `traspaso-migraciones` vive en el repositorio de API-Auth.

## Capabilities

### New Capabilities
- `esquema-de-datos`: propiedad y evolución del esquema de IAM en la base compartida: tabla de control propia, migración base idempotente, precondiciones y protección contra comandos destructivos.
- `operacion-del-servicio`: lo que el servicio expone para operarse: prefijo de rutas, endpoint de salud y formato de errores.

### Modified Capabilities
<!-- Ninguna: el repositorio no tiene specs. -->

## Impact

- **Repositorio nuevo**: código, Docker, documentación y configuración de OpenSpec.
- **Base `IAM`**: aparece la tabla `iam_migrations`. En una base que ya tiene el esquema de API-Auth no se crea ni altera ninguna otra tabla.
- **Dependencias**: `laravel/framework` 13, `ubo/common` ^3.3 (desde el repositorio `LuisOlavUBO/UBO-Common`) y `laravel/boost` (desarrollo), aprobadas en la exploración.
- **API-Auth**: este cambio requiere `account-lifecycle` aplicado en la base real antes de migrar allí, y se coordina con `traspaso-migraciones` (copias de las migraciones de IAM solo para tests, división de `create_users_table`, `prohibitDestructiveCommands`, `RoleScopeSeeder` solo para tests). Se despliega primero API-IAM.
