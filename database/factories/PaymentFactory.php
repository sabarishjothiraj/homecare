<?php

namespace Database\Factories;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = \App\Models\Payment::class;

    public function definition(): array
    {
        return [
            'payment_type' => 'contract',
            'contract_id' => Contract::factory(),
            'amount' => 1000.00,
            'status' => 'completed',
            'method' => 'cash',
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'method' => 'razorpay',
            'paid_at' => null,
            'razorpay_order_id' => 'order_'.fake()->unique()->lexify('??????????'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed', 'paid_at' => null]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => ['status' => 'refunded']);
    }

    public function of(float $amount): static
    {
        return $this->state(fn () => ['amount' => $amount]);
    }
}
