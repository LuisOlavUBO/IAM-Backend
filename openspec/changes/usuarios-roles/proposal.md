# Proposal

## Why

El portal ya muestra los roles de cada persona, pero no permite cambiarlos: hoy solo `iam:install` y `iam:grant-admin` asignan roles, y lo demás exigiría tocar la base a mano. Asignar y quitar roles es lo que más hace la mesa de ayuda, y es la primera escritura del portal. Debe respetar las protecciones acordadas (escalamiento de privilegios, nada sobre uno mismo, último administrador), quedar en la auditoría y, al quitar un rol, cerrar las sesiones de la persona para que el cambio rija de inmediato.

## What Changes

- `POST /api/iam/v1/usuarios/{uuid}/roles` con `{aplicacion, rol}`: asigna un rol a la persona. 201 si lo asigna; 200 si ya lo tenía (sin cambios ni auditoría).
- `DELETE /api/iam/v1/usuarios/{uuid}/roles/{aplicacion}/{rol}`: quita el rol y luego revoca las sesiones de la persona en API-Auth (`POST /api/auth/v1/users/{uuid}/revoke`, ya existe). Responde si la revocación funcionó; si API-Auth falla, el rol queda quitado y el portal avisa y ofrece reintentar.
- `POST /api/iam/v1/usuarios/{uuid}/sesiones/revocar`: reintenta la revocación.
- Los tres exigen sesión, `iam:usuarios.administrar` y el token CSRF.
- Reglas (rechazos auditados con `resultado` `rechazado`):
  - Asignar o quitar un rol de `iam-admin` exige que quien actúa tenga todos los permisos de `iam-admin` (403 `ESCALAMIENTO_DE_PRIVILEGIOS`): la mesa de ayuda asigna cualquier otro rol.
  - Nadie cambia sus propios roles ni revoca sus propias sesiones (403 `ACCION_SOBRE_SI_MISMO`).
  - No se quita `iam-admin/administrador` a la última persona activa que lo tiene (409 `ULTIMO_ADMINISTRADOR`).
- Auditoría: `usuario.rol.asignacion`, `usuario.rol.retiro` y `usuario.sesiones.revocacion`, en la misma transacción que el cambio.
- Casos de uso `AsignarRol`, `QuitarRol` y `RevocarSesiones` (`iam:make-feature --command`), con un repositorio de escritura, las reglas en `Domain/` y el cliente de API-Auth en `Infrastructure/`.
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): en el detalle de la persona, «Asignar rol» (aplicación y rol) y «Quitar» por rol con confirmación, visibles con `iam:usuarios.administrar`; aviso con «Reintentar» si la revocación falla.
- Fuera de este cambio: alta, baja y reactivación, reiniciar clave, editar datos de la persona, roles de clientes OAuth.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `usuarios`: se agregan la asignación y el retiro de roles, la revocación de sesiones y sus protecciones.

## Impact

- Backend: `app/Domains/Usuarios/` (Features `AsignarRol`, `QuitarRol`, `RevocarSesiones`; `Domain/` con reglas, excepciones e interfaces; `Infrastructure/` con el repositorio de escritura y el cliente de API-Auth), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/usuarios/` (servicio y detalle de la persona) y sus tests.
- Datos: escribe solo `role_user` (dueño: API-IAM) e `iam_auditoria`; lee `users`, `roles`, `applications`, `oauth_scopes` y `oauth_scope_role`.
- API-Auth: usa `POST /api/auth/v1/users/{uuid}/revoke` con el cliente `iam-admin-sistema` (`UBO_AUTH_CLIENT_ID`/`SECRET`, rol `session-revoker`), que ya existen. Sin cambios en API-Auth; sin migraciones ni dependencias nuevas.
