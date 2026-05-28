import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/lib/utils';

interface Props {
    centers: { id: string; slug: string; name: string; description: string; logo: string | null; features: Record<string, boolean>; currency: string }[];
    featuredEvents: { id: string; title: string; description: string; slug: string; starts_at: string; price_cents: number | null; currency: string; tenant: { slug: string; name: string }; teachers: { name: string }[] }[];
}

export default function HubHome({ centers, featuredEvents }: Props) {
    return (
        <>
            <Head title="Zendo — Find Your Retreat" />

            <div className="max-w-6xl mx-auto px-4 py-12">
                <h1 className="text-4xl font-bold tracking-tight mb-4">
                    Find Your Retreat
                </h1>
                <p className="text-lg text-gray-600 mb-8">
                    Discover yoga retreats, meditation centers, and wellness programs from around the world.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                    {centers.map((center) => (
                        <div key={center.id} className="border rounded-lg p-6 hover:shadow-lg transition-shadow">
                            <h2 className="text-xl font-semibold mb-2">{center.name}</h2>
                            {center.description && (
                                <p className="text-sm text-gray-600 mb-3">{center.description}</p>
                            )}
                            <div className="flex gap-2 flex-wrap">
                                {center.features?.meals && <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Meals</span>}
                                {center.features?.lodging && <span className="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Lodging</span>}
                                {center.features?.memberships && <span className="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">Memberships</span>}
                            </div>
                            <Link
                                href={`/hub/events?center=${center.slug}`}
                                className="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                View events →
                            </Link>
                        </div>
                    ))}
                </div>

                {featuredEvents.length > 0 && (
                    <div>
                        <h2 className="text-2xl font-bold mb-4">Featured Events</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {featuredEvents.map((event) => (
                                <div key={event.id} className="border rounded-lg p-6 hover:shadow-lg transition-shadow">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded">
                                            {event.tenant.name}
                                        </span>
                                    </div>
                                    <h3 className="text-lg font-semibold">{event.title}</h3>
                                    <p className="text-sm text-gray-500 mt-1">{new Date(event.starts_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                                    {event.price_cents != null && (
                                        <p className="text-sm font-semibold mt-1">{formatCurrency(event.price_cents, event.currency)}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}