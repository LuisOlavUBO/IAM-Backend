## 1. Núcleo

- [x] 1.1 `Domain/ReglasDePersonas` (escalamiento sobre la persona con datos perezosos y último administrador al dar de baja, D3, D4) y su test unitario
- [x] 1.2 `IPersonasRepository` + `PersonasRepository`: estado de la persona, `darDeBaja`, `reactivar`, `cambiarClave` y si tiene roles de `iam-admin`; test del repositorio

## 2. Casos de uso

- [x] 2.1 `DarDeBaja` (`iam:make-feature --command`): reglas auditadas, transacción con `usuario.baja`, revocación después del commit, idempotente (D5); ruta `POST usuarios/{uuid}/baja` (`usuarios.baja`)
- [x] 2.2 `Reactivar`: reglas, `usuario.alta`, idempotente; ruta `POST usuarios/{uuid}/reactivacion` (`usuarios.reactivacion`)
- [x] 2.3 `ReiniciarClave`: reglas, `ClaveTemporal`, `usuario.clave.reinicio` sin valores, revocación, `no-store` (D6); ruta `POST usuarios/{uuid}/clave` (`usuarios.clave`)
- [x] 2.4 Tests de feature por requisito: permiso y 404; baja (con y sin revocación, ya de baja, conserva roles); reactivación (y ya activa); reinicio (clave nueva válida, anterior inválida, persona solo Microsoft, sin clave en la auditoría, `no-store`); protecciones (uno mismo, mesa sobre administrador en las tres acciones, último administrador) con su entrada `rechazado` y sin llamar a API-Auth

## 3. Documentación

- [x] 3.1 OpenAPI (tres operaciones, respuestas y códigos) y Bruno (`Usuarios/Dar de baja`, `Reactivar`, `Reiniciar clave`); `DocsTest` en verde

## 4. Frontend (IAM-Frontend)

- [x] 4.1 `usuarios.service.ts`: `darDeBaja`, `reactivar`, `reiniciarClave`; tipos
- [x] 4.2 `detalle-usuario` (D7): acciones de cuenta con confirmación, clave temporal mostrada una vez, aviso de revocación con «Reintentar»; ocultas en el propio perfil y sin el permiso; tests y README

## 5. Verificación

- [x] 5.1 Pint, suites completas, `openspec validate usuarios-baja-y-clave --strict`
- [x] 5.2 En el portal con la base de desarrollo, sobre las personas de prueba que indique el usuario: baja (y que no pueda iniciar sesión), reactivación y reinicio de clave, con sus entradas en Auditoría
