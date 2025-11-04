<?php

namespace App\Services;

use App\Models\AffiliateNetwork;
use App\Models\LandingPage;
use App\Models\MarketingAsset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MarketingService
{
    /**
     * Generate landing page content using AI
     */
    public function generateLandingPageContent(
        AffiliateNetwork $network,
        string $goal,
        string $affiliateLink,
        ?string $extraContext = null
    ): string {
        $apiKey = Config::get('services.openai.api_key');
        
        if (!$apiKey) {
            throw new \Exception('OpenAI API key is not configured.');
        }

        $prompt = $this->buildLandingPagePrompt($network, $goal, $affiliateLink, $extraContext);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert landing page copywriter and web designer. Generate complete, production-ready HTML landing pages optimized for affiliate marketing conversions. Include modern CSS styling inline, and make sure all affiliate links are properly embedded. The page should be mobile-responsive and conversion-optimized. Output only HTML code, no markdown, no explanations.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Failed to generate landing page content.';
            throw new \Exception('AI generation failed: ' . $error);
        }

        $aiContent = trim((string) data_get($response->json(), 'choices.0.message.content'));

        if (empty($aiContent)) {
            throw new \Exception('AI returned empty content.');
        }

        // Extract HTML content (handle markdown code blocks if present)
        if (preg_match('/```html\s*(.*?)\s*```/s', $aiContent, $matches)) {
            $htmlContent = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $aiContent, $matches)) {
            $htmlContent = $matches[1];
        } else {
            $htmlContent = $aiContent;
        }

        // Sanitize HTML (basic sanitization)
        $htmlContent = $this->sanitizeHtml($htmlContent);

        // Ensure affiliate link is properly embedded
        $htmlContent = $this->embedAffiliateLink($htmlContent, $affiliateLink);

        return $htmlContent;
    }

    /**
     * Generate ad copy using AI
     */
    public function generateAdCopy(AffiliateNetwork $network, string $goal, string $affiliateLink): string
    {
        $apiKey = Config::get('services.openai.api_key');
        
        if (!$apiKey) {
            throw new \Exception('OpenAI API key is not configured.');
        }

        $prompt = $this->buildAdCopyPrompt($network, $goal, $affiliateLink);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert copywriter specializing in affiliate marketing ad copy. Generate compelling, conversion-focused ad copy that drives clicks and conversions.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.8,
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Failed to generate ad copy.';
            throw new \Exception('AI generation failed: ' . $error);
        }

        return trim((string) data_get($response->json(), 'choices.0.message.content'));
    }

    /**
     * Generate email follow-ups using AI
     */
    public function generateEmailFollowUps(AffiliateNetwork $network, string $goal, string $affiliateLink): array
    {
        $apiKey = Config::get('services.openai.api_key');
        
        if (!$apiKey) {
            throw new \Exception('OpenAI API key is not configured.');
        }

        $prompt = $this->buildEmailSeriesPrompt($network, $goal, $affiliateLink);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert email marketing strategist. Generate a series of follow-up emails optimized for affiliate marketing conversions. Return a JSON array with objects containing "subject", "body", and "send_day" fields.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.8,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Failed to generate email series.';
            throw new \Exception('AI generation failed: ' . $error);
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content'));
        $emailData = json_decode($content, true);

        if (!is_array($emailData) || !isset($emailData['emails'])) {
            // Fallback: create a simple array structure
            return [
                [
                    'subject' => "Don't miss out on {$network->name}",
                    'body' => "Hi there! We noticed you're interested in {$network->name}. Here's your exclusive link: {$affiliateLink}",
                    'send_day' => 1,
                ],
            ];
        }

        return $emailData['emails'];
    }

    /**
     * Build landing page prompt
     */
    protected function buildLandingPagePrompt(
        AffiliateNetwork $network,
        string $goal,
        string $affiliateLink,
        ?string $extraContext = null
    ): string {
        $context = $extraContext ? "\n\nAdditional context: {$extraContext}" : '';

        return <<<PROMPT
Generate a complete, production-ready HTML landing page for {$network->name} affiliate marketing.

Requirements:
1. Network: {$network->name} ({$network->category})
2. Campaign Goal: {$goal}
3. Affiliate Link: {$affiliateLink}
4. The page must be mobile-responsive
5. Include modern, conversion-optimized CSS (inline)
6. Use a clear call-to-action button with the affiliate link
7. Include compelling headline, benefits, social proof, and strong CTA
8. The HTML should be complete and ready to deploy
{$context}

Output the complete HTML document only (no markdown, no explanations, just the HTML code).
The affiliate link should be in a prominent CTA button: <a href="{$affiliateLink}" class="cta-button">Get Started / Sign Up / Buy Now</a>

Make it visually appealing, conversion-focused, and network-appropriate.
PROMPT;
    }

    /**
     * Build ad copy prompt
     */
    protected function buildAdCopyPrompt(AffiliateNetwork $network, string $goal, string $affiliateLink): string
    {
        return <<<PROMPT
Generate compelling ad copy for {$network->name} affiliate marketing.

Network: {$network->name} ({$network->category})
Campaign Goal: {$goal}
Affiliate Link: {$affiliateLink}

Create multiple ad copy variations (3-5) with different angles:
- Benefit-focused
- Urgency/scarcity
- Social proof
- Problem-solution

Each variation should be 50-100 words and include a strong call-to-action.
PROMPT;
    }

    /**
     * Build email series prompt
     */
    protected function buildEmailSeriesPrompt(AffiliateNetwork $network, string $goal, string $affiliateLink): string
    {
        return <<<PROMPT
Generate a series of 3-5 follow-up emails for {$network->name} affiliate marketing.

Network: {$network->name} ({$network->category})
Campaign Goal: {$goal}
Affiliate Link: {$affiliateLink}

Return a JSON object with an "emails" array. Each email should have:
- "subject": Email subject line
- "body": Email body (HTML or plain text)
- "send_day": Day to send (1, 3, 7, 14, 30)

Make the emails engaging, value-driven, and conversion-focused.
PROMPT;
    }

    /**
     * Embed affiliate link into HTML content
     */
    protected function embedAffiliateLink(string $htmlContent, string $affiliateLink): string
    {
        // Replace placeholder or ensure affiliate link is present in all CTAs
        $htmlContent = preg_replace(
            '/href=["\']#["\']/i',
            'href="' . htmlspecialchars($affiliateLink, ENT_QUOTES, 'UTF-8') . '"',
            $htmlContent
        );

        // If no CTA buttons found, add one at the end of body
        if (stripos($htmlContent, $affiliateLink) === false) {
            $ctaButton = <<<HTML
<div style="text-align: center; padding: 40px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin-top: 40px;">
    <a href="{$affiliateLink}" style="display: inline-block; background: white; color: #667eea; padding: 18px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 18px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        Get Started Now
    </a>
</div>
HTML;
            // Insert before closing </body> tag
            $htmlContent = preg_replace('/<\/body>/i', $ctaButton . "\n</body>", $htmlContent);
        }

        return $htmlContent;
    }

    /**
     * Sanitize HTML content (basic sanitization)
     */
    protected function sanitizeHtml(string $html): string
    {
        // Remove script tags and dangerous attributes
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi', '', $html);
        $html = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        
        // Allow safe HTML tags
        $allowedTags = '<div><section><article><header><footer><main><h1><h2><h3><h4><h5><h6><p><span><a><img><button><ul><ol><li><strong><em><b><i><br><hr><style>';
        
        // Keep basic structure, strip dangerous content
        return strip_tags($html, $allowedTags);
    }

    /**
     * Save marketing assets for a landing page
     */
    public function saveMarketingAssets(LandingPage $landingPage, array $assets): void
    {
        foreach ($assets as $asset) {
            MarketingAsset::create([
                'landing_page_id' => $landingPage->id,
                'type' => $asset['type'] ?? 'ad_copy',
                'content' => $asset['content'] ?? '',
                'meta' => $asset['meta'] ?? null,
            ]);
        }
    }
}

