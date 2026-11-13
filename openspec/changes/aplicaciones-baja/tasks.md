## 1. Esquema

- [x] 1.1 Migración aditiva `disabled_at` en `applications` y `roles` (D1) con test (base con datos: columnas nulas, nada más cambia)

## 2. Casos de uso y consultas

- [x] 2.1 `DarDeBajaAplicacion` y `ReactivarAplicacion`; rutas `POST aplicaciones/{nombre}/baja` y `/reactivacion`
- [x] 2.2 `DarDeBajaRol` y `ReactivarRol`; rutas `POST aplicaciones/{nombre}/roles/{rol}/baja` y `/reactivacion`
- [x] 2.3 `baja_desde` en el listado, el detalle y los roles del detalle (D4)
- [x] 2.4 Tests de feature: baja sin confirmar (409 con impacto) y confirmada, reactivación, idempotencia sin auditoría, asignaciones intactas, `iam-admin` con `rechazado`, 403 y 401, `baja_desde` en las consultas

## 3. Documentación

- [x] 3.1 OpenAPI (cuatro operaciones, `baja_desde`, `BAJA_SIN_CONFIRMAR`) y Bruno; README: orden de despliegue con API-Auth `catalogo-dado-de-baja`; `DocsTest` en verde

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `aplicaciones.service.ts`: baja (con y sin confirmación) y reactivación; `baja_desde` en los tipos
- [x] 4.2 Listado y detalle (D5); tests y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate aplicaciones-baja --strict`
- [ ] 5.2 En desarrollo, con API-Auth `catalogo-dado-de-baja` desplegado y sobre el catálogo de prueba que indique el usuario: dar de baja un rol y comprobar que el token de la persona deja de traer sus scopes; reactivarlo; ver las entradas en Auditoría
