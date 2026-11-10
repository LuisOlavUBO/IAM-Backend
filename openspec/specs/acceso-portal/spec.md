# acceso-portal Specification

## Purpose

Define cómo una persona entra y sale del portal de IAM a través del SSO de API-Auth, sin que el navegador vea nunca un token, y cómo API-IAM decide qué rutas del portal puede usar según sus permisos `iam:*`.

## Requirements

### Requirement: Inicio de sesión por el SSO de IAM
`GET /api/iam/v1/auth/login` SHALL redirigir el navegador al login central de API-Auth con Authorization Code y PKCE `S256`, usando el cliente `authorization-code` de `iam-admin`. API-IAM MUST NOT recibir ni pedir la contraseña de la persona.

#### Scenario: Navegador sin sesión
- **WHEN** el navegador abre `GET /api/iam/v1/auth/login?redirect=/usuarios`
- **THEN** recibe un 302 hacia `/api/auth/oauth/authorize` de IAM con `client_id` del portal, `redirect_uri` igual a `<APP_URL>/api/iam/v1/auth/callback`, `state` y `code_challenge` con `code_challenge_method=S256`

### Requirement: Fin del inicio de sesión
`GET /api/iam/v1/auth/callback` SHALL canjear el código en el servidor, abrir la sesión y redirigir a la ruta pedida al iniciar, solo si es de la misma aplicación. El navegador MUST recibir solo la cookie de sesión, nunca el token.

#### Scenario: Login exitoso
- **GIVEN** un login iniciado desde ese navegador con `redirect=/usuarios`
- **WHEN** IAM vuelve a `/api/iam/v1/auth/callback` con un `code` y el `state` correcto
- **THEN** la respuesta es un 302 a `/usuarios` con la cookie de sesión, y ni la respuesta ni la cookie contienen el access token ni el refresh token

#### Scenario: Destino externo
- **WHEN** el login se inicia con `redirect=https://otro-sitio.cl/`
- **THEN** al terminar el login la persona vuelve a la página inicial del portal, no al sitio externo

### Requirement: Errores del inicio de sesión
Si el callback falla (un `state` que no corresponde, un código rechazado o una persona sin roles en `iam-admin`), API-IAM SHALL redirigir a la página inicial del portal con el parámetro `error_login` y un código estable, sin abrir sesión.

#### Scenario: State que no corresponde
- **WHEN** llega `GET /api/iam/v1/auth/callback` con un `state` que no inició ese navegador
- **THEN** la respuesta es un 302 a la página inicial con `error_login=LOGIN_INVALIDO` y no hay sesión

#### Scenario: Persona sin acceso al portal
- **WHEN** el token entregado por IAM no trae ningún rol de `iam-admin`
- **THEN** la respuesta es un 302 a la página inicial con `error_login=SIN_ACCESO` y no hay sesión

### Requirement: Cierre de sesión
`POST /api/iam/v1/auth/logout` SHALL exigir el token CSRF, revocar en IAM el token de la sesión, cerrar la sesión local y redirigir al logout central de IAM, que vuelve al portal.

#### Scenario: Logout desde el portal
- **GIVEN** una persona con sesión
- **WHEN** el navegador envía `POST /api/iam/v1/auth/logout` con el token CSRF
- **THEN** la sesión local se cierra y la respuesta redirige a `/api/auth/logout` de IAM con el `client_id` del portal y `return` del mismo origen que la `redirect_uri`

#### Scenario: Logout sin token CSRF
- **WHEN** se envía `POST /api/iam/v1/auth/logout` sin token CSRF
- **THEN** la respuesta es 419 y la sesión sigue abierta

### Requirement: Persona con sesión
`GET /api/iam/v1/me` SHALL responder 200 con `data` que contiene `uuid`, `nombre`, `correo`, `roles` y `permisos` de la persona con sesión, y `csrf` con el token CSRF vigente. `nombre` y `correo` MUST salir de `users` de la base `IAM`.

#### Scenario: Administrador con sesión
- **GIVEN** una persona con sesión y el rol `administrador` de `iam-admin`
- **WHEN** Angular llama a `GET /api/iam/v1/me`
- **THEN** la respuesta es 200 con su `uuid` en minúsculas, su `nombre` y `correo`, `roles` igual a `["administrador"]`, los siete permisos `iam:*` en `permisos` y un `csrf` no vacío

### Requirement: Rutas del portal exigen sesión
Toda ruta del portal, salvo las de `auth`, MUST responder 401 en RFC 9457 con `error` igual a `NO_AUTENTICADO` cuando no hay sesión, sin redirigir.

#### Scenario: Me sin sesión
- **WHEN** se llama a `GET /api/iam/v1/me` sin cookie de sesión
- **THEN** la respuesta es 401 `application/problem+json` con `error` igual a `NO_AUTENTICADO`

### Requirement: Autorización por permisos
Cada ruta del portal que exige un permiso `iam:*` MUST responder 403 en RFC 9457 cuando el token de la sesión no lo trae. Los permisos SHALL ser los del token de la persona en `iam-admin`.

#### Scenario: Mesa de ayuda en una ruta de clientes
- **GIVEN** una persona con sesión y los permisos `iam:usuarios.leer`, `iam:usuarios.administrar` e `iam:aplicaciones.leer`
- **WHEN** llama a una ruta que exige `iam:clientes.leer`
- **THEN** la respuesta es 403 `application/problem+json`

### Requirement: Protección CSRF en el portal
Las rutas del portal que cambian datos (`POST`, `PUT`, `PATCH`, `DELETE`) MUST rechazar con 419 las peticiones sin un token CSRF válido. El token SHALL aceptarse en el header `X-XSRF-TOKEN` (cookie `XSRF-TOKEN`) o en `X-CSRF-TOKEN`.

#### Scenario: Petición de Angular
- **GIVEN** una persona con sesión
- **WHEN** Angular envía un `POST` a una ruta del portal con el header `X-XSRF-TOKEN` tomado de la cookie `XSRF-TOKEN`
- **THEN** la petición pasa la verificación CSRF

### Requirement: Pérdida de acceso durante la sesión
Una sesión abierta MUST terminar cuando IAM informa que su token ya no está activo (persona dada de baja o sesiones revocadas), en a lo más el intervalo de revalidación configurado (300 segundos por defecto), y cuando la persona deja de tener roles en `iam-admin` al renovarse el token.

#### Scenario: Persona dada de baja con la sesión abierta
- **GIVEN** una persona con sesión en el portal
- **WHEN** es dada de baja en IAM y vence el intervalo de revalidación
- **THEN** su siguiente llamada a `GET /api/iam/v1/me` responde 401
