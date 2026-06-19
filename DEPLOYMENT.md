# CityU Research Review Portal — Deployment Guide

> **Audience:** Anyone deploying the portal — from a local developer to a cloud sysadmin.  
> **Last updated:** June 2026

---

## Quick Start (TL;DR)

Pick the line that matches your target and run it. Details follow below.

```bash
# Docker on any Linux / macOS / WSL machine (http://localhost)
# Creates seed accounts: admin@cityu.edu, coordinator@cityu.edu, etc.
bash deploy/quick-start-docker.sh

# Docker on a custom port (http://localhost:8080)
bash deploy/quick-start-docker.sh --port 8080

# Docker with a real domain + automatic SSL (production server, run as root)
# ⚠️  Use --no-seed for production (manually create first admin account instead)
export ADMIN_EMAIL=your-email@example.com
sudo bash deploy/quick-start-docker.sh --domain portal.myorg.com --https --no-seed

# Bare-metal Ubuntu 22/24/26 (no Docker)
# ⚠️  Always creates seed accounts (admin@cityu.edu + others)
sudo bash deploy/install.sh --domain portal.myorg.com --email your-email@example.com

# Remote cloud VM from your local machine (any provider)
# Creates seed accounts on the remote VM
export VM_HOST=YOUR_VM_IP  VM_USER=ubuntu  SSH_KEY=~/.ssh/id_rsa
bash deploy/install-remote.sh --domain portal.myorg.com --email your-email@example.com
```

After deployment the portal is available at the URL printed at the end of the script.

**First-time login:**
- **If you did NOT use `--no-seed`:** Default admin login is `admin@cityu.edu` / `admin12345` — change these immediately.
- **If you used `--no-seed` (recommended for production):** See [Creating the first admin account](#creating-the-first-admin-account-no-seed) below.

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
15. [Windows Deployment Workflows](#15-windows-deployment-workflows)
16. [Post-Deployment Validation (Docker + Direct)](#16-post-deployment-validation-docker--direct)

---

## 1. Architecture Overview

```
Browser (HTTPS :443)
    |
    v
[Host Nginx]  ← installed on the host OS by ssl-setup.sh
    |           SSL termination, HSTS, reverse proxy
    |           Ports 80 (redirect) + 443 (HTTPS)
    | HTTP proxies to → localhost:8080 (when SSL enabled)
    |                → localhost:80   (HTTP-only mode, no host nginx)
    v
[Docker: rrp_app]   Ubuntu 24.04 · PHP 8.4-FPM · Nginx (container)
  |               /*     -> React SPA  (/var/www/frontend/)
  |               /api/* -> Laravel 11 (PHP-FPM :9000)
    |
    +-- [Docker: rrp_worker]   php artisan queue:work
    +-- [Docker: rrp_postgres] PostgreSQL 16  (volume: pgdata)
    +-- [Docker: rrp_redis]    Redis 7        (volume: redis_data)
```

> **Two-layer nginx (SSL mode):** There are two separate nginx instances. The host nginx (Ubuntu package) owns ports 80/443, terminates TLS, and proxies plain HTTP to the container on port 8080. The container nginx (inside Docker) serves the React SPA and routes `/api/*` to PHP-FPM. In HTTP-only mode (no SSL) there is no host nginx — Docker binds port 80 directly.

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

- Ubuntu 22.04 LTS, 24.04 LTS, or newer Ubuntu releases (e.g., 26.04)
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
export ADMIN_EMAIL=your-email@example.com
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
8. Optionally runs `deploy/ssl-setup.sh` which: installs **nginx on the host** (separate from the container's nginx), obtains a Let's Encrypt certificate, and configures host nginx as an SSL-terminating reverse proxy that forwards traffic to Docker on port 8080
9. Prints the portal URL and useful commands

> **Two-layer nginx:** When SSL is enabled the portal runs two nginx instances: one inside the Docker container (serves React SPA + PHP-FPM on port 8080) and one on the host OS (handles port 80/443, terminates SSL, proxies to `127.0.0.1:8080`). This is why `ssl-setup.sh` must run on the host, not inside Docker.

### Script options

| Option | Default | Description |
|---|---|---|
| `--domain DOMAIN` | `localhost` | Public hostname or IP used in APP_URL and cookie config |
| `--port PORT` | `80` | Host port Docker binds on the host (auto-overridden to `8080` when `--https` is used) |
| `--https` | off | Provision Let's Encrypt cert after start. Requires: `ADMIN_EMAIL` set, real domain with DNS live, ports 80+443 open, root/sudo. Automatically moves Docker to port 8080 so host nginx can own port 80 for the ACME challenge. |
| `--no-seed` | off | Skip demo account seeding (recommended for production) |
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

### Production verification (Docker)

Run these checks after every fresh deployment or update:

```bash
# 1) Containers and health
docker compose ps

# 2) API health from inside the host
# HTTP-only mode:
curl -fsS http://127.0.0.1:${HOST_PORT:-80}/api/system/public
# HTTPS mode (when --https is enabled):
curl -kfsS https://127.0.0.1/api/system/public

# 3) Laravel production environment
docker exec rrp_app php artisan about --only=environment

# 4) Queue worker running
docker compose logs --tail=50 worker
```

Expected result:
- `rrp_app` status is `healthy`
- API health endpoint returns JSON
- `Application Environment` shows `production`
- No repeating worker crash loop in logs

---

## 4. Option B -- Bare-Metal Linux (Ubuntu)

Run the installer directly on the server. No Docker required -- installs PHP, PostgreSQL, Redis, Nginx, Supervisor, and Certbot as native system services.

```bash
# Clone the repo onto the server
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git /opt/rrp
cd /opt/rrp

# Run the installer (requires root)
sudo bash deploy/install.sh \
  --domain portal.myorg.com \
  --email  your-email@example.com
```

**Dev-like HTTP-only mode (no SSL):**

```bash
sudo bash deploy/install.sh --dev
```

### install.sh options

| Option | Description |
|---|---|
| `--domain DOMAIN` | Public domain name -- used in APP_URL and Nginx vhost |
| `--email EMAIL` | Admin email for Let's Encrypt and seed admin account |
| `--skip-ssl` | Skip TLS certificate provisioning (configure SSL later) |
| `--dev` | Convenience HTTP-only install mode (domain can be omitted) |

### What the installer does

1. Installs PHP-FPM, Composer, Nginx, PostgreSQL, Redis, Node 20, Certbot
2. Creates system user `rrp` and application directory `/var/www/rrp/`
3. Copies application files; installs Composer and npm dependencies
4. Creates the PostgreSQL database and user with a random password
5. Generates `APP_KEY`; writes `/var/www/rrp/backend/.env`
6. Runs database migrations and seeds
7. Configures PHP-FPM pool, Nginx vhost, and Supervisor for queue workers
8. Obtains a Let's Encrypt TLS certificate (if `--domain` provided and DNS is live)
9. Installs a daily cron for `php artisan schedule:run`

Compatibility notes:
- On Ubuntu 22.04/24.04, the script installs PHP 8.4 via `ppa:ondrej/php`.
- On newer Ubuntu codenames where that PPA (or PGDG/Redis upstream repos) is not published yet, the script automatically falls back to distro packages.
- If migrating from Docker+SSL on the same host, the installer removes the legacy `rrp-v2` nginx site symlink to avoid `server_name` conflicts on port 80/443.
- Re-running the installer rotates DB app-user credentials in PostgreSQL and updates `.env` to keep them in sync.

After installation the portal is running as a system service and restarts automatically on reboot. The SPA is served at the site root, not under `/app`.

### Production verification (direct / bare-metal)

```bash
# 1) Core services
sudo systemctl status nginx --no-pager
sudo systemctl status "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm" --no-pager
sudo systemctl status postgresql --no-pager
sudo systemctl status redis-server --no-pager

# 2) Queue worker
sudo supervisorctl status

# 3) API health (replace with your real domain)
# HTTP-only deployments:
curl -fsS http://portal.myorg.com/api/system/public
# HTTPS deployments:
curl -fsS https://portal.myorg.com/api/system/public

# 4) Laravel config
sudo -u rrp php /var/www/rrp/backend/artisan about --only=environment
```

Expected result:
- All services are `active (running)`
- Supervisor shows worker processes as `RUNNING`
- API health endpoint returns JSON
- Laravel environment is `production`

---

## 5. Option C -- Remote Cloud VM

Deploy to any cloud provider (Azure, AWS, GCP, DigitalOcean, Hetzner, etc.) from your local machine. The script SSHes into the VM, copies your local repo contents to the VM, and performs a Docker Compose deployment remotely.

### From your local machine

```bash
# Clone the repo locally first
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git
cd CityU-Research-Tracker

# IMPORTANT: this script is Bash. On Windows, run it from WSL or Git Bash.
# (PowerShell without WSL/Git Bash cannot execute deploy/*.sh directly.)

# Set connection details
export VM_HOST=YOUR_VM_IP        # VM public IP or hostname
export VM_USER=ubuntu          # SSH username (ubuntu, azureadmin, ec2-user, etc.)
export SSH_KEY=~/.ssh/id_rsa   # Path to your private key
# Or use a password instead:
# export VM_PASS=YourPassword

# Deploy
bash deploy/install-remote.sh \
  --domain portal.myorg.com \
  --email  your-email@example.com
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
| `VM_PASS` | SSH password (alternative to SSH_KEY; if `sshpass` is missing, script falls back to interactive SSH password prompts) |

### What the script does

1. Ensures Docker Engine and Docker Compose are installed on the VM
2. Copies the repository to `/opt/rrp-v2/` on the VM via rsync over SSH
3. Generates `/opt/rrp-v2/.env` (if missing) with fresh secrets and domain settings
4. Runs `docker compose up -d --build` on the VM and waits for `rrp_app` health
5. Runs migrations + seed and cache commands inside the app container
6. Optionally runs `deploy/ssl-setup.sh` on the VM (unless `--skip-ssl`)
7. Reports success and prints the portal URL and health URL

> **Private repository note:** the tested remote deployment path assumes the VM does not clone the repository from GitHub directly. Instead, `install-remote.sh` copies your already-checked-out local workspace to the VM. This is the correct path for private repositories and avoids requiring GitHub credentials on the server.

### Troubleshooting (Option C)

| Symptom | Cause | Fix |
|---|---|---|
| `Windows Subsystem for Linux has no installed distributions` when running `bash deploy/install-remote.sh ...` | You are in native PowerShell without a local Bash runtime | Either run from WSL/Git Bash, or use PowerShell `ssh`/`scp` and execute deployment scripts directly on the Linux VM |
| `Connection closed by <ip> port 22` | SSH not reachable or password login disabled by VM/NSG/sshd | Verify NSG allows TCP 22, VM is running, and `PasswordAuthentication yes` in `/etc/ssh/sshd_config` (or use SSH key auth) |
| `permission denied while trying to connect to the Docker daemon socket` during remote deploy | Current SSH session does not yet have updated `docker` group membership | Re-login to VM, or use `sudo docker ...` for the current session |
| `set: pipefail` or `$'pipefail\r'` on Linux | Shell scripts were copied with Windows CRLF line endings | Keep the repository `.gitattributes` file intact so `*.sh`, `Dockerfile`, `docker-compose.yml`, and `*.conf` stay LF on checkout/copy |
| Certbot/SSL fails | DNS/ports not ready | Confirm A-record points to VM and inbound ports 80 and 443 are open, then rerun `sudo bash deploy/ssl-setup.sh <domain> <email> 8080` |

### Provider-specific examples

**Azure VM:**
```bash
export VM_HOST=YOUR_VM_IP          # e.g. the Public IP from the Azure portal
export VM_USER=azureadmin          # default Azure VM username
export SSH_KEY=~/.ssh/azure_rsa
bash deploy/install-remote.sh --domain portal.myorg.com --email your-email@example.com
```

**AWS EC2:**
```bash
export VM_HOST=YOUR_EC2_PUBLIC_DNS  # e.g. ec2-xx-xx-xx-xx.compute-1.amazonaws.com
export VM_USER=ec2-user
export SSH_KEY=~/.ssh/my-ec2-key.pem
bash deploy/install-remote.sh --domain portal.myorg.com --email your-email@example.com
```

**DigitalOcean / Hetzner / any Ubuntu VPS:**
```bash
export VM_HOST=YOUR_VM_IP
export VM_USER=root
export SSH_KEY=~/.ssh/id_rsa
bash deploy/install-remote.sh --domain portal.myorg.com --email your-email@example.com
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

### Windows without WSL (remote VM deployment only)

If you only need to deploy to a remote Linux VM (and not run containers locally on Windows), you can use native PowerShell SSH instead of local Bash scripts:

```powershell
# PowerShell (Windows)
ssh azureadmin@your-vm-hostname

# On the VM shell
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git /opt/rrp-v2
cd /opt/rrp-v2
sudo bash deploy/quick-start-docker.sh --domain portal.myorg.com --no-seed

# Optional HTTPS
export ADMIN_EMAIL=admin@myorg.com
sudo bash deploy/quick-start-docker.sh --domain portal.myorg.com --https --no-seed
```

This path avoids the local Bash dependency entirely.

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
| `ENABLE_EMERGENCY_ADMIN` | No | Break-glass override. Set to `true` to force `emergency.admin@system.local` active even when other admins exist. Default is `false`/unset. |

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

There are two paths depending on whether this is a fresh install or an existing HTTP deployment:

---

#### Path A — Fresh install with HTTPS from the start (recommended)

One command does everything — generates `.env`, builds containers, runs migrations, installs host nginx, obtains certificate:

```bash
export ADMIN_EMAIL=your-email@example.com
sudo bash deploy/quick-start-docker.sh --domain portal.myorg.com --https --no-seed
```

The `--https` flag automatically:
- Binds Docker to port **8080** (not 80) so host nginx can own port 80 for the ACME challenge
- Sets `APP_URL=https://portal.myorg.com` in `.env`
- Runs `ssl-setup.sh` after the containers are healthy

**Requirements:**
- Domain A-record must point to this server's public IP **before** running
- Ports 80 **and** 443 open in firewall/NSG
- Must run as root (`sudo`)
- `ADMIN_EMAIL` environment variable must be set

---

#### Path B — Add HTTPS to an existing HTTP deployment

Use this if the portal is already running on HTTP and you want to upgrade it to HTTPS.

**Step 1 — Move Docker off port 80**

```bash
# On the server, inside the repo directory:
cd ~/CityU-Research-Tracker

# Set HOST_PORT=8080 in .env (adds the line if absent, updates it if present)
grep -q '^HOST_PORT=' .env && sed -i 's/^HOST_PORT=.*/HOST_PORT=8080/' .env || echo 'HOST_PORT=8080' >> .env

# Update APP_URL and cookie domain to https (replace portal.myorg.com with your actual domain)
sed -i 's|^APP_URL=.*|APP_URL=https://portal.myorg.com|' .env
sed -i 's/^SESSION_DOMAIN=.*/SESSION_DOMAIN=portal.myorg.com/' .env
sed -i 's/^SANCTUM_STATEFUL_DOMAINS=.*/SANCTUM_STATEFUL_DOMAINS=portal.myorg.com/' .env

# Restart container on new port
docker compose up -d
```

**Step 2 — Install host nginx, obtain certificate, configure SSL**

```bash
sudo bash deploy/ssl-setup.sh portal.myorg.com your-email@example.com 8080
```

This installs nginx on the host OS, obtains a Let's Encrypt certificate, and configures host nginx to terminate SSL and proxy to the Docker container on port 8080.

**Common failure — ACME connection timeout:**
If certbot reports `Timeout during connect (likely firewall problem)` it means either:
1. Docker is still on port 80 — complete Step 1 first
2. Port 80 is blocked in the cloud firewall/NSG — open it for inbound traffic

If certbot reports `Connection reset by peer` during challenge:
1. The reset is usually upstream of the VM (cloud edge, LB, WAF, NVA, or firewall path), not in the application container.
2. Validate external reachability with `letsdebug.net` for your domain.
3. Retry with standalone mode to isolate host nginx from the challenge path:

```bash
sudo systemctl stop nginx
sudo certbot certonly --standalone --preferred-challenges http \
  -d portal.myorg.com -m your-email@example.com --agree-tos --no-eff-email --non-interactive
sudo systemctl start nginx
```

### Manual renewal

```bash
sudo certbot renew --quiet
sudo systemctl reload nginx 2>/dev/null || sudo service nginx reload
```

Auto-renewal is configured by `ssl-setup.sh` via a cron job at `/etc/cron.d/certbot-renew-rrp` (runs daily at 03:00).

### Existing certificate

If you already have a certificate (e.g. from a corporate CA or another ACME client), place the files where nginx-vhost.conf expects them and apply the vhost:

```bash
# Copy your cert files to the Let's Encrypt path (adjust source paths)
sudo mkdir -p /etc/letsencrypt/live/portal.myorg.com
sudo cp fullchain.pem /etc/letsencrypt/live/portal.myorg.com/fullchain.pem
sudo cp privkey.pem   /etc/letsencrypt/live/portal.myorg.com/privkey.pem

# Apply the nginx vhost (replace domain and proxy port as needed)
sudo bash deploy/apply-ssl-vhost.sh portal.myorg.com 8080
```

The `apply-ssl-vhost.sh` script substitutes the domain and port into `deploy/nginx-vhost.conf`, writes it to `/etc/nginx/sites-available/rrp-v2`, and reloads nginx.

### Upload size limit

Edit `deploy/nginx-vhost.conf` and set:

```nginx
client_max_body_size 100M;
```

Then: `sudo systemctl reload nginx`

---

## 9. First-Time Configuration

### Creating the first admin account (--no-seed)

If you deployed with `--no-seed` flag (recommended for production), no default accounts exist. Create the first admin account via SSH:

```bash
# SSH into the deployment machine
ssh -p 2222 YOUR_VM_IP

# Create admin user (replace email and password as needed)
cd /opt/rrp-v2
echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php -r '
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
\App\Models\User::create([
    "email" => "your-admin@example.com",
    "name" => "Admin User",
    "password_hash" => bcrypt("your-secure-password-here"),
    "is_active" => 1,
    "email_verified_at" => now(),
    "roles" => ["admin"]
]);
echo "Admin created successfully\n";
'
```

Then open the portal in your browser and log in with the email and password you specified above.

### Manually seeding data after a --no-seed deployment

If you intentionally deployed with `--no-seed` and later want baseline data (users, programs, workflows, submission categories), run:

```bash
cd /opt/rrp-v2
echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php artisan db:seed --force
```

Seed only one dataset (examples):

```bash
# Users only
echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php artisan db:seed --class=UsersSeeder --force

# Programs only
echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php artisan db:seed --class=ProgramsSeeder --force

# Submission categories + workflows
echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php artisan db:seed --class=SubmissionTypeSeeder --force
```

Verify seeded row counts:

```bash
echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php artisan db:show --counts
```

Look for non-zero rows in at least these tables:
- `users`
- `programs`
- `submission_types`
- `workflow_definitions`
- `stage_templates`

### Emergency admin break-glass mode

By default, `emergency.admin@system.local` is only active when there are no other active admin users.

If you need temporary emergency access during an outage, you can force-enable it:

```bash
cd /opt/rrp-v2
grep -q '^ENABLE_EMERGENCY_ADMIN=' .env \
  && sed -i 's/^ENABLE_EMERGENCY_ADMIN=.*/ENABLE_EMERGENCY_ADMIN=true/' .env \
  || echo 'ENABLE_EMERGENCY_ADMIN=true' >> .env

echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php artisan config:clear
```

After recovery, disable it again:

```bash
cd /opt/rrp-v2
grep -q '^ENABLE_EMERGENCY_ADMIN=' .env \
  && sed -i 's/^ENABLE_EMERGENCY_ADMIN=.*/ENABLE_EMERGENCY_ADMIN=false/' .env \
  || echo 'ENABLE_EMERGENCY_ADMIN=false' >> .env

echo 'YOUR_SUDO_PASSWORD' | sudo -S docker exec rrp_app php artisan config:clear
```

For normal operations, keep `ENABLE_EMERGENCY_ADMIN=false` (or unset).

### Post-login configuration

Once logged in as an admin, complete these required steps before opening the portal to users:

### Required

1. **Change the admin password** -- Admin > User Management > edit your account
2. **Create other user accounts as needed** -- Admin > User Management > + New User (if not using seed accounts)
3. **Set your organisation name** -- Admin > Settings > Organisation > Portal Name
4. **Configure email** -- Admin > Settings > Email > enter SMTP details > Send Test Email
5. **Set the correct timezone** -- Admin > Settings > Organisation > Timezone

### Recommended

6. **Configure password policy** -- Admin > Settings > Password and Security
7. **Add a Submission Category** -- Admin > Submission Categories > + New Category
8. **Set up SSO** (if using Azure AD / OIDC) -- Admin > Settings > SSO Providers > + Add Provider
9. **Enable daily backups** -- add to `/etc/crontab`:
   ```
   0 2 * * * root bash /opt/rrp-v2/deploy/backup.sh --keep-days 14 >> /var/log/rrp-backup.log 2>&1
   ```
10. **Run the smoke test checklist** -- see `deploy/smoke-test-checklist.md`

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

Use `deploy/update.sh` to push code changes and rebuild the remote Docker stack in a consistent, supported way.

```bash
# Standard update (backend + frontend + migrations)
VM_HOST=YOUR_VM_IP VM_USER=ubuntu bash deploy/update.sh

# Backend only
VM_HOST=YOUR_VM_IP VM_USER=ubuntu bash deploy/update.sh --backend-only

# Frontend only (no migrations)
VM_HOST=YOUR_VM_IP VM_USER=ubuntu bash deploy/update.sh --frontend-only

```

### What the update does

1. Rsyncs the repository contents needed for the selected update scope to the VM
2. Runs `docker compose up -d --build` on the VM
3. Waits for `rrp_app` health to return `healthy`
4. Runs `php artisan migrate --force` unless `--no-migrate` or `--frontend-only` is used
5. Rebuilds config and route caches and restarts the queue worker
6. Validates and reloads Nginx inside the app container

### Unsupported in the current script

- `--zero-downtime` is reserved for future use and currently returns an error instead of attempting a blue-green swap.

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

> **Only created if you do NOT pass the `--no-seed` flag to `quick-start-docker.sh`.** Seed accounts are disabled by default for production deployments.

If seed accounts are created, they are:

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@cityu.edu` | `admin12345` |
| Coordinator | `coordinator@cityu.edu` | `admin12345` |
| Reviewer | `reviewer@cityu.edu` | `admin12345` |
| Student | `student@cityu.edu` | `admin12345` |
| Committee Reviewer | `committee1@cityu.edu` | `admin12345` |
| Committee Reviewer | `committee2@cityu.edu` | `admin12345` |
| Committee Reviewer | `committee3@cityu.edu` | `admin12345` |
| Reviewer | `reviewer1@cityu.edu` | `admin12345` |
| Reviewer | `reviewer2@cityu.edu` | `admin12345` |
| Reviewer | `reviewer3@cityu.edu` | `admin12345` |
| Director (Reviewer role) | `director1@cityu.edu` | `admin12345` |
| Director (Reviewer role) | `director2@cityu.edu` | `admin12345` |
| Director (Reviewer role) | `director3@cityu.edu` | `admin12345` |
| Coordinator | `coordinator1@cityu.edu` | `admin12345` |
| Coordinator | `coordinator2@cityu.edu` | `admin12345` |
| Coordinator | `coordinator3@cityu.edu` | `admin12345` |
| Student | `student1@cityu.edu` | `admin12345` |
| Student | `student2@cityu.edu` | `admin12345` |
| Student | `student3@cityu.edu` | `admin12345` |

> **⚠️ For Production:** Use `--no-seed` flag and manually create your first admin account (see [Creating the first admin account](#creating-the-first-admin-account-no-seed)). This ensures you control all initial credentials and have a clean audit trail.

---

## 15. Windows Deployment Workflows

Windows is supported in two ways:

1. **Path A (optional):** local Docker Desktop + local WSL 2 distro.
2. **Path B (validated in this project):** Windows PowerShell client + Linux VM on Azure (no local WSL required).

---

### Path A — Local Windows + Docker Desktop + WSL 2

Use this when you want to run the full stack locally on your Windows machine.

#### Prerequisites

| Requirement | Notes |
|---|---|
| Windows 10 21H2+ or Windows 11 | WSL 2 requires modern kernel support |
| Docker Desktop for Windows | https://docs.docker.com/desktop/install/windows-install/ |
| WSL 2 enabled with Linux distro | Ubuntu 22.04/24.04 recommended |
| Windows firewall + port forwarding (if external access needed) | Required only when accessing WSL services from other machines/subnets |

Make sure you valid unix distribution. Below exmaple is assuming ubuntu24.04 distribution.
You can install ubuntu-24.04 distribution using below command.

```powershell
wsl --install -d Ubuntu-24.04
```

#### Network prerequisite for WSL (external access only)

If you run the stack in WSL and need access from outside the Windows host (for example from LAN, another VM, or cloud public IP), configure both:

1. Windows port forwarding via `netsh interface portproxy`
2. Windows inbound firewall rules for the same ports

WSL may run in NAT mode instead of mirroring mode. If WSL is running on NAT mode, below configuration is needed.
First, get your current WSL IP from inside the WSL distro:

```bash
# Run inside WSL
hostname -I
```

Then configure forwarding from elevated PowerShell on Windows:

```powershell
# Forward host SSH port 2222 to WSL SSH service
netsh interface portproxy add v4tov4 listenaddress=<windows_ip> listenport=2222 connectaddress=<wsl_ip> connectport=22

# Forward host HTTPS 443 (to local listener on Windows/WSL)
netsh interface portproxy add v4tov4 listenaddress=<windows_ip> listenport=443 connectaddress=<wsl_ip> connectport=443

# Forward host HTTPS 80 (to local listener on Windows/WSL). Port 80 is temporarily needed until ssl is configured.
netsh interface portproxy add v4tov4 listenaddress=<windows_ip> listenport=80 connectaddress=<wsl_ip> connectport=80

# Show current forwarding rules
netsh interface portproxy show all

# Open matching firewall ports
New-NetFirewallRule -DisplayName "Allow Port 2222" -Direction Inbound -Protocol TCP -LocalPort 2222 -Action Allow
New-NetFirewallRule -DisplayName "Allow Port 443"  -Direction Inbound -Protocol TCP -LocalPort 443  -Action Allow
New-NetFirewallRule -DisplayName "Allow Port 80"  -Direction Inbound -Protocol TCP -LocalPort 80  -Action Allow
```

Notes:

- If your app is exposed on HTTP (`--port 8080`), add a matching `8080` portproxy + firewall rule instead of `443`.
- You can get `<wsl_ip>` any time by running `hostname -I` inside the WSL machine.
- WSL IPs are dynamic after reboot; update `connectaddress` when needed.
- If access is only from local Windows browser (`http://localhost` or `http://localhost:8080`), this step is usually not required.

#### Quick steps

Inside the Ubuntu shell:

```bash
git clone https://github.com/cityuseattle/CityU-Research-Tracker.git
cd CityU-Research-Tracker
bash deploy/quick-start-docker.sh --port 8080
```

Open `http://localhost:8080` in Windows browser.

---

### Path B — Windows PowerShell + Azure Linux VM (no local WSL)

This is the validated production workflow used in this project.

#### What this path means

- You run **PowerShell** on Windows as a remote client.
- The application runs on a **Linux VM** (Docker or direct host install).
- You use `ssh` and `scp` from Windows to manage and deploy.
- You do **not** need local WSL to operate production deployments.

#### Prerequisites on Windows client

| Requirement | Notes |
|---|---|
| PowerShell 5.1+ or PowerShell 7+ | Default on modern Windows |
| OpenSSH client (`ssh`, `scp`) | Built into modern Windows |
| Network access to VM SSH port | Example used in this project: `-p 2222` |

#### Example remote operations from Windows PowerShell

```powershell
# SSH into VM
ssh -p 2222 azureadmin@YOUR_VM_HOST

# Copy a file to VM
scp -P 2222 .\frontend\src\pages\UsersPage.tsx azureadmin@YOUR_VM_HOST:/opt/rrp-v2/frontend/src/pages/UsersPage.tsx
```

#### Docker-based deployment on VM

Run on the VM:

```bash
cd /opt/rrp-v2
bash deploy/quick-start-docker.sh --domain portal.myorg.com --https --no-seed
```

#### Direct host-level deployment on VM

Run on the VM:

```bash
cd /opt/rrp-v2
sudo bash deploy/install.sh --domain portal.myorg.com --email your-email@example.com
```

#### Notes for Windows users

- Scripts in `deploy/*.sh` are Bash scripts. Execute them **on Linux** (VM or WSL/Git Bash), not directly in native PowerShell.
- Native PowerShell is fully suitable for remote deployment management via `ssh` and `scp`.

---

### Troubleshooting (Windows client + VM)

| Symptom | Fix |
|---|---|
| `Windows Subsystem for Linux has no installed distributions` | Not required for VM workflow; use PowerShell + `ssh`/`scp` and run Bash scripts on VM |
| `Permission denied` on SSH | Verify SSH username/key/password and NSG/firewall rules for SSH port |
| `Connection closed by <ip> port 22/2222` | VM SSH service or network security rule issue; verify inbound rule and sshd status |
| `sudo: a terminal is required` in automation | Use `echo 'PASSWORD' \| sudo -S <command>` or SSH interactively |

---

## 16. Post-Deployment Validation (Docker + Direct)

Use this section as a final go-live checklist regardless of deployment mode.

### A. Required checks

1. Login works from the browser using an admin account.
2. `GET /api/system/public` returns HTTP 200 and valid JSON.
3. File upload succeeds for a small test file.
4. Queue processing works (trigger any queued notification and confirm it is processed).
5. No repeating errors in app logs for 5 to 10 minutes after first login.

### B. HTTPS checks (when enabled)

```bash
# Certificate files exist (replace portal.myorg.com with your actual domain)
sudo ls -la /etc/letsencrypt/live/portal.myorg.com

# Nginx config is valid
sudo nginx -t

# Public HTTPS response (replace portal.myorg.com with your actual domain)
curl -I https://portal.myorg.com/

# Renewal dry-run
sudo certbot renew --dry-run
```

### C. Security cleanup before go-live

1. Change all default seeded passwords.
2. Set real SMTP credentials and send a test email.
3. Confirm `APP_DEBUG=false`.
4. Restrict SSH and database exposure by IP/network policy.
5. Ensure backups are scheduled and tested once.
