<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateNetwork;
use App\Models\LandingPage;
use App\Models\LandingPageAnalytic;
use App\Notifications\LandingPagePublishedNotification;
use App\Notifications\LowCreditsWarningNotification;
use App\Services\CreditService;
use App\Services\LandingPageService;
use App\Services\MarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class LandingPageController extends Controller
{
    public function __construct(
        protected CreditService $creditService,
        protected LandingPageService $landingPageService,
        protected MarketingService $marketingService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = LandingPage::with(['user', 'project', 'affiliateNetwork'])
            ->where('user_id', $user->id)
            ->latest();

        $landingPages = $query->get()->map(function ($page) {
            return $this->formatLandingPage($page);
        });

        return response()->json($landingPages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'content' => ['nullable', 'string'],
                'project_id' => ['nullable', 'exists:projects,id'],
                'affiliate_network_id' => ['nullable', 'exists:affiliate_networks,id'],
                'affiliate_link' => ['nullable', 'url', 'max:500'],
                'campaign_goal' => ['nullable', 'string', 'in:sales,signups,leads'],
                'type' => ['sometimes', 'string', 'in:template,ai-generated'],
                'metadata' => ['nullable', 'array'],
            ]);

            $user = $request->user();
            
            // Generate unique subdomain
            $subdomain = $this->landingPageService->generateSubdomain(
                $user,
                $validated['title']
            );

            $landingPage = LandingPage::create([
                'user_id' => $user->id,
                'project_id' => $validated['project_id'] ?? null,
                'title' => $validated['title'],
                'content' => $validated['content'] ?? null,
                'subdomain' => $subdomain,
                'type' => $validated['type'] ?? 'template',
                'metadata' => $validated['metadata'] ?? null,
                'status' => 'pending',
                'credit_cost' => config('app.landing_page_credit_cost', 1),
            ]);

            return response()->json([
                'message' => 'Landing page created successfully',
                'landing_page' => $this->formatLandingPage($landingPage->fresh(['user', 'project', 'affiliateNetwork'])),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create landing page',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Ensure user owns this landing page
        if ($landingPage->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->formatLandingPage($landingPage->load(['user', 'project', 'affiliateNetwork'])));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Ensure user owns this landing page
        if ($landingPage->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Cannot update if active (must unpublish first)
        if ($landingPage->status === 'active') {
            return response()->json([
                'message' => 'Cannot update active landing page. Please unpublish it first.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'type' => ['sometimes', 'string', 'in:template,ai-generated'],
            'metadata' => ['nullable', 'array'],
        ]);

        $landingPage->update($validated);

        return response()->json([
            'message' => 'Landing page updated successfully',
            'landing_page' => $this->formatLandingPage($landingPage->fresh(['user', 'project'])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Ensure user owns this landing page
        if ($landingPage->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Undeploy if active
        if ($landingPage->status === 'active') {
            $this->landingPageService->undeploy($landingPage);
        }

        $landingPage->delete();

        return response()->json(['message' => 'Landing page deleted successfully']);
    }

    /**
     * Publish a landing page (deducts credits)
     */
    public function publish(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Ensure user owns this landing page
        if ($landingPage->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = $request->user();
        $creditCost = $landingPage->credit_cost;

        // Check if user has enough credits
        if (!$this->creditService->hasEnoughCredits($user, $creditCost)) {
            throw ValidationException::withMessages([
                'credits' => ['Insufficient credits. You need ' . $creditCost . ' credits to publish this landing page.'],
            ]);
        }

        DB::transaction(function () use ($landingPage, $user, $creditCost) {
            // Deduct credits
            $this->creditService->deductCredits(
                $user,
                $creditCost,
                "Published landing page: {$landingPage->title}"
            );

            // Update landing page status
            $landingPage->update([
                'status' => 'active',
                'expires_at' => now()->addDays(30),
            ]);

            // Deploy the landing page
            $this->landingPageService->deploy($landingPage);
        });

        // Send notification
        $user->notify(new LandingPagePublishedNotification($landingPage->fresh()));

        return response()->json([
            'message' => 'Landing page published successfully',
            'landing_page' => $this->formatLandingPage($landingPage->fresh(['user', 'project'])),
            'remaining_credits' => $user->fresh()->credits,
        ]);
    }

    /**
     * Unpublish a landing page
     */
    public function unpublish(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Ensure user owns this landing page
        if ($landingPage->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($landingPage->status !== 'active') {
            return response()->json([
                'message' => 'Landing page is not active',
            ], 422);
        }

        DB::transaction(function () use ($landingPage) {
            // Undeploy the landing page
            $this->landingPageService->undeploy($landingPage);

            // Update status
            $landingPage->update([
                'status' => 'paused',
                'expires_at' => null,
            ]);
        });

        return response()->json([
            'message' => 'Landing page unpublished successfully',
            'landing_page' => $this->formatLandingPage($landingPage->fresh(['user', 'project'])),
        ]);
    }

    /**
     * Renew a landing page (extend expiry by 30 days)
     */
    public function renew(Request $request, LandingPage $landingPage): JsonResponse
    {
        try {
            // Ensure user owns this landing page
            if ($landingPage->user_id !== $request->user()->id && !$request->user()->is_admin) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if ($landingPage->status !== 'active') {
                return response()->json([
                    'message' => 'Landing page must be active to renew',
                ], 422);
            }

            $user = $request->user();
            
            // Calculate renewal cost based on network category
            $network = $landingPage->affiliateNetwork;
            $creditCost = $this->creditService->calculateRenewalCost($network);

            // Validate credit cost
            if ($creditCost <= 0) {
                return response()->json([
                    'message' => 'Invalid credit cost for renewal',
                ], 422);
            }

            // Check if user has enough credits
            if (!$this->creditService->hasEnoughCredits($user, $creditCost)) {
                // Send low credits warning notification
                $user->notify(new LowCreditsWarningNotification(
                    $creditCost,
                    $user->credits,
                    $landingPage->title
                ));
                
                throw ValidationException::withMessages([
                    'credits' => ['Insufficient credits. You need ' . $creditCost . ' credits to renew this landing page.'],
                ]);
            }

            DB::transaction(function () use ($landingPage, $user, $creditCost) {
                // Deduct credits
                $this->creditService->deductCredits(
                    $user,
                    $creditCost,
                    "Renewed landing page: {$landingPage->title}"
                );

                // Extend expiry date by 30 days
                $newExpiresAt = $landingPage->expires_at 
                    ? $landingPage->expires_at->copy()->addDays(30)
                    : now()->addDays(30);

                $landingPage->update([
                    'expires_at' => $newExpiresAt,
                    'next_renewal_date' => $newExpiresAt,
                    'last_renewal_date' => now(),
                    'credits_used' => ($landingPage->credits_used ?? 0) + $creditCost,
                ]);
            });

            return response()->json([
                'message' => 'Landing page renewed successfully',
                'landing_page' => $this->formatLandingPage($landingPage->fresh(['user', 'project', 'affiliateNetwork'])),
                'remaining_credits' => $user->fresh()->credits,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to renew landing page',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle auto-renew for a landing page
     */
    public function toggleAutoRenew(Request $request, LandingPage $landingPage): JsonResponse
    {
        try {
            // Ensure user owns this landing page or is admin
            if ($landingPage->user_id !== $request->user()->id && !$request->user()->is_admin) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $validated = $request->validate([
                'auto_renew' => ['required', 'boolean'],
            ]);

            // Only allow auto-renew on active pages
            if ($validated['auto_renew'] && $landingPage->status !== 'active') {
                return response()->json([
                    'message' => 'Auto-renew can only be enabled for active landing pages',
                ], 422);
            }

            $landingPage->update(['auto_renew' => $validated['auto_renew']]);

            return response()->json([
                'message' => 'Auto-renew ' . ($validated['auto_renew'] ? 'enabled' : 'disabled'),
                'landing_page' => $this->formatLandingPage($landingPage->fresh(['user', 'project', 'affiliateNetwork'])),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to toggle auto-renew',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format landing page for JSON response
     */
    protected function formatLandingPage(LandingPage $landingPage): array
    {
        // Calculate days until renewal
        $daysUntilRenewal = null;
        if ($landingPage->next_renewal_date) {
            $daysUntilRenewal = max(0, now()->diffInDays($landingPage->next_renewal_date, false));
        }

        // Calculate is due for renewal (within 3 days or past due)
        $isDueForRenewal = false;
        if ($landingPage->next_renewal_date 
            && $landingPage->auto_renew
            && $landingPage->status === 'active') {
            $daysUntil = now()->diffInDays($landingPage->next_renewal_date, false);
            $isDueForRenewal = $daysUntil <= 3;
        }

        return [
            'id' => $landingPage->id,
            'affiliate_network_id' => $landingPage->affiliate_network_id,
            'network' => $landingPage->network,
            'affiliate_link' => $landingPage->affiliate_link,
            'title' => $landingPage->title,
            'content' => $landingPage->content,
            'html_content' => $landingPage->html_content,
            'ad_copy' => $landingPage->ad_copy,
            'email_series' => $landingPage->email_series,
            'campaign_goal' => $landingPage->campaign_goal,
            'subdomain' => $landingPage->subdomain,
            'domain' => $landingPage->domain,
            'url' => $this->landingPageService->getUrl($landingPage),
            'status' => $landingPage->status,
            'expires_at' => $landingPage->expires_at?->toISOString(),
            'credit_cost' => $landingPage->credit_cost,
            'setup_credits' => $landingPage->setup_credits,
            'renewal_credits' => $landingPage->renewal_credits,
            'credits_used' => $landingPage->credits_used,
            'views' => $landingPage->views,
            'conversions' => $landingPage->conversions,
            'type' => $landingPage->type,
            'ai_template_type' => $landingPage->ai_template_type,
            'metadata' => $landingPage->metadata,
            'auto_renew' => $landingPage->auto_renew,
            'next_renewal_date' => $landingPage->next_renewal_date?->toISOString(),
            'last_renewal_date' => $landingPage->last_renewal_date?->toISOString(),
            'days_until_renewal' => $daysUntilRenewal,
            'is_due_for_renewal' => $isDueForRenewal,
            'project_id' => $landingPage->project_id,
            'project' => $landingPage->project ? [
                'id' => $landingPage->project->id,
                'name' => $landingPage->project->name,
            ] : null,
            'affiliate_network' => $landingPage->affiliateNetwork ? [
                'id' => $landingPage->affiliateNetwork->id,
                'name' => $landingPage->affiliateNetwork->name,
                'slug' => $landingPage->affiliateNetwork->slug,
                'category' => $landingPage->affiliateNetwork->category,
                'logo_url' => $landingPage->affiliateNetwork->logo_url,
            ] : null,
            'user' => [
                'id' => $landingPage->user->id,
                'name' => $landingPage->user->name,
                'email' => $landingPage->user->email,
            ],
            'created_at' => $landingPage->created_at->toISOString(),
            'updated_at' => $landingPage->updated_at->toISOString(),
            'ctr' => $landingPage->ctr,
        ];
    }

    /**
     * Track a view for a landing page (IP-based deduplication)
     */
    public function trackView(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Only track if page is active
        if ($landingPage->status !== 'active') {
            return response()->json(['message' => 'Landing page is not active'], 422);
        }

        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        $referer = $request->header('referer');

        // Check if this IP already viewed this page in the last 24 hours
        $recentView = LandingPageAnalytic::where('landing_page_id', $landingPage->id)
            ->where('event_type', 'view')
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if (!$recentView) {
            // Record the view
            LandingPageAnalytic::create([
                'landing_page_id' => $landingPage->id,
                'event_type' => 'view',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'referer' => $referer,
            ]);

            // Increment views counter
            $landingPage->increment('views');
        }

        return response()->json(['message' => 'View tracked', 'tracked' => !$recentView]);
    }

    /**
     * Track a conversion for a landing page
     */
    public function trackConversion(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Only track if page is active
        if ($landingPage->status !== 'active') {
            return response()->json(['message' => 'Landing page is not active'], 422);
        }

        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        $referer = $request->header('referer');
        $metadata = $request->get('metadata', []);

        // Record the conversion
        LandingPageAnalytic::create([
            'landing_page_id' => $landingPage->id,
            'event_type' => 'conversion',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'metadata' => $metadata,
        ]);

        // Increment conversions counter
        $landingPage->increment('conversions');

        return response()->json(['message' => 'Conversion tracked']);
    }

    /**
     * Show analytics for a landing page
     */
    public function showAnalytics(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Ensure user owns this landing page
        if ($landingPage->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $days = (int) $request->get('days', 30);
        $startDate = now()->subDays($days);

        // Get daily views and conversions
        $dailyStats = LandingPageAnalytic::where('landing_page_id', $landingPage->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, event_type, COUNT(*) as count')
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($dayStats) {
                return [
                    'date' => $dayStats->first()->date,
                    'views' => $dayStats->where('event_type', 'view')->sum('count'),
                    'conversions' => $dayStats->where('event_type', 'conversion')->sum('count'),
                ];
            })
            ->values();

        // Get top 5 pages by CTR (for comparison)
        $topPages = LandingPage::where('user_id', $landingPage->user_id)
            ->where('status', '!=', 'pending')
            ->get()
            ->sortByDesc('ctr')
            ->take(5)
            ->map(function ($page) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'ctr' => $page->ctr,
                    'views' => $page->views,
                    'conversions' => $page->conversions,
                ];
            })
            ->values();

        // Conversion funnel
        $totalViews = $landingPage->views;
        $totalConversions = $landingPage->conversions;
        $conversionRate = $totalViews > 0 ? round(($totalConversions / $totalViews) * 100, 2) : 0;

        return response()->json([
            'landing_page' => [
                'id' => $landingPage->id,
                'title' => $landingPage->title,
                'views' => $landingPage->views,
                'conversions' => $landingPage->conversions,
                'ctr' => $landingPage->ctr,
            ],
            'daily_stats' => $dailyStats,
            'top_pages' => $topPages,
            'conversion_funnel' => [
                'views' => $totalViews,
                'conversions' => $totalConversions,
                'conversion_rate' => $conversionRate,
                'drop_off' => $totalViews - $totalConversions,
            ],
        ]);
    }

    /**
     * Get leaderboard (top affiliates by CTR/conversions)
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $type = $request->get('type', 'ctr'); // 'ctr' or 'conversions'
        $limit = (int) $request->get('limit', 10);

        $query = LandingPage::with('user')
            ->where('status', '!=', 'pending')
            ->where('views', '>', 0) // Only pages with views
            ->get()
            ->groupBy('user_id')
            ->map(function ($pages) {
                $totalViews = $pages->sum('views');
                $totalConversions = $pages->sum('conversions');
                $avgCtr = $totalViews > 0 ? round(($totalConversions / $totalViews) * 100, 2) : 0;
                
                return [
                    'user' => [
                        'id' => $pages->first()->user->id,
                        'name' => $pages->first()->user->name,
                        'email' => $pages->first()->user->email,
                    ],
                    'total_views' => $totalViews,
                    'total_conversions' => $totalConversions,
                    'avg_ctr' => $avgCtr,
                    'page_count' => $pages->count(),
                ];
            })
            ->values();

        // Sort by type
        if ($type === 'ctr') {
            $query = $query->sortByDesc('avg_ctr');
        } else {
            $query = $query->sortByDesc('total_conversions');
        }

        return response()->json([
            'type' => $type,
            'leaderboard' => $query->take($limit)->values(),
        ]);
    }

    /**
     * Generate AI-powered landing page from network and affiliate link
     */
    public function generateLandingPage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'affiliate_network_id' => ['required', 'exists:affiliate_networks,id'],
            'affiliate_link' => ['required', 'url', 'max:500'],
            'campaign_goal' => ['required', 'string', 'in:sales,signups,leads'],
            'title' => ['sometimes', 'string', 'max:255'],
            'extra_context' => ['nullable', 'string', 'max:1000'],
            'with_email_automation' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $affiliateNetwork = AffiliateNetwork::findOrFail($validated['affiliate_network_id']);
        $affiliateLink = $validated['affiliate_link'];
        $goal = $validated['campaign_goal'];
        $withEmailAutomation = $validated['with_email_automation'] ?? false;

        // Validate affiliate link matches network base URL
        if ($affiliateNetwork->base_url && !$this->validateAffiliateLink($affiliateLink, $affiliateNetwork->base_url)) {
            throw ValidationException::withMessages([
                'affiliate_link' => ['The affiliate link does not match the selected network.'],
            ]);
        }

        // Calculate credit cost
        $setupCredits = $this->creditService->calculateLandingPageCost($affiliateNetwork, $withEmailAutomation);
        $renewalCredits = $this->creditService->calculateRenewalCost($affiliateNetwork);

        // Check if user has enough credits
        if (!$this->creditService->hasEnoughCredits($user, $setupCredits)) {
            throw ValidationException::withMessages([
                'credits' => [
                    "Insufficient credits. You need {$setupCredits} credits to generate this landing page. " .
                    "You currently have {$user->credits} credits."
                ],
            ]);
        }

        // Generate title if not provided
        $title = $validated['title'] ?? "{$affiliateNetwork->name} Affiliate Landing Page";
        
        // Generate unique subdomain
        $subdomain = $this->landingPageService->generateSubdomain($user, $title);

        try {
            // Generate AI content using MarketingService
            $htmlContent = $this->marketingService->generateLandingPageContent(
                $affiliateNetwork,
                $goal,
                $affiliateLink,
                $validated['extra_context'] ?? null
            );

            // Generate ad copy and email series if email automation is enabled
            $adCopy = null;
            $emailSeries = null;
            
            if ($withEmailAutomation) {
                $adCopy = $this->marketingService->generateAdCopy($affiliateNetwork, $goal, $affiliateLink);
                $emailSeries = $this->marketingService->generateEmailFollowUps($affiliateNetwork, $goal, $affiliateLink);
            }

            DB::beginTransaction();

            try {
                // Deduct setup credits
                $this->creditService->deductCredits(
                    $user,
                    $setupCredits,
                    "Generated landing page for {$affiliateNetwork->name}"
                );

                // Create landing page
                $landingPage = LandingPage::create([
                    'user_id' => $user->id,
                    'affiliate_network_id' => $affiliateNetwork->id,
                    'network' => $affiliateNetwork->slug,
                    'affiliate_link' => $affiliateLink,
                    'title' => $title,
                    'content' => $htmlContent,
                    'html_content' => $htmlContent,
                    'ad_copy' => $adCopy,
                    'email_series' => $emailSeries,
                    'campaign_goal' => $goal,
                    'subdomain' => $subdomain,
                    'type' => 'ai-generated',
                    'status' => 'active',
                    'credit_cost' => $renewalCredits,
                    'setup_credits' => $setupCredits,
                    'renewal_credits' => $renewalCredits,
                    'credits_used' => $setupCredits,
                    'expires_at' => now()->addDays(30),
                    'next_renewal_date' => now()->addDays(30),
                    'auto_renew' => true,
                ]);

                // Save marketing assets
                if ($adCopy) {
                    $this->marketingService->saveMarketingAssets($landingPage, [
                        ['type' => 'ad_copy', 'content' => $adCopy],
                    ]);
                }

                if ($emailSeries) {
                    foreach ($emailSeries as $email) {
                        $this->marketingService->saveMarketingAssets($landingPage, [
                            ['type' => 'email', 'content' => $email['body'], 'meta' => ['subject' => $email['subject'], 'send_day' => $email['send_day'] ?? null]],
                        ]);
                    }
                }

                // Deploy the landing page
                $this->landingPageService->deploy($landingPage);

                DB::commit();

                // Send notification
                $user->notify(new LandingPagePublishedNotification($landingPage->fresh()));

                return response()->json([
                    'message' => 'Landing page generated and published successfully',
                    'landing_page' => $this->formatLandingPage($landingPage->fresh(['user', 'project', 'affiliateNetwork'])),
                    'remaining_credits' => $user->fresh()->credits,
                    'setup_credits' => $setupCredits,
                    'renewal_credits' => $renewalCredits,
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate landing page',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate affiliate link matches network base URL
     */
    protected function validateAffiliateLink(string $link, string $baseUrl): bool
    {
        $linkHost = parse_url($link, PHP_URL_HOST);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        
        if (!$linkHost || !$baseHost) {
            return false;
        }

        // Allow exact match or subdomain match
        return $linkHost === $baseHost || str_ends_with($linkHost, '.' . $baseHost);
    }

    /**
     * Build AI prompt for landing page generation
     */
    protected function buildLandingPagePrompt(
        string $network,
        string $affiliateLink,
        string $templateType,
        ?string $extraContext = null
    ): string {
        $context = $extraContext ? "\n\nAdditional context: {$extraContext}" : '';

        return <<<PROMPT
Generate a complete, production-ready HTML landing page for {$network} affiliate marketing.

Requirements:
1. Network: {$network}
2. Template Type: {$templateType}
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
     * Download landing page assets as ZIP
     */
    public function downloadAssets(Request $request, LandingPage $landingPage): JsonResponse
    {
        // Ensure user owns this landing page or is admin
        if ($landingPage->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $assets = [
            'html_content' => $landingPage->html_content,
            'ad_copy' => $landingPage->ad_copy,
            'email_series' => $landingPage->email_series,
        ];

        // Create a simple JSON response with all assets
        // In production, you might want to create an actual ZIP file
        return response()->json([
            'message' => 'Assets prepared for download',
            'assets' => $assets,
        ]);
    }
}
