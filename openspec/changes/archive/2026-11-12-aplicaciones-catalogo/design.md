## Context

El catálogo vive en `applications` (`name` único, `description` opcional), `oauth_scopes` (`name` único global, `description` obligatoria, `application_id`), `roles` (`name` único por aplicación, `description` opcional, `application_id`) y `oauth_scope_role`; todo es de API-IAM y API-Auth lo lee en cada emisión de tokens. Hoy solo `iam:install` lo escribe (`ensureApplication`, `ensureCatalog`), auditando `aplicacion.creacion` `{nombre, descripcion}`, `permiso.creacion` `{nombre, aplicacion, descripcion}`, `rol.creacion` `{nombre: "app/rol", descripcion}` y `rol.permiso.asignacion` `{permiso}`, con el id numérico como `entidad_id` (que la auditoría ya traduce a nombre por `IDirectorioAplicacionesService`). El dominio Aplicaciones solo tiene lectura (`ListarAplicaciones`, `VerAplicacion`) y su `Contracts/` de nombres. En desarrollo hay nombres de rol con mayúscula (`Docente`) y permisos sin prefijo (`introspect`). Usuarios y Clientes dejaron el molde de escrituras (reglas puras en `Domain/`, repositorio de escritura, rechazos auditados, idempotencia).

## Goals / Non-Goals

**Goals:**
- Dar de alta un sistema completo desde el portal: aplicación, permisos, roles y qué permisos da cada rol.
- Que nadie amplíe los permisos de `iam-admin` ni reparta permisos `iam:*` desde el portal.

**Non-Goals:**
- Renombrar (con impacto y `confirmar_renombre`), borrar lo nunca usado y la baja con `disabled_at`: cambios siguientes.
- Mover un permiso o un rol de aplicación.

## Decisions

**D1. Rutas por nombre**, bajo la aplicación, en el grupo `can:iam:aplicaciones.administrar`: `POST aplicaciones`, `PATCH aplicaciones/{nombre}`, `POST …/permisos`, `PATCH …/permisos/{permiso}`, `POST …/roles`, `PATCH …/roles/{rol}`, `PUT …/roles/{rol}/permisos`. Los PATCH solo aceptan `descripcion` (el nombre no se cambia aquí); el PUT reemplaza la lista completa, como `PUT clientes/{id}/redirecciones`.

**D2. Estructura** (cada caso de uso con `iam:make-feature Aplicaciones <Caso> --command`):
```
app/Domains/Aplicaciones/
+-- Features/CrearAplicacion, EditarAplicacion, CrearPermiso, EditarPermiso, CrearRol, EditarRol, CambiarPermisosDeRol
+-- Domain/ReglasDeCatalogo.php            formatos de nombre y protección de iam-admin (puras)
+-- Domain/CatalogoDeIamAdminException.php 409; AplicacionExistente, PermisoExistente, RolExistente (409); PermisoNoEncontrado, RolNoEncontrado (404)
+-- Domain/Interfaces/ICatalogoRepository.php   escritura: aplicación, permiso y rol por nombre (con id y aplicación), crear*, describir*, permisosDeRol, asignar/quitar permiso
+-- Infrastructure/CatalogoRepository.php
```
Con más de diez archivos en la raíz de `Domain/`, las excepciones nuevas van a `Domain/Exceptions/` (regla de subcarpetas por tipo).

**D3. Formatos de nombre** (en `ReglasDeCatalogo`, usados por los Request):
- Aplicación: `^[a-z0-9]+(-[a-z0-9]+)*$`, ≤ 100 (como `iam-admin`, `pago-honorarios`).
- Permiso: `^[a-z0-9][a-z0-9:._-]*$`, ≤ 100 (acepta `iam:usuarios.leer`, `geografia:leer`, `introspect`).
- Rol: `^[\pL\pN][\pL\pN ._-]*$`, ≤ 100, sin `/` (acepta `mesa-de-ayuda` y `Docente`).
Se aplican solo a lo nuevo; lo existente no se valida.

**D4. Protección de `iam-admin`.** `ReglasDeCatalogo::verificarQueNoEsIamAdmin(aplicacion)` en todo caso de uso sobre esa aplicación, y al cambiar los permisos de un rol, que ninguno de los permisos nuevos sea de `iam-admin`. Se audita con `IAuditor::rechazar` usando la acción intentada.

**D5. Permisos de un rol.** El handler resuelve todos los nombres (422 en `permisos` con los que no existen), calcula agregados y quitados respecto de los actuales y, en una transacción, inserta y borra en `oauth_scope_role` y registra una entrada por permiso agregado (`rol.permiso.asignacion`) y quitado (`rol.permiso.retiro`), la granularidad de `iam:install`. Sin diferencias: 200 sin cambios ni auditoría.

**D6. Auditoría.** Las acciones de `iam:install` para las altas; ediciones con `antes` y `despues` de la descripción (`aplicacion.edicion`, `permiso.edicion`, `rol.edicion`); `entidad_id` el id numérico, como hoy.

**D7. Frontend.** Listado: «Nueva aplicación» (con `iam:aplicaciones.administrar`) a `/aplicaciones/nueva` (antes de `:nombre`). Detalle (si no es `iam-admin` y con el permiso): editar la descripción; «Nuevo permiso» y «Nuevo rol» con formularios en línea; en cada rol, «Editar permisos» con casillas para los permisos de la aplicación y un campo para agregar por nombre permisos de otras aplicaciones. Tras cada cambio se recarga el detalle; errores por campo y 409 con su `detail`.

## Risks / Trade-offs

- [Un rol de una aplicación que da permisos de otra] → Lo permite API-Auth y ya existe en desarrollo (`pago-honorarios/administrador` da `user:read`); se mantiene, salvo los `iam:*`.
- [Quitar permisos no corta tokens vigentes] → Rige en el siguiente token, como define API-Auth.
- [Nombres mal escritos] → Como no se renombra en este cambio, el formulario muestra el formato esperado; renombrar llega en el cambio siguiente.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; los endpoints son nuevos.
