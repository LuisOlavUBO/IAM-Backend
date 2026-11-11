## 1. Núcleo

- [x] 1.1 `Domain/Redireccion` (D3) con su regla de validación y test unitario; `InstallCommand` la usa en vez de `isValidRedirect`
- [x] 1.2 `NuevoCliente` sin SQL (D2): `NuevoClienteDto`, `IClientesRepository::crear` y `nombreEnUso` en `ClientesRepository`; `iam:install` usa el repositorio; `InstallCommandTest` y `NuevoClienteTest` en verde (ajustar solo cómo se construye `NuevoCliente`)
- [x] 1.3 `ClienteExistenteException` (409 `CLIENTE_EXISTENTE`)

## 2. Caso de uso

- [x] 2.1 `CrearCliente` (`iam:make-feature Clientes CrearCliente --command`): Request (D4), CommandHandler (D5, D6), Endpoint 201 con `no-store`; ruta `POST clientes` (`clientes.crear`)
- [x] 2.2 Tests de feature: los dos tipos (concesiones, provider, redirecciones, confidencial), secreto válido con `Hash::check`, 422 por campo (nombre, aplicación inexistente y `iam-admin`, tipo, redirecciones faltantes, sobrantes e inseguras), 409 nombre repetido sin distinguir mayúsculas, 403 y 401, auditoría sin el secreto ni su hash, `no-store`

## 3. Documentación

- [x] 3.1 OpenAPI (`POST /clientes`, cuerpo, 201, 403/409/419/422) y Bruno (`Clientes/Crear cliente`); `DocsTest` en verde

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `clientes.service.ts`: `crear` y lista de aplicaciones; tipos; ruta `nuevo` antes de `:id`
- [x] 4.2 Página `nuevo-cliente` (D7) y botón «Nuevo cliente» en `lista-clientes`; tests (validación por campo, 409, redirecciones solo con `authorization-code`, secreto mostrado una vez) y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate clientes-crear --strict`
- [x] 5.2 En el portal con la base de desarrollo, con datos que indique el usuario: crear un cliente `client-credentials` y comprobar que obtiene un token en API-Auth de desarrollo con su secreto (sin scopes, por no tener roles), y ver la entrada en Auditoría sin el secreto
