<?php

namespace App\Jobs;

use App\Models\CreditTransaction;
use App\Models\Modules\Dropservicing\GigFulfillment;
use App\Models\Modules\Dropservicing\GigOrder;
use App\Services\CreditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FulfillGigJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CreditService $creditService): void
    {
        $order = GigOrder::with(['gig', 'gig.user', 'gig.service'])->find($this->orderId);

        if (!$order) {
            Log::error('FulfillGigJob: Order not found', ['order_id' => $this->orderId]);
            return;
        }

        // Update order status to processing
        $order->update(['status' => 'processing']);

        $gig = $order->gig;
        $user = $gig->user;
        $service = $gig->service;

        if (!$service) {
            Log::error('FulfillGigJob: Service not found', ['order_id' => $this->orderId]);
            $order->update(['status' => 'failed']);
            return;
        }

        // Calculate credit cost (10-30 credits based on service)
        $creditCost = $service->base_credit_cost;
        if ($creditCost < 10) {
            $creditCost = 10;
        } elseif ($creditCost > 30) {
            $creditCost = 30;
        }

        // Check if user has enough credits
        if (!$creditService->hasEnoughCredits($user, $creditCost)) {
            Log::error('FulfillGigJob: Insufficient credits', [
                'order_id' => $this->orderId,
                'user_id' => $user->id,
                'required' => $creditCost,
                'available' => $user->credits,
            ]);
            $order->update(['status' => 'failed']);
            return;
        }

        try {
            // Get AI prompt template
            $promptTemplate = $service->ai_prompt_template;
            $prompt = $this->buildPrompt($promptTemplate, $order->requirements);

            // Call OpenAI API
            $apiKey = Config::get('services.openai.api_key');
            if (!$apiKey) {
                throw new \Exception('OpenAI API key not configured');
            }

            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $promptTemplate['model'] ?? 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $promptTemplate['system_prompt'] ?? 'You are a professional service provider. Deliver high-quality work based on the requirements provided.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => $promptTemplate['temperature'] ?? 0.7,
                    'max_tokens' => $promptTemplate['max_tokens'] ?? 1500,
                ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? 'Failed to generate content';
                throw new \Exception('AI generation failed: ' . $error);
            }

            $aiOutput = trim((string) data_get($response->json(), 'choices.0.message.content'));

            if (empty($aiOutput)) {
                throw new \Exception('AI returned empty response');
            }

            // Create fulfillment record
            $fulfillment = GigFulfillment::create([
                'order_id' => $order->id,
                'ai_output' => $aiOutput,
                'file_url' => null, // Can be extended to save files
            ]);

            // Deduct credits
            $creditService->deductCredits($user, $creditCost, 'Gig order fulfillment: ' . $gig->title);

            // Update order status to completed
            $order->update(['status' => 'completed']);

            // TODO: Send notification to buyer and seller
            // $user->notify(new GigFulfilledNotification($order));
            // Mail::to($order->buyer_email)->send(new OrderCompletedMail($order));

            Log::info('FulfillGigJob: Order fulfilled successfully', [
                'order_id' => $this->orderId,
                'credits_deducted' => $creditCost,
            ]);
        } catch (\Exception $e) {
            Log::error('FulfillGigJob: Error fulfilling order', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);

            $order->update(['status' => 'failed']);
        }
    }

    /**
     * Build prompt from template and requirements
     */
    protected function buildPrompt(array $template, string $requirements): string
    {
        $prompt = $template['prompt'] ?? 'Create content based on these requirements: {requirements}';

        // Replace placeholders
        $replacements = [
            '{requirements}' => $requirements,
            '{topic}' => $requirements,
            '{word_count}' => '1000',
            '{service_type}' => 'service',
            '{audience}' => 'general',
            '{tone}' => 'professional',
        ];

        foreach ($replacements as $placeholder => $value) {
            $prompt = str_replace($placeholder, $value, $prompt);
        }

        return $prompt;
    }
}
