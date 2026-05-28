import { Head } from '@inertiajs/react';

interface Props {
    centers: { id: string; slug: string; name: string; description: string; logo: string | null; features: Record<string, boolean>; currency: string; timezone: string | null; locale: string | null }[];
}

export default function HubCenterList({ centers }: Props) {
    return (
        <>
            <Head title="Retreat Centers" />
            <div className="max-w-6xl mx-auto px-4 py-12">
                <h1 className="text-3xl font-bold tracking-tight mb-2">Retreat Centers</h1>
                <p className="text-gray-600 mb-8">Explore our network of retreat centers around the world.</p>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {centers.map((center) => (
                        <div key={center.id} className="border rounded-lg p-6 hover:shadow-lg transition-shadow">
                            <h2 className="text-xl font-semibold mb-2">{center.name}</h2>
                            {center.description && (
                                <p className="text-sm text-gray-600 mb-3">{center.description}</p>
                            )}
                            <div className="flex gap-2 flex-wrap mb-3">
                                {center.features?.meals && <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Meals</span>}
                                {center.features?.lodging && <span className="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Lodging</span>}
                                {center.features?.memberships && <span className="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">Memberships</span>}
                            </div>
                            <a href={`/hub/events?center=${center.slug}`} className="text-sm text-indigo-600 hover:text-indigo-800">View events →</a>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}