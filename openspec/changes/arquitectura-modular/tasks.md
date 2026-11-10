# Tasks

## 1. Núcleo compartido

- [x] 1.1 Crear `app/Shared/Rut/Rut` moviendo `App\Rules\Rut` y agregando `Rut::esValido()`, y actualizar sus usos (`iam:install`, Usuarios) y `tests/Unit/RutTest.php`. Verificar que `RutTest` pasa con un caso nuevo para `esValido()` y que no queda ninguna referencia a `App\Rules\Rut`
- [x] 1.2 Crear `Shared/Auditoria/IAuditor` y `Shared/Auditoria/AuditorBaseDatos` (la clase actual `Domains\Auditoria\Auditoria`), enlazarlos en `Shared/SharedServiceProvider` (registrado en `bootstrap/providers.php`) y hacer que `iam:install` e `iam:grant-admin` dependan de `IAuditor`. Verificar que `AuditoriaTest`, `InstallCommandTest` y `GrantAdminCommandTest` pasan sin cambiar sus aserciones (solo imports)
- [x] 1.3 Crear `Shared/Paginacion/Pagina` y `Shared/Paginacion/RespuestaPaginada` (design D4). Verificar con `tests/Unit/RespuestaPaginadaTest.php` que arma `{data, meta: {total, pagina, por_pagina}}` desde una `Pagina` y un resource

## 2. Usuarios como referencia

- [x] 2.1 Crear `Usuarios/Contracts/IUsuariosConsultas` con sus datos en `Contracts/Datos/` (`FiltroUsuarios`, `UsuarioResumen`, `UsuarioDetalle`, `RolAsignado`) e implementarlo en `Infrastructure/Consultas/UsuariosConsultasSql` con el query builder (solo columnas públicas, `LIKE` escapado, RUT, orden y paginación con `Pagina`). Enlazarlo en `UsuariosServiceProvider`. Verificar con un test de integración que el listado selecciona solo `uuid`, `name`, `email`, `rut` y `disabled_at`
- [x] 2.2 Crear `Features/ListarUsuarios` (`ListarUsuariosEndpoint`, `ListarUsuariosRequest`, `ListarUsuariosHandler` que normaliza el RUT con `Rut::esValido`, `UsuarioResumenResource`) y `Features/VerUsuario` (`VerUsuarioEndpoint`, `VerUsuarioHandler`, `UsuarioDetalleResource`), mover `ClaveTemporal` y `UsuarioNoEncontrado` a `Domain/`, apuntar las rutas a los endpoints y borrar `Application/`, `Http/`, `Models/` y `Infrastructure/Eloquent`. Verificar que `ListarUsuariosTest` y `VerUsuarioTest` pasan sin cambios y que `UsuarioServiceTest` pasa a `tests/Unit/Usuarios/` probando los dos handlers con un `IUsuariosConsultas` falso

## 3. Acceso y Clientes

- [x] 3.1 Mover `MeController` a `Acceso/Features/VerPersonaConSesion` (endpoint, handler y resource) y `ErrorDeLogin` a `Acceso/Infrastructure/`, actualizando `routes/v1.php` y `bootstrap/app.php`. Verificar que `MeTest` y `LoginTest` pasan sin cambios
- [x] 3.2 Mover `NuevoCliente` a `Clientes/Domain/` y actualizar `iam:install`. Verificar que `NuevoClienteTest` e `InstallCommandTest` pasan cambiando solo imports

## 4. Verificación de la arquitectura

- [x] 4.1 Crear `tests/Unit/ArquitecturaTest.php` con las reglas de design D3 (dentro de un dominio, entre dominios, `Shared` y `Console`). Verificar que pasa sobre el código y que falla, nombrando archivo e import, con ejemplos en memoria de cada escenario de "Dependencias dentro de un dominio", "Dependencias entre dominios" e "Infraestructura importada por un comando"
- [x] 4.2 Crear `iam:make-feature` con stubs en `stubs/feature/` (design D8). Verificar con `tests/Feature/MakeFeatureCommandTest.php` los escenarios "Caso de uso nuevo" (archivos creados en un dominio temporal, Pint sin cambios y test de arquitectura en verde), "Caso de uso que ya existe" y "Nombre inválido", limpiando lo generado al terminar

## 5. Documentación e integración

- [x] 5.1 Agregar la sección Arquitectura a `.ai/guidelines/iam/core.blade.php`, llevarla a `AGENTS.md` (a mano: `php artisan boost:update` pide `boost:install`, que es interactivo y reescribe más archivos) y actualizar la sección Estructura del README. Verificar que `AGENTS.md` contiene la sección y que el README describe `Shared`, `Features` y el generador
- [x] 5.2 Ejecutar `vendor/bin/pint --test`, la suite completa (`php artisan test`), `php artisan route:list --path=api/iam` y `openspec validate arquitectura-modular --strict`. Verificar que todo pasa, que las rutas y sus middleware son los mismos que antes del cambio y que `DocsTest` sigue en verde
- [x] 5.3 Con el backend de desarrollo arriba, abrir el portal y verificar que `/me` (nombre y menú), el listado, la búsqueda por RUT y el detalle de usuarios responden igual que antes

## 6. Estructura simplificada y nomenclatura

- [x] 6.1 Integrar `Contracts/` en `Domain/` (`Domain/Dto/`, `Domain/Interfaces/`), aplanar `Domain/Exceptions/` e `Infrastructure/Consultas/` y renombrar al estilo .NET (design D10 y D11): `IUsuarioQueryRepository`, `UsuarioQueryRepository`, `*Dto`, `UsuarioNoEncontradoException`, `IDirectorioUsuariosService`, `DirectorioUsuariosService`, `*QueryHandler` y `<Caso>Resource`; `Api/` pasa a `Contracts/` (lo público del dominio). Verificar que los tests HTTP pasan sin cambios y que `ArquitecturaTest` aplica a `Contracts` las reglas de la parte pública (la antigua `Api`)
- [x] 6.2 Agregar `--command` a `iam:make-feature` (`<Caso>CommandHandler`; sin la opción, `<Caso>QueryHandler`). Verificar con `MakeFeatureCommandTest`
- [x] 6.3 Actualizar la spec, el design, la guía, `AGENTS.md` y el README, y ejecutar `vendor/bin/pint --test`, `php artisan test` y `openspec validate arquitectura-modular --strict`

## Workflow follow-up

- Archivar el cambio cuando esté desplegado y verificado.
- Frontend (`IAM/frontend`): aplicar la misma organización (`dominios/<d>/data`, `features`, `ui`) y su verificación de dependencias.
- Repositorio plantilla para sistemas nuevos con `Shared`, el test de arquitectura, el generador y la configuración de OpenSpec.
- API-Maestros: evaluar adoptar `Features/` en sus próximos cambios con escrituras.
- Evaluar mover `Shared/Paginacion` y `Shared/Rut` a `ubo/common` cuando otro sistema los necesite.
