## MODIFIED Requirements

### Requirement: Capas opcionales y registro del dominio
Las carpetas `Domain/`, `Infrastructure/` y `Contracts/` de un dominio MUST existir solo cuando contienen algo. Cada dominio SHALL registrar qué implementación corresponde a cada interfaz en un `ServiceProvider` propio, listado en `bootstrap/providers.php`, y sus rutas en `routes/v1.php`.

#### Scenario: Interfaces enlazadas por el dominio
- **WHEN** se resuelve del contenedor `IUsuarioQueryRepository`
- **THEN** se obtiene `UsuarioQueryRepository` de `Usuarios/Infrastructure`, enlazado por `UsuariosServiceProvider`

#### Scenario: Dominio sin capas vacías
- **WHEN** un dominio todavía no tiene casos de uso, como Auditoria
- **THEN** su carpeta contiene solo `routes/v1.php`, sin carpetas vacías

#### Scenario: Dominio sin Contracts
- **WHEN** ningún otro dominio usa un dominio, como Aplicaciones
- **THEN** su carpeta no tiene `Contracts/`
