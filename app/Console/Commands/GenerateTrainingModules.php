<?php

namespace App\Console\Commands;

use App\Models\AffiliateNetwork;
use App\Models\TrainingModule;
use App\Services\TrainingContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateTrainingModules extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'training:generate {network? : The ID or name of the network to generate for}';

    /**
     * The console command description.
     */
    protected $description = 'Generate training modules for affiliate networks using AI';

    /**
     * Execute the console command.
     */
    public function handle(TrainingContentGenerator $generator): int
    {
        $networkParam = $this->argument('network');

        if ($networkParam) {
            // Generate for a specific network
            $network = AffiliateNetwork::where('id', $networkParam)
                ->orWhere('name', 'LIKE', "%{$networkParam}%")
                ->where('is_active', true)
                ->first();

            if (!$network) {
                $this->error("Network '{$networkParam}' not found or is inactive.");
                return Command::FAILURE;
            }

            $this->generateForNetwork($network, $generator);
        } else {
            // Generate for all active networks
            $networks = AffiliateNetwork::where('is_active', true)->get();

            if ($networks->isEmpty()) {
                $this->warn('No active networks found.');
                return Command::SUCCESS;
            }

            $this->info("Generating training modules for {$networks->count()} networks...");
            $bar = $this->output->createProgressBar($networks->count());
            $bar->start();

            foreach ($networks as $network) {
                $this->generateForNetwork($network, $generator);
                $bar->advance();
                
                // Rate limit: wait 3 seconds between generations
                if ($networks->last() !== $network) {
                    sleep(3);
                }
            }

            $bar->finish();
            $this->newLine();
        }

        $this->info('Training module generation completed!');
        return Command::SUCCESS;
    }

    /**
     * Generate training for a single network
     */
    private function generateForNetwork(AffiliateNetwork $network, TrainingContentGenerator $generator): void
    {
        try {
            $this->line("Generating training for: {$network->name}");

            $content = $generator->generateTraining(
                $network->name,
                $network->category,
                $network->description
            );

            $title = $generator->generateTitle($network->name);

            // Check if training already exists
            $training = TrainingModule::where('network_id', $network->id)->first();

            if ($training) {
                $training->update([
                    'title' => $title,
                    'content' => $content,
                    'credit_cost' => 5,
                    'is_published' => true,
                ]);
                $this->info("  ✓ Updated existing training module");
            } else {
                TrainingModule::create([
                    'network_id' => $network->id,
                    'title' => $title,
                    'content' => $content,
                    'credit_cost' => 5,
                    'is_published' => true,
                ]);
                $this->info("  ✓ Created new training module");
            }

            Log::info("Training module generated", [
                'network_id' => $network->id,
                'network_name' => $network->name,
            ]);
        } catch (\Exception $e) {
            $this->error("  ✗ Failed for {$network->name}: {$e->getMessage()}");
            Log::error("Training generation failed", [
                'network_id' => $network->id,
                'network_name' => $network->name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

