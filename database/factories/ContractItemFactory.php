<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractItemFactory extends Factory
{
    protected $model = \App\Models\ContractItem::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'service_id' => Service::factory(),
            'amount' => 5000.00,
            'max_assignments' => 10,
            'assignment_count' => 1,
        ];
    }
}
