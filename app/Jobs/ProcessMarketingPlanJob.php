<?php

namespace App\Jobs;

use App\Models\AIFulfillmentLog;
use App\Models\DropservicingMarketingPlan;
use App\Models\Modules\Dropservicing\UserGig;
use App\Notifications\MarketingPlanCompletedNotification;
use App\Notifications\MarketingPlanFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessMarketingPlanJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $planId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $plan = DropservicingMarketingPlan::with(['gig', 'user'])->find($this->planId);

        if (!$plan) {
            Log::error('ProcessMarketingPlanJob: Plan not found', ['plan_id' => $this->planId]);
            return;
        }

        // Update status to pending (if not already)
        $plan->update(['status' => 'pending']);

        try {
            // Build prompt based on plan type
            $prompt = $this->buildPrompt($plan);

            // Get OpenAI API key
            $apiKey = Config::get('services.openai.api_key');
            if (!$apiKey) {
                throw new \Exception('OpenAI API key not configured');
            }

            // Call OpenAI API
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt($plan->plan_type),
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 3000,
                ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? 'Failed to generate marketing plan';
                throw new \Exception('AI generation failed: ' . $error);
            }

            $aiOutput = trim((string) data_get($response->json(), 'choices.0.message.content'));
            $tokensUsed = data_get($response->json(), 'usage.total_tokens', 0);
            $model = data_get($response->json(), 'model', 'gpt-4o-mini');

            if (empty($aiOutput)) {
                throw new \Exception('AI returned empty response');
            }

            // Update plan
            $plan->update([
                'ai_output' => $aiOutput,
                'status' => 'completed',
                'tokens_used' => $tokensUsed,
            ]);

            // Create fulfillment log
            AIFulfillmentLog::create([
                'marketing_plan_id' => $plan->id,
                'ai_model' => $model,
                'tokens_used' => $tokensUsed,
                'success' => true,
            ]);

            // Send notification to user
            $plan->user->notify(new MarketingPlanCompletedNotification($plan));

            Log::info('ProcessMarketingPlanJob: Marketing plan generated successfully', [
                'plan_id' => $this->planId,
                'tokens_used' => $tokensUsed,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessMarketingPlanJob: Error generating marketing plan', [
                'plan_id' => $this->planId,
                'error' => $e->getMessage(),
            ]);

            // Update plan status to failed
            $plan->update(['status' => 'failed']);

            // Create failure log
            AIFulfillmentLog::create([
                'marketing_plan_id' => $plan->id,
                'ai_model' => 'gpt-4o-mini',
                'success' => false,
                'error_message' => $e->getMessage(),
            ]);

            // Send notification to user about failure
            $plan->user->notify(new MarketingPlanFailedNotification($plan, $e->getMessage()));
        }
    }

    /**
     * Build prompt based on plan type
     */
    protected function buildPrompt(DropservicingMarketingPlan $plan): string
    {
        $input = $plan->input_summary;
        $gig = $plan->gig;

        switch ($plan->plan_type) {
            case '7-day':
                return $this->build7DayPlanPrompt($gig, $input);
            case '30-day':
                return $this->build30DayPlanPrompt($gig, $input);
            case 'ads-only':
                return $this->buildAdsOnlyPrompt($gig, $input);
            case 'content-calendar':
                return $this->buildContentCalendarPrompt($gig, $input);
            default:
                throw new \Exception('Unknown plan type: ' . $plan->plan_type);
        }
    }

    /**
     * Build 7-day marketing plan prompt
     */
    protected function build7DayPlanPrompt(?UserGig $gig, array $input): string
    {
        $gigTitle = $gig ? $gig->title : 'Service';
        $serviceName = $gig && $gig->service ? $gig->service->title : 'Service';
        $sellerName = $gig && $gig->user ? $gig->user->name : 'Seller';

        return "Generate a 7-day marketing plan for the following gig:

Title: {$gigTitle}
Service: {$serviceName}
Seller: {$sellerName}
Audience: {$input['audience']}
Platforms: " . implode(', ', $input['platforms']) . "
Goal: {$input['goal']}
Budget: " . ($input['budget'] ?? 'Not specified') . "
Tone: " . ($input['tone'] ?? 'professional') . "

Output format:
1) Short Overview (2-3 lines)
2) Daily plan (Day 1 to Day 7): action, caption example, ideal post time, CTA
3) 3 ad copy variations with headlines and descriptions
4) 10 target keywords/hashtags
5) Suggested budget allocation per day
6) Quick metrics to track (KPIs)";
    }

    /**
     * Build 30-day content calendar prompt
     */
    protected function build30DayPlanPrompt(?UserGig $gig, array $input): string
    {
        $gigTitle = $gig ? $gig->title : 'Service';
        $serviceName = $gig && $gig->service ? $gig->service->title : 'Service';

        return "Create a 30-day content calendar for the gig:

Title: {$gigTitle}
Service: {$serviceName}
Audience: {$input['audience']}
Platforms: " . implode(', ', $input['platforms']) . "
Tone: " . ($input['tone'] ?? 'professional') . "

Output format:
- A table with Date, Platform, Post Type, Caption, Hashtags, Suggested Visual Idea, CTA
- 30 caption examples
- 15 keywords/hashtags and their intent
- Suggested posting cadence and best times";
    }

    /**
     * Build ads-only prompt
     */
    protected function buildAdsOnlyPrompt(?UserGig $gig, array $input): string
    {
        $product = $gig ? $gig->title : 'Service';
        $platforms = implode(', ', $input['platforms']);
        $budget = $input['budget'] ?? 'Not specified';

        return "Create 3 high-converting ad campaigns for {$product} targeted at {$input['audience']} on {$platforms} with budget {$budget}.

Include ad headlines, descriptions, suggested visuals, audience targeting suggestions, recommended bids, and landing page messaging.";
    }

    /**
     * Build content calendar prompt
     */
    protected function buildContentCalendarPrompt(?UserGig $gig, array $input): string
    {
        $gigTitle = $gig ? $gig->title : 'Service';
        $serviceName = $gig && $gig->service ? $gig->service->title : 'Service';

        return "Create a comprehensive 30-day content calendar for:

Title: {$gigTitle}
Service: {$serviceName}
Audience: {$input['audience']}
Platforms: " . implode(', ', $input['platforms']) . "
Tone: " . ($input['tone'] ?? 'professional') . "

Output format:
- A table with Date, Platform, Post Type, Caption, Hashtags, Suggested Visual Idea, CTA
- 30 caption examples
- 15 keywords/hashtags and their intent
- Suggested posting cadence and best times";
    }

    /**
     * Get system prompt based on plan type
     */
    protected function getSystemPrompt(string $planType): string
    {
        return match ($planType) {
            '7-day' => 'You are an expert digital marketer and growth strategist. Create actionable, results-driven marketing plans.',
            '30-day', 'content-calendar' => 'You are an expert social media content strategist. Create engaging, platform-optimized content calendars.',
            'ads-only' => 'You are a PPC specialist and conversion optimization expert. Create high-converting ad campaigns.',
            default => 'You are an expert digital marketing strategist.',
        };
    }
}
