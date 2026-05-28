import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome to Zendo" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-indigo-50 to-white dark:from-gray-900 dark:to-gray-950 p-6">
                <div className="max-w-2xl w-full text-center">
                    <div className="mb-8">
                        <h1 className="text-5xl font-bold tracking-tight text-gray-900 dark:text-white mb-4">
                            Zendo
                        </h1>
                        <p className="text-xl text-gray-600 dark:text-gray-300">
                            Discover yoga retreats, meditation centers, and wellness programs from around the world.
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-4 justify-center mb-12">
                        <Link
                            href="/hub"
                            className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors"
                        >
                            Browse Retreat Centers
                        </Link>
                        <Link
                            href="/hub/events"
                            className="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-50 transition-colors dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                        >
                            Find Events
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 text-left">
                        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                <svg className="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.136-4.136l1.591-1.591M3 12h2.25m.386-6.364l1.591 1.591M6.75 12a5.25 5.25 0 1110.5 0 5.25 5.25 0 01-10.5 0z" />
                                </svg>
                            </div>
                            <h3 className="font-semibold text-gray-900 dark:text-white">Mindful Retreats</h3>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Curated yoga and meditation retreats from trusted centers worldwide.
                            </p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg className="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 10.5h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 6.75h.75m-.75 3h.75m-.75 3h.75M15 6.75h.75m-.75 3h.75m-.75 3h.75" />
                                </svg>
                            </div>
                            <h3 className="font-semibold text-gray-900 dark:text-white">Comfortable Stays</h3>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Find lodging with meals, private rooms, and peaceful settings.
                            </p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                                <svg className="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.958-1.581M7.5 14.25a3 3 0 00-3.957 2.832c0 .621.146 1.21.407 1.748M7.5 14.25a48.665 48.665 0 0112 0m-6 0a3 3 0 00-3.957 2.832c0 .621.146 1.21.407 1.748m0 0l.001.031c.012.167.037.331.074.49M12 3a9 9 0 00-7.5 16.449" />
                                </svg>
                            </div>
                            <h3 className="font-semibold text-gray-900 dark:text-white">Expert Teachers</h3>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Learn from experienced yoga and meditation teachers across traditions.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}