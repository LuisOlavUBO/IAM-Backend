## ADDED Requirements

### Requirement: Permiso para administrar el catálogo
Crear y editar aplicaciones, permisos y roles, y cambiar los permisos de un rol, SHALL exigir la sesión del portal, `iam:aplicaciones.administrar` y el token CSRF. Sin el permiso MUST responder 403 `ACCESO_DENEGADO`, y una aplicación, permiso o rol inexistente en la ruta, 404 (`APLICACION_NO_ENCONTRADA`, `PERMISO_NO_ENCONTRADO`, `ROL_NO_ENCONTRADO`).

#### Scenario: Mesa de ayuda
- **GIVEN** la mesa de ayuda, con `iam:aplicaciones.leer` pero sin `iam:aplicaciones.administrar`
- **WHEN** pide crear una aplicación
- **THEN** recibe 403 `ACCESO_DENEGADO` y no se crea nada

### Requirement: Crear y describir aplicaciones
`POST /api/iam/v1/aplicaciones` con `nombre` y `descripcion` opcional SHALL crear la aplicación y responder 201; `PATCH /api/iam/v1/aplicaciones/{nombre}` con `descripcion` SHALL cambiar su descripción y responder 200. El nombre MUST ser un slug en minúsculas (`^[a-z0-9]+(-[a-z0-9]+)*$`, hasta 100 caracteres; si no, 422) y no repetirse (409 `APLICACION_EXISTENTE`).

#### Scenario: Registrar un sistema nuevo
- **WHEN** un administrador pide `POST /api/iam/v1/aplicaciones` con `{"nombre": "biblioteca", "descripcion": "Sistema de biblioteca"}`
- **THEN** responde 201, `GET /api/iam/v1/aplicaciones/biblioteca` la muestra sin permisos ni roles y hay una entrada `aplicacion.creacion`

#### Scenario: Nombre repetido
- **WHEN** se pide crear `Biblioteca` y ya existe `biblioteca`
- **THEN** responde 422 por el formato (mayúscula) y, con `biblioteca`, 409 `APLICACION_EXISTENTE`

### Requirement: Crear y describir permisos
`POST /api/iam/v1/aplicaciones/{nombre}/permisos` con `nombre` y `descripcion` SHALL crear un permiso de esa aplicación y responder 201; `PATCH /api/iam/v1/aplicaciones/{nombre}/permisos/{permiso}` con `descripcion` SHALL cambiar su descripción. El nombre MUST tener solo minúsculas, dígitos y `:._-`, empezar con letra o dígito, hasta 100 caracteres (si no, 422), y ser único en todo IAM (409 `PERMISO_EXISTENTE`). La descripción es obligatoria.

#### Scenario: Permiso de la biblioteca
- **WHEN** se crea `biblioteca:prestamos.leer` con descripción «Ver préstamos» en `biblioteca`
- **THEN** responde 201, aparece en los permisos de `biblioteca` y hay una entrada `permiso.creacion`

### Requirement: Crear y describir roles
`POST /api/iam/v1/aplicaciones/{nombre}/roles` con `nombre` y `descripcion` opcional SHALL crear un rol de esa aplicación, sin permisos, y responder 201; `PATCH /api/iam/v1/aplicaciones/{nombre}/roles/{rol}` con `descripcion` SHALL cambiar su descripción. El nombre MUST tener letras, dígitos, espacios o `._-`, sin `/`, hasta 100 caracteres (si no, 422), y ser único dentro de la aplicación sin distinguir mayúsculas (409 `ROL_EXISTENTE`).

#### Scenario: Rol de la biblioteca
- **WHEN** se crea el rol `bibliotecario` en `biblioteca`
- **THEN** responde 201, aparece en los roles de `biblioteca` sin permisos y hay una entrada `rol.creacion` con `nombre` `biblioteca/bibliotecario`

### Requirement: Permisos que da un rol
`PUT /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/permisos` con `permisos` (lista de nombres, de cualquier aplicación) SHALL reemplazar los permisos que da el rol y responder 200. Cada permiso agregado SHALL registrar `rol.permiso.asignacion` y cada quitado `rol.permiso.retiro`, con `{"permiso": "<nombre>"}`, en la misma transacción. Un permiso inexistente MUST responder 422 sin cambiar nada. Los cambios rigen en el siguiente token de quienes tienen el rol.

#### Scenario: Dar permisos a un rol
- **GIVEN** el rol `bibliotecario` sin permisos
- **WHEN** se piden sus permisos `["biblioteca:prestamos.leer", "geografia:leer"]`
- **THEN** responde 200, el detalle de `biblioteca` muestra el rol con esos dos permisos y hay dos entradas `rol.permiso.asignacion`

#### Scenario: Quitar un permiso
- **WHEN** se piden los permisos `["biblioteca:prestamos.leer"]` para ese rol
- **THEN** responde 200, el rol da solo ese permiso y hay una entrada `rol.permiso.retiro` con `geografia:leer`

### Requirement: Catálogo de iam-admin protegido
Crear permisos o roles en `iam-admin`, editar la aplicación `iam-admin`, sus permisos o sus roles, cambiar los permisos de un rol de `iam-admin`, y agregar un permiso de `iam-admin` a un rol de otra aplicación MUST responder 409 `CATALOGO_DE_IAM_ADMIN` sin cambiar nada, y el intento SHALL quedar en la auditoría como `rechazado`.

#### Scenario: Ampliar la mesa de ayuda
- **WHEN** un administrador intenta agregar `iam:clientes.administrar` a los permisos de `iam-admin/mesa-de-ayuda`
- **THEN** responde 409 `CATALOGO_DE_IAM_ADMIN`, el rol no cambia y hay una entrada con `resultado` `rechazado`

#### Scenario: Permiso iam en otra aplicación
- **WHEN** se intenta dar `iam:usuarios.administrar` a un rol de `biblioteca`
- **THEN** responde 409 `CATALOGO_DE_IAM_ADMIN`
