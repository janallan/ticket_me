# Deployment

How to install and update the helpdesk on a Linux server for real users. For a development setup on your own machine, see [LOCAL-SETUP.md](LOCAL-SETUP.md) in the README.

A server install differs from a development one in a few ways that matter: production `.env` values (`APP_DEBUG=false`, a real mailer, a database queue), dependencies without development tools, Nginx with HTTPS and upload limits, a supervised queue worker so emails are sent, writable and backed-up `storage`, cached configuration, and a changed admin password.

- [First install](#first-install)
- [Deploying an update](#deploying-an-update)

---

## First install

These steps are for a Linux server with Nginx, PHP-FPM, MySQL and Supervisor. Adjust paths and the PHP version to match your server.

1. **Install the requirements** listed in the [README](../README.md#requirements), plus Nginx, PHP-FPM and Supervisor. On Ubuntu:
   ```bash
   sudo apt update
   sudo apt install -y software-properties-common git unzip curl
   sudo add-apt-repository -y ppa:ondrej/php
   sudo apt update
   sudo apt install -y nginx supervisor mysql-server \
       php8.5-fpm php8.5-cli php8.5-bcmath php8.5-curl php8.5-intl php8.5-mbstring \
       php8.5-mysql php8.5-xml php8.5-zip
   ```
   Install Composer and Node.js as in [LOCAL-SETUP.md](LOCAL-SETUP.md) (steps 2 and 3). Node.js is only needed to build the assets, so you can also build them in CI or on another machine and copy `public/build`.
2. **Create a database and user:**
   ```sql
   CREATE DATABASE ticket_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'ticket'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT ALL PRIVILEGES ON ticket_db.* TO 'ticket'@'localhost';
   ```
3. **Get the code:**
   ```bash
   cd /var/www
   git clone <repository-url> ticket
   cd ticket
   ```
4. **Install the dependencies and build the assets:**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```
5. **Create `.env`:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   and set at least:
   ```dotenv
   APP_NAME="Ticket"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://ticket.example.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ticket_db
   DB_USERNAME=ticket
   DB_PASSWORD=a-strong-password

   QUEUE_CONNECTION=database

   MAIL_MAILER=smtp
   MAIL_HOST=smtp.example.com
   MAIL_PORT=587
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=helpdesk@example.com
   MAIL_FROM_NAME="${APP_NAME}"
   ```
   In production, passwords must be at least 12 characters with mixed case, letters, numbers and symbols, and must not appear in known data breaches.
6. **Create the tables and starting data** (first install only):
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```
   Then sign in as **admin@example.com** / **password** and **change that password straight away**, or create your own administrator and deactivate the seeded one.
7. **Make the writable folders writable** by the web server user:
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R ug+rwX storage bootstrap/cache
   ```
   Attachments are stored on the private disk in `storage/app/private`. Keep `storage` on persistent storage and include it in your backups, together with the database.
8. **Cache the configuration** for speed:
   ```bash
   php artisan optimize
   ```
9. **Configure Nginx** with the document root on `public/`:
   ```nginx
   server {
       listen 80;
       server_name ticket.example.com;
       root /var/www/ticket/public;
       index index.php;

       client_max_body_size 60M;   # up to 5 attachments of 10 MB each, see config/tickets.php

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include fastcgi_params;
           fastcgi_pass unix:/run/php/php8.5-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```
   Add HTTPS (for example with Certbot), and raise PHP's `upload_max_filesize` and `post_max_size` to match the attachment limits.
10. **Run a queue worker** so emails are sent. Create `/etc/supervisor/conf.d/ticket-worker.conf`:
    ```ini
    [program:ticket-worker]
    command=php /var/www/ticket/artisan queue:work --sleep=3 --tries=3 --max-time=3600
    user=www-data
    autostart=true
    autorestart=true
    stopwaitsecs=3600
    stdout_logfile=/var/www/ticket/storage/logs/worker.log
    ```
    and start it:
    ```bash
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start ticket-worker
    ```
11. **Set up the roles.** In **Roles**, check that each role has the permissions you want (see [Permissions and roles](../README.md#permissions-and-roles)), and create your users under **Users**.


---

## Deploying an update

To deploy new code, run these on the server from the project folder:

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan app:acl-sync
php artisan optimize
php artisan queue:restart
php artisan up
```

- `app:acl-sync` creates any new permissions and removes ones that were dropped from the code (also from every role). It never grants permissions to roles: if a release adds a permission, tick it on the roles that should have it in the **Roles** screen.
- `queue:restart` makes the worker pick up the new code.
- Don't run `db:seed` on an existing database; it skips tables that already have data.
