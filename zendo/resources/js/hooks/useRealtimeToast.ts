import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

interface Toast {
    id: string;
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
}

export function useRealtimeToast() {
    const [toasts, setToasts] = useState<Toast[]>([]});

    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo) return;

        window.Echo.private('tenant.' + window.currentTenantSlug)
            .listen('.registration.confirmed', (event: { type: string; registration_id: string; event_title: string; guest_name: string }) => {
                addToast('success', `New registration: ${event.guest_name} for ${event.event_title}`);
            });

        return () => {
            window.Echo?.leaveChannel('tenant.' + window.currentTenantSlug);
        };
    }, []);

    const addToast = (type: Toast['type'], message: string) => {
        const id = Math.random().toString(36).substr(2, 9);
        setToasts((prev) => [...prev, { id, type, message }]);
        setTimeout(() => {
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, 5000);
    };

    return { toasts };
}