# CityU Research Review Portal — Getting Started

> **Repository:** https://github.com/cityuseattle/CityU-Research-Tracker.git  
> **Stack:** Laravel 10 · PHP 8.4 · PostgreSQL 16 · Redis 7 · React 18 · TypeScript · Vite · Tailwind CSS

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Clone the Repository](#2-clone-the-repository)
3. [Quick Start — Docker (recommended)](#3-quick-start--docker-recommended)
4. [Local Development (no Docker)](#4-local-development-no-docker)
5. [Production Deployment](#5-production-deployment)
6. [Default Demo Accounts](#6-default-demo-accounts)
7. [Project Structure](#7-project-structure)
8. [API Reference (Phase 1)](#8-api-reference-phase-1)

---

## 1. Prerequisites

### Docker path (fastest)

| Tool | Minimum version | Install |
|---|---|---|
| Docker Engine | 24+ | https://docs.docker.com/engine/install/ |
| Docker Compose | v2 (plugin) | bundled with Docker Desktop / Engine 24+ |

Works on Linux, macOS (Docker Desktop), and Windows (WSL 2 + Docker Desktop).

### Local dev path (no Docker)

| Tool | Version | Install |
|---|---|---|
| PHP | 8.4 | https://www.php.net/downloads |
| Composer | 2 | https://getcomposer.org/ |
| Node.js | 22 | https://nodejs.org/ |
| PostgreSQL | 16 | https://www.postgresql.org/download/ |
| Redis | 7 | https://redis.io/docs/getting-started/ |

---

## 2. Clone the Repository

```bash
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git
cd CityU-Research-Tracker
```

---

## 3. Quick Start — Docker (recommended)

The `deploy/quick-start-docker.sh` script handles everything: generates `.env` with random credentials, builds images, starts containers, runs migrations, and optionally provisions SSL.

### Local development (http://localhost:8080)

```bash
bash deploy/quick-start-docker.sh --port 8080
```

### Local on the default port (http://localhost)

```bash
bash deploy/quick-start-docker.sh
```

### Production server with SSL

```bash
export ADMIN_EMAIL=admin@myorg.com
sudo bash deploy/quick-start-docker.sh \
  --domain portal.myorg.com \
  --https \
  --no-seed
```

### What the script does

1. Checks Docker and Docker Compose are installed
2. Generates a `.env` file with random `APP_KEY`, `DB_PASSWORD`, and `REDIS_PASSWORD`
3. Sets `APP_URL`, `SESSION_DOMAIN`, and `SANCTUM_STATEFUL_DOMAINS` from `--domain`
4. Runs `docker compose up -d --build`
5. Waits until the `rrp_app` container is healthy
6. Runs `php artisan migrate --force` and `storage:link`
7. Optionally seeds demo accounts (omit with `--no-seed` for production)
8. Optionally provisions a Let's Encrypt certificate (`--https`)
9. Prints the portal URL and useful management commands

### Managing the running stack

```bash
# View live logs
docker compose logs -f app
docker compose logs -f worker

# Open a shell in the app container
docker exec -it rrp_app bash

# Run any Artisan command
docker exec rrp_app php artisan <command>

# Reload config after editing .env
docker exec rrp_app php artisan config:cache

# Stop all services (data preserved in Docker volumes)
docker compose down

# Stop and wipe all data — DESTRUCTIVE
docker compose down -v
```

---

## 4. Local Development (no Docker)

### Backend (Laravel)

```bash
cd backend

# Install PHP dependencies
composer install

# Copy and edit the environment file
cp .env.example .env
# Set DB_HOST=127.0.0.1, DB_PASSWORD, REDIS_PASSWORD, etc.
nano .env

# Generate the application key
php artisan key:generate

# Run migrations and seed demo data
php artisan migrate --seed

# Start the development server (port 8000)
php artisan serve
```

### Frontend (React)

```bash
cd frontend

# Install Node dependencies
npm install

# Start the Vite dev server (port 5173, proxies /api -> :8000)
npm run dev
```

Open http://localhost:5173

> The Vite dev server proxies `/api/*` and `/sanctum/*` requests to `http://localhost:8000` automatically — no CORS setup needed during local dev.

---

## 5. Production Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for the full guide covering all deployment scenarios. Quick reference:

| Scenario | Command |
|---|---|
| Any machine with Docker | `bash deploy/quick-start-docker.sh --domain portal.myorg.com --https --no-seed` |
| Bare-metal Ubuntu 22/24 | `sudo bash deploy/install.sh --domain portal.myorg.com --email admin@myorg.com` |
| Remote cloud VM (Azure / AWS / GCP / etc.) | See below |

### Remote cloud VM (from your local machine)

```bash
# Set your VM connection details
export VM_HOST=YOUR_VM_IP        # VM public IP address
export VM_USER=azureadmin        # SSH username (azureadmin for Azure, ec2-user for AWS, etc.)
export SSH_KEY=~/.ssh/id_rsa     # Path to your SSH private key

# Deploy to the VM
bash deploy/install-remote.sh \
  --domain portal.myorg.com \
  --email  admin@myorg.com
```

The script copies the repository to `/opt/rrp-v2/` on the VM, installs Docker if missing, generates a `.env` with fresh credentials, and starts the stack.

### After first deployment

1. **Change all default passwords** — Admin > User Management
2. **Set your organisation name** — Admin > Settings > Organisation
3. **Configure email** — Admin > Settings > Email
4. **Run the smoke-test checklist** — `deploy/smoke-test-checklist.md`

---

## 6. Default Demo Accounts

Created automatically when `db:seed` runs. **Change all passwords before going live.**

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@cityu.edu` | `admin12345` |
| Coordinator | `coordinator@cityu.edu` | `admin12345` |
| Reviewer | `reviewer@cityu.edu` | `admin12345` |
| Student | `student@cityu.edu` | `admin12345` |

---

## 7. Project Structure

```
CityU-Research-Tracker/
├── backend/                    Laravel 10 API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/    Route controllers
│   │   │   ├── Middleware/     EnsureRole, etc.
│   │   │   └── Resources/      API resource transformers
│   │   └── Models/             Eloquent models
│   ├── database/
│   │   ├── migrations/         Database schema (all tables)
│   │   └── seeders/            DatabaseSeeder — demo accounts
│   ├── routes/api.php          All API routes
│   └── .env.example            Environment template
│
├── frontend/                   React 18 + Vite + TypeScript + Tailwind CSS
│   └── src/
│       ├── components/         Shared UI (guards, layout shell)
│       ├── lib/                Axios client, React Query setup
│       ├── pages/              Route-level page components
│       ├── stores/             Zustand state (auth, etc.)
│       ├── types/              TypeScript interfaces
│       └── router.tsx          React Router configuration
│
├── docker/
│   ├── nginx.conf              Internal Nginx config (port 8080, SPA + API)
│   ├── entrypoint.sh           Container startup (secrets, migrate, FPM+Nginx)
│   └── postgres-init.sql       rrp_readonly role setup
│
├── deploy/
│   ├── quick-start-docker.sh   One-command Docker deployment
│   ├── install.sh              Bare-metal Ubuntu installer
│   ├── install-remote.sh       Remote VM deployment via SSH
│   ├── ssl-setup.sh            Let's Encrypt certificate provisioning
│   ├── update.sh               Rolling update (code + migrations)
│   ├── rollback.sh             Restore from backup
│   ├── backup.sh               Database + file backup
│   ├── nginx-vhost.conf        Host-level Nginx reverse proxy template
│   ├── supervisord.conf        Supervisor config for Docker stack management
│   ├── watchdog.sh             Health-check watchdog
│   └── smoke-test-checklist.md Post-deployment verification steps
│
├── Dockerfile                  Multi-stage: Node 22 → Composer 2 → Ubuntu 24.04
├── docker-compose.yml          app + worker + postgres + redis
├── DEPLOYMENT.md               Full deployment guide (all scenarios)
├── OPERATIONS-MANUAL.md        Sysadmin reference (monitoring, backups, etc.)
└── README.md                   Project overview
```

---

## 8. API Reference (Phase 1)

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/auth/login` | — | Login, returns Sanctum token |
| POST | `/api/auth/logout` | ✓ | Revoke current token |
| GET | `/api/auth/me` | ✓ | Current authenticated user |
| POST | `/api/auth/change-password` | ✓ | Change own password |
| GET | `/api/system/public` | — | Org branding for the login page |
| GET | `/api/system/organization` | ✓ Admin | Organisation settings |
| PATCH | `/api/system/organization` | ✓ Admin | Update organisation settings |
| POST | `/api/system/organization/logo` | ✓ Admin | Upload organisation logo |
| GET | `/api/system/feature-flags` | ✓ Admin | All feature flags |
| PATCH | `/api/system/feature-flags` | ✓ Admin | Update feature flags |
| GET | `/api/system/password-policy` | ✓ Admin | Current password policy |
| PATCH | `/api/system/password-policy` | ✓ Admin | Update password policy |