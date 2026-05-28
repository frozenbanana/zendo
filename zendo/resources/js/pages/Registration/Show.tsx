import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/lib/utils';

interface Registration {
    id: string;
    status: string;
    guest_first_name: string;
    guest_last_name: string;
    guest_email: string;
    guest_phone: string | null;
    total_cents: number;
    currency: string;
    created_at: string;
    event: { id: string; title: string; tenant: { name: string; slug: string } };
    eventInstance: { id: string; title: string; starts_at: string; ends_at: string } | null;
    stay: {
        room: { name: string; building: { name: string } };
        check_in: string;
        check_out: string;
        price_cents: number;
    } | null;
    mealSelections: { meal_plan_id: string; date: string; meal_type: string; mealPlan: { name: string; price_cents: number } }[];
    addOnSelections: { add_on_name: string; quantity: number; price_cents: number }[];
}

interface Props {
    registration: Registration;
}

const STATUS_STYLES: Record<string, string> = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    CONFIRMED: 'bg-green-100 text-green-800',
    CANCELLED: 'bg-red-100 text-red-800',
    WAITLISTED: 'bg-blue-100 text-blue-800',
};

export default function RegistrationShow({ registration }: Props) {
    const statusStyle = STATUS_STYLES[registration.status] ?? 'bg-gray-100 text-gray-800';

    return (
        <>
            <Head title={`Registration — ${registration.guest_first_name} ${registration.guest_last_name}`} />

            <div className="max-w-3xl mx-auto px-4 py-8">
                <Link
                    href="/hub/events"
                    className="text-sm text-gray-500 hover:text-gray-900 mb-4 inline-block"
                >
                    &larr; Back to events
                </Link>

                <div className="flex items-center justify-between mb-8">
                    <h1 className="text-3xl font-bold tracking-tight">Registration Confirmation</h1>
                    <span className={`text-xs font-medium px-3 py-1 rounded-full ${statusStyle}`}>
                        {registration.status}
                    </span>
                </div>

                <div className="space-y-6">
                    <div className="border rounded-lg p-6">
                        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                            Guest Information
                        </h2>
                        <p className="text-lg font-medium">
                            {registration.guest_first_name} {registration.guest_last_name}
                        </p>
                        <p className="text-sm text-gray-600">{registration.guest_email}</p>
                        {registration.guest_phone && (
                            <p className="text-sm text-gray-600">{registration.guest_phone}</p>
                        )}
                    </div>

                    <div className="border rounded-lg p-6">
                        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                            Event
                        </h2>
                        <p className="text-lg font-medium">{registration.event.title}</p>
                        <p className="text-sm text-gray-500">
                            {registration.event.tenant.name}
                        </p>
                        {registration.eventInstance && (
                            <p className="text-sm text-gray-500 mt-1">
                                {new Date(registration.eventInstance.starts_at).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    month: 'long',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                                {' '}at{' '}
                                {new Date(registration.eventInstance.starts_at).toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                })}
                            </p>
                        )}
                    </div>

                    {registration.stay && (
                        <div className="border rounded-lg p-6">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                Accommodation
                            </h2>
                            <p className="font-medium">
                                {registration.stay.room.name}
                            </p>
                            <p className="text-sm text-gray-500">
                                {registration.stay.room.building?.name}
                            </p>
                            <p className="text-sm text-gray-500">
                                {new Date(registration.stay.check_in).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                                {' '}&rarr;{' '}
                                {new Date(registration.stay.check_out).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                            </p>
                        </div>
                    )}

                    {registration.mealSelections && registration.mealSelections.length > 0 && (
                        <div className="border rounded-lg p-6">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                Meal Plans
                            </h2>
                            <ul className="space-y-2">
                                {registration.mealSelections.map((sel, i) => (
                                    <li key={i} className="flex justify-between text-sm">
                                        <span>{sel.mealPlan?.name ?? sel.meal_type}</span>
                                        {sel.mealPlan && (
                                            <span className="font-medium">
                                                {formatCurrency(sel.mealPlan.price_cents, registration.currency)}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {registration.addOnSelections && registration.addOnSelections.length > 0 && (
                        <div className="border rounded-lg p-6">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                Add-ons
                            </h2>
                            <ul className="space-y-2">
                                {registration.addOnSelections.map((addon, i) => (
                                    <li key={i} className="flex justify-between text-sm">
                                        <span>
                                            {addon.add_on_name}
                                            {addon.quantity > 1 && ` x${addon.quantity}`}
                                        </span>
                                        <span className="font-medium">
                                            {formatCurrency(addon.price_cents, registration.currency)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="border rounded-lg p-6 bg-gray-50">
                        <div className="flex justify-between items-center">
                            <span className="text-lg font-semibold">Total</span>
                            <span className="text-2xl font-bold">
                                {formatCurrency(registration.total_cents, registration.currency)}
                            </span>
                        </div>
                    </div>
                </div>

                <p className="text-sm text-gray-400 mt-8 text-center">
                    Registration ID: {registration.id}
                </p>
            </div>
        </>
    );
}