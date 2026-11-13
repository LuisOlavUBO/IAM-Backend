## 1. Backend

- [x] 1.1 Dominio `Indicadores` (D1): `IIndicadoresQueryRepository` + implementación con un método por bloque (D2, D5), DTOs y `IndicadoresServiceProvider` en `bootstrap/providers.php`; test del repositorio con datos en `oauth_access_tokens`, `sessions` y la auditoría de la base de tests
- [x] 1.2 `VerIndicadores` (`iam:make-feature Indicadores VerIndicadores`): bloques por permiso (D3) y caché por bloque (D4); ruta `GET indicadores` (`indicadores.ver`) con sesión
- [x] 1.3 Filtro `inactivas` en `ListarUsuarios` (D6)
- [x] 1.4 Tests de feature por requisito: bloques según permisos, inactividad 30/90/nunca, administradores inactivos, M2M (tokens por cliente, sin uso, sin roles, secretos sin rotar), entorno, auditoría, señales, caché y `calculado_en`, sin escritura ni ids, y que el filtro de usuarios coincide con el indicador

## 2. Documentación

- [x] 2.1 OpenAPI (`GET /indicadores` con sus bloques, `inactivas` en `/usuarios`) y Bruno; `DocsTest` en verde

## 3. Frontend (IAM-Frontend)

- [x] 3.1 Servicio y tipos de indicadores; filtro `inactivas` en el servicio y la URL de `lista-usuarios`
- [x] 3.2 Dashboard en el inicio (D7) con tarjetas, barras CSS y enlaces; bloques solo si vienen; tests y README

## 4. Verificación

- [x] 4.1 Pint, suites completas, `openspec validate indicadores-portal --strict`
- [x] 4.2 En el portal con la base de desarrollo: comparar los números con consultas de solo lectura (personas, inactivos, tokens por cliente) y seguir un indicador hasta su lista

## Workflow follow-up

- 4.2, verificada el 2026-11-13 en el portal con la base `IAM` de desarrollo: `activas` 15, `sin_actividad` 6, aplicaciones 10 y tokens de servicio en 7 días 32 coinciden con consultas de solo lectura; los rechazos (`ACCION_SOBRE_SI_MISMO` 2) son los de la verificación de `usuarios-roles`; «Sin actividad nunca» lleva a `/usuarios?estado=activos&inactivas=nunca` con «Mostrando 1–6 de 6».
- Bug encontrado al verificar: la segunda petición respondía 500 (`UnhandledMatchError` en `VerIndicadoresResource`). `config/cache.php` tiene `serializable_classes => false`, así que los DTO cacheados en el store `file` volvían como `__PHP_Incomplete_Class`; los tests usaban el store `array`, que no serializa. Se corrigió con una lista explícita (los DTO de `Indicadores\Domain\Dto` y `CarbonImmutable`) y un test con el store `file`.

