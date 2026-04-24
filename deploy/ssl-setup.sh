#!/bin/bash
# =============================================================================
# deploy/ssl-setup.sh
# Obtain a Let's Encrypt TLS certificate for the RRP v2 portal and configure
# Nginx to use it.
#
# Usage (run as root or with sudo):
#   sudo bash ssl-setup.sh <domain> [email]
#
# Examples:
#   sudo bash ssl-setup.sh portal.cityu.edu admin@cityu.edu
#
# Requirements:
#   - Nginx installed and running (the HTTP vhost must already be in place)
#   - Port 80 open in the firewall so Let's Encrypt can reach the ACME challenge
#   - The domain DNS A-record must point to this server's public IP
# =============================================================================
set -euo pipefail

DOMAIN="${1:-}"
EMAIL="${2:-webmaster@${DOMAIN}}"
PROXY_PORT="${3:-8080}"  # port Docker container is bound to on the host
WEBROOT="/var/www/letsencrypt"
VHOST_SOURCE="$(dirname "$0")/nginx-vhost.conf"
VHOST_DEST="/etc/nginx/sites-available/rrp-v2"
VHOST_LINK="/etc/nginx/sites-enabled/rrp-v2"

# ── Pre-flight ────────────────────────────────────────────────────────────────
if [ -z "$DOMAIN" ]; then
    echo "Usage: $0 <domain> [email]"
    exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: This script must be run as root (or with sudo)."
    exit 1
fi

# ── Pre-flight: Docker must NOT be bound to port 80 ──────────────────────────
# certbot's ACME challenge needs host nginx on port 80. If the Docker container
# is still bound to port 80 this script cannot install host nginx on that port.
# Fix: set HOST_PORT=8080 in .env and run 'docker compose up -d' first.
if ss -tlnp 2>/dev/null | grep -q ':80 ' || netstat -tlnp 2>/dev/null | grep -q ':80 '; then
    OWNER=$(ss -tlnp 2>/dev/null | grep ':80 ' | grep -o 'users:.*' | head -1 || true)
    if echo "$OWNER" | grep -qi docker; then
        echo "ERROR: Docker container is already bound to port 80."
        echo "       Host nginx needs port 80 for the ACME challenge."
        echo ""
        echo "  Fix: edit .env → set HOST_PORT=8080"
        echo "       then run: docker compose up -d"
        echo "       then re-run this script."
        exit 1
    fi
fi

echo "==> Domain   : $DOMAIN"
echo "==> Email    : $EMAIL"
echo "==> Webroot  : $WEBROOT"

# ── Install dependencies ──────────────────────────────────────────────────────
echo ""
echo "==> Installing certbot and python3-certbot-nginx …"
apt-get update -q
apt-get install -y -q certbot python3-certbot-nginx nginx

# ── Create webroot for ACME challenge ────────────────────────────────────────
mkdir -p "$WEBROOT"
chown www-data:www-data "$WEBROOT"

# ── Deploy the Nginx vhost (HTTP-only first so certbot can do the challenge) ──
if [ ! -f "$VHOST_SOURCE" ]; then
    echo "ERROR: nginx-vhost.conf not found at $VHOST_SOURCE"
    echo "       Copy it here or run this script from the deploy/ directory."
    exit 1
fi

echo "==> Installing Nginx vhost …"

# ── Step 1: Deploy HTTP-only vhost so certbot can complete the ACME challenge ─
# The full vhost (with SSL block) cannot load until the cert files exist,
# so we use a minimal HTTP-only config first.
cat > "$VHOST_DEST" <<HTTPONLY
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    # Let's Encrypt ACME challenge
    location /.well-known/acme-challenge/ {
        root $WEBROOT;
    }

    # Proxy everything else to the Docker container while cert is being issued
    location / {
        proxy_pass http://127.0.0.1:$PROXY_PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto http;
    }
}
HTTPONLY

ln -sf "$VHOST_DEST" "$VHOST_LINK"

# Remove default site if it conflicts on port 80
[ -L /etc/nginx/sites-enabled/default ] && rm /etc/nginx/sites-enabled/default

nginx -t
# Use systemctl if available (standard Ubuntu), fall back to service command (WSL)
if command -v systemctl &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null; then
    systemctl reload nginx
else
    service nginx reload 2>/dev/null || nginx -s reload
fi

# ── Obtain certificate ────────────────────────────────────────────────────────
echo ""
echo "==> Obtaining Let's Encrypt certificate for $DOMAIN …"
certbot certonly \
    --webroot \
    --webroot-path "$WEBROOT" \
    --non-interactive \
    --agree-tos \
    --email "$EMAIL" \
    -d "$DOMAIN"

# ── Step 2: Deploy full SSL vhost now that the cert files exist ───────────────
echo "==> Deploying SSL vhost …"
if [ ! -f "$VHOST_SOURCE" ]; then
    echo "ERROR: nginx-vhost.conf not found at $VHOST_SOURCE"
    exit 1
fi
sed -e "s/PORTAL_DOMAIN/$DOMAIN/g" -e "s/PROXY_PORT/$PROXY_PORT/g" "$VHOST_SOURCE" > "$VHOST_DEST"

# ── Reload Nginx with SSL config ──────────────────────────────────────────────
echo "==> Reloading Nginx with TLS …"
nginx -t
if command -v systemctl &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null; then
    systemctl reload nginx
else
    service nginx reload 2>/dev/null || nginx -s reload
fi

# ── Cron auto-renewal ─────────────────────────────────────────────────────────
CRON_JOB="0 3 * * * root certbot renew --quiet --post-hook 'systemctl reload nginx'"
CRON_FILE="/etc/cron.d/certbot-renew-rrp"
if [ ! -f "$CRON_FILE" ]; then
    echo "==> Installing auto-renewal cron job …"
    echo "$CRON_JOB" > "$CRON_FILE"
    chmod 644 "$CRON_FILE"
else
    echo "==> Auto-renewal cron job already exists — skipping."
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo "============================================================"
echo " SSL setup complete!"
echo ""
echo " Certificate : /etc/letsencrypt/live/$DOMAIN/fullchain.pem"
echo " Private key : /etc/letsencrypt/live/$DOMAIN/privkey.pem"
echo " Auto-renewal: $CRON_FILE (runs at 03:00 daily)"
echo " Nginx vhost : $VHOST_DEST"
echo ""
echo " Verify at  : https://$DOMAIN/"
echo " ACME test  : certbot renew --dry-run"
echo "============================================================"
