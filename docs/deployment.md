# Zendo Deployment Guide

## Architecture Overview

```
                    ┌─────────────────────┐
                    │   Nginx / Caddy     │
                    │   (reverse proxy)   │
                    └──────────┬──────────┘
                               │
                    ┌──────────┴──────────┐
                    │   Laravel (Octane)   │
                    │   or php-fpm        │
                    └──────────┬──────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
   ┌──────┴──────┐    ┌───────┴───────┐    ┌──────┴──────┐
   │  PostgreSQL  │    │    Redis      │    │ Meilisearch  │
   │  (primary)   │    │  (queue/cache │    │  (search)    │
   │              │    │   /broadcast) │    │              │
   └──────────────┘    └───────┬───────┘    └─────────────┘
                               │
                    ┌──────────┴──────────┐
                    │   Reverb            │
                    │   (WebSocket)       │
                    └─────────────────────┘
```

## Local Development

### Docker Compose

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan key:generate
```

### Services

| Service | Container | Port | Purpose |
|---------|-----------|------|---------|
| Laravel app | `app` | 8000 | Application server |
| Nginx | `nginx` | 80 | Reverse proxy |
| PostgreSQL | `postgres` | 5432 | Database |
| Redis | `redis` | 6379 | Queue, cache, sessions, broadcast |
| Meilisearch | `meilisearch` | 7700 | Full-text search |
| Reverb | `reverb` | 8080 | WebSockets |
| Mailpit | `mailpit` | 8025 | Email testing (local only) |

## Staging

### Requirements

- Ubuntu 22.04+ or equivalent
- PHP 8.3+
- PostgreSQL 16+
- Redis 7+
- Meilisearch 1.x+
- Node.js 20+ (for building Inertia assets)
- Supervisor (for queue workers and Reverb)

### Setup

```bash
# 1. Clone and install
git clone <repo> /var/www/zendo
cd /var/www/zendo
composer install --optimize-autoloader --no-dev
npm install && npm run build

# 2. Configure environment
cp .env.example .env
# Edit .env with staging values

# 3. Generate key
php artisan key:generate

# 4. Run migrations
php artisan migrate --force

# 5. Create storage link
php artisan storage:link

# 6. Seed (first time only)
php artisan db:seed --force

# 7. Enable RLS
php artisan rls:enable

# 8. Start services
sudo systemctl start zendo-worker
sudo systemctl start zendo-reverb
sudo systemctl start zendo-scheduler
```

### Supervisor configuration

```ini
# /etc/supervisor/conf.d/zendo-worker.conf
[program:zendo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zendo/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/zendo/worker.log
stopwaitsecs=3600

# /etc/supervisor/conf.d/zendo-reverb.conf
[program:zendo-reverb]
command=php /var/www/zendo/artisan reverb:start --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/zendo/reverb.log

# /etc/supervisor/conf.d/zendo-scheduler.conf
[program:zendo-scheduler]
command=php /var/www/zendo/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/zendo/scheduler.log
```

### Nginx configuration

```nginx
server {
    listen 80;
    server_name zendo.test *.zendo.test;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name zendo.test *.zendo.test;

    ssl_certificate /etc/ssl/certs/zendo.pem;
    ssl_certificate_key /etc/ssl/private/zendo.key;

    root /var/www/zendo/public;
    index index.php;

    charset utf-8;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # WebSocket proxy for Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }
}
```

## Production

### Checklist before going live

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `FORCE_HTTPS=true`
- [ ] `BYPASS_AUTH` is NOT in `.env` (no auth bypass in production)
- [ ] Database has RLS policies enabled (`php artisan rls:enable`)
- [ ] `QUEUE_CONNECTION=redis` (not `sync`)
- [ ] `CACHE_DRIVER=redis` (not `file`)
- [ ] `SESSION_DRIVER=redis` (not `file`)
- [ ] `SCOUT_DRIVER=meilisearch`
- [ ] Stripe webhook secret configured
- [ ] Sentry DSN configured
- [ ] Mail configured (not Mailpit)
- [ ] File storage configured (S3/R2, not `local`)
- [ ] Queue workers running (Supervisor)
- [ ] Scheduler running (Supervisor)
- [ ] Reverb running (Supervisor)
- [ ] Meilisearch running and indexed
- [ ] SSL certificate installed
- [ ] Security headers middleware enabled (not just in Nginx)
- [ ] Rate limiting configured for login, registration, API, webhooks
- [ ] Telescope disabled in production (`TELESCOPE_ENABLED=false`)
- [ ] Pulse dashboard restricted to admins
- [ ] Horizon dashboard restricted to admins
- [ ] Horizon password-protected
- [ ] `php artisan config:cache` run
- [ ] `php artisan route:cache` run
- [ ] `php artisan view:cache` run
- [ ] `php artisan event:cache` run
- [ ] Database backups configured
- [ ] Redis persistence configured
- [ ] Log rotation configured
- [ ] Monitoring alerts configured (queue failure, high error rate, disk space)

### Octane (optional, for performance)

```bash
composer require laravel/octane
php artisan octane:start --server=roadrunner --workers=4 --max-requests=500
```

Use Octane after the baseline app is stable and tenant scope reset behavior has been tested. Octane keeps the application in memory, so any leaked state (like `app('current_tenant_id')`) will persist between requests. The `ScopeTenant` middleware must explicitly clear the tenant context at the end of each request.

### Database backups

```bash
# Daily backup via pg_dump
pg_dump -U zendo -Fc zendo > /var/backups/zendo/$(date +%Y%m%d).dump

# Rotate backups older than 30 days
find /var/backups/zendo/ -name "*.dump" -mtime +30 -delete
```

Add to scheduler:

```php
// app/Console/Kernel.php
$schedule->command('backup:database')->dailyAt('03:00');
```

### Monitoring

| Service | What to monitor |
|---------|---------------|
| Sentry | All unhandled exceptions, with tenant_id and user_id context |
| Horizon | Failed jobs, queue length, throughput |
| Pulse | Response time > 2s, exception rate > 1%, queue backlog > 100 |
| `/health` | DB, Redis, Meilisearch, queue worker, storage connectivity |
| Structured logs | Rate limit hits, cross-tenant access, auth failures |
| Supervisor | Process crashes, restart count |

### Deployment script

```bash
#!/bin/bash
set -e

echo "Deploying Zendo..."

# Pull latest code
git pull origin main

# Install dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Cache configs
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations
php artisan migrate --force

# Enable RLS
php artisan rls:enable --force

# Rebuild search index
php artisan scout:import "App\Modules\Events\Models\Event"
php artisan scout:import "App\Modules\Tenancy\Models\Tenant"
php artisan scout:import "App\Modules\Events\Models\Teacher"

# Restart workers
sudo supervisorctl restart zendo-worker:*
sudo supervisorctl restart zendo-reverb:*
sudo supervisorctl restart zendo-scheduler:*

# Health check
curl -sf http://localhost/health || (echo "Health check failed!" && exit 1)

echo "Deployment complete!"
```