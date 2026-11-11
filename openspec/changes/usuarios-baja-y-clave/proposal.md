# Proposal

## Why

En Usuarios ya se puede dar de alta a una persona y asignarle o quitarle roles, pero quedan dos cosas que la mesa de ayuda hace a diario y hoy exigen tocar la base: quitarle el acceso a alguien que dejó la universidad (y devolvérselo si vuelve), y entregar una clave nueva a quien la olvidó. Las dos deben cerrar las sesiones de la persona en API-Auth y quedar auditadas, y ninguna debe permitir que la mesa de ayuda tome el control de una cuenta de administración.

## What Changes

- `POST /api/iam/v1/usuarios/{uuid}/baja`: pone `disabled_at` y después revoca sus sesiones en API-Auth (`sesiones_revocadas` en la respuesta; si falla, se reintenta con la revocación que ya existe). API-Auth ya no le emite tokens. Si ya estaba de baja, responde 200 sin cambios.
- `POST /api/iam/v1/usuarios/{uuid}/reactivacion`: quita `disabled_at`. Si ya estaba activa, 200 sin cambios. Conserva sus roles.
- `POST /api/iam/v1/usuarios/{uuid}/clave`: genera una clave temporal nueva (la misma `ClaveTemporal` del alta), la devuelve **una sola vez** (`Cache-Control: no-store`) y revoca sus sesiones. API-Auth obliga a cambiarla en el primer ingreso.
- Los tres exigen sesión, `iam:usuarios.administrar` y CSRF, y se auditan: `usuario.baja`, `usuario.alta` (reactivación, el nombre que ya usa `iam:grant-admin`) y `usuario.clave.reinicio` (sin la clave ni su hash).
- Protecciones, auditadas como `rechazado`:
  - **Nada sobre uno mismo** (403 `ACCION_SOBRE_SI_MISMO`), como en los roles.
  - **Sin escalamiento:** actuar sobre una persona que tiene algún rol de `iam-admin` exige todos los permisos de `iam-admin` (403 `ESCALAMIENTO_DE_PRIVILEGIOS`). Así la mesa de ayuda no puede reiniciar la clave de un administrador y entrar con ella, ni dejar a IAM sin administradores.
  - **Último administrador:** no se da de baja a la última persona activa con `iam-admin/administrador` (409 `ULTIMO_ADMINISTRADOR`).
- Casos de uso `DarDeBaja`, `Reactivar` y `ReiniciarClave`; las reglas de persona se suman a `Domain/` junto a `ReglasDeRoles`.
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): en el detalle de la persona, con `iam:usuarios.administrar` y salvo en el propio perfil: «Dar de baja» o «Reactivar» con confirmación, y «Reiniciar clave» con confirmación, que muestra la clave temporal una sola vez como en el alta.
- Fuera de este cambio: editar nombre, correo o RUT; desvincular la cuenta Microsoft; borrar personas (nunca se borran).

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `usuarios`: se agregan la baja, la reactivación y el reinicio de clave, y la regla de escalamiento sobre personas con roles de `iam-admin`.

## Impact

- Backend: `app/Domains/Usuarios/` (Features `DarDeBaja`, `Reactivar`, `ReiniciarClave`; reglas y repositorio de escritura), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/usuarios/` (servicio y detalle de la persona) y sus tests.
- Datos: actualiza `users.disabled_at`, `users.password` y `users.temporary_password_expires_at` (dueño: API-IAM) e inserta en `iam_auditoria`.
- API-Auth: sin cambios; ya rechaza tokens de personas con `disabled_at`, obliga a cambiar la clave temporal y ofrece la revocación de sesiones. Sin migraciones ni dependencias nuevas.
