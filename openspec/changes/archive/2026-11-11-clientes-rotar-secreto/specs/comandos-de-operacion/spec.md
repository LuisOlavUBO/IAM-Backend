## ADDED Requirements

### Requirement: Rotación de secreto por consola
`iam:rotate-secret {cliente}` SHALL rotar el secreto de un cliente confidencial, indicado por nombre o por id, con la confirmación de ambiente y base de los comandos `iam:*`, y mostrar una sola vez el secreto nuevo y, para los clientes de `iam-admin`, la línea del `.env` lista para copiar. MUST registrar `cliente.secreto.rotacion` con actor `comando` y MUST fallar sin cambiar nada si el cliente no existe, es público o el nombre corresponde a más de un cliente.

#### Scenario: Recuperar el login del portal
- **WHEN** se ejecuta `php artisan iam:rotate-secret iam-admin-portal` y se confirma
- **THEN** el secreto cambia, se muestra una sola vez la línea `UBO_LOGIN_CLIENT_SECRET=...` y hay una entrada `cliente.secreto.rotacion` con actor `comando`

#### Scenario: Confirmación rechazada
- **WHEN** se ejecuta el comando y se responde "no"
- **THEN** el secreto no cambia y no se muestra ninguno

#### Scenario: Nombre ambiguo o inexistente
- **WHEN** el nombre no corresponde a ningún cliente, o a más de uno
- **THEN** el comando falla sin cambiar nada y, si es ambiguo, lista los ids para elegir
