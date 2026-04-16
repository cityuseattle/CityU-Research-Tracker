import paramiko, os

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('rcgapimtest.eastus2.cloudapp.azure.com',
               username='azureadmin', password='Microsoft12345', timeout=30)

sftp = client.open_sftp()
base = '/var/www/html/wp-content/plugins/research-review-portal'
files = [
    ('includes/class-portal-rest.php',       base + '/includes/class-portal-rest.php'),
    ('includes/class-portal-data.php',        base + '/includes/class-portal-data.php'),
    ('includes/class-workflow-engine.php',    base + '/includes/class-workflow-engine.php'),
    ('research-review-portal.php',            base + '/research-review-portal.php'),
    ('assets/portal.js',                      base + '/assets/portal.js'),
    ('assets/portal.css',                     base + '/assets/portal.css'),
]
for local, remote in files:
    sftp.put(local, remote)
    status = 'OK' if os.path.getsize(local) == sftp.stat(remote).st_size else 'SIZE MISMATCH'
    print(f'{local}: [{status}]')
sftp.close()

_, out, _ = client.exec_command('echo Microsoft12345 | sudo -S apache2ctl graceful 2>&1; echo "EXIT:$?"')
print('Apache reload:', out.read().decode().strip())
client.close()
