# Roadmap v1.1: Company customization

Lets the organisation running the helpdesk put its own name, logos, colour and email details on it, instead of the TicketMe defaults. See [ROADMAP.md](ROADMAP.md) for the project conventions and what is already built.

**Status:** built. Everything below is implemented and covered by tests; the manual check under **Verification** is still to do before v1.1 ships.

---

## Decisions
- **One company per install.** Each install belongs to one organisation. There is no multi-tenancy: users, departments and tickets are unchanged.
- **What can be customized:**
  - **Name and logos:** the company name (used as the app name), a logo for light mode, a logo for dark mode, and the favicon.
  - **Brand colour:** the accent colour for primary buttons, links and highlights.
  - **Emails:** the sender name and address, the mail server (SMTP host, port, encryption, username and password, or the log file), and a logo and footer text in notification emails.
  - **Sign-in page:** a welcome heading and message, and an optional background image.
- **Who can change it:** anyone with the existing `roles` permission. There is no new permission; the policy checks `Permission::Roles`.
- **Defaults stay TicketMe.** Every setting is optional. When one is empty, the app falls back to what it shows today: the TicketMe logos and favicon, the current accent colours, `APP_NAME`, and `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.
- **One screen, one row: App settings.** All settings live in a single `app_settings` row, edited on one **App settings** screen under Administration, with a tab per area (Branding, Email, Sign-in page). The name keeps it apart from each user's personal **Settings** (profile, security, appearance), and later versions add more tabs there (for example a General tab for time zone, date format and attachment limits) instead of new screens. Settings are cached and the cache is cleared on save.
- **Uploads are public files.** Logos, the favicon and the sign-in background must be reachable by browsers and email clients, so they go on the `public` disk under `app-settings/`. This needs `php artisan storage:link` on every install.
- **The mail server is saved to `.env`, not the settings row.** On save, the Email tab writes `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME` and `MAIL_PASSWORD` to `.env` (only when they changed; empty fields are written as `null`), then clears a cached config and runs `queue:restart` so the worker picks them up. The web server needs write access to `.env`. The password is never sent back to the browser, and an empty password field keeps the saved one. Switching to another mailer keeps the SMTP values in `.env`.
- **No SVG uploads.** An uploaded SVG opened directly in the browser can run scripts, so uploads accept PNG, JPG and WebP only (plus ICO and PNG for the favicon). The bundled TicketMe SVGs stay as the defaults.

## 1. Data model
- [x] **`app_settings` table** (one row):

| Column | Type | Notes |
|---|---|---|
| `name` | string, nullable | Replaces `APP_NAME` in page titles, the sign-in page and emails |
| `logo_light_path` | string, nullable | Logo on light backgrounds |
| `logo_dark_path` | string, nullable | Logo on dark backgrounds; falls back to the light logo |
| `favicon_path` | string, nullable | |
| `accent_color` | string(7), nullable | `#rrggbb` |
| `mail_from_name` | string, nullable | |
| `mail_from_address` | string, nullable | |
| `mail_logo_path` | string, nullable | Falls back to the light logo |
| `mail_footer` | text, nullable | Plain text, shown under every email |
| `sign_in_heading` | string, nullable | |
| `sign_in_message` | text, nullable | Plain text |
| `sign_in_background_path` | string, nullable | |
| timestamps | | |

- [x] **`AppSettings` model** with `current()`, which returns the single row (creating an empty one if missing) from the cache, and a `fileUrl(string $column)` helper for the uploaded files. Only the row's attribute array is cached: Laravel 13's cache refuses to unserialize objects.
- [x] **No seeder row needed:** an empty row means "all defaults".

## 2. Business logic (`app/Actions/AppSettings/`)
- [x] **`UpdateAppSettings`:** saves the text fields, stores new uploads on the `public` disk under `app-settings/` (deleting the file they replace), supports removing an upload to go back to the default, and clears the settings cache.
- [x] **`AppBranding`** (a small service resolved once per request): the effective name, logo URLs, favicon URL, accent colours, mail sender and sign-in text, each with its default. Everything that shows branding reads from here, so the fallbacks live in one place.
- [x] **Accent colours:** from the chosen `#rrggbb`, derive the three Flux variables (`--color-accent`, `--color-accent-content`, `--color-accent-foreground`) for light and for dark mode. Buttons use the colour in both modes; text on them is white or near-black, whichever gives the higher contrast. In dark mode, link text is mixed with white until it reaches 4.5:1 on the dark background. Colours below 3:1 against white are rejected, with a message.

## 3. Authorization
- [x] **`AppSettingsPolicy`:** `view` and `update` both require `Permission::Roles`.

## 4. UI
- [x] **`AppSettings/AppSettingsForm`** (`/app-settings`), with a tab per area and cards like the other settings screens. The open tab is kept in the URL (`?tab=email`).

| Tab | Contents |
|---|---|
| **Branding** | Company name, light logo, dark logo, favicon (each upload with a preview and a "Remove" button that returns to the default), and the brand colour: a colour picker with a hex field, a live preview of a primary button and a link, and "Reset to default" |
| **Email** | Sender name and address, email logo, footer text, and a **Mail server** card with one field per `WRITABLE_KEYS` key, each labelled with its `.env` name: delivery method (SMTP, sendmail or log file; any other mailer already in `.env` is kept as an option), SMTP host, port, encryption, username and password, and a "Send test email" button that sends to the signed-in user straight away |
| **Sign-in page** | Heading, message, background image, with a preview |

- [x] **Menu:** an **App settings** item under Administration, shown to holders of `roles`.
- [x] **Applying the branding:**
  - `partials/head`: the page title uses the company name, the favicon links use the uploaded favicon, and the accent variables are printed in a small `<style>` block.
  - `brand-logo`: uses the uploaded light and dark logos when set.
  - `layouts/auth/simple`: shows the sign-in heading, message and background image when set.

## 5. Emails
- [x] **Sender:** set `mail.from` from the settings at boot, so every notification uses it.
- [x] **Mail server:** `WriteEnvironmentFile` replaces or appends `KEY=value` lines in `.env`, only for the keys in its `WRITABLE_KEYS` allowlist (the six `MAIL_*` server keys), so it can never change `APP_KEY` or the database credentials (quoting and escaping values so dotenv reads them back unchanged, rejecting line breaks); `UpdateMailServer` writes the `MAIL_*` values, applies them to this request, clears a cached config and restarts the queue worker.
- [x] **Theme:** publish Laravel's mail views and keep only the changed ones (`resources/views/vendor/mail/html/message.blade.php`, `html/header.blade.php`, `text/message.blade.php`): the header shows the email logo (or the company name when there is none) and the footer shows the footer text. A long-running queue worker re-reads the settings before every job.
- [x] **App name in emails:** subjects and greetings that use `config('app.name')` pick up the company name.

## 6. Tests
- [x] **`AppSettings/AppSettingsTest`:**
  - Only holders of `roles` can open and save the screen.
  - Text fields save, and uploads are stored, replaced (old file deleted) and removed.
  - Invalid uploads (SVG, too large) and invalid or low-contrast colours are rejected.
  - The mail server is written to `.env` (a temporary file in tests) with special characters intact and the password never sent back; SMTP needs a host and port; line breaks are rejected; other tabs leave `.env` alone; an unwritable `.env` shows an error and saves nothing; the test email is sent, or its error shown.
- [x] **`AppSettings/WriteEnvironmentFileTest`:** keys are replaced in place (line endings and comments kept) or appended, and values with quotes, `#` and `$` read back unchanged.
- [x] **`AppSettings/AppBrandingTest`:**
  - With no settings, every value is the TicketMe or `.env` default.
  - With settings, pages show the company name in the title, the uploaded logos and favicon, and the accent variables.
  - The sign-in page shows the heading, message and background.
  - Notification emails use the sender, logo and footer.

## 7. Documentation
- [x] **LOCAL-SETUP.md and DEPLOYMENT.md:** add `php artisan storage:link` to the install steps, and include `storage/app/public` (the App settings uploads) in backups.
- [x] **README:** mention the App settings screen under Features and Permissions (`roles` also covers app settings).

## Verification
1. `php artisan migrate` and `php artisan storage:link`.
2. `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse` and `php artisan test --compact` all pass. Run `npm run build`.
3. Manual check:
   - As a user with `roles`, open **App settings**; as a user without it, the menu item is missing and `/app-settings` returns 403.
   - On the **Branding** tab, set a name, both logos, a favicon and a brand colour. The sidebar, header, tab title, favicon and primary buttons change, in both light and dark mode.
   - Remove the uploads and reset the colour; the TicketMe defaults return.
   - On the **Sign-in page** tab, set the heading, message and background, sign out, and check the sign-in page.
   - On the **Email** tab, set the sender, logo and footer, trigger a ticket email, and check it in `storage/logs/laravel.log`.
   - On the **Email** tab, choose SMTP, enter a real server, save, and use **Send test email**; then a wrong password shows the error. Check that `.env` has the new `MAIL_*` values.
4. CI passes on GitHub Actions.
