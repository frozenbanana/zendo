<?php

namespace App\Modules\Hub\Controllers;

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
            ->select(['id', 'slug', 'name', 'description', 'logo', 'features'])
            ->orderBy('name')
            ->get();

        $featuredEvents = Event::with(['tenant', 'teachers'])
            ->where('status', 'published')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        return Inertia::render('Hub/Home', [
            'centers' => $centers,
            'featuredEvents' => $featuredEvents,
        ]);
    }

    public function centers()
    {
        $centers = Tenant::where('is_active', true)
            ->select(['id', 'slug', 'name', 'description', 'logo', 'features', 'timezone', 'locale'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Hub/CenterList', [
            'centers' => $centers,
        ]);
    }

    public function events(Request $request)
    {
        $query = Event::with(['tenant', 'teachers'])
            ->where('status', 'published')
            ->where('starts_at', '>', now());

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($center = $request->input('center')) {
            $query->whereHas('tenant', function ($q) use ($center) {
                $q->where('slug', $center);
            });
        }

        $events = $query->orderBy('starts_at')->paginate(12);

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
        $event = Event::with(['tenant', 'teachers', 'eventInstances' => function ($q) {
            $q->where('starts_at', '>', now())->orderBy('starts_at');
        }])->where('status', 'published')
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
