# Operations Manual — CityU Research Review Portal

> **Version:** 1.0.0  
> **Author:** Kiran Kumar Vejendla — [vejendlakirankumar@cityu.edu] and Jemell Garris - [Garris.jemell@cityu.edu] (mailto:vejendlakirankumar@cityu.edu)  
> **Institution:** City University of Seattle · School of Technology and Computing (STC)  
> **Last updated:** March 2026

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Environment Reference](#environment-reference)
4. [Deployment Guide](#deployment-guide)
5. [First-Run Configuration](#first-run-configuration)
6. [Routine Maintenance](#routine-maintenance)
7. [Backup & Recovery](#backup--recovery)
8. [User & Role Management](#user--role-management)
9. [REST API Reference](#rest-api-reference)
10. [Configuration Reference](#configuration-reference)
11. [Data File Reference](#data-file-reference)
12. [Monitoring & Logging](#monitoring--logging)
13. [Troubleshooting](#troubleshooting)
14. [Enabling HTTPS (Let's Encrypt)](#enabling-https-lets-encrypt)
15. [Microsoft Entra SSO](#microsoft-entra-sso)
16. [Security Hardening](#security-hardening)
17. [Updating the Plugin](#updating-the-plugin)

---

## System Overview

The **CityU Research Review Portal** is a WordPress plugin that replaces the default WordPress front end with a custom single-page application. It provides:

- Multi-stage structured review workflows for five academic submission types
- Role-based dashboards (Student, Reviewer, Coordinator, Admin)
- Reviewer pool management and automatic assignment suggestions
- Conflict-of-interest tracking
- Full audit log on every submission
- Analytics reports (workflow throughput, reviewer performance, overdue tracking)
- JSON-file-based submission and reviewer state (no custom database tables)

### Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| CMS | WordPress | 6.7 |
| Language | PHP | 8.1 |
| Web server | Apache | 2.4 |
| Database (WP core) | MySQL | 8.0 |
| Front-end SPA | Vanilla JavaScript (IIFE) | — |
| Word document preview | Mammoth.js | 1.7.0 |
| Containerisation | Docker / Docker Compose | — |
| Infrastructure provisioning | Makefile + Bash scripts | — |

---

## Architecture

```
Browser
  │  HTTPS (port 80 / 443)
  ▼
Apache 2.4  ─── mod_rewrite ──► WordPress (wp-config, wp-includes, plugins)
                                       │
                                       ├─ Plugin: research-review-portal/
                                       │          ├─ research-review-portal.php  ← main plugin
                                       │          ├─ assets/portal.js            ← SPA
                                       │          ├─ assets/portal.css
                                       │          ├─ includes/
                                       │          │   ├─ class-portal-data.php   ← JSON R/W
                                       │          │   ├─ class-portal-rest.php   ← REST API
                                       │          │   ├─ class-user-management.php
                                       │          │   ├─ class-process-documentation.php
                                       │          │   └─ class-auth-provider.php
                                       │          └─ data/
                                       │              ├─ config.json             ← system config
                                       │              ├─ submissions.json        ← all submissions
                                       │              ├─ reviewers.json          ← reviewer pool
                                       │              └─ uploads/
                                       │                  └─ <submission-id>/   ← uploaded files
                                       │
                                       └─ WordPress DB (MySQL)
                                              └─ wp_users, wp_usermeta, wp_options
                                                 (WP core + plugin settings only)
```

### What is stored where

| Data | Storage | Location |
|------|---------|----------|
| Submissions, stages, decisions, comments | JSON file | `data/submissions.json` |
| Reviewer pool, expertise, assignments | JSON file | `data/reviewers.json` |
| Workflow configuration, stage templates | JSON file | `data/config.json` |
| Uploaded documents | File system | `data/uploads/<submission-id>/` |
| WordPress user accounts | MySQL | `wp_users` + `wp_usermeta` |
| Portal user meta (role, degree, dept.) | MySQL usermeta | `wp_usermeta` keys: `rrp_*` |
| WordPress settings (siteurl, home) | MySQL | `wp_options` |

---

## Environment Reference

### Production (Azure VM)

| Item | Value |
|------|-------|
| Hostname | `deployvm.cityu.edu` |
| OS | Ubuntu 22.04.5 LTS |
| PHP | 8.1.2 |
| MySQL | 8.0.45 |
| Apache | 2.4.52 |
| Plugin path | `/mnt/c/Development/CityU-Research-Tracker` |
| Apache htdocs | `/var/www/html` |
| WP admin | `admin` / `Admin1234!` |
| DB name | `wordpress` |
| DB user | `wp` |
| DB password | `wp_test_pass` |

### Development (Docker)

| Item | Value |
|------|-------|
| WP URL | `http://localhost:8080` (configurable via `.env`) |
| WP admin | `admin` / `Admin1234!` (configurable via `.env`) |
| MySQL | `mysql:8.0` Docker image |
| PHP | 8.1 (wordpress:6.7-php8.1-apache) |
| Upload limit | 64 MB (custom `rrp.ini`) |
| Memory limit | 256 MB |
| Max execution time | 60 s |

### PHP ini overrides (both environments)

| Setting | Value | Notes |
|---------|-------|-------|
| `upload_max_filesize` | 64M | Allows large dissertation PDFs |
| `post_max_size` | 64M | Must be ≥ upload_max_filesize |
| `memory_limit` | 256M | Mammoth.js Word parsing is memory-intensive |
| `max_execution_time` | 60 | Long-running export/analytics calls |

---

## Deployment Guide

Full step-by-step deployment instructions are in [DEPLOYMENT.md](DEPLOYMENT.md). This section provides the operational overview and quick-reference commands.

### Docker (recommended)

```bash
cp .env.example .env          # first time only — edit WP_URL + WP_PORT
make up                       # build image and start containers
make init                     # install WordPress + activate plugin (first time only)
```

Access the portal at the URL set in `.env` (default: `http://localhost:8080`).

**Daily operations:**

```bash
make up          # start
make down        # stop
make logs        # tail Apache/PHP error log
make shell       # bash into the WordPress container
make db-export   # dump MySQL → data/rrp-db-export.sql
make db-import   # restore from data/rrp-db-export.sql
make reset       # ⚠ destroy volumes — full wipe
```

### Bare-Metal Ubuntu / WSL

```bash
export WP_URL="http://your-server.example.com"   # optional; defaults to http://localhost
chmod +x scripts/install-wsl.sh
./scripts/install-wsl.sh
```

After installation:
```bash
sudo service apache2 start
sudo service mysql start
```

To change the public URL on an existing install:
```bash
chmod +x scripts/set-public-url.sh
./scripts/set-public-url.sh http://new-hostname.example.com
```

---

## First-Run Configuration

After deploying and running `make init` (Docker) or `install-wsl.sh` (bare-metal), perform the following steps:

### 1. Verify the health endpoint

```bash
curl http://localhost:8080/wp-json/research-portal/v1/health
# Expected: {"ok":true,"bootId":<number>}
```

### 2. Log in to WordPress Admin

Open `http://<your-url>/wp-admin` and log in with the admin credentials.

### 3. Create portal user accounts

Navigate to **WP Admin → Users → Add New** for each person who needs access, assigning the appropriate role (`rrp_student`, `rrp_reviewer`, `rrp_coordinator`, `rrp_admin`).

Or create accounts programmatically via WP-CLI:

```bash
# Docker: make shell, then run:
wp user create student student@institution.edu --role=rrp_student --user_pass=InitialPass123! --allow-root
wp user create reviewer1 reviewer1@institution.edu --role=rrp_reviewer --user_pass=InitialPass123! --allow-root
wp user create coordinator1 coord1@institution.edu --role=rrp_coordinator --user_pass=InitialPass123! --allow-root
wp user create admin1 admin1@institution.edu --role=rrp_admin --user_pass=InitialPass123! --allow-root
```

> Instruct all users to change their password immediately on first login.

### 4. Review and adjust `data/config.json`

Open `data/config.json` in a text editor or use the portal's **Admin → Configuration** panel. Key settings to check:

- `stageDueDays` — days per stage before a submission is marked overdue
- `reviewerPools` — assign appropriate reviewer user IDs by submission type
- `stageRequirements` — set how many reviewers are required per stage

### 5. Seed reviewer pool

In the portal admin panel, go to **Admin → Users**, find each reviewer, and complete their profile:
- Expertise tags (used for automatic assignment suggestions)
- Department
- Available submission types

### 6. Configure email notifications (production)

WordPress uses `wp_mail()` for notifications. On a production server configure an SMTP plugin or set up PHP's `sendmail` to ensure delivery. Recommended: install **WP Mail SMTP** and configure with your institution's SMTP server.

### 7. Configure Microsoft Entra SSO (optional)

If your institution uses Microsoft Entra ID (Azure AD) for identity, enable SSO so users log in with their university account instead of a WordPress password. See the full [Microsoft Entra SSO](#microsoft-entra-sso) section for detailed steps.

---

## Routine Maintenance

### Weekly

- [ ] Check `make logs` (Docker) or `/var/log/apache2/error.log` (bare-metal) for PHP errors
- [ ] Review the Overdue Analytics report and follow up with coordinators

### Monthly

- [ ] Run `make db-export` and commit `data/rrp-db-export.sql` to git
- [ ] Commit `data/submissions.json`, `data/reviewers.json`, `data/config.json` to git
- [ ] Review disk usage in `data/uploads/`; archive completed submission folders if needed

### As needed

- [ ] After any code change, run `make build` (Docker) or copy updated files to `/var/www/html/wp-content/plugins/research-review-portal/`
- [ ] After WordPress core updates, verify the health endpoint still returns `{"ok":true}`

---

## Backup & Recovery

### What to back up

| Component | Backup method | Frequency |
|-----------|--------------|-----------|
| `data/submissions.json` | git commit OR copy | Daily |
| `data/reviewers.json` | git commit OR copy | On change |
| `data/config.json` | git commit OR copy | On change |
| `data/uploads/` directory | rsync / zip | Weekly |
| MySQL database | `make db-export` / `wp db export` | Daily |

### Full backup procedure (Docker)

```bash
# 1. Export the database
make db-export
# writes to: data/rrp-db-export.sql

# 2. Commit everything to git
cd /path/to/CityU-Research-Tracker
git add data/
git commit -m "Backup: $(date +%Y-%m-%d)"
git push origin main
```

### Full backup procedure (bare-metal)

```bash
wp --path=/var/www/html --allow-root db export \
  /mnt/c/Development/CityU-Research-Tracker/data/rrp-db-export.sql

tar -czf /tmp/rrp-uploads-$(date +%Y%m%d).tar.gz \
  /mnt/c/Development/CityU-Research-Tracker/data/uploads/
```

### Recovery procedure

**Restore to a new Docker environment:**

```bash
git clone <repo-url> CityU-Research-Tracker
cd CityU-Research-Tracker
cp .env.example .env          # set WP_URL
make up
make init
make db-import                # restores data/rrp-db-export.sql
```

All JSON data files are in the repo — no additional import step is needed.

**Restore to bare-metal:**

```bash
# 1. Run the install script (installs WP + activates plugin)
./scripts/install-wsl.sh

# 2. Import the database dump
wp --path=/var/www/html --allow-root db import \
  /mnt/c/Development/CityU-Research-Tracker/data/rrp-db-export.sql

# 3. Update the site URL if the hostname has changed
./scripts/set-public-url.sh http://new-hostname.example.com
```

---

## User & Role Management

### Roles defined by the plugin

The plugin registers five custom roles on activation:

| Role | Capability slug | Intended for |
|------|----------------|--------------|
| `rrp_student` | `rrp_submit` | Submitters |
| `rrp_reviewer` | `rrp_review` | Reviewers |
| `rrp_coordinator` | `rrp_coordinate` | Stage coordinators |
| `rrp_admin` | `rrp_administrate` | Portal administrators |
| `administrator` | WordPress default | WP system admin |

### Re-registering roles

If roles disappear (e.g. after a fresh database import), deactivate and re-activate the plugin from **WP Admin → Plugins**. Role registration runs on `register_activation_hook`.

### Changing a user's role

```bash
# WP-CLI
wp user set-role <user-id-or-email> rrp_coordinator --allow-root
```

Or via WP Admin → Users → Edit User → Role dropdown.

### Bulk role assignment via WP-CLI

```bash
# Promote all users with @cityu.edu email to rrp_student
wp user list --format=csv --fields=ID,user_email | grep "@cityu.edu" | \
  awk -F',' '{print $1}' | \
  xargs -I{} wp user set-role {} rrp_student --allow-root
```

---

## REST API Reference

**Base URL:** `{site_url}/wp-json/research-portal/v1`

All authenticated endpoints require a valid WordPress nonce in the `X-WP-Nonce` header (provided automatically by the SPA via `window.RRP.nonce`).

### Authentication & Health

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/health` | None | Returns `{"ok":true,"bootId":<int>}` — use for monitoring |

### Submissions

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/submit` | Logged-in | Create a new submission (multipart/form-data) |
| GET | `/submissions` | Logged-in | List submissions visible to the current user |
| GET | `/submissions/public` | None | List approved submissions (public-facing) |
| GET | `/submissions/{id}` | Logged-in | Get single submission details |
| PUT | `/submissions/{id}` | Coordinator+ | Update submission metadata or status |
| GET | `/submissions/{id}/preview` | Logged-in | Stream the uploaded file for in-browser preview |
| POST | `/submissions/{id}/feedback` | Reviewer | Submit reviewer feedback and decision |
| GET | `/submissions/{id}/comments` | Logged-in | Get all comments on a submission |
| POST | `/submissions/{id}/comments` | Logged-in | Post a new comment |
| POST | `/submissions/{id}/attachments` | Reviewer | Upload a reviewer attachment |
| DELETE | `/submissions/{id}/attachments/{filename}` | Admin | Delete an attachment |
| GET | `/submissions/{id}/timeline` | Logged-in | Get the full stage timeline |
| GET | `/submissions/{id}/audit-log` | Coordinator+ | Get the detailed audit log |
| POST | `/submissions/{id}/skip-stage` | Coordinator | Skip the active review stage |
| GET/PUT | `/submissions/{id}/deadlines` | Coordinator | Get or set per-stage deadlines |

### Reviews & Assignments

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/reviews` | Reviewer+ | List reviews assigned to the current user |
| GET | `/reviewers` | Coordinator+ | List all reviewers in the pool |
| GET | `/assignment-summary` | Coordinator+ | Compact per-submission stage + reviewer summary |
| POST | `/reviews/rate` | Coordinator+ | Submit a reviewer quality rating |
| GET | `/conflicts` | Coordinator+ | List declared conflicts of interest |
| POST | `/conflicts` | Reviewer | Declare a conflict of interest |

### Analytics

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/analytics/workflow` | Coordinator+ | Stage completion rates and durations |
| GET | `/analytics/performance` | Coordinator+ | Submission volume by type and date |
| GET | `/analytics/reviewer` | Coordinator+ | Per-reviewer workload and turnaround |
| GET | `/analytics/workload` | Coordinator+ | Active workload distribution |
| GET | `/analytics/daily` | Coordinator+ | Daily submission and decision counts |
| GET | `/analytics/overdue` | Coordinator+ | All overdue submissions |
| GET | `/reports/export` | Admin | Export report data as CSV |

### Dashboard & Notifications

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/dashboard` | Logged-in | Combined data for the current user's dashboard |
| GET | `/notifications` | Logged-in | Unread notifications for the current user |

### Configuration

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/config` | Admin | Get full system configuration |
| PUT | `/config` | Admin | Update system configuration |
| GET | `/config/review-templates` | Admin | Get review question templates |
| POST | `/config/review-templates` | Admin | Create or update a review template |
| GET | `/config/suggest-reviewers` | Coordinator+ | Suggest reviewers for a submission |
| POST | `/config/apply-pool-to-submissions` | Admin | Bulk-apply reviewer pool changes |

### Submission Types & Workflows

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/submission-types` | Logged-in | List all submission types |
| POST | `/submission-types` | Admin | Create a new submission type |
| GET | `/submission-types/{id}` | Logged-in | Get a single submission type |
| PUT | `/submission-types/{id}` | Admin | Update a submission type |
| GET | `/workflow-stages` | Logged-in | List workflow stage definitions |
| PUT | `/workflow-stages/{id}` | Admin | Update a workflow stage |
| DELETE | `/workflow-stages/{id}` | Admin | Delete a workflow stage |

### Portal Users

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/portal-users/me` | Logged-in | Get the current user's profile |
| PUT | `/portal-users/me` | Logged-in | Update the current user's profile |
| GET | `/portal-users` | Admin | List all portal users |
| POST | `/portal-users` | Admin | Create a new portal user |
| GET | `/portal-users/{id}` | Admin | Get a portal user by WP user ID |
| PUT | `/portal-users/{id}` | Admin | Update a portal user |
| DELETE | `/portal-users/{id}` | Admin | Delete a portal user |
| GET | `/portal-users/json/{jsonId}` | Admin | Look up user by JSON-file reviewer ID |
| GET | `/portal-users/json-student/{email}` | Coordinator+ | Look up student by email |

---

## Configuration Reference

`data/config.json` is the primary configuration file. It is read at runtime and can be updated via the **Admin → Configuration** panel in the portal or directly on disk (stop the server before editing).

### Top-level keys

```jsonc
{
  "stageDueDays": {
    "ARS": 7,          //days allowed per stage for Research Papers / Dissertations
    "PUB": 10,         // Journal Publications
    "GRN": 14,         // Grant Proposals
    "CAP": 7           // Capstone Projects
  },

  "reviewerPools": {
    "ARS": {
      "reviewerIds": [3, 5, 8],          // WP user IDs
      "assignmentMode": "round_robin"    // "random" | "round_robin"
    },
    "PUB": { "reviewerIds": [], "assignmentMode": "random" },
    "GRN": { "reviewerIds": [], "assignmentMode": "random" },
    "CAP": { "reviewerIds": [], "assignmentMode": "random" }
  },

  "stageRequirements": {
    "ARS": {
      "Committee Review": { "requiredCount": 3 },
      "default": { "requiredCount": 1 }
    }
    // ...
  },

  "notificationEmail": "research-portal@cityu.edu",  // reply-to address for emails
  "maxFileUploadMB": 64                               // enforced by PHP ini; informational
}
```

### `reviewers.json` structure

Each entry in the array represents one reviewer in the pool:

```jsonc
[
  {
    "id": "RVW-001",             // Internal reviewer JSON ID
    "userId": 5,                  // WordPress user ID
    "email": "reviewer@test.com",
    "name": "Dr. Jane Smith",
    "department": "Computer Science",
    "expertise": ["machine learning", "computer vision"],
    "submissionTypes": ["ARS", "PUB"],
    "activeAssignments": 2,
    "completedReviews": 14
  }
]
```

### `submissions.json` structure (abridged)

```jsonc
[
  {
    "id": "ARS-2026-001",
    "type": "ARS",
    "title": "...",
    "abstract": "...",
    "keywords": ["..."],
    "submitterId": 12,
    "submitterEmail": "student@test.com",
    "status": "Under Review",
    "createdAt": "2026-01-15T09:00:00Z",
    "updatedAt": "2026-02-03T14:22:00Z",
    "stages": [
      {
        "stageName": "Chair Review",
        "reviewers": ["reviewer@test.com"],
        "decisions": { "reviewer@test.com": "Approved" },
        "skipped": false,
        "completedAt": "2026-01-20T11:00:00Z"
      }
    ],
    "auditLog": [
      {
        "timestamp": "2026-01-15T09:00:00Z",
        "actor": "student@test.com",
        "action": "Submitted",
        "detail": "Initial submission"
      }
    ],
    "documents": [
      { "filename": "dissertation-draft.pdf", "uploadedAt": "..." }
    ]
  }
]
```

---

## Data File Reference

### Directory layout

```
data/
├── config.json           ← system configuration
├── submissions.json      ← all submissions + stages + decisions + audit log
├── reviewers.json        ← reviewer pool registry
└── uploads/
    ├── ARS-2026-001/
    │   ├── main-document.pdf
    │   └── reviewer-notes.pdf
    ├── ARS-2026-002/
    │   └── capstone-final.docx
    └── ...
```

### File permissions

The web server user (`www-data` on Ubuntu/Apache) must have read+write access to the entire `data/` directory:

```bash
# Bare-metal
sudo chown -R www-data:www-data /var/www/html/wp-content/plugins/research-review-portal/data
sudo chmod -R 775 /var/www/html/wp-content/plugins/research-review-portal/data

# Docker (run inside container)
chown -R www-data:www-data /var/www/html/wp-content/plugins/research-review-portal/data
```

---

## Monitoring & Logging

### Health check endpoint

Use `/wp-json/research-portal/v1/health` for automated monitoring:

```bash
# Returns HTTP 200 + JSON if healthy; HTTP 5xx if WordPress is broken
curl -sf http://localhost:8080/wp-json/research-portal/v1/health
```

Add this to your uptime monitoring tool (UptimeRobot, Pingdom, Azure Monitor, etc.) with a 5-minute interval.

### PHP / Apache error log

| Environment | Log location |
|------------|-------------|
| Docker | `make logs` (streams container stdout/stderr) |
| Bare-metal | `/var/log/apache2/error.log` |
| Bare-metal (PHP) | `/var/log/apache2/php_errors.log` (if configured) |

Enable WordPress debug logging in `wp-config.php` for development only:

```php
define( 'WP_DEBUG',         true  );
define( 'WP_DEBUG_LOG',     true  );   // writes to wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );   // do not show errors to end users
```

> **Never enable `WP_DEBUG_DISPLAY` on a production server.**

### Audit log

Every submission has a built-in audit log accessible via:
- Portal UI: click **📋 Log** on any submission card
- REST API: `GET /wp-json/research-portal/v1/submissions/{id}/audit-log`

The audit log records: timestamp, actor email, action type, and detail string for every system event.

---

## Troubleshooting

### Portal returns blank page or "Portal API not configured"

1. Verify the plugin is active: `wp plugin list --allow-root`
2. Check WordPress permalinks are set to **Post name** (`/%postname%/`):
   ```bash
   wp rewrite structure '/%postname%/' --allow-root
   wp rewrite flush --allow-root
   ```
3. Check Apache `mod_rewrite` is enabled:
   ```bash
   sudo a2enmod rewrite
   sudo service apache2 restart
   ```

### REST API returns 401 Unauthorized

Usually a nonce or authentication problem:
1. Hard-refresh the browser (`Ctrl+Shift+R`)
2. Log out and log back in to get a fresh nonce
3. If still failing, check that `WP_SITEURL` / `WP_HOME` in `wp-config.php` match the URL you're using

### Chrome redirects to `localhost`

The plugin's `option_siteurl`/`option_home` filters should handle this automatically. If the redirect persists:

1. Verify the plugin is active (the filters fire at plugin load time)
2. Check `wp-config.php` contains the `WP_HOME` / `WP_SITEURL` constants set to the correct public URL:
   ```bash
   grep -i 'WP_HOME\|WP_SITEURL' /var/www/html/wp-config.php
   ```
3. Update if wrong:
   ```bash
   ./scripts/set-public-url.sh http://correct-hostname.example.com
   ```

### File uploads fail silently

1. Check `data/uploads/` exists and is writable by `www-data`
2. Confirm PHP `upload_max_filesize` and `post_max_size` are ≥ 64M:
   ```bash
   php -r "echo ini_get('upload_max_filesize');"
   ```
3. In Docker, the custom `rrp.ini` in the image sets these values — rebuild if changed: `make build`

### Submissions not saving (JSON write errors)

```bash
# Check permissions
ls -la /var/www/html/wp-content/plugins/research-review-portal/data/
# Should show write permission for www-data

# Fix
sudo chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/research-review-portal/data
```

### Database connection refused (Docker)

The WordPress container starts before MySQL is fully initialised. The `docker-compose.yml` health check on the `db` service prevents this in most cases. If it happens:

```bash
make down
make up
# Wait 30 seconds then:
make init
```

### Roles missing from WP Admin "Add User" dropdown

The plugin registers roles on the WordPress `register_activation_hook`. If roles are absent:

```bash
wp plugin deactivate research-review-portal --allow-root
wp plugin activate  research-review-portal --allow-root
```

### MySQL container fails to start

```bash
# Check for port conflict
netstat -an | grep 3306

# Reset Docker volumes (WARNING: destroys all data — ensure backup exists)
make reset
make up
make init
make db-import   # if a backup exists
```

---

## Enabling HTTPS (Let's Encrypt)

Microsoft Entra ID **requires** an `https://` redirect URI for any non-localhost application. Run this section before configuring SSO.

### Step 1 — Open port 443 in the Azure NSG

1. In the [Azure Portal](https://portal.azure.com), navigate to your VM → **Networking** → **Network security group**.
2. Click **Add inbound security rule**:
   - Source: `Any`
   - Source port ranges: `*`
   - Destination: `Any`
   - Destination port ranges: `443`
   - Protocol: `TCP`
   - Action: `Allow`
   - Priority: `340` (or any unused priority below `Deny All`)
   - Name: `HTTPS`
3. Click **Add** and wait ~30 seconds for the rule to apply.

### Step 2 — Run the HTTPS setup script

SSH into the VM and run the provided script:

```bash
ssh itadmin@rrp.cityu.edu
cd /mnt/c/Development/CityU-Research-Tracker
sudo bash scripts/enable-https.sh
```

The script will:
- Install **Certbot** and the Apache plugin
- Obtain a free **Let's Encrypt** certificate for `rrp.cityu.edu`
- Configure Apache to serve HTTPS on port 443
- Add an automatic **HTTP → HTTPS redirect** (301)
- Update WordPress `siteurl` and `home` to `https://`
- Install an **auto-renewal cron** (certificates renew every 60 days; valid for 90)

> The script is idempotent — safe to re-run if something fails partway through.

### Step 3 — Verify HTTPS is working

```bash
# HTTP should redirect to HTTPS
curl -I http://rrp.cityu.edu
# Expected: HTTP/1.1 301 Moved Permanently  + Location: https://...

# HTTPS should return 200
curl -I https://rrp.cityu.edu
# Expected: HTTP/1.1 200 OK
```

Open `https://rrp.cityu.edu` in a browser — you should see a padlock (🔒) and no security warning.

### Step 4 — Update the Redirect URI everywhere

After enabling HTTPS, update the redirect URI in **both** places:

| Where | Old value | New value |
|-------|-----------|----------|
| Portal Settings → SSO → Redirect URI | `http://rrp.../auth/callback` | `https://rrp.cityu.edu/wp-json/research-portal/v1/auth/callback` |
| Azure App Registration → Authentication → Redirect URIs | same `http://` URI | same `https://` URI |

### Certificate renewal

Let's Encrypt certificates are valid for 90 days. Certbot installs a cron job at `/etc/cron.d/certbot` that runs twice daily and automatically renews any certificate expiring within 30 days, then reloads Apache.

To manually test renewal:
```bash
sudo certbot renew --dry-run
```

To check certificate expiry:
```bash
sudo certbot certificates
```

---

## Microsoft Entra SSO

The portal supports Microsoft Entra ID (Azure AD) as a Single Sign-On provider. When enabled, users click **Login with Microsoft** and are authenticated by Entra — no separate WordPress password is required.

> **Authorization is still local.** Entra only proves *who* the user is. Portal roles (Student / Reviewer / Coordinator / Admin) are assigned by a portal administrator after first login. A brand-new Entra user has no portal access until a role is assigned.

---

### Prerequisites

- An **Azure subscription** with permission to create or manage App Registrations in Entra ID.
- **HTTPS must be enabled** on the portal server. Entra ID enforces `https://` redirect URIs for any non-localhost application. See [Enabling HTTPS (Let's Encrypt)](#enabling-https-lets-encrypt) above.
- You have the portal's public HTTPS URL: `https://rrp.cityu.edu`.

---

### Step 1 — Configure the App Registration in Azure Portal

1. Sign in to [portal.azure.com](https://portal.azure.com) and open **Microsoft Entra ID**.

2. Go to **App registrations** → select your existing enterprise application (or create a new one).

3. On the **Overview** page, note down:
   - **Application (client) ID** — looks like `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
   - **Directory (tenant) ID** — same format

4. Go to **Authentication** → **Add a platform** → choose **Web**.

5. Set the **Redirect URI** to:
   ```
   https://rrp.cityu.edu/wp-json/research-portal/v1/auth/callback
   ```
   *(Replace the hostname with your actual domain if different. Must be `https://` — Entra does not accept plain `http://` for non-localhost URIs.)*

6. Under **Implicit grant and hybrid flows**, leave both checkboxes **unchecked** (the portal uses the Authorization Code flow).

7. Click **Save**.

8. Go to **Certificates & secrets** → **Client secrets** → **New client secret**.
   - Description: `Research Review Portal`
   - Expiry: choose an appropriate expiry (12 or 24 months)
   - Click **Add**, then **immediately copy the secret Value** — it is only shown once.

9. Go to **API permissions** → confirm the following **delegated** permissions are present (they are defaults for any App Registration):
   - `openid`
   - `profile`
   - `email`

   If any are missing, click **Add a permission → Microsoft Graph → Delegated → add them**. Then click **Grant admin consent**.

---

### Step 2 — Configure SSO in the Portal Settings UI

This is the **recommended method** — credentials are stored encrypted in the WordPress database using AES-256-GCM.

1. Log in to the portal as a **Coordinator** or **Admin**.

2. In the left sidebar, click **Settings → Portal Settings**.

3. Scroll to the **Single Sign-On (SSO)** fieldset:

   | Field | Value |
   |-------|-------|
   | Enable SSO | ✅ checked |
   | SSO Provider | Microsoft Entra ID (Azure AD) |
   | Tenant ID | paste your Directory (tenant) ID |
   | Client ID | paste your Application (client) ID |
   | Client Secret | paste the secret value you copied in Step 1.8 |
   | Redirect URI | `https://rrp.cityu.edu/wp-json/research-portal/v1/auth/callback` |
   | Auto-provision new users | ✅ recommended (creates WP account on first login; no portal role until admin assigns one) |

   > **Note:** If the Redirect URI field shows `http://` (before HTTPS was enabled), update it to `https://` here and in the Azure App Registration.

4. Click **💾 Save Settings**.

5. The client secret is encrypted with AES-256-GCM before being written to the database. The Settings page will show `[encrypted]` in the secret field on subsequent visits — this is expected.

---

### Step 3 — (Alternative) Configure via `wp-config.php` constants

If you prefer to keep all secrets out of the database entirely, add these constants to `wp-config.php` on the VM **before** the `/* That's all, stop editing! */` line.

```bash
ssh itadmin@rrp.cityu.edu
sudo nano /var/www/html/wp-config.php
```

Add:

```php
// ── Research Review Portal — Entra SSO ──────────────────────────────────
define( 'RRP_AUTH_PROVIDER',      'entra' );
define( 'RRP_ENTRA_TENANT_ID',    'YOUR-TENANT-ID-HERE' );
define( 'RRP_ENTRA_CLIENT_ID',    'YOUR-CLIENT-ID-HERE' );
define( 'RRP_ENTRA_CLIENT_SECRET','YOUR-CLIENT-SECRET-HERE' );
define( 'RRP_ENTRA_REDIRECT_URI', 'https://rrp.cityu.edu/wp-json/research-portal/v1/auth/callback' );
```

> Constants take **priority over** the database settings. If both are set, `wp-config.php` wins.

> ⚠ **Never commit `wp-config.php` to git.** It is already in `.gitignore`.

---

### Step 4 — Test the SSO login flow

1. Open a **private/incognito browser window**.

2. Navigate to `https://rrp.cityu.edu` (the portal home page) **or** go directly to `https://rrp.cityu.edu/wp-login.php`.

3. Both login paths now redirect automatically to `login.microsoftonline.com` when Entra SSO is enabled.

4. Sign in with a university Microsoft account.

5. On first login, a new WordPress account is created automatically (if Auto-provision is on). The portal will show a message that no role is assigned yet.

6. As an admin, go to **Settings → Portal Settings** or **WP Admin → Users**, find the new user, and assign the appropriate portal role (`rrp_student`, `rrp_reviewer`, etc.).

7. The user can now log in again and access the portal with their assigned role.

---

### Step 5 — Test the logout flow

Logging out calls the Entra single-logout endpoint, which signs the user out of Entra SSO as well. Verify by:
1. Clicking **Logout** in the portal top bar.
2. Confirming the browser lands back on the portal home page.
3. Clicking **Login** again — the user should be prompted by Microsoft (not silently re-authenticated).

---

### Rotating the Client Secret

Entra client secrets expire. To rotate:

1. In [portal.azure.com](https://portal.azure.com), go to your App Registration → **Certificates & secrets** → add a **New client secret**.
2. Copy the new secret value.
3. In the portal **Settings → Portal Settings**, paste the new secret and click **Save**. The old secret is overwritten.
4. Back in Azure Portal, delete the old (expired) secret.
5. Test a login to confirm the new secret works before the old one expires.

---

### Emergency Local Login Bypass

The login page always shows **both** options: a **Sign in with Microsoft** button (visible only when Entra SSO is enabled) and the standard WordPress username/password form below it. There is no auto-redirect, so local login is always accessible at:

```
https://rrp.cityu.edu/wp-login.php
```

Simply scroll past the Microsoft button and use the username/password form. If you need to disable SSO entirely from the command line:

```bash
wp --path=/var/www/html --allow-root eval-file /tmp/disable_sso.php
# or directly:
wp --path=/var/www/html --allow-root option patch update rrp_portal_settings sso_enabled false --format=json
```

---

### Troubleshooting SSO

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Redirected to WP login instead of Microsoft | SSO not enabled or tenant/client ID missing | Check Portal Settings → SSO fields are filled and Enable SSO is checked |
| `wp-login.php` shows password form | SSO is disabled or `local_login=1` bypass is in use | Enable SSO in Portal Settings; remove `?local_login=1` from URL |
| `AADSTS50011 — redirect URI mismatch` | Redirect URI in Entra doesn't exactly match the portal's URI | Copy the URI character-for-character from Portal Settings into Entra App Registration → Authentication |
| `AADSTS700016 — application not found` | Wrong tenant ID | Verify Directory (tenant) ID in Portal Settings matches the App Registration |
| `AADSTS7000215 — invalid client secret` | Secret expired or copied incorrectly | Rotate the secret (see above) |
| `403 Invalid authentication state` | Browser cleared cookies mid-flow | Retry from a clean browser tab |
| User logs in but sees "No portal role" | Auto-provision created account but no role assigned | Admin assigns role via Users panel or WP Admin → Users |
| Logout doesn't sign out of Microsoft | Entra single-logout not configured | Verify `post_logout_redirect_uri` is an allowed reply URL in Entra App Registration → Authentication |

---

### Disabling SSO

To revert to WordPress-native login:

- **UI method:** Portal Settings → uncheck **Enable SSO** → Save.
- **wp-config method:** Change `RRP_AUTH_PROVIDER` from `'entra'` to `'wordpress'` (or remove the constant entirely).

Existing user accounts are preserved — users will just log in with their WordPress password instead.

---

## Security Hardening

### Required for any internet-facing deployment

1. **Change default credentials** immediately:
   - WP admin password: WP Admin → Users → Administrator → Set Password
   - DB root password: update `DB_ROOT_PASSWORD` in `.env` then `make reset` (or `ALTER USER` in MySQL directly)

2. **Set strong `AUTH_KEY` / `SECURE_AUTH_KEY` salts** in `wp-config.php`:
   - Generate new salts at `https://api.wordpress.org/secret-key/1.1/salt/`
   - Paste into `wp-config.php` (replaces the placeholder keys)

3. **Restrict WP Admin access** to internal IP ranges using Apache or an NSG rule:
   ```apache
   <Location /wp-admin>
     Require ip 10.0.0.0/8 192.168.0.0/16
   </Location>
   ```

4. **Enable HTTPS** with Let's Encrypt — see [Enabling HTTPS (Let's Encrypt)](#enabling-https-lets-encrypt) in this manual. Run `sudo bash scripts/enable-https.sh` on the VM.

5. **File permission hardening** — the `data/` directory should not be web-accessible directly. Apache serves PHP which reads the files; direct URL access to `.json` files should return 403:
   ```apache
   <Files "*.json">
     Require all denied
   </Files>
   ```

6. **Disable XML-RPC** (not needed by the portal):
   ```php
   // In wp-config.php or a must-use plugin:
   add_filter( 'xmlrpc_enabled', '__return_false' );
   ```

7. **Review Azure NSG rules** — only port 80 (and 443 if HTTPS is configured) should be open from the internet. Port 22 (SSH) should be restricted to known admin IPs.

### OWASP considerations in the plugin code

| Concern | Mitigation |
|---------|-----------|
| SQL injection | No custom SQL queries; all DB interaction via WordPress abstraction (WP_Query, wp_usermeta) |
| XSS | All output passed through `esc_html()`, `esc_attr()`, `esc_url()`; JS data via `wp_json_encode()` |
| CSRF | All state-changing REST endpoints require a valid `X-WP-Nonce` header |
| Path traversal | Upload filenames sanitised with `sanitize_file_name()`; stored under submission-ID-scoped subdirectory |
| Broken access control | All REST endpoints enforce `is_user_logged_in()` + role capability checks via WordPress permission callbacks |
| SSRF | No outbound HTTP requests made by the plugin |

---

## Updating the Plugin

### Update via file copy (bare-metal)

```bash
# 1. Pull latest code on your local machine
git pull origin main

# 2. Copy changed files to the VM
# (Using Send-FileSSH or scp — see DEPLOYMENT.md)
scp -r . itadmin@<VM-HOSTNAME>:/mnt/c/Development/CityU-Research-Tracker/

# 3. On the VM: no restart is needed unless PHP files changed
# WordPress loads PHP on each request; JS/CSS have cache-busted query strings
```

### Update via Docker rebuild

```bash
git pull origin main    # get latest code
make down
make build              # rebuild image (picks up Dockerfile / php.ini changes)
make up
# No wp-cli init required — data volumes are preserved
```

### Checking for WordPress core updates

```bash
wp core check-update --allow-root
wp core update --allow-root   # if an update is available
```

> Test on Docker/dev environment before updating production.

---

*Developed by Kiran Kumar Vejendla — [vejendlakirankumar@cityu.edu](mailto:vejendlakirankumar@cityu.edu)*  
*City University of Seattle · School of Technology and Computing (STC)*
