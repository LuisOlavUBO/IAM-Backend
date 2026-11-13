# Proposal

## Why

Desde el portal ya se crean aplicaciones, permisos y roles, pero algo creado por error no se puede quitar. Solo se borra lo que no está en uso: lo que ya se usa se da de baja (cambio `aplicaciones-baja`).

Los nombres no se cambian. El nombre de un permiso es el scope que verifica cada sistema y el de un rol viaja en la claim `roles`: renombrarlos rompería a quienes los usan sin que IAM pueda coordinarlo. Un nombre mal escrito se corrige borrando lo creado por error, mientras no esté en uso, y creándolo de nuevo.

## What Changes

- **Borrar lo que no está en uso**: `DELETE /api/iam/v1/aplicaciones/{nombre}`, `…/permisos/{permiso}` y `…/roles/{rol}`.
  - Un permiso se borra si ningún rol lo da; un rol, si ninguna persona ni cliente lo tiene (se van con él los permisos que daba); una aplicación, si no tiene permisos, roles ni clientes.
  - Si está en uso: 409 `EN_USO` con el **impacto** en `impacto`, sin borrar nada: para una aplicación, sus permisos, roles y clientes; para un permiso, los roles que lo dan y las personas y clientes que lo reciben; para un rol, las personas y clientes que lo tienen y sus permisos.
  - Audita `aplicacion.borrado`, `permiso.borrado` o `rol.borrado` con el nombre y la descripción en `antes`.
- Todo exige sesión, `iam:aplicaciones.administrar` y CSRF; el catálogo de `iam-admin` sigue protegido (409 `CATALOGO_DE_IAM_ADMIN`, auditado como `rechazado`).
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): en el detalle de la aplicación (fuera de `iam-admin`), «Borrar» para la aplicación, cada permiso y cada rol, con confirmación y aviso con el impacto si está en uso. Al borrar la aplicación, el portal vuelve al listado.
- Fuera de este cambio: renombrar (los nombres son fijos) y la baja con `disabled_at` (cambio `aplicaciones-baja`, que depende de API-Auth).

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `aplicaciones`: se agrega el borrado de lo que no está en uso, con su impacto.

## Impact

- Backend: `app/Domains/Aplicaciones/` (tres casos de uso, cálculo de impacto en `ICatalogoRepository`, `EnUsoException`), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/aplicaciones/` (servicio y detalle) y sus tests.
- Datos: borra en `applications`, `oauth_scopes`, `roles` y `oauth_scope_role` (dueño: API-IAM), con las FK en cascada que ya existen para `oauth_scope_role`; inserta en `iam_auditoria`.
- API-Auth: sin cambios. Sin migraciones ni dependencias nuevas.
