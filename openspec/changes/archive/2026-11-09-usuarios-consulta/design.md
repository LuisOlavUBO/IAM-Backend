# Design

## Context

Ver `proposal.md` → Why. Requisitos: `specs/usuarios`.

Estado y restricciones que dan forma al enfoque:

- **acceso-portal** dejó los dominios montados en `routes/web.php` bajo `api/iam/v1` con `ubo.login`, el `Gate::before` del módulo Login que convierte los permisos `iam:*` en habilidades (`can:iam:usuarios.leer`) y `ProblemRenderer` de `ubo/common` para los errores: `ValidationException` → 422 `VALIDACION` con `errores`, 401 `NO_AUTENTICADO`, 403 `ACCESO_DENEGADO`, y `DomainProblem` para códigos de negocio.
- **No hay modelos Eloquent**: el modelo `User` de Laravel se eliminó en el esqueleto y `MeController` lee `users` con el query builder.
- **API-Maestros** organiza cada dominio en capas (`Http`, `Application`, `Contracts`, `Infrastructure`, `Models`, `Exceptions` y un `ServiceProvider`), con SOLID; API-IAM debe mantener esa línea.
- **Datos** (`esquema_base_iam`): `users` (`uuid` único, `rut` normalizado `12345678-5` o nulo, `entra_oid`, `password` nulable, `disabled_at`, `temporary_password_expires_at`), `role_user` (`user_id`, `role_id`), `roles` (`application_id`, `name`, `description`) y `applications` (`name`, `description`). La base usa la intercalación `SQL_Latin1_General_CP1_CI_AS`: las comparaciones no distinguen mayúsculas pero sí tildes.
- **`App\Rules\Rut`** ya valida y normaliza RUT (copiada de API-Auth en acceso-portal).
- **OpenAPI** ya define `MetaPaginacion` (`total`, `pagina`, `por_pagina`) y la respuesta `Validacion`.

## Goals / Non-Goals

**Goals:**
- Fijar en este primer endpoint de lectura las convenciones que repetirán los demás dominios: parámetros `buscar`, `pagina` y `por_pagina`, `meta` de paginación y 404 con un código por recurso.

**Non-Goals:**
- Cualquier escritura, incluida la auditoría: consultar no se audita (la auditoría registra cambios).
- Ordenamientos configurables, filtros por aplicación o rol y exportación.
- Búsqueda que ignore tildes.

## Decisions

### D1. Capas del dominio como en API-Maestros
`app/Domains/Usuarios` sigue la misma organización que los dominios de API-Maestros, para mantener una sola línea de desarrollo entre las APIs de la UBO (SOLID, dependencias en una sola dirección: `Http` → `Application` → `Contracts` ← `Infrastructure`):

```
app/Domains/Usuarios/
+-- UsuariosServiceProvider.php     enlaza los contratos con su implementación
+-- Application/                    UsuarioService, FiltroUsuarios, DetalleUsuario, RolAsignado
+-- Contracts/                      IUsuarioRepository
+-- Exceptions/                     UsuarioNoEncontrado
+-- Http/Controllers/V1/            UsuarioController (index, show)
+-- Http/Requests/V1/               ListarUsuariosRequest
+-- Http/Resources/V1/              UsuarioResource, UsuarioDetalleResource
+-- Infrastructure/Eloquent/        EloquentUsuarioRepository
+-- Models/                         Usuario
+-- routes/v1.php
```

- El controlador solo traduce HTTP: valida con el `FormRequest`, llama al servicio y responde con un `Resource`.
- `UsuarioService` tiene las reglas de la consulta (normalizar el RUT buscado, rechazar un `uuid` sin formato) y depende de `IUsuarioRepository`, no de Eloquent.
- `EloquentUsuarioRepository` tiene las consultas.
- Las rutas siguen montándose desde `routes/web.php` como en acceso-portal (sesión y `ubo.login`), con `can:iam:usuarios.leer` y los nombres `usuarios.listar` y `usuarios.ver`. El `ServiceProvider` solo registra el binding; no monta rutas, a diferencia de Maestros, porque el grupo `web` con sesión ya lo arma `routes/web.php`.
- *Alternativa:* dos controladores invocables con las consultas dentro (la primera versión de este diseño). Es menos código, pero deja a IAM con una estructura distinta de Maestros y, cuando lleguen las escrituras (alta, baja con revocación y auditoría en la misma transacción), habría que reorganizar igual.
- No hay decorador de caché (`Infrastructure/Cache`): los datos de usuarios cambian y se consultan poco; si hiciera falta, se agrega otra implementación del mismo contrato sin tocar el servicio.

### D2. Modelo `Usuario` de solo lectura sobre `users`
`Models\Usuario` (tabla `users`) con `$hidden` para `password`, `remember_token` y `entra_oid`, sin `$fillable` (este cambio no escribe) y con casts de fecha. Los `Resource` arman la respuesta con una lista blanca de campos, así que el `id`, la contraseña y `entra_oid` no salen aunque el modelo los cargue. `microsoft_vinculado` y `tiene_clave` se exponen como métodos del modelo (`entra_oid !== null`, `password !== null`).
- *Alternativa:* query builder con columnas seleccionadas a mano, como `MeController`. Evita cargar la contraseña en memoria, pero rompe con el patrón de Maestros (modelos + repositorios); la lista blanca de los `Resource` y `$hidden` cubren el riesgo de exponer columnas.
- Los roles no usan modelos del dominio Catálogo (aún no existe): el repositorio los devuelve como `RolAsignado` (aplicación y rol con sus descripciones), un objeto de `Application`. Así Usuarios no depende de otro dominio.

### D3. Validación con un `FormRequest`
`Http/Requests/V1/ListarUsuariosRequest`: `buscar` (string, máx. 100), `estado` (`in:activos,baja,todos`), `pagina` (entero ≥ 1) y `por_pagina` (entero 1–100), y un método `filtro()` que entrega un `FiltroUsuarios` con los valores por omisión. El 422 lo arma `ProblemRenderer`.

### D4. Búsqueda con `LIKE` escapado y RUT normalizado
`nombre LIKE %buscar%` o `email LIKE %buscar%`, escapando `%`, `_` y `[` (comodines de SQL Server) con `ESCAPE`. Si `Rut::normalize(buscar)` da un RUT válido, se agrega `OR rut = <normalizado>`. La normalización la decide `UsuarioService` (regla de negocio) y el repositorio solo aplica el filtro. La intercalación CI resuelve las mayúsculas sin `LOWER()`, que impediría usar el índice único de `email`.
- *Alternativa:* búsqueda de texto completo. Requiere un índice de texto en `users`, tabla que lee API-Auth: no se justifica para este volumen.

### D5. Paginación con `paginate()` y `meta` propia
El repositorio usa `paginate()` ordenando por `name` y `uuid` (desempate estable). El controlador no devuelve el paginador de Laravel (su JSON trae `current_page`, `links`, `per_page`…): responde `UsuarioResource::collection(items)` con `meta: {total, pagina, por_pagina}`, el esquema `MetaPaginacion` ya documentado.

### D6. 404 `USUARIO_NO_ENCONTRADO` como `DomainProblem`
`Exceptions\UsuarioNoEncontrado extends DomainProblem` (404, "Usuario no encontrado"). IAM no tiene un `Shared` con una base `RecursoNoEncontrado` como Maestros; se usa directamente `DomainProblem` de `ubo/common`. Un `uuid` sin formato de UUID (`Str::isUuid`) lo rechaza el servicio sin consultar la base, para no enviar a SQL Server un valor que no convierte a `uniqueidentifier`.
- *Alternativa:* `abort(404)`. Daría `RUTA_NO_ENCONTRADA`, que confunde "la persona no existe" con "la ruta no existe".

### D7. Roles agrupados por aplicación
`IUsuarioRepository::rolesDe(Usuario)` hace una consulta `role_user ⋈ roles ⋈ applications` ordenada por nombre de aplicación y de rol; `UsuarioDetalleResource` los agrupa por aplicación. Una persona tiene pocos roles: dos consultas (persona y roles) bastan.

### D8. Fechas en ISO 8601
Las columnas `datetime` de SQL Server no guardan zona; los casts del modelo las leen en la zona de la aplicación (`APP_TIMEZONE`) y los `Resource` las emiten con `toIso8601String()`.

## Risks / Trade-offs

- [La búsqueda distingue tildes: "perez" no encuentra "Pérez"] → Se acepta en este cambio (Non-Goal). Si molesta, se compara con `COLLATE ..._AI` en la consulta, sin tocar el esquema.
- [`LIKE '%texto%'` no usa índices y recorre `users`] → Aceptable con miles de filas; el límite de 100 por página acota la respuesta. Se mide si `users` crece a cientos de miles.
- [El `COUNT(*)` más la página son dos consultas por listado] → Costo bajo y da `total` exacto, que el portal necesita para paginar.

## Migration Plan

Sin migraciones ni configuración nueva: se despliega con la imagen siguiente y no requiere pasos manuales. Rollback: volver a la imagen anterior; las rutas desaparecen y el portal muestra la sección como no disponible.
