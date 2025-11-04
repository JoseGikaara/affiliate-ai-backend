<?php

namespace Database\Seeders;

use App\Models\AffiliateNetwork;
use App\Models\TrainingModule;
use App\Services\TrainingContentGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class TrainingModuleSeeder extends Seeder
{
    /**
     * Seed training modules using AI generation
     * Networks to generate for: Fiverr, Digistore24, Udemy, CPA Grip, Deriv, ICMarkets, Jumia
     */
    public function run(): void
    {
        $generator = app(TrainingContentGenerator::class);
        
        // Networks to generate training for
        $networkNames = ['Fiverr', 'Digistore24', 'Udemy', 'CPA Grip', 'Deriv', 'ICMarkets', 'Jumia'];
        
        $networks = AffiliateNetwork::whereIn('name', $networkNames)
            ->where('is_active', true)
            ->get();

        if ($networks->isEmpty()) {
            $this->command?->warn('No matching active networks found. Run AffiliateNetworkSeeder first.');
            Log::warning('TrainingModuleSeeder: No matching networks found');
            return;
        }

        $this->command?->info("Generating training modules for {$networks->count()} networks using AI...");

        foreach ($networks as $index => $network) {
            try {
                $this->command?->line("Generating for: {$network->name}");

                $content = $generator->generateTraining(
                    $network->name,
                    $network->category,
                    $network->description
                );

                $title = $generator->generateTitle($network->name);

                TrainingModule::updateOrCreate(
                    ['network_id' => $network->id],
                    [
                        'title' => $title,
                        'content' => $content,
                        'thumbnail_url' => null,
                        'credit_cost' => 5,
                        'is_published' => true,
                    ]
                );

                $this->command?->info("  ✓ Generated training for {$network->name}");
                Log::info("Training module generated", ['network_id' => $network->id, 'network_name' => $network->name]);

                // Rate limit: wait 3 seconds between generations (except for the last one)
                if ($index < $networks->count() - 1) {
                    sleep(3);
                }
            } catch (\Exception $e) {
                $this->command?->error("  ✗ Failed for {$network->name}: {$e->getMessage()}");
                Log::error("Training generation failed", [
                    'network_id' => $network->id,
                    'network_name' => $network->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->command?->info('Training module generation completed!');
    }
}


