## ADDED Requirements

### Requirement: Baja de aplicaciones y roles
Las tablas `applications` y `roles` SHALL tener una columna `disabled_at` (fecha y hora, nula), agregada por una migración de API-IAM que solo agrega columnas. Nula significa activa.

#### Scenario: Migración sobre una base existente
- **GIVEN** una base con aplicaciones y roles creados antes de este cambio
- **WHEN** se ejecuta `php artisan migrate` en API-IAM
- **THEN** ambas tablas tienen `disabled_at` en `null` en todas sus filas y nada más cambia
