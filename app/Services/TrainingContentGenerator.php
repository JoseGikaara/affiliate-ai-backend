<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrainingContentGenerator
{
    /**
     * Generate training content for an affiliate network using OpenAI
     */
    public function generateTraining(string $networkName, ?string $networkCategory = null, ?string $networkDescription = null): string
    {
        $apiKey = Config::get('services.openai.api_key');
        
        if (!$apiKey) {
            throw new \Exception('OpenAI API key is not configured.');
        }

        $prompt = $this->buildPrompt($networkName, $networkCategory, $networkDescription);

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert affiliate marketing educator. Write comprehensive, beginner-friendly training articles that teach people how to make money using affiliate networks. Use clear, actionable language and include real-world examples. Format all output in Markdown with proper headings, lists, and callouts.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Failed to generate training content.';
            Log::error('OpenAI training generation failed', [
                'network' => $networkName,
                'error' => $error,
                'response' => $response->json(),
            ]);
            throw new \Exception('AI generation failed: ' . $error);
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content'));
        
        if (empty($content)) {
            throw new \Exception('AI returned empty content. Please try again.');
        }

        Log::info('Training content generated successfully', ['network' => $networkName, 'length' => strlen($content)]);

        return $content;
    }

    /**
     * Build the prompt for OpenAI
     */
    private function buildPrompt(string $networkName, ?string $networkCategory, ?string $networkDescription): string
    {
        $parts = [
            "Write a detailed, structured training article teaching beginners how to make money using the {$networkName} affiliate network.",
        ];

        if ($networkCategory) {
            $parts[] = "Network Category: {$networkCategory}";
        }

        if ($networkDescription) {
            $parts[] = "Network Overview: {$networkDescription}";
        }

        $parts[] = "\nThe article must include the following sections:";
        $parts[] = "1. **Introduction to {$networkName}** - What the network is, what products/services it offers, and why it's valuable for affiliates.";
        $parts[] = "2. **How Commissions and Conversions Work** - Explain the commission structure, conversion types (CPC, CPA, CPS, etc.), payout terms, and how affiliates earn money.";
        $parts[] = "3. **Common Mistakes Beginners Make** - List 5-7 common pitfalls and how to avoid them, with actionable advice.";
        $parts[] = "4. **How Affiliate AI Automates the Process** - Explain how our platform simplifies affiliate marketing with {$networkName}, highlighting features like landing page generation, tracking, and campaign management.";
        $parts[] = "5. **Step-by-Step Guide to Setting Up a Campaign** - A detailed walkthrough (numbered steps) showing users how to:";
        $parts[] = "   - Register and get approved on {$networkName}";
        $parts[] = "   - Find the right offers/products to promote";
        $parts[] = "   - Use Affiliate AI to create a landing page";
        $parts[] = "   - Launch and track their campaign";
        $parts[] = "6. **Pro Tips for Maximizing Results** - Advanced strategies, best practices, and optimization tips (5-7 actionable tips).";
        $parts[] = "\nEnd with a motivational closing paragraph that encourages readers to take action and start earning.";
        $parts[] = "\n**Important:** Format the entire output in Markdown. Use proper headings (## for main sections, ### for subsections), bullet points for lists, and bold text for emphasis. Make it engaging and easy to follow.";

        return implode("\n\n", $parts);
    }

    /**
     * Generate title for training module
     */
    public function generateTitle(string $networkName): string
    {
        return "{$networkName} Affiliate Training";
    }
}

