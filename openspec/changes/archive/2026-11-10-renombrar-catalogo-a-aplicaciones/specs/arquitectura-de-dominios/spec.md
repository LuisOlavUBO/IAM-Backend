## MODIFIED Requirements

### Requirement: Capas opcionales y registro del dominio
Las carpetas `Domain/`, `Infrastructure/` y `Contracts/` de un dominio MUST existir solo cuando contienen algo. Cada dominio SHALL registrar qué implementación corresponde a cada interfaz en un `ServiceProvider` propio, listado en `bootstrap/providers.php`, y sus rutas en `routes/v1.php`.

#### Scenario: Interfaces enlazadas por el dominio
- **WHEN** se resuelve del contenedor `IUsuarioQueryRepository`
- **THEN** se obtiene `UsuarioQueryRepository` de `Usuarios/Infrastructure`, enlazado por `UsuariosServiceProvider`

#### Scenario: Dominio sin capas vacías
- **WHEN** un dominio todavía no tiene casos de uso, como Aplicaciones
- **THEN** su carpeta contiene solo `routes/v1.php`, sin carpetas vacías

### Requirement: Dependencias entre dominios
Un dominio MUST depender de otro solo a través de la carpeta `Contracts/` del otro. MUST NOT importar sus `Features`, `Domain` ni `Infrastructure`. `app/Shared` MUST NOT depender de ningún dominio.

#### Scenario: Dominio que usa el modelo de otro
- **GIVEN** una clase de `Domains/Aplicaciones` que importa una clase de `Domains/Usuarios/Domain`
- **WHEN** se ejecuta el test de arquitectura
- **THEN** el test falla nombrando el archivo y la dependencia prohibida

#### Scenario: Núcleo compartido independiente
- **WHEN** se revisan las importaciones de `app/Shared`
- **THEN** ninguna apunta a `App\Domains`

### Requirement: Generador de casos de uso
`php artisan iam:make-feature <Dominio> <CasoDeUso>` SHALL crear `app/Domains/<Dominio>/Features/<CasoDeUso>/` con el endpoint, el request, el handler (`<CasoDeUso>QueryHandler`, o `<CasoDeUso>CommandHandler` con `--command`) y el resource, más un test de feature en `tests/Feature/<Dominio>/<CasoDeUso>Test.php`, y mostrar la línea de ruta que hay que agregar. MUST negarse a sobrescribir archivos existentes y a aceptar nombres que no sean PascalCase.

#### Scenario: Caso de uso nuevo
- **WHEN** se ejecuta `php artisan iam:make-feature Aplicaciones ListarAplicaciones`
- **THEN** se crean `ListarAplicacionesEndpoint`, `ListarAplicacionesRequest`, `ListarAplicacionesQueryHandler`, `ListarAplicacionesResource` y su test, el código generado pasa Pint y el test de arquitectura, y la consola muestra `Route::get(..., ListarAplicacionesEndpoint::class)` para copiar en `app/Domains/Aplicaciones/routes/v1.php`

#### Scenario: Caso de uso que escribe
- **WHEN** se ejecuta `php artisan iam:make-feature Aplicaciones CrearAplicacion --command`
- **THEN** se crea `CrearAplicacionCommandHandler` en vez de un `QueryHandler`, y el endpoint generado lo recibe

#### Scenario: Caso de uso que ya existe
- **WHEN** se ejecuta `php artisan iam:make-feature Usuarios ListarUsuarios`
- **THEN** el comando termina con error sin modificar ningún archivo

#### Scenario: Nombre inválido
- **WHEN** se ejecuta `php artisan iam:make-feature usuarios listar-usuarios`
- **THEN** el comando termina con error indicando que los nombres van en PascalCase, sin crear archivos
