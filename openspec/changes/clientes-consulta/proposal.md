# Proposal

## Why

La sección Clientes del portal sigue «en construcción»: hoy no hay forma de ver qué clientes OAuth existen, a qué aplicación pertenecen, a qué direcciones pueden volver ni qué roles tienen, salvo consultando la base. Al diagnosticar un login o una integración rota, lo primero que se necesita es ver el cliente. Además es el paso previo a administrarlos (crear, rotar el secreto y revocar).

## What Changes

- `GET /api/iam/v1/clientes`: listado paginado de clientes OAuth, ordenado por nombre, con búsqueda por nombre del cliente o de su aplicación (o por id exacto). Cada uno trae su id, nombre, aplicación, si está revocado y cuántos roles tiene.
- `GET /api/iam/v1/clientes/{id}`: detalle de un cliente con sus concesiones (`grant_types`), si es confidencial, sus redirecciones, si está revocado, sus fechas y sus roles (con la aplicación de cada rol).
- Ambos exigen sesión del portal y `iam:clientes.leer`. El id del cliente (el `client_id` OAuth, público) es el identificador en la URL. Las respuestas MUST NOT exponer el secreto ni su hash, ni ids numéricos; no escriben en la base.
- Casos de uso `ListarClientes` y `VerCliente` creados con `iam:make-feature`, con su repositorio de lectura en `Infrastructure/`.
- OpenAPI y Bruno de los dos endpoints.
- Frontend (IAM-Frontend): la sección Clientes pasa de «en construcción» a lista con búsqueda y paginación, y detalle.
- Fuera de este cambio: crear clientes, rotar el secreto, revocar, editar redirecciones y asignar roles.

## Capabilities

### New Capabilities

- `clientes`: cómo el portal consulta los clientes OAuth registrados en IAM, sus redirecciones y sus roles.

### Modified Capabilities

(ninguna)

## Impact

- Backend: `app/Domains/Clientes/` (Features, `Domain/Dto`, `Domain/Interfaces`, excepción, Infrastructure y `ClientesServiceProvider` en `bootstrap/providers.php`), `routes/v1.php` del dominio, tests de feature y unitarios, `docs/openapi/openapi.yaml` y `docs/bruno/Clientes/`.
- Frontend: `dominios/clientes/` (servicio, tipos, páginas de lista y detalle) y sus tests.
- Datos: solo lectura de `oauth_clients`, `oauth_client_role`, `roles` y `applications`.
- Sin dependencias nuevas ni cambios en API-Auth.
