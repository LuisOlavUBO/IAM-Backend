# Tasks

## 1. Proyecto y dependencias

- [x] 1.1 Crear el proyecto Laravel 13 (PHP 8.4) en una carpeta temporal y mover su contenido a la raíz del repositorio, conservando `api-iam-bases.md`, `openspec/` y `.claude/` (el directorio no está vacío). Verificar con `php artisan --version` que la versión es 13.x
- [x] 1.2 Agregar `ubo/common` `^3.3` desde el repositorio vcs `https://github.com/LuisOlavUBO/UBO-Common` y `laravel/boost` en `require-dev`. Verificar con `composer show ubo/common` (3.3.x) y `composer show laravel/boost`
- [x] 1.3 Ejecutar la instalación de Boost con `boost.json` (`"guidelines": true`, como API-Auth) y agregar a `CLAUDE.md` la sección de convenciones de IAM descrita en design D10. Verificar que `CLAUDE.md` contiene las guías generadas y la sección propia
- [x] 1.4 Completar `openspec/config.yaml` con el `context` del proyecto (artefactos en español con SHALL/MUST y `#### Scenario:`, KISS, base `IAM` compartida con un dueño por tabla, rutas en español bajo `/api/iam/v1`, dependencias nuevas con aprobación). Verificar que `openspec context --json` lo carga sin errores

## 2. Docker de desarrollo y pruebas

- [x] 2.1 Copiar de API-Auth `Dockerfile.dev`, `docker-compose.yml`, `docker-entrypoint.sh` y `nginx/default.conf`, adaptados según design D8 (nombres `api-iam`, puerto `8003`, sin llaves de Passport). Verificar que `docker compose up -d --build` levanta app y nginx, y que `http://localhost:8003` llega a Laravel
- [x] 2.2 Agregar al perfil `tests` el SQL Server en el puerto `14335` y el `sqlserver-init` que crea `IAM_test` con intercalación `SQL_Latin1_General_CP1_CI_AS`. Verificar con `docker compose --profile tests up -d sqlserver sqlserver-init` que la base existe con esa intercalación
- [x] 2.3 Configurar `phpunit.xml` para `APP_ENV=testing`, `DB_CONNECTION=sqlsrv`, `DB_DATABASE=IAM_test`, `BCRYPT_ROUNDS=4`, caché y sesión `array`. Verificar que `php artisan test` corre contra `IAM_test`

## 3. Esquema de datos

- [x] 3.1 Consultar en la base `IAM` de desarrollo (`sys.indexes`, `sys.foreign_keys`, `sys.key_constraints`, `INFORMATION_SCHEMA.COLUMNS`) los tipos, nombres de índices y restricciones de las ocho tablas de IAM, y confirmar que `account-lifecycle` está aplicado. Verificar dejando el resultado en el encabezado de la migración base junto con el commit de API-Auth de referencia
- [x] 3.2 Configurar `migrations.table = 'iam_migrations'` y `DB::prohibitDestructiveCommands` fuera de `testing` (design D1 y D4). Verificar con tests de `specs/esquema-de-datos` ("Migrar en la base compartida", "Historial de API-Auth intacto", "migrate:fresh en desarrollo")
- [x] 3.3 Escribir `esquema_base_iam` según design D2 y D3. Verificar con tests (design D9) de los escenarios "Base de pruebas vacía", "Unicidad de RUT y Microsoft solo cuando hay valor", "Rol único por aplicación", "Base con el esquema de API-Auth", "API-Auth sin account-lifecycle" y "Rollback del esquema base"
- [x] 3.4 Ejecutar a mano `php artisan migrate` contra la base `IAM` de desarrollo y comparar su esquema con el de `IAM_test` (columnas, tipos, nulabilidad, índices y FK). Verificar que solo se creó `iam_migrations` con la migración base registrada, y que la comparación no muestra diferencias, salvo los nombres de claves primarias generados por SQL Server

## 4. Operación del servicio

- [x] 4.1 Configurar el ruteo según design D5: `apiPrefix: 'api/iam'`, sin la ruta `/up`, carpetas `app/Domains/{Acceso,Catalogo,Usuarios,Clientes,Auditoria}` con su `routes/v1.php` vacío montado bajo `v1`. Verificar con `php artisan route:list` que toda ruta propia está bajo `/api/iam`
- [x] 4.2 Registrar `GET /api/iam/v1/salud` (Health de Common), `ProblemRenderer` para `api/*` y Tracing (design D6). Verificar con tests de los escenarios de `specs/operacion-del-servicio`: base disponible (200), base caída (503 sin detalle), ruta inexistente (`RUTA_NO_ENCONTRADA`), método no permitido (`METODO_NO_PERMITIDO`), error inesperado (`ERROR_INTERNO` sin mensaje interno) y `X-Trace-Id` con y sin `traceparent`
- [x] 4.3 Escribir `.env.example` según design D7 (base `IAM`, sesión y caché `file`, `BCRYPT_ROUNDS=12`, `TRUSTED_PROXIES`, variables de Login y Auth de Common comentadas). Verificar con un test que el hasher es bcrypt y que la configuración de sesión y caché por defecto es `file`
- [x] 4.4 Documentar `GET /api/iam/v1/salud` y los esquemas comunes (`data`/`meta`, problem RFC 9457, paginación) en `docs/openapi/openapi.yaml`, servirlo en `/api/iam/docs/openapi.yaml` con Swagger UI en `/api/iam/docs/swagger`, y agregar la colección de Bruno con la petición de salud en `docs/bruno/`. Verificar con un `DocsTest` (como el de API-Auth) que toda ruta `/api/iam/*` está documentada y al revés, y ejecutando la petición de Bruno contra `localhost:8003`

## 5. Producción y README

- [x] 5.1 Copiar `Dockerfile.prod`, `docker-compose.prod.yml` y `docker-entrypoint.prod.sh` de API-Auth (imagen `ghcr.io/luisolavubo/api-iam`, `RUN_MIGRATIONS=false`, nginx en la red externa `proxy`, sin llaves de Passport). Verificar con `docker compose -f docker-compose.prod.yml up -d --build` que `GET /api/iam/v1/salud` responde 200 a través de nginx
- [x] 5.2 Escribir el README: levantar desarrollo, correr los tests, desplegar, la regla de no correr comandos desde el entrypoint y el orden de migración de un ambiente nuevo (API-IAM, después API-Auth, después `iam:install` cuando exista). Verificar ejecutando los comandos tal como están escritos

## 6. Verificación integral

- [x] 6.1 Ejecutar `vendor/bin/pint --test`, la suite completa con `php artisan test` y `openspec validate esqueleto --strict`. Verificar que los tres terminan sin errores

## Workflow follow-up

- Proponer en el repositorio de API-Auth el cambio hermano `traspaso-migraciones` (copias de las migraciones de IAM solo para tests, división de `create_users_table`, `prohibitDestructiveCommands`, `RoleScopeSeeder` solo para tests).
- Desplegar este cambio en cada ambiente antes que `traspaso-migraciones`, y repetir en cada uno las tareas 3.1 y 3.4.
- Archivar el cambio cuando esté desplegado y verificado.
