# Thrive — Ubuntu 22.04 VPS Deployment Runbook

Target: fresh Ubuntu 22.04 LTS VPS, bare metal (no Docker), root access assumed at start.
Run sections top-to-bottom. Each section is self-contained.

---

## 1. Initial Server Hardening

### 1.1 Create a deploy user

```bash
# Run as root
adduser deploy                          # follow prompts, set a strong password
usermod -aG sudo deploy                 # add to sudo group
```

### 1.2 Set up SSH key authentication for deploy user

On your **local machine**, generate a key pair if you do not already have one:

```bash
# Run locally
ssh-keygen -t ed25519 -C "thrive-deploy"
cat ~/.ssh/id_ed25519.pub               # copy this output
```

Back on the server (still as root):

```bash
mkdir -p /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
# Paste your public key into authorized_keys
echo "PASTE_YOUR_PUBLIC_KEY_HERE" >> /home/deploy/.ssh/authorized_keys  # TODO: replace with your actual public key
chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
```

### 1.3 Harden SSH daemon

```bash
cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak   # backup before editing

cat > /etc/ssh/sshd_config.d/99-thrive-hardening.conf << 'EOF'
PermitRootLogin no                  # disable direct root SSH
PasswordAuthentication no           # keys only — no password login
PubkeyAuthentication yes
AuthorizedKeysFile .ssh/authorized_keys
X11Forwarding no
AllowTcpForwarding no
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
EOF

systemctl restart sshd

# Test in a NEW terminal before closing your current session
# ssh deploy@YOUR_SERVER_IP
```

> **Warning:** Open a second SSH session to verify login works before closing the current one.
> If locked out, use your VPS provider's console to fix `/etc/ssh/sshd_config.d/99-thrive-hardening.conf`.

### 1.4 UFW Firewall

```bash
ufw default deny incoming
ufw default allow outgoing

ufw allow 22/tcp        # SSH
ufw allow 80/tcp        # HTTP
ufw allow 443/tcp       # HTTPS
ufw allow 1935/tcp      # RTMP ingest (OBS → MediaMTX)
ufw allow 8935/tcp      # MediaMTX HTTP API / HLS endpoint

ufw --force enable
ufw status verbose      # confirm rules
```

---

## 2. Install Dependencies

From here on, **run as the `deploy` user** (or prefix with `sudo` as needed).

```bash
ssh deploy@YOUR_SERVER_IP  # TODO: replace with your server IP
```

### 2.1 System packages and PPAs

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y \
    curl wget git unzip software-properties-common \
    gnupg2 ca-certificates lsb-release apt-transport-https
```

### 2.2 PHP 8.2 + extensions

```bash
# Add Ondrej's PPA — most reliable PHP source for Ubuntu
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y \
    php8.2 \
    php8.2-fpm \
    php8.2-pgsql \
    php8.2-redis \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-gd \
    php8.2-cli \
    php8.2-common

php -v   # should print PHP 8.2.x
```

### 2.3 Composer (latest)

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
# Verify hash from https://composer.github.io/pubkeys.html before running next line
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
composer --version
```

### 2.4 Node.js 20 LTS

```bash
# NodeSource script pins the repo to the specified major version
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v    # should print v20.x.x
npm -v
```

### 2.5 PostgreSQL 15

```bash
# Add the official PostgreSQL apt repo (ships newer versions than Ubuntu default)
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update
sudo apt install -y postgresql-15 postgresql-client-15

sudo systemctl enable postgresql
sudo systemctl start postgresql
psql --version
```

### 2.6 Redis 7

```bash
# Ubuntu 22.04's default Redis is 6.x; pull 7 from the official PPA
curl -fsSL https://packages.redis.io/gpg | sudo gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] https://packages.redis.io/deb $(lsb_release -cs) main" | \
    sudo tee /etc/apt/sources.list.d/redis.list
sudo apt update
sudo apt install -y redis
redis-server --version
```

### 2.7 Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### 2.8 FFmpeg

```bash
sudo apt install -y ffmpeg
ffmpeg -version | head -1
```

### 2.9 MediaMTX

MediaMTX is a single static binary — no package manager needed.

```bash
# Check https://github.com/bluenviron/mediamtx/releases for the latest version
# and replace the URL below accordingly
MEDIAMTX_VERSION="v1.9.1"   # TODO: pin to the latest stable release

cd /tmp
wget "https://github.com/bluenviron/mediamtx/releases/download/${MEDIAMTX_VERSION}/mediamtx_${MEDIAMTX_VERSION}_linux_amd64.tar.gz"
tar -xzf "mediamtx_${MEDIAMTX_VERSION}_linux_amd64.tar.gz"

sudo mv mediamtx /usr/local/bin/mediamtx
sudo chmod +x /usr/local/bin/mediamtx

# Create config directory
sudo mkdir -p /etc/mediamtx
mediamtx --version
```

---

## 3. PostgreSQL Setup

```bash
# Switch to the postgres system user to run psql
sudo -u postgres psql << 'SQL'
CREATE DATABASE thrive;
CREATE USER thrive_user WITH PASSWORD 'CHANGE_THIS_PASSWORD';   -- TODO: use a strong password
GRANT ALL PRIVILEGES ON DATABASE thrive TO thrive_user;
\c thrive
GRANT ALL ON SCHEMA public TO thrive_user;
\q
SQL
```

`.env` lines for the database (filled in Section 6):

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=thrive
DB_USERNAME=thrive_user
DB_PASSWORD=CHANGE_THIS_PASSWORD    # TODO: match the password set above
```

---

## 4. Redis Setup

### 4.1 Set a password

```bash
sudo nano /etc/redis/redis.conf
```

Find the `requirepass` line (it is commented out by default) and set a strong password:

```
# Before:
# requirepass foobared

# After (uncomment and change the value):
requirepass CHANGE_THIS_REDIS_PASSWORD    # TODO: use a strong password
```

Also bind Redis to localhost only (should already be the default, but verify):

```
bind 127.0.0.1 -::1
```

```bash
sudo systemctl enable redis-server
sudo systemctl restart redis-server

# Verify auth works
redis-cli -a CHANGE_THIS_REDIS_PASSWORD ping   # should return PONG
```

`.env` lines for Redis (filled in Section 6):

```dotenv
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=CHANGE_THIS_REDIS_PASSWORD   # TODO: match the password set above
```

---

## 5. Deploy the Laravel Application

### 5.1 Clone the repository

```bash
sudo mkdir -p /var/www/thrive
sudo chown deploy:deploy /var/www/thrive

# Ensure your server's SSH key is authorised on GitHub/GitLab
# If not, use HTTPS with a personal access token instead
git clone git@github.com:YOUR_ORG/thrive.git /var/www/thrive   # TODO: replace with your repo URL
cd /var/www/thrive
```

### 5.2 Install PHP dependencies

```bash
cd /var/www/thrive
composer install --no-dev --optimize-autoloader
```

### 5.3 Build frontend assets

```bash
cd /var/www/thrive
npm ci                  # clean install from package-lock.json
npm run build           # outputs compiled assets to public/build/
```

### 5.4 Create and populate .env

```bash
cp .env.example .env
nano .env               # fill in all TODO values from Section 6
```

### 5.5 Generate application key

```bash
php artisan key:generate
```

### 5.6 Run migrations

```bash
php artisan migrate --force   # --force is required in production (non-interactive)
```

### 5.7 Create storage symlink and optimise

```bash
php artisan storage:link        # links public/storage → storage/app/public
php artisan optimize            # caches config, routes, views, events
```

### 5.8 Set correct file ownership

```bash
# Nginx/PHP-FPM runs as www-data; storage and cache must be writable
sudo chown -R www-data:www-data /var/www/thrive/storage
sudo chown -R www-data:www-data /var/www/thrive/bootstrap/cache

# deploy user needs to write to storage during deploys too, so use the group
sudo usermod -aG www-data deploy
sudo chmod -R 775 /var/www/thrive/storage
sudo chmod -R 775 /var/www/thrive/bootstrap/cache
```

---

## 6. Production .env Template

Create `/var/www/thrive/.env` with the contents below.
Replace every `TODO` value before running `php artisan optimize`.

```dotenv
APP_NAME=Thrive
APP_ENV=production
APP_KEY=                            # filled by php artisan key:generate
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP       # TODO: replace with IP now; update to https://yourdomain.com later

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning                   # production: warning or error (not debug)

# ── Database ────────────────────────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=thrive
DB_USERNAME=thrive_user
DB_PASSWORD=CHANGE_THIS_PASSWORD    # TODO

# ── Cache / Session / Queue ─────────────────────────────────────────────────
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=log            # update if you add Laravel Echo later

# ── Redis ────────────────────────────────────────────────────────────────────
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=CHANGE_THIS_REDIS_PASSWORD   # TODO
REDIS_CLIENT=phpredis               # phpredis extension is installed above

# ── Mail ─────────────────────────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.net          # TODO: replace with your mail provider
MAIL_PORT=587
MAIL_USERNAME=TODO
MAIL_PASSWORD=TODO
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com    # TODO
MAIL_FROM_NAME="${APP_NAME}"

# ── PayChangu ────────────────────────────────────────────────────────────────
PAYCHANGU_SECRET_KEY=TODO           # TODO: get from PayChangu dashboard
PAYCHANGU_BASE_URL=https://api.paychangu.com

# ── MediaMTX / Streaming ─────────────────────────────────────────────────────
MEDIAMTX_API_URL=http://127.0.0.1:8935
# RTMP ingest URL broadcasters use: rtmp://YOUR_SERVER_IP/live/{streamKey}

# ── Filesystem ───────────────────────────────────────────────────────────────
FILESYSTEM_DISK=local
```

After editing, always re-optimise:

```bash
cd /var/www/thrive
php artisan optimize:clear
php artisan optimize
```

---

## 7. Nginx Configuration

### 7.1 Server block (IP-only, no domain yet)

```bash
sudo nano /etc/nginx/sites-available/thrive
```

Paste the following:

```nginx
# /etc/nginx/sites-available/thrive

# Redirect bare HTTP to the canonical server block (useful once domain is added)
# For now a single server block is enough.

server {
    listen 80 default_server;
    listen [::]:80 default_server;

    # TODO: once you have a domain, replace _ with: server_name yourdomain.com www.yourdomain.com;
    server_name _;

    root /var/www/thrive/public;
    index index.php;

    # ── Security headers ────────────────────────────────────────────────────
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # ── Laravel front controller ─────────────────────────────────────────────
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── PHP-FPM ──────────────────────────────────────────────────────────────
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm-thrive.sock;   # custom pool socket (see Section 8)
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Raise timeouts for long-running requests (e.g. large uploads)
        fastcgi_read_timeout 120;
    }

    # ── HLS live stream segments served as static files ───────────────────────
    # MediaMTX writes .m3u8 and .ts files here; Nginx serves them directly.
    location /hls/ {
        alias /var/www/thrive/storage/app/public/hls/;

        # CORS — Flutter player and browser players need this
        add_header Access-Control-Allow-Origin "*" always;
        add_header Cache-Control "no-cache";    # HLS segments must not be cached aggressively

        types {
            application/vnd.apple.mpegurl m3u8;
            video/mp2t ts;
        }

        expires -1;
    }

    # ── Compiled frontend assets (Vite build output) ──────────────────────────
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|webp)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # ── Gzip compression ─────────────────────────────────────────────────────
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/json application/javascript
               text/xml application/xml application/xml+rss text/javascript
               application/vnd.apple.mpegurl;

    # ── Block access to dotfiles ──────────────────────────────────────────────
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # ── Logging ───────────────────────────────────────────────────────────────
    access_log /var/log/nginx/thrive-access.log;
    error_log  /var/log/nginx/thrive-error.log;
}
```

Enable the site and test:

```bash
sudo ln -s /etc/nginx/sites-available/thrive /etc/nginx/sites-enabled/thrive
sudo rm -f /etc/nginx/sites-enabled/default   # remove default placeholder
sudo nginx -t                                  # must print "syntax is ok"
sudo systemctl reload nginx
```

---

## 8. PHP-FPM Pool Tuning

Create a dedicated pool for Thrive instead of using the default `www` pool.
This gives isolated resource limits and its own socket path.

```bash
sudo nano /etc/php/8.2/fpm/pool.d/thrive.conf
```

```ini
; /etc/php/8.2/fpm/pool.d/thrive.conf
; Tuned for a 2–4 GB RAM VPS. Adjust pm.max_children if you have more RAM.

[thrive]
user  = www-data
group = www-data

; Use a Unix socket — faster than TCP for local Nginx → PHP-FPM communication
listen = /run/php/php8.2-fpm-thrive.sock
listen.owner = www-data
listen.group = www-data
listen.mode  = 0660

; Dynamic process manager: spawns workers on demand, idles them when quiet
pm = dynamic

; Total worker ceiling — each PHP worker uses ~30–50 MB RSS.
; 2 GB RAM with OS overhead: ~20 workers. Raise to 30–40 for 4 GB.
pm.max_children = 20

; Workers kept alive when idle (avoids cold-start latency on quiet servers)
pm.start_servers = 4

; Floor/ceiling for idle workers
pm.min_spare_servers = 2
pm.max_spare_servers = 6

; Recycle workers after this many requests to prevent slow memory leaks
pm.max_requests = 500

; Log slow requests (adjust threshold to taste)
slowlog = /var/log/php8.2-fpm-thrive-slow.log
request_slowlog_timeout = 10s

; Env vars injected into PHP workers
env[APP_ENV] = production

; PHP settings that override php.ini for this pool only
php_admin_value[error_log]   = /var/log/php8.2-fpm-thrive.log
php_admin_flag[log_errors]   = on
php_value[upload_max_filesize] = 50M
php_value[post_max_size]       = 55M
php_value[memory_limit]        = 256M
php_value[max_execution_time]  = 120
```

Disable the default pool so it does not waste a process:

```bash
sudo mv /etc/php/8.2/fpm/pool.d/www.conf /etc/php/8.2/fpm/pool.d/www.conf.disabled
```

Restart FPM and confirm the socket exists:

```bash
sudo systemctl restart php8.2-fpm
ls -la /run/php/php8.2-fpm-thrive.sock   # must be present
```

---

## 9. Queue Worker (systemd)

```bash
sudo nano /etc/systemd/system/thrive-queue.service
```

```ini
[Unit]
Description=Thrive Laravel Queue Worker
After=network.target redis.service postgresql.service
Requires=redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/thrive

# --max-time restarts the worker every hour, preventing memory drift
ExecStart=/usr/bin/php /var/www/thrive/artisan queue:work redis \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --queue=default,heartbeats

# php artisan queue:restart writes a file that signals workers to stop cleanly
ExecStop=/usr/bin/php /var/www/thrive/artisan queue:restart

# Restart the worker automatically on crash; wait 5 s before retrying
Restart=on-failure
RestartSec=5

# Pass production environment
Environment=APP_ENV=production

StandardOutput=journal
StandardError=journal
SyslogIdentifier=thrive-queue

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable thrive-queue
sudo systemctl start thrive-queue
sudo systemctl status thrive-queue    # should show "active (running)"
```

View live logs:

```bash
journalctl -u thrive-queue -f
```

---

## 10. Scheduler (cron)

Laravel's scheduler needs exactly one cron entry — it manages everything else internally.

```bash
# Edit the crontab for www-data (the user PHP-FPM runs as)
sudo crontab -u www-data -e
```

Add this single line:

```cron
* * * * * /usr/bin/php /var/www/thrive/artisan schedule:run >> /dev/null 2>&1
```

Verify:

```bash
sudo crontab -u www-data -l   # should show the entry
```

---

## 11. MediaMTX Setup

### 11.1 Configuration file

```bash
sudo nano /etc/mediamtx/mediamtx.yml
```

```yaml
# /etc/mediamtx/mediamtx.yml
# MediaMTX: RTMP ingest, HLS output for Thrive live streaming

###############################################################################
# Global settings
###############################################################################

logLevel: info
logDestinations: [stdout]   # journald picks this up via the systemd service

# API — used by Thrive backend to check stream health / active paths
api: yes
apiAddress: 127.0.0.1:8935   # only accessible locally; Nginx proxies if needed

###############################################################################
# RTMP (ingest from OBS / broadcasting software)
###############################################################################

rtmp: yes
rtmpAddress: :1935
rtmpEncryption: no    # TODO: set to "optional" and provide certs if you want RTMPS

###############################################################################
# HLS (output consumed by Flutter player and web browsers)
###############################################################################

hls: yes
hlsAddress: :8888           # internal only; Nginx serves /hls/ from disk

# Write HLS segments to disk so Nginx can serve them as static files.
# {stream_key} maps to the RTMP stream path (e.g. rtmp://host/live/abc123)
hlsDirectory: /var/www/thrive/storage/app/public/hls

# Segment length (seconds) — shorter = lower latency, more HTTP requests
hlsSegmentDuration: 2s

# Keep 5 segments in the playlist (10 s of buffer)
hlsPartDuration: 200ms
hlsSegmentMaxSize: 50MB

###############################################################################
# Stream paths
###############################################################################

paths:
  # Wildcard: any RTMP path under /live/ is accepted
  # Broadcasters push to: rtmp://YOUR_SERVER_IP/live/STREAM_KEY
  "~^live/(?P<name>.+)$":
    source: publisher
    publishUser: ""           # authentication handled by Laravel via stream keys
    publishPass: ""

    # Run FFmpeg to produce multiple HLS qualities (optional; remove if not needed)
    # If you want single-quality HLS without re-encoding, delete runOnReady below.
    # runOnReady: ffmpeg -i rtmp://127.0.0.1:1935/$MTX_PATH
    #     -c:v libx264 -preset veryfast -b:v 1500k -maxrate 1500k -bufsize 3000k
    #     -c:a aac -b:a 128k -f hls
    #     -hls_time 2 -hls_list_size 5 -hls_flags delete_segments
    #     /var/www/thrive/storage/app/public/hls/$MTX_QUERY_PARAM_STREAM_KEY/index.m3u8
    # runOnReadyRestart: yes

  all_others:
    source: redirect
    sourceRedirect: rtsp://localhost:$RTSP_PORT/$MTX_PATH
```

Ensure the HLS output directory exists and is writable by the mediamtx process:

```bash
sudo mkdir -p /var/www/thrive/storage/app/public/hls
sudo chown -R www-data:www-data /var/www/thrive/storage/app/public/hls
```

### 11.2 systemd service for MediaMTX

```bash
sudo nano /etc/systemd/system/mediamtx.service
```

```ini
[Unit]
Description=MediaMTX RTMP/HLS Media Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data

ExecStart=/usr/local/bin/mediamtx /etc/mediamtx/mediamtx.yml

# Restart immediately on crash; the 3 s delay avoids tight restart loops
Restart=on-failure
RestartSec=3

StandardOutput=journal
StandardError=journal
SyslogIdentifier=mediamtx

# Allow binding to port 1935 (< 1024 usually requires root, but systemd can grant it)
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable mediamtx
sudo systemctl start mediamtx
sudo systemctl status mediamtx

# Confirm ports are open
ss -tlnp | grep -E '1935|8935|8888'
```

---

## 12. Add Domain + Cloudflare SSL (Do This Later)

When your domain is ready, complete these steps in order.

### 12.1 DNS — Cloudflare setup

1. Log in to Cloudflare and add your domain.
2. Create an **A record**:
   - Name: `@` (or `yourdomain.com`)
   - Value: your server's IP address
   - **Proxy status: DNS Only (grey cloud)** initially — turn off the Cloudflare proxy until SSL is issued, then re-enable.
3. Optionally add a `www` CNAME pointing to `@`.
4. Wait for DNS propagation (usually a few minutes with Cloudflare).

### 12.2 Update Nginx server_name

```bash
sudo nano /etc/nginx/sites-available/thrive
```

Change:

```nginx
# Before
server_name _;

# After
server_name yourdomain.com www.yourdomain.com;    # TODO: replace with your domain
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 12.3 Install Certbot and obtain SSL certificate

```bash
sudo apt install -y certbot python3-certbot-nginx

# Certbot reads server_name from Nginx and configures SSL automatically
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com   # TODO: replace with your domain

# Test auto-renewal
sudo certbot renew --dry-run
```

Certbot automatically:
- Obtains a Let's Encrypt certificate
- Edits your Nginx server block to add `ssl_certificate` directives
- Adds an HTTP → HTTPS redirect
- Sets up a renewal cron/systemd timer

### 12.4 Update .env and re-optimise

```bash
cd /var/www/thrive
nano .env
# Change: APP_URL=https://yourdomain.com    # TODO: replace with your domain

php artisan optimize:clear
php artisan optimize
```

### 12.5 Enable Cloudflare proxy (orange cloud)

After SSL is confirmed working:
1. In Cloudflare, switch the A record proxy status to **Proxied (orange cloud)**.
2. Set Cloudflare SSL/TLS mode to **Full (strict)** — this validates the origin cert.
3. Enable **Always Use HTTPS** in Cloudflare SSL/TLS → Edge Certificates.

---

## 13. Deployment Update Script

Place this at `/var/www/thrive/deploy.sh`. Run it as the `deploy` user for all future code pushes.

```bash
sudo nano /var/www/thrive/deploy.sh
```

```bash
#!/usr/bin/env bash
# deploy.sh — zero-downtime-ish deploy for Thrive
# Usage: cd /var/www/thrive && bash deploy.sh
# Must be run as the deploy user (not root, not www-data)

set -euo pipefail   # exit on error, undefined var, or pipe failure

APP_DIR="/var/www/thrive"
PHP="/usr/bin/php"
COMPOSER="/usr/local/bin/composer"

echo "==> [1/8] Pulling latest code..."
cd "$APP_DIR"
git pull origin main   # TODO: change branch name if yours is not "main"

echo "==> [2/8] Installing PHP dependencies (production, no dev)..."
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "==> [3/8] Installing Node dependencies..."
npm ci --prefer-offline   # --prefer-offline uses local cache when possible

echo "==> [4/8] Building frontend assets..."
npm run build

echo "==> [5/8] Running database migrations..."
$PHP artisan migrate --force --no-interaction

echo "==> [6/8] Clearing and re-caching config, routes, views..."
$PHP artisan optimize:clear
$PHP artisan optimize

echo "==> [7/8] Restarting queue workers (they will drain current jobs first)..."
$PHP artisan queue:restart

echo "==> [8/8] Fixing storage permissions..."
sudo chown -R www-data:www-data "$APP_DIR/storage"
sudo chown -R www-data:www-data "$APP_DIR/bootstrap/cache"

echo ""
echo "Deploy complete."
```

Make it executable:

```bash
chmod +x /var/www/thrive/deploy.sh
```

Allow the `deploy` user to run the chown commands without a password prompt:

```bash
sudo visudo
```

Add this line at the bottom:

```
deploy ALL=(ALL) NOPASSWD: /bin/chown -R www-data\:www-data /var/www/thrive/storage, /bin/chown -R www-data\:www-data /var/www/thrive/bootstrap/cache
```

Run a deploy:

```bash
cd /var/www/thrive && bash deploy.sh
```

---

## Quick-Reference Checklist

After completing all sections, verify the following:

```bash
# Services running
sudo systemctl is-active nginx php8.2-fpm postgresql redis-server mediamtx thrive-queue

# Ports listening
ss -tlnp | grep -E ':80|:443|:1935|:8935|:5432|:6379'

# Laravel health
cd /var/www/thrive
php artisan about          # prints environment, drivers, cache status

# Queue connected
php artisan queue:monitor  # shows queue depth

# MediaMTX API
curl http://127.0.0.1:8935/v3/paths/list   # returns JSON list of active streams
```

---

## File Locations Summary

| What | Path |
|------|------|
| Application root | `/var/www/thrive` |
| Nginx site config | `/etc/nginx/sites-available/thrive` |
| PHP-FPM pool config | `/etc/php/8.2/fpm/pool.d/thrive.conf` |
| MediaMTX config | `/etc/mediamtx/mediamtx.yml` |
| Queue worker service | `/etc/systemd/system/thrive-queue.service` |
| MediaMTX service | `/etc/systemd/system/mediamtx.service` |
| HLS output directory | `/var/www/thrive/storage/app/public/hls/` |
| Deploy script | `/var/www/thrive/deploy.sh` |
| Nginx logs | `/var/log/nginx/thrive-{access,error}.log` |
| PHP-FPM log | `/var/log/php8.2-fpm-thrive.log` |
| Queue worker logs | `journalctl -u thrive-queue` |
| MediaMTX logs | `journalctl -u mediamtx` |
