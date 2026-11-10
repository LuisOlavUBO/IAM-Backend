## Context

Usuarios ya tiene `ListarUsuarios` y `VerUsuario` (lectura) y `Contracts/` para otros dominios. `role_user` (`user_id`, `role_id`) es de API-IAM; cada rol pertenece a una aplicación (`roles.application_id`) y da permisos por `oauth_scope_role`. La auditoría se escribe con `IAuditor` (`registrar` dentro de la transacción, `rechazar` para los intentos rechazados). Quien actúa es el `UboUser` de la sesión del portal: su `id` es el `uuid` de la persona y `scopes` sus permisos `iam:*` vigentes. API-Auth ofrece `POST /api/auth/v1/users/{uuid}/revoke` (204; 404 si no existe), protegido con `sessions:revoke`; `iam-admin-sistema` tiene el rol `session-revoker` y su id y secreto están en `UBO_AUTH_CLIENT_ID`/`SECRET`. `ubo/common` ya obtiene y cachea su token (`ServiceTokenProvider`). Este es el primer caso de uso de escritura del portal.

## Goals / Non-Goals

**Goals:**
- Asignar y quitar roles desde el portal con las protecciones acordadas y auditoría completa.
- Que quitar un rol rija de inmediato (revocación de sesiones), sin que una falla de API-Auth deshaga el cambio.
- Dejar el molde de los casos de uso de escritura (`CommandHandler`, repositorio de escritura, reglas en `Domain/`).

**Non-Goals:**
- Alta, baja, reactivación, reiniciar clave y edición de la persona (cambios siguientes).
- Revocar al asignar un rol: dar un permiso no exige cerrar sesiones; rige al renovar el token o en el siguiente login.
- Roles de clientes OAuth.

## Decisions

**D1. Rutas.** `POST usuarios/{uuid}/roles` (cuerpo `{aplicacion, rol}`), `DELETE usuarios/{uuid}/roles/{aplicacion}/{rol}` y `POST usuarios/{uuid}/sesiones/revocar`, en el grupo `can:iam:usuarios.administrar`. Se usan nombres y no ids, como en el resto de la API. Los nombres de aplicación y rol no llevan `/`, así que caben en la ruta.

**D2. Estructura** (creada con `iam:make-feature Usuarios <Caso> --command`):
```
app/Domains/Usuarios/
+-- Features/AsignarRol/          Endpoint, Request, CommandHandler, Resource (sin Resource si no hace falta)
+-- Features/QuitarRol/           Endpoint, CommandHandler
+-- Features/RevocarSesiones/     Endpoint, CommandHandler
+-- Domain/
|   +-- ReglasDeRoles.php                          reglas puras (D3)
|   +-- EscalamientoDePrivilegiosException.php     403
|   +-- AccionSobreSiMismoException.php            403
|   +-- UltimoAdministradorException.php           409
|   +-- RolNoAsignadoException.php                 404
|   +-- RevocacionFallidaException.php             502
|   +-- Dto/RolDeAplicacionDto.php                 id interno, aplicacion, nombre
|   +-- Interfaces/IRolesDeUsuarioRepository.php   escritura (D4)
|   +-- Interfaces/IRevocadorDeSesiones.php        API-Auth (D5)
+-- Infrastructure/RolesDeUsuarioRepository.php
+-- Infrastructure/ApiAuthRevocadorDeSesiones.php
```

**D3. Reglas en `Domain/ReglasDeRoles`**, puras y probadas sin base. Los datos que solo importan para roles de `iam-admin` (sus permisos, si la persona está activa y cuántos administradores quedan) llegan como funciones que la regla llama solo cuando el rol es de `iam-admin`, para no consultar la base en los demás casos:
- `verificarQueNoEsUnoMismo(actorUuid, personaUuid)` → `AccionSobreSiMismoException`.
- `verificarEscalamiento(rol, permisosDelActor, permisosDeIamAdmin)`: si el rol es de `iam-admin`, el actor debe tener todos los permisos de `iam-admin` (los lee el repositorio de `oauth_scopes`; hoy son los siete `iam:*`). Se aplica al asignar y al quitar.
- `verificarUltimoAdministrador(rol, administradoresActivos)`: si el rol es `iam-admin/administrador` y la persona es la única activa que lo tiene → `UltimoAdministradorException`.
El orden de chequeo es: persona existe (404) → uno mismo (403) → rol existe/asignado (422/404) → escalamiento (403) → último administrador (409). Cada excepción de regla se audita con `IAuditor::rechazar` antes de propagarse; los 404 y 422 no se auditan (no hubo intento sobre algo existente).

**D4. `IRolesDeUsuarioRepository`** (escritura, en `Domain/Interfaces`): `idDePersona(uuid): ?int`, `rol(aplicacion, nombre): ?RolDeAplicacionDto`, `tiene(userId, rolId): bool`, `asignar(userId, rolId)`, `quitar(userId, rolId)`, `permisosDeAplicacion(aplicacion): list<string>` y `administradoresActivos(rolId): int`. Los ids internos no salen de Usuarios ni de la respuesta.

**D5. Revocación después de la transacción.** `QuitarRol` hace en una transacción el retiro y `IAuditor::registrar('usuario.rol.retiro', ...)`; después del commit llama a `IRevocadorDeSesiones::revocar(uuid): bool`. `ApiAuthRevocadorDeSesiones` usa `ServiceTokenProvider::token(['sessions:revoke'])` y `Http::withToken(...)->timeout(5)->post(config('ubo-auth.url').'/api/auth/v1/users/{uuid}/revoke')`; 204 (o 404, la persona no existe en API-Auth) → `true`; cualquier otra respuesta, excepción o falta de token → `false`, con un `warning` en el log (sin el token). Si el token es rechazado con 401 se descarta del caché (`forget`) para el siguiente intento. Se descarta revocar dentro de la transacción: una falla de red no debe deshacer un retiro ya decidido, y un rollback tras revocar dejaría sesiones cerradas sin cambio.

**D6. `RevocarSesiones`** (reintento): aplica la regla de uno mismo; si `revocar` devuelve `true`, registra `usuario.sesiones.revocacion` en una transacción y responde 204; si no, `RevocacionFallidaException` (502 `REVOCACION_FALLIDA`).

**D7. Respuestas.** `AsignarRol`: 201 o 200 sin cuerpo útil (`{data: {asignado: true|false}}`) para que el portal recargue el detalle. `QuitarRol`: 200 `{data: {sesiones_revocadas: bool}}`. Los errores en RFC 9457 con los códigos de la spec.

**D8. Frontend.** En `detalle-usuario`, con `iam:usuarios.administrar`: un formulario «Asignar rol» que lista las aplicaciones (`GET /aplicaciones?por_pagina=100`) y los roles de la elegida (`GET /aplicaciones/{nombre}`), y un botón «Quitar» por rol con confirmación. Tras cada cambio se recarga el detalle; los errores se muestran con su mensaje (`detail`). Si `sesiones_revocadas` es `false`, un aviso con «Reintentar» llama a `sesiones/revocar`. El portal no oculta los roles de `iam-admin` a la mesa de ayuda: el backend los rechaza y el mensaje lo explica.

## Risks / Trade-offs

- [Permisos del actor tomados del token de la sesión] → Si le quitan un permiso a quien actúa, sus `scopes` siguen vigentes hasta la revalidación de la sesión (`UBO_LOGIN_REVALIDATE`, 5 min); se acepta, y además su sesión se revoca al quitarle el rol.
- [Revocación fallida] → El cambio queda aplicado y auditado; el portal avisa y permite reintentar. La persona conserva sus tokens hasta que expiren o se reintente.
- [Asignar no revoca] → Un permiso nuevo rige al renovar el token; es lo esperado.
- [Concurrencia en «último administrador»] → Dos retiros simultáneos podrían dejar cero administradores; con el volumen actual se acepta, y `iam:grant-admin` existe para recuperar.

## Migration Plan

Sin migraciones. Requiere en cada ambiente `UBO_AUTH_CLIENT_ID`/`UBO_AUTH_CLIENT_SECRET` de `iam-admin-sistema` (ya los pide el README). Se despliegan backend y frontend; los endpoints son nuevos.
