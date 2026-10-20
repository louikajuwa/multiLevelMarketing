# MLM Enterprise

A Laravel 13 multi-level marketing platform. Partners recruit sub-partners and customers, customers purchase products at negotiated discounts, and the system automatically calculates partner commissions and upline sponsor bonuses based on a 4-level hierarchy.

> **What's new (scalability + isolation pass):** all selectors (partner, customer, product, sponsor, category, brand) are now **searchable inputs** that load 10 results at a time instead of dumping the whole table; the **genealogy tree** is paginated ("Load more") with a search box; **report exports stream** in chunks (PDF capped at 2000 rows); and a **partner sees only their own customers** everywhere (fail-closed). See [`details.md`](details.md) and [`code.md`](code.md).

## Quick Start

### First Time Setup

```bash
make setup
```

This single command:
- Creates `.env` from `.env.example` if it doesn't exist
- Builds and starts all Docker containers
- Installs PHP dependencies via Composer
- Generates the Laravel `APP_KEY`
- Generates the `SOCKET_SECRET` for real-time communication
- Runs database migrations and seeders
- Links storage directory
- Resets permission cache

After setup completes, visit:
- **App:** `http://localhost:8080`
- **phpMyAdmin:** `http://localhost:8081`
- **Mailpit (test email):** `http://localhost:8025`

### Default Credentials

After seeding, log in with:
- **Super Admin:** `superadmin@mlm.test` / `password`
- **Admin:** `admin@mlm.test` / `password`

See [`details.md`](details.md) for the full list of seeded demo accounts.

### Subsequent Runs

**Start:**
```bash
make up
```

**Stop:**
```bash
make down
```

**Restart:**
```bash
make restart
```

## Common Commands

| Command | Purpose |
|---------|---------|
| `make logs` | Tail container logs (last 100 lines) |
| `make ps` | Show running containers |
| `make shell` | Open a bash shell in the app container |
| `make migrate` | Run pending database migrations |
| `make fresh` | Reset database (drop + re-migrate + re-seed) |
| `make seed` | Run database seeders |
| `make build` | Rebuild Docker images |
| `make test` | Run test suite (isolated: SQLite in-memory) |
| `make pint` | Format code with Laravel Pint |

## Architecture

### Services & Ports

| Service | Port | Purpose |
|---------|------|---------|
| Laravel App (FPM) | — | Core business logic (internal) |
| Nginx | 8080 | Web server (browser) |
| MySQL | 3308 | Database (host access for external tools) |
| Redis | — | Cache, sessions, queue (internal) |
| phpMyAdmin | 8081 | Database GUI |
| Mailpit | 8025 | Email UI (development) |
| Socket.IO | 3001 | Real-time events (internal, reverse-proxied by Nginx) |

### Stack

- **PHP 8.4** (FPM) with Laravel 13
- **MySQL 8.4** (persistent volume)
- **Redis 7** (cache, sessions, queue, events)
- **Nginx 1.27** (reverse proxy, static assets)
- **Node 20** (Socket.IO real-time server)
- **Vite** (asset bundling, development)
- **Spatie Laravel Permission** (roles & permissions)

## Environment Variables

Key `.env` settings (auto-created from `.env.example`):

- `APP_URL=http://localhost:8080` — used for links in emails/redirects
- `DB_HOST=mysql` (container name, not localhost)
- `REDIS_HOST=redis` (container name)
- `SOCKET_SECRET` — auto-generated during setup; do not edit
- `MAIL_HOST=mailpit` (in-memory, no actual email)

For production, update `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and any third-party API keys.

## Data & Persistence

- **MySQL:** persisted in Docker volume `mlm_mysql`
- **Redis:** persisted in Docker volume `mlm_redis`
- **Project files:** live-bind mounted into the container (edits reflect immediately)

To reset the database:
```bash
make fresh
```

To delete all volumes (including DB):
```bash
docker compose down -v
make setup
```

## Real-Time Events

The system publishes domain events to Redis; a Node.js Socket.IO service (`socket-server/`) relays them to the browser. Events are secured by `SOCKET_SECRET` (generated during setup).

## Debugging

- **Business logic bugs:** See [`code.md`](code.md) for the service/model map.
- **Database issues:** Use phpMyAdmin at `http://localhost:8081`.
- **Email issues:** Check Mailpit at `http://localhost:8025` (no SMTP auth needed in dev).
- **Container logs:** `make logs`
- **Inside the container:** `make shell`

## Project Overview

See [`details.md`](details.md) for:
- Default login credentials and roles
- System business logic (partner hierarchy, commissions, discounts)
- How orders, payments, and audits work

See [`code.md`](code.md) for:
- Route → Controller → View → Model/Service map
- Core service layer (where business logic lives)
- Debugging index ("if X is broken, look here")

## Troubleshooting

**Containers won't start:**
```bash
make build
make up
```

**Database errors on first run:**
```bash
make fresh
```

**Permission denied on storage:**
```bash
make shell
chmod -R 777 storage bootstrap/cache
```

**"Connection refused" when accessing localhost:8080:**
- Ensure containers are running: `make ps`
- Check logs: `make logs`

**Email not sending:**
- Emails go to Mailpit in dev. Check `http://localhost:8081` (phpmyadmin) to verify DB connectivity, and `http://localhost:8025` (Mailpit) to see queued mail.

## Testing

```bash
make test
```

Runs the test suite in an isolated environment (SQLite in-memory, array drivers for cache/session/queue). This does not affect your dev database.

## Code Quality

```bash
make pint
```

Formats code to Laravel standards using Pint.
