# Proposal

## Why

El secreto de un cliente OAuth confidencial hoy solo se cambia con el procedimiento de emergencia del README: generar un hash con tinker y hacer un `UPDATE` a mano en `oauth_clients`, la única carga manual que todavía se permite. Si un secreto se filtra o se pierde (por ejemplo, el de un sistema que se reinstala), debe poder rotarse sin SQL, con auditoría y mostrando el secreto nuevo una sola vez. Y si el que se pierde es el del propio portal (`iam-admin-portal`), el portal no puede iniciar sesión, así que hace falta también un comando de consola.

## What Changes

- `POST /api/iam/v1/clientes/{id}/secreto`: genera un secreto nuevo para un cliente confidencial, guarda solo su hash bcrypt, invalida el anterior y devuelve el secreto **una sola vez** (`Cache-Control: no-store`). Exige sesión, `iam:clientes.administrar` y CSRF. Se audita `cliente.secreto.rotacion` (sin el secreto ni su hash), la acción que ya usa el procedimiento de emergencia.
- Los clientes de `iam-admin` (`iam-admin-portal`, `iam-admin-sistema`) también se rotan, pero exigen escribir el nombre del cliente en `confirmar`: rotarlos corta el login del portal o la revocación de sesiones hasta que se actualice el `.env` de API-IAM (`UBO_LOGIN_CLIENT_SECRET` / `UBO_AUTH_CLIENT_SECRET`) y se reinicie. La respuesta dice qué variable cambiar.
- Un cliente público (sin secreto) no se rota: 409 `CLIENTE_PUBLICO`, porque darle un secreto rompería a la aplicación que lo usa con PKCE.
- `php artisan iam:rotate-secret {cliente}`: el mismo caso de uso por consola (por nombre o id), con la confirmación de ambiente y base de los comandos `iam:*`, para recuperar el portal. Muestra la línea del `.env` lista para copiar. Reemplaza el procedimiento de emergencia del README.
- Caso de uso `RotarSecreto` (`iam:make-feature Clientes RotarSecreto --command`), con el secreto generado en `Domain/` y un repositorio de escritura de clientes.
- OpenAPI y Bruno; README sin el `UPDATE` manual.
- Frontend (IAM-Frontend): en el detalle de un cliente confidencial, con `iam:clientes.administrar`, «Rotar secreto» con confirmación (y el nombre escrito para los de `iam-admin`); muestra el secreto una sola vez con «Copiar».
- Fuera de este cambio: crear clientes, revocar y reactivar, roles y redirecciones de clientes, y revocar los tokens ya emitidos con el secreto anterior (siguen válidos hasta vencer).

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `clientes`: se agrega la rotación del secreto desde el portal.
- `comandos-de-operacion`: se agrega `iam:rotate-secret` como recuperación.

## Impact

- Backend: `app/Domains/Clientes/` (Feature `RotarSecreto`, `Domain/SecretoDeCliente`, excepciones, `IClientesRepository` + implementación), `app/Console/Commands/RotateSecretCommand.php`, `routes/v1.php`, tests, OpenAPI, Bruno y README.
- Frontend: `dominios/clientes/` (servicio y detalle) y sus tests.
- Datos: actualiza `oauth_clients.secret` y `updated_at` (dueño: API-IAM) e inserta en `iam_auditoria`.
- API-Auth: sin cambios; valida el secreto con `Hash::check` contra `oauth_clients.secret` en cada `client_credentials` y en cada canje de código. Sin migraciones ni dependencias nuevas.
