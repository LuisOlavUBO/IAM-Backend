## 1. Listado de aplicaciones

- [x] 1.1 Crear con `iam:make-feature Aplicaciones ListarAplicaciones` el caso de uso, `FiltroAplicacionesDto`, `AplicacionResumenDto`, `IAplicacionQueryRepository::listar`, `AplicacionQueryRepository` (D3) y `AplicacionesServiceProvider` (registrado en `bootstrap/providers.php`); registrar `GET aplicaciones` (`aplicaciones.listar`) con `can:iam:aplicaciones.leer`
- [x] 1.2 `tests/Feature/Aplicaciones/ListarAplicacionesTest.php`: permiso (200, 403, 401), primera página, conteo de permisos y roles, paginación inválida, búsqueda por descripción y comodín como texto
- [x] 1.3 OpenAPI (`AplicacionResumen`, 401/403/422) y `docs/bruno/Aplicaciones/Listar aplicaciones.bru`; `DocsTest` en verde

## 2. Detalle de aplicación

- [x] 2.1 Crear `VerAplicacion` (sin Request), `AplicacionDetalleDto`, `PermisoDto`, `RolDto`, `AplicacionNoEncontradaException` e `IAplicacionQueryRepository::detalle` (D4, D5); registrar `GET aplicaciones/{nombre}` (`aplicaciones.ver`)
- [x] 2.2 `tests/Feature/Aplicaciones/VerAplicacionTest.php`: aplicación con permisos y roles, rol sin permisos, nombre en mayúsculas, inexistente (404) y 403 sin permiso; test de "Sin identificadores internos ni escritura" sobre listado y detalle
- [x] 2.3 Test unitario del handler de detalle con un repositorio falso (inexistente → `AplicacionNoEncontradaException`)
- [x] 2.4 OpenAPI (`AplicacionDetalle`, 404 `APLICACION_NO_ENCONTRADA`) y `docs/bruno/Aplicaciones/Ver aplicacion.bru`

## 3. Frontend (IAM-Frontend)

- [x] 3.1 `dominios/aplicaciones/`: `aplicacion.ts`, `aplicaciones.service.ts` y rutas a `paginas/lista-aplicaciones` (búsqueda y paginación en la URL) y `paginas/detalle-aplicacion` (permisos y roles con sus permisos), en lugar de `EnConstruccion`
- [x] 3.2 Tests del servicio y de ambas páginas; README del frontend

## 4. Verificación

- [x] 4.1 Pint, suite completa del backend y del frontend, y `openspec validate aplicaciones-consulta --strict`
- [x] 4.2 En el portal con la base de desarrollo: buscar, abrir `iam-admin` y `pago-honorarios` y comprobar que permisos y roles coinciden con la base

## Workflow follow-up

- 4.2, verificada el 2026-11-10 en el portal con la base `IAM` de desarrollo (8 aplicaciones): los conteos de permisos y roles del listado coinciden con la base; `buscar=honor` trae solo `pago-honorarios`; el detalle de `pago-honorarios` muestra sus 4 permisos y sus 4 roles con los permisos de cada uno; `/aplicaciones/no-existe` muestra «No existe esa aplicación».
- Observación: un rol puede dar permisos de otra aplicación (`pago-honorarios/administrador` da `user:read` de `api-auth`). El detalle los muestra entre los permisos del rol, pero no en la lista de permisos de la aplicación. Si confunde, un cambio futuro puede indicar a qué aplicación pertenece cada permiso del rol.
- Las respuestas tardan unos 2 s contra la base remota de desarrollo.
