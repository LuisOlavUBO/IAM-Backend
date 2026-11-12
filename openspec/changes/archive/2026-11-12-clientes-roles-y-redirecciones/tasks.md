## 1. Núcleo

- [x] 1.1 `ClienteParaEscribirDto` con `tipo` y `redirecciones` (D2); `Domain/ReglasDeClientes` y las excepciones (D3) con test unitario
- [x] 1.2 `IClientesRepository`: `rol`, `tieneRol`, `asignarRol`, `quitarRol`, `cambiarRedirecciones`, con test del repositorio

## 2. Casos de uso

- [x] 2.1 `AsignarRolACliente` (`iam:make-feature --command`); ruta `POST clientes/{id}/roles` (`clientes.roles.asignar`)
- [x] 2.2 `QuitarRolACliente`; ruta `DELETE clientes/{id}/roles/{aplicacion}/{rol}` (`clientes.roles.quitar`)
- [x] 2.3 `CambiarRedirecciones`; ruta `PUT clientes/{id}/redirecciones` (`clientes.redirecciones`)
- [x] 2.4 Tests de feature por requisito: asignar (201, 200, 422, rol de otra aplicación), quitar (200, 404), rechazos auditados (`CLIENTE_SIN_ROLES`, `ROL_DE_IAM_ADMIN`, `CLIENTE_SIN_REDIRECCIONES`, `CLIENTE_DE_IAM_ADMIN` en las tres acciones), redirecciones (reemplazo, sin cambios, vacías, insegura), 403 y 401

## 3. Documentación

- [x] 3.1 OpenAPI (tres operaciones, respuestas y códigos nuevos en la tabla de errores) y Bruno (`Clientes/Asignar rol`, `Quitar rol`, `Cambiar redirecciones`); `DocsTest` en verde

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `clientes.service.ts`: `asignarRol`, `quitarRol`, `cambiarRedirecciones`
- [x] 4.2 `detalle-cliente` (D5): roles en clientes de servicio y edición de redirecciones en los de login, ocultos sin el permiso y en `iam-admin`; tests y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate clientes-roles-y-redirecciones --strict`
- [x] 5.2 En el portal con la base de desarrollo, sobre clientes de prueba que indique el usuario: asignar un rol a un cliente de servicio y comprobar que su token `client_credentials` en API-Auth de desarrollo trae los scopes del rol; quitarlo; cambiar las redirecciones de un cliente de login; ver las entradas en Auditoría
