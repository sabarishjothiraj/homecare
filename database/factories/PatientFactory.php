<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = \App\Models\Patient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('98########'),
            'address1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'Tamil Nadu',
            'country' => 'India',
            'pincode' => fake()->numerify('6#####'),
            'created_by' => User::factory(),
            'stay_with_them' => 'no',
            'is_active' => true,
        ];
    }
}
