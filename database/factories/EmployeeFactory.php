<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = \App\Models\Employee::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('97########'),
            'field_data' => [],
            'created_by' => User::factory(),
            'status' => 'active',
            'source' => 'admin',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}
