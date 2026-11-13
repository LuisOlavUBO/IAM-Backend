## Context

`aplicaciones-catalogo` dejó el dominio Aplicaciones con altas y ediciones de descripción (`CrearAplicacion`, `EditarAplicacion`, `CrearPermiso`, `EditarPermiso`, `CrearRol`, `EditarRol`, `CambiarPermisosDeRol`), `ReglasDeCatalogo` (formatos y protección de `iam-admin`), `ICatalogoRepository` y las excepciones `…Existente`, `…NoEncontrado` y `CatalogoDeIamAdminException`. Las FK de `oauth_scope_role`, `role_user` y `oauth_client_role` hacia `roles` y `oauth_scopes` borran en cascada; las de `oauth_scopes`, `roles` y `oauth_clients` hacia `applications` no. La auditoría guarda el id numérico y muestra el nombre actual.

El cambio se propuso también con renombre (con impacto y confirmación en dos pasos). Se descartó al implementarlo: en los tokens, el nombre del permiso es el scope que verifica cada sistema y el del rol viaja en la claim `roles`, así que renombrar no tiene sentido para permisos y roles. El nombre del cambio se mantiene porque `aplicaciones-baja` lo referencia.

## Goals / Non-Goals

**Goals:**
- Quitar lo creado por error, diciendo por qué no se puede cuando está en uso.

**Non-Goals:**
- Renombrar aplicaciones, permisos o roles: los nombres son fijos.
- Baja reversible (`aplicaciones-baja`).

## Decisions

**D1. Rutas.** `DELETE` sobre el recurso, en el grupo `can:iam:aplicaciones.administrar`. Respuesta 200 `{data: {borrado: true}}`.

**D2. Impacto** (`ICatalogoRepository::impactoDe{Aplicacion,Permiso,Rol}`), conteos con consultas agregadas, sin listar personas: aplicación `{permisos, roles, clientes}`; permiso `{roles: [app/rol], personas, clientes}` (personas y clientes distintos que lo reciben por esos roles); rol `{personas, clientes, permisos: [nombres]}`. Acompaña al 409 `EN_USO` como miembro extra del problema RFC 9457 (como `errores` en 422). `aplicaciones-baja` lo reutiliza para su confirmación.

**D3. «En uso».** Permiso: lo da algún rol. Rol: lo tiene alguna persona o cliente (borrarlo quita sus filas de `oauth_scope_role` por la cascada). Aplicación: tiene permisos, roles o clientes (hay que borrarlos o darlos de baja antes). Se define por las referencias actuales: lo que se usó y se dejó de usar se puede borrar; su historia queda en la auditoría con el nombre en `antes`.

**D4. Estructura**: casos de uso `BorrarAplicacion`, `BorrarPermiso`, `BorrarRol` (`iam:make-feature … --command`); `EnUsoException` (409) en la raíz de `Domain/`, como las demás del dominio; el `impacto` sale como miembro extra del problema con `DomainProblem::extensions()` de ubo/common, sin tocar el renderer; `ImpactoDto` en `Domain/Dto/`, con la regla de «en uso». La protección de `iam-admin` reutiliza `ReglasDeCatalogo`.

**D5. Frontend.** En `detalle-aplicacion` (con el permiso y fuera de `iam-admin`): «Borrar» pide `confirm()`; si llega 409 `EN_USO`, muestra el impacto en palabras junto al elemento («Lo dan biblioteca/bibliotecario; lo reciben 3 personas y 0 clientes»). Al borrar la aplicación, navega al listado.

## Risks / Trade-offs

- [Un nombre mal escrito ya en uso queda así] → Se crea el elemento correcto, se migra a quienes lo usan y el viejo se da de baja (`aplicaciones-baja`) o se borra cuando deja de usarse.
- [Carrera entre la comprobación de uso y el borrado] → Las FK de `oauth_scopes`, `roles` y `oauth_clients` hacia `applications` no borran en cascada: una aplicación que ganó un permiso entre medio no se borra. Para permisos y roles, la ventana es mínima y queda en la auditoría.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; los endpoints son nuevos.
