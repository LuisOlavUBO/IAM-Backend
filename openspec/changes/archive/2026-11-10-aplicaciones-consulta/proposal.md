# Proposal

## Why

La sección Aplicaciones del portal sigue «en construcción»: hoy no hay forma de ver qué sistemas están registrados en IAM, qué permisos define cada uno y qué permisos da cada rol, salvo consultando la base. Es además la base de lo que viene: para asignar roles a personas y clientes primero hay que poder verlos. Es también el primer dominio nuevo que se construye con la arquitectura modular y `iam:make-feature`.

## What Changes

- `GET /api/iam/v1/aplicaciones`: listado paginado de aplicaciones, ordenado por nombre, con búsqueda por nombre o descripción y la cantidad de permisos y roles de cada una.
- `GET /api/iam/v1/aplicaciones/{nombre}`: detalle de una aplicación con sus permisos y sus roles, y para cada rol los permisos que da.
- Ambos exigen sesión del portal y `iam:aplicaciones.leer`; no exponen ids numéricos y no escriben en la base.
- Casos de uso `ListarAplicaciones` y `VerAplicacion` creados con `iam:make-feature`, con su repositorio de lectura en `Infrastructure/`.
- OpenAPI y Bruno de los dos endpoints.
- Frontend (IAM-Frontend): la sección Aplicaciones pasa de «en construcción» a lista con búsqueda y paginación, y detalle.
- Fuera de este cambio: crear, editar y dar de baja aplicaciones, permisos y roles; el renombre con confirmación (409) y la baja con `disabled_at`.

## Capabilities

### New Capabilities

- `aplicaciones`: cómo el portal consulta las aplicaciones registradas en IAM, sus permisos y sus roles.

### Modified Capabilities

- `arquitectura-de-dominios`: el ejemplo de dominio sin capas pasa a Auditoria (Aplicaciones ya tiene casos de uso) y se agrega el de un dominio sin `Contracts/`.

## Impact

- Backend: `app/Domains/Aplicaciones/` (Features, Domain, Infrastructure y `AplicacionesServiceProvider` en `bootstrap/providers.php`), `routes/v1.php` del dominio, tests de feature y unitarios, `docs/openapi/openapi.yaml` y `docs/bruno/Aplicaciones/`.
- Frontend: `dominios/aplicaciones/` (servicio, tipos, páginas de lista y detalle) y sus tests.
- Datos: solo lectura de `applications`, `oauth_scopes`, `roles` y `oauth_scope_role`.
- Sin dependencias nuevas ni cambios en API-Auth.
