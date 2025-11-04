<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LandingPageService
{
    protected string $baseDomain;

    public function __construct()
    {
        $this->baseDomain = config('app.landing_page_domain', 'affnet.app');
    }

    /**
     * Generate a unique subdomain for a user
     */
    public function generateSubdomain(User $user, string $title): string
    {
        $baseSubdomain = Str::slug($user->name . '-' . $title);
        $baseSubdomain = strtolower($baseSubdomain);
        $baseSubdomain = preg_replace('/[^a-z0-9-]/', '', $baseSubdomain);
        
        // Ensure it's not too long (subdomain max 63 chars, but leave room for uniqueness)
        $baseSubdomain = substr($baseSubdomain, 0, 40);
        
        $subdomain = $baseSubdomain;
        $counter = 1;
        
        // Ensure uniqueness
        while (LandingPage::where('subdomain', $subdomain)->exists()) {
            $subdomain = $baseSubdomain . '-' . $counter;
            $counter++;
        }
        
        return $subdomain;
    }

    /**
     * Deploy a landing page (create static HTML file)
     */
    public function deploy(LandingPage $landingPage): bool
    {
        try {
            $htmlContent = $this->generateHtml($landingPage);
            
            // Store in public/landing-pages directory
            $filename = "landing-pages/{$landingPage->subdomain}/index.html";
            
            Storage::disk('public')->put($filename, $htmlContent);
            
            // In production, you might want to sync to a CDN or static hosting
            // For now, we'll just store it locally
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to deploy landing page', [
                'landing_page_id' => $landingPage->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Undeploy a landing page (remove static files)
     */
    public function undeploy(LandingPage $landingPage): bool
    {
        try {
            $directory = "landing-pages/{$landingPage->subdomain}";
            
            if (Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->deleteDirectory($directory);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to undeploy landing page', [
                'landing_page_id' => $landingPage->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate HTML content for the landing page
     */
    protected function generateHtml(LandingPage $landingPage): string
    {
        $content = $landingPage->content ?? '';
        
        // If content is empty, use a default template
        if (empty($content)) {
            $content = $this->getDefaultTemplate();
        }
        
        // Wrap in a full HTML document
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$landingPage->title}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .content {
            padding: 40px 20px;
        }
        .cta-button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        .cta-button:hover {
            background: #5568d3;
        }
        footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>{$landingPage->title}</h1>
        </div>
    </div>
    <div class="content">
        <div class="container">
            {$content}
        </div>
    </div>
    <footer>
        <div class="container">
            <p>&copy; " . date('Y') . " {$landingPage->title}. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
HTML;
    }

    /**
     * Get default template content
     */
    protected function getDefaultTemplate(): string
    {
        return <<<HTML
<h2>Welcome to Our Landing Page</h2>
<p>This is a default landing page template. Edit your landing page content to customize it.</p>
<a href="#" class="cta-button">Get Started</a>
HTML;
    }

    /**
     * Get full URL for a landing page
     */
    public function getUrl(LandingPage $landingPage): string
    {
        if ($landingPage->domain) {
            return 'https://' . $landingPage->domain;
        }
        
        // Use /lp/{slug} route (shorter URL)
        $baseUrl = config('app.url', 'http://localhost');
        return $baseUrl . '/lp/' . $landingPage->subdomain;
    }

    /**
     * Track a view for a landing page
     */
    public function trackView(LandingPage $landingPage): void
    {
        $landingPage->increment('views');
    }

    /**
     * Track a conversion for a landing page
     */
    public function trackConversion(LandingPage $landingPage): void
    {
        $landingPage->increment('conversions');
    }
}

