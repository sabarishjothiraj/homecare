<?php

namespace Database\Seeders;

use App\Models\Caste;
use Illuminate\Database\Seeder;

class CasteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $castes = [
            ['name' => 'SC (Scheduled Caste)', 'display_order' => 1],
            ['name' => 'ST (Scheduled Tribe)', 'display_order' => 2],
            ['name' => 'BC (Backward Class)', 'display_order' => 3],
            ['name' => 'MBC (Most Backward Class)', 'display_order' => 4],
            ['name' => 'OBC (Other Backward Class)', 'display_order' => 5],
            ['name' => 'General', 'display_order' => 6],
        ];

        foreach ($castes as $caste) {
            Caste::updateOrCreate(
                ['name' => $caste['name']],
                [
                    'is_active' => true,
                    'display_order' => $caste['display_order'],
                ]
            );
        }
    }
}
