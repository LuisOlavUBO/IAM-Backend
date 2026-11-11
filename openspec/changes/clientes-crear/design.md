## Context

`oauth_clients` es de API-IAM (tabla de Passport: `id` UUID v7, `name`, `secret` bcrypt, `provider`, `redirect_uris` y `grant_types` en JSON, `revoked`, `application_id`). `Clientes/Domain/NuevoCliente` crea clientes con los atributos que fija API-Auth (`authorization-code` → `authorization_code`+`refresh_token` y `provider=users`; `client-credentials` → `client_credentials` sin provider), pero hace el `INSERT` desde `Domain/` (deuda frente a la regla «`Domain` sin SQL»). `iam:install` lo usa para los dos clientes de `iam-admin` y valida las redirecciones con `InstallCommand::isValidRedirect` (https o http en localhost, sin fragmento) y audita `cliente.creacion` con `{nombre, aplicacion, tipo, redirecciones}`. El dominio Clientes ya tiene `IClientesRepository` (buscar, buscarPorNombre, cambiarSecreto, marcarRevocado), `SecretoDeCliente`, el grupo `can:iam:clientes.administrar` y, en el frontend, `shared/ui/secreto-mostrado`.

## Goals / Non-Goals

**Goals:**
- Crear desde el portal los dos tipos de cliente confidencial, con el secreto mostrado una sola vez.
- Sacar el SQL de `NuevoCliente` y compartir la regla de redirecciones entre el portal e `iam:install`.

**Non-Goals:**
- Roles, edición de redirecciones, clientes públicos, cambiar la aplicación (cambios siguientes).
- Crear clientes de `iam-admin` desde el portal.

## Decisions

**D1. `POST clientes`** en el grupo `can:iam:clientes.administrar`; respuesta 201 `{data: {id, secreto}}` con `no-store`.

**D2. `NuevoCliente` sin SQL.** Pasa a construir el cliente: genera el id (UUID v7 en minúsculas), el secreto con `SecretoDeCliente` y los atributos según el tipo, y devuelve un `NuevoClienteDto` con lo que hay que guardar más el secreto en claro. `IClientesRepository::crear(NuevoClienteDto, aplicacionId)` hace el `INSERT`. `iam:install` llama a `NuevoCliente` y al repositorio por su interfaz (Console puede usar `Domain`, no `Infrastructure`); su comportamiento y su salida no cambian.

**D3. `Domain/Redireccion::esValida(string): bool`**, movida desde `InstallCommand::isValidRedirect`, y una regla de validación para el Request. `iam:install` pasa a usarla.

**D4. Validación en el Request** (D3 para cada redirección; `tipo` `in:`; `redirecciones` `required_if:tipo,authorization-code`, `prohibited_if:tipo,client-credentials`); la aplicación se resuelve en el handler: inexistente o `iam-admin` → `ValidationException` en `aplicacion`.

**D5. Nombre único.** `IClientesRepository::nombreEnUso(nombre)` antes de insertar (la intercalación CI no distingue mayúsculas) → `ClienteExistenteException` 409. No hay índice único en `oauth_clients.name` (tabla de Passport que API-Auth lee; agregarlo exigiría limpiar duplicados históricos), así que dos altas simultáneas con el mismo nombre podrían pasar: se acepta, como en `iam:rotate-secret`, que ante nombres ambiguos pide el id.

**D6. Transacción y auditoría.** `crear` y `IAuditor::registrar('cliente.creacion', 'cliente', $id, null, {nombre, aplicacion, tipo, redirecciones})` en la misma transacción; el secreto en claro solo vuelve al Endpoint.

**D7. Frontend.** Botón «Nuevo cliente» en `lista-clientes` (con `iam:clientes.administrar`); ruta `nuevo` antes de `:id`; página `nuevo-cliente` con aplicación (lista de `/aplicaciones` por URL, sin `iam-admin`), nombre, tipo (`authorization-code` por omisión) y redirecciones (una por línea, solo para `authorization-code`). Errores 422 junto a su campo y 409 como mensaje. Al crear, muestra el secreto con `SecretoMostrado` y un botón «Ir al cliente».

## Risks / Trade-offs

- [Nombre duplicado por carrera] → Aceptado (D5); el comando de rotación pide el id si el nombre es ambiguo.
- [Cliente de servicio sin roles] → Obtiene tokens sin scopes hasta que se le asignen; el detalle lo muestra sin roles y el siguiente cambio permite asignarlos.
- [Cambio de `NuevoCliente` afecta a `iam:install`] → Sus tests (`InstallCommandTest`, `NuevoClienteTest`) deben seguir en verde sin cambiar lo que comprueban.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; el endpoint es nuevo.
