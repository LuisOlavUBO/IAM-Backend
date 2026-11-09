# Proposal

## Why

El portal ya permite entrar y muestra la sección Usuarios, pero vacía: hoy la mesa de ayuda y los administradores no pueden buscar a una persona ni ver qué acceso tiene sin consultar la base a mano. Una consulta de solo lectura es la base que necesitan las próximas acciones sobre usuarios (alta, edición, baja, roles y reinicio de clave) y permite construir la vertical completa, de API-IAM al portal, con poco riesgo: no escribe en ninguna tabla.

## What Changes

- Nuevo `GET /api/iam/v1/usuarios`: listado paginado de personas con búsqueda por nombre, correo o RUT y filtro por estado (activas o dadas de baja), ordenado por nombre.
- Nuevo `GET /api/iam/v1/usuarios/{uuid}`: detalle de una persona con sus datos de cuenta (RUT, forma de acceso, estado, vencimiento de la clave temporal) y sus roles agrupados por aplicación.
- Ambas rutas viven en el dominio Usuarios, en el grupo web con la sesión del portal, y exigen el permiso `iam:usuarios.leer` (403 sin él, 401 sin sesión).
- Una persona inexistente responde 404 con el código estable `USUARIO_NO_ENCONTRADO`; parámetros de búsqueda o paginación inválidos responden 422 `VALIDACION`.
- La respuesta nunca incluye la contraseña, su hash, el token de recordar sesión, el `entra_oid` ni el id interno: la persona se identifica por su `uuid` en minúsculas.
- OpenAPI y Bruno documentan los dos endpoints.
- Fuera de alcance (cambios siguientes): alta, edición, baja y reactivación, asignación de roles, reinicio de clave y revocación de sesiones.

## Capabilities

### New Capabilities
- `usuarios`: consulta de las personas de IAM desde el portal (listado con búsqueda y detalle con roles); los cambios siguientes agregan aquí la administración de usuarios.

### Modified Capabilities
<!-- Ninguna: la autorización por permisos iam:* y el formato de errores ya están definidos en acceso-portal. -->

## Impact

- **Código**: `app/Domains/Usuarios` (controladores de listado y detalle, y la excepción `UsuarioNoEncontrado`) y `app/Domains/Usuarios/routes/v1.php`. Solo lectura sobre `users`, `role_user`, `roles` y `applications` con el query builder, como `MeController`.
- **API**: dos rutas nuevas bajo `/api/iam/v1/usuarios`; ningún cambio en las existentes.
- **Documentación**: `docs/openapi/openapi.yaml` y carpeta `Usuarios` en `docs/bruno/`.
- **Datos**: sin migraciones; ninguna escritura en la base `IAM`.
- **API-Auth**: no depende de ningún cambio de API-Auth.
- **Portal**: el frontend Angular (`IAM/frontend`) consume estas rutas en la sección Usuarios.
- **Dependencias**: ninguna nueva.
