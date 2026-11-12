# Proposal

## Why

Un cliente recién creado desde el portal no sirve del todo: si es de servicio (`client-credentials`) obtiene tokens sin scopes hasta que tenga roles, y si es de login (`authorization-code`) sus redirecciones quedan fijas desde su creación (un sistema que cambia de dominio o agrega un ambiente no tiene cómo actualizarlas). Hoy ambas cosas solo se hacen con SQL o con el comando `oauth:client-role` de API-Auth, que ya no escribe esas tablas. Este cambio completa la administración de clientes en el portal.

## What Changes

- **Roles de clientes de servicio**:
  - `POST /api/iam/v1/clientes/{id}/roles` con `{aplicacion, rol}` (201, o 200 si ya lo tenía);
  - `DELETE /api/iam/v1/clientes/{id}/roles/{aplicacion}/{rol}` (200, o 404 `ROL_NO_ASIGNADO`).
  - Solo para clientes `client-credentials`: los roles de un cliente solo dan scopes a sus tokens de cliente (409 `CLIENTE_SIN_ROLES` en un `authorization-code`).
  - Roles de cualquier aplicación, **salvo los de `iam-admin`**, que no se dan a clientes (409 `ROL_DE_IAM_ADMIN`).
  - Se auditan `cliente.rol.asignacion` y `cliente.rol.retiro` con `{rol: "aplicacion/rol"}`, las acciones que ya usa `iam:install`.
  - Al quitar un rol, los tokens ya emitidos conservan sus scopes hasta vencer (máximo 60 minutos), como define API-Auth: los cambios de roles rigen en el siguiente token.
- **Redirecciones de clientes de login**:
  - `PUT /api/iam/v1/clientes/{id}/redirecciones` con `{redirecciones: [...]}` las reemplaza;
  - solo en clientes `authorization-code`, con al menos una, con la regla `Redireccion` (409 `CLIENTE_SIN_REDIRECCIONES` en un `client-credentials`);
  - se audita `cliente.redirecciones.cambio` con `antes` y `despues`.
- **Los clientes de `iam-admin` no se modifican desde el portal** (409 `CLIENTE_DE_IAM_ADMIN`, ya existe): cambiar sus roles o redirecciones podría dejar a API-IAM sin revocación de sesiones o al portal sin login.
- Todo exige sesión, `iam:clientes.administrar` y CSRF; los rechazos de reglas se auditan como `rechazado`.
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): en el detalle del cliente, con `iam:clientes.administrar` y fuera de `iam-admin`: «Asignar rol» y «×» por rol en los de servicio (como en Usuarios) y «Editar redirecciones» (una por línea) en los de login.
- Fuera de este cambio: cambiar la aplicación o el tipo de un cliente, clientes públicos.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `clientes`: se agregan los roles de clientes de servicio y la edición de redirecciones.

## Impact

- Backend: `app/Domains/Clientes/` (Features `AsignarRolACliente`, `QuitarRolACliente`, `CambiarRedirecciones`; excepciones; métodos en `IClientesRepository`; nombres de roles por el contrato de Aplicaciones), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/clientes/` (servicio y detalle) y sus tests.
- Datos: escribe `oauth_client_role` y `oauth_clients.redirect_uris` (dueño: API-IAM) e `iam_auditoria`.
- API-Auth: sin cambios. Sin migraciones ni dependencias nuevas.
