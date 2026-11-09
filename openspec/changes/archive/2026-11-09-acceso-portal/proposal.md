# Proposal

## Why

API-IAM ya arranca y es dueño del esquema, pero nadie puede entrar al portal: no hay login, no existe la aplicación `iam-admin` con sus permisos, y no hay forma de crear el primer administrador sin SQL a mano. Este cambio deja el portal listo para que el Angular inicie sesión por el SSO de IAM y sepa qué puede mostrar a cada persona, y prepara la auditoría que exigen los cambios siguientes (catálogo, usuarios, clientes).

## What Changes

- **Login del portal (BFF)** con el módulo Login de `ubo/common`: `GET /api/iam/v1/auth/login`, `GET /api/iam/v1/auth/callback` y `POST /api/iam/v1/auth/logout`, con cliente confidencial, PKCE, tokens en la bóveda del servidor y renovación automática. Angular nunca ve un token.
- **`GET /api/iam/v1/me`**: la persona con sesión (uuid, nombre, correo, roles, permisos y el token CSRF para el logout).
- **Rutas del portal en el grupo `web`** (sesión y CSRF), bajo el mismo prefijo `/api/iam/v1`. Sin sesión responden 401 y sin el permiso, 403, siempre en RFC 9457. `routes/api.php` queda para la salud y los futuros endpoints M2M.
- **Revalidación contra IAM** con el cliente M2M de API-IAM (estrategia `hybrid`): una persona dada de baja o con sesiones revocadas pierde el acceso al portal en minutos.
- **Auditoría mínima**: tabla `iam_auditoria` y una sola forma de escribir en ella (quién, qué acción, sobre qué entidad, valores anteriores y nuevos sin secretos, resultado y `trace_id`). La consulta queda para `auditoria-consulta`.
- **`iam:install`**: se ejecuta una vez por ambiente. Asegura el catálogo base de `api-auth` y crea:
  - la aplicación `iam-admin` con los permisos `iam:{usuarios,catalogo,clientes}.{leer,administrar}` e `iam:auditoria.leer`;
  - los roles `administrador` y `mesa-de-ayuda`;
  - el cliente `authorization-code` del portal y el cliente M2M de API-IAM;
  - el primer administrador.
  Se niega a correr si `iam-admin` ya existe y muestra los secretos una sola vez.
- **`iam:grant-admin {email}`**: acceso de emergencia que devuelve el rol `administrador` a una persona, con confirmación y registro en la auditoría.

Fuera de alcance: endpoints de usuarios, catálogo, clientes y consulta de auditoría (cambios siguientes), la revocación de sesiones al dar de baja o quitar roles (`usuarios`), `iam:rotate-secret` (`clientes-oauth`) y la aplicación de las protecciones de roles de `iam-admin` (regla de la mesa de ayuda, último administrador, nada sobre uno mismo), que actúan al asignar roles (`usuarios`).

## Capabilities

### New Capabilities
- `acceso-portal`: inicio y cierre de sesión del portal por el SSO de IAM, la persona con sesión (`/me`) y la autorización de las rutas del portal por permisos `iam:*`.
- `comandos-de-operacion`: comandos que se ejecutan a mano en el servidor para instalar IAM en un ambiente y recuperar el acceso de administración.
- `auditoria`: registro inalterable de las acciones de administración y de sus intentos rechazados.

### Modified Capabilities
<!-- Ninguna: la salud, el prefijo y los errores de operacion-del-servicio no cambian. -->

## Impact

- **Código**: rutas del portal en `routes/web.php`; dominio `Acceso` (`/me`) y `Auditoria` (registro); comandos en `app/Console`; configuración de Common (Login y Auth).
- **Base `IAM`**: nueva tabla `iam_auditoria`. `iam:install` escribe en `applications`, `oauth_scopes`, `roles`, `oauth_scope_role`, `role_user`, `oauth_clients`, `oauth_client_role` y `users`.
- **Dependencias**: ninguna nueva (`ubo/common` ya instalado).
- **API-Auth**: requiere `account-lifecycle` (ya desplegado en desarrollo: contraseña temporal, `sessions:revoke` y `session-revoker`) y `uuid` en el token (Common 3.3). No requiere cambios en API-Auth.
- **Frontend Angular**: debe servirse en el mismo origen que `/api/iam/*` y usar URLs relativas. El login y el logout son navegaciones completas, no llamadas de `HttpClient`.
- **Operación**: en cada ambiente se ejecuta `iam:install` una vez, a mano, y se copian al `.env` las credenciales que muestra.
