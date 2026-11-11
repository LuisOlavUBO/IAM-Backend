## Context

`oauth_clients.revoked` es de API-IAM. En API-Auth, Passport ya trata un cliente revocado como inexistente al emitir y renovar tokens (`ClientRepository::findActive`) y en `TokenGuard`; la introspección solo lo reporta inactivo con el cambio `introspeccion-cliente-revocado` de API-Auth. `clientes-rotar-secreto` dejó el dominio Clientes con su primera escritura: `IClientesRepository` (`buscar`, `buscarPorNombre`, `cambiarSecreto`), `ClienteParaRotarDto` (id, nombre, aplicación, confidencial), el grupo de rutas `can:iam:clientes.administrar` y, en el frontend, acciones en `detalle-cliente` con `AuthService.tienePermiso`. Los clientes de `iam-admin` los usa la propia API-IAM (`iam-admin-portal` para el login del portal, `iam-admin-sistema` para la revocación de sesiones).

## Goals / Non-Goals

**Goals:**
- Revocar y reactivar clientes desde el portal, auditado.
- Que revocar corte de inmediato junto con API-Auth `introspeccion-cliente-revocado`.

**Non-Goals:**
- Escribir en `oauth_access_tokens` (tabla de API-Auth).
- Crear clientes, editar redirecciones y roles.
- Un comando de consola para reactivar (si se revoca un cliente de `iam-admin` por otra vía, se reactiva desde el portal con el otro cliente, o se crea un cambio para ello).

## Decisions

**D1. Rutas como acciones**: `POST clientes/{id}/revocacion` y `POST clientes/{id}/reactivacion`, como `usuarios/{uuid}/baja` y `/reactivacion`. Respuesta 200 `{data: {revocado: bool}}`.

**D2. Estructura**:
```
app/Domains/Clientes/
+-- Features/RevocarCliente/       Endpoint, CommandHandler
+-- Features/ReactivarCliente/     Endpoint, CommandHandler
+-- Domain/ClienteDeIamAdminException.php      409 CLIENTE_DE_IAM_ADMIN
+-- Domain/Dto/ClienteParaRotarDto.php         se renombra a ClienteParaEscribirDto y suma `revocado`
+-- Domain/Interfaces/IClientesRepository.php  + marcarRevocado(id, bool)
```
Renombrar el DTO evita uno casi igual por caso de uso: lo usan ya dos casos de uso (regla «sube a `Domain/` cuando lo usa un segundo»).

**D3. Orden del handler.** id no UUID o inexistente → 404; al revocar, cliente de `iam-admin` → `IAuditor::rechazar('cliente.revocacion', 'cliente', $id, 'CLIENTE_DE_IAM_ADMIN')` y 409; si ya está en el estado pedido → 200 sin cambios; si no, transacción con `marcarRevocado` (+ `updated_at`) y `IAuditor::registrar`.

**D4. Frontend.** En `detalle-cliente`, con `iam:clientes.administrar` y si el cliente no es de `iam-admin`: «Revocar» (confirm: «El sistema que usa este cliente pierde el acceso de inmediato; se puede reactivar») o «Reactivar», según `revocado`. Tras la acción se recarga el cliente. En un cliente de `iam-admin` revocado se muestra «Reactivar» igualmente.

## Risks / Trade-offs

- [API-IAM desplegado antes que API-Auth] → La revocación no corta los tokens vigentes en las APIs que introspectan hasta que venzan (≤ 60 min). Se documenta el orden de despliegue.
- [Reactivar revive tokens no vencidos] → Es lo esperado al deshacer una revocación por error; si el motivo fue un secreto filtrado, se rota el secreto antes de reactivar (el confirm del portal lo recuerda).

## Migration Plan

Sin migraciones. Orden: 1) API-Auth `introspeccion-cliente-revocado`; 2) backend y frontend de este cambio.
