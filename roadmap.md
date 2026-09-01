# Roadmap de implementación — registro por tarjeta y gestión de cuponeras

## 1. Objetivo

Adaptar la plataforma de Hacienda Monterrico para que el socio o representante de una familia pueda autorregistrarse usando el código único impreso en una tarjeta física, verificar su correo electrónico, crear su propia contraseña y recibir una cuponera vinculada exclusivamente a su cuenta.

El administrador conservará el control de la emisión de tarjetas, la administración de socios, la consulta de cuponeras, el consumo de cupones y las exportaciones a Excel. La creación manual de socios desde el panel administrativo quedará deshabilitada en la primera versión.

## 2. Alcance confirmado

- Se entrega una tarjeta física a una familia, persona o socio.
- Cada tarjeta incluye:
  - Un código de activación único en texto.
  - Un código QR que dirige a la página pública de registro. El QR no contiene credenciales ni activa por sí solo la tarjeta.
- El socio se autorregistra con nombre completo, celular, correo, contraseña y código de activación.
- El sistema valida que el código exista, esté habilitado y no haya sido utilizado.
- El correo se confirma mediante un código temporal de un solo uso (OTP).
- Una tarjeta y su cuponera solo pueden quedar vinculadas a un socio.
- Después de completar la activación, el código de la tarjeta no puede volver a utilizarse.
- El socio ingresa posteriormente con correo y contraseña.
- Cada cuponera contiene la misma cantidad y composición de cupones.
- Cada cupón asignado tiene una identidad propia y se consume individualmente.
- El administrador registra los canjes y puede bloquear socios, tarjetas o cupones cuando corresponda.
- El administrador puede exportar a Excel el resumen operativo y el listado de socios.
- La interfaz debe usar los logotipos, colores y tipografía de Hacienda Monterrico.
- SMS queda previsto como mejora posterior; el canal obligatorio de la primera versión es correo.

## 3. Glosario funcional

| Concepto | Definición |
| --- | --- |
| Tarjeta | Medio físico entregado al socio o familia. Posee un código único para iniciar el registro. |
| Código de activación | Código de un solo uso impreso en la tarjeta. Vincula la tarjeta con una cuenta. |
| Cuponera | Conjunto de cupones asignado al socio al activar la tarjeta. |
| Cupón | Beneficio individual dentro de una cuponera. Puede estar disponible, consumido o anulado. |
| OTP | Código temporal enviado al correo para verificar que la dirección pertenece al socio. |
| Socio | Usuario titular de la tarjeta y la cuponera. Puede representar a una familia. |
| Canje | Operación mediante la cual un administrador marca un cupón como consumido. |

En código y base de datos se debe evitar llamar `coupon_code` al código de la tarjeta. El nombre recomendado es `activation_code` para distinguirlo del identificador individual de un cupón.

## 4. Situación actual del proyecto

La aplicación ya cuenta con:

- Laravel 11, Jetstream/Fortify, Sanctum, Inertia y Vue 3.
- Roles `admin` y `member`.
- Gestión administrativa de socios.
- Catálogo de cupones.
- Asignaciones en `coupon_user` con estados `available` y `redeemed`.
- Canje manual y canje por QR con bloqueo transaccional.
- Panel del socio con sus cupones.
- Verificación estándar de correo de Laravel.
- Pruebas Pest para registro, socios, cupones, canjes y QR.

Brechas frente al nuevo requerimiento:

- El administrador crea actualmente a los socios y genera una contraseña temporal.
- El registro público no exige una tarjeta válida.
- No existen entidades explícitas para tarjeta o cuponera.
- La asignación actual une directamente usuario y tipo de cupón, sin representar la pertenencia a una cuponera física.
- La restricción única `coupon_id + user_id` impide que una misma cuponera contenga más de una unidad del mismo tipo de beneficio.
- No existe OTP numérico por correo con expiración, reenvío y límite de intentos.
- No existen importación/generación de tarjetas en lote ni exportaciones a Excel.
- `members.store` y el botón “Nuevo socio” contradicen la decisión de deshabilitar el alta administrativa.

## 5. Arquitectura de datos propuesta

### 5.1 `booklet_templates`

Define la composición estándar de las cuponeras y permite cambiarla en campañas futuras sin alterar cuponeras ya emitidas.

Campos mínimos:

- `id`
- `name`
- `description` nullable
- `version`
- `is_active`
- `created_at`, `updated_at`

Relación: un template contiene varios tipos de cupón mediante `booklet_template_items`.

### 5.2 `booklet_template_items`

- `id`
- `booklet_template_id`
- `coupon_id`
- `quantity` con valor mínimo 1
- `position` para ordenar los beneficios
- restricción única según la regla de composición definida

### 5.3 `membership_cards`

Representa cada tarjeta física emitida.

- `id`
- `activation_code_hash` único
- `activation_code_last4` para identificación administrativa sin revelar el código completo
- `booklet_template_id`
- `batch_reference` o relación con un lote
- `status`: `available`, `reserved`, `activated`, `blocked`, `cancelled`
- `activated_by_user_id` nullable y único
- `activated_at` nullable
- `expires_at` nullable
- `created_by` nullable
- `created_at`, `updated_at`

El código completo no debe almacenarse en texto plano. Al importarlo o generarlo se normaliza, se calcula un hash/HMAC consultable y solo se conserva una parte enmascarada para soporte.

### 5.4 `booklets`

Representa la cuponera concreta de un socio.

- `id`
- `membership_card_id` único
- `user_id` único para cumplir la regla actual de una cuponera por socio
- `booklet_template_id`
- `status`: `active`, `exhausted`, `blocked`, `cancelled`
- `activated_at`
- `created_at`, `updated_at`

La composición se copia al momento de activación; no debe depender dinámicamente del template después de ser emitida.

### 5.5 Evolución de `coupon_user`

Mantener inicialmente la tabla para reducir riesgo, pero convertirla conceptualmente en “cupón emitido”:

- Añadir `booklet_id`.
- Añadir `serial_code_hash` o un identificador público aleatorio si cada cupón físico necesita identificación propia.
- Añadir `position` y opcionalmente `sequence_number`.
- Ampliar estados a `available`, `redeemed`, `cancelled`.
- Conservar `redeemed_at`, `redeemed_by` y `redemption_note`.
- Eliminar la restricción única `coupon_id + user_id` y sustituirla por una restricción que permita cantidades repetidas sin duplicar el mismo cupón emitido.
- Añadir índices para `booklet_id + status`, `user_id + status` y consultas de auditoría.

Antes de cambiar la restricción se debe definir una migración de compatibilidad para las asignaciones existentes. Cada socio actual recibirá una cuponera heredada y sus asignaciones serán vinculadas a ella sin perder estados ni fechas de canje.

### 5.6 `email_verification_codes`

- `id`
- `registration_token` único
- `email`
- `code_hash`
- `expires_at`
- `attempts`
- `resend_count`
- `last_sent_at`
- `verified_at` nullable
- `consumed_at` nullable
- `payload` cifrado o referencia segura al registro pendiente
- `created_at`, `updated_at`

Nunca guardar el OTP en texto plano. El código debe caducar, ser de un solo uso y quedar invalidado al emitir uno nuevo.

### 5.7 `card_batches` — recomendado

Permite administrar los bloques físicos entregados a marketing.

- `id`
- `name` o referencia de lote
- `booklet_template_id`
- `quantity`
- `status`: `draft`, `generated`, `active`, `closed`
- `created_by`
- marcas de tiempo

Facilita generar/importar códigos, exportarlos para impresión y medir activaciones por lote.

## 6. Flujo de autorregistro

### Paso 1 — acceso por QR

- El QR dirige a una URL pública estable, por ejemplo `/registro`.
- Puede precargar el código mediante un parámetro opcional, pero no debe activar la tarjeta ni exponer datos internos.
- Si el código se incluye en la URL, evitar registrarlo en logs y analítica; es preferible que el QR solo abra la página y el usuario escriba el código impreso.

### Paso 2 — validación inicial

El formulario solicita:

- Nombre y apellido del titular.
- Celular.
- Correo electrónico.
- Código de activación.
- Contraseña y confirmación.
- Aceptación de términos y política de privacidad, si corresponde.

Validaciones:

- Correo único y normalizado.
- Contraseña según las reglas existentes de Fortify.
- Celular normalizado; obligatorio según el requerimiento visual, aunque SMS todavía no esté activo.
- Tarjeta existente, disponible, no vencida, no bloqueada y no vinculada.
- Mensajes públicos genéricos para no facilitar la enumeración de códigos.

### Paso 3 — envío y confirmación del OTP

- Crear un registro pendiente y enviar un OTP al correo.
- Pantalla separada para ingresar el código.
- Expiración recomendada: 10 minutos.
- Máximo recomendado: 5 intentos por código.
- Reenvío después de 60 segundos, con límite por correo, código de tarjeta e IP.
- Un reenvío invalida el OTP anterior.
- No crear todavía el usuario definitivo ni consumir la tarjeta.

### Paso 4 — activación atómica

Al verificar el OTP, ejecutar una única transacción de base de datos:

1. Bloquear la tarjeta con `lockForUpdate()`.
2. Volver a comprobar que continúa disponible.
3. Crear el usuario con rol `member`, correo verificado y contraseña elegida.
4. Generar el `member_code` existente.
5. Crear la cuponera.
6. Copiar desde el template todos los cupones emitidos.
7. Vincular tarjeta, cuponera y socio.
8. Marcar la tarjeta como `activated`.
9. Marcar el OTP como consumido.
10. Autenticar al socio y mostrar la pantalla de activación exitosa.

La transacción y los índices únicos deben impedir que dos solicitudes simultáneas activen la misma tarjeta.

### Paso 5 — acceso posterior

- Inicio de sesión mediante correo y contraseña.
- Mantener recuperación de contraseña por correo.
- Eliminar para nuevos socios la obligación de cambiar una contraseña temporal.
- Conservar temporalmente `member_code` como forma de inicio de sesión para socios heredados, hasta definir una estrategia de migración.

## 7. Cambios de backend

### Modelos y relaciones

- Crear `MembershipCard`, `Booklet`, `BookletTemplate`, `BookletTemplateItem`, `CardBatch` y `EmailVerificationCode`.
- Agregar relaciones correspondientes a `User`, `Coupon` y `CouponAssignment`.
- Definir enums PHP o constantes centralizadas para estados; evitar strings distribuidos por controladores y vistas.
- Agregar scopes para tarjetas disponibles, cupones canjeables y cuponeras activas.

### Servicios y acciones

Crear componentes con responsabilidades pequeñas:

- `ActivationCodeService`: normalización, hash, validación y generación segura.
- `RegistrationOtpService`: emisión, envío, verificación, expiración y límites.
- `ActivateMembershipAction`: transacción de activación completa.
- `CreateBookletFromTemplateAction`: copia inmutable de la composición.
- `RedeemCouponAction`: centraliza la lógica hoy incluida en `CouponController`.
- `CardBatchService`: generación/importación y exportación de códigos para impresión.
- `MemberExportService` y `OperationalSummaryExportService`.

### Solicitudes y autorización

- Usar Form Requests para registro inicial, OTP, reenvío, lotes, tarjetas, cuponeras y canjes.
- Mantener middleware de administrador en todas las operaciones de emisión, bloqueo, exportación y canje.
- Añadir Policies para tarjetas, cuponeras y asignaciones cuando haya acceso por modelo.
- Verificar explícitamente que el socio solo consulte su propia cuponera.

### Rutas previstas

Públicas:

- `GET /registro`
- `POST /registro/iniciar`
- `GET /registro/verificar/{registrationToken}`
- `POST /registro/verificar`
- `POST /registro/reenviar-codigo`

Socio autenticado:

- `GET /mi-cuponera`
- Rutas existentes de visualización/QR, adaptadas a `booklet_id`.

Administrador:

- Gestión de lotes y tarjetas.
- Consulta de cuponeras.
- Bloqueo/reactivación de tarjetas y cuponeras.
- Canje manual o por QR.
- Exportaciones de socios y resumen operativo.

Los nombres definitivos deben respetar el patrón actual de rutas con nombre.

## 8. Cambios de interfaz

### Registro público

- Rehacer `resources/js/Pages/Auth/Register.vue` con la identidad de Hacienda Monterrico.
- Eliminar textos, marcas y accesos sociales heredados de Velzon.
- Diseñar estados claros: formulario, envío, verificación, procesando y éxito.
- Mostrar errores específicos al usuario sin revelar si un código válido pertenece a otra persona.
- Asegurar funcionamiento móvil, ya que el acceso principal será mediante QR.
- Añadir indicador de fortaleza de contraseña, reenvío temporizado y correo enmascarado.

### Panel del administrador

- Retirar el botón y modal “Nuevo socio”.
- Mantener búsqueda, bloqueo y consulta de socios.
- Incorporar filtros por estado de activación, lote, fecha y disponibilidad de cupones.
- Añadir secciones para lotes, tarjetas y cuponeras.
- Permitir al administrador consultar la trazabilidad completa sin ver el código de activación completo.
- Añadir botones de exportación con filtros equivalentes a los de pantalla.

### Panel del socio

- Presentar la cuponera como unidad principal.
- Mostrar total, disponibles, consumidos y vigencia.
- Mostrar cada cupón con estado y fecha de consumo.
- Mantener QR dinámico por cupón solo si el flujo operativo aprobado exige que el administrador escanee el teléfono del socio.

### Identidad visual

- Consolidar logotipos oficiales en `resources/images`.
- Centralizar colores y tipografías en variables SCSS/CSS.
- Revisar contraste, tamaños táctiles, mensajes y diseño responsive.
- Reemplazar cualquier texto o recurso visual residual de la plantilla Velzon.

## 9. Gestión administrativa de tarjetas y lotes

### Generación/importación

- Permitir crear un lote indicando template, cantidad y vencimiento opcional.
- Generar códigos con entropía suficiente, sin secuencias predecibles y excluyendo caracteres ambiguos.
- Alternativamente, importar un archivo CSV/XLSX suministrado por marketing.
- Validar duplicados dentro del archivo y contra la base de datos.
- Mostrar un resumen antes de confirmar la carga.

### Archivo para impresión

Exportar por lote:

- Referencia de lote.
- Código de activación en texto.
- URL que debe codificarse en el QR.
- Identificador interno o correlativo de impresión.

Esta exportación contiene secretos de activación y debe generarse solo para administradores autorizados, quedar auditada y no almacenarse indefinidamente en una URL pública.

### Operaciones permitidas

- Activar un lote.
- Bloquear o cancelar una tarjeta no utilizada.
- Bloquear una tarjeta ya vinculada sin borrar al socio ni el historial.
- Reemitir una tarjeta solo mediante un proceso administrativo explícito y auditable.
- No permitir eliminar físicamente tarjetas activadas, cuponeras o canjes.

## 10. Canje de cupones

- Reutilizar la transacción y `lockForUpdate()` ya existentes.
- Validar estado del socio, cuponera, cupón y vigencia antes de consumir.
- Registrar administrador, fecha, nota y método de canje (`manual`, `qr`).
- Garantizar idempotencia: un cupón consumido no puede volver a canjearse.
- Definir si el código individual del cupón estará impreso físicamente. Mientras no se confirme, conservar el canje actual desde el panel y el QR dinámico del socio como opciones técnicas, sin incluir un QR individual en impresión.
- Actualizar automáticamente la cuponera a `exhausted` cuando ya no tenga cupones disponibles.

## 11. Exportaciones a Excel

### Listado de socios

Columnas mínimas:

- Código de socio.
- Nombre completo.
- Correo.
- Celular.
- Estado.
- Fecha de registro/activación.
- Referencia de tarjeta enmascarada.
- Lote.
- Total de cupones.
- Disponibles.
- Consumidos.

### Resumen operativo

- Tarjetas generadas, disponibles, activadas, bloqueadas y canceladas.
- Cuponeras activas, agotadas y bloqueadas.
- Cupones emitidos, disponibles, consumidos y anulados.
- Canjes por periodo.
- Activaciones por lote y periodo.

### Decisión técnica

- Para `.xlsx`, incorporar una dependencia mantenida y compatible con Laravel 11, preferentemente Laravel Excel/PhpSpreadsheet.
- Ejecutar exportaciones grandes mediante cola para evitar tiempos de espera.
- Aplicar los mismos filtros de pantalla a la exportación.
- Evitar incluir hashes, códigos completos, contraseñas, OTP o datos internos sensibles.

## 12. Seguridad y privacidad

- Hash/HMAC para códigos de activación y códigos OTP.
- Códigos de activación aleatorios y no enumerables.
- Límite de intentos por código, correo e IP.
- Protección CSRF en todos los formularios web.
- Transacciones e índices únicos contra activación o canje doble.
- Mensajes públicos que no permitan descubrir qué códigos o correos existen.
- Enmascaramiento de tarjeta y correo en pantallas de soporte/verificación.
- No escribir códigos, OTP ni contraseñas en logs.
- Auditoría de generación de lotes, exportaciones, bloqueos, activaciones y canjes.
- Consentimiento y política de privacidad para correo y celular.
- Retención y limpieza programada de registros pendientes y OTP vencidos.
- Backups y prueba de restauración antes de migrar datos productivos.

## 13. Correo y futura verificación por SMS

### Primera versión

- Configurar el remitente y dominio de correo de producción.
- Crear notificación de OTP con plantilla de Hacienda Monterrico.
- Enviar mediante cola.
- Registrar entrega/fallo sin registrar el código.
- Incluir alternativa de soporte si el correo no llega.

### Segunda versión opcional

- Crear una interfaz `VerificationChannel` para que correo y SMS compartan el flujo.
- Evaluar proveedor, costos, cobertura en Perú, plantillas, consentimiento y límites antifraude.
- Definir si SMS será alternativo al correo o un segundo factor adicional.
- No activar SMS hasta que esa decisión de negocio esté documentada.

## 14. Migración de datos existentes

1. Realizar backup verificado.
2. Crear las tablas nuevas sin eliminar ni renombrar todavía las actuales.
3. Crear un template “Cuponera heredada”.
4. Crear una cuponera heredada por cada socio existente.
5. Asociar sus filas de `coupon_user` conservando estados, fechas, responsable y notas.
6. Crear tarjetas migradas en estado especial o permitir `membership_card_id` nullable únicamente durante la transición.
7. Validar conteos antes y después:
   - socios;
   - asignaciones;
   - disponibles;
   - canjeados;
   - responsables y fechas.
8. Ejecutar pruebas de regresión.
9. Aplicar restricciones `NOT NULL` cuando toda la información esté migrada.
10. Retirar el flujo administrativo antiguo solo después de validar producción.

No modificar migraciones ya aplicadas; todos los cambios deben agregarse mediante migraciones nuevas y reversibles.

## 15. Estrategia de pruebas

### Registro

- Tarjeta válida inicia el registro.
- Código inexistente, bloqueado, vencido, cancelado o utilizado es rechazado.
- Correo duplicado es rechazado.
- OTP correcto activa la cuenta.
- OTP incorrecto, vencido, reutilizado o sustituido es rechazado.
- Límites de intentos y reenvíos funcionan.
- Dos solicitudes concurrentes no activan la misma tarjeta.
- Un fallo al crear cupones revierte usuario, cuponera y tarjeta.

### Cuponeras y cupones

- La composición se copia exactamente desde el template.
- Todas las cuponeras de un mismo lote contienen la cantidad configurada.
- Un socio no puede consultar la cuponera de otro.
- Un cupón solo puede consumirse una vez, incluso con solicitudes concurrentes.
- El canje registra administrador, fecha, método y nota.
- La cuponera pasa a agotada al consumir el último cupón.

### Administración y exportaciones

- Un usuario no administrador no accede a lotes, tarjetas, canjes ni exportaciones.
- El administrador no puede crear socios desde la interfaz ni por la ruta antigua.
- Los filtros y totales coinciden con el archivo exportado.
- Los archivos no contienen secretos.
- La importación detecta duplicados y filas inválidas.

### Calidad

- Ejecutar Pest/PHPUnit para backend.
- Añadir pruebas de componentes o flujo crítico de Vue cuando se configure el framework correspondiente.
- Ejecutar `npm run build` en cada entrega.
- Probar registro y canje en móvil real o emulación móvil.
- Probar correo en entorno de staging antes de producción.

## 16. Fases de implementación

### Fase 0 — cierre funcional y preparación

- Confirmar quién representa al “socio”: individuo o familia, y si se requieren datos adicionales de familiares.
- Confirmar cantidad y composición exacta de cupones.
- Confirmar si cada cupón físico tendrá código/QR impreso o si el canje se realizará desde el panel/QR del socio.
- Confirmar vigencia de tarjeta, cuponera y cupones.
- Confirmar formato y columnas de los Excel.
- Confirmar proveedor y configuración de correo.
- Documentar criterios de aceptación y preparar backup/staging.

**Salida:** requerimientos cerrados y dataset de prueba aprobado.

### Fase 1 — base de datos y dominio

- Crear migraciones, modelos, enums, relaciones y factories.
- Implementar templates, tarjetas, cuponeras, lotes y OTP.
- Preparar migración de asignaciones existentes.
- Añadir pruebas unitarias y de integridad.

**Salida:** dominio persistente y migración reversible sin cambios visibles para usuarios.

### Fase 2 — autorregistro y verificación por correo

- Implementar rutas, Form Requests, rate limits y servicios.
- Crear flujo de OTP y notificaciones en cola.
- Implementar activación transaccional.
- Adaptar Fortify para conservar login y recuperación de contraseña.
- Construir las pantallas móviles de registro, verificación y éxito.

**Salida:** un socio puede activar una tarjeta y entrar con correo/contraseña.

### Fase 3 — cuponera del socio y canjes

- Adaptar dashboard del socio a la entidad cuponera.
- Migrar el canje actual al nuevo servicio y relaciones.
- Mantener canje manual y QR según la decisión funcional final.
- Añadir auditoría y estado agotado.

**Salida:** cupones visibles y consumibles sin doble canje.

### Fase 4 — administración de lotes, tarjetas y cuponeras

- Crear pantallas de generación/importación de lotes.
- Crear consultas, filtros, bloqueos y trazabilidad.
- Retirar alta manual de socios y su ruta `POST /socios`.
- Mantener gestión del estado del socio.

**Salida:** marketing/administración controla el ciclo de las tarjetas sin crear cuentas manualmente.

### Fase 5 — exportaciones

- Incorporar generación de `.xlsx`.
- Implementar listado de socios y resumen operativo.
- Añadir colas para volúmenes grandes y controles de acceso.
- Verificar conteos y privacidad.

**Salida:** archivos Excel validados por el cliente.

### Fase 6 — identidad visual, QA y salida a producción

- Aplicar logos, colores y tipografía oficiales en registro y paneles modificados.
- Eliminar residuos de la plantilla Velzon en las pantallas del alcance.
- Ejecutar pruebas completas, build y revisión responsive/accesibilidad.
- Ensayar migración con copia de producción.
- Preparar rollback, monitoreo y manual operativo.
- Desplegar y validar correo, colas, cron, registros y métricas.

**Salida:** versión productiva estable y monitoreada.

### Fase 7 — SMS opcional

- Seleccionar proveedor.
- Implementar canal SMS sobre la abstracción preparada.
- Aplicar consentimiento, límites y monitoreo de costos/fraude.
- Definir y probar recuperación cuando el usuario cambia de número.

**Salida:** verificación por SMS disponible según la regla de negocio aprobada.

## 17. Criterios de aceptación del MVP

- El QR de la tarjeta abre correctamente el registro en móvil.
- Solo una tarjeta válida y disponible permite iniciar el proceso.
- El correo debe verificarse antes de crear y activar definitivamente la cuenta.
- Una tarjeta no puede activar más de una cuenta.
- La cuenta, tarjeta, cuponera y cupones se crean de forma atómica.
- El socio inicia sesión con correo y la contraseña que eligió.
- El socio solo ve su propia cuponera.
- El administrador puede consultar socios, tarjetas, cuponeras y estados.
- El administrador puede consumir un cupón y el sistema impide un segundo consumo.
- El administrador no puede crear socios manualmente.
- Las exportaciones Excel coinciden con los datos filtrados y no exponen secretos.
- Los datos existentes se conservan después de la migración.
- Las pruebas automáticas, el build frontend y el ensayo de migración terminan correctamente.

## 18. Dependencias y decisiones pendientes

Bloquean partes concretas de la implementación:

1. Cantidad y composición definitiva de cada cuponera.
2. Confirmación de si el cupón físico tendrá un código/QR individual impreso.
3. Definición exacta de “familia” y datos que se deben registrar además del titular.
4. Vigencia de las tarjetas y beneficios.
5. Formatos finales de importación para marketing y exportación administrativa.
6. Credenciales y proveedor de correo productivo.
7. Política para socios actuales que no tienen tarjeta.
8. Decisión sobre mantener indefinidamente el login por `member_code`.
9. Canal y proceso de soporte ante tarjeta perdida, correo incorrecto o cambio de titular.
10. Aprobación posterior del alcance y proveedor de SMS.

## 19. Orden recomendado para comenzar

No iniciar por las pantallas. El primer incremento debe ser:

1. Cerrar los puntos pendientes de la Fase 0.
2. Diseñar y aprobar el diagrama de datos.
3. Crear pruebas de comportamiento para activación única y canje único.
4. Implementar migraciones y servicios de dominio.
5. Implementar el flujo público de registro y OTP.
6. Adaptar los paneles sobre el modelo ya estabilizado.

Este orden reduce el riesgo de rehacer interfaces y protege la información de socios y canjes que ya existe.

## 20. Estado de implementación — 1 de septiembre de 2026

Implementado en el MVP actual:

- Dominio de templates, lotes, tarjetas, cuponeras y OTP.
- Migración compatible de socios y asignaciones existentes.
- Autorregistro exclusivo mediante tarjeta disponible.
- Verificación de correo mediante OTP con expiración, intentos y reenvíos limitados.
- Activación transaccional y protección contra doble registro.
- Generación de lotes y descarga única de códigos para impresión.
- Códigos almacenados únicamente como hash con últimos cuatro caracteres para soporte.
- Administración y bloqueo de tarjetas.
- Deshabilitación del alta manual de socios.
- Cupones repetibles por cuponera, canje único y agotamiento automático.
- Exportación de socios y resumen en CSV UTF-8 compatible con Excel.
- Pruebas automáticas de registro, concurrencia lógica, lotes, autorización y regresión.

Pendiente fuera del MVP:

- Exportación nativa `.xlsx` si el cliente no acepta CSV abierto desde Excel.
- Importación de lotes desde archivos entregados por marketing.
- Auditoría persistente de exportaciones y cambios administrativos.
- Canal SMS, sujeto a proveedor y regla funcional aprobados.
- Definición e impresión de un código o QR físico individual por cada cupón.
