<?php

namespace App\Jobs;

use App\Models\AIFulfillmentLog;
use App\Models\DropservicingOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessDropservicingOrderJob implements ShouldQueue
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
    public function handle(): void
    {
        $order = DropservicingOrder::with(['service'])->find($this->orderId);

        if (!$order) {
            Log::error('ProcessDropservicingOrderJob: Order not found', ['order_id' => $this->orderId]);
            return;
        }

        // Update order status to in_progress
        $order->update(['status' => 'in_progress']);

        $service = $order->service;
        $inputData = $order->input_data;
        $promptTemplate = $service->ai_prompt_template;

        try {
            // Format the prompt template with user's input data
            $formattedPrompt = $this->formatPrompt($promptTemplate, $inputData);

            // Get OpenAI API key
            $apiKey = Config::get('services.openai.api_key');
            if (!$apiKey) {
                throw new \Exception('OpenAI API key not configured');
            }

            // Call OpenAI API
            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a professional freelancer delivering high-quality client projects. Provide detailed, well-structured output that meets all client requirements.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $formattedPrompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? 'Failed to generate content';
                throw new \Exception('AI generation failed: ' . $error);
            }

            $aiResponse = trim((string) data_get($response->json(), 'choices.0.message.content'));
            $tokensUsed = data_get($response->json(), 'usage.total_tokens', 0);
            $model = data_get($response->json(), 'model', 'gpt-4o-mini');

            if (empty($aiResponse)) {
                throw new \Exception('AI returned empty response');
            }

            // Update order with AI response
            $order->update([
                'ai_response' => $aiResponse,
                'status' => 'completed',
            ]);

            // Create fulfillment log
            AIFulfillmentLog::create([
                'order_id' => $order->id,
                'ai_model' => $model,
                'tokens_used' => $tokensUsed,
                'success' => true,
            ]);

            // TODO: Send notification to user
            // $order->user->notify(new OrderCompletedNotification($order));

            Log::info('ProcessDropservicingOrderJob: Order fulfilled successfully', [
                'order_id' => $this->orderId,
                'tokens_used' => $tokensUsed,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessDropservicingOrderJob: Error processing order', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);

            // Update order status to failed
            $order->update(['status' => 'failed']);

            // Create failure log
            AIFulfillmentLog::create([
                'order_id' => $order->id,
                'ai_model' => 'gpt-4o-mini',
                'success' => false,
                'error_message' => $e->getMessage(),
            ]);

            // TODO: Send notification to user about failure
            // $order->user->notify(new OrderFailedNotification($order, $e->getMessage()));
        }
    }

    /**
     * Format prompt template with user input data
     */
    protected function formatPrompt(string $template, array $inputData): string
    {
        $prompt = $template;

        // Replace placeholders like {field_name} with actual values
        foreach ($inputData as $key => $value) {
            $placeholder = '{' . $key . '}';
            $prompt = str_replace($placeholder, $value, $prompt);
        }

        // Also support common placeholders
        $commonReplacements = [
            '{topic}' => $inputData['topic'] ?? $inputData['subject'] ?? '',
            '{word_count}' => $inputData['word_count'] ?? $inputData['length'] ?? '1000',
            '{tone}' => $inputData['tone'] ?? 'professional',
            '{audience}' => $inputData['audience'] ?? 'general',
        ];

        foreach ($commonReplacements as $placeholder => $defaultValue) {
            if (strpos($prompt, $placeholder) !== false) {
                $prompt = str_replace($placeholder, $defaultValue, $prompt);
            }
        }

        return $prompt;
    }
}
