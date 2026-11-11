## ADDED Requirements

### Requirement: Crear un cliente
`POST /api/iam/v1/clientes` con `nombre`, `aplicacion`, `tipo` y `redirecciones` SHALL crear un cliente confidencial de esa aplicación y responder 201 con `data.id` y `data.secreto` (40 caracteres) una sola vez, con `Cache-Control: no-store`. Las concesiones MUST ser `authorization_code` y `refresh_token` para `authorization-code`, y `client_credentials` para `client-credentials`. MUST exigir sesión, `iam:clientes.administrar` y CSRF.

#### Scenario: Cliente para el login de un sistema
- **WHEN** un administrador pide `POST /api/iam/v1/clientes` con `{"nombre": "satelite-web", "aplicacion": "satelite", "tipo": "authorization-code", "redirecciones": ["https://satelite.ubo.cl/auth/callback"]}`
- **THEN** responde 201 con el id y el secreto, y el detalle del cliente muestra la aplicación `satelite`, las concesiones `authorization_code` y `refresh_token`, la redirección, `confidencial` `true` y ningún rol

#### Scenario: Cliente de servicio
- **WHEN** se crea un cliente `client-credentials` sin redirecciones
- **THEN** responde 201 y el cliente tiene la concesión `client_credentials` y ninguna redirección

#### Scenario: Sin el permiso
- **GIVEN** la mesa de ayuda, sin `iam:clientes.administrar`
- **WHEN** pide crear un cliente
- **THEN** recibe 403 `ACCESO_DENEGADO` y no se crea nada

### Requirement: Datos válidos del cliente
El alta MUST responder 422 `VALIDACION`, con el mensaje en su campo y sin crear nada, cuando: falta el nombre o pasa de 100 caracteres; la aplicación no existe o es `iam-admin`; el tipo no es uno de los dos; un cliente `authorization-code` no trae redirecciones; un cliente `client-credentials` trae redirecciones; o una redirección no es una URL absoluta `https` (o `http` en `localhost`) sin fragmento.

#### Scenario: Redirección insegura
- **WHEN** se crea un cliente `authorization-code` con la redirección `http://satelite.ubo.cl/callback`
- **THEN** responde 422 con el mensaje en `errores` de esa redirección

#### Scenario: Cliente de iam-admin
- **WHEN** se pide crear un cliente de la aplicación `iam-admin`
- **THEN** responde 422 con el mensaje en `errores.aplicacion`

### Requirement: Nombre único del cliente
El alta MUST rechazar un nombre que ya usa otro cliente (sin distinguir mayúsculas) con 409 `CLIENTE_EXISTENTE`, sin crear nada.

#### Scenario: Nombre repetido
- **GIVEN** el cliente `satelite-web`
- **WHEN** se pide crear otro cliente llamado `Satelite-Web`
- **THEN** responde 409 `CLIENTE_EXISTENTE`

### Requirement: Creación auditada sin el secreto
Cada creación SHALL registrar `cliente.creacion` sobre la entidad `cliente` con su id y `despues` igual a `{nombre, aplicacion, tipo, redirecciones}`, en la misma transacción. El secreto y su hash MUST NOT quedar en la auditoría, en logs ni en otra respuesta.

#### Scenario: Entrada de la creación
- **WHEN** un administrador crea `satelite-web`
- **THEN** hay una entrada `cliente.creacion` con el id del cliente, el actor y `despues` con nombre, aplicación, tipo y redirecciones, y ninguna fila de `iam_auditoria` contiene el secreto ni su hash
