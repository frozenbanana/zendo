import { Head, Link } from '@inertiajs/react';

interface Props {
    event: {
        id: string;
        title: string;
        description: string;
        status: string;
        starts_at: string | null;
        ends_at: string | null;
        capacity: number | null;
        price_cents: number | null;
        tenant: { slug: string; name: string };
        teachers: { name: string; bio: string }[];
        instances: { id: string; title: string; starts_at: string; ends_at: string; capacity: number | null; price_cents: number | null }[];
    };
}

export default function HubEventDetail({ event }: Props) {
    const nextInstance = event.instances[0];

    return (
        <>
            <Head title={event.title}>
                <meta name="description" content={event.description} />
            </Head>

            <div className="max-w-4xl mx-auto px-4 py-8">
                <Link
                    href={route('hub.events')}
                    className="text-sm text-gray-500 hover:text-gray-900 mb-4 inline-block"
                >
                    &larr; Back to all events
                </Link>

                <h1 className="text-4xl font-bold tracking-tight">{event.title}</h1>
                <div className="flex items-center gap-2 mt-2">
                    <span className="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded">
                        {event.tenant.name}
                    </span>
                    {event.teachers.map((t) => (
                        <span key={t.name} className="text-xs border border-gray-300 px-2 py-0.5 rounded">
                            {t.name}
                        </span>
                    ))}
                </div>

                <div className="mt-8 prose max-w-none">
                    <p>{event.description}</p>
                </div>

                {nextInstance && (
                    <div className="mt-8 border rounded-lg p-6">
                        <h3 className="text-lg font-semibold">Next Available Date</h3>
                        <div className="mt-4 flex items-center justify-between">
                            <div>
                                <p className="font-medium">
                                    {nextInstance.title}
                                </p>
                                <p className="text-sm text-gray-500">
                                    {new Date(nextInstance.starts_at).toLocaleDateString('en-US', {
                                        weekday: 'long',
                                        month: 'long',
                                        day: 'numeric',
                                        year: 'numeric',
                                    })}
                                </p>
                                {nextInstance.capacity && (
                                    <p className="text-sm text-gray-500">
                                        {nextInstance.capacity} spots available
                                    </p>
                                )}
                            </div>
                            {nextInstance.price_cents != null && (
                                <div className="text-right">
                                    <p className="text-2xl font-bold">${(nextInstance.price_cents / 100).toFixed(2)}</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}