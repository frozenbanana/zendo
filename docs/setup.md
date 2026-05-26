# Zendo Setup Guide

## Prerequisites

- PHP 8.3+
- Node.js 20+
- Composer
- npm
- Docker & Docker Compose

All infrastructure services (PostgreSQL, Redis, Meilisearch, Mailpit) run in Docker. You do **not** need to install or configure PostgreSQL, Redis, Meilisearch, or Mailpit locally.

## Setup

### 1. Clone and install

```bash
cd ~/Work/metaprovide/lotus
git clone <repo-url> zendo
cd zendo
cp .env.example .env
composer install
npm install
php artisan key:generate
```

### 2. Start infrastructure services

```bash
docker compose up -d
```

This starts:
- PostgreSQL on `127.0.0.1:5432`
- Redis on `127.0.0.1:6379`
- Meilisearch on `127.0.0.1:7700`
- Mailpit on `127.0.0.1:8025` (web) and `127.0.0.1:1025` (SMTP)

### 3. Configure `.env`

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=zendo
DB_USERNAME=zendo
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=masterKey

BROADCAST_DRIVER=reverb
REVERB_APP_ID=zendo
REVERB_APP_KEY=zendo-key
REVERB_APP_SECRET=zendo-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS=noreply@zendo.test
MAIL_FROM_NAME="Zendo"

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

SENTRY_LARAVEL_DSN=
```

### 4. Database setup

```bash
php artisan migrate
php artisan db:seed
```

### 5. Start the application

```bash
# Terminal 1: Laravel dev server
php artisan serve --host=0.0.0.0

# Terminal 2: Queue worker
php artisan queue:work

# Terminal 3: Vite (frontend hot-reload)
npm run dev

# Terminal 4: Reverb (WebSockets)
php artisan reverb:start

# Terminal 5: Scheduler (optional, for cron jobs)
php artisan schedule:work
```

### 6. Add hosts file entries

```bash
# Add to /etc/hosts:
echo "127.0.0.1 ivy.zendo.test nalanda.zendo.test bodhi-tree.zendo.test" | sudo tee -a /etc/hosts
```

### 7. Access the application

| URL | Purpose |
|-----|---------|
| http://localhost:8000 | Hub homepage |
| http://ivy.zendo.test:8000 | Ivy Retreat Center (requires hosts file entry) |
| http://nalanda.zendo.test:8000 | Nalanda Center (requires hosts file entry) |
| http://bodhi-tree.zendo.test:8000 | Bodhi Tree House (requires hosts file entry) |
| http://localhost:8000/admin | Filament admin panel |
| http://localhost:8000/horizon | Horizon queue dashboard |
| http://localhost:8000/telescope | Telescope (local only) |
| http://localhost:8000/pulse | Pulse dashboard |
| http://localhost:8025 | Mailpit (email testing) |

## Common Docker Commands

```bash
docker compose up -d          # Start all services
docker compose down            # Stop all services (data preserved)
docker compose down -v         # Stop and delete all data (fresh start)
docker compose ps              # Check which services are running
docker compose logs redis      # View logs for a specific service
docker compose restart postgres # Restart a single service
```

## Seed Data

The seeder creates 3 tenants with different feature flags:

| Tenant | Slug | Features | Sample Data |
|--------|------|----------|-------------|
| Ivy Retreat Center | `ivy` | meals, lodging, memberships | 5 events, 3 buildings, 10 rooms, 3 meal plans, 2 membership plans, 15 registrations |
| Nalanda Center | `nalanda` | lodging, memberships (no meals) | 3 events, 2 buildings, 6 rooms, 0 meal plans, 1 membership plan, 8 registrations |
| Bodhi Tree House | `bodhi-tree` | meals (no lodging, no memberships) | 4 events, 0 buildings, 0 rooms, 2 meal plans, 0 membership plans, 5 registrations |

### Test users

| Email | Password | Global Role | Tenant Roles |
|-------|----------|-------------|-------------|
| admin@zendo.test | password | GLOBAL_ADMIN | ADMIN in all tenants |
| ivy-admin@zendo.test | password | USER | ADMIN in Ivy |
| nalanda-admin@zendo.test | password | USER | ADMIN in Nalanda |
| ivy-editor@zendo.test | password | USER | EDITOR in Ivy |
| ivy-viewer@zendo.test | password | USER | VIEWER in Ivy |
| guest@zendo.test | password | USER | None (guest) |

## Stripe Testing

Use Stripe test mode keys. Test card numbers:

| Card Number | Scenario |
|-------------|----------|
| `4242 4242 4242 4242` | Success |
| `4000 0000 0000 0002` | Decline |
| `4000 0000 0000 3220` | 3D Secure |

## Running Tests

### Pest (backend)

```bash
# All tests
php artisan test

# Specific categories
php artisan test --filter=Unit
php artisan test --filter=Feature
php artisan test --filter=Policy
php artisan test --filter=Job
php artisan test --filter=Webhook
php artisan test --filter=Architecture
php artisan test --filter=TenantIsolation

# Parallel execution
php artisan test --parallel

# Coverage
php artisan test --coverage
```

### Cypress (E2E)

```bash
# Interactive mode
npx cypress open

# Headless mode
npx cypress run

# Specific spec
npx cypress run --spec "cypress/e2e/registration.cy.js"
```