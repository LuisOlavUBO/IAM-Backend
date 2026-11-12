## 1. Núcleo

- [x] 1.1 `Domain/ReglasDeCatalogo` (formatos D3 y protección de `iam-admin` D4) con test unitario; excepciones (D2)
- [x] 1.2 `ICatalogoRepository` + `CatalogoRepository` (D2), enlazado en `AplicacionesServiceProvider`, con test del repositorio

## 2. Casos de uso

- [x] 2.1 `CrearAplicacion` y `EditarAplicacion`; rutas `POST aplicaciones` y `PATCH aplicaciones/{nombre}`
- [x] 2.2 `CrearPermiso` y `EditarPermiso`; rutas `POST aplicaciones/{nombre}/permisos` y `PATCH …/permisos/{permiso}`
- [x] 2.3 `CrearRol` y `EditarRol`; rutas `POST aplicaciones/{nombre}/roles` y `PATCH …/roles/{rol}`
- [x] 2.4 `CambiarPermisosDeRol` (D5); ruta `PUT …/roles/{rol}/permisos`
- [x] 2.5 Tests de feature por requisito: altas (201, 422 de formato, 409 de nombre, sin distinguir mayúsculas donde corresponde), ediciones (200, 404), permisos de un rol (agregar, quitar, sin cambios, permiso inexistente, permiso de otra aplicación), protección de `iam-admin` en cada caso con su entrada `rechazado`, 403 (mesa de ayuda) y 401, entradas de auditoría con los campos de `iam:install`

## 3. Documentación

- [x] 3.1 OpenAPI (siete operaciones, cuerpos, respuestas y códigos nuevos en la tabla de errores) y Bruno (`Aplicaciones/…`); `DocsTest` en verde

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `aplicaciones.service.ts`: las siete operaciones; tipos; ruta `nueva` antes de `:nombre`
- [x] 4.2 Página `nueva-aplicacion`, botón en el listado y acciones en `detalle-aplicacion` (D7), ocultas en `iam-admin` y sin el permiso; tests y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate aplicaciones-catalogo --strict`
- [x] 5.2 En el portal con la base de desarrollo, con datos de prueba que indique el usuario: crear una aplicación, un permiso y un rol, darle el permiso al rol, asignar el rol a una persona de prueba y comprobar que su token trae el scope; ver las entradas en Auditoría
