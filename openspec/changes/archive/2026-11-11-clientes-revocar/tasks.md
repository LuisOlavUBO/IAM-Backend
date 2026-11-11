## 1. Núcleo

- [x] 1.1 `ClienteDeIamAdminException`; renombrar `ClienteParaRotarDto` a `ClienteParaEscribirDto` con `revocado` (D2) y ajustar `RotarSecreto`; `IClientesRepository::marcarRevocado` y su test

## 2. Casos de uso

- [x] 2.1 `RevocarCliente` (`iam:make-feature Clientes RevocarCliente --command`): D3; ruta `POST clientes/{id}/revocacion` (`clientes.revocacion`)
- [x] 2.2 `ReactivarCliente`: D3 sin la regla de `iam-admin`; ruta `POST clientes/{id}/reactivacion` (`clientes.reactivacion`)
- [x] 2.3 Tests de feature: revocar y reactivar (con auditoría), idempotencia sin auditoría, 409 de `iam-admin` con entrada `rechazado`, reactivar un cliente de `iam-admin` revocado, 403 (mesa de ayuda), 401 y 404

## 3. Documentación

- [x] 3.1 OpenAPI (dos operaciones, 200/401/403/404/409/419) y Bruno (`Clientes/Revocar cliente`, `Reactivar cliente`); `DocsTest` en verde; README: orden de despliegue con API-Auth

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `clientes.service.ts`: `revocar`, `reactivar`
- [x] 4.2 `detalle-cliente` (D4): «Revocar»/«Reactivar» con confirmación, ocultos sin el permiso y «Revocar» oculto en `iam-admin`; tests

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate clientes-revocar --strict`
- [x] 5.2 En el portal con la base de desarrollo, sobre un cliente de prueba que indique el usuario: revocar, comprobar que `client_credentials` falla en API-Auth de desarrollo (y, si `introspeccion-cliente-revocado` ya está desplegado ahí, que un token previo se introspecta inactivo), reactivar y comprobar que vuelve a obtener tokens
  - Hecho el 2026-11-11 con `satelite-web` (authorization_code, sin client_credentials): con el cliente activo, `GET /api/auth/oauth/authorize` de API-Auth de desarrollo redirige al login (302); revocado, responde 401 `invalid_client`; reactivado, vuelve a 302. Auditoría `cliente.revocacion` y `cliente.reactivacion` con `{revocado}` antes y después. La introspección de un token previo no se probó (no había token del cliente).
