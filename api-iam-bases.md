# Encargo: API-IAM, el backend de administración de IAM de la UBO

Este documento es el punto de partida de un repositorio nuevo y vacío. Resume lo que ya está decidido y lo que falta decidir. Las decisiones marcadas como **decidido** no se vuelven a discutir. Las **abiertas** se resuelven en este repositorio antes de proponer.

## 1. Contexto

La UBO tiene un servicio de autenticación central, **API-Auth** (repositorio `LuisOlavUBO/API-Auth`). Emite los tokens de todos los sistemas ("satélites"): login central con Authorization Code + PKCE, login con Microsoft Entra ID, tokens de servicio (client credentials), introspección y clave pública.

Hoy la administración de IAM (aplicaciones, roles, permisos, clientes y roles de usuarios) se hace con comandos Artisan dentro de API-Auth. **Decidido:** la administración se muda a un servicio aparte, **API-IAM**, y API-Auth queda solo para autenticar y autorizar.

**API-IAM** es un monolito modular en Laravel, dueño de los datos de identidad y de acceso. Ofrece el portal de administración de IAM.

### Stack y convenciones (igual que API-Auth)
- Laravel 13, PHP 8.4, SQL Server, PHPUnit, Pint.
- Paquete `ubo/common` (repositorio `LuisOlavUBO/UBO-Common`): errores RFC 9457 (Problem Details), Health, Tracing y SqlServer.
- Rutas bajo un prefijo propio y versionadas: **`/api/iam/v1/...`**. El proxy inverso `ubo-nginx-proxy` de `apidev.ubo.cl` elige el contenedor por el segmento que sigue a `/api` y no reescribe la ruta.
- Despliegue on-premise con Docker:
  - imagen de producción `Dockerfile.prod` publicada en `ghcr.io/luisolavubo/api-iam:<versión>`;
  - en el servidor, `docker compose -f docker-compose.prod.yml pull` y `up -d --no-build`;
  - nginx + PHP-FPM, con nginx en la red externa `proxy`.
- Metodología **OpenSpec** (`openspec/specs` como fuente de verdad, cambios en `openspec/changes`): explore → propose → aprobación → apply → archive. Los artefactos van en español, con `SHALL`/`MUST` y `#### Scenario:`.
- Principio **KISS**: resolver lo de hoy con lo más simple; no agregar capas ni abstracciones "por si acaso"; las dependencias nuevas requieren aprobación.
- Documentación: OpenAPI en `docs/openapi/` y colección de Bruno en `docs/bruno/`.
- Tests contra SQL Server en Docker, no contra la base remota de desarrollo.

## 2. Responsabilidades

| API-IAM hace | API-IAM **no** hace |
|---|---|
| Alta, edición, baja y búsqueda de usuarios | Mostrar el login ni pedir contraseñas para entrar |
| Catálogo: aplicaciones, roles, permisos (scopes) y qué permisos da cada rol | Emitir, validar ni renovar tokens |
| Asignar y quitar roles a usuarios y a clientes M2M | Escribir en las tablas de tokens o sesiones |
| Alta de clientes OAuth, rotación de secretos, revocación | Cambiar la contraseña de la persona después del primer acceso (eso es de API-Auth) |
| Pedir a API-Auth que cierre las sesiones de un usuario | |
| Auditoría de los cambios de administración | |

## 3. Datos: una base, un dueño por tabla

**Decidido:** las dos APIs usan **la misma base** SQL Server (`apiauth`), con **un usuario de base de datos distinto cada una**. Cada tabla tiene un solo servicio que escribe; el otro solo lee. Los permisos de SQL Server (`GRANT`) hacen cumplir esta regla.

| Tabla | Escribe | API-Auth |
|---|---|---|
| `users` | **API-IAM** | Lee. Escribe solo `entra_oid` (al vincular con Microsoft) y `password` junto con `temporary_password_expires_at` (cuando la persona cambia su contraseña temporal) |
| `applications`, `oauth_scopes`, `roles`, `oauth_scope_role` | **API-IAM** | Lee al emitir tokens |
| `role_user`, `oauth_client_role` | **API-IAM** | Lee |
| `oauth_clients` | **API-IAM** | Lee para validar clientes |
| `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_auth_codes`, `oauth_device_codes`, `sessions`, `cache`, `jobs`, `password_reset_tokens` | **API-Auth** | — (API-IAM no las toca) |
| Auditoría (nueva) | **API-IAM** | No la usa |

### Esquema actual de las tablas que administrará API-IAM
Hoy lo crean las migraciones de API-Auth. **Decidido:** las migraciones pasan a API-IAM, que las corre en producción, y API-Auth guarda una copia solo para sus tests. El traspaso se hace en un cambio coordinado: hasta entonces, el esquema lo sigue migrando API-Auth.

- **`users`**
  - `id` bigint identity: interno, nunca se expone a los satélites.
  - `uuid` uniqueidentifier, único y obligatorio: el identificador público. Se genera como UUID v7 al crear el usuario, **en minúsculas**, y no cambia nunca.
  - `rut` varchar(10) nullable, único solo cuando no es nulo (índice filtrado). Formato `12345678-K`: sin puntos, con guion y el dígito verificador en mayúscula.
  - `entra_oid` uniqueidentifier nullable, único cuando no es nulo. Lo escribe API-Auth.
  - `name`, `email` (único).
  - `email_verified_at` nullable.
  - `password` nullable, hash **bcrypt** (`BCRYPT_ROUNDS=12`). `null` significa que no puede entrar con contraseña.
  - `remember_token`, `created_at`, `updated_at`.
  - **Vienen en el cambio `account-lifecycle` de API-Auth (aún sin implementar):** `disabled_at` datetime nullable (fecha de baja) y `temporary_password_expires_at` datetime nullable (si no es nulo, la contraseña es temporal y vence en esa fecha).
- **`applications`:** `id`, `name` varchar(100) único (p. ej. `api-auth`, `api-maestros`, `portal`), `description` nullable y timestamps. Una aplicación puede ser un satélite, una API o un monolito, y usa solo lo que necesita: permisos si valida accesos, roles si tiene usuarios o los ofrece a quien la consume, clientes si pide tokens.
- **`oauth_scopes`** (permisos): `id`, `application_id` (obligatorio, FK) con la aplicación que **exige** el permiso, `name` varchar(100) **único en toda la organización** (convención `dominio:permiso`, p. ej. `geografia:leer`), `description` y timestamps.
- **`roles`:** `id`, `application_id` (obligatorio, FK) con la aplicación donde se usa el rol, `name` varchar(100), único por `(application_id, name)`, `description` nullable y timestamps. En la interfaz se escribe `<aplicación>/<rol>`.
- **`oauth_scope_role`:** `(role_id, oauth_scope_id)`. Un rol puede dar permisos de **cualquier** aplicación.
- **`role_user`:** `(user_id, role_id)`. Un usuario puede tener varios roles, en varias aplicaciones.
- **`oauth_client_role`:** `(client_id, role_id)`. Roles de los clientes M2M, normalmente los que ofrece cada API que consumen.
- **`oauth_clients`** (tabla de Passport):
  - `id` uniqueidentifier (UUID), `name`, `application_id` (obligatorio, FK).
  - `secret` nullable: **hash bcrypt** del secreto (Passport lo hashea; el secreto en claro solo se muestra al crearlo).
  - `provider`: `users` para los tipos `password` y `authorization-code`, `null` para `client-credentials`.
  - `redirect_uris` y `grant_types`: texto con JSON (ver tabla de abajo).
  - `revoked` bit, `owner_type`/`owner_id` nullable (sin uso) y timestamps.

  | Tipo | `grant_types` | `redirect_uris` |
  |---|---|---|
  | `client-credentials` | `["client_credentials"]` | `[]` |
  | `password` | `["password","refresh_token"]` | `[]` |
  | `authorization-code` | `["authorization_code","refresh_token"]` | lista de URL absolutas, al menos una |

**Ojo con SQL Server:** varias FK tienen `ON DELETE CASCADE`, pero SQL Server no permite cascadas en ciertos ciclos. Las bajas de aplicaciones o roles deben pensarse explícitamente: prohibir borrar lo que está en uso, o borrar las asignaciones antes.

### Cómo usa API-Auth estos datos (lo que API-IAM no debe romper)
- **Token de usuario** (login central, con el cliente de una aplicación X):
  - lleva los permisos que dan los roles del usuario **en la aplicación X**;
  - la claim `roles` lleva sus roles en X;
  - `aud` es `[client_id, <aplicaciones dueñas de esos permisos>]`, con el `client_id` siempre primero;
  - la claim `uuid` lleva el `users.uuid`.
- **Persona sin roles en la aplicación del cliente:** API-Auth no le entrega código y le muestra "No tienes acceso a este sistema, contacta a soporte".
- **Token M2M:** lleva los permisos que dan los roles del cliente. Con el parámetro `api=<aplicación>`, lleva solo los de esa aplicación.
- **Propagación:** los cambios de roles o permisos se aplican en el siguiente login o refresh. El access token dura 60 minutos y el refresh 120 minutos de inactividad. Para cortar antes hay que revocar (sección 4).
- **Regla de cambios de esquema (decidida):** en las tablas que lee API-Auth, los cambios **solo agregan** columnas o tablas. Renombrar o borrar exige primero una versión de API-Auth que ya no las use.

## 4. Contratos con API-Auth

1. **Revocación de sesiones** (viene en el cambio `account-lifecycle` de API-Auth):
   - `POST /api/auth/v1/users/{uuid}/revoke`, con un token **client credentials** de API-IAM que lleve el scope `sessions:revoke`.
   - Revoca todos los access y refresh tokens del usuario y cierra su sesión de IAM.
   - Responde 204 (también si no tenía sesiones) o 404 si el `uuid` no existe.
   - API-IAM debe llamarlo **al dar de baja a un usuario y al quitarle roles**. Si la llamada falla, reintentar o avisar en el portal que el cambio se guardó pero las sesiones no se cerraron (**abierta**: elegir una).
2. **Baja de usuarios:** API-IAM escribe `users.disabled_at`. API-Auth rechaza a ese usuario en el login con contraseña, en el login con Microsoft, en el refresh y en el canje de código, y la introspección reporta sus tokens como inactivos. Dar de alta de nuevo es poner `disabled_at` en `null`.
3. **Contraseña inicial:** API-IAM crea al usuario con una contraseña temporal aleatoria (bcrypt con `Hash::make`) y `temporary_password_expires_at` (por ejemplo, ahora + 72 horas). La muestra **una sola vez** al administrador. API-Auth obliga a cambiarla en el primer acceso y rechaza la vencida. "Reiniciar contraseña" en el portal es lo mismo: una nueva temporal con vencimiento.
4. **Secretos de clientes:** API-IAM genera el secreto (40 caracteres aleatorios), guarda su hash bcrypt en `oauth_clients.secret` y lo muestra una sola vez. Rotar es generar uno nuevo; revocar es `revoked = 1`. No hace falta instalar Passport en API-IAM: basta con `Hash::make` y los formatos de la tabla de arriba.
5. **Microsoft:** la persona entra a los satélites con Microsoft o con contraseña. **Decidido:** el alta automática al entrar con Microsoft se elimina; API-Auth solo vinculará por email a un usuario creado antes por API-IAM. Ese cambio en API-Auth se hace cuando API-IAM ya pueda crear usuarios.
6. **Datos públicos de API-Auth que API-IAM puede usar:** `GET /api/auth/v1/public-key` (PEM para validar JWT RS256 localmente), `POST /api/auth/v1/introspect` (scope `introspect`) y `POST /api/auth/oauth/token`.

## 5. Autenticación del propio portal

El portal de API-IAM es **un satélite más** de API-Auth: las personas entran por el login central y API-IAM nunca ve su contraseña.

- **Aplicación `iam-admin`** en el catálogo, con:
  - **permisos** propios, que exige API-IAM. Propuesta: `iam:usuarios.leer`, `iam:usuarios.administrar`, `iam:catalogo.administrar`, `iam:clientes.administrar`, `iam:auditoria.leer`; los nombres finales se deciden aquí;
  - un rol de **administrador** que da esos permisos;
  - un **cliente `authorization-code`** confidencial (con secreto) cuya redirección apunta al callback del portal;
  - un **cliente `client-credentials`** de API-IAM con el rol que da `sessions:revoke`, para llamar a la revocación.
- **Flujo de login:**
  1. Generar `code_verifier`, `state` y `code_challenge` (S256).
  2. Redirigir a `GET /api/auth/oauth/authorize?response_type=code&client_id=…&redirect_uri=…&state=…&code_challenge=…&code_challenge_method=S256`.
  3. En el callback, verificar `state` y canjear el código en `POST /api/auth/oauth/token` con `client_secret` y `code_verifier`.
  4. Guardar los tokens en la sesión del servidor y renovar con `refresh_token`.
  5. Logout: `GET /api/auth/logout?client_id=…&return=<url del mismo origen que la redirección>`.
- **Autorización en API-IAM:** cada acción exige el permiso correspondiente en el token (claim `scopes`) y que `aud` incluya `iam-admin`. Validar la firma con la clave pública o usar la introspección: la introspección detecta bajas y revocaciones de inmediato; la firma sola no, hasta que vence el token.
- **Restricción de API-Auth hoy:** no hay clientes públicos ni CORS para SPA. Por eso el portal debe usar un **cliente confidencial desde el servidor**: Laravel con páginas del lado del servidor (Blade, Livewire o Inertia), o un backend que actúe como BFF. Una SPA pura con el token en el navegador requeriría un cambio en API-Auth. (**Abierta**: la tecnología del frontend.)

## 6. Primera entrega (alcance)

Reemplaza los 9 comandos `oauth:*` de API-Auth y agrega lo que hoy no se puede hacer.

**Usuarios**
- Listar y buscar por nombre, email o RUT, con paginación.
- Ver el detalle: datos, estado, roles por aplicación y si está vinculado a Microsoft.
- Crear: `name`, `email` (único), `rut` opcional y una contraseña temporal generada (sección 4.3). El RUT se normaliza antes de validar (quitar puntos y espacios, mayúscula, guion antes del último carácter) y se valida el dígito verificador con módulo 11 (ver la regla `App\Rules\Rut` de API-Auth). El RUT debe ser único.
- Editar nombre, email y RUT.
- Dar de baja y reactivar (sección 4.2). Al dar de baja se llama a la revocación.
- Reiniciar la contraseña con una nueva temporal.
- No se borran usuarios: se dan de baja. Los tokens y la auditoría los referencian.

**Roles de usuarios**
- Agregar y quitar roles puntuales por aplicación. Hoy solo existe "reemplazar todos los de una aplicación".
- Al quitar un rol, llamar a la revocación.

**Catálogo**
- Aplicaciones: crear y editar.
- Roles: crear y editar dentro de una aplicación.
- Permisos: crear y editar dentro de una aplicación (nombre único en toda la organización).
- Dar y quitar permisos a un rol. Hoy solo se pueden agregar.
- Vistas de consulta: qué permisos da cada rol de una aplicación, agrupados por la aplicación dueña del permiso.
- Borrar: solo lo que no está en uso (**abierta**: si se permite en la primera entrega).

**Clientes OAuth**
- Crear de los tres tipos; `authorization-code` exige al menos una redirección absoluta.
- Mostrar el secreto una sola vez, rotarlo y revocar el cliente.
- Editar las redirecciones.
- Dar y quitar roles a clientes M2M.
- Cambiar la aplicación de un cliente.
- Ver lo que puede pedir: su aplicación, sus roles y sus permisos agrupados por aplicación.

**Auditoría**
- Guardar quién (uuid del administrador, o el usuario del sistema si fue un comando), qué acción, sobre qué entidad, los valores anteriores y nuevos (sin secretos ni contraseñas) y cuándo.
- Ver la auditoría en el portal (permiso `iam:auditoria.leer`).

## 7. Comandos de API-IAM

Solo dos. Todo lo demás se hace en el portal.

1. **`iam:install`**: se corre una vez por ambiente. Crea la aplicación `iam-admin`, sus permisos, el rol de administrador, el cliente del portal (con su redirección), el cliente M2M de API-IAM y el primer administrador con contraseña temporal.
2. **`iam:grant-admin {email}`**: acceso de emergencia, para devolverle el rol de administrador a alguien si nadie puede entrar al portal.

**Reglas de seguridad (decididas):**
- Ningún comando tiene ruta web ni asistente de instalación por HTTP.
- `iam:install` **se niega a correr si `iam-admin` ya existe**. Para reparar algo se usa un comando aparte y explícito.
- Los secretos y contraseñas se generan al azar al correr el comando y **se muestran una sola vez**. No hay credenciales por defecto ni valores tomados del `.env`.
- Se ejecutan **a mano**, nunca desde el `docker-entrypoint` ni desde CI, para que los secretos no queden en logs.
- Muestran el ambiente y la base de datos y piden confirmación.
- La contraseña temporal del primer administrador vence (72 horas) y se debe cambiar en el primer acceso.
- `iam:grant-admin` solo da el rol de administrador de IAM, pide confirmación y **queda registrado en la auditoría**.
- No se cargan datos a mano por SQL. Un secreto perdido se recupera con `iam:rotate-secret` (procedimiento de emergencia del README).

## 8. Orden de trabajo y dependencias

1. **API-Auth, cambio `account-lifecycle`** (propuesto, por implementar): `disabled_at`, contraseña temporal con cambio obligatorio y vencimiento, y `POST /v1/users/{uuid}/revoke` con `sessions:revoke`. API-IAM depende de esto para la baja, la contraseña inicial y la revocación.
2. **API-IAM, entregas propias**: esqueleto del proyecto (Laravel, `ubo/common`, Docker, OpenSpec, CLAUDE.md con estas convenciones), `iam:install` y el login del portal, y después las funcionalidades de la sección 6.
3. **API-Auth, segundo cambio** (cuando API-IAM ya cubra la sección 6):
   - quitar el alta automática con Microsoft;
   - eliminar los comandos `oauth:*` y la spec `access-administration`;
   - mover las migraciones a API-IAM, con copia para tests en API-Auth;
   - aplicar los usuarios de base de datos con permisos por tabla.

Mientras tanto, los comandos `oauth:*` de API-Auth siguen siendo la forma de administrar.

## 9. Decisiones abiertas para este repositorio
1. Tecnología del frontend del portal (Blade, Livewire, Inertia), con un cliente confidencial desde el servidor.
2. Nombres finales de los permisos `iam:*` y si hay más de un rol (por ejemplo, solo lectura o mesa de ayuda).
3. Qué hacer si la revocación falla: reintentar o avisar.
4. Si la primera entrega permite borrar aplicaciones, roles, permisos o clientes que no están en uso.
5. Si API-IAM expone además una API para otros sistemas (por ejemplo, el endpoint de roles por aplicación que necesita un satélite para armar su menú) o solo el portal.
6. Cómo se resuelve en los tests de API-IAM la dependencia de API-Auth (fakes HTTP para la revocación y tokens firmados con una llave de prueba).
