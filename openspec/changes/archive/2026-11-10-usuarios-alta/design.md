## Context

`users` es de API-IAM y ya tiene todo lo necesario: `uuid` (único), `name`, `email` (único; la intercalación CI no distingue mayúsculas), `rut` (índice único filtrado `users_rut_unique`), `password` (bcrypt, `BCRYPT_ROUNDS=12`), `temporary_password_expires_at`, `entra_oid` y `disabled_at`. `iam:install` ya crea personas con `ClaveTemporal` (`Usuarios/Domain`) y `Rut` (`Shared`), y registra `usuario.creacion` con `{nombre, correo, rut, acceso}`. En API-Auth, `User::fromMicrosoft` vincula el login con Microsoft a la persona con el mismo correo (o la crea sin roles si no existe), y el login con clave obliga a cambiar una clave temporal y la rechaza si venció. `usuarios-roles` dejó el molde de los casos de uso de escritura (`CommandHandler`, repositorio de escritura, auditoría en la transacción).

## Goals / Non-Goals

**Goals:**
- Dar de alta personas con acceso Microsoft o con clave temporal desde el portal.
- Que la clave temporal se vea una sola vez y no quede en ningún registro.

**Non-Goals:**
- Editar, dar de baja, reactivar o reiniciar la clave (cambios siguientes).
- Asignar roles en el mismo formulario: el portal lleva al detalle, que ya lo hace.
- Que `iam:install` reutilice este caso de uso (hoy funciona; se unifica si cambia la regla del alta).
- Restringir el dominio del correo.

## Decisions

**D1. `POST /usuarios`** en el grupo `can:iam:usuarios.administrar`, con `{nombre, correo, rut?, acceso}` y respuesta 201 `{data: {uuid, clave_temporal: {clave, vence} | null}}`. El endpoint agrega `Cache-Control: no-store` para que ni el navegador ni un proxy guarden la clave.

**D2. Estructura**:
```
app/Domains/Usuarios/
+-- Features/CrearUsuario/        Endpoint, Request, CommandHandler, Resource
+-- Domain/CorreoEnUsoException.php, RutEnUsoException.php    409
+-- Domain/Dto/NuevaPersonaDto.php                             nombre, correo, rut, acceso (ya normalizados)
+-- Domain/Dto/PersonaCreadaDto.php                            uuid, ClaveTemporal|null
+-- Domain/Interfaces/IPersonasRepository.php                  correoEnUso, rutEnUso, crear
+-- Infrastructure/PersonasRepository.php
```

**D3. Normalización y validación en el Request.** `correo`: `trim` y minúsculas antes de validar (`required|email|max:255`). `rut`: `Rut::normalize` y luego la regla `Rut` (módulo 11). `nombre`: `trim`, `required|max:255`. `acceso`: `in:microsoft,clave`. El `CommandHandler` recibe `NuevaPersonaDto` ya normalizado.

**D4. Unicidad.** El handler pregunta `correoEnUso` y `rutEnUso` antes de insertar (mensajes claros). Como dos altas simultáneas podrían pasar ambas la pregunta, el repositorio además traduce la violación de índice único de SQL Server (2601/2627) en la excepción que corresponda según el índice (`users_email_unique` / `users_rut_unique`).

**D5. Transacción y auditoría.** Inserción en `users` y `IAuditor::registrar('usuario.creacion', 'usuario', $uuid, null, {nombre, correo, rut, acceso})` en la misma transacción. La clave temporal solo existe en memoria del handler y en la respuesta: el handler no la pasa a la auditoría ni la registra en logs.

**D6. Frontend.** Botón «Nueva persona» en `lista-usuarios` (con `iam:usuarios.administrar`); ruta `/usuarios/nueva` (antes de `:uuid`) con la página `nueva-persona`: formulario con nombre, correo, RUT y acceso (Microsoft por omisión). Los errores 422 se muestran junto a su campo y los 409 como mensaje. Con acceso Microsoft, al crear navega al detalle; con clave, muestra la clave temporal en grande con «Copiar», su vencimiento y el aviso de que no se volverá a mostrar, y un botón «Ir a la persona». La clave vive solo en una señal de la página y se pierde al salir.

## Risks / Trade-offs

- [La clave queda en el historial del navegador o en capturas] → Se muestra en la página, no en la URL, y con `no-store`; el aviso pide entregarla por un canal seguro.
- [Alta con Microsoft y correo distinto al de Entra ID] → La persona no quedaría vinculada y API-Auth crearía otra al primer login. El formulario explica que el correo debe ser el de su cuenta Microsoft.
- [Lógica de alta duplicada con `iam:install`] → Aceptado por ahora (Non-Goals); ambas usan `ClaveTemporal`, `Rut` y la misma acción de auditoría.

## Migration Plan

Sin migraciones. Se despliegan backend y frontend; el endpoint es nuevo.
