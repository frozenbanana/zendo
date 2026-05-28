import { Head, Link, router } from '@inertiajs/react';
import { events as eventsRoute, eventDetail } from '@/actions/App/Modules/Hub/Controllers/HubController';
import { formatCurrency } from '@/lib/utils';

interface Props {
    events: { data: { id: string; title: string; description: string; starts_at: string; ends_at: string; price_cents: number | null; currency: string; capacity: number | null; tenant: { slug: string; name: string }; teachers: { name: string }[] }[]; current_page: number; last_page: number };
    centers: { slug: string; name: string }[];
    filters: { search: string; center: string };
}

export default function HubEvents({ events, centers, filters }: Props) {
    const handleSearch = (search: string) => {
        router.get(eventsRoute.url(), { search, center: filters.center }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleCenterFilter = (center: string) => {
        router.get(eventsRoute.url(), { search: filters.search, center }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Events" />

            <div className="max-w-6xl mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold tracking-tight mb-2">
                    Find Your Retreat
                </h1>
                <p className="text-muted-foreground mb-8">
                    Browse events from retreat centers around the world.
                </p>

                <div className="flex gap-4 mb-8 flex-wrap">
                    <input
                        type="text"
                        placeholder="Search events..."
                        defaultValue={filters.search}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="max-w-sm flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                    <div className="flex gap-2 flex-wrap">
                        <button
                            className={`px-3 py-1 text-sm rounded-md ${filters.center === '' ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'}`}
                            onClick={() => handleCenterFilter('')}
                        >
                            All Centers
                        </button>
                        {centers.map((center) => (
                            <button
                                key={center.slug}
                                className={`px-3 py-1 text-sm rounded-md ${filters.center === center.slug ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'}`}
                                onClick={() => handleCenterFilter(center.slug)}
                            >
                                {center.name}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {events.data.map((event) => (
                        <div key={event.id} className="border rounded-lg p-6 hover:shadow-lg transition-shadow">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded">
                                    {event.tenant.name}
                                </span>
                                {event.capacity && (
                                    <span className="text-xs text-gray-500">
                                        {event.capacity} spots
                                    </span>
                                )}
                            </div>
                            <h3 className="text-lg font-semibold">
                                <Link href={eventDetail.url({ id: event.id })}>
                                    {event.title}
                                </Link>
                            </h3>
                            {event.description && (
                                <p className="text-sm text-gray-500 mt-1 line-clamp-2">
                                    {event.description}
                                </p>
                            )}
                            <div className="mt-4 flex items-center justify-between">
                                <span className="text-sm">
                                    {new Date(event.starts_at).toLocaleDateString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                    })}
                                </span>
                                {event.price_cents != null && (
                                    <span className="font-semibold">
                                        {formatCurrency(event.price_cents, event.currency)}
                                    </span>
                                )}
                            </div>
                            {event.teachers.length > 0 && (
                                <div className="mt-2 flex gap-1 flex-wrap">
                                    {event.teachers.map((teacher) => (
                                        <span key={teacher.name} className="text-xs border border-gray-300 px-2 py-0.5 rounded">
                                            {teacher.name}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                {events.last_page > 1 && (
                    <div className="mt-8 flex justify-center gap-2">
                        {Array.from({ length: events.last_page }, (_, i) => i + 1).map((page) => (
                            <button
                                key={page}
                                className={`px-3 py-1 text-sm rounded-md ${page === events.current_page ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'}`}
                                onClick={() =>
                                    router.get(eventsRoute.url(), {
                                        page,
                                        search: filters.search,
                                        center: filters.center,
                                    })
                                }
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}