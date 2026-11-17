# Proposal

## Why

La arquitectura de API-IAM se va a proponer a un equipo de siete desarrolladores, y la carpeta `Domain/` de cada dominio es lo que más confunde: choca con `app/Domains/` (`app/Domains/Usuarios/Domain/`), sugiere entidades DDD que no existen (Eloquent es Active Record, así que las lecturas van a DTO y las escrituras a repositorios + reglas) y mezcla en un solo lugar cuatro tipos de cosas (reglas y objetos de valor, excepciones, DTO e interfaces). Conviene aclararlo antes de que el equipo empiece a escribir código sobre esta base, mientras el cambio es solo mover archivos.

## What Changes

- Se elimina la carpeta `Domain/` de cada dominio y se reemplaza por cuatro carpetas explícitas al nivel del dominio, que juntas forman el **núcleo**:
  - `Rules/`: clases de reglas y objetos de valor (`ReglasDePersonas`, `ReglasDeRoles`, `ReglasDeCatalogo`, `ReglasDeClientes`, `ClaveTemporal`, `SecretoDeCliente`, `Redireccion`, `NuevoCliente`).
  - `Exceptions/`: todas las `…Exception` de negocio.
  - `Dto/`: lo que hoy está en `Domain/Dto/`.
  - `Interfaces/`: lo que hoy está en `Domain/Interfaces/`.
- El núcleo mantiene las reglas de dependencia que hoy tiene `Domain` (no importa `Features`, `Infrastructure`, `Contracts` ni `Illuminate\Http`); `Features` e `Infrastructure` pueden usarlo; otro dominio no.
- `Contracts/` no cambia: sigue siendo lo público del dominio, con sus propias interfaces y DTO. Regla para decidir: «¿lo usa otro dominio? `Contracts/`; ¿solo este? `Dto/` o `Interfaces/`».
- Se aplica en un solo cambio a los cinco dominios que tienen `Domain/` (Aplicaciones, Auditoria, Clientes, Indicadores, Usuarios), sin convivencia de ambas formas: 83 clases cambian de carpeta y de namespace (82 al núcleo y `PersonaCreadaDto` a `Features/CrearUsuario/`, el único caso de uso que la usa), y se actualizan los imports en `app/`, `config/`, `tests/`, los comandos y el stub del generador.
- `ArquitecturaTest` pasa a tratar `Rules`, `Exceptions`, `Dto` e `Interfaces` como una sola capa «núcleo», con sus casos positivos y negativos actualizados, y agrega una regla: un handler (`…QueryHandler`, `…CommandHandler`) no puede importar `Illuminate\Http`, para que «el handler recibe datos tipados, nunca el request» lo verifique el test y no solo la convención.
- Se actualizan la spec `arquitectura-de-dominios`, la guía `.ai/guidelines/iam/core.blade.php`, `AGENTS.md` y el README.
- Sin cambios en la API: rutas, respuestas y errores siguen iguales. La arquitectura sigue siendo la misma (hexagonal lite con vertical slices y CQRS lite); solo cambia el nombre y la forma de las carpetas del núcleo.

## Capabilities

### New Capabilities
<!-- Ninguna. -->

### Modified Capabilities
- `arquitectura-de-dominios`: la organización interna del núcleo (`Rules/`, `Exceptions/`, `Dto/`, `Interfaces/` en vez de `Domain/`), las capas opcionales, las reglas de dependencia, los puntos de entrada, la nomenclatura y las lecturas por repositorios de consulta pasan a nombrar esas carpetas; y el test de arquitectura verifica además que los handlers no importan HTTP.

## Impact

- **Código**: `app/Domains/{Aplicaciones,Auditoria,Clientes,Indicadores,Usuarios}` (83 clases movidas, una de ellas a `Features/CrearUsuario/`; 85 archivos de `Features/`, `Infrastructure/` y `ServiceProvider` con imports), `app/Console/Commands/InstallCommand.php` y `RotateSecretCommand.php`, `config/cache.php` (lista de clases serializables de Indicadores) y `stubs/feature/handler.stub`. Acceso no cambia (no tiene `Domain/`).
- **Tests**: 21 archivos de `tests/` cambian imports; `tests/Unit/ArquitecturaTest.php` cambia su clasificación de capas y sus casos. Ningún test de feature cambia de comportamiento.
- **API, datos, configuración de despliegue y API-Auth**: sin cambios. La caché de Indicadores guarda objetos serializados con su nombre de clase: tras desplegar, lo que haya en caché con el namespace viejo se descarta y se recalcula (ver design).
- **Documentación**: spec `arquitectura-de-dominios`, `.ai/guidelines/iam/core.blade.php`, `AGENTS.md` y README (sección Arquitectura). OpenAPI y Bruno no cambian.
- **Dependencias**: ninguna nueva.
