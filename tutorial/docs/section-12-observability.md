# 12. Observability

> **Milestone:** You can see every request, queue job, and error across Horizon, Telescope, Pulse, and Sentry dashboards — all with tenant context so you know *which center* is affected.

## Prerequisites

- [Section 11: Testing](section-11-testing.md) completed
- Docker services running (`docker compose up -d`)
- All modules from Sections 1–11 in place

## What You'll Learn

| Concept | What it is | Why it matters |
|---------|-----------|----------------|
| Horizon | Queue monitoring dashboard | See failed jobs, throughput, retry status |
| Telescope | Request/query debugging | Local and staging: see every query, log, event |
| Pulse | Application health dashboard | Response times, exceptions, server resources |
| Sentry | Production error tracking | Get alerts when things break, with tenant context |
| Structured logging | JSON logs with context | Find issues fast with request ID, tenant ID, user ID |
| Health endpoint | `/health` checking all services | Pre-flight check before serving traffic |

---

## The Big Picture

Observability is like the dashboard in a car. **Horizon** is the engine diagnostic screen (what's happening under the hood right now). **Telescope** is the trip recorder (detailed log of every stop). **Pulse** is the general health indicator (oil pressure, temperature). **Sentry** is the check engine light that calls the mechanic. And the **health endpoint** is the pre-flight checklist before takeoff.

Without observability, you're driving blind. Someone calls you at 3 AM saying "Nalanda's registrations are broken." Without these tools, you're SSH-ing into a server, grepping log files, guessing. With them, you open Horizon, see the failed job, open Sentry, see the stack trace with the exact tenant and user, and fix it before your morning coffee.

```mermaid
graph TD
    subgraph "Request Flow"
        REQ[HTTP Request] --> APP[Laravel App]
        APP --> DB[(PostgreSQL)]
        APP --> RD[(Redis Queue)]
        APP --> MS[(Meilisearch)]
    end

    subgraph "Observability Stack"
        H[Horizon<br/>Queue Dashboard] --> RD
        T[Telescope<br/>Request Inspector] --> APP
        P[Pulse<br/>Health Dashboard] --> APP
        S[Sentry<br/>Error Tracker] --> APP
        LOG[Structured Logs] --> APP
        HP[/health Endpoint] --> DB
        HP --> RD
        HP --> MS
    end

    style H fill:#7c3aed,color:#fff
    style T fill:#2563eb,color:#fff
    style P fill:#059669,color:#fff
    style S fill:#dc2626,color:#fff
    style LOG fill:#d97706,color:#fff
    style HP fill:#0891b2,color:#fff
```

---

## Step 1: Install and Configure Horizon

Horizon gives you a beautiful dashboard for monitoring your Redis queues. You can see which jobs are processing, which failed, and what the throughput looks like.

```bash
cd ~/Work/metaprovide/lotus/zendo
composer require laravel/horizon
php artisan horizon:install
```

This creates `config/horizon.php`. Edit it to match our multi-tenant setup:

```php
<?php

use Illuminate\Support\Str;

return [
    'domain' => env('HORIZON_DOMAIN'),

    'uri' => 'horizon',

    'middleware' => ['web', 'auth', \App\Modules\Tenancy\Middleware\EnsureSuperAdmin::class],

    'guard' => 'web',

    'notifications' => [
        'slack' => [
            'webhook_url' => env('HORIZON_SLACK_WEBHOOK_URL'),
            'channel' => env('HORIZON_SLACK_CHANNEL', '#zendo-alerts'),
        ],
    ],

    'waits' => [
        'redis:default' => 60,
    ],

    'trim' => [
        'recent' => 60 * 24,      // 24 hours
        'pending' => 60 * 24,     // 24 hours
        'completed' => 60 * 24,   // 24 hours
        'recent_failed' => 60 * 7, // 7 days
        'failed' => 60 * 30,      // 30 days
        'monitored' => 60 * 24,   // 24 hours
    ],

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'webhooks', 'notifications', 'registrations'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 8,
            'maxBalanceShift' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-production' => [
                'connection' => 'redis',
                'queue' => ['default', 'webhooks', 'notifications', 'registrations'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 16,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 120,
            ],
        ],

        'local' => [
            'supervisor-local' => [
                'connection' => 'redis',
                'queue' => ['default', 'webhooks', 'notifications', 'registrations'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 4,
                'tries' => 3,
                'timeout' => 60,
            ],
        ],
    ],
];
```

??? tip "Why separate queues?"
    Not all jobs are equal. Webhook processing must be fast (Stripe retries on timeout). Notifications can wait. Registration confirmation is important but not time-critical. Separate queues let you prioritize:

    - `webhooks`: Fast, retry-sensitive (3 tries, 30s timeout)
    - `registrations`: Medium priority
    - `notifications`: Can wait
    - `default`: Everything else

### Add Tenant Context to Jobs

Every queued job should include tenant context so Horizon shows which center the job belongs to. Add this to your base job class:

```php
<?php

namespace App\Modules\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

abstract class TenantJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public string $tenantId;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->onQueue($this->getQueue());
    }

    public function tags(): array
    {
        $tenant = \App\Modules\Tenancy\Models\Tenant::find($this->tenantId);

        return [
            'tenant:' . ($tenant?->slug ?? $this->tenantId),
            static::class,
        ];
    }

    protected function getQueue(): string
    {
        return 'default';
    }
}
```

Now every job shows up in Horizon tagged with the tenant slug.

### Start Horizon

```bash
php artisan horizon
```

Visit `http://localhost:8000/horizon` — you'll see the dashboard with queue metrics, recent jobs, and failed jobs.

!!! success "Horizon is running"
    - ✅ Dashboard accessible at `/horizon`
    - ✅ Separate queues for webhooks, notifications, registrations
    - ✅ Tenant tags on every job
    - ✅ Slack notifications for failed jobs

---

## Step 2: Install and Configure Telescope

Telescope is your **local and staging** debugging companion. It shows you every request, query, log entry, event, and job for the current session. Don't run it in production (it's too heavy), but for development it's invaluable.

```bash
composer require laravel/telescope --dev
php artisan telescope:install
```

Edit `config/telescope.php`:

```php
<?php

use Laravel\Telescope\Watchers;

return [
    'domain' => env('TELESCOPE_DOMAIN'),

    'path' => 'telescope',

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'chunk' => 1000,
        ],
    ],

    'prune' => [
        'hours' => env('TELESCOPE_PRUNE_HOURS', 24),
    ],

    'middleware' => [
        'web',
        \App\Modules\Tenancy\Middleware\EnsureSuperAdmin::class,
    ],

    'only_exceptions' => false,

    'ignored_paths' => [
        'nova-api*',
        'telescope*',
        'horizon*',
        '_debugbar*',
        'health',
    ],

    'watchers' => [
        Watchers\BatchWatcher::class => env('TELESCOPE_BATCH_WATCHER', true),
        Watchers\CacheWatcher::class => env('TELESCOPE_CACHE_WATCHER', true),
        Watchers\CommandWatcher::class => env('TELESCOPE_COMMAND_WATCHER', true),
        Watchers\DumpWatcher::class => env('TELESCOPE_DUMP_WATCHER', true),
        Watchers\EventWatcher::class => env('TELESCOPE_EVENT_WATCHER', true),
        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),
        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),
        Watchers\LogWatcher::class => env('TELESCOPE_LOG_WATCHER', true),
        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),
        Watchers\ModelWatcher::class => env('TELESCOPE_MODEL_WATCHER', true),
        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),
        Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'slow' => 100, // Log queries over 100ms
        ],
        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),
        Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => 64, // KB
        ],
        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
        Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', true),
    ],
];
```

!!! warning "Telescope is for local/staging only"
    Telescope watches *everything* — every query, every request, every log. In production, this adds significant overhead and storage. Keep it in `--dev` dependencies and use the environment gating:

    ```php
    // AppServiceProvider::register()
    if (app()->environment('local', 'staging')) {
        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
    }
    ```

### Pruning Telescope Data

Telescope accumulates data fast. Set up auto-pruning in `App\Console\Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('telescope:prune --hours=24')->daily();
}
```

---

## Step 3: Install and Configure Pulse

Pulse is Laravel's **application health dashboard** — response times, exceptions, slow queries, server resources. Unlike Telescope, it's designed for **production** — it aggregates data, not individual requests.

```bash
composer require laravel/pulse
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
```

Edit `config/pulse.php`:

```php
<?php

return [
    'enabled' => env('PULSE_ENABLED', true),

    'path' => 'pulse',

    'middleware' => ['web', 'auth', \App\Modules\Tenancy\Middleware\EnsureSuperAdmin::class],

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),
        'database' => [
            'connection' => env('DB_CONNECTION', 'pgsql'),
        ],
    ],

    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'storage'),
        'trim' => [
            'lottery' => [1, 1000],   // Keep 1 in 1000 entries for long-term
            'keep' => '7 days',       // Keep detailed data for 7 days
        ],
    ],

    'recorders' => [
        \Laravel\Pulse\Recorders\CacheInteractions::class => [
            'enabled' => env('PULSE_CACHE_INTERACTIONS', true),
            'sample' => env('PULSE_CACHE_SAMPLE', 1),
            'threshold' => env('PULSE_CACHE_THRESHOLD', 1000),
        ],
        \Laravel\Pulse\Recorders\Exceptions::class => [
            'enabled' => env('PULSE_EXCEPTIONS', true),
            'sample' => env('PULSE_EXCEPTION_SAMPLE', 1),
            'location' => env('PULSE_EXCEPTION_LOCATION', true),
        ],
        \Laravel\Pulse\Recorders\Queues::class => [
            'enabled' => env('PULSE_QUEUES', true),
            'sample' => env('PULSE_QUEUE_SAMPLE', 1),
        ],
        \Laravel\Pulse\Recorders\Servers::class => [
            'enabled' => env('PULSE_SERVER', true),
        ],
        \Laravel\Pulse\Recorders\SlowQueries::class => [
            'enabled' => env('PULSE_SLOW_QUERIES', true),
            'sample' => env('PULSE_SLOW_QUERY_SAMPLE', 1),
            'threshold' => env('PULSE_SLOW_QUERY_THRESHOLD', 500),
        ],
        \Laravel\Pulse\Recorders\SlowRequests::class => [
            'enabled' => env('PULSE_SLOW_REQUESTS', true),
            'sample' => env('PULSE_SLOW_REQUEST_SAMPLE', 1),
            'threshold' => env('PULSE_SLOW_REQUEST_THRESHOLD', 1000),
        ],
        \Laravel\Pulse\Recorders\SlowOutgoingRequests::class => [
            'enabled' => env('PULSE_SLOW_OUTGOING_REQUESTS', true),
            'sample' => env('PULSE_SLOW_OUTGOING_SAMPLE', 1),
            'threshold' => env('PULSE_SLOW_OUTGOING_THRESHOLD', 1000),
        ],
        \Laravel\Pulse\Recorders\UserJobs::class => [
            'enabled' => env('PULSE_USER_JOBS', true),
            'sample' => env('PULSE_USER_JOB_SAMPLE', 1),
        ],
        \Laravel\Pulse\Recorders\UserRequests::class => [
            'enabled' => env('PULSE_USER_REQUESTS', true),
            'sample' => env('PULSE_USER_REQUEST_SAMPLE', 1),
        ],
    ],
];
```

### Add Tenant Context to Pulse

Pulse records per-user metrics. In a multi-tenant app, we also want per-tenant metrics. Add a custom recorder:

Create `app/Modules/Tenancy/Pulse/TenantRecorder.php`:

```php
<?php

namespace App\Modules\Tenancy\Pulse;

use Laravel\Pulse\Recorders\Recorder;
use Laravel\Pulse\Pulse;

class TenantRecorder extends Recorder
{
    public function register(Pulse $pulse): void
    {
        $pulse->user(
            fn ($user) => [
                'name' => $user->name,
                'extra' => $user->tenant?->name,
                'avatar' => $user->avatar_url,
            ],
        );
    }
}
```

Then add it to `config/pulse.php`:

```php
\App\Modules\Tenancy\Pulse\TenantRecorder::class => [
    'enabled' => true,
],
```

Visit `http://localhost:8000/pulse` — you'll see the health dashboard with response times, slow queries, and exceptions.

---

## Step 4: Install and Configure Sentry

Sentry is your **production error tracker**. When something goes wrong in production, Sentry sends you an alert with the full stack trace, user context, tenant context, and breadcrumbs. It's the "check engine light that calls the mechanic."

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish
```

This creates `config/sentry.php`. Edit it with tenant context:

```php
<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('SENTRY_RELEASE', trim(file_get_contents(base_path('VERSION')) ?: 'dev')),

    'environment' => env('APP_ENV', 'production'),

    'sample_rate' => env('SENTRY_SAMPLE_RATE', 1.0),

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.2),

    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    'breadcrumbs' => [
        'logs' => env('SENTRY_BREADCRUMBS_LOGS', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE', true),
        'queue' => env('SENTRY_BREADCRUMBS_QUEUE', true),
    ],
];
```

### Add Tenant, User, and Request Context

Create `app/Providers/SentryServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sentry\Laravel\Facade as Sentry;
use Illuminate\Support\Str;

class SentryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('production')) {
            Sentry::configureScope(function (\Sentry\Scope $scope) {
                $tenant = app('currentTenant');

                if ($tenant) {
                    $scope->setTag('tenant.slug', $tenant->slug);
                    $scope->setTag('tenant.id', $tenant->id);
                    $scope->setContext('tenant', [
                        'id' => $tenant->id,
                        'slug' => $tenant->slug,
                        'name' => $tenant->name,
                    ]);
                }

                $user = auth()->user();
                if ($user) {
                    $scope->setUser([
                        'id' => $user->id,
                        'email' => $user->email,
                        'username' => $user->name,
                        'tenant_id' => $tenant?->id,
                    ]);
                }

                $requestId = Str::uuid()->toString();
                $scope->setTag('request_id', $requestId);
                $scope->setContext('request', [
                    'id' => $requestId,
                    'method' => request()->method(),
                    'url' => request()->fullUrl(),
                    'ip' => request()->ip(),
                ]);
            });
        }
    }
}
```

Register the provider in `bootstrap/providers.php`:

```php
return [
    // ... other providers
    App\Providers\SentryServiceProvider::class,
];
```

Now when an error hits Sentry, you'll see:

| Context | Example |
|---------|---------|
| Tenant | `ivy` (slug), `uuid-xxx` (id) |
| User | `jane@ivy.test`, role: `admin` |
| Request ID | `550e8400-e29b-41d4-a716-446655440000` |
| URL | `POST https://ivy.zendo.test/registrations` |

??? tip "Why request IDs?"
    When Nalanda reports a bug, they say "I tried to register at 3 PM and it didn't work." Without a request ID, you'd search logs by timestamp (noisy). With a request ID, you:

    1. Look up the Sentry error
    2. Find the request ID in the context
    3. Grep structured logs: `grep "request_id\":\"550e8400" storage/logs/structured.log`
    4. See every log line for that exact request — the SQL queries, the cache hits, the queue dispatches

    One ID traces the entire request lifecycle.

---

## Step 5: Set Up Structured Logging

By default, Laravel logs are single-line strings: `[2024-01-15 14:30:00] production.INFO: User logged in`. In a multi-tenant app with millions of log lines, you need **structured JSON logs** that you can search, filter, and aggregate.

### Create a Structured Log Channel

Edit `config/logging.php`:

```php
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'structured'],
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'structured' => [
            'driver' => 'custom',
            'via' => \App\Logging\StructuredLogger::class,
            'path' => storage_path('logs/structured.log'),
            'level' => 'debug',
            'days' => 30,
        ],

        'sentry' => [
            'driver' => 'sentry',
            'level' => 'warning',
        ],
    ],
];
```

Create `app/Logging/StructuredLogger.php`:

```php
<?php

namespace App\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class StructuredLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('structured');

        $handler = new RotatingFileHandler(
            $config['path'],
            $config['days'] ?? 30,
            $logger::toMonologLevel($config['level'] ?? 'debug')
        );

        $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());

        $logger->pushHandler($handler);
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new IntrospectionProcessor());
        $logger->pushProcessor([$this, 'addTenantContext']);
        $logger->pushProcessor([$this, 'addRequestIdContext']);

        return $logger;
    }

    public function addTenantContext(array $record): array
    {
        $tenant = app('currentTenant');

        $record['extra']['tenant_id'] = $tenant?->id;
        $record['extra']['tenant_slug'] = $tenant?->slug;

        return $record;
    }

    public function addRequestIdContext(array $record): array
    {
        $record['extra']['request_id'] = request()->header('X-Request-ID', Str::uuid()->toString());

        return $record;
    }
}
```

Now you can log with full context:

```php
use Illuminate\Support\Facades\Log;

Log::channel('structured')->info('Registration created', [
    'registration_id' => $registration->id,
    'event_id' => $registration->event_id,
    'tenant_id' => $registration->tenant_id,
    'user_id' => auth()->id(),
]);
```

This produces a JSON log line like:

```json
{
    "message": "Registration created",
    "context": {
        "registration_id": "550e8400-e29b-41d4-a716-446655440000",
        "event_id": "abc-123",
        "tenant_id": "ivy-uuid",
        "user_id": "user-uuid"
    },
    "extra": {
        "tenant_id": "ivy-uuid",
        "tenant_slug": "ivy",
        "request_id": "550e8400-e29b-41d4",
        "url": "/ivy/registrations",
        "ip": "192.168.1.1",
        "http_method": "POST"
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "structured",
    "datetime": "2024-01-15T14:30:00+00:00"
}
```

??? question "Why JSON logs instead of plain text?"
    Plain text logs are human-readable but machine-hostile. In production, you'll have millions of log lines. JSON lets you:

    - **Filter by tenant**: `jq 'select(.extra.tenant_slug == "ivy")' structured.log`
    - **Filter by request**: `jq 'select(.extra.request_id == "550e8400")' structured.log`
    - **Aggregate**: How many errors per tenant in the last hour?
    - **Feed into ELK/Datadog**: Every major log aggregator ingests JSON natively

    During development, `daily` (plain text) is fine. In production, `structured` (JSON) is essential.

---

## Step 6: Create the Health Endpoint

A `/health` endpoint is the pre-flight checklist. Load balancers, monitoring systems, and deployment scripts all ping this endpoint to know if the app is ready for traffic.

Create `app/Modules/Hub/Controllers/HealthController.php`:

```php
<?php

namespace App\Modules\Hub\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'meilisearch' => $this->checkMeilisearch(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->isQueryGrammarSupported();

            return ['status' => 'healthy', 'latency_ms' => $this->measureLatency(fn () => DB::select('SELECT 1'))];
        } catch (\Throwable $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $latency = $this->measureLatency(fn () => Redis::connection()->ping());

            return ['status' => 'healthy', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkMeilisearch(): array
    {
        try {
            $latency = $this->measureLatency(fn () => \Meilisearch\Client::new(config('scout.meilisearch.host'), config('scout.meilisearch.key'))->health());

            return ['status' => 'healthy', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Redis::connection()->l_len('queues:default');

            return [
                'status' => 'healthy',
                'queue_size' => $size,
                'warning' => $size > 100 ? 'Queue backlog detected' : null,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function measureLatency(callable $callback): float
    {
        $start = microtime(true);
        $callback();

        return round((microtime(true) - $start) * 1000, 2);
    }
}
```

Add the route in `routes/web.php`:

```php
Route::get('/health', \App\Modules\Hub\Controllers\HealthController::class);
```

!!! warning "Health endpoint must be publicly accessible without auth"
    Load balancers need to hit this endpoint without authentication. Don't put it behind the auth middleware. But **do** exclude it from rate limiting and telemetry tracking.

### Health Endpoint Response

A healthy response:

```json
{
    "status": "healthy",
    "timestamp": "2024-01-15T14:30:00+00:00",
    "checks": {
        "database": { "status": "healthy", "latency_ms": 2.34 },
        "redis": { "status": "healthy", "latency_ms": 0.45 },
        "meilisearch": { "status": "healthy", "latency_ms": 5.12 },
        "queue": { "status": "healthy", "queue_size": 3 }
    }
}
```

An unhealthy response (database down):

```json
{
    "status": "unhealthy",
    "timestamp": "2024-01-15T14:30:00+00:00",
    "checks": {
        "database": { "status": "unhealthy", "error": "SQLSTATE[08006]: Connection refused" },
        "redis": { "status": "healthy", "latency_ms": 0.51 },
        "meilisearch": { "status": "healthy", "latency_ms": 4.88 },
        "queue": { "status": "unhealthy", "error": "Redis connection failed" }
    }
}
```

---

## Step 7: Restrict Dashboard Access

All observability dashboards (Horizon, Telescope, Pulse) must be restricted to super admins only. Regular tenant admins should never see other tenants' data in Telescope, for example.

Create `app/Modules/Tenancy/Middleware/EnsureSuperAdmin.php`:

```php
<?php

namespace App\Modules\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->is_super_admin) {
            abort(403, 'Access denied. Super admins only.');
        }

        return $next($request);
    }
}
```

??? tip "Why super admin and not tenant admin?"
    Telescope shows *all* requests across all tenants. If a tenant admin at Ivy could access Telescope, they'd see Nalanda's database queries, cache keys, and event dispatches. That's a data leak within the observability tools themselves.

    Only super admins (your platform team) should access these dashboards. For tenant-level monitoring, build a custom dashboard inside the tenant's Filament admin panel.

### Environment Gating

Add to `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    // Only enable Telescope in local/staging
    if (app()->environment('local', 'staging')) {
        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
    }

    // Only enable Horizon in non-testing environments
    if (!app()->environment('testing')) {
        $this->app->register(\Laravel\Horizon\HorizonServiceProvider::class);
    }
}
```

---

## Step 8: Wire Up the Observability Stack

Add a middleware that adds request IDs to every request. This ties together logs, Sentry, and telemetry.

Create `app/Modules/Tenancy/Middleware/AssignRequestId.php`:

```php
<?php

namespace App\Modules\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssignRequestId
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = $request->header('X-Request-ID', Str::uuid()->toString());

        $response = $next($request);

        $response->header('X-Request-ID', $requestId);

        return $response;
    }
}
```

Add it to global middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append([
        \App\Modules\Tenancy\Middleware\AssignRequestId::class,
    ]);
})
```

Now every response includes an `X-Request-ID` header. If a tenant reports an issue, they can give you this ID and you can trace it through logs, Sentry, and Telescope.

### Summary of Endpoints

| Tool | URL | Who Can Access | Purpose |
|------|-----|---------------|---------|
| Horizon | `/horizon` | Super admins | Queue monitoring |
| Telescope | `/telescope` | Super admins (local/staging) | Request debugging |
| Pulse | `/pulse` | Super admins | Application health |
| Sentry | Dashboard (external) | Platform team | Production error tracking |
| Health | `/health` | Public (no auth) | Service status checks |

```mermaid
graph LR
    subgraph "Prod Monitoring"
        S[Sentry<br/>Errors + Context] --> SLACK[Slack Alerts]
        H[/health Endpoint] --> LB[Load Balancer]
        P[Pulse Dashboard] --> OPS[Ops Team]
    end

    subgraph "Dev/Staging Monitoring"
        T[Telescope<br/>Requests + Queries] --> DEV[Developer]
        HZ[Horizon<br/>Queue Dashboard] --> DEV
    end

    subgraph "Cross-Cutting"
        RID[Request ID<br/>X-Request-ID] --> S
        RID --> T
        RID --> LOG[Structured JSON Logs]
        TC[Tenant Context] --> S
        TC --> LOG
    end
```

!!! success "Checkpoint"
    At this point you should have:

    - ✅ Horizon running at `/horizon` with tenant-tagged jobs
    - ✅ Telescope running at `/telescope` (local/staging only)
    - ✅ Pulse running at `/pulse` with tenant context
    - ✅ Sentry configured with tenant slug, user ID, request ID
    - ✅ Structured JSON logging with `Log::channel('structured')`
    - ✅ Health endpoint at `/health` checking DB, Redis, Meilisearch, queue
    - ✅ Request ID middleware adding `X-Request-ID` header
    - ✅ All dashboards restricted to super admins
    - ✅ Environment gating for Telescope and Horizon

---

## What's Next

In [Section 13: Hardening — RLS & Security](section-13-hardening.md), we'll add PostgreSQL Row-Level Security as a database-level safety net, rate limiting, CSRF protection, and API versioning.

We'll cover:

- **PostgreSQL RLS** — the database itself rejects cross-tenant queries
- **RLS migration** — enabling RLS on all tenant-scoped tables
- **Testing RLS** — the test suite we wrote in Section 11 now passes
- **Rate limiting** — login, API, registration, and webhook throttling
- **CSRF and security headers** — protecting Inertia forms and API endpoints
- **API versioning** — `/api/v1/` routes with versioned controllers