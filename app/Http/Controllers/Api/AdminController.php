<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\BillingLog;
use App\Models\CreditTransaction;
use App\Models\LandingPage;
use App\Models\Offer;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // ========== USER MANAGEMENT ==========
    
    public function users(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $users = User::select('id', 'name', 'email', 'credits', 'is_admin', 'status', 'created_at')
            ->latest()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'credits' => $user->credits,
                    'is_admin' => $user->is_admin,
                    'status' => $user->status ?? 'active',
                    'created_at' => $user->created_at->toISOString(),
                ];
            });

        return response()->json($users);
    }

    public function createUser(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $validated['is_admin'] ?? false,
            'status' => $validated['status'] ?? 'active',
            'credits' => 0,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'status' => $user->status,
                'created_at' => $user->created_at->toISOString(),
            ],
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'status' => $user->status ?? 'active',
            ],
        ]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete yourself'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function updateCredits(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
        ]);

        $amount = (int) $validated['amount'];

        $user->increment('credits', $amount);
        CreditTransaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => $amount >= 0 ? 'addition' : 'deduction',
            'description' => $validated['description'] ?? 'Admin adjustment',
        ]);

        return response()->json([
            'message' => 'Credits updated',
            'user' => $user->fresh(['id', 'name', 'email', 'credits']),
        ]);
    }

    // ========== AFFILIATE MANAGEMENT ==========

    public function affiliates(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $affiliates = Affiliate::with('user')
            ->latest()
            ->get()
            ->map(function ($affiliate) {
                return [
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'email' => $affiliate->email,
                    'commission_rate' => (float) $affiliate->commission_rate,
                    'total_clicks' => $affiliate->total_clicks,
                    'total_leads' => $affiliate->total_leads,
                    'total_conversions' => $affiliate->total_conversions,
                    'commission_earned' => (float) $affiliate->commission_earned,
                    'status' => $affiliate->status,
                    'created_at' => $affiliate->created_at->toISOString(),
                ];
            });

        return response()->json($affiliates);
    }

    public function updateAffiliate(Request $request, Affiliate $affiliate): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $affiliate->update($validated);

        return response()->json([
            'message' => 'Affiliate updated successfully',
            'affiliate' => $affiliate->fresh(),
        ]);
    }

    public function updateAffiliateStatus(Request $request, Affiliate $affiliate): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $affiliate->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Affiliate status updated successfully',
            'affiliate' => $affiliate->fresh(),
        ]);
    }

    public function deleteAffiliate(Request $request, Affiliate $affiliate): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $affiliate->delete();

        return response()->json(['message' => 'Affiliate deleted successfully']);
    }

    // ========== OFFER MANAGEMENT ==========

    public function offers(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $offers = Offer::latest()->get();

        return response()->json($offers);
    }

    public function createOffer(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'payout' => ['required', 'numeric', 'min:0'],
            'conversion_type' => ['required', 'string', 'in:CPL,CPA,CPS'],
            'image_url' => ['nullable', 'url'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $offer = Offer::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'payout' => $validated['payout'],
            'conversion_type' => $validated['conversion_type'],
            'image_url' => $validated['image_url'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Offer created successfully',
            'offer' => $offer,
        ], 201);
    }

    public function updateOffer(Request $request, Offer $offer): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'payout' => ['sometimes', 'numeric', 'min:0'],
            'conversion_type' => ['sometimes', 'string', 'in:CPL,CPA,CPS'],
            'image_url' => ['nullable', 'url'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $offer->update($validated);

        return response()->json([
            'message' => 'Offer updated successfully',
            'offer' => $offer->fresh(),
        ]);
    }

    public function updateOfferStatus(Request $request, Offer $offer): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $offer->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Offer status updated successfully',
            'offer' => $offer->fresh(),
        ]);
    }

    public function deleteOffer(Request $request, Offer $offer): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $offer->delete();

        return response()->json(['message' => 'Offer deleted successfully']);
    }

    // ========== PAYOUT MANAGEMENT ==========

    public function payments(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payouts = PayoutRequest::with('affiliate')
            ->latest()
            ->get()
            ->map(function ($payout) {
                return [
                    'id' => $payout->id,
                    'affiliate_id' => $payout->affiliate_id,
                    'affiliate_name' => $payout->affiliate->name ?? 'Unknown',
                    'affiliate_email' => $payout->affiliate->email ?? 'Unknown',
                    'amount' => (float) $payout->amount,
                    'payment_method' => $payout->payment_method,
                    'status' => $payout->status,
                    'notes' => $payout->notes,
                    'created_at' => $payout->created_at->toISOString(),
                    'updated_at' => $payout->updated_at->toISOString(),
                ];
            });

        return response()->json($payouts);
    }

    public function createPayment(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'affiliate_id' => ['required', 'exists:affiliates,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:paypal,bank_transfer,check,crypto'],
            'notes' => ['nullable', 'string'],
        ]);

        $payout = PayoutRequest::create([
            'affiliate_id' => $validated['affiliate_id'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Payout request created successfully',
            'payout' => $payout->load('affiliate'),
        ], 201);
    }

    public function updatePayment(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $payoutRequest->update($validated);

        return response()->json([
            'message' => 'Payout updated successfully',
            'payout' => $payoutRequest->fresh()->load('affiliate'),
        ]);
    }

    // ========== ANALYTICS ==========

    public function dashboardStats(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $totalUsers = User::count();
        $totalAffiliates = Affiliate::count();
        $activeOffers = Offer::where('status', 'active')->count();
        $totalLandingPages = LandingPage::count();
        
        // Total clicks from affiliate links
        $totalClicks = \App\Models\AffiliateLink::sum('total_clicks');
        if (!$totalClicks) {
            $totalClicks = Affiliate::sum('total_clicks');
        }
        
        // Total conversions
        $totalConversions = \App\Models\Conversion::count();
        
        // Total credits issued (from credit transactions - credits added)
        $totalCreditsIssued = abs(CreditTransaction::where('type', 'credit')
            ->orWhere('type', 'addition')
            ->sum('amount'));
        
        // Total credits used (from credit transactions - debits/deductions)
        $totalCreditsUsed = abs(CreditTransaction::where('type', 'debit')
            ->orWhere('type', 'deduction')
            ->sum('amount'));

        return response()->json([
            'total_users' => $totalUsers,
            'total_affiliates' => $totalAffiliates,
            'active_offers' => $activeOffers,
            'total_landing_pages' => $totalLandingPages,
            'total_clicks' => (int) $totalClicks,
            'total_conversions' => $totalConversions,
            'total_credits_issued' => (int) $totalCreditsIssued,
            'total_credits_used' => (int) $totalCreditsUsed,
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Calculate totals
        $totalRevenue = \App\Models\Commission::where('status', 'approved')
            ->sum('amount');
        
        $totalAffiliates = Affiliate::where('status', 'active')->count();
        $totalUsers = User::count();
        $totalConversions = \App\Models\Conversion::count();
        $totalClicks = Affiliate::sum('total_clicks');

        // Revenue trend (last 6 months) from commissions
        $revenueTrend = \App\Models\Commission::where('status', 'approved')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(created_at, "%b") as date, SUM(amount) as revenue')
            ->groupBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'revenue' => (float) $item->revenue,
                ];
            });

        // Daily earnings trend (last 30 days)
        $dailyEarningsTrend = \App\Models\Commission::where('status', 'approved')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as earnings')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'earnings' => (float) $item->earnings,
                ];
            });

        // Top performing affiliates by conversions
        $topAffiliatesByConversions = Affiliate::withCount('conversions')
            ->orderBy('conversions_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($affiliate) {
                return [
                    'name' => $affiliate->name,
                    'conversions' => $affiliate->conversions_count,
                    'earnings' => (float) $affiliate->commission_earned,
                ];
            });

        // Top performing affiliates by revenue
        $topAffiliatesByRevenue = \App\Models\Commission::where('status', 'approved')
            ->selectRaw('affiliate_id, SUM(amount) as total_earnings')
            ->groupBy('affiliate_id')
            ->orderBy('total_earnings', 'desc')
            ->limit(10)
            ->with('affiliate')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->affiliate->name ?? 'Unknown',
                    'earnings' => (float) $item->total_earnings,
                    'conversions' => $item->affiliate->total_conversions ?? 0,
                ];
            });

        // Top performing offers
        $topOffers = \App\Models\Conversion::selectRaw('offer_id, COUNT(*) as conversions, SUM(conversion_value) as revenue')
            ->groupBy('offer_id')
            ->orderBy('conversions', 'desc')
            ->limit(10)
            ->with('offer')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->offer->name ?? 'Unknown',
                    'revenue' => (float) $item->revenue,
                    'conversions' => (int) $item->conversions,
                ];
            });

        // Offer performance heatmap data (offers vs conversion count)
        $offerPerformance = \App\Models\AffiliateLink::selectRaw('offer_id, SUM(total_clicks) as clicks, SUM(total_conversions) as conversions')
            ->groupBy('offer_id')
            ->with('offer')
            ->get()
            ->map(function ($item) {
                return [
                    'offer_name' => $item->offer->name ?? 'Unknown',
                    'clicks' => (int) $item->clicks,
                    'conversions' => (int) $item->conversions,
                    'conversion_rate' => $item->clicks > 0 
                        ? round(($item->conversions / $item->clicks) * 100, 2)
                        : 0,
                ];
            })
            ->sortByDesc('conversions')
            ->values();

        // Conversion rate trend (calculate from actual data)
        $conversionRateTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();
            
            $monthClicks = \App\Models\AffiliateLink::whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total_clicks');
            
            $monthConversions = \App\Models\Conversion::whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
            
            $rate = $monthClicks > 0 ? round(($monthConversions / $monthClicks) * 100, 2) : 0;
            
            $conversionRateTrend[] = [
                'date' => $monthStart->format('M'),
                'rate' => $rate,
            ];
        }

        return response()->json([
            'total_revenue' => (float) $totalRevenue,
            'total_affiliates' => $totalAffiliates,
            'total_users' => $totalUsers,
            'total_conversions' => $totalConversions,
            'total_clicks' => $totalClicks,
            'revenue_trend' => $revenueTrend->values(),
            'daily_earnings_trend' => $dailyEarningsTrend->values(),
            'top_affiliates_by_conversions' => $topAffiliatesByConversions->values(),
            'top_affiliates_by_revenue' => $topAffiliatesByRevenue->values(),
            'top_offers' => $topOffers->values(),
            'offer_performance' => $offerPerformance,
            'conversion_rate_trend' => $conversionRateTrend,
        ]);
    }

    // ========== SETTINGS ==========

    public function getSettings(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            // Get all settings or return defaults
            $settings = [
                'app_name' => Setting::getValue('app_name', 'Affiliate AI SaaS'),
                'logo_url' => Setting::getValue('logo_url', ''),
                'email_settings' => [
                    'system_email' => Setting::getValue('email_system_email', 'noreply@affiliateai.com'),
                    'enable_notifications' => Setting::getValue('email_enable_notifications', 'true') === 'true',
                    'smtp_host' => Setting::getValue('email_smtp_host', ''),
                    'smtp_port' => (int) Setting::getValue('email_smtp_port', '587'),
                    'smtp_username' => Setting::getValue('email_smtp_username', ''),
                    'smtp_password' => Setting::getValue('email_smtp_password', ''),
                ],
                'commission_defaults' => [
                    'default_rate' => (float) Setting::getValue('commission_default_rate', '10'),
                    'minimum_payout' => (float) Setting::getValue('commission_minimum_payout', '50'),
                    'payout_schedule' => Setting::getValue('commission_payout_schedule', 'monthly'),
                ],
                'general' => [
                    'site_url' => Setting::getValue('general_site_url', ''),
                    'maintenance_mode' => Setting::getValue('general_maintenance_mode', 'false') === 'true',
                    'allow_registration' => Setting::getValue('general_allow_registration', 'true') === 'true',
                ],
            ];

            return response()->json($settings);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $validated = $request->validate([
                'app_name' => ['sometimes', 'string', 'max:255'],
                'logo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
                'email_settings' => ['sometimes', 'array'],
                'email_settings.system_email' => ['sometimes', 'email', 'max:255'],
                'email_settings.enable_notifications' => ['sometimes', 'boolean'],
                'email_settings.smtp_host' => ['sometimes', 'nullable', 'string', 'max:255'],
                'email_settings.smtp_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
                'email_settings.smtp_username' => ['sometimes', 'nullable', 'string', 'max:255'],
                'email_settings.smtp_password' => ['sometimes', 'nullable', 'string', 'max:255'],
                'commission_defaults' => ['sometimes', 'array'],
                'commission_defaults.default_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'commission_defaults.minimum_payout' => ['sometimes', 'numeric', 'min:0'],
                'commission_defaults.payout_schedule' => ['sometimes', 'string', 'in:weekly,bi-weekly,monthly'],
                'general' => ['sometimes', 'array'],
                'general.site_url' => ['sometimes', 'nullable', 'url', 'max:500'],
                'general.maintenance_mode' => ['sometimes', 'boolean'],
                'general.allow_registration' => ['sometimes', 'boolean'],
            ]);

            // Save app_name
            if (isset($validated['app_name'])) {
                Setting::setValue('app_name', $validated['app_name']);
            }

            // Save logo_url
            if (isset($validated['logo_url'])) {
                Setting::setValue('logo_url', $validated['logo_url'] ?? '');
            }

            // Save email settings - handle all at once
            if (isset($validated['email_settings'])) {
                $emailSettings = $validated['email_settings'];
                
                Setting::setValue('email_system_email', $emailSettings['system_email'] ?? Setting::getValue('email_system_email', 'noreply@affiliateai.com'));
                
                if (isset($emailSettings['enable_notifications'])) {
                    Setting::setValue('email_enable_notifications', $emailSettings['enable_notifications'] ? 'true' : 'false');
                }
                
                if (isset($emailSettings['smtp_host'])) {
                    Setting::setValue('email_smtp_host', $emailSettings['smtp_host'] ?? '');
                }
                
                if (isset($emailSettings['smtp_port'])) {
                    Setting::setValue('email_smtp_port', $emailSettings['smtp_port'] ? (string) $emailSettings['smtp_port'] : '587');
                }
                
                if (isset($emailSettings['smtp_username'])) {
                    Setting::setValue('email_smtp_username', $emailSettings['smtp_username'] ?? '');
                }
                
                if (isset($emailSettings['smtp_password'])) {
                    Setting::setValue('email_smtp_password', $emailSettings['smtp_password'] ?? '');
                }
            }

            // Save commission defaults
            if (isset($validated['commission_defaults'])) {
                $commissionDefaults = $validated['commission_defaults'];
                
                if (isset($commissionDefaults['default_rate'])) {
                    Setting::setValue('commission_default_rate', (string) $commissionDefaults['default_rate']);
                }
                
                if (isset($commissionDefaults['minimum_payout'])) {
                    Setting::setValue('commission_minimum_payout', (string) $commissionDefaults['minimum_payout']);
                }
                
                if (isset($commissionDefaults['payout_schedule'])) {
                    Setting::setValue('commission_payout_schedule', $commissionDefaults['payout_schedule']);
                }
            }

            // Save general settings
            if (isset($validated['general'])) {
                $general = $validated['general'];
                
                if (isset($general['site_url'])) {
                    Setting::setValue('general_site_url', $general['site_url'] ?? '');
                }
                
                if (isset($general['maintenance_mode'])) {
                    Setting::setValue('general_maintenance_mode', $general['maintenance_mode'] ? 'true' : 'false');
                }
                
                if (isset($general['allow_registration'])) {
                    Setting::setValue('general_allow_registration', $general['allow_registration'] ? 'true' : 'false');
                }
            }

            // Return updated settings
            $updatedSettings = [
                'app_name' => Setting::getValue('app_name', 'Affiliate AI SaaS'),
                'logo_url' => Setting::getValue('logo_url', ''),
                'email_settings' => [
                    'system_email' => Setting::getValue('email_system_email', 'noreply@affiliateai.com'),
                    'enable_notifications' => Setting::getValue('email_enable_notifications', 'true') === 'true',
                    'smtp_host' => Setting::getValue('email_smtp_host', ''),
                    'smtp_port' => (int) Setting::getValue('email_smtp_port', '587'),
                    'smtp_username' => Setting::getValue('email_smtp_username', ''),
                    'smtp_password' => Setting::getValue('email_smtp_password', ''),
                ],
                'commission_defaults' => [
                    'default_rate' => (float) Setting::getValue('commission_default_rate', '10'),
                    'minimum_payout' => (float) Setting::getValue('commission_minimum_payout', '50'),
                    'payout_schedule' => Setting::getValue('commission_payout_schedule', 'monthly'),
                ],
                'general' => [
                    'site_url' => Setting::getValue('general_site_url', ''),
                    'maintenance_mode' => Setting::getValue('general_maintenance_mode', 'false') === 'true',
                    'allow_registration' => Setting::getValue('general_allow_registration', 'true') === 'true',
                ],
            ];

            return response()->json([
                'message' => 'Settings updated successfully',
                'settings' => $updatedSettings,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update admin password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'current_password' => ['required'],
        ]);

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    // ========== LANDING PAGES MANAGEMENT ==========

    public function landingPages(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $landingPages = LandingPage::with(['user', 'project'])
            ->latest()
            ->get()
            ->map(function ($page) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'subdomain' => $page->subdomain,
                    'domain' => $page->domain,
                    'status' => $page->status,
                    'expires_at' => $page->expires_at?->toISOString(),
                    'credit_cost' => $page->credit_cost,
                    'views' => $page->views,
                    'conversions' => $page->conversions,
                    'type' => $page->type,
                    'user' => [
                        'id' => $page->user->id,
                        'name' => $page->user->name,
                        'email' => $page->user->email,
                    ],
                    'project' => $page->project ? [
                        'id' => $page->project->id,
                        'name' => $page->project->name,
                    ] : null,
                    'created_at' => $page->created_at->toISOString(),
                ];
            });

        return response()->json($landingPages);
    }

    public function updateLandingPageCreditCost(Request $request, LandingPage $landingPage): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'credit_cost' => ['required', 'integer', 'min:1'],
        ]);

        $landingPage->update([
            'credit_cost' => $validated['credit_cost'],
        ]);

        return response()->json([
            'message' => 'Landing page credit cost updated successfully',
            'landing_page' => [
                'id' => $landingPage->id,
                'title' => $landingPage->title,
                'credit_cost' => $landingPage->credit_cost,
            ],
        ]);
    }

    public function deleteLandingPage(Request $request, LandingPage $landingPage): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Undeploy if active
        if ($landingPage->status === 'active') {
            app(\App\Services\LandingPageService::class)->undeploy($landingPage);
        }

        $landingPage->delete();

        return response()->json(['message' => 'Landing page deleted successfully']);
    }

    public function landingPagesLeaderboard(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $type = $request->get('type', 'ctr'); // 'ctr', 'conversions', 'views'
        $limit = (int) $request->get('limit', 20);

        $pages = LandingPage::with('user')
            ->where('status', '!=', 'draft')
            ->where('views', '>', 0)
            ->get();

        $leaderboard = $pages->groupBy('user_id')
            ->map(function ($userPages) {
                $totalViews = $userPages->sum('views');
                $totalConversions = $userPages->sum('conversions');
                $avgCtr = $totalViews > 0 ? round(($totalConversions / $totalViews) * 100, 2) : 0;
                
                return [
                    'user' => [
                        'id' => $userPages->first()->user->id,
                        'name' => $userPages->first()->user->name,
                        'email' => $userPages->first()->user->email,
                    ],
                    'total_views' => $totalViews,
                    'total_conversions' => $totalConversions,
                    'avg_ctr' => $avgCtr,
                    'page_count' => $userPages->count(),
                ];
            })
            ->values();

        // Sort by type
        if ($type === 'ctr') {
            $leaderboard = $leaderboard->sortByDesc('avg_ctr');
        } elseif ($type === 'conversions') {
            $leaderboard = $leaderboard->sortByDesc('total_conversions');
        } else {
            $leaderboard = $leaderboard->sortByDesc('total_views');
        }

        return response()->json([
            'type' => $type,
            'leaderboard' => $leaderboard->take($limit)->values(),
        ]);
    }

    public function landingPagesStats(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $totalPages = LandingPage::count();
        $activePages = LandingPage::where('status', 'active')->count();
        $expiredPages = LandingPage::where('status', 'expired')->count();
        $draftPages = LandingPage::where('status', 'draft')->count();
        
        $totalViews = LandingPage::sum('views');
        $totalConversions = LandingPage::sum('conversions');
        $avgCtr = $totalViews > 0 ? round(($totalConversions / $totalViews) * 100, 2) : 0;

        // Most viewed pages
        $topPages = LandingPage::with('user')
            ->where('views', '>', 0)
            ->orderByDesc('views')
            ->take(5)
            ->get()
            ->map(function ($page) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'subdomain' => $page->subdomain,
                    'views' => $page->views,
                    'conversions' => $page->conversions,
                    'ctr' => $page->ctr,
                    'user' => [
                        'id' => $page->user->id,
                        'name' => $page->user->name,
                    ],
                ];
            });

        return response()->json([
            'summary' => [
                'total_pages' => $totalPages,
                'active_pages' => $activePages,
                'expired_pages' => $expiredPages,
                'draft_pages' => $draftPages,
            ],
            'analytics' => [
                'total_views' => $totalViews,
                'total_conversions' => $totalConversions,
                'avg_ctr' => $avgCtr,
            ],
            'top_pages' => $topPages,
        ]);
    }

    public function billingLogs(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $type = $request->get('type'); // Filter by type
        $status = $request->get('status'); // Filter by status
        $pageId = $request->get('landing_page_id'); // Filter by landing page

        $query = BillingLog::with(['user', 'landingPage'])
            ->latest();

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($pageId) {
            $query->where('landing_page_id', $pageId);
        }

        $logs = $query->paginate(50);

        return response()->json([
            'data' => $logs->items()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ],
                    'landing_page' => $log->landingPage ? [
                        'id' => $log->landingPage->id,
                        'title' => $log->landingPage->title,
                        'subdomain' => $log->landingPage->subdomain,
                    ] : null,
                    'type' => $log->type,
                    'credits_deducted' => $log->credits_deducted,
                    'status' => $log->status,
                    'message' => $log->message,
                    'created_at' => $log->created_at->toISOString(),
                ];
            }),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function billingStats(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        // Monthly renewal revenue (credits)
        $monthlyRevenue = BillingLog::where('status', 'success')
            ->where('type', 'auto_renew')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('credits_deducted');

        // Total credits earned from all billing
        $totalCreditsEarned = BillingLog::where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('credits_deducted');

        // Failed renewals count
        $failedRenewals = BillingLog::where('type', 'auto_renew')
            ->where('status', 'failed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Successful renewals count
        $successfulRenewals = BillingLog::where('type', 'auto_renew')
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Renewal success rate
        $totalRenewals = $failedRenewals + $successfulRenewals;
        $successRate = $totalRenewals > 0 ? round(($successfulRenewals / $totalRenewals) * 100, 2) : 0;

        // Top earners (users consuming most credits)
        $topEarners = BillingLog::where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('user_id, SUM(credits_deducted) as total_credits')
            ->groupBy('user_id')
            ->orderByDesc('total_credits')
            ->take(10)
            ->get()
            ->map(function ($log) {
                $user = User::find($log->user_id);
                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'total_credits' => $log->total_credits,
                ];
            });

        // Daily credits trend
        $dailyTrend = BillingLog::where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(credits_deducted) as credits')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'credits' => (int) $item->credits,
                ];
            });

        // Active vs expired trend
        $activeVsExpired = LandingPage::selectRaw('DATE(created_at) as date, 
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'active' => (int) $item->active,
                    'expired' => (int) $item->expired,
                ];
            });

        return response()->json([
            'summary' => [
                'monthly_revenue' => $monthlyRevenue,
                'total_credits_earned' => $totalCreditsEarned,
                'failed_renewals' => $failedRenewals,
                'successful_renewals' => $successfulRenewals,
                'success_rate' => $successRate,
            ],
            'top_earners' => $topEarners,
            'daily_trend' => $dailyTrend,
            'active_vs_expired_trend' => $activeVsExpired,
        ]);
    }

    public function retryFailedRenewal(Request $request, BillingLog $billingLog): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($billingLog->status !== 'failed' || $billingLog->type !== 'auto_renew') {
            return response()->json([
                'message' => 'This billing log cannot be retried',
            ], 422);
        }

        $landingPage = $billingLog->landingPage;
        if (!$landingPage) {
            return response()->json([
                'message' => 'Landing page not found',
            ], 404);
        }

        $user = $billingLog->user;
        $creditCost = $landingPage->credit_cost;

        // Check if user has enough credits now
        if (!app(CreditService::class)->hasEnoughCredits($user, $creditCost)) {
            return response()->json([
                'message' => 'User still does not have enough credits',
            ], 422);
        }

        // Process renewal
        DB::transaction(function () use ($landingPage, $user, $creditCost, $billingLog) {
            $renewalInterval = config('landing_pages.renewal.default_interval', 30);
            $now = now();

            // Deduct credits
            app(CreditService::class)->deductCredits(
                $user,
                $creditCost,
                "Retried renewal for landing page: {$landingPage->title}"
            );

            // Update landing page
            $expiresAt = $now->copy()->addDays($renewalInterval);
            $landingPage->update([
                'status' => 'active',
                'expires_at' => $expiresAt,
                'next_renewal_date' => $expiresAt,
                'last_renewal_date' => $now,
            ]);

            // Update billing log
            $billingLog->update([
                'status' => 'success',
                'credits_deducted' => $creditCost,
                'message' => "Renewal retried successfully. Extended by {$renewalInterval} days.",
            ]);

            // Create new billing log for successful retry
            BillingLog::create([
                'user_id' => $user->id,
                'landing_page_id' => $landingPage->id,
                'type' => 'auto_renew',
                'credits_deducted' => $creditCost,
                'status' => 'success',
                'message' => "Renewal retried successfully after user topped up credits.",
            ]);
        });

        return response()->json([
            'message' => 'Renewal retried successfully',
            'landing_page' => [
                'id' => $landingPage->id,
                'title' => $landingPage->title,
                'status' => $landingPage->status,
                'expires_at' => $landingPage->expires_at?->toISOString(),
            ],
        ]);
    }
}
