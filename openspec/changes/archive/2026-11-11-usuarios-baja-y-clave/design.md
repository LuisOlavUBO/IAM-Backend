## Context

`usuarios-roles` dejó las piezas de escritura de Usuarios: `ReglasDeRoles` (uno mismo, escalamiento con datos perezosos, último administrador), `IRolesDeUsuarioRepository`, `IRevocadorDeSesiones` (API-Auth, después del commit, nunca lanza) y `RevocarSesiones` para reintentar. `usuarios-alta` agregó `IPersonasRepository`, `ClaveTemporal` en uso desde el portal y la pantalla que muestra una clave una sola vez. En API-Auth, una persona con `disabled_at` no obtiene tokens (ni por login ni por refresh), pero sus access tokens ya emitidos siguen válidos hasta vencer: por eso la baja revoca. El login con clave temporal obliga a cambiarla y la rechaza vencida. `iam:grant-admin` ya registra la reactivación como `usuario.alta` con `{activo: false} → {activo: true}`.

## Goals / Non-Goals

**Goals:**
- Baja, reactivación y reinicio de clave desde el portal, con revocación de sesiones y auditoría.
- Cerrar el camino de toma de cuentas: la mesa de ayuda no actúa sobre personas con roles de `iam-admin`.

**Non-Goals:**
- Editar los datos de la persona, desvincular Microsoft, borrar personas.
- Revocar al reactivar (no hay sesiones vigentes que cerrar).
- Avisar a la persona por correo de su clave nueva (se entrega por el canal que use la mesa de ayuda).

## Decisions

**D1. Rutas como acciones** (`POST usuarios/{uuid}/baja`, `/reactivacion`, `/clave`) en el grupo `can:iam:usuarios.administrar`, igual que `sesiones/revocar`. Son acciones con efectos (revocación, clave nueva), no actualizaciones de un campo, y así cada una tiene su permiso, su auditoría y su respuesta. Alternativa descartada: `PATCH usuarios/{uuid}` con `{activo}`, que mezcla en una ruta acciones con reglas distintas.

**D2. Estructura** (cada uno con `iam:make-feature Usuarios <Caso> --command`):
```
app/Domains/Usuarios/
+-- Features/DarDeBaja/          Endpoint, CommandHandler
+-- Features/Reactivar/          Endpoint, CommandHandler
+-- Features/ReiniciarClave/     Endpoint, CommandHandler, Resource
+-- Domain/ReglasDePersonas.php  escalamiento sobre la persona y último administrador al dar de baja
+-- Domain/Interfaces/IPersonasRepository.php   + estado(uuid), darDeBaja, reactivar, cambiarClave, rolesDeIamAdmin
```
`ReglasDeRoles::verificarQueNoEsUnoMismo` se reutiliza. Los handlers siguen el molde de `QuitarRol`: buscar la persona (404) → reglas auditadas → transacción con cambio y auditoría → revocación después del commit.

**D3. Escalamiento sobre la persona.** `ReglasDePersonas::verificarEscalamiento(actor, tieneRolDeIamAdmin: callable(): bool, permisosDeIamAdmin: callable(): list<string>)`: si la persona tiene algún rol de `iam-admin`, el actor debe tener todos los permisos de `iam-admin`. Datos perezosos como en `ReglasDeRoles`: si la persona no tiene roles de `iam-admin`, no se piden los permisos. Se aplica a las tres acciones.

**D4. Último administrador al dar de baja.** Si la persona tiene `iam-admin/administrador`, está activa y no hay otra activa con ese rol → `UltimoAdministradorException`. Reutiliza `IRolesDeUsuarioRepository::otrosActivosConRol`. Reactivar y reiniciar clave no lo necesitan.

**D5. Idempotencia.** Baja de alguien ya de baja y reactivación de alguien activo responden 200 sin cambios, sin auditoría ni revocación (`sesiones_revocadas: null` en la baja). Las reglas (uno mismo, escalamiento) se verifican antes, para que un intento indebido quede auditado aunque no cambiara nada.

**D6. Reiniciar clave.** `ClaveTemporal::generar()`; se actualizan `password` y `temporary_password_expires_at` (y `updated_at`). Se audita `usuario.clave.reinicio` sin `antes` ni `despues`. Respuesta 200 `{data: {clave_temporal: {clave, vence}, sesiones_revocadas}}` con `Cache-Control: no-store`. A una persona que solo entraba con Microsoft le da además una clave: es lo que se pide cuando no puede usar Microsoft.

**D7. Frontend.** En `detalle-usuario`, en la sección Cuenta, con `editable()` (permiso y no uno mismo): «Dar de baja» / «Reactivar» según `estado`, y «Reiniciar clave», ambos con `confirm()`. El reinicio muestra la clave temporal en la misma página con «Copiar» y el aviso de una sola vez (reutilizando el bloque de la página `nueva-persona` si está en `dominios/usuarios`). Tras cada acción se recarga la persona; si `sesiones_revocadas` es `false`, el mismo aviso con «Reintentar» que al quitar un rol.

## Risks / Trade-offs

- [La mesa de ayuda no puede ayudar a un administrador] → Es intencional: lo atiende otro administrador; `iam:grant-admin` queda para emergencias.
- [Reinicio a personas con Microsoft les agrega una clave] → Aceptado y documentado; la clave es temporal y obliga a cambiarla.
- [Baja con revocación fallida] → API-Auth igual rechaza refresh y login; los access tokens vigentes duran como máximo su vida útil. El portal ofrece reintentar.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; los endpoints son nuevos.
