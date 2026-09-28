# Ticket

An internal helpdesk built with Laravel: staff open tickets, and the agents in the ticket's department work them. It is a home-grown alternative to osTicket, built for staff only (there is no public form or email-to-ticket).

- [Requirements](#requirements)
- [Setup](#setup): [on your machine](docs/LOCAL-SETUP.md) · [on a server](docs/DEPLOYMENT.md)
- [Features](#features) · [Permissions and roles](#permissions-and-roles) · [Testing](#testing-and-code-quality) · [Project structure](#project-structure)

The plan, conventions and version history live in [docs/ROADMAP.md](docs/ROADMAP.md).

---

## Requirements

| | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer (developed on 8.5) | Extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`. `intl` is required: attachment sizes are formatted with it. |
| Composer | 2.x | |
| Node.js | 20.19+ or 22.12+, with npm | Only needed to build the CSS and JavaScript. |
| MySQL | 8.0 or newer | The dashboard uses window functions (`count(*) over ()`), so MySQL 5.7 will not work. |
| Web server | Nginx (or Apache) with PHP-FPM | Only on a server. Locally, `php artisan serve` is enough. |

Check PHP and its extensions with `php -v` and `php -m`.

---

## Setup

| Guide | For |
|---|---|
| [docs/LOCAL-SETUP.md](docs/LOCAL-SETUP.md) | Running it on your own machine for development: Linux (Ubuntu/Debian), or optionally Windows with Laragon |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Installing it on a Linux server for real users, and deploying updates |

Quick start on a machine that already has the [requirements](#requirements) and an empty MySQL database:

```bash
git clone <repository-url> ticket && cd ticket
composer run setup          # dependencies, .env, app key, assets
# set DB_*, QUEUE_CONNECTION=sync and MAIL_MAILER=log in .env
php artisan migrate
php artisan db:seed
composer run dev             # then open http://localhost:8000
```

Sign in as **admin@example.com** / **password**. The guides explain each step, and what changes on a server.

---

## Features

- **Tickets.** Anyone signed in can open a ticket with a subject, an optional description, a department, a priority and attachments. Tickets have a thread of public replies and internal notes, and every change (edits, status, priority, department, assignee) is kept in the thread with its original values.
- **Visibility by department.** Everyone sees the tickets they opened. Agents see the tickets in their departments; holders of `tickets-all` see everything.
- **Assignment.** Tickets start unassigned. Agents can claim them; people with `tickets-all` can assign them to anyone who can work them.
- **Email notifications** for new tickets, assignment, replies, internal notes and status changes, sent only to active users and never to the person who caused the event.
- **Dashboard** with ticket counts, "assigned to me" and "waiting longest" lists, and open tickets by status and department, scoped to what the user can see.
- **Administration.** Users (one role each, one or more departments with a default), roles and their permissions, and the ticket settings: departments, statuses and priorities.
- **Accounts.** Login, password reset, email verification, two-factor authentication and passkeys. There is no public registration; administrators create users, who receive a link to set their password.

Built with Laravel 13, Livewire 4 (class-based components), Flux UI (free), Tailwind CSS 4, Fortify and spatie/laravel-permission.

## Permissions and roles

Permissions are defined in [`App\Enums\Permission`](app/Enums/Permission.php) and granted to roles in the **Roles** screen. Each user has exactly one role. Authorization always goes through policies that check permissions, never role names.

| Permission | Grants |
|---|---|
| `users` | Manage users |
| `roles` | Manage roles and their permissions |
| `tickets` | Work tickets in your departments: reply, internal notes, claim, change status, priority and department |
| `tickets-all` | See and work every ticket, and assign tickets to anyone |
| `departments` | Manage departments |
| `ticket-statuses` | Manage ticket statuses |
| `ticket-priorities` | Manage ticket priorities |

Opening a ticket and following your own tickets needs no permission. On a new database the seeded roles start with: **Admin** everything, **Manager** `tickets` and `tickets-all`, **Agent** `tickets`.

When you add, rename or remove a case in the `Permission` enum, run `php artisan app:acl-sync`.

## Testing and code quality

```bash
php artisan test --compact          # the test suite (SQLite in memory, no setup needed)
composer run test                   # Pint check, PHPStan and the tests, as CI runs them
vendor/bin/pint --dirty             # format the files you changed
vendor/bin/phpstan analyse          # static analysis (level 5)
```

Every change ships with PHPUnit feature tests, and Pint and PHPStan must pass. GitHub Actions ([.github/workflows/laravel.yml](.github/workflows/laravel.yml)) runs the same checks and the asset build on pushes and pull requests to `main`.

## Project structure

| Path | What's there |
|---|---|
| `app/Livewire/` | Pages and components, grouped by area (`Tickets`, `Users`, `Roles`, `Departments`, `TicketStatuses`, `TicketPriorities`, `Settings`) plus the `Dashboard` |
| `app/Actions/` | Business logic, such as `Tickets/CreateTicket`, `Tickets/AddTicketMessage`, `Tickets/AssignTicket` and `Users/SyncUserDepartments` |
| `app/Policies/` | Authorization rules for each model |
| `app/Notifications/` | The ticket emails and the set-password email |
| `app/Enums/` | `Permission`, `BadgeColor` and `TicketMessageType` |
| `app/Helpers/Helper.php` | Global helpers, such as `user()` for the signed-in user |
| `config/tickets.php` | Attachment limits: number of files, size and allowed types |
| `routes/` | One file per area: `tickets.php`, `users.php`, `roles.php`, `ticket-settings.php`, `settings.php` |
| `docs/` | The roadmap and version plans, the local setup guide (`LOCAL-SETUP.md`) and the deployment guide (`DEPLOYMENT.md`) |
