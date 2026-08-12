# Cómo dejar funcionando el panel de pedidos en cPanel

Sigue estos pasos en orden. No hace falta saber programar — es todo desde la interfaz de cPanel.

## 1. Crear la base de datos

En cPanel → **"Bases de datos MySQL"** (MySQL Database Wizard):
1. Crea una base de datos nueva, por ejemplo `repuestos`. cPanel le va a poner un prefijo automático (te va a quedar algo como `usuario_repuestos`).
2. Crea un usuario de base de datos con una clave segura.
3. Asígnale **todos los privilegios** sobre esa base de datos.
4. Anota los 3 datos que te quedan: nombre completo de la base, usuario completo, y la clave. Los vas a necesitar en el paso 3.

## 2. Crear las tablas

1. Entra a **phpMyAdmin** desde cPanel.
2. Selecciona la base de datos que creaste.
3. Ve a la pestaña **SQL**.
4. Abre el archivo `db/schema.sql` de este proyecto, copia todo su contenido, pégalo ahí y dale a "Continuar/Ejecutar".
5. Deberías ver dos tablas nuevas: `orders` y `admin_users`.

## 3. Subir los archivos del sitio

Sube **todo** el contenido de este proyecto (incluidas las carpetas `api/`, `admin/`, `db/`) a la carpeta donde cPanel sirve tu sitio (normalmente `public_html`), vía el **Administrador de Archivos** de cPanel o por FTP.

> El archivo `api/db.php` real (con las credenciales) **no está en el repositorio** — lo vas a crear directo en el servidor en el siguiente paso.

## 4. Completar las credenciales de la base de datos

1. En el Administrador de Archivos de cPanel, entra a la carpeta `api/`.
2. Duplica `db.example.php` y renombra la copia a `db.php` (mismo directorio).
3. Edita `db.php` y reemplaza los 4 valores de ejemplo por los datos reales del paso 1:
   ```php
   $DB_HOST = 'localhost';
   $DB_NAME = 'usuario_repuestos';   // el nombre real que te dio cPanel
   $DB_USER = 'usuario_dbuser';      // el usuario real
   $DB_PASS = 'tu_clave_real';
   ```
4. Guarda.

## 5. Crear el usuario del panel de administración

1. Visita en el navegador: `https://tudominio.cl/db/create_admin.php?user=TU_USUARIO&pass=TU_CLAVE`
   - Reemplaza `TU_USUARIO` y `TU_CLAVE` por los que quieras usar para entrar al panel (la clave debe tener al menos 8 caracteres).
   - Ejemplo: `https://tudominio.cl/db/create_admin.php?user=admin&pass=UnaClaveSegura123`
2. Deberías ver el mensaje "Usuario creado/actualizado correctamente".
3. **Importante:** borra el archivo `db/create_admin.php` del servidor apenas termines este paso (o renómbralo). Si queda publicado, cualquiera podría crear o resetear el usuario del panel.

## 6. Probar el panel

1. Ve a `https://tudominio.cl/admin/login.php` e ingresa con el usuario/clave que creaste.
2. Deberías ver el panel de pedidos (vacío todavía).
3. Prueba abrir `https://tudominio.cl/admin/index.php` en una ventana privada/incógnito, **sin haber iniciado sesión** — debe redirigirte a login, no mostrar datos. Si ves la tabla de pedidos sin loguearte, avísame de inmediato.

## 7. Probar el flujo completo

1. Entra al sitio público y haz un pedido de prueba (puedes usar datos ficticios).
2. Confirma que el pedido aparece en `admin/index.php`.
3. Prueba cambiar su estado (pendiente → despachado → entregado) desde el panel.

## 8. Activar HTTPS (si no lo tienes ya)

En cPanel → **SSL/TLS Status**, activa **AutoSSL** si el sitio todavía no tiene candado en el navegador. El login del panel usa cookies de sesión seguras que dependen de HTTPS.

---

Cualquier paso que te trabe, mándame el mensaje de error exacto (pantallazo sirve) y seguimos desde ahí.
