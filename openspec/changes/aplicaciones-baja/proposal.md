# Proposal

## Why

Lo que ya se usó no se borra (`aplicaciones-renombrar-y-borrar`), pero hace falta poder retirar un sistema o un rol: un sistema que se apaga, un rol que ya no corresponde. Dar de baja debe ser reversible y dejar todo como estaba para poder volver atrás, igual que con las personas (`disabled_at`).

## What Changes

- Migración en API-IAM (dueño de las tablas): columna `disabled_at` nula en `applications` y en `roles` (solo se agregan columnas, como exige la regla de tablas que lee API-Auth).
- `POST /api/iam/v1/aplicaciones/{nombre}/baja` y `/reactivacion`; `POST /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/baja` y `/reactivacion`.
  - La baja pide confirmación con impacto, como el renombre: sin `confirmar_baja: true` responde 409 `BAJA_SIN_CONFIRMAR` con el `impacto` (personas y clientes que perderían acceso o permisos).
  - Idempotentes; se auditan `aplicacion.baja` / `aplicacion.alta` y `rol.baja` / `rol.alta` con `{"activo": …}`, los mismos nombres que en personas.
  - El catálogo de `iam-admin` no se da de baja (409 `CATALOGO_DE_IAM_ADMIN`).
- Efecto, con API-Auth `catalogo-dado-de-baja`: un rol de baja no da acceso ni permisos (ni a personas ni a clientes) y no viaja en `roles`; una aplicación de baja no deja entrar a nadie, sus clientes no obtienen tokens y sus permisos no se conceden. Las asignaciones (`role_user`, `oauth_client_role`, `oauth_scope_role`) no se tocan, así que reactivar deja todo como estaba.
- Las consultas muestran el estado: `baja_desde` en el listado y el detalle de aplicaciones, y en cada rol del detalle.
- Frontend (IAM-Frontend): «Dar de baja» / «Reactivar» para la aplicación y para cada rol, con el impacto y la confirmación; marca «De baja» en el listado y en los roles.
- Fuera de este cambio: impedir asignar un rol que está de baja (se puede asignar, pero no da nada mientras siga de baja) y dar de baja permisos sueltos.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `aplicaciones`: se agregan la baja y la reactivación de aplicaciones y roles, y el estado en las consultas.
- `esquema-de-datos`: `applications` y `roles` suman `disabled_at`.

## Impact

- Backend: migración, `app/Domains/Aplicaciones/` (cuatro casos de uso, consultas con `baja_desde`), tests, OpenAPI y Bruno.
- Frontend: `dominios/aplicaciones/` (servicio, listado y detalle) y sus tests.
- Datos: agrega columnas y escribe `applications.disabled_at` y `roles.disabled_at` (dueño: API-IAM) e `iam_auditoria`.
- **Depende de API-Auth `catalogo-dado-de-baja`** para que la baja tenga efecto en los tokens. Orden: 1) desplegar API-IAM y correr la migración (las columnas existen y la baja queda registrada, pero aún no corta nada); 2) desplegar API-Auth, que empieza a respetarlas. Sin migraciones destructivas ni dependencias nuevas.
