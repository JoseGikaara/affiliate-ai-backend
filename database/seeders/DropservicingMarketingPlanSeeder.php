<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DropservicingMarketingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed default marketing plan credit costs in settings
        DB::table('settings')->updateOrInsert(
            ['key' => 'marketing_plan_costs'],
            [
                'value' => json_encode([
                    '7-day' => 8,
                    '30-day' => 20,
                    'ads-only' => 10,
                    'content-calendar' => 12,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Marketing plan default costs seeded successfully.');
    }
}
