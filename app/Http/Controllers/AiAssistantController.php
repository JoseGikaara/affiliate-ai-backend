<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    public function __construct(private CreditService $creditService)
    {
    }

    public function index(): View
    {
        return view('dashboard.ai-assistant');
    }

    public function generatePlan(Request $request): RedirectResponse
    {
        $supportedNetworks = \App\Models\LandingPage::getSupportedNetworks();
        
        $validated = $request->validate([
            'affiliate_network' => ['required', 'string', 'in:' . implode(',', $supportedNetworks)],
            'niche' => ['required', 'string', 'max:200'],
            'affiliate_link' => ['required', 'url', 'max:500'],
        ]);

        $user = Auth::user();
        $cost = 2;

        if (!$this->creditService->hasEnoughCredits($user, $cost)) {
            return back()->withInput()->with('error', "You don’t have enough credits to generate a plan. Please top up.");
        }

        $apiKey = Config::get('services.openai.api_key');
        if (!$apiKey) {
            return back()->withInput()->with('error', 'OpenAI API key is not configured.');
        }

        $prompt = $this->buildPrompt($validated['affiliate_network'], $validated['niche'], $validated['affiliate_link']);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an expert affiliate marketing assistant that creates concise, actionable multi-channel marketing plans.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                ]);

            if (!$response->successful()) {
                $message = $response->json('error.message') ?? 'Failed to generate plan. Please try again.';
                return back()->withInput()->with('error', $message);
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content'));
            if ($content === '') {
                return back()->withInput()->with('error', 'The AI returned an empty response. Please try again.');
            }

            $project = Project::create([
                'user_id' => $user->id,
                'name' => 'AI Plan - ' . $validated['niche'] . ' (' . $validated['affiliate_network'] . ')',
                'description' => $content,
                'affiliate_network' => $validated['affiliate_network'],
                'affiliate_link' => $validated['affiliate_link'],
                'status' => 'draft',
            ]);

            $this->creditService->deductCredits($user, $cost, 'AI plan generation');

            return redirect()->route('ai-assistant.index')->with([
                'success' => 'Your marketing plan has been generated.',
                'generated_plan' => $content,
                'generated_meta' => [
                    'affiliate_network' => $validated['affiliate_network'],
                    'niche' => $validated['niche'],
                    'affiliate_link' => $validated['affiliate_link'],
                    'project_id' => $project->id,
                ],
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'OpenAI request failed: ' . $e->getMessage());
        }
    }

    private function buildPrompt(string $network, string $niche, string $link): string
    {
        return <<<PROMPT
Create a concise, actionable affiliate marketing plan for the following:

- Affiliate Network: {$network}
- Niche / Product Type: {$niche}
- Affiliate Link: {$link}

Deliverables:
- Campaign summary and positioning
- Target audience and angles
- 10 content ideas (titles + one-liners)
- Short-form scripts (2 examples)
- SEO blog outline (H2/H3)
- Email sequence (3 emails, short)
- Promotion checklist

Constraints:
- Keep it practical and formatted with headings and bullet points
- Avoid fluff, make it easy to execute this week
PROMPT;
    }
}


