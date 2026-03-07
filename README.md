# Acción en Territorio - Plataforma PHP

Proyecto base en PHP 8.1+ para la plataforma del Equipo de Promoción y Prevención (Acción en Territorio), con enrutador ligero, vistas con Bootstrap 5 y sistema de autenticación por roles.

## Requisitos

- PHP 8.1 o superior
- Composer
- Servidor web Apache (WAMP en local) con `mod_rewrite` habilitado
- MySQL/MariaDB

## Instalación rápida

1. **Clonar o copiar el proyecto** dentro de tu carpeta de WAMP, por ejemplo:

   - Carpeta: `c:/wamp64/www/homo`
   - DocumentRoot del virtual host: `c:/wamp64/www/homo/public`

2. **Instalar dependencias de PHP**:

   ```bash
   composer install
   ```

3. **Crear archivo de entorno**:

   Copia el archivo `.env.example` a `.env` y ajusta los valores:

   - Datos de conexión a la base de datos (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
   - Entorno de la app (`APP_ENV`, `APP_DEBUG`)
   - Zona horaria (`APP_TIMEZONE`)

4. **Crear la base de datos y tablas de autenticación**:

   - Crear la base de datos, por ejemplo `homo_db`.
   - Ejecutar el script SQL:

   ```sql
   SOURCE database/migrations/001_create_auth_tables.sql;
   ```

5. **Configurar el virtual host en Apache** (ejemplo):

   ```apache
   <VirtualHost *:80>
       ServerName accion-territorio.local
       DocumentRoot "c:/wamp64/www/homo/public"

       <Directory "c:/wamp64/www/homo/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

6. **Abrir la aplicación**:

   - Navega a `http://accion-territorio.local` (o la URL que configures).

## Próximos pasos

- Implementar autenticación real contra la tabla `users` usando contraseñas encriptadas (`password_hash`).
- Añadir gestión de roles desde la base de datos para controlar el acceso a cada módulo.
- Crear los módulos específicos (Asesores, Programa, Utilidades, etc.) usando rutas protegidas y vistas modernas con Bootstrap y SweetAlert2.

