## ADDED Requirements

### Requirement: Permisos de Aplicaciones en bases ya instaladas
`php artisan migrate` SHALL renombrar en `oauth_scopes` el permiso `iam:catalogo.leer` a `iam:aplicaciones.leer` y `iam:catalogo.administrar` a `iam:aplicaciones.administrar`, conservando su id y por lo tanto los roles que los dan. MUST NOT cambiar nada cuando el permiso antiguo no existe o el nuevo ya existe. Su reversión SHALL devolver los nombres anteriores con la misma regla.

#### Scenario: Base instalada con los nombres antiguos
- **GIVEN** una base con `iam:catalogo.leer` e `iam:catalogo.administrar`, asignados a `administrador` y `mesa-de-ayuda`
- **WHEN** se ejecuta `php artisan migrate`
- **THEN** los permisos se llaman `iam:aplicaciones.leer` e `iam:aplicaciones.administrar`, conservan su id y los roles siguen dándolos

#### Scenario: Base vacía o instalada con los nombres nuevos
- **GIVEN** una base sin `iam:catalogo.*`
- **WHEN** se ejecuta `php artisan migrate`
- **THEN** `oauth_scopes` no cambia

#### Scenario: Reversión
- **GIVEN** una base donde la migración ya renombró los permisos
- **WHEN** se revierte esa migración
- **THEN** los permisos vuelven a llamarse `iam:catalogo.leer` e `iam:catalogo.administrar`, con su id y sus roles
