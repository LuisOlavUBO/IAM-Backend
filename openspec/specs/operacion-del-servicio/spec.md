# operacion-del-servicio Specification

## Purpose
Define lo que API-IAM expone para operarse detrás del proxy inverso de la UBO: el prefijo de sus rutas, el endpoint de salud que usan los healthchecks y el formato de los errores de su API.

## Requirements

### Requirement: Prefijo de rutas
Toda ruta HTTP de API-IAM SHALL publicarse bajo `/api/iam/`, y las de su versión 1 bajo `/api/iam/v1/`, sin depender de que el proxy reescriba la ruta.

#### Scenario: Ruta detrás del proxy
- **WHEN** el proxy de `apidev.ubo.cl` reenvía `GET /api/iam/v1/salud` sin cambiar la ruta
- **THEN** API-IAM atiende la petición en esa misma ruta

### Requirement: Salud con la base disponible
`GET /api/iam/v1/salud` SHALL responder sin autenticación. Si la base `IAM` responde, MUST devolver 200 con `{"estado":"OK","base_datos":"OK"}`.

#### Scenario: Base disponible
- **WHEN** se llama a `GET /api/iam/v1/salud` sin credenciales y la base responde
- **THEN** la respuesta es 200 con `{"estado":"OK","base_datos":"OK"}`

### Requirement: Salud con la base caída
Si la base `IAM` no responde, `GET /api/iam/v1/salud` MUST devolver 503 con `{"estado":"ERROR","base_datos":"NO_DISPONIBLE"}`, sin exponer el error.

#### Scenario: Base caída
- **WHEN** se llama a `GET /api/iam/v1/salud` y la conexión a la base falla
- **THEN** la respuesta es 503 con `{"estado":"ERROR","base_datos":"NO_DISPONIBLE"}` y no incluye el mensaje de la excepción

### Requirement: Errores en Problem Details
Los errores de las rutas bajo `/api/iam/` SHALL responder con `Content-Type: application/problem+json` (RFC 9457) y un código estable en el campo `error`.

#### Scenario: Ruta inexistente
- **WHEN** se llama a `GET /api/iam/v1/no-existe`
- **THEN** la respuesta es 404 `application/problem+json` con `error` igual a `RUTA_NO_ENCONTRADA`

#### Scenario: Método no permitido
- **WHEN** se llama a `POST /api/iam/v1/salud`
- **THEN** la respuesta es 405 `application/problem+json` con `error` igual a `METODO_NO_PERMITIDO`

#### Scenario: Error inesperado sin detalles internos
- **WHEN** una ruta bajo `/api/iam/` lanza una excepción no controlada en producción
- **THEN** la respuesta es 500 `application/problem+json` con `error` igual a `ERROR_INTERNO` y no incluye la traza ni el mensaje interno

### Requirement: Identificador de traza
Cada respuesta bajo `/api/iam/` SHALL incluir el header `X-Trace-Id`, que continúa la traza W3C (`traceparent`) recibida o inicia una nueva.

#### Scenario: Petición con traceparent
- **WHEN** llega `GET /api/iam/v1/salud` con un header `traceparent` válido
- **THEN** la respuesta incluye `X-Trace-Id` con el `trace-id` de ese `traceparent`

#### Scenario: Petición sin traceparent
- **WHEN** llega `GET /api/iam/v1/salud` sin `traceparent`
- **THEN** la respuesta incluye un `X-Trace-Id` nuevo
