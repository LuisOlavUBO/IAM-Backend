# Tasks

## 1. Preparación

- [x] 1.1 Confirmar que no hay ramas abiertas ni cambios OpenSpec activos que toquen `app/Domains` (`git branch -a --no-merged main`, `openspec list`) y guardar la lista de rutas de antes en el scratchpad (`php artisan route:list --path=api/iam --json`). Verificar que el archivo existe y que no hay ramas pendientes

## 2. Núcleo en carpetas explícitas

- [x] 2.1 Mover con `git mv` las 83 clases de `app/Domains/*/Domain/` según design D1, D2 y D2b (`Domain/Dto` → `Dto/`, salvo `PersonaCreadaDto` → `Features/CrearUsuario/`; `Domain/Interfaces` → `Interfaces/`; `…Exception` → `Exceptions/`; el resto → `Rules/`) y cambiar su `namespace`. Verificar que no queda ninguna carpeta `app/Domains/*/Domain/`, que `Usuarios/Dto/` tiene 8 clases y que `git status` muestra los archivos como renombrados
- [x] 2.2 Reemplazar los imports `App\Domains\<Dominio>\Domain\…` por los nuevos en `app/Domains` (85 archivos), `app/Console/Commands/InstallCommand.php`, `RotateSecretCommand.php`, `config/cache.php` y `tests/` (21 archivos), y agregar los `use` de excepciones en `ReglasDeCatalogo`, `ReglasDeClientes`, `ReglasDePersonas` y `ReglasDeRoles` (design D5). Verificar que `grep -rF '\Domain\' app config tests` solo encuentra `UBO\Common\Login\Domain\UboUser`, que `composer dump-autoload` no avisa de clases fuera de PSR-4 y que pasan los tests unitarios (`php artisan test --compact tests/Unit`)
- [x] 2.3 Cambiar la clave de la caché de indicadores en `VerIndicadoresQueryHandler` (design D6) y agregar a `VerIndicadoresTest` un caso que guarde una entrada con la clave anterior y verifique que `GET /api/iam/v1/indicadores` responde 200 sin leerla. Verificar que `tests/Feature/Indicadores` pasa

## 3. Test de arquitectura

- [x] 3.1 Cambiar `tests/Unit/ArquitecturaTest.php` para tratar `Rules`, `Exceptions`, `Dto` e `Interfaces` como la capa `nucleo` y reportar las carpetas no reconocidas de `app/Domains/<Dominio>/` (design D4), actualizando el docblock con la tabla de dependencias. Reemplazar los casos negativos y positivos que nombran `Domain/` por los de D4. Verificar que el test pasa sobre el código y que cada escenario de "Dependencias dentro de un dominio", "Dependencias entre dominios" y "Carpeta fuera de la estructura" tiene su caso en memoria
- [x] 3.2 Agregar a `ArquitecturaTest` la regla de design D4: un `…QueryHandler.php` o `…CommandHandler.php` de `Features/` no importa `Illuminate\Http\…`, con casos negativos (un QueryHandler y un CommandHandler que importan `Illuminate\Http\Request`) y uno positivo (un Endpoint que importa `Illuminate\Http\JsonResponse`), y una línea en el docblock. Verificar que el test pasa sobre el código y que los escenarios "Handler que importa HTTP" y "Endpoint que usa HTTP" tienen su caso
- [x] 3.3 Actualizar el comentario de `stubs/feature/handler.stub` para que nombre `Interfaces/` y `Dto/` en vez de `Domain/Interfaces` y `Domain/Dto`. Verificar que `tests/Feature/MakeFeatureCommandTest.php` pasa (el código generado pasa Pint y el test de arquitectura)

## 4. Documentación

- [x] 4.1 Actualizar `.ai/guidelines/iam/core.blade.php` y copiar los mismos cambios a `AGENTS.md`: capas opcionales (`Rules/`, `Exceptions/`, `Dto/`, `Interfaces/`), la regla «¿lo usa otro dominio? `Contracts/`; ¿solo este? `Dto/` o `Interfaces/`», «sube al núcleo cuando lo usa un segundo caso de uso» y las dependencias del núcleo. Verificar que `grep -n 'Domain/' .ai/guidelines/iam/core.blade.php AGENTS.md` no da resultados
- [x] 4.2 Actualizar la sección Arquitectura del README: árbol de `app/Domains/Usuarios/` con las cuatro carpetas, la viñeta de CQRS lite (escrituras con repositorio, reglas de `Rules/` y excepciones de `Exceptions/`, sin entidades) y la de capas opcionales. Verificar que el README no menciona `Domain/` y que el árbol coincide con `ls app/Domains/Usuarios`

## 5. Verificación integral

- [x] 5.1 Con SQL Server en Docker (README, sección Tests), ejecutar `php artisan test`, `vendor/bin/pint --test` y `openspec validate nucleo-explicito --strict`. Verificar que todo pasa
- [x] 5.2 Comparar `php artisan route:list --path=api/iam --json` con la lista guardada en 1.1. Verificar que la salida es idéntica (métodos, URLs, middleware, nombres y acciones: los endpoints no se mueven)

## Workflow follow-up

- Revisión del usuario antes del commit; luego archivar el cambio (`openspec archive nucleo-explicito`), que aplica el delta a `openspec/specs/arquitectura-de-dominios/spec.md`, y verificar que la spec principal ya no nombra `Domain/`.
