## 1. Núcleo

- [x] 1.1 `ImpactoDto` y `EnUsoException` con `impacto` en el problema (D2, D4); comprobar que el renderer de ubo/common incluye el dato extra
- [x] 1.2 `ICatalogoRepository`: impacto (D2) y borrar; test del repositorio (conteos de personas y clientes distintos, cascada al borrar un rol)

## 2. Casos de uso

- [x] 2.1 `BorrarAplicacion`, `BorrarPermiso`, `BorrarRol` (D3); rutas `DELETE`
- [x] 2.2 Tests de feature por requisito: borrado sin uso (y cascada de permisos del rol), 409 `EN_USO` con impacto, protección de `iam-admin` con `rechazado`, 404, 403, 401 y 419

## 3. Documentación

- [x] 3.1 OpenAPI (tres operaciones, esquema `Impacto`, código `EN_USO`) y Bruno; `DocsTest` en verde

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `aplicaciones.service.ts`: borrar; tipo `Impacto`
- [x] 4.2 `detalle-aplicacion` (D5): borrar con confirmación y aviso de uso, vuelta al listado tras borrar la aplicación; tests y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate aplicaciones-renombrar-y-borrar --strict`
- [ ] 5.2 En el portal con la base de desarrollo, sobre el catálogo de prueba que indique el usuario: borrar un rol sin uso y comprobar el 409 de algo en uso; ver las entradas en Auditoría
