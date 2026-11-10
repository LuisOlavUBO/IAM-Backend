# Proposal

## Why

La sección Auditoría del portal es la última que sigue «en construcción». `iam_auditoria` ya registra cada acción de administración (hoy, las de `iam:install` y los comandos), pero solo se puede leer consultando la base. Antes de que el portal empiece a escribir (roles, bajas, clientes), quien administra IAM necesita ver quién hizo qué, sobre qué y con qué resultado, desde el mismo portal.

## What Changes

- `GET /api/iam/v1/auditoria`: listado paginado de las entradas de auditoría, de la más reciente a la más antigua, con filtros por rango de fechas, actor, acción (prefijo), entidad (tipo e id) y resultado.
- Cada entrada trae la fecha, el actor (tipo, `uuid` y, si es una persona, su nombre y correo), la acción, la entidad (tipo, id y su nombre actual: «rol iam-admin/mesa-de-ayuda» en vez de «rol 16»), el resultado, el motivo, los valores antes y después (como JSON) y el `trace_id`.
- Exige sesión del portal y `iam:auditoria.leer`; no escribe en la base (consultar la auditoría no se audita).
- Caso de uso `ListarAuditoria` creado con `iam:make-feature`, con su repositorio de lectura en `Infrastructure/`. Los nombres del actor y de la entidad se obtienen por lote de los `Contracts/` de cada dominio dueño: Usuarios suma `personas`, y Aplicaciones y Clientes estrenan `Contracts/` con sus directorios de nombres.
- OpenAPI y Bruno del endpoint.
- Frontend (IAM-Frontend): la sección Auditoría pasa de «en construcción» a un listado con filtros en la URL y el detalle de cada entrada (antes y después) desplegable en la misma fila.
- Fuera de este cambio: exportar, un endpoint de detalle por entrada y enlaces «ver auditoría» desde las páginas de usuario y cliente. El nombre que se muestra es el actual de la entidad, no el que tenía al momento de la acción (el original queda en `despues` de su creación).

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `auditoria`: se agrega la consulta del registro desde el portal (permiso, listado, filtros y forma de cada entrada).
- `arquitectura-de-dominios`: el ejemplo de dominio sin capas vacías deja de ser Auditoria (ahora tiene casos de uso) y pasa a Acceso, que no tiene `Domain/`; el de dominio sin `Contracts/` pasa de Aplicaciones (que ahora ofrece uno) a Auditoria.

## Impact

- Backend: `app/Domains/Auditoria/` (Features, Domain, Infrastructure y `AuditoriaServiceProvider` en `bootstrap/providers.php`), `routes/v1.php` del dominio; `Usuarios/Contracts/IDirectorioUsuariosService::personas`, `Aplicaciones/Contracts/IDirectorioAplicacionesService` y `Clientes/Contracts/IDirectorioClientesService` con sus implementaciones; tests de feature y unitarios; `docs/openapi/openapi.yaml` y `docs/bruno/Auditoria/`.
- Frontend: `dominios/auditoria/` (servicio, tipos y página de listado) y sus tests.
- Datos: solo lectura de `iam_auditoria` y, por los contratos, de los nombres en `users`, `applications`, `roles`, `oauth_scopes` y `oauth_clients`.
- Sin migraciones, dependencias nuevas ni cambios en API-Auth.
