# Proposal

## Why

Hoy una persona entra a IAM solo de dos formas: `iam:install` crea al primer administrador, o la persona inicia sesión con Microsoft y API-Auth la crea sin roles. No hay forma de dar de alta desde el portal a alguien que entra con clave (personal externo, cuentas sin Microsoft) ni de preparar una cuenta con su RUT antes de que la persona llegue. La mesa de ayuda necesita hacerlo, y es el paso que falta antes de asignarle roles.

## What Changes

- `POST /api/iam/v1/usuarios` con `{nombre, correo, rut?, acceso}`, donde `acceso` es `microsoft` o `clave`:
  - Crea la persona con un `uuid` nuevo, el correo en minúsculas y el RUT normalizado y validado (módulo 11).
  - Con `clave`, genera una clave temporal (`ClaveTemporal`, la de `iam:install`) que vence según `IAM_CLAVE_TEMPORAL_HORAS`; API-Auth obliga a cambiarla en el primer ingreso. La clave va en la respuesta **una sola vez** (`Cache-Control: no-store`) y nunca en logs ni en la auditoría.
  - Con `microsoft`, la crea sin clave: API-Auth la vincula por correo en su primer login con Microsoft.
  - Responde 201 con el `uuid` (y la clave temporal si corresponde). Correo o RUT ya usados → 409 `CORREO_EN_USO` / `RUT_EN_USO`.
  - Registra `usuario.creacion` en la auditoría con nombre, correo, RUT y tipo de acceso, en la misma transacción.
- Exige sesión, `iam:usuarios.administrar` y CSRF.
- Caso de uso `CrearUsuario` (`iam:make-feature --command`) con su repositorio de escritura.
- OpenAPI y Bruno.
- Frontend (IAM-Frontend): «Nueva persona» en el listado de usuarios (con `iam:usuarios.administrar`), formulario en `/usuarios/nueva` y, al crearla con clave, una pantalla que muestra la clave temporal una sola vez, con copiar y su vencimiento; luego va al detalle para asignar roles.
- Fuera de este cambio: editar los datos de la persona, baja y reactivación, reiniciar clave, asignar roles en el mismo formulario y que `iam:install` use este caso de uso.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `usuarios`: se agrega el alta de personas.

## Impact

- Backend: `app/Domains/Usuarios/` (Feature `CrearUsuario`, excepciones `CorreoEnUso` y `RutEnUso`, repositorio de escritura), `routes/v1.php`, tests, OpenAPI y Bruno.
- Frontend: `dominios/usuarios/` (servicio, ruta y página `nueva-persona`, botón en el listado) y sus tests.
- Datos: inserta en `users` (dueño: API-IAM, columnas que ya existen: `uuid`, `name`, `email`, `rut`, `password`, `temporary_password_expires_at`) e `iam_auditoria`.
- API-Auth: sin cambios; ya obliga a cambiar la clave temporal y vincula por correo el login con Microsoft. Sin migraciones ni dependencias nuevas.
