# Local setup

How to run the helpdesk on your own machine for development or to try it out. It uses development settings (emails go to a log file, debug is on), so don't use it for a server: for that, see [DEPLOYMENT.md](DEPLOYMENT.md).

Check the [requirements](../README.md#requirements) in the README first.

- [Linux (Ubuntu or Debian)](#linux-ubuntu-or-debian)
- [Optional: Windows with Laragon](#optional-windows-with-laragon)
- [Working locally](#working-locally)
- [Updating after pulling new code](#updating-after-pulling-new-code)

---

## Linux (Ubuntu or Debian)

These steps are for Ubuntu or Debian. On other Linux distributions, install the same packages with your package manager. Windows is covered as an [optional alternative](#optional-windows-with-laragon) below.

1. **Install PHP, MySQL and the tools.** Ubuntu's own PHP may be older than 8.3, so this uses the widely used [ondrej/php](https://launchpad.net/~ondrej/+archive/ubuntu/php) packages:
   ```bash
   sudo apt update
   sudo apt install -y software-properties-common git unzip curl
   sudo add-apt-repository -y ppa:ondrej/php
   sudo apt update
   sudo apt install -y php8.5-cli php8.5-bcmath php8.5-curl php8.5-intl php8.5-mbstring \
       php8.5-mysql php8.5-xml php8.5-zip mysql-server
   ```
   On Debian, use the [packages.sury.org](https://packages.sury.org/php/) repository instead of the PPA.
2. **Install Composer** (see [getcomposer.org](https://getcomposer.org/download/) for the checksum-verified installer):
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```
3. **Install Node.js** 22 with [nvm](https://github.com/nvm-sh/nvm):
   ```bash
   curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
   source ~/.bashrc
   nvm install 22
   ```
4. **Check the versions:** `php -v` (8.3 or newer), `php -m | grep intl`, `composer --version`, `node -v`.
5. **Get the code and install the dependencies:**
   ```bash
   git clone <repository-url> ticket
   cd ticket
   composer run setup
   ```
   This runs `composer install`, copies `.env.example` to `.env`, generates the app key, runs the migrations on the example's SQLite database, runs `npm install` and builds the assets.
6. **Create a MySQL database and user:**
   ```bash
   sudo mysql -e "CREATE DATABASE ticket_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'ticket'@'localhost' IDENTIFIED BY 'secret';
   GRANT ALL PRIVILEGES ON ticket_db.* TO 'ticket'@'localhost';"
   ```
7. **Point `.env` at it**, and set local mail and queue:
   ```dotenv
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ticket_db
   DB_USERNAME=ticket
   DB_PASSWORD=secret

   QUEUE_CONNECTION=sync
   MAIL_MAILER=log
   ```
8. **Create the tables and starting data:**
   ```bash
   php artisan config:clear
   php artisan migrate
   php artisan db:seed
   ```
9. **Start the app:**
   ```bash
   composer run dev
   ```
   This runs `php artisan dev`, which starts the development processes together. Alternatively run `php artisan serve` for the site and `npm run dev` for the assets in two terminals.
10. **Open [http://localhost:8000](http://localhost:8000)** and sign in as **admin@example.com** / **password**.

## Optional: Windows with Laragon

Linux is the main platform for this project. If you develop on Windows, [Laragon](https://laragon.org) is the simplest way to get PHP, MySQL and a web server; servers should still run Linux (see [DEPLOYMENT.md](DEPLOYMENT.md)).

1. **Install Laragon** (Full edition), plus [Node.js](https://nodejs.org) 22 LTS and [Git for Windows](https://git-scm.com/download/win). Laragon includes PHP, MySQL, Composer and a web server.
2. **Pick PHP 8.3 or newer:** right-click the Laragon tray icon → **PHP** → choose the version. Enable any missing extension (such as `intl`) under **PHP → Extensions**.
3. **Get the code** into Laragon's web folder and install the dependencies (in Laragon's **Terminal**):
   ```bash
   cd C:\laragon\www
   git clone <repository-url> ticket
   cd ticket
   composer run setup
   ```
4. **Create the database** `ticket_db`: click **Database** in Laragon (opens HeidiSQL), or run `mysql -u root -e "CREATE DATABASE ticket_db"`.
5. **Set `.env`** as in step 7 above, with `APP_URL=http://ticket.local`, `DB_USERNAME=root` and an empty `DB_PASSWORD` (Laragon's defaults).
6. **Create the tables and starting data:** `php artisan config:clear`, `php artisan migrate` and `php artisan db:seed`.
7. **Open [http://ticket.local](http://ticket.local).** Laragon creates this address for every folder in `www`; if it doesn't resolve yet, restart Laragon or use **Menu → Apache/Nginx → Reload**. Run `npm run dev` while you work on views or CSS.
8. **Sign in** as **admin@example.com** / **password**.

## Working locally

- **Mail:** with `MAIL_MAILER=log`, emails are written to `storage/logs/laravel.log` instead of being sent.
- **Queue:** emails are queued notifications. With `QUEUE_CONNECTION=sync` they are sent immediately and no worker is needed. If you use `QUEUE_CONNECTION=database` instead, run `php artisan queue:work`.
- **Assets:** after changing Blade views or CSS, run `npm run build`, or keep `npm run dev` running. If a change doesn't show up, this is usually why.
- **Seed data:** the seeders create the permissions, the Admin, Manager and Agent roles, the HR and IT departments, the default ticket statuses and priorities, and the admin account. They only fill **empty** tables, so running `db:seed` again never changes an existing database. To start over: `php artisan migrate:fresh --seed` (this deletes all data).

## Updating after pulling new code

```bash
composer install
npm install
npm run build
php artisan migrate
php artisan app:acl-sync
```

`app:acl-sync` creates new permissions and removes dropped ones; tick new permissions on the roles in the **Roles** screen.
