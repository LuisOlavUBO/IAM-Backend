## Purpose

Define el registro de las acciones de administración de IAM: quién hizo qué, sobre qué entidad, con qué valores antes y después, y si se aplicó o se rechazó. Lo escriben el portal y los comandos, y nadie lo modifica.

## ADDED Requirements

### Requirement: Registro de cada acción aplicada
Cada acción de administración aplicada SHALL dejar una entrada en la auditoría con la fecha, el tipo de actor (`persona` o `comando`), el `uuid` de la persona cuando corresponde, un código de acción estable, el tipo y el identificador de la entidad, los valores anteriores y nuevos, `resultado` igual a `ok` y el `trace_id` de la petición o del comando.

#### Scenario: Acción de un comando
- **WHEN** `iam:grant-admin ana@ubo.cl` asigna el rol de administrador
- **THEN** existe una entrada con actor `comando`, sin `uuid` de actor, la acción `usuario.rol.asignacion.emergencia`, la entidad `usuario` con el `uuid` de Ana, el rol asignado en los valores nuevos y `resultado` igual a `ok`

### Requirement: Registro en la misma transacción
La entrada de una acción aplicada MUST guardarse en la misma transacción que el cambio: si el cambio no se guarda, la entrada tampoco, y si la entrada no se puede guardar, el cambio no se aplica.

#### Scenario: Falla al guardar el cambio
- **WHEN** una acción falla después de escribir su entrada de auditoría
- **THEN** ni el cambio ni la entrada quedan en la base

### Requirement: Registro de intentos rechazados
Una acción rechazada por una regla de negocio SHALL dejar una entrada con `resultado` igual a `rechazado` y el código del motivo, aunque no haya cambio que guardar.

#### Scenario: Intento rechazado
- **WHEN** una acción de administración se rechaza con el código `ESCALAMIENTO_DE_PRIVILEGIOS`
- **THEN** existe una entrada con `resultado` igual a `rechazado` y `motivo` igual a `ESCALAMIENTO_DE_PRIVILEGIOS`

### Requirement: Sin secretos en la auditoría
Los valores anteriores y nuevos MUST incluir solo los campos que cada acción declara como auditables, y MUST NOT contener contraseñas, secretos de clientes ni sus hashes. La generación de un secreto SHALL registrarse solo como acción.

#### Scenario: Instalación
- **WHEN** `iam:install` crea los clientes y un administrador con contraseña temporal
- **THEN** ninguna entrada de la auditoría contiene los secretos, la contraseña temporal ni sus hashes

### Requirement: Auditoría inalterable
API-IAM MUST NOT modificar ni borrar entradas de la auditoría, y sus entradas MUST NOT depender de la existencia de la entidad que describen.

#### Scenario: Entidad borrada
- **GIVEN** una entrada de auditoría sobre un rol
- **WHEN** ese rol se borra
- **THEN** la entrada sigue existiendo sin cambios
