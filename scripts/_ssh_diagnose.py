"""Temporary SSH diagnostic + fix script for the VM Apache/SSL issue."""
import paramiko, sys

HOST = "rcgapimtest.eastus2.cloudapp.azure.com"
USER = "azureadmin"
PW   = "Microsoft12345"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    client.connect(HOST, username=USER, password=PW, timeout=15)
    print(f"[OK] Connected to {HOST}\n")
except Exception as e:
    sys.exit(f"[FAIL] Cannot connect: {e}")

def run(label, cmd, show=True):
    full = f"echo '{PW}' | sudo -S bash -c {repr(cmd)}" if cmd.lstrip().startswith("sudo") else cmd
    _, stdout, stderr = client.exec_command(full, timeout=30)
    out = (stdout.read() + stderr.read()).decode(errors="replace").strip()
    if show:
        print(f"=== {label} ===")
        print(out or "(no output)")
        print()
    return out

# ── Diagnostics ───────────────────────────────────────────────────────────────
run("OS / kernel",       "uname -a")
run("Apache status",     "sudo service apache2 status 2>&1 | head -30")
run("Apache configtest", "sudo apache2ctl -t 2>&1")
run("Ports 80/443",      "sudo ss -tlnp | grep -E ':80|:443'")
run("Sites enabled",     "sudo ls -la /etc/apache2/sites-enabled/")
run("ports.conf",        "sudo cat /etc/apache2/ports.conf")
run("SSL module",        "sudo apache2ctl -M 2>&1 | grep ssl")
run("Last error log",    "sudo tail -40 /var/log/apache2/error.log 2>/dev/null || echo 'no error log'")
run("apache2.conf snippet", "sudo grep -n 'ServerName\\|Listen\\|IncludeOptional' /etc/apache2/apache2.conf | head -20")
run("default-ssl conf",  "sudo cat /etc/apache2/sites-available/default-ssl.conf 2>/dev/null | head -40")
run("certbot certs",     "sudo certbot certificates 2>&1 | head -30")

client.close()
print("[DONE] Review output above and share with the agent for the fix.")
