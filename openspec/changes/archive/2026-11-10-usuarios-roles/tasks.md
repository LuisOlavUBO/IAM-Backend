## 1. Núcleo

- [x] 1.1 `Domain/ReglasDeRoles` y las excepciones `EscalamientoDePrivilegios`, `AccionSobreSiMismo`, `UltimoAdministrador`, `RolNoAsignado` y `RevocacionFallida` (D3); test unitario de las reglas
- [x] 1.2 `IRolesDeUsuarioRepository` y `RolesDeUsuarioRepository` (D4); `IRevocadorDeSesiones` y `ApiAuthRevocadorDeSesiones` con `ServiceTokenProvider` y `Http` (D5); enlazarlos en `UsuariosServiceProvider`; test del revocador con `Http::fake` (204, 404, 500, sin token, 401 que descarta el token)

## 2. Casos de uso

- [x] 2.1 `AsignarRol` (`iam:make-feature --command`): Request (`aplicacion`, `rol`), CommandHandler con reglas, transacción y auditoría; ruta `POST usuarios/{uuid}/roles` (`usuarios.roles.asignar`)
- [x] 2.2 `QuitarRol`: CommandHandler con reglas, transacción, auditoría y revocación después del commit; ruta `DELETE usuarios/{uuid}/roles/{aplicacion}/{rol}` (`usuarios.roles.quitar`)
- [x] 2.3 `RevocarSesiones`: ruta `POST usuarios/{uuid}/sesiones/revocar` (`usuarios.sesiones.revocar`)
- [x] 2.4 Tests de feature por requisito: permiso (403, 401, 419 con CSRF real), 404 de persona, asignar (201, 200, 422), quitar (200 con y sin revocación, 404 `ROL_NO_ASIGNADO`), reintento (204, 502), protecciones (escalamiento al asignar y quitar, uno mismo, último administrador) con su entrada `rechazado`, y que un rechazo no cambia `role_user`

## 3. Documentación

- [x] 3.1 OpenAPI (tres operaciones, cuerpos, 201/200/204/403/404/409/419/422/502 y los códigos nuevos en la tabla de errores) y Bruno (`Usuarios/Asignar rol`, `Quitar rol`, `Revocar sesiones`); `DocsTest` en verde

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `usuarios.service.ts`: `asignarRol`, `quitarRol`, `revocarSesiones`; tipos
- [x] 4.2 `detalle-usuario`: con `iam:usuarios.administrar`, formulario «Asignar rol» (aplicación y rol), «Quitar» por rol con confirmación, mensajes de error por código y aviso con «Reintentar» si la revocación falla; tests y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate usuarios-roles --strict`
- [x] 5.2 En el portal con la base de desarrollo: asignar y quitar un rol a una persona de prueba, ver las entradas en Auditoría, comprobar el rechazo al intentar cambiar los propios roles y la revocación en API-Auth

## Workflow follow-up

- 5.1, 2026-11-10: Pint sin cambios, backend 227 tests, frontend 80 tests y build, `openspec validate usuarios-roles --strict` válido.
- 5.2, completada el 2026-11-10 con `luis.olavarria3@ubo.cl` (elegido por el usuario, sin roles): desde el formulario del portal se asignó `pago-honorarios/Docente` («Se asignó pago-honorarios/Docente.», el rol aparece tras recargar) y con «×» y confirmación se quitó («Se quitó … y se cerraron sus sesiones.», API-Auth de desarrollo respondió la revocación); la Auditoría muestra `usuario.rol.asignacion` y `usuario.rol.retiro` con actor «Luis Olavarria» y entidad «Luis Antonio Olavarria Valderrama». La persona quedó sin roles, como estaba.
- Rendimiento: el primer retiro tardó más de 9 s. Cada consulta a la base remota de desarrollo toma ~0,9 s y el retiro hacía 8 más la revocación (~1,1 s). Las reglas de `iam-admin` pasaron a pedir sus datos solo para roles de `iam-admin` (callables en `ReglasDeRoles`, con test de que no consultan en otros roles): tras el cambio, asignar tarda 2,9 s, quitar con revocación 2,8 s y revocar 1,8 s.
- 5.2, parcial antes el 2026-11-10 en el portal con la base `IAM` de desarrollo, con la sesión de `luis.olavarria@ubo.cl` (elegida por el usuario): en su propio detalle el portal muestra «No puede cambiar sus propios roles…» sin formulario ni botones de quitar; `POST /usuarios/{su uuid}/roles` responde 403 `ACCION_SOBRE_SI_MISMO` con su `detail`, y la Auditoría muestra las entradas `rechazado` con `entidad.nombre` «Luis Olavarria». Ningún rol cambió. Falta asignar y quitar un rol a otra persona de prueba y ver la revocación en API-Auth.
- Observación: un `POST` del navegador sin `X-XSRF-TOKEN` no recibe 419 sino que llega a la regla, porque Laravel 13 (`PreventRequestForgery`) acepta las peticiones con `Sec-Fetch-Site: same-origin`, cabecera que pone el navegador y que otro sitio no puede falsificar. El 419 sigue cubierto por el test sin esa cabecera.
