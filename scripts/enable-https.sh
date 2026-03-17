#!/usr/bin/env bash
# ============================================================
# Research Review Portal — Enable HTTPS with Let's Encrypt
#
# Run on the Ubuntu VM as azureadmin:
#   chmod +x scripts/enable-https.sh
#   sudo bash scripts/enable-https.sh
#
# Prerequisites:
#   - Port 443 open in Azure NSG (see OPERATIONS-MANUAL.md)
#   - Port 80 open (used for ACME HTTP-01 challenge)
#   - Domain resolves publicly (e.g. rcgapimtest.eastus2.cloudapp.azure.com)
# ============================================================
set -euo pipefail

DOMAIN="${DOMAIN:-rcgapimtest.eastus2.cloudapp.azure.com}"
ADMIN_EMAIL="${ADMIN_EMAIL:-vejendlakirankumar@cityu.edu}"
WP_PATH="/var/www/html"

echo "==> Domain  : $DOMAIN"
echo "==> WP path : $WP_PATH"
echo ""

# ── 1. Install certbot ────────────────────────────────────────────────────────
echo "==> Installing certbot and Apache plugin..."
apt-get update -qq
apt-get install -y certbot python3-certbot-apache

# ── 2. Obtain Let's Encrypt certificate ──────────────────────────────────────
echo "==> Requesting Let's Encrypt certificate for $DOMAIN ..."
certbot --apache \
  --non-interactive \
  --agree-tos \
  --email "$ADMIN_EMAIL" \
  --domains "$DOMAIN" \
  --redirect         # auto-add HTTP→HTTPS redirect

# certbot --apache automatically:
#   - Creates /etc/apache2/sites-available/<domain>-le-ssl.conf
#   - Enables the SSL site and mod_ssl
#   - Adds a 301 redirect from HTTP to HTTPS in the original vhost
#   - Installs the renewal hook

# ── 3. Verify Apache SSL is enabled ──────────────────────────────────────────
echo "==> Enabling Apache SSL module and headers..."
a2enmod ssl headers
service apache2 restart

# ── 4. Update WordPress URLs to https ────────────────────────────────────────
NEW_URL="https://$DOMAIN"
echo "==> Updating WordPress site URL to $NEW_URL ..."

wp --path="$WP_PATH" --allow-root config set WP_HOME    "$NEW_URL" --type=constant
wp --path="$WP_PATH" --allow-root config set WP_SITEURL "$NEW_URL" --type=constant
wp --path="$WP_PATH" --allow-root option update home    "$NEW_URL"
wp --path="$WP_PATH" --allow-root option update siteurl "$NEW_URL"
wp --path="$WP_PATH" --allow-root search-replace "http://$DOMAIN" "$NEW_URL" --skip-columns=guid --allow-root

echo ""
echo "==> Flushing rewrite rules..."
wp --path="$WP_PATH" --allow-root rewrite flush

# ── 5. Set up auto-renewal cron ──────────────────────────────────────────────
echo "==> Ensuring auto-renewal cron is active..."
# certbot installs /etc/cron.d/certbot automatically; verify it exists
if [ -f /etc/cron.d/certbot ]; then
  echo "    Auto-renewal cron already installed at /etc/cron.d/certbot"
else
  # Fallback: add a daily renewal attempt via crontab
  (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'service apache2 reload'") | crontab -
  echo "    Added certbot renew to crontab (daily at 03:00)"
fi

# ── 6. Summary ────────────────────────────────────────────────────────────────
echo ""
echo "============================================================"
echo " HTTPS is now active!"
echo ""
echo " Portal URL     : $NEW_URL"
echo " Certificate    : /etc/letsencrypt/live/$DOMAIN/"
echo " Auto-renews    : every 60 days (cert valid 90 days)"
echo " HTTP redirect  : http://$DOMAIN → https://$DOMAIN (301)"
echo ""
echo " NEXT STEPS:"
echo "  1. Update Portal Settings → SSO → Redirect URI to:"
echo "     $NEW_URL/wp-json/research-portal/v1/auth/callback"
echo "  2. Update Azure App Registration → Authentication → Redirect URI"
echo "     to the same URL."
echo "  3. Test: curl -I http://$DOMAIN  (should get 301 → https)"
echo "           curl -I $NEW_URL        (should get 200)"
echo "============================================================"
