# CityU Research Review Portal — Deployment Guide

> **Audience:** Anyone deploying the portal — from a local developer to a cloud sysadmin.  
> **Last updated:** April 2026

---

## Quick Start (TL;DR)

Pick the line that matches your target and run it. Details follow below.

```bash
# Docker on any Linux / macOS / WSL machine (http://localhost)
bash deploy/quick-start-docker.sh

# Docker on a custom port (http://localhost:8080)
bash deploy/quick-start-docker.sh --port 8080

# Docker with a real domain + automatic SSL (production server, run as root)
export ADMIN_EMAIL=admin@myorg.com
sudo bash deploy/quick-start-docker.sh --domain portal.myorg.com --https --no-seed

# Bare-metal Ubuntu 22/24 (no Docker)
sudo bash deploy/install.sh --domain portal.myorg.com --email admin@myorg.com

# Remote cloud VM from your local machine (any provider)
export VM_HOST=YOUR_VM_IP  VM_USER=ubuntu  SSH_KEY=~/.ssh/id_rsa
bash deploy/install-remote.sh --domain portal.myorg.com --email admin@myorg.com
```

After deployment the portal is available at the URL printed at the end of the script.  
Default admin login: `admin@cityu.edu` / `admin12345` — **change immediately**.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Prerequisites](#2-prerequisites)
3. [Option A — Docker Compose (recommended)](#3-option-a--docker-compose-recommended)
4. [Option B — Bare-Metal Linux (Ubuntu)](#4-option-b--bare-metal-linux-ubuntu)
5. [Option C — Remote Cloud VM](#5-option-c--remote-cloud-vm)
6. [Option D — Local Development](#6-option-d--local-development)
7. [Environment Variables](#7-environment-variables)
8. [SSL / HTTPS](#8-ssl--https)
9. [First-Time Configuration](#9-first-time-configuration)
10. [CI/CD with GitHub Actions](#10-cicd-with-github-actions)
11. [Updating the Application](#11-updating-the-application)
12. [Rollback](#12-rollback)
13. [Backups](#13-backups)
14. [Default Seed Accounts](#14-default-seed-accounts)
15. [Windows — Docker Desktop + WSL 2](#15-windows--docker-desktop--wsl-2)

---

## 1. Architecture Overview

```
Browser (HTTPS :443)
    |
    v
[Host Nginx]  -- SSL termination, HSTS, reverse proxy
    | HTTP -> localhost:${HOST_PORT:-80}
    v
[Docker: rrp_app]   Ubuntu 24.04 . PHP 8.4-FPM . Nginx
    |               /*     -> React SPA  (/var/www/frontend/)
    |               /api/* -> Laravel 10 (PHP-FPM :9000)
    |
    +-- [Docker: rrp_worker]   php artisan queue:work
    +-- [Docker: rrp_postgres] PostgreSQL 16  (volume: pgdata)
    +-- [Docker: rrp_redis]    Redis 7        (volume: redis_data)
```

**Docker image** is built from the repo's `Dockerfile` using three stages:

| Stage | Base | What it does |
|---|---|---|
| `frontend-builder` | Node 22 Alpine | `npm run build` -- React SPA static files |
| `composer-builder` | Composer 2 | `composer install --no-dev` |
| Runtime | Ubuntu 24.04 | PHP 8.4-FPM + Nginx; copies artefacts from both stages |

---

## 2. Prerequisites

### Docker deployment (Options A and D)

| Requirement | Minimum version | Check |
|---|---|---|
| Docker Engine | 24+ | `docker --version` |
| Docker Compose | v2 (plugin) | `docker compose version` |
| RAM | 1 GB free | `free -h` |
| Disk | 5 GB free | `df -h` |

Works on: Ubuntu, Debian, Fedora, RHEL, macOS (Docker Desktop), Windows WSL 2.

Install Docker: https://docs.docker.com/engine/install/

### Bare-metal deployment (Option B)

- Ubuntu 22.04 LTS or 24.04 LTS (fresh server recommended)
- Minimum 2 vCPU, 2 GB RAM, 20 GB disk
- Root / sudo access
- Ports 80 and 443 open inbound

### Remote cloud VM (Option C)

- Same as bare-metal above, plus:
- SSH access from your local machine to the VM
- `ssh` installed locally
- `sshpass` installed locally (optional, for password auth): `brew install sshpass` / `apt install sshpass`

---

## 3. Option A -- Docker Compose (recommended)

Use `deploy/quick-start-docker.sh` -- it handles everything: generates `.env`, builds images, starts containers, runs migrations, and optionally provisions SSL.

### Step 1 -- Clone the repository

```bash
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git
cd CityU-Research-Tracker
```

### Step 2 -- Run the quick-start script

**Local machine (http://localhost):**

```bash
bash deploy/quick-start-docker.sh
```

**Production server with a custom domain + SSL:**

```bash
export ADMIN_EMAIL=admin@myorg.com
sudo bash deploy/quick-start-docker.sh \
  --domain portal.myorg.com \
  --https \
  --no-seed
```

**Custom port (e.g. running on the same server as another site):**

```bash
bash deploy/quick-start-docker.sh --domain portal.myorg.com --port 8080
```

**Bring your own `.env` file:**

```bash
bash deploy/quick-start-docker.sh --env-file /path/to/my.env --no-seed
```

### What the script does

1. Checks Docker and Docker Compose are installed
2. Generates a `.env` file with random `APP_KEY`, `DB_PASSWORD`, and `REDIS_PASSWORD` (skipped if `.env` already exists)
3. Sets `APP_URL`, `SESSION_DOMAIN`, and `SANCTUM_STATEFUL_DOMAINS` from `--domain`
4. Runs `docker compose up -d --build`
5. Waits until `rrp_app` is healthy
6. Runs `php artisan migrate --force`
7. Optionally seeds demo accounts
8. Optionally runs `deploy/ssl-setup.sh` to provision a Let's Encrypt certificate
9. Prints the portal URL and useful commands

### Script options

| Option | Default | Description |
|---|---|---|
| `--domain DOMAIN` | `localhost` | Public hostname or IP used in APP_URL and cookie config |
| `--port PORT` | `80` | Host port the portal listens on |
| `--https` | off | Provision Let's Encrypt cert after start (requires root + DNS live) |
| `--no-seed` | off | Skip demo account seeding |
| `--env-file FILE` | -- | Use an existing `.env` instead of auto-generating one |

### Manage the running stack

```bash
# View logs
docker compose logs -f app
docker compose logs -f worker

# Open a shell in the app container
docker exec -it rrp_app bash

# Run any Artisan command
docker exec rrp_app php artisan <command>

# Stop all services (data preserved in Docker volumes)
docker compose down

# Stop and wipe all data -- DESTRUCTIVE
docker compose down -v
```

---

## 4. Option B -- Bare-Metal Linux (Ubuntu)

Run the installer directly on the server. No Docker required -- installs PHP 8.4, PostgreSQL 16, Redis, Nginx, Supervisor, and Certbot as native system services.

```bash
# Clone the repo onto the server
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git /opt/rrp
cd /opt/rrp

# Run the installer (requires root)
sudo bash deploy/install.sh \
  --domain portal.myorg.com \
  --email  admin@myorg.com
```

**Dev mode (no SSL, SQLite):**

```bash
sudo bash deploy/install.sh --dev
```

### install.sh options

| Option | Description |
|---|---|
| `--domain DOMAIN` | Public domain name -- used in APP_URL and Nginx vhost |
| `--email EMAIL` | Admin email for Let's Encrypt and seed admin account |
| `--skip-ssl` | Skip TLS certificate provisioning (configure SSL later) |
| `--dev` | Development mode: no SSL, uses SQLite, binds to localhost only |

### What the installer does

1. Installs PHP 8.4-FPM, Composer, Nginx, PostgreSQL 16, Redis 7, Node 20, Certbot
2. Creates system user `rrp` and application directory `/var/www/rrp/`
3. Copies application files; installs Composer and npm dependencies
4. Creates the PostgreSQL database and user with a random password
5. Generates `APP_KEY`; writes `/var/www/rrp/backend/.env`
6. Runs database migrations and seeds
7. Configures PHP-FPM pool, Nginx vhost, and Supervisor for queue workers
8. Obtains a Let's Encrypt TLS certificate (if `--domain` provided and DNS is live)
9. Installs a daily cron for `php artisan schedule:run`

After installation the portal is running as a system service and restarts automatically on reboot.

---

## 5. Option C -- Remote Cloud VM

Deploy to any cloud provider (Azure, AWS, GCP, DigitalOcean, Hetzner, etc.) from your local machine. The script SSHes into the VM, copies the repo, and runs `deploy/install.sh` remotely.

### From your local machine

```bash
# Clone the repo locally first
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git
cd CityU-Research-Tracker

# Set connection details
export VM_HOST=YOUR_VM_IP        # VM public IP or hostname
export VM_USER=ubuntu          # SSH username (ubuntu, azureadmin, ec2-user, etc.)
export SSH_KEY=~/.ssh/id_rsa   # Path to your private key
# Or use a password instead:
# export VM_PASS=YourPassword

# Deploy
bash deploy/install-remote.sh \
  --domain portal.myorg.com \
  --email  admin@myorg.com
```

### install-remote.sh options

| Option | Description |
|---|---|
| `--domain DOMAIN` | Required. Public domain name for the portal |
| `--email EMAIL` | Required. Admin email for Let's Encrypt |
| `--skip-ssl` | Skip SSL provisioning on the remote server |

### Required environment variables

| Variable | Description |
|---|---|
| `VM_HOST` | VM IP address or hostname |
| `VM_USER` | SSH login username |
| `SSH_KEY` | Path to SSH private key (recommended) |
| `VM_PASS` | SSH password (alternative to SSH_KEY; requires `sshpass` installed) |

### What the script does

1. Verifies SSH connectivity to the VM
2. Copies the repository to `/opt/rrp-v2/` on the VM via rsync over SSH
3. SSHes in and runs `deploy/install.sh --domain ... --email ...`
4. Reports success and prints the portal URL

### Provider-specific examples

**Azure VM:**
```bash
export VM_HOST=YOUR_VM_IP          # e.g. the Public IP from the Azure portal
export VM_USER=azureadmin          # default Azure VM username
export SSH_KEY=~/.ssh/azure_rsa
bash deploy/install-remote.sh --domain portal.myorg.com --email admin@myorg.com
```

**AWS EC2:**
```bash
export VM_HOST=YOUR_EC2_PUBLIC_DNS  # e.g. ec2-xx-xx-xx-xx.compute-1.amazonaws.com
export VM_USER=ec2-user
export SSH_KEY=~/.ssh/my-ec2-key.pem
bash deploy/install-remote.sh --domain portal.myorg.com --email admin@myorg.com
```

**DigitalOcean / Hetzner / any Ubuntu VPS:**
```bash
export VM_HOST=YOUR_VM_IP
export VM_USER=root
export SSH_KEY=~/.ssh/id_rsa
bash deploy/install-remote.sh --domain portal.myorg.com --email admin@myorg.com
```

> **DNS first:** Point your domain A record to the VM public IP *before* running the script. Let's Encrypt will fail if DNS is not resolving.

---

## 6. Option D -- Local Development

Run the portal on `localhost` with no domain or SSL required.

```bash
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git
cd CityU-Research-Tracker

# Start on http://localhost:8080
bash deploy/quick-start-docker.sh --port 8080
```

The script generates a `.env` with `APP_URL=http://localhost:8080` and seeds demo accounts automatically.

### Bring your own .env

```bash
bash deploy/quick-start-docker.sh --port 8080 --env-file .env.local
```

### macOS (Docker Desktop)

Port 80 may require elevated privileges on macOS. Use `--port 8080` to avoid this:

```bash
bash deploy/quick-start-docker.sh --port 8080
```

### Windows (WSL 2)

Run the script inside a WSL 2 terminal (Ubuntu shell). Docker Desktop with the WSL 2 backend is recommended.

```bash
# Inside WSL terminal
bash deploy/quick-start-docker.sh --port 8080
```

The portal will be accessible at `http://localhost:8080` from your Windows browser.

---

## 7. Environment Variables

The `.env` file controls all runtime configuration. `quick-start-docker.sh` generates it automatically. Edit it to configure email, SSO, file storage, and integrations, then reload:

```bash
docker exec rrp_app php artisan config:cache
```

### Core

| Variable | Required | Default | Description |
|---|---|---|---|
| `APP_KEY` | Yes | -- | 32-byte base64 key. Auto-generated by install scripts |
| `APP_ENV` | Yes | `production` | Must be `production` in prod |
| `APP_DEBUG` | Yes | `false` | Must be `false` in prod |
| `APP_URL` | Yes | `http://localhost` | Full public URL (no trailing slash) |
| `LOG_CHANNEL` | -- | `stderr` | Use `stderr` in Docker; read logs via `docker logs rrp_app` |
| `LOG_LEVEL` | -- | `error` | Use `debug` only when troubleshooting |

### Database

| Variable | Required | Default | Description |
|---|---|---|---|
| `DB_HOST` | Yes | `postgres` | Docker service name or `127.0.0.1` (bare-metal) |
| `DB_DATABASE` | Yes | `rrp_production` | Database name |
| `DB_USERNAME` | Yes | `rrp_app` | Database user |
| `DB_PASSWORD` | Yes | -- | Strong random password |

### Cache / Queue

| Variable | Default | Description |
|---|---|---|
| `REDIS_HOST` | `redis` | Docker service name or `127.0.0.1` (bare-metal) |
| `REDIS_PASSWORD` | -- | Redis auth password (recommended) |
| `QUEUE_CONNECTION` | `redis` | Use `redis` in production |

### Auth / CORS

| Variable | Required | Description |
|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | Yes | Frontend domain(s) -- must match the browser URL bar exactly |
| `SESSION_DOMAIN` | Yes | Cookie domain |

### Email

| Variable | Default | Description |
|---|---|---|
| `MAIL_MAILER` | `log` | `log` (suppress sending), `smtp`, or `ses` |
| `MAIL_HOST` | -- | SMTP hostname |
| `MAIL_PORT` | `587` | SMTP port |
| `MAIL_USERNAME` | -- | SMTP username |
| `MAIL_PASSWORD` | -- | SMTP password |
| `MAIL_FROM_ADDRESS` | -- | Sender email address |

### File Storage

| Variable | Default | Description |
|---|---|---|
| `FILESYSTEM_DISK` | `local` | `local`, `azure`, or `s3` |
| `AZURE_STORAGE_NAME` | -- | Azure Storage account name (if `FILESYSTEM_DISK=azure`) |
| `AZURE_STORAGE_KEY` | -- | Azure Storage account key |
| `AWS_ACCESS_KEY_ID` | -- | AWS access key (if `FILESYSTEM_DISK=s3`) |
| `AWS_SECRET_ACCESS_KEY` | -- | AWS secret key |
| `AWS_DEFAULT_REGION` | `us-east-1` | S3 region |
| `AWS_BUCKET` | -- | S3 bucket name |

---

## 8. SSL / HTTPS

> **Optional step.** HTTP works without this. Follow these steps when you are ready to enable HTTPS and disable plain HTTP.

### Automatic (Let's Encrypt) -- recommended

#### Prerequisites

Before running `ssl-setup.sh` the Docker container **must not** be bound to port 80.
Host nginx needs port 80 to complete the ACME challenge.

**Step 1 — Move Docker off port 80 (skip if already on 8080)**

```bash
# On the server, inside the repo directory:
cd ~/CityU-Research-Tracker

# Set HOST_PORT=8080 in .env (adds the line if absent, updates it if present)
grep -q '^HOST_PORT=' .env && sed -i 's/^HOST_PORT=.*/HOST_PORT=8080/' .env || echo 'HOST_PORT=8080' >> .env

# Update APP_URL to https (replace portal.myorg.com with your actual domain)
sed -i 's|^APP_URL=.*|APP_URL=https://portal.myorg.com|' .env
sed -i 's/^SESSION_DOMAIN=.*/SESSION_DOMAIN=portal.myorg.com/' .env
sed -i 's/^SANCTUM_STATEFUL_DOMAINS=.*/SANCTUM_STATEFUL_DOMAINS=portal.myorg.com/' .env

# Restart container on new port
docker compose up -d
```

**Step 2 — Obtain certificate and configure host nginx**

```bash
sudo bash deploy/ssl-setup.sh portal.myorg.com admin@myorg.com 8080
```

Replace `portal.myorg.com` with your actual domain and `admin@myorg.com` with your email.

Pass `--https` to the quick-start script for a fresh install that does everything in one go:

```bash
export ADMIN_EMAIL=admin@myorg.com
sudo bash deploy/quick-start-docker.sh --domain portal.myorg.com --https --no-seed
```

**Requirements:**
- Domain A/CNAME record must point to this server's public IP
- Port 80 **and** 443 open in firewall/NSG for the ACME challenge and HTTPS traffic
- Docker container on port 8080 (not 80) before running `ssl-setup.sh`
- Must run as root (sudo)

**Common failure — ACME connection timeout:**
If certbot reports `Timeout during connect (likely firewall problem)` it means either:
1. Docker is still on port 80 — complete Step 1 above first
2. Port 80 is blocked in the cloud firewall/NSG — open it for inbound traffic

### Manual renewal

```bash
sudo certbot renew --quiet
sudo systemctl reload nginx
```

### Existing certificate

```bash
sudo cp fullchain.pem /etc/ssl/rrp/fullchain.pem
sudo cp privkey.pem   /etc/ssl/rrp/privkey.pem
sudo cp deploy/nginx-vhost.conf /etc/nginx/sites-available/rrp
sudo sed -i 's/PORTAL_DOMAIN/portal.myorg.com/g' /etc/nginx/sites-available/rrp
sudo nginx -t && sudo systemctl reload nginx
```

### Upload size limit

Edit `deploy/nginx-vhost.conf` and set:

```nginx
client_max_body_size 100M;
```

Then: `sudo systemctl reload nginx`

---

## 9. First-Time Configuration

Log in as `admin@cityu.edu` and complete these steps before opening the portal to users.

### Required

1. **Change all default passwords** -- Admin > User Management > edit each seed account
2. **Set your organisation name** -- Admin > Settings > Organisation > Portal Name
3. **Configure email** -- Admin > Settings > Email > enter SMTP details > Send Test Email
4. **Set the correct timezone** -- Admin > Settings > Organisation > Timezone

### Recommended

5. **Configure password policy** -- Admin > Settings > Password and Security
6. **Add a Submission Category** -- Admin > Submission Categories > + New Category
7. **Set up SSO** (if using Azure AD / OIDC) -- Admin > Settings > SSO Providers > + Add Provider
8. **Enable daily backups** -- add to `/etc/crontab`:
   ```
   0 2 * * * root bash /opt/rrp-v2/deploy/backup.sh --keep-days 14 >> /var/log/rrp-backup.log 2>&1
   ```
9. **Run the smoke test checklist** -- see `deploy/smoke-test-checklist.md`

---

## 10. CI/CD with GitHub Actions

`.github/workflows/` contains pre-configured pipelines.

| Job | Trigger | What it does |
|---|---|---|
| `backend` | every push / PR | PHPUnit tests (SQLite in-memory) |
| `frontend` | every push / PR | TypeScript check, Vitest, `vite build` |
| `deploy` | push to `main` only | SSHes to VM; runs `deploy/update.sh` |

### Required GitHub Secrets

**Settings > Secrets and variables > Actions:**

| Secret | Description |
|---|---|
| `VM_HOST` | Production VM IP |
| `VM_USER` | SSH username |
| `VM_PASS` or `SSH_KEY` | SSH credentials |

### Manual deploy trigger

```bash
VM_HOST=YOUR_VM_IP VM_USER=ubuntu SSH_KEY=~/.ssh/id_rsa \
  bash deploy/update.sh
```

---

## 11. Updating the Application

Use `deploy/update.sh` to push code changes without rebuilding the entire image.

```bash
# Standard update (backend + frontend + migrations)
VM_HOST=YOUR_VM_IP VM_USER=ubuntu bash deploy/update.sh

# Backend only
VM_HOST=YOUR_VM_IP VM_USER=ubuntu bash deploy/update.sh --backend-only

# Frontend only (no migrations)
VM_HOST=YOUR_VM_IP VM_USER=ubuntu bash deploy/update.sh --frontend-only

# Zero-downtime blue-green swap
VM_HOST=YOUR_VM_IP VM_USER=ubuntu bash deploy/update.sh --zero-downtime
```

### What the update does

1. Rsyncs changed PHP files to the VM (skips `vendor/`, `.env`, `storage/`)
2. Runs `composer install --no-dev` inside the container
3. Builds the React SPA (`npm run build`) and copies it into the container
4. Runs `php artisan migrate --force`
5. Clears and rebuilds config, route, and view caches
6. Issues `php artisan queue:restart` for a graceful worker restart
7. Reloads Nginx

### Manual cache clear

```bash
docker exec rrp_app php artisan config:cache
docker exec rrp_app php artisan route:cache
docker exec rrp_app php artisan view:cache
docker exec rrp_app php artisan queue:restart
```

---

## 12. Rollback

```bash
# Interactive -- lists available backups, prompts for selection
sudo bash /opt/rrp-v2/deploy/rollback.sh

# Restore a specific archive (non-interactive)
sudo bash /opt/rrp-v2/deploy/rollback.sh \
  --to /opt/rrp-backups/rrp-backup-20260422_020001.tar.gz

# Database only
sudo bash /opt/rrp-v2/deploy/rollback.sh --db-only

# Application files only
sudo bash /opt/rrp-v2/deploy/rollback.sh --app-only
```

### Migration-only rollback

```bash
docker exec rrp_app php artisan migrate:rollback --step=1 --force
```

> Always take a fresh backup before rolling back.

---

## 13. Backups

```bash
# Manual backup (creates timestamped .tar.gz in /opt/rrp-backups/)
sudo bash /opt/rrp-v2/deploy/backup.sh

# Retain last 30 days only
sudo bash /opt/rrp-v2/deploy/backup.sh --keep-days 30

# Database dump only
sudo bash /opt/rrp-v2/deploy/backup.sh --db-only

# Upload to Azure Blob Storage after backup
AZURE_STORAGE_ACCOUNT=myaccount AZURE_STORAGE_KEY=mykey \
  sudo bash /opt/rrp-v2/deploy/backup.sh --upload
```

### Daily automated backup (cron)

```bash
# /etc/crontab -- runs at 02:00 daily as root
0 2 * * * root bash /opt/rrp-v2/deploy/backup.sh --keep-days 14 >> /var/log/rrp-backup.log 2>&1
```

---

## 14. Default Seed Accounts

Created when `db:seed` runs (automatic unless `--no-seed` is passed to `quick-start-docker.sh`).

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@cityu.edu` | `admin12345` |
| Coordinator | `coordinator@cityu.edu` | `admin12345` |
| Reviewer | `reviewer@cityu.edu` | `admin12345` |
| Student | `student@cityu.edu` | `admin12345` |

> **Change all default passwords immediately after first login in production.**

---

## 15. Windows — Docker Desktop + WSL 2

Running the portal on a Windows machine is fully supported via **Docker Desktop** with the **WSL 2 backend**. All deployment scripts are standard Bash and run without modification inside a WSL 2 distribution.

### Prerequisites

| Requirement | Notes |
|---|---|
| Windows 10 21H2+ or Windows 11 | WSL 2 requires kernel 5.10+ |
| Docker Desktop for Windows | https://docs.docker.com/desktop/install/windows-install/ |
| WSL 2 enabled with a Linux distro | Ubuntu 22.04 or 24.04 recommended |
| Git | Either Windows Git or `git` inside WSL |

### Step 1 — Enable WSL 2 and install Ubuntu

Open PowerShell as Administrator and run:

```powershell
# Enable WSL and install Ubuntu 24.04 in one command (Windows 10 2004+ / Windows 11)
wsl --install -d Ubuntu-24.04
```

Restart your machine when prompted. On first launch Ubuntu will ask you to create a UNIX username and password.

If WSL is already installed but set to v1 for your distro, upgrade it:

```powershell
wsl --set-version Ubuntu-24.04 2
wsl --set-default-version 2
```

### Step 2 — Install and configure Docker Desktop

1. Download and install **Docker Desktop for Windows** from https://docs.docker.com/desktop/install/windows-install/
2. During installation ensure **"Use WSL 2 instead of Hyper-V"** is checked.
3. After installation open Docker Desktop → **Settings → Resources → WSL Integration**.
4. Enable integration for your Ubuntu distro (toggle on).
5. Click **Apply & Restart**.

Verify from inside WSL:

```bash
# Open Ubuntu from the Start menu (or: wsl -d Ubuntu-24.04)
docker --version          # Docker Engine 24+
docker compose version    # Docker Compose v2.x
```

### Step 3 — Clone the repository inside WSL

Always work inside the WSL filesystem (`/home/<user>/`) rather than the Windows filesystem (`/mnt/c/...`). Disk I/O for Docker bind-mounts is much faster.

```bash
# Inside the Ubuntu WSL terminal
cd ~
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git
cd CityU-Research-Tracker
```

### Step 4 — Run the quick-start script

```bash
# Local dev on http://localhost:8080
bash deploy/quick-start-docker.sh --port 8080
```

Open your **Windows browser** and go to `http://localhost:8080/` — Docker Desktop bridges the WSL 2 network to Windows automatically.

For a custom domain with SSL (production-like setup on Windows is uncommon, but possible with a hosts-file entry):

```bash
bash deploy/quick-start-docker.sh --domain myportal.local --port 8080
```

Add `127.0.0.1 myportal.local` to `C:\Windows\System32\drivers\etc\hosts` on the Windows side.

### Step 5 — Manage the stack

All `docker` and `docker compose` commands run from the WSL terminal:

```bash
# View logs
docker compose logs -f app

# Open a shell in the app container
docker exec -it rrp_app bash

# Run Artisan commands
docker exec rrp_app php artisan migrate:status
docker exec rrp_app php artisan config:cache

# Stop (data preserved)
docker compose down

# Stop and wipe all data — DESTRUCTIVE
docker compose down -v
```

You can also use the **Docker Desktop GUI** on the Windows side to start/stop containers and view logs — it shows all containers running in WSL 2.

### Accessing files from Windows Explorer

Your WSL filesystem is accessible in Windows Explorer at:

```
\\wsl$\Ubuntu-24.04\home\<your-username>\CityU-Research-Tracker
```

Or pin it to Quick Access by typing that path in the Explorer address bar.

### Troubleshooting

| Symptom | Fix |
|---|---|
| `docker: command not found` inside WSL | Open Docker Desktop → Settings → Resources → WSL Integration → enable your distro → Apply & Restart |
| Port 80 already in use | Use `--port 8080` (or any free port) |
| Slow file I/O | Clone the repo inside WSL (`~/`) not on the Windows drive (`/mnt/c/`) |
| `docker compose` not found | Upgrade to Docker Desktop 4.x+ which bundles Compose v2 as a plugin |
| Container healthy but browser shows nothing | Check Windows Defender Firewall is not blocking port 8080; try disabling temporarily |
| `permission denied` on shell scripts | Run `chmod +x deploy/*.sh` once inside WSL |

### Updating WSL kernel (if required)

```powershell
# In PowerShell (Windows side)
wsl --update
wsl --shutdown   # then re-open Ubuntu
```
