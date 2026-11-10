## 1. Listado de clientes

- [x] 1.1 Crear con `iam:make-feature Clientes ListarClientes` el caso de uso, `FiltroClientesDto`, `ClienteResumenDto`, `IClienteQueryRepository::listar`, `ClienteQueryRepository` (D3) y `ClientesServiceProvider` (registrado en `bootstrap/providers.php`); registrar `GET clientes` (`clientes.listar`) con `can:iam:clientes.leer`
- [x] 1.2 `tests/Feature/Clientes/ListarClientesTest.php`: permiso (200, 403, 401), primera página, aplicación, revocado y conteo de roles, paginación inválida, búsqueda por aplicación, por id en mayúsculas y comodín como texto
- [x] 1.3 OpenAPI (`ClienteResumen`, 401/403/422) y `docs/bruno/Clientes/Listar clientes.bru`; `DocsTest` en verde

## 2. Detalle de cliente

- [x] 2.1 Crear `VerCliente` (sin Request), `ClienteDetalleDto`, `RolClienteDto`, `ClienteNoEncontradoException` e `IClienteQueryRepository::detalle` (D1, D4, D6); registrar `GET clientes/{id}` (`clientes.ver`)
- [x] 2.2 `tests/Feature/Clientes/VerClienteTest.php`: client-credentials con roles de otra aplicación, authorization-code con redirección, cliente público, id en mayúsculas, id inválido e inexistente (404) y 403 sin permiso; test de «Sin secretos, ids internos ni escritura» sobre listado y detalle (incluye que el hash no aparece en el cuerpo)
- [x] 2.3 Test unitario de los handlers con un repositorio falso (id no UUID e inexistente → `ClienteNoEncontradoException`, sin llamar al repositorio en el primer caso)
- [x] 2.4 OpenAPI (`ClienteDetalle`, 404 `CLIENTE_NO_ENCONTRADO`) y `docs/bruno/Clientes/Ver cliente.bru`

## 3. Frontend (IAM-Frontend)

- [x] 3.1 `dominios/clientes/`: `cliente.ts`, `clientes.service.ts` y rutas a `paginas/lista-clientes` (búsqueda y paginación en la URL) y `paginas/detalle-cliente` (concesiones, redirecciones, roles con su aplicación y enlaces a `/aplicaciones/{nombre}`), en lugar de `EnConstruccion`
- [x] 3.2 Tests del servicio y de ambas páginas; README del frontend

## 4. Verificación

- [x] 4.1 Pint, suite completa del backend y del frontend, y `openspec validate clientes-consulta --strict`
- [x] 4.2 En el portal con la base de desarrollo: buscar, abrir `iam-admin-portal` e `iam-admin-sistema` y comprobar que redirecciones, concesiones y roles coinciden con la base, y que el secreto no aparece en las respuestas

## Workflow follow-up

- 4.1, 2026-11-10: Pint sin cambios, backend 172 tests, frontend 63 tests y build, el cambio y las 8 specs válidas con `--strict`.
- 4.2, verificada el 2026-11-10 en el portal con la base `IAM` de desarrollo: el listado trae los 12 clientes de `oauth_clients`, con los conteos de roles iguales a `oauth_client_role` (`iam-admin-sistema` 2, `portal-backend` 2, `maestros-introspector` 1, el resto 0); `buscar=honor` trae los tres clientes de `pago-honorarios`; `iam-admin-portal` muestra `authorization_code` y `refresh_token` con la redirección `http://localhost:4200/api/iam/v1/auth/callback`; `iam-admin-sistema` (pedido con el id en mayúsculas) muestra `client_credentials` y los roles `introspector` y `session-revoker` de `api-auth`; ninguna respuesta contiene `secret` ni un hash bcrypt; `/clientes/no-es-uuid` responde 404 `application/problem+json`.
