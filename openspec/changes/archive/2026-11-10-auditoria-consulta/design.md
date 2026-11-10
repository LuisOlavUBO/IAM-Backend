## Context

`iam_auditoria` (spec `auditoria`) la escribe solo `App\Shared\Auditoria\AuditorBaseDatos`: `ocurrido_en` (`datetime2(7)` en la zona de la aplicación), `actor_tipo` (`persona` o `comando`), `actor_uuid`, `accion` (código estable, como `cliente.secreto.rotacion`), `entidad_tipo`, `entidad_id` (texto: un `uuid` para usuario y cliente, el id numérico para aplicación, rol y permiso), `resultado`, `motivo`, `antes` y `despues` (JSON en texto) y `trace_id`. Tiene índices por `ocurrido_en`, por `(actor_uuid, ocurrido_en)` y por `(entidad_tipo, entidad_id, ocurrido_en)`. En desarrollo hay 27 entradas, todas de comandos. El dominio Auditoria solo tiene `routes/v1.php`; Usuarios ofrece `IDirectorioUsuariosService::persona(uuid)` en su `Contracts/`.

## Goals / Non-Goals

**Goals:**
- Ver en el portal quién hizo qué, sobre qué y con qué resultado, con los filtros que se necesitan para investigar (fechas, persona, acción, entidad, rechazados).
- Mismo contrato de listado que los demás dominios.

**Non-Goals:**
- Exportar, detalle por entrada, enlaces desde las páginas de usuario y cliente, nombres de entidades con id numérico.
- Cambiar cómo se escribe la auditoría.

## Decisions

**D1. Solo un endpoint de listado, con `antes` y `despues` en cada entrada.** Los valores auditables son pocos campos por diseño (spec `auditoria`), así que viajan completos y el portal los despliega en la fila. Sin endpoint de detalle ni id de la entrada en la respuesta: no hace falta para navegar y no se exponen ids numéricos. Alternativa descartada: detalle por id (un endpoint y una página más sin necesidad hoy).

**D2. Estructura del dominio** (creada con `iam:make-feature`):
```
app/Domains/Auditoria/
+-- AuditoriaServiceProvider.php          IAuditoriaQueryRepository => AuditoriaQueryRepository
+-- routes/v1.php                         can:iam:auditoria.leer
+-- Features/ListarAuditoria/             Endpoint, Request, QueryHandler, Resource
+-- Domain/Dto/                           FiltroAuditoriaDto, EntradaAuditoriaDto, ActorDto
+-- Domain/Interfaces/IAuditoriaQueryRepository.php
+-- Infrastructure/AuditoriaQueryRepository.php
```
La lectura vive en el dominio; la escritura sigue en `Shared/Auditoria` (`IAuditor`), que usan todos. Sin `Contracts/`.

**D3. Nombre del actor por el contrato de Usuarios, en lote.** El repositorio devuelve las entradas con `actor_uuid`; el `QueryHandler` junta los `uuid` distintos de la página y pide `IDirectorioUsuariosService::personas(list<uuid>)`, un método nuevo que hace una sola consulta (`whereIn('uuid', ...)`) y devuelve un mapa `uuid => PersonaBasicaDto`. Así Auditoria no lee `users` directamente (regla de dependencias entre dominios) y no hay N+1. Alternativa descartada: `join users` en el repositorio de Auditoria (rompe la regla de dominios).

**D3b. Nombre actual de la entidad, también por contratos.** `entidad_id` guarda el id interno: un `uuid` para usuario y cliente, y el id numérico para aplicación, rol y permiso (estable ante renombres, pero ilegible: «rol 16»). El `QueryHandler` agrupa los ids de la página por tipo y pide los nombres en lote, una consulta por tipo presente:
- `usuario` → `IDirectorioUsuariosService::personas` (el nombre de la persona);
- `aplicacion`, `rol`, `permiso` → `Aplicaciones/Contracts/IDirectorioAplicacionesService::{aplicaciones, roles, permisos}(ids)`, que devuelven `id => nombre`; el del rol va como `aplicacion/rol`, igual que lo escribe la auditoría;
- `cliente` → `Clientes/Contracts/IDirectorioClientesService::clientes(ids)`.
Antes de consultar se descartan los ids con forma inválida (no numéricos, o no UUID para usuario y cliente), porque SQL Server fallaría al convertirlos. Si la entidad ya no existe o el tipo es otro, `nombre` es `null`. Es el nombre actual, no el del momento de la acción: identifica siempre la misma entidad, y el nombre original queda en `despues` de su creación. Alternativa descartada: guardar el nombre en `entidad_id` al escribir, porque un renombre cortaría la historia de la entidad.

**D4. Filtros sobre los índices existentes.** `desde`/`hasta` se traducen a `ocurrido_en >= desde 00:00` y `ocurrido_en < hasta + 1 día` (zona de la aplicación, igual que la escritura). `accion` usa `LIKE 'prefijo%'` con comodines escapados (`ESCAPE '!'`); `entidad_tipo`, `entidad_id`, `actor` y `resultado` son igualdad. El `actor` se compara en minúsculas, como lo escribe `AuditorBaseDatos`. Orden `ocurrido_en desc, id desc` (el `id` solo desempata y no sale del repositorio).

**D5. `antes`/`despues` decodificados.** Se devuelven como objetos JSON; un valor que no decodifica a un objeto o lista se devuelve como `null`. `ocurrido_en` se formatea con `toIso8601String` (sin fracciones de segundo).

**D6. Validación del Request.** `desde`/`hasta` con `date_format:Y-m-d` y `hasta` `after_or_equal:desde`; `actor` `uuid`; `accion` hasta 100 caracteres; `entidad_tipo` hasta 50; `entidad_id` hasta 100; `resultado` `in:ok,rechazado`; paginación como en los demás listados.

**D7. Frontend.** `dominios/auditoria/`: `auditoria.ts`, `auditoria.service.ts` y `paginas/lista-auditoria` con `rxResource`. Los filtros de fecha, acción y resultado tienen controles; `actor`, `entidad_tipo` y `entidad_id` se respetan desde la URL (para enlazar en el futuro) y se muestran como filtro activo con opción de quitarlo. Cada fila se despliega para ver `antes`, `despues`, el motivo y el `trace_id`. El actor persona enlaza a `/usuarios/{uuid}`. La entidad se muestra como `tipo · nombre` (o `tipo · id (ya no existe)` cuando no tiene nombre) y enlaza a `/usuarios/{id}`, `/clientes/{id}` o `/aplicaciones/{nombre}` cuando es de esos tipos; su id va en el detalle de la fila.

## Risks / Trade-offs

- [La tabla crece sin límite] → El orden y los filtros usan los índices existentes; el `count` del total recorre el índice de `ocurrido_en`. Si el volumen lo exige, un cambio futuro puede limitar el rango por omisión.
- [Nombre actual, no histórico] → Tras un renombre, las entradas viejas muestran el nombre nuevo; el original sigue en `despues` de la creación y el id no cambia (D3b).
- [Más dependencias del caso de uso] → `ListarAuditoria` usa tres contratos; cada uno hace a lo más una consulta por página.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; el endpoint es nuevo, así que el orden no importa.
