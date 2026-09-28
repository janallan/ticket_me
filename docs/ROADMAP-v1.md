# Roadmap v1: Ticketing

The first version of the helpdesk: staff open tickets, and the agents in the ticket's department work them. See [ROADMAP.md](ROADMAP.md) for the project conventions and what is already built.

**Status:** built. Everything below is implemented and covered by tests; the manual check under **Verification** is still to do before v1 ships.

---

## Decisions
- **Requesters:** staff only. Every active logged-in user can open tickets, and this needs **no permission**. There's no public form and no email-to-ticket.
- **Scope:** departments, replies with internal notes, attachments and email notifications, all built in one go.
- **Assignment:** manual. Tickets start unassigned, managers assign them, and agents can claim them.
- **Statuses and priorities:** configurable in the UI and stored in the database.
- **Visibility:** by department. Everyone sees tickets they opened. Agents see tickets in their departments. Holders of `tickets-all` see everything.
- **Subject:** must not match another **open** ticket from the same requester (closed tickets and other people's tickets don't count); checked when opening and editing.
- **Description:** optional, stored on the ticket (`tickets.description`) and included in the list search.
- **Editing and history:** only the requester can edit the subject, description and department, and only while the ticket is open (a requester who works tickets in the department can also change the status there). People who work a ticket they did not open change its status, priority, department and assignee from the ticket page. Every change — edits, status, priority, department, assignee, and a reopen by the requester — adds a public **log entry** to the thread listing the original values ("Changes (original values)"), so there is no separate history table.
- **Departments per user:** a user belongs to one or more departments, and one of them is their default department. Both are set on the user form.

## 1. Permissions
- Add the cases below to `App\Enums\Permission`, each with a `label()` and `description()`:
  - [x] `Tickets = 'tickets'`: work tickets in your departments (view, reply, internal notes, claim, change status and priority).
  - [x] `TicketsAll = 'tickets-all'`: see and work every ticket, and assign tickets to anyone.
  - [x] `Departments = 'departments'`, `TicketStatuses = 'ticket-statuses'` and `TicketPriorities = 'ticket-priorities'`: each manages its own settings screen.
- Opening a ticket and following your own tickets needs **no** permission.
- Set the `RoleSeeder` defaults:
  - [x] Admin → everything (currently `users`, `roles`, `departments`, `ticket-statuses`, `ticket-priorities`).
  - [x] Manager → `tickets` and `tickets-all`.
  - [x] Agent → `tickets`.
- Seeders only fill tables that are empty, so they never touch an existing database. There, run `php artisan app:acl-sync` (it creates new permissions and removes ones no longer in the enum, also from every role), then tick the new permissions on each role in the Roles screen.

## 2. Data model
- [x] Settings tables: `departments`, `department_user`, `ticket_statuses`, `ticket_priorities`.
- [x] `users.default_department_id`.
- [x] Ticket tables: `tickets`, `ticket_messages`, `ticket_attachments`.

| Table | Columns |
|---|---|
| `departments` | name (unique), description (nullable), is_active (default true), timestamps |
| `department_user` | department_id, user_id (unique pair). Defines membership; a user can belong to many departments. |
| `users` (addition) | default_department_id → departments (nullable, null on delete). Always one of the user's departments, enforced by `SyncUserDepartments` (`app/Actions/Users/`). |
| `ticket_statuses` | name (unique), color (Flux badge color), is_default, is_closed, sort_order |
| `ticket_priorities` | name (unique), color, is_default, sort_order |
| `tickets` | subject, description (text, optional, searchable), department_id, ticket_status_id, ticket_priority_id, requester_id → users, assignee_id → users (nullable), closed_at, last_activity_at, timestamps. Foreign keys on status, department and priority restrict deletes. |
| `ticket_messages` | ticket_id, user_id, type (`message` or `log`, see `TicketMessageType` enum), body, is_internal, timestamps. Replies, internal notes and change logs; the original description is `tickets.description`. |
| `ticket_attachments` | ticket_id, ticket_message_id (nullable: null means the file belongs to the description), disk, path, original_name, mime_type, size, timestamps |

- **Models:**
  - [x] `Department`: `users()` and an `active()` scope. `tickets()` comes with the tickets table.
  - [x] `TicketStatus` and `TicketPriority`: `findDefault()`, `makeDefault()` and an `ordered()` scope, shared through `App\Models\Concerns\HasDefaultOption`. Colors use the `App\Enums\BadgeColor` enum.
  - [x] `Ticket`: relations; a `number` accessor that shows `#000123`; a `visibleTo(User)` scope; `isClosed()`.
  - [x] `TicketMessage`: `attachments()` and a `public()` scope.
  - [x] `TicketAttachment`.
- **User additions:**
  - [x] `departments()` and `defaultDepartment()`. Every user must have at least one department, and a default department from among them.
  - [x] `requestedTickets()`, `assignedTickets()`, and `worksTicketsIn(Department)`, which is true with `tickets` plus membership, or with `tickets-all`.
- [x] **`TicketSettingsSeeder`**, which only creates what's missing:
  - Statuses: Open (default), Pending, Resolved (closed), Closed (closed).
  - Priorities: Low, Normal (default), High, Urgent.
  - Departments: HR and IT. The seeded admin joins IT, which is also their default department.
- [x] Factories for the settings models, and a `UserFactory::inDepartment()` state. [x] Factories for the ticket models.

## 3. Business logic (`app/Actions/`)
- [x] **`Users/SyncUserDepartments`:** sets a user's departments and default department together, and rejects a default that isn't one of the user's departments.
- [x] **`Tickets/CreateTicket`:**
  - Creates the ticket with its description and attachments, in the chosen or default status and with the chosen or default priority.
  - The department defaults to the requester's default department.
  - Notifies the department.
- [x] **`Tickets/AddTicketMessage`:**
  - Adds a public reply or internal note, with attachments, and bumps `last_activity_at`.
  - If the requester replies publicly to a closed ticket, it reopens with the default status.
  - Sends the notifications.
- [x] **`Tickets/AssignTicket`:**
  - The assignee must be active and able to work the ticket.
  - `null` unassigns.
  - Notifies the assignee unless they assigned themselves.
- [x] **`Tickets/UpdateTicketDetails`:**
  - Changes status, priority and department.
  - Sets or clears `closed_at` when the status changes between open and closed.
  - Changing the department drops an assignee who can't work in the new one.
  - Notifies the requester when the status changes.
- [x] **`Tickets/StoreTicketAttachments`:**
  - Stores files on the private `local` disk under `tickets/{ticket}/`.
  - Limits are set in `config/tickets.php`: up to 5 files, 10 MB each, and an allow-list of types (images, pdf, office documents, txt, csv, zip).

## 4. Authorization
- [x] **`TicketPolicy`**

| Ability | Rule |
|---|---|
| `viewAny`, `create` | Any active user |
| `view`, `reply` | Requester, `tickets-all`, or works tickets in the ticket's department |
| `work` (internal notes, status, priority, department) | `tickets-all`, or works tickets in the department |
| `claim` | `work`, and the ticket is unassigned |
| `assign` | `tickets-all` |

- [x] **`DepartmentPolicy`, `TicketStatusPolicy`, `TicketPriorityPolicy`:**
  - Each policy requires its own permission: `departments`, `ticket-statuses` or `ticket-priorities`.
  - [x] A record can't be deleted while tickets use it (added together with the tickets table).
  - The default status or priority can't be deleted, and saving a new default un-defaults the old one.
  - Deactivating a department hides it from the new-ticket form but keeps its tickets.
- [x] **Attachment downloads** (`tickets.attachments.show`):
  - Requires `view` on the ticket, plus `work` if the attachment is on an internal note.
  - Streams the file from the private disk.

## 5. UI
- [x] **`Tickets/TicketList`** (`/tickets`):
  - Lists every ticket the user can see. A row of switches under the filters narrows it: **Opened by me** (everyone), **Assigned to me** and **Unassigned** (only people who work tickets; turning one on turns the other off), and **Show closed**. All of them are kept in the URL.
  - Filters: search by number, subject or description, status, priority, department. Closed tickets are hidden unless "Show closed" is on.
  - A paginated table with number, subject, department, priority, status, assignee and last activity.
- [x] **`Tickets/TicketForm`** (`/tickets/create` and `/tickets/{ticket}/edit`, one component for both): subject, an optional description and attachments, with a Details panel for status, department (active only; preselects the requester's default department) and priority. Anyone can open a ticket; only people who work tickets in the chosen department can pick a status other than the default. When editing, it saves through the `Tickets/EditTicket` action (`TicketPolicy::update`: only the requester, while the ticket is open); the status stays read-only unless you work tickets in the department, new files are added by replying, and the original values of every change go into one log entry.
- [x] **`Tickets/TicketView`** (`/tickets/{ticket}`): the page (header, description, assignee, details and info panels). The thread is its own component, `Tickets/TicketMessageList`, and the reply box is `Tickets/TicketMessageForm`; they stay in step through the `ticket-message-added` (form → list and page) and `ticket-updated` (page → list) events.
  - **Thread:** the description and its attachments, then messages oldest first. Only the newest entries show at first (`TicketMessageList::MESSAGES_PER_PAGE`, currently 5); a "Show older replies" button at the top loads that many more each click. Internal notes are highlighted and visible only to workers.
  - **Reply box:** message and attachments, plus an "Internal note" switch for workers.
  - **Edit ticket** button in the header links to `/tickets/{ticket}/edit`. The ticket page itself is view-only apart from replying and the details panel.
  - **Details panel:** workers can change status, priority and department. The assignee is a select or a Claim button. Everyone else sees these fields read-only.
  - **Info panel** below Details: Created by, Created At, Last updated.
- [x] **Ticket settings** (each screen requires its own permission):
  - `Departments/DepartmentList` and `DepartmentForm`: name, description, active, and a member count linking to the filtered Users list.
  - `TicketStatuses/TicketStatusList` and `TicketStatusForm`: name, color with a badge preview that shows the name, default, counts as closed, sort order.
  - `TicketPriorities/TicketPriorityList` and `TicketPriorityForm`: name, color with the same preview, default, sort order.
  - Deletion goes through the shared `settings-delete-section` component, with a short confirmation heading and an optional description.
- [x] **User departments** (`Users/UserForm` and `UserList`):
  - The form has a departments checkbox group and a default department select. The select only lists the ticked departments.
  - The list has a department filter and a Departments column, with the default department highlighted.
- **Menu** (each item is shown only to users with its permission):
  - [x] Platform: Dashboard, Tickets.
  - [x] Administration: Users, Roles.
  - [x] Ticket settings: Departments, Statuses, Priorities. Each item is gated by its own permission. The sidebar uses the shared partial `partials/admin-nav`, and the header layout uses a dropdown.

## 6. Email notifications
- [x] Add the notifications below. All are queued on the database queue and go to **active** users only, never to the person who caused the event.

| Notification | Sent to |
|---|---|
| `TicketCreatedNotification` | Department members who can work tickets |
| `TicketAssignedNotification` | The new assignee |
| `TicketRepliedNotification` (public reply) | The requester and the assignee. If the ticket is unassigned, department workers instead. |
| `TicketNoteAddedNotification` (internal note) | The assignee |
| `TicketStatusChangedNotification` | The requester |

Each email shows the ticket number and subject, with a "View ticket" button. Locally, mail is written to `storage/logs/laravel.log`, and a queue worker must be running.

## 7. Tests
- [x] **`Tickets/TicketVisibilityTest`:** requester, department agent, other-department agent and `tickets-all`. Internal notes and their attachments are hidden from requesters.
- [x] **`Tickets/CreateTicketTest`:** no permission needed, defaults (including the requester's default department), description, attachments, status choice limited to workers, validation, department notification.
- [x] **`Tickets/TicketConversationTest`:** reply notifications, internal notes limited to workers, a requester reply reopens a closed ticket.
- [x] **`Tickets/TicketAssignmentTest`:** claim only when unassigned, only `tickets-all` can assign, ineligible assignees are rejected, a department change drops an ineligible assignee.
- [x] **`Tickets/TicketStatusChangeTest`:** `closed_at` is set and cleared, and the requester is notified.
- [x] **`Tickets/EditTicketTest`:** who can edit (only the requester, while open; workers cannot edit tickets they did not open), the log entry with original values for edits, details, assignment and reopening, and no log when nothing changed.
- [x] **`TicketSettings/DepartmentManagementTest`, `TicketStatusManagementTest`, `TicketPriorityManagementTest`:** CRUD, each permission gate on its own, the menu per permission, a single default. [x] Delete blocked while in use.
- [x] **`Users/UserManagementTest`:** several departments per user, a required department and default department, the default must be one of the user's departments, and the department filter.
- [x] **`TicketSettingsSeederTest`:** defaults are created, and running it again changes nothing.

## Verification
1. Run `php artisan migrate`, `php artisan app:acl-sync` and `php artisan db:seed`. Then grant the new permissions to the roles in the Roles screen.
2. `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse` and `php artisan test --compact` all pass. Run `npm run build`.
3. Manual check with `composer run dev`:
   - As Admin, open an Agent's user page and put them in IT, with IT as their default department.
   - As the Agent, open a ticket with an attachment. The department starts as IT.
   - As Admin, check the notification in `laravel.log`, assign the ticket, add an internal note, and reply.
   - As the Agent (who is also the requester), the note is hidden and the reply is visible.
   - Resolve the ticket, then reply as the requester; it reopens.
   - As the requester, downloading an internal note's attachment returns 403.
4. CI passes on GitHub Actions.
