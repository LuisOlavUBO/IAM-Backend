## Context

El dominio Clientes solo tiene `routes/v1.php` (vacío) y `Domain/NuevoCliente`, que usa `iam:install` para crear `iam-admin-portal` (authorization-code) e `iam-admin-sistema` (client-credentials, con los roles `introspector` y `session-revoker` de `api-auth`). `oauth_clients` es la tabla de Passport: `id` UUID (`uniqueidentifier`, SQL Server lo devuelve en mayúsculas), `name`, `secret` (hash bcrypt o `null`), `provider`, `redirect_uris` y `grant_types` (JSON en texto), `revoked`, `owner_*` (morph, sin uso en IAM), fechas y `application_id`. `oauth_client_role` une clientes con roles, y cada rol pertenece a una aplicación que puede ser distinta de la del cliente. Aplicaciones (`aplicaciones-consulta`) es la referencia directa.

## Goals / Non-Goals

**Goals:**
- Ver en el portal los clientes OAuth, su aplicación, sus redirecciones, sus concesiones, si están revocados y qué roles tienen.
- Mismo contrato y estructura que Usuarios y Aplicaciones, sin piezas nuevas en `Shared`.

**Non-Goals:**
- Escritura: crear, rotar el secreto, revocar, editar redirecciones, asignar roles.
- Ver los tokens emitidos a un cliente (tablas de API-Auth).

## Decisions

**D1. El id del cliente identifica el recurso: `/clientes/{id}`.** Es el `client_id` OAuth, ya público (va en las URLs de autorización y en la configuración de cada sistema), así que exponerlo no filtra nada y es lo que se busca al diagnosticar. Se devuelve en minúsculas (`Str::lower`, como el `uuid` de usuarios). El handler valida con `Str::isUuid` antes de consultar: un id que no es UUID haría fallar la conversión a `uniqueidentifier` en SQL Server, así que responde 404 directamente, como `VerUsuario`. Alternativa descartada: el nombre, que no es único en `oauth_clients`.

**D2. Estructura del dominio** (creada con `iam:make-feature`):
```
app/Domains/Clientes/
+-- ClientesServiceProvider.php           IClienteQueryRepository => ClienteQueryRepository
+-- routes/v1.php                         can:iam:clientes.leer
+-- Features/ListarClientes/              Endpoint, Request, QueryHandler, Resource
+-- Features/VerCliente/                  Endpoint, QueryHandler, Resource (sin Request)
+-- Domain/NuevoCliente.php               (existente, sin cambios)
+-- Domain/ClienteNoEncontradoException.php
+-- Domain/Dto/                           FiltroClientesDto, ClienteResumenDto, ClienteDetalleDto, RolClienteDto
+-- Domain/Interfaces/IClienteQueryRepository.php
+-- Infrastructure/ClienteQueryRepository.php
```
Sin `Contracts/`: ningún otro dominio lo consulta.

**D3. Listado.** `oauth_clients` con `join applications`, el conteo de roles por subconsulta sobre `oauth_client_role`, orden por `oauth_clients.name` y luego `id`. `buscar` usa `LIKE` escapado (`ESCAPE '!'`) sobre el nombre del cliente y el de la aplicación; si `buscar` es un UUID se agrega `or oauth_clients.id = ?`. Se incluyen los revocados (con `revocado`): esconderlos ocultaría justo lo que se diagnostica.

**D4. Detalle en dos consultas fijas**: el cliente con su aplicación; y sus roles con `join roles` y `join applications` (la aplicación del rol), ordenados por aplicación y nombre. `concesiones` y `redirecciones` se decodifican del JSON; un valor que no es una lista JSON se trata como lista vacía. `confidencial` es `secret is not null`, calculado en SQL: el hash nunca sale de la consulta.

**D5. Qué no sale**: ni `secret`, ni `owner_*`, ni `provider` (siempre `users` o `null`; no aporta al portal), ni ids numéricos. Se seleccionan solo las columnas de la respuesta, así que el hash no llega ni al DTO.

**D6. `ClienteNoEncontradoException`** como `DomainProblem` 404 con código `CLIENTE_NO_ENCONTRADO`.

**D7. Frontend igual que Aplicaciones**: `cliente.ts`, `clientes.service.ts` y `paginas/lista-clientes` y `paginas/detalle-cliente` con `rxResource`, filtros en la URL y la guarda de página fuera de rango. La aplicación del cliente y de cada rol enlaza a `/aplicaciones/{nombre}` por URL, sin importar el dominio Aplicaciones.

## Risks / Trade-offs

- [`redirect_uris` o `grant_types` con datos inesperados escritos por otra vía] → Se muestran como lista vacía en vez de fallar (D4).
- [El id en la URL] → Es público por diseño de OAuth; el secreto nunca se expone.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; ambos endpoints son nuevos, así que el orden no importa.
