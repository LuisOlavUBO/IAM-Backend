## ADDED Requirements

### Requirement: Filtro por inactividad
`GET /api/iam/v1/usuarios` SHALL aceptar `inactivas` con `30`, `90` o `nunca` para listar las personas sin actividad hace más de 30 o 90 días, o sin actividad nunca, con la misma definición de actividad que los indicadores (último access token emitido). Un valor distinto MUST responder 422 `VALIDACION`. Se combina con `buscar` y `estado`.

#### Scenario: Desde el indicador
- **WHEN** se pide `GET /api/iam/v1/usuarios?estado=activos&inactivas=90`
- **THEN** `data` trae solo las personas activas sin actividad hace más de 90 días y `meta.total` coincide con `personas.inactivas_90` de los indicadores
