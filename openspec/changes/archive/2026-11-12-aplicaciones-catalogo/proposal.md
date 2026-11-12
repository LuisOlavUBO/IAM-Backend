# Proposal

## Why

Para conectar un sistema nuevo a IAM hay que registrar su aplicación, sus permisos (scopes) y sus roles, y decir qué permisos da cada rol. Hoy solo `iam:install` crea catálogo (el de `iam-admin` y el base de `api-auth`); todo lo demás requeriría SQL, que el proyecto ya no permite. El portal ya administra personas y clientes: falta que administre el catálogo de las aplicaciones.

## What Changes

- **Aplicaciones**: `POST /api/iam/v1/aplicaciones` con `{nombre, descripcion?}` (201); `PATCH /api/iam/v1/aplicaciones/{nombre}` con `{descripcion}`.
- **Permisos**: `POST /api/iam/v1/aplicaciones/{nombre}/permisos` con `{nombre, descripcion}` (201); `PATCH /api/iam/v1/aplicaciones/{nombre}/permisos/{permiso}` con `{descripcion}`.
- **Roles**: `POST /api/iam/v1/aplicaciones/{nombre}/roles` con `{nombre, descripcion?}` (201); `PATCH /api/iam/v1/aplicaciones/{nombre}/roles/{rol}` con `{descripcion}`.
- **Permisos de un rol**: `PUT /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/permisos` con `{permisos: [nombres]}` reemplaza los permisos que da el rol; pueden ser de cualquier aplicación, como ya permite API-Auth.
- **Nombres**: la aplicación como slug (`pago-honorarios`), único; el permiso único en todo IAM; el rol único dentro de su aplicación, sin `/`. Un nombre repetido responde 409 (`APLICACION_EXISTENTE`, `PERMISO_EXISTENTE`, `ROL_EXISTENTE`). Los nombres no se cambian en este cambio: solo las descripciones.
- **`iam-admin` protegido**: su aplicación, sus permisos `iam:*` y sus roles no se crean, editan ni cambian desde el portal, y sus permisos no se agregan a roles de otras aplicaciones (409 `CATALOGO_DE_IAM_ADMIN`). Cambiar qué permisos da `mesa-de-ayuda` sería un escalamiento de privilegios. Se audita como `rechazado`.
- Todo exige sesión, `iam:aplicaciones.administrar` y CSRF, y se audita con las acciones que ya usa `iam:install` (`aplicacion.creacion`, `permiso.creacion`, `rol.creacion`, `rol.permiso.asignacion`) más `rol.permiso.retiro` y las ediciones (`aplicacion.edicion`, `permiso.edicion`, `rol.edicion`).
- Los cambios en los permisos de un rol rigen en el siguiente token (login o refresh), como define API-Auth.
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): «Nueva aplicación» en el listado; en el detalle, editar la descripción, «Nuevo permiso», «Nuevo rol» y, en cada rol, «Editar permisos» (los de la aplicación marcables y los de otras aplicaciones por nombre). Nada de esto aparece en `iam-admin` ni sin el permiso.
- Fuera de este cambio: renombrar con impacto, borrar lo nunca usado y la baja con `disabled_at` (requiere API-Auth).

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `aplicaciones`: se agrega la administración del catálogo (crear y editar aplicaciones, permisos y roles, y los permisos de cada rol).

## Impact

- Backend: `app/Domains/Aplicaciones/` (siete casos de uso, reglas y excepciones en `Domain/`, `ICatalogoRepository` de escritura), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/aplicaciones/` (servicio, listado, detalle y una página `nueva-aplicacion`) y sus tests.
- Datos: escribe `applications`, `oauth_scopes`, `roles` y `oauth_scope_role` (dueño: API-IAM) e `iam_auditoria`.
- API-Auth: sin cambios; lee el catálogo en cada emisión de tokens. Sin migraciones ni dependencias nuevas.
