# Roadmap

This is an internal helpdesk built on Laravel, a home-grown alternative to osTicket. This document covers the whole project: the conventions, what is already built, and the list of versions. Each version has its own plan in `ROADMAP-v{version}.md`.

Conventions for all new work:
- Livewire components are class-based (`php artisan make:livewire Module/EntityList --class`) and named after their entity, such as `TicketList` or `TicketForm`. One form component handles both create and edit.
- Page titles come from `#[Title('...')]`, or from `<x-slot:title>` in the Blade view when the title changes. Never use `->title()` in `render()`.
- The UI uses Flux (free) components. Toasts are passed through `session('toast')` and shown with `Flux::toast`.
- Authorization goes through policies that check **permissions** from `App\Enums\Permission`, never role names.
- Every change ships with PHPUnit feature tests, and Pint and PHPStan (level 5) must pass.

---

## Versions

| Version | Goal | Status | Plan |
|---|---|---|---|
| v1 | Ticketing: departments, statuses and priorities, tickets with replies, internal notes, attachments and email notifications | In progress. The ticket settings are done; tickets are next. | [ROADMAP-v1.md](ROADMAP-v1.md) |

To plan a new version, add `ROADMAP-v{version}.md` next to this file, using the same sections as v1: decisions, then numbered work areas with checkboxes, tests and verification. Then add a row to this table. When a version ships, move what it built into **Done** below and mark its row as shipped.

---

## Done

| Area | What exists | Key files |
|---|---|---|
| Foundation | Laravel 13, Livewire 4 with class-based components, Flux, Fortify and Tailwind 4, from the Livewire starter kit. Laravel Boost is set up for Claude Code only. GitHub Actions CI runs Pint, PHPStan, tests and the asset build. | [composer.json](../composer.json), [laravel.yml](../.github/workflows/laravel.yml) |
| Authentication | Login, forgot/reset password, email verification, 2FA, passkeys and password confirmation. There's **no public registration**, and the welcome page is removed. `/` redirects to the dashboard, or to login for guests. | [fortify.php](../config/fortify.php), [FortifyServiceProvider.php](../app/Providers/FortifyServiceProvider.php), [web.php](../routes/web.php) |
| Profile settings | Users can edit their name; the email is locked. Security covers password, 2FA and passkeys, and there's an appearance setting. Self-service account deletion is removed. | [app/Livewire/Settings/](../app/Livewire/Settings/) |
| Permissions | The `Permission` enum (`users`, `roles`, `departments`, `ticket-statuses`, `ticket-priorities`) is synced to spatie/laravel-permission by `PermissionSeeder` and `php artisan app:acl-sync`. Each permission gives full access to its area. | [Permission.php](../app/Enums/Permission.php), [SyncPermissions.php](../app/Actions/Permissions/SyncPermissions.php) |
| Roles | A Roles screen to list, add and edit roles and choose their permissions. Anyone with `roles` can grant any permission. A role can only be deleted when no users are assigned to it. The last active role manager is protected. The seeded defaults are Admin, Manager and Agent. | [RoleList](../app/Livewire/Roles/RoleList.php), [RoleForm](../app/Livewire/Roles/RoleForm.php), [RolePolicy](../app/Policies/RolePolicy.php), [RoleSeeder](../database/seeders/RoleSeeder.php), [EnsureRoleManagerRemains](../app/Actions/Roles/EnsureRoleManagerRemains.php) |
| Users | A Users screen with search and filters for role, status and department. It shows each user's departments, with the default one highlighted. Creating a user emails them a set-password link. Also: edit, **one role per user**, **one or more departments with one default department**, deactivate/reactivate, and resend the set-password link. Deactivated users can't log in, and any open sessions are logged out. | [UserList](../app/Livewire/Users/UserList.php), [UserForm](../app/Livewire/Users/UserForm.php), [UserPolicy](../app/Policies/UserPolicy.php), [SyncUserDepartments](../app/Actions/Users/SyncUserDepartments.php), [EnsureUserIsActive](../app/Http/Middleware/EnsureUserIsActive.php), [SetPasswordNotification](../app/Notifications/SetPasswordNotification.php) |
| Ticket settings (v1) | Screens for departments, ticket statuses and ticket priorities, each behind its own permission. Statuses and priorities have a color with a live badge preview, a single default and a sort order. Statuses can count as closed. The default status or priority can't be deleted. | [app/Livewire/Departments/](../app/Livewire/Departments/), [app/Livewire/TicketStatuses/](../app/Livewire/TicketStatuses/), [app/Livewire/TicketPriorities/](../app/Livewire/TicketPriorities/), [ticket-settings.php](../routes/ticket-settings.php), [TicketSettingsSeeder](../database/seeders/TicketSettingsSeeder.php) |
| Seed data | `admin@example.com` / `password` with the Admin role, in the IT department, which is also the admin's default. Change it outside local development. Default departments (HR, IT), statuses and priorities. | [DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php), [TicketSettingsSeeder](../database/seeders/TicketSettingsSeeder.php) |
