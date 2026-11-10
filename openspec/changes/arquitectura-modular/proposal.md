# Proposal

## Why

API-IAM será la base de otros sistemas parecidos (backends BFF de portales de la UBO), pero su organización actual no escala: cada dominio agrupa el código por capa técnica (un servicio y un controlador por entidad), lo que con la administración de usuarios llevará a clases con muchas responsabilidades y a casos de uso repartidos en seis carpetas; lo transversal (auditoría, RUT) vive disperso; y ninguna regla de dependencia se verifica, así que el orden depende solo de la disciplina de quien escribe. Conviene fijar la arquitectura ahora, con un solo dominio implementado, antes de que lleguen las escrituras.

## What Changes

- Arquitectura de **monolito modular por dominios con un caso de uso por carpeta** (vertical slices): `Domains/<Dominio>/Features/<CasoDeUso>/` con su endpoint invocable, su request, su handler y su resource.
- Capas opcionales por dominio, que se crean solo cuando hacen falta: `Domain/` (el núcleo: entidades, reglas y excepciones, con `Dto/` e `Interfaces/`), `Infrastructure/` (implementaciones) y `Contracts/` (lo único que otros dominios pueden usar).
- **CQRS lite** (QueryHandler y CommandHandler, sin bus ni eventos) y **nomenclatura al estilo .NET** (`I…`, `…Dto`, `…QueryRepository`, `…Exception`), para que cada clase diga su rol en el nombre.
- **Núcleo compartido `app/Shared`** pequeño: página de resultados y respuesta paginada `{data, meta}`, regla y normalización de RUT, y el puerto de auditoría con su implementación.
- **Lecturas por repositorios de consulta** que devuelven DTO con solo las columnas públicas; las escrituras (cambios siguientes) pasarán por el modelo de dominio.
- **Test de arquitectura** sin dependencias nuevas que falla si se rompe una regla de dependencia.
- **Generador `php artisan iam:make-feature`** que crea la carpeta de un caso de uso con sus archivos y su test.
- **Migración de lo existente**:
  - Usuarios (listar y ver) pasa a `Features/` con un repositorio de consulta; se elimina el modelo `Usuario`, que no se usa para escribir.
  - `Acceso`: `/me` pasa a `Features/` y `ErrorDeLogin` a `Infrastructure/`.
  - `Auditoria` y `Rules\Rut` pasan a `Shared`.
  - `NuevoCliente` pasa a `Clientes/Domain` y `ClaveTemporal` a `Usuarios/Domain`.
- Las convenciones quedan escritas en las guías del proyecto (`.ai/guidelines`, de donde sale `AGENTS.md`) y en el README.
- Sin cambios en la API: rutas, respuestas y errores siguen iguales, y los tests HTTP existentes pasan sin modificarse.

## Capabilities

### New Capabilities
- `arquitectura-de-dominios`: cómo se organiza el código de API-IAM por dominio y caso de uso, qué puede depender de qué, qué es compartido y cómo se verifica y se genera; es la referencia para los sistemas que partan de esta base.

### Modified Capabilities
<!-- Ninguna: el comportamiento de usuarios, acceso-portal, auditoria y comandos-de-operacion no cambia. -->

## Impact

- **Código**: `app/Domains/*` (Usuarios, Acceso, Clientes y Auditoria se reorganizan), nuevo `app/Shared`, `app/Console/Commands` (imports y el generador), `bootstrap/providers.php`, `stubs/` del generador.
- **Tests**: los de feature no cambian; se mueven o ajustan los imports de los unitarios (`RutTest`, `ClaveTemporalTest`, `UsuarioServiceTest` pasa a probar los handlers) y se agrega el test de arquitectura.
- **API, datos y API-Auth**: sin cambios.
- **Documentación**: README (Estructura), `.ai/guidelines/iam/core.blade.php` y `AGENTS.md` regenerado con Boost.
- **Dependencias**: ninguna nueva.
- **Fuera de alcance**: la misma organización en el frontend (`IAM/frontend`), un repositorio plantilla para sistemas nuevos y la migración de API-Maestros; quedan como seguimiento.
