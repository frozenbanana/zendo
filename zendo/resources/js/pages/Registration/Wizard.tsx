import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { formatCurrency } from '@/lib/utils';

interface Props {
    steps: string[];
    features: {
        lodging: boolean;
        meals: boolean;
    };
    eventInstances: {
        id: string;
        title: string;
        starts_at: string;
        ends_at: string | null;
        capacity: number | null;
        price_cents: number | null;
        currency: string;
        event_name: string;
    }[];
    rooms: {
        id: string;
        name: string;
        room_type: string;
        capacity: number;
        price_cents: number;
        currency: string;
        building_name: string | null;
    }[];
    mealPlans: {
        id: string;
        name: string;
        description: string;
        meal_type: string;
        price_cents: number;
        currency: string;
    }[];
}

const STEP_LABELS: Record<string, string> = {
    event: 'Choose Event',
    'guest-info': 'Your Details',
    lodging: 'Accommodation',
    meals: 'Meal Plans',
    review: 'Review & Submit',
};

export default function RegistrationWizard({ steps, features, eventInstances, rooms, mealPlans }: Props) {
    const [currentStep, setCurrentStep] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [form, setForm] = useState({
        event_instance_id: '',
        guest_first_name: '',
        guest_last_name: '',
        guest_email: '',
        guest_phone: '',
        notes: [] as string[],
        stay: {
            room_id: '',
            check_in: '',
            check_out: '',
        },
        meal_selections: [] as { meal_plan_id: string; date: string; meal_type: string }[],
    });

    const currentStepName = steps[currentStep];
    const isLastStep = currentStep === steps.length - 1;

    const next = () => {
        if (validateCurrentStep()) {
            setCurrentStep((s) => Math.min(s + 1, steps.length - 1));
        }
    };

    const prev = () => {
        setCurrentStep((s) => Math.max(s - 1, 0));
    };

    const validateCurrentStep = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (currentStepName === 'event' && !form.event_instance_id) {
            newErrors.event_instance_id = 'Please select an event date.';
        }

        if (currentStepName === 'guest-info') {
            if (!form.guest_first_name) newErrors.guest_first_name = 'First name is required.';
            if (!form.guest_last_name) newErrors.guest_last_name = 'Last name is required.';
            if (!form.guest_email) newErrors.guest_email = 'Email is required.';
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.guest_email))
                newErrors.guest_email = 'Please enter a valid email.';
        }

        if (currentStepName === 'lodging' && form.stay.room_id) {
            if (!form.stay.check_in) newErrors.check_in = 'Check-in date is required.';
            if (!form.stay.check_out) newErrors.check_out = 'Check-out date is required.';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!validateCurrentStep()) return;

        setSubmitting(true);

        const payload: Record<string, unknown> = {
            event_instance_id: form.event_instance_id,
            guest_first_name: form.guest_first_name,
            guest_last_name: form.guest_last_name,
            guest_email: form.guest_email,
            guest_phone: form.guest_phone || null,
        };

        if (form.stay.room_id && features.lodging) {
            payload.stay = {
                room_id: form.stay.room_id,
                check_in: form.stay.check_in,
                check_out: form.stay.check_out,
            };
        }

        if (form.meal_selections.length > 0 && features.meals) {
            payload.meal_selections = form.meal_selections;
        }

        router.post('/registrations', payload, {
            onFinish: () => setSubmitting(false),
        });
    };

    const goToStep = (index: number) => {
        if (index <= currentStep) {
            setCurrentStep(index);
            return;
        }
        for (let i = currentStep; i < index; i++) {
            if (!validateCurrentStep()) return;
        }
        setCurrentStep(index);
    };

    const selectedInstance = eventInstances.find((i) => i.id === form.event_instance_id);
    const selectedRoom = rooms.find((r) => r.id === form.stay.room_id);

    const renderStepIndicator = () => (
        <nav className="flex items-center justify-center gap-2 mb-8">
            {steps.map((step, i) => (
                <button
                    key={step}
                    onClick={() => goToStep(i)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-full transition-colors ${
                        i === currentStep
                            ? 'bg-indigo-600 text-white'
                            : i < currentStep
                              ? 'bg-indigo-100 text-indigo-700'
                              : 'bg-gray-100 text-gray-400'
                    }`}
                >
                    <span className="w-5 h-5 flex items-center justify-center rounded-full text-xs font-medium bg-white/20">
                        {i < currentStep ? '✓' : i + 1}
                    </span>
                    <span className="hidden sm:inline">{STEP_LABELS[step] || step}</span>
                </button>
            ))}
        </nav>
    );

    const renderEventStep = () => (
        <div>
            <h2 className="text-2xl font-bold mb-4">Choose Your Event</h2>
            <p className="text-gray-600 mb-6">Select a date and time for your retreat.</p>
            {eventInstances.length === 0 ? (
                <div className="text-center py-8 text-gray-400">
                    No upcoming dates available. Please check back later.
                </div>
            ) : (
                <div className="grid gap-3">
                    {eventInstances.map((instance) => (
                        <button
                            key={instance.id}
                            type="button"
                            onClick={() =>
                                setForm((f) => ({ ...f, event_instance_id: instance.id }))
                            }
                            className={`text-left p-4 border rounded-lg transition-colors ${
                                form.event_instance_id === instance.id
                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                                    : 'border-gray-200 hover:border-gray-300'
                            }`}
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <p className="font-medium">{instance.event_name}</p>
                                    <p className="text-sm text-gray-500">{instance.title}</p>
                                    <p className="text-sm text-gray-500">
                                        {new Date(instance.starts_at).toLocaleDateString('en-US', {
                                            weekday: 'short',
                                            month: 'long',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })}
                                    </p>
                                    {instance.capacity !== null && (
                                        <p className="text-sm text-gray-500">
                                            {instance.capacity} spots available
                                        </p>
                                    )}
                                </div>
                                {instance.price_cents != null && (
                                    <p className="font-semibold">
                                        {formatCurrency(instance.price_cents, instance.currency)}
                                    </p>
                                )}
                            </div>
                        </button>
                    ))}
                </div>
            )}
            {errors.event_instance_id && (
                <p className="mt-2 text-sm text-red-600">{errors.event_instance_id}</p>
            )}
        </div>
    );

    const renderGuestInfoStep = () => (
        <div>
            <h2 className="text-2xl font-bold mb-4">Your Details</h2>
            <p className="text-gray-600 mb-6">
                Tell us about yourself so we can confirm your registration.
            </p>
            <div className="grid gap-4 max-w-md">
                <div>
                    <label htmlFor="first_name" className="block text-sm font-medium text-gray-700 mb-1">
                        First Name
                    </label>
                    <input
                        id="first_name"
                        type="text"
                        value={form.guest_first_name}
                        onChange={(e) =>
                            setForm((f) => ({ ...f, guest_first_name: e.target.value }))
                        }
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                    {errors.guest_first_name && (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_first_name}</p>
                    )}
                </div>
                <div>
                    <label htmlFor="last_name" className="block text-sm font-medium text-gray-700 mb-1">
                        Last Name
                    </label>
                    <input
                        id="last_name"
                        type="text"
                        value={form.guest_last_name}
                        onChange={(e) =>
                            setForm((f) => ({ ...f, guest_last_name: e.target.value }))
                        }
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                    {errors.guest_last_name && (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_last_name}</p>
                    )}
                </div>
                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        value={form.guest_email}
                        onChange={(e) =>
                            setForm((f) => ({ ...f, guest_email: e.target.value }))
                        }
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                    {errors.guest_email && (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_email}</p>
                    )}
                </div>
                <div>
                    <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                        Phone (optional)
                    </label>
                    <input
                        id="phone"
                        type="tel"
                        value={form.guest_phone}
                        onChange={(e) =>
                            setForm((f) => ({ ...f, guest_phone: e.target.value }))
                        }
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>
            </div>
        </div>
    );

    const renderLodgingStep = () => (
        <div>
            <h2 className="text-2xl font-bold mb-4">Accommodation</h2>
            <p className="text-gray-600 mb-6">
                Choose your room for the retreat. Leave blank if you don&apos;t need lodging.
            </p>
            {rooms.length === 0 ? (
                <div className="text-center py-8 text-gray-400">
                    No rooms available at this time.
                </div>
            ) : (
                <div className="grid gap-3 mb-6">
                    {rooms.map((room) => (
                        <button
                            key={room.id}
                            type="button"
                            onClick={() =>
                                setForm((f) => ({
                                    ...f,
                                    stay: { ...f.stay, room_id: room.id },
                                }))
                            }
                            className={`text-left p-4 border rounded-lg transition-colors ${
                                form.stay.room_id === room.id
                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                                    : 'border-gray-200 hover:border-gray-300'
                            }`}
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <p className="font-medium">{room.name}</p>
                                    <p className="text-sm text-gray-500">
                                        {room.building_name} &middot; {room.room_type} &middot;{' '}
                                        {room.capacity} guests
                                    </p>
                                </div>
                                <p className="font-semibold">
                                    {formatCurrency(room.price_cents, room.currency)}/night
                                </p>
                            </div>
                        </button>
                    ))}
                </div>
            )}
            {form.stay.room_id && (
                <div className="grid gap-4 max-w-md border-t pt-4">
                    <div>
                        <label
                            htmlFor="check_in"
                            className="block text-sm font-medium text-gray-700 mb-1"
                        >
                            Check-in
                        </label>
                        <input
                            id="check_in"
                            type="date"
                            value={form.stay.check_in}
                            onChange={(e) =>
                                setForm((f) => ({
                                    ...f,
                                    stay: { ...f.stay, check_in: e.target.value },
                                }))
                            }
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        {errors.check_in && (
                            <p className="mt-1 text-sm text-red-600">{errors.check_in}</p>
                        )}
                    </div>
                    <div>
                        <label
                            htmlFor="check_out"
                            className="block text-sm font-medium text-gray-700 mb-1"
                        >
                            Check-out
                        </label>
                        <input
                            id="check_out"
                            type="date"
                            value={form.stay.check_out}
                            onChange={(e) =>
                                setForm((f) => ({
                                    ...f,
                                    stay: { ...f.stay, check_out: e.target.value },
                                }))
                            }
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        {errors.check_out && (
                            <p className="mt-1 text-sm text-red-600">{errors.check_out}</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );

    const renderMealsStep = () => (
        <div>
            <h2 className="text-2xl font-bold mb-4">Meal Plans</h2>
            <p className="text-gray-600 mb-6">
                Select meal plans for your stay. You can choose multiple.
            </p>
            {mealPlans.length === 0 ? (
                <div className="text-center py-8 text-gray-400">
                    No meal plans available at this time.
                </div>
            ) : (
                <div className="grid gap-3">
                    {mealPlans.map((plan) => {
                        const isSelected = form.meal_selections.some(
                            (m) => m.meal_plan_id === plan.id,
                        );
                        return (
                            <button
                                key={plan.id}
                                type="button"
                                onClick={() => {
                                    setForm((f) => ({
                                        ...f,
                                        meal_selections: isSelected
                                            ? f.meal_selections.filter(
                                                  (m) => m.meal_plan_id !== plan.id,
                                              )
                                            : [
                                                  ...f.meal_selections,
                                                  {
                                                      meal_plan_id: plan.id,
                                                      date: '',
                                                      meal_type: plan.meal_type,
                                                  },
                                              ],
                                    }));
                                }}
                                className={`text-left p-4 border rounded-lg transition-colors ${
                                    isSelected
                                        ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                                        : 'border-gray-200 hover:border-gray-300'
                                }`}
                            >
                                <div className="flex justify-between items-start">
                                    <div>
                                        <p className="font-medium">{plan.name}</p>
                                        {plan.description && (
                                            <p className="text-sm text-gray-500">
                                                {plan.description}
                                            </p>
                                        )}
                                        <p className="text-xs text-gray-400 mt-1">
                                            {plan.meal_type}
                                        </p>
                                    </div>
                                    <p className="font-semibold">
                                        {formatCurrency(plan.price_cents, plan.currency)}
                                    </p>
                                </div>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );

    const renderReviewStep = () => (
        <div>
            <h2 className="text-2xl font-bold mb-4">Review Your Registration</h2>
            <p className="text-gray-600 mb-6">
                Please review your selections before submitting.
            </p>
            <div className="space-y-6">
                <div className="border rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">
                        Event
                    </h3>
                    {selectedInstance ? (
                        <div>
                            <p className="font-medium">{selectedInstance.title}</p>
                            <p className="text-sm text-gray-500">
                                {new Date(selectedInstance.starts_at).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    month: 'long',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                            </p>
                            {selectedInstance.price_cents != null && (
                                <p className="text-sm font-semibold mt-1">
                                    {formatCurrency(selectedInstance.price_cents, selectedInstance.currency)}
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-gray-400">No event selected</p>
                    )}
                </div>

                <div className="border rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">
                        Guest Info
                    </h3>
                    <p className="font-medium">
                        {form.guest_first_name} {form.guest_last_name}
                    </p>
                    <p className="text-sm text-gray-500">{form.guest_email}</p>
                    {form.guest_phone && (
                        <p className="text-sm text-gray-500">{form.guest_phone}</p>
                    )}
                </div>

                {features.lodging && form.stay.room_id && selectedRoom && (
                    <div className="border rounded-lg p-4">
                        <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            Accommodation
                        </h3>
                        <p className="font-medium">{selectedRoom.name}</p>
                        <p className="text-sm text-gray-500">
                            {selectedRoom.building_name} &middot; {selectedRoom.room_type}
                        </p>
                        {form.stay.check_in && (
                            <p className="text-sm text-gray-500">
                                {form.stay.check_in} &rarr; {form.stay.check_out}
                            </p>
                        )}
                        <p className="text-sm font-semibold mt-1">
                            {formatCurrency(selectedRoom.price_cents, selectedRoom.currency)}
                            /night
                        </p>
                    </div>
                )}

                {features.meals && form.meal_selections.length > 0 && (
                    <div className="border rounded-lg p-4">
                        <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            Meal Plans
                        </h3>
                        <ul className="space-y-1">
                            {form.meal_selections.map((sel) => {
                                const plan = mealPlans.find((p) => p.id === sel.meal_plan_id);
                                return (
                                    <li key={sel.meal_plan_id} className="text-sm">
                                        {plan?.name ?? sel.meal_plan_id} &mdash;{' '}
                                        {plan
                                            ? formatCurrency(plan.price_cents, plan.currency)
                                            : ''}
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );

    const renderCurrentStep = () => {
        switch (currentStepName) {
            case 'event':
                return renderEventStep();
            case 'guest-info':
                return renderGuestInfoStep();
            case 'lodging':
                return renderLodgingStep();
            case 'meals':
                return renderMealsStep();
            case 'review':
                return renderReviewStep();
            default:
                return null;
        }
    };

    return (
        <>
            <Head title="Register for a Retreat" />

            <div className="max-w-2xl mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold tracking-tight mb-2">
                    Register for a Retreat
                </h1>
                <p className="text-gray-600 mb-8">
                    Complete each step to reserve your spot.
                </p>

                {renderStepIndicator()}

                <form onSubmit={submit}>
                    <div className="mb-8">{renderCurrentStep()}</div>

                    <div className="flex justify-between">
                        {currentStep > 0 ? (
                            <button
                                type="button"
                                onClick={prev}
                                className="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Back
                            </button>
                        ) : (
                            <a
                                href="/hub/events"
                                className="px-4 py-2 text-sm text-gray-500 hover:text-gray-700"
                            >
                                Cancel
                            </a>
                        )}

                        {isLastStep ? (
                            <button
                                type="submit"
                                disabled={submitting}
                                className="px-6 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {submitting ? 'Submitting...' : 'Complete Registration'}
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={next}
                                className="px-6 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                            >
                                Continue
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}