## 1. Permisos

- [x] 1.1 Migración `renombrar_permisos_catalogo_a_aplicaciones`: renombra `iam:catalogo.{leer,administrar}` a `iam:aplicaciones.{leer,administrar}` solo si existe el antiguo y no el nuevo; `down()` inverso con la misma regla
- [x] 1.2 Test de la migración: base con los nombres antiguos asignados a roles (renombra, conserva id y roles), base sin ellos (no cambia) y reversión
- [x] 1.3 `InstallCommand`: `iam:aplicaciones.*` en `IAM_SCOPES` e `IAM_ROLES`; actualizar `InstallCommandTest` (incluido que no exista ningún `iam:catalogo.*`)
- [x] 1.4 Reemplazar `iam:catalogo.*` en `MeTest`, `AutorizacionPortalTest`, `ListarUsuariosTest` y `VerUsuarioTest`

## 2. Dominio

- [x] 2.1 Mover `app/Domains/Catalogo` a `app/Domains/Aplicaciones` (comentario de `routes/v1.php`) y actualizar la lista de dominios de `routes/web.php`
- [x] 2.2 Ejemplos de `MakeFeatureCommand` (`Aplicaciones ListarAplicaciones`), escenario de `ArquitecturaTest` y comentario de `RolAsignadoDto`
- [x] 2.3 README (dominios, `iam:install`, generador) y descripción de `docs/openapi/openapi.yaml`; sin menciones de Catálogo en código ni documentación vigentes
- [x] 2.4 Pint y la suite completa de tests en verde

## 3. Frontend (IAM-Frontend)

- [x] 3.1 Sección «Aplicaciones» en `core/secciones.ts` (ruta `aplicaciones`, permiso `iam:aplicaciones.leer`), `app.routes.ts` y `dominios/catalogo` → `dominios/aplicaciones`
- [x] 3.2 `layout.spec.ts` y README del frontend; tests en verde

## 4. Verificación

- [x] 4.1 En desarrollo: `php artisan migrate`, volver a iniciar sesión y comprobar que `/me` trae `iam:aplicaciones.*` y que el menú muestra «Aplicaciones»
