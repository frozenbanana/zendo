import { Head } from '@inertiajs/react';

interface Props {
    centers: { id: string; slug: string; name: string; description: string; timezone: string | null; locale: string | null }[];
}

export default function HubCenterList({ centers }: Props) {
    return (
        <>
            <Head title="Retreat Centers" />

            <div className="max-w-6xl mx-auto px-4 py-12">
                <h1 className="text-3xl font-bold tracking-tight mb-2">
                    Retreat Centers
                </h1>
                <p className="text-gray-600 mb-8">
                    Explore our network of retreat centers around the world.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {centers.map((center) => (
                        <div key={center.id} className="border rounded-lg p-6 hover:shadow-lg transition-shadow">
                            <h2 className="text-xl font-semibold mb-2">{center.name}</h2>
                            {center.description && (
                                <p className="text-sm text-gray-600 mb-3">{center.description}</p>
                            )}
                            <a
                                href={`/hub/events?center=${center.slug}`}
                                className="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                View events →
                            </a>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}