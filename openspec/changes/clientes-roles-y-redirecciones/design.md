## Context

`oauth_client_role` (`client_id`, `role_id`) y `oauth_clients.redirect_uris` (JSON) son de API-IAM. En API-Auth, un token de cliente lleva los scopes de los roles del cliente; un token de persona, los de los roles de la persona en la aplicación del cliente: los roles de un cliente `authorization-code` no se usan. Los cambios de roles rigen en el siguiente token (spec `scope-authorization`). El dominio Clientes tiene `IClientesRepository` (buscar → `ClienteParaEscribirDto` con id, nombre, aplicación, confidencial, revocado), `Redireccion::esValida`, `ClienteDeIamAdminException` y las escrituras `CrearCliente`, `RotarSecreto`, `RevocarCliente` y `ReactivarCliente`. `iam:install` ya audita `cliente.rol.asignacion` con `{rol: "api-auth/introspector"}`. `usuarios-roles` es el molde de asignar y quitar roles (rutas, 201/200, 404 `ROL_NO_ASIGNADO`, rechazos auditados) y del frontend (formulario aplicación → rol y «×» por rol).

## Goals / Non-Goals

**Goals:**
- Que un cliente de servicio reciba y pierda roles, y que un cliente de login cambie sus redirecciones, desde el portal.

**Non-Goals:**
- Cambiar la aplicación o el tipo de un cliente; clientes públicos; revocar tokens al quitar un rol.

## Decisions

**D1. Rutas** en el grupo `can:iam:clientes.administrar`: `POST clientes/{id}/roles`, `DELETE clientes/{id}/roles/{aplicacion}/{rol}` (como en Usuarios) y `PUT clientes/{id}/redirecciones` (la lista completa: es un reemplazo, no una acción).

**D2. Tipo del cliente.** `ClienteParaEscribirDto` suma `tipo` (`authorization-code` si sus concesiones incluyen `authorization_code`, `client-credentials` si incluyen `client_credentials`) y `redirecciones`. Las reglas dependen de él.

**D3. Estructura**:
```
app/Domains/Clientes/
+-- Features/AsignarRolACliente/      Endpoint, Request, CommandHandler
+-- Features/QuitarRolACliente/       Endpoint, CommandHandler
+-- Features/CambiarRedirecciones/    Endpoint, Request, CommandHandler
+-- Domain/ReglasDeClientes.php       iam-admin, tipo y rol de iam-admin (puras)
+-- Domain/ClienteSinRolesException.php, ClienteSinRedireccionesException.php, RolDeIamAdminException.php (409)
+-- Domain/RolNoAsignadoException.php (404, código ROL_NO_ASIGNADO como en Usuarios)
+-- Domain/Interfaces/IClientesRepository.php  + rol(aplicacion, nombre), tieneRol, asignarRol, quitarRol, cambiarRedirecciones
```
El rol se busca en `roles` + `applications` dentro del repositorio de Clientes (`oauth_client_role` es de Clientes y la consulta solo resuelve el id del rol), igual que hace `RolesDeUsuarioRepository` en Usuarios.

**D4. Orden de los handlers** (molde de `QuitarRol`): 404 cliente → regla `iam-admin` (auditada) → tipo (auditada) → validación del rol (422) o rol no asignado (404) → rol de `iam-admin` al asignar (auditada) → idempotencia → transacción con el cambio y la auditoría. Las redirecciones se comparan sin importar el orden para decidir si hubo cambio, y se guardan en el orden recibido, sin duplicados.

**D5. Frontend.** En `detalle-cliente`, con el permiso y si el cliente no es de `iam-admin`: en los de servicio, la sección Roles suma «×» por rol con confirmación y un formulario «Asignar rol» (aplicación de `/aplicaciones` sin `iam-admin`, luego rol); en los de login, «Editar» en Redirecciones abre un textarea (una por línea) con «Guardar» y «Cancelar». Errores con su `detail` o el mensaje de su campo.

## Risks / Trade-offs

- [Quitar un rol no corta los tokens vigentes] → Rige en el siguiente token (≤ 60 min), como define API-Auth; si es urgente, se revoca el cliente.
- [Quitar todas las redirecciones dejaría el cliente inútil] → Se exige al menos una.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; los endpoints son nuevos.
