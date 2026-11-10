# Proposal

## Why

«Catálogo» es un término interno de la exploración: quien abre el portal no entiende a primera vista que ahí se administran los sistemas registrados con sus permisos y roles. «Aplicaciones» dice lo que es y coincide con la tabla `applications`. Conviene renombrarlo ahora, antes de que el dominio tenga código y mientras sus permisos solo existen en la base de desarrollo.

## What Changes

- El dominio `Catalogo` pasa a llamarse `Aplicaciones`: carpeta `app/Domains/Aplicaciones`, su registro en `routes/web.php`, los ejemplos de `iam:make-feature`, el test de arquitectura, el README y la descripción del OpenAPI.
- **BREAKING** Los permisos `iam:catalogo.leer` e `iam:catalogo.administrar` pasan a ser `iam:aplicaciones.leer` e `iam:aplicaciones.administrar`:
  - `iam:install` crea los nuevos nombres en las instalaciones nuevas;
  - una migración de datos idempotente renombra los permisos (solo el nombre: las descripciones ya hablan de aplicaciones) en las bases donde IAM ya está instalado; los roles `administrador` y `mesa-de-ayuda` los conservan porque la asignación es por id.
  - Las personas con sesión abierta deben volver a iniciar sesión para que su token traiga los nombres nuevos.
- Frontend (repositorio IAM-Frontend): la sección del menú pasa a «Aplicaciones», con ruta `/aplicaciones`, permiso `iam:aplicaciones.leer` y carpeta `dominios/aplicaciones`.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `comandos-de-operacion`: la aplicación `iam-admin` se instala con `iam:aplicaciones.*` en vez de `iam:catalogo.*`.
- `esquema-de-datos`: nueva migración que renombra los permisos en las bases ya instaladas.
- `acceso-portal`: el escenario de autorización de la mesa de ayuda usa `iam:aplicaciones.leer`.
- `arquitectura-de-dominios`: los escenarios que usan Catálogo como ejemplo pasan a Aplicaciones.

## Impact

- Backend: `InstallCommand`, una migración nueva en `database/migrations`, `routes/web.php`, `app/Domains/Catalogo` → `app/Domains/Aplicaciones`, `MakeFeatureCommand` (textos de ayuda), comentario de `RolAsignadoDto`, tests (`InstallCommandTest`, `MeTest`, `AutorizacionPortalTest`, `ListarUsuariosTest`, `VerUsuarioTest`, `ArquitecturaTest`), README y `docs/openapi/openapi.yaml`.
- Frontend: `core/secciones.ts`, `app.routes.ts`, `dominios/catalogo` → `dominios/aplicaciones`, `layout.spec.ts` y README.
- Datos: actualiza dos filas de `oauth_scopes` (tabla de API-IAM). No hay otros consumidores de `iam:catalogo.*`: API-Auth no los conoce y ningún otro proyecto los usa.
- Sin dependencias nuevas ni cambios en API-Auth.
