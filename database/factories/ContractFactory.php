<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = \App\Models\Contract::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'service_id' => Service::factory(),
            'created_by' => User::factory(),
            'duration_type' => 'monthly',
            'duration_value' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'status' => 'not_assigned',
        ];
    }

    public function worth(float $amount): static
    {
        return $this->state(fn () => ['amount' => $amount]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }
}
