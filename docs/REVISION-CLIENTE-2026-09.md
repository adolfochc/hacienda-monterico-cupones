# Correcciones de tarjetas y cuponera

Se implementaron las observaciones del Word «Web - tarjetas» y los criterios visuales del brandbook HMR de diciembre de 2023. Los archivos se trataron como referencias de contenido y diseño. No se utilizaron las credenciales incluidas en el Word ni se modificó el sitio publicado.

## Diseño aplicado

- Logotipo principal extraído de la página 17, conservando su forma y proporción. Aplicación en neutro oscuro o crema, sin reconstruirlo con texto.
- Libre Baskerville para títulos y Montserrat para lectura e interfaces. Las fuentes y sus licencias OFL se incluyen localmente.
- Paleta principal: crema `#FFF8E9`, terracota `#8F3B28`, marrón `#332820`, arena `#9C8A72`, oliva `#576443` y dorado `#BFB574`.
- Login con tratamiento marrón, enlace para activar tarjeta, campo de correo y control accesible para mostrar la contraseña.
- Registro y verificación explican que el código llega al correo electrónico. Se acepta Gmail u otro proveedor; no se exige crear un nombre de usuario.
- Cupones con corte lateral, borde interior, separación punteada y títulos crema, siguiendo el mockup móvil. Filtros por disponibilidad, comida, bebidas y canjes.
- Modal nativo con cierre por Escape, foco contenido y retorno al botón de origen. Código numérico alternativo, vigencia, condiciones y errores recuperables.

La foto provisional del login procede de una referencia fotográfica de la página 78 del brandbook. Los dos enlaces de Drive del Word no pudieron abrirse; debe sustituirse por una de las fotos finales del restaurante cuando esté accesible. Blackriver Bold no se distribuyó como archivo de fuente: se utiliza Libre Baskerville, también autorizada en el brandbook, sin simular Blackriver ni extraer una fuente incompleta del PDF.

## Funcionamiento

| Observación | Resultado |
| --- | --- |
| Correo o usuario | Autenticación por correo; se eliminó el acceso por código de socio. |
| Confirmación de suscripción | Texto explícito de verificación por email y paso 1/2. El envío real depende del SMTP configurado. |
| Todas las promociones | Carga específica con siete promociones, condiciones y fechas del Word. Conserva campañas anteriores. |
| Incentivo por cupón | Una participación por canje válido de la campaña, calculada desde el registro de canjes; no suma por abrir un QR ni por canjes repetidos. |
| Acceso de mozos | `/login` con cuenta `staff`; redirección a `/canjes`. Sin permisos sobre socios, tarjetas o creación/asignación de promociones. |
| Canje por código | Diez dígitos aleatorios con duración de cinco minutos. Se revisan los datos del socio antes de confirmar. |
| Canje único | Validación de estado, vigencia y socio activo dentro de una transacción con bloqueo de la asignación. Registro de operador, fecha y método. |
| Asignación general | Opción «todos los socios activos»; no duplica asignaciones existentes ni restaura cupones canjeados. Abarca los socios actuales, no altas futuras. |
| QR de acceso | QR descargable en Tarjetas y lotes. Dirige al registro; el código de activación sigue siendo individual e impreso en la tarjeta. |

La campaña fuente va del **16/11/2026 al 26/12/2026**, con sorteo el **28/12/2026** de una cena doble. La interfaz distingue promociones futuras de beneficios que ya se pueden canjear. Se registra la participación; no se implementó la selección automática de un ganador ni se inventaron bases adicionales del sorteo.

## Activación del entorno

La compilación genera los recursos en `public/build`. No se requiere una nueva migración para estos cambios.

Para cargar las siete promociones en la base configurada:

```powershell
php artisan db:seed --class=ClientCampaignSeeder
```

La carga se probó dos veces en SQLite para verificar que no duplica registros. El intento contra el MySQL local de este proyecto falló porque `127.0.0.1:3306` rechazó la conexión; **la campaña aún no se cargó en la base principal**. Arrancar el MySQL correspondiente y ejecutar el comando anterior. Después, asignar las promociones desde Cupones o incluirlas al generar nuevos lotes de tarjetas.

Para crear un acceso de personal sin conceder administración:

```powershell
php artisan hmr:staff
```

El comando solicita nombre, correo y contraseña temporal oculta, guarda un hash y exige cambiar la contraseña al ingresar. No se crearon cuentas de personal en la base principal.

Usar un almacenamiento de caché persistente entre peticiones para los códigos de canje: el controlador `file` existente funciona en una sola instancia; varias instancias requieren caché compartida. El QR de registro se genera con la URL de la aplicación: comprobar el dominio del entorno antes de imprimirlo.

## Validación

- Pruebas de autenticación, registro, lotes, cupones y correcciones del cliente sobre SQLite aislado: 24 pruebas y 115 aserciones correctas.
- Compilación Vite correcta. Persisten advertencias anteriores de Sass y del tamaño de los paquetes.
- Revisión en Chrome a 320, 375, 390, 768 y 1440 px: login, registro, cuponera, filtros y flujo de canje con datos ficticios.
- Canje por código numérico y desde una imagen QR completados desde la interfaz del personal. El QR usa una referencia aleatoria de 45 caracteres y cinco minutos de duración: se corrigió la densidad excesiva del QR cifrado anterior. No se probó una cámara física.
- Asignación general desde el panel administrativo y visualización del QR de registro verificadas en navegador. Sin errores JavaScript en las pantallas de socio y personal.
- La base de revisión se guarda en `tmp/brand-review/brand-review.sqlite`. Sus fechas de inicio se adelantaron exclusivamente para permitir pruebas de canje antes de noviembre. No representa datos reales del restaurante.

Pendientes externos: fotos definitivas de Drive, carga sobre MySQL disponible, creación de cuentas reales del personal y comprobación de entrega SMTP en el entorno final. Gmail ya se admite como dirección; un botón «Continuar con Google» sería una integración OAuth adicional que requiere las credenciales del cliente y no forma parte de lo activado.
