## Context

El dominio Catálogo (aplicaciones, sus permisos y sus roles) todavía no tiene casos de uso: en el backend es solo `app/Domains/Catalogo/routes/v1.php` y en el frontend una sección «en construcción». Sus permisos `iam:catalogo.leer` e `iam:catalogo.administrar` sí existen: los crea `iam:install` y están en la base de desarrollo, asignados a `iam-admin/administrador` (ambos) y a `iam-admin/mesa-de-ayuda` (`leer`). Ninguna ruta los exige aún; solo el menú del portal usa `iam:catalogo.leer`. Ningún otro proyecto (API-Auth incluido) los nombra.

## Goals / Non-Goals

**Goals:**
- Que el dominio, la sección del portal y los permisos se llamen Aplicaciones en todo el código vigente.
- Que las bases ya instaladas queden con los nombres nuevos sin SQL manual ni reinstalar.

**Non-Goals:**
- Implementar casos de uso de Aplicaciones (será la vertical siguiente).
- Reescribir los cambios archivados: son historia y conservan el nombre de su momento.

## Decisions

**D1. Renombrar con una migración de datos, conservando el id.** Un `UPDATE` de `oauth_scopes.name` por nombre exacto mantiene las filas de `oauth_scope_role`, así que los roles siguen dando el permiso sin tocar otra tabla. Alternativas descartadas: SQL manual (prohibido salvo emergencia), un comando `iam:*` nuevo (más código para un ajuste de una vez) y crear permisos nuevos y borrar los antiguos (hay que reasignarlos a los roles y deja ids huérfanos en tokens vigentes).

**D2. Idempotente y reversible.** Cada renombre se aplica solo si existe el nombre antiguo y no existe el nuevo; así la migración es inocua en una base vacía (tests) o instalada con la versión nueva de `iam:install`. `down()` hace lo inverso con la misma regla. A diferencia del esquema base, aquí revertir no pierde datos.

**D3. Solo el nombre.** Las descripciones («Ver aplicaciones, roles y permisos», «Crear y editar aplicaciones, roles y permisos») ya dicen lo correcto.

**D4. Sin registro en `iam_auditoria`.** La auditoría registra acciones de personas o comandos de operación; esta migración queda registrada en `iam_migrations`, como cualquier cambio de esquema.

**D5. Nombre del dominio `Aplicaciones` (plural), igual que `Usuarios` y `Clientes`.** La ruta del portal es `/aplicaciones` y el futuro endpoint `/api/iam/v1/aplicaciones`.

## Risks / Trade-offs

- [Sesiones abiertas con el token antiguo] → El token trae `iam:catalogo.*` hasta que la persona vuelva a iniciar sesión; mientras tanto el menú no muestra Aplicaciones. Ninguna ruta del backend exige esos permisos aún, así que no hay errores. Se avisa en el despliegue.
- [Orden de despliegue] → Backend (con `php artisan migrate`) y frontend se despliegan juntos. Si el frontend llega antes, la sección queda oculta hasta migrar; si llega después, la sección vieja queda oculta. Ninguno de los dos casos rompe nada.

## Migration Plan

1. Desplegar el backend y ejecutar a mano `php artisan migrate` (como siempre, nunca desde el entrypoint).
2. Desplegar el frontend.
3. Las personas con sesión vuelven a iniciar sesión.

Rollback: `php artisan migrate:rollback --step=1` y volver a las versiones anteriores de backend y frontend.
