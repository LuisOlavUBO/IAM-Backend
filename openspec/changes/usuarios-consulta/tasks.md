# Tasks

## 1. Listado de usuarios

- [x] 1.1 Registrar en `app/Domains/Usuarios/routes/v1.php` `GET usuarios` (`usuarios.listar`) con `can:iam:usuarios.leer` e implementar el dominio en capas como API-Maestros (design D1–D5: `UsuariosServiceProvider`, `IUsuarioRepository` con `EloquentUsuarioRepository`, modelo `Usuario`, `UsuarioService`, `ListarUsuariosRequest`, `UsuarioResource` y `UsuarioController::index`): validación de `buscar`, `estado`, `pagina` y `por_pagina`, búsqueda por nombre y correo con `LIKE` escapado, RUT normalizado, orden por nombre y `meta` de paginación. Verificar con `tests/Feature/ListarUsuariosTest.php` los escenarios de "Permiso para consultar usuarios" (200, 403 `ACCESO_DENEGADO`, 401 `NO_AUTENTICADO`), "Listado paginado de usuarios" (primera página, página siguiente, paginación inválida) y "Búsqueda de usuarios" (nombre, correo, RUT con puntos, solo bajas, estado inválido), más un `buscar=%` que no trae a todos
- [x] 1.2 Documentar `GET /api/iam/v1/usuarios` en `docs/openapi/openapi.yaml` (parámetros, `UsuarioResumen`, `MetaPaginacion`, 401/403/422) y agregar `docs/bruno/Usuarios/Listar usuarios.bru`. Verificar que `DocsTest` pasa y que `php artisan route:list --path=api/iam/v1/usuarios` muestra la ruta con su middleware

## 2. Detalle de usuario

- [x] 2.1 Registrar `GET usuarios/{uuid}` (`usuarios.ver`) con `can:iam:usuarios.leer`, crear `Exceptions\UsuarioNoEncontrado` (`DomainProblem`, 404) e implementar `UsuarioService::obtener`, `IUsuarioRepository::rolesDe`, `UsuarioDetalleResource` y `UsuarioController::show` (design D2, D6–D8): datos de la persona, `tiene_clave`, `microsoft_vinculado`, fechas ISO 8601 y roles agrupados por aplicación. Verificar con `tests/Feature/VerUsuarioTest.php` los escenarios de "Detalle de un usuario" (roles en dos aplicaciones, sin roles, baja con clave temporal, UUID en mayúsculas, persona inexistente y `uuid` mal formado) y el 403 sin permiso, y con `tests/Unit/UsuarioServiceTest.php` (repositorio falso) que el RUT llega normalizado y que un `uuid` mal formado no consulta el repositorio
- [x] 2.2 Verificar con un test el requisito "Sin credenciales ni identificadores internos" sobre el listado y el detalle (sin `password`, `remember_token`, `entra_oid` ni `id`, y `microsoft_vinculado` en `true`) y que la cantidad de filas de `users`, `role_user` e `iam_auditoria` no cambia
- [x] 2.3 Documentar `GET /api/iam/v1/usuarios/{uuid}` en OpenAPI (`UsuarioDetalle`, 404 `USUARIO_NO_ENCONTRADO`) y agregar `docs/bruno/Usuarios/Ver usuario.bru`. Verificar que `DocsTest` pasa

## 3. Verificación integral

- [x] 3.1 Ejecutar `vendor/bin/pint --test`, la suite completa con `php artisan test` y `openspec validate usuarios-consulta --strict`. Verificar que los tres terminan sin errores
- [ ] 3.2 Con el backend de desarrollo arriba y una sesión del portal, abrir la sección Usuarios del portal Angular (`IAM/frontend`): buscar por nombre y por RUT, paginar y abrir un detalle. Verificar que los datos coinciden con la base `IAM` y que una persona sin `iam:usuarios.leer` no ve la sección

## Workflow follow-up

- 3.2, avance del 2026-11-09 en el portal: búsqueda por nombre (`olavarria`), por RUT con puntos (`18.251.599-k`) y detalle con roles por aplicación coinciden con la base `IAM`. Falta verlo en vivo: paginar (la base de desarrollo tiene menos de 20 personas) y entrar con una persona sin `iam:usuarios.leer`. Ambos casos están cubiertos por tests automáticos (backend 403 y paginación; frontend `permisoGuard` y menú).

- Archivar el cambio cuando esté desplegado y verificado.
- Cambios siguientes del dominio: administración de usuarios (alta, edición, baja y reactivación con revocación) y asignación de roles con sus protecciones.
