# Tasks

## 1. Auditoría

- [x] 1.1 Crear la migración `crear_iam_auditoria` según design D6 (columnas, tres índices, sin FK). Verificar con un test que la tabla y sus índices existen en `IAM_test`
- [x] 1.2 Implementar `App\Domains\Auditoria\Auditoria` (`registrar` exige transacción abierta, `rechazar` escribe sola, actor desde el guard `ubo` o `comando`, `trace_id` de `TraceContext`, rechazo de claves `password`/`secret`/`secreto`). Verificar con tests de los escenarios de `specs/auditoria`: acción aplicada, misma transacción (rollback borra la entrada), intento rechazado, sin secretos e inalterable sin FK

## 2. Login del portal y rutas

- [x] 2.1 Activar el módulo Login (design D9: variables en `.env.example` y `config`) y mover el montaje de los dominios de `routes/api.php` a `routes/web.php` bajo `api/iam/v1` con `ubo.login` (design D1). Verificar con `php artisan route:list` que `api/iam/v1/auth/{login,callback,logout}` existen en el grupo `web` y que `api/iam/v1/salud` sigue en `api`, y que `RutasTest` y `DocsTest` pasan
- [x] 2.2 Redirigir los errores del callback a `UBO_LOGIN_HOME?error_login=<código>` (design D3). Verificar con tests del escenario "State que no corresponde" (`LOGIN_INVALIDO`), de "Persona sin acceso al portal" (`SIN_ACCESO`, con `Http::fake` del canje y un token sin roles, como `tests/Login/CallbackTest.php` de Common) y de IAM no disponible (`IAM_NO_DISPONIBLE`)
- [x] 2.3 Verificar con tests los escenarios "Navegador sin sesión" (302 a `/api/auth/oauth/authorize` con PKCE `S256` y la `redirect_uri` esperada), "Destino externo", "Login exitoso" (sin tokens en la respuesta ni en la cookie), "Logout desde el portal" y "Logout sin token CSRF" (419)
- [x] 2.4 Implementar `GET /api/iam/v1/me` en `app/Domains/Acceso` (design D4). Verificar con tests de "Administrador con sesión" (`UboLogin::actingAs` y una fila en `users`, `uuid` en minúsculas) y "Me sin sesión" (401 `NO_AUTENTICADO`)
- [x] 2.5 Verificar la autorización y el CSRF del portal con tests sobre una ruta de prueba registrada en el test (`can:iam:clientes.leer`, `POST`): "Mesa de ayuda en una ruta de clientes" (403 RFC 9457), "Petición de Angular" (pasa con `X-XSRF-TOKEN`) y sin token (419)
- [x] 2.6 Configurar la revalidación (design D5: `UBO_AUTH_STRATEGY=hybrid`, `UBO_LOGIN_REVALIDATE=300`, `UBO_AUTH_DEGRADED_MODE=false`). Verificar con un test que una sesión cuyo token IAM informa inactivo en la introspección (`Http::fake`) responde 401 en `/me` tras el intervalo (`travel`)
- [x] 2.7 Documentar `auth/login`, `auth/callback`, `auth/logout` y `me` en `docs/openapi/openapi.yaml` (respuestas y códigos `error_login`) y agregar a Bruno la carpeta Acceso con `me`. Verificar que `DocsTest` pasa

## 3. Comandos de operación

- [x] 3.1 Copiar de API-Auth la regla `Rut` con su normalizador, y crear `Usuarios\ClaveTemporal` (12 caracteres sin ambiguos en 3 grupos, vencimiento de `config('iam.clave_temporal_horas')`, 72 por defecto) y `Clientes\NuevoCliente` (campos de `Application::createClient`, secreto de 40 caracteres con hash bcrypt). Verificar con tests unitarios: normalización y dígito verificador, formato y alfabeto de la clave, `grant_types`, `provider` y `redirect_uris` por tipo, y `Hash::check` del secreto
- [x] 3.2 Implementar `iam:install` (design D7). Verificar con tests de todos los escenarios de "Confirmación con ambiente y base visibles", "Instalación única por ambiente", "Catálogo base de api-auth", "Aplicación iam-admin", "Clientes de API-IAM", "Primer administrador" e "Instalación atómica y secretos mostrados una vez" de `specs/comandos-de-operacion`, incluido que la auditoría no contiene secretos
- [x] 3.3 Implementar `iam:grant-admin` (design D8). Verificar con tests de "Recuperar el acceso", "Persona dada de baja" (con y sin `--reactivar`) y "Email inexistente"
- [x] 3.4 Verificar con un test que ninguna ruta de API-IAM instala IAM ni otorga roles (escenario "Sin ruta de instalación")
- [x] 3.5 Documentar en el README la instalación de un ambiente (`migrate` + `iam:install`, copia de credenciales, `APP_URL` y redirecciones, desarrollo con `ng serve` en `:4200`), `iam:grant-admin` y el procedimiento de emergencia para un secreto perdido. Verificar que los comandos del README coinciden con las firmas (`php artisan iam:install --help`, `php artisan iam:grant-admin --help`)

## 4. Verificación integral

- [x] 4.1 Ejecutar `vendor/bin/pint --test`, la suite completa con `php artisan test` y `openspec validate acceso-portal --strict`. Verificar que los tres terminan sin errores
- [x] 4.2 Con la aprobación del usuario: ejecutar `php artisan migrate` e `iam:install` en la base `IAM` de desarrollo, configurar el `.env` con las credenciales mostradas e iniciar sesión de punta a punta (`/api/iam/v1/auth/login` → login de IAM → `/api/iam/v1/me` con el administrador). Verificar que `/me` devuelve los siete permisos `iam:*` y que el logout vuelve al portal

## Workflow follow-up

- 4.2 verificada el 2026-11-09 con el portal Angular (`IAM/frontend`): login por IAM, `/me` con los siete permisos y logout local y central. En este PC hizo falta `UBO_AUTH_TIMEOUT=15` (apidev tarda 3–5 s en `/oauth/token`) y `UBO_AUTH_LEEWAY=30` (reloj del PC ~15–20 s atrasado respecto de apidev; lo correcto es sincronizar la hora y quitarlo).

- Archivar el cambio cuando esté desplegado y verificado.
- `traspaso-migraciones` en API-Auth sigue pendiente e independiente de este cambio.
