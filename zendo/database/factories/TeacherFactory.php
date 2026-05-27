<?php

namespace Database\Factories;

use App\Modules\Events\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        $specialties = [
            ['Yoga', 'Mindfulness'],
            ['Vipassana', 'Meditation'],
            ['Breathwork', 'Pranayama'],
            ['Zen', 'Meditation'],
            ['Tai Chi', 'Qi Gong'],
            ['Sound Healing', 'Meditation'],
            ['Ashtanga', 'Vinyasa'],
            ['Trauma-Informed Yoga', 'Somatics'],
        ];

        return [
            'name' => fake()->name(),
            'bio' => fake()->paragraphs(2, true),
            'photo' => null,
            'specialties' => fake()->randomElement($specialties),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
