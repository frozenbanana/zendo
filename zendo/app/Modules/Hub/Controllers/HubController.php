<?php

namespace App\Modules\Hub\Controllers;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\Teacher;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HubController
{
    public function home()
    {
        $centers = Tenant::where('is_active', true)
            ->select(['id', 'slug', 'name', 'description', 'logo', 'features', 'currency'])
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'description' => $c->description,
                'logo' => $c->logo,
                'features' => $c->featureFlags()->toArray(),
                'currency' => $c->currency,
            ]);

        $featuredEvents = Event::with(['tenant' => fn ($q) => $q->select('id', 'slug', 'name', 'currency')])
            ->where('status', EventStatus::Published)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(6)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'slug' => $e->slug,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'price_cents' => $e->price_cents,
                'currency' => $e->tenant->currency,
                'tenant' => [
                    'slug' => $e->tenant->slug,
                    'name' => $e->tenant->name,
                ],
                'teachers' => $e->teachers->map(fn ($t) => ['name' => $t->name])->toArray(),
            ]);

        return Inertia::render('Hub/Home', [
            'centers' => $centers,
            'featuredEvents' => $featuredEvents,
        ]);
    }

    public function centers()
    {
        $centers = Tenant::where('is_active', true)
            ->select(['id', 'slug', 'name', 'description', 'logo', 'features', 'currency', 'timezone', 'locale'])
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'description' => $c->description,
                'logo' => $c->logo,
                'features' => $c->featureFlags()->toArray(),
                'currency' => $c->currency,
                'timezone' => $c->timezone,
                'locale' => $c->locale,
            ]);

        return Inertia::render('Hub/CenterList', [
            'centers' => $centers,
        ]);
    }

    public function events(Request $request)
    {
        $query = Event::with(['tenant' => fn ($q) => $q->select('id', 'slug', 'name', 'currency'), 'teachers'])
            ->where('status', EventStatus::Published)
            ->where('starts_at', '>', now());

        if ($search = $request->input('search')) {
            $lowerSearch = strtolower($search);
            $query->where(function ($q) use ($lowerSearch) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$lowerSearch}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lowerSearch}%"]);
            });
        }

        if ($center = $request->input('center')) {
            $query->whereHas('tenant', function ($q) use ($center) {
                $q->where('slug', $center);
            });
        }

        $events = $query->orderBy('starts_at')->paginate(12);

        $events->through(fn ($event) => [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'price_cents' => $event->price_cents,
            'currency' => $event->tenant->currency,
            'capacity' => $event->capacity,
            'tenant' => [
                'slug' => $event->tenant->slug,
                'name' => $event->tenant->name,
            ],
            'teachers' => $event->teachers->map(fn ($t) => ['name' => $t->name])->toArray(),
        ]);

        $centers = Tenant::where('is_active', true)
            ->select(['slug', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Hub/Events', [
            'events' => $events,
            'centers' => $centers,
            'filters' => [
                'search' => $request->input('search', ''),
                'center' => $request->input('center', ''),
            ],
        ]);
    }

    public function eventDetail(string $id)
    {
        $event = Event::with(['tenant' => fn ($q) => $q->select('id', 'slug', 'name', 'currency'), 'teachers', 'eventInstances' => function ($q) {
            $q->where('starts_at', '>', now())->orderBy('starts_at');
        }])->where('status', EventStatus::Published)
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('Hub/EventDetail', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'status' => $event->status,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'capacity' => $event->capacity,
                'price_cents' => $event->price_cents,
                'currency' => $event->tenant->currency,
                'tenant' => [
                    'slug' => $event->tenant->slug,
                    'name' => $event->tenant->name,
                ],
                'teachers' => $event->teachers->map(fn ($t) => [
                    'name' => $t->name,
                    'bio' => $t->bio,
                ]),
                'instances' => $event->eventInstances->map(fn ($i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'starts_at' => $i->starts_at->toIso8601String(),
                    'ends_at' => $i->ends_at->toIso8601String(),
                    'capacity' => $i->capacity,
                    'price_cents' => $i->price_override_cents,
                    'currency' => $event->tenant->currency,
                ]),
            ],
        ]);
    }

    public function teachers()
    {
        $teachers = Teacher::orderBy('name')
            ->paginate(20);

        return Inertia::render('Hub/Teachers', [
            'teachers' => $teachers,
        ]);
    }
}
