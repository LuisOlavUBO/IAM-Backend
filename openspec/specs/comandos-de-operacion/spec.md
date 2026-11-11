# comandos-de-operacion Specification

## Purpose

Define los comandos que se ejecutan a mano en el servidor para instalar IAM en un ambiente y para recuperar el acceso de administración, sin rutas web, sin credenciales por defecto y mostrando los secretos una sola vez.

## Requirements

### Requirement: Confirmación con ambiente y base visibles
Todo comando `iam:*` SHALL mostrar el ambiente (`APP_ENV`), el servidor y la base de datos antes de actuar, y MUST pedir confirmación. Sin confirmación no cambia nada.

#### Scenario: Confirmación rechazada
- **WHEN** se ejecuta `php artisan iam:install` y la persona responde "no" a la confirmación
- **THEN** el comando termina sin escribir en la base

### Requirement: Comandos solo por consola
Los comandos `iam:*` MUST NOT tener una ruta web ni un asistente de instalación por HTTP.

#### Scenario: Sin ruta de instalación
- **WHEN** se listan las rutas de API-IAM
- **THEN** ninguna ruta instala IAM ni otorga roles de administración

### Requirement: Instalación única por ambiente
`iam:install` MUST negarse a correr si la aplicación `iam-admin` ya existe, sin cambiar nada.

#### Scenario: IAM ya instalado
- **GIVEN** una base donde existe la aplicación `iam-admin`
- **WHEN** se ejecuta `php artisan iam:install`
- **THEN** el comando falla con un mensaje que indica que IAM ya está instalado y no escribe en la base

### Requirement: Catálogo base de api-auth
`iam:install` SHALL asegurar que existan la aplicación `api-auth`, sus permisos `user:read`, `user:write`, `introspect` y `sessions:revoke`, y sus roles `user`, `admin`, `introspector` y `session-revoker` con los permisos que da cada uno. MUST agregar solo lo que falte, sin quitar ni cambiar lo existente.

#### Scenario: Base nueva
- **GIVEN** una base sin la aplicación `api-auth`
- **WHEN** se ejecuta `php artisan iam:install` y se confirma
- **THEN** existen `api-auth`, sus cuatro permisos, sus cuatro roles, y `session-revoker` da `sessions:revoke`

#### Scenario: api-auth ya existe con permisos extra
- **GIVEN** una base donde `api-auth/admin` da además un permiso agregado a mano
- **WHEN** se ejecuta `php artisan iam:install` y se confirma
- **THEN** `api-auth/admin` conserva ese permiso

### Requirement: Aplicación iam-admin
`iam:install` SHALL crear la aplicación `iam-admin` con los permisos `iam:usuarios.leer`, `iam:usuarios.administrar`, `iam:aplicaciones.leer`, `iam:aplicaciones.administrar`, `iam:clientes.leer`, `iam:clientes.administrar` e `iam:auditoria.leer`; el rol `administrador`, que da los siete, y el rol `mesa-de-ayuda`, que da `iam:usuarios.leer`, `iam:usuarios.administrar` e `iam:aplicaciones.leer`.

#### Scenario: Roles del portal
- **WHEN** termina `iam:install`
- **THEN** `iam-admin/administrador` da los siete permisos `iam:*` y `iam-admin/mesa-de-ayuda` da exactamente `iam:usuarios.leer`, `iam:usuarios.administrar` e `iam:aplicaciones.leer`

#### Scenario: Sin permisos de Catálogo
- **WHEN** termina `iam:install`
- **THEN** no existe ningún permiso `iam:catalogo.*`

### Requirement: Clientes de API-IAM
`iam:install` SHALL crear un cliente `authorization-code` del portal, de `iam-admin`, con las redirecciones indicadas en `--redirect` (al menos una, cada una absoluta y `https`, salvo `localhost`), y un cliente `client-credentials` de `iam-admin` con los roles `api-auth/session-revoker` y `api-auth/introspector`. Sus secretos MUST guardarse con hash bcrypt.

#### Scenario: Instalación con dos redirecciones
- **WHEN** se ejecuta `iam:install` con `--redirect=https://iam.ubo.cl/api/iam/v1/auth/callback --redirect=http://localhost:4200/api/iam/v1/auth/callback` y se confirma
- **THEN** el cliente del portal tiene `grant_types` `["authorization_code","refresh_token"]`, `provider` `users` y esas dos redirecciones, y el cliente M2M tiene `grant_types` `["client_credentials"]` y los roles `session-revoker` e `introspector`

#### Scenario: Redirección inválida
- **WHEN** se ejecuta `iam:install` sin `--redirect`, o con una redirección relativa o `http` que no es `localhost`
- **THEN** el comando falla antes de pedir confirmación y no escribe en la base

### Requirement: Primer administrador
`iam:install` SHALL dar el rol `iam-admin/administrador` a la persona de `--admin-email`. Si no existe, MUST crearla con `--admin-nombre` y `--admin-rut` opcional; con `--admin-acceso=clave` (por defecto) recibe una contraseña temporal que vence en 72 horas, y con `--admin-acceso=microsoft` queda sin contraseña. Si ya existe, MUST NOT cambiar su contraseña ni sus datos.

#### Scenario: Administrador nuevo con clave
- **GIVEN** que no existe una persona con el email indicado
- **WHEN** se ejecuta `iam:install --admin-email=ana@ubo.cl --admin-nombre="Ana Pérez"` y se confirma
- **THEN** se crea la persona con un `uuid` en minúsculas, una contraseña temporal con hash bcrypt, `temporary_password_expires_at` 72 horas después y el rol `iam-admin/administrador`

#### Scenario: Persona existente
- **GIVEN** una persona existente con el email indicado y contraseña definitiva
- **WHEN** se ejecuta `iam:install` con ese email y se confirma
- **THEN** recibe el rol `iam-admin/administrador` y su contraseña, `temporary_password_expires_at` y datos no cambian

### Requirement: Instalación atómica y secretos mostrados una vez
`iam:install` SHALL hacer todos sus cambios en una sola transacción y mostrar, solo al terminar con éxito, el `client_id` y el secreto de cada cliente y la contraseña temporal del administrador si se creó. Los secretos MUST NOT tomarse del `.env` ni escribirse en logs ni en la auditoría.

#### Scenario: Falla a mitad de la instalación
- **WHEN** `iam:install` falla después de crear `iam-admin`
- **THEN** la base queda como antes de ejecutarlo y no se muestra ningún secreto

#### Scenario: Instalación exitosa
- **WHEN** `iam:install` termina con éxito
- **THEN** muestra una sola vez las líneas `UBO_LOGIN_CLIENT_ID`, `UBO_LOGIN_CLIENT_SECRET`, `UBO_AUTH_CLIENT_ID` y `UBO_AUTH_CLIENT_SECRET` y, si se creó, la contraseña temporal

### Requirement: Acceso de emergencia
`iam:grant-admin {email}` SHALL dar el rol `iam-admin/administrador` a una persona existente y activa, sin cambiar su contraseña. Si la persona está dada de baja, MUST fallar salvo con `--reactivar`, que además la deja activa. Si ya tiene el rol, no cambia nada.

#### Scenario: Recuperar el acceso
- **GIVEN** una persona activa sin roles en `iam-admin`
- **WHEN** se ejecuta `php artisan iam:grant-admin ana@ubo.cl` y se confirma
- **THEN** la persona tiene el rol `iam-admin/administrador` y queda una entrada en la auditoría

#### Scenario: Persona dada de baja
- **GIVEN** una persona con `disabled_at` no nulo
- **WHEN** se ejecuta `php artisan iam:grant-admin` con su email sin `--reactivar`
- **THEN** el comando falla y no cambia nada

#### Scenario: Email inexistente
- **WHEN** se ejecuta `php artisan iam:grant-admin` con un email que no existe
- **THEN** el comando falla y no crea a nadie

### Requirement: Rotación de secreto por consola
`iam:rotate-secret {cliente}` SHALL rotar el secreto de un cliente confidencial, indicado por nombre o por id, con la confirmación de ambiente y base de los comandos `iam:*`, y mostrar una sola vez el secreto nuevo y, para los clientes de `iam-admin`, la línea del `.env` lista para copiar. MUST registrar `cliente.secreto.rotacion` con actor `comando` y MUST fallar sin cambiar nada si el cliente no existe, es público o el nombre corresponde a más de un cliente.

#### Scenario: Recuperar el login del portal
- **WHEN** se ejecuta `php artisan iam:rotate-secret iam-admin-portal` y se confirma
- **THEN** el secreto cambia, se muestra una sola vez la línea `UBO_LOGIN_CLIENT_SECRET=...` y hay una entrada `cliente.secreto.rotacion` con actor `comando`

#### Scenario: Confirmación rechazada
- **WHEN** se ejecuta el comando y se responde "no"
- **THEN** el secreto no cambia y no se muestra ninguno

#### Scenario: Nombre ambiguo o inexistente
- **WHEN** el nombre no corresponde a ningún cliente, o a más de uno
- **THEN** el comando falla sin cambiar nada y, si es ambiguo, lista los ids para elegir
