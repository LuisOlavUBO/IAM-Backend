# Convenciones de API-IAM

## Datos
- La base `IAM` es compartida con API-Auth. Cada tabla tiene un solo servicio que escribe: API-IAM es dueño de `users`, `applications`, `oauth_scopes`, `roles`, `oauth_scope_role`, `role_user`, `oauth_client_role`, `oauth_clients` y de toda tabla `iam_*`. Nunca escribir en las tablas de API-Auth (`oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_auth_codes`, `oauth_device_codes`, `sessions`, `cache`, `jobs`, `password_reset_tokens`, `migrations`).
- Las migraciones se registran en `iam_migrations`. En las tablas que lee API-Auth los cambios solo agregan columnas o tablas: renombrar o borrar exige antes una versión de API-Auth que ya no las use.
- Las tablas propias llevan el prefijo `iam_`.
- No referirse a claves primarias por nombre en migraciones: sus nombres difieren entre ambientes.
- No se cargan datos a mano por SQL. Un secreto perdido se recupera con `iam:rotate-secret` (README, "Procedimiento de emergencia").
- `migrate:fresh`, `migrate:refresh`, `migrate:reset` y `db:wipe` están prohibidos fuera de `testing`: borrarían también las tablas de API-Auth.

## Contraseñas y secretos
- Hasher bcrypt con `BCRYPT_ROUNDS=12`, igual que API-Auth: API-Auth valida con `Hash::check` lo que escribe API-IAM. No cambiar el driver.
- Las contraseñas temporales y los secretos de clientes se muestran una sola vez y nunca se registran en logs ni en la auditoría.

## API
- Todas las rutas van bajo `/api/iam/v1`, en español, organizadas por dominio en `app/Domains/<Dominio>/routes/v1.php`.
- Errores en RFC 9457 con `ubo/common` (Problems), con códigos estables en mayúsculas (`RUTA_NO_ENCONTRADA`).
- `uuid` de usuarios en minúsculas: SQL Server devuelve los `uniqueidentifier` en mayúsculas.

## Arquitectura
- Monolito modular por dominio, CQRS lite y un caso de uso por carpeta (spec `arquitectura-de-dominios`): `app/Domains/<Dominio>/Features/<CasoDeUso>/` con `<CasoDeUso>Endpoint` (invocable, solo HTTP), `<CasoDeUso>Request`, `<CasoDeUso>QueryHandler` (lee) o `<CasoDeUso>CommandHandler` (escribe) y `<CasoDeUso>Resource`. El handler recibe datos tipados, nunca el request. Sin bus ni eventos.
- Crear un caso de uso con `php artisan iam:make-feature <Dominio> <CasoDeUso>` (`--command` si escribe) y agregar la línea de ruta que muestra en `routes/v1.php` del dominio.
- Capas opcionales del dominio, solo si tienen algo: `Domain/` (el núcleo, sin HTTP ni SQL: entidades, reglas y excepciones en la raíz, DTO en `Domain/Dto/`, interfaces en `Domain/Interfaces/`), `Infrastructure/` (implementaciones: SQL, API-Auth) y `Contracts/` (lo único que otros dominios pueden usar). Cada dominio enlaza interfaz → implementación en su `ServiceProvider` (`bootstrap/providers.php`).
- Nombres: negocio en español y rol en inglés al estilo .NET: `I…` (interfaz), `…Dto` (`final readonly`), `…Exception`, `…QueryRepository` (lectura) / `…Repository` (escritura), `…Service` (lo que un dominio ofrece a otros). Subcarpetas por tipo solo cuando una carpeta pasa de unos diez archivos.
- Lo que usa un solo caso de uso vive en su carpeta; sube a `Domain/` cuando lo usa un segundo.
- `app/Shared` solo para lo que usan varios dominios: `Paginacion` (`Pagina` y `RespuestaPaginada`), `Rut` y `Auditoria` (`IAuditor`).
- Dependencias: un caso de uso no importa otro caso de uso ni `Infrastructure`; `Domain` no importa `Features`, `Infrastructure`, `Contracts` ni HTTP; entre dominios, solo `Contracts/`; `Shared` no conoce dominios; `app/Console` no importa `Infrastructure`. Lo verifica `tests/Unit/ArquitecturaTest.php`.
- Lecturas por un `I<Entidad>QueryRepository` que selecciona solo las columnas de la respuesta y devuelve DTO; las interfaces no exponen tipos de paginación de Laravel.

## Pruebas
- Contra SQL Server en Docker (`docker compose --profile tests up -d sqlserver sqlserver-init`, base `IAM_test`), nunca contra la base remota de desarrollo.

## Documentación
- OpenAPI en `docs/openapi/` y colección de Bruno en `docs/bruno/`. Actualizarlas cuando cambie un endpoint.
- Metodología OpenSpec: los artefactos van en `openspec/`, en español.

## Dependencias nuevas
- Requieren aprobación previa.
- Principio KISS: resolver lo de hoy con lo más simple; no agregar capas ni abstracciones por si acaso.
