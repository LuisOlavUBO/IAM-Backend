## 1. Núcleo

- [x] 1.1 `Domain/SecretoDeCliente`, `ClientePublicoException`, `ClienteParaRotarDto`, `IClientesRepository` + `ClientesRepository` (D2); enlazar en `ClientesServiceProvider`; test unitario de `SecretoDeCliente` y test del repositorio

## 2. Caso de uso y comando

- [x] 2.1 `RotarSecreto` (`iam:make-feature Clientes RotarSecreto --command`): Request (`confirmar` opcional), CommandHandler (D3, D4), Endpoint con `no-store`; ruta `POST clientes/{id}/secreto` (`clientes.secreto.rotar`) con `can:iam:clientes.administrar`
- [x] 2.2 `iam:rotate-secret {cliente}` (D5) reutilizando el CommandHandler
- [x] 2.3 Tests de feature: rotación (secreto nuevo valida, el anterior no), auditoría sin el secreto ni su hash, `no-store`, 403 (mesa de ayuda) y 401, 404, 409 público, 422 de `iam-admin` sin confirmar o con otro nombre, 200 confirmando con `variable_env`
- [x] 2.4 Tests del comando: confirmación rechazada, rotación por nombre y por id con la línea del `.env`, nombre ambiguo e inexistente, cliente público, actor `comando` en la auditoría

## 3. Documentación

- [x] 3.1 OpenAPI (`POST /clientes/{id}/secreto`, 200/401/403/404/409/419/422) y Bruno (`Clientes/Rotar secreto`); `DocsTest` en verde
- [x] 3.2 README: el procedimiento de emergencia pasa a `iam:rotate-secret` (sin `UPDATE` manual) y se lista el comando junto a los demás

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `clientes.service.ts`: `rotarSecreto`; tipos
- [x] 4.2 `detalle-cliente` (D6): «Rotar secreto» con confirmación (y nombre escrito para `iam-admin`), secreto mostrado una vez con «Copiar» y aviso de `variable_env`; oculto en clientes públicos y sin el permiso; si se reutiliza el componente de Usuarios, moverlo a `shared/ui`; tests y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate clientes-rotar-secreto --strict`
- [x] 5.2 En el portal con la base de desarrollo, sobre un cliente de prueba que indique el usuario (no los de `iam-admin`): rotar y comprobar que el secreto nuevo obtiene un token `client_credentials` en API-Auth de desarrollo y el anterior no
