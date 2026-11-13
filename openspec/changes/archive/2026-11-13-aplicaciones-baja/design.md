## Context

`applications` y `roles` son de API-IAM y API-Auth los lee en cada emisión de tokens y en el login central. Las personas ya tienen `users.disabled_at`, con baja reversible auditada como `usuario.baja` / `usuario.alta`. `aplicaciones-renombrar-y-borrar` define el `impacto` y la confirmación en dos pasos (409 con `impacto`) para cambios que afectan a otros. En una tabla que lee API-Auth los cambios solo agregan columnas; las migraciones de API-IAM se registran en `iam_migrations`, y API-Auth guarda una copia del esquema solo para sus tests. API-Auth `catalogo-dado-de-baja` define el efecto en los tokens.

## Goals / Non-Goals

**Goals:**
- Retirar y devolver sistemas y roles sin perder asignaciones.

**Non-Goals:**
- Baja de permisos sueltos (se quita el permiso del rol o se borra si no está en uso).
- Bloquear la asignación de roles de baja.

## Decisions

**D1. Migración aditiva** `agregar_baja_a_aplicaciones_y_roles`: `disabled_at` `dateTime` nulo en `applications` y `roles`, con `hasColumn` para ser idempotente; su `down()` las quita (solo en `testing` tiene sentido; en los demás ambientes no se revierte).

**D2. Rutas como acciones** (`POST …/baja`, `…/reactivacion`), como en personas y clientes, en el grupo `can:iam:aplicaciones.administrar`. La baja pide `confirmar_baja` con el `impacto` de `aplicaciones-renombrar-y-borrar` (`BajaSinConfirmarException`, 409); la reactivación no pide confirmar.

**D3. Casos de uso** `DarDeBajaAplicacion`, `ReactivarAplicacion`, `DarDeBajaRol`, `ReactivarRol` (`iam:make-feature … --command`), con la protección de `iam-admin` de `ReglasDeCatalogo` y la idempotencia de `DarDeBaja` de Usuarios.

**D4. Consultas.** `AplicacionQueryRepository` suma `disabled_at` al listado y al detalle (y a cada rol); los Resources lo exponen como `baja_desde`. El `OpenAPI` agrega el campo a `AplicacionResumen`, `AplicacionDetalle` y al rol.

**D5. Frontend.** Marca «De baja» en el listado y en los roles del detalle; «Dar de baja» con el mismo flujo de impacto y confirmación del renombre, y «Reactivar» con `confirm()`.

## Risks / Trade-offs

- [API-IAM desplegado antes que API-Auth] → La baja queda registrada pero no corta nada hasta que API-Auth la respete; el portal lo dice en el aviso de confirmación mientras el despliegue de API-Auth no esté hecho (se documenta el orden).
- [Tokens vigentes] → Los ya emitidos conservan sus scopes y roles hasta vencer, como en los demás cambios de roles; la introspección los corta para clientes de una aplicación de baja (ver API-Auth).

## Migration Plan

1. Desplegar API-IAM y correr `php artisan migrate` a mano (agrega las columnas).
2. Desplegar API-Auth con `catalogo-dado-de-baja`.
Volver atrás: API-Auth sin el cambio ignora las columnas; las columnas pueden quedarse.
