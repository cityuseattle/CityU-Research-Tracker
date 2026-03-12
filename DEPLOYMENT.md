# Deployment Guide — Research Review Portal

> **Goal:** Get the plugin running on a WordPress instance for local testing.  
> Three options are covered — choose whichever fits your machine.

---

## Option A — Local WP with Docker (recommended, no PHP install needed)

### Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running

### Steps

**1. Create a `docker-compose.yml` in any working folder** (outside this repo):

```yaml
version: '3.8'

services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wp
      MYSQL_PASSWORD: wp
    volumes:
      - db_data:/var/lib/mysql

  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wp
      WORDPRESS_DB_PASSWORD: wp
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - wp_data:/var/www/html
      - ./plugins/research-review-portal:/var/www/html/wp-content/plugins/research-review-portal

volumes:
  db_data:
  wp_data:
```

**2. Copy the plugin into the volume-mapped folder:**

```powershell
# From your working folder (where docker-compose.yml lives)
New-Item -ItemType Directory -Force -Path plugins\research-review-portal
Copy-Item -Recurse "D:\Development\CityU-Research-Tracker\*" plugins\research-review-portal\
```

**3. Start the containers:**

```powershell
docker-compose up -d
```

**4. Run WordPress setup:**

Open http://localhost:8080 in your browser and complete the 5-minute WordPress install wizard (choose any site title, create an admin user).

**5. Activate the plugin:**

- Log in to http://localhost:8080/wp-admin
- Go to **Plugins → Installed Plugins**
- Find **Research Review Portal** and click **Activate**

**6. Fix file permissions** (so the plugin can write JSON data):

```powershell
docker-compose exec wordpress chmod -R 777 /var/www/html/wp-content/plugins/research-review-portal/data
```

**7. Create the portal page:**

- Go to **Pages → Add New**
- Title: `Research Portal`
- Body: `[research_review_portal]` (switch to Text / Code editor mode)
- Click **Publish**

**8. Open the portal:**

- http://localhost:8080/research-portal/?portal=1

**To stop:**
```powershell
docker-compose down
```

---

## Option B — Local WP with LocalWP (GUI, easiest)

### Prerequisites
- Download and install [LocalWP](https://localwp.com/) (free)

### Steps

1. Open **LocalWP** → click **+ Create a new site**
2. Site name: `CityU Research Portal` → continue through defaults (PHP 8.1, MySQL 8.0, Nginx/Apache)
3. Note the site path shown in LocalWP (e.g. `C:\Users\YourName\Local Sites\cityu-research-portal`)
4. **Copy the plugin** into the plugins folder:

```powershell
$localSite = "C:\Users\YourName\Local Sites\cityu-research-portal\app\public\wp-content\plugins"
Copy-Item -Recurse "D:\Development\CityU-Research-Tracker" "$localSite\research-review-portal"
```

5. Click **Start site** in LocalWP
6. Click **WP Admin** → log in → **Plugins → Activate** Research Review Portal
7. Go to **Pages → Add New**, add title `Research Portal`, body `[research_review_portal]`, Publish
8. Click **Open site** or browse to the local URL shown in LocalWP, then append `/research-portal/?portal=1`

---

## Option C — GitHub Codespace (no local setup)

### Prerequisites
- GitHub account with access to this repository
- The repo pushed to GitHub

### Steps

1. On GitHub, open the repository → click **Code → Codespaces → Create codespace on main**

2. Once the Codespace terminal opens, install Docker-in-Docker or use the pre-installed PHP and Apache:

```bash
# Install WordPress CLI
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Install Apache + PHP + MySQL
sudo apt-get update -qq
sudo apt-get install -y apache2 php php-mysql php-curl php-mbstring php-xml libapache2-mod-php mysql-server

# Start services
sudo service mysql start
sudo service apache2 start

# Create WordPress database
sudo mysql -e "CREATE DATABASE wordpress; CREATE USER 'wp'@'localhost' IDENTIFIED BY 'wp'; GRANT ALL ON wordpress.* TO 'wp'@'localhost';"
```

3. **Download and install WordPress:**

```bash
cd /var/www/html
sudo wp core download --allow-root
sudo wp config create --dbname=wordpress --dbuser=wp --dbpass=wp --allow-root
sudo wp core install --url=localhost --title="CityU Research Portal" \
  --admin_user=admin --admin_password=admin123 --admin_email=admin@test.com --allow-root
```

4. **Link the plugin:**

```bash
sudo ln -s /workspaces/<repo-name> /var/www/html/wp-content/plugins/research-review-portal
sudo chmod -R 777 /var/www/html/wp-content/plugins/research-review-portal/data
sudo wp plugin activate research-review-portal --allow-root
```

5. **Create the portal page:**

```bash
sudo wp post create --post_type=page --post_title="Research Portal" \
  --post_content='[research_review_portal]' --post_status=publish --allow-root
```

6. In the Codespace **Ports** tab, forward port **80** and open the forwarded URL → append `/research-portal/?portal=1`

---

## Post-install: Create test users

Create one user per role to test the full workflow:

**In WP Admin → Users → Add New:**

| Display name    | Email                        | Role              |
|-----------------|------------------------------|-------------------|
| Test Student    | student@test.com             | RRP Student       |
| Test Reviewer   | reviewer@test.com            | RRP Reviewer      |
| Test Coordinator| coordinator@test.com         | RRP Coordinator   |
| Test Admin      | rrpadmin@test.com            | RRP Admin         |

Or via WP-CLI (adjust `--url` as needed):

```bash
wp user create student student@test.com --role=rrp_student --user_pass=test123 --allow-root
wp user create reviewer reviewer@test.com --role=rrp_reviewer --user_pass=test123 --allow-root
wp user create coordinator coordinator@test.com --role=rrp_coordinator --user_pass=test123 --allow-root
wp user create rrpadmin rrpadmin@test.com --role=rrp_admin --user_pass=test123 --allow-root
```

---

## Quick smoke test

| Step | URL / action | Expected result |
|------|-------------|-----------------|
| 1 | `/wp-json/research-portal/v1/health` | `{"ok":true}` |
| 2 | Log in as `student@test.com`, open portal | Submission form visible |
| 3 | Submit a conference abstract | Confirmation with reference ID |
| 4 | Log in as `reviewer@test.com`, open **Reviewer Dashboard** | Assigned submission listed |
| 5 | Click **Review** on assignment | Detail view with "Record Your Decision" form |
| 6 | Log in as `rrpadmin@test.com`, open **Dashboard** | All submissions listed with Details/Timeline buttons |

---

## Configuration

The plugin reads from `data/config.json` (inside the plugin folder). Key settings:

| Key | Purpose | Default |
|-----|---------|---------|
| `stageDueDays.<type>` | Days allowed per stage before overdue | 7 |
| `reviewerPools.<type>.reviewerIds` | Pool of reviewer IDs for auto-assignment | see file |
| `reviewerPools.<type>.assignmentMode` | `random` or `round_robin` | `random` |
| `stageRequirements.<type>.<stage>.requiredCount` | Reviewers required per stage | varies |

Edit via the portal's **Config** API (`PUT /wp-json/research-portal/v1/config`) or directly in the JSON file when the site is stopped.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| REST API returns 401 on all endpoints | Ensure WordPress permalinks are set to **Post name** (Settings → Permalinks → Save) |
| JSON write errors / submissions not saving | `chmod -R 777 data/` inside the plugin folder |
| "Portal API not configured" on page | The shortcode page was published but plugin is not activated — activate it in WP Admin → Plugins |
| Roles not appearing in Add User dropdown | Deactivate and re-activate the plugin once (role registration runs on activation hook) |
| File uploads silently fail | Check `data/uploads/` exists and is writable; max upload size in `php.ini` must be ≥ 2 MB |
