# Design

## Context

Ver `proposal.md` → Why. Requisitos: `specs/arquitectura-de-dominios`.

Estado actual que da forma al enfoque:

- **Usuarios** quedó en capas técnicas como API-Maestros (`Http/Controllers/V1`, `Http/Requests/V1`, `Http/Resources/V1`, `Application/UsuarioService`, `Contracts/IUsuarioRepository`, `Infrastructure/Eloquent`, `Models/Usuario`, `Exceptions`). El contrato devuelve `LengthAwarePaginator` y el listado carga el modelo completo (con `password`) para mostrar cinco campos.
- **Acceso** tiene `MeController` (query builder) y `ErrorDeLogin` (renderizador de excepciones del login de `ubo/common`, registrado en `bootstrap/app.php`) sueltos en la raíz del dominio.
- **Auditoria** (`App\Domains\Auditoria\Auditoria`) es transversal: la usan `iam:install` e `iam:grant-admin` y la usarán todos los casos de uso que escriben. Exige transacción abierta y rechaza claves secretas.
- **`App\Rules\Rut`** (regla, `normalize()` y `checkDigit()`) la usan `iam:install` y Usuarios; para saber si un texto es un RUT válido hoy se arma un `Validator`.
- **`Clientes\NuevoCliente`** y **`Usuarios\Application\ClaveTemporal`** los usa `iam:install`.
- Las rutas de todos los dominios se montan en `routes/web.php` (grupo `web`, sesión `ubo.login`); los errores salen en RFC 9457 por `ProblemRenderer` de `ubo/common`, y los de negocio extienden `DomainProblem`.
- Las convenciones del proyecto viven en `.ai/guidelines/iam/core.blade.php` y en su copia de `AGENTS.md` (la compila Laravel Boost cuando está instalado).
- La suite corre contra SQL Server en Docker; `tests/Unit` y `tests/Feature` son los dos testsuites de `phpunit.xml`.

## Goals / Non-Goals

**Goals:**
- Que agregar, cambiar o borrar un caso de uso toque una sola carpeta.
- Que las reglas de dependencia se verifiquen en cada `php artisan test`, sin depender de revisiones.
- Que un sistema nuevo pueda copiar `Shared`, el test de arquitectura y el generador y nacer con la misma forma.
- Cero cambios observables en la API: los tests de feature existentes pasan sin tocarlos.

**Non-Goals:**
- Comandos, eventos de dominio o bus de mensajes: llegan con el primer caso de uso que escribe (administración de usuarios), sobre esta estructura.
- Reorganizar el frontend, crear el repositorio plantilla o migrar API-Maestros.
- Mover a `ubo/common` piezas de `Shared` (se evalúa cuando un segundo sistema las necesite).

## Decisions

### D1. Monolito modular por dominio, con un caso de uso por carpeta
```
app/
├── Shared/                       núcleo compartido (D4)
├── Domains/<Dominio>/
│   ├── <Dominio>ServiceProvider.php   enlaces de cada interfaz con su implementación en Infrastructure
│   ├── routes/v1.php
│   ├── Features/<CasoDeUso>/          Endpoint, Request, QueryHandler o CommandHandler, Resource
│   ├── Domain/                        el núcleo, sin HTTP ni SQL: entidades, reglas y excepciones en la raíz
│   │   ├── Dto/                       objetos de datos que cruzan las interfaces
│   │   └── Interfaces/                repositorios y servicios que el dominio necesita
│   ├── Infrastructure/                implementaciones: repositorios SQL, API-Auth, renderizadores
│   └── Contracts/                     lo público: lo único que otros dominios pueden usar (interfaz + Dto)
└── Console/Commands/                  puntos de entrada que coordinan dominios (D6)
```
- El **endpoint** es un controlador invocable que solo traduce HTTP: recibe el request validado, llama al handler y elige cómo se responde (sobre, código, headers); el **resource** da formato a cada campo. El **handler** es el caso de uso, recibe datos tipados (nunca el request) y por constructor solo las interfaces que usa, así lo pueden llamar también los comandos y se prueba sin HTTP. Así se evita el servicio por entidad, que con la administración de usuarios tendría nueve métodos y seis dependencias.
- Lo que usa un solo caso de uso vive en su carpeta; sube a `Domain/` cuando lo usa un segundo caso de uso (misma regla que `shared/ui` en el frontend). Las capas vacías no se crean.
- *Alternativa:* capas técnicas por dominio, como API-Maestros (lo que hay hoy). Sirve para dominios de solo lectura con pocos endpoints, pero reparte cada caso de uso en seis carpetas y concentra la lógica en servicios grandes. Se descarta como base para dominios con escrituras.
- *Alternativa:* módulos como paquetes de Composer separados. Aísla más, pero agrega versionado y publicación sin beneficio para un equipo y un despliegue.

### D2. Lecturas por repositorios de consulta con DTO
Los casos de uso de lectura usan un repositorio de consulta `Domain/Interfaces/I<Entidad>QueryRepository` cuya implementación (`Infrastructure/<Entidad>QueryRepository`) usa el query builder y selecciona solo las columnas de la respuesta. Devuelve DTO `final readonly` de `Domain/Dto/` (para Usuarios: `FiltroUsuariosDto`, `UsuarioResumenDto`, `UsuarioDetalleDto`, `RolAsignadoDto`) y la página de `Shared` (D4). Las escrituras (cambios siguientes) usarán entidades de `Domain/` con su `I<Entidad>Repository` (D10).
- Usuarios elimina `Models/Usuario`: ningún caso de uso escribe todavía, y el listado deja de cargar `password` en memoria. La entidad de escritura vuelve en `Domain/` con la administración de usuarios.
- Los DTO que cruzan una interfaz viven en `Domain/Dto/`, junto a ella, porque `Domain` no puede depender de `Features`.
- *Alternativa:* un repositorio Eloquent para todo (lo anterior). Mezcla lectura y escritura, carga columnas de más y obliga a exponer `LengthAwarePaginator` en la interfaz.
- *Alternativa:* una capa aparte para las interfaces y DTO internos (primera versión de este cambio, que la llamaba `Contracts/`). Tenía las mismas reglas de dependencia que `Domain/`, así que solo agregaba un concepto y carpetas; se integró en `Domain/`, y el nombre `Contracts/` pasó a la parte pública del dominio (D11).

### D3. Reglas de dependencia
| Desde | Puede usar | No puede usar |
|---|---|---|
| `Features/X` | su propia carpeta, `Domain`, `Contracts`, `Shared` | otro `Features/Y`, `Infrastructure`, otros dominios salvo su `Contracts` |
| `Domain` | `Domain`, `Shared` | `Features`, `Infrastructure`, `Contracts`, `Illuminate\Http` |
| `Infrastructure` | `Domain`, `Contracts`, `Shared` | `Features` |
| `Contracts` | `Contracts`, `Domain`, `Shared` del dominio | `Features`, `Infrastructure` |
| Otro dominio | solo `Contracts` | todo lo demás |
| `Shared` | `ubo/common`, Laravel | `App\Domains` |
| `Console` | `Shared`, `Domain`, `Contracts` y `Features` de cualquier dominio | `Infrastructure` |

### D4. `app/Shared` mínimo
- `Shared/Paginacion/Pagina` (objeto de datos: `items`, `total`, `pagina`, `porPagina`) y `Shared/Paginacion/RespuestaPaginada` (arma `{data, meta: {total, pagina, por_pagina}}` desde una `Pagina` y la clase del resource).
- `Shared/Rut/Rut`: la regla actual (`App\Rules\Rut`) más `Rut::esValido(string): bool`, para no armar un `Validator` cuando solo se pregunta.
- `Shared/Auditoria/IAuditor` (puerto con `registrar` y `rechazar`) y `Shared/Auditoria/AuditorBaseDatos` (la clase actual `Domains\Auditoria\Auditoria`, sin cambios de comportamiento), enlazados por `Shared/SharedServiceProvider`.
- El dominio Auditoría queda para la consulta del log (cambio siguiente): escribir el log es transversal y consultarlo es un dominio.
- *Alternativa:* poner estas piezas en `ubo/common` desde ya. Es lo correcto cuando un segundo sistema las use; antes, publicar versiones de la librería por cada ajuste frena más de lo que ayuda.

### D5. Ubicación de lo disperso
| Hoy | Pasa a | Motivo |
|---|---|---|
| `Acceso/MeController` | `Acceso/Features/VerPersonaConSesion/` (endpoint + handler) | es un caso de uso HTTP. Nombre y correo salen de `users`, tabla de Usuarios: se leen por `Usuarios/Contracts/IDirectorioUsuariosService`, el primer uso de `Contracts/` |
| `Acceso/ErrorDeLogin` | `Acceso/Infrastructure/ErrorDeLogin` | adapta las excepciones del login de `ubo/common` |
| `Domains/Auditoria/Auditoria` | `Shared/Auditoria/AuditorBaseDatos` + `IAuditor` | transversal (D4) |
| `App\Rules\Rut` | `Shared/Rut/Rut` | lo usan Usuarios y los comandos |
| `Clientes/NuevoCliente` | `Clientes/Domain/NuevoCliente` | regla de negocio de clientes; el cambio `clientes-oauth` decide si se separa en regla y repositorio |
| `Usuarios/Application/ClaveTemporal` | `Usuarios/Domain/ClaveTemporal` | regla del dominio que usará también reiniciar clave |
| `Usuarios/Exceptions/UsuarioNoEncontrado` | `Usuarios/Domain/UsuarioNoEncontradoException` | la lanzan varios casos de uso |
| `Usuarios/Application`, `Http`, `Models`, `Infrastructure/Eloquent` | `Features/ListarUsuarios`, `Features/VerUsuario`, `Domain/Dto`, `Domain/Interfaces`, `Infrastructure/` | D1, D2 |

### D6. Comandos de Artisan como puntos de entrada
`iam:install` e `iam:grant-admin` coordinan varios dominios (catálogo, clientes, usuarios, auditoría). Se quedan en `app/Console/Commands` y pueden usar `Shared` y el `Domain`, `Contracts` y `Features` de cualquier dominio, pero no `Infrastructure` (D3). Partirlos en casos de uso por dominio queda para cuando existan esos casos de uso.

### D7. Test de arquitectura sin dependencias nuevas
`tests/Unit/ArquitecturaTest.php` recorre `app/**/*.php`, lee las sentencias `use` de cada archivo, clasifica origen y destino (dominio y capa) y compara con la tabla de D3. Al fallar lista cada `archivo → import prohibido`. Incluye casos negativos y positivos como pares (ruta dentro de `app/`, código) analizados en memoria, sin escribir archivos, para probar que el test detecta cada violación. No necesita base de datos.
- *Alternativa:* `pestphp/pest-plugin-arch` o `phpat`. Expresan las reglas con menos código, pero son dependencias nuevas (requieren aprobación) y el proyecto usa PHPUnit.

### D8. Generador `iam:make-feature`
`app/Console/Commands/MakeFeatureCommand.php` con stubs en `stubs/feature/` (`endpoint`, `request`, `handler`, `resource`, `test`). Crea `<Caso>QueryHandler`, o `<Caso>CommandHandler` con `--command` (D10). Valida PascalCase, se niega a sobrescribir, crea la carpeta del caso de uso y su test en `tests/Feature/<Dominio>/` y muestra la línea de ruta a copiar en `routes/v1.php`. No edita las rutas solo, para que el método HTTP, la URL y el permiso los decida quien lo usa.
- *Alternativa:* los `make:*` de Laravel por separado. No conocen la estructura de carpetas ni la convención de nombres.

### D9. Convenciones escritas donde las leen las personas y los asistentes
Se agrega una sección "Arquitectura" a `.ai/guidelines/iam/core.blade.php` (resumen de D1–D3 y el generador) y la misma sección en `AGENTS.md`. Se copia a mano: `php artisan boost:update` exige `boost:install`, que es interactivo y reescribe otros archivos. El README cambia su sección Estructura. La spec `arquitectura-de-dominios` es la fuente de verdad.

### D10. CQRS lite, hexagonal solo en los bordes
Cada caso de uso es una Query (lee) o un Command (escribe), con su handler en `Features/`. Las Queries van por un `I<Entidad>QueryRepository` directo a DTO (D2); los Commands orquestan la entidad de `Domain/`, su `I<Entidad>Repository`, la transacción y `IAuditor`. Las interfaces existen solo donde hay entrada o salida (base de datos, API-Auth, otro dominio); una regla pura como `ClaveTemporal` no tiene interfaz.
- Sin command bus ni mediator: Laravel ya inyecta el handler en el endpoint. Sin base ni tablas de lectura separadas (la base `IAM` es compartida con API-Auth), sin event sourcing y sin eventos de dominio hasta que haya un caso concreto.
- Costo aceptado: un cambio de columna en una tabla puede tocar la entidad y la consulta; viven en el mismo dominio y los tests de feature lo detectan.
- *Alternativa:* hexagonal completo (puertos de entrada por caso de uso, mappers entre capas, una interfaz por clase). Duplica archivos sin beneficio en un BFF con mayoría de lecturas.

### D11. Nomenclatura al estilo .NET
El negocio en español y el rol técnico como sufijo o prefijo en inglés, para que cada clase diga qué es sin abrir la carpeta: `I…` (interfaz), `…Dto`, `…Exception`, `…QueryRepository` / `…Repository`, `…Service` (lo que un dominio ofrece a otros), y en el caso de uso `…Endpoint`, `…Request`, `…QueryHandler` / `…CommandHandler`, `…Resource`. `Resource` y `Request` mantienen el nombre de Laravel (no `Response`, que es otra clase de Laravel). La implementación no lleva el nombre de la tecnología (`UsuarioQueryRepository`, no `…Sql`): su carpeta ya lo dice.
- Subcarpetas por tipo solo en `Domain/` (`Dto/`, `Interfaces/`); el resto queda plano hasta que una carpeta pase de unos diez archivos (entonces `Entities/`, `Exceptions/`, `Repositories/`…), porque el sufijo ya identifica cada clase.
- Lo que un dominio ofrece a otros vive en `Contracts/` (como el proyecto `Modulo.Contracts` de un monolito modular .NET): interfaces y DTO públicos, que se pueden ampliar pero no cambiar sin revisar a quién los usa. `Domain/` es lo privado. Se descartó `Api/` porque se confunde con las rutas HTTP `/api/iam/v1`.
- *Alternativa:* una subcarpeta por tipo desde el inicio (`Exceptions/`, `ValueObjects/`, `Repositories/`, `Services/`). Repite lo que dice el sufijo y deja carpetas de un archivo.

## Risks / Trade-offs

- [Más archivos por caso de uso (cuatro más su test)] → El generador los crea; a cambio, cada archivo es corto y tiene un solo motivo para cambiar.
- [Duda sobre si algo va en `Features` o en `Domain`] → Regla de D1: en la carpeta del caso de uso hasta que lo use un segundo.
- [El test de arquitectura analiza `use` y no detecta referencias con nombre completo sin `use` ni resoluciones dinámicas del contenedor] → Pint y la convención del proyecto usan siempre `use`; las resoluciones dinámicas son raras y se revisan en el PR.
- [La migración mueve muchos archivos y puede romper imports] → Los tests de feature no cambian y cubren cada endpoint; la suite completa y el test de arquitectura corren al final de cada grupo de tareas.
- [Diverge de API-Maestros] → Es intencional para dominios con escrituras; Maestros puede adoptarla de a poco (seguimiento).

## Migration Plan

Refactor interno sin cambios de API, datos ni configuración: se despliega con la imagen siguiente sin pasos manuales. Rollback: volver a la imagen anterior.
