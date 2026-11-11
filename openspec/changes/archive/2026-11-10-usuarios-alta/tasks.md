## 1. Backend

- [x] 1.1 `CorreoEnUsoException`, `RutEnUsoException`, `NuevaPersonaDto`, `PersonaCreadaDto`, `IPersonasRepository` y `PersonasRepository` (D2, D4, con la traducción de las violaciones de índice único); enlazar en `UsuariosServiceProvider`
- [x] 1.2 `CrearUsuario` (`iam:make-feature --command`): Request con normalización y validación (D3), CommandHandler con unicidad, transacción y auditoría (D5), Endpoint con 201 y `Cache-Control: no-store`; ruta `POST usuarios` (`usuarios.crear`)
- [x] 1.3 Tests de feature: alta Microsoft (normalización, sin clave ni roles), alta con clave (formato, vencimiento, `Hash::check`, `tiene_clave` en el detalle), 422 por campo, 409 correo (sin distinguir mayúsculas) y RUT, 403 y 401, entrada de auditoría sin la clave ni su hash, `no-store`
- [x] 1.4 Test del repositorio: una violación de índice único se traduce en la excepción del índice

## 2. Documentación

- [x] 2.1 OpenAPI (`POST /usuarios`, cuerpo, 201 con `clave_temporal`, 409 con los dos códigos, 403/419/422) y Bruno (`Usuarios/Crear usuario`); `DocsTest` en verde

## 3. Frontend (IAM-Frontend)

- [x] 3.1 `usuarios.service.ts`: `crear`; tipos; ruta `nueva` antes de `:uuid`
- [x] 3.2 Página `nueva-persona` (D6) y botón «Nueva persona» en `lista-usuarios`; tests (validación por campo, 409, alta Microsoft que navega al detalle, alta con clave que la muestra una vez y la copia) y README

## 4. Verificación

- [x] 4.1 Pint, suites completas, `openspec validate usuarios-alta --strict`
- [x] 4.2 En el portal con la base de desarrollo: dar de alta una persona de prueba con clave y otra con Microsoft (datos que el usuario indique), ver la entrada en Auditoría sin la clave y comprobar en el detalle `tiene_clave` y el vencimiento
