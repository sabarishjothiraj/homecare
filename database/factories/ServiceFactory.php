<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = \App\Models\Service::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'icon' => 'fas fa-heartbeat',
            'is_active' => true,
            'order' => 0,
        ];
    }
}
