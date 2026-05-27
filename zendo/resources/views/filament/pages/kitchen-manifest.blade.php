<x-filament-panels::page>
    <div class="space-y-6">
        @foreach($this->getMealPlans() as $mealPlan)
            <x-filament::section>
                <x-slot name="heading">
                    {{ $mealPlan->name }} — {{ $mealPlan->guests_count }} guests
                </x-slot>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Vegetarian</p>
                        <p class="text-2xl font-bold">{{ $mealPlan->vegetarian_count }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Vegan</p>
                        <p class="text-2xl font-bold">{{ $mealPlan->vegan_count }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Gluten-free</p>
                        <p class="text-2xl font-bold">{{ $mealPlan->gluten_free_count }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>