<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssignmentFactory extends Factory
{
    protected $model = \App\Models\Assignment::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'contract_item_id' => ContractItem::factory(),
            'employee_id' => Employee::factory(),
            'service_id' => Service::factory(),
            'line_amount' => 5000.00,
            'start_date' => now()->toDateString(),
            'is_emergency_replacement' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
