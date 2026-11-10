## Context

El dominio Aplicaciones solo tiene `routes/v1.php`. Las tablas que consulta (`applications`, `oauth_scopes`, `roles`, `oauth_scope_role`) son de API-IAM y hoy las escribe solo `iam:install`. En desarrollo hay 8 aplicaciones, 23 permisos y 16 roles; los nombres de aplicación son slugs únicos (`iam-admin`, `pago-honorarios`). Usuarios ya resolvió la misma forma de consulta (listado paginado y detalle) y es la referencia.

## Goals / Non-Goals

**Goals:**
- Ver en el portal las aplicaciones, sus permisos y qué permisos da cada rol.
- Construir el primer dominio nuevo con `iam:make-feature` y la estructura estándar, sin piezas nuevas en `Shared`.

**Non-Goals:**
- Escritura (alta, edición, renombre con 409, baja con `disabled_at`).
- Personas o clientes que tienen cada rol: es otra consulta (por rol) y pertenece a Usuarios y Clientes.

## Decisions

**D1. El nombre de la aplicación identifica el recurso: `/aplicaciones/{nombre}`.** Es único, legible (`/aplicaciones/iam-admin`) y evita exponer el id numérico, igual que Usuarios usa el `uuid`. La intercalación de la base (`CI_AS`) ya compara sin mayúsculas. Cuando exista el renombre, la URL cambia con el nombre: aceptable porque el renombre es deliberado y raro. Alternativa descartada: el id numérico (contradice la regla de no exponer ids internos).

**D2. Estructura del dominio** (creada con `iam:make-feature`):
```
app/Domains/Aplicaciones/
+-- AplicacionesServiceProvider.php       IAplicacionQueryRepository => AplicacionQueryRepository
+-- routes/v1.php                         can:iam:aplicaciones.leer
+-- Features/ListarAplicaciones/          Endpoint, Request, QueryHandler, Resource
+-- Features/VerAplicacion/               Endpoint, QueryHandler, Resource (sin Request: no hay parámetros)
+-- Domain/AplicacionNoEncontradaException.php
+-- Domain/Dto/                           FiltroAplicacionesDto, AplicacionResumenDto, AplicacionDetalleDto, PermisoDto, RolDto
+-- Domain/Interfaces/IAplicacionQueryRepository.php
+-- Infrastructure/AplicacionQueryRepository.php
```
Sin `Contracts/`: ningún otro dominio lo usa todavía.

**D3. Listado con conteos por subconsulta.** `select name, description, (select count(*) from oauth_scopes ...) as permisos, (select count(*) from roles ...) as roles`, con `LIKE` escapado (`ESCAPE '!'`, como Usuarios) sobre nombre y descripción, orden por nombre y paginación con `Pagina`/`RespuestaPaginada`. Se pagina aunque hoy sean pocas aplicaciones: mismo contrato que Usuarios y el frontend reutiliza el patrón.

**D4. Detalle en tres consultas fijas**, sin N+1: la aplicación; sus permisos; y sus roles con un `left join` a `oauth_scope_role` y `oauth_scopes` que el repositorio agrupa por rol. Los ids se usan solo dentro de la consulta y no salen del repositorio.

**D5. `AplicacionNoEncontradaException`** como `DomainProblem` 404 con código `APLICACION_NO_ENCONTRADA`, igual que `UsuarioNoEncontradoException`.

**D6. Frontend igual que Usuarios**: `aplicaciones.service.ts`, `aplicacion.ts` (tipos del OpenAPI) y `paginas/lista-aplicaciones` y `paginas/detalle-aplicacion` con `rxResource`, filtros en la URL y la misma guarda de página fuera de rango.

## Risks / Trade-offs

- [URL que cambia con el renombre] → El futuro cambio de renombre deberá redirigir el portal a la nueva URL tras confirmar.
- [Roles con muchos permisos] → El detalle trae todo sin paginar; con los volúmenes actuales (decenas) es aceptable.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; ambos endpoints son nuevos, así que el orden no importa.
