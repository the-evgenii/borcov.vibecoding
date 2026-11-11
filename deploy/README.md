# Deployment — vibecoding-marketing

## Public test URL

`https://62.109.12.44:8443`

Admin login: `admin@vibecoding.local`. The password and cookie key are in the local, untracked `deploy/credentials.txt` (mode 0600).

The remote production copy is `/opt/vibecoding-marketing/app/.env` (root:vibecoding, 0640). Do not print or commit either file.

## TLS

The deployment uses a self-signed local CA and a leaf certificate with SAN `IP:62.109.12.44`, because no domain was provided. Browsers will show a certificate warning unless the local CA is explicitly trusted.

- Local CA certificate for curl/trust: `deploy/caddy-root.crt`
- Fingerprints: `deploy/tls-fingerprints.txt`
- Root CA SHA-256: `B0:82:96:3E:91:5E:0C:50:11:5E:A4:D2:AA:29:B5:BD:87:CE:D8:49:CA:58:69:05:75:0C:22:01:C5:86:96:1F`
- Leaf SHA-256: `10:E9:64:42:9A:27:C9:2B:78:45:57:6C:12:8B:40:2E:A3:29:DF:45:28:B5:AA:77:82:73:51:3C:52:00:26:D4`

Verification uses `curl --cacert deploy/caddy-root.crt`; `-k` is not used.

The CA certificate, fingerprint files and credentials file are local deployment artifacts and are intentionally excluded from Git.

Caddy serves only 8443 over TLS. Caddy admin is disabled. The app sets secure session cookies, CSP, `X-Content-Type-Options`, `X-Frame-Options` and `Referrer-Policy`.

## Isolation

- User/group: `vibecoding` (system user, nologin shell).
- Root: `/opt/vibecoding-marketing`
- Application: `/opt/vibecoding-marketing/app` (code root-owned; runtime/assets owned by `vibecoding`)
- SQLite: `/opt/vibecoding-marketing/data/db.sqlite`
- PHP-FPM socket: `/opt/vibecoding-marketing/run/php-fpm.sock`
- PHP-FPM config: `/opt/vibecoding-marketing/php-fpm.conf` — 2 workers max, 128M per worker, display_errors off.
- Caddy config: `/opt/vibecoding-marketing/caddy/Caddyfile`
- Certificates/keys: `/opt/vibecoding-marketing/caddy/certs/`
- Logs: `/opt/vibecoding-marketing/logs/`
- PHP-FPM unit: `vibecoding-marketing-php-fpm.service`
- Caddy unit: `vibecoding-marketing-caddy.service`

Existing `caddy`, `php8.5-fpm`, `frankenphp` on 8081/8082 and other services were not restarted or modified. Docker/Compose were not installed; no container deployment was used.

Port 8443 was free, has a pre-existing UFW allow rule, and is now bound only by the isolated Caddy. The old nginx site `octane-test` is inactive/disabled and was not modified.

## Deploy layout notes

Only production files were transferred. `tests`, `runtime`, local `.env`, coverage, logs, `vendor` from the local tree and `web/router.php` were not copied. Production dependencies were built locally with Composer `--no-dev` and uploaded; the server did not run a dependency resolver.

The app is served strictly from `/opt/vibecoding-marketing/app/web`; `.env`, SQLite, vendor, runtime and tests are outside the web root.

## Operational commands

```bash
systemctl status vibecoding-marketing-php-fpm.service
systemctl status vibecoding-marketing-caddy.service
journalctl -u vibecoding-marketing-php-fpm.service -n 50
journalctl -u vibecoding-marketing-caddy.service -n 50

# migration/admin/demo seed as the isolated user
sudo -u vibecoding -H env HOME=/opt/vibecoding-marketing \
  /usr/bin/php /opt/vibecoding-marketing/app/yii migrate/up --interactive=0
sudo -u vibecoding -H env HOME=/opt/vibecoding-marketing \
  /usr/bin/php /opt/vibecoding-marketing/app/yii app/init-admin
sudo -u vibecoding -H env HOME=/opt/vibecoding-marketing \
  /usr/bin/php /opt/vibecoding-marketing/app/yii app/demo-data
```

The demo seed is idempotent: first run loaded 5 files / 89 rows; second run loaded 0 and skipped 5.

## Verification summary

- `curl --cacert deploy/caddy-root.crt https://62.109.12.44:8443/login` → 200, HTTP/2.
- Unauthenticated `/keywords` → 302 `/login`.
- Secure cookie present; no `X-Debug` header.
- CSV and JSON upload → 302; invalid JSON/CSV → 422.
- Missing CSRF on `/settings` → 400.
- Campaign generation → 302; preview structure check passed; export 200, 26 columns, Final URLs include `https://site.pro/Website-Builder/` and `https://site.pro/de/`.
- `/.env`, `/data/db.sqlite`, `/vendor/autoload.php`, `/runtime/logs/app.log`, `/tests/bootstrap.php` → 404.
- E2E artifacts: `runtime/deploy-e2e/`, log `runtime/deploy-e2e.log`.

Memory is tight: ~393 MiB available and swap nearly exhausted. Keep PHP-FPM at 2 workers and monitor before scaling.
