## 1. Contrato de Usuarios

- [x] 1.1 Agregar `IDirectorioUsuariosService::personas(list<string> $uuids): array<string, PersonaBasicaDto>` y su implementación en `DirectorioUsuariosService` (una consulta con `whereIn`, sin consultar si la lista está vacía) (D3)
- [x] 1.2 Test de `personas`: varios `uuid`, uno inexistente y lista vacía

## 2. Listado de auditoría

- [x] 2.1 Crear con `iam:make-feature Auditoria ListarAuditoria` el caso de uso, `FiltroAuditoriaDto`, `EntradaAuditoriaDto`, `ActorDto`, `IAuditoriaQueryRepository`, `AuditoriaQueryRepository` (D4, D5) y `AuditoriaServiceProvider` (en `bootstrap/providers.php`); el `QueryHandler` completa los actores por lote (D3); registrar `GET auditoria` (`auditoria.listar`) con `can:iam:auditoria.leer`
- [x] 2.2 `tests/Feature/Auditoria/ListarAuditoriaTest.php`: permiso (200, 403, 401), más recientes primero con paginación, entrada de comando, entrada de persona con nombre y correo, actor inexistente, cada filtro (rango de fechas con 23:59, acción por prefijo con comodín como texto, entidad, actor con resultado), filtros inválidos (422) y que consultar no escribe en `iam_auditoria`
- [x] 2.3 Test unitario del `QueryHandler` con repositorio y directorio falsos: pide los `uuid` distintos una sola vez, y sin personas pide una lista vacía (que el directorio responde sin consultar, 1.2)
- [x] 2.4 OpenAPI (`EntradaAuditoria`, parámetros, 401/403/422) y `docs/bruno/Auditoria/Listar auditoria.bru`; `DocsTest` en verde

## 3. Frontend (IAM-Frontend)

- [x] 3.1 `dominios/auditoria/`: `auditoria.ts`, `auditoria.service.ts` y `paginas/lista-auditoria` (filtros en la URL, filtros activos de actor y entidad, fila desplegable con antes, después, motivo y `trace_id`, enlaces a usuarios y clientes) en lugar de `EnConstruccion`; quitar `EnConstruccion` si queda sin uso
- [x] 3.2 Tests del servicio y de la página; README del frontend

## 4. Verificación

- [x] 4.1 Pint, suite completa del backend y del frontend, y `openspec validate auditoria-consulta --strict`
- [x] 4.2 En el portal con la base de desarrollo: comprobar que las entradas de `iam:install` aparecen con sus acciones y valores, y filtrar por acción `cliente.` y por un cliente

## 5. Nombre de la entidad (agregado tras revisar «rol 16» en el portal)

- [x] 5.1 `Aplicaciones/Contracts/IDirectorioAplicacionesService` (`aplicaciones`, `roles` como `aplicacion/rol`, `permisos`: `id => nombre`) y `Clientes/Contracts/IDirectorioClientesService::clientes`, con sus implementaciones en `Infrastructure/` y enlazados en sus `ServiceProvider` (D3b); tests de ambos (varios ids, inexistente, lista vacía sin consulta)
- [x] 5.2 `ListarAuditoriaQueryHandler` completa `entidad.nombre` por tipo, una consulta por tipo presente y descartando ids con forma inválida; Resource con `entidad.nombre`; tests de feature (rol, aplicación, permiso, persona, cliente, entidad borrada) y unitario (ids inválidos no se consultan)
- [x] 5.3 OpenAPI (`entidad.nombre`) y Bruno; frontend con `tipo · nombre` (o `id (ya no existe)`), enlace a `/aplicaciones/{nombre}` y el id en el detalle; tests y README
- [x] 5.4 Suites completas, `openspec validate --strict` y verificación en el portal: las entradas del rol 16 muestran `iam-admin/mesa-de-ayuda`

## Workflow follow-up

- 4.1, 2026-11-10: Pint sin cambios, backend 189 tests, frontend 72 tests y build, el cambio y las 9 specs válidas con `--strict`. `EnConstruccion` quedó sin uso y se quitó del frontend.
- 4.2, verificada el 2026-11-10 en el portal con la base `IAM` de desarrollo: el listado trae las 27 entradas de `iam_auditoria` (todas de comandos, del 9 de noviembre), de la más reciente (`cliente.secreto.rotacion`) a la más antigua (`aplicacion.creacion` de `iam-admin`); `accion=cliente.` trae las 6 de clientes; el filtro por `iam-admin-sistema` trae sus 4 entradas (rotación, dos asignaciones de rol y creación) con sus valores; `desde=2026-11-09&hasta=2026-11-09` trae las 27; `hasta` anterior a `desde` responde 422; la fila desplegada muestra antes, después y `trace_id`. Ninguna respuesta tiene un hash bcrypt ni claves `secret`/`secreto` (la palabra «secreto» aparece solo en la acción `cliente.secreto.rotacion` y en el motivo escrito por el operador).
- Observación: con el panel angosto (640 px) la tabla se desplaza horizontalmente y el detalle desplegado queda a la izquierda del área visible; en 1280 px se ve completo.
- 5.4, verificada el 2026-11-10 en el portal con la base `IAM` de desarrollo: backend 194 tests y frontend 73 tests y build en verde; las 27 entradas resuelven su entidad (`rol 16` → `iam-admin/mesa-de-ayuda`, `rol 15` → `iam-admin/administrador`, `aplicacion 7` → `iam-admin`, los permisos 17 a 23 por su nombre, los dos clientes y la persona del primer administrador); con `accion=rol.` la tabla muestra «rol · iam-admin/mesa-de-ayuda» y «rol · iam-admin/administrador». Los permisos 19 y 20 aparecen como `iam:aplicaciones.*`, su nombre actual, aunque se crearon como `iam:catalogo.*` (D3b); el nombre original sigue en `despues`.
