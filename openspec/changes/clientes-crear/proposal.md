# Proposal

## Why

Para conectar un sistema nuevo a IAM hace falta un cliente OAuth, y hoy solo `iam:install` crea clientes (los dos de `iam-admin`). Cualquier otro se tendría que cargar a mano en `oauth_clients`, lo que el proyecto ya no permite. El portal ya consulta, rota y revoca clientes: falta crearlos.

## What Changes

- `POST /api/iam/v1/clientes` con `{nombre, aplicacion, tipo, redirecciones?}`:
  - `tipo` `authorization-code` (login de personas, con al menos una redirección) o `client-credentials` (servicio a servicio, sin redirecciones). Son los dos únicos tipos que se crean, ambos confidenciales.
  - Las redirecciones deben ser URL absolutas `https` (o `http` en `localhost`), sin fragmento: la misma regla de `iam:install`.
  - El nombre no puede repetirse entre los clientes (409 `CLIENTE_EXISTENTE`), para que `iam:rotate-secret` lo encuentre sin ambigüedad.
  - No se crean clientes en `iam-admin` desde el portal (422 en `aplicacion`): esos los crea `iam:install`.
  - Responde 201 con `data.id` y `data.secreto`, el secreto **una sola vez** (`Cache-Control: no-store`), y se audita `cliente.creacion` con `{nombre, aplicacion, tipo, redirecciones}`, sin el secreto (la forma que ya usa `iam:install`).
- Exige sesión, `iam:clientes.administrar` y CSRF.
- `NuevoCliente` deja de hacer SQL en `Domain/`: genera el cliente y el repositorio lo inserta. `iam:install` sigue igual por fuera.
- Caso de uso `CrearCliente` (`iam:make-feature --command`).
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): «Nuevo cliente» en el listado (con `iam:clientes.administrar`), formulario en `/clientes/nuevo` (aplicación, nombre, tipo y redirecciones) y el secreto mostrado una sola vez con `SecretoMostrado`; luego va al detalle.
- Fuera de este cambio: roles del cliente (un cliente `client-credentials` sin roles obtiene tokens sin scopes hasta que se le asignen, en el cambio siguiente), editar redirecciones, clientes públicos, cambiar la aplicación de un cliente.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `clientes`: se agrega la creación de clientes desde el portal.

## Impact

- Backend: `app/Domains/Clientes/` (Feature `CrearCliente`, `NuevoCliente` sin SQL, regla de redirecciones en `Domain/`, `IClientesRepository::crear` y `nombreEnUso`, excepción `ClienteExistente`), `InstallCommand` (usa la regla de redirecciones del dominio), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/clientes/` (servicio, ruta y página `nuevo-cliente`, botón en el listado) y sus tests.
- Datos: inserta en `oauth_clients` (dueño: API-IAM) e `iam_auditoria`.
- API-Auth: sin cambios; usa los clientes que encuentra en `oauth_clients`. Sin migraciones ni dependencias nuevas.
