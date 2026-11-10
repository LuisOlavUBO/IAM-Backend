# arquitectura-de-dominios Specification

## Purpose

Define cómo se organiza el código de API-IAM: por dominio y, dentro de cada dominio, por caso de uso; qué puede depender de qué; qué es compartido; y cómo se verifica y se genera esa organización. Es la base de los sistemas que partan de API-IAM.

## Requirements

### Requirement: Organización por dominio y caso de uso
El código de negocio SHALL vivir en `app/Domains/<Dominio>/`, y cada caso de uso expuesto por HTTP SHALL tener su propia carpeta `Features/<CasoDeUso>/` con un endpoint invocable (`<CasoDeUso>Endpoint`), su handler y, cuando corresponde, su request y su resource. El handler SHALL llamarse `<CasoDeUso>QueryHandler` si solo lee y `<CasoDeUso>CommandHandler` si escribe (CQRS lite), y MUST recibir datos tipados, nunca el request HTTP.

#### Scenario: Caso de uso autocontenido
- **WHEN** se revisa la carpeta `app/Domains/Usuarios/Features/ListarUsuarios/`
- **THEN** contiene `ListarUsuariosEndpoint`, `ListarUsuariosRequest`, `ListarUsuariosQueryHandler` y `ListarUsuariosResource`, y la ruta `GET /api/iam/v1/usuarios` apunta a ese endpoint

### Requirement: Capas opcionales y registro del dominio
Las carpetas `Domain/`, `Infrastructure/` y `Contracts/` de un dominio MUST existir solo cuando contienen algo. Cada dominio SHALL registrar qué implementación corresponde a cada interfaz en un `ServiceProvider` propio, listado en `bootstrap/providers.php`, y sus rutas en `routes/v1.php`.

#### Scenario: Interfaces enlazadas por el dominio
- **WHEN** se resuelve del contenedor `IUsuarioQueryRepository`
- **THEN** se obtiene `UsuarioQueryRepository` de `Usuarios/Infrastructure`, enlazado por `UsuariosServiceProvider`

#### Scenario: Dominio sin capas vacías
- **WHEN** un dominio todavía no tiene casos de uso, como Catálogo
- **THEN** su carpeta contiene solo `routes/v1.php`, sin carpetas vacías

### Requirement: Organización interna del núcleo
`Domain/` SHALL guardar en su raíz las entidades, reglas y excepciones, los DTO en `Domain/Dto/` y las interfaces en `Domain/Interfaces/`. `Infrastructure/` y la raíz de `Domain/` MUST NOT tener subcarpetas por tipo hasta que pasen de unos diez archivos.

#### Scenario: Núcleo de Usuarios
- **WHEN** se revisa `app/Domains/Usuarios/Domain/`
- **THEN** contiene `ClaveTemporal` y `UsuarioNoEncontradoException` en la raíz, los DTO en `Dto/` y `IUsuarioQueryRepository` en `Interfaces/`, sin otras subcarpetas

### Requirement: Dependencias dentro de un dominio
Las dependencias SHALL ir hacia adentro: `Features` puede usar `Domain`, `Contracts` y `Shared`; `Infrastructure` implementa las interfaces de `Domain/Interfaces` y de `Contracts`. `Domain` MUST NOT depender de `Features`, de `Infrastructure`, de `Contracts` ni de clases HTTP. Un caso de uso MUST NOT importar clases de otro caso de uso.

#### Scenario: Handler que depende de un repositorio
- **WHEN** un handler necesita datos de la base
- **THEN** recibe una interfaz de `Domain/Interfaces/` por constructor, y su implementación en `Infrastructure/` se enlaza en el `ServiceProvider` del dominio

#### Scenario: Caso de uso que importa a otro
- **GIVEN** una clase de `Features/VerUsuario/` que importa una clase de `Features/ListarUsuarios/`
- **WHEN** se ejecuta el test de arquitectura
- **THEN** el test falla nombrando el archivo y la dependencia prohibida

### Requirement: Dependencias entre dominios
Un dominio MUST depender de otro solo a través de la carpeta `Contracts/` del otro. MUST NOT importar sus `Features`, `Domain` ni `Infrastructure`. `app/Shared` MUST NOT depender de ningún dominio.

#### Scenario: Dominio que usa el modelo de otro
- **GIVEN** una clase de `Domains/Catalogo` que importa una clase de `Domains/Usuarios/Domain`
- **WHEN** se ejecuta el test de arquitectura
- **THEN** el test falla nombrando el archivo y la dependencia prohibida

#### Scenario: Núcleo compartido independiente
- **WHEN** se revisan las importaciones de `app/Shared`
- **THEN** ninguna apunta a `App\Domains`

### Requirement: Puntos de entrada fuera de los dominios
Los comandos de Artisan (`app/Console`) y la configuración de la aplicación SHALL poder usar `Shared` y el `Domain`, `Contracts` y `Features` de cualquier dominio, porque coordinan varios dominios. MUST NOT importar `Infrastructure` de ningún dominio.

#### Scenario: Comando de instalación
- **WHEN** se revisan las importaciones de `iam:install`
- **THEN** usa la auditoría y el RUT de `Shared`, la clave temporal de `Usuarios/Domain` y la creación de clientes de `Clientes/Domain`, y ninguna clase de `Infrastructure`

### Requirement: Núcleo compartido mínimo
`app/Shared` SHALL contener solo lo que usan dos o más dominios o los puntos de entrada: la página de resultados y la respuesta paginada `{data, meta: {total, pagina, por_pagina}}`, la regla y normalización de RUT, y el puerto de auditoría con su implementación. La auditoría MUST mantener el comportamiento de la spec `auditoria`.

#### Scenario: Listado paginado de cualquier dominio
- **WHEN** un endpoint de listado responde
- **THEN** arma `{data, meta}` con la respuesta paginada de `Shared`, y el JSON coincide con el esquema `MetaPaginacion` del OpenAPI

#### Scenario: Auditoría desde un comando
- **WHEN** `iam:grant-admin` registra una acción
- **THEN** usa el puerto de auditoría de `Shared` y la fila en `iam_auditoria` tiene los mismos campos que antes de este cambio

### Requirement: Nomenclatura de clases
Cada clase SHALL decir su rol en el nombre: el negocio en español y el rol técnico como sufijo o prefijo al estilo .NET. Las interfaces MUST empezar con `I`; los objetos de datos `final readonly` MUST terminar en `Dto`; las excepciones de negocio en `Exception`; los repositorios en `QueryRepository` (lectura) o `Repository` (escritura); los servicios que un dominio ofrece a otros en `Service`; y las piezas de un caso de uso en `Endpoint`, `Request`, `QueryHandler`, `CommandHandler` y `Resource`.

#### Scenario: Clases de Usuarios
- **WHEN** se revisa `app/Domains/Usuarios`
- **THEN** encuentra `IUsuarioQueryRepository` en `Domain/Interfaces/`, `UsuarioResumenDto` y `UsuarioDetalleDto` en `Domain/Dto/`, `UsuarioNoEncontradoException` en `Domain/`, `UsuarioQueryRepository` en `Infrastructure/` e `IDirectorioUsuariosService` con `PersonaBasicaDto` en `Contracts/`

### Requirement: Lecturas por repositorios de consulta
Los casos de uso de solo lectura SHALL obtener sus datos de un repositorio de consulta (`I<Entidad>QueryRepository` en `Domain/Interfaces/`) que devuelve DTO de solo lectura con las columnas que la respuesta necesita, sin pasar por las entidades de escritura. Las consultas MUST NOT cargar columnas de credenciales (`password`, `remember_token`, `entra_oid`) cuando la respuesta no las usa, y las interfaces MUST NOT exponer tipos de paginación de Laravel.

#### Scenario: Listado de usuarios sin credenciales en memoria
- **WHEN** se pide `GET /api/iam/v1/usuarios`
- **THEN** la consulta a `users` selecciona solo `uuid`, `name`, `email`, `rut` y `disabled_at`, y la respuesta es la misma que define la spec `usuarios`

#### Scenario: Contrato sin tipos de Laravel
- **WHEN** se revisa `IUsuarioQueryRepository`
- **THEN** su listado devuelve la página de resultados de `Shared`, no un `LengthAwarePaginator`

### Requirement: Verificación automática de la arquitectura
Un test de arquitectura SHALL recorrer `app/` y fallar, nombrando el archivo y la importación, ante cualquier dependencia que prohíben los requisitos de dependencias de este spec. MUST correr en la suite normal (`php artisan test`) y no requerir dependencias nuevas ni base de datos.

#### Scenario: Código que respeta las reglas
- **WHEN** se ejecuta `php artisan test` sobre el código de este cambio
- **THEN** el test de arquitectura pasa

#### Scenario: Infraestructura importada por un comando
- **GIVEN** un comando de `app/Console` que importa una clase de `Domains/*/Infrastructure`
- **WHEN** se ejecuta el test de arquitectura
- **THEN** el test falla nombrando el comando y la clase importada

### Requirement: Generador de casos de uso
`php artisan iam:make-feature <Dominio> <CasoDeUso>` SHALL crear `app/Domains/<Dominio>/Features/<CasoDeUso>/` con el endpoint, el request, el handler (`<CasoDeUso>QueryHandler`, o `<CasoDeUso>CommandHandler` con `--command`) y el resource, más un test de feature en `tests/Feature/<Dominio>/<CasoDeUso>Test.php`, y mostrar la línea de ruta que hay que agregar. MUST negarse a sobrescribir archivos existentes y a aceptar nombres que no sean PascalCase.

#### Scenario: Caso de uso nuevo
- **WHEN** se ejecuta `php artisan iam:make-feature Catalogo ListarAplicaciones`
- **THEN** se crean `ListarAplicacionesEndpoint`, `ListarAplicacionesRequest`, `ListarAplicacionesQueryHandler`, `ListarAplicacionesResource` y su test, el código generado pasa Pint y el test de arquitectura, y la consola muestra `Route::get(..., ListarAplicacionesEndpoint::class)` para copiar en `app/Domains/Catalogo/routes/v1.php`

#### Scenario: Caso de uso que escribe
- **WHEN** se ejecuta `php artisan iam:make-feature Catalogo CrearAplicacion --command`
- **THEN** se crea `CrearAplicacionCommandHandler` en vez de un `QueryHandler`, y el endpoint generado lo recibe

#### Scenario: Caso de uso que ya existe
- **WHEN** se ejecuta `php artisan iam:make-feature Usuarios ListarUsuarios`
- **THEN** el comando termina con error sin modificar ningún archivo

#### Scenario: Nombre inválido
- **WHEN** se ejecuta `php artisan iam:make-feature usuarios listar-usuarios`
- **THEN** el comando termina con error indicando que los nombres van en PascalCase, sin crear archivos
