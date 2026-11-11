# Proposal

## Why

Un cliente OAuth hoy no se puede desactivar sin SQL: ni para retirar un sistema que ya no se usa ni para cortar el acceso de un secreto filtrado. Rotar el secreto (`clientes-rotar-secreto`) protege los tokens futuros, pero no corta a quien ya obtuvo uno; revocar el cliente sí, y además deja el registro intacto para poder reactivarlo.

## What Changes

- `POST /api/iam/v1/clientes/{id}/revocacion`: marca `oauth_clients.revoked`. API-Auth deja de emitir y renovar tokens para el cliente, sus rutas rechazan sus tokens y, con el cambio `introspeccion-cliente-revocado` de API-Auth, la introspección reporta inactivos los tokens vigentes. Si ya estaba revocado, 200 sin cambios.
- `POST /api/iam/v1/clientes/{id}/reactivacion`: quita la marca; los tokens que no vencieron vuelven a valer. Si ya estaba activo, 200 sin cambios.
- Los clientes de `iam-admin` no se revocan (409 `CLIENTE_DE_IAM_ADMIN`): revocarlos dejaría sin login al portal o sin revocación de sesiones, y el portal no podría deshacerlo.
- Ambos exigen sesión, `iam:clientes.administrar` y CSRF, y se auditan como `cliente.revocacion` y `cliente.reactivacion` (`{"revocado": false}` ↔ `{"revocado": true}`).
- Casos de uso `RevocarCliente` y `ReactivarCliente`, sobre `IClientesRepository`.
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): en el detalle del cliente, con `iam:clientes.administrar`, «Revocar» o «Reactivar» con confirmación (ocultos en los de `iam-admin`); el listado ya muestra el estado.
- Fuera de este cambio: crear clientes, roles y redirecciones de clientes, y revocar en `oauth_access_tokens` (tabla de API-Auth).

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `clientes`: se agregan la revocación y la reactivación de clientes.

## Impact

- Backend: `app/Domains/Clientes/` (Features `RevocarCliente` y `ReactivarCliente`, excepción, métodos en `IClientesRepository`), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/clientes/` (servicio y detalle) y sus tests.
- Datos: actualiza `oauth_clients.revoked` y `updated_at` (dueño: API-IAM) e inserta en `iam_auditoria`.
- **Depende de API-Auth `introspeccion-cliente-revocado`**, que se despliega primero. Sin él todo funciona, salvo que los tokens vigentes del cliente siguen aceptados por las APIs que introspectan hasta vencer (máximo 60 minutos).
