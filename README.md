# Helpdesk

An internal helpdesk ticketing system built with **Laravel 13**, **PostgreSQL** and **AdminLTE 4** (`jeroennoten/laravel-adminlte`).

## Features

- **Tickets:** references (`TKT-000123`), departments, nested categories, priorities, statuses, tags, watchers (CC), attachments, internal notes, and PostgreSQL full-text search
- **Roles:** Admin, Agent and Requester (`spatie/laravel-permission`)
  - Agents see their department's tickets
  - Requesters see their own tickets and the ones they watch
- **Routing:** new tickets are auto-assigned to the least-busy agent in the department. Tickets with no department go to the admins for triage.
- **SLA:** response and resolution targets per priority
  - The clock pauses on *Pending* or *On Hold* statuses
  - A scheduled breach check runs every 5 minutes
  - Escalation has two levels: first the assignee and department lead, then the admins after `HELPDESK_ESCALATION_L2_MINUTES`
- **Notifications:** queued email plus an in-app bell on create, assign, reply, status change and SLA breach
- **Audit log:** every property change is shown on the ticket (`spatie/laravel-activitylog`)
- **Knowledge base:** a rich-text editor (Quill, sanitized with HTMLPurifier), full-text search, drafts and helpful votes
- **Canned replies:** shared and personal, with `{requester}`, `{agent}` and `{reference}` placeholders
- **Reports:** volume by status, priority, department and category, agent performance and SLA compliance, all with CSV export
- **Admin:** user management (roles, activate/deactivate) plus configuration for departments, categories, priorities and SLA, statuses, tags and KB categories

## Getting started (Laravel Sail)

Requires Docker.

```bash
cp .env.example .env            # DB_CONNECTION=pgsql, QUEUE_CONNECTION=redis
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link
```

Run these in separate terminals:

```bash
./vendor/bin/sail artisan queue:work      # sends notifications
./vendor/bin/sail artisan schedule:work   # SLA breach checks
```

- App: http://localhost
- Mailpit (outgoing mail): http://localhost:8025

> On SELinux hosts (e.g. Fedora), the bind mounts in `compose.yaml` carry the `:z` label. If port 6379 is taken locally, set `FORWARD_REDIS_PORT` in `.env`.

### Demo accounts

All demo accounts use the password `password`. They're seeded only outside production.

| Role      | Email                 |
|-----------|-----------------------|
| Admin     | `admin@example.com`   |
| Agent     | `agent@example.com` (IT Support) |
| Requester | `user@example.com`    |

## Tests

```bash
./vendor/bin/sail artisan test
```

The tests run against the Sail PostgreSQL `testing` database, because full-text search uses `tsvector`.

## Configuration

Settings live in `config/helpdesk.php` and can be overridden from `.env`:

| Variable | Default | Purpose |
|---|---|---|
| `HELPDESK_REFERENCE_PREFIX` | `TKT-` | Prefix for ticket references |
| `HELPDESK_AUTO_ASSIGN` | `true` | Auto-assign new tickets within the department |
| `HELPDESK_ESCALATION_L2_MINUTES` | `60` | Delay before a breached ticket escalates to the admins |
| `HELPDESK_AT_RISK_MINUTES` | `60` | When the SLA badge turns "at risk" |
| `HELPDESK_ATTACHMENT_MAX_KB` | `10240` | Maximum size per attachment |
