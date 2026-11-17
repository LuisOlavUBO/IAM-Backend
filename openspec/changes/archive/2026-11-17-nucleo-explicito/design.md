# Design

## Context

Ver `proposal.md` → Why. Requisitos: `specs/arquitectura-de-dominios`. La arquitectura vigente está en la spec `arquitectura-de-dominios` y en el design archivado `2026-11-10-arquitectura-modular` (D1 estructura, D3 dependencias, D10 CQRS lite, D11 nomenclatura .NET).

Estado actual que da forma al enfoque:

- Cinco dominios tienen `Domain/`: Aplicaciones, Auditoria, Clientes, Indicadores y Usuarios (83 clases). Acceso no tiene.
- La regla «el handler recibe datos tipados, nunca el request» hoy es solo convención: `ArquitecturaTest` no distingue el handler de las demás clases del caso de uso.
- La raíz de `Domain/` mezcla reglas puras (`ReglasDe…`), objetos de valor (`ClaveTemporal`, `SecretoDeCliente`, `Redireccion`), un armador (`NuevoCliente`) y 25 excepciones; `Dto/` e `Interfaces/` ya son subcarpetas.
- Cuatro clases de reglas lanzan excepciones de su mismo namespace sin `use` (`ReglasDeCatalogo`, `ReglasDeClientes`, `ReglasDePersonas`, `ReglasDeRoles`): al separarlas en carpetas distintas necesitan el `use`.
- `tests/Unit/ArquitecturaTest.php` clasifica la capa por la tercera parte de la ruta (`Domains/<Dominio>/<capa>/…`) y trata cualquier capa desconocida como permitida (`default => true`).
- `config/cache.php` lista los 12 DTO de Indicadores en `serializable_classes`: la caché de indicadores (`indicadores.<bloque>`, 300 s) guarda esos objetos serializados con el nombre de su clase.
- Referencias al namespace `…\Domain\…` fuera de `Domain/`: 85 archivos en `app/Domains` (features, infraestructura y providers), 2 comandos (`InstallCommand`, `RotateSecretCommand`), `config/cache.php` y 21 archivos de `tests/`. El stub `handler.stub` menciona `Domain/Interfaces` y `Domain/Dto` en comentarios.

## Goals / Non-Goals

**Goals:**
- Que la carpeta diga qué tipo de clase hay adentro sin conocer DDD, y que no haya dos carpetas llamadas casi igual (`Domains/` y `Domain/`).
- Que el test de arquitectura impida volver a crear `Domain/` u otra carpeta fuera de la estructura, y que un handler importe HTTP.
- Cero cambios observables: rutas, respuestas, errores, datos y despliegue iguales.

**Non-Goals:**
- Cambiar la arquitectura (sigue siendo hexagonal lite con vertical slices y CQRS lite), las reglas de dependencia, `Contracts/`, `Shared`, `Features/` o `Infrastructure/`.
- Renombrar clases o cambiar su contenido más allá del namespace y los `use`.
- Introducir entidades, agregados u objetos de dominio nuevos.

## Decisions

### D1. Cuatro carpetas explícitas al nivel del dominio
```
app/Domains/<Dominio>/
├── <Dominio>ServiceProvider.php
├── routes/v1.php
├── Features/<CasoDeUso>/     QUÉ HACE: Endpoint, Request, QueryHandler o CommandHandler, Resource
├── Rules/                    ┐ reglas puras y objetos de valor
├── Exceptions/               │ NÚCLEO: sin HTTP ni SQL, privado del dominio
├── Dto/                      │ objetos de datos que cruzan las interfaces
├── Interfaces/               ┘ repositorios y servicios que el dominio necesita
├── Infrastructure/           CÓMO: SQL, API-Auth
└── Contracts/                LO PÚBLICO para otros dominios (interfaces + DTO)
```
- Las cuatro carpetas son una sola capa, el **núcleo**, con las mismas reglas que hoy tiene `Domain/` (D3 de `arquitectura-modular`): no importan `Features`, `Infrastructure`, `Contracts` ni `Illuminate\Http`, y se usan entre sí libremente (una regla usa un Dto y lanza una excepción). `Features` e `Infrastructure` pueden usarlas; otro dominio no.
- Como hoy, cada carpeta existe solo si tiene algo: Auditoria e Indicadores quedan con `Dto/` e `Interfaces/`.
- Correspondencia de namespaces: `…\Domain\Dto\X` → `…\Dto\X`; `…\Domain\Interfaces\X` → `…\Interfaces\X`; `…\Domain\XException` → `…\Exceptions\XException`; el resto de la raíz → `…\Rules\X`.
- Los nombres de carpeta siguen la misma convención .NET de los sufijos (D11): plural en inglés por tipo técnico, como `Exceptions/` o `Interfaces/` en un proyecto .NET. No implican Clean Architecture: no hay capa de aplicación separada, ni casos de uso fuera de `Features/`, ni mappers entre capas.

### D2. Clasificación de la raíz de `Domain/`
Todos los archivos encajan en `Rules/` o `Exceptions/`; ninguno queda sin lugar.

| Dominio | `Rules/` | `Exceptions/` |
|---|---|---|
| Aplicaciones | `ReglasDeCatalogo` | `AplicacionExistente`, `AplicacionNoEncontrada`, `BajaSinConfirmar`, `CatalogoDeIamAdmin`, `EnUso`, `PermisoExistente`, `PermisoNoEncontrado`, `RolExistente`, `RolNoEncontrado` (9) |
| Clientes | `ReglasDeClientes`, `NuevoCliente`, `Redireccion`, `SecretoDeCliente` | `ClienteDeIamAdmin`, `ClienteExistente`, `ClienteNoEncontrado`, `ClientePublico`, `ClienteSinRedirecciones`, `ClienteSinRoles`, `RolDeIamAdmin`, `RolNoAsignado` (8) |
| Usuarios | `ReglasDePersonas`, `ReglasDeRoles`, `ClaveTemporal` | `AccionSobreSiMismo`, `CorreoEnUso`, `EscalamientoDePrivilegios`, `RevocacionFallida`, `RolNoAsignado`, `RutEnUso`, `UltimoAdministrador`, `UsuarioNoEncontrado` (8) |

(Las excepciones se listan sin el sufijo `Exception`.) `Dto/` recibe 39 clases (Aplicaciones 9, Auditoria 3, Clientes 7, Indicadores 12, Usuarios 8) e `Interfaces/` 10 (2, 1, 2, 1, 4). Al núcleo van 8 + 25 + 39 + 10 = 82; la clase 83 es `PersonaCreadaDto`, que va a `Features/CrearUsuario/` (D2b).

Casos de borde, resueltos así:
- `ClaveTemporal`, `SecretoDeCliente` (objetos de valor que generan y hashean) y `Redireccion` (regla de URL que además implementa `ValidationRule` de Laravel para usarse en los requests) van a `Rules/`: son reglas puras del negocio, sin E/S. `ValidationRule` es de `Illuminate\Contracts\Validation`, no de `Illuminate\Http`, así que no rompe la regla del núcleo; y coincide con lo que Laravel llama *rule* (`app/Rules`).
- `NuevoCliente` arma los atributos de un cliente OAuth confidencial con las mismas reglas que API-Auth: es la regla de cómo nace un cliente, así que va a `Rules/`.
- *Alternativa:* una carpeta `ValueObjects/` para `ClaveTemporal`, `SecretoDeCliente` y `Redireccion`, y `Factories/` para `NuevoCliente`. Serían cuatro clases repartidas en dos carpetas más; el sufijo y el comentario de la clase ya dicen qué son (KISS).

### D2b. `PersonaCreadaDto` baja a su caso de uso
`Usuarios/Domain/Dto/PersonaCreadaDto` solo lo usan `CrearUsuarioCommandHandler` (lo devuelve) y `CrearUsuarioResource` (lo formatea): ninguna interfaz, regla, infraestructura ni `Contracts/` lo referencia. Por la regla vigente («lo que usa un solo caso de uso vive en su carpeta; sube al núcleo cuando lo usa un segundo») va a `Features/CrearUsuario/PersonaCreadaDto`, como ya están `ClaveReiniciadaDto`, `ClienteCreadoDto` o `SecretoRotadoDto`. Sigue importando `ClaveTemporal`, ahora de `Usuarios/Rules`, que `Features` puede usar. El resto de `Dto/` cruza una interfaz o lo usan varios casos de uso, así que se queda.

### D3. `Contracts/` no cambia; regla para decidir dónde va un Dto o una interfaz
`Contracts/` sigue siendo lo público del dominio, con sus propias interfaces y DTO (`IDirectorioUsuariosService`, `PersonaBasicaDto`). La pregunta para el equipo es una sola: **¿lo usa otro dominio? `Contracts/`. ¿Solo este? `Dto/` o `Interfaces/`.** Pasar algo de `Dto/` a `Contracts/` es una decisión explícita, porque desde ese momento cambiarlo exige revisar a quién lo usa.

### D4. `ArquitecturaTest` con una capa «núcleo» y estructura cerrada
- `ubicarArchivo` y `ubicarClase` traducen `Rules`, `Exceptions`, `Dto` e `Interfaces` a una sola capa `nucleo` (una constante con las cuatro carpetas); las reglas de `permitida` y `permitidaEnElDominio` reemplazan `Domain` por `nucleo`. La tabla del docblock se actualiza igual.
- Nuevo: un archivo de `app/Domains/<Dominio>/` en una carpeta que no sea `Features`, `Rules`, `Exceptions`, `Dto`, `Interfaces`, `Infrastructure`, `Contracts` ni `routes` se reporta como violación («carpeta no reconocida»). Hoy una capa desconocida se acepta en silencio (`default => true`), así que un `Domain/` que alguien vuelva a crear pasaría el test sin que se aplique ninguna regla. Con siete personas escribiendo, el test es el que mantiene la forma.
- Nuevo: un handler (archivo de `Features/<Caso>/` cuyo nombre termina en `QueryHandler.php` o `CommandHandler.php`) no puede importar `Illuminate\Http\…`. Así «el handler recibe datos tipados, nunca el request» (spec, «Organización por dominio y caso de uso») lo verifica el test y no solo la convención; hoy ningún handler lo importa. El Endpoint, el Request y el Resource del mismo caso de uso sí pueden, porque son el adaptador HTTP. La regla se decide por el sufijo del archivo, que ya es obligatorio (D11 de `arquitectura-modular`), sin agregar capas al test.
- Casos negativos (reemplazan los que nombran `Domain/`): el núcleo importa un caso de uso (`Usuarios/Rules` → `Features/VerUsuario`); una interfaz del núcleo importa HTTP (`Usuarios/Interfaces` → `Illuminate\Http\Request`); una excepción importa infraestructura (`Usuarios/Exceptions` → `Infrastructure`); un Dto importa `Contracts`; un dominio usa el núcleo de otro (`Aplicaciones/Rules` → `Usuarios/Rules\ClaveTemporal`); un archivo en `Usuarios/Domain/` (carpeta no reconocida); un handler importa HTTP (`Features/ListarUsuarios/ListarUsuariosQueryHandler.php` → `Illuminate\Http\Request`, y lo mismo con un `CommandHandler`).
- Casos positivos: un endpoint importa HTTP (`Features/CrearUsuario/CrearUsuarioEndpoint.php` → `Illuminate\Http\JsonResponse`); un caso de uso usa las cuatro carpetas y `Shared`; una regla usa un Dto y una excepción de su dominio; una interfaz usa sus Dto; la infraestructura implementa una interfaz y devuelve un Dto; un comando usa `Clientes/Rules\NuevoCliente`.
- `MakeFeatureCommandTest` usa `violacionesEn` sobre el código generado; no cambia.
- *Alternativa:* cuatro capas separadas con reglas entre ellas (por ejemplo, que `Exceptions` no use `Rules`). Son reglas que nadie pidió y que obligarían a pensar en el orden de cuatro carpetas que hoy se usan juntas.

### D5. Migración en un solo cambio, mecánica
- Mover con `git mv` (conserva el historial por archivo) y reemplazar los namespaces con la correspondencia de D1 en `app/`, `config/` y `tests/`.
- Agregar los `use` de excepciones en las cuatro clases de reglas que hoy las usan sin importarlas (D2 de Context). PHP no avisa hasta que la línea se ejecuta: los tests unitarios de reglas (`ReglasDe…Test`) lo cubren.
- Al terminar, `grep` de `\Domain\` sobre `app/`, `config/` y `tests/` no debe dar resultados propios (excluido `UBO\Common\Login\Domain\UboUser`, que es de `ubo/common`).
- Sin convivencia: se hace de una vez porque son 83 archivos movidos sin cambio de lógica, y dos formas a la vez es justo lo que confunde al equipo. Debe hacerse cuando no haya ramas abiertas que toquen `app/Domains` (hoy no hay).

### D6. Clave de la caché de Indicadores
La caché guarda los DTO serializados con su nombre de clase y `serializable_classes` solo permite las de `config/cache.php`. Tras desplegar, una entrada escrita por la versión anterior (`…\Domain\Dto\PersonasDto`) se deserializaría como `__PHP_Incomplete_Class` y `GET /indicadores` fallaría hasta que venza (300 s). Se cambia la clave a `indicadores.<bloque>.v2` (o equivalente) en `VerIndicadoresQueryHandler`: la versión nueva no lee lo que dejó la anterior, y las entradas viejas vencen solas. Si se vuelve a la imagen anterior, esta lee su propia clave y tampoco se cruzan.
- *Alternativa:* `php artisan cache:clear` al desplegar. Es un paso manual más, y en un ambiente con `CACHE_STORE=database` vaciaría la tabla `cache`, que es de API-Auth.
- *Alternativa:* aceptar hasta cinco minutos de error en el tablero. Se descarta porque es evitable con una línea.

### D7. Por qué no hay entidades
La arquitectura sigue siendo **hexagonal lite** (interfaces solo en los bordes: base de datos, API-Auth, otro dominio) con **vertical slices** (`Features/`) y **CQRS lite** (D10 de `arquitectura-modular`). No hay entidades de dominio porque Eloquent es Active Record: un modelo mezcla datos, reglas y persistencia, y envolverlo en entidades puras obligaría a mapear en cada escritura. Por eso:
- las **lecturas** van por un `I<Entidad>QueryRepository` directo a DTO de `Dto/`;
- las **escrituras** van por un `I<Entidad>Repository` que recibe y devuelve DTO, con las verificaciones en clases de `Rules/` (puras: los datos llegan como argumentos) y los errores como clases de `Exceptions/`.
El nombre `Domain/` prometía otra cosa; `Rules/` dice lo que hay.

### D8. Alternativa descartada: renombrar `Domain/` a `Core/`
Resuelve el choque de nombres con `app/Domains/` y la promesa de entidades DDD, pero deja la carpeta mezclando cuatro tipos de clases, con unas en la raíz y otras en subcarpetas, y la pregunta «¿esto va en la raíz o en una subcarpeta?» sigue abierta. Otras descartadas: mantener `Domain/` con `Rules/` y `Exceptions/` adentro (sigue el choque de nombres y un nivel de más) y `Models/` (en Laravel sugiere modelos Eloquent).

### D9. Regla de crecimiento
Igual que hoy: las carpetas del núcleo, `Infrastructure/` y `Features/<Caso>/` quedan planas hasta pasar de unos diez archivos; entonces se agrupan por tema (por ejemplo, `Exceptions/Roles/`), no por tipo, porque el tipo ya lo dice la carpeta. El test acepta subcarpetas dentro de las del núcleo (la capa sale de la tercera parte de la ruta). `Indicadores/Dto/` tiene 12 clases y queda plana: todas son el resultado de una sola consulta (`VerIndicadores`) y separarlas no ayuda a encontrarlas.

### D10. Documentación
La spec `arquitectura-de-dominios` se actualiza al archivar. `.ai/guidelines/iam/core.blade.php` y `AGENTS.md` cambian las líneas de capas opcionales, «sube a `Domain/`» y dependencias (se copian a mano, como en D9 de `arquitectura-modular`). El README cambia el árbol de Usuarios y las viñetas de la sección Arquitectura. El comentario de `stubs/feature/handler.stub` pasa a nombrar `Interfaces/` y `Dto/`. OpenAPI y Bruno no cambian.

## Risks / Trade-offs

- [Más carpetas en la raíz del dominio (hasta ocho)] → Cada una tiene un solo tipo de clase y se crea solo si hace falta; Auditoria e Indicadores quedan con dos.
- [`Interfaces/` y `Contracts/` lado a lado pueden confundirse] → La regla de D3 en la guía, el README y el comentario de la clase; `Contracts/` es la única carpeta que otro dominio puede importar, y el test lo hace cumplir.
- [Una clase de reglas que lanza una excepción sin `use` falla solo en ejecución] → Paso explícito en D5 y los tests unitarios de cada `ReglasDe…` (`php -l` no lo detecta; la suite sí).
- [Caché de Indicadores con clases del namespace viejo] → D6.
- [Conflictos con ramas abiertas] → Se aplica cuando no hay ramas que toquen `app/Domains`; si aparece una, se rebasa con el mismo reemplazo de namespaces.

## Migration Plan

Refactor interno sin cambios de API, datos ni configuración de despliegue: se despliega con la imagen siguiente sin pasos manuales (D6 evita limpiar la caché). Rollback: volver a la imagen anterior.
