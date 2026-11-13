# esquema-de-datos Specification

## Purpose
Define cómo API-IAM es dueño del esquema de IAM dentro de la base compartida con API-Auth: dónde registra sus migraciones, cómo toma un esquema que ya existe sin alterarlo y cómo se protege la base de comandos que borrarían datos de ambos servicios.

## Requirements

### Requirement: Registro propio de migraciones
API-IAM SHALL registrar sus migraciones en la tabla `iam_migrations` de la base `IAM`. MUST NOT leer ni escribir la tabla `migrations`, que es el historial de API-Auth.

#### Scenario: Migrar en la base compartida
- **WHEN** se ejecuta `php artisan migrate` de API-IAM sobre la base `IAM`
- **THEN** las migraciones ejecutadas quedan registradas en `iam_migrations` y la tabla `migrations` no cambia

#### Scenario: Historial de API-Auth intacto
- **GIVEN** la tabla `migrations` contiene las migraciones de API-Auth
- **WHEN** se consulta el estado de las migraciones de API-IAM
- **THEN** solo aparecen las migraciones de API-IAM

### Requirement: Esquema base en una base vacía
La migración base SHALL crear, en una base sin esas tablas, `users`, `applications`, `oauth_scopes`, `roles`, `oauth_scope_role`, `role_user`, `oauth_client_role` y `oauth_clients` con las columnas, claves, índices y restricciones que tienen hoy en la base de API-Auth.

#### Scenario: Base de pruebas vacía
- **WHEN** se migra una base vacía
- **THEN** existen las ocho tablas, incluidas las columnas `users.uuid`, `users.rut`, `users.entra_oid`, `users.disabled_at`, `users.temporary_password_expires_at`, `oauth_scopes.application_id`, `roles.application_id` y `oauth_clients.application_id`

#### Scenario: Unicidad de RUT y Microsoft solo cuando hay valor
- **GIVEN** el esquema creado por la migración base
- **WHEN** se insertan dos usuarios con `rut` nulo y `entra_oid` nulo
- **THEN** ambas inserciones se aceptan, y una segunda inserción con un `rut` ya usado se rechaza

#### Scenario: Rol único por aplicación
- **GIVEN** el esquema creado por la migración base
- **WHEN** se crean dos roles con el mismo nombre en aplicaciones distintas
- **THEN** ambos se aceptan, y un segundo rol con el mismo nombre en la misma aplicación se rechaza

### Requirement: Esquema base en una base existente
Si una tabla de IAM ya existe, la migración base MUST NOT crearla, alterarla ni modificar sus filas. La migración SHALL quedar registrada como ejecutada.

#### Scenario: Base con el esquema de API-Auth
- **GIVEN** una base con las tablas de IAM creadas por API-Auth y con datos
- **WHEN** se ejecuta `php artisan migrate` de API-IAM
- **THEN** ninguna tabla, columna, índice ni fila de IAM cambia, y la migración base queda en `iam_migrations`

### Requirement: Precondición de account-lifecycle
Si `users` existe sin las columnas `disabled_at` o `temporary_password_expires_at`, la migración base MUST fallar sin cambiar la base, con un mensaje que indique aplicar antes las migraciones de API-Auth.

#### Scenario: API-Auth sin account-lifecycle
- **GIVEN** una base con `users` sin `temporary_password_expires_at`
- **WHEN** se ejecuta `php artisan migrate` de API-IAM
- **THEN** el comando falla con un mensaje que pide aplicar las migraciones de API-Auth, y no se crea ni registra nada

### Requirement: Esquema base irreversible
Revertir la migración base MUST fallar sin borrar ni alterar ninguna tabla.

#### Scenario: Rollback del esquema base
- **GIVEN** la migración base registrada en `iam_migrations`
- **WHEN** se ejecuta un rollback que la incluye
- **THEN** el comando falla con un mensaje que indica que el esquema base no se revierte, y las tablas de IAM siguen existiendo con sus datos

### Requirement: Comandos destructivos prohibidos fuera de pruebas
Fuera del ambiente `testing`, API-IAM MUST negarse a ejecutar comandos que borran tablas o datos de toda la base, como `migrate:fresh`, `migrate:refresh`, `migrate:reset` y `db:wipe`.

#### Scenario: migrate:fresh en desarrollo
- **WHEN** se ejecuta `php artisan migrate:fresh` con `APP_ENV=local`
- **THEN** el comando se niega a correr y ninguna tabla de la base `IAM` cambia

#### Scenario: Pruebas automáticas
- **WHEN** las pruebas recrean la base con `APP_ENV=testing`
- **THEN** los comandos destructivos se ejecutan sobre la base de pruebas

### Requirement: Permisos de Aplicaciones en bases ya instaladas
`php artisan migrate` SHALL renombrar en `oauth_scopes` el permiso `iam:catalogo.leer` a `iam:aplicaciones.leer` y `iam:catalogo.administrar` a `iam:aplicaciones.administrar`, conservando su id y por lo tanto los roles que los dan. MUST NOT cambiar nada cuando el permiso antiguo no existe o el nuevo ya existe. Su reversión SHALL devolver los nombres anteriores con la misma regla.

#### Scenario: Base instalada con los nombres antiguos
- **GIVEN** una base con `iam:catalogo.leer` e `iam:catalogo.administrar`, asignados a `administrador` y `mesa-de-ayuda`
- **WHEN** se ejecuta `php artisan migrate`
- **THEN** los permisos se llaman `iam:aplicaciones.leer` e `iam:aplicaciones.administrar`, conservan su id y los roles siguen dándolos

#### Scenario: Base vacía o instalada con los nombres nuevos
- **GIVEN** una base sin `iam:catalogo.*`
- **WHEN** se ejecuta `php artisan migrate`
- **THEN** `oauth_scopes` no cambia

#### Scenario: Reversión
- **GIVEN** una base donde la migración ya renombró los permisos
- **WHEN** se revierte esa migración
- **THEN** los permisos vuelven a llamarse `iam:catalogo.leer` e `iam:catalogo.administrar`, con su id y sus roles

### Requirement: Baja de aplicaciones y roles
Las tablas `applications` y `roles` SHALL tener una columna `disabled_at` (fecha y hora, nula), agregada por una migración de API-IAM que solo agrega columnas. Nula significa activa.

#### Scenario: Migración sobre una base existente
- **GIVEN** una base con aplicaciones y roles creados antes de este cambio
- **WHEN** se ejecuta `php artisan migrate` en API-IAM
- **THEN** ambas tablas tienen `disabled_at` en `null` en todas sus filas y nada más cambia
