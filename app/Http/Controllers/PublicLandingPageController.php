<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\LandingPageAnalytic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\LandingPageService;

class PublicLandingPageController extends Controller
{
    protected LandingPageService $landingPageService;

    public function __construct(LandingPageService $landingPageService)
    {
        $this->landingPageService = $landingPageService;
    }

    /**
     * Show public landing page by subdomain
     */
    public function show(Request $request, string $subdomain)
    {
        $landingPage = LandingPage::where('subdomain', $subdomain)->first();

        if (!$landingPage) {
            abort(404, 'Landing page not found');
        }

        // Check if page is active and not expired
        if ($landingPage->status !== 'active') {
            abort(404, 'Landing page not available');
        }

        if ($landingPage->isExpired()) {
            // Auto-deactivate expired pages
            $landingPage->update(['status' => 'expired']);
            abort(404, 'Landing page has expired');
        }

        // Track view (async, don't block response)
        dispatch(function () use ($landingPage, $request) {
            $this->trackView($landingPage, $request);
        });

        // Get HTML content from storage or generate from model
        $filename = "landing-pages/{$subdomain}/index.html";
        
        if (Storage::disk('public')->exists($filename)) {
            $htmlContent = Storage::disk('public')->get($filename);
            
            // Inject tracking script if needed
            $htmlContent = $this->injectTrackingScript($htmlContent, $landingPage);
            
            return response($htmlContent)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }

        // Fallback: generate HTML from model content
        $htmlContent = $this->landingPageService->generateHtml($landingPage);
        
        // Inject tracking script
        $htmlContent = $this->injectTrackingScript($htmlContent, $landingPage);
        
        return response($htmlContent)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Track a view for the landing page
     */
    protected function trackView(LandingPage $landingPage, Request $request): void
    {
        $ipAddress = $request->ip();
        
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
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ]);

            // Increment views counter
            $landingPage->increment('views');
        }
    }

    /**
     * Inject tracking script into HTML content
     */
    protected function injectTrackingScript(string $htmlContent, LandingPage $landingPage): string
    {
        $trackingUrl = url('/api/landing-pages/' . $landingPage->id . '/track-view');
        $conversionUrl = url('/api/landing-pages/' . $landingPage->id . '/track-conversion');
        $affiliateLink = htmlspecialchars($landingPage->affiliate_link ?? '', ENT_QUOTES, 'UTF-8');
        
        $trackingScript = <<<SCRIPT
<script>
(function() {
    // Track page view via API
    fetch('{$trackingUrl}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
    }).catch(function(error) {
        console.log('Tracking error:', error);
    });

    // Track clicks on affiliate links
    document.addEventListener('click', function(e) {
        var target = e.target.closest('a[href*="{$affiliateLink}"]');
        if (target) {
            fetch('{$conversionUrl}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            }).catch(function(error) {
                console.log('Conversion tracking error:', error);
            });
        }
    });
})();
</script>
SCRIPT;

        // Insert before closing </body> tag
        if (stripos($htmlContent, '</body>') !== false) {
            $htmlContent = preg_replace('/<\/body>/i', $trackingScript . "\n</body>", $htmlContent);
        } else {
            // If no body tag, append at the end
            $htmlContent .= $trackingScript;
        }

        return $htmlContent;
    }
}

