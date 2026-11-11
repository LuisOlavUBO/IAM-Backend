## Context

`oauth_clients` es de API-IAM; `secret` guarda el hash bcrypt (o `null` en clientes públicos) y API-Auth lo valida con `Hash::check` en cada `client_credentials` y en cada canje de código, sin caché. `NuevoCliente` (`Clientes/Domain`) crea clientes en `iam:install` con `Str::random(40)` y `Hash::make`. El README tiene un procedimiento de emergencia con `UPDATE` manual y anuncia `iam:rotate-secret`. `iam:install` y el procedimiento ya usan la acción `cliente.secreto.rotacion`. El dominio Clientes tiene `ListarClientes` y `VerCliente`, sin escrituras. `usuarios-alta` y `usuarios-baja-y-clave` dejaron el molde de «secreto mostrado una sola vez» (`no-store`, componente `ClaveTemporalMostrada` en el frontend de usuarios). API-IAM usa dos clientes de `iam-admin`: `iam-admin-portal` (`UBO_LOGIN_CLIENT_SECRET`, login del portal) e `iam-admin-sistema` (`UBO_AUTH_CLIENT_SECRET`, revocación de sesiones).

## Goals / Non-Goals

**Goals:**
- Rotar el secreto de un cliente confidencial desde el portal y por consola, sin SQL manual.
- Que rotar un cliente de `iam-admin` sea deliberado y diga qué variable cambiar.

**Non-Goals:**
- Período de gracia con dos secretos válidos (Passport guarda uno).
- Revocar los tokens emitidos con el secreto anterior (viven hasta vencer; si el secreto se filtró, se combina con revocar el cliente, que es otro cambio).
- Crear, revocar o editar clientes.

## Decisions

**D1. `POST clientes/{id}/secreto`** en un grupo `can:iam:clientes.administrar`, cuerpo opcional `{confirmar}`, respuesta 200 `{data: {secreto, variable_env: string|null}}` con `Cache-Control: no-store`. Es una acción (genera algo nuevo), no una edición del cliente.

**D2. Estructura**:
```
app/Domains/Clientes/
+-- Features/RotarSecreto/                Endpoint, Request, CommandHandler, Resource
+-- Domain/SecretoDeCliente.php           genera 40 caracteres (Str::random) y su hash; sin SQL
+-- Domain/ClientePublicoException.php    409 CLIENTE_PUBLICO
+-- Domain/Dto/ClienteParaRotarDto.php    id, nombre, aplicacion, confidencial
+-- Domain/Interfaces/IClientesRepository.php   buscar(id), buscarPorNombre(nombre): list, cambiarSecreto(id, hash)
+-- Infrastructure/ClientesRepository.php
app/Console/Commands/RotateSecretCommand.php       iam:rotate-secret (usa el CommandHandler de Features)
```
`NuevoCliente` no se toca (su deuda de SQL en `Domain/` queda para el cambio de crear clientes).

**D3. Confirmación de `iam-admin`.** La regla vive en el handler: si `aplicacion === 'iam-admin'` y `confirmar !== nombre`, `ValidationException` en `confirmar` (422), sin auditar (no es un intento indebido sino un paso faltante). `variable_env` se calcula por nombre: `iam-admin-portal` → `UBO_LOGIN_CLIENT_SECRET`, `iam-admin-sistema` → `UBO_AUTH_CLIENT_SECRET`, otro → `null`. El comando no exige `confirmar`: su confirmación es la de ambiente y base, y quien lo corre está en el servidor.

**D4. Orden del handler.** id no UUID o inexistente → 404 `CLIENTE_NO_ENCONTRADO`; público → 409 `CLIENTE_PUBLICO`; confirmación de `iam-admin` → 422; transacción con `cambiarSecreto` (+ `updated_at`) y `IAuditor::registrar('cliente.secreto.rotacion', 'cliente', $id)`; devuelve el secreto en claro solo al Endpoint.

**D5. Comando.** `iam:rotate-secret {cliente}`: si es UUID busca por id; si no, por nombre (si hay más de uno, falla y lista `id · aplicación` de cada uno). Muestra ambiente, servidor, base y el cliente; pide confirmación; llama al handler con `confirmar` igual al nombre; imprime `UBO_LOGIN_CLIENT_SECRET=<secreto>` (o la variable que corresponda) o, para otros clientes, `client_id` y `client_secret`. El README cambia el procedimiento de emergencia por este comando.

**D6. Frontend.** En `detalle-cliente`, si `confidencial` y la persona tiene `iam:clientes.administrar`: botón «Rotar secreto» con `confirm()` que explica que el anterior deja de servir; para clientes de `iam-admin`, además un campo donde escribir el nombre del cliente. El secreto se muestra una sola vez con «Copiar» y, si viene `variable_env`, el aviso «Actualice `<variable>` en el `.env` de API-IAM y reinicie». Si el componente de clave mostrada una vez de Usuarios sirve igual, se mueve a `shared/ui` (lo usan dos dominios), siguiendo la regla del README del frontend.

## Risks / Trade-offs

- [Rotar `iam-admin-portal` corta el login del portal hasta actualizar el `.env`] → Confirmación escrita, `variable_env` en la respuesta, y `iam:rotate-secret` para recuperar si el secreto se pierde.
- [Tokens emitidos con el secreto anterior siguen válidos] → Aceptado (Non-Goals); la rotación protege los tokens futuros.
- [Un sistema queda sin acceso al rotar su secreto] → Es lo esperado; el confirm lo explica.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend. Desde ese despliegue, el procedimiento de emergencia del README es `iam:rotate-secret`.
