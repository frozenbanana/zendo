# Deployment Checklist

## Pre-Deployment

- [ ] Run all tests: `php artisan test --compact`
- [ ] Run linter: `composer run lint:check`
- [ ] Verify `.env.production` has all required variables:
  - [ ] `APP_KEY` set
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false`
  - [ ] `DB_*` credentials configured
  - [ ] `REDIS_*` connection configured
  - [ ] `QUEUE_CONNECTION=redis`
  - [ ] `SCOUT_DRIVER=meilisearch`
  - [ ] `MEILISEARCH_HOST` and `MEILISEARCH_KEY` set
  - [ ] `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` set
  - [ ] `REVERB_*` configured for WebSockets
  - [ ] `LOG_CHANNEL=json-stderr` for structured logs in production
- [ ] Stripe webhook endpoint configured in Stripe Dashboard
- [ ] Meilisearch indexes created: `php artisan scout:sync-all-indexes`

## Deployment Steps

1. [ ] Pull latest code: `git pull origin main`
2. [ ] Install dependencies: `composer install --no-dev --optimize-autoloader`
3. [ ] Install frontend: `npm ci && npm run build`
4. [ ] Run migrations: `php artisan migrate --force`
5. [ ] Clear caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. [ ] Sync search indexes: `php artisan scout:sync-all-indexes`
7. [ ] Restart queue workers: `php artisan horizon:terminate`
8. [ ] Restart Reverb: `php artisan reverb:restart`

## Post-Deployment Verification

- [ ] Health check passes: `curl https://your-domain.com/health`
- [ ] Horizon dashboard accessible: `https://your-domain.com/horizon`
- [ ] Test registration flow end-to-end
- [ ] Verify webhook endpoint returns 200: `curl -X POST https://your-domain.com/stripe/webhook`
- [ ] Check structured logs are JSON formatted
- [ ] Verify rate limiting on login: more than 5 failed attempts should throttle
- [ ] Security headers present on responses: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`

## Rollback Plan

- [ ] Previous migration rollback: `php artisan migrate:rollback --step=1`
- [ ] Previous code deploy: `git checkout <previous-tag>`
- [ ] Clear all caches after rollback