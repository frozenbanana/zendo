<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($this->getTodayRegistrations() as $registration)
            <x-filament::card>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold">{{ $registration->guest_name }}</h3>
                        <p class="text-sm text-gray-500">{{ $registration->event_title }}</p>
                    </div>
                    <x-filament::button
                        color="success"
                        size="sm"
                        wire:click="checkIn({{ $registration->id }})"
                    >
                        Check In
                    </x-filament::button>
                </div>
            </x-filament::card>
        @endforeach
    </div>
</x-filament-panels::page>