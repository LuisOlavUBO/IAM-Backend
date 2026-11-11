<laravel-boost-guidelines>
=== .ai/iam/core rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- No se usa Laravel Cloud. Se despliega en un servidor on-premise con Docker.
- Flujo:
  1. En el PC, construir la imagen de producción (`Dockerfile.prod`) con su versión: `IMAGE=ghcr.io/luisolavubo/api-iam:<versión> docker compose -f docker-compose.prod.yml build app`.
  2. Publicarla en GitHub Packages: `docker push ghcr.io/luisolavubo/api-iam:<versión>`.
  3. En el servidor, con `IMAGE` en su `.env`: `docker compose -f docker-compose.prod.yml pull` y `docker compose -f docker-compose.prod.yml up -d --no-build`.
- Las migraciones y los comandos `iam:*` se ejecutan a mano, nunca desde el entrypoint ni desde CI.
- El detalle está en la sección "Despliegue" del README.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
