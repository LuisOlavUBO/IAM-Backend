## ADDED Requirements

### Requirement: Impacto de un cambio en el catálogo
El `impacto` SHALL describir a quién afecta el elemento: para una aplicación, la cantidad de `permisos`, `roles` y `clientes` que tiene; para un permiso, los `roles` que lo dan (como `aplicacion/rol`) y la cantidad de `personas` y `clientes` distintos que lo reciben por esos roles; para un rol, la cantidad de `personas` y `clientes` que lo tienen y sus `permisos`.

#### Scenario: Impacto de un rol
- **GIVEN** el rol `pago-honorarios/revisor`, que tienen 2 personas y ningún cliente, y que da `honorarios:leer`
- **WHEN** se pide borrarlo
- **THEN** responde 409 `EN_USO` con `impacto` `{"personas": 2, "clientes": 0, "permisos": ["honorarios:leer"]}`

### Requirement: Borrar lo que no está en uso
`DELETE` de una aplicación, un permiso o un rol SHALL borrarlo si no está en uso y registrar `aplicacion.borrado`, `permiso.borrado` o `rol.borrado` con su nombre y descripción en `antes`, en la misma transacción, y responder 200. En uso MUST responder 409 `EN_USO` con `impacto`, sin borrar nada. Al borrar un rol se quitan también los permisos que daba. MUST exigir sesión, `iam:aplicaciones.administrar` y CSRF.

#### Scenario: Rol creado por error
- **GIVEN** el rol `biblioteca/bibliotecaro`, sin personas ni clientes, que da un permiso
- **WHEN** se pide borrarlo
- **THEN** responde 200, el rol ya no existe, el permiso sigue existiendo y hay una entrada `rol.borrado`

#### Scenario: Permiso en uso
- **GIVEN** un permiso que da algún rol
- **WHEN** se pide borrarlo
- **THEN** responde 409 `EN_USO` con los roles que lo dan y el permiso no se borra

### Requirement: Qué está en uso
Un permiso SHALL estar en uso si algún rol lo da; un rol, si alguna persona o cliente lo tiene (los permisos que da no cuentan); una aplicación, si tiene permisos, roles o clientes.

#### Scenario: Aplicación con un cliente
- **GIVEN** una aplicación sin permisos ni roles, con un cliente
- **WHEN** se pide borrarla
- **THEN** responde 409 `EN_USO` con `impacto` `{"permisos": 0, "roles": 0, "clientes": 1}`

### Requirement: Catálogo de iam-admin sin borrados
Borrar la aplicación `iam-admin`, sus permisos o sus roles MUST responder 409 `CATALOGO_DE_IAM_ADMIN` sin cambiar nada, y el intento SHALL quedar en la auditoría como `rechazado`.

#### Scenario: Borrar un rol de iam-admin
- **WHEN** se intenta borrar `iam-admin/mesa-de-ayuda`
- **THEN** responde 409 `CATALOGO_DE_IAM_ADMIN` y hay una entrada `rol.borrado` con `resultado` `rechazado`
