# 10. Search with Meilisearch

> **Milestone:** Searching "meditation" on the hub returns events across all centers, with results scoped to the current tenant in admin.

## Prerequisites

- [Section 9: Payments](section-09-payments.md) completed
- Meilisearch running in Docker (`docker compose up -d` includes Meilisearch)

## What You'll Learn

| Concept | What it is | Why it matters |
|---------|-----------|----------------|
| Scout | Laravel's search abstraction | Switch search engines without changing code |
| Meilisearch | Fast, typo-tolerant search engine | Great UX out of the box, no ElasticSearch complexity |
| Tenant-aware indexing | Index per-tenant, search per-context | Hub search is cross-tenant; admin search is scoped |
| Async indexing | Search updates via queue | Don't slow down the HTTP response for search indexing |
| Searchable trait | Scout's mixin that auto-indexes models | Adding search is one trait and one method |

## The Big Picture

Scout is like a librarian who knows exactly which shelves to check. Meilisearch is the library catalog — it's pre-indexed, so you don't have to walk through every aisle. And tenant-aware indexing is like having a public catalog (anyone can browse all books) and a staff catalog (librarians only see books in their branch).

```mermaid
flowchart TD
    subgraph "Hub — Cross-Tenant Search"
        HUB[Hub Search Bar] --> MSC[Meilisearch<br/>hub_events index]
        MSC --> R1[Ivy: Meditation Retreat]
        MSC --> R2[Nalanda: Yoga Teacher Training]
        MSC --> R3[Bodhi Tree: Silent Retreat]
    end

    subgraph "Admin — Tenant-Scoped Search"
        ADM[Admin Search] --> MST[Meilisearch<br/>tenant_{id}_events index]
        MST --> R4[Ivy events only]
    end

    subgraph "Indexing Pipeline"
        E[Event saved] -->|SCOUT_QUEUE=true| Q[Redis Queue]
        Q --> J[Scout job]
        J -->|hub_events| MSC
        J -->|tenant_{id}_events| MST
    end
```

---

## Step 1: Install and Configure Scout with Meilisearch

```bash
composer require laravel/scout
```

Scout is already in `composer.json` from [Section 1](section-01-get-running.md), but if you skipped that step, the command above installs it.

Publish the Scout config:

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

Start Meilisearch locally:

```bash
docker run -d --name meilisearch -p 7700:7700 getmeili/meilisearch:latest
```

Add to `.env`:

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=masterKey

# Queue-based indexing — critical for production
SCOUT_QUEUE=true
```

Edit `config/scout.php`:

```php
'driver' => env('SCOUT_DRIVER', 'meilisearch'),

'meilisearch' => [
    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'key' => env('MEILISEARCH_KEY'),
],

'queue' => [
    'connection' => 'redis',
    'queue' => 'scout',
],
```

!!! warning "SCOUT_QUEUE=true is essential"
    Without `SCOUT_QUEUE=true`, every model save blocks the HTTP response while Meilisearch indexes. With 50 concurrent users saving events, each one waits for a network round-trip to Meilisearch. With queuing enabled, the save returns immediately, and a background job handles indexing. The search index might lag by a few seconds, but the user experience stays fast.

## Step 2: Make Events Searchable

Scout's `Searchable` trait makes any model searchable. Add it to the models we want to search.

Edit `app/Modules/Events/Models/Event.php`:

```php
<?php

namespace App\Modules\Events\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Event extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'slug',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(EventInstance::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant->name,
            'tenant_slug' => $this->tenant->slug,
            'is_published' => $this->is_published,
            'instance_dates' => $this->instances->map(fn ($i) => [
                'starts_at' => $i->starts_at?->toIso8601String(),
                'ends_at' => $i->ends_at?->toIso8601String(),
            ])->toArray(),
        ];
    }
}
```

??? question "What does toSearchableArray() do?"
    This method tells Scout exactly what data to send to Meilisearch. Without it, Scout would index every column — including IDs, timestamps, and internal fields that don't help search. We curate the array to include only what users search by (title, description) and what we filter by (tenant_id, is_published).

Edit `app/Modules/Events/Models/EventInstance.php`:

```php
<?php

namespace App\Modules\Events\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class EventInstance extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'starts_at',
        'ends_at',
        'capacity',
        'price_cents',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price_cents' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'event_title' => $this->event->title,
            'event_description' => $this->event->description,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant->name,
            'tenant_slug' => $this->tenant->slug,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'price_cents' => $this->price_cents,
            'is_published' => $this->event->is_published,
        ];
    }
}
```

## Step 3: Make Teachers and Tenants Searchable

We also want to search by teacher name and by center. These models support the hub search experience.

Edit `app/Modules/People/Models/Teacher.php`:

```php
<?php

namespace App\Modules\People\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Teacher extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'tenant_id',
        'name',
        'bio',
        'photo',
        'specialties',
    ];

    protected $casts = [
        'specialties' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'bio' => $this->bio,
            'specialties' => $this->specialties,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant->name,
        ];
    }
}
```

Edit `app/Modules/Tenancy/Models/Tenant.php`, adding the `Searchable` trait:

```php
<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Scout\Searchable;

class Tenant extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'logo',
        'custom_domain',
        'features',
        'registration_mode',
        'currency',
        'timezone',
        'locale',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
        ];
    }
}
```

## Step 4: Configure Tenant-Aware Indexing

Here's the key architecture decision: hub search is **cross-tenant** (search all published events across all centers), while admin search is **tenant-scoped** (search only within the current center).

We achieve this with two different search indexes:

| Index | Scope | Who uses it | What's in it |
|-------|-------|-------|-------------|
| `hub_events` | All tenants | Public hub visitors | Only published events |
| `tenant_{id}_events` | Single tenant | Admin panel | All events (published + draft) |

Add the index configuration to each searchable model. Edit `app/Modules/Events/Models/Event.php`:

```php
public function searchableAs(): string
{
    if ($this->is_published) {
        return 'hub_events';
    }
    return 'tenant_' . $this->tenant_id . '_events';
}
```

Wait — we actually need the event in **both** indexes when published. Let's use Scout's `shouldBeSearchable()` method instead:

```php
public function searchableAs(): string
{
    return 'hub_events';
}

public function shouldBeSearchable(): bool
{
    return $this->is_published;
}
```

??? question "How does admin search work then?"
    Good question. The hub index contains published events from all tenants. Admin search uses a **different approach** — it queries Meilisearch with a `tenant_id` filter on the hub index, or uses a separate tenant-scoped method (see Step 7). 

    We use one index (`hub_events`) for simplicity, and filter by `tenant_id` at query time. Meilisearch's filtering is fast enough that we don't need separate indexes per tenant — it can filter millions of documents in milliseconds. Separate indexes would mean N times the syncing overhead.

## Step 5: Define Meilisearch Index Settings

Meilisearch needs to know which fields are searchable, which are filterable, and which are sortable. We configure this in a Scout command.

Create `app/Console/Commands/ConfigureMeilisearch.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client;

class ConfigureMeilisearch extends Command
{
    protected $signature = 'meilisearch:configure';
    protected $description = 'Configure Meilisearch index settings';

    public function handle(): int
    {
        $client = new Client(
            config('scout.meilisearch.host'),
            config('scout.meilisearch.key')
        );

        $hubEventsSettings = [
            'searchableAttributes' => [
                'title',
                'description',
                'tenant_name',
                'tenant_slug',
            ],
            'filterableAttributes' => [
                'tenant_id',
                'is_published',
                'tenant_slug',
            ],
            'sortableAttributes' => [
                'price_cents',
            ],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => [
                    'oneTypo' => 4,
                    'twoTypos' => 8,
                ],
            ],
        ];

        $client->index('hub_events')->updateSettings($hubEventsSettings);

        $this->info('Configured hub_events index');

        $this->info('Meilisearch configuration complete.');
        return Command::SUCCESS;
    }
}
```

Run the configuration:

```bash
php artisan meilisearch:configure
```

## Step 6: Import Existing Data

If you have existing data, import it into Meilisearch:

```bash
php artisan scout:import "App\Modules\Events\Models\Event"
php artisan scout:import "App\Modules\Events\Models\EventInstance"
php artisan scout:import "App\Modules\People\Models\Teacher"
php artisan scout:import "App\Modules\Tenancy\Models\Tenant"
```

From now on, any model that uses `Searchable` automatically stays in sync — when you save, update, or delete a record, Scout queues a job to update the search index.

??? tip "Why import and not sync?"
    `scout:import` does a full bulk import of all existing records. It's the equivalent of restocking the entire library catalog at once. After that, each individual change (a new book arriving, a book being removed) is handled automatically by the `Searchable` trait — like a librarian updating the catalog card whenever a book changes shelves.

## Step 7: Build the Hub Search Endpoint

The hub search is cross-tenant and public. Anyone can search for events across all centers.

Create `app/Modules/Hub/Controllers/SearchController.php`:

```php
<?php

namespace App\Modules\Hub\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\People\Models\Teacher;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController
{
    public function __invoke(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return Inertia::render('hub/Search', [
                'query' => $query,
                'events' => [],
                'centers' => [],
                'teachers' => [],
            ]);
        }

        $events = Event::search($query)
            ->where('is_published', true)
            ->get();

        $centers = Tenant::search($query)
            ->where('is_active', true)
            ->get();

        $teachers = Teacher::search($query)->get();

        return Inertia::render('hub/Search', [
            'query' => $query,
            'events' => $events->load(['tenant', 'instances']),
            'centers' => $centers,
            'teachers' => $teachers->load('tenant'),
        ]);
    }
}
```

Add the route. Edit `routes/web.php`:

```php
Route::get('/search', [SearchController::class, '__invoke'])->name('hub.search');
```

??? question "Where does Tenant scoping happen for admin?"
    In the hub, there's no tenant scope — we search everything. In the Filament admin panel (Section 5), the tenant is already resolved by Filament's multi-tenancy. For custom admin search, you'd filter:

    ```php
    Event::search($query)
        ->where('tenant_id', $tenant->id)
        ->get();
    ```

    This is tenant-aware indexing: same index, filtered query. No need for separate indexes.

## Step 8: Build the Hub Search UI

Create the search page with results for events, centers, and teachers:

```bash
mkdir -p resources/js/pages/hub
```

Create `resources/js/pages/hub/Search.tsx`:

```tsx
import { Head, Link, useForm } from '@inertiajs/react';

interface Event {
  id: string;
  title: string;
  description: string;
  tenant_name: string;
  tenant_slug: string;
  instance_dates: { starts_at: string; ends_at: string }[];
}

interface Center {
  id: string;
  name: string;
  description: string;
  slug: string;
}

interface Teacher {
  id: string;
  name: string;
  bio: string;
  tenant_name: string;
  specialties: string[];
}

interface Props {
  query: string;
  events: Event[];
  centers: Center[];
  teachers: Teacher[];
}

export default function Search({ query: initialQuery, events, centers, teachers }: Props) {
  const { data, setData, get } = useForm({ q: initialQuery });

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    get(route('hub.search'), { preserveState: true });
  };

  return (
    <>
      <Head title={`Search: ${initialQuery || 'Zendo Hub'}`} />
      <div className="max-w-4xl mx-auto py-8 px-4">
        <h1 className="text-3xl font-bold mb-6">Find Your Retreat</h1>

        <form onSubmit={handleSearch} className="mb-8">
          <div className="flex gap-2">
            <input
              type="text"
              value={data.q}
              onChange={(e) => setData('q', e.target.value)}
              placeholder="Search events, centers, teachers..."
              className="flex-1 rounded-lg border-gray-300 shadow-sm px-4 py-3 text-lg"
              autoFocus
            />
            <button
              type="submit"
              className="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700"
            >
              Search
            </button>
          </div>
        </form>

        {initialQuery && (
          <div className="space-y-8">
            {events.length > 0 && (
              <section>
                <h2 className="text-xl font-semibold mb-4">Events ({events.length})</h2>
                <div className="grid gap-4">
                  {events.map((event) => (
                    <Link
                      key={event.id}
                      href={`/${event.tenant_slug}/events/${event.id}`}
                      className="block p-4 border rounded-lg hover:border-indigo-300 transition-colors"
                    >
                      <h3 className="font-medium text-lg">{event.title}</h3>
                      <p className="text-gray-600 text-sm mt-1">{event.description}</p>
                      <div className="mt-2 flex items-center gap-2">
                        <span className="text-sm text-indigo-600 font-medium">{event.tenant_name}</span>
                        {event.instance_dates?.[0] && (
                          <span className="text-sm text-gray-500">
                            {new Date(event.instance_dates[0].starts_at).toLocaleDateString()}
                          </span>
                        )}
                      </div>
                    </Link>
                  ))}
                </div>
              </section>
            )}

            {centers.length > 0 && (
              <section>
                <h2 className="text-xl font-semibold mb-4">Centers ({centers.length})</h2>
                <div className="grid gap-4">
                  {centers.map((center) => (
                    <Link
                      key={center.id}
                      href={`/${center.slug}`}
                      className="block p-4 border rounded-lg hover:border-indigo-300 transition-colors"
                    >
                      <h3 className="font-medium text-lg">{center.name}</h3>
                      <p className="text-gray-600 text-sm mt-1">{center.description}</p>
                    </Link>
                  ))}
                </div>
              </section>
            )}

            {teachers.length > 0 && (
              <section>
                <h2 className="text-xl font-semibold mb-4">Teachers ({teachers.length})</h2>
                <div className="grid gap-4">
                  {teachers.map((teacher) => (
                    <div key={teacher.id} className="p-4 border rounded-lg">
                      <h3 className="font-medium text-lg">{teacher.name}</h3>
                      <p className="text-gray-600 text-sm mt-1">{teacher.bio}</p>
                      <div className="mt-2 flex gap-2">
                        {teacher.specialties?.map((s) => (
                          <span key={s} className="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-xs">
                            {s}
                          </span>
                        ))}
                      </div>
                      <span className="text-sm text-gray-500 mt-2 block">at {teacher.tenant_name}</span>
                    </div>
                  ))}
                </div>
              </section>
            )}

            {events.length === 0 && centers.length === 0 && teachers.length === 0 && (
              <div className="text-center py-12 text-gray-500">
                <p className="text-lg">No results found for "{initialQuery}"</p>
                <p className="text-sm mt-2">Try a different search term</p>
              </div>
            )}
          </div>
        )}
      </div>
    </>
  );
}
```

## Step 9: Add Search to the Hub Navigation Bar

Update the hub layout to include the search bar. Edit `resources/js/layouts/HubLayout.tsx` (or create one if it doesn't exist):

```tsx
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function HubLayout({ children, centers }) {
  const [searchQuery, setSearchQuery] = useState('');

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      window.location.href = `/search?q=${encodeURIComponent(searchQuery)}`;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
          <Link href="/hub" className="text-xl font-bold text-indigo-600">
            Zendo
          </Link>

          <form onSubmit={handleSearch} className="flex-1 max-w-md mx-4">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search retreats, centers, teachers..."
              className="w-full rounded-lg border-gray-300 shadow-sm px-4 py-2 text-sm"
            />
          </form>

          <div className="flex items-center gap-4">
            <Link href="/hub" className="text-sm text-gray-600 hover:text-indigo-600">
              Centers
            </Link>
          </div>
        </div>
      </nav>

      <main>{children}</main>
    </div>
  );
}
```

## Step 10: Test Search

```bash
php artisan tinker
```

```php
>>> use App\Modules\Events\Models\Event;
>>> use App\Modules\Tenancy\Models\Tenant;

>>> // Create events to search across centers
>>> $ivy = Tenant::where('slug', 'ivy')->first();
>>> $nalanda = Tenant::where('slug', 'nalanda')->first();

>>> Event::create(['tenant_id' => $ivy->id, 'title' => 'Mindful Meditation Retreat', 'description' => 'A week of silent meditation in the mountains', 'slug' => 'mindful-meditation', 'is_published' => true]);
>>> Event::create(['tenant_id' => $ivy->id, 'title' => 'Yoga and Breathwork', 'description' => 'Daily yoga with pranayama sessions', 'slug' => 'yoga-breathwork', 'is_published' => true]);
>>> Event::create(['tenant_id' => $nalanda->id, 'title' => 'Vipassana Meditation Course', 'description' => '10-day silent meditation retreat', 'slug' => 'vipassana-meditation', 'is_published' => true]);
>>> Event::create(['tenant_id' => $nalanda->id, 'title' => 'Yoga Teacher Training', 'description' => '200-hour certified yoga teacher training', 'slug' => 'yoga-teacher-training', 'is_published' => true]);

>>> // Import into Meilisearch
>>> Artisan::call('scout:import', ['model' => Event::class]);

>>> // Search for "meditation" — should return events across centers
>>> Event::search('meditation')->where('is_published', true)->get();
// => [Mindful Meditation Retreat (Ivy), Vipassana Meditation Course (Nalanda)]

>>> // Search for "yoga" — should return events across centers
>>> Event::search('yoga')->where('is_published', true)->get();
// => [Yoga and Breathwork (Ivy), Yoga Teacher Training (Nalanda)]

>>> // Typo tolerance — "meditaton" (missing 'i') should still find "meditation"
>>> Event::search('meditaton')->where('is_published', true)->get();
// => [Mindful Meditation Retreat (Ivy), Vipassana Meditation Course (Nalanda)]
```

??? tip "Meilisearch's typo tolerance is what makes it special"
    Notice that searching "meditaton" (with a typo — missing the second 'i') still returns the meditation events. Meilisearch's typo tolerance is configurable: it starts tolerating typos after 4 characters (one typo) and 8 characters (two typos). This is why we configured `minWordSizeForTypos` in the index settings. ElasticSearch can do this too, but it requires complex configuration. Meilisearch gets it right out of the box.

## Step 11: Add a Search Integration Test

Create a test to verify that search works correctly across tenants:

```bash
php artisan make:test SearchTest
```

Edit `tests/Feature/SearchTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Modules\Events\Models\Event;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $ivy;
    private Tenant $nalanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ivy = Tenant::create([
            'slug' => 'ivy',
            'name' => 'Ivy Retreat Center',
            'is_active' => true,
            'features' => ['meals' => true, 'lodging' => true],
        ]);

        $this->nalanda = Tenant::create([
            'slug' => 'nalanda',
            'name' => 'Nalanda Center',
            'is_active' => true,
            'features' => ['meals' => false, 'lodging' => true],
        ]);
    }

    public function test_hub_search_returns_events_across_tenants(): void
    {
        Event::create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Mindful Meditation Retreat',
            'description' => 'Silent meditation in the mountains',
            'slug' => 'mindful-meditation',
            'is_published' => true,
        ]);

        Event::create([
            'tenant_id' => $this->nalanda->id,
            'title' => 'Vipassana Meditation Course',
            'description' => '10-day silent retreat',
            'slug' => 'vipassana-meditation',
            'is_published' => true,
        ]);

        $results = Event::search('meditation')
            ->where('is_published', true)
            ->get();

        $this->assertCount(2, $results);
    }

    public function test_hub_search_excludes_unpublished_events(): void
    {
        Event::create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Secret Retreat',
            'description' => 'A yoga retreat',
            'slug' => 'secret-retreat',
            'is_published' => false,
        ]);

        Event::create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Public Yoga Retreat',
            'description' => 'An open yoga retreat',
            'slug' => 'public-yoga',
            'is_published' => true,
        ]);

        $results = Event::search('yoga')
            ->where('is_published', true)
            ->get();

        $this->assertEquals(1, $results->count());
        $this->assertEquals('Public Yoga Retreat', $results->first()->title);
    }

    public function test_admin_search_scopes_to_tenant(): void
    {
        Event::create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Ivy Yoga Class',
            'description' => 'Yoga at Ivy',
            'slug' => 'ivy-yoga',
            'is_published' => true,
        ]);

        Event::create([
            'tenant_id' => $this->nalanda->id,
            'title' => 'Nalanda Yoga Class',
            'description' => 'Yoga at Nalanda',
            'slug' => 'nalanda-yoga',
            'is_published' => true,
        ]);

        $ivyResults = Event::search('yoga')
            ->where('tenant_id', $this->ivy->id)
            ->get();

        $this->assertEquals(1, $ivyResults->count());
        $this->assertEquals('Ivy Yoga Class', $ivyResults->first()->title);
    }
}
```

Run the tests (Meilisearch must be running):

```bash
php artisan test --filter=SearchTest
```

??? warning "Meilisearch must be running for search tests"
    Scout's `search()` method calls Meilisearch directly. If Meilisearch isn't running, tests that use `Event::search()` will fail with a connection error. For CI, you can either:
    
    1. Run Meilisearch as a service in your CI pipeline
    2. Use the `collection` Scout driver for tests that don't need real search, and the `meilisearch` driver for integration tests
    
    Set `SCOUT_DRIVER=collection` in `phpunit.xml` for unit tests, and override it in your CI integration test suite.

!!! success "Checkpoint"
    At this point you should have:
    
    - ✅ Scout configured with Meilisearch as the driver
    - ✅ `SCOUT_QUEUE=true` for async indexing
    - ✅ Event, EventInstance, Teacher, and Tenant models with `Searchable` trait
    - ✅ Meilisearch index settings configured (searchable, filterable, sortable attributes)
    - ✅ Hub search endpoint returning cross-tenant results
    - ✅ Admin search filtering by `tenant_id`
    - ✅ Typo-tolerant search working ("meditaton" finds "meditation")
    - ✅ Unpublished events excluded from hub search
    - ✅ Search UI with events, centers, and teachers sections

---

## What's Next

In [Section 11: Testing with Pest](section-11-testing.md), we'll build a comprehensive test suite using Pest's elegant syntax, covering unit tests for the RegistrationService, feature tests for the wizard flow, and integration tests for the payment pipeline.

We'll cover:

- **Pest** — Laravel testing framework with a clean, expressive syntax
- **Unit tests** — RegistrationService, InvoiceService in isolation
- **Feature tests** — wizard submission, payment flow, webhook handling
- **Integration tests** — Stripe webhook idempotency, search across tenants